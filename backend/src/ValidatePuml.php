<?php

declare(strict_types=1);

final class ValidatePuml
{
    private const SUPPORTED_MACROS = [
        'Person',
        'Person_Ext',
        'Enterprise_Boundary',
        'Boundary',
        'System',
        'System_Ext',
        'SystemDb',
        'System_Boundary',
        'SystemQueue',
        'SystemQueue_Ext',
        'SystemDb_Ext',
        'Container_Boundary',
        'Container',
        'ContainerDb',
        'ContainerQueue',
        'Container_Ext',
        'ContainerDb_Ext',
        'ContainerQueue_Ext',
        'Component',
        'Component_Ext',
        'ComponentDb',
        'ComponentQueue',
        'ComponentDb_Ext',
        'ComponentQueue_Ext',
        'Rel',
        'Rel_Back',
        'RelIndex',
        'Rel_U',
        'Rel_Up',
        'Rel_D',
        'Rel_Down',
        'Rel_L',
        'Rel_Left',
        'Rel_R',
        'Rel_Right',
        'BiRel',
        'UpdateRelStyle',
        'UpdateElementStyle',
        'UpdateLayoutConfig',
        'AddElementTag',
        'AddRelTag',
        'AddProperty',
        'WithoutPropertyHeader',
        'Deployment_Node',
        'Deployment_Node_L',
        'Deployment_Node_R',
        'Node',
        'Node_L',
        'Node_R',
        'Lay_U',
        'Lay_Up',
        'Lay_D',
        'Lay_Down',
        'Lay_L',
        'Lay_Left',
        'Lay_R',
        'Lay_Right',
        'title',
        'LAYOUT_LEFT_RIGHT',
        'SHOW_LEGEND',
    ];

    /** @return array{ok:bool, errors:string[], warnings:string[]} */
    public static function validate(string $text): array
    {
        $errors = [];
        $warnings = [];

        $s = (string)$text;
        if (trim($s) === '') {
            return ['ok' => false, 'errors' => ['Input is empty. Paste PlantUML C4 or Mermaid C4 (C4Context/C4Container/C4Component).'], 'warnings' => []];
        }

        $fmt = ParseAnyC4::detect($s)['format'];
        if ($fmt === 'plantuml') {
            $startCount = preg_match_all('/@startuml\b/i', $s) ?: 0;
            $endCount = preg_match_all('/@enduml\b/i', $s) ?: 0;
            if ($startCount !== 1 || $endCount !== 1) {
                $errors[] = 'Expected exactly one @startuml ... @enduml block.';
            }
        } else {
            // Mermaid C4: start line should be C4Context/C4Container/C4Component; parser will enforce it too.
            $warnings[] = 'Mermaid C4 detected. Make sure the first line is C4Context / C4Container / C4Component.';
        }

        // Common copy-paste issue: curly quotes
        if (preg_match('/[\x{201C}\x{201D}\x{201E}\x{201F}\x{00AB}\x{00BB}\x{2039}\x{203A}]/u', $s)) {
            $warnings[] = 'Curly quotes detected. Replace them with straight ASCII quotes: ".';
        }

        // Single-line macro calls check (best-effort)
        $lines = preg_split("/\r\n|\n|\r/", $s) ?: [];
        foreach ($lines as $i => $line) {
            $t = trim((string)$line);
            if ($t === '' || str_starts_with($t, "'")) continue;
            // If a macro call starts but doesn't close on the same line, it will likely be ignored by the parser.
            if (preg_match('/^\w+\s*\(/', $t) && !preg_match('/\)\s*$/', $t) && !preg_match('/\{\s*$/', $t)) {
                $warnings[] = 'Macro call should be on a single line (line ' . ($i + 1) . ').';
            }
        }

        // Unsupported macros / directives (best-effort)
        foreach ($lines as $i => $line) {
            $t = trim((string)$line);
            if ($t === '' || str_starts_with($t, "'")) continue;
            if (preg_match('/^(\w+)\s*\(/', $t, $m)) {
                $kind = $m[1] ?? '';
                if (!in_array($kind, self::SUPPORTED_MACROS, true)) {
                    $warnings[] = 'Unsupported macro "' . $kind . '" (line ' . ($i + 1) . '). It will be ignored.';
                }
            }
        }

        // Deep checks using the parser
        try {
            $ir = ParseAnyC4::parse($s);

            $ids = [];
            foreach (['persons', 'systems', 'systemsExt', 'databases', 'components', 'containers', 'containerDbs'] as $k) {
                foreach (($ir[$k] ?? []) as $item) {
                    $id = $item['id'] ?? null;
                    if (is_string($id) && $id !== '') $ids[$id] = true;
                }
            }

            foreach (($ir['rels'] ?? []) as $r) {
                $from = $r['from'] ?? null;
                $to = $r['to'] ?? null;
                if (is_string($from) && !isset($ids[$from])) {
                    $errors[] = 'Rel references unknown ID: ' . $from;
                }
                if (is_string($to) && !isset($ids[$to])) {
                    $errors[] = 'Rel references unknown ID: ' . $to;
                }
            }

            // Boundary mixing warning (parser prefers Components if mixed)
            foreach (($ir['boundaries'] ?? []) as $b) {
                $bid = (string)($b['id'] ?? '');
                $hasComps = false;
                $hasConts = false;
                foreach (($ir['components'] ?? []) as $c) if (($c['boundaryId'] ?? null) === $bid) { $hasComps = true; break; }
                foreach (($ir['containers'] ?? []) as $c) if (($c['boundaryId'] ?? null) === $bid) { $hasConts = true; break; }
                foreach (($ir['containerDbs'] ?? []) as $c) if (($c['boundaryId'] ?? null) === $bid) { $hasConts = true; break; }
                if ($hasComps && $hasConts) {
                    $warnings[] = 'Boundary "' . ($b['name'] ?? $bid) . '" mixes Component with Container/ContainerDb. Use one type per boundary.';
                }
            }
        } catch (Throwable $e) {
            // Parser error means conversion will fail.
            $errors[] = $e->getMessage();
        }

        $ok = count($errors) === 0;
        return ['ok' => $ok, 'errors' => $errors, 'warnings' => $warnings];
    }
}

