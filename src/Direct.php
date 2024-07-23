<?php

namespace Cloudcogs\Woocommerce\Gateway\PowerTranz;

use Cloudcogs\Woocommerce\Gateway\PowerTranz\Api\OmniPayPowerTranz;
use Omnipay\Omnipay;
use Omnipay\PowerTranz\Gateway;
use Omnipay\PowerTranz\Schema\PaymentResponse;
use Omnipay\PowerTranz\Schema\RiskManagementResponse;
use SkyVerge\WooCommerce\PluginFramework\v5_10_13 as Framework;

class Direct extends Framework\SV_WC_Payment_Gateway_Direct
{

    const ENVIRONMENT_SANDBOX = 'sandbox';

    protected string $merchant_id;
    protected string $merchant_password;
    protected string $sandbox_merchant_id;
    protected string $sandbox_merchant_password;
    protected string $threed_secure = 'yes';
    protected string $request_avs = 'no';
    protected string $ipgeolocation_api;

    protected OmniPayPowerTranz $api;

    protected string $enable_csc = 'yes';
    protected string $enable_token_csc = 'yes';
    protected $handled_response = null;
    protected \WC_Order $order;

    public function __construct()
    {
        add_filter('wc_'.Plugin::CREDIT_CARD_GATEWAY_ID_DIRECT.'_icon', 'cc_woocommerce_gateway_powertranz_icon');

        parent::__construct(
            Plugin::CREDIT_CARD_GATEWAY_ID_DIRECT,
            cc_woocommerce_gateway_powertranz_plugin(),
            [
                'method_title' => __(\WC_PowerTranz_Loader::PLUGIN_NAME, Plugin::TEXT_DOMAIN),
                'method_description' => __(
                    'Allow customers to securely pay using their credit card via '.\WC_PowerTranz_Loader::PLUGIN_NAME
                ),
                'supports' => [
                    self::FEATURE_CARD_TYPES,
                    self::FEATURE_CREDIT_CARD_AUTHORIZATION,
                    self::FEATURE_CREDIT_CARD_CAPTURE,
                    self::FEATURE_CREDIT_CARD_CHARGE,
                    self::FEATURE_CREDIT_CARD_CHARGE_VIRTUAL,
                    //self::FEATURE_CREDIT_CARD_PARTIAL_CAPTURE,
                    self::FEATURE_DETAILED_CUSTOMER_DECLINE_MESSAGES,
                    self::FEATURE_PAYMENT_FORM,
                    self::FEATURE_PRODUCTS,
                    self::FEATURE_REFUNDS,
                    self::FEATURE_VOIDS,
                ],
                'payment_type' => self::PAYMENT_TYPE_CREDIT_CARD,
                'environments' => $this->get_environments(),
                'card_types' => [
                    'VISA' => 'Visa',
                    'MC' => 'MasterCard'
                ],
            ]
        );

        add_action(
            'woocommerce_api_' . Plugin::CREDIT_CARD_GATEWAY_ID_DIRECT . '_process_payment',
            [$this, 'gateway_callback']
        );
        add_action('add_meta_boxes_shop_order', array( $this, 'add_box' ));
    }

    /**
     * Initialize a remote gateway object
     * @return Gateway
     */
    public function initPowerTranzGateway(): Gateway
    {
        /** @var $Gateway Gateway */
        $Gateway = Omnipay::create('PowerTranz');

        $Gateway->setTestMode($this->is_test_environment());
        $Gateway->setPowerTranzId($this->get_merchant_id());
        $Gateway->setPowerTranzPassword($this->get_merchant_password());
        $Gateway->setReturnUrl(get_site_url()."/wc-api/".$this->get_id()."_process_payment");

        return $Gateway;
    }

    /**
     * @return void
     * @throws Framework\SV_WC_API_Exception
     * @throws Framework\SV_WC_Plugin_Exception
     * @throws \JsonException
     * @throws \ReflectionException
     */
    public function gateway_callback()
    {
        $PowerTranzGateway = $this->initPowerTranzGateway();

        if (isset($_POST['Response'])) {
            $postData = json_decode(stripslashes($_POST['Response']), true, 512, JSON_UNESCAPED_SLASHES);
            if (is_array($postData) && isset($postData['TransactionType'])) {
                switch ($postData['TransactionType']) {
                    case 1:
                        $Response = $PowerTranzGateway->completeAuthorize()->send();
                        break;

                    case 2:
                        $Response = $PowerTranzGateway->completePurchase()->send();
                        break;

                    default:
                        wp_safe_redirect(get_site_url()."/checkout/");
                        exit;
                }

                /** @var $Response PaymentResponse */
                if ($Response instanceof PaymentResponse) {
                    $OrderIdentifier = explode("|", $Response->getTransactionId() ?? "");
                    if (is_array($OrderIdentifier) && count($OrderIdentifier) == 2) {
                        $WC_OrderID = $OrderIdentifier[0];
                        $WC_OrderKey = $OrderIdentifier[1];

                        $this->handled_response = $this->get_api()->handle_postback_response($Response);

                        try {
                            $this->add_payment_gateway_transaction_data(
                                $this->get_order($WC_OrderID),
                                $this->handled_response
                            );
                        } catch (\Exception $e) {
                        }

                        $this->process_payment($WC_OrderID); // returns array

                        wp_safe_redirect(
                            get_site_url()."/checkout/order-received/".$WC_OrderID."/?key=".$WC_OrderKey
                        );
                        exit;
                    }
                }
            }
        }

        // Not a valid post back, redirect to checkout page
        //echo "<script>window.parent.location = '".get_site_url()."/checkout/';</script>";
        wp_safe_redirect(get_site_url()."/checkout/");
        exit;
    }

    public function add_box()
    {
        global $post;
        $order = $this->get_order($post->ID);

        if ($order->get_payment_method() == $this->id) {
            add_meta_box($this->get_id_dasherized() . 'risk-management', 'Risk Management', [
                $this,
                'create_box_content_risk_management'
            ], 'shop_order', 'side', 'high');
        }
    }

    public function create_box_content_risk_management()
    {
        global $post;
        $order = $this->get_order($post->ID);

        $RiskData = json_decode($order->get_meta($this->id.'_risk_management'), true);
        $Geolocation = json_decode($order->get_meta($this->id.'_ip_geolocation'), true);
        $Binlist = json_decode($order->get_meta($this->id.'_bin_info'), true);

        if (is_array($RiskData)) {
            $RiskManagement = new RiskManagementResponse($RiskData);
            print "<img style=\"float:right;margin-top:-1px;margin-right:10px;clear:left;width:50px !important;\" 
            src=\"".get_site_url()."/wp-content/plugins/".Plugin::TEXT_DOMAIN."/assets/3ds2-shield.png\">";

            if (is_array($Binlist)) {
                print "<div style=\"float:right;margin-top:2px;clear:right;width:72px;text-align: center\">
				<img src=\"".get_site_url()."/wp-content/plugins/".Plugin::TEXT_DOMAIN."/vendor/skyverge/wc-plugin-framework/woocommerce/payment-gateway/assets/images/card-".$Binlist['scheme'].".svg'\" width=\"30\" style=\"width:30px !important;\">
				<img src=\"https://ipgeolocation.io/static/flags/".strtolower($Binlist['country']['alpha2'])."_64.png\" width=\"30\" style=\"width:30px !important;\">
				";

                if (isset($Binlist['bank']['name'])) {
                    print "
                    <p style='margin:0px;font-size: x-small;line-height: 1'><small>
                    <a target='_blank' href='https://".($Binlist['bank']['url'] ?? '#')."'>".
                          $Binlist['country']['alpha2'].", ".explode(" ", $Binlist['bank']['name'])[0]."
                    </a>
                    </small></p>
                    ";
                } else {
                    print "<p style='margin:0px;font-size: x-smallline-height: 1'>
                    <small>".$Binlist['country']['name']."</small></p>";
                }

                print "</div>";
            }

            if (is_array($Geolocation)) {
                print "
                <div style=\"float:right;margin-top:10px;clear:right;width:72px;text-align: center\">
				    <p style='margin:0px;font-size: small;font-weight: 600'><small>Geolocation</small></p>
				    <img src=\"".$Geolocation['country_flag']."\" width=\"30\" style=\"width:40px !important;\">
				    <p style='margin:0px;font-size: x-small'>
				        <small>".$Geolocation['country_code2'];
                print "<a target='_blank' href='https://www.ipaddress.com/".((stripos($Geolocation['ip'],":") > -1) ? "ipv6" : "ipv4")."/".$Geolocation['ip']."'>".$Geolocation['ip']."</a>
				        </small>
				    </p>
				</div>";
            }

            print "<ul>";

            if (isset($RiskManagement->ThreeDSecure)) {
                $data = $RiskManagement->ThreeDSecure->toArray();

                print "<li><dl>";
                foreach ($data as $h => $v) {
                    if (in_array($h, ["Cavv", "Xid", "DsTransId"])) {
                        continue;
                    }
                    print "<dt style='font-weight: 600'>$h</dt>";
                    print "<dd style='margin: 0px;margin-bottom:5px;'>$v</dd>";
                }
                print "</dl></li>";
            }

            if ($this->is_avs_requested()) {
                print "<li style='border-top: 1px solid #000;'>
                           <dl>
                               <dt style='font-weight: 600'>AVS Response Code</dt>
                               <dd style='margin: 0px;'>"
                                .(($RiskManagement->AvsResponseCode) ?? "<small style='color: crimson'>
                                <strong><em>AVS Response Unavailable</em></strong></small>")."
                               </dd>
                           </dl>
                       </li>";
            }

            if (isset($RiskManagement->CvvResponseCode)) {
                print "<li style='border-top: 1px solid #000;'>
                           <dl>
                               <dt style='font-weight: 600'>CVV Response Code</dt>
                               <dd style='margin: 0px;'>"
                                .(($RiskManagement->CvvResponseCode) ?? "<small style='color: crimson'>
                                <strong><em>AVS Response Unavailable</em></strong></small>")."
                               </dd>
                           </dl>
                       </li>";
            }

            print "</ul>";
        } else {
            print "<small style='color: crimson'><strong><em>Risk Data Unavailable</em></strong></small>";
        }
    }

    /**
     * @inheritDoc
     */
    public function enqueue_gateway_assets()
    {
        if ($this->is_available() && $this->is_payment_form_page()) {
            parent::enqueue_gateway_assets();
        }
    }

    /**
     * Returns true if the current page contains a payment form
     * @return bool
     */
    public function is_payment_form_page(): bool
    {
        return ( is_checkout() && ! is_order_received_page() )
               || is_checkout_pay_page()
               || is_add_payment_method_page();
    }

    /**
     * @inheritDoc
     * @param int $order_id
     * @return \WC_Order
     */
    public function get_order($order_id): \WC_Order
    {
        $order = $this->order ?? parent::get_order($order_id);

        // test amount when in sandbox mode
        if ($this->is_test_environment() &&
            ($test_amount =
                Framework\SV_WC_Helper::get_posted_value('wc-' . $this->get_id_dasherized() . '-test-amount'))) {
            $order->payment_total = Framework\SV_WC_Helper::number_format($test_amount);
        }

        return $this->order = $order;
    }

    /**
     * @inheritDoc
     * @return array
     */
    protected function get_method_form_fields() : array
    {
        return [
            // production
            'merchant_id' => [
                'title'    => __('PowerTranzId', Plugin::TEXT_DOMAIN),
                'type'     => 'text',
                'class'    => 'environment-field production-field',
                'desc_tip' => __('Merchant identifier for the merchant account with PowerTranz.', Plugin::TEXT_DOMAIN),
            ],

            'merchant_password' => [
                'title'    => __('PowerTranzPassword', Plugin::TEXT_DOMAIN),
                'type'     => 'password',
                'class'    => 'environment-field production-field',
                'desc_tip' => __('Merchant processing password.', Plugin::TEXT_DOMAIN),
            ],

            // sandbox
            'sandbox_merchant_id' => [
                'title'    => __('Sandbox PowerTranzId', Plugin::TEXT_DOMAIN),
                'type'     => 'text',
                'class'    => 'environment-field sandbox-field',
                'desc_tip' => __('Merchant identifier for the merchant STAGING 
                                    account with PowerTranz.', Plugin::TEXT_DOMAIN),
            ],

            'sandbox_merchant_password' => [
                'title'    => __('Sandbox PowerTranzPassword', Plugin::TEXT_DOMAIN),
                'type'     => 'password',
                'class'    => 'environment-field sandbox-field',
                'desc_tip' => __('Merchant processing STAGING password.', Plugin::TEXT_DOMAIN),
            ],

            // 3DS
            'threed_secure_title' => [
                'title'       => __('3D Secure', Plugin::TEXT_DOMAIN),
                'type'        => 'title',
                'description' => __(
                    'The PowerTranz gateway supports EMV 3D-Secure versions 2.x with fallback to 3DS version 1.0 as 
                    well as support for non-3DS enabled cards.',
                    Plugin::TEXT_DOMAIN
                ),
            ],
            'threed_secure' => [
                'title'    => __('Use 3D Secure', Plugin::TEXT_DOMAIN),
                'type'     => 'select',
                'default'  => 'yes',
                'options'  => ['yes'=>'Yes (Strongly Recommended)','no'=>'No'],
            ],

            // AVS
            'request_avs' => [
                'title'    => __('Request Address Verification (AVS)', Plugin::TEXT_DOMAIN),
                'type'     => 'select',
                'default'  => 'no',
                'options'  => ['yes'=>'Yes','no'=>'No'],
            ],

            // IP Geolocation
            'ipgeolocation_title' => [
                'title'       => __('IP Geolocation', Plugin::TEXT_DOMAIN),
                'type'        => 'title',
                'description' => __(
                    'Performs IP geolocation lookup of customer IP for each order.',
                    Plugin::TEXT_DOMAIN
                ),
            ],
            'ipgeolocation_api' => [
                'title'       => __('API Key', Plugin::TEXT_DOMAIN),
                'type'        => 'text',
                'description' => __(
                    'Signup at <a target=\"_blank\" href=\"https://ipgeolocation.io\">https://ipgeolocation.io</a>',
                    Plugin::TEXT_DOMAIN
                ),
                'desc_tip'     => "Your IP Geolocation API Key",
            ]
        ];
    }

    /**
     * @inheritDoc
     * @return bool
     */
    public function is_configured() : bool
    {
        $is_configured = parent::is_configured();

        if (! $this->get_merchant_id() || ! $this->get_merchant_password()) {
            $is_configured = false;
        }

        return $is_configured;
    }

    /**
     * Get the API object
     *
     * @return OmniPayPowerTranz|Framework\SV_WC_Payment_Gateway_API
     */
    public function get_api()
    {
        if (isset($this->api)) {
            return $this->api;
        }

        return $this->api = new OmniPayPowerTranz($this);
    }

    /**
     * @inheritDoc
     * @return array
     */
    public function get_environments() : array
    {
        return [
            self::ENVIRONMENT_PRODUCTION => __('Production', Plugin::TEXT_DOMAIN),
            self::ENVIRONMENT_SANDBOX => __('Sandbox', Plugin::TEXT_DOMAIN)
        ];
    }

    /**
     * Returns true if the current gateway environment is configured to 'sandbox'
     *
     * @inheritDoc
     * @param $environment_id
     * @return bool
     */
    public function is_test_environment($environment_id = null) : bool
    {
        // Check the passed in environment (if available)
        if (!is_null($environment_id)) {
            return self::ENVIRONMENT_SANDBOX === $environment_id;
        }

        return $this->is_environment(self::ENVIRONMENT_SANDBOX);
    }

    /**
     * Returns the merchant ID based on the current environment
     *
     * @param string|null $environment_id - optional one of 'sandbox' or 'production',
     * defaults to current configured environment.
     *
     * @return string
     */
    public function get_merchant_id(?string $environment_id = null) : string
    {
        if (is_null($environment_id)) {
            $environment_id = $this->get_environment();
        }

        return self::ENVIRONMENT_PRODUCTION === $environment_id ? $this->merchant_id : $this->sandbox_merchant_id;
    }

    /**
     * Returns the merchant password based on the current environment
     *
     * @param string|null $environment_id - optional one of 'sandbox' or 'production', defaults to current
     * configured environment
     *
     * @return string
     */
    public function get_merchant_password(?string $environment_id = null) : string
    {
        if (is_null($environment_id)) {
            $environment_id = $this->get_environment();
        }

        return self::ENVIRONMENT_PRODUCTION === $environment_id ?
            $this->merchant_password : $this->sandbox_merchant_password;
    }

    public function is_three_ds_enabled() : bool
    {
        return ($this->threed_secure == 'yes') ?? false;
    }

    public function is_avs_requested() : bool
    {
        return ($this->request_avs == 'yes') ?? false;
    }

    protected function init_payment_form_instance() : DirectPaymentForm
    {
        return new DirectPaymentForm($this);
    }

    public function add_payment_gateway_transaction_data($order, $response)
    {
        $order->add_meta_data(
            Plugin::CREDIT_CARD_GATEWAY_ID_DIRECT.'_transaction_identifier',
            $response->getResponse()->TransactionIdentifier ?? null,
            true
        );
        $order->add_meta_data(
            Plugin::CREDIT_CARD_GATEWAY_ID_DIRECT.'_risk_management',
            json_encode(
                $response->getResponse()->getRequest()->RiskManagement,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ) ?? null,
            true
        );
        $order->save_meta_data();
    }

    /**
     * May be needed to close off order after partial capture
     *
     * @param $order
     * @param $response
     *
     * @return bool
     */
    protected function maybe_void_instead_of_refund($order, $response) : bool
    {

        if (!$response->transaction_approved()) {
            $actual_refund = $order->get_total() - $this->get_order_meta($order, 'capture_total') ;
            $this->add_order_meta($order, 'refund_amount', $actual_refund);
            $order->save_meta_data();
            $order->refund->amount = $actual_refund;
            $this->add_refund_order_note($order, $response);
            $order->set_status('Processing');
            $order->save();

            // set this as equal to allow a void to be processed
            // Skyverge code does not allow partial void to be processed.
            $order->refund->amount = $order->get_total() ;

            return true;
        }

        return false;
    }

    /**
     * @throws Framework\SV_WC_API_Exception
     * @throws Framework\SV_WC_Payment_Gateway_Exception
     * @throws Framework\SV_WC_Plugin_Exception
     * @throws \ReflectionException
     */
    protected function do_credit_card_transaction($order, $response = null)
    {
        if (is_null($response)) {
            if (is_null($this->handled_response)) {
                $order->add_meta_data(
                    Plugin::CREDIT_CARD_GATEWAY_ID_DIRECT."_last_four",
                    substr($order->payment->account_number, -4),
                    true
                );

                $order->add_meta_data(
                    Plugin::CREDIT_CARD_GATEWAY_ID_DIRECT."_bin",
                    substr($order->payment->account_number, 0, 6),
                    true
                );

                $order->save_meta_data();
                $response = $this->perform_credit_card_charge($order) ?
                    $this->get_api()->credit_card_charge($order) : $this->get_api()->credit_card_authorization($order);
            } else {
                $response = $this->handled_response;

                $last_four = $order->get_meta(Plugin::CREDIT_CARD_GATEWAY_ID_DIRECT."_last_four", true);
                $bin = $order->get_meta(Plugin::CREDIT_CARD_GATEWAY_ID_DIRECT."_bin", true);

                $order->payment->account_number = $last_four;
                $order->payment->card_type = $response->getResponse()->CardBrand ?? null;

                $order->delete_meta_data(Plugin::CREDIT_CARD_GATEWAY_ID_DIRECT."_last_four");
                $order->delete_meta_data(Plugin::CREDIT_CARD_GATEWAY_ID_DIRECT."_bin");

                if (strlen($bin) > 0) {
                    $binlistdata = file_get_contents('https://lookup.binlist.net/'.$bin);
                    $order->add_meta_data($this->id.'_bin_info', utf8_encode($binlistdata), true);
                }

                if ($this->ipgeolocation_api != null) {
                    $ip = get_post_meta($order->get_id(), '_customer_ip_address', true);

                    $location_data = file_get_contents(
                        'https://api.ipgeolocation.io/ipgeo?apiKey='.$this->ipgeolocation_api.'&ip='.$ip
                    );

                    $order->add_meta_data($this->id.'_ip_geolocation', utf8_encode($location_data), true);
                }
            }
        }

        return parent::do_credit_card_transaction($order, $response);
    }
}
