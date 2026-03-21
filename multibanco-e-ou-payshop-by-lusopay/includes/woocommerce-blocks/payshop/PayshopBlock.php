<?php

namespace Automattic\WooCommerce\Blocks\Payments\Integrations;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Dummy Payments Blocks integration
 *
 * @since 1.0.3
 */

final class LusopayPayshopBlock extends AbstractPaymentMethodType
{
    /**
     * The gateway instance.
     *
     * @var WC_Lusopay_PS
     */
    private $gateway;

    /**
     * Payment method name/id/slug.
     *
     * @var string
     */
    protected $name = 'lusopay_payshop';

    /**
     * Initializes the payment method type.
     */

    public function initialize()
    {
        $this->settings = get_option('woocommerce_lusopay_payshop_settings');
        $gateways = WC()->payment_gateways->payment_gateways();
        if (array_key_exists($this->name, $gateways)) {
            $this->gateway = $gateways[$this->name];
        } else {
            // Log a message if the gateway is not found
            error_log("Payment gateway '{$this->name}' is not registered.");
            $this->gateway = null; // Handle accordingly
        }
    }

    /**
     * Returns if this payment method should be active. If false, the scripts will not be enqueued.
     *
     * @return boolean
     */
    public function is_active()
    {
        return !empty($this->settings['enabled']) && 'yes' === $this->settings['enabled'];
    }

    /**
     * Returns an array of scripts/handles to be registered for this payment method.
     *
     * @return array
     */
    public function get_payment_method_script_handles()
    {
        wp_register_script(
            'wc-lusopay-payshop',
            plugins_url('src/index.js', __FILE__),
            [
                'wc-blocks-registry',
                'wc-settings',
                'wp-element',
                'wp-html-entities',
                'wp-i18n',
            ],
            false,
            true
        );
        return ['wc-lusopay-payshop'];
    }

    /**
     * Returns an array of key=>value pairs of data made available to the payment methods script.
     *
     * @return array
     */
    public function get_payment_method_data()
    {
        return [
            'img' => plugins_url('payshop_icon.png', __FILE__),

            'title' => $this->get_setting('title'),
            'description' => $this->get_setting('description'),
        ];
    }
}
