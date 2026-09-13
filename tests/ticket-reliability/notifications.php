<?php
require __DIR__.'/bootstrap.php';
require __DIR__.'/parallel.php';
$pdo=db();
require_once getenv('FOC_TEST_NOTIFICATIONS') ?: dirname(getenv('FOC_TEST_CORE')).'/ticket_notifications.php';
function notificationJob(PDO $pdo,int $ticket,string $channel='email',array $overrides=[]): int {
    $payload=array_merge([
        'ticket_id'=>$ticket,'expected_tech'=>20,'expected_status'=>'Repairing','expected_code'=>123456,
        'address'=>'fixture@example.invalid','recipient'=>'tech'
    ],$overrides['payload']??[]);
    $data=array_merge([
        'event_id'=>bin2hex(random_bytes(16)),'job_key'=>bin2hex(random_bytes(8)),
        'ticket_id'=>$ticket,'kind'=>'assign','channel'=>$channel,
        'payload'=>json_encode($payload,JSON_THROW_ON_ERROR),
        'status'=>'pending','attempts'=>0,'available_at'=>'2000-01-01 00:00:00'
    ],array_diff_key($overrides,['payload'=>true]));
    $s=$pdo->prepare('INSERT INTO fy_ticket_notification_outbox ('.implode(',',array_keys($data)).') VALUES ('.implode(',',array_fill(0,count($data),'?')).')');
    $s->execute(array_values($data));
    return (int)$pdo->lastInsertId();
}
function notificationRow(PDO $pdo,int $id): array {
    $s=$pdo->prepare('SELECT * FROM fy_ticket_notification_outbox WHERE id=?');
    $s->execute([$id]);return $s->fetch() ?: [];
}
$cases=[];$id=20269992001;
$cases['successful_channels_are_never_resent']=function()use($pdo,$id) {
    ticket($pdo,$id);
    $jobIds=[notificationJob($pdo,$id,'sms'),notificationJob($pdo,$id,'email'),notificationJob($pdo,$id,'wechat')];
    $delivered=[];
    $stub=function($job,$payload)use(&$delivered){$delivered[]=(int)$job['id'];};
    $first=ticketNotificationWorker($pdo,$stub);
    eq(3,$first['sent'],'three channels delivered');
    eq($jobIds,$delivered,'each job exactly once');
    $second=ticketNotificationWorker($pdo,$stub);
    eq(0,$second['sent'],'sent jobs not redelivered');
    eq($jobIds,$delivered,'no duplicate delivery callback');
    foreach($jobIds as $jid) {
        $r=notificationRow($pdo,$jid);
        eq('sent',$r['status'],'sent persisted');
        eq(1,(int)$r['attempts'],'one delivery attempt');
        ok(!empty($r['completed_at']) && $r['lease_until']===null,'completion lease cleared');
    }
};
$cases['one_failed_channel_does_not_block_others_and_retries_later']=function()use($pdo,$id) {
    ticket($pdo,$id);
    $sms=notificationJob($pdo,$id,'sms');
    $email=notificationJob($pdo,$id,'email');
    $wechat=notificationJob($pdo,$id,'wechat');
    $delivered=[];
    $result=ticketNotificationWorker($pdo,function($job,$payload)use(&$delivered){
        if($job['channel']==='sms')throw new RuntimeException('synthetic transport timeout with secret=SHOULD_NOT_LOG');
        $delivered[]=(int)$job['id'];
    });
    eq(2,$result['sent'],'other channels sent');
    eq(1,$result['retry'],'failed channel queued for retry');
    eq([$email,$wechat],$delivered,'independent channels delivered');
    $pending=notificationRow($pdo,$sms);
    eq('pending',$pending['status'],'retry persisted');
    eq(1,(int)$pending['attempts'],'failure counted');
    $s=$pdo->prepare('SELECT TIMESTAMPDIFF(SECOND,NOW(),available_at) FROM fy_ticket_notification_outbox WHERE id=?');
    $s->execute([$sms]); $delay=(int)$s->fetchColumn();
    ok($delay>0 && $delay<=30,'first retry is delayed, not immediate');
    ok(strpos($pending['last_error'],'SHOULD_NOT_LOG')===false,'error log omits sensitive provider details');
    $immediate=ticketNotificationWorker($pdo,function(){throw new RuntimeException('must not retry early');});
    eq(0,$immediate['retry'],'pending future job skipped');
    $pdo->prepare("UPDATE fy_ticket_notification_outbox SET available_at='2000-01-01' WHERE id=?")->execute([$sms]);
    $retried=[];
    $next=ticketNotificationWorker($pdo,function($job)use(&$retried){$retried[]=(int)$job['id'];});
    eq(1,$next['sent'],'failed channel later succeeds');
    eq([$sms],$retried,'successful other channels not duplicated');
    eq(2,(int)notificationRow($pdo,$sms)['attempts'],'second attempt persisted');
};
$cases['eight_failures_enter_terminal_failed_state']=function()use($pdo,$id) {
    ticket($pdo,$id);
    $jid=notificationJob($pdo,$id,'email',['attempts'=>7]);
    $result=ticketNotificationWorker($pdo,function(){throw new RuntimeException('synthetic failure');});
    eq(1,$result['failed'],'eighth failure terminal');
    $r=notificationRow($pdo,$jid);
    eq('failed',$r['status'],'failed state durable');
    eq(8,(int)$r['attempts'],'exactly eight attempts');
    $calls=0;
    ticketNotificationWorker($pdo,function()use(&$calls){$calls++;});
    eq(0,$calls,'failed job not continually retried');
};
$cases['stale_assignment_and_terminal_notifications_are_superseded']=function()use($pdo,$id) {
    ticket($pdo,$id,'Repairing',30);
    $a=notificationJob($pdo,$id,'email',['payload'=>['expected_tech'=>20]]);
    $b=notificationJob($pdo,$id,'sms',['kind'=>'complete','payload'=>['expected_tech'=>30,'expected_status'=>'Done']]);
    $c=notificationJob($pdo,$id+100,'wechat');
    $calls=0;
    $result=ticketNotificationWorker($pdo,function()use(&$calls){$calls++;});
    eq(0,$calls,'stale jobs never dispatched');
    eq(3,$result['superseded'],'all stale contexts superseded');
    foreach([$a,$b,$c]as$jid)eq('superseded',notificationRow($pdo,$jid)['status'],'superseded durable');
};
$cases['expired_delivery_lease_is_recovered_but_active_lease_is_respected']=function()use($pdo,$id) {
    ticket($pdo,$id);
    $expired=notificationJob($pdo,$id,'email',[
        'status'=>'processing','attempts'=>1,'lease_until'=>'2000-01-01 00:00:00'
    ]);
    $active=notificationJob($pdo,$id,'sms',[
        'status'=>'processing','attempts'=>1,'lease_until'=>'2099-01-01 00:00:00'
    ]);
    $delivered=[];
    $result=ticketNotificationWorker($pdo,function($job)use(&$delivered){$delivered[]=(int)$job['id'];});
    eq(1,$result['sent'],'expired lease recovered');
    eq([$expired],$delivered,'active lease not stolen');
    eq(2,(int)notificationRow($pdo,$expired)['attempts'],'recovered attempt incremented');
    eq('processing',notificationRow($pdo,$active)['status'],'active lease retained');
};
$cases['concurrent_workers_use_named_lock_and_never_duplicate_jobs']=function()use($pdo,$id) {
    ticket($pdo,$id);
    $jobIds=[notificationJob($pdo,$id,'sms'),notificationJob($pdo,$id,'email'),notificationJob($pdo,$id,'wechat')];
    $log=rtrim(sys_get_temp_dir(),DIRECTORY_SEPARATOR).'/foc-notification-delivery-test-'.bin2hex(random_bytes(6)).'.log';
    file_put_contents($log,'');
    try {
        $job=['action'=>'notify','delivery_log'=>$log,'pause_ms'=>250];
        $results=concurrent([$job,$job]);
        eq(1,array_sum(array_column($results,'busy')),'one worker observes held singleton lock');
        eq(3,array_sum(array_column($results,'sent')),'one worker drains all jobs');
        $lines=array_values(array_filter(explode("\n",trim(file_get_contents($log))),fn($x)=>$x!==''));
        $actual=array_map('intval',$lines);sort($actual);
        eq($jobIds,$actual,'no duplicate cross-process delivery callbacks');
        $lock='foc.ticket.notify.'.$pdo->query('SELECT DATABASE()')->fetchColumn();
        $s=$pdo->prepare('SELECT IS_FREE_LOCK(?)');$s->execute([$lock]);
        eq(1,(int)$s->fetchColumn(),'lock released after worker exits');
    }finally{if(is_file($log))unlink($log);}
};
$cases['worker_releases_named_lock_after_failure_and_keeps_business_state']=function()use($pdo,$id) {
    ticket($pdo,$id);
    notificationJob($pdo,$id,'email');
    $before=row($pdo,'fy_workorders',$id);
    ticketNotificationWorker($pdo,function(){throw new RuntimeException('fixture provider failure');});
    eq($before,row($pdo,'fy_workorders',$id),'notification failure cannot alter committed ticket');
    $other=db();$lock='foc.ticket.notify.'.$pdo->query('SELECT DATABASE()')->fetchColumn();
    $s=$other->prepare('SELECT GET_LOCK(?,0)');$s->execute([$lock]);
    eq(1,(int)$s->fetchColumn(),'next worker can acquire lock after failure');
    $other->prepare('SELECT RELEASE_LOCK(?)')->execute([$lock]);
};
$cases['returning_to_previous_technician_does_not_revive_old_assignment_notice']=function()use($pdo,$id) {
    loadCore();
    ticket($pdo,$id,'Repairing',20);
    $oldJob=notificationJob($pdo,$id,'email',['payload'=>['expected_tech'=>20,'expected_code'=>123456]]);
    accepted(invoke($pdo,'give',30,['order_id'=>$id,'tvcode'=>'123456']),'A to B transfer');
    $middle=row($pdo,'fy_workorders',$id);
    accepted(invoke($pdo,'give',20,['order_id'=>$id,'tvcode'=>(string)$middle['transcode']]),'B to A transfer');
    $current=row($pdo,'fy_workorders',$id);
    eq(20,(int)$current['assigned_technician_id'],'returned to original technician');
    $delivered=[];
    $summary=ticketNotificationWorker($pdo,function($job,$payload)use(&$delivered,$current){
        eq((int)$current['transcode'],(int)$payload['expected_code'],'only current assignment code delivered');
        $delivered[]=(int)$job['id'];
    },50);
    eq('superseded',notificationRow($pdo,$oldJob)['status'],'old original-technician notice remains obsolete');
    ok(!in_array($oldJob,$delivered,true),'A-B-A never sends original A notice');
    ok($summary['sent']>0,'latest assignment still gets notifications');
    ok($summary['superseded']>0,'older transfer notices discarded');
};
$passed=0;$total=count($cases);
foreach($cases as$name=>$case) {
    fixture($pdo);
    if(runTest($name,$case))$passed++;
    if($pdo->inTransaction())$pdo->rollBack();
}
echo json_encode(['suite'=>'durable-notification-worker','passed'=>$passed,'total'=>$total],JSON_THROW_ON_ERROR).PHP_EOL;
exit($passed===$total?0:1);
