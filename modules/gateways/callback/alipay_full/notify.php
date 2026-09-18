<?php
/**
 * 电脑/手机网站支付 - 异步通知 (notify_url)。
 *
 * 支付宝服务器发起 POST。入账成功必须原样输出 "success"，
 * 否则支付宝会按策略持续重投这条通知。
 */

require_once __DIR__ . "/init.php";

exit(alipayfull_record_payment($_POST, "即时到账 - 异步入账") ? "success" : "failed");
