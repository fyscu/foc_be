<?php
$name = isset($_GET['name']) ? htmlspecialchars($_GET['name']) : '客户';
$time = isset($_GET['time']) ? htmlspecialchars($_GET['time']) : '未知时间';
$phone = isset($_GET['phone']) ? htmlspecialchars($_GET['phone']) : '';
$date = isset($_GET['date']) ? htmlspecialchars($_GET['date']) : '未知日期';
$loc = isset($_GET['loc']) ? htmlspecialchars($_GET['loc']) : '未知地点';
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
  <p>同学姓名：<?php echo $name; ?></p>
  <p>面试日期：<?php echo $date; ?></p>
  <p>面试时间：<?php echo $time; ?></p>
  <p>面试地点：<?php echo $loc; ?></p>
  <p>手机号：<?php echo $phone ? $phone : '未提供手机号'; ?></p>
  <div id="qrcode"></div>

  <script>
    // 获取 URL 参数
    const urlParams = new URLSearchParams(window.location.search);
    const name = urlParams.get('name') || '客户';
    const time = urlParams.get('time') || '未知时间';
    const phone = urlParams.get('phone') || '';
    const date = urlParams.get('date') || '未知日期';
    const loc = urlParams.get('loc') || '未知地点';

    // 生成短信内容
    const message = `【四川大学飞扬俱乐部】
 亲爱的 ${name} 同学，感谢你对飞扬俱乐部的热爱与支持！恭喜你通过报名表申请，顺利进入四川大学飞扬俱乐部2025-2026学年年度面试，现将相关事宜通知如下：
🌟你的面试安排在【${date} ${time}】【${loc}】。请您收到短信后及时加入面试QQ群【910301568】（加群时请务必备注：学院+姓名，否则不通过加群申请）。
🌟面试前请准备一段口述简介，除自我情况的介绍，还包括：对社团工作生活中的收获期望、想要了解的社团相关问题，我们将以最轻松的姿态迎接你们的到来。
🌟祝愿你以良好的心态与自信的笑容参加面试，期待你在面试时精彩的表现！若恰逢雨天，请注意保暖，携带雨具。请在时间段前10分钟抵达面试教室等候哦～
🌟如确认收到本短信，请回复【姓名＋收到】！
🌟最后，再次感谢您选择了四川大学飞扬俱乐部，愿学业有成，万事胜意！`;

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
