<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
ini_set('display_errors','0');
ini_set('log_errors','1');
require_once __DIR__ . '/../utils/ticket_notifications.php';
try {
    $config = require __DIR__ . '/../config.php';
    $pdo = new PDO('mysql:host='.$config['db']['host'].';dbname='.$config['db']['dbname'].';charset=utf8mb4',
        $config['db']['username'],$config['db']['password'],
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    echo json_encode(ticketNotificationWorker($pdo, function ($job,$payload) use ($config) {
        ticketDeliverNotification($config,$job,$payload);
    }), JSON_THROW_ON_ERROR), PHP_EOL;
} catch (Throwable $e) {
    error_log('[ticket.notify] worker failure class='.get_class($e).' code='.$e->getCode());
    exit(1);
}
