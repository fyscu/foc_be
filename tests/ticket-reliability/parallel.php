<?php
function concurrent(array $jobs): array {
    $base = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . '/foc-reliability-test-' . bin2hex(random_bytes(6));
    if (!mkdir($base, 0700)) throw new RuntimeException('Cannot create test barrier directory.');
    $gate = $base . '/start';
    $processes = [];
    $created = [];
    try {
        foreach ($jobs as $i => $job) {
            $job['gate'] = $gate;
            $job['ready'] = $base . '/ready-' . $i;
            $created[] = $job['ready'];
            $encoded = base64_encode(json_encode($job, JSON_THROW_ON_ERROR));
            $pipes = [];
            $p = proc_open([PHP_BINARY,__DIR__.'/worker.php',$encoded], [
                0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']
            ], $pipes);
            if (!is_resource($p)) throw new RuntimeException('Unable to start parallel PHP worker.');
            fclose($pipes[0]);
            $processes[] = [$p, $pipes];
        }
        $deadline = microtime(true) + 20;
        do {
            $ready = true;
            foreach ($created as $file) $ready = $ready && is_file($file);
            if ($ready) break;
            if (microtime(true) > $deadline) throw new RuntimeException('Workers failed to connect before concurrency test.');
            usleep(10000);
        } while (true);
        file_put_contents($gate,'start');
        $created[] = $gate;
        $results = [];
        foreach ($processes as [$p,$pipes]) {
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]);
            $exit = proc_close($p);
            $decoded = json_decode($stdout,true);
            if ($exit !== 0 || !is_array($decoded) || !array_key_exists('result',$decoded)) {
                throw new RuntimeException('Concurrent worker failed: ' . $stdout . ' ' . $stderr);
            }
            $results[] = $decoded['result'];
        }
        $processes = [];
        return $results;
    } finally {
        foreach ($processes as [$p,$pipes]) {
            if (is_resource($p)) proc_terminate($p);
            foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
            if (is_resource($p)) proc_close($p);
        }
        // Only explicitly created barrier files are removed; no recursive deletion.
        foreach ($created as $file) if (is_file($file)) unlink($file);
        if (is_dir($base) && count(scandir($base)) === 2) rmdir($base);
    }
}
