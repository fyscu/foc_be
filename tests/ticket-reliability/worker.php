<?php
require __DIR__ . '/bootstrap.php';
loadCore();
$job = json_decode(base64_decode($argv[1] ?? '', true) ?: '', true, 512, JSON_THROW_ON_ERROR);
$pdo = db();
file_put_contents($job['ready'], 'ready');
$deadline = microtime(true) + 20;
while (!is_file($job['gate'])) {
    if (microtime(true) > $deadline) throw new RuntimeException('Concurrent worker start gate timed out');
    usleep(10000);
}
try {
    if ($job['action'] === 'notify') {
        require_once getenv('FOC_TEST_NOTIFICATIONS') ?: dirname(getenv('FOC_TEST_CORE')).'/ticket_notifications.php';
        $result = ticketNotificationWorker($pdo, function ($message, $payload) use ($job) {
            file_put_contents($job['delivery_log'], $message['id'] . PHP_EOL, FILE_APPEND | LOCK_EX);
            usleep((int)($job['pause_ms'] ?? 0) * 1000);
        }, 50);
    } else {
        $result = invoke($pdo, $job['action'], $job['actor'], $job['data']);
    }
    echo json_encode(['result'=>$result], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    echo json_encode(['exception'=>get_class($e), 'message'=>$e->getMessage()], JSON_THROW_ON_ERROR);
    exit(1);
}
