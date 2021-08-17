<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Buttons\PayPalButton;

use Mollie\WooCommerce\Gateway\PayPal\Mollie_WC_Gateway_PayPal;
use Mollie\WooCommerce\Notice\NoticeInterface;
use Mollie\WooCommerce\Plugin;
use Mollie\WooCommerce\Utils\GatewaySurchargeHandler;
use Psr\Log\LoggerInterface as Logger;
use WC_Data_Exception;

class PayPalAjaxRequests
{
    /**
     * @var Mollie_WC_Gateway_PayPal
     */
    protected $gateway;
    /**
     * @var NoticeInterface
     */
    protected $notice;
    /**
     * @var Logger
     */
    protected $logger;

    /**
     * PayPalAjaxRequests constructor.
     *
     * @param Mollie_WC_Gateway_PayPal $gateway
     */
    public function __construct(Mollie_WC_Gateway_PayPal $gateway, NoticeInterface $notice, Logger $logger)
    {
        $this->gateway = $gateway;
        $this->notice = $notice;
        $this->logger = $logger;
    }

    /**
     * Adds all the Ajax actions to perform the whole workflow
     */
    public function bootstrapAjaxRequest()
    {
        add_action(
            'wp_ajax_' . PropertiesDictionary::CREATE_ORDER,
            [$this, 'createWcOrder']
        );
        add_action(
            'wp_ajax_nopriv_' . PropertiesDictionary::CREATE_ORDER,
            [$this, 'createWcOrder']
        );
        add_action(
            'wp_ajax_' . PropertiesDictionary::CREATE_ORDER_CART,
            [$this, 'createWcOrderFromCart']
        );
        add_action(
            'wp_ajax_nopriv_' . PropertiesDictionary::CREATE_ORDER_CART,
            [$this, 'createWcOrderFromCart']
        );
        add_action(
            'wp_ajax_' . PropertiesDictionary::UPDATE_AMOUNT,
            [$this, 'updateAmount']
        );
        add_action(
            'wp_ajax_nopriv_' . PropertiesDictionary::UPDATE_AMOUNT,
            [$this, 'updateAmount']
        );
    }

    /**
     * Creates the order from the product detail page and process the payment
     * On error returns an array of errors to be handled by the script
     * On success returns the status success
     * and the url to redirect the user
     *
     * @throws WC_Data_Exception
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function createWcOrder()
    {
        $payPalRequestDataObject = $this->payPalDataObjectHttp();
        $payPalRequestDataObject->orderData($_POST, 'productDetail');
        if (!$this->isNonceValid($payPalRequestDataObject)) {
            return;
        }

        $order = wc_create_order();
        $order->add_product(
            wc_get_product($payPalRequestDataObject->productId),
            $payPalRequestDataObject->productQuantity
        );

        $surchargeHandler = new GatewaySurchargeHandler();
        $order = $surchargeHandler->addSurchargeFeeProductPage($order, 'mollie_wc_gateway_paypal');

        $orderId = $order->get_id();
        $order->calculate_totals();
        $this->updateOrderPostMeta($orderId, $order);

        $result = $this->processOrderPayment($orderId);

        if (isset($result['result'])
            && 'success' === $result['result']
        ) {
            wp_send_json_success($result);
        } else {
            /* translators: Placeholder 1: Payment method title */
            $message = sprintf(
                __(
                    'Could not create %s payment.',
                    'mollie-payments-for-woocommerce'
                ),
                'PayPal'
            );

            $this->logger->log(\WC_Log_Levels::DEBUG, $message, 'error');
            wp_send_json_error($message);
        }
    }

    /**
     * Creates the order from the cart page and process the payment
     * On error returns an array of errors to be handled by the script
     * On success returns the status success
     * and the url to redirect the user
     *
     * @throws WC_Data_Exception
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function createWcOrderFromCart()
    {
        $payPalRequestDataObject = $this->payPalDataObjectHttp();
        $payPalRequestDataObject->orderData($_POST, 'cart');
        if (!$this->isNonceValid($payPalRequestDataObject)) {
            return;
        }

        list($cart, $order) = $this->createOrderFromCart();
        $orderId = $order->get_id();
        $order->calculate_totals();
        $surchargeHandler = new GatewaySurchargeHandler();
        $order = $surchargeHandler->addSurchargeFeeProductPage($order, 'mollie_wc_gateway_paypal');
        $this->updateOrderPostMeta($orderId, $order);
        $result = $this->processOrderPayment($orderId);
        if (isset($result['result'])
            && 'success' === $result['result']
        ) {
            $cart->empty_cart();
            wp_send_json_success($result);
        } else {
            /* translators: Placeholder 1: Payment method title */
            $message = sprintf(
                __(
                    'Could not create %s payment.',
                    'mollie-payments-for-woocommerce'
                ),
                'PayPal'
            );

            $this->notice->addNotice($message, 'error');
            wp_send_json_error($message);
        }
    }

    public function updateAmount()
    {
        $payPalRequestDataObject = $this->payPalDataObjectHttp();
        $payPalRequestDataObject->orderData($_POST, 'productDetail');

        if (!$this->isNonceValid($payPalRequestDataObject)) {
            wp_send_json_error('no nonce');
        }

        $order = new WCOrderCalculator();
        $order->set_currency(get_woocommerce_currency());
        $order->set_prices_include_tax('yes' === get_option('woocommerce_prices_include_tax'));
        $order->add_product(
            wc_get_product($payPalRequestDataObject->productId),
            $payPalRequestDataObject->productQuantity
        );

        $updatedAmount = $order->calculate_totals();

        wp_send_json_success($updatedAmount);
    }

    /**
     * Data Object to collect and validate all needed data collected
     * through HTTP
     *
     * @return PayPalDataObjectHttp
     */
    protected function PayPalDataObjectHttp(): PayPalDataObjectHttp
    {
        return new PayPalDataObjectHttp();
    }

    /**
     * Update order post meta
     *
     * @param string $orderId
     * @param        $order
     */
    protected function updateOrderPostMeta($orderId, $order)
    {
        update_post_meta($orderId, '_customer_user', get_current_user_id());
        update_post_meta(
            $orderId,
            '_payment_method',
            'mollie_wc_gateway_paypal'
        );
        update_post_meta($orderId, '_payment_method_title', 'PayPal');
        $order->update_status(
            'Processing',
            'PayPal Button order',
            true
        );
    }

    /**
     * Process order payment with PayPal gateway
     *
     * @param int $orderId
     *
     * @return array|string[]
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    protected function processOrderPayment($orderId)
    {
        return $this->gateway->process_payment($orderId);
    }

    /**
     * Handles the order creation in cart page
     *
     * @return array
     * @throws Exception
     */
    protected function createOrderFromCart()
    {
        $cart = WC()->cart;
        $checkout = WC()->checkout();
        $orderId = $checkout->create_order([]);
        $order = wc_get_order($orderId);
        return [$cart, $order];
    }

    /**
     * Checks if the nonce in the data object is valid
     *
     * @param PayPalDataObjectHttp $PayPalRequestDataObject
     *
     * @return bool|int
     */
    protected function isNonceValid(
        PayPalDataObjectHttp $PayPalRequestDataObject
    ) {

        $isNonceValid = wp_verify_nonce(
            $PayPalRequestDataObject->nonce,
            'mollie_PayPal_button'
        );
        return $isNonceValid;
    }
}