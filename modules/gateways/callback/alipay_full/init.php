<?php
/**
 * 三个回调入口 (notify.php / return.php / f2fpay_notify.php) 的公共引导文件。
 *
 * 回调是由支付宝或客户浏览器直接访问的，不经过 WHMCS 的模板流程，
 * 所以这里要自己把 WHMCS 环境和网关辅助函数加载起来。
 */

define("BASIC_PATH", realpath(__DIR__ . "/../../../../") . "/");

if (file_exists(BASIC_PATH . "init.php")) {
    require_once BASIC_PATH . "init.php";
} else {
    require_once BASIC_PATH . "dbconnect.php";
}

require_once BASIC_PATH . "includes/functions.php";
require_once BASIC_PATH . "includes/gatewayfunctions.php";
require_once BASIC_PATH . "includes/invoicefunctions.php";
require_once __DIR__ . "/../../class/alipay_full/api_client.php";

use Illuminate\Database\Capsule\Manager as Capsule;

/** 支付宝不收取网关手续费，入账手续费固定为 0 */
$fee = "0";

/** WHMCS 里的模块标识，需与 modules/gateways/alipay_full.php 同名 */
$gatewaymodule = "alipay_full";

/**
 * 把支付宝收到的金额换算回发票所用货币。
 *
 * 管理员在网关里设置了 "Convert To" 时，WHMCS 下单时已按该货币换算过金额，
 * 因此入账前必须换算回客户货币，否则发票金额对不上。
 *
 * @param string|int $invoiceid
 * @param string|float $amount 支付宝实收金额
 * @return string|float 发票货币下的金额
 */
function convert_helper($invoiceid, $amount)
{
    $setting = Capsule::table("tblpaymentgateways")
        ->where("gateway", "alipay_full")
        ->where("setting", "convertto")
        ->first();

    // 未启用多货币换算，原样返回
    if (empty($setting)) {
        return $amount;
    }

    $invoice = Capsule::table("tblinvoices")->where("id", $invoiceid)->first();
    if (empty($invoice)) {
        return $amount;
    }

    $currency = getCurrency($invoice->userid);

    return convertCurrency($amount, $setting->value, $currency["id"]);
}

/**
 * 判断某个支付宝交易号是否已经入账过。
 *
 * WHMCS 的 checkCbTransID() 遇到重复交易号会直接终止脚本，这对同步跳转来说
 * 意味着客户看不到发票页，因此同步跳转要先自己查一次。
 *
 * @param string $transid 支付宝交易号
 * @return bool
 */
function alipayfull_transaction_exists($transid)
{
    if ($transid === '') {
        return false;
    }

    return Capsule::table("tblaccounts")->where("transid", $transid)->exists();
}

/**
 * 回调入账的公共流程：验签 -> 主动查单 -> 写入发票收款。
 *
 * 通知报文里的交易状态、金额、交易号都是外部输入，验签只能证明报文来自支付宝，
 * 因此一律以 alipay.trade.query 的查单结果为准再入账。
 *
 * @param array  $data               回调原始参数 ($_POST 或 $_GET)
 * @param string $label              写入网关日志的说明文字
 * @param bool   $tolerate_duplicate 交易已入账时是否视为成功。
 *                                   同步跳转传 true，这样异步通知先到也不影响客户跳转
 * @return bool 交易是否已确认付款
 */
function alipayfull_record_payment(array $data, $label, $tolerate_duplicate = false)
{
    global $fee, $gatewaymodule;

    $out_trade_no = isset($data['out_trade_no']) ? $data['out_trade_no'] : '';
    $invoice_id = alipayfull_api::parse_invoice_id($out_trade_no);
    if ($out_trade_no === '' || $invoice_id === '') {
        return false;
    }

    $params = getGatewayVariables($gatewaymodule);
    if (!$params["type"]) {
        die("Module Not Activated");
    }

    if (!alipayfull_api::verify_callback($data, $params)) {
        logTransaction($gatewaymodule, $data, $label . " - 验签失败");
        return false;
    }

    $trade = alipayfull_api::query_trade($params, $out_trade_no, isset($data['trade_no']) ? $data['trade_no'] : '');
    if (!alipayfull_api::is_paid($trade)) {
        return false;
    }

    // 同步跳转与异步通知会各来一次，这笔交易可能已经入过账了
    if ($tolerate_duplicate && alipayfull_transaction_exists($trade->trade_no)) {
        return true;
    }

    $invoiceid = checkCbInvoiceID($invoice_id, $params["name"]);
    $amount = convert_helper($invoice_id, $trade->total_amount);

    // 靠支付宝交易号去重，重复的通知会在这里被 WHMCS 拦下
    checkCbTransID($trade->trade_no);
    addInvoicePayment($invoiceid, $trade->trade_no, $amount, $fee, $gatewaymodule);
    logTransaction($gatewaymodule, $data, $label);

    return true;
}
