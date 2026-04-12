<?php

declare(strict_types=1);

final class ParseMermaidC4
{
    public static function parse(string $text): array
    {
        $raw = self::stripFencesAndComments(self::normalizeQuotesAndBom($text));
        $lines = preg_split("/\n/", $raw) ?: [];
        $lines = array_values(array_filter(array_map(static fn($l) => trim((string)$l), $lines), static fn($l) => $l !== ''));

        if (count($lines) === 0) {
            throw new RuntimeException('Empty input.');
        }

        // Mermaid C4 starts with: C4Context / C4Container / C4Component / ...
        $first = $lines[0] ?? '';
        $diagram = '';
        if (preg_match('/^(C4\w+)\b/', $first, $m)) {
            $diagram = (string)($m[1] ?? '');
            array_shift($lines);
        } elseif (preg_match('/^mermaid\b/i', $first)) {
            // Sometimes users paste just "mermaid" without fences
            array_shift($lines);
            $first2 = $lines[0] ?? '';
            if (preg_match('/^(C4\w+)\b/', $first2, $m2)) {
                $diagram = (string)($m2[1] ?? '');
                array_shift($lines);
            }
        }

        if ($diagram === '') {
            throw new RuntimeException('Mermaid C4 input must start with C4Context / C4Container / C4Component.');
        }

        $ir = [
            'title' => '',
            'persons' => [],
            'systems' => [],
            'systemsExt' => [],
            'databases' => [],
            'components' => [],
            'containers' => [],
            'containerDbs' => [],
            'c2Standalone' => [],
            'boundaries' => [],
            'rels' => [],
        ];

        // Stack of boundary contexts (nested). Boundaries that should appear in IR are emitted on closing '}'.
        $boundaryStack = [];

        for ($i = 0; $i < count($lines); $i++) {
            $line = $lines[$i];
            if (preg_match('/^title\s+(.+)$/i', $line, $tm)) {
                $ir['title'] = trim((string)$tm[1]);
                continue;
            }

            if ($line === '}') {
                if (!empty($boundaryStack)) {
                    $ctx = array_pop($boundaryStack);
                    if (($ctx['emit'] ?? false) && isset($ctx['boundary']) && is_array($ctx['boundary'])) {
                        $ir['boundaries'][] = $ctx['boundary'];
                    }
                }
                continue;
            }

            // Boundary starts (may be nested)
            if (preg_match('/^(\w+)\s*\(([\s\S]*?)\)\s*\{\s*$/', $line, $bm)) {
                $bKind = (string)($bm[1] ?? '');
                $args = self::parseArgs((string)($bm[2] ?? ''));
                $bid = (string)($args[0] ?? '');
                $bname = (string)($args[1] ?? $bid);

                $isEmitBoundary = (
                    $bKind === 'Container_Boundary'
                    || $bKind === 'System_Boundary'
                    || $bKind === 'Enterprise_Boundary'
                    || $bKind === 'Boundary'
                );
                $boundary = null;
                if ($isEmitBoundary) {
                    $boundaryKind = 'C1B';
                    if ($bKind === 'Container_Boundary') {
                        $boundaryKind = 'C2';
                    } elseif ($bKind === 'System_Boundary') {
                        $boundaryKind = 'C3';
                    }
                    $boundary = [
                        'id' => $bid,
                        'name' => $bname !== '' ? $bname : $bid,
                        'sourceKind' => $bKind,
                        'componentIds' => [],
                        'items' => [],
                        'memberIds' => [],
                        'kind' => $boundaryKind,
                    ];
                }

                if ($isEmitBoundary) {
                    // Register nested boundary id as a member of the nearest emitting ancestor boundary (if any),
                    // so outer boxes can wrap inner boundaries too.
                    for ($pi = count($boundaryStack) - 1; $pi >= 0; $pi--) {
                        if (!empty($boundaryStack[$pi]['emit']) && isset($boundaryStack[$pi]['boundary']) && is_array($boundaryStack[$pi]['boundary'])) {
                            $boundaryStack[$pi]['boundary']['memberIds'][] = $bid;
                            break;
                        }
                    }
                }

                $boundaryStack[] = [
                    'kind' => $bKind,
                    'id' => $bid,
                    'emit' => $isEmitBoundary,
                    'boundary' => $boundary,
                ];
                continue;
            }

            if (preg_match('/^(\w+)\s*\(([\s\S]*?)\)\s*$/', $line, $call)) {
                $kind = (string)($call[1] ?? '');
                $args = self::parseArgs((string)($call[2] ?? ''));

                $activeBoundaryId = null;
                $activeBoundaryIdx = null;
                for ($si = count($boundaryStack) - 1; $si >= 0; $si--) {
                    if (!empty($boundaryStack[$si]['emit'])) {
                        $activeBoundaryId = (string)($boundaryStack[$si]['id'] ?? null);
                        $activeBoundaryIdx = $si;
                        break;
                    }
                }

                if ($kind === 'Person' || $kind === 'Person_Ext') {
                    $id = (string)($args[0] ?? '');
                    if ($id !== '') {
                        $ir['persons'][] = [
                            'id' => $id,
                            'name' => (string)($args[1] ?? $id),
                            // Mermaid/C4-PlantUML allows optional description; 2-arg form is common in docs.
                            'description' => (string)($args[2] ?? ''),
                            'external' => $kind === 'Person_Ext',
                            'boundaryId' => $activeBoundaryId,
                        ];
                        if ($activeBoundaryIdx !== null && !empty($boundaryStack[$activeBoundaryIdx]['emit'])) {
                            $boundaryStack[$activeBoundaryIdx]['boundary']['memberIds'][] = $id;
                        }
                    }
                    continue;
                }

                if ($kind === 'System') {
                    $id = (string)($args[0] ?? '');
                    if ($id !== '') {
                        $ir['systems'][] = [
                            'id' => $id,
                            'name' => (string)($args[1] ?? $id),
                            'description' => (string)($args[2] ?? ''),
                            'boundaryId' => $activeBoundaryId,
                        ];
                        if ($activeBoundaryIdx !== null && !empty($boundaryStack[$activeBoundaryIdx]['emit'])) {
                            $boundaryStack[$activeBoundaryIdx]['boundary']['memberIds'][] = $id;
                        }
                    }
                    continue;
                }

                if ($kind === 'System_Ext' || $kind === 'SystemDb_Ext' || $kind === 'SystemQueue_Ext') {
                    $id = (string)($args[0] ?? '');
                    if ($id !== '') {
                        $ir['systemsExt'][] = [
                            'id' => $id,
                            'name' => (string)($args[1] ?? $id),
                            'description' => (string)($args[2] ?? ''),
                            'boundaryId' => $activeBoundaryId,
                        ];
                        if ($activeBoundaryIdx !== null && !empty($boundaryStack[$activeBoundaryIdx]['emit'])) {
                            $boundaryStack[$activeBoundaryIdx]['boundary']['memberIds'][] = $id;
                        }
                    }
                    continue;
                }

                if ($kind === 'SystemDb') {
                    $id = (string)($args[0] ?? '');
                    if ($id !== '') {
                        $ir['databases'][] = ['id' => $id, 'name' => (string)($args[1] ?? $id), 'description' => (string)($args[2] ?? ''), 'boundaryId' => $activeBoundaryId];
                        if ($activeBoundaryIdx !== null && !empty($boundaryStack[$activeBoundaryIdx]['emit'])) {
                            $boundaryStack[$activeBoundaryIdx]['boundary']['memberIds'][] = $id;
                        }
                    }
                    continue;
                }

                if ($kind === 'SystemQueue') {
                    $id = (string)($args[0] ?? '');
                    if ($id !== '') {
                        $ir['systems'][] = [
                            'id' => $id,
                            'name' => (string)($args[1] ?? $id),
                            'description' => (string)($args[2] ?? ''),
                            'boundaryId' => $activeBoundaryId,
                        ];
                        if ($activeBoundaryIdx !== null && !empty($boundaryStack[$activeBoundaryIdx]['emit'])) {
                            $boundaryStack[$activeBoundaryIdx]['boundary']['memberIds'][] = $id;
                        }
                    }
                    continue;
                }

                if ($kind === 'Component' || $kind === 'Component_Ext' || $kind === 'ComponentDb' || $kind === 'ComponentQueue' || $kind === 'ComponentDb_Ext' || $kind === 'ComponentQueue_Ext') {
                    $id = (string)($args[0] ?? '');
                    if ($id !== '') {
                        $ir['components'][] = [
                            'id' => $id,
                            'name' => (string)($args[1] ?? $id),
                            'technology' => (string)($args[2] ?? ''),
                            'description' => (string)($args[3] ?? ''),
                            'boundaryId' => $activeBoundaryId,
                        ];
                        if ($activeBoundaryIdx !== null && !empty($boundaryStack[$activeBoundaryIdx]['emit'])) {
                            $boundaryStack[$activeBoundaryIdx]['boundary']['componentIds'][] = $id;
                            $boundaryStack[$activeBoundaryIdx]['boundary']['memberIds'][] = $id;
                        }
                    }
                    continue;
                }

                if ($kind === 'Container' || $kind === 'Container_Ext' || $kind === 'ContainerQueue' || $kind === 'ContainerQueue_Ext') {
                    $id = (string)($args[0] ?? '');
                    if ($id !== '') {
                        $rowKind = ($kind === 'ContainerQueue' || $kind === 'ContainerQueue_Ext') ? 'queue' : 'container';
                        $row = [
                            'kind' => $rowKind,
                            'id' => $id,
                            'name' => (string)($args[1] ?? $id),
                            'technology' => (string)($args[2] ?? ''),
                            'description' => (string)($args[3] ?? ''),
                        ];
                        $ir['containers'][] = [
                            'id' => $id,
                            'name' => $row['name'],
                            'technology' => $row['technology'],
                            'description' => $row['description'],
                            'boundaryId' => $activeBoundaryId,
                        ];
                        if ($activeBoundaryIdx !== null && !empty($boundaryStack[$activeBoundaryIdx]['emit'])) {
                            $boundaryStack[$activeBoundaryIdx]['boundary']['items'][] = $row;
                            $boundaryStack[$activeBoundaryIdx]['boundary']['memberIds'][] = $id;
                        } else {
                            $ir['c2Standalone'][] = $row;
                        }
                    }
                    continue;
                }

                if ($kind === 'ContainerDb' || $kind === 'ContainerDb_Ext') {
                    $id = (string)($args[0] ?? '');
                    if ($id !== '') {
                        $row = [
                            'kind' => 'db',
                            'id' => $id,
                            'name' => (string)($args[1] ?? $id),
                            'technology' => (string)($args[2] ?? ''),
                            'description' => (string)($args[3] ?? ''),
                        ];
                        $ir['containerDbs'][] = [
                            'id' => $id,
                            'name' => $row['name'],
                            'technology' => $row['technology'],
                            'description' => $row['description'],
                            'boundaryId' => $activeBoundaryId,
                        ];
                        if ($activeBoundaryIdx !== null && !empty($boundaryStack[$activeBoundaryIdx]['emit'])) {
                            $boundaryStack[$activeBoundaryIdx]['boundary']['items'][] = $row;
                            $boundaryStack[$activeBoundaryIdx]['boundary']['memberIds'][] = $id;
                        } else {
                            $ir['c2Standalone'][] = $row;
                        }
                    }
                    continue;
                }

                if ($kind === 'Rel' || $kind === 'BiRel' || $kind === 'RelIndex' || $kind === 'Rel_Back' || str_starts_with($kind, 'Rel_')) {
                    $r = self::parseRelFromArgs($args);
                    if ($r) $ir['rels'][] = $r;
                    continue;
                }

                // Mermaid-only helpers that we ignore for draw.io (layout/style/etc.)
                if ($kind === 'UpdateRelStyle' || $kind === 'UpdateElementStyle' || $kind === 'UpdateLayoutConfig') continue;
                if ($kind === 'Lay_U' || $kind === 'Lay_Up' || $kind === 'Lay_D' || $kind === 'Lay_Down' || $kind === 'Lay_L' || $kind === 'Lay_Left' || $kind === 'Lay_R' || $kind === 'Lay_Right') continue;

                // Deployment/Node macros are ignored (not supported by our draw.io emitter yet).
                if ($kind === 'Deployment_Node' || $kind === 'Node' || $kind === 'Node_L' || $kind === 'Node_R') continue;
            }
        }

        // Prefer the diagram type from Mermaid header
        $ir['level'] =
            strcasecmp($diagram, 'C4Component') === 0 ? 'C3' :
            (strcasecmp($diagram, 'C4Container') === 0 ? 'C2' :
            (strcasecmp($diagram, 'C4Context') === 0 ? 'C1' : 'C1'));

        self::reconcileBoundaryKinds($ir);

        return $ir;
    }

    /**
     * System_Boundary is emitted with kind C3 by default, but C4-PlantUML/Mermaid
     * often uses it for container diagrams too. EmitDrawio C2 path only reads
     * boundaries with kind C2 — reclassify when the boundary holds containers/dbs.
     *
     * @param array $ir by reference from parse()
     */
    private static function reconcileBoundaryKinds(array &$ir): void
    {
        foreach (($ir['boundaries'] ?? []) as $i => $b) {
            if (!is_array($b)) {
                continue;
            }
            if (($b['kind'] ?? null) !== 'C3') {
                continue;
            }
            $compIds = $b['componentIds'] ?? [];
            if (is_array($compIds) && count($compIds) > 0) {
                continue;
            }
            $items = $b['items'] ?? [];
            if (!is_array($items) || count($items) === 0) {
                continue;
            }
            $hasC2Item = false;
            foreach ($items as $it) {
                if (!is_array($it)) {
                    continue;
                }
                $k = (string)($it['kind'] ?? '');
                if ($k === 'container' || $k === 'db' || $k === 'queue') {
                    $hasC2Item = true;
                    break;
                }
            }
            if ($hasC2Item) {
                $ir['boundaries'][$i]['kind'] = 'C2';
            }
        }
    }

    private static function normalizeQuotesAndBom(string $text): string
    {
        $s = preg_replace('/^\x{FEFF}/u', '', $text) ?? $text;
        $s = str_replace(["\r\n", "\r"], ["\n", "\n"], $s);
        $curly = "\u{201C}\u{201D}\u{201E}\u{201F}\u{00AB}\u{00BB}\u{2039}\u{203A}";
        $re = '/[' . preg_quote($curly, '/') . ']/u';
        return preg_replace($re, '"', $s) ?? $s;
    }

    private static function stripFencesAndComments(string $text): string
    {
        $out = [];
        foreach (preg_split("/\n/", $text) ?: [] as $l) {
            $t = trim((string)$l);
            if ($t === '') { $out[] = ''; continue; }
            if (str_starts_with($t, '```')) { $out[] = ''; continue; } // code fences
            if (str_starts_with($t, '%%')) { $out[] = ''; continue; } // mermaid comment
            $out[] = (string)$l;
        }
        return implode("\n", $out);
    }

    /**
     * Mermaid args:
     * - separated by commas
     * - strings may be quoted with escapes
     * - may contain named params like $tags=... which we ignore
     *
     * @return string[]
     */
    private static function parseArgs(string $inner): array
    {
        $s = trim($inner);
        if ($s === '') return [];

        $out = [];
        $buf = '';
        $inQ = false;
        $esc = false;
        $len = strlen($s);
        for ($i = 0; $i < $len; $i++) {
            $ch = $s[$i];
            if ($esc) { $buf .= $ch; $esc = false; continue; }
            if ($ch === '\\') { $buf .= $ch; $esc = true; continue; }
            if ($ch === '"') { $buf .= $ch; $inQ = !$inQ; continue; }
            if (!$inQ && $ch === ',') {
                $t = trim($buf);
                if ($t !== '' && $t[0] !== '$') $out[] = self::tokenToString($t);
                $buf = '';
                continue;
            }
            $buf .= $ch;
        }
        $t = trim($buf);
        if ($t !== '' && $t[0] !== '$') $out[] = self::tokenToString($t);
        return $out;
    }

    private static function tokenToString(string $t): string
    {
        $x = trim($t);
        if ($x === '') return '';
        if ($x[0] === '"' && substr($x, -1) === '"') {
            $mid = substr($x, 1, -1);
            return self::unescapeStr($mid);
        }
        return self::unescapeStr($x);
    }

    private static function parseRelFromArgs(array $args): ?array
    {
        $from = (string)($args[0] ?? '');
        $to = (string)($args[1] ?? '');
        // Rel(from, to, label, ?techn, ?descr, ...) — we map optional 4th arg to technology when present.
        $desc = (string)($args[2] ?? '');
        if ($from === '' || $to === '') return null;
        if ($desc === '') {
            // 2-arg Rel is unusual but appears in some snippets; treat as empty label.
            $desc = '';
        }
        $tech = isset($args[3]) ? (string)$args[3] : null;
        return ['from' => $from, 'to' => $to, 'description' => $desc, 'technology' => $tech];
    }

    private static function unescapeStr(string $s): string
    {
        $s = str_replace('\\"', '"', $s);
        return str_replace('\\n', "\n", $s);
    }
}

