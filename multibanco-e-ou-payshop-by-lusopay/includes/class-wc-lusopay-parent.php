<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gateway agrupador LUSOPAY
 */
class WC_Lusopay_Group extends WC_Payment_Gateway {

	public function __construct() {
		$this->id                 = 'lusopay_group';
		$this->has_fields         = false;
		$this->method_title       = __( 'Lusopay', 'lusopaygateway' );
		$this->method_description = __( 'Métodos de pagamento fornecidos pela LUSOPAY.', 'lusopaygateway' );
		$this->enabled            = 'yes';
	}

	public function is_available() {
		return true;
	}

	public function payment_fields() {
		return;
	}
}
