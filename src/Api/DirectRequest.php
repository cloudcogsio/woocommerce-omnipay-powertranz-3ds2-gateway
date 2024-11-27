<?php

namespace Cloudcogs\Woocommerce\Gateway\PowerTranz\Api;

use Cloudcogs\Woocommerce\Gateway\PowerTranz\Direct;
use Cloudcogs\Woocommerce\Gateway\PowerTranz\Plugin;
use Omnipay\Common\Message\AbstractRequest;
use Omnipay\PowerTranz\Gateway;
use Omnipay\PowerTranz\Schema\Address;
use Omnipay\PowerTranz\Schema\ExtendedRequestData;
use Omnipay\PowerTranz\Schema\Source;
use Omnipay\PowerTranz\Schema\ThreeDSecureRequestData;
use Omnipay\PowerTranz\Support\DataHelper;
use Ramsey\Uuid\Uuid;
use SkyVerge\WooCommerce\PluginFramework\v5_15_1 as Framework;
use Omnipay\PowerTranz\Message\AbstractRequest as PowerTranzRequest;

class DirectRequest implements Framework\SV_WC_Payment_Gateway_API_Request
{

    protected ?\WC_Order $order;
    protected Gateway $PowerTranzGateway;

    /** @var Direct $WC_Gateway */
    protected Framework\SV_WC_Payment_Gateway $WC_Gateway;

    /** @var PowerTranzRequest $PowerTranzRequest */
    protected AbstractRequest $PowerTranzRequest;

    protected array $request_data = [];

    public function __construct(
        ?\WC_Order $order = null,
        Framework\SV_WC_Payment_Gateway $WC_Gateway,
        Gateway $PowerTranzGateway
    ) {
        $this->order = $order;
        $this->PowerTranzGateway = $PowerTranzGateway;
        $this->WC_Gateway = $WC_Gateway;
    }

    public function getPowerTranzRequest() : PowerTranzRequest
    {
        return $this->PowerTranzRequest;
    }

    /**
     * @throws \ReflectionException
     */
    protected function get_extended_data() : ExtendedRequestData
    {
        return new ExtendedRequestData([
            'ThreeDSecure' => new ThreeDSecureRequestData([
                'ChallengeWindowSize' => 5,
                'ChallengeIndicator' => "01"
            ]),
            'MerchantResponseUrl' => $this->PowerTranzGateway->getMerchantResponseURL()
        ]);
    }

    /**
     * @throws \ReflectionException
     *
     * //TODO - Add admin option to specify countries that should erase state and zip fields.
     */
    protected function get_billing_address() : Address
    {
        return new Address([
            'FirstName' => $this->get_order()->get_billing_first_name(),
            'LastName' => $this->get_order()->get_billing_last_name(),
            'Line1' => $this->get_order()->get_billing_address_1(),
            'Line2' => $this->get_order()->get_billing_address_2(),
            'City' => $this->get_order()->get_billing_city(),
            'State' => (DataHelper::CountryCode($this->get_order()->get_billing_country()) == 780) ?
                "" : $this->get_order()->get_billing_state(),
            'CountryCode' => DataHelper::CountryCode($this->get_order()->get_billing_country()),
            'PostalCode' => (DataHelper::CountryCode($this->get_order()->get_billing_country()) == 780) ?
                "" : str_replace([" ","-"], "", trim($this->get_order()->get_billing_postcode())),
            'EmailAddress' => $this->get_order()->get_billing_email(),
            'PhoneNumber' => $this->get_order()->get_billing_phone()
        ]);
    }

    /**
     * @throws \ReflectionException
     */
    protected function get_card_details() : Source
    {

        return new Source([
            'CardPan' => $this->get_order()->payment->account_number,
            'CardCvv' => $this->get_order()->payment->csc,
            'CardExpiration' => substr($this->get_order()->payment->exp_year, 0, 2).
                                $this->get_order()->payment->exp_month,
            'CardholderName' => $this->get_order()->get_billing_first_name()." ".
                                $this->get_order()->get_billing_last_name()
        ]);
    }

    protected function generate_order_identifier() : string
    {
        return $this->order->get_order_number()."|".$this->order->get_order_key();
    }

    protected function generate_transaction_identifier() : string
    {
        return Uuid::uuid4();
    }

    public function get_data(): array
    {
        $this->request_data = apply_filters('wc_'.Plugin::PLUGIN_ID.'_api_request_data', $this->request_data, $this);
        $this->request_data = $this->remove_empty_data($this->request_data);

        return $this->request_data;
    }

    public function to_string()
    {
        return print_r($this->get_data(), true);
    }

    public function to_string_safe()
    {
        return $this->to_string();
    }

    public function remove_empty_data(array $array): array
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $cleaned = $this->remove_empty_data($value);
                $array[$key] = $cleaned;
            } else {
                if (empty($value)) {
                    unset($array[$key]);
                }
            }
        }

        return $array;
    }

    public function get_order(): ?\WC_Order
    {
        return $this->order;
    }

    public function get_order_identifier() : string
    {
        return $this->get_order()->get_meta($this->WC_Gateway->id.'_transaction_identifier');
    }

    public function get_method()
    {
    }

    public function get_path()
    {
    }

    public function get_params()
    {
    }
}
