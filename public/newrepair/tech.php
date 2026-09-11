<?php
$error = trim((string) ($_GET['error'] ?? ''));
if ($error !== '') {
    header('Location: index.html#/tech?error=' . rawurlencode($error), true, 302);
    exit;
}

$params = ['wx' => '1'];
$claim = trim((string) ($_GET['claim'] ?? ''));
if ($claim !== '') {
    $params = ['claim' => $claim] + $params;
}

$next = '/public/newrepair/index.html#/tech?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
header('Location: api/index.php?route=wechat.start&next=' . rawurlencode($next), true, 302);
exit;
