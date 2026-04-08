<?php

declare(strict_types=1);

final class EasyC4Shapes
{
    /**
     * Reads EasyC4 library (JSON array) and returns templates map keyed by title.
     *
     * @return array<string, array{w:int,h:int,style:string,objectOpen:string,cellValue:string}>
     */
    public static function load(string $easyC4LibraryJsonFile): array
    {
        if (!is_file($easyC4LibraryJsonFile)) {
            throw new RuntimeException("EasyC4 library file is missing: {$easyC4LibraryJsonFile}");
        }

        $json = file_get_contents($easyC4LibraryJsonFile);
        if ($json === false) {
            throw new RuntimeException("Cannot read file: {$easyC4LibraryJsonFile}");
        }

        $lib = json_decode($json, true);
        if (!is_array($lib)) {
            throw new RuntimeException("Invalid JSON in {$easyC4LibraryJsonFile}");
        }

        $out = [];
        foreach ($lib as $item) {
            if (!is_array($item)) continue;
            $title = (string)($item['title'] ?? '');
            if ($title === '') continue;
            $w = (int)($item['w'] ?? 0);
            $h = (int)($item['h'] ?? 0);
            $b64 = (string)($item['xml'] ?? '');
            if ($b64 === '') continue;

            $xml = self::decodeShapeXml($b64);
            $entry = self::extractEntry($xml);
            $out[$title] = [
                'w' => $w,
                'h' => $h,
                'style' => $entry['style'],
                'objectOpen' => $entry['objectOpen'],
                'cellValue' => $entry['cellValue'],
            ];
        }

        return $out;
    }

    private static function decodeShapeXml(string $b64): string
    {
        $bin = base64_decode($b64, true);
        if ($bin === false) {
            throw new RuntimeException('Cannot base64-decode an EasyC4 shape');
        }

        // The library uses raw DEFLATE + URI-encoded XML (same pattern as draw.io libraries).
        if (!function_exists('gzinflate')) {
            throw new RuntimeException('Missing gzinflate() (PHP extension zlib is required) to decode the EasyC4 library');
        }

        $inflated = @gzinflate($bin);
        if ($inflated === false) {
            throw new RuntimeException('Cannot decompress an EasyC4 shape (gzinflate failed)');
        }

        // Some libraries store URI-encoded XML, some already plain XML.
        $maybe = @urldecode($inflated);
        if (is_string($maybe) && str_contains($maybe, '<mxCell') || str_contains($maybe, '<object')) {
            return $maybe;
        }
        return $inflated;
    }

    /** @return array{style:string,objectOpen:string,cellValue:string} */
    private static function extractEntry(string $xml): array
    {
        $objectOpen = '';
        if (preg_match('/<object ([\s\S]*?)>/', $xml, $om)) {
            $objectOpen = $om[1] ?? '';
        }

        $style = '';
        if (preg_match('/style="([^"]*)"/', $xml, $sm)) {
            $style = $sm[1] ?? '';
        }

        $cellValue = '';
        if ($objectOpen === '') {
            // Some shapes are plain <mxCell> without <object> (e.g. Legend)
            if (preg_match('/<mxCell ([^>]*value="[^"]*"[^>]*)>/', $xml, $cm)) {
                $attrs = $cm[1] ?? '';
                if (preg_match('/value="([^"]*)"/', $attrs, $vm)) {
                    $cellValue = $vm[1] ?? '';
                }
            }
        }

        return ['style' => (string)$style, 'objectOpen' => (string)$objectOpen, 'cellValue' => (string)$cellValue];
    }
}

