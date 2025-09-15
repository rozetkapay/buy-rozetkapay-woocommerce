<?php
/**
 * Class of order payment actions for admin side.
 *
 * @package RozetkaPay Gateway
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class of order payment actions for admin side.
 */
class RozetkaPay_Order_Payment_Action {
	/**
	 * Initialize order payment action on the admin side.
	 */
	public static function init(): void {
		add_action(
			'admin_menu',
			function () {
				add_submenu_page(
					null,
					__( 'RozetkaPay payment receipt', 'buy-rozetkapay-woocommerce' ),
					null,
					'manage_woocommerce',
					'rozetkapay-payment-receipt',
					array( __CLASS__, 'view_payment_receipt' ),
				);
			}
		);

		add_action(
			'admin_menu',
			function () {
				add_submenu_page(
					null,
					__( 'RozetkaPay resend payment callback', 'buy-rozetkapay-woocommerce' ),
					null,
					'manage_woocommerce',
					'rozetkapay-resend-payment-callback',
					array( __CLASS__, 'resend_payment_callback' ),
				);
			}
		);

		add_action(
			'admin_menu',
			function () {
				add_submenu_page(
					null,
					__( 'RozetkaPay cancel payment', 'buy-rozetkapay-woocommerce' ),
					null,
					'manage_woocommerce',
					'rozetkapay-cancel-payment',
					array( __CLASS__, 'cancel_payment' ),
				);
			}
		);
	}

	/**
	 * View payment receipt action.
	 */
	public static function view_payment_receipt(): void {
		$nonce_key = RozetkaPay_Helper::generate_nonce_key( 'payment-receipt', '_nonce' );

		if (
			! isset( $_GET[ $nonce_key ] )
			|| ! wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_GET[ $nonce_key ] ) ),
				RozetkaPay_Helper::generate_nonce_key( 'payment-receipt', '_action' )
			)
		) {
			wp_die( 'Wrong action nonce' );
		}

		$order_id = (int) $_GET['order_id'] ?? 0;
		$gateway  = RozetkaPay_Helper::get_payment_gateway();

		$response = RozetkaPay_API::get_payment_receipt(
			$order_id,
			$gateway->login,
			$gateway->password,
		);

		$error_message = is_wp_error( $response ) ? $response->get_error_message() : null;

		if ( ! empty( $error_message ) ) {
			self::show_error_message( $error_message );
		} elseif ( ! empty( $response['receipt_url'] ) ) {
            // phpcs:ignore
            wp_redirect( $response['receipt_url'] );
			exit;
		} elseif ( ! empty( $response['message'] ) ) {
			echo '<div class="error"><p>' . esc_html( $response['message'] ) . '</p></div>';
		}

		self::show_back_button( $order_id );
	}

	/**
	 * Resend payment callback action.
	 */
	public static function resend_payment_callback(): void {
		$nonce_key = RozetkaPay_Helper::generate_nonce_key( 'resend-payment-callback', '_nonce' );

		if (
			! isset( $_GET[ $nonce_key ] )
			|| ! wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_GET[ $nonce_key ] ) ),
				RozetkaPay_Helper::generate_nonce_key( 'resend-payment-callback', '_action' )
			)
		) {
			wp_die( 'Wrong action nonce' );
		}

		$order_id = (int) $_GET['order_id'] ?? 0;

		if ( ! isset( $_GET['sent'] ) ) {
			$gateway = RozetkaPay_Helper::get_payment_gateway();

			$response = RozetkaPay_API::resend_payment_callback(
				$order_id,
				$gateway->login,
				$gateway->password,
			);

			$error_message = is_wp_error( $response ) ? $response->get_error_message() : null;

			if ( ! empty( $error_message ) ) {
				self::show_error_message( $error_message );
			} else {
				$id = 'resend-payment-callback';

				wp_safe_redirect(
					RozetkaPay_Helper::generate_admin_page_url(
						$id,
						'order_id=' . $order_id . '&sent=' . ( true === $response ? 'yes' : 'no' )
						. '&' . RozetkaPay_Helper::generate_nonce_key( $id, '_nonce' ) . '='
							. wp_create_nonce( RozetkaPay_Helper::generate_nonce_key( $id, '_action' ) )
					)
				);

				exit;
			}
		} elseif ( 'yes' === $_GET['sent'] ) {
				echo '<div class="updated"><p>'
					. esc_html__( 'Payment callback was successfully resent', 'buy-rozetkapay-woocommerce' )
					. '</p></div>';
		} else {
			echo '<div class="error"><p>'
				. esc_html__( 'Something went wrong', 'buy-rozetkapay-woocommerce' )
				. '</p></div>';
		}

		self::show_back_button( $order_id );
	}

	/**
	 * Temporary empty function (for future functional).
	 */
	public static function cancel_payment(): void {
	}

	/**
	 * Print error message template.
	 *
	 * @param string $message Message text.
	 */
	private static function show_error_message( string $message ): void {
		echo '<div class="error"><p>' . esc_html( $message ) . '</p></div>';
	}

	/**
	 * Print back button template.
	 *
	 * @param int $order_id Order ID.
	 */
	private static function show_back_button( int $order_id ): void {
		$back_url = admin_url( 'admin.php?page=wc-orders&action=edit&id=' . $order_id );

		?>
		<p>
			<a href="<?php echo esc_url( $back_url ); ?>" class="page-title-action">
				<?php echo esc_html__( 'Back to order view', 'buy-rozetkapay-woocommerce' ); ?>
			</a>
		</p>
		<?php
	}
}
