<?php
/** Drains durable jobs; transport injected in integration tests (no external sends). */
function ticketNotificationWorker(PDO $pdo, callable $deliver, int $limit = 12): array
{
    $summary = ['sent'=>0,'retry'=>0,'failed'=>0,'superseded'=>0,'busy'=>0];
    $lock = 'foc.ticket.notify.' . $pdo->query('SELECT DATABASE()')->fetchColumn();
    $stmt = $pdo->prepare('SELECT GET_LOCK(?,0)');
    $stmt->execute([$lock]);
    if ((int)$stmt->fetchColumn() !== 1) {
        $summary['busy'] = 1;
        return $summary;
    }
    try {
        $deadline = microtime(true) + 45;
        $pdo->exec(
            "UPDATE fy_ticket_notification_outbox SET status='pending',available_at=NOW(),lease_until=NULL "
            . "WHERE status='processing' AND lease_until < NOW()"
        );
        for ($i=0; $i<max(0,min($limit,50)); $i++) {
            if (microtime(true) >= $deadline) {
                break;
            }
            $pdo->beginTransaction();
            $job = $pdo->query(
                "SELECT * FROM fy_ticket_notification_outbox WHERE status='pending' AND available_at<=NOW() "
                . 'ORDER BY id LIMIT 1 FOR UPDATE SKIP LOCKED'
            )->fetch(PDO::FETCH_ASSOC);
            if (!$job) {
                $pdo->commit();
                break;
            }
            $pdo->prepare(
                "UPDATE fy_ticket_notification_outbox SET status='processing',attempts=attempts+1,"
                . 'lease_until=NOW()+INTERVAL 5 MINUTE WHERE id=?'
            )->execute([$job['id']]);
            $pdo->commit();
            try {
                $payload = json_decode($job['payload'], true, 512, JSON_THROW_ON_ERROR);
                $stmt = $pdo->prepare('SELECT repair_status,assigned_technician_id,transcode FROM fy_workorders WHERE id=?');
                $stmt->execute([$job['ticket_id']]);
                $current = $stmt->fetch(PDO::FETCH_ASSOC);
                $currentMatches = $current
                    && (int)($current['assigned_technician_id'] ?? 0) === (int)$payload['expected_tech'];
                if (in_array($job['kind'], ['assign','transfer'], true)) {
                    $currentMatches = $currentMatches
                        && isset($payload['expected_code'])
                        && (int)$current['transcode'] === (int)$payload['expected_code']
                        && in_array($current['repair_status'], ['Repairing','UserConfirming','TechConfirming'], true);
                } else {
                    $currentMatches = $currentMatches
                        && $current['repair_status'] === $payload['expected_status'];
                }
                if (!$currentMatches) {
                    $pdo->prepare("UPDATE fy_ticket_notification_outbox SET status='superseded',completed_at=NOW(),lease_until=NULL WHERE id=?")
                        ->execute([$job['id']]);
                    $summary['superseded']++;
                    continue;
                }
                $deliver($job, $payload); // Must throw on rejection/timeout, not report a false success.
                $pdo->prepare(
                    "UPDATE fy_ticket_notification_outbox SET status='sent',completed_at=NOW(),lease_until=NULL,last_error=NULL WHERE id=?"
                )->execute([$job['id']]);
                $summary['sent']++;
            } catch (Throwable $e) {
                $attempts = (int)$job['attempts'] + 1;
                $terminal = $attempts >= 8;
                $delay = min(21600, 30 * (2 ** min(10,$attempts-1)));
                $stmt = $pdo->prepare(
                    'UPDATE fy_ticket_notification_outbox SET status=?,available_at=TIMESTAMPADD(SECOND,?,NOW()),'
                    . 'lease_until=NULL,last_error=? WHERE id=?'
                );
                $stmt->execute([$terminal?'failed':'pending', $delay,
                    get_class($e) . ': delivery failed; see provider console', $job['id']]);
                $summary[$terminal?'failed':'retry']++;
                error_log('[ticket.notify] job=' . $job['id'] . ' channel=' . $job['channel'] . ' attempt=' . $attempts . ' result=' . ($terminal?'failed':'retry'));
            }
        }
    } finally {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $pdo->prepare('SELECT RELEASE_LOCK(?)')->execute([$lock]);
    }
    return $summary;
}

function ticketWechatJson(string $url, ?array $payload = null): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER=>true, CURLOPT_CONNECTTIMEOUT=>3, CURLOPT_TIMEOUT=>10,
        CURLOPT_SSL_VERIFYPEER=>true, CURLOPT_SSL_VERIFYHOST=>2,
    ]);
    if ($payload !== null) {
        curl_setopt_array($ch, [
            CURLOPT_POST=>true, CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
            CURLOPT_POSTFIELDS=>json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),
        ]);
    }
    $raw = curl_exec($ch);
    $status = curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if ($raw === false || $status !== 200) {
        throw new RuntimeException('Wechat transport failed');
    }
    $data = json_decode($raw,true,512,JSON_THROW_ON_ERROR);
    if (!is_array($data)) {
        throw new RuntimeException('Invalid Wechat response');
    }
    return $data;
}

function ticketDeliverNotification(array $config, array $job, array $p): void
{
    $kind = $job['kind'];
    $isTech = $p['recipient'] === 'tech';
    $id = $p['ticket_id'];
    if ($job['channel'] === 'sms') {
        $template = $kind === 'complete' ? 'completion'
            : ($kind === 'close' ? 'beclosed' : ($isTech?'assign_to_technician':'assign_to_user'));
        if (empty($config['sms']['template_ids'][$template])) {
            throw new RuntimeException('Missing SMS template');
        }
        $params = in_array($kind,['close','complete'],true) ? [] : ($isTech
            ? ['tech'=>$p['tech_name'],'mate'=>$p['owner_name'],'maten'=>$p['user_phone']]
            : ['mate'=>$p['owner_name'],'tech'=>$p['tech_name'],'techn'=>$p['tech_phone']]);
        require_once __DIR__ . '/sms.php';
        $reply = (new Sms($config))->sendSms($template,$p['address'],$params);
        if (!is_array($reply) || ($reply['Code'] ?? '') !== 'OK'
            || (array_key_exists('Success',$reply) && !$reply['Success'])) {
            throw new RuntimeException('SMS provider rejected notification');
        }
        return;
    }
    if ($job['channel'] === 'email') {
        require_once __DIR__ . '/phpmailer/Exception.php';
        require_once __DIR__ . '/phpmailer/PHPMailer.php';
        require_once __DIR__ . '/phpmailer/SMTP.php';
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $email = $config['email'];
        $mail->isSMTP();
        $mail->Host = $email['smtp_host'];
        $mail->SMTPAuth = true;
        $mail->Username = $email['username'];
        $mail->Password = $email['password'];
        $mail->SMTPSecure = 'ssl';
        $mail->Port = $email['smtp_port'];
        $mail->CharSet = 'UTF-8';
        $mail->Timeout = 10;
        $mail->Timelimit = 15;
        $mail->setFrom($email['username'],'飞扬俱乐部');
        $mail->addAddress($p['address']);
        $mail->isHTML(false);
        if ($kind === 'complete') {
            $mail->Subject = '报修工单已完成';
            $mail->Body = "您的报修工单 单号：$id 已维修完成，请及时取回。";
        } elseif ($isTech) {
            $mail->Subject = '新的报修工单';
            $mail->Body = "亲爱的技术员{$p['tech_name']}，您有一个新的报修工单，工单编号：$id。用户联系方式：{$p['user_phone']}，请尽快联系用户！";
        } else {
            $mail->Subject = $kind === 'transfer' ? '报修工单已重新分配' : '报修工单已分配';
            $mail->Body = "您的报修工单（单号：$id）已分配给技术员{$p['tech_name']}。技术员联系方式：{$p['tech_phone']}。";
        }
        if (!$mail->send()) {
            throw new RuntimeException('Email delivery failed');
        }
        return;
    }
    if ($job['channel'] === 'wechat') {
        static $access = null;
        static $expires = 0;
        if (!$access || $expires < time()) {
            $token = ticketWechatJson('https://api.weixin.qq.com/cgi-bin/token?' . http_build_query([
                'grant_type'=>'client_credential','appid'=>$config['wechat']['app_id'],
                'secret'=>$config['wechat']['app_secret'],
            ]));
            if (empty($token['access_token'])) {
                throw new RuntimeException('Wechat token unavailable');
            }
            $access = $token['access_token'];
            $expires = time() + max(1,(int)($token['expires_in'] ?? 60)-30);
        }
        $values = $isTech
            ? ['character_string1'=>$id, 'short_thing2'=>mb_substr($p['owner_name'] ?: '神秘用户',0,10),
                'thing4'=>mb_substr($p['fault_type'],0,20), 'time6'=>$p['create_time'],
                'thing11'=>mb_substr('联系方式：' . $p['qq_number'],0,20)]
            : ['thing2'=>mb_substr($p['fault_type'],0,20), 'phone_number5'=>$p['tech_phone'],
                'thing10'=>'工单已被接单，请查看小程序'];
        $messageData = [];
        foreach ($values as $k=>$v) {
            $messageData[$k] = ['value'=>(string)$v];
        }
        $reply = ticketWechatJson('https://api.weixin.qq.com/cgi-bin/message/subscribe/send?access_token=' . rawurlencode($access),[
            'touser'=>$p['address'],
            'template_id'=>$isTech?'KMe-rYXD_Js_X3oE9_t6qMoa6DMm07Dfzeq94bsMvxg':'FGhVRnNp7C4580nyAXMOqSvSZCNG36cd6nEInS_RVCs',
            'page'=>'pages/homePage/ticketDetail/index?id=' . $id . '&role=' . ($isTech?'technician':'user'),
            'data'=>$messageData,'miniprogram_state'=>'formal',
        ]);
        if (($reply['errcode'] ?? -1) !== 0) {
            if (in_array($reply['errcode'] ?? -1,[40001,40014,42001],true)) {
                $access = null;
            }
            throw new RuntimeException('Wechat notification rejected');
        }
        return;
    }
    throw new RuntimeException('Unknown notification channel');
}
