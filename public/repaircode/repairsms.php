<?php
if (session_status() === PHP_SESSION_NONE) {
    session_name('admin');
    session_start();
}
$_SESSION['ved'] == true;
$ved = true;
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <title>大修短信发送</title>

    <link href="assets/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.js"></script>

    <style>
      .bd-placeholder-img {
        font-size: 1.125rem;
        text-anchor: middle;
        -webkit-user-select: none;
        -moz-user-select: none;
        user-select: none;
      }

      @media (min-width: 768px) {
        .bd-placeholder-img-lg {
          font-size: 3.5rem;
        }
      }
      .container{
            text-align: center;
            float: none;
        }
    </style>
  </head>
  <body>
    
<nav class="navbar navbar-expand-md navbar-dark bg-dark mb-4">
  <div class="container-fluid">
    <a class="navbar-brand" href="#">大修短信发送系统</a>
    
  </div>
</nav>
<?php if($ved){ ?>
<main class="container">
  <div class="bg-light p-5 rounded">
    <p>当前阶段：维修完成通知用户取回</p>
    <input type="tel" class="form-control" id="phone" placeholder="用户手机号">
      <br>
    <button class="btn btn-lg btn-primary" type="button" id="gophone" aria-expanded="false">发送</button>
            </div>
</main>
<script>
        $('#gophone').click(function() {
            const notyf = new Notyf({
                        	  duration: 10000,
                        	  position: {
                        		x: 'right',
                        		y: 'top',
                        	  },
                        	});
            const phone = $('#phone').val();
            if (!phone) {
                notyf.error('请输入手机号');
                return;
            }
            $("#gophone").prop("disabled", true).text("发送短信中...");
            $.post('repairsmss', { action: 'sms', phone: phone}, function(response) {
                if (response.success){
                    notyf.success(response.message);
                    $("#gophone").prop("disabled", false).text("发送");
                } else {
                    notyf.error(response.message);
                    $("#gophone").prop("disabled", false).text("发送");
                }
            }, 'json');
        });
</script>
<?php } else { ?>
<main class="container">
  <div class="bg-light p-5 rounded">
    <p>请输入验证码</p>
    <input type="text" class="form-control" id="vcode" placeholder="大修点位验证码">
    <br>
    <div id="result"></div>
    <br>
    <button class="btn btn-lg btn-primary" type="button" id="govcode" aria-expanded="false">发送</button>
  </div>
</main>
<script>
        $('#govcode').click(function(e) {
            const notyf = new Notyf({
                        	  duration: 10000,
                        	  position: {
                        		x: 'right',
                        		y: 'top',
                        	  },
                        	});
            const vcode = $('#vcode').val();
            if (!vcode) {
                notyf.error('请输入验证码');
                return;
            }
            e.preventDefault();
            
            $.ajax({
                type: "POST",
                url: 'repairsmss',
                data: {
                    vcode: vcode,
                    action: "verify"
                },
                success: function (response) {
                    // console.log(response)
                    if(response.success){
                        notyf.success('验证成功');
                        location.reload()
                    } else {
                        notyf.error('验证码错误');
                    }
                    
                },
                error: function () {
                    notyf.error('验证码错误');
                }
            });
        });
</script>
<?php } ?>
    <script src="assets/bootstrap.bundle.min.js"></script>
    
      
</body>
</html>
