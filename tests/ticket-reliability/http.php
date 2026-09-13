<?php
require __DIR__.'/bootstrap.php';
$pdo=db();
$webroot=realpath(getenv('FOC_TEST_WEB_ROOT') ?: '');
if(!$webroot || !is_dir($webroot)) throw new RuntimeException('FOC_TEST_WEB_ROOT must contain actual candidate endpoints and isolated config/db.php.');
$testWebConfig=require $webroot.'/config.php';
if(($testWebConfig['db']['dbname']??'') !== $pdo->query('SELECT DATABASE()')->fetchColumn()) {
    throw new RuntimeException('Refusing HTTP writes: candidate config must select exactly the guarded disposable test schema.');
}
foreach(['give','set','complete'] as $action) {
    if(!is_file($webroot.'/v1/ticket/'.$action.'.php')) throw new RuntimeException('Actual endpoint missing: '.$action);
}
$port=(int)(getenv('FOC_TEST_HTTP_PORT') ?: 0);
if(!$port) {
    $listener=stream_socket_server('tcp://127.0.0.1:0',$errno,$errstr);
    if(!$listener) throw new RuntimeException('Unable to reserve localhost test port: '.$errstr);
    $port=(int)substr(strrchr(stream_socket_get_name($listener,false),':'),1);
    fclose($listener);
}
$address='127.0.0.1:'.$port;
$pipes=[];
$server=proc_open([PHP_BINARY,'-d','display_errors=0','-S',$address,__DIR__.'/router.php'], [
    0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']
],$pipes);
if(!is_resource($server)) throw new RuntimeException('Unable to start isolated PHP HTTP server');
fclose($pipes[0]);
stream_set_blocking($pipes[1],false);stream_set_blocking($pipes[2],false);
$httpBase='http://'.$address;

function httpRequest(string $action, $body, ?string $token, string $header='Authorization',string $method='POST'): array {
    global $httpBase;
    $headers=['Content-Type: application/json'];
    if($token!==null) $headers[]=$header.': Bearer '.$token;
    $context=stream_context_create(['http'=>[
        'method'=>$method,'header'=>implode("\r\n",$headers),
        'content'=>is_string($body)?$body:json_encode($body,JSON_THROW_ON_ERROR),
        'ignore_errors'=>true,'timeout'=>10,
    ]]);
    $path=$action==='admin/setTicket'?'/v1/admin/setTicket.php':'/v1/ticket/'.$action;
    $result=file_get_contents($httpBase.$path,false,$context);
    if($result===false) throw new RuntimeException('HTTP request failed');
    $status=0;
    foreach($http_response_header??[] as $line) {
        if(preg_match('#^HTTP/\\S+ (\\d+)#',$line,$m)) $status=(int)$m[1];
    }
    $data=json_decode($result,true);
    if($method!=='OPTIONS' && !is_array($data)) {
        throw new RuntimeException('Endpoint did not return clean JSON: '.substr($result,0,300));
    }
    return ['status'=>$status,'json'=>$data,'raw'=>$result];
}
$cases=[];
$id=20269991001;
foreach([
    ['legacy',hash('sha256','fixture-legacy-20'),20],
    ['admin',hash('sha256','fixture-admin-40'),40],
    ['app',hash('sha256','fixture-app-30'),30]
] as [$label,$token,$uid]) {
    $cases['actual_http_'.$label.'_token_give_edit_complete']=function()use($pdo,$id,$token,$uid) {
        ticket($pdo,$id,'Pending',null);
        $target=$uid===40?20:$uid;
        $give=httpRequest('give',['order_id'=>(string)$id,'tvcode'=>'123456','tid'=>$target],$token,'authorization');
        eq(200,$give['status'],'lowercase authorization accepted');
        accepted($give['json'],'give HTTP JSON');
        eq($target,(int)row($pdo,'fy_workorders',$id)['assigned_technician_id'],'actual assignment');
        $set=httpRequest('set',['tid'=>(string)$id,'repair_description'=>'Updated through real HTTP '.$uid],$token);
        eq(200,$set['status'],'edit HTTP status');
        accepted($set['json'],'edit HTTP JSON');
        eq('Updated through real HTTP '.$uid,row($pdo,'fy_workorders',$id)['repair_description'],'actual edit committed');
        $done=httpRequest('complete',['order_id'=>(string)$id],$token,'AUTHORIZATION');
        eq(200,$done['status'],'uppercase authorization accepted');
        accepted($done['json'],'complete HTTP JSON');
        eq('Done',row($pdo,'fy_workorders',$id)['repair_status'],'actual completion committed');
    };
}
$cases['actual_http_missing_invalid_and_expired_tokens_return_401']=function()use($pdo,$id) {
    ticket($pdo,$id);
    foreach([null,'invalid-fixture-token'] as $token) {
        foreach(['give','set','complete'] as $action) {
            eq(401,httpRequest($action,['order_id'=>$id,'tid'=>$id,'tvcode'=>'123456'],$token)['status'],'invalid auth '.$action);
        }
    }
    $pdo->exec("UPDATE fy_users SET token_expiry=DATE_SUB(NOW(), INTERVAL 1 DAY)");
    $pdo->exec("UPDATE fy_admin_tokens SET expires_at=DATE_SUB(NOW(), INTERVAL 1 DAY)");
    $pdo->exec("UPDATE fy_app_tokens SET expires_at=DATE_SUB(NOW(), INTERVAL 1 DAY)");
    foreach(['fixture-legacy-20','fixture-admin-40','fixture-app-30'] as $seed) {
        eq(401,httpRequest('complete',['order_id'=>$id],hash('sha256',$seed))['status'],'expired token '.$seed);
    }
    eq('Repairing',row($pdo,'fy_workorders',$id)['repair_status'],'invalid authentication cannot mutate');
};
$cases['actual_http_invalid_json_and_id_return_400']=function()use($pdo,$id) {
    ticket($pdo,$id);
    $token=hash('sha256','fixture-legacy-20');
    foreach(['give','set','complete'] as $action) {
        foreach(['{broken-json','null','[]','42'] as $invalid) {
            eq(400,httpRequest($action,$invalid,$token)['status'],'malformed/non-object request '.$action);
        }
        eq(400,httpRequest($action,['order_id'=>'20269991001junk','tid'=>'20269991001junk'],$token)['status'],'malformed id '.$action);
    }
    eq('Repairing',row($pdo,'fy_workorders',$id)['repair_status'],'bad payload does not alter ticket');
};
$cases['actual_http_options_is_unauthenticated_preflight']=function() {
    foreach(['give','set','complete'] as $action) {
        $r=httpRequest($action,'',null,'Authorization','OPTIONS');
        ok(in_array($r['status'],[200,204],true),'preflight accepted '.$action);
    }
};
$cases['actual_admin_setTicket_id_contract_and_permissions']=function()use($pdo,$id) {
    ticket($pdo,$id);
    $admin=hash('sha256','fixture-admin-40');
    $wrongRoute=httpRequest('admin/setTicket',[
        'id'=>(string)$id,'repair_description'=>'Must use normal editing route'
    ],$admin);
    eq(400,$wrongRoute['status'],'legacy admin route remains status-only');
    $blocked=httpRequest('admin/setTicket',['id'=>$id,'repair_status'=>'Closed'],hash('sha256','fixture-app-30'));
    eq(403,$blocked['status'],'technician cannot use admin-only route');
    eq('Repairing',row($pdo,'fy_workorders',$id)['repair_status'],'blocked close not saved');
    $closed=httpRequest('admin/setTicket',['id'=>(string)$id,'repair_status'=>'Closed'],$admin);
    eq(200,$closed['status'],'admin closes through existing id contract');
    accepted($closed['json'],'admin close');
    eq('Closed',row($pdo,'fy_workorders',$id)['repair_status'],'admin close persisted');
    eq(3,(int)row($pdo,'fy_users',10)['available'],'admin close refunds one quota');
    eq(1,(int)row($pdo,'fy_users',20)['available'],'admin close releases assigned technician');
    $logs=$pdo->query('SELECT * FROM fy_admin_logs ORDER BY id')->fetchAll();
    eq(1,count($logs),'admin audit preserved exactly once');
    eq('fixture-admin',$logs[0]['admin_username'],'authoritative admin username logged');
    eq('super',$logs[0]['admin_role'],'super role logged');
    eq('set_ticket',$logs[0]['action'],'original admin audit action preserved');
    eq((string)$id,$logs[0]['target_id'],'large ticket id logged without truncation');
    accepted(httpRequest('admin/setTicket',['id'=>$id,'repair_status'=>'Closed'],$admin)['json'],'repeat admin close');
    eq(1,(int)$pdo->query('SELECT COUNT(*) FROM fy_admin_logs')->fetchColumn(),'no repeated audit on idempotent retry');
    eq(3,(int)row($pdo,'fy_users',10)['available'],'repeated admin close cannot refund again');
    accepted(httpRequest('admin/setTicket',['id'=>$id,'repair_status'=>'Canceled'],$admin)['json'],'terminal close/cancel retry returns current final state');
    eq('Closed',row($pdo,'fy_workorders',$id)['repair_status'],'alternate terminal label does not rewrite closure');
    eq(1,(int)$pdo->query('SELECT COUNT(*) FROM fy_admin_logs')->fetchColumn(),'no false audit for unapplied alternate terminal label');
    eq(3,(int)row($pdo,'fy_users',10)['available'],'alternate terminal label does not refund twice');
    $pdo->exec("UPDATE fy_admins SET role='lucky' WHERE id=1");
    eq(403,httpRequest('admin/setTicket',['id'=>$id,'repair_status'=>'Closed'],$admin)['status'],'non-super admin role remains forbidden');
};
$cases['actual_http_failure_is_clean_json_and_transaction_rolls_back']=function()use($pdo,$id) {
    ticket($pdo,$id);
    $before=row($pdo,'fy_workorders',$id);
    $pdo->exec("CREATE TRIGGER foc_test_outbox_fail BEFORE INSERT ON fy_ticket_notification_outbox FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='sensitive synthetic SQL detail must not reach HTTP'");
    try {
        $r=httpRequest('give',['order_id'=>$id,'tvcode'=>'123456'],hash('sha256','fixture-app-30'));
        eq(500,$r['status'],'database failure HTTP status');
        rejected($r['json'],'database failure not reported success');
        ok(strpos($r['raw'],'SQLSTATE')===false,'response omits SQL details');
        ok(strpos($r['raw'],'sensitive synthetic')===false,'response omits internal fault message');
        eq($before,row($pdo,'fy_workorders',$id),'HTTP failure rolled back ticket');
        eq(0,auditCount($pdo,$id),'HTTP failure rolled back audit');
    }finally{$pdo->exec('DROP TRIGGER foc_test_outbox_fail');}
};
$cases['actual_http_request_size_and_method_limits']=function()use($pdo,$id) {
    ticket($pdo,$id);
    $token=hash('sha256','fixture-legacy-20');
    eq(413,httpRequest('set',['tid'=>$id,'repair_description'=>str_repeat('x',131073)],$token)['status'],'oversized request rejected');
    eq(405,httpRequest('complete','',$token,'Authorization','GET')['status'],'GET cannot mutate');
    eq('Repairing',row($pdo,'fy_workorders',$id)['repair_status'],'size/method errors do not change ticket');
};
$passed=0;$total=count($cases);
try{
    $deadline=microtime(true)+10;
    do {
        $probe=@stream_socket_client('tcp://'.$address,$errno,$error,0.2);
        if($probe){fclose($probe);break;}
        if(microtime(true)>$deadline) throw new RuntimeException('PHP HTTP server not ready');
        usleep(20000);
    }while(true);
    foreach($cases as $name=>$case) {
        fixture($pdo);
        if(runTest($name,$case))$passed++;
        // Drain routine webserver logs to avoid pipe backpressure.
        stream_get_contents($pipes[1]); stream_get_contents($pipes[2]);
    }
}finally{
    proc_terminate($server);
    foreach($pipes as $pipe)if(is_resource($pipe))fclose($pipe);
    proc_close($server);
}
echo json_encode(['suite'=>'actual-ticket-http-endpoints','passed'=>$passed,'total'=>$total],JSON_THROW_ON_ERROR).PHP_EOL;
exit($passed===$total?0:1);
