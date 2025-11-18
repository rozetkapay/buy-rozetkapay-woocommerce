<?php
/**
 * Plugin Name: WooCommerce RozetkaPay Payparts
 * Plugin URI: https://rozetkapay.com/
 * Description: Інтеграція оплати частинами RozetkaPay для WooCommerce
 * Version: 2.0.3
 * Author: RozetkaPay
 * Author URI: https://rozetkapay.com/
 * Text Domain: rp-payparts
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.7
 * WC requires at least: 5.0
 * WC tested up to: 9.3
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Network: false
 * Requires PHP: 7.4
 */

// HPOS compatibility
add_action( 'before_woocommerce_init', function() {
    if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__,
            true
        );
    }
} );


if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Bootstrap guard to prevent multiple initializations
if ( defined('RP_PAYPARTS_BOOTSTRAPPED') ) {
    return;
}
define('RP_PAYPARTS_BOOTSTRAPPED', true);

// Define plugin constants
define('RP_PAYPARTS_VERSION','2.0.3-fixed');
define( 'RP_PAYPARTS_PLUGIN_FILE', __FILE__ );
define( 'RP_PAYPARTS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'RP_PAYPARTS_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'RP_PAYPARTS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Security check - ensure WooCommerce is active
 */
function rp_payparts_check_woocommerce() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-error"><p>';
            printf(
                /* translators: %s: Plugin name */
                esc_html__( '%s потребує активного WooCommerce для роботи.', 'rp-payparts' ),
                '<strong>RozetkaPay Payparts</strong>'
            );
            echo '</p></div>';
        });
        return false;
    }
    return true;
}

/**
 * Load plugin files and initialize all components
 */
function rp_payparts_init() {
    // Перевіряємо наявність WooCommerce
    if ( ! rp_payparts_check_woocommerce() ) {
        return;
    }
    
    // Список файлів для завантаження
    $files_to_load = [
        'includes/functions.php',               // Глобальні функції
        'includes/class-api.php',               // API для payparts
        'includes/class-gateway.php',           // Gateway для payparts
        'includes/class-standard-api.php',      // API для standard (опційно)
        'includes/class-standard-gateway.php',  // Gateway для standard (опційно)
        'includes/class-email.php',             // Email-сповіщення (опційно)
    ];
    
    // Завантажуємо файли з перевіркою існування
    foreach ( $files_to_load as $file ) {
        $file_path = RP_PAYPARTS_PLUGIN_PATH . $file;
        if ( file_exists( $file_path ) ) {
            require_once $file_path;
            error_log( 'RozetkaPay: Loaded file - ' . $file );
        } else {
            error_log( 'RozetkaPay: File not found - ' . $file );
            
            // Показуємо помилку тільки для критично важливих файлів
            if ( in_array( $file, [ 'includes/functions.php', 'includes/class-gateway.php' ], true ) ) {
                add_action( 'admin_notices', function() use ( $file ) {
                    echo '<div class="notice notice-error"><p>';
                    printf(
                        /* translators: %s: File name */
                        esc_html__( 'RozetkaPay Payparts: Критичний файл не знайдено - %s', 'rp-payparts' ),
                        esc_html( $file )
                    );
                    echo '</p></div>';
                });
            }
        }
    }
    
    // Реєструємо payment gateways у WooCommerce
    add_filter( 'woocommerce_payment_gateways', function( $gateways ) {
        // Додаємо Payparts gateway
        if ( class_exists( 'RP_Payparts_Gateway' ) ) {
            $gateways[] = 'RP_Payparts_Gateway';
            error_log( 'RozetkaPay: RP_Payparts_Gateway added to WooCommerce' );
        }
        
        // Додаємо Standard gateway (якщо доступний)
        if ( class_exists( 'RP_Standard_Gateway' ) ) {
            $gateways[] = 'RP_Standard_Gateway';
            error_log( 'RozetkaPay: RP_Standard_Gateway added to WooCommerce' );
        }
        
        return $gateways;
    });
    
    // Завантажуємо адмін-панель (якщо в адмінці)
    if ( is_admin() ) {
        $admin_file = RP_PAYPARTS_PLUGIN_PATH . 'admin/class-admin.php';
        if ( file_exists( $admin_file ) ) {
            require_once $admin_file;
            error_log( 'RozetkaPay: Admin class loaded' );
        }
    }
    
    // Завантажуємо менеджер ресурсів (CSS/JS)
    $assets_file = RP_PAYPARTS_PLUGIN_PATH . 'includes/class-assets-manager.php';
    if ( file_exists( $assets_file ) ) {
        require_once $assets_file;
        if ( class_exists( 'RP_Payparts_Assets_Manager' ) ) {
            new RP_Payparts_Assets_Manager();
            error_log( 'RozetkaPay: Assets manager loaded' );
        }
    }
    
    error_log( 'RozetkaPay: Plugin initialization completed successfully' );
}

/**
 * Plugin activation hook
 */
function rp_payparts_activate() {
    // Перевіряємо наявність WooCommerce
    if ( ! class_exists( 'WooCommerce' ) ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        wp_die(
            esc_html__( 'RozetkaPay Payparts потребує активного WooCommerce для роботи.', 'rp-payparts' ),
            esc_html__( 'Помилка активації плагіна', 'rp-payparts' ),
            array( 'back_link' => true )
        );
    }
    
    // Перевіряємо версію PHP
    if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        wp_die(
            esc_html__( 'RozetkaPay Payparts потребує PHP 7.4 або вище.', 'rp-payparts' ),
            esc_html__( 'Помилка активації плагіна', 'rp-payparts' ),
            array( 'back_link' => true )
        );
    }
    
    // Створюємо необхідні таблиці БД (якщо потрібно)
    rp_payparts_create_tables();
    
    // Встановлюємо налаштування за замовчуванням
    rp_payparts_set_default_options();
    
    // Очищаємо кеш
    if ( function_exists( 'rp_payparts_clear_cache' ) ) {
        rp_payparts_clear_cache();
    }
    
    // Примусово оновлюємо rewrite rules
    flush_rewrite_rules();
    
    // Логуємо активацію
    error_log( 'RozetkaPay: Plugin activated successfully' );
    
    // Показуємо сповіщення про активацію
    set_transient( 'rp_payparts_activation_notice', true, 30 );
}

/**
 * Plugin deactivation hook
 */
function rp_payparts_deactivate() {
    // Зупиняємо заплановані задачі
    wp_clear_scheduled_hook( 'rp_payparts_cleanup_transients' );
    wp_clear_scheduled_hook( 'rp_rozetkapay_status_check' );
    wp_clear_scheduled_hook( 'rp_rozetkapay_payparts_status_check' );
    
    // Очищаємо кеш
    if ( function_exists( 'rp_payparts_clear_cache' ) ) {
        rp_payparts_clear_cache();
    }
    
    // Очищаємо rewrite rules
    flush_rewrite_rules();
    
    // Логуємо деактивацію
    error_log( 'RozetkaPay: Plugin deactivated' );
}

/**
 * Plugin uninstall hook
 */
function rp_payparts_uninstall() {
    // Видаляємо налаштування плагіна
    delete_option( 'woocommerce_rp_payparts_settings' );
    delete_option( 'woocommerce_rp_standard_settings' );
    
    // Очищаємо всі transients
    if ( function_exists( 'rp_payparts_cleanup_transients' ) ) {
        rp_payparts_cleanup_transients();
    }
    
    // Видаляємо кастомні таблиці БД (якщо були створені)
    rp_payparts_remove_tables();
    
    // Логуємо видалення
    error_log( 'RozetkaPay: Plugin uninstalled completely' );
}

/**
 * Create database tables if needed
 */
function rp_payparts_create_tables() {
    // У поточній версії використовуємо meta-поля WooCommerce
    // У майбутніх версіях тут можна створити кастомні таблиці
}

/**
 * Set default plugin options
 */
function rp_payparts_set_default_options() {
    // Налаштування за замовчуванням для Payparts gateway
    $default_payparts_settings = array(
        'enabled' => 'no',
        'test_mode' => 'no',
        'title' => __( 'Оплата частинами', 'rp-payparts' ),
        'description' => __( 'Оберіть зручний план оплати частинами нижче.', 'rp-payparts' ),
        'display_style' => 'slider',
        'debug_mode' => 'yes', // Вмикаємо debug за замовчуванням
        'min_amount' => '0',
        'max_amount' => '0',
        'allowed_banks' => '',
        'allowed_terms' => '',
        'enable_status_polling' => 'yes',
    );
    
    // Налаштування за замовчуванням для Standard gateway
    $default_standard_settings = array(
        'enabled' => 'no',
        'test_mode' => 'no',
        'title' => __( 'RozetkaPay', 'rp-payparts' ),
        'description' => __( 'Оплата банківською карткою через RozetkaPay.', 'rp-payparts' ),
        'two_step_payment' => 'no',
        'debug_mode' => 'yes', // Вмикаємо debug за замовчуванням
        'enable_status_polling' => 'yes',
    );
    
    // Оновлюємо налаштування, якщо вони не існують
    $current_payparts_settings = get_option( 'woocommerce_rp_payparts_settings', array() );
    if ( empty( $current_payparts_settings ) ) {
        update_option( 'woocommerce_rp_payparts_settings', $default_payparts_settings );
        error_log( 'RozetkaPay: Set default Payparts settings' );
    }
    
    $current_standard_settings = get_option( 'woocommerce_rp_standard_settings', array() );
    if ( empty( $current_standard_settings ) ) {
        update_option( 'woocommerce_rp_standard_settings', $default_standard_settings );
        error_log( 'RozetkaPay: Set default Standard settings' );
    }
}

/**
 * Remove custom database tables
 */
function rp_payparts_remove_tables() {
    // Видаляємо кастомні таблиці, якщо вони були створені в майбутніх версіях
}

/**
 * Schedule cleanup tasks
 */
function rp_payparts_schedule_cleanup() {
    if ( ! wp_next_scheduled( 'rp_payparts_cleanup_transients' ) ) {
        wp_schedule_event( time(), 'daily', 'rp_payparts_cleanup_transients' );
    }
}

/**
 * Add plugin action links on plugins page
 */
function rp_payparts_plugin_action_links( $links ) {
    $settings_links = array(
        '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=rp_payparts' ) ) . '">' . 
            esc_html__( 'Оплата частинами', 'rp-payparts' ) . '</a>',
        '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=rp_standard' ) ) . '">' . 
            esc_html__( 'Картки', 'rp-payparts' ) . '</a>',
    );
    
    return array_merge( $settings_links, $links );
}

/**
 * Load plugin textdomain for translations
 */
function rp_payparts_load_textdomain() {
    load_plugin_textdomain( 'rp-payparts', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}

/**
 * Show activation notice
 */
function rp_payparts_activation_notice() {
    if ( get_transient( 'rp_payparts_activation_notice' ) ) {
        delete_transient( 'rp_payparts_activation_notice' );
        
        echo '<div class="notice notice-success is-dismissible">';
        echo '<p><strong>' . esc_html__( 'RozetkaPay Payparts активовано!', 'rp-payparts' ) . '</strong></p>';
        echo '<p>' . sprintf( 
            esc_html__( 'Налаштуйте методи оплати: %s або %s', 'rp-payparts' ),
            '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=rp_payparts' ) ) . '">' . esc_html__( 'Оплата частинами', 'rp-payparts' ) . '</a>',
            '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=rp_standard' ) ) . '">' . esc_html__( 'Банківські картки', 'rp-payparts' ) . '</a>'
        ) . '</p>';
        echo '</div>';
    }
}

/**
 * Основні hooks для ініціалізації плагіна
 */
// Ініціалізуємо плагін після завантаження всіх плагінів
add_action( 'plugins_loaded', 'rp_payparts_init', 11 );

// Стандартні WordPress hooks
add_action( 'init', 'rp_payparts_schedule_cleanup' );
add_action( 'plugins_loaded', 'rp_payparts_load_textdomain' );
add_action( 'admin_notices', 'rp_payparts_activation_notice' );

// Реєструємо hooks активації/деактивації/видалення
register_activation_hook( __FILE__, 'rp_payparts_activate' );
register_deactivation_hook( __FILE__, 'rp_payparts_deactivate' );
register_uninstall_hook( __FILE__, 'rp_payparts_uninstall' );

// Додаємо посилання на сторінці плагінів
add_filter( 'plugin_action_links_' . RP_PAYPARTS_PLUGIN_BASENAME, 'rp_payparts_plugin_action_links' );

// Реєструємо cleanup-функцію
add_action( 'rp_payparts_cleanup_transients', 'rp_payparts_cleanup_transients' );

/**
 * === ОНОВЛЕНИЙ CRON — опитування статусу платежів RozetkaPay Standard (кожні 10 хвилин) ===
 */
add_filter( 'cron_schedules', function( $schedules ) {
    if ( ! isset( $schedules['every_ten_minutes'] ) ) {
        $schedules['every_ten_minutes'] = array(
            'interval' => 600, // 10 хвилин
            'display'  => __( 'Every 10 minutes (RozetkaPay)', 'rp-payparts' ),
        );
    }
    return $schedules;
} );

register_activation_hook( __FILE__, function () {
    if ( ! wp_next_scheduled( 'rp_rozetkapay_status_check' ) ) {
        wp_schedule_event( time() + 300, 'every_ten_minutes', 'rp_rozetkapay_status_check' );
    }
    if ( ! wp_next_scheduled( 'rp_rozetkapay_payparts_status_check' ) ) {
        wp_schedule_event( time() + 360, 'every_ten_minutes', 'rp_rozetkapay_payparts_status_check' );
    }
} );

register_deactivation_hook( __FILE__, function () {
    $timestamp = wp_next_scheduled( 'rp_rozetkapay_status_check' );
    if ( $timestamp ) {
        wp_unschedule_event( $timestamp, 'rp_rozetkapay_status_check' );
    }
    $timestamp2 = wp_next_scheduled( 'rp_rozetkapay_payparts_status_check' );
    if ( $timestamp2 ) {
        wp_unschedule_event( $timestamp2, 'rp_rozetkapay_payparts_status_check' );
    }
} );

/**
 * ✅ ОНОВЛЕНИЙ CRON для Standard-платежів з підтримкою повернень
 */
add_action( 'rp_rozetkapay_status_check', function () {
    if ( ! function_exists( 'wc_get_orders' ) ) {
        return;
    }

    // Налаштування Standard-методу
    $std_settings = get_option( 'woocommerce_rp_standard_settings', array() );
    $api_user = isset( $std_settings['api_user'] ) ? $std_settings['api_user'] : '';
    $api_pass = isset( $std_settings['api_pass'] ) ? $std_settings['api_pass'] : '';
    $debug    = isset( $std_settings['debug_mode'] ) && $std_settings['debug_mode'] === 'yes';
    
    // Якщо опитування статусів вимкнено в налаштуваннях — виходимо
    if ( isset( $std_settings['enable_status_polling'] ) && $std_settings['enable_status_polling'] !== 'yes' ) {
        if ( $debug ) error_log( 'RozetkaPay CRON: опитування статусів вимкнено налаштуванням.' );
        return;
    }

    if ( empty( $api_user ) || empty( $api_pass ) ) {
        if ( $debug ) error_log( 'RozetkaPay CRON: пропуск — немає API-креденшіалів для rp_standard.' );
        return;
    }

    if ( ! class_exists( 'RP_Standard_API' ) ) {
        // Спроба підключити клас напряму (на випадок порядку завантаження)
        if ( file_exists( __DIR__ . '/includes/class-standard-api.php' ) ) {
            require_once __DIR__ . '/includes/class-standard-api.php';
        }
    }
    if ( ! class_exists( 'RP_Standard_API' ) ) {
        if ( $debug ) error_log( 'RozetkaPay CRON: RP_Standard_API недоступний.' );
        return;
    }

    $api = new RP_Standard_API( $api_user, $api_pass );

    $page     = 1;
    $per_page = 50;
    $processed = 0;

    do {
        $query_args = array(
            'status'         => array( 'pending', 'on-hold', 'processing' ),
            'payment_method' => 'rp_standard',
            'limit'          => $per_page,
            'paged'          => $page,
            'return'         => 'objects',
            'orderby'        => 'date',
            'order'          => 'DESC',
        );

        $orders = wc_get_orders( $query_args );

        if ( empty( $orders ) ) {
            break;
        }

        foreach ( $orders as $order ) {
            $external_id = $order->get_meta( '_rp_reference', true ) ?: ( method_exists( $order, 'get_order_number' ) ? (string) $order->get_order_number() : (string) $order->get_id() );
            
            if ( empty( $external_id ) ) {
                continue;
            }

            // Перевіряємо, чи не обробляли вже повернення для цього замовлення
            if ( $order->get_meta( '_rp_refund_processed', true ) ) {
                continue;
            }

            if ( $debug ) {
                error_log( sprintf( 'RozetkaPay CRON: перевірка статусу замовлення #%s (external_id=%s)', $order->get_id(), $external_id ) );
            }

            // ✅ ОНОВЛЕНО: Отримуємо повну інформацію про платіж, включаючи повернення
            $status_data = $api->get_payment_status( $external_id );
            if ( is_wp_error( $status_data ) ) {
                if ( $debug ) error_log( 'RozetkaPay CRON: помилка статусу — ' . $status_data->get_error_message() );
                continue;
            }

            if ( $debug ) {
                error_log( 'RozetkaPay CRON: структура відповіді: ' . json_encode( array_keys( is_array( $status_data ) ? $status_data : array() ) ) );
            }

            // ✅ НОВЕ: Перевіряємо інформацію про повернення у відповіді
            $refund_amount = 0;
            $has_refund = false;
            
            // Перевіряємо різні можливі поля для повернень
            if ( isset( $status_data['refund_amount'] ) && floatval( $status_data['refund_amount'] ) > 0 ) {
                $refund_amount = floatval( $status_data['refund_amount'] );
                $has_refund = true;
            } elseif ( isset( $status_data['refunded'] ) && intval( $status_data['refunded'] ) === 1 ) {
                $has_refund = true;
                // Якщо сума повернення не вказана, повертаємо повну суму замовлення
                $refund_amount = isset( $status_data['amount'] ) ? floatval( $status_data['amount'] ) : floatval( $order->get_total() );
            } elseif ( isset( $status_data['refunds'] ) && is_array( $status_data['refunds'] ) && ! empty( $status_data['refunds'] ) ) {
                // Аналізуємо масив повернень
                foreach ( $status_data['refunds'] as $refund ) {
                    if ( is_array( $refund ) ) {
                        $refund_status = isset( $refund['status'] ) ? strtolower( $refund['status'] ) : '';
                        if ( $refund_status === 'success' || $refund_status === 'completed' ) {
                            $refund_amount += isset( $refund['amount'] ) ? floatval( $refund['amount'] ) : 0;
                            $has_refund = true;
                        }
                    }
                }
            }

            // ✅ НОВЕ: Обробляємо знайдені повернення
            if ( $has_refund && $refund_amount > 0 ) {
                if ( $debug ) {
                    error_log( sprintf( 'RozetkaPay CRON: ✅ ЗНАЙДЕНО ПОВЕРНЕННЯ для замовлення #%s, сума=%s', $order->get_id(), $refund_amount ) );
                }
                
                rp_standard_process_refund( $order, $refund_amount, $debug );
                
            } else {
                // Обробляємо основний статус платежу лише якщо немає повернення
                $status = '';
                if ( is_array( $status_data ) ) {
                    $status = isset( $status_data['status'] ) ? strtolower( $status_data['status'] ) : '';
                }

                if ( $debug ) {
                    error_log( 'RozetkaPay CRON: статус платежу = ' . $status );
                }

                switch ( $status ) {
                    case 'success':
                    case 'paid':
                    case 'completed':
                        if ( $order->has_status( array( 'pending', 'on-hold', 'processing' ) ) ) {
                            $order->payment_complete();
                            $order->add_order_note( 'RozetkaPay: платіж підтверджено через cron (payment info).' );
                            if ( $debug ) error_log( 'RozetkaPay CRON: замовлення #' . $order->get_id() . ' переведено в paid.' );
                        }
                        break;

                    case 'rejected':
                    case 'failed':
                    case 'canceled':
                    case 'cancelled':
                        if ( ! $order->has_status( 'failed' ) ) {
                            $order->update_status( 'failed', 'RozetkaPay: платіж відхилено через cron (payment info).' );
                            if ( $debug ) error_log( 'RozetkaPay CRON: замовлення #' . $order->get_id() . ' позначено як failed.' );
                        }
                        break;

                    case 'processing':
                    case 'pending':
                    default:
                        // залишаємо як є
                        break;
                }
            }

            $processed++;
        }

        $page++;
        // обмежимо одне спрацювання 300 замовленнями, щоб не зависати
        if ( $processed >= 300 ) {
            break;
        }

    } while ( true );

    if ( $debug ) {
        error_log( 'RozetkaPay CRON: завершено, опрацьовано замовлень: ' . $processed );
    }
} );

/**
 * ✅ НОВА функція для обробки повернень Standard-платежів
 */
function rp_standard_process_refund( $order, $refund_amount, $debug = false ) {
    $order_id = $order->get_id();
    $order_total = floatval( $order->get_total() );
    
    if ( $refund_amount <= 0 ) {
        $refund_amount = $order_total;
    }

    if ( $debug ) {
        error_log( sprintf( 'Standard REFUND: обробка повернення для замовлення #%s, сума=%s з %s', 
            $order_id, $refund_amount, $order_total ) );
    }

    $existing_refunds = $order->get_refunds();
    $total_refunded = 0;
    
    foreach ( $existing_refunds as $existing_refund ) {
        $total_refunded += abs( $existing_refund->get_amount() );
    }
    
    if ( $debug ) {
        error_log( sprintf( 'Standard REFUND: вже повернено=%s, нове повернення=%s', $total_refunded, $refund_amount ) );
    }

    if ( $total_refunded >= $refund_amount ) {
        if ( $debug ) error_log( 'Standard REFUND: повернення вже було оброблено раніше' );
        return;
    }

    $amount_to_refund = $refund_amount - $total_refunded;
    
    if ( $amount_to_refund > 0 ) {
        $refund_id = wc_create_refund( array(
            'amount' => $amount_to_refund,
            'reason' => 'RozetkaPay Standard refund (CRON)',
            'order_id' => $order_id,
            'refund_payment' => false,
            'restock_items' => false,
        ) );

        if ( $refund_id && ! is_wp_error( $refund_id ) ) {
            if ( $debug ) error_log( 'Standard REFUND: створено WC_Refund #' . $refund_id . ' на суму ' . $amount_to_refund );
            
            $order->update_meta_data( '_rp_refund_processed', current_time( 'mysql' ) );
            
            $total_after_refund = $total_refunded + $amount_to_refund;
            if ( $total_after_refund >= $order_total ) {
                $order->update_status( 'refunded', 'RozetkaPay Standard: повне повернення опрацьовано.' );
                if ( $debug ) error_log( 'Standard REFUND: замовлення #' . $order_id . ' переведено в статус refunded' );
            } else {
                $order->add_order_note( sprintf( 'RozetkaPay Standard: часткове повернення %s грн (усього повернено: %s грн)', 
                    $amount_to_refund, $total_after_refund ) );
            }
            
            $order->save();
        } else {
            if ( $debug ) error_log( 'Standard REFUND: помилка створення WC_Refund: ' . ( is_wp_error( $refund_id ) ? $refund_id->get_error_message() : 'unknown error' ) );
        }
    }
}

/**
 * ОНОВЛЕНА CRON-задача для PayParts з правильною обробкою структури API
 */
add_action( 'rp_rozetkapay_payparts_status_check', function () {
    if ( ! function_exists( 'wc_get_orders' ) ) {
        return;
    }

    $pp_settings = get_option( 'woocommerce_rp_payparts_settings', array() );
    $api_user = isset( $pp_settings['api_user'] ) ? $pp_settings['api_user'] : '';
    $api_pass = isset( $pp_settings['api_pass'] ) ? $pp_settings['api_pass'] : '';
    $test_mode = isset( $pp_settings['test_mode'] ) && $pp_settings['test_mode'] === 'yes';
    $debug    = isset( $pp_settings['debug_mode'] ) && $pp_settings['debug_mode'] === 'yes';
    
    if ( isset( $pp_settings['enable_status_polling'] ) && $pp_settings['enable_status_polling'] !== 'yes' ) {
        if ( $debug ) error_log( 'RozetkaPay PayParts CRON: опитування статусів вимкнено налаштуванням.' );
        return;
    }

    if ( empty( $api_user ) || empty( $api_pass ) ) {
        if ( $debug ) error_log( 'RozetkaPay PayParts CRON: пропуск — немає API-креденшіалів.' );
        return;
    }

    if ( ! class_exists( 'RP_Payparts_API' ) ) {
        if ( file_exists( __DIR__ . '/includes/class-api.php' ) ) {
            require_once __DIR__ . '/includes/class-api.php';
        }
    }
    if ( ! class_exists( 'RP_Payparts_API' ) ) {
        if ( $debug ) error_log( 'RozetkaPay PayParts CRON: RP_Payparts_API недоступний.' );
        return;
    }

    $api = new RP_Payparts_API( $api_user, $api_pass, $test_mode );

    $page = 1;
    $per_page = 50;
    $processed = 0;

    do {
        $orders = wc_get_orders( array(
            'status'         => array( 'pending', 'on-hold', 'processing' ),
            'payment_method' => 'rp_payparts',
            'limit'          => $per_page,
            'paged'          => $page,
            'return'         => 'objects',
            'orderby'        => 'date',
            'order'          => 'DESC',
        ) );

        if ( empty( $orders ) ) {
            break;
        }

        foreach ( $orders as $order ) {
            $external_id = $order->get_meta( '_rp_reference', true ) ?: ( method_exists( $order, 'get_order_number' ) ? (string) $order->get_order_number() : (string) $order->get_id() );
            
            if ( empty( $external_id ) ) {
                continue;
            }

            // Перевіряємо, чи не обробляли вже повернення для цього замовлення
            if ( $order->get_meta( '_rp_refund_processed', true ) ) {
                continue;
            }

            if ( $debug ) {
                error_log( sprintf( 'PayParts CRON: перевірка операцій замовлення #%s (external_id=%s)', 
                    $order->get_id(), $external_id ) );
            }

            // Отримуємо всі операції по замовленню
            $operations_data = $api->get_operations_info( $external_id );
            
            if ( is_wp_error( $operations_data ) ) {
                if ( $debug ) error_log( 'PayParts CRON: помилка API — ' . $operations_data->get_error_message() );
                continue;
            }
            
            if ( empty( $operations_data ) || ! is_array( $operations_data ) ) {
                if ( $debug ) error_log( 'PayParts CRON: порожня відповідь API для замовлення #' . $order->get_id() );
                continue;
            }

            if ( $debug ) {
                error_log( 'PayParts CRON: структура відповіді API: ' . print_r( array_keys( $operations_data ), true ) );
            }

            // ОНОВЛЕНО: правильна обробка структури відповіді API
            $total_refunded = isset( $operations_data['amount_refunded'] ) ? floatval( $operations_data['amount_refunded'] ) : 0;
            $is_refunded = isset( $operations_data['refunded'] ) ? intval( $operations_data['refunded'] ) : 0;
            $refund_details = isset( $operations_data['refund_details'] ) && is_array( $operations_data['refund_details'] ) ? $operations_data['refund_details'] : array();

            // Отримуємо основний статус операції
            $main_status = '';
            $main_status_code = '';
            if ( isset( $operations_data['purchase_details'] ) && is_array( $operations_data['purchase_details'] ) ) {
                $purchase = $operations_data['purchase_details'];
                $main_status = isset( $purchase['status'] ) ? strtolower( $purchase['status'] ) : '';
                $main_status_code = isset( $purchase['status_code'] ) ? strtolower( $purchase['status_code'] ) : '';
            }

            if ( $debug ) {
                error_log( sprintf( 'PayParts CRON: замовлення #%s - total_refunded=%s, is_refunded=%s, refund_details_count=%s, main_status=%s, main_status_code=%s', 
                    $order->get_id(), $total_refunded, $is_refunded, count( $refund_details ), $main_status, $main_status_code ) );
            }

            // ОНОВЛЕНО: перевіряємо наявність успішних повернень
            $has_successful_refund = false;
            $successful_refund_amount = 0;

            if ( $is_refunded > 0 && ! empty( $refund_details ) ) {
                foreach ( $refund_details as $refund ) {
                    if ( ! is_array( $refund ) ) {
                        continue;
                    }

                    $refund_status = isset( $refund['status'] ) ? strtolower( $refund['status'] ) : '';
                    $refund_status_code = isset( $refund['status_code'] ) ? strtolower( $refund['status_code'] ) : '';
                    $refund_amount = isset( $refund['amount'] ) ? floatval( $refund['amount'] ) : 0;

                    if ( $debug ) {
                        error_log( sprintf( 'PayParts CRON: операція повернення - status=%s, status_code=%s, amount=%s', 
                            $refund_status, $refund_status_code, $refund_amount ) );
                    }

                    // Перевіряємо успішне повернення
                    if ( ( $refund_status === 'success' && $refund_status_code === 'refund_successful' ) ||
                         ( $refund_status_code === 'refund_successful' ) ) {
                        $has_successful_refund = true;
                        $successful_refund_amount += $refund_amount;
                        
                        if ( $debug ) {
                            error_log( sprintf( 'PayParts CRON: ЗНАЙДЕНО УСПІШНЕ ПОВЕРНЕННЯ для замовлення #%s, сума=%s', 
                                $order->get_id(), $refund_amount ) );
                        }
                    }
                }
            }

            // Обробляємо знайдені повернення
            if ( $has_successful_refund && $successful_refund_amount > 0 ) {
                if ( $debug ) {
                    error_log( sprintf( 'PayParts CRON: Обробляємо повернення замовлення #%s на суму %s', 
                        $order->get_id(), $successful_refund_amount ) );
                }
                
                rp_payparts_process_refund( $order, $successful_refund_amount, $debug );
                
            } else {
                // Обробляємо статус основної операції лише якщо немає повернення
                $final_status = $main_status_code ?: $main_status;
                
                switch ( $final_status ) {
                    case 'approved':
                    case 'success':
                    case 'paid':
                    case 'completed':
                    case 'transaction_successful':
                        if ( $order->has_status( array( 'pending', 'on-hold' ) ) ) {
                            $order->payment_complete();
                            $order->add_order_note( 'RozetkaPay PayParts: платіж підтверджено по cron.' );
                            if ( $debug ) error_log( 'PayParts CRON: замовлення #' . $order->get_id() . ' переведено в paid.' );
                        }
                        break;

                    case 'contract_was_signed_on_client_side':
                    case 'contract_signed':
                        if ( $order->has_status( array( 'pending', 'on-hold' ) ) ) {
                            $order->update_status( 'processing', 'PayParts: контракт підписано клієнтом, очікується оплата.' );
                            if ( $debug ) error_log( 'PayParts CRON: замовлення #' . $order->get_id() . ' переведено в processing (contract signed).' );
                        }
                        break;

                    case 'rejected':
                    case 'declined':
                    case 'failed':
                    case 'canceled':
                    case 'cancelled':
                        if ( ! $order->has_status( array( 'failed', 'cancelled' ) ) ) {
                            $order->update_status( 'failed', 'RozetkaPay PayParts: платіж відхилено по cron.' );
                            if ( $debug ) error_log( 'PayParts CRON: замовлення #' . $order->get_id() . ' позначено як failed.' );
                        }
                        break;

                    case 'processing':
                    case 'pending':
                    case 'waiting_for_payment':
                    default:
                        if ( $debug ) error_log( 'PayParts CRON: замовлення #' . $order->get_id() . ' лишається в поточному статусі (' . $final_status . ').' );
                        break;
                }
            }

            $processed++;
        }

        $page++;
        if ( $processed >= 300 ) {
            break;
        }
    } while ( true );

    if ( $debug ) {
        error_log( 'PayParts CRON: завершено, опрацьовано замовлень: ' . $processed );
    }
} );

/**
 * Функція для обробки повернень PayParts
 */
function rp_payparts_process_refund( $order, $refund_amount, $debug = false ) {
    $order_id = $order->get_id();
    $order_total = floatval( $order->get_total() );
    
    if ( $refund_amount <= 0 ) {
        $refund_amount = $order_total;
    }

    if ( $debug ) {
        error_log( sprintf( 'PayParts REFUND: обробка повернення для замовлення #%s, сума=%s з %s', 
            $order_id, $refund_amount, $order_total ) );
    }

    $existing_refunds = $order->get_refunds();
    $total_refunded = 0;
    
    foreach ( $existing_refunds as $existing_refund ) {
        $total_refunded += abs( $existing_refund->get_amount() );
    }
    
    if ( $debug ) {
        error_log( sprintf( 'PayParts REFUND: вже повернено=%s, нове повернення=%s', $total_refunded, $refund_amount ) );
    }

    if ( $total_refunded >= $refund_amount ) {
        if ( $debug ) error_log( 'PayParts REFUND: повернення вже було оброблено раніше' );
        return;
    }

    $amount_to_refund = $refund_amount - $total_refunded;
    
    if ( $amount_to_refund > 0 ) {
        $refund_id = wc_create_refund( array(
            'amount' => $amount_to_refund,
            'reason' => 'RozetkaPay PayParts refund (CRON)',
            'order_id' => $order_id,
            'refund_payment' => false,
            'restock_items' => false,
        ) );

        if ( $refund_id && ! is_wp_error( $refund_id ) ) {
            if ( $debug ) error_log( 'PayParts REFUND: створено WC_Refund #' . $refund_id . ' на суму ' . $amount_to_refund );
            
            $order->update_meta_data( '_rp_refund_processed', current_time( 'mysql' ) );
            
            $total_after_refund = $total_refunded + $amount_to_refund;
            if ( $total_after_refund >= $order_total ) {
                $order->update_status( 'refunded', 'RozetkaPay PayParts: повне повернення опрацьовано.' );
                if ( $debug ) error_log( 'PayParts REFUND: замовлення #' . $order_id . ' переведено в статус refunded' );
            } else {
                $order->add_order_note( sprintf( 'RozetkaPay PayParts: часткове повернення %s грн (усього повернено: %s грн)', 
                    $amount_to_refund, $total_after_refund ) );
            }
            
            $order->save();
        } else {
            if ( $debug ) error_log( 'PayParts REFUND: помилка створення WC_Refund: ' . ( is_wp_error( $refund_id ) ? $refund_id->get_error_message() : 'unknown error' ) );
        }
    }
}

// При активації — переконуємось, що подія також існує
add_action( 'init', function(){
    if ( ! wp_next_scheduled( 'rp_rozetkapay_payparts_status_check' ) ) {
        wp_schedule_event( time() + 360, 'every_ten_minutes', 'rp_rozetkapay_payparts_status_check' );
    }
} );

/**
 * DEBUG-функції для розробників
 */
// Показати інформацію про зареєстровані hooks (лише для адміністраторів)
add_action( 'wp_footer', function() {
    if ( ! current_user_can( 'manage_options' ) || ! isset( $_GET['debug_rp_hooks'] ) ) {
        return;
    }
    
    global $wp_filter;
    
    echo '<!-- RozetkaPay Debug Information -->';
    echo '<script>console.log("=== RozetkaPay Hooks Debug ===");</script>';
    
    $hooks_to_check = [
        'woocommerce_api_rp_payparts',
        'woocommerce_api_rp_standard',
        'init',
        'plugins_loaded',
        'woocommerce_loaded'
    ];
    
    foreach ( $hooks_to_check as $hook ) {
        if ( isset( $wp_filter[ $hook ] ) ) {
            echo '<script>console.log("✅ Hook registered: ' . esc_js( $hook ) . '");</script>';
            
            // Показуємо кількість callback\'ів для hook\'а
            $callback_count = 0;
            foreach ( $wp_filter[ $hook ]->callbacks as $priority => $callbacks ) {
                $callback_count += count( $callbacks );
            }
            echo '<script>console.log("   📊 Callbacks count: ' . $callback_count . '");</script>';
        } else {
            echo '<script>console.log("❌ Hook NOT registered: ' . esc_js( $hook ) . '");</script>';
        }
    }
    
    // Показуємо URLs для callback\'ів
    echo '<script>console.log("🔗 Callback URLs:");</script>';
    echo '<script>console.log("   Payparts: ' . esc_js( home_url( '/wc-api/rp_payparts' ) ) . '");</script>';
    echo '<script>console.log("   Standard: ' . esc_js( home_url( '/wc-api/rp_standard' ) ) . '");</script>';
    
    // Показуємо інформацію про класи
    echo '<script>console.log("📦 Classes status:");</script>';
    echo '<script>console.log("   RP_Payparts_Gateway: ' . ( class_exists( 'RP_Payparts_Gateway' ) ? 'EXISTS' : 'NOT FOUND' ) . '");</script>';
    echo '<script>console.log("   RP_Standard_Gateway: ' . ( class_exists( 'RP_Standard_Gateway' ) ? 'EXISTS' : 'NOT FOUND' ) . '");</script>';
}, 999 );

// Показати системну інформацію для відлагодження
add_action( 'wp_ajax_rp_payparts_system_info', function() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Access denied' );
    }
    
    $info = array(
        'plugin_version' => RP_PAYPARTS_VERSION,
        'wordpress_version' => get_bloginfo( 'version' ),
        'woocommerce_version' => defined( 'WC_VERSION' ) ? WC_VERSION : 'Not detected',
        'php_version' => PHP_VERSION,
        'server_info' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        'callback_urls' => array(
            'payparts' => home_url( '/wc-api/rp_payparts' ),
            'standard' => home_url( '/wc-api/rp_standard' ),
        ),
        'classes_loaded' => array(
            'RP_Payparts_Gateway' => class_exists( 'RP_Payparts_Gateway' ),
            'RP_Standard_Gateway' => class_exists( 'RP_Standard_Gateway' ),
        ),
        'settings' => array(
            'payparts' => get_option( 'woocommerce_rp_payparts_settings', array() ),
            'standard' => get_option( 'woocommerce_rp_standard_settings', array() ),
        )
    );
    
    wp_send_json( $info );
});

/**
 * Фінальна перевірка перед завершенням завантаження файлу
 */
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
    error_log( 'RozetkaPay: Main plugin file loaded successfully. Version: ' . RP_PAYPARTS_VERSION );
}
?>
