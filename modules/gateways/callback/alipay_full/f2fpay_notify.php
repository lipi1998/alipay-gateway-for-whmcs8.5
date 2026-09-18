<?php
if (empty($_POST['out_trade_no'])&& trim($_POST['out_trade_no'])==""){
    exit("error");
}

require_once __DIR__ . "/init.php";
require_once __DIR__ . "/../../class/alipay_full/link_gen.php";
require_once __DIR__ .  "/../../class/alipay_full/f2fpay/service/AlipayTradeService.php";
use Illuminate\Database\Capsule\Manager as Capsule;

$GATEWAY = getGatewayVariables($gatewaymodule);
if (!$GATEWAY["type"]) die("Module Not Activated");

$out_trade_no = $_POST['out_trade_no'];
$trade_no = isset($_POST['trade_no']) ? $_POST['trade_no'] : '';
$amount    = isset($_POST['total_amount']) ? $_POST['total_amount'] : '';
$parts = explode("-", $out_trade_no);
$invoice_id = isset($parts[1]) ? $parts[1] : '';
if ($invoice_id === '') {
    exit("failed");
}

$queryContentBuilder = new AlipayTradeQueryContentBuilder();
$queryContentBuilder->setOutTradeNo(trim($_POST['out_trade_no']));

$a = new alipayfull_link();

$queryResponse = new AlipayTradeService($a->f2fpay_get_basicconfig($GATEWAY));
$queryResult = $queryResponse->queryTradeResult($queryContentBuilder);
$result = $queryResult->getResponse();
if ($result && isset($result->msg) && $result->msg == "Success"){
    if($result->trade_status == 'TRADE_FINISHED' || $result->trade_status == 'TRADE_SUCCESS'){
        $invoiceid = checkCbInvoiceID($invoice_id,$GATEWAY["name"]);
        $paidAmount = isset($result->total_amount) ? $result->total_amount : $amount;
        $amount = convert_helper( $invoice_id, $paidAmount );
        checkCbTransID($trade_no);
        addInvoicePayment($invoiceid,$trade_no,$amount,$fee,$gatewaymodule);
        logTransaction($gatewaymodule, $_POST, "当面付 - 异步入账");
        exit("success");
    }
}
exit("failed");