<?php
/**
 * Basic initialization class.
 *
 * @package RozetkaPay Gateway
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Basic initialization class.
 */
final class RozetkaPay_Init {
	/**
	 * General initializing.
	 */
	public static function init(): void {
		if ( ! defined( 'ROZETKAPAY_GATEWAY_PLUGIN_DIR' ) ) {
			return;
		}

		require_once ROZETKAPAY_GATEWAY_PLUGIN_DIR . 'includes/class-rozetkapay-const.php';

		self::init_textdomain();
		self::init_order_statuses();
		self::register_wc_gateway();

		add_action(
			'plugins_loaded',
			function () {
				if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
					return;
				}

				// Require necessary classes.
				require_once ROZETKAPAY_GATEWAY_PLUGIN_DIR . 'includes/class-rozetkapay-helper.php';
				require_once ROZETKAPAY_GATEWAY_PLUGIN_DIR . 'includes/class-rozetkapay-logger.php';
				require_once ROZETKAPAY_GATEWAY_PLUGIN_DIR . 'includes/class-rozetkapay-api.php';
				require_once ROZETKAPAY_GATEWAY_PLUGIN_DIR . 'includes/class-rozetkapay-callback.php';
				require_once ROZETKAPAY_GATEWAY_PLUGIN_DIR . 'includes/class-rozetkapay-gateway.php';
				require_once ROZETKAPAY_GATEWAY_PLUGIN_DIR . 'includes/class-rozetkapay-one-click.php';
				require_once ROZETKAPAY_GATEWAY_PLUGIN_DIR . 'includes/class-rozetkapay-admin-view.php';
				require_once ROZETKAPAY_GATEWAY_PLUGIN_DIR . 'includes/class-rozetkapay-order-payment-action.php';

				// Initialize callback handler.
				RozetkaPay_Callback::init();

				// Initialize one-click integration.
				RozetkaPay_One_Click::init();

				RozetkaPay_Admin_View::init();
				RozetkaPay_Order_Payment_Action::init();
			},
			20
		);
	}

	/**
	 * Creates the logs folder when the plugin is activated.
	 * The folder is created with permissions 0755 and is created recursively if it doesn't exist.
	 *
	 * @global WP_Filesystem_Base $wp_filesystem
	 */
	public static function activate_plugin(): void {
		if ( ! defined( 'ROZETKAPAY_GATEWAY_PLUGIN_DIR' ) ) {
			return;
		}

		global $wp_filesystem;

		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . '/wp-admin/includes/file.php';
			WP_Filesystem();
		}

		$log_dir = ROZETKAPAY_GATEWAY_PLUGIN_DIR . 'logs';

		if ( ! $wp_filesystem->is_dir( $log_dir ) ) {
			$wp_filesystem->mkdir( $log_dir, FS_CHMOD_DIR );
		}
	}

	/**
	 * Initialize languages.
	 */
	private static function init_textdomain(): void {
		add_action(
			'plugins_loaded',
			function () {
                // phpcs:ignore
				load_plugin_textdomain(
					'buy-rozetkapay-woocommerce',
					false,
					dirname( plugin_basename( __FILE__ ) ) . '/../languages',
				);
			}
		);
	}

	/**
	 * Initialize order statuses.
	 */
	private static function init_order_statuses(): void {
		$status_prefix = 'wc-';

		add_action(
			'init',
			function () use ( $status_prefix ) {
				register_post_status(
					$status_prefix . RozetkaPay_Const::ORDER_STATUS_CREATED,
					array(
						'label'                     => __( 'RPay created', 'buy-rozetkapay-woocommerce' ),
						'public'                    => false,
						'exclude_from_search'       => true,
						'show_in_admin_all_list'    => false,
						'show_in_admin_status_list' => true,
						// translators: %s: Number of posts in this status.
						'label_count'               => __( 'RPay created (%s)', 'buy-rozetkapay-woocommerce' ),
					)
				);

				register_post_status(
					$status_prefix . RozetkaPay_Const::ORDER_STATUS_POST_PAYMENT,
					array(
						'label'                     => __( 'RPay post payment', 'buy-rozetkapay-woocommerce' ),
						'public'                    => false,
						'exclude_from_search'       => false,
						'show_in_admin_all_list'    => true,
						'show_in_admin_status_list' => true,
						// translators: %s: Number of posts in this status.
						'label_count'               => __( 'RPay post payment (%s)', 'buy-rozetkapay-woocommerce' ),
					)
				);
			}
		);

		add_filter(
			'wc_order_statuses',
			function ( $order_statuses ) use ( $status_prefix ) {
				$order_statuses[ $status_prefix . RozetkaPay_Const::ORDER_STATUS_CREATED ]
					= __( 'RPay created', 'buy-rozetkapay-woocommerce' );

				$order_statuses[ $status_prefix . RozetkaPay_Const::ORDER_STATUS_POST_PAYMENT ]
					= __( 'RPay post payment', 'buy-rozetkapay-woocommerce' );

				return $order_statuses;
			}
		);

		add_filter(
			'woocommerce_valid_order_statuses_for_payment_complete',
			function ( $statuses ) {
				$statuses[] = RozetkaPay_Const::ORDER_STATUS_CREATED;
				$statuses[] = RozetkaPay_Const::ORDER_STATUS_POST_PAYMENT;

				return $statuses;
			},
			10,
			2,
		);
	}

	/**
	 * Register WooCommerce gateway.
	 */
	private static function register_wc_gateway(): void {
		add_filter(
			'woocommerce_payment_gateways',
			function ( $methods ) {
				$class = RozetkaPay_Helper::get_class_name( RozetkaPay_Gateway::class );

				if ( class_exists( $class ) ) {
					$methods[] = $class;
				}

				return $methods;
			}
		);
	}
}
