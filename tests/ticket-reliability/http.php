<?php
require __DIR__.'/bootstrap.php';
$pdo=db();
$webroot=realpath(getenv('FOC_TEST_WEB_ROOT') ?: '');
if(!$webroot || !is_dir($webroot)) throw new RuntimeException('FOC_TEST_WEB_ROOT must contain actual candidate endpoints and isolated config/db.php.');
$testWebConfig=require $webroot.'/config.php';
if(($testWebConfig['db']['dbname']??'') !== $pdo->query('SELECT DATABASE()')->fetchColumn()) {
    throw new RuntimeException('Refusing HTTP writes: candidate config must select exactly the guarded disposable test schema.');
}
foreach(['give','set','complete','create','restore_archive'] as $action) {
    if(!is_file($webroot.'/v1/ticket/'.$action.'.php')) throw new RuntimeException('Actual endpoint missing: '.$action);
}
if(!is_file($webroot.'/v1/status/getTicket.php')) throw new RuntimeException('Actual endpoint missing: status/getTicket');
$port=(int)(getenv('FOC_TEST_HTTP_PORT') ?: 0);
if(!$port) {
    $listener=stream_socket_server('tcp://127.0.0.1:0',$errno,$errstr);
    if(!$listener) throw new RuntimeException('Unable to reserve localhost test port: '.$errstr);
    $port=(int)substr(strrchr(stream_socket_get_name($listener,false),':'),1);
    fclose($listener);
}
$address='127.0.0.1:'.$port;
$pipes=[];
$server=proc_open([PHP_BINARY,'-d','display_errors=0','-d','log_errors=1','-d','error_log=/dev/stderr','-S',$address,__DIR__.'/router.php'], [
    0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']
],$pipes);
if(!is_resource($server)) throw new RuntimeException('Unable to start isolated PHP HTTP server');
fclose($pipes[0]);
stream_set_blocking($pipes[1],false);stream_set_blocking($pipes[2],false);
$httpBase='http://'.$address;

function httpRequest(string $action, $body, ?string $token, string $header='Authorization',string $method='POST',array $query=[]): array {
    global $httpBase,$pipes;
    $headers=['Content-Type: application/json'];
    if($token!==null) $headers[]=$header.': Bearer '.$token;
    $context=stream_context_create(['http'=>[
        'method'=>$method,'header'=>implode("\r\n",$headers),
        'content'=>is_string($body)?$body:json_encode($body,JSON_THROW_ON_ERROR),
        'ignore_errors'=>true,'timeout'=>10,
    ]]);
    if($action==='admin/setTicket') $path='/v1/admin/setTicket.php';
    elseif($action==='status/getTicket') $path='/v1/status/getTicket.php';
    else $path='/v1/ticket/'.$action;
    $url=$httpBase.$path.($query?'?'.http_build_query($query):'');
    $result=file_get_contents($url,false,$context);
    if($result===false) throw new RuntimeException('HTTP request failed');
    $status=0;
    foreach($http_response_header??[] as $line) {
        if(preg_match('#^HTTP/\\S+ (\\d+)#',$line,$m)) $status=(int)$m[1];
    }
    $data=json_decode($result,true);
    if($method!=='OPTIONS' && !is_array($data)) {
        $serverError=is_resource($pipes[2])?stream_get_contents($pipes[2]):'';
        throw new RuntimeException('Endpoint did not return clean JSON: '.substr($result,0,300).' server='.substr($serverError,0,1000));
    }
    return ['status'=>$status,'json'=>$data,'raw'=>$result];
}

function validCreatePayload(): array {
    return [
        'uid'=>'10','phone'=>'10000000000','purchase_date'=>'2020-01-01',
        'device_type'=>'computer','brand'=>'Fixture brand',
        'description'=>'Synthetic create endpoint test',
        'image'=>'https://focapp.feiyang.ac.cn/public/ticketdefault.svg',
        'fault_type'=>'fixture','qq'=>'10000','campus'=>'江安','DuoCampus'=>0,
        'warranty_status'=>'unknown','model'=>'Fixture model','user_nick'=>'Fixture customer',
    ];
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
$cases['actual_http_create_requires_explicit_global_flag']=function()use($pdo) {
    $token=hash('sha256','fixture-legacy-10');
    $created=httpRequest('create',validCreatePayload(),$token);
    eq(200,$created['status'],'enabled repair creation HTTP status');
    accepted($created['json'],'explicit Global_Flag=1 permits creation');
    ok(is_string($created['json']['orderid']??null),'created order id is a string');
    eq(1,(int)$pdo->query('SELECT COUNT(*) FROM fy_workorders')->fetchColumn(),'enabled request creates one order');
};
$cases['actual_http_create_fails_closed_for_disabled_or_indeterminate_flag']=function()use($pdo) {
    $token=hash('sha256','fixture-legacy-10');
    $states=[
        'disabled'=>function()use($pdo){$pdo->exec("UPDATE fy_confs SET data='0' WHERE name='Global_Flag'");},
        'malformed'=>function()use($pdo){$pdo->exec("UPDATE fy_confs SET data='true' WHERE name='Global_Flag'");},
        'missing'=>function()use($pdo){$pdo->exec("DELETE FROM fy_confs WHERE name='Global_Flag'");},
        'duplicate'=>function()use($pdo){$pdo->exec("INSERT INTO fy_confs(name,info,data) VALUES ('Global_Flag','duplicate','1'),('Global_Flag','duplicate','1')");},
    ];
    foreach($states as $label=>$arrange) {
        $pdo->exec("DELETE FROM fy_confs WHERE name='Global_Flag'");
        if($label!=='missing') $pdo->exec("INSERT INTO fy_confs(name,info,data) VALUES ('Global_Flag','test','1')");
        $arrange();
        $before=(int)$pdo->query('SELECT available FROM fy_users WHERE id=10')->fetchColumn();
        $result=httpRequest('create',validCreatePayload(),$token);
        eq(503,$result['status'],'fail-closed HTTP status '.$label);
        rejected($result['json'],'creation rejected '.$label);
        eq('repair_paused',$result['json']['status']??null,'stable paused status '.$label);
        eq(0,(int)$pdo->query('SELECT COUNT(*) FROM fy_workorders')->fetchColumn(),'no order created '.$label);
        eq($before,(int)$pdo->query('SELECT available FROM fy_users WHERE id=10')->fetchColumn(),'quota unchanged '.$label);
    }
};
$cases['actual_http_restore_preserves_ids_above_javascript_safe_integer']=function()use($pdo) {
    $archiveId=9007199254740993;
    ticket($pdo,$archiveId,'Canceled',null,10,['archived'=>1,'restored_from'=>null]);
    $token=hash('sha256','fixture-legacy-10');
    $restored=httpRequest('restore_archive',['archive_id'=>(string)$archiveId],$token);
    eq(200,$restored['status'],'restore HTTP status');
    accepted($restored['json'],'large archive id restored');
    $newId=$restored['json']['orderid']??null;
    eq('9007199254740994',$newId,'generated id remains lossless JSON string');
    ok(is_string($newId),'generated id JSON type');
    eq((string)$archiveId,(string)row($pdo,'fy_workorders',(int)$newId)['restored_from'],'new order links to exact archive id');
    eq($newId,(string)row($pdo,'fy_workorders',$archiveId)['restored_from'],'archive links to exact new id');
    $listed=httpRequest('status/getTicket','',$token,'Authorization','GET',['orderid'=>(string)$archiveId]);
    eq(200,$listed['status'],'getTicket HTTP status');
    accepted($listed['json'],'getTicket response');
    $item=$listed['json']['data'][0]??[];
    eq((string)$archiveId,$item['id']??null,'ticket id remains a JSON string');
    eq($newId,$item['restored_from']??null,'restored_from remains a JSON string');
    ok(is_string($item['restored_from']??null),'restored_from JSON type');
};
$cases['actual_http_restore_rejects_malformed_or_overflow_ids_without_aliasing']=function()use($pdo) {
    $archiveId=9007199254740993;
    ticket($pdo,$archiveId,'Canceled',null,10,['archived'=>1,'restored_from'=>null]);
    $token=hash('sha256','fixture-legacy-10');
    foreach(['9007199254740993junk','9.007199254740993e15','-9007199254740993','9223372036854775808',0,1.5,[]]as$bad) {
        $result=httpRequest('restore_archive',['archive_id'=>$bad],$token);
        eq(400,$result['status'],'invalid archive id HTTP status');
        rejected($result['json'],'invalid archive id rejected');
        eq('invalid_params',$result['json']['status']??null,'invalid archive id status');
    }
    eq(1,(int)$pdo->query('SELECT COUNT(*) FROM fy_workorders')->fetchColumn(),'invalid ids create no order');
    eq(null,row($pdo,'fy_workorders',$archiveId)['restored_from'],'invalid ids do not mark archive');
};
$cases['actual_http_restore_obeys_global_flag_fail_closed']=function()use($pdo) {
    $archiveId=9007199254740993;
    ticket($pdo,$archiveId,'Canceled',null,10,['archived'=>1,'restored_from'=>null]);
    $states=[
        'disabled'=>function()use($pdo){$pdo->exec("UPDATE fy_confs SET data='0' WHERE name='Global_Flag'");},
        'malformed'=>function()use($pdo){$pdo->exec("UPDATE fy_confs SET data='true' WHERE name='Global_Flag'");},
        'missing'=>function()use($pdo){$pdo->exec("DELETE FROM fy_confs WHERE name='Global_Flag'");},
        'duplicate'=>function()use($pdo){$pdo->exec("INSERT INTO fy_confs(name,info,data) VALUES ('Global_Flag','duplicate','1'),('Global_Flag','duplicate','1')");},
    ];
    foreach($states as $label=>$arrange) {
        $pdo->exec("DELETE FROM fy_confs WHERE name='Global_Flag'");
        if($label!=='missing') $pdo->exec("INSERT INTO fy_confs(name,info,data) VALUES ('Global_Flag','test','1')");
        $arrange();
        $before=(int)$pdo->query('SELECT available FROM fy_users WHERE id=10')->fetchColumn();
        $result=httpRequest('restore_archive',['archive_id'=>(string)$archiveId],hash('sha256','fixture-legacy-10'));
        eq(503,$result['status'],'restore paused HTTP status '.$label);
        rejected($result['json'],'restore rejected '.$label);
        eq('repair_paused',$result['json']['status']??null,'restore stable paused status '.$label);
        eq(1,(int)$pdo->query('SELECT COUNT(*) FROM fy_workorders')->fetchColumn(),'paused restore creates no order '.$label);
        eq(null,row($pdo,'fy_workorders',$archiveId)['restored_from'],'paused restore does not mark archive '.$label);
        eq($before,(int)$pdo->query('SELECT available FROM fy_users WHERE id=10')->fetchColumn(),'paused restore preserves quota '.$label);
    }
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
