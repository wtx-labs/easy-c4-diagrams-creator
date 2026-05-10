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
        $CSB = $HY['Container scope boundary'] ?? null;

        $title = (string)($ir['title'] ?? '');

        /** @var array<string, array{x:float,y:float,w:float,h:float}> */
        $boxes = [];
        $putBox = static function (string $id, float $x, float $y, float $w, float $h) use (&$boxes): void {
            if ($id === '') return;
            $boxes[$id] = ['x' => $x, 'y' => $y, 'w' => $w, 'h' => $h];
        };

        $shapeXml = '';

        $personRowYC1 = self::contentRowBelowDiagramTitle($HY, 'System Context diagram title');
        $phMaxC1 = 0;
        $nPersonsC1 = count($ir['persons'] ?? []);
        foreach (($ir['persons'] ?? []) as $p) {
            if (!is_array($p)) {
                continue;
            }
            $key = !empty($p['external']) ? 'External Person' : 'Person';
            $phMaxC1 = max($phMaxC1, (int)$HY[$key]['h']);
        }
        $systemYC1 = $nPersonsC1 > 0
            ? $personRowYC1 + (float)$phMaxC1 + self::vSpace(72.0)
            : $personRowYC1 + self::vSpace(36.0);

        $personGapC1 = 5.0;
        $personBandWC1 = self::personBandTotalWidth($ir['persons'] ?? [], $HY, $personGapC1);
        $nSysC1 = count($ir['systems'] ?? []);
        $swC1 = (int)$SS['w'];
        $personStartXC1 = 40.0;
        $systemLeftXC1 = 200.0;
        if ($nPersonsC1 > 0 && $nSysC1 > 0) {
            $W_bandC1 = max($personBandWC1, (float)$swC1);
            $bandStartXC1 = 40.0;
            $personStartXC1 = $bandStartXC1 + ($W_bandC1 - $personBandWC1) / 2.0;
            $systemLeftXC1 = $bandStartXC1 + ($W_bandC1 - (float)$swC1) / 2.0;
        } elseif ($nPersonsC1 > 0) {
            $personStartXC1 = 40.0;
        }

        $accPersonXC1 = $personStartXC1;
        $piC1 = 0;
        foreach (($ir['persons'] ?? []) as $p) {
            if (!is_array($p)) {
                continue;
            }
            if ($piC1 > 0) {
                $accPersonXC1 += $personGapC1;
            }
            $key = !empty($p['external']) ? 'External Person' : 'Person';
            $dim = $HY[$key];
            $px = $accPersonXC1;
            $py = $personRowYC1;
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
            $accPersonXC1 += (float)$pw0;
            $piC1++;
        }

        $systemsListC1 = $ir['systems'] ?? [];
        $systemsBottomYC1 = $systemYC1 + (float)(int)$SS['h'];
        if (count($systemsListC1) > 0) {
            $sw = (int)$SS['w'];
            $sh = (int)$SS['h'];
            $sysHGap = 36.0;
            $sysVGap = self::vSpace(28.0);
            $nSys = count($systemsListC1);
            $ncol = max(1, (int)ceil(sqrt((float)$nSys)));
            $nrow = (int)ceil($nSys / $ncol);
            $gridW = $ncol * $sw + ($ncol - 1) * $sysHGap;
            $gridH = $nrow * $sh + ($nrow - 1) * $sysVGap;
            $anchorCenterX = $nPersonsC1 > 0
                ? $personStartXC1 + $personBandWC1 / 2.0
                : $systemLeftXC1 + (float)$sw / 2.0;
            $startXC1 = $anchorCenterX - $gridW / 2.0;

            foreach ($systemsListC1 as $si => $s) {
                $col = $si % $ncol;
                $row = intdiv($si, $ncol);
                $sx = $startXC1 + $col * ($sw + $sysHGap);
                $sy = $systemYC1 + $row * ($sh + $sysVGap);
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
            $systemsBottomYC1 = $systemYC1 + $gridH;
        }

        $bottomGap = 30;
        $bx = -200;
        $bottomY = max(880.0, $systemsBottomYC1 + self::vSpace(220.0));
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
        $boundaryBoxes = [];
        if ($CSB) {
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
                $bwI = (int)max(120, (int)round($bw));
                $bhI = (int)max(120, (int)round($bh));

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
                    'x' => $bx0,
                    'y' => $by0,
                    'w' => (float)$bwI,
                    'h' => (float)$bhI,
                    'xml' => self::vo(
                        $HY,
                        $tpl,
                        'bdry-mmd-' . $bid,
                        'A_NAME="' . self::escapeAttr((string)($b['name'] ?? $bid)) . '"',
                        $bx0,
                        $by0,
                        $bwI,
                        $bhI
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

        $minXB = INF;
        $minYB = INF;
        $maxXB = -INF;
        $maxYB = -INF;
        foreach ($boxes as $bb) {
            $minXB = min($minXB, $bb['x']);
            $minYB = min($minYB, $bb['y']);
            $maxXB = max($maxXB, $bb['x'] + $bb['w']);
            $maxYB = max($maxYB, $bb['y'] + $bb['h']);
        }
        foreach ($boundaryBoxes as $brow) {
            if (!isset($brow['x'], $brow['y'], $brow['w'], $brow['h'])) {
                continue;
            }
            $minXB = min($minXB, $brow['x']);
            $minYB = min($minYB, $brow['y']);
            $maxXB = max($maxXB, $brow['x'] + $brow['w']);
            $maxYB = max($maxYB, $brow['y'] + $brow['h']);
        }
        if (!is_finite($minXB) || !is_finite($minYB)) {
            $minXB = -40.0;
            $minYB = $personRowYC1;
            $maxXB = 900.0;
            $maxYB = $bottomY + (float)(int)$ESS['h'] + 40.0;
        }

        $titDm1 = self::diagramTitleEmittedDims($HY, 'System Context diagram title');
        $frameTopC1 = self::diagramTitleFrameTopY();
        $titleX1 = $minXB;
        $titleY1 = $frameTopC1;
        $titleXml = self::vo(
            $HY,
            'System Context diagram title',
            'tit-ctx',
            'A_C1_DIAGRAM_NAME="' . self::escapeAttr($title) . '" B_C1_DIAGRAM_DESCRIPTION=""',
            $titleX1,
            $titleY1,
            $titDm1['emitW'],
            $titDm1['h']
        );

        $legM = self::LEGEND_EDGE_MARGIN;
        $legGapC1 = self::vSpace(self::GAP_AFTER_DIAGRAM_TITLE);
        $legendXml = '';
        $legXC1 = 0.0;
        $legYC1 = 0.0;
        if ($LEG) {
            $legXC1 = $maxXB - (float)(int)$LEG['w'];
            $legYC1 = $maxYB + $legGapC1;
            $legendXml = self::vo(
                $HY,
                'Legend',
                'legend',
                '',
                $legXC1,
                $legYC1,
                (int)$LEG['w'],
                (int)$LEG['h']
            );
        }
        $pageW = (int)ceil(max(
            $maxXB + $legM + 40.0,
            $titleX1 + (float)$titDm1['emitW'] + $legM + 40.0,
            $LEG ? $legXC1 + (float)(int)$LEG['w'] + $legM + 40.0 : 0.0,
            640.0
        ));
        $pageH = (int)ceil(max(
            $LEG ? $legYC1 + (float)(int)$LEG['h'] + $legM + 40.0 : $maxYB + $legM + 100.0,
            $titleY1 + (float)$titDm1['h'] + $legM + 40.0,
            720.0
        ));

        $body = $behind . $shapeXml . $edgeXml . $titleXml . $legendXml;
        return self::mxfile($title !== '' ? $title : 'C4 Context', 'c4-context-auto', $pageW, $pageH, $body);
    }

    /** @param 'container'|'db'|'queue'|'system'|'extsystem' $itemKind */
    private static function c2ItemTemplateKey(array $HY, string $itemKind): string
    {
        if ($itemKind === 'db') {
            return 'Data Container';
        }
        if ($itemKind === 'system') {
            return 'Software System';
        }
        if ($itemKind === 'extsystem') {
            return 'External Software System';
        }
        if ($itemKind === 'queue' && isset($HY['Message Bus Container'])) {
            return 'Message Bus Container';
        }
        return 'Container';
    }

    /** @param 'container'|'db'|'queue'|'system'|'extsystem' $itemKind @return array{0:int,1:int} */
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

    /**
     * @param list<array{0:int,1:int}> $whList
     * @return array{0: list<array{x:float,y:float,w:int,h:int}>, 1: float, 2: float}
     */
    private static function layoutRectGrid(array $whList, float $hGap, float $vGap): array
    {
        $n = count($whList);
        if ($n === 0) {
            return [[], 0.0, 0.0];
        }
        $ncol = max(1, (int)ceil(sqrt($n)));
        $nrow = (int)ceil($n / $ncol);
        $colWidths = array_fill(0, $ncol, 0);
        $rowHeights = array_fill(0, $nrow, 0);
        for ($i = 0; $i < $n; $i++) {
            $c = $i % $ncol;
            $r = intdiv($i, $ncol);
            $colWidths[$c] = max($colWidths[$c], $whList[$i][0]);
            $rowHeights[$r] = max($rowHeights[$r], $whList[$i][1]);
        }
        $x0 = [];
        $acc = 0.0;
        for ($c = 0; $c < $ncol; $c++) {
            $x0[$c] = $acc;
            $acc += $colWidths[$c] + ($c < $ncol - 1 ? $hGap : 0.0);
        }
        $y0 = [];
        $acc = 0.0;
        for ($r = 0; $r < $nrow; $r++) {
            $y0[$r] = $acc;
            $acc += $rowHeights[$r] + ($r < $nrow - 1 ? $vGap : 0.0);
        }
        $totalW = array_sum($colWidths) + ($ncol > 1 ? ($ncol - 1) * $hGap : 0.0);
        $totalH = array_sum($rowHeights) + ($nrow > 1 ? ($nrow - 1) * $vGap : 0.0);
        $pos = [];
        for ($i = 0; $i < $n; $i++) {
            $c = $i % $ncol;
            $r = intdiv($i, $ncol);
            $w = $whList[$i][0];
            $h = $whList[$i][1];
            $x = $x0[$c] + ($colWidths[$c] - $w) / 2;
            $y = $y0[$r] + ($rowHeights[$r] - $h) / 2;
            $pos[] = ['x' => $x, 'y' => $y, 'w' => $w, 'h' => $h];
        }
        return [$pos, $totalW, $totalH];
    }

    /**
     * Układ siatki (wierszami), kolumny ~√n — większy „kwadrat” niż jedna wąska kolumna.
     *
     * @param list<array<string, mixed>> $items
     * @return array{0: list<array{x:float,y:float,w:int,h:int}>, 1: float, 2: float} pozycje względem (0,0), szerokość i wysokość bloku
     */
    private static function layoutC2ItemsGrid(array $items, array $HY, float $hGap, float $vGap): array
    {
        /** @var list<array{0:int,1:int}> $whList */
        $whList = [];
        foreach ($items as $it) {
            $ik = self::normalizeC2ItemKind($it);
            $whList[] = self::c2ItemWh($HY, $ik);
        }
        return self::layoutRectGrid($whList, $hGap, $vGap);
    }

    /** @return 'container'|'db'|'queue'|'system'|'extsystem' */
    private static function normalizeC2ItemKind(array $it): string
    {
        $ik = (string)($it['kind'] ?? 'container');
        if ($ik !== 'db' && $ik !== 'queue' && $ik !== 'system' && $ik !== 'extsystem') {
            return 'container';
        }
        return $ik;
    }

    /**
     * Heurystyka dla C3: jeśli komponent wygląda na bus/queue (po nazwie/technologii/opisie),
     * użyj szablonu Message Bus Container zamiast zwykłego Component.
     */
    private static function c3ComponentTemplateKey(array $HY, array $component): string
    {
        if (!isset($HY['Message Bus Container'])) {
            return 'Component';
        }
        return self::c3LooksLikeBusOrQueue($component) ? 'Message Bus Container' : 'Component';
    }

    private static function c3LooksLikeBusOrQueue(array $component): bool
    {
        $haystack = strtolower(trim(
            (string)($component['id'] ?? '') . ' ' .
            (string)($component['name'] ?? '') . ' ' .
            (string)($component['technology'] ?? '') . ' ' .
            (string)($component['description'] ?? '')
        ));
        if ($haystack === '') {
            return false;
        }

        $busNeedles = [
            'bus', 'queue', 'pub/sub', 'pubsub', 'topic', 'broker',
            'kafka', 'rabbitmq', 'rabbit mq', 'nats', 'stream', 'event stream',
        ];
        $hasBusSignal = false;
        foreach ($busNeedles as $needle) {
            if (str_contains($haystack, $needle)) {
                $hasBusSignal = true;
                break;
            }
        }

        $hasRedis = str_contains($haystack, 'redis');
        // Sama wzmianka o Redis nie oznacza busa: Redis może być zwykłą bazą/KV.
        if ($hasRedis && !$hasBusSignal) {
            return false;
        }

        // Jeśli opis wygląda wyłącznie na klasyczną bazę i nie ma wyraźnego sygnału bus/queue, traktuj jako nie-bus.
        $strictDbNeedles = ['database', 'baza danych', 'postgres', 'mysql', 'oracle', 'mongodb'];
        $hasStrictDbSignal = false;
        foreach ($strictDbNeedles as $needle) {
            if (str_contains($haystack, $needle)) {
                $hasStrictDbSignal = true;
                break;
            }
        }
        if ($hasStrictDbSignal && !$hasBusSignal) {
            return false;
        }
        return $hasBusSignal;
    }

    /**
     * Jednolita siatka (np. komponenty C3 lub systemy zewnętrzne).
     *
     * @return list<array{x:float,y:float}>
     */
    private static function layoutUniformSquareGrid(int $n, float $cellW, float $cellH, float $hGap, float $vGap): array
    {
        if ($n <= 0) {
            return [];
        }
        $ncol = max(1, (int)ceil(sqrt($n)));
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $col = $i % $ncol;
            $row = intdiv($i, $ncol);
            $out[] = [
                'x' => $col * ($cellW + $hGap),
                'y' => $row * ($cellH + $vGap),
            ];
        }
        return $out;
    }

    /**
     * Jedna pozioma linia (np. systemy zewnętrzne w dolnym rzędzie diagramu).
     *
     * @return array{0: list<array{x:float,y:float}>, 1: float, 2: float}
     */
    private static function layoutUniformSingleRow(int $n, float $cellW, float $cellH, float $hGap): array
    {
        if ($n <= 0) {
            return [[], 0.0, 0.0];
        }
        $pos = [];
        for ($i = 0; $i < $n; $i++) {
            $pos[] = ['x' => $i * ($cellW + $hGap), 'y' => 0.0];
        }
        $totalW = $n * $cellW + ($n - 1) * $hGap;
        return [$pos, $totalW, $cellH];
    }

    private const GAP_AFTER_DIAGRAM_TITLE = 18.0;
    private const LEGEND_EDGE_MARGIN = 20.0;

    /** Współczynnik powiększenia odstępów pionowych między elementami layoutu. */
    private const VERTICAL_ELEMENT_GAP_SCALE = 2.0;

    private static function vSpace(float $base): float
    {
        return $base * self::VERTICAL_ELEMENT_GAP_SCALE;
    }

    /**
     * Łączna szerokość rzędu osób (szerokości szablonów + odstępy poziome).
     *
     * @param list<mixed> $personList
     */
    private static function personBandTotalWidth(array $personList, array $HY, float $hGapBetweenPersons): float
    {
        $sum = 0.0;
        $first = true;
        foreach ($personList as $p) {
            if (!is_array($p)) {
                continue;
            }
            $key = !empty($p['external']) ? 'External Person' : 'Person';
            if (!isset($HY[$key]['w'])) {
                continue;
            }
            if (!$first) {
                $sum += $hGapBetweenPersons;
            }
            $sum += (float)(int)$HY[$key]['w'];
            $first = false;
        }
        return $sum;
    }

    /** Lewa krawędź rzędu osób wyśrodkowana w poziomie względem [rowLeft, rowRight]. */
    private static function personRowStartXCenteredOnSpan(float $rowLeft, float $rowRight, float $personBandW): float
    {
        if ($personBandW <= 0.0) {
            return $rowLeft;
        }
        $mid = ($rowLeft + $rowRight) / 2.0;

        return $mid - $personBandW / 2.0;
    }

    /** Margines strony nad blokiem tytułu (Y lewej górnej tytułu). */
    private static function diagramTitleFrameTopY(): float
    {
        return self::LEGEND_EDGE_MARGIN;
    }

    /** Y pierwszego rzędu treści pod tytułem (tytuł w y = diagramTitleFrameTopY). */
    private static function contentRowBelowDiagramTitle(array $HY, string $titleTemplateKey): float
    {
        $d = self::diagramTitleEmittedDims($HY, $titleTemplateKey);

        return self::diagramTitleFrameTopY() + (float)$d['h'] + self::vSpace(self::GAP_AFTER_DIAGRAM_TITLE);
    }

    /** @return array{h:int, emitW:int} */
    private static function diagramTitleEmittedDims(array $HY, string $titleTemplateKey): array
    {
        $t = $HY[$titleTemplateKey] ?? null;
        $h = is_array($t) ? (int)($t['h'] ?? 40) : 40;
        $w = is_array($t) ? (int)($t['w'] ?? 240) : 240;
        return ['h' => $h, 'emitW' => $w * 2];
    }

    /** @return array{minX: float, minY: float, maxX: float, maxY: float} */
    private static function diagramBBoxMerge(?array $bbox, float $x, float $y, float $w, float $h): array
    {
        $rx = $x + $w;
        $by = $y + $h;
        if ($bbox === null) {
            return ['minX' => $x, 'minY' => $y, 'maxX' => $rx, 'maxY' => $by];
        }

        return [
            'minX' => min($bbox['minX'], $x),
            'minY' => min($bbox['minY'], $y),
            'maxX' => max($bbox['maxX'], $rx),
            'maxY' => max($bbox['maxY'], $by),
        ];
    }

    private static function voWithDiagramBBox(?array &$bbox, array $HY, string $templateKey, string $id, string $extraAttrs, float $x, float $y, float $w, float $h, string $parent = '1'): string
    {
        $bbox = self::diagramBBoxMerge($bbox, $x, $y, $w, $h);

        return self::vo($HY, $templateKey, $id, $extraAttrs, $x, $y, $w, $h, $parent);
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
    private static function layoutDeploymentSubtree(array $ir, array $HY, string $nodeId, float $colW, float $originX, float $originY, ?array &$mergeBBox = null): array
    {
        $nodes = $ir['deployment']['nodes'] ?? [];
        $node = $nodes[$nodeId] ?? null;
        if (!is_array($node)) {
            return [0.0, 0.0, ''];
        }

        $pad = 32.0;
        $vGap = self::vSpace(28.0);
        $header = self::vSpace(52.0);

        $cx = $originX + $pad;
        $cy = $originY + $header;
        $maxRight = $cx;

        $innerXml = '';

        foreach ($node['childOrder'] ?? [] as $cid) {
            if (!is_string($cid) || $cid === '') {
                continue;
            }
            [$cw, $ch, $sub] = self::layoutDeploymentSubtree($ir, $HY, $cid, $colW, $cx, $cy, $mergeBBox);
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
            $innerXml .= $mergeBBox !== null
                ? self::voWithDiagramBBox($mergeBBox, $HY, 'Container', self::nid($cid), 'A_NAME="' . self::escapeAttr((string)$c['name']) . '" B_TECHNOLOGY="' . self::escapeAttr((string)$c['technology']) . '" C_DESCRIPTION="' . self::escapeAttr((string)$c['description']) . '"', $cx, $cy, (float)$wh[0], (float)$wh[1], '1')
                : self::vo(
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
            $innerXml .= $mergeBBox !== null
                ? self::voWithDiagramBBox($mergeBBox, $HY, 'Data Container', self::nid($did), 'A_NAME="' . self::escapeAttr((string)$d['name']) . '" B_TECHNOLOGY="' . self::escapeAttr((string)$d['technology']) . '" C_DESCRIPTION="' . self::escapeAttr((string)$d['description']) . '"', $cx, $cy, (float)$wh[0], (float)$wh[1], '1')
                : self::vo(
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
            $cy = $originY + $header + self::vSpace(36.0);
        }

        $boxW = max(280.0, $maxRight - $originX + $pad);
        $boxH = $cy - $originY + $pad;

        $depAttrs = self::deploymentNodePlaceholderAttrs($node);
        $bwOut = (int)ceil($boxW);
        $bhOut = (int)ceil($boxH);
        $boundaryXml = $mergeBBox !== null
            ? self::voWithDiagramBBox($mergeBBox, $HY, 'Deployment Node', 'dep-' . $nodeId, $depAttrs, $originX, $originY, (float)$bwOut, (float)$bhOut, '1')
            : self::vo(
                $HY,
                'Deployment Node',
                'dep-' . $nodeId,
                $depAttrs,
                $originX,
                $originY,
                $bwOut,
                $bhOut,
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
        $bbox = null;

        $titDmD = self::diagramTitleEmittedDims($HY, 'Containers diagram title');
        $frameTopD = self::diagramTitleFrameTopY();
        $contentTopD = $frameTopD + (float)$titDmD['h'] + self::vSpace(self::GAP_AFTER_DIAGRAM_TITLE);
        $pnListD = $ir['persons'] ?? [];
        $phMaxD = 0;
        foreach ($pnListD as $pq) {
            if (!is_array($pq)) {
                continue;
            }
            $pk = !empty($pq['external']) ? 'External Person' : 'Person';
            $phMaxD = max($phMaxD, (int)$HY[$pk]['h']);
        }
        $personRowYD = $contentTopD;
        $personBandEndD = count($pnListD) > 0
            ? $personRowYD + (float)$phMaxD + self::vSpace(28.0)
            : $contentTopD;

        $deployRootLeftX = 60.0;
        $rootX = $deployRootLeftX;
        $rootY = $personBandEndD + self::vSpace(18.0);
        $gapBetweenRoots = 72.0;
        $maxBottom = $rootY;
        $maxRight = $rootX;

        foreach ($roots as $rid) {
            if (!is_string($rid) || $rid === '') {
                continue;
            }
            [$w, $h, $chunk] = self::layoutDeploymentSubtree($ir, $HY, $rid, (float)$colW, $rootX, $rootY, $bbox);
            $parts[] = $chunk;
            $maxBottom = max($maxBottom, $rootY + $h);
            $maxRight = max($maxRight, $rootX + $w);
            $rootX += $w + $gapBetweenRoots;
        }

        $personGapD = 28.0;
        $personBandWD = self::personBandTotalWidth($pnListD, $HY, $personGapD);
        $personStartXD = count($pnListD) > 0
            ? self::personRowStartXCenteredOnSpan($deployRootLeftX, $maxRight, $personBandWD)
            : null;
        $pxD = $personStartXD ?? 36.0;
        foreach ($pnListD as $p) {
            if (!is_array($p)) {
                continue;
            }
            $key = !empty($p['external']) ? 'External Person' : 'Person';
            $dim = $HY[$key];
            $pw0 = (int)$dim['w'];
            $ph0 = (int)$dim['h'];
            $parts[] = self::voWithDiagramBBox(
                $bbox,
                $HY,
                $key,
                self::nid((string)$p['id']),
                'A_NAME="' . self::escapeAttr((string)$p['name']) . '" B_DESCRIPTION="' . self::escapeAttr((string)$p['description']) . '"',
                $pxD,
                $personRowYD,
                (float)$pw0,
                (float)$ph0,
                '1'
            );
            $pxD += $pw0 + $personGapD;
        }

        $extDep = $ir['systemsExt'] ?? [];
        $essWd = (int)$ESS['w'];
        $essHd = (int)$ESS['h'];
        $extHGapD = 36.0;
        $coreMaxYD = $maxBottom;
        $extBottomYD = 0.0;
        $pageWDraftD = max(1200.0, (float)ceil($maxRight) + 120.0, 30.0 + (float)$CT2['w'] * 2.0 + 40.0);
        if (is_array($extDep) && count($extDep) > 0) {
            [$posExtD, $extRowWD,] = self::layoutUniformSingleRow(count($extDep), (float)$essWd, (float)$essHd, $extHGapD);
            $extRowYD = $coreMaxYD + self::vSpace(44.0);
            $extStartXD = max(40.0, ($pageWDraftD - $extRowWD) / 2.0);
            $pageWDraftD = max($pageWDraftD, $extStartXD + $extRowWD + 80.0);
            $extBottomYD = $extRowYD + (float)$essHd;
            foreach ($extDep as $ei => $item) {
                if (!is_array($item)) {
                    continue;
                }
                $parts[] = self::voWithDiagramBBox(
                    $bbox,
                    $HY,
                    'External Software System',
                    self::nid((string)$item['id']),
                    'A_NAME="' . self::escapeAttr((string)$item['name']) . '" B_DESCRIPTION="' . self::escapeAttr((string)$item['description']) . '"',
                    $extStartXD + $posExtD[$ei]['x'],
                    $extRowYD + $posExtD[$ei]['y'],
                    (float)$essWd,
                    (float)$essHd,
                    '1'
                );
            }
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

        $bbD = $bbox ?? [
            'minX' => $deployRootLeftX,
            'minY' => $personRowYD,
            'maxX' => $maxRight,
            'maxY' => max($maxBottom, $extBottomYD),
        ];
        $titleXD = $bbD['minX'];
        $titleYD = $frameTopD;
        $parts[] = self::vo(
            $HY,
            'Containers diagram title',
            'tit-c2',
            'A_C2_DIAGRAM_NAME="' . self::escapeAttr($title) . '" B_C2_DIAGRAM_DESCRIPTION=""',
            $titleXD,
            $titleYD,
            $titDmD['emitW'],
            $titDmD['h'],
            '1'
        );

        $legGapD = self::vSpace(self::GAP_AFTER_DIAGRAM_TITLE);
        $legM = self::LEGEND_EDGE_MARGIN;
        $legXD = 0.0;
        $legYD = 0.0;
        if ($LEG) {
            $legXD = $bbD['maxX'] - (float)(int)$LEG['w'];
            $legYD = $bbD['maxY'] + $legGapD;
            $parts[] = self::vo(
                $HY,
                'Legend',
                'legend',
                '',
                $legXD,
                $legYD,
                (int)$LEG['w'],
                (int)$LEG['h'],
                '1'
            );
        }

        $pageW = (int)ceil(max(
            $pageWDraftD,
            $titleXD + (float)$titDmD['emitW'] + $legM + 40.0,
            $LEG ? $legXD + (float)(int)$LEG['w'] + $legM + 40.0 : 0.0,
            1200.0
        ));
        $pageH = (int)ceil(max(
            900.0,
            max($maxBottom, $extBottomYD) + 120.0,
            $LEG ? $legYD + (float)(int)$LEG['h'] + $legM + 40.0 : 0.0,
            $titleYD + (float)$titDmD['h'] + $legM + 40.0
        ));

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

        $allC2Boundaries = [];
        foreach (($ir['boundaries'] ?? []) as $b) {
            if (!is_array($b)) {
                continue;
            }
            // Mermaid C4 snippets often use mixed boundary macros; if boundary
            // contains C2 items, treat it as a C2 boundary for rendering.
            if (!empty($b['items']) && is_array($b['items'])) {
                $allC2Boundaries[] = $b;
            }
        }

        $c2BoundaryIds = [];
        foreach ($allC2Boundaries as $b) {
            $bid = (string)($b['id'] ?? '');
            if ($bid !== '') {
                $c2BoundaryIds[$bid] = true;
            }
        }

        // System wrapper boundary (e.g. migVisor) that contains other boundaries.
        $outerBoundary = null;
        $c2Boundaries = [];
        foreach ($allC2Boundaries as $b) {
            $memberIds = $b['memberIds'] ?? [];
            $hasInnerBoundaryMembers = false;
            if (is_array($memberIds)) {
                foreach ($memberIds as $mid) {
                    if (is_string($mid) && $mid !== '' && isset($c2BoundaryIds[$mid])) {
                        $hasInnerBoundaryMembers = true;
                        break;
                    }
                }
            }

            $sourceKind = (string)($b['sourceKind'] ?? '');
            if (
                $outerBoundary === null
                && $hasInnerBoundaryMembers
                && ($sourceKind === 'System_Boundary' || $sourceKind === 'Boundary' || $sourceKind === 'Enterprise_Boundary')
            ) {
                $outerBoundary = $b;
                continue;
            }
            $c2Boundaries[] = $b;
        }

        $bbox = null;
        $titDmC2 = self::diagramTitleEmittedDims($HY, 'Containers diagram title');
        $frameTopC2 = self::diagramTitleFrameTopY();
        $contentTopC2 = $frameTopC2 + (float)$titDmC2['h'] + self::vSpace(self::GAP_AFTER_DIAGRAM_TITLE);
        $pnListC2 = $ir['persons'] ?? [];
        $phMaxPersonC2 = 0;
        foreach ($pnListC2 as $pq) {
            if (!is_array($pq)) {
                continue;
            }
            $pk = !empty($pq['external']) ? 'External Person' : 'Person';
            $phMaxPersonC2 = max($phMaxPersonC2, (int)$HY[$pk]['h']);
        }
        $personRowYC2 = $contentTopC2;

        $pad = 58;
        $bdryInnerTop = 55.0;
        /** Odległość od górnej krawędzi boundary do pierwszego elementu (połowa dawnego 55+pad). */
        $bdryContentTopInset = ($bdryInnerTop + (float)$pad) / 2.0;
        $vGap = self::vSpace(34.0);
        $hGap = 40;
        $bdryX = 200;
        $bdryY = count($pnListC2) > 0
            ? $personRowYC2 + (float)$phMaxPersonC2 + self::vSpace(28.0)
            : $contentTopC2;
        $bdryW = (int)$CSB['w'];
        $bdryH = (int)$CSB['h'];

        $colW = max((int)$CN['w'], (int)$DC['w']);
        if (isset($HY['Message Bus Container'])) {
            $colW = max($colW, (int)$HY['Message Bus Container']['w']);
        }

        /** @var array<string, true> */
        $innerIds = [];
        $lastBoundaryBottom = $bdryY;
        if (!empty($c2Boundaries)) {
            $currY = $bdryY;
            foreach ($c2Boundaries as $bi => $c2Boundary) {
                $innerItems = self::dedupeC2ItemsById($c2Boundary['items'] ?? []);
                $bW = (int)$CSB['w'];
                $bH = (int)$CSB['h'];
                $gridPos = [];
                $totalW = 0.0;
                $totalH = 0.0;
                if (count($innerItems) > 0) {
                    [$gridPos, $totalW, $totalH] = self::layoutC2ItemsGrid($innerItems, $HY, $hGap, $vGap);
                    $bW = max((int)$CSB['w'], (int)ceil($pad * 2 + $totalW));
                    $bH = max((int)$CSB['h'], (int)ceil($bdryContentTopInset + $totalH + $pad));
                }
                $parts[] = self::voWithDiagramBBox(
                    $bbox,
                    $HY,
                    'Container scope boundary',
                    'bdry-c2-' . $bi,
                    'A_NAME="' . self::escapeAttr((string)($c2Boundary['name'] ?? '')) . '"',
                    (float)$bdryX,
                    $currY,
                    (float)$bW,
                    (float)$bH
                );

                if (count($innerItems) > 0) {
                    $contentLeft = $bdryX + ($bW - $totalW) / 2;
                    $contentTop = $currY + $bdryContentTopInset;
                    foreach ($innerItems as $gi => $it) {
                        $iid = (string)($it['id'] ?? '');
                        if ($iid !== '') {
                            $innerIds[$iid] = true;
                        }
                        $ik = self::normalizeC2ItemKind($it);
                        $tpl = self::c2ItemTemplateKey($HY, $ik);
                        $p = $gridPos[$gi];
                        $attrs = 'A_NAME="' . self::escapeAttr((string)$it['name']) . '" B_TECHNOLOGY="' . self::escapeAttr((string)$it['technology']) . '" C_DESCRIPTION="' . self::escapeAttr((string)$it['description']) . '"';
                        if ($ik === 'system' || $ik === 'extsystem') {
                            $attrs = 'A_NAME="' . self::escapeAttr((string)$it['name']) . '" B_DESCRIPTION="' . self::escapeAttr((string)$it['description']) . '"';
                        }
                        $parts[] = self::voWithDiagramBBox(
                            $bbox,
                            $HY,
                            $tpl,
                            self::nid((string)$it['id']),
                            $attrs,
                            $contentLeft + $p['x'],
                            $contentTop + $p['y'],
                            $p['w'],
                            $p['h']
                        );
                    }
                }
                $lastBoundaryBottom = $currY + $bH;
                $currY += $bH + self::vSpace(52.0);
                $bdryW = max($bdryW, $bW);
                $bdryH = max($bdryH, $bH);
            }
        } else {
            $innerItems = self::dedupeC2ItemsById($ir['c2Standalone'] ?? []);
            $gridPos = [];
            $totalW = 0.0;
            $totalH = 0.0;
            if (count($innerItems) > 0) {
                [$gridPos, $totalW, $totalH] = self::layoutC2ItemsGrid($innerItems, $HY, $hGap, $vGap);
                $bdryW = max((int)$CSB['w'], (int)ceil($pad * 2 + $totalW));
                $bdryH = max((int)$CSB['h'], (int)ceil($bdryContentTopInset + $totalH + $pad));
            }

            if (count($innerItems) > 0) {
                $contentLeft = $bdryX + ($bdryW - $totalW) / 2;
                $contentTop = $bdryY + $bdryContentTopInset;
                foreach ($innerItems as $gi => $it) {
                    $iid = (string)($it['id'] ?? '');
                    if ($iid !== '') {
                        $innerIds[$iid] = true;
                    }
                    $ik = self::normalizeC2ItemKind($it);
                    $tpl = self::c2ItemTemplateKey($HY, $ik);
                    $p = $gridPos[$gi];
                    $attrs = 'A_NAME="' . self::escapeAttr((string)$it['name']) . '" B_TECHNOLOGY="' . self::escapeAttr((string)$it['technology']) . '" C_DESCRIPTION="' . self::escapeAttr((string)$it['description']) . '"';
                    if ($ik === 'system' || $ik === 'extsystem') {
                        $attrs = 'A_NAME="' . self::escapeAttr((string)$it['name']) . '" B_DESCRIPTION="' . self::escapeAttr((string)$it['description']) . '"';
                    }
                    $parts[] = self::voWithDiagramBBox(
                        $bbox,
                        $HY,
                        $tpl,
                        self::nid((string)$it['id']),
                        $attrs,
                        $contentLeft + $p['x'],
                        $contentTop + $p['y'],
                        $p['w'],
                        $p['h']
                    );
                }
            }
            $lastBoundaryBottom = $bdryY + $bdryH;
        }

        $orphanGap = 56;
        $c2OrphanList = self::dedupeC2ItemsById($ir['c2Standalone'] ?? []);
        $orphanListFiltered = [];
        if (!empty($c2Boundaries)) {
            foreach ($c2OrphanList as $it) {
                $oid = (string)($it['id'] ?? '');
                if ($oid !== '' && isset($innerIds[$oid])) {
                    continue;
                }
                $orphanListFiltered[] = $it;
            }
        }
        $maxOrphanW = (float)$colW;
        if (count($orphanListFiltered) > 0) {
            [, $maxOrphanW, ] = self::layoutC2ItemsGrid($orphanListFiltered, $HY, $hGap, $vGap);
        }
        $ox = $bdryX - $maxOrphanW - $orphanGap;
        if ($ox < 30) {
            $ox = 30;
        }
        $oy = $bdryY + $bdryContentTopInset;
        $orphanBottomY = $bdryY + $bdryContentTopInset;
        if (!empty($c2Boundaries) && count($orphanListFiltered) > 0) {
            [$gridOr, $totalWOr, $totalHOr] = self::layoutC2ItemsGrid($orphanListFiltered, $HY, $hGap, $vGap);
            $orphanBottomY = $oy + $totalHOr;
            foreach ($orphanListFiltered as $gi => $it) {
                $ik = self::normalizeC2ItemKind($it);
                $tpl = self::c2ItemTemplateKey($HY, $ik);
                $p = $gridOr[$gi];
                $attrs = 'A_NAME="' . self::escapeAttr((string)$it['name']) . '" B_TECHNOLOGY="' . self::escapeAttr((string)$it['technology']) . '" C_DESCRIPTION="' . self::escapeAttr((string)$it['description']) . '"';
                if ($ik === 'system' || $ik === 'extsystem') {
                    $attrs = 'A_NAME="' . self::escapeAttr((string)$it['name']) . '" B_DESCRIPTION="' . self::escapeAttr((string)$it['description']) . '"';
                }
                $parts[] = self::voWithDiagramBBox(
                    $bbox,
                    $HY,
                    $tpl,
                    self::nid((string)$it['id']),
                    $attrs,
                    $ox + $p['x'],
                    $oy + $p['y'],
                    $p['w'],
                    $p['h']
                );
            }
        }

        $personGapC2 = 28.0;
        $rowLeftC2 = (float)$bdryX;
        $rowRightC2 = $bdryX + (float)$bdryW;
        if (count($orphanListFiltered) > 0) {
            $rowLeftC2 = min($rowLeftC2, (float)$ox);
            $rowRightC2 = max($rowRightC2, $ox + $maxOrphanW);
        }
        $wPersonRowC2 = self::personBandTotalWidth($pnListC2, $HY, $personGapC2);
        $pxC2 = count($pnListC2) > 0
            ? self::personRowStartXCenteredOnSpan($rowLeftC2, $rowRightC2, $wPersonRowC2)
            : 40.0;
        $personRowLeftC2 = $pxC2;
        foreach ($pnListC2 as $p) {
            if (!is_array($p)) {
                continue;
            }
            $key = !empty($p['external']) ? 'External Person' : 'Person';
            $dim = $HY[$key];
            $pw0 = (int)$dim['w'];
            $ph0 = (int)$dim['h'];
            $parts[] = self::voWithDiagramBBox(
                $bbox,
                $HY,
                $key,
                self::nid((string)$p['id']),
                'A_NAME="' . self::escapeAttr((string)$p['name']) . '" B_DESCRIPTION="' . self::escapeAttr((string)$p['description']) . '"',
                $pxC2,
                $personRowYC2,
                (float)$pw0,
                (float)$ph0
            );
            $pxC2 += $pw0 + $personGapC2;
        }

        $extSystems = [];
        foreach (($ir['systemsExt'] ?? []) as $item) {
            if (!empty($item['boundaryId'])) {
                continue;
            }
            $extSystems[] = $item;
        }
        $essW = (int)$ESS['w'];
        $essH = (int)$ESS['h'];
        $extHGap = 36.0;
        $coreMaxYC2 = max($bdryY + $bdryH, $lastBoundaryBottom);
        if (!empty($c2Boundaries) && count($orphanListFiltered) > 0) {
            $coreMaxYC2 = max($coreMaxYC2, $orphanBottomY);
        }
        $extBottomY = 0.0;
        $extStartXC2 = null;
        $pageWC2Draft = max(
            880.0,
            30.0 + (float)$CT2['w'] * 2.0 + 40.0,
            (float)$bdryX + (float)$bdryW + 80.0,
            (!empty($c2Boundaries) && count($orphanListFiltered) > 0) ? $ox + $maxOrphanW + 60.0 : 0.0
        );
        if (count($extSystems) > 0) {
            [$posExt, $extRowWC2,] = self::layoutUniformSingleRow(count($extSystems), (float)$essW, (float)$essH, $extHGap);
            $extRowYC2 = $coreMaxYC2 + self::vSpace(44.0);
            $extStartX = max(40.0, ($pageWC2Draft - $extRowWC2) / 2.0);
            $extStartXC2 = $extStartX;
            $pageWC2Draft = max($pageWC2Draft, $extStartX + $extRowWC2 + 60.0);
            $extBottomY = $extRowYC2 + (float)$essH;
            foreach ($extSystems as $ei => $item) {
                $parts[] = self::voWithDiagramBBox(
                    $bbox,
                    $HY,
                    'External Software System',
                    self::nid((string)$item['id']),
                    'A_NAME="' . self::escapeAttr((string)$item['name']) . '" B_DESCRIPTION="' . self::escapeAttr((string)$item['description']) . '"',
                    $extStartX + $posExt[$ei]['x'],
                    $extRowYC2 + $posExt[$ei]['y'],
                    (float)$essW,
                    (float)$essH
                );
            }
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

        $maxY = max($coreMaxYC2, $extBottomY);

        // Draw optional outer (system-level) boundary around internal C2 boundaries.
        if ($outerBoundary !== null && !empty($c2Boundaries)) {
            $outerPadX = 26.0;
            $outerPadTop = 34.0;
            $outerPadBottom = 28.0;
            $outerX = (float)$bdryX - $outerPadX;
            $outerY = (float)$bdryY - $outerPadTop;
            $outerW = (float)$bdryW + $outerPadX * 2.0;
            $outerH = max(120.0, ($lastBoundaryBottom - $bdryY) + $outerPadTop + $outerPadBottom);
            $outerTpl = 'System scope boundary';
            $outerSourceKind = (string)($outerBoundary['sourceKind'] ?? '');
            if ($outerSourceKind === 'Container_Boundary') {
                $outerTpl = 'Container scope boundary';
            }
            $outerXml = self::voWithDiagramBBox(
                $bbox,
                $HY,
                $outerTpl,
                'bdry-c2-outer',
                'A_NAME="' . self::escapeAttr((string)($outerBoundary['name'] ?? '')) . '"',
                $outerX,
                $outerY,
                $outerW,
                $outerH
            );
            array_unshift($parts, $outerXml);
        }

        $bbC2 = $bbox ?? [
            'minX' => (float)$bdryX,
            'minY' => (float)$personRowYC2,
            'maxX' => (float)$bdryX + (float)$bdryW,
            'maxY' => (float)$maxY,
        ];
        $titleXC2 = $bbC2['minX'];
        $titleYC2 = $frameTopC2;
        $parts[] = self::vo(
            $HY,
            'Containers diagram title',
            'tit-c2',
            'A_C2_DIAGRAM_NAME="' . self::escapeAttr($title) . '" B_C2_DIAGRAM_DESCRIPTION=""',
            $titleXC2,
            $titleYC2,
            $titDmC2['emitW'],
            $titDmC2['h']
        );

        $legGapC2 = self::vSpace(self::GAP_AFTER_DIAGRAM_TITLE);
        $legM = self::LEGEND_EDGE_MARGIN;
        $legXC2 = 0.0;
        $legYC2 = 0.0;
        if ($LEG) {
            $legXC2 = $bbC2['maxX'] - (float)(int)$LEG['w'];
            $legYC2 = $bbC2['maxY'] + $legGapC2;
            $parts[] = self::vo(
                $HY,
                'Legend',
                'legend',
                '',
                $legXC2,
                $legYC2,
                (int)$LEG['w'],
                (int)$LEG['h']
            );
        }

        $pageW = (int)ceil(max(
            $pageWC2Draft,
            $titleXC2 + (float)$titDmC2['emitW'] + $legM + 40.0,
            $LEG ? $legXC2 + (float)(int)$LEG['w'] + $legM + 40.0 : 0.0,
            880.0
        ));
        $pageH = (int)ceil(max(
            620.0,
            $maxY + 80.0,
            $LEG ? $legYC2 + (float)(int)$LEG['h'] + $legM + 40.0 : 0.0,
            $titleYC2 + (float)$titDmC2['h'] + $legM + 40.0
        ));

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

        $bbox = null;
        $titDmC3 = self::diagramTitleEmittedDims($HY, 'Components diagram title');
        $frameTopC3 = self::diagramTitleFrameTopY();
        $contentTopC3 = $frameTopC3 + (float)$titDmC3['h'] + self::vSpace(self::GAP_AFTER_DIAGRAM_TITLE);
        $pnListC3 = $ir['persons'] ?? [];
        $phMaxPersonC3 = 0;
        foreach ($pnListC3 as $pq) {
            if (!is_array($pq)) {
                continue;
            }
            $pk = !empty($pq['external']) ? 'External Person' : 'Person';
            $phMaxPersonC3 = max($phMaxPersonC3, (int)$HY[$pk]['h']);
        }
        $personRowYC3 = $contentTopC3;
        $bdryX = 180;
        $bdryY = count($pnListC3) > 0
            ? $personRowYC3 + (float)$phMaxPersonC3 + self::vSpace(28.0)
            : $contentTopC3;

        $pad = 58;
        $bdryInnerTop = 55.0;
        $bdryContentTopInset = ($bdryInnerTop + (float)$pad) / 2.0;
        $vGap = self::vSpace(34.0);
        $hGap = 40;
        $nb = count($inBoundary);
        /** @var list<array{tpl:string,w:int,h:int}> $inBoundaryMeta */
        $inBoundaryMeta = [];
        /** @var list<array{0:int,1:int}> $compWhList */
        $compWhList = [];
        foreach ($inBoundary as $c) {
            $tpl = self::c3ComponentTemplateKey($HY, $c);
            $dim = $HY[$tpl];
            $w = (int)$dim['w'];
            $h = (int)$dim['h'];
            $inBoundaryMeta[] = ['tpl' => $tpl, 'w' => $w, 'h' => $h];
            $compWhList[] = [$w, $h];
        }
        /** @var list<array{x:float,y:float,w:int,h:int}> $posComp */
        $posComp = [];
        $contentLeft = 0.0;
        $contentTop = 0.0;
        $compGridH = 0;
        if ($nb > 0) {
            [$posComp, $gridW, $gridH] = self::layoutRectGrid($compWhList, $hGap, $vGap);
            $bdryW = max((int)$Bdims['w'], (int)ceil($pad * 2 + $gridW));
            $bdryH = max((int)$Bdims['h'], (int)ceil($bdryContentTopInset + $gridH + $pad));
            $contentLeft = $bdryX + ($bdryW - $gridW) / 2;
            $contentTop = $bdryY + $bdryContentTopInset;
            $compGridH = $gridH;
        } else {
            $compW = (int)$COMP['w'];
            $compH = (int)$COMP['h'];
            $bdryW = max((int)$Bdims['w'], $compW + $pad * 2);
            $bdryH = max((int)$Bdims['h'], (int)ceil($bdryContentTopInset + $compH + $pad));
        }

        $orphanGap = 56;
        $colW = max((int)$CN['w'], (int)$DC['w']);
        /** @var list<array{0:int,1:int}> $orphWhList */
        $orphWhList = [];
        foreach ($orphanContainers as $c) {
            $orphWhList[] = [(int)$CN['w'], (int)$CN['h']];
        }
        foreach ($orphanDbs as $db) {
            $orphWhList[] = [(int)$DC['w'], (int)$DC['h']];
        }
        if (count($orphWhList) > 0) {
            [$posOr, $orphGridW, $orphTotalH] = self::layoutRectGrid($orphWhList, $hGap, $vGap);
        } else {
            $posOr = [];
            $orphGridW = (float)$colW;
            $orphTotalH = 0.0;
        }
        $ox = $bdryX - $orphGridW - $orphanGap;
        if ($ox < 30) {
            $ox = 30;
        }
        $oy0 = $bdryY + $bdryContentTopInset;
        $gi = 0;
        foreach ($orphanContainers as $c) {
            $p = $posOr[$gi++];
            $parts[] = self::voWithDiagramBBox(
                $bbox,
                $HY,
                'Container',
                self::nid((string)$c['id']),
                'A_NAME="' . self::escapeAttr((string)$c['name']) . '" B_TECHNOLOGY="' . self::escapeAttr((string)$c['technology']) . '" C_DESCRIPTION="' . self::escapeAttr((string)$c['description']) . '"',
                $ox + $p['x'],
                $oy0 + $p['y'],
                $p['w'],
                $p['h']
            );
        }
        foreach ($orphanDbs as $db) {
            $p = $posOr[$gi++];
            $parts[] = self::voWithDiagramBBox(
                $bbox,
                $HY,
                'Data Container',
                self::nid((string)$db['id']),
                'A_NAME="' . self::escapeAttr((string)$db['name']) . '" B_TECHNOLOGY="' . self::escapeAttr((string)$db['technology']) . '" C_DESCRIPTION="' . self::escapeAttr((string)$db['description']) . '"',
                $ox + $p['x'],
                $oy0 + $p['y'],
                $p['w'],
                $p['h']
            );
        }

        if ($boundary) {
            $parts[] = self::voWithDiagramBBox(
                $bbox,
                $HY,
                $bdryTplKey,
                'bdry-main',
                'A_NAME="' . self::escapeAttr((string)($boundary['name'] ?? '')) . '"',
                (float)$bdryX,
                (float)$bdryY,
                (float)$bdryW,
                (float)$bdryH
            );
        }

        $cy = $bdryY + $bdryContentTopInset;
        if ($nb > 0) {
            foreach ($inBoundary as $ci => $c) {
                $tpl = $inBoundaryMeta[$ci]['tpl'];
                $parts[] = self::voWithDiagramBBox(
                    $bbox,
                    $HY,
                    $tpl,
                    self::nid((string)$c['id']),
                    'A_NAME="' . self::escapeAttr((string)$c['name']) . '" B_TECHNOLOGY="' . self::escapeAttr((string)$c['technology']) . '" C_DESCRIPTION="' . self::escapeAttr((string)$c['description']) . '"',
                    $contentLeft + $posComp[$ci]['x'],
                    $contentTop + $posComp[$ci]['y'],
                    (float)$posComp[$ci]['w'],
                    (float)$posComp[$ci]['h']
                );
            }
            $cy = $contentTop + $compGridH;
        }

        $personGapC3 = 28.0;
        $rowLeftC3 = (float)$bdryX;
        $rowRightC3 = $bdryX + (float)$bdryW;
        if (count($orphWhList) > 0) {
            $rowLeftC3 = min($rowLeftC3, (float)$ox);
            $rowRightC3 = max($rowRightC3, $ox + $orphGridW);
        }
        $personBandWC3 = self::personBandTotalWidth($pnListC3, $HY, $personGapC3);
        $person0XC3 = count($pnListC3) > 0
            ? self::personRowStartXCenteredOnSpan($rowLeftC3, $rowRightC3, $personBandWC3)
            : 40.0;
        $pxAcc = $person0XC3;
        foreach ($pnListC3 as $p) {
            if (!is_array($p)) {
                continue;
            }
            $key = !empty($p['external']) ? 'External Person' : 'Person';
            $dim = $HY[$key];
            $pw0 = (int)$dim['w'];
            $ph0 = (int)$dim['h'];
            $parts[] = self::voWithDiagramBBox(
                $bbox,
                $HY,
                $key,
                self::nid((string)$p['id']),
                'A_NAME="' . self::escapeAttr((string)$p['name']) . '" B_DESCRIPTION="' . self::escapeAttr((string)$p['description']) . '"',
                $pxAcc,
                $personRowYC3,
                (float)$pw0,
                (float)$ph0
            );
            $pxAcc += $pw0 + $personGapC3;
        }

        $coreMaxYC3 = $bdryY + $bdryH;
        if ($nb > 0) {
            $coreMaxYC3 = max($coreMaxYC3, $cy - $vGap);
        }
        if (count($orphWhList) > 0) {
            $coreMaxYC3 = max($coreMaxYC3, $oy0 + $orphTotalH);
        }

        $extSystemsC3 = $ir['systemsExt'] ?? [];
        $essW3 = (int)$ESS['w'];
        $essH3 = (int)$ESS['h'];
        $extHGap3 = 36.0;
        $extBottomY3 = 0.0;
        $extRowWC3 = 0.0;
        $extStartX3Saved = null;
        if (is_array($extSystemsC3) && count($extSystemsC3) > 0) {
            [$posExt3, $extRowWC3,] = self::layoutUniformSingleRow(count($extSystemsC3), (float)$essW3, (float)$essH3, $extHGap3);
            $extRowYC3 = $coreMaxYC3 + self::vSpace(44.0);
            $pageW0C3 = max(
                880.0,
                30.0 + (float)$CT['w'] * 2.0 + 40.0,
                (float)$bdryX + (float)$bdryW + 80.0,
                (count($orphWhList) > 0) ? $ox + $orphGridW + 60.0 : 0.0
            );
            $extStartX3 = max(40.0, ($pageW0C3 - $extRowWC3) / 2.0);
            $extStartX3Saved = $extStartX3;
            $pageWC3Draft = max($pageW0C3, $extStartX3 + $extRowWC3 + 60.0);
            $extBottomY3 = $extRowYC3 + (float)$essH3;
            foreach ($extSystemsC3 as $ei => $item) {
                if (!is_array($item)) {
                    continue;
                }
                $parts[] = self::voWithDiagramBBox(
                    $bbox,
                    $HY,
                    'External Software System',
                    self::nid((string)$item['id']),
                    'A_NAME="' . self::escapeAttr((string)$item['name']) . '" B_DESCRIPTION="' . self::escapeAttr((string)$item['description']) . '"',
                    $extStartX3 + $posExt3[$ei]['x'],
                    $extRowYC3 + $posExt3[$ei]['y'],
                    (float)$essW3,
                    (float)$essH3
                );
            }
        } else {
            $pageWC3Draft = max(
                880.0,
                30.0 + (float)$CT['w'] * 2.0 + 40.0,
                (float)$bdryX + (float)$bdryW + 80.0,
                (count($orphWhList) > 0) ? $ox + $orphGridW + 60.0 : 0.0
            );
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

        $maxY = $coreMaxYC3;
        if (is_array($extSystemsC3) && count($extSystemsC3) > 0) {
            $maxY = max($maxY, $extBottomY3);
        }

        $bbC3 = $bbox ?? [
            'minX' => (float)$bdryX,
            'minY' => (float)$personRowYC3,
            'maxX' => (float)$bdryX + (float)$bdryW,
            'maxY' => (float)$maxY,
        ];
        $titleXC3 = $bbC3['minX'];
        $titleYC3 = $frameTopC3;
        $parts[] = self::vo(
            $HY,
            'Components diagram title',
            'tit-c3',
            'A_C3_DIAGRAM_NAME="' . self::escapeAttr($title) . '" B_C3_DIAGRAM_DESCRIPTION=""',
            $titleXC3,
            $titleYC3,
            $titDmC3['emitW'],
            $titDmC3['h']
        );

        $legGapC3 = self::vSpace(self::GAP_AFTER_DIAGRAM_TITLE);
        $legM = self::LEGEND_EDGE_MARGIN;
        $legXC3 = 0.0;
        $legYC3 = 0.0;
        if ($LEG) {
            $legXC3 = $bbC3['maxX'] - (float)(int)$LEG['w'];
            $legYC3 = $bbC3['maxY'] + $legGapC3;
            $parts[] = self::vo(
                $HY,
                'Legend',
                'legend',
                '',
                $legXC3,
                $legYC3,
                (int)$LEG['w'],
                (int)$LEG['h']
            );
        }

        $pageW = (int)ceil(max(
            $pageWC3Draft,
            $titleXC3 + (float)$titDmC3['emitW'] + $legM + 40.0,
            $LEG ? $legXC3 + (float)(int)$LEG['w'] + $legM + 40.0 : 0.0,
            880.0
        ));
        $pageH = (int)ceil(max(
            620.0,
            $maxY + 80.0,
            $LEG ? $legYC3 + (float)(int)$LEG['h'] + $legM + 40.0 : 0.0,
            $titleYC3 + (float)$titDmC3['h'] + $legM + 40.0
        ));

        return self::mxfile($title !== '' ? $title : 'C4 Components', 'c4-components-auto', $pageW, $pageH, implode('', $parts));
    }
}

