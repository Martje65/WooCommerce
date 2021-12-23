<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods;

class Belfius implements PaymentMethodI
{
    use CommonPaymentMethodTrait;

    /**
     * @var string[]
     */
    private $config = [];
    /**
     * @var array[]
     */
    private $settings = [];
    /**
     * Ideal constructor.
     */
    public function __construct(PaymentMethodSettingsHandlerI $paymentMethodSettingsHandler)
    {
        $this->config = $this->getConfig();
        $this->settings = $paymentMethodSettingsHandler->getSettings($this);
    }

    private function getConfig(): array
    {
        return [
            'id' => 'belfius',
            'defaultTitle' => __('Belfius Direct Net', 'mollie-payments-for-woocommerce'),
            'settingsDescription' => '',
            'defaultDescription' => '',
            'paymentFields' => false,
            'instructions' => false,
            'supports' => [
                'products',
                'refunds',
            ],
            'filtersOnBuild' => false,
            'confirmationDelayed' => false,
            'SEPA' => true
        ];
    }

    public function getFormFields($generalFormFields): array
    {
        return $generalFormFields;
    }
}