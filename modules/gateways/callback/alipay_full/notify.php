<?php
require_once __DIR__  .  "/init.php";
require_once __DIR__ ."/../../class/alipay_full/aop/AopClient.php";
require_once __DIR__ ."/../../class/alipay_full/aop/AopCertClient.php";
require_once __DIR__ ."/../../class/alipay_full/aop/AopCertification.php";
require_once __DIR__ ."/../../class/alipay_full/aop/AlipayConfig.php";
require_once __DIR__ ."/../../class/alipay_full/aop/request/AlipayTradeQueryRequest.php";


use Illuminate\Database\Capsule\Manager as Capsule;

$out_trade_no = $_POST['out_trade_no'];
$trade_no = $_POST['trade_no'];
$trade_status = $_POST['trade_status'];
$amount    = $_POST['total_amount'];
$invoice_id = explode("-",$out_trade_no)[1];

$params = getGatewayVariables($gatewaymodule);
if (!$params["type"]) die("Module Not Activated");
 $aop = new AopClient ();
$aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
$aop->appId = $params['app_id'];
$aop->rsaPrivateKey = $params['rsa_key'];
$aop->alipayrsaPublicKey=$params['alipay_key'];
$aop->apiVersion = '1.0';
$aop->signType = 'RSA2';
$aop->postCharset='UTF-8';
$aop->format='json';
$request = new AlipayTradeQueryRequest ();
$request->setBizContent("{" .
"  \"out_trade_no\":\"".$out_trade_no."\"," .
"  \"trade_no\":\"".$trade_no."\"," .
"  \"query_options\":[" .
"    \"trade_settle_info\"" .
"  ]" .
"}");
$result = $aop->execute ( $request); 

$responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
$resultCode = $result->$responseNode->code;
if(!empty($resultCode)&&$resultCode == 10000){
    if($trade_status == 'TRADE_FINISHED' or $trade_status == 'TRADE_SUCCESS')
    {
            $invoiceid = checkCbInvoiceID($invoice_id,$GATEWAY["name"]);
            $amount = convert_helper( $invoice_id, $amount );
            checkCbTransID($trade_no);
            addInvoicePayment($invoiceid,$trade_no,$amount,$fee,$gatewaymodule);
            logTransaction($gatewaymodule, $_POST, "即时到账 - 同步入账");
            exit("success");
    }
}
exit("failed");