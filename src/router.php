<?php
$requested = __DIR__ . parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
if (file_exists($requested) && is_file($requested)) {
    return false;
}
require __DIR__ . '/index.php';
