<?php
/**
 * 电脑/手机网站支付 - 同步跳转 (return_url)。
 *
 * 付款完成后由客户浏览器带 GET 参数访问，作用是让客户立刻看到已付款的发票。
 * 真正保证入账的是 notify.php，两边靠支付宝交易号去重，谁先到都不会重复入账。
 */

require_once __DIR__ . "/init.php";

global $CONFIG;

if (!alipayfull_record_payment($_GET, "即时到账 - 同步入账", true)) {
    exit("入账失败 , 请联系管理员为您手工入账");
}

$invoice_id = alipayfull_api::parse_invoice_id($_GET['out_trade_no']);
header("Location: " . rtrim($CONFIG["SystemURL"], '/') . "/viewinvoice.php?id=" . $invoice_id . "&paymentsuccess=true");
exit();
