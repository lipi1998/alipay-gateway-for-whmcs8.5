<?php
if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}
  require_once __DIR__ .'/aop/AopClient.php';
  require_once __DIR__ .'/aop/AopCertClient.php';
  require_once __DIR__ .'/aop/AopCertification.php';
  require_once __DIR__ .'/aop/AlipayConfig.php';
  require_once __DIR__ .'/aop/request/AlipayTradePagePayRequest.php';
  require_once __DIR__ .'/aop/request/AlipayTradeWapPayRequest.php';


use Illuminate\Database\Capsule\Manager as Capsule;

class alipayfull_link {
    
    public function get_paylink($params){
        if (!function_exists("openssl_open")){
            return '<span style="color:red">Fatal Error:管理员未开启openssl组件<br/>正常情况下该组件必须开启<br/>请开启openssl组件解决该问题</span>';
        }
        if (!function_exists("scandir")){
            return '<span style="color:red">Fatal Error:管理员未开启scandir PHP函数<br/>支付宝Sdk 需要使用该函数<br/>请修改php.ini下的disable_function来解决该问题</span>';
        }
        // $type = Capsule::table("tblpaymentgateways")->where("gateway","alipay_full")->where("setting","apitype")->first();
        $type = $params['apitype'];
        $skintype = Capsule::table("tblpaymentgateways")->where("gateway","alipay_full")->where("setting","skintype")->first();
        switch ($type) {
            case "1":
                // return $this->normal_mapi($params);
                return $this->normal_pcpay($params);
            case "2";
                if ($this->is_mobile()){
                    return $this->normal_h5pay($params);
                } else {
                    return $this->normal_pcpay($params);
                }
            case "3";
                return $this->normal_f2fpay($params);
        }
    }
    
    public function normal_mapi($params){
        require_once __DIR__ ."/alipay_mapi/alipay.class.php";
        $alipay_controller = new Alipay($this->mapi_get_basicconfig($params));
        $parameter = $this->mapi_get_orderconfig($params);
        $normal = $alipay_controller->buildRequestFormHTML($parameter,"get");
        $parameter["qr_pay_mode"] = "1";
        $qr = $alipay_controller->buildRequestQr($parameter,"get");
        return $qr.$normal;
    }
    
    public function normal_pcpay($params){
        // $privateKey = "MIIEpAIBAAKCAQEA3MahFATZJoHjhXatXUmrYhib4ox872lZ9l8k6O4a4vmHfBsKhqbU7f8ePDRx/KguH4oHpYftR6XBEVzdTQOeJmwOJh8MCDVSjnr5BmgPLQEq67cGPrjlPAxSbhqvLmUzTQq0rEITLMWoZNr+hOyHwoiqVVLz8MD44PVRksHfEM+rQUsqj0kunHYhntYuCs3clZHJahMZ3wJGUwvxUfVjvrT0kR6d1SASbSSTycbYl7grFKObLpRuLu7OS7KwNeuGnNM8PiBWeq4ncR+JVn1wXz1oDJSbbybSHDxOugIanwyWmgOaEXQqoD9s5pOI8X1RUhLvYJXNUw1M/Y3x7ApZHwIDAQABAoIBAFmqTldsFKmgDUDyTzzZI8MGjaE4P7GYjxIR2FLGCaNvhsgvz1mavlYezC/VeQoNYBYtICfpicQUnNIpbjPOKVgfgKuY5nEa/vmhFiy07JzxoXX0cpPc0jVOJ9hR/B4SugArPe/MMi9344l6q+5ehlDK4qsesrZwGWR8HfJFzQvtGpksvWnoTyz/Y3aDtOzA7e47g9/IuO1jY+aPD3HID8b4trHDlbMnaysvUexBqZJSg8vw+v3rvd9207vD7BUcuT1ssMIjiZnAT5yTUHtaNQanKczSPQ4vjgdGdX2NL2uf569oLWvlZNBTDtF8f8tv8EAqIeLaYZBOkFx9WYGfUeECgYEA+mEyPcyzpzgIT0NaLBfLoSFg2P3mcunrfmJ+fDv/zUtWciZF/zZfkTOLmVTxnHn9Z+DSWfXQLRpqVdkjt7Bb8A9zYKPRKXeCAbCsAZx8LrHGsrT4ipcEADlZqvMtOH4jgmixvfLiSdWcaDpGwKTAw+RZdppnWWnyChEJ+nkdey8CgYEA4btQsYbZkybtN1Y2T96BNgIyA+zAJUmgnNA8m/GUWtao6D9LJ6YoGKJNl5Qkq7tvArvkzwKNkZluJ7CWdHoghtNV+AZ9HQ2x/xk0w1xgpLR5LQIp6XbnFLkIeCFoFfOixNn7FrOQDbDQXYcYRBLDJ0Grt7k5Ov6otKVEzkjWxRECgYAGAHx21Mhdss8oL0IjGnLsKuOqb/OtP4RApFXJ3ppULoEk/VviMUh7L5QiGdIs4RO9ALuqImVaH277HdhoV9bsW0J1x5eE+fNo3PZSl5C2gdZ0hDgNAm+7HaTTnz6vQv7Q6neQSRk5keBM81Cs34Yra/blC/B8STjfGud1VJ/rSwKBgQCTE6Y/FVr8Sxeyv4SBw7sywnluHzsO0ItKwU9MWDpOeaDyOhMw0U08x7uAsPC3yFdLU7uAuewd2vdv+tn9KHm6/0X7ZdbtMDgyu2yqga0ig8iUb915FZT45prDExkrfGQomNLF9tc8ZGFPHy/LYuIu2NYWziOg8b5gfXJ4afMt0QKBgQCI300PRZgji7+sntIxpx6U9tgXj4xsByzCwZ3ZVL5SQ66ZquS5vlLQjaJcC9oeFCRRzNHJoghi1SNdNiyXjc7UBF0AbvpRT2x5CmrFYrQDrLDKS/z42yk2ROwl3I/SO7Sv65drmBzwieGk83jk9vfGt2BNvyVj87ximTd6dRsprA==";
        // $alipayPublicKey = "MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAok8hnb8hEZBNO+axeUgL1b7PYkVxV+Vly1h5QWbNLPFk/Q4EOcqpc4gqXCamR6AGQFhZ6qY/szu0LisTbJkdZIvJW3zlBLAfK1eG13Cg1x4tOa0DZRaA+YIfDJHlkU2tosjc626Uygb651Z+h6QLu7ncNE4quTdXGmg4lfgnZV5wJlWXRXWSbXZfNYNGDcCJg9lY4wsCb7oyG3lh656DZz5EIZ5/axE/TNhXmLFl8LlBvZvKMKqjMRIyM63BsflShw3fGtFbf8bU15rMwYkb6amuBn1Py91CuZoOCX7L4wQqVUmWiJFCDWmEIU1ugE3JTAPIzEjR4E0ieVGdkhXgOQIDAQAB";
        $alipayConfig = new AlipayConfig();
        $alipayConfig->setServerUrl("https://openapi.alipay.com/gateway.do");
        $alipayConfig->setAppId($params['app_id']);
        $alipayConfig->setPrivateKey($params['rsa_key']);
        $alipayConfig->setFormat("json");
        $alipayConfig->setAlipayPublicKey($params['alipay_key']);
        $alipayConfig->setCharset("UTF-8");
        $alipayConfig->setSignType("RSA2");
        $alipayClient = new AopClient($alipayConfig);
        $request = new AlipayTradePagePayRequest();
        $request->setBizContent("{".
            "\"out_trade_no\":\""."lipi".md5(uniqid())."-".$params['invoiceid']."\",".
            "\"total_amount\":\"".$params['amount']."\",".
            "\"subject\":\"".$params['companyname']."订单 [# ".$params['invoiceid']." ]"."\",".
            "\"product_code\":\"FAST_INSTANT_TRADE_PAY\"".
        "}");
        $responseResult = $alipayClient->pageExecute($request);
        return $responseResult;
    }

    public function normal_h5pay($params){
        // $privateKey = "MIIEpAIBAAKCAQEA3MahFATZJoHjhXatXUmrYhib4ox872lZ9l8k6O4a4vmHfBsKhqbU7f8ePDRx/KguH4oHpYftR6XBEVzdTQOeJmwOJh8MCDVSjnr5BmgPLQEq67cGPrjlPAxSbhqvLmUzTQq0rEITLMWoZNr+hOyHwoiqVVLz8MD44PVRksHfEM+rQUsqj0kunHYhntYuCs3clZHJahMZ3wJGUwvxUfVjvrT0kR6d1SASbSSTycbYl7grFKObLpRuLu7OS7KwNeuGnNM8PiBWeq4ncR+JVn1wXz1oDJSbbybSHDxOugIanwyWmgOaEXQqoD9s5pOI8X1RUhLvYJXNUw1M/Y3x7ApZHwIDAQABAoIBAFmqTldsFKmgDUDyTzzZI8MGjaE4P7GYjxIR2FLGCaNvhsgvz1mavlYezC/VeQoNYBYtICfpicQUnNIpbjPOKVgfgKuY5nEa/vmhFiy07JzxoXX0cpPc0jVOJ9hR/B4SugArPe/MMi9344l6q+5ehlDK4qsesrZwGWR8HfJFzQvtGpksvWnoTyz/Y3aDtOzA7e47g9/IuO1jY+aPD3HID8b4trHDlbMnaysvUexBqZJSg8vw+v3rvd9207vD7BUcuT1ssMIjiZnAT5yTUHtaNQanKczSPQ4vjgdGdX2NL2uf569oLWvlZNBTDtF8f8tv8EAqIeLaYZBOkFx9WYGfUeECgYEA+mEyPcyzpzgIT0NaLBfLoSFg2P3mcunrfmJ+fDv/zUtWciZF/zZfkTOLmVTxnHn9Z+DSWfXQLRpqVdkjt7Bb8A9zYKPRKXeCAbCsAZx8LrHGsrT4ipcEADlZqvMtOH4jgmixvfLiSdWcaDpGwKTAw+RZdppnWWnyChEJ+nkdey8CgYEA4btQsYbZkybtN1Y2T96BNgIyA+zAJUmgnNA8m/GUWtao6D9LJ6YoGKJNl5Qkq7tvArvkzwKNkZluJ7CWdHoghtNV+AZ9HQ2x/xk0w1xgpLR5LQIp6XbnFLkIeCFoFfOixNn7FrOQDbDQXYcYRBLDJ0Grt7k5Ov6otKVEzkjWxRECgYAGAHx21Mhdss8oL0IjGnLsKuOqb/OtP4RApFXJ3ppULoEk/VviMUh7L5QiGdIs4RO9ALuqImVaH277HdhoV9bsW0J1x5eE+fNo3PZSl5C2gdZ0hDgNAm+7HaTTnz6vQv7Q6neQSRk5keBM81Cs34Yra/blC/B8STjfGud1VJ/rSwKBgQCTE6Y/FVr8Sxeyv4SBw7sywnluHzsO0ItKwU9MWDpOeaDyOhMw0U08x7uAsPC3yFdLU7uAuewd2vdv+tn9KHm6/0X7ZdbtMDgyu2yqga0ig8iUb915FZT45prDExkrfGQomNLF9tc8ZGFPHy/LYuIu2NYWziOg8b5gfXJ4afMt0QKBgQCI300PRZgji7+sntIxpx6U9tgXj4xsByzCwZ3ZVL5SQ66ZquS5vlLQjaJcC9oeFCRRzNHJoghi1SNdNiyXjc7UBF0AbvpRT2x5CmrFYrQDrLDKS/z42yk2ROwl3I/SO7Sv65drmBzwieGk83jk9vfGt2BNvyVj87ximTd6dRsprA==";
        // $alipayPublicKey = "MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAok8hnb8hEZBNO+axeUgL1b7PYkVxV+Vly1h5QWbNLPFk/Q4EOcqpc4gqXCamR6AGQFhZ6qY/szu0LisTbJkdZIvJW3zlBLAfK1eG13Cg1x4tOa0DZRaA+YIfDJHlkU2tosjc626Uygb651Z+h6QLu7ncNE4quTdXGmg4lfgnZV5wJlWXRXWSbXZfNYNGDcCJg9lY4wsCb7oyG3lh656DZz5EIZ5/axE/TNhXmLFl8LlBvZvKMKqjMRIyM63BsflShw3fGtFbf8bU15rMwYkb6amuBn1Py91CuZoOCX7L4wQqVUmWiJFCDWmEIU1ugE3JTAPIzEjR4E0ieVGdkhXgOQIDAQAB";
        $alipayConfig = new AlipayConfig();
        $alipayConfig->setServerUrl("https://openapi.alipay.com/gateway.do");
        $alipayConfig->setAppId($params['app_id']);
        $alipayConfig->setPrivateKey($params['rsa_key']);
        $alipayConfig->setFormat("json");
        $alipayConfig->setAlipayPublicKey($params['alipay_key']);
        $alipayConfig->setCharset("UTF-8");
        $alipayConfig->setSignType("RSA2");
        $alipayClient = new AopClient($alipayConfig);
        $request = new AlipayTradeWapPayRequest();
        $request->setBizContent("{".
            "\"out_trade_no\":\""."lipi".md5(uniqid())."-".$params['invoiceid']."\",".
            "\"total_amount\":\"".$params['amount']."\",".
            "\"subject\":\"".$params['companyname']."订单 [# ".$params['invoiceid']." ]"."\",".
            "\"product_code\":\"QUICK_WAP_WAY\"".
        "}");
        $responseResult = $alipayClient->pageExecute($request);
        return $responseResult;
    }
    
    public function mobile_mapi($params){
        require_once __DIR__ ."/alipay_mapi/alipay.class.php";
        $alipay_controller = new Alipay($this->mapi_get_basicconfig($params),true);
        $parameter = $this->mapi_get_orderconfig($params);
        $normal = $alipay_controller->buildRequestFormHTML($parameter,"get");
        $parameter["qr_pay_mode"] = "1";
        $qr = $alipay_controller->buildRequestQr($parameter,"get");
        return $qr.$normal;
    }

    public function normal_f2fpay($params){
        require_once __DIR__ ."/f2fpay/model/builder/AlipayTradePrecreateContentBuilder.php";
        require_once __DIR__ ."/f2fpay/service/AlipayTradeService.php";
        if (empty($params['alipay_key'])){
            return "管理员未配置 支付宝公钥 , 无法使用该支付接口";
        } 
        if (empty($params['rsa_key'])){
            return "管理员未配置 RSA私钥  , 无法使用该支付接口";
        }
        $qrPayRequestBuilder = new AlipayTradePrecreateContentBuilder();
        $qrPayRequestBuilder->setOutTradeNo("weloveidc".md5(uniqid())."-".$params['invoiceid']);
        $qrPayRequestBuilder->setTimeExpress("5m");
        $qrPayRequestBuilder->setTotalAmount($params['amount']);
        $qrPayRequestBuilder->setSubject($params['companyname']."订单 [# ".$params['invoiceid']." ]");
        $qrPayRequestBuilder->setBody($params["description"]);
        try {
            $qrPay = new AlipayTradeService($this->f2fpay_get_basicconfig($params));
            $qrPayResult = $qrPay->qrPay($qrPayRequestBuilder);
        } catch (Exception $e) {
            return "管理员模块配置出现问题 <br/> 无法使用该接口(签名不符合)";
        }
        
        switch ($qrPayResult->getTradeStatus()){
            case "SUCCESS":
                $response = $qrPayResult->getResponse();
                $qrcode = $qrPay->create_erweima($response->qr_code);
                if ($this->is_mobile()){
                    $skin_raw = file_get_contents(__DIR__ . "/skin/default/fpay_mobile.tpl");
                } else {
                    $skin_raw = file_get_contents(__DIR__ . "/skin/default/fpay.tpl");
                }
                $skin_raw = str_replace('{$url}',urldecode($qrcode),$skin_raw);
                return $skin_raw;
            case "FAILED":
                return "支付宝创建订单二维码失败";
            case "UNKNOWN":
                return "系统异常，状态未知";
            default:
                return "不支持的返回状态，创建订单二维码返回异常";
        }
    }
    
    public function mapi_get_basicconfig($params){
        return [
        "partner" => trim($params['partnerID']),
        "key" => trim($params['security_code']),
        "seller_email" => trim($params['seller_email']),
        "sign_type" => "MD5",
        "input_charset" => "utf-8",
        "transport" => "https",
        "payment_type" => 1,
        "return_url" =>  $params['systemurl']."/modules/gateways/callback/alipay_full/return.php",
        "notify_url" => $params['systemurl']."/modules/gateways/callback/alipay_full/notify.php",
        "cacert" => dirname(__FILE__) . "/alipay_mapi/cacert.pem",
        ];
    }
    
    private function mapi_get_orderconfig($params){
        return [
        "quantity" => 1,
        "subject" => $params['companyname']."订单 [# ".$params['invoiceid']." ]",
        "price" => $params['amount'],
        "body" => $params["description"],
        "out_trade_no" => "weloveidc".md5(uniqid())."-".$params['invoiceid'],
        ];
    }

    public function f2fpay_get_basicconfig($params){
        return [
            'sign_type' => "RSA2",
            'alipay_public_key' => $params['alipay_key'],
            'merchant_private_key' => 
                str_replace(["\r", "\n", "-----BEGIN RSA PRIVATE KEY-----", "-----END RSA PRIVATE KEY-----"],
                    "", $params['rsa_key']),
            'charset' => "UTF-8",
            'gatewayUrl' => "https://openapi.alipay.com/gateway.do",
            'app_id' => $params['app_id'],
            'notify_url' => $params['systemurl']."/modules/gateways/callback/alipay_full/f2fpay_notify.php",
            'MaxQueryRetry' => "10",
            'QueryDuration' => "3"
        ];
    }
    
    private function is_mobile(){
        $useragent=$_SERVER['HTTP_USER_AGENT'];
        if(preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i',$useragent)||preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i',substr($useragent,0,4))){
            return true;
        }
        return false;
    }
}