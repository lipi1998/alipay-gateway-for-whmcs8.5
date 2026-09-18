<?php
if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * 管理端配置项。
 *
 * WHMCS 的网关配置是一次性声明的，没有"根据已选项动态显示字段"的机制，
 * 所以这里先读一次库里已保存的 apitype，再决定要暴露哪些字段：
 * 未保存过时只显示提示，保存后才显示对应接口需要的密钥字段。
 */
class alipayfull_config
{
    /**
     * 组装配置项数组，供 alipay_full_config() 返回给 WHMCS。
     *
     * @return array
     */
    public function get_configuration()
    {
        global $_ADMINLANG, $CONFIG;

        $apitype = $this->get_saved_setting("apitype");

        if ($apitype === null) {
            // 还没保存过接口类型，先让管理员选一次并保存
            $extra_config = [
                "notice" => $this->notice_field(
                    "danger",
                    "alipay_full_notice",
                    "请点击 [ " . $_ADMINLANG['global']['savechanges'] . " ] 后 , 再进行修改配置",
                    "温馨提示"
                ),
            ];
        } else {
            $extra_config = $this->credential_fields($apitype);
        }

        $base_config = [
            "FriendlyName" => [
                'Type' => 'System',
                'Value' => 'LiPi - 支付宝全能模块',
            ],
            "apitype" => [
                'FriendlyName' => '支付宝接口类型',
                'Type' => 'dropdown',
                'Options' => [
                    "1" => "[官方] 电脑网站支付",
                    "2" => "[官方] 电脑网站支付 + 手机网站支付",
                    "3" => "[官方] 当面付",
                ],
            ],
        ];

        $config = array_merge($base_config, $extra_config);
        $config["author"] = $this->notice_field(
            "success",
            "alipay_full_author",
            "该插件由 <a href='https://www.lipiapp.com' target='_blank'><span class='glyphicon glyphicon-new-window'></span> LiPi</a> 开发 ， 本款插件为免费开源插件"
            . "<br/><span class='glyphicon glyphicon-ok'></span> 适用于 WHMCS 8.5 , 当前 WHMCS 版本 " . $CONFIG["Version"]
            . "<br/><span class='glyphicon glyphicon-ok'></span> 需要 PHP 7.4 以上的环境 , 当前 PHP 版本 " . phpversion(),
            '',
            "<style>* {font-family: Microsoft YaHei Light , Microsoft YaHei}</style>"
        );

        return $config;
    }

    /**
     * 读取本模块已保存的某项配置。
     *
     * @param string $setting
     * @return string|null 未保存过时返回 null
     */
    private function get_saved_setting($setting)
    {
        $row = Capsule::table("tblpaymentgateways")
            ->where("gateway", "alipay_full")
            ->where("setting", $setting)
            ->first();

        return empty($row) ? null : (string) $row->value;
    }

    /**
     * 三种接口类型都需要 APPID + 支付宝公钥 + RSA2 私钥，差别只在签约提示文案。
     *
     * @param string $apitype 已保存的接口类型 ("1" / "2" / "3")
     * @return array
     */
    private function credential_fields($apitype)
    {
        $contract_notice = [
            "1" => "请确保已签约电脑网站支付",
            "2" => "请确保已签约电脑网站支付，并申请手机网站支付功能",
            "3" => "请确保已经在支付宝签约 当面付 必需合约",
        ];

        if (!isset($contract_notice[$apitype])) {
            return [];
        }

        $fields = [
            "app_id" => [
                "FriendlyName" => "应用ID (APPID)",
                "Type" => "text",
                "Size" => "60",
            ],
            "alipay_key" => [
                "FriendlyName" => "支付宝公钥",
                "Type" => "textarea",
                'Rows' => '10',
                'Cols' => '60',
            ],
            "rsa_key" => [
                "FriendlyName" => "RSA2(SHA256) 私钥",
                "Type" => "textarea",
                'Rows' => '10',
                'Cols' => '60',
                "Description" => $this->rsa_key_description(),
            ],
            "notice" => $this->notice_field(
                "info",
                "alipay_full_notice",
                "以上信息均可以在 <a href='https://open.alipay.com/platform/keyManage.htm' target='_blank'><span class='glyphicon glyphicon-new-window'></span> 商家支付宝 开放平台</a> 找到 。 "
                . $contract_notice[$apitype]
            ),
        ];

        if ($apitype === "2") {
            $fields["extra_notice"] = $this->notice_field(
                "info",
                "alipay_full_moblie",
                "请确保已经申请支付宝手机网站支付功能 , 否则未申请手机端将不会显示支付界面(显示未签约或其他错误页面)"
            );
        }

        return $fields;
    }

    /**
     * 生成一个"伪配置项"，用来在配置页面里插入一段提示框。
     *
     * WHMCS 只允许输出预定义的几种控件，因此这里借用 dropdown：
     * 先闭合它自己的 select，输出提示框，再开一个隐藏的 select 把后面的标签吃掉。
     *
     * @param string $style        Bootstrap 提示框样式 (info / danger / success)
     * @param string $id           提示框元素 id，用于隐藏对应的 label
     * @param string $html         提示框内容
     * @param string $friendlyname 左侧标题，通常留空
     * @param string $append       额外追加的 HTML
     * @return array
     */
    private function notice_field($style, $id, $html, $friendlyname = '', $append = '')
    {
        $markup = "</option></select>"
            . "<div class='alert alert-" . $style . "' role='alert' id='" . $id . "' style='margin-bottom: 0px;'>" . $html . "</div>"
            . "<script>$('#" . $id . "').prev().hide();</script>"
            . $append
            . "<select style='display:none'>";

        return [
            'FriendlyName' => $friendlyname,
            'Type' => 'dropdown',
            'Options' => ['1' => $markup],
        ];
    }

    /**
     * RSA2 私钥字段下方的填写说明。
     *
     * @return string
     */
    private function rsa_key_description()
    {
        return '您可能需要 :<br/>'
            . '<a type="button" class="btn btn-primary" href="https://opendocs.alipay.com/common/02kipl" target="_blank"><span class="glyphicon glyphicon-new-window"></span> 密钥生成工具与教程</a>'
            . '<br/>生成器私钥文件名 : rsa_private_key.pem 公钥文件名 : rsa_public_key.pem'
            . '<br/>将私钥文件内容使用<span style="color:red">非Windows记事本打开</span> , 并将里面内容复制到上面文本框中'
            . '<br/>公钥则请到'
            . " <a href='https://open.alipay.com/platform/keyManage.htm' target='_blank'><span class='glyphicon glyphicon-new-window'></span> 商家支付宝 开放平台</a> 绑定";
    }
}
