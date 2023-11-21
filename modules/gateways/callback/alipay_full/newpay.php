<?php
  require_once __DIR__ .'/aop/AopClient.php';
  require_once __DIR__ .'/aop/AopCertClient.php';
  require_once __DIR__ .'/aop/AopCertification.php';
  require_once __DIR__ .'/aop/AlipayConfig.php';
  require_once __DIR__ .'/aop/request/AlipayTradePagePayRequest.php';
  require_once __DIR__ .'/aop/request/AlipayTradeWapPayRequest.php';

  $params = getGatewayVariables($gatewaymodule);
  if (!$GATEWAY["type"]) die("Module Not Activated");
  $aop = new AopClient ();
$aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
$aop->appId = $params['app_id'];
$aop->rsaPrivateKey = $params['rsa_key'];
$aop->alipayrsaPublicKey=$params['alipay_key'];
$aop->apiVersion = '1.0';
$aop->signType = 'RSA2';
$aop->postCharset='GBK';
$aop->format='json';
$request = new AlipayTradeQueryRequest ();
$request->setBizContent("{" .
"  \"out_trade_no\":\"20150320010101001\"," .
"  \"trade_no\":\"2014112611001004680 073956707\"," .
"  \"query_options\":[" .
"    \"trade_settle_info\"" .
"  ]" .
"}");
$result = $aop->execute ( $request); 

$responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
$resultCode = $result->$responseNode->code;
if(!empty($resultCode)&&$resultCode == 10000){
echo "成功";
} else {
echo "失败";
}