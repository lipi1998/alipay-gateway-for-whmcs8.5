<?php
require_once __DIR__  .  "/init.php";
require_once __DIR__ ."/../../class/alipay_full/aop/AopClient.php";
require_once __DIR__ ."/../../class/alipay_full/aop/AopCertClient.php";
require_once __DIR__ ."/../../class/alipay_full/aop/AopCertification.php";
require_once __DIR__ ."/../../class/alipay_full/aop/AlipayConfig.php";
require_once __DIR__ ."/../../class/alipay_full/aop/request/AlipayTradeQueryRequest.php";


use Illuminate\Database\Capsule\Manager as Capsule;

$out_trade_no = isset($_POST['out_trade_no']) ? $_POST['out_trade_no'] : '';
$trade_no = isset($_POST['trade_no']) ? $_POST['trade_no'] : '';
$trade_status = isset($_POST['trade_status']) ? $_POST['trade_status'] : '';
$amount    = isset($_POST['total_amount']) ? $_POST['total_amount'] : '';
$parts = explode("-", $out_trade_no);
$invoice_id = isset($parts[1]) ? $parts[1] : '';

if ($out_trade_no === '' || $invoice_id === '') {
    exit("failed");
}

$params = getGatewayVariables($gatewaymodule);
if (!$params["type"]) die("Module Not Activated");

$aop = new AopClient ();
$aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
$aop->appId = $params['app_id'];
$aop->rsaPrivateKey = preg_replace('/\s+/', '', str_replace(
    ['-----BEGIN RSA PRIVATE KEY-----','-----END RSA PRIVATE KEY-----','-----BEGIN PRIVATE KEY-----','-----END PRIVATE KEY-----'],
    '',
    $params['rsa_key']
));
$aop->alipayrsaPublicKey = preg_replace('/\s+/', '', str_replace(
    ['-----BEGIN PUBLIC KEY-----','-----END PUBLIC KEY-----'],
    '',
    $params['alipay_key']
));
$aop->apiVersion = '1.0';
$aop->signType = 'RSA2';
$aop->postCharset='UTF-8';
$aop->format='json';

// 先验签，再查单确认交易状态
if (empty($_POST['sign']) || !$aop->rsaCheckV1($_POST, null, 'RSA2')) {
    logTransaction($gatewaymodule, $_POST, "异步回调验签失败");
    exit("failed");
}

$request = new AlipayTradeQueryRequest ();
$request->setBizContent(json_encode([
    "out_trade_no" => $out_trade_no,
    "trade_no" => $trade_no,
], JSON_UNESCAPED_UNICODE));
$result = $aop->execute ( $request);

$responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
$response = isset($result->$responseNode) ? $result->$responseNode : null;
$resultCode = $response ? $response->code : null;
$queryTradeStatus = $response && isset($response->trade_status) ? $response->trade_status : $trade_status;
$queryAmount = $response && isset($response->total_amount) ? $response->total_amount : $amount;

if(!empty($resultCode)&&$resultCode == 10000){
    if($queryTradeStatus == 'TRADE_FINISHED' || $queryTradeStatus == 'TRADE_SUCCESS')
    {
            $invoiceid = checkCbInvoiceID($invoice_id,$params["name"]);
            $amount = convert_helper( $invoice_id, $queryAmount );
            checkCbTransID($trade_no);
            addInvoicePayment($invoiceid,$trade_no,$amount,$fee,$gatewaymodule);
            logTransaction($gatewaymodule, $_POST, "即时到账 - 异步入账");
            exit("success");
    }
}
exit("failed");
