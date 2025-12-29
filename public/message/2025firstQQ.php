<?php
$name = isset($_GET['name']) ? htmlspecialchars($_GET['name']) : '客户';
$time = isset($_GET['time']) ? htmlspecialchars($_GET['time']) : '未知时间';
$qq = isset($_GET['qq']) ? htmlspecialchars($_GET['qq']) : '';
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
  <style>
    .copy-btn {
    cursor: pointer;
    color: #007bff;
    text-decoration: underline;
    margin-left: 5px;
    }
    </style>
  <!-- ✅ 使用 jsdelivr CDN 版本的 qrcodejs -->
  <script src="qr.js"></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.css">
<script src="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.js"></script>
</head>
<body>

  <h2>发送短信</h2>
  <p>同学姓名：<?php echo $name; ?></p>
  <p>面试日期：<?php echo $date; ?></p>
  <p>面试时间：<?php echo $time; ?></p>
  <p>面试地点：<?php echo $loc; ?></p>
  <p>QQ号：<span id="qqText"><?php echo $qq ? $qq : '未提供QQ号'; ?></span></p>
  <span class="copy-btn" onclick="copyQQ()">复制QQ号</span>
  <p>
    <span class="copy-btn" onclick="copyMsg()">复制消息</span>
  </p>
  

  <script>
    const notyf = new Notyf({
    duration: 1500,
    position: { x: 'right', y: 'top' }
    });
    // 获取 URL 参数
    const urlParams = new URLSearchParams(window.location.search);
    const name = urlParams.get('name') || '客户';
    const time = urlParams.get('time') || '未知时间';
    const qq = urlParams.get('qq') || '';
    const date = urlParams.get('date') || '未知日期';
    const loc = urlParams.get('loc') || '未知地点';

    const message = `【四川大学飞扬俱乐部】
 亲爱的 ${name} 同学，感谢你对飞扬俱乐部的热爱与支持！恭喜你通过报名表申请，顺利进入四川大学飞扬俱乐部2025-2026学年年度面试，现将相关事宜通知如下：
🌟你的面试安排在【${date} ${time}】【${loc}】。请您收到通知后及时加入面试QQ群【910301568】（加群时请务必备注：学院+姓名，否则不通过加群申请）。
🌟面试前请准备一段口述简介，除自我情况的介绍，还包括：对社团工作生活中的收获期望、想要了解的社团相关问题，我们将以最轻松的姿态迎接你们的到来。
🌟祝愿你以良好的心态与自信的笑容参加面试，期待你在面试时精彩的表现！若恰逢雨天，请注意保暖，携带雨具。请在时间段前10分钟抵达面试教室等候哦～
🌟如确认收到本通知，请回复【姓名＋收到】！
🌟最后，再次感谢您选择了四川大学飞扬俱乐部，愿学业有成，万事胜意！`;

function copyQQ() {
  const qq = document.getElementById('qqText').innerText;
  navigator.clipboard.writeText(qq).then(() => {
    notyf.success('QQ号已复制');
  }).catch(() => {
    notyf.error('复制失败');
  });
}

function copyMsg() {
  navigator.clipboard.writeText(message).then(() => {
    notyf.success('消息已复制');
  }).catch(() => {
    notyf.error('复制失败');
  });
}


  </script>

</body>
</html>
