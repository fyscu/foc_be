<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Max-Age: 86400");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$config = include('../../config.php');
include('../../db.php');
include('../../utils/token.php');
include('../../utils/headercheck.php');
include('../../utils/admincheck.php');

requireAdminRole(['super', 'active']);

// 两条 SQL 移植自老 public/quickactive/pending_users.php

// 待激活：pending 且 immed=1 的有手机号用户
$sqlAct = "
  SELECT  u.phone,
          COALESCE(sl.created_at , u.regtime) AS request_time,
          NULL        AS openid,
          'activation' AS flag
  FROM    fy_users u
  LEFT JOIN (
      SELECT phone, MAX(id) AS max_id
      FROM   fy_sms_log
      GROUP  BY phone
  ) latest ON BINARY latest.phone = BINARY u.phone
  LEFT JOIN fy_sms_log sl ON sl.id = latest.max_id
  WHERE   u.phone IS NOT NULL AND u.phone <> ''
    AND   u.status = 'pending'
    AND   u.immed  = 1
  ORDER BY request_time DESC";
$act = $pdo->query($sqlAct)->fetchAll(PDO::FETCH_ASSOC);

// 待迁移：最后一条 imm 短信 + 旧账户(verified/immed=0/无openid) + 新账户(有openid/无手机号)同时存在
$sqlMig = "
  SELECT  sl.phone,
          sl.openid,
          sl.created_at   AS request_time,
          'migration'     AS flag
  FROM    fy_sms_log sl
  JOIN   (SELECT phone, MAX(id) AS max_id
          FROM   fy_sms_log
          WHERE  type='imm'
          GROUP  BY phone) latest
         ON latest.max_id = sl.id
  JOIN    fy_users u_old
         ON BINARY u_old.phone = BINARY sl.phone
        AND u_old.status='verified'
        AND u_old.immed = 0
        AND (u_old.openid IS NULL OR u_old.openid='')
  JOIN    fy_users u_new
         ON BINARY u_new.openid = BINARY sl.openid
        AND (u_new.phone IS NULL OR u_new.phone='')
  ORDER BY sl.created_at DESC";
$mig = $pdo->query($sqlMig)->fetchAll(PDO::FETCH_ASSOC);

$users = array_merge($act, $mig);
usort($users, function ($a, $b) {
    return strtotime($b['request_time']) - strtotime($a['request_time']);
});

echo json_encode([
    'success' => true,
    'count_activation' => count($act),
    'count_migration' => count($mig),
    'rows' => $users
], JSON_UNESCAPED_UNICODE);
