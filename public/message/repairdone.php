<?php
$name = isset($_GET['name']) ? htmlspecialchars($_GET['name']) : '客户';
$time = isset($_GET['time']) ? htmlspecialchars($_GET['time']) : '未知时间';
$phone = isset($_GET['phone']) ? htmlspecialchars($_GET['phone']) : '';
$date = isset($_GET['date']) ? htmlspecialchars($_GET['date']) : '未知日期';
$loc = isset($_GET['loc']) ? htmlspecialchars($_GET['loc']) : '未知地点';
$message = isset($_GET['message']) ? htmlspecialchars($_GET['message']) : '未知内容';
?>
<!DOCTYPE html>
<html lang="zh-cn">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>一键发送</title>
  <style>
    body {
      font-family: "Segoe UI", sans-serif;
      text-align: center;
      padding: 40px;
      background: #f7f7f7;
    }
    #qrcode {
      margin-top: 20px;
      background: white;
      display: inline-block;
      padding: 16px;
      border-radius: 12px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    p {
      color: #333;
    }
  </style>
  <!-- ✅ 使用 jsdelivr CDN 版本的 qrcodejs -->
  <script src="qr.js"></script>
</head>
<body>

  <h2>发送短信</h2>
  <p>手机号：<?php echo $phone ? $phone : '未提供手机号'; ?></p>
  <p>短信内容：<?php echo $message ? $message : '未提供短信内容'; ?></p>
  <div id="qrcode"></div>

  <script>
    // 获取 URL 参数
    const urlParams = new URLSearchParams(window.location.search);
    const name = urlParams.get('name') || '客户';
    const time = urlParams.get('time') || '未知时间';
    const phone = urlParams.get('phone') || '';
    const date = urlParams.get('date') || '未知日期';
    const loc = urlParams.get('loc') || '未知地点';
    const message = urlParams.get('message') || '未知内容';

    // 构造短信 URI
    const smsUri = `sms:${phone}?body=${encodeURIComponent(message)}`;

    // 当前页面的完整 URL（生成二维码用）
    const pageUrl = `${window.location.origin}${window.location.pathname}?${urlParams.toString()}`;

    // 生成二维码（支持超长文本）
    new QRCode(document.getElementById("qrcode"), {
      text: pageUrl,
      width: 300,
      height: 300,
      correctLevel: QRCode.CorrectLevel.L // 最大数据容量
    });

    // 如果页面本身是扫码打开（带参数），则自动跳转
    if (phone) {
      setTimeout(() => {
        window.location.href = smsUri;
      }, 1000);
    }
  </script>

</body>
</html>
