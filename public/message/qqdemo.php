<script>
function contactQQ() {
if (/QQ\//.test(navigator.userAgent)) {
  alert("请点击右上角菜单，在浏览器中打开以联系QQ客服。");
} else {
  window.location.href = "mqqwpa://im/chat?chat_type=wpa&uin=12345678&version=1&src_type=web";
}
}
</script>
<body onload="contactQQ()">