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
            'deployment' => ['nodes' => [], 'roots' => []],
        ];

        /** @var list<string> */
        $deploymentStack = [];

        for ($li = 0; $li < count($lines); $li++) {
            $line = $lines[$li];

            if (preg_match('/^title\s+(.+)$/i', $line, $tm)) {
                $ir['title'] = trim($tm[1]);
                continue;
            }

            if ($line === '}') {
                if (!empty($deploymentStack)) {
                    array_pop($deploymentStack);
                }
                continue;
            }

            if (preg_match('/^(?:Deployment_Node|Node)(_L|_R)?\s*\(([\s\S]*)\)\s*\{\s*$/', $line, $dm)) {
                $p = self::parseDeploymentNodeInner(trim($dm[2]));
                if ($p) {
                    $suffix = (string)($dm[1] ?? '');
                    $side = $suffix === '_L' ? 'L' : ($suffix === '_R' ? 'R' : '');
                    $id = $p['id'];
                    $parentId = null;
                    if (!empty($deploymentStack)) {
                        $parentId = $deploymentStack[array_key_last($deploymentStack)];
                        $ir['deployment']['nodes'][$parentId]['childOrder'][] = $id;
                    } else {
                        $ir['deployment']['roots'][] = $id;
                    }
                    $ir['deployment']['nodes'][$id] = [
                        'id' => $id,
                        'name' => $p['a'],
                        'technology' => $p['b'],
                        'description' => $p['c'],
                        'side' => $side,
                        'parentId' => $parentId,
                        'properties' => [],
                        'childOrder' => [],
                        'containerIds' => [],
                        'containerDbIds' => [],
                    ];
                    $deploymentStack[] = $id;
                }
                continue;
            }

            if (preg_match('/^AddProperty\s*\(\s*' . self::Q . '\s*,\s*' . self::Q . '\s*\)\s*$/s', $line, $ap)) {
                if (!empty($deploymentStack)) {
                    $top = $deploymentStack[array_key_last($deploymentStack)];
                    $ir['deployment']['nodes'][$top]['properties'][] = [
                        'key' => self::unescapeStr($ap[1]),
                        'value' => self::unescapeStr($ap[2]),
                    ];
                }
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
                    $p = self::parseSystem($inner);
                    if ($p) {
                        $ir['systems'][] = ['id' => $p['id'], 'name' => $p['name'], 'description' => $p['description']];
                    }
                    continue;
                }

                if ($kind === 'System_Ext') {
                    $p = self::parseSystem($inner);
                    if ($p) {
                        $ir['systemsExt'][] = ['id' => $p['id'], 'name' => $p['name'], 'description' => $p['description']];
                    }
                    continue;
                }

                if ($kind === 'SystemDb') {
                    $p = self::parseSystem($inner);
                    if ($p) {
                        $ir['systems'][] = ['id' => $p['id'], 'name' => $p['name'], 'description' => $p['description']];
                    }
                    continue;
                }

                if ($kind === 'SystemDb_Ext') {
                    $p = self::parseSystem($inner);
                    if ($p) {
                        $ir['systemsExt'][] = ['id' => $p['id'], 'name' => $p['name'], 'description' => $p['description']];
                    }
                    continue;
                }

                if ($kind === 'SystemQueue') {
                    $p = self::parseSystem($inner);
                    if ($p) {
                        $ir['systems'][] = ['id' => $p['id'], 'name' => $p['name'], 'description' => $p['description']];
                    }
                    continue;
                }

                if ($kind === 'SystemQueue_Ext') {
                    $p = self::parseSystem($inner);
                    if ($p) {
                        $ir['systemsExt'][] = ['id' => $p['id'], 'name' => $p['name'], 'description' => $p['description']];
                    }
                    continue;
                }

                if ($kind === 'Component') {
                    $p = self::parseStrings4Or3($inner);
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

                if ($kind === 'Component_Ext') {
                    $p = self::parseStrings4Or3($inner);
                    if ($p) {
                        $desc = trim($p['b']) !== '' ? ($p['b'] . '; ' . $p['c']) : $p['c'];
                        // Render external components similarly to external systems in Draw.io output.
                        $ir['systemsExt'][] = ['id' => $p['id'], 'name' => $p['a'], 'description' => $desc];
                    }
                    continue;
                }

                if ($kind === 'Container' || $kind === 'Container_Ext' || $kind === 'ContainerQueue' || $kind === 'ContainerQueue_Ext') {
                    $p = self::parseStrings4Or3($inner);
                    if ($p) {
                        $depTop = !empty($deploymentStack) ? $deploymentStack[array_key_last($deploymentStack)] : null;
                        $rowKind = ($kind === 'ContainerQueue' || $kind === 'ContainerQueue_Ext') ? 'queue' : 'container';
                        $ir['containers'][] = [
                            'id' => $p['id'],
                            'name' => $p['a'],
                            'technology' => $p['b'],
                            'description' => $p['c'],
                            'boundaryId' => null,
                            'deploymentNodeId' => $depTop,
                        ];
                        $row = [
                            'kind' => $rowKind,
                            'id' => $p['id'],
                            'name' => $p['a'],
                            'technology' => $p['b'],
                            'description' => $p['c'],
                            'deploymentNodeId' => $depTop,
                        ];
                        $ir['c2Standalone'][] = $row;
                        if ($depTop !== null && isset($ir['deployment']['nodes'][$depTop])) {
                            $ir['deployment']['nodes'][$depTop]['containerIds'][] = $p['id'];
                        }
                    }
                    continue;
                }

                if ($kind === 'ContainerDb' || $kind === 'ContainerDb_Ext') {
                    $p = self::parseStrings4Or3($inner);
                    if ($p) {
                        $depTop = !empty($deploymentStack) ? $deploymentStack[array_key_last($deploymentStack)] : null;
                        $ir['containerDbs'][] = [
                            'id' => $p['id'],
                            'name' => $p['a'],
                            'technology' => $p['b'],
                            'description' => $p['c'],
                            'boundaryId' => null,
                            'deploymentNodeId' => $depTop,
                        ];
                        $row = [
                            'kind' => 'db',
                            'id' => $p['id'],
                            'name' => $p['a'],
                            'technology' => $p['b'],
                            'description' => $p['c'],
                            'deploymentNodeId' => $depTop,
                        ];
                        $ir['c2Standalone'][] = $row;
                        if ($depTop !== null && isset($ir['deployment']['nodes'][$depTop])) {
                            $ir['deployment']['nodes'][$depTop]['containerDbIds'][] = $p['id'];
                        }
                    }
                    continue;
                }

                if (
                    $kind === 'Rel'
                    || $kind === 'BiRel'
                    || $kind === 'Rel_Back'
                    || $kind === 'RelIndex'
                    || str_starts_with($kind, 'Rel_')
                ) {
                    $r = self::parseRel($inner, $kind);
                    if ($r) {
                        $ir['rels'][] = $r;
                    }
                    continue;
                }
            }

            $bRe = '/^(System_Boundary|Container_Boundary|Boundary|Enterprise_Boundary)\s*\(([\s\S]*)\)\s*\{\s*$/';
            if (preg_match($bRe, $line, $bm)) {
                $bArgs = self::splitLooseArgs(trim((string)$bm[2]));
                if (count($bArgs) < 2) {
                    continue;
                }
                $bid = trim((string)$bArgs[0]);
                if (!preg_match('/^\w+$/', $bid)) {
                    continue;
                }
                $bname = self::unescapeStr(trim((string)$bArgs[1], " \t\n\r\0\x0B\""));
                $boundary = [
                    'id' => $bid,
                    'name' => $bname,
                    'macro' => (string)$bm[1],
                    'componentIds' => [],
                    'items' => [],
                    'kind' => 'empty',
                ];

                [$innerLines, $j] = self::extractInnerLinesForBraceBlock($lines, $li);

                $hasDeploymentInBoundary = false;
                foreach ($innerLines as $il0) {
                    $t0 = trim((string)$il0);
                    if ($t0 === '' || str_starts_with($t0, "'")) {
                        continue;
                    }
                    if (preg_match('/^(?:Deployment_Node|Node)(_L|_R)?\s*\(/', $t0)) {
                        $hasDeploymentInBoundary = true;
                        break;
                    }
                }

                if ($hasDeploymentInBoundary) {
                    $boundary['kind'] = 'DEPLOYMENT';
                    $pWrap = [
                        'id' => $bid,
                        'a' => $bname,
                        'b' => '',
                        'c' => '',
                    ];
                    $parentId = null;
                    if (!empty($deploymentStack)) {
                        $parentId = $deploymentStack[array_key_last($deploymentStack)];
                        $ir['deployment']['nodes'][$parentId]['childOrder'][] = $bid;
                    } else {
                        $ir['deployment']['roots'][] = $bid;
                    }
                    $ir['deployment']['nodes'][$bid] = [
                        'id' => $bid,
                        'name' => $pWrap['a'],
                        'technology' => '',
                        'description' => '',
                        'side' => '',
                        'parentId' => $parentId,
                        'properties' => [],
                        'childOrder' => [],
                        'containerIds' => [],
                        'containerDbIds' => [],
                    ];
                    $deploymentStack[] = $bid;
                    self::processDeploymentInnerLines($innerLines, $ir, $deploymentStack);
                    $ir['boundaries'][] = $boundary;
                    // for (...) $li++ runs after continue — use $j-1 so the next line processed is index $j.
                    $li = $j - 1;
                    continue;
                }

                $hasComponent = false;
                $hasContainerLine = false;
                $hasSystemLine = false;
                foreach ($innerLines as $il) {
                    if (preg_match('/^Component\s*\(/', $il)) $hasComponent = true;
                    if (preg_match('/^Container(_Ext|Queue|Queue_Ext)?\s*\(/', $il) || preg_match('/^ContainerDb(_Ext)?\s*\(/', $il) || preg_match('/^SystemDb(_Ext)?\s*\(/', $il)) $hasContainerLine = true;
                    if (preg_match('/^System(_Ext|Db|Db_Ext|Queue|Queue_Ext)?\s*\(/', $il)) $hasSystemLine = true;
                }

                if ($hasComponent) {
                    $boundary['kind'] = 'C3';
                    foreach ($innerLines as $il) {
                        if (preg_match('/^Component\s*\(([\s\S]*)$/', $il, $cm)) {
                            if (!preg_match('/^([\s\S]*)\)\s*$/', $cm[1], $im)) continue;
                            $p = self::parseStrings4Or3($im[1]);
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
                        // In C3 boundaries, Container/ContainerDb may coexist with components
                        // as side modules (e.g. auth/observability). Keep them as standalone
                        // C3 elements (outside component boundary) so relations resolve.
                        elseif (preg_match('/^Container(_Ext|Queue|Queue_Ext)?\s*\(([\s\S]*)$/', $il, $contM)) {
                            if (!preg_match('/^([\s\S]*)\)\s*$/', $contM[2], $im)) continue;
                            $p = self::parseStrings4Or3($im[1]);
                            if ($p) {
                                $ir['containers'][] = [
                                    'id' => $p['id'],
                                    'name' => $p['a'],
                                    'technology' => $p['b'],
                                    'description' => $p['c'],
                                    'boundaryId' => null,
                                ];
                            }
                        } elseif (preg_match('/^ContainerDb(_Ext)?\s*\(([\s\S]*)$/', $il, $dbM)) {
                            if (!preg_match('/^([\s\S]*)\)\s*$/', $dbM[2], $im)) continue;
                            $p = self::parseStrings4Or3($im[1]);
                            if ($p) {
                                $ir['containerDbs'][] = [
                                    'id' => $p['id'],
                                    'name' => $p['a'],
                                    'technology' => $p['b'],
                                    'description' => $p['c'],
                                    'boundaryId' => null,
                                ];
                            }
                        } elseif (preg_match('/^SystemDb(_Ext)?\s*\(([\s\S]*)$/', $il, $sysDbM)) {
                            if (!preg_match('/^([\s\S]*)\)\s*$/', $sysDbM[2], $im)) continue;
                            $p = self::parseSystem($im[1]);
                            if ($p) {
                                $ir['containerDbs'][] = [
                                    'id' => $p['id'],
                                    'name' => $p['name'],
                                    'technology' => '',
                                    'description' => $p['description'],
                                    'boundaryId' => null,
                                ];
                            }
                        }
                    }
                } elseif ($hasContainerLine) {
                    $boundary['kind'] = 'C2';
                    foreach ($innerLines as $il) {
                        if (preg_match('/^Container(_Ext|Queue|Queue_Ext)?\s*\(([\s\S]*)$/', $il, $contM)) {
                            $kindSuffix = (string)($contM[1] ?? '');
                            if (!preg_match('/^([\s\S]*)\)\s*$/', $contM[2], $im)) continue;
                            $p = self::parseStrings4Or3($im[1]);
                            if ($p) {
                                $rowKind = ($kindSuffix === 'Queue' || $kindSuffix === 'Queue_Ext') ? 'queue' : 'container';
                                $row = [
                                    'kind' => $rowKind,
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
                        } elseif (preg_match('/^ContainerDb(_Ext)?\s*\(([\s\S]*)$/', $il, $dbM)) {
                            if (!preg_match('/^([\s\S]*)\)\s*$/', $dbM[2], $im)) continue;
                            $p = self::parseStrings4Or3($im[1]);
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
                        } elseif (preg_match('/^SystemDb(_Ext)?\s*\(([\s\S]*)$/', $il, $sysDbM)) {
                            if (!preg_match('/^([\s\S]*)\)\s*$/', $sysDbM[2], $im)) continue;
                            $p = self::parseSystem($im[1]);
                            if ($p) {
                                $row = [
                                    'kind' => 'db',
                                    'id' => $p['id'],
                                    'name' => $p['name'],
                                    'technology' => '',
                                    'description' => $p['description'],
                                ];
                                $ir['containerDbs'][] = [
                                    'id' => $p['id'],
                                    'name' => $p['name'],
                                    'technology' => '',
                                    'description' => $p['description'],
                                    'boundaryId' => $bid,
                                ];
                                $boundary['items'][] = $row;
                            }
                        } elseif (preg_match('/^System(_Ext|Queue_Ext)?\s*\(([\s\S]*)$/', $il, $sysExtM)) {
                            if (!preg_match('/^([\s\S]*)\)\s*$/', $sysExtM[2], $im)) continue;
                            $p = self::parseSystem($im[1]);
                            if ($p) {
                                $row = [
                                    'kind' => 'extsystem',
                                    'id' => $p['id'],
                                    'name' => $p['name'],
                                    'technology' => '',
                                    'description' => $p['description'],
                                ];
                                $ir['systemsExt'][] = [
                                    'id' => $p['id'],
                                    'name' => $p['name'],
                                    'description' => $p['description'],
                                    'boundaryId' => $bid,
                                ];
                                $boundary['items'][] = $row;
                            }
                        } elseif (preg_match('/^System(Queue)?\s*\(([\s\S]*)$/', $il, $sysM)) {
                            if (!preg_match('/^([\s\S]*)\)\s*$/', $sysM[2], $im)) continue;
                            $p = self::parseSystem($im[1]);
                            if ($p) {
                                $row = [
                                    'kind' => 'system',
                                    'id' => $p['id'],
                                    'name' => $p['name'],
                                    'technology' => '',
                                    'description' => $p['description'],
                                ];
                                $ir['systems'][] = [
                                    'id' => $p['id'],
                                    'name' => $p['name'],
                                    'description' => $p['description'],
                                    'boundaryId' => $bid,
                                ];
                                $boundary['items'][] = $row;
                            }
                        }
                    }
                } elseif ($hasSystemLine) {
                    $boundary['kind'] = 'C1';
                    foreach ($innerLines as $il) {
                        if (preg_match('/^System\s*\(([\s\S]*)$/', $il, $sysM)) {
                            if (!preg_match('/^([\s\S]*)\)\s*$/', $sysM[1], $im)) continue;
                            $p = self::parseSystem($im[1]);
                            if ($p) {
                                $ir['systems'][] = [
                                    'id' => $p['id'],
                                    'name' => $p['name'],
                                    'description' => $p['description'],
                                    'boundaryId' => $bid,
                                ];
                            }
                        }
                    }
                }

                // Parse relationships declared inside Boundary(...) { ... } blocks.
                foreach ($innerLines as $il) {
                    if (preg_match('/^(\w+)\s*\(([\s\S]*)$/', $il, $callInner)) {
                        $innerKind = (string)$callInner[1];
                        if (
                            $innerKind === 'Rel'
                            || $innerKind === 'BiRel'
                            || $innerKind === 'Rel_Back'
                            || $innerKind === 'RelIndex'
                            || str_starts_with($innerKind, 'Rel_')
                        ) {
                            if (!preg_match('/^([\s\S]*)\)\s*$/', $callInner[2], $innerM)) {
                                continue;
                            }
                            $r = self::parseRel($innerM[1], $innerKind);
                            if ($r) {
                                $ir['rels'][] = $r;
                            }
                        }
                    }
                }

                $ir['boundaries'][] = $boundary;
                // for (...) $li++ runs after continue — use $j-1 so the next line processed is index $j.
                $li = $j - 1;
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

        $c2 = (count($ir['containers']) > 0) || (count($ir['containerDbs']) > 0) || (!empty($ir['deployment']['roots']));
        if (!$c2) {
            foreach ($ir['boundaries'] as $b) {
                $bk = $b['kind'] ?? null;
                if ($bk === 'C2' || $bk === 'DEPLOYMENT') { $c2 = true; break; }
            }
        }

        $ir['level'] = $c3 ? 'C3' : ($c2 ? 'C2' : 'C1');
        return $ir;
    }

    /**
     * Lines after `Foo(...) {` until matching `}` (quote-aware), excluding the closing `}` line.
     *
     * @param list<string> $lines
     * @return array{0: list<string>, 1: int}
     */
    private static function extractInnerLinesForBraceBlock(array $lines, int $openIdx): array
    {
        $inner = [];
        $depth = 1;
        $j = $openIdx + 1;
        $n = count($lines);
        while ($j < $n) {
            $line = (string)$lines[$j];
            $delta = self::braceDeltaOutsideQuotes($line);
            $newDepth = $depth + $delta;
            if ($newDepth <= 0) {
                return [$inner, $j + 1];
            }
            $inner[] = $line;
            $depth = $newDepth;
            $j++;
        }
        return [$inner, $j];
    }

    private static function braceDeltaOutsideQuotes(string $line): int
    {
        $d = 0;
        $inQ = false;
        $esc = false;
        $n = strlen($line);
        for ($i = 0; $i < $n; $i++) {
            $ch = $line[$i];
            if ($esc) {
                $esc = false;
                continue;
            }
            if ($ch === '\\') {
                $esc = true;
                continue;
            }
            if ($ch === '"') {
                $inQ = !$inQ;
                continue;
            }
            if (!$inQ) {
                if ($ch === '{') {
                    $d++;
                } elseif ($ch === '}') {
                    $d--;
                }
            }
        }
        return $d;
    }

    /**
     * @param list<string> $lines
     * @param list<string> $deploymentStack
     */
    private static function processDeploymentInnerLines(array $lines, array &$ir, array &$deploymentStack): void
    {
        foreach ($lines as $line) {
            $t = trim((string)$line);
            if ($t === '' || str_starts_with($t, "'")) {
                continue;
            }
            if ($t === '}') {
                if (!empty($deploymentStack)) {
                    array_pop($deploymentStack);
                }
                continue;
            }
            if (preg_match('/^WithoutPropertyHeader\s*\(\s*\)\s*$/', $t)) {
                continue;
            }
            if (preg_match('/^(?:Deployment_Node|Node)(_L|_R)?\s*\(([\s\S]*)\)\s*\{\s*$/', $t, $dm)) {
                $p = self::parseDeploymentNodeInner(trim($dm[2]));
                if ($p) {
                    $suffix = (string)($dm[1] ?? '');
                    $side = $suffix === '_L' ? 'L' : ($suffix === '_R' ? 'R' : '');
                    $id = $p['id'];
                    $parentId = null;
                    if (!empty($deploymentStack)) {
                        $parentId = $deploymentStack[array_key_last($deploymentStack)];
                        $ir['deployment']['nodes'][$parentId]['childOrder'][] = $id;
                    } else {
                        $ir['deployment']['roots'][] = $id;
                    }
                    $ir['deployment']['nodes'][$id] = [
                        'id' => $id,
                        'name' => $p['a'],
                        'technology' => $p['b'],
                        'description' => $p['c'],
                        'side' => $side,
                        'parentId' => $parentId,
                        'properties' => [],
                        'childOrder' => [],
                        'containerIds' => [],
                        'containerDbIds' => [],
                    ];
                    $deploymentStack[] = $id;
                }
                continue;
            }
            if (preg_match('/^AddProperty\s*\(\s*' . self::Q . '\s*,\s*' . self::Q . '\s*\)\s*$/s', $t, $ap)) {
                if (!empty($deploymentStack)) {
                    $top = $deploymentStack[array_key_last($deploymentStack)];
                    $ir['deployment']['nodes'][$top]['properties'][] = [
                        'key' => self::unescapeStr($ap[1]),
                        'value' => self::unescapeStr($ap[2]),
                    ];
                }
                continue;
            }
            if (preg_match('/^(\w+)\s*\(([\s\S]*)$/', $t, $call) && strpos($t, '{') === false) {
                $kind = $call[1];
                $rest = $call[2];
                if (!preg_match('/^([\s\S]*)\)\s*$/', $rest, $innerM)) {
                    continue;
                }
                $inner = $innerM[1];
                if ($kind === 'Container' || $kind === 'Container_Ext' || $kind === 'ContainerQueue' || $kind === 'ContainerQueue_Ext') {
                    $p = self::parseStrings4Or3($inner);
                    if ($p) {
                        $depTop = !empty($deploymentStack) ? $deploymentStack[array_key_last($deploymentStack)] : null;
                        $rowKind = ($kind === 'ContainerQueue' || $kind === 'ContainerQueue_Ext') ? 'queue' : 'container';
                        $ir['containers'][] = [
                            'id' => $p['id'],
                            'name' => $p['a'],
                            'technology' => $p['b'],
                            'description' => $p['c'],
                            'boundaryId' => null,
                            'deploymentNodeId' => $depTop,
                        ];
                        $row = [
                            'kind' => $rowKind,
                            'id' => $p['id'],
                            'name' => $p['a'],
                            'technology' => $p['b'],
                            'description' => $p['c'],
                            'deploymentNodeId' => $depTop,
                        ];
                        $ir['c2Standalone'][] = $row;
                        if ($depTop !== null && isset($ir['deployment']['nodes'][$depTop])) {
                            $ir['deployment']['nodes'][$depTop]['containerIds'][] = $p['id'];
                        }
                    }
                    continue;
                }
                if ($kind === 'ContainerDb' || $kind === 'ContainerDb_Ext') {
                    $p = self::parseStrings4Or3($inner);
                    if ($p) {
                        $depTop = !empty($deploymentStack) ? $deploymentStack[array_key_last($deploymentStack)] : null;
                        $ir['containerDbs'][] = [
                            'id' => $p['id'],
                            'name' => $p['a'],
                            'technology' => $p['b'],
                            'description' => $p['c'],
                            'boundaryId' => null,
                            'deploymentNodeId' => $depTop,
                        ];
                        $row = [
                            'kind' => 'db',
                            'id' => $p['id'],
                            'name' => $p['a'],
                            'technology' => $p['b'],
                            'description' => $p['c'],
                            'deploymentNodeId' => $depTop,
                        ];
                        $ir['c2Standalone'][] = $row;
                        if ($depTop !== null && isset($ir['deployment']['nodes'][$depTop])) {
                            $ir['deployment']['nodes'][$depTop]['containerDbIds'][] = $p['id'];
                        }
                    }
                    continue;
                }
                if (
                    $kind === 'Rel'
                    || $kind === 'BiRel'
                    || $kind === 'Rel_Back'
                    || $kind === 'RelIndex'
                    || str_starts_with($kind, 'Rel_')
                ) {
                    $r = self::parseRel($inner, $kind);
                    if ($r) {
                        $ir['rels'][] = $r;
                    }
                }
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

    /**
     * C4-PlantUML: Deployment_Node(alias, label, type, description) or 3-arg form without description.
     *
     * @return array{id:string,a:string,b:string,c:string}|null
     */
    private static function parseDeploymentNodeInner(string $inner): ?array
    {
        $p4 = self::parseStrings4($inner);
        if ($p4) {
            return $p4;
        }
        $p3 = self::parseStrings3($inner);
        if ($p3) {
            return ['id' => $p3['id'], 'a' => $p3['a'], 'b' => $p3['b'], 'c' => ''];
        }
        return null;
    }

    private static function parseStrings3(string $inner): ?array
    {
        // Allow trailing C4-PlantUML options, e.g. , $tags="fallback" , $sprite="..." (ignored for IR).
        $re = '/^\s*(\w+)\s*,\s*' . self::Q . '\s*,\s*' . self::Q . '\s*/s';
        if (preg_match($re, trim($inner), $m)) {
            return ['id' => $m[1], 'a' => self::unescapeStr($m[2]), 'b' => self::unescapeStr($m[3])];
        }
        $parts = self::splitLooseArgs($inner);
        if (count($parts) < 3) return null;
        $id = trim((string)$parts[0]);
        if (!preg_match('/^\w+$/', $id)) return null;
        return ['id' => $id, 'a' => trim((string)$parts[1]), 'b' => trim((string)$parts[2])];
    }

    private static function parseStrings4(string $inner): ?array
    {
        $re = '/^\s*(\w+)\s*,\s*' . self::Q . '\s*,\s*' . self::Q . '\s*,\s*' . self::Q . '\s*/s';
        if (preg_match($re, trim($inner), $m)) {
            return ['id' => $m[1], 'a' => self::unescapeStr($m[2]), 'b' => self::unescapeStr($m[3]), 'c' => self::unescapeStr($m[4])];
        }
        $parts = self::splitLooseArgs($inner);
        if (count($parts) < 4) return null;
        $id = trim((string)$parts[0]);
        if (!preg_match('/^\w+$/', $id)) return null;
        return ['id' => $id, 'a' => trim((string)$parts[1]), 'b' => trim((string)$parts[2]), 'c' => trim((string)$parts[3])];
    }

    /**
     * C4-PlantUML: 4-arg (id, "label", "technology", "description") or 3-arg (id, "label", "description").
     *
     * @return array{id:string,a:string,b:string,c:string}|null
     */
    private static function parseStrings4Or3(string $inner): ?array
    {
        $p4 = self::parseStrings4($inner);
        if ($p4 !== null) {
            return $p4;
        }
        $p3 = self::parseStrings3($inner);
        if ($p3 === null) {
            return null;
        }
        return ['id' => $p3['id'], 'a' => $p3['a'], 'b' => '', 'c' => $p3['b']];
    }

    /** @return array{id:string,name:string,description:string}|null */
    private static function parseSystem(string $inner): ?array
    {
        // Support both variants:
        // System(id, "name", "description")
        // System(id, "name", "technology", "description")
        $p4 = self::parseStrings4($inner);
        if ($p4) {
            $description = trim($p4['b']) !== '' ? ($p4['b'] . '; ' . $p4['c']) : $p4['c'];
            return ['id' => $p4['id'], 'name' => $p4['a'], 'description' => $description];
        }

        $p3 = self::parseStrings3($inner);
        if ($p3) {
            return ['id' => $p3['id'], 'name' => $p3['a'], 'description' => $p3['b']];
        }
        return null;
    }

    private static function parseRel(string $inner, ?string $kind = null): ?array
    {
        $re2 = '/^\s*(\w+)\s*,\s*(\w+)\s*,\s*' . self::Q . '\s*/s';
        $re4 = '/^\s*(\w+)\s*,\s*(\w+)\s*,\s*' . self::Q . '\s*,\s*' . self::Q . '\s*/s';
        $t = trim($inner);
        if (preg_match($re4, $t, $m)) {
            if ($kind === 'RelIndex') {
                return ['from' => $m[1], 'to' => $m[2], 'description' => self::unescapeStr($m[3]), 'technology' => null];
            }
            return ['from' => $m[1], 'to' => $m[2], 'description' => self::unescapeStr($m[3]), 'technology' => self::unescapeStr($m[4])];
        }
        if (preg_match($re2, $t, $m)) {
            return ['from' => $m[1], 'to' => $m[2], 'description' => self::unescapeStr($m[3]), 'technology' => null];
        }
        $parts = self::splitLooseArgs($t);
        if (count($parts) >= 3) {
            $from = trim((string)$parts[0]);
            $to = trim((string)$parts[1]);
            if (!preg_match('/^\w+$/', $from) || !preg_match('/^\w+$/', $to)) {
                return null;
            }
            $desc = trim((string)$parts[2]);
            $tech = isset($parts[3]) ? trim((string)$parts[3]) : null;
            if ($kind === 'RelIndex') {
                return ['from' => $from, 'to' => $to, 'description' => $desc, 'technology' => null];
            }
            return ['from' => $from, 'to' => $to, 'description' => $desc, 'technology' => $tech !== '' ? $tech : null];
        }
        return null;
    }

    /** @return list<string> */
    private static function splitLooseArgs(string $inner): array
    {
        $parts = [];
        $buf = '';
        $inQ = false;
        $esc = false;
        $depth = 0;
        $n = strlen($inner);
        for ($i = 0; $i < $n; $i++) {
            $ch = $inner[$i];
            if ($esc) {
                $buf .= $ch;
                $esc = false;
                continue;
            }
            if ($ch === '\\') {
                $buf .= $ch;
                $esc = true;
                continue;
            }
            if ($ch === '"') {
                $buf .= $ch;
                $inQ = !$inQ;
                continue;
            }
            if (!$inQ) {
                if ($ch === '(') {
                    $depth++;
                } elseif ($ch === ')') {
                    $depth = max(0, $depth - 1);
                } elseif ($ch === ',' && $depth === 0) {
                    $parts[] = trim($buf);
                    $buf = '';
                    continue;
                }
            }
            $buf .= $ch;
        }
        $parts[] = trim($buf);
        return $parts;
    }

    private static function unescapeStr(string $s): string
    {
        $s = str_replace('\\"', '"', $s);
        return str_replace('\\n', "\n", $s);
    }
}

