<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods;

class Ideal implements PaymentMethodI
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
            'id' => 'ideal',
            'defaultTitle' => __('iDEAL', 'mollie-payments-for-woocommerce'),
            'settingsDescription' => '',
            'defaultDescription' => __('Select your bank', 'mollie-payments-for-woocommerce'),
            'paymentFields' => true,
            'instructions' => true,
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
        $paymentMethodFormFieds =  [
            'issuers_dropdown_shown' => [
                'title' => __('Show iDEAL banks dropdown', 'mollie-payments-for-woocommerce'),
                'type' => 'checkbox',
                'description' => sprintf(
                    __(
                        'If you disable this, a dropdown with various iDEAL banks
                         will not be shown in the WooCommerce checkout,
                         so users will select a iDEAL bank on the Mollie payment page after checkout.',
                        'mollie-payments-for-woocommerce'
                    ),
                    $this->getProperty('defaultTitle')
                ),
                'default' => 'yes',
            ],
            'issuers_empty_option' => [
                'title' => __('Issuers empty option', 'mollie-payments-for-woocommerce'),
                'type' => 'text',
                'description' => sprintf(
                    __(
                        'This text will be displayed as the first option in the iDEAL issuers drop down,
                         if nothing is entered, "Select your bank" will be shown. Only if the above 
                         \'Show iDEAL banks dropdown\' is enabled.',
                        'mollie-payments-for-woocommerce'
                    ),
                    $this->getProperty('defaultTitle')
                ),
                'default' => 'Select your bank',
            ]
        ];
        return array_merge($generalFormFields, $paymentMethodFormFieds);
    }
}