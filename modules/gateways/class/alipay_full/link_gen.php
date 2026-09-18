<?php
if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

require_once __DIR__ . '/api_client.php';
require_once __DIR__ . '/aop/request/AlipayTradePagePayRequest.php';
require_once __DIR__ . '/aop/request/AlipayTradeWapPayRequest.php';

/**
 * 生成发票页面上的支付宝付款界面。
 *
 * 支持三种接口类型 (对应模块配置里的 apitype)：
 *   1 - 电脑网站支付 (alipay.trade.page.pay)
 *   2 - 电脑网站支付 + 手机网站支付 (按 User-Agent 自动切换到 alipay.trade.wap.pay)
 *   3 - 当面付 (alipay.trade.precreate，展示二维码)
 */
class alipayfull_link
{
    /**
     * 供 alipay_full_link() 调用的入口。
     *
     * @param array $params WHMCS 传入的网关参数
     * @return string 直接输出到发票页面的 HTML
     */
    public function get_paylink($params)
    {
        // 签名与二维码都依赖这两项，缺失时给出明确提示而不是让 SDK 抛致命错误
        if (!function_exists("openssl_open")) {
            return '<span style="color:red">Fatal Error:管理员未开启openssl组件<br/>正常情况下该组件必须开启<br/>请开启openssl组件解决该问题</span>';
        }
        if (!function_exists("scandir")) {
            return '<span style="color:red">Fatal Error:管理员未开启scandir PHP函数<br/>支付宝Sdk 需要使用该函数<br/>请修改php.ini下的disable_function来解决该问题</span>';
        }

        switch ((string) $params['apitype']) {
            case "1":
                return $this->normal_pcpay($params);
            case "2":
                return $this->is_mobile() ? $this->normal_h5pay($params) : $this->normal_pcpay($params);
            case "3":
                return $this->normal_f2fpay($params);
            default:
                return '<span style="color:red">管理员未选择支付宝接口类型</span>';
        }
    }

    /**
     * 电脑网站支付：返回一个自动提交到支付宝网关的表单。
     *
     * @param array $params
     * @return string
     */
    public function normal_pcpay($params)
    {
        return $this->build_page_request($params, new AlipayTradePagePayRequest(), "FAST_INSTANT_TRADE_PAY");
    }

    /**
     * 手机网站支付：同样是自动提交表单，只是 product_code 与接口不同。
     *
     * @param array $params
     * @return string
     */
    public function normal_h5pay($params)
    {
        return $this->build_page_request($params, new AlipayTradeWapPayRequest(), "QUICK_WAP_WAY");
    }

    /**
     * 当面付：预下单换取二维码，再套用皮肤模板展示。
     *
     * @param array $params
     * @return string
     */
    public function normal_f2fpay($params)
    {
        require_once __DIR__ . "/f2fpay/model/builder/AlipayTradePrecreateContentBuilder.php";
        require_once __DIR__ . "/f2fpay/service/AlipayTradeService.php";

        if (empty($params['alipay_key'])) {
            return "管理员未配置 支付宝公钥 , 无法使用该支付接口";
        }
        if (empty($params['rsa_key'])) {
            return "管理员未配置 RSA私钥  , 无法使用该支付接口";
        }

        $builder = new AlipayTradePrecreateContentBuilder();
        $builder->setOutTradeNo(alipayfull_api::build_out_trade_no($params['invoiceid']));
        $builder->setTimeExpress("5m");
        $builder->setTotalAmount($params['amount']);
        $builder->setSubject($params['companyname'] . "订单 [# " . $params['invoiceid'] . " ]");
        $builder->setBody($params["description"]);

        try {
            $service = new AlipayTradeService($this->f2fpay_get_basicconfig($params));
            $result = $service->qrPay($builder);
        } catch (Exception $e) {
            return "管理员模块配置出现问题 <br/> 无法使用该接口(签名不符合)";
        }

        switch ($result->getTradeStatus()) {
            case "SUCCESS":
                $template = $this->is_mobile() ? "fpay_mobile.tpl" : "fpay.tpl";
                $skin = file_get_contents(__DIR__ . "/skin/default/" . $template);

                return str_replace('{$url}', $result->getResponse()->qr_code, $skin);
            case "FAILED":
                return "支付宝创建订单二维码失败";
            case "UNKNOWN":
                return "系统异常，状态未知";
            default:
                return "不支持的返回状态，创建订单二维码返回异常";
        }
    }

    /**
     * 当面付服务所需的配置数组 (f2fpay SDK 自成一套参数格式)。
     *
     * @param array $params
     * @return array
     */
    private function f2fpay_get_basicconfig($params)
    {
        return [
            'sign_type' => alipayfull_api::SIGN_TYPE,
            'alipay_public_key' => alipayfull_api::normalize_key($params['alipay_key']),
            'merchant_private_key' => alipayfull_api::normalize_key($params['rsa_key']),
            'charset' => "UTF-8",
            'gatewayUrl' => alipayfull_api::GATEWAY_URL,
            'app_id' => trim($params['app_id']),
            'notify_url' => alipayfull_api::callback_url($params, "f2fpay_notify.php"),
            'MaxQueryRetry' => "10",
            'QueryDuration' => "3",
        ];
    }

    /**
     * 电脑/手机网站支付共用的下单流程。
     *
     * @param array  $params
     * @param object $request      已实例化的 AlipayTradePagePayRequest / AlipayTradeWapPayRequest
     * @param string $product_code 支付宝要求的销售产品码
     * @return string
     */
    private function build_page_request($params, $request, $product_code)
    {
        try {
            $client = alipayfull_api::build_client($params);
        } catch (Exception $e) {
            return '<span style="color:red">' . $e->getMessage() . '</span>';
        }

        $request->setNotifyUrl(alipayfull_api::callback_url($params, "notify.php"));
        $request->setReturnUrl(alipayfull_api::callback_url($params, "return.php"));
        $request->setBizContent(json_encode([
            "out_trade_no" => alipayfull_api::build_out_trade_no($params['invoiceid']),
            "total_amount" => $params['amount'],
            "subject" => $params['companyname'] . "订单 [# " . $params['invoiceid'] . " ]",
            "product_code" => $product_code,
        ], JSON_UNESCAPED_UNICODE));

        return $client->pageExecute($request);
    }

    /**
     * 粗略判断是否手机浏览器，用于在接口类型 2 下切换到手机网站支付。
     *
     * @return bool
     */
    private function is_mobile()
    {
        $useragent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        if ($useragent === '') {
            return false;
        }

        return (bool) (preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i', $useragent)
            || preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i', substr($useragent, 0, 4)));
    }
}
