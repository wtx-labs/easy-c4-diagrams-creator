<?php

declare(strict_types=1);

$ROOT = dirname(__DIR__);
$BACKEND = $ROOT . '/backend';

require_once $BACKEND . '/src/EasyC4Shapes.php';
require_once $BACKEND . '/src/ParseC4.php';
require_once $BACKEND . '/src/ParseMermaidC4.php';
require_once $BACKEND . '/src/ParseAnyC4.php';
require_once $BACKEND . '/src/EmitDrawio.php';
require_once $BACKEND . '/src/ValidatePuml.php';

function asciiFilenameForHeader(string $s): string
{
    $t = (string)$s;
    $t = str_replace(["\u{2013}", "\u{2014}", "\u{2212}"], '-', $t); // – — − → ASCII -
    if (class_exists('Transliterator')) {
        $tr = Transliterator::create('NFKD; [:Nonspacing Mark:] Remove; NFC');
        if ($tr) $t = $tr->transliterate($t);
    } else {
        $t = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $t) ?: $t;
    }
    $t = preg_replace('/[<>:"\/\\\\|?*\x00-\x1f]/', '', $t) ?? $t;
    $t = preg_replace('/[^\x20-\x7E]/', '_', $t) ?? $t;
    $t = preg_replace('/_+/', '_', $t) ?? $t;
    $t = trim((string)preg_replace('/^_|_$/', '', $t));
    $t = trim((string)preg_replace('/\s+/', ' ', $t));
    $t = substr($t, 0, 80);
    return $t !== '' ? $t : 'diagram';
}

function unicodeDrawioName(string $s): string
{
    $base = (string)$s;
    $base = preg_replace('/[<>:"\/\\\\|?*\x00-\x1f]/', '', $base) ?? $base;
    $base = trim($base);
    $base = substr($base, 0, 120);
    if ($base === '') $base = 'diagram';
    return $base . '.drawio';
}

function contentDispositionDrawio(string $title): string
{
    $ascii = asciiFilenameForHeader($title);
    $unicodeFile = unicodeDrawioName($title);
    $star = rawurlencode($unicodeFile);
    return 'attachment; filename="' . $ascii . '.drawio"; filename*=UTF-8\'\'' . $star;
}

function sendJson(int $status, array $data): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function readBodyText(): string
{
    $raw = file_get_contents('php://input');
    return $raw === false ? '' : $raw;
}

function parseIncomingPuml(): string
{
    $ct = $_SERVER['CONTENT_TYPE'] ?? '';
    $raw = readBodyText();

    if (stripos($ct, 'application/json') !== false) {
        $j = json_decode($raw, true);
        if (is_array($j)) {
            $v = $j['puml'] ?? ($j['source'] ?? '');
            return is_string($v) ? $v : '';
        }
        return '';
    }

    // text/plain, application/puml, etc.
    if (is_string($raw)) return $raw;
    return '';
}

// ---------------- Router ----------------
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (!is_string($path) || $path === '') {
    $path = '/';
}

// Internal rewrite (e.g. Apache: RewriteRule ^api/ index.php) often leaves REQUEST_URI as /index.php;
// the original path is usually in REDIRECT_URL / REDIRECT_URI.
if (!str_starts_with($path, '/api/')) {
    foreach (['REDIRECT_URL', 'REDIRECT_URI'] as $k) {
        if (empty($_SERVER[$k]) || !is_string($_SERVER[$k])) {
            continue;
        }
        $candidate = parse_url($_SERVER[$k], PHP_URL_PATH);
        if (is_string($candidate) && str_starts_with($candidate, '/api/')) {
            $path = $candidate;
            break;
        }
    }
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

$easyC4File = $BACKEND . '/data/easyc4-library.json';
$HY = EasyC4Shapes::load($easyC4File);

// Support hosts without rewrite rules: allow routing via query param, e.g. index.php?r=/api/convert
if (isset($_GET['r']) && is_string($_GET['r']) && $_GET['r'] !== '') {
    $rp = $_GET['r'];
    if ($rp[0] !== '/') {
        $rp = '/' . $rp;
    }
    $path = $rp;
}

// If this request is not for API, serve the same UI as Node version.
// (On Apache this is usually handled by .htaccess fallback to index.html, but this makes it work without rewrites too.)
if (!is_string($path) || $path === '') $path = '/';
if (!str_starts_with($path, '/api/')) {
    if ($method !== 'GET' && $method !== 'HEAD') {
        http_response_code(404);
        echo "Not found";
        exit;
    }
    header('Content-Type: text/html; charset=utf-8');
    readfile(__DIR__ . '/index.html');
    exit;
}

if ($path === '/api/health' && $method === 'GET') {
    sendJson(200, ['ok' => true, 'shapes' => count($HY)]);
    exit;
}

if ($path === '/api/preview-ir' && $method === 'POST') {
    try {
        $text = parseIncomingPuml();
        if (trim($text) === '') {
            sendJson(400, ['error' => 'Empty input. Provide PlantUML C4 or Mermaid C4 in the request body.']);
            exit;
        }
        $ir = ParseAnyC4::parse($text);
        sendJson(200, $ir);
    } catch (Throwable $e) {
        sendJson(400, ['error' => $e->getMessage()]);
    }
    exit;
}

if ($path === '/api/validate' && $method === 'POST') {
    try {
        $text = parseIncomingPuml();
        $out = ValidatePuml::validate($text);
        if (!$out['ok']) {
            sendJson(400, $out);
            exit;
        }
        sendJson(200, $out);
    } catch (Throwable $e) {
        sendJson(400, ['ok' => false, 'errors' => [$e->getMessage()], 'warnings' => []]);
    }
    exit;
}

if ($path === '/api/convert' && $method === 'POST') {
    try {
        $text = parseIncomingPuml();
        if (trim($text) === '') {
            sendJson(400, ['error' => 'Empty input (provide PlantUML C4 or Mermaid C4 as raw body or JSON field "puml").']);
            exit;
        }
        $ir = ParseAnyC4::parse($text);
        $xml = EmitDrawio::irToDrawio($ir, $HY);

        header('Content-Type: application/xml; charset=utf-8');
        header('Content-Disposition: ' . contentDispositionDrawio((string)($ir['title'] ?? 'diagram')));
        echo $xml;
    } catch (Throwable $e) {
        sendJson(400, ['error' => $e->getMessage()]);
    }
    exit;
}

http_response_code(404);
echo "Not found";
exit;
