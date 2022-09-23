<?php

namespace Cloudcogs\Woocommerce\Gateway\PowerTranz\Api;

use Omnipay\PowerTranz\Message\AbstractResponse;
use SkyVerge\WooCommerce\PluginFramework\v5_10_13 as Framework;

class DirectResponse implements
    Framework\SV_WC_API_Response,
    Framework\SV_WC_Payment_Gateway_API_Response,
    Framework\SV_WC_Payment_Gateway_API_Authorization_Response
{

    protected AbstractResponse $response;
    const RESPONSE_TYPE = "credit-card";

    public function __construct(AbstractResponse $response)
    {
        $this->response = $response;
    }

    public function getResponse(): AbstractResponse
    {
        return $this->response;
    }

    //*************** SV_WC_API_Response methods

    public function to_string()
    {
        return print_r($this->response->toArray(), true);
    }

    public function to_string_safe()
    {
        return $this->to_string();
    }

    //*************** SV_WC_Payment_Gateway_API_Response methods

    public function transaction_approved(): bool
    {
        return $this->response->isSuccessful();
    }

    public function transaction_held(): bool
    {
        return $this->response->isPending();
    }

    public function get_status_message(): ?string
    {
        return $this->response->getMessage();
    }

    public function get_status_code(): ?string
    {
        return $this->response->getCode();
    }

    public function get_transaction_id(): ?string
    {
        return $this->response->RRN ?? null;
    }

    public function get_payment_type(): string
    {
        return self::RESPONSE_TYPE;
    }

    public function get_user_message(): ?string
    {
        return $this->get_status_message();
    }

    //*************** SV_WC_Payment_Gateway_API_Response methods

    public function get_authorization_code(): ?string
    {
        return $this->response->AuthorizationCode ?? null;
    }

    public function get_avs_result(): ?string
    {
        return !is_null($this->response->RiskManagement ?? null) ?
            $this->response->RiskManagement->AvsResponseCode : null;
    }

    public function get_csc_result(): ?string
    {
        return !is_null($this->response->RiskManagement ?? null) ?
            $this->response->RiskManagement->CvvResponseCode : null;
    }

    public function csc_match(): bool
    {
        return $this->get_csc_result() == 'M';
    }
}
