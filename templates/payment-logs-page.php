<?php
/**
 * Admin page for displaying and managing RozetkaPay logs.
 *
 * @package RozetkaPay Gateway
 *
 * @var string $logs_type
 * @var array $logs
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

?>
<div class="wrap">
	<h1><?php esc_html_e( 'RozetkaPay Logs', 'buy-with-rozetkapay-gateway-for-woocommerce' ); ?></h1>
	<?php

	if (
			isset( $_POST['rozetkapay_clear_logs'] )
			&& check_admin_referer( 'rozetkapay_clear_log_action', 'rozetkapay_clear_log_nonce' )
		) {
		RozetkaPay_Logger::clear_logs( $logs_type );

		echo '<div class="updated notice"><p>'
			. esc_html__( 'Logs are cleared successfully', 'buy-with-rozetkapay-gateway-for-woocommerce' )
			. '</p></div>';
	}

	?>
	<h2><?php echo esc_html( ucfirst( str_replace( '_', ' ', $logs_type ) ) ); ?></h2>
	<form method="post">
		<?php wp_nonce_field( 'rozetkapay_clear_log_action', 'rozetkapay_clear_log_nonce' ); ?>
		<input type="hidden" name="log_type" value="<?php echo esc_html( $logs_type ); ?>" />
		<p>
			<button type="submit" name="rozetkapay_clear_logs" class="button button-secondary">
				<?php esc_html_e( 'Clear Logs', 'buy-with-rozetkapay-gateway-for-woocommerce' ); ?>
			</button>
		</p>
	</form>
	<?php if ( ! empty( $logs ) ) : ?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Timestamp', 'buy-with-rozetkapay-gateway-for-woocommerce' ); ?></th>
					<th><?php esc_html_e( 'Data', 'buy-with-rozetkapay-gateway-for-woocommerce' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php

				foreach ( $logs as $log_entry ) {
					$json_data = wp_json_encode( $log_entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );

					?>
						<tr>
							<td style="white-space: nowrap; font-size: 11px;">
							<?php

								$timestamp = explode( ' ', $log_entry['timestamp'] );

								echo esc_html( $timestamp[0] ?? '' );
								echo '<br />';
								echo esc_html( $timestamp[1] ?? '' );

							?>
							</td>
							<td>
								<pre
									style="padding: 24px; overflow: scroll; background-color: #000; font-size: 11px; color: #CCC;"
								><?php echo esc_html( $json_data ); ?></pre>
							</td>
						</tr>
						<?php
				}

				?>
			</tbody>
		</table>
	<?php else : ?>
		<p><?php esc_html_e( 'No log entries found', 'buy-with-rozetkapay-gateway-for-woocommerce' ); ?></p>
	<?php endif; ?>
</div>
