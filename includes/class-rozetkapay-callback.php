<?php
/**
 * RozetkaPay Callback handler class.
 *
 * @package RozetkaPay Gateway
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * RozetkaPay Callback interaction class.
 */
class RozetkaPay_Callback {
	/**
	 * Initialize callback endpoint.
	 */
	public static function init(): void {
		add_action(
			sprintf(
				'woocommerce_api_%s',
				strtolower( RozetkaPay_Helper::get_class_name( self::class ) ),
			),
			array( __CLASS__, 'handle_callback' ),
		);
	}

	/**
	 * Handle the incoming callback from RozetkaPay.
	 */
	public static function handle_callback(): void {
		$data                 = self::extract_request_json_data();
		$parsed_data          = json_decode( $data, true );
		$headers              = getallheaders();
		$api_password         = RozetkaPay_Helper::get_payment_gateway()->get_option( 'password' );
		$original_signature   =
			RozetkaPay_Helper::get_request_header_value( RozetkaPay_Const::HEADER_SIGNATURE ) ?? '';
		// The payments service signing format is not guaranteed, so accept
		// either standard base64 or url-safe base64.
		$calculated_signatures = array(
			self::calculate_signature( $data, $api_password, false ),
			self::calculate_signature( $data, $api_password, true ),
		);
		$calculated_signature  = $calculated_signatures[0];

		// Log the callback data.
		RozetkaPay_Logger::log(
			'callbacks',
			array(
				'raw'    => $data,
				'parsed' => $parsed_data,
			),
			array(
				'headers'              => $headers,
				'original_signature'   => $original_signature,
				'calculated_signature' => $calculated_signature,
			),
		);

		if ( empty( $data ) ) {
			self::send_bad_request_response();
		}

		self::validate_common_data_structure( $parsed_data );

		$data_structure_type = self::detect_data_structure_type( $parsed_data );

		self::verify_signature(
			$calculated_signatures,
			$original_signature,
			$data,
			$parsed_data,
		);

		$order = wc_get_order( (int) $parsed_data['external_id'] );

		if ( ! $order ) {
			status_header( 404 );
			exit( 'Order not found' );
		}

		switch ( $data_structure_type ) {
			case 'payment':
				self::handle_payment_callback( $order, $parsed_data );
				break;

			case 'refund':
				self::handle_refund_payment_callback( $order, $parsed_data );
				break;

			case 'instalment_payment':
				self::handle_instalment_payment_callback( $order, $parsed_data );
				break;

			case 'post_payment':
				self::handle_post_payment_callback( $order, $parsed_data );
				break;

			default:
				self::send_bad_request_response();
		}

		self::set_order_payment_operation_type( $order, $data_structure_type );

		status_header( 200 );
		exit( 'Callback processed' );
	}

	/**
	 * Extract and decode form data from the callback request
	 */
	private static function extract_request_json_data(): ?string {
		$raw_body = file_get_contents( 'php://input' );
		$raw_body = (string) preg_replace( '/^data=/i', '', $raw_body );

		if ( preg_match( '/%[0-9A-Fa-f]{2}/', $raw_body ) ) {
			return urldecode( $raw_body );
		}

		return $raw_body;
	}

	/**
	 * Common validate parsed request body data.
	 *
	 * @param array $data Parsed request body data.
	 */
	private static function validate_common_data_structure( array $data ): void {
		if (
			empty( $data['external_id'] )
			|| ! array_key_exists( 'customer', $data )
		) {
			self::send_bad_request_response();
		}
	}

	/**
	 * Detect data structure type.
	 *
	 * @param array $data Parsed request body data.
	 */
	private static function detect_data_structure_type( array $data ): ?string {
		if (
			! empty( $data['operation'] )
			&& ! empty( $data['details']['status'] )
			&& ! empty( $data['details']['transaction_id'] )
		) {
			if ( 'payment' === $data['operation'] ) {
				return 'payment';
			}

			if (
				'refund' === $data['operation']
				&& ! empty( $data['details']['amount'] )
			) {
				return 'refund';
			}
		}

		if (
			! empty( $data['details']['status'] )
			&& ! empty( $data['details']['transaction_id'] )
		) {
			return 'instalment_payment';
		}

		if (
			array_key_exists( 'purchased', $data )
			&& ! empty( $data['purchase_details'] )
			&& is_array( $data['purchase_details'] )
		) {
			foreach ( $data['purchase_details'] as $item ) {
				if (
					! empty( $item['status_code'] )
					&& 'order_with_postpayment_confirmed' === $item['status_code']
				) {
					return 'post_payment';
				}
			}
		}

		return null;
	}

	/**
	 * Calculate signature based on request data.
	 *
	 * @param string $data      Request body data.
	 * @param string $password  API password.
	 * @param bool   $safe_mode Safe mode for base64 format.
	 */
	private static function calculate_signature( string $data, string $password, bool $safe_mode = false ): string {
        // phpcs:ignore
        $encoded_data = $safe_mode ? RozetkaPay_Helper::encode_safe_base64( $data ) : base64_encode( $data );
		$sha1_data    = sha1( $password . $encoded_data . $password, true );

        // phpcs:ignore
		return $safe_mode ? RozetkaPay_Helper::encode_safe_base64( $sha1_data ) : base64_encode( $sha1_data );
	}

	/**
	 * Verify calculated signature with original signature and log during error.
	 *
	 * @param string[]    $calculated_signatures Candidate calculated signatures.
	 * @param string      $original_signature    Original signature.
	 * @param string|null $data                  Request body data.
	 * @param array|null  $parsed_data           Parsed request body data.
	 */
	private static function verify_signature(
		array $calculated_signatures,
		string $original_signature,
		?string $data,
		?array $parsed_data
	): void {
		// Normalize both sides: drop optional prefixes and base64 padding, then
		// accept the callback if any candidate encoding matches.
		$normalized_original = self::normalize_signature( $original_signature );
		$matched             = false;

		foreach ( $calculated_signatures as $calculated_signature ) {
			if ( hash_equals( self::normalize_signature( $calculated_signature ), $normalized_original ) ) {
				$matched = true;
				break;
			}
		}

		if ( ! $matched ) {
			$message = 'Wrong signature';

			RozetkaPay_Logger::log(
				'errors',
				array(
					'message'              => $message,
					'original_signature'   => $original_signature,
					'calculated_signature' => $calculated_signatures,
				),
				array(
					'data_raw'    => $data,
					'data_parsed' => $parsed_data,
				)
			);

			status_header( 406 );
			exit( esc_html( $message ) );
		}
	}

	/**
	 * Normalize a signature for comparison.
	 *
	 * Strips optional algorithm prefixes (e.g. "sha1=", "signature=") and
	 * trailing base64 padding so safe (base64url) and standard forms match.
	 *
	 * @param string $signature Signature value.
	 */
	private static function normalize_signature( string $signature ): string {
		$signature = str_ireplace( array( 'sha1=', 'signature=' ), '', trim( $signature ) );

		return rtrim( $signature, '=' );
	}

	/**
	 * Exit and send bad request status (400).
	 */
	private static function send_bad_request_response(): void {
		status_header( 400 );
		exit( 'Invalid callback structure' );
	}

	/**
	 * Handle payment callback.
	 *
	 * @param WC_Order $order Order object.
	 * @param array    $data  Parsed request body data.
	 */
	private static function handle_payment_callback( WC_Order $order, array $data ): void {
		self::handle_order_status(
			$order,
			strtolower( trim( $data['operation'] ) ),
			strtolower( trim( $data['details']['status'] ) ),
			$data['details']['transaction_id'] ?? null,
		);

		self::handle_order_billing_data( $order, $data['customer'] );
		self::handle_order_shipping_data( $order, $data['delivery_details'] ?? null );

		self::handle_order_recipient_data(
			$order,
			$data['order_recipient'] ?? $data['customer'] ?? null,
		);
	}

	/**
	 * Handle refund payment callback.
	 *
	 * @param WC_Order $order Order object.
	 * @param array    $data  Parsed request body data.
	 */
	private static function handle_refund_payment_callback( WC_Order $order, array $data ): void {
		$operation_status = strtolower( trim( $data['details']['status'] ) );
		$amount           = ! empty( $data['details']['amount'] ) ? (float) $data['details']['amount'] : 0;
		$reason           = $data['details']['payload'] ?? null;

		if ( 'success' !== $operation_status ) {
			return;
		}

		wc_create_refund(
			array(
				'amount'         => round( $amount, 2 ),
				'reason'         => (string) $reason,
				'order_id'       => $order->get_id(),
				'refund_payment' => false,
			)
		);

		if (
			0.0 === (float) $order->get_remaining_refund_amount()
			&& $order->get_status() !== 'refunded'
		) {
			$order->set_status( 'refunded' );
			$order->save();
		}
	}

	/**
	 * Handle instalment payment callback.
	 *
	 * @param WC_Order $order Order object.
	 * @param array    $data  Parsed request body data.
	 */
	private static function handle_instalment_payment_callback( WC_Order $order, array $data ): void {
		self::handle_order_status(
			$order,
			'payment',
			strtolower( trim( $data['details']['status'] ) ),
			$data['details']['transaction_id'] ?? null,
		);

		self::handle_order_billing_data( $order, $data['customer'] );
		self::handle_order_shipping_data( $order, $data['delivery_details'] ?? null );

		self::handle_order_recipient_data(
			$order,
			$data['order_recipient'] ?? $data['customer'] ?? null,
		);
	}

	/**
	 * Handle post payment callback.
	 *
	 * @param WC_Order $order Order object.
	 * @param array    $data  Parsed request body data.
	 */
	private static function handle_post_payment_callback( WC_Order $order, array $data ): void {
		$process = false;

		if ( true === $data['purchased'] ) {
			foreach ( $data['purchase_details'] as $item ) {
				if (
					! empty( $item['status_code'] )
					&& 'order_with_postpayment_confirmed' === $item['status_code']
					&& ! empty( $item['status'] )
					&& 'success' === $item['status']
				) {
					$process = true;
					break;
				}
			}
		}

		if ( ! $process ) {
			return;
		}

		self::handle_order_status(
			$order,
			'post_payment',
			null,
			null,
		);

		self::handle_order_billing_data( $order, $data['customer'] );

		$dd = $data['delivery_details'] ?? null;

		if ( ! empty( $dd ) ) {
			self::handle_order_shipping_data(
				$order,
				array(
					'city'             => $dd['city']['cityName'] ?? null,
					'street'           => $dd['street']['name'] ?? null,
					'house'            => $dd['house'] ?? null,
					'apartment'        => $dd['apartment'] ?? null,
					'delivery_type'    => $dd['delivery_type'] ?? null,
					'provider'         => $dd['provider'] ?? null,
					'warehouse_number' => $dd['warehouse_number']['name'] ?? null,
				)
			);
		}

		self::handle_order_recipient_data(
			$order,
			$data['order_recipient'] ?? $data['customer'] ?? null,
		);
	}

	/**
	 * Handle order status.
	 *
	 * @param WC_Order    $order            Order object.
	 * @param string      $operation        Operation name.
	 * @param string|null $operation_status Operation status.
	 * @param string|null $transaction_id   Transaction ID.
	 */
	private static function handle_order_status(
		WC_Order $order,
		string $operation,
		?string $operation_status,
		?string $transaction_id
	): void {
		switch ( $operation ) {
			case 'payment':
				if (
					'success' === $operation_status
					&& ! $order->is_paid()
				) {
					$order->payment_complete( $transaction_id ?? '' );
					$order->add_order_note( __( 'Payment completed via RozetkaPay', 'buy-rozetkapay-woocommerce' ) );
				} elseif ( 'failure' === $operation_status && $order->get_status() !== 'failed' ) {
					$order->update_status(
						'failed',
						__( 'Payment failed via RozetkaPay', 'buy-rozetkapay-woocommerce' ),
					);
				}
				break;

			case 'post_payment':
				if ( $order->get_status() !== RozetkaPay_Const::ORDER_STATUS_POST_PAYMENT ) {
					$order->update_status(
						RozetkaPay_Const::ORDER_STATUS_POST_PAYMENT,
						__( 'Post payment via RozetkaPay', 'buy-rozetkapay-woocommerce' ),
					);
				}
				break;

			// todo: at the current moment this not working on the API side.
			case 'cancel':
				if ( 'success' === $operation_status && $order->get_status() !== 'cancelled' ) {
					$order->update_status(
						'cancelled',
						__( 'Payment cancelled via RozetkaPay', 'buy-rozetkapay-woocommerce' ),
					);
				}
				break;
		}

		if ( ! empty( $transaction_id ) && $order->get_transaction_id() !== $transaction_id ) {
			$order->set_transaction_id( $transaction_id );
			$order->save();
		}
	}

	/**
	 * Handle order billing data.
	 *
	 * @param WC_Order   $order         Order object.
	 * @param array|null $customer_data Billing customer data for order.
	 */
	private static function handle_order_billing_data( WC_Order $order, ?array $customer_data ): void {
		if ( empty( $customer_data ) ) {
			return;
		}

		$value = $customer_data['email'] ?? null;
		if ( ! empty( $value ) ) {
			$order->set_billing_email( $value );
		}

		$value = $customer_data['phone'] ?? null;
		if ( ! empty( $value ) ) {
			$order->set_billing_phone( $value );
		}

		$value = $customer_data['first_name'] ?? null;
		if ( ! empty( $value ) ) {
			$order->set_billing_first_name( $value );
		}

		$value = $customer_data['last_name'] ?? null;
		if ( ! empty( $value ) ) {
			$order->set_billing_last_name( $value );
		}

		$value = $customer_data['patronym'] ?? null;
		if ( ! empty( $value ) ) {
			update_post_meta( $order->get_id(), RozetkaPay_Const::BILLING_PATRONYM_OPTION_KEY, $value );
		}

		$order->save();
	}

	/**
	 * Handle order shipping data.
	 *
	 * @param WC_Order   $order         Order object.
	 * @param array|null $shipping_data Order shipping data.
	 */
	private static function handle_order_shipping_data( WC_Order $order, ?array $shipping_data ): void {
		if ( empty( $shipping_data ) ) {
			return;
		}

		$value = $shipping_data['city'] ?? null;
		if ( ! empty( $value ) ) {
			$order->set_shipping_city( $value );
		}

		$address_1 = '';

		$value = $shipping_data['street'] ?? null;
		if ( ! empty( $value ) ) {
			$address_1 .= ! empty( $address_1 ) ? ' ' : '';
			$address_1 .= $value;
		}

		$value = $shipping_data['house'] ?? null;
		if ( ! empty( $value ) ) {
			$address_1 .= ! empty( $address_1 ) ? ' ' : '';
			$address_1 .= $value;
		}

		if ( empty( $address_1 ) ) {
			$address_1 = '-';
		}

		$order->set_shipping_address_1( $address_1 );

		$value = $shipping_data['apartment'] ?? null;
		if ( ! empty( $value ) ) {
			/* translators: apartment value */
			$order->set_shipping_address_2( sprintf( __( 'apartment %s', 'buy-rozetkapay-woocommerce' ), $value ) );
		}

		$value = $shipping_data['delivery_type'] ?? null;
		if ( ! empty( $value ) ) {
			update_post_meta( $order->get_id(), RozetkaPay_Const::SHIPPING_DELIVERY_TYPE_OPTION_KEY, $value );
		}

		$value = $shipping_data['provider'] ?? null;
		if ( ! empty( $value ) ) {
			update_post_meta( $order->get_id(), RozetkaPay_Const::SHIPPING_PROVIDER_OPTION_KEY, $value );
		}

		$value = $shipping_data['warehouse_number'] ?? null;
		if ( ! empty( $value ) ) {
			update_post_meta( $order->get_id(), RozetkaPay_Const::SHIPPING_WAREHOUSE_NUMBER_OPTION_KEY, $value );
		}

		$order->save();
	}

	/**
	 * Handle order recipient data.
	 *
	 * @param WC_Order   $order          Order object.
	 * @param array|null $recipient_data Recipient data for order.
	 */
	private static function handle_order_recipient_data( WC_Order $order, ?array $recipient_data ): void {
		$value = $recipient_data['phone'] ?? null;
		if ( ! empty( $value ) ) {
			$order->set_shipping_phone( $value );
		}

		$value = $recipient_data['first_name'] ?? null;
		if ( ! empty( $value ) ) {
			$order->set_shipping_first_name( $value );
		}

		$value = $recipient_data['last_name'] ?? null;
		if ( ! empty( $value ) ) {
			$order->set_shipping_last_name( $value );
		}

		$value = $recipient_data['patronym'] ?? null;
		if ( ! empty( $value ) ) {
			update_post_meta( $order->get_id(), RozetkaPay_Const::RECIPIENT_PATRONYM_OPTION_KEY, $value );
		}

		$order->save();
	}

	/**
	 * Set operation type value for order object meta.
	 *
	 * @param WC_Order $order          Order object.
	 * @param string   $operation_type Order operation type.
	 */
	private static function set_order_payment_operation_type( WC_Order $order, string $operation_type ): void {
		update_post_meta(
			$order->get_id(),
			RozetkaPay_Const::PAYMENT_OPERATION_TYPE_OPTION_KEY,
			$operation_type,
		);
	}
}
