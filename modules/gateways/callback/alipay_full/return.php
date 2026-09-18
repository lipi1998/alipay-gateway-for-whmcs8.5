<?php
require_once __DIR__  . "/init.php";
require_once __DIR__ ."/../../class/alipay_full/aop/AopClient.php";
require_once __DIR__ ."/../../class/alipay_full/aop/AopCertClient.php";
require_once __DIR__ ."/../../class/alipay_full/aop/AopCertification.php";
require_once __DIR__ ."/../../class/alipay_full/aop/AlipayConfig.php";
require_once __DIR__ ."/../../class/alipay_full/aop/request/AlipayTradeQueryRequest.php";

$out_trade_no = isset($_GET['out_trade_no']) ? $_GET['out_trade_no'] : '';
$trade_no = isset($_GET['trade_no']) ? $_GET['trade_no'] : '';
$amount    = isset($_GET['total_amount']) ? $_GET['total_amount'] : '';
$parts = explode("-", $out_trade_no);
$invoice_id = isset($parts[1]) ? $parts[1] : '';


global $CONFIG;
$params = getGatewayVariables($gatewaymodule);
if (!$params["type"]) die("Module Not Activated");

if ($out_trade_no === '' || $invoice_id === '') {
    exit("入账失败 , 请联系管理员为您手工入账");
}

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

if (empty($_GET['sign']) || !$aop->rsaCheckV1($_GET, null, 'RSA2')) {
    logTransaction($gatewaymodule, $_GET, "同步回调验签失败");
    exit("入账失败 , 请联系管理员为您手工入账");
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
$queryTradeStatus = $response && isset($response->trade_status) ? $response->trade_status : '';
$queryAmount = $response && isset($response->total_amount) ? $response->total_amount : $amount;

if (!empty($resultCode)&&$resultCode == 10000 && in_array($queryTradeStatus, ['TRADE_SUCCESS', 'TRADE_FINISHED'], true)){
            $invoiceid = checkCbInvoiceID($invoice_id,$params["name"]);
            $amount = convert_helper( $invoice_id, $queryAmount );
            checkCbTransID($trade_no);
            addInvoicePayment($invoiceid,$trade_no,$amount,$fee,$gatewaymodule);
            logTransaction($gatewaymodule, $_GET, "即时到账 - 同步入账");
            header("Location: ".$CONFIG["SystemURL"]."/viewinvoice.php?id=".$invoice_id);
            exit();

} else {
    exit("入账失败 , 请联系管理员为您手工入账");
}
