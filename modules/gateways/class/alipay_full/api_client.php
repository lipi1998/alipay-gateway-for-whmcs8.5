<?php
if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

require_once __DIR__ . '/aop/AopClient.php';
require_once __DIR__ . '/aop/request/AlipayTradeQueryRequest.php';

/**
 * 支付宝开放平台 (openapi) 调用封装。
 *
 * 前台下单 (link_gen.php) 与三个回调入口 (callback/alipay_full/*.php) 共用这里的
 * 密钥处理、客户端构建、验签与查单逻辑，避免同一套参数在多处各写一遍。
 */
class alipayfull_api
{
    /** 支付宝正式环境网关 */
    const GATEWAY_URL = "https://openapi.alipay.com/gateway.do";

    /** 模块统一使用 RSA2(SHA256) 签名 */
    const SIGN_TYPE = "RSA2";

    /**
     * 去掉 PEM 头尾标记与换行。
     *
     * 管理员通常直接粘贴 .pem 文件全文，而 SDK 只接受不含头尾、不含换行的
     * 裸 base64 字符串（它会自行补上头尾再交给 openssl）。
     *
     * @param string $key
     * @return string
     */
    public static function normalize_key($key)
    {
        return trim(preg_replace('/\s+/', '', str_replace(
            [
                "-----BEGIN RSA PRIVATE KEY-----",
                "-----END RSA PRIVATE KEY-----",
                "-----BEGIN PRIVATE KEY-----",
                "-----END PRIVATE KEY-----",
                "-----BEGIN PUBLIC KEY-----",
                "-----END PUBLIC KEY-----",
            ],
            "",
            $key
        )));
    }

    /**
     * 用 WHMCS 里保存的模块配置构建 AopClient。
     *
     * @param array $params 网关配置 (alipay_full_link 的 $params 或 getGatewayVariables 的返回值)
     * @return AopClient
     * @throws Exception 配置不完整时抛出，由调用方转成对用户可见的提示
     */
    public static function build_client(array $params)
    {
        if (empty($params['app_id']) || empty($params['rsa_key']) || empty($params['alipay_key'])) {
            throw new Exception("管理员未完整配置 APPID / RSA私钥 / 支付宝公钥");
        }

        $config = new AlipayConfig();
        $config->setServerUrl(self::GATEWAY_URL);
        $config->setAppId(trim($params['app_id']));
        $config->setPrivateKey(self::normalize_key($params['rsa_key']));
        $config->setAlipayPublicKey(self::normalize_key($params['alipay_key']));
        $config->setSignType(self::SIGN_TYPE);
        $config->setCharset("UTF-8");
        $config->setFormat("json");

        return new AopClient($config);
    }

    /**
     * 拼出回调地址。
     *
     * @param array  $params 网关配置，需含 systemurl
     * @param string $file   callback/alipay_full/ 下的文件名
     * @return string
     */
    public static function callback_url(array $params, $file)
    {
        return rtrim($params['systemurl'], '/') . "/modules/gateways/callback/alipay_full/" . $file;
    }

    /**
     * 校验支付宝回调报文的 RSA2 签名。
     *
     * 同步跳转 ($_GET) 与异步通知 ($_POST) 用的是同一套签名规则，因此两者共用本方法。
     *
     * @param array $data   原始回调参数
     * @param array $params 网关配置
     * @return bool
     */
    public static function verify_callback(array $data, array $params)
    {
        if (empty($data['sign'])) {
            return false;
        }

        try {
            $aop = self::build_client($params);
        } catch (Exception $e) {
            return false;
        }

        return (bool) $aop->rsaCheckV1($data, null, self::SIGN_TYPE);
    }

    /**
     * 调用 alipay.trade.query 主动查单。
     *
     * 回调报文里的交易状态与金额都是外部输入，即使验签通过也只能当作“有笔交易变化了”
     * 的提示；真正入账用的状态、金额、支付宝交易号一律以本次查单结果为准。
     *
     * @param array  $params       网关配置
     * @param string $out_trade_no 商户订单号
     * @param string $trade_no     支付宝交易号，可为空
     * @return object|null 查单成功 (code=10000) 返回 response 节点，否则返回 null
     */
    public static function query_trade(array $params, $out_trade_no, $trade_no = '')
    {
        try {
            $aop = self::build_client($params);
        } catch (Exception $e) {
            return null;
        }

        $biz = ["out_trade_no" => $out_trade_no];
        if ($trade_no !== '') {
            $biz["trade_no"] = $trade_no;
        }

        $request = new AlipayTradeQueryRequest();
        $request->setBizContent(json_encode($biz, JSON_UNESCAPED_UNICODE));

        try {
            $result = $aop->execute($request);
        } catch (Exception $e) {
            // 网络异常或响应验签失败，视为查单失败，调用方不入账
            return null;
        }

        $node = str_replace(".", "_", $request->getApiMethodName()) . "_response";
        if (!is_object($result) || !isset($result->$node)) {
            return null;
        }

        $response = $result->$node;

        return (isset($response->code) && $response->code == "10000") ? $response : null;
    }

    /**
     * 判断查单返回的交易状态是否已付款。
     *
     * @param object|null $response query_trade 的返回值
     * @return bool
     */
    public static function is_paid($response)
    {
        return is_object($response)
            && isset($response->trade_status)
            && in_array($response->trade_status, ["TRADE_SUCCESS", "TRADE_FINISHED"], true);
    }

    /**
     * 生成商户订单号。
     *
     * 结尾必须是 "-发票号"，回调时靠这个后缀反查 WHMCS 发票。
     *
     * @param string|int $invoice_id
     * @return string
     */
    public static function build_out_trade_no($invoice_id)
    {
        return "lipi" . md5(uniqid('', true)) . "-" . $invoice_id;
    }

    /**
     * 从商户订单号里取回发票号。
     *
     * @param string $out_trade_no
     * @return string 取不到时返回空字符串
     */
    public static function parse_invoice_id($out_trade_no)
    {
        $parts = explode("-", (string) $out_trade_no);

        return isset($parts[1]) ? $parts[1] : '';
    }
}
