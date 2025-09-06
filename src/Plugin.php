<?php

namespace Cloudcogs\Woocommerce\Gateway\PowerTranz;

use SkyVerge\WooCommerce\PluginFramework\v5_15_1 as Framework;

class Plugin extends Framework\SV_WC_Payment_Gateway_Plugin
{

    /** plugin version number */
    const VERSION = '0.2.1';

    /** plugin id */
    const PLUGIN_ID = 'cc_woocommerce_gateway_powertranz';

    const TEXT_DOMAIN = 'cc-woocommerce-gateway-powertranz';

    /** credit card gateway ID */
    const CREDIT_CARD_GATEWAY_ID_DIRECT = 'powertranz_3ds2_gateway_direct';
    const CREDIT_CARD_GATEWAY_CLASS_DIRECT = Direct::class;

    /** @var Plugin */
    protected static $instance;

    public static function instance() : Plugin
    {

        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function __construct()
    {

        parent::__construct(
            self::PLUGIN_ID,
            self::VERSION,
            [
                'text_domain' => self::TEXT_DOMAIN,
                'gateways' => [
                    self::CREDIT_CARD_GATEWAY_ID_DIRECT => self::CREDIT_CARD_GATEWAY_CLASS_DIRECT
                ],
                'require_ssl' => true,
                'supports' => [
                    self::FEATURE_CAPTURE_CHARGE,
                ],
                'supported_features' => [
                    'hpos'   => true,
                    'blocks' => [
                        'cart'     => false,
                        'checkout' => false,
                    ],
                ],
            ]
        );
    }

    public function add_admin_notices(): void
    {
        $credit_card_settings = get_option('woocommerce_'.self::CREDIT_CARD_GATEWAY_ID_DIRECT.'_settings');
        if (! $this->is_plugin_settings()) {
            if (empty($credit_card_settings) &&
                !$this->get_admin_notice_handler()->is_notice_dismissed('install-notice')) {
                $this->get_admin_notice_handler()->add_admin_notice(
                    sprintf(
                        __(
                            \WC_PowerTranz_Loader::PLUGIN_NAME.
                            ' is almost ready. To get started, %sconfigure your account</a>.',
                            self::TEXT_DOMAIN
                        ),
                        '<a href="'.$this->get_settings_url(self::CREDIT_CARD_GATEWAY_ID_DIRECT).'">'
                    ),
                    'install-notice',
                    ['notice_class' => 'updated']
                );
            }

            // SSL check
            if (! wc_checkout_is_https() &&
                !$this->get_admin_notice_handler()->is_notice_dismissed('ssl-recommended-notice')) {
                $this
                    ->get_admin_notice_handler()
                    ->add_admin_notice(
                        __(
                            'WooCommerce is not being forced over SSL -- Using '.
                            \WC_PowerTranz_Loader::PLUGIN_NAME.' requires that checkout to be forced over SSL.',
                            self::TEXT_DOMAIN
                        ),
                        'ssl-recommended-notice'
                    );
            }
        }
    }

    public function get_plugin_file(): string
    {
        return 'cc-woocommerce-gateway-powertranz/cc-woocommerce-gateway-powertranz.php';
    }

    protected function get_file(): string {
        return __FILE__;
    }

    public function get_plugin_name(): string {
        return __(\WC_PowerTranz_Loader::PLUGIN_NAME, self::TEXT_DOMAIN);
    }

    public function get_documentation_url() : string
    {
        return 'https://github.com/cloudcogsio/woocommerce-omnipay-powertranz-3ds2-gateway/wiki';
    }

    public function get_support_url() : string
    {
        return 'https://github.com/cloudcogsio/woocommerce-omnipay-powertranz-3ds2-gateway/issues';
    }

    public function get_settings_link($plugin_id = null) : string
    {
        return sprintf(
            '<a href="%s">%s</a>',
            $this->get_settings_url($plugin_id),
            self::CREDIT_CARD_GATEWAY_ID_DIRECT === $plugin_id ?
                __('Configure Gateway', self::TEXT_DOMAIN) : "Configure"
        );
    }
}
