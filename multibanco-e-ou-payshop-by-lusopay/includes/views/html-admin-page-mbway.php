<?php global $woocommerce; ?>
<h3><?php echo esc_attr( $this->method_title ); ?>
			<span style="font-size: 75%;">v.<?php echo esc_attr( WC_Lusopay::VERSION ); ?></span></h3>
		<p>
			<b><?php esc_html_e( 'Follow the instructions to activate MB Way service and callback:', 'lusopaygateway' ); ?></b>
		</p>
		<ul class="lusopaygateway_list">
    <li>
        <?php
        echo __( "Sign up on our website if you haven't yet.", 'lusopaygateway' ) . ' <a href="https://www.lusopay.com" target="_blank">https://www.lusopay.com/</a>';
        ?>
    </li>
    <li>
        <?php
        echo __( 'To activate the Callback (automatically update order status to "processing" or "canceled" depending on payment status), go to the ', 'lusopaygateway' );
        ?><a href="admin.php?page=lusopay-config"><?php esc_html_e('Lusopay Settings', 'lusopaygateway'); ?></a><?php
        echo __( ' page and click "Update Callbacks" to update all payment methods automatically.', 'lusopaygateway' );
        ?>
    </li>
    <li>
        <?php
        echo __('If there is an error updating callbacks through the plugin, you can submit the callback URL manually in your LusoPay customer area under your profile. Copy the URL below:', 'lusopaygateway');
        ?><br>
        <b><?php echo esc_attr( $this->notify_url ); ?></b>
    </li>
  
</ul>

		<hr/>
		<script type="text/javascript">
			jQuery( document ).ready( function () {
				var $secret_key = jQuery( '#woocommerce_lusopaygateway_secret_key' );
				if ( $secret_key.val() === '' ) {
					$secret_key.val( '<?php echo esc_attr( $this->secret_key ); ?>' );
					jQuery( '#woocommerce_lusopaygateway_secret_key_label' ).html( '<?php echo esc_attr( $this->secret_key ); ?>' );
					jQuery( '#mainform' ).submit();
				}
			} );
		</script>
		<table class="form-table">
			<?php
			if ( trim( get_woocommerce_currency() ) === 'EUR' ) {
				$this->generate_settings_html();
			} else {
				?>
				<p>
					<b><?php esc_html_e( 'Error!', 'lusopaygateway' ); ?>
						<?php
						echo __( 'Select the currency "Euro" ', 'lusopaygateway' ) . '<a href="admin.php?page=woocommerce_settings&tab=general">' . __( 'Here', 'lusopaygateway' ) . '</a>.';
						?>
					</b>
				</p>
				<?php
			}
			?>
		</table>
		<style type="text/css">
			.lusopaygateway_list {
				list-style: disc inside;
			}

			.lusopaygateway_list li {
				margin-left: 1.5em;
			}
		</style>
