<?php
require __DIR__.'/bootstrap.php';
require __DIR__.'/parallel.php';
loadCore();
$pdo = db();
$pdo->exec(file_get_contents(__DIR__.'/schema.sql'));
$migration = getenv('FOC_TEST_MIGRATION') ?: '';
if (!$migration || !is_file($migration)) throw new RuntimeException('FOC_TEST_MIGRATION is required.');
$pdo->exec(file_get_contents($migration));
$cases = [];
$id = 20269990001;
$cases['transfer_string_code_atomic_and_retry'] = function () use ($pdo,$id) {
    ticket($pdo,$id);
    $request = ['order_id'=>(string)$id,'tvcode'=>'123456'];
    accepted(invoke($pdo,'give',30,$request),'string code must match integer DB code');
    $current = row($pdo,'fy_workorders',$id);
    eq(30,(int)$current['assigned_technician_id'],'new technician');
    eq('Repairing',$current['repair_status'],'state');
    eq(1,(int)row($pdo,'fy_users',20)['available'],'old technician released');
    eq(0,(int)row($pdo,'fy_users',30)['available'],'new technician at capacity');
    eq(1,auditCount($pdo,$id),'one transfer audit');
    $queued = outboxCount($pdo);
    ok($queued>0,'notifications persisted before success');
    accepted(invoke($pdo,'give',30,$request),'retry of same consumed transfer code');
    eq($current,row($pdo,'fy_workorders',$id),'retry must not change assigned time or code');
    eq(1,auditCount($pdo,$id),'retry must not add audit');
    eq($queued,outboxCount($pdo),'retry must not enqueue notifications again');
};
$cases['initial_assignment_and_hash'] = function () use ($pdo,$id) {
    ticket($pdo,$id,'Pending',null);
    accepted(invoke($pdo,'give',20,['order_id'=>$id,'order_hash'=>hash('sha256','fixture-order-'.$id)]),'hash assignment');
    eq(20,(int)row($pdo,'fy_workorders',$id)['assigned_technician_id'],'assigned technician');
    eq(1,auditCount($pdo,$id),'one assignment audit');
    eq('assign',$pdo->query('SELECT type FROM fy_transfer_record LIMIT 1')->fetchColumn(),'assignment audit type');
};
$cases['wrong_missing_and_non_decimal_codes_do_not_write'] = function () use ($pdo,$id) {
    ticket($pdo,$id);
    $before = row($pdo,'fy_workorders',$id);
    foreach ([[],['tvcode'=>'000000'],['tvcode'=>'1.23456e5'],['tvcode'=>'123456x'],['tvcode'=>123456.5],['order_hash'=>'bad']] as $credential) {
        rejected(invoke($pdo,'give',30,array_merge(['order_id'=>$id],$credential)),'invalid credential rejected');
    }
    eq($before,row($pdo,'fy_workorders',$id),'invalid credentials preserve order');
    eq(0,auditCount($pdo,$id),'no audit');
    eq(0,outboxCount($pdo),'no notifications');
};
$cases['ordinary_user_cannot_claim_or_target_other_technician'] = function () use ($pdo,$id) {
    ticket($pdo,$id);
    rejected(invoke($pdo,'give',10,['order_id'=>$id,'tvcode'=>123456]),'customer cannot claim');
    rejected(invoke($pdo,'give',30,['order_id'=>$id,'tvcode'=>123456,'tid'=>20]),'technician cannot assign on another behalf');
    eq(20,(int)row($pdo,'fy_workorders',$id)['assigned_technician_id'],'assignee preserved');
};
$cases['admin_can_transfer_but_target_must_be_technician'] = function () use ($pdo,$id) {
    ticket($pdo,$id);
    rejected(invoke($pdo,'give',40,['order_id'=>$id,'tvcode'=>123456,'tid'=>50]),'ordinary user target rejected');
    rejected(invoke($pdo,'give',40,['order_id'=>$id,'tvcode'=>123456,'tid'=>999]),'missing target rejected');
    accepted(invoke($pdo,'give',40,['order_id'=>$id,'tvcode'=>123456,'tid'=>30]),'admin transfer');
    eq(30,(int)row($pdo,'fy_workorders',$id)['assigned_technician_id'],'admin target applied');
};
$cases['terminal_orders_cannot_transfer_or_complete_as_new'] = function () use ($pdo,$id) {
    foreach (['Done','Closed','Canceled'] as $i=>$status) {
        $tid=$id+$i; ticket($pdo,$tid,$status);
        rejected(invoke($pdo,'give',30,['order_id'=>$tid,'tvcode'=>123456]),'terminal transfer rejected '.$status);
        if ($status!=='Done') rejected(invoke($pdo,'complete',20,['order_id'=>$tid]),'terminal completion rejected '.$status);
        eq($status,row($pdo,'fy_workorders',$tid)['repair_status'],'terminal remains');
    }
    eq(0,auditCount($pdo,$id),'no terminal transfer');
};
$cases['replayed_transfer_cannot_steal_order_after_later_transfer'] = function () use ($pdo,$id) {
    ticket($pdo,$id);
    $old=['order_id'=>$id,'tvcode'=>'123456'];
    accepted(invoke($pdo,'give',30,$old),'first transfer');
    $code=row($pdo,'fy_workorders',$id)['transcode'];
    accepted(invoke($pdo,'give',20,['order_id'=>$id,'tvcode'=>(string)$code]),'transfer back with fresh code');
    rejected(invoke($pdo,'give',30,$old),'stale receipt after different transfer');
    eq(20,(int)row($pdo,'fy_workorders',$id)['assigned_technician_id'],'old receipt cannot reclaim');
    eq(2,auditCount($pdo,$id),'only actual transfers recorded');
};
$cases['concurrent_same_transfer_applies_once'] = function () use ($pdo,$id) {
    ticket($pdo,$id);
    $job=['action'=>'give','actor'=>30,'data'=>['order_id'=>$id,'tvcode'=>'123456']];
    foreach (concurrent([$job,$job,$job,$job]) as $result) accepted($result,'same-target concurrent retry');
    eq(30,(int)row($pdo,'fy_workorders',$id)['assigned_technician_id'],'new technician');
    eq(1,auditCount($pdo,$id),'one concurrent transfer');
    $queue=outboxCount($pdo);
    accepted(invoke($pdo,'give',30,$job['data']),'post-concurrency retry');
    eq($queue,outboxCount($pdo),'no repeated notifications');
};
$cases['concurrent_competing_transfer_has_only_one_winner'] = function () use ($pdo,$id) {
    ticket($pdo,$id,'Pending',null);
    $results=concurrent([
        ['action'=>'give','actor'=>20,'data'=>['order_id'=>$id,'tvcode'=>'123456']],
        ['action'=>'give','actor'=>30,'data'=>['order_id'=>$id,'tvcode'=>'123456']],
    ]);
    eq(1,count(array_filter($results,fn($r)=>($r['success']??false)===true)),'exactly one claimant');
    eq(1,auditCount($pdo,$id),'only winning assignment audited');
};
$cases['capacity_counts_other_repairing_and_confirming_orders'] = function () use ($pdo,$id) {
    ticket($pdo,$id);
    ticket($pdo,$id+1,'UserConfirming',20,11);
    accepted(invoke($pdo,'give',30,['order_id'=>$id,'tvcode'=>'123456']),'transfer while old technician retains work');
    eq(0,(int)row($pdo,'fy_users',20)['available'],'old technician still busy with confirmation');
    $pdo->exec('UPDATE fy_users SET max_concurrent=2 WHERE id=30');
    ticket($pdo,$id+2,'Repairing',30,11);
    accepted(invoke($pdo,'complete',30,['order_id'=>$id+2]),'one of two orders completed');
    eq(1,(int)row($pdo,'fy_users',30)['available'],'remaining one order below capacity two');
};
$cases['cancel_sequential_retry_refunds_quota_once'] = function () use ($pdo,$id) {
    ticket($pdo,$id);
    $request=['tid'=>$id,'repair_status'=>'Canceled'];
    accepted(invoke($pdo,'set',10,$request),'first cancellation');
    eq('Canceled',row($pdo,'fy_workorders',$id)['repair_status'],'canceled');
    eq(3,(int)row($pdo,'fy_users',10)['available'],'refund once');
    $queue=outboxCount($pdo);
    accepted(invoke($pdo,'set',10,$request),'repeat cancellation');
    eq(3,(int)row($pdo,'fy_users',10)['available'],'repeat no refund');
    eq($queue,outboxCount($pdo),'repeat no duplicate notice');
};
$cases['cancel_concurrent_retry_refunds_quota_once'] = function () use ($pdo,$id) {
    ticket($pdo,$id);
    $job=['action'=>'set','actor'=>10,'data'=>['tid'=>$id,'repair_status'=>'Canceled']];
    foreach (concurrent([$job,$job,$job,$job]) as $r) accepted($r,'concurrent cancel response');
    eq(3,(int)row($pdo,'fy_users',10)['available'],'concurrent refund once');
    eq('Canceled',row($pdo,'fy_workorders',$id)['repair_status'],'final canceled');
};
$cases['cancel_quota_caps_at_weekly_limit_and_keeps_busy_technician'] = function () use ($pdo,$id) {
    $pdo->exec('UPDATE fy_users SET available=5 WHERE id=10');
    ticket($pdo,$id); ticket($pdo,$id+1,'TechConfirming',20,11);
    accepted(invoke($pdo,'set',10,['tid'=>$id,'repair_status'=>'Canceled']),'cancel');
    eq(5,(int)row($pdo,'fy_users',10)['available'],'quota capped');
    eq(0,(int)row($pdo,'fy_users',20)['available'],'other live work keeps technician busy');
};
$cases['two_party_confirmation_is_role_specific_and_atomic'] = function () use ($pdo,$id) {
    ticket($pdo,$id);
    rejected(invoke($pdo,'set',10,['tid'=>$id,'repair_status'=>'UserConfirming']),'customer cannot impersonate technician confirmation');
    rejected(invoke($pdo,'set',20,['tid'=>$id,'repair_status'=>'TechConfirming']),'technician cannot impersonate customer confirmation');
    accepted(invoke($pdo,'set',10,['tid'=>$id,'repair_status'=>'TechConfirming']),'customer confirms (awaiting technician)');
    eq('TechConfirming',row($pdo,'fy_workorders',$id)['repair_status'],'one-party state');
    accepted(invoke($pdo,'set',10,['tid'=>$id,'repair_status'=>'TechConfirming']),'customer repeat');
    eq('TechConfirming',row($pdo,'fy_workorders',$id)['repair_status'],'repeat not a second party');
    accepted(invoke($pdo,'set',20,['tid'=>$id,'repair_status'=>'UserConfirming']),'technician confirms (awaiting customer)');
    $done=row($pdo,'fy_workorders',$id);
    eq('Done',$done['repair_status'],'both parties finalize');
    ok(!empty($done['completion_time']),'completion time set');
    eq(1,(int)row($pdo,'fy_users',20)['available'],'capacity restored');
};
$cases['concurrent_two_party_confirmation_ends_done'] = function () use ($pdo,$id) {
    ticket($pdo,$id);
    $jobs=[
        ['action'=>'set','actor'=>10,'data'=>['tid'=>$id,'repair_status'=>'TechConfirming']],
        ['action'=>'set','actor'=>20,'data'=>['tid'=>$id,'repair_status'=>'UserConfirming']],
    ];
    foreach(concurrent($jobs) as $r) accepted($r,'simultaneous confirmation');
    eq('Done',row($pdo,'fy_workorders',$id)['repair_status'],'Done after simultaneous confirmation');
};
$cases['done_does_not_regress_or_refund_quota'] = function () use ($pdo,$id) {
    ticket($pdo,$id);
    accepted(invoke($pdo,'complete',20,['order_id'=>$id]),'complete');
    $before=row($pdo,'fy_workorders',$id); $queue=outboxCount($pdo);
    foreach (['UserConfirming','TechConfirming','Repairing','Pending','Canceled','Closed'] as $state) {
        invoke($pdo,'set',40,['tid'=>$id,'repair_status'=>$state]);
        eq('Done',row($pdo,'fy_workorders',$id)['repair_status'],'done does not regress to '.$state);
    }
    accepted(invoke($pdo,'complete',20,['order_id'=>$id]),'repeat complete');
    eq($before,row($pdo,'fy_workorders',$id),'repeat complete unchanged');
    eq(2,(int)row($pdo,'fy_users',10)['available'],'completion does not refund cancellation quota');
    eq($queue,outboxCount($pdo),'no duplicate completion notification');
};
$cases['completion_permission_and_concurrent_idempotency'] = function () use ($pdo,$id) {
    ticket($pdo,$id);
    rejected(invoke($pdo,'complete',30,['order_id'=>$id]),'unassigned technician denied');
    rejected(invoke($pdo,'complete',11,['order_id'=>$id]),'other customer denied');
    $job=['action'=>'complete','actor'=>20,'data'=>['order_id'=>$id]];
    foreach(concurrent([$job,$job,$job]) as $r) accepted($r,'concurrent complete');
    eq('Done',row($pdo,'fy_workorders',$id)['repair_status'],'complete state');
    $queue=outboxCount($pdo);
    accepted(invoke($pdo,'complete',20,['order_id'=>$id]),'retry');
    eq($queue,outboxCount($pdo),'one set completion notifications');
};
$cases['customer_and_assigned_technician_can_edit_basic_information'] = function () use ($pdo,$id) {
    ticket($pdo,$id);
    accepted(invoke($pdo,'set',10,['tid'=>$id,'repair_description'=>'Updated by customer','model'=>'New model','user_phone'=>'10000000000']),'owner edits');
    eq('Updated by customer',row($pdo,'fy_workorders',$id)['repair_description'],'owner description saved');
    accepted(invoke($pdo,'set',20,['tid'=>$id,'repair_description'=>'Updated by technician','complete_image_url'=>'https://example.invalid/test.jpg']),'assigned technician edits');
    eq('Updated by technician',row($pdo,'fy_workorders',$id)['repair_description'],'technician description saved');
    rejected(invoke($pdo,'set',30,['tid'=>$id,'repair_description'=>'Unauthorized']),'unassigned technician denied');
    rejected(invoke($pdo,'set',11,['tid'=>$id,'repair_description'=>'Unauthorized']),'other user denied');
};
$cases['admin_edit_preserves_privileged_and_internal_fields'] = function () use ($pdo,$id) {
    ticket($pdo,$id);
    accepted(invoke($pdo,'set',40,[
        'tid'=>$id,'model'=>'Administrator edit','repair_description'=>'Admin description',
        'device_type'=>'laptop','campus'=>'updated-campus'
    ]),'admin edit');
    eq('Administrator edit',row($pdo,'fy_workorders',$id)['model'],'admin model');
    $before=row($pdo,'fy_workorders',$id);
    invoke($pdo,'set',10,['tid'=>$id,'user_id'=>11,'order_hash'=>'forged','transcode'=>987654,'assigned_technician_id'=>30,'completion_time'=>'2020-01-01 00:00:00']);
    $after=row($pdo,'fy_workorders',$id);
    foreach(['user_id','order_hash','transcode','assigned_technician_id','completion_time'] as $field) {
        eq($before[$field],$after[$field],'unprivileged internal field protected '.$field);
    }
};
$cases['missing_and_malformed_ids_do_not_alias_valid_orders'] = function () use ($pdo,$id) {
    ticket($pdo,$id);
    foreach([null,0,-1,'20269990001x','2.0269990001e10',[],1.5] as $bad) {
        rejected(invoke($pdo,'give',30,['order_id'=>$bad,'tvcode'=>123456]),'invalid give id');
        rejected(invoke($pdo,'set',10,['tid'=>$bad,'repair_description'=>'bad']),'invalid set id');
        rejected(invoke($pdo,'complete',20,['order_id'=>$bad]),'invalid complete id');
    }
    rejected(invoke($pdo,'give',30,['order_id'=>$id+99,'tvcode'=>123456]),'missing order');
    eq('Repairing',row($pdo,'fy_workorders',$id)['repair_status'],'valid order untouched');
};
$cases['outbox_insert_failure_rolls_back_assignment_and_audit'] = function () use ($pdo,$id) {
    ticket($pdo,$id);
    $before=row($pdo,'fy_workorders',$id);
    $pdo->exec("CREATE TRIGGER foc_test_outbox_fail BEFORE INSERT ON fy_ticket_notification_outbox FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic notification persistence failure'");
    try {
        try {
            $r=invoke($pdo,'give',30,['order_id'=>$id,'tvcode'=>123456]);
            rejected($r,'outbox failure must prevent successful mutation');
        } catch (PDOException $e) {
            // Surface failure is acceptable; committing partial business data is not.
        }
        eq($before,row($pdo,'fy_workorders',$id),'order rolled back');
        eq(0,auditCount($pdo,$id),'audit rolled back');
        eq(0,(int)row($pdo,'fy_users',20)['available'],'old technician capacity rolled back');
        eq(1,(int)row($pdo,'fy_users',30)['available'],'new technician capacity rolled back');
        ok(!$pdo->inTransaction(),'failed action closes its transaction');
    } finally { $pdo->exec('DROP TRIGGER foc_test_outbox_fail'); }
};
$cases['three_token_types_resolve_authoritative_actor'] = function () use ($pdo,$id) {
    $tokenFile=getenv('FOC_TEST_TOKEN') ?: dirname(getenv('FOC_TEST_CORE')).'/token.php';
    require_once $tokenFile;
    foreach([
        [hash('sha256','fixture-legacy-20'),20],
        [hash('sha256','fixture-admin-40'),40],
        [hash('sha256','fixture-app-30'),30],
    ] as $i=>[$token,$uid]) {
        $resolved=verifyToken($token);
        ok(is_array($resolved),'token resolves user');
        eq($uid,(int)$resolved['id'],'resolved actor');
        ticket($pdo,$id+$i,'Repairing',$uid===40 ? 20 : $uid);
        accepted(ticketComplete($pdo,$resolved,['order_id'=>$id+$i]),'resolved token actor can complete');
    }
    eq('user_not_found',verifyToken('invalid-fixture-token'),'invalid token rejected');
    $pdo->exec("UPDATE fy_users SET token_expiry=DATE_SUB(NOW(), INTERVAL 1 DAY) WHERE id=20");
    eq('token_expired',verifyToken(hash('sha256','fixture-legacy-20')),'expired legacy token rejected');
};

$cases['admin_set_reassignment_uses_same_atomic_transfer_rules'] = function () use ($pdo,$id) {
    ticket($pdo,$id);
    accepted(invoke($pdo,'set',40,['tid'=>$id,'assigned_technician_id'=>30,'repair_description'=>'Admin reassigned']),'admin edits and transfers atomically');
    $after=row($pdo,'fy_workorders',$id);
    eq(30,(int)$after['assigned_technician_id'],'set changed assignee');
    eq('Admin reassigned',$after['repair_description'],'set saved normal fields');
    eq(1,auditCount($pdo,$id),'set reassignment audited');
    eq(1,(int)row($pdo,'fy_users',20)['available'],'old technician released');
    eq(0,(int)row($pdo,'fy_users',30)['available'],'new technician busy');
    $queue=outboxCount($pdo);
    accepted(invoke($pdo,'set',40,['tid'=>$id,'assigned_technician_id'=>30,'repair_description'=>'Admin reassigned']),'repeat admin set');
    eq(1,auditCount($pdo,$id),'repeat set not another transfer');
    eq($queue,outboxCount($pdo),'repeat set no extra notifications');
    rejected(invoke($pdo,'set',40,['tid'=>$id,'assigned_technician_id'=>50,'repair_description'=>'Must roll back']),'invalid target rejects entire edit');
    eq($after,row($pdo,'fy_workorders',$id),'invalid multi-field edit leaves previous state');
};
$cases['archived_order_actions_are_rejected'] = function () use ($pdo,$id) {
    ticket($pdo,$id,'Repairing',20,10,['archived'=>1]);
    $before=row($pdo,'fy_workorders',$id);
    rejected(invoke($pdo,'give',30,['order_id'=>$id,'tvcode'=>123456]),'archived give');
    rejected(invoke($pdo,'set',40,['tid'=>$id,'repair_description'=>'Changed']),'archived edit');
    rejected(invoke($pdo,'complete',20,['order_id'=>$id]),'archived complete');
    eq($before,row($pdo,'fy_workorders',$id),'archived order unchanged');
};
$cases['incorrect_hash_never_falls_back_to_valid_transfer_code'] = function () use ($pdo,$id) {
    ticket($pdo,$id);
    rejected(invoke($pdo,'give',30,['order_id'=>$id,'order_hash'=>'bad','tvcode'=>123456]),'bad hash with good code rejected');
    eq(20,(int)row($pdo,'fy_workorders',$id)['assigned_technician_id'],'incorrect hash cannot transfer');
};
$cases['invalid_edit_field_types_and_lengths_do_not_partially_save'] = function () use ($pdo,$id) {
    ticket($pdo,$id);
    $before=row($pdo,'fy_workorders',$id);
    foreach([
        ['model'=>['invalid']],
        ['model'=>str_repeat('x',256)],
        ['user_phone'=>str_repeat('1',21)],
        ['repair_description'=>str_repeat('x',66000)],
        ['repair_status'=>'UndefinedStatus'],
        ['DuoCampus'=>[]],
    ] as $invalid) {
        rejected(invoke($pdo,'set',10,array_merge(['tid'=>$id,'computer_brand'=>'Partial write forbidden'],$invalid)),'invalid fields rejected');
        eq($before,row($pdo,'fy_workorders',$id),'invalid field update must be atomic');
    }
};
$cases['concurrent_transfer_and_cancellation_preserve_capacity_and_quota'] = function () use ($pdo,$id) {
    ticket($pdo,$id);
    $results=concurrent([
        ['action'=>'give','actor'=>30,'data'=>['order_id'=>$id,'tvcode'=>'123456']],
        ['action'=>'set','actor'=>10,'data'=>['tid'=>$id,'repair_status'=>'Canceled']],
    ]);
    accepted($results[1],'owner cancellation succeeds in either lock order');
    eq('Canceled',row($pdo,'fy_workorders',$id)['repair_status'],'canceled final state');
    eq(3,(int)row($pdo,'fy_users',10)['available'],'one refund in transfer/cancel race');
    eq(1,(int)row($pdo,'fy_users',20)['available'],'old technician released');
    eq(1,(int)row($pdo,'fy_users',30)['available'],'new technician no longer holds live work');
    ok(in_array(auditCount($pdo,$id),[0,1],true),'zero or one transfer before cancellation');
};
$cases['outbox_failure_rolls_back_cancellation_and_completion'] = function () use ($pdo,$id) {
    foreach(['set','complete'] as $i=>$action) {
        $ticketId=$id+$i; ticket($pdo,$ticketId);
        $before=row($pdo,'fy_workorders',$ticketId);
        $pdo->exec("CREATE TRIGGER foc_test_outbox_fail BEFORE INSERT ON fy_ticket_notification_outbox FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic notification persistence failure'");
        try {
            try {
                $data=$action==='set'?['tid'=>$ticketId,'repair_status'=>'Canceled']:['order_id'=>$ticketId];
                $r=invoke($pdo,$action,$action==='set'?10:20,$data);
                rejected($r,'outbox failure blocks successful '.$action);
            } catch(PDOException $e) {}
            eq($before,row($pdo,'fy_workorders',$ticketId),'mutation rolled back after outbox failure');
            eq(2,(int)row($pdo,'fy_users',10)['available'],'quota rolled back');
            eq(0,(int)row($pdo,'fy_users',20)['available'],'capacity rolled back');
            ok(!$pdo->inTransaction(),'failed action closes its transaction');
        } finally {$pdo->exec('DROP TRIGGER foc_test_outbox_fail');}
    }
};

$cases['static_hash_delayed_retry_with_same_request_id_cannot_reclaim_order'] = function () use ($pdo,$id) {
    ticket($pdo,$id);
    $first=['order_id'=>$id,'order_hash'=>hash('sha256','fixture-order-'.$id),'request_id'=>'fixture-give-1'];
    accepted(invoke($pdo,'give',30,$first),'first hash claim with request id');
    accepted(invoke($pdo,'give',20,[
        'order_id'=>$id,'order_hash'=>$first['order_hash'],'request_id'=>'fixture-give-2'
    ]),'later explicit transfer to other technician');
    $before=row($pdo,'fy_workorders',$id); $queued=outboxCount($pdo);
    $late=invoke($pdo,'give',30,$first);
    rejected($late,'late original hash operation rejected');
    eq(409,$late['_httpStatus']??0,'late operation reports stale conflict');
    eq($before,row($pdo,'fy_workorders',$id),'late retry cannot steal back assignment');
    eq(2,auditCount($pdo,$id),'late retry cannot create third transfer');
    eq($queued,outboxCount($pdo),'late retry does not queue old assignment again');
};
$cases['new_request_id_allows_intentional_reclaim_using_same_static_hash'] = function () use ($pdo,$id) {
    ticket($pdo,$id);
    $hash=hash('sha256','fixture-order-'.$id);
    accepted(invoke($pdo,'give',30,['order_id'=>$id,'order_hash'=>$hash,'request_id'=>'fixture-give-1']),'first claim');
    accepted(invoke($pdo,'give',20,['order_id'=>$id,'order_hash'=>$hash,'request_id'=>'fixture-give-2']),'other technician claims');
    accepted(invoke($pdo,'give',30,['order_id'=>$id,'order_hash'=>$hash,'request_id'=>'fixture-give-3']),'new intentional action using same QR remains usable');
    eq(30,(int)row($pdo,'fy_workorders',$id)['assigned_technician_id'],'new action reassigns to intended technician');
    eq(3,auditCount($pdo,$id),'three explicit user actions audited');
};
$cases['request_id_reuse_with_different_ticket_or_payload_is_rejected'] = function () use ($pdo,$id) {
    ticket($pdo,$id); ticket($pdo,$id+1,'Repairing',20,11);
    $hash=hash('sha256','fixture-order-'.$id);
    accepted(invoke($pdo,'give',30,['order_id'=>$id,'order_hash'=>$hash,'request_id'=>'fixture-give-1']),'original request');
    $before=row($pdo,'fy_workorders',$id);
    foreach([
        ['order_id'=>$id+1,'order_hash'=>hash('sha256','fixture-order-'.($id+1)),'request_id'=>'fixture-give-1'],
        ['order_id'=>$id,'tvcode'=>(string)$before['transcode'],'request_id'=>'fixture-give-1']
    ]as$changed) {
        $r=invoke($pdo,'give',30,$changed);
        rejected($r,'same request id with different operation parameters');
        eq(409,$r['_httpStatus']??0,'request id payload collision is conflict');
    }
    eq($before,row($pdo,'fy_workorders',$id),'original ticket unchanged');
    eq(20,(int)row($pdo,'fy_workorders',$id+1)['assigned_technician_id'],'second ticket not claimed by reused request id');
    eq(1,auditCount($pdo,$id),'only original action audited');
    eq(0,auditCount($pdo,$id+1),'no reused request action audit');
};
$cases['concurrent_static_hash_same_request_id_executes_once'] = function () use ($pdo,$id) {
    ticket($pdo,$id);
    $job=['action'=>'give','actor'=>30,'data'=>[
        'order_id'=>$id,'order_hash'=>hash('sha256','fixture-order-'.$id),'request_id'=>'fixture-give-1'
    ]];
    foreach(concurrent([$job,$job,$job,$job])as$r)accepted($r,'same-id concurrent hash request retries succeed');
    eq(30,(int)row($pdo,'fy_workorders',$id)['assigned_technician_id'],'target claimed once');
    eq(1,auditCount($pdo,$id),'one transfer for concurrent request id');
    $queued=outboxCount($pdo);
    ok($queued>0,'notification persisted');
    accepted(invoke($pdo,'give',30,$job['data']),'post-concurrency same-id retry');
    eq(1,auditCount($pdo,$id),'retry remains one transfer');
    eq($queued,outboxCount($pdo),'no duplicate event notifications');
};

$passed=0; $total=count($cases);
foreach($cases as $name=>$case) {
    fixture($pdo);
    if(runTest($name,$case)) $passed++;
    if($pdo->inTransaction()) $pdo->rollBack();
}
echo json_encode(['suite'=>'mysql-ticket-reliability','passed'=>$passed,'total'=>$total],JSON_THROW_ON_ERROR).PHP_EOL;
exit($passed===$total ? 0 : 1);
