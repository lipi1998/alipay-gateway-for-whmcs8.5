# alipay-gateway-for-whmcs8.5

支付宝支付网关，适用于 WHMCS 8.5。已移除旧的 mapi 接口，改用支付宝开放平台的
电脑网站支付、手机网站支付与当面付。

测试版本：WHMCS 8.5.1

## 支持的接口

在 `设置 - 支付网关 - 支付宝全能模块` 里选择接口类型后保存，再填写密钥：

| 接口类型 | 支付宝接口 | 说明 |
| --- | --- | --- |
| 电脑网站支付 | `alipay.trade.page.pay` | 跳转到支付宝收银台 |
| 电脑网站支付 + 手机网站支付 | `alipay.trade.wap.pay` | 按 User-Agent 自动切换，需额外申请手机网站支付 |
| 当面付 | `alipay.trade.precreate` | 展示二维码，手机端直接跳转支付宝 App |

三种方式都需要填写 APPID、支付宝公钥、RSA2(SHA256) 商户私钥。

## 安装

把 `modules/` 目录合并到 WHMCS 根目录，然后在管理端激活模块。

回调地址由模块自动生成，无需在支付宝后台配置：

- `modules/gateways/callback/alipay_full/notify.php` — 电脑/手机网站支付异步通知
- `modules/gateways/callback/alipay_full/return.php` — 电脑/手机网站支付同步跳转
- `modules/gateways/callback/alipay_full/f2fpay_notify.php` — 当面付异步通知

## 目录结构

```
modules/gateways/
├── alipay_full.php                      WHMCS 模块入口
├── callback/alipay_full/
│   ├── init.php                         回调公共引导：WHMCS 环境、货币换算、验签入账流程
│   ├── notify.php                       电脑/手机网站支付异步通知
│   ├── return.php                       电脑/手机网站支付同步跳转
│   └── f2fpay_notify.php                当面付异步通知
└── class/alipay_full/
    ├── config_gen.php                   管理端配置项
    ├── link_gen.php                     发票页面的付款界面
    ├── api_client.php                   开放平台调用封装：密钥处理 / 验签 / 查单
    ├── aop/                             支付宝官方 PHP SDK
    ├── f2fpay/                          当面付 SDK
    └── skin/default/                    当面付二维码页面模板
```

## 入账逻辑

回调报文里的交易状态和金额都是外部输入，因此每个回调都会先用支付宝公钥验签，
再调用 `alipay.trade.query` 主动查单，最终以查单返回的状态、金额和支付宝交易号入账。
同步跳转与异步通知都会到达，靠支付宝交易号去重，不会重复入账。

## 环境要求

PHP 7.4 以上，需要 `openssl`、`curl`、`mbstring` 扩展。
