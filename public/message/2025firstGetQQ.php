<?php
$name = isset($_GET['name']) ? htmlspecialchars($_GET['name']) : '客户';
$time = isset($_GET['time']) ? htmlspecialchars($_GET['time']) : '未知时间';
$qq = isset($_GET['qq']) ? htmlspecialchars($_GET['qq']) : '';
$date = isset($_GET['date']) ? htmlspecialchars($_GET['date']) : '未知日期';
$loc = isset($_GET['loc']) ? htmlspecialchars($_GET['loc']) : '未知地点';
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

  <h2>发送QQ信息</h2>
  <p>同学姓名：<?php echo $name; ?></p>
  <p>录取部门：<?php echo $department; ?></p>
  <p>手机号：<?php echo $phone; ?></p>
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
    const department = urlParams.get('department') || '未知部门';
    const qq = urlParams.get('qq') || '';

    const message = `【四川大学飞扬俱乐部】
${name} 同学：
 你好！
🌊 海纳百川，有容乃大！经过面试官们层层筛选，现已确定出面试合格名单。很高兴通知你，你已通过【四川大学飞扬俱乐部】【 ${department} 】面试！
✨ 恭喜你成为了新一代飞扬er，请加入25飞扬干事总群：993447117 ，进入总群后请注意持续关注群公告，期待你日后活跃的身影！
👏 再次欢迎加入四川大学飞扬俱乐部！
😎 收到请回复：【第一个部门+姓名 收到！！】
                        四川大学飞扬俱乐部`;

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
