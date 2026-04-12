<?php

declare(strict_types=1);

final class EmitDrawio
{
    public static function irToDrawio(array $ir, array $HY): string
    {
        $isC3 = (($ir['level'] ?? null) === 'C3');
        if (!$isC3 && isset($ir['components']) && is_array($ir['components'])) {
            foreach ($ir['components'] as $c) {
                if (($c['boundaryId'] ?? null) !== null) { $isC3 = true; break; }
            }
        }
        if ($isC3) return self::emitC3($ir, $HY);
        if (($ir['level'] ?? null) === 'C2') return self::emitC2($ir, $HY);
        return self::emitC1($ir, $HY);
    }

    private static function escapeAttr(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private static function nid(string $plantId): string
    {
        return 'n-' . $plantId;
    }

    /** @return array{tech:string,body:string} */
    private static function splitDbDescription(?string $desc): array
    {
        $s = (string)$desc;
        $sep = ' - ';
        $i = strpos($s, $sep);
        if ($i !== false && $i > 0) {
            return ['tech' => trim(substr($s, 0, $i)), 'body' => trim(substr($s, $i + strlen($sep)))];
        }
        return ['tech' => 'Database', 'body' => $s];
    }

    private static function mxfile(string $diagramName, string $diagramId, int $pageW, int $pageH, string $body): string
    {
        return
            "<mxfile host=\"app.diagrams.net\">\n" .
            "  <diagram name=\"" . self::escapeAttr($diagramName) . "\" id=\"{$diagramId}\">\n" .
            "    <mxGraphModel dx=\"2600\" dy=\"900\" grid=\"1\" gridSize=\"10\" guides=\"1\" tooltips=\"1\" connect=\"1\" arrows=\"1\" fold=\"1\" page=\"1\" pageScale=\"1\" pageWidth=\"{$pageW}\" pageHeight=\"{$pageH}\" math=\"0\" shadow=\"0\">\n" .
            "      <root>\n" .
            "        <mxCell id=\"0\" />\n" .
            "        <mxCell id=\"1\" parent=\"0\" />\n" .
            $body .
            "      </root>\n" .
            "    </mxGraphModel>\n" .
            "  </diagram>\n" .
            "</mxfile>\n";
    }

    private static function vo(array $HY, string $templateKey, string $id, string $extraAttrs, float $x, float $y, float $w, float $h, string $parent = '1'): string
    {
        if (!isset($HY[$templateKey])) {
            throw new RuntimeException('Missing EasyC4 shape template: ' . $templateKey);
        }
        $t = $HY[$templateKey];

        if (!empty($t['cellValue'])) {
            $value = (string)$t['cellValue'];
            $style = (string)$t['style'];
            return
                "        <mxCell id=\"{$id}\" parent=\"{$parent}\" value=\"{$value}\" style=\"{$style}\" vertex=\"1\">\n" .
                "          <mxGeometry x=\"{$x}\" y=\"{$y}\" width=\"{$w}\" height=\"{$h}\" as=\"geometry\" />\n" .
                "        </mxCell>\n";
        }

        $attrs = (string)($t['objectOpen'] ?? '');
        $attrs = preg_replace('/\s*id="2"\s*$/', '', $attrs) ?? $attrs;
        $attrs = preg_replace('/\s*id="2"\s*/', ' ', $attrs) ?? $attrs;

        $attrs = preg_replace('/A_NAME="[^"]*"\s*/', '', $attrs) ?? $attrs;
        $attrs = preg_replace('/B_DESCRIPTION="[^"]*"\s*/', '', $attrs) ?? $attrs;
        $attrs = preg_replace('/B_TECHNOLOGY="[^"]*"\s*/', '', $attrs) ?? $attrs;
        $attrs = preg_replace('/C_DESCRIPTION="[^"]*"\s*/', '', $attrs) ?? $attrs;
        $attrs = preg_replace('/A_C1_DIAGRAM_NAME="[^"]*"\s*/', '', $attrs) ?? $attrs;
        $attrs = preg_replace('/B_C1_DIAGRAM_DESCRIPTION="[^"]*"\s*/', '', $attrs) ?? $attrs;
        $attrs = preg_replace('/A_C3_DIAGRAM_NAME="[^"]*"\s*/', '', $attrs) ?? $attrs;
        $attrs = preg_replace('/B_C3_DIAGRAM_DESCRIPTION="[^"]*"\s*/', '', $attrs) ?? $attrs;
        $attrs = preg_replace('/A_C2_DIAGRAM_NAME="[^"]*"\s*/', '', $attrs) ?? $attrs;
        $attrs = preg_replace('/B_C2_DIAGRAM_DESCRIPTION="[^"]*"\s*/', '', $attrs) ?? $attrs;
        $attrs = preg_replace('/A_DESCRIPTION="[^"]*"\s*/', '', $attrs) ?? $attrs;
        $attrs = preg_replace('/B_INSTANCES_NUMBER="[^"]*"\s*/', '', $attrs) ?? $attrs;
        $attrs = preg_replace('/C_TECHNOLOGY="[^"]*"\s*/', '', $attrs) ?? $attrs;
        $attrs = preg_replace('/placeholders="1"\s*/', '', $attrs) ?? $attrs;
        $attrs = preg_replace('/\s+/', ' ', $attrs) ?? $attrs;
        $attrs = trim($attrs);

        $merged = trim($attrs . ' ' . $extraAttrs . ' placeholders="1" id="' . $id . '"');
        $style = (string)$t['style'];
        return
            "        <object {$merged}>\n" .
            "          <mxCell parent=\"{$parent}\" style=\"{$style}\" vertex=\"1\">\n" .
            "            <mxGeometry x=\"{$x}\" y=\"{$y}\" width=\"{$w}\" height=\"{$h}\" as=\"geometry\" />\n" .
            "          </mxCell>\n" .
            "        </object>\n";
    }

    private static function eo(array $HY, string $templateKey, string $id, string $extraAttrs, string $source, string $target): string
    {
        if (!isset($HY[$templateKey])) {
            throw new RuntimeException('Missing EasyC4 shape template: ' . $templateKey);
        }
        $t = $HY[$templateKey];

        $attrs = (string)($t['objectOpen'] ?? '');
        $attrs = preg_replace('/\s*id="2"\s*$/', '', $attrs) ?? $attrs;
        $attrs = preg_replace('/\s*id="2"\s*/', ' ', $attrs) ?? $attrs;

        $attrs = preg_replace('/A_DESCRIPTION="[^"]*"\s*/', '', $attrs) ?? $attrs;
        $attrs = preg_replace('/B_TECHNOLOGY="[^"]*"\s*/', '', $attrs) ?? $attrs;
        $attrs = preg_replace('/placeholders="1"\s*/', '', $attrs) ?? $attrs;
        $attrs = preg_replace('/\s+/', ' ', $attrs) ?? $attrs;
        $attrs = trim($attrs);

        $merged = trim($attrs . ' ' . $extraAttrs . ' placeholders="1" id="' . $id . '"');
        $style = (string)$t['style'];

        return
            "        <object {$merged}>\n" .
            "          <mxCell edge=\"1\" parent=\"1\" source=\"{$source}\" target=\"{$target}\" style=\"{$style}\">\n" .
            "            <mxGeometry relative=\"1\" as=\"geometry\" />\n" .
            "          </mxCell>\n" .
            "        </object>\n";
    }

    private static function emitC1(array $ir, array $HY): string
    {
        $SS = $HY['Software System'];
        $ESS = $HY['External Software System'];
        $DC = $HY['Data Container'];
        $LEG = $HY['Legend'] ?? null;
        $T1 = $HY['System Context diagram title'];
        $CSB = $HY['Container scope boundary'] ?? null;

        $title = (string)($ir['title'] ?? '');

        /** @var array<string, array{x:float,y:float,w:float,h:float}> */
        $boxes = [];
        $putBox = static function (string $id, float $x, float $y, float $w, float $h) use (&$boxes): void {
            if ($id === '') return;
            $boxes[$id] = ['x' => $x, 'y' => $y, 'w' => $w, 'h' => $h];
        };

        $titleXml = self::vo(
            $HY,
            'System Context diagram title',
            'tit-ctx',
            'A_C1_DIAGRAM_NAME="' . self::escapeAttr($title) . '" B_C1_DIAGRAM_DESCRIPTION=""',
            -40,
            220,
            ((int)($T1['w'] ?? 240)) * 2,
            (int)$T1['h']
        );

        $shapeXml = '';

        $gap = 5;
        $pw = (int)($HY['Person']['w'] ?? 180);
        $x0 = -30;
        foreach (($ir['persons'] ?? []) as $i => $p) {
            $key = !empty($p['external']) ? 'External Person' : 'Person';
            $dim = $HY[$key];
            $px = $x0 + $i * ($pw + $gap);
            $py = 320.0;
            $pw0 = (int)$dim['w'];
            $ph0 = (int)$dim['h'];
            $putBox((string)$p['id'], $px, $py, (float)$pw0, (float)$ph0);
            $shapeXml .= self::vo(
                $HY,
                $key,
                self::nid((string)$p['id']),
                'A_NAME="' . self::escapeAttr((string)$p['name']) . '" B_DESCRIPTION="' . self::escapeAttr((string)$p['description']) . '"',
                $px,
                $py,
                $pw0,
                $ph0
            );
        }

        $n = count($ir['persons'] ?? []);
        $bandRight = $n > 0 ? $x0 + $n * $pw + ($n - 1) * $gap : $x0 + $pw;
        $centerX = $n > 0 ? ($x0 + $bandRight) / 2 - ((int)$SS['w']) / 2 : 200;
        foreach (($ir['systems'] ?? []) as $s) {
            $sx = $centerX;
            $sy = 530.0;
            $sw = (int)$SS['w'];
            $sh = (int)$SS['h'];
            $putBox((string)$s['id'], $sx, $sy, (float)$sw, (float)$sh);
            $shapeXml .= self::vo(
                $HY,
                'Software System',
                self::nid((string)$s['id']),
                'A_NAME="' . self::escapeAttr((string)$s['name']) . '" B_DESCRIPTION="' . self::escapeAttr((string)$s['description']) . '"',
                $sx,
                $sy,
                $sw,
                $sh
            );
        }

        $bottomGap = 30;
        $bx = -200;
        $bottomY = 960;
        foreach (($ir['systemsExt'] ?? []) as $item) {
            $ew = (int)$ESS['w'];
            $eh = (int)$ESS['h'];
            $putBox((string)$item['id'], $bx, $bottomY, (float)$ew, (float)$eh);
            $shapeXml .= self::vo(
                $HY,
                'External Software System',
                self::nid((string)$item['id']),
                'A_NAME="' . self::escapeAttr((string)$item['name']) . '" B_DESCRIPTION="' . self::escapeAttr((string)$item['description']) . '"',
                $bx,
                $bottomY,
                $ew,
                $eh
            );
            $bx += (int)$ESS['w'] + $bottomGap;
        }

        foreach (($ir['databases'] ?? []) as $db) {
            $split = self::splitDbDescription((string)($db['description'] ?? ''));
            $dw = (int)$DC['w'];
            $dh = (int)$DC['h'];
            $putBox((string)$db['id'], $bx, $bottomY - 10, (float)$dw, (float)$dh);
            $shapeXml .= self::vo(
                $HY,
                'Data Container',
                self::nid((string)$db['id']),
                'A_NAME="' . self::escapeAttr((string)$db['name']) . '" B_TECHNOLOGY="' . self::escapeAttr($split['tech']) . '" C_DESCRIPTION="' . self::escapeAttr($split['body']) . '"',
                $bx,
                $bottomY - 10,
                $dw,
                $dh
            );
            $bx += (int)$DC['w'] + $bottomGap;
        }

        // Mermaid C4Context: nested boundaries (Enterprise/Boundary/System) — draw behind elements
        $behind = '';
        if ($CSB) {
            $boundaryBoxes = [];
            foreach (($ir['boundaries'] ?? []) as $b) {
                if (!is_array($b)) continue;
                $bid = (string)($b['id'] ?? '');
                if ($bid === '') continue;

                $memberIds = $b['memberIds'] ?? [];
                if (!is_array($memberIds) || count($memberIds) === 0) continue;

                $minX = INF;
                $minY = INF;
                $maxX = -INF;
                $maxY = -INF;
                $seen = [];
                foreach ($memberIds as $mid) {
                    if (!is_string($mid) || $mid === '') continue;
                    if (isset($seen[$mid])) continue;
                    $seen[$mid] = true;
                    if (!isset($boxes[$mid])) continue;
                    $bb = $boxes[$mid];
                    $minX = min($minX, $bb['x']);
                    $minY = min($minY, $bb['y']);
                    $maxX = max($maxX, $bb['x'] + $bb['w']);
                    $maxY = max($maxY, $bb['y'] + $bb['h']);
                }
                if (!is_finite($minX) || !is_finite($minY) || !is_finite($maxX) || !is_finite($maxY)) {
                    continue;
                }

                $pad = 26.0;
                $bx0 = $minX - $pad;
                $by0 = $minY - $pad;
                $bw = ($maxX - $minX) + 2 * $pad;
                $bh = ($maxY - $minY) + 2 * $pad;

                $srcKind = (string)($b['sourceKind'] ?? '');
                $tpl = 'Container scope boundary';
                if ($srcKind === 'System_Boundary') {
                    $tpl = 'System scope boundary';
                } elseif ($srcKind === 'Container_Boundary') {
                    $tpl = 'Container scope boundary';
                } elseif ($srcKind === 'Enterprise_Boundary' || $srcKind === 'Boundary') {
                    $tpl = 'Container scope boundary';
                }

                $boundaryBoxes[] = [
                    'area' => $bw * $bh,
                    'xml' => self::vo(
                        $HY,
                        $tpl,
                        'bdry-mmd-' . $bid,
                        'A_NAME="' . self::escapeAttr((string)($b['name'] ?? $bid)) . '"',
                        $bx0,
                        $by0,
                        (int)max(120, (int)round($bw)),
                        (int)max(120, (int)round($bh))
                    ),
                ];
            }

            usort($boundaryBoxes, static fn($a, $b) => ($b['area'] <=> $a['area']));
            foreach ($boundaryBoxes as $row) {
                $behind .= $row['xml'];
            }
        }

        $edgeXml = '';
        $ei = 0;
        foreach (($ir['rels'] ?? []) as $r) {
            $id = 'e-' . $ei++;
            if (!empty($r['technology'])) {
                $edgeXml .= self::eo(
                    $HY,
                    'Relationship with description and technology',
                    $id,
                    'A_DESCRIPTION="' . self::escapeAttr((string)$r['description']) . '" B_TECHNOLOGY="' . self::escapeAttr((string)$r['technology']) . '"',
                    self::nid((string)$r['from']),
                    self::nid((string)$r['to'])
                );
            } else {
                $edgeXml .= self::eo(
                    $HY,
                    'Relationship with description',
                    $id,
                    'A_DESCRIPTION="' . self::escapeAttr((string)$r['description']) . '"',
                    self::nid((string)$r['from']),
                    self::nid((string)$r['to'])
                );
            }
        }

        $legendXml = '';
        if ($LEG) {
            $legendXml = self::vo(
                $HY,
                'Legend',
                'legend',
                '',
                1100 - (int)$LEG['w'] - 40,
                1280 - (int)$LEG['h'] - 40,
                (int)$LEG['w'],
                (int)$LEG['h']
            );
        }

        $body = $titleXml . $behind . $shapeXml . $edgeXml . $legendXml;
        return self::mxfile($title !== '' ? $title : 'C4 Context', 'c4-context-auto', 1100, 1280, $body);
    }

    /** @param 'container'|'db'|'queue' $itemKind */
    private static function c2ItemTemplateKey(array $HY, string $itemKind): string
    {
        if ($itemKind === 'db') {
            return 'Data Container';
        }
        if ($itemKind === 'queue' && isset($HY['Message Bus Container'])) {
            return 'Message Bus Container';
        }
        return 'Container';
    }

    /** @param 'container'|'db'|'queue' $itemKind @return array{0:int,1:int} */
    private static function c2ItemWh(array $HY, string $itemKind): array
    {
        $key = self::c2ItemTemplateKey($HY, $itemKind);
        $t = $HY[$key];
        return [(int)$t['w'], (int)$t['h']];
    }

    /**
     * Keep the first row per element id. Deployment-style PlantUML can surface the same
     * Container/ContainerDb id twice in IR when nesting is flattened inconsistently.
     *
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    private static function dedupeC2ItemsById(array $items): array
    {
        $seen = [];
        $out = [];
        foreach ($items as $it) {
            if (!is_array($it)) {
                continue;
            }
            $id = (string)($it['id'] ?? '');
            if ($id === '') {
                $out[] = $it;
                continue;
            }
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $out[] = $it;
        }
        return $out;
    }

    /** @param array<string, mixed> $node */
    private static function deploymentNodePlaceholderAttrs(array $node): string
    {
        $name = trim((string)($node['name'] ?? ''));
        $tech = trim((string)($node['technology'] ?? ''));
        $desc = trim((string)($node['description'] ?? ''));
        $propParts = [];
        foreach ($node['properties'] ?? [] as $pv) {
            if (!is_array($pv)) {
                continue;
            }
            $k = trim((string)($pv['key'] ?? ''));
            $v = trim((string)($pv['value'] ?? ''));
            if ($k !== '' || $v !== '') {
                $propParts[] = $k . ': ' . $v;
            }
        }
        if (count($propParts) > 0) {
            $name .= ' — ' . implode(' · ', $propParts);
        }
        $cTech = $tech;
        if ($desc !== '') {
            $cTech = $tech !== '' ? $tech . ' · ' . $desc : $desc;
        }
        return 'A_NAME="' . self::escapeAttr($name) . '" B_INSTANCES_NUMBER="' . self::escapeAttr('1') . '" C_TECHNOLOGY="' . self::escapeAttr($cTech) . '"';
    }

    /** @return array<string, mixed>|null */
    private static function irFindContainer(array $ir, string $id): ?array
    {
        foreach (($ir['containers'] ?? []) as $c) {
            if (is_array($c) && (($c['id'] ?? '') === $id)) {
                return $c;
            }
        }
        return null;
    }

    /** @return array<string, mixed>|null */
    private static function irFindContainerDb(array $ir, string $id): ?array
    {
        foreach (($ir['containerDbs'] ?? []) as $c) {
            if (is_array($c) && (($c['id'] ?? '') === $id)) {
                return $c;
            }
        }
        return null;
    }

    /**
     * @return array{0: float, 1: float, 2: string}
     */
    private static function layoutDeploymentSubtree(array $ir, array $HY, string $nodeId, float $colW, float $originX, float $originY): array
    {
        $nodes = $ir['deployment']['nodes'] ?? [];
        $node = $nodes[$nodeId] ?? null;
        if (!is_array($node)) {
            return [0.0, 0.0, ''];
        }

        $pad = 24.0;
        $vGap = 18.0;
        $header = 52.0;

        $cx = $originX + $pad;
        $cy = $originY + $header;
        $maxRight = $cx;

        $innerXml = '';

        foreach ($node['childOrder'] ?? [] as $cid) {
            if (!is_string($cid) || $cid === '') {
                continue;
            }
            [$cw, $ch, $sub] = self::layoutDeploymentSubtree($ir, $HY, $cid, $colW, $cx, $cy);
            if ($sub !== '') {
                $innerXml .= $sub;
            }
            if ($cw > 0 || $ch > 0) {
                $maxRight = max($maxRight, $cx + $cw);
                $cy += $ch + $vGap;
            }
        }

        foreach ($node['containerIds'] ?? [] as $cid) {
            if (!is_string($cid) || $cid === '') {
                continue;
            }
            $c = self::irFindContainer($ir, $cid);
            if ($c === null) {
                continue;
            }
            $wh = [(int)$HY['Container']['w'], (int)$HY['Container']['h']];
            $innerXml .= self::vo(
                $HY,
                'Container',
                self::nid($cid),
                'A_NAME="' . self::escapeAttr((string)$c['name']) . '" B_TECHNOLOGY="' . self::escapeAttr((string)$c['technology']) . '" C_DESCRIPTION="' . self::escapeAttr((string)$c['description']) . '"',
                $cx,
                $cy,
                $wh[0],
                $wh[1],
                '1'
            );
            $maxRight = max($maxRight, $cx + $wh[0]);
            $cy += $wh[1] + $vGap;
        }

        foreach ($node['containerDbIds'] ?? [] as $did) {
            if (!is_string($did) || $did === '') {
                continue;
            }
            $d = self::irFindContainerDb($ir, $did);
            if ($d === null) {
                continue;
            }
            $wh = [(int)$HY['Data Container']['w'], (int)$HY['Data Container']['h']];
            $innerXml .= self::vo(
                $HY,
                'Data Container',
                self::nid($did),
                'A_NAME="' . self::escapeAttr((string)$d['name']) . '" B_TECHNOLOGY="' . self::escapeAttr((string)$d['technology']) . '" C_DESCRIPTION="' . self::escapeAttr((string)$d['description']) . '"',
                $cx,
                $cy,
                $wh[0],
                $wh[1],
                '1'
            );
            $maxRight = max($maxRight, $cx + $wh[0]);
            $cy += $wh[1] + $vGap;
        }

        if ($cy <= $originY + $header) {
            $cy = $originY + $header + 36.0;
        }

        $boxW = max(280.0, $maxRight - $originX + $pad);
        $boxH = $cy - $originY + $pad;

        $depAttrs = self::deploymentNodePlaceholderAttrs($node);
        $boundaryXml = self::vo(
            $HY,
            'Deployment Node',
            'dep-' . $nodeId,
            $depAttrs,
            $originX,
            $originY,
            (int)ceil($boxW),
            (int)ceil($boxH),
            '1'
        );

        return [(float)$boxW, (float)$boxH, $boundaryXml . $innerXml];
    }

    private static function emitC2(array $ir, array $HY): string
    {
        $dep = $ir['deployment'] ?? [];
        if (is_array($dep) && !empty($dep['roots'])) {
            return self::emitC2Deployment($ir, $HY);
        }
        return self::emitC2Flat($ir, $HY);
    }

    private static function emitC2Deployment(array $ir, array $HY): string
    {
        $dep = $ir['deployment'] ?? [];
        $roots = $dep['roots'] ?? [];
        if (!is_array($roots) || count($roots) === 0) {
            return self::emitC2Flat($ir, $HY);
        }

        $CN = $HY['Container'];
        $DC = $HY['Data Container'];
        $ESS = $HY['External Software System'];
        $CT2 = $HY['Containers diagram title'];
        $LEG = $HY['Legend'] ?? null;

        $colW = max((int)$CN['w'], (int)$DC['w']);
        if (isset($HY['Message Bus Container'])) {
            $colW = max($colW, (int)$HY['Message Bus Container']['w']);
        }

        $title = (string)($ir['title'] ?? '');
        $parts = [];
        $parts[] = self::vo(
            $HY,
            'Containers diagram title',
            'tit-c2',
            'A_C2_DIAGRAM_NAME="' . self::escapeAttr($title) . '" B_C2_DIAGRAM_DESCRIPTION=""',
            30,
            15,
            (int)$CT2['w'] * 2,
            (int)$CT2['h']
        );

        $rootX = 60.0;
        $rootY = 70.0;
        $gapBetweenRoots = 40.0;
        $maxBottom = $rootY;
        $maxRight = $rootX;

        foreach ($roots as $rid) {
            if (!is_string($rid) || $rid === '') {
                continue;
            }
            [$w, $h, $chunk] = self::layoutDeploymentSubtree($ir, $HY, $rid, (float)$colW, $rootX, $rootY);
            $parts[] = $chunk;
            $maxBottom = max($maxBottom, $rootY + $h);
            $maxRight = max($maxRight, $rootX + $w);
            $rootX += $w + $gapBetweenRoots;
        }

        foreach (($ir['persons'] ?? []) as $p) {
            $key = !empty($p['external']) ? 'External Person' : 'Person';
            $dim = $HY[$key];
            $parts[] = self::vo(
                $HY,
                $key,
                self::nid((string)$p['id']),
                'A_NAME="' . self::escapeAttr((string)$p['name']) . '" B_DESCRIPTION="' . self::escapeAttr((string)$p['description']) . '"',
                25,
                220,
                (int)$dim['w'],
                (int)$dim['h'],
                '1'
            );
        }

        $ey = 100;
        foreach (($ir['systemsExt'] ?? []) as $item) {
            $parts[] = self::vo(
                $HY,
                'External Software System',
                self::nid((string)$item['id']),
                'A_NAME="' . self::escapeAttr((string)$item['name']) . '" B_DESCRIPTION="' . self::escapeAttr((string)$item['description']) . '"',
                (int)$maxRight + 40,
                $ey,
                (int)$ESS['w'],
                (int)$ESS['h'],
                '1'
            );
            $ey += (int)$ESS['h'] + 40;
        }

        $ei = 0;
        foreach (($ir['rels'] ?? []) as $r) {
            $id = 'e-' . $ei++;
            if (!empty($r['technology'])) {
                $parts[] = self::eo(
                    $HY,
                    'Relationship with description and technology',
                    $id,
                    'A_DESCRIPTION="' . self::escapeAttr((string)$r['description']) . '" B_TECHNOLOGY="' . self::escapeAttr((string)$r['technology']) . '"',
                    self::nid((string)$r['from']),
                    self::nid((string)$r['to'])
                );
            } else {
                $parts[] = self::eo(
                    $HY,
                    'Relationship with description',
                    $id,
                    'A_DESCRIPTION="' . self::escapeAttr((string)$r['description']) . '"',
                    self::nid((string)$r['from']),
                    self::nid((string)$r['to'])
                );
            }
        }

        $pageW = max(1200, (int)ceil($maxRight) + 120, 30 + (int)$CT2['w'] * 2 + 40);
        $pageH = max(900, (int)ceil($maxBottom) + 120);

        if ($LEG) {
            $parts[] = self::vo(
                $HY,
                'Legend',
                'legend',
                '',
                $pageW - (int)$LEG['w'] - 40,
                $pageH - (int)$LEG['h'] - 40,
                (int)$LEG['w'],
                (int)$LEG['h'],
                '1'
            );
        }

        return self::mxfile($title !== '' ? $title : 'C4 Deployment', 'c4-deployment-auto', $pageW, $pageH, implode('', $parts));
    }

    /** Classic C2 layout (no deployment tree). */
    private static function emitC2Flat(array $ir, array $HY): string
    {
        $parts = [];
        $CN = $HY['Container'];
        $DC = $HY['Data Container'];
        $ESS = $HY['External Software System'];
        $CSB = $HY['Container scope boundary'];
        $CT2 = $HY['Containers diagram title'];
        $LEG = $HY['Legend'] ?? null;

        $title = (string)($ir['title'] ?? '');
        $parts[] = self::vo(
            $HY,
            'Containers diagram title',
            'tit-c2',
            'A_C2_DIAGRAM_NAME="' . self::escapeAttr($title) . '" B_C2_DIAGRAM_DESCRIPTION=""',
            30,
            15,
            (int)$CT2['w'] * 2,
            (int)$CT2['h']
        );

        $c2Boundary = null;
        foreach (($ir['boundaries'] ?? []) as $b) {
            if (($b['kind'] ?? null) === 'C2' && !empty($b['items'])) { $c2Boundary = $b; break; }
        }

        $innerItems = (!empty($c2Boundary['items'])) ? $c2Boundary['items'] : ($ir['c2Standalone'] ?? []);
        $innerItems = self::dedupeC2ItemsById($innerItems);

        $pad = 50;
        $vGap = 24;
        $bdryX = 200;
        $bdryY = 60;
        $bdryW = (int)$CSB['w'];
        $bdryH = (int)$CSB['h'];

        $colW = max((int)$CN['w'], (int)$DC['w']);
        if (isset($HY['Message Bus Container'])) {
            $colW = max($colW, (int)$HY['Message Bus Container']['w']);
        }
        if (count($innerItems) > 0) {
            $h = $pad;
            foreach ($innerItems as $it) {
                $ik = (string)($it['kind'] ?? 'container');
                if ($ik !== 'db' && $ik !== 'queue') {
                    $ik = 'container';
                }
                $wh = self::c2ItemWh($HY, $ik);
                $h += $wh[1] + $vGap;
            }
            $h += $pad - $vGap;
            $bdryW = max((int)$CSB['w'], $colW + $pad * 2);
            $bdryH = max((int)$CSB['h'], $h);
        }

        if ($c2Boundary && count($innerItems) > 0) {
            $parts[] = self::vo(
                $HY,
                'Container scope boundary',
                'bdry-c2',
                'A_NAME="' . self::escapeAttr((string)($c2Boundary['name'] ?? '')) . '"',
                $bdryX,
                $bdryY,
                $bdryW,
                $bdryH
            );
        }

        $cy = $bdryY + 55;
        $cx = $bdryX + ($bdryW - $colW) / 2;
        /** @var array<string, true> */
        $innerIds = [];
        foreach ($innerItems as $it) {
            $iid = (string)($it['id'] ?? '');
            if ($iid !== '') {
                $innerIds[$iid] = true;
            }
            $ik = (string)($it['kind'] ?? 'container');
            if ($ik !== 'db' && $ik !== 'queue') {
                $ik = 'container';
            }
            $tpl = self::c2ItemTemplateKey($HY, $ik);
            $wh = self::c2ItemWh($HY, $ik);
            $parts[] = self::vo(
                $HY,
                $tpl,
                self::nid((string)$it['id']),
                'A_NAME="' . self::escapeAttr((string)$it['name']) . '" B_TECHNOLOGY="' . self::escapeAttr((string)$it['technology']) . '" C_DESCRIPTION="' . self::escapeAttr((string)$it['description']) . '"',
                $cx,
                $cy,
                $wh[0],
                $wh[1]
            );
            $cy += $wh[1] + $vGap;
        }

        $orphanGap = 40;
        $ox = $bdryX - $colW - $orphanGap;
        if ($ox < 30) $ox = 30;
        $oy = $bdryY + 55;
        $c2OrphanList = self::dedupeC2ItemsById($ir['c2Standalone'] ?? []);
        if ($c2Boundary && !empty($c2OrphanList)) {
            foreach ($c2OrphanList as $it) {
                $oid = (string)($it['id'] ?? '');
                if ($oid !== '' && isset($innerIds[$oid])) {
                    continue;
                }
                $ik = (string)($it['kind'] ?? 'container');
                if ($ik !== 'db' && $ik !== 'queue') {
                    $ik = 'container';
                }
                $tpl = self::c2ItemTemplateKey($HY, $ik);
                $wh = self::c2ItemWh($HY, $ik);
                $parts[] = self::vo(
                    $HY,
                    $tpl,
                    self::nid((string)$it['id']),
                    'A_NAME="' . self::escapeAttr((string)$it['name']) . '" B_TECHNOLOGY="' . self::escapeAttr((string)$it['technology']) . '" C_DESCRIPTION="' . self::escapeAttr((string)$it['description']) . '"',
                    $ox,
                    $oy,
                    $wh[0],
                    $wh[1]
                );
                $oy += $wh[1] + $vGap;
            }
        }

        foreach (($ir['persons'] ?? []) as $p) {
            $key = !empty($p['external']) ? 'External Person' : 'Person';
            $dim = $HY[$key];
            $parts[] = self::vo(
                $HY,
                $key,
                self::nid((string)$p['id']),
                'A_NAME="' . self::escapeAttr((string)$p['name']) . '" B_DESCRIPTION="' . self::escapeAttr((string)$p['description']) . '"',
                25,
                220,
                (int)$dim['w'],
                (int)$dim['h']
            );
        }

        $ey = 100;
        foreach (($ir['systemsExt'] ?? []) as $item) {
            $parts[] = self::vo(
                $HY,
                'External Software System',
                self::nid((string)$item['id']),
                'A_NAME="' . self::escapeAttr((string)$item['name']) . '" B_DESCRIPTION="' . self::escapeAttr((string)$item['description']) . '"',
                $bdryX + $bdryW + 40,
                $ey,
                (int)$ESS['w'],
                (int)$ESS['h']
            );
            $ey += (int)$ESS['h'] + 40;
        }

        $ei = 0;
        foreach (($ir['rels'] ?? []) as $r) {
            $id = 'e-' . $ei++;
            if (!empty($r['technology'])) {
                $parts[] = self::eo(
                    $HY,
                    'Relationship with description and technology',
                    $id,
                    'A_DESCRIPTION="' . self::escapeAttr((string)$r['description']) . '" B_TECHNOLOGY="' . self::escapeAttr((string)$r['technology']) . '"',
                    self::nid((string)$r['from']),
                    self::nid((string)$r['to'])
                );
            } else {
                $parts[] = self::eo(
                    $HY,
                    'Relationship with description',
                    $id,
                    'A_DESCRIPTION="' . self::escapeAttr((string)$r['description']) . '"',
                    self::nid((string)$r['from']),
                    self::nid((string)$r['to'])
                );
            }
        }

        $maxY = $bdryY + $bdryH;
        if (count($innerItems) > 0) $maxY = max($maxY, $cy - $vGap);
        if ($c2Boundary && !empty($c2OrphanList)) $maxY = max($maxY, $oy - $vGap);

        $pageW = max(
            880,
            30 + (int)$CT2['w'] * 2 + 40,
            $bdryX + $bdryW + 40 + (int)$ESS['w'] + 80,
            ($c2Boundary && !empty($c2OrphanList)) ? $ox + $colW + 60 : 0
        );
        $pageH = max(620, $maxY + 80);

        if ($LEG) {
            $parts[] = self::vo(
                $HY,
                'Legend',
                'legend',
                '',
                $pageW - (int)$LEG['w'] - 40,
                $pageH - (int)$LEG['h'] - 40,
                (int)$LEG['w'],
                (int)$LEG['h']
            );
        }

        return self::mxfile($title !== '' ? $title : 'C4 Containers', 'c4-containers-auto', $pageW, $pageH, implode('', $parts));
    }

    private static function emitC3(array $ir, array $HY): string
    {
        $parts = [];
        $COMP = $HY['Component'];
        $ESS = $HY['External Software System'];
        $CN = $HY['Container'];
        $DC = $HY['Data Container'];
        $CT = $HY['Components diagram title'];
        $LEG = $HY['Legend'] ?? null;

        $title = (string)($ir['title'] ?? '');

        // C3 diagrams in Mermaid often include Container/ContainerDb outside the component boundary
        // (e.g. SPA/Mobile + DB). These exist in IR but must be emitted explicitly.
        $orphanContainers = [];
        foreach (($ir['containers'] ?? []) as $c) {
            if (empty($c['boundaryId'])) {
                $orphanContainers[] = $c;
            }
        }
        $orphanDbs = [];
        foreach (($ir['containerDbs'] ?? []) as $db) {
            if (empty($db['boundaryId'])) {
                $orphanDbs[] = $db;
            }
        }

        $parts[] = self::vo(
            $HY,
            'Components diagram title',
            'tit-c3',
            'A_C3_DIAGRAM_NAME="' . self::escapeAttr($title) . '" B_C3_DIAGRAM_DESCRIPTION=""',
            30,
            15,
            (int)$CT['w'] * 2,
            (int)$CT['h']
        );

        $inBoundary = [];
        foreach (($ir['components'] ?? []) as $c) {
            if (!empty($c['boundaryId'])) $inBoundary[] = $c;
        }

        $boundary = null;
        foreach (($ir['boundaries'] ?? []) as $b) {
            if (($b['kind'] ?? null) === 'C3') { $boundary = $b; break; }
        }
        if ($boundary === null && !empty($ir['boundaries'][0])) {
            $boundary = $ir['boundaries'][0];
        }

        $bdryTplKey = 'Container scope boundary';
        $boundaryMacro = (string)($boundary['macro'] ?? $boundary['sourceKind'] ?? '');
        if ($boundary !== null && $boundaryMacro === 'System_Boundary') {
            $bdryTplKey = 'System scope boundary';
        }
        $Bdims = $HY[$bdryTplKey];

        $pad = 50;
        $compW = (int)$COMP['w'];
        $compH = (int)$COMP['h'];
        $vGap = 24;
        $nb = max(1, count($inBoundary));
        $bdryW = max((int)$Bdims['w'], $compW + $pad * 2);
        $bdryH = max((int)$Bdims['h'], $pad + $nb * $compH + ($nb - 1) * $vGap + $pad);
        $bdryX = 180;
        $bdryY = 60;

        $orphanGap = 40;
        $colW = max((int)$CN['w'], (int)$DC['w']);
        $ox = $bdryX - $colW - $orphanGap;
        if ($ox < 30) {
            $ox = 30;
        }
        $oy = $bdryY + 55;
        foreach ($orphanContainers as $c) {
            $wh = [(int)$CN['w'], (int)$CN['h']];
            $parts[] = self::vo(
                $HY,
                'Container',
                self::nid((string)$c['id']),
                'A_NAME="' . self::escapeAttr((string)$c['name']) . '" B_TECHNOLOGY="' . self::escapeAttr((string)$c['technology']) . '" C_DESCRIPTION="' . self::escapeAttr((string)$c['description']) . '"',
                $ox,
                $oy,
                $wh[0],
                $wh[1]
            );
            $oy += $wh[1] + $vGap;
        }
        foreach ($orphanDbs as $db) {
            $wh = [(int)$DC['w'], (int)$DC['h']];
            $parts[] = self::vo(
                $HY,
                'Data Container',
                self::nid((string)$db['id']),
                'A_NAME="' . self::escapeAttr((string)$db['name']) . '" B_TECHNOLOGY="' . self::escapeAttr((string)$db['technology']) . '" C_DESCRIPTION="' . self::escapeAttr((string)$db['description']) . '"',
                $ox,
                $oy,
                $wh[0],
                $wh[1]
            );
            $oy += $wh[1] + $vGap;
        }

        if ($boundary) {
            $parts[] = self::vo(
                $HY,
                $bdryTplKey,
                'bdry-main',
                'A_NAME="' . self::escapeAttr((string)($boundary['name'] ?? '')) . '"',
                $bdryX,
                $bdryY,
                $bdryW,
                $bdryH
            );
        }

        $cy = $bdryY + 55;
        $cx = $bdryX + ($bdryW - $compW) / 2;
        foreach ($inBoundary as $c) {
            $parts[] = self::vo(
                $HY,
                'Component',
                self::nid((string)$c['id']),
                'A_NAME="' . self::escapeAttr((string)$c['name']) . '" B_TECHNOLOGY="' . self::escapeAttr((string)$c['technology']) . '" C_DESCRIPTION="' . self::escapeAttr((string)$c['description']) . '"',
                $cx,
                $cy,
                (int)$COMP['w'],
                (int)$COMP['h']
            );
            $cy += $compH + $vGap;
        }

        foreach (($ir['persons'] ?? []) as $p) {
            $key = !empty($p['external']) ? 'External Person' : 'Person';
            $dim = $HY[$key];
            $parts[] = self::vo(
                $HY,
                $key,
                self::nid((string)$p['id']),
                'A_NAME="' . self::escapeAttr((string)$p['name']) . '" B_DESCRIPTION="' . self::escapeAttr((string)$p['description']) . '"',
                25,
                220,
                (int)$dim['w'],
                (int)$dim['h']
            );
        }

        $ey = 100;
        foreach (($ir['systemsExt'] ?? []) as $item) {
            $parts[] = self::vo(
                $HY,
                'External Software System',
                self::nid((string)$item['id']),
                'A_NAME="' . self::escapeAttr((string)$item['name']) . '" B_DESCRIPTION="' . self::escapeAttr((string)$item['description']) . '"',
                $bdryX + $bdryW + 40,
                $ey,
                (int)$ESS['w'],
                (int)$ESS['h']
            );
            $ey += (int)$ESS['h'] + 40;
        }

        $ei = 0;
        foreach (($ir['rels'] ?? []) as $r) {
            $id = 'e-' . $ei++;
            if (!empty($r['technology'])) {
                $parts[] = self::eo(
                    $HY,
                    'Relationship with description and technology',
                    $id,
                    'A_DESCRIPTION="' . self::escapeAttr((string)$r['description']) . '" B_TECHNOLOGY="' . self::escapeAttr((string)$r['technology']) . '"',
                    self::nid((string)$r['from']),
                    self::nid((string)$r['to'])
                );
            } else {
                $parts[] = self::eo(
                    $HY,
                    'Relationship with description',
                    $id,
                    'A_DESCRIPTION="' . self::escapeAttr((string)$r['description']) . '"',
                    self::nid((string)$r['from']),
                    self::nid((string)$r['to'])
                );
            }
        }

        $maxY = $bdryY + $bdryH;
        if (count($inBoundary) > 0) {
            $maxY = max($maxY, $cy - $vGap);
        }
        if (count($orphanContainers) > 0 || count($orphanDbs) > 0) {
            $maxY = max($maxY, $oy - $vGap);
        }

        $pageW = max(
            880,
            30 + (int)$CT['w'] * 2 + 40,
            $bdryX + $bdryW + 40 + (int)$ESS['w'] + 40,
            (count($orphanContainers) > 0 || count($orphanDbs) > 0) ? ($ox + $colW + 60) : 0
        );
        $pageH = max(620, $maxY + 80);

        if ($LEG) {
            $parts[] = self::vo(
                $HY,
                'Legend',
                'legend',
                '',
                $pageW - (int)$LEG['w'] - 40,
                $pageH - (int)$LEG['h'] - 40,
                (int)$LEG['w'],
                (int)$LEG['h']
            );
        }

        return self::mxfile($title !== '' ? $title : 'C4 Components', 'c4-components-auto', $pageW, $pageH, implode('', $parts));
    }
}

