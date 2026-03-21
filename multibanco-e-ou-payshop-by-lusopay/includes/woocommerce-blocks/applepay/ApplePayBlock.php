<?php

namespace Automattic\WooCommerce\Blocks\Payments\Integrations;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Lusopay Gateway Integration Checkout Blocks
 */
final class LusopayApplePayBlock extends AbstractPaymentMethodType
{
    /**
     * The gateway instance.
     *
     * @var WC_LUSOPAY_APPLEPAY
     */
    private $gateway;

    /**
     * Payment method name/id/slug.
     *
     * @var string
     */
    protected $name = 'lusopay_applepay';

    /**
     * Initializes the payment method type.
     */
    public function initialize()
    {
        $this->settings = get_option('woocommerce_lusopay_applepay_settings');

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
        return ! empty($this->settings['enabled']) && 'yes' === $this->settings['enabled'];
    }

    /**
     * Returns an array of scripts/handles to be registered for this payment method.
     *
     * @return array
     */
    public function get_payment_method_script_handles()
    {
        wp_register_script(
            'wc-lusopay-applepay',
            plugins_url('src/teste.js', __FILE__),
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

        return [ 'wc-lusopay-applepay' ];
    }

    /**
     * Returns an array of key=>value pairs of data made available to the payment methods script.
     *
     * @return array
     */
    public function get_payment_method_data()
    {

        $nifText = "";
        $description =  $this->get_setting('description');
        $maxInstallments = "";

        // Check the store language
        $locale = get_locale();

   


       

        
        return [
            'img' =>  plugins_url( 'applepay.svg', __FILE__ ),
            'title' => $this->get_setting('title'),
            'description' => $this->get_setting('description'),
        ];
    }

    /**
     * Enqueue the JavaScript file with the proper script handles.
     */
    public function enqueue_scripts()
    {
        parent::enqueue_scripts();
        wp_enqueue_script('wc-lusopay-applepay');
    }
}
