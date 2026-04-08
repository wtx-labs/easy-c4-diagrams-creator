<?php

declare(strict_types=1);

final class ParseAnyC4
{
    /** @return array{format:'plantuml'|'mermaid'} */
    public static function detect(string $text): array
    {
        $s = trim((string)$text);
        $s = preg_replace('/^\x{FEFF}/u', '', $s) ?? $s;

        if (preg_match('/@startuml\b/i', $s)) return ['format' => 'plantuml'];

        // Mermaid: may be fenced or plain. Look for "C4Context" etc near the top.
        $head = implode("\n", array_slice(preg_split("/\r\n|\n|\r/", $s) ?: [], 0, 12));
        if (preg_match('/^\s*```+\s*mermaid\b/im', $head)) return ['format' => 'mermaid'];
        if (preg_match('/^\s*mermaid\b/im', $head) && preg_match('/^\s*C4\w+\b/im', $head)) return ['format' => 'mermaid'];
        if (preg_match('/^\s*C4(?:Context|Container|Component|Dynamic|Deployment)\b/im', $head)) return ['format' => 'mermaid'];

        // Default: try PlantUML first (better error messages for the existing users)
        return ['format' => 'plantuml'];
    }

    public static function parse(string $text): array
    {
        $fmt = self::detect($text)['format'];
        if ($fmt === 'mermaid') return ParseMermaidC4::parse($text);
        return ParseC4::parse($text);
    }
}

