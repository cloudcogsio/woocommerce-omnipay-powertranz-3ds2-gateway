<?php

namespace Cloudcogs\Woocommerce\Gateway\PowerTranz\Api;

use Cloudcogs\Woocommerce\Gateway\PowerTranz\Api\DirectRequest\Transaction;
use Cloudcogs\Woocommerce\Gateway\PowerTranz\Direct;
use Omnipay\PowerTranz\Gateway;
use Omnipay\PowerTranz\Message\Response\AuthResponse;
use Omnipay\PowerTranz\Schema\PaymentResponse;
use SkyVerge\WooCommerce\PluginFramework\v5_15_1 as Framework;

class OmniPayPowerTranz extends Framework\SV_WC_API_Base implements Framework\SV_WC_Payment_Gateway_API
{

    const TYPE_TRANSACTION = 'transaction';

    /** @var Direct */
    protected $WC_Gateway;

    /** @var \WC_Order */
    protected \WC_Order $order;

    /** @var DirectRequest */
    protected $request;

    protected Gateway $PowerTranzGateway;

    public function __construct(Framework\SV_WC_Payment_Gateway $gateway)
    {
        $this->WC_Gateway = $gateway;
        $this->PowerTranzGateway = $this->WC_Gateway->initPowerTranzGateway();
    }

    /**
     * @param $args
     *
     * @inheritDoc
     * @return DirectRequest
     * @throws Framework\SV_WC_API_Exception
     */
    protected function get_new_request($args = array()) : DirectRequest
    {
        $this->order = isset($args['order']) && $args['order'] instanceof \WC_Order ? $args['order'] : null;

        ($this->WC_Gateway->get_environment() == Direct::ENVIRONMENT_SANDBOX) ?
            $this->PowerTranzGateway->setTestMode(true) : $this->PowerTranzGateway->setTestMode(false);

        switch ($args['type']) {
            case self::TYPE_TRANSACTION:
                $this->set_response_handler(DirectResponse::class);
                return new Transaction($this->order, $this->WC_Gateway, $this->PowerTranzGateway);

            default:
                throw new Framework\SV_WC_API_Exception('Invalid request type');
        }
    }

    /**
     * @param $request_uri
     * @param $request_args
     *
     * @inheritDoc
     * @return \Exception|\Omnipay\Common\Message\ResponseInterface
     */
    public function do_remote_request($request_uri, $request_args)
    {
        try {
            $response = $this->request->getPowerTranzRequest()->send();
        } catch (\Exception $e) {
            $response = $e;
        }

        return $response;
    }

    /**
     * @param PaymentResponse $response
     * @param string $response_handler
     *
     * @return DirectResponse
     * @throws Framework\SV_WC_API_Exception
     */
    public function handle_postback_response(
        PaymentResponse $response,
        string $response_handler = DirectResponse::class
    ): DirectResponse {
        $this->set_response_handler($response_handler);
        return $this->handle_response($response);
    }

    /**
     * @param $response
     *
     * @inheritDoc
     * @return DirectResponse
     * @throws Framework\SV_WC_API_Exception
     */
    protected function handle_response($response) : DirectResponse
    {
        // check if response contains exception and convert to framework exception
        if ($response instanceof \Exception) {
            throw new Framework\SV_WC_API_Exception($response->getMessage(), $response->getCode(), $response);
        }

        if ($response instanceof AuthResponse || $response instanceof PaymentResponse) {
            switch ($response->getTransactionType()) {
                case 1: // Auth
                case 2: // Sale
                    if ($response->IsoResponseCode == "SP4") {
                        /**
                         * User browser redirect for 3DS auth flow
                         * @see Direct::gateway_callback() for response capture.
                         * The captured response is passed back to this method
                         */
                        $response->redirect();
                    }
                    break;
            }
        }

        // At this point we should have a final response from the gateway

        $handler_class = $this->get_response_handler();
        $this->response = new $handler_class($response);

        // broadcast request
        $this->broadcast_request();

        return $this->response;
    }

    /**
     * @param \WC_Order $order
     *
     * @inheritDoc
     * @return DirectResponse
     * @throws Framework\SV_WC_API_Exception
     * @throws \ReflectionException
     */
    public function credit_card_authorization(\WC_Order $order) : DirectResponse
    {
        /** @var $request Transaction */
        $request = $this->get_new_request([
            'type' => self::TYPE_TRANSACTION,
            'order' => $order,
        ]);

        $request->create_credit_card_authorization();

        return $this->perform_request($request);
    }

    /**
     * @param \WC_Order $order
     *
     * @inheritDoc
     * @return DirectResponse
     * @throws Framework\SV_WC_API_Exception
     * @throws \ReflectionException
     */
    public function credit_card_charge(\WC_Order $order) : DirectResponse
    {
        /** @var $request Transaction */
        $request = $this->get_new_request([
            'type' => self::TYPE_TRANSACTION,
            'order' => $order,
        ]);

        $request->create_credit_card_charge();

        return $this->perform_request($request);
    }

    /**
     * @param \WC_Order $order
     *
     * @inheritDoc
     * @return DirectResponse
     * @throws Framework\SV_WC_API_Exception
     * @throws \ReflectionException
     */
    public function credit_card_capture(\WC_Order $order) : DirectResponse
    {
        /** @var $request Transaction */
        $request = $this->get_new_request([
            'type' => self::TYPE_TRANSACTION,
            'order' => $order,
        ]);

        $request->create_credit_card_capture();

        return $this->perform_request($request);
    }

    /**
     * @param \WC_Order $order
     *
     * @inheritDoc
     * @return DirectResponse
     * @throws Framework\SV_WC_API_Exception
     * @throws \ReflectionException
     */
    public function refund(\WC_Order $order) : DirectResponse
    {
        /** @var $request Transaction */
        $request = $this->get_new_request([
            'type' => self::TYPE_TRANSACTION,
            'order' => $order,
        ]);

        $request->create_credit_card_refund();

        return $this->perform_request($request);
    }

    /**
     * @param \WC_Order $order
     *
     * @inheritDoc
     * @return DirectResponse
     * @throws Framework\SV_WC_API_Exception
     * @throws \ReflectionException
     */
    public function void(\WC_Order $order) : DirectResponse
    {
        /** @var $request Transaction */
        $request = $this->get_new_request([
            'type' => self::TYPE_TRANSACTION,
            'order' => $order,
        ]);

        $request->create_credit_card_void();

        return $this->perform_request($request);
    }

    /**
     * @inheritDoc
     */
    public function check_debit(\WC_Order $order)
    {
        // TODO: Implement check_debit() method.
    }

    /**
     * @inheritDoc
     */
    public function tokenize_payment_method(\WC_Order $order)
    {
        // TODO: Implement tokenize_payment_method() method.
    }

    /**
     * @inheritDoc
     */
    public function update_tokenized_payment_method(\WC_Order $order)
    {
        // TODO: Implement update_tokenized_payment_method() method.
    }

    /**
     * @inheritDoc
     * //TODO - Add support for tokenization
     */
    public function supports_update_tokenized_payment_method()
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function remove_tokenized_payment_method($token, $customer_id)
    {
        // TODO: Implement remove_tokenized_payment_method() method.
    }

    /**
     * @inheritDoc
     * //TODO - Add support for tokenization
     */
    public function supports_remove_tokenized_payment_method()
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function get_tokenized_payment_methods($customer_id)
    {
        // TODO: Implement get_tokenized_payment_methods() method.
    }

    /**
     * @inheritDoc
     * //TODO - Add support for tokenization
     */
    public function supports_get_tokenized_payment_methods(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function get_order()
    {
        return $this->order;
    }

    /**
     * @inheritDoc
     */
    protected function get_plugin()
    {
        return $this->WC_Gateway->get_plugin();
    }
}
