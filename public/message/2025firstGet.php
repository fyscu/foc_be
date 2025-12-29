<?php
$name = isset($_GET['name']) ? htmlspecialchars($_GET['name']) : '客户';
$department = isset($_GET['department']) ? htmlspecialchars($_GET['department']) : '未知部门';
$phone = isset($_GET['phone']) ? htmlspecialchars($_GET['phone']) : '';
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
  <p>录取部门：<?php echo $department; ?></p>
  <p>手机号：<?php echo $phone ? $phone : '未提供手机号'; ?></p>
  <div id="qrcode"></div>

  <script>
    // 获取 URL 参数
    const urlParams = new URLSearchParams(window.location.search);
    const name = urlParams.get('name') || '客户';
    const department = urlParams.get('department') || '未知部门';
    const phone = urlParams.get('phone') || '';

    // 生成短信内容
    const message = `【四川大学飞扬俱乐部】
${name} 同学：
 你好！
🌊 海纳百川，有容乃大！经过面试官们层层筛选，现已确定出面试合格名单。很高兴通知你，你已通过【四川大学飞扬俱乐部】【 ${department} 】面试！
✨ 恭喜你成为了新一代飞扬er，请加入25飞扬干事总群：993447117 ，进入总群后请注意持续关注群公告，期待你日后活跃的身影！
👏 再次欢迎加入四川大学飞扬俱乐部！
😎 收到请回复：【第一个部门+姓名 收到！！】
                        四川大学飞扬俱乐部`;

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
