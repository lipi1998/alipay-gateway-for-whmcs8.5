<?php
/**
 * 支付宝全能支付网关 - WHMCS 8.5
 *
 * 仅作为 WHMCS 的入口，具体实现拆到 class/alipay_full/ 下：
 *   config_gen.php - 管理端配置项
 *   link_gen.php   - 发票页面的付款界面
 *   api_client.php - 开放平台调用封装 (下单 / 验签 / 查单)
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

/**
 * 模块元数据。
 *
 * @return array
 */
function alipay_full_MetaData()
{
    return [
        'DisplayName' => 'WeLoveIDC - 支付宝全能模块',
        'APIVersion' => '1.1',
    ];
}

/**
 * 管理端 "设置 - 支付网关" 里显示的配置项。
 *
 * @return array
 */
function alipay_full_config()
{
    require_once __DIR__ . "/class/alipay_full/config_gen.php";

    $config = new alipayfull_config();

    return $config->get_configuration();
}

/**
 * 发票页面上的付款界面。
 *
 * @param array $params WHMCS 传入的网关参数与发票信息
 * @return string HTML
 */
function alipay_full_link($params)
{
    require_once __DIR__ . "/class/alipay_full/link_gen.php";

    $link = new alipayfull_link();

    return $link->get_paylink($params);
}
