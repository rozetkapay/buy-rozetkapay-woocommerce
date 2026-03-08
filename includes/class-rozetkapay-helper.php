<?php
/**
 * Helper class.
 *
 * @package RozetkaPay Gateway
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Helper class.
 */
class RozetkaPay_Helper {

	/**
	 * Extract "clear" class name
	 *
	 * @param string|object $class_reference Class object or class path.
	 *
	 * @throws RuntimeException Runtime exception.
	 */
	public static function get_class_name( $class_reference ): string {
		if ( is_object( $class_reference ) ) {
			$class_reference = get_class( $class_reference );
		}

		if ( is_string( $class_reference ) ) {
			$path = explode( '\\', $class_reference );

			return end( $path );
		}

		throw new RuntimeException( 'Wrong class type' );
	}

	/**
	 * Get payment gateway name (id).
	 */
	public static function get_payment_gateway(): RozetkaPay_Gateway {
		return WC()->payment_gateways->payment_gateways()[ RozetkaPay_Const::ID_PAYMENT_GATEWAY ];
	}

	/**
	 * Check if the object is on "rozetkapay" payment type.
	 *
	 * @param WC_Order $order Order object.
	 */
	public static function is_rozetkapay_order( WC_Order $order ): bool {
		return $order->get_payment_method() === RozetkaPay_Const::ID_PAYMENT_GATEWAY;
	}

	/**
	 * Generate admin page URL.
	 *
	 * @param string $id    Page slug (id).
	 * @param string $query URL query (GET parameters).
	 */
	public static function generate_admin_page_url( string $id, string $query = '' ): string {
		return admin_url(
			sprintf(
				'admin.php?page=%s-%s%s',
				RozetkaPay_Const::ID_PAYMENT_GATEWAY,
				$id,
				! empty( $query ) ? '&' . $query : '',
			)
		);
	}

	/**
	 * Generate nonce key
	 *
	 * @param string $id      Id.
	 * @param string $postfix Postfix.
	 */
	public static function generate_nonce_key( string $id, string $postfix = '' ): string {
		return sprintf( '%s_%s%s', RozetkaPay_Const::ID_PAYMENT_GATEWAY, $id, $postfix );
	}

	/**
	 * Get request header value.
	 *
	 * @param string $name Request header's name.
	 */
	public static function get_request_header_value( string $name ): ?string {
        // phpcs:ignore
		return $_SERVER[ 'HTTP_' . strtoupper( str_replace( '-', '_', $name ) ) ] ?? null;
	}

	/**
	 * Encode string data to safe base64 format.
	 *
	 * @param string $data Data for encoding.
	 */
	public static function encode_safe_base64( string $data ): string {
        // phpcs:ignore
		return strtr( base64_encode( $data ), '+/', '-_' );
	}
}
