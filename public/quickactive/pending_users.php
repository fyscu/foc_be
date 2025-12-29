<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
if (session_status() === PHP_SESSION_NONE) {
    session_name('admin');
    session_start();
}
require '../../db.php';
$config = include('../../config.php');

function isLoggedIn()
{
    return !empty($_SESSION['admin_logged_in']);
}
if (!isLoggedIn()) {
    header('Location: index.php'); 
    exit;
}

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

usort($users, fn($a,$b) => strtotime($b['request_time']) - strtotime($a['request_time']));
$cntAct = count($act);
$cntMig = count($mig);

if ($_SERVER['REQUEST_METHOD']==='POST') {

    if (isset($_POST['do_activation'])) {
        $phone = $_POST['phone'] ?? '';
        if ($phone) {
            $stmt = $pdo->prepare(
              "UPDATE fy_users
                 SET status='verified', immed=0
               WHERE phone COLLATE utf8mb4_general_ci = ?
                 AND status='pending' AND immed=1");
            $stmt->execute([$phone]);
            $success_message = "手机号 {$phone} 已激活";
            header("Location: ".$_SERVER['PHP_SELF']); exit;
        }
    }

    if (isset($_POST['do_migration'])) {
        $phone  = $_POST['phone']  ?? '';
        $openid = $_POST['openid'] ?? '';

        if ($phone && $openid) {
            try {
                $pdo->beginTransaction();

                $newId = $pdo->prepare(
                    "SELECT id FROM fy_users
                      WHERE openid=? ORDER BY id DESC LIMIT 1");
                $newId->execute([$openid]);
                $newId = $newId->fetchColumn();
                if ($newId) {
                    $del = $pdo->prepare("DELETE FROM fy_users WHERE id=?");
                    $del->execute([$newId]);
                }

                $upd = $pdo->prepare(
                    "UPDATE fy_users
                        SET openid = ?, immed = 1
                      WHERE phone  COLLATE utf8mb4_general_ci = ?
                        AND status  = 'verified'
                        AND immed   = 0
                        AND (openid IS NULL OR openid='')");
                $upd->execute([$openid,$phone]);

                $pdo->commit();
                $success_message = "手机号 {$phone} 迁移完成";
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "迁移失败: ".$e->getMessage();
            }
            header("Location: ".$_SERVER['PHP_SELF']); exit;
        }
    }
}
?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>待激活 / 待迁移 用户列表</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.css">
<script src="https://cdn.bootcdn.net/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.js"></script>
<style>.container{max-width:1200px}.table-responsive{margin-top:20px}</style>
</head>
<body>
<nav class="navbar navbar-expand-md navbar-dark bg-dark mb-4">
 <div class="container-fluid">
   <a class="navbar-brand" href="#">用户状态管理</a>
   <div class="d-flex">
     <a class="btn btn-outline-light me-2" href="index.php">返回主页</a>
     <span class="navbar-text text-light me-3"><?=htmlspecialchars($_SESSION['admin_username'])?></span>
     
   </div>
 </div>
</nav>

<main class="container">
 <div class="bg-light p-5 rounded">
   <h2>待处理用户</h2>
   <p class="lead">
     待激活 <span class="badge bg-warning"><?=$cntAct?></span>
     待迁移 <span class="badge bg-info"><?=$cntMig?></span>
   </p>

   <?php if(!empty($error)):?><div class="alert alert-danger"><?=$error?></div><?php endif;?>
   <?php if(!empty($success_message)):?><div class="alert alert-success"><?=$success_message?></div><?php endif;?>

   <div class="table-responsive">
     <table class="table table-hover align-middle">
       <thead><tr>
         <th>#</th><th>手机号</th><th>请求时间</th><th>状态</th><th>操作</th>
       </tr></thead>
       <tbody>
       <?php if(!$users):?>
         <tr><td colspan="5" class="text-center">暂无记录</td></tr>
       <?php else: foreach($users as $i=>$u):?>
         <tr>
           <td><?=$i+1?></td>
           <td><?=htmlspecialchars($u['phone'])?></td>
           <td><?=$u['request_time']?></td>
           <td>
             <?php if($u['flag']=='activation'):?>
               <span class="badge bg-warning text-dark">待激活</span>
             <?php else:?>
               <span class="badge bg-info">待迁移</span>
             <?php endif;?>
           </td>
           <td>
             <?php if($u['flag']=='activation'):?>
               <form method="post" class="d-inline">
                 <input type="hidden" name="phone" value="<?=htmlspecialchars($u['phone'])?>">
                 <button class="btn btn-sm btn-danger" name="do_activation"
                  onclick="return confirm('激活该用户？');">激活</button>
               </form>
             <?php else:?>
               <form method="post" class="d-inline">
                 <input type="hidden" name="phone"  value="<?=htmlspecialchars($u['phone'])?>">
                 <input type="hidden" name="openid" value="<?=htmlspecialchars($u['openid'])?>">
                 <button class="btn btn-sm btn-success" name="do_migration"
                  onclick="return confirm('执行迁移？');">迁移</button>
               </form>
             <?php endif;?>
           </td>
         </tr>
       <?php endforeach; endif;?>
       </tbody>
     </table>
   </div>
 </div>
</main>

<script>
$(function(){
  const n=new Notyf({duration:5e3,position:{x:'right',y:'top'}});
  <?php if(!empty($error)):?>n.error('<?=addslashes($error)?>');<?php endif;?>
  <?php if(!empty($success_message)):?>n.success('<?=addslashes($success_message)?>');<?php endif;?>
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<footer class="mt-5 text-center text-muted small">
  <hr>
  Developed with ❤️ by <strong>初音过去</strong> in 2025<br>
</footer>
</body>
</html>
