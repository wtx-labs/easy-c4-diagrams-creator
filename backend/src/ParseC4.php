<?php

declare(strict_types=1);

final class ParseC4
{
    private const Q = '"((?:\\\\.|[^"\\\\])*)"';

    public static function parse(string $text): array
    {
        $raw = self::stripCommentsAndInclude(self::normalizeQuotesAndBom($text));

        if (!preg_match('/@startuml([\s\S]*?)@enduml/i', $raw, $m)) {
            throw new RuntimeException('Missing @startuml ... @enduml block.');
        }

        $body = $m[1];
        $lines = preg_split("/\n/", $body) ?: [];
        $lines = array_values(array_filter(array_map(static fn($l) => trim((string)$l), $lines), static fn($l) => $l !== ''));

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

        for ($li = 0; $li < count($lines); $li++) {
            $line = $lines[$li];

            if (preg_match('/^title\s+(.+)$/i', $line, $tm)) {
                $ir['title'] = trim($tm[1]);
                continue;
            }

            if (preg_match('/^(\w+)\s*\(([\s\S]*)$/', $line, $call) && strpos($line, '{') === false) {
                $kind = $call[1];
                $rest = $call[2];
                if (!preg_match('/^([\s\S]*)\)\s*$/', $rest, $innerM)) {
                    continue;
                }
                $inner = $innerM[1];

                if ($kind === 'Person' || $kind === 'Person_Ext') {
                    $p = self::parseStrings3($inner);
                    if ($p) {
                        $ir['persons'][] = [
                            'id' => $p['id'],
                            'name' => $p['a'],
                            'description' => $p['b'],
                            'external' => $kind === 'Person_Ext',
                        ];
                    }
                    continue;
                }

                if ($kind === 'System') {
                    $p = self::parseStrings3($inner);
                    if ($p) {
                        $ir['systems'][] = ['id' => $p['id'], 'name' => $p['a'], 'description' => $p['b']];
                    }
                    continue;
                }

                if ($kind === 'System_Ext') {
                    $p = self::parseStrings3($inner);
                    if ($p) {
                        $ir['systemsExt'][] = ['id' => $p['id'], 'name' => $p['a'], 'description' => $p['b']];
                    }
                    continue;
                }

                if ($kind === 'SystemDb') {
                    $p = self::parseStrings3($inner);
                    if ($p) {
                        $ir['databases'][] = ['id' => $p['id'], 'name' => $p['a'], 'description' => $p['b']];
                    }
                    continue;
                }

                if ($kind === 'Component') {
                    $p = self::parseStrings4($inner);
                    if ($p) {
                        $ir['components'][] = [
                            'id' => $p['id'],
                            'name' => $p['a'],
                            'technology' => $p['b'],
                            'description' => $p['c'],
                            'boundaryId' => null,
                        ];
                    }
                    continue;
                }

                if ($kind === 'Container') {
                    $p = self::parseStrings4($inner);
                    if ($p) {
                        $ir['containers'][] = [
                            'id' => $p['id'],
                            'name' => $p['a'],
                            'technology' => $p['b'],
                            'description' => $p['c'],
                            'boundaryId' => null,
                        ];
                        $ir['c2Standalone'][] = [
                            'kind' => 'container',
                            'id' => $p['id'],
                            'name' => $p['a'],
                            'technology' => $p['b'],
                            'description' => $p['c'],
                        ];
                    }
                    continue;
                }

                if ($kind === 'ContainerDb') {
                    $p = self::parseStrings4($inner);
                    if ($p) {
                        $ir['containerDbs'][] = [
                            'id' => $p['id'],
                            'name' => $p['a'],
                            'technology' => $p['b'],
                            'description' => $p['c'],
                            'boundaryId' => null,
                        ];
                        $ir['c2Standalone'][] = [
                            'kind' => 'db',
                            'id' => $p['id'],
                            'name' => $p['a'],
                            'technology' => $p['b'],
                            'description' => $p['c'],
                        ];
                    }
                    continue;
                }

                if ($kind === 'Rel') {
                    $r = self::parseRel($inner);
                    if ($r) {
                        $ir['rels'][] = $r;
                    }
                    continue;
                }
            }

            $bRe = '/^System_Boundary\s*\(\s*(\w+)\s*,\s*"((?:\\\\.|[^"\\\\])*)"\s*\)\s*\{\s*$/';
            if (preg_match($bRe, $line, $bm)) {
                $bid = $bm[1];
                $bname = self::unescapeStr($bm[2]);
                $boundary = [
                    'id' => $bid,
                    'name' => $bname,
                    'componentIds' => [],
                    'items' => [],
                    'kind' => 'empty',
                ];

                $j = $li + 1;
                $innerLines = [];
                while ($j < count($lines) && $lines[$j] !== '}') {
                    $innerLines[] = $lines[$j];
                    $j++;
                }

                $hasComponent = false;
                $hasContainerLine = false;
                foreach ($innerLines as $il) {
                    if (preg_match('/^Component\s*\(/', $il)) $hasComponent = true;
                    if (preg_match('/^Container\s*\(/', $il) || preg_match('/^ContainerDb\s*\(/', $il)) $hasContainerLine = true;
                }

                if ($hasComponent) {
                    $boundary['kind'] = 'C3';
                    foreach ($innerLines as $il) {
                        if (preg_match('/^Component\s*\(([\s\S]*)$/', $il, $cm)) {
                            if (!preg_match('/^([\s\S]*)\)\s*$/', $cm[1], $im)) continue;
                            $p = self::parseStrings4($im[1]);
                            if ($p) {
                                $ir['components'][] = [
                                    'id' => $p['id'],
                                    'name' => $p['a'],
                                    'technology' => $p['b'],
                                    'description' => $p['c'],
                                    'boundaryId' => $bid,
                                ];
                                $boundary['componentIds'][] = $p['id'];
                            }
                        }
                    }
                } elseif ($hasContainerLine) {
                    $boundary['kind'] = 'C2';
                    foreach ($innerLines as $il) {
                        if (preg_match('/^Container\s*\(([\s\S]*)$/', $il, $contM)) {
                            if (!preg_match('/^([\s\S]*)\)\s*$/', $contM[1], $im)) continue;
                            $p = self::parseStrings4($im[1]);
                            if ($p) {
                                $row = [
                                    'kind' => 'container',
                                    'id' => $p['id'],
                                    'name' => $p['a'],
                                    'technology' => $p['b'],
                                    'description' => $p['c'],
                                ];
                                $ir['containers'][] = [
                                    'id' => $p['id'],
                                    'name' => $p['a'],
                                    'technology' => $p['b'],
                                    'description' => $p['c'],
                                    'boundaryId' => $bid,
                                ];
                                $boundary['items'][] = $row;
                            }
                        } elseif (preg_match('/^ContainerDb\s*\(([\s\S]*)$/', $il, $dbM)) {
                            if (!preg_match('/^([\s\S]*)\)\s*$/', $dbM[1], $im)) continue;
                            $p = self::parseStrings4($im[1]);
                            if ($p) {
                                $row = [
                                    'kind' => 'db',
                                    'id' => $p['id'],
                                    'name' => $p['a'],
                                    'technology' => $p['b'],
                                    'description' => $p['c'],
                                ];
                                $ir['containerDbs'][] = [
                                    'id' => $p['id'],
                                    'name' => $p['a'],
                                    'technology' => $p['b'],
                                    'description' => $p['c'],
                                    'boundaryId' => $bid,
                                ];
                                $boundary['items'][] = $row;
                            }
                        }
                    }
                }

                $ir['boundaries'][] = $boundary;
                $li = $j;
                continue;
            }
        }

        $c3 = false;
        foreach ($ir['components'] as $c) {
            if ($c['boundaryId'] !== null) { $c3 = true; break; }
        }
        if (!$c3) {
            foreach ($ir['boundaries'] as $b) {
                if (($b['kind'] ?? null) === 'C3') { $c3 = true; break; }
            }
        }

        $c2 = (count($ir['containers']) > 0) || (count($ir['containerDbs']) > 0);
        if (!$c2) {
            foreach ($ir['boundaries'] as $b) {
                if (($b['kind'] ?? null) === 'C2') { $c2 = true; break; }
            }
        }

        $ir['level'] = $c3 ? 'C3' : ($c2 ? 'C2' : 'C1');
        return $ir;
    }

    private static function normalizeQuotesAndBom(string $text): string
    {
        $s = preg_replace('/^\x{FEFF}/u', '', $text) ?? $text;
        $s = str_replace(["\r\n", "\r"], ["\n", "\n"], $s);
        $curly = "\u{201C}\u{201D}\u{201E}\u{201F}\u{00AB}\u{00BB}\u{2039}\u{203A}";
        $re = '/[' . preg_quote($curly, '/') . ']/u';
        return preg_replace($re, '"', $s) ?? $s;
    }

    private static function stripCommentsAndInclude(string $text): string
    {
        $out = [];
        foreach (preg_split("/\n/", $text) ?: [] as $l) {
            $t = trim((string)$l);
            if ($t !== '' && str_starts_with($t, "'")) {
                $out[] = '';
                continue;
            }
            if (preg_match('/^\s*!include(?:url)?\b/i', (string)$l)) {
                if (preg_match('/^\s*!include(?:url)?\b\s+(?:"((?:\\\\.|[^"\\\\])*)"|(\S+))\s*(.*)$/i', (string)$l, $m)) {
                    $out[] = trim((string)($m[3] ?? ''));
                    continue;
                }
                $out[] = '';
                continue;
            }
            $out[] = (string)$l;
        }
        return implode("\n", $out);
    }

    private static function parseStrings3(string $inner): ?array
    {
        $re = '/^\s*(\w+)\s*,\s*' . self::Q . '\s*,\s*' . self::Q . '\s*$/s';
        if (!preg_match($re, trim($inner), $m)) return null;
        return ['id' => $m[1], 'a' => self::unescapeStr($m[2]), 'b' => self::unescapeStr($m[3])];
    }

    private static function parseStrings4(string $inner): ?array
    {
        $re = '/^\s*(\w+)\s*,\s*' . self::Q . '\s*,\s*' . self::Q . '\s*,\s*' . self::Q . '\s*$/s';
        if (!preg_match($re, trim($inner), $m)) return null;
        return ['id' => $m[1], 'a' => self::unescapeStr($m[2]), 'b' => self::unescapeStr($m[3]), 'c' => self::unescapeStr($m[4])];
    }

    private static function parseRel(string $inner): ?array
    {
        $re2 = '/^\s*(\w+)\s*,\s*(\w+)\s*,\s*' . self::Q . '\s*$/s';
        $re4 = '/^\s*(\w+)\s*,\s*(\w+)\s*,\s*' . self::Q . '\s*,\s*' . self::Q . '\s*$/s';
        $t = trim($inner);
        if (preg_match($re4, $t, $m)) {
            return ['from' => $m[1], 'to' => $m[2], 'description' => self::unescapeStr($m[3]), 'technology' => self::unescapeStr($m[4])];
        }
        if (preg_match($re2, $t, $m)) {
            return ['from' => $m[1], 'to' => $m[2], 'description' => self::unescapeStr($m[3]), 'technology' => null];
        }
        return null;
    }

    private static function unescapeStr(string $s): string
    {
        $s = str_replace('\\"', '"', $s);
        return str_replace('\\n', "\n", $s);
    }
}

