<!--
    当面付 - 桌面端二维码页面

    可用变量
    {$url} - 支付宝预下单返回的二维码内容 (qr_code)

    页面会定时重新拉取当前发票页并检查是否已出现"已付款"标记，
    以便客户扫码付款后无需手动刷新。注意：每次拉取都会重新渲染发票页，
    也就会向支付宝再发一次预下单请求，因此轮询间隔不宜过短。
-->
<script src="/assets/js/jquery.min.js"></script>
<script src="/assets/js/bootstrap.min.js"></script>
<script src="modules/gateways/class/alipay_full/skin/default/assets/qrcode.min.js"></script>
<div class="alipay_qrcode"><span>正在生成二维码</span></div>
<p>支付宝扫码支付</p>
<script>
jQuery(document).ready(function() {
	var paid_timer = setInterval(function(){
		$.ajax({
			type: "get",
			url : window.location.href,
			dataType : "text",
			success: function(data){
				// 发票页出现 class="paid" 即表示回调已入账
				if ( data.indexOf('class="'+"paid"+'"') != -1)
				{
					clearInterval(paid_timer)
					$('#paidsuccess').modal('show')
					setTimeout(function(){location.reload()},3000)
				}
			}})
	},3000)
	$(".alipay_qrcode span").remove()
	new QRCode($(".alipay_qrcode")[0], {
			text: "{$url}",
			width: 200,
			height: 200,
			colorDark : "#000000",
			colorLight : "#ffffff",
			correctLevel : QRCode.CorrectLevel.H
	});
})
</script>
<div class="modal fade" id="paidsuccess">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title"><p class="text-success">支付成功</p></h4>
      </div>
      <div class="modal-body">
        <p>本页面将在3秒后刷新</p>
      </div>
    </div>
  </div>
</div>
