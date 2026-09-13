<?php
// Routes actual candidate endpoints only, in the isolated test webroot.
$root = realpath(getenv('FOC_TEST_WEB_ROOT') ?: '');
if (!$root || !is_dir($root)) { http_response_code(500); exit('Missing FOC_TEST_WEB_ROOT'); }
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (preg_match('#^/v1/ticket/(give|set|complete)(?:\\.php)?$#', $path, $m)) {
    $file = $root . '/v1/ticket/' . $m[1] . '.php';
} elseif ($path === '/v1/admin/setTicket.php' || $path === '/v1/admin/setTicket') {
    $file = $root . '/v1/admin/setTicket.php';
} else {
    http_response_code(404); exit;
}
if (!is_file($file)) { http_response_code(500); exit('Missing endpoint candidate'); }
chdir(dirname($file));
require $file;
