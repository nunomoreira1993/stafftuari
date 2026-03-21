<?php

namespace Automattic\WooCommerce\Blocks\Payments\Integrations;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Dummy Payments Blocks integration
 *
 * @since 1.0.3
 */
final class MbwBlockLusopay extends AbstractPaymentMethodType
{
    /**
     * The gateway instance.
     *
     * @var WC_Lusopay_MBWAY
     */
    private $gateway;

    /**
     * Payment method name/id/slug.
     *
     * @var string
     */
    protected $name = 'lusopay_mbway';

    /**
     * Initializes the payment method type.
     */
    public function initialize()
    {
        $this->settings = get_option('woocommerce_lusopay_mbway_settings');
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
    public function get_payment_method_script_handles() {
		wp_register_script(
			'wc-lusopay-mbway',
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
		return [ 'wc-lusopay-mbway' ];
	}

    /**
     * Returns an array of key=>value pairs of data made available to the payment methods script.
     *
     * @return array
     */
    public function get_payment_method_data()
    {

        $phoneText = "";
        $description = "";

        // Check the store language
        $locale = get_locale();

        if ($locale === 'pt_PT') {
            $phoneText = __('Insira o número do telemóvel associado ao MB Way', 'lusopay-mbway');
            $description = __('Insira o número do telemóvel associado ao MB Way', 'lusopay-mbway');
        } else {
            $phoneText = __('Phone number registered on MB Way', 'lusopay-mbway');
            $description = __('Phone number registered on MB Way', 'lusopay-mbway');
        }


        return [
            'img' =>  plugins_url( 'mbway_icon.jpg', __FILE__ ),
            'title' => $this->get_setting('title'),
            'description' => $this->get_setting('description'),
            'phoneText' => __($phoneText, 'lusopay-mbway'),
        ];
    }
}
