<?php
/**
 * 当面付 - 异步通知 (notify_url)。
 *
 * 与 notify.php 走的是同一套验签与查单流程，只是日志说明不同；
 * 单独保留入口是因为下单时已把这个地址写进了支付宝订单。
 */

require_once __DIR__ . "/init.php";

exit(alipayfull_record_payment($_POST, "当面付 - 异步入账") ? "success" : "failed");
