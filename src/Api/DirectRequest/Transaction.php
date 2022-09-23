<?php

namespace Cloudcogs\Woocommerce\Gateway\PowerTranz\Api\DirectRequest;

use Cloudcogs\Woocommerce\Gateway\PowerTranz\Api\DirectRequest;
use Omnipay\PowerTranz\Schema\AuthRequest;
use Omnipay\PowerTranz\Schema\CaptureRequest;
use Omnipay\PowerTranz\Schema\RefundRequest;
use Omnipay\PowerTranz\Schema\SaleRequest;
use Omnipay\PowerTranz\Schema\VoidRequest;
use Omnipay\PowerTranz\Support\DataHelper;

class Transaction extends DirectRequest
{

    /**
     * @throws \ReflectionException
     */
    public function create_credit_card_charge()
    {

        $Auth = new SaleRequest([
            'TransactionIdentifier' => $this->generate_transaction_identifier(),
            'OrderIdentifier' => $this->generate_order_identifier(),
            'TotalAmount' => $this->get_order()->payment_total,
            'CurrencyCode' => DataHelper::CurrencyCode($this->get_order()->get_currency()),
            'ThreeDSecure' => $this->WC_Gateway->is_three_ds_enabled(),
            'AddressVerification' => $this->WC_Gateway->is_avs_requested(),
            'Source' => $this->get_card_details(),
            'BillingAddress' => $this->get_billing_address(),
            'ExtendedData' => $this->get_extended_data(),
            'AccountVerification' => true
        ]);

        $this->PowerTranzRequest = $this->PowerTranzGateway->PowerTranzSale($Auth);
    }

    /**
     * @throws \ReflectionException
     */
    public function create_credit_card_authorization()
    {

        $Auth = new AuthRequest([
            'TransactionIdentifier' => $this->generate_transaction_identifier(),
            'OrderIdentifier' => $this->generate_order_identifier(),
            'TotalAmount' => $this->get_order()->payment_total,
            'CurrencyCode' => DataHelper::CurrencyCode($this->get_order()->get_currency()),
            'ThreeDSecure' => $this->WC_Gateway->is_three_ds_enabled(),
            'AddressVerification' => $this->WC_Gateway->is_avs_requested(),
            'Source' => $this->get_card_details(),
            'BillingAddress' => $this->get_billing_address(),
            'ExtendedData' => $this->get_extended_data(),
            'AccountVerification' => true
        ]);

        $this->PowerTranzRequest = $this->PowerTranzGateway->PowerTranzAuth($Auth);
    }

    /**
     * @throws \ReflectionException
     */
    public function create_credit_card_capture()
    {

        $Capture = new CaptureRequest([
            'TotalAmount' => $this->get_order()->capture->amount,
            'TransactionIdentifier' => $this->get_order_identifier()
        ]);

        $this->PowerTranzRequest = $this->PowerTranzGateway->PowerTranzCapture($Capture);
    }

    /**
     * @throws \ReflectionException
     */
    public function create_credit_card_refund()
    {
        $Refund = new RefundRequest([
            'TotalAmount' => $this->get_order()->refund->amount,
            'TransactionIdentifier' => $this->get_order_identifier()
        ]);

        $this->PowerTranzRequest = $this->PowerTranzGateway->PowerTranzRefund($Refund);
    }

    /**
     * @throws \ReflectionException
     */
    public function create_credit_card_void()
    {
        $Void = new VoidRequest([
            'TransactionIdentifier' => $this->get_order_identifier()
        ]);

        $this->PowerTranzRequest = $this->PowerTranzGateway->PowerTranzVoid($Void);
    }
}
