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
        return str_replace('"', '&quot;', $s);
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
        $parts = [];
        $SS = $HY['Software System'];
        $ESS = $HY['External Software System'];
        $DC = $HY['Data Container'];
        $LEG = $HY['Legend'] ?? null;
        $T1 = $HY['System Context diagram title'];

        $title = (string)($ir['title'] ?? '');

        $parts[] = self::vo(
            $HY,
            'System Context diagram title',
            'tit-ctx',
            'A_C1_DIAGRAM_NAME="' . self::escapeAttr($title) . '" B_C1_DIAGRAM_DESCRIPTION=""',
            -40,
            220,
            ((int)($T1['w'] ?? 240)) * 2,
            (int)$T1['h']
        );

        $gap = 5;
        $pw = (int)($HY['Person']['w'] ?? 180);
        $x0 = -30;
        foreach (($ir['persons'] ?? []) as $i => $p) {
            $key = !empty($p['external']) ? 'External Person' : 'Person';
            $dim = $HY[$key];
            $parts[] = self::vo(
                $HY,
                $key,
                self::nid((string)$p['id']),
                'A_NAME="' . self::escapeAttr((string)$p['name']) . '" B_DESCRIPTION="' . self::escapeAttr((string)$p['description']) . '"',
                $x0 + $i * ($pw + $gap),
                320,
                (int)$dim['w'],
                (int)$dim['h']
            );
        }

        $n = count($ir['persons'] ?? []);
        $bandRight = $n > 0 ? $x0 + $n * $pw + ($n - 1) * $gap : $x0 + $pw;
        $centerX = $n > 0 ? ($x0 + $bandRight) / 2 - ((int)$SS['w']) / 2 : 200;
        foreach (($ir['systems'] ?? []) as $s) {
            $parts[] = self::vo(
                $HY,
                'Software System',
                self::nid((string)$s['id']),
                'A_NAME="' . self::escapeAttr((string)$s['name']) . '" B_DESCRIPTION="' . self::escapeAttr((string)$s['description']) . '"',
                $centerX,
                530,
                (int)$SS['w'],
                (int)$SS['h']
            );
        }

        $bottomGap = 30;
        $bx = -200;
        $bottomY = 960;
        foreach (($ir['systemsExt'] ?? []) as $item) {
            $parts[] = self::vo(
                $HY,
                'External Software System',
                self::nid((string)$item['id']),
                'A_NAME="' . self::escapeAttr((string)$item['name']) . '" B_DESCRIPTION="' . self::escapeAttr((string)$item['description']) . '"',
                $bx,
                $bottomY,
                (int)$ESS['w'],
                (int)$ESS['h']
            );
            $bx += (int)$ESS['w'] + $bottomGap;
        }

        foreach (($ir['databases'] ?? []) as $db) {
            $split = self::splitDbDescription((string)($db['description'] ?? ''));
            $parts[] = self::vo(
                $HY,
                'Data Container',
                self::nid((string)$db['id']),
                'A_NAME="' . self::escapeAttr((string)$db['name']) . '" B_TECHNOLOGY="' . self::escapeAttr($split['tech']) . '" C_DESCRIPTION="' . self::escapeAttr($split['body']) . '"',
                $bx,
                $bottomY - 10,
                (int)$DC['w'],
                (int)$DC['h']
            );
            $bx += (int)$DC['w'] + $bottomGap;
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

        if ($LEG) {
            $parts[] = self::vo(
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

        return self::mxfile($title !== '' ? $title : 'C4 Context', 'c4-context-auto', 1100, 1280, implode('', $parts));
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

    private static function emitC2(array $ir, array $HY): string
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
        foreach ($innerItems as $it) {
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
        if ($c2Boundary && !empty($ir['c2Standalone'])) {
            foreach (($ir['c2Standalone'] ?? []) as $it) {
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
        if ($c2Boundary && !empty($ir['c2Standalone'])) $maxY = max($maxY, $oy - $vGap);

        $pageW = max(
            880,
            30 + (int)$CT2['w'] * 2 + 40,
            $bdryX + $bdryW + 40 + (int)$ESS['w'] + 80,
            ($c2Boundary && !empty($ir['c2Standalone'])) ? $ox + $colW + 60 : 0
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
        $SB = $HY['System scope boundary'];
        $CT = $HY['Components diagram title'];
        $LEG = $HY['Legend'] ?? null;

        $title = (string)($ir['title'] ?? '');

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

        $pad = 50;
        $compW = (int)$COMP['w'];
        $compH = (int)$COMP['h'];
        $vGap = 24;
        $nb = max(1, count($inBoundary));
        $bdryW = max((int)$SB['w'], $compW + $pad * 2);
        $bdryH = max((int)$SB['h'], $pad + $nb * $compH + ($nb - 1) * $vGap + $pad);
        $bdryX = 180;
        $bdryY = 60;

        if ($boundary) {
            $parts[] = self::vo(
                $HY,
                'System scope boundary',
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

        $pageW = max(880, 30 + (int)$CT['w'] * 2 + 40, $bdryX + $bdryW + 40 + (int)$ESS['w'] + 40);
        $pageH = max(620, $bdryY + $bdryH + 80);

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

