<?php
session_start();
require_once 'api/config.php';
header('Content-Type: text/html; charset=utf-8');
$pdo = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = trim($_POST['password'] ?? '');
    $stmt = $pdo->prepare("SELECT data FROM fy_confs WHERE name='FydPassword'");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row && $row['data'] === $password) {
        $_SESSION['fyd_password'] = $password;
        header('Location: https://focapp.feiyang.ac.cn/public/repairticket/'); // 登录成功跳转主页面
        exit;
    } else {
        $error = "❌ 密码错误，请重试。";
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>密码登录</title>
<style>
body {
    font-family: "Segoe UI", "Microsoft YaHei", sans-serif;
    background: linear-gradient(135deg, #7fa8ff, #5260de);
    color: #333;
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;
}
.container {
    background: white;
    padding: 40px;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.2);
    text-align: center;
    width: 340px;
}
input[type="password"] {
    width: 80%;
    padding: 10px;
    margin: 15px 0;
    border-radius: 8px;
    border: 1px solid #ccc;
    font-size: 16px;
}
button {
    background-color: #5563DE;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 8px;
    font-size: 16px;
    cursor: pointer;
    transition: 0.3s;
}
button:hover {
    background-color: #3846B8;
}
</style>
<script type="text/javascript" src="https://lf1-cdn-tos.bytegoofy.com/goofy/lark/op/h5-js-sdk-1.5.26.js"></script>
<script src="https://lf-package-cn.feishucdn.com/obj/feishu-static/lark/passport/qrcode/LarkSSOSDKWebQRCode-1.0.3.js"></script>
<script src='https://lf-package-cn.feishucdn.com/obj/feishu-static/op/fe/devtools_frontend/remote-debug-0.0.1-alpha.6.js'></script>
</head>
<body>
<div class="container">
    <h2>🔐 输入访问密码</h2>
    <?php if (!empty($error)) echo "<p style='color:red;'>$error</p>"; ?>
    <form method="post">
        <input type="password" name="password" placeholder="请输入密码" required>
        <br>
        <button type="submit">登录</button>
    </form>
</div>
<script>
if (window.tt && window.tt.requestAccess) {
    window.tt.requestAccess({
        appID: "cli_a80cfb400bb9d00d",
        scopeList: [],
        success: (res) => {
            const { code } = res;
            console.log("授权成功，授权码：", code);
        },
        fail: (error) => {
            if (error.errno === 103) {
                console.log(error);
                callRequestAuthCode();
            }
        }
    });
} else {
    console.warn("window.tt 不存在，当前环境不支持抖音/头条授权");
}
</script>
</body>
</html>
