<!--
    当面付 - 移动端页面

    可用变量
    {$url} - 支付宝预下单返回的二维码内容 (qr_code)

    手机上无法自己扫自己的二维码，因此直接跳转到该地址，由支付宝 App 接管付款。
-->
<script>
window.location.href = '{$url}';
</script>
