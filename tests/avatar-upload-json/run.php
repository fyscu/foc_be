<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$passed = 0;

function ok(bool $condition, string $message): void
{
    global $passed;
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $passed++;
    echo "ok - {$message}\n";
}

function writeFile(string $path, string $contents): void
{
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException("Cannot create {$directory}");
    }
    if (file_put_contents($path, $contents) === false) {
        throw new RuntimeException("Cannot write {$path}");
    }
}

function removeTree(string $path): void
{
    if (!is_dir($path)) {
        return;
    }
    $items = new FilesystemIterator($path, FilesystemIterator::SKIP_DOTS);
    foreach ($items as $item) {
        if ($item->isDir() && !$item->isLink()) {
            removeTree($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }
    rmdir($path);
}

function runEndpoint(string $runner, string $scenario): array
{
    $pipes = [];
    $process = proc_open(
        [PHP_BINARY, $runner, $scenario],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes
    );
    if (!is_resource($process)) {
        throw new RuntimeException('Cannot start endpoint subprocess');
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    ok($exitCode === 0, "{$scenario} endpoint exits cleanly");
    try {
        $json = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        throw new RuntimeException("{$scenario} response is not pure JSON: " . var_export($stdout, true));
    }
    ok(is_array($json), "{$scenario} response is a JSON object");
    return [$json, $stderr];
}

require $root . '/utils/qiniusdk/qiniu/php-sdk/src/Qiniu/Config.php';

$phpErrors = [];
set_error_handler(static function (int $severity, string $message) use (&$phpErrors): bool {
    $phpErrors[] = [$severity, $message];
    return true;
});
$sdkConfig = new Qiniu\Config();
restore_error_handler();

ok(property_exists($sdkConfig, 'zone'), 'Qiniu Config declares the legacy zone property');
ok($sdkConfig->zone === null, 'Qiniu Config keeps the default null zone behavior');
ok($phpErrors === [], 'Qiniu Config construction emits no PHP 8.3 deprecation');

$fixture = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'foc-avatar-json-' . bin2hex(random_bytes(8));
try {
    writeFile($fixture . '/v1/user/avatar.php', file_get_contents($root . '/v1/user/avatar.php'));
    writeFile($fixture . '/config.php', <<<'PHP'
<?php
return ['qiniu' => [
    'accessKey' => 'test-ak',
    'secretKey' => 'test-sk',
    'bucket' => 'test-bucket',
    'domain' => 'https://cdn.example',
    'uploadUrl' => 'https://upload.example'
]];
PHP);
    foreach (['db.php', 'email.php', 'sms.php', 'gets.php', 'token.php', 'headercheck.php'] as $file) {
        $path = $file === 'db.php' ? $fixture . '/db.php' : $fixture . '/utils/' . $file;
        writeFile($path, "<?php\n");
    }
    writeFile($fixture . '/utils/qiniu_avatar.php', <<<'PHP'
<?php
function uploadImage($file, $accessKey, $secretKey, $bucket, $domain, $uploadUrl) {
    switch (getenv('AVATAR_TEST_SCENARIO')) {
        case 'success':
        case 'signing-error':
            return json_encode(['success' => true, 'data' => 'https://cdn.example/avatar/test.jpg']);
        case 'provider-error':
            return json_encode(['success' => false, 'data' => 'simulated provider failure']);
        case 'invalid-json':
            return 'Deprecated warning before JSON';
        case 'exception':
            throw new RuntimeException('simulated transport exception');
        default:
            return json_encode(['success' => false, 'data' => 'unexpected scenario']);
    }
}
PHP);
    writeFile($fixture . '/utils/qiniu_url.php', <<<'PHP'
<?php
function generatePrivateLink($object, $bucketDomain = null) {
    if (getenv('AVATAR_TEST_SCENARIO') === 'signing-error') {
        throw new RuntimeException('simulated signing exception');
    }
    return 'signed:' . $object;
}
PHP);
    writeFile($fixture . '/runner.php', <<<'PHP'
<?php
$scenario = $argv[1] ?? 'missing-file';
putenv('AVATAR_TEST_SCENARIO=' . $scenario);
$_SERVER['REQUEST_METHOD'] = $scenario === 'options' ? 'OPTIONS' : 'POST';
$_FILES = [];
if ($scenario !== 'missing-file' && $scenario !== 'options') {
    $_FILES['file'] = [
        'name' => 'test.jpg',
        'tmp_name' => __FILE__,
        'error' => UPLOAD_ERR_OK,
        'size' => 1
    ];
}
chdir(__DIR__ . '/v1/user');
require __DIR__ . '/v1/user/avatar.php';
PHP);

    [$success] = runEndpoint($fixture . '/runner.php', 'success');
    ok($success === [
        'success' => true,
        'data' => 'signed:https://cdn.example/avatar/test.jpg',
        'rawdata' => 'https://cdn.example/avatar/test.jpg'
    ], 'success response preserves success/data/rawdata');

    foreach (['provider-error', 'invalid-json', 'exception', 'signing-error'] as $scenario) {
        [$failure] = runEndpoint($fixture . '/runner.php', $scenario);
        ok($failure === ['success' => false, 'data' => '七牛云上传错误'], "{$scenario} returns compatible failure JSON");
    }

    [$missing] = runEndpoint($fixture . '/runner.php', 'missing-file');
    ok($missing === ['success' => false, 'data' => '本地上传错误'], 'missing file returns compatible failure JSON');

    [$options] = runEndpoint($fixture . '/runner.php', 'options');
    ok($options === ['success' => true, 'data' => null], 'OPTIONS response is pure JSON');
} finally {
    removeTree($fixture);
}

echo "PASS ({$passed} assertions)\n";
