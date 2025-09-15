<?php
/**
 * Admin edit order view page JS injections.
 *
 * @package RozetkaPay Gateway
 *
 * @var WC_Order $order
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( $order ) {
	$payment_operation_type =
		get_post_meta( $order->get_id(), RozetkaPay_Const::PAYMENT_OPERATION_TYPE_OPTION_KEY, true );

	if ( 'post_payment' === $payment_operation_type ) {
		?>
		<script type="application/javascript">
			jQuery(document).ready(function ($) {
				const order_meta = $('.woocommerce-order-data__meta');

				if (order_meta.length) {
					const html = order_meta.html();
					const modified_content = html.replace(
						/RozetkaPay\./,
						'RozetkaPay (<?php esc_html_e( 'Payment upon receipt', 'buy-rozetkapay-woocommerce' ); ?>).'
					);

					order_meta.html(modified_content);
				}
			});
		</script>
		<?php
	}
}
