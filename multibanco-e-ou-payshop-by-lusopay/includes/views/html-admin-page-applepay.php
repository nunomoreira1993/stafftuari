<?php global $woocommerce; ?>
<h3><?php echo esc_attr( $this->method_title ); ?>
    <span style="font-size: 75%;">v.<?php echo esc_attr( WC_Lusopay::VERSION ); ?></span></h3>
<p>
    <b><?php esc_html_e( 'Use Apple Pay com Verifone. Vá até o terminal Verifone e pague diretamente.', 'lusopaygateway' ); ?></b>
</p>
<ul class="lusopaygateway_list">
    <li>
        <?php
        echo __( "Se você ainda não possui conta, cadastre-se no nosso site.", 'lusopaygateway' ) . ' <a href="https://www.verifone.com" target="_blank">https://www.verifone.com/</a>';
        ?>
    </li>
    <li>
        <?php
        echo __( 'Apple Pay com Verifone permite pagamentos rápidos, seguros e sem contato diretamente no terminal.', 'lusopaygateway' );
        ?><br>
        <b><?php echo esc_attr( $this->notify_url ); ?></b>
    </li>
</ul>
<h3><?php echo 'Como funciona'; ?></h3>
<p>
    <b><?php esc_html_e( 'Explicação simples de como pagar com Apple Pay usando Verifone:', 'lusopaygateway' ); ?></b>
</p>
<ul class="lusopaygateway_list">
    <li>
        <?php
        echo __( "<b>1</b> - Esta opção está disponível para todos os comerciantes que utilizam terminais Verifone.", 'lusopaygateway' );
        ?>
    </li>
    <li>
        <?php
        echo __( "<b>2</b> - Selecione Apple Pay como método de pagamento no checkout e aproxime seu dispositivo do terminal Verifone.", 'lusopaygateway');
        ?>
    </li>
    <li>
        <?php
        echo __( "<b>3</b> - O pagamento será processado instantaneamente e você receberá a confirmação no terminal.", 'lusopaygateway');
        ?>
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
