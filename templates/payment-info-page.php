<?php
/**
 * Admin page for displaying information about payment in the admin side.
 *
 * @package RozetkaPay Gateway
 *
 * @var int $order_id
 * @var string|null $error_message
 * @var string $json_data
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$back_url = admin_url( 'admin.php?page=wc-orders&action=edit&id=' . $order_id );

?>
<div class="wrap">
	<h1 class="wp-heading-inline">
		<?php esc_html_e( 'RozetkaPay payment information for the external order ID', 'buy-rozetkapay-woocommerce' ); ?>:
		<?php echo esc_html( $order_id ); ?>
	</h1>
	<hr class="wp-header-end" style="margin: 4px 0;" />
	<a href="<?php echo esc_url( $back_url ); ?>" class="page-title-action"><?php esc_html_e( 'Back to order view', 'buy-rozetkapay-woocommerce' ); ?></a>
	<hr class="wp-header-end" style="margin: 4px 0;" />
	<?php

	if ( null !== $error_message ) {
		?>
			<p><?php echo esc_html( $error_message ); ?></p>
			<?php
	} else {
		?>
			<pre
				style="padding: 24px; overflow: scroll; background-color: #000; font-size: 11px; color: #CCC;"
			><?php echo esc_html( $json_data ); ?></pre>
			<?php
	}

	?>
</div>
