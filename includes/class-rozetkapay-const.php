<?php
/**
 * Class of configuration constants.
 *
 * @package RozetkaPay Gateway
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class of configuration constants.
 */
final class RozetkaPay_Const {
	public const VERSION                              = '1.0.7';
	public const API_BASE_URL                         = 'https://api.rozetkapay.com/api';
	public const API_REQUEST_TIMEOUT                  = 60;
	public const ID_PAYMENT_GATEWAY                   = 'rozetkapay';
	public const MAX_LOG_ITEMS                        = 50;
	public const ID_BUY_ONE_CLICK                     = 'rozetkapay-one-click';
	public const PAYMENT_MODE                         = 'express_checkout';
	public const PAYMENT_CURRENCIES                   = array( 'UAH' );
	public const HEADER_SIGNATURE                     = 'X-Rozetkapay-Signature';
	public const BILLING_PATRONYM_OPTION_KEY          = '_billing_patronym';
	public const SHIPPING_DELIVERY_TYPE_OPTION_KEY    = '_shipping_delivery_type';
	public const SHIPPING_PROVIDER_OPTION_KEY         = '_shipping_provider';
	public const SHIPPING_WAREHOUSE_NUMBER_OPTION_KEY = '_shipping_warehouse_number';
	public const RECIPIENT_PATRONYM_OPTION_KEY        = '_recipient_patronym';
	public const PAYMENT_OPERATION_TYPE_OPTION_KEY    = '_payment_operation_type';
	public const PRODUCT_DEFAULT_IMAGE_PATH           = 'assets/img/product-placeholder.png';
	public const ORDER_STATUS_CREATED                 = 'rpaycreated';
	public const ORDER_STATUS_POST_PAYMENT            = 'rpaypostpayment';
}
