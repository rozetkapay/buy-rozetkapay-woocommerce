<?php
/**
 * RozetkaPay Payparts Helper Functions - ВИПРАВЛЕНА ВЕРСІЯ
 * Допоміжні функції для плагіна
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'rp_payparts_get_month_declension' ) ) {
    /**
     * Отримати правильне відмінювання слова "місяць"
     * 
     * @param int $number Кількість місяців
     * @return string Правильне відмінювання
     */
    function rp_payparts_get_month_declension( $number ) {
        $cases = array( 2, 0, 1, 1, 1, 2 );
        $titles = array(
            __( 'місяць', 'rp-payparts' ),
            __( 'місяці', 'rp-payparts' ),
            __( 'місяців', 'rp-payparts' )
        );
        
        return $titles[ ( $number % 100 > 4 && $number % 100 < 20 ) ? 2 : $cases[ min( $number % 10, 5 ) ] ];
    }
}

if ( ! function_exists( 'rp_payparts_get_bank_name' ) ) {
    /**
     * Отримати зручну для користувача назву банку за slug
     * 
     * @param string $bank_slug Slug банку
     * @return string Назва банку
     */
    function rp_payparts_get_bank_name( $bank_slug ) {
        // ✅ ОНОВЛЕНИЙ список реальних банків RozetkaPay API
        $bank_names = array(
            'abank' => 'ABank',           // ✅ ДОДАНО
            'monobank' => 'Monobank',
            'privatbank' => 'ПриватБанк',
            'rozetkapay' => 'RozetkaPay',
            'izibank' => 'izibank',
        );
        
        return isset( $bank_names[ $bank_slug ] ) 
            ? $bank_names[ $bank_slug ] 
            : ucfirst( str_replace( array( '-', '_' ), ' ', $bank_slug ) );
    }
}

if ( ! function_exists( 'rp_payparts_get_bank_min_period' ) ) {
    /**
     * Отримати мінімальний період для банку – НОВА ФУНКЦІЯ
     * 
     * @param string $bank_slug Slug банку
     * @return int Мінімальний період у місяцях
     */
    function rp_payparts_get_bank_min_period( $bank_slug ) {
        $bank_min_periods = array(
            'monobank' => 3,     // Monobank мінімум 3 місяці
            'izibank' => 3,      // izibank мінімум 3 місяці  
            'abank' => 2,        // Abank мінімум 2 місяці
            'privatbank' => 2,   // ПриватБанк може від 2
            'rozetkapay' => 2,   // RozetkaPay може від 2
        );
        
        return isset( $bank_min_periods[ $bank_slug ] ) ? $bank_min_periods[ $bank_slug ] : 3;
    }
}

if ( ! function_exists( 'rp_payparts_validate_bank_terms' ) ) {
    /**
     * Валідація списку періодів для банку – НОВА ФУНКЦІЯ
     * 
     * @param string $bank_slug Slug банку
     * @param array $periods Масив періодів
     * @return array Відфільтрований масив періодів
     */
    function rp_payparts_validate_bank_terms( $bank_slug, $periods ) {
        $min_period = rp_payparts_get_bank_min_period( $bank_slug );
        
        return array_filter( $periods, function( $period ) use ( $min_period ) {
            return intval( $period ) >= $min_period && intval( $period ) <= 25;
        } );
    }
}

if ( ! function_exists( 'rp_payparts_format_currency' ) ) {
    /**
     * Форматування валюти
     * 
     * @param float $amount Сума
     * @param string $currency Код валюти
     * @return string Відформатована сума
     */
    function rp_payparts_format_currency( $amount, $currency = 'UAH' ) {
        $formatted = number_format( $amount, 0, ',', ' ' );
        
        switch ( $currency ) {
            case 'UAH':
                return $formatted . ' грн';
            case 'USD':
                return '$' . $formatted;
            case 'EUR':
                return '€' . $formatted;
            default:
                return $formatted . ' ' . $currency;
        }
    }
}

if ( ! function_exists( 'rp_payparts_log' ) ) {
    /**
     * Логування подій плагіна
     * 
     * @param string $message Повідомлення для лога
     * @param string $level Рівень лога (info, warning, error)
     */
    function rp_payparts_log( $message, $level = 'info' ) {
        if ( ! WP_DEBUG_LOG ) {
            return;
        }
        
        $timestamp = current_time( 'Y-m-d H:i:s' );
        $log_message = sprintf( '[%s] [RozetkaPay Payparts] [%s] %s', $timestamp, strtoupper( $level ), $message );
        
        error_log( $log_message );
    }
}

if ( ! function_exists( 'rp_payparts_is_plugin_active' ) ) {
    /**
     * Перевірити, чи активний плагін
     * 
     * @return bool
     */
    function rp_payparts_is_plugin_active() {
        return class_exists( 'RP_Payparts_Gateway' );
    }
}

if ( ! function_exists( 'rp_payparts_get_gateway' ) ) {
    /**
     * Отримати екземпляр gateway
     * 
     * @return RP_Payparts_Gateway|null
     */
    function rp_payparts_get_gateway() {
        if ( ! class_exists( 'WC_Payment_Gateways' ) ) {
            return null;
        }
        
        $gateways = WC()->payment_gateways()->get_available_payment_gateways();
        return isset( $gateways['rp_payparts'] ) ? $gateways['rp_payparts'] : null;
    }
}

if ( ! function_exists( 'rp_get_payment_method_type' ) ) {
    /**
     * Визначити тип методу оплати за замовленням – НОВА ФУНКЦІЯ
     * 
     * @param WC_Order $order Замовлення
     * @return string|null Тип методу ('payparts' або 'standard')
     */
    function rp_get_payment_method_type( $order ) {
        if ( ! $order ) {
            return null;
        }
        
        $payment_method = $order->get_payment_method();
        
        switch ( $payment_method ) {
            case 'rp_payparts':
                return 'payparts';
            case 'rp_standard':
                return 'standard';
            default:
                return null;
        }
    }
}

if ( ! function_exists( 'rp_get_payment_method_title' ) ) {
    /**
     * Отримати назву методу оплати – НОВА ФУНКЦІЯ
     * 
     * @param string $method Тип методу ('payparts' або 'standard')
     * @return string
     */
    function rp_get_payment_method_title( $method ) {
        switch ( $method ) {
            case 'payparts':
                return __( 'RozetkaPay Оплата частинами', 'rp-payparts' );
            case 'standard':
                return __( 'RozetkaPay Банківські картки', 'rp-payparts' );
            default:
                return __( 'RozetkaPay', 'rp-payparts' );
        }
    }
}

if ( ! function_exists( 'rp_payparts_calculate_installment' ) ) {
    /**
     * Розрахувати розмір щомісячного платежу
     * 
     * @param float $total Загальна сума
     * @param int $months Кількість місяців
     * @return float Розмір щомісячного платежу
     */
    function rp_payparts_calculate_installment( $total, $months ) {
        if ( $months <= 0 ) {
            return 0;
        }
        
        return round( $total / $months, 2 );
    }
}

if ( ! function_exists( 'rp_payparts_get_available_banks' ) ) {
    /**
     * Отримати список доступних банків
     * 
     * @return array
     */
    function rp_payparts_get_available_banks() {
        $gateway = rp_payparts_get_gateway();
        
        if ( ! $gateway ) {
            return array();
        }
        
        // ✅ ОНОВЛЕНІ тестові банки (відповідають реальному API)
        if ( $gateway->test_mode ) {
            return array(
                'abank' => 'ABank',           // ✅ ДОДАНО  
                'monobank' => 'Monobank',
                'privatbank' => 'ПриватБанк',
                'rozetkapay' => 'RozetkaPay',
                'izibank' => 'izibank',
            );
        }
        
        // Отримуємо з кешу або API
        $banks = get_transient( 'rp_payparts_banks' );
        if ( false === $banks && method_exists( $gateway, 'get_api_client' ) ) {
            $api = $gateway->get_api_client();
            $banks = $api->fetch_banks();
            
            if ( ! empty( $banks ) && ! is_wp_error( $banks ) ) {
                set_transient( 'rp_payparts_banks', $banks, HOUR_IN_SECONDS );
            }
        }
        
        return is_array( $banks ) ? $banks : array();
    }
}

if ( ! function_exists( 'rp_payparts_clear_cache' ) ) {
    /**
     * Очистити кеш плагіна
     */
    function rp_payparts_clear_cache() {
        delete_transient( 'rp_payparts_banks' );
        delete_transient( 'rozetkapay_payparts_banks' ); // Старий ключ для сумісності
        
        rp_payparts_log( 'Cache cleared' );
    }
}

if ( ! function_exists( 'rp_payparts_get_plugin_version' ) ) {
    /**
     * Отримати версію плагіна
     * 
     * @return string
     */
    function rp_payparts_get_plugin_version() {
        return defined( 'RP_PAYPARTS_VERSION' ) ? RP_PAYPARTS_VERSION : '2.0.2';
    }
}

if ( ! function_exists( 'rp_payparts_is_woocommerce_active' ) ) {
    /**
     * Перевірити, чи активний WooCommerce
     * 
     * @return bool
     */
    function rp_payparts_is_woocommerce_active() {
        return class_exists( 'WooCommerce' );
    }
}

if ( ! function_exists( 'rp_payparts_get_order_payment_data' ) ) {
    /**
     * Отримати метадані замовлення, пов’язані з розстрочкою
     * 
     * @param int $order_id ID замовлення
     * @return array
     */
    function rp_payparts_get_order_payment_data( $order_id ) {
        return array(
            'option' => get_post_meta( $order_id, '_rp_payparts_option', true ),
            'payment_id' => get_post_meta( $order_id, '_rp_payparts_payment_id', true ),
            'payment_url' => get_post_meta( $order_id, '_rp_payparts_payment_url', true ),
            'transaction_id' => get_post_meta( $order_id, '_rp_payparts_transaction_id', true ),
            'bank' => get_post_meta( $order_id, '_rp_payparts_bank', true ),
            'term' => get_post_meta( $order_id, '_rp_payparts_term', true ),
        );
    }
}

if ( ! function_exists( 'rp_payparts_parse_option' ) ) {
    /**
     * Парсинг опції розстрочки – ОНОВЛЕНА ВЕРСІЯ
     * 
     * @param string $option Опція у форматі "bank|term"
     * @return array|null
     */
    function rp_payparts_parse_option( $option ) {
        if ( empty( $option ) || ! strpos( $option, '|' ) ) {
            return null;
        }
        
        $parts = explode( '|', $option );
        if ( count( $parts ) !== 2 ) {
            return null;
        }
        
        $bank_slug = sanitize_text_field( $parts[0] );
        $term = intval( $parts[1] );
        
        if ( empty( $bank_slug ) || $term <= 0 ) {
            return null;
        }
        
        // ✅ Використовуємо нову функцію отримання назви банку
        $bank_name = rp_payparts_get_bank_name( $bank_slug );
        
        return array(
            'bank_slug' => $bank_slug,
            'bank_name' => $bank_name,
            'term' => $term,
            'term_text' => $term . ' ' . rp_payparts_get_month_declension( $term ),
        );
    }
}

if ( ! function_exists( 'rp_payparts_get_order_installment_summary' ) ) {
    /**
     * Отримати зведення по розстрочці для замовлення
     * 
     * @param int $order_id ID замовлення
     * @return array|null
     */
    function rp_payparts_get_order_installment_summary( $order_id ) {
        $option = get_post_meta( $order_id, '_rp_payparts_option', true );
        $parsed = rp_payparts_parse_option( $option );
        
        if ( ! $parsed ) {
            return null;
        }
        
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return null;
        }
        
        $monthly_payment = rp_payparts_calculate_installment( $order->get_total(), $parsed['term'] );
        
        return array_merge( $parsed, array(
            'monthly_payment' => $monthly_payment,
            'monthly_payment_formatted' => rp_payparts_format_currency( $monthly_payment ),
            'total_amount' => $order->get_total(),
            'total_amount_formatted' => $order->get_formatted_order_total(),
        ) );
    }
}

if ( ! function_exists( 'rp_payparts_validate_amount' ) ) {
    /**
     * Валідація суми для розстрочки – ОНОВЛЕНА ВЕРСІЯ
     * 
     * @param float $amount Сума для перевірки
     * @return array Результат валідації
     */
    function rp_payparts_validate_amount( $amount ) {
        $gateway = rp_payparts_get_gateway();
        
        if ( ! $gateway ) {
            return array(
                'valid' => false,
                'message' => __( 'Метод оплати недоступний', 'rp-payparts' )
            );
        }
        
        // ✅ Отримуємо ліміти з реальних даних банків
        $limits = rp_payparts_get_banks_limits();
        
        // Перевірка мінімальної суми
        if ( $limits['min_amount'] > 0 && $amount < $limits['min_amount'] ) {
            return array(
                'valid' => false,
                'message' => sprintf( 
                    __( 'Мінімальна сума для розстрочки: %s', 'rp-payparts' ),
                    rp_payparts_format_currency( $limits['min_amount'] )
                )
            );
        }
        
        // Перевірка максимальної суми
        if ( $limits['max_amount'] > 0 && $amount > $limits['max_amount'] ) {
            return array(
                'valid' => false,
                'message' => sprintf( 
                    __( 'Максимальна сума для розстрочки: %s', 'rp-payparts' ),
                    rp_payparts_format_currency( $limits['max_amount'] )
                )
            );
        }
        
        return array(
            'valid' => true,
            'message' => __( 'Сума підходить для розстрочки', 'rp-payparts' )
        );
    }
}

if ( ! function_exists( 'rp_payparts_get_banks_limits' ) ) {
    /**
     * Отримати мінімальні та максимальні ліміти з усіх банків – НОВА ФУНКЦІЯ
     * 
     * @return array
     */
    function rp_payparts_get_banks_limits() {
        $banks = get_transient( 'rp_payparts_banks' );
        
        if ( false === $banks || ! is_array( $banks ) ) {
            // Резервні ліміти на основі реальних даних API
            return array(
                'min_amount' => 1,      // Monobank має мінімум 1 грн
                'max_amount' => 250000, // Більшість банків до 250 000
            );
        }
        
        $min_amount = PHP_INT_MAX;
        $max_amount = 0;
        
        foreach ( $banks as $bank ) {
            if ( isset( $bank['limits'] ) ) {
                if ( isset( $bank['limits']['min_amount'] ) ) {
                    $min_amount = min( $min_amount, $bank['limits']['min_amount'] );
                }
                if ( isset( $bank['limits']['max_amount'] ) ) {
                    $max_amount = max( $max_amount, $bank['limits']['max_amount'] );
                }
            }
        }
        
        return array(
            'min_amount' => $min_amount === PHP_INT_MAX ? 1 : $min_amount,
            'max_amount' => $max_amount ?: 250000,
        );
    }
}

if ( ! function_exists( 'rp_payparts_get_bank_fee' ) ) {
    /**
     * Отримати комісію банку за певний період – НОВА ФУНКЦІЯ
     * 
     * @param string $bank_slug Slug банку
     * @param int $period Період у місяцях
     * @return float|null Комісія у відсотках або null, якщо не знайдено
     */
    function rp_payparts_get_bank_fee( $bank_slug, $period ) {
        $banks = get_transient( 'rp_payparts_banks' );
        
        if ( false === $banks || ! is_array( $banks ) ) {
            return null;
        }
        
        foreach ( $banks as $bank ) {
            if ( isset( $bank['name'] ) && $bank['name'] === $bank_slug ) {
                if ( isset( $bank['periods'] ) && is_array( $bank['periods'] ) ) {
                    foreach ( $bank['periods'] as $period_data ) {
                        if ( isset( $period_data['period'] ) && intval( $period_data['period'] ) === $period ) {
                            return isset( $period_data['fee'] ) ? floatval( $period_data['fee'] ) : null;
                        }
                    }
                }
                break;
            }
        }
        
        return null;
    }
}

if ( ! function_exists( 'rp_payparts_cleanup_transients' ) ) {
    /**
     * Очищення старих transient'ів
     */
    function rp_payparts_cleanup_transients() {
        global $wpdb;
        
        // Очищаємо прострочені transient'и з нашим префіксом
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->options} 
             WHERE (option_name LIKE %s OR option_name LIKE %s)
             AND option_value < %d",
            '_transient_timeout_rp_payparts_%',
            '_transient_timeout_rozetkapay_payparts_%',
            time()
        ) );
        
        // Очищаємо відповідні transient'и
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE %s 
             OR option_name LIKE %s",
            '_transient_rp_payparts_%',
            '_transient_rozetkapay_payparts_%'
        ) );
    }
}

if ( ! function_exists( 'rp_payparts_debug' ) ) {
    /**
     * Відлагоджувальне логування (лише в debug-режимі)
     * 
     * @param mixed $data Дані для лога
     * @param string $context Контекст
     */
    function rp_payparts_debug( $data, $context = 'debug' ) {
        if ( ! WP_DEBUG || ! WP_DEBUG_LOG ) {
            return;
        }
        
        $gateway = rp_payparts_get_gateway();
        if ( ! $gateway || ! $gateway->debug_mode ) {
            return;
        }
        
        $message = sprintf( '[%s] %s', $context, is_string( $data ) ? $data : print_r( $data, true ) );
        rp_payparts_log( $message, 'debug' );
    }
}

if ( ! function_exists( 'rp_payparts_get_plugin_data' ) ) {
    /**
     * Отримати дані плагіна
     * 
     * @return array
     */
    function rp_payparts_get_plugin_data() {
        if ( ! function_exists( 'get_plugin_data' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        $plugin_file = RP_PAYPARTS_PLUGIN_PATH . 'rozetkapay-payparts.php';
        
        if ( file_exists( $plugin_file ) ) {
            return get_plugin_data( $plugin_file );
        }
        
        return array(
            'Name' => 'RozetkaPay Payparts',
            'Version' => rp_payparts_get_plugin_version(),
            'Description' => __( 'Оплата частинами через RozetkaPay', 'rp-payparts' ),
            'Author' => 'RozetkaPay',
        );
    }
}

if ( ! function_exists( 'rp_payparts_widget_shortcode' ) ) {
    /**
     * Шорткод для віджета розстрочки
     * 
     * @param array $atts Атрибути шорткоду
     * @return string HTML віджета
     */
    function rp_payparts_widget_shortcode( $atts ) {
        $atts = shortcode_atts( array(
            'price' => 0,
            'style' => 'compact',
            'show_logo' => 'true',
            'show_terms' => 'true',
            'terms' => '3,6,12',
        ), $atts );
        
        $price = floatval( $atts['price'] );
        
        // Якщо ціну не вказано, пробуємо отримати з глобального продукту
        if ( $price <= 0 ) {
            global $product;
            if ( $product && $product->get_price() ) {
                $price = $product->get_price();
            }
        }
        
        if ( $price <= 0 ) {
            return '<p>' . esc_html__( 'Вкажіть ціну для розрахунку розстрочки', 'rp-payparts' ) . '</p>';
        }
        
        $gateway = rp_payparts_get_gateway();
        if ( ! $gateway || ! $gateway->is_available() ) {
            return '';
        }
        
        $validation = rp_payparts_validate_amount( $price );
        if ( ! $validation['valid'] ) {
            return '';
        }
        
        // Отримуємо доступні варіанти
        $banks = rp_payparts_get_available_banks();
        if ( empty( $banks ) ) {
            return '';
        }
        
        $target_terms = ! empty( $atts['terms'] ) ? array_map( 'intval', explode( ',', $atts['terms'] ) ) : array( 3, 6, 12 );
        $min_payment = null;
        
        // Знаходимо мінімальний платіж
        foreach ( $target_terms as $term ) {
            $monthly_payment = rp_payparts_calculate_installment( $price, $term );
            if ( null === $min_payment || $monthly_payment < $min_payment ) {
                $min_payment = $monthly_payment;
            }
        }
        
        if ( null === $min_payment ) {
            return '';
        }
        
        ob_start();
        ?>
        <div class="rozetkapay-payparts-widget rozetkapay-style-<?php echo esc_attr( $atts['style'] ); ?>">
            <?php if ( 'true' === $atts['show_logo'] ) : ?>
                <div class="rozetkapay-widget-logo">
                    <img src="<?php echo esc_url( RP_PAYPARTS_PLUGIN_URL . 'assets/images/rozetkapay-logo.png' ); ?>" 
                         alt="RozetkaPay" class="rozetkapay-logo">
                </div>
            <?php endif; ?>
            
            <div class="rozetkapay-widget-content">
                <div class="rozetkapay-widget-title">
                    <?php esc_html_e( 'Розстрочка без переплат', 'rp-payparts' ); ?>
                </div>
                
                <div class="rozetkapay-widget-best">
                    <?php esc_html_e( 'від', 'rp-payparts' ); ?>
                    <strong><?php echo esc_html( rp_payparts_format_currency( $min_payment ) ); ?></strong>
                    <?php esc_html_e( 'на місяць', 'rp-payparts' ); ?>
                </div>
            </div>
        </div>
        <?php
        
        return ob_get_clean();
    }
}

// Реєструємо шорткод тільки якщо він ще не зареєстрований
if ( ! shortcode_exists( 'rp_payparts_widget' ) ) {
    add_shortcode( 'rp_payparts_widget', 'rp_payparts_widget_shortcode' );
}

// === Added: reference helpers ===
if ( ! function_exists( 'rp_build_reference' ) ) {
    function rp_build_reference( WC_Order $order, $prefix = 'roz1', $sep = '-' ) {
        $number = method_exists( $order, 'get_order_number' ) ? $order->get_order_number() : $order->get_id();
        $prefix = trim( (string) $prefix );
        $sep    = (string) $sep;
        $ref = $prefix !== '' ? $prefix . $sep . $number : (string) $number;
        $order->update_meta_data( '_rp_reference', $ref );
        $order->save();
        return $ref;
    }
}

if ( ! function_exists( 'rp_find_order_by_reference' ) ) {
    function rp_find_order_by_reference( $reference ) {
        $orders = wc_get_orders( array(
            'meta_key'   => '_rp_reference',
            'meta_value' => $reference,
            'limit'      => 1,
            'return'     => 'ids',
        ) );
        return $orders ? wc_get_order( $orders[0] ) : false;
    }
}
// === End helpers ===

// ДІАГНОСТИЧНИЙ КОД – можна видалити після перевірки

/**
 * ШВИДКИЙ ТЕСТ API – додати у functions.php
 * Видалити після перевірки!
 */

add_action('init', function() {
    
    // Запускаємо тільки якщо в URL є параметр test_rp_api
    if (!isset($_GET['test_rp_api'])) {
        return;
    }
    
    // Тільки для адміністраторів
    if (!current_user_can('manage_options')) {
        wp_die('Access denied');
    }
    
    echo '<h1>🔍 RozetkaPay API Test</h1>';
    echo '<style>body{font-family:monospace;margin:20px;} .success{color:green;} .error{color:red;} .info{color:blue;}</style>';
    
    $test_endpoints = [
        'Production API' => 'https://api.rozetkapay.com',
        'Sandbox API' => 'https://api.sandbox.rozetkapay.com',
        'Alternative 1' => 'https://pay-api.rozetka.com.ua',
        'Alternative 2' => 'https://payment-api.rozetka.ua'
    ];
    
    foreach ($test_endpoints as $name => $base_url) {
        
        echo "<h3>🌐 Тестування: {$name}</h3>";
        echo "<div class='info'>URL: {$base_url}</div>";
        
        // Тест 1: Простий GET-запит
        echo "<h4>📡 Test 1: Basic connectivity</h4>";
        
        $start_time = microtime(true);
        
        $response = wp_remote_get($base_url, [
            'timeout' => 10,
            'user-agent' => 'Mozilla/5.0 (WordPress) RozetkaPay Test',
            'headers' => [
                'Accept' => 'application/json',
                'Accept-Language' => 'uk-UA,en;q=0.8'
            ]
        ]);
        
        $end_time = microtime(true);
        $response_time = round(($end_time - $start_time) * 1000, 2);
        
        if (is_wp_error($response)) {
            echo "<div class='error'>❌ ПОМИЛКА: " . $response->get_error_message() . "</div>";
        } else {
            $code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            
            echo "<div class='info'>⏱️ Час відповіді: {$response_time}ms</div>";
            echo "<div class='info'>📊 HTTP Code: {$code}</div>";
            
            if ($code === 403) {
                echo "<div class='error'>🚫 ЗАБЛОКОВАНО CLOUDFLARE</div>";
            } elseif ($code < 500) {
                echo "<div class='success'>✅ Сервер доступний</div>";
            } else {
                echo "<div class='error'>❌ Помилка сервера</div>";
            }
            
            // Показуємо перші 200 символів відповіді
            echo "<div class='info'>📄 Попередній перегляд відповіді: " . substr($body, 0, 200) . "...</div>";
        }
        
        // Тест 2: API endpoint
        echo "<h4>🔌 Test 2: API Endpoint</h4>";
        
        $api_url = $base_url . '/api/payments';
        
        $api_response = wp_remote_post($api_url, [
            'timeout' => 10,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode('test:test'),
                'User-Agent' => 'Mozilla/5.0 (WordPress) RozetkaPay Test'
            ],
            'body' => json_encode(['test' => 'connectivity'])
        ]);
        
        if (is_wp_error($api_response)) {
            echo "<div class='error'>❌ ПОМИЛКА API: " . $api_response->get_error_message() . "</div>";
        } else {
            $api_code = wp_remote_retrieve_response_code($api_response);
            echo "<div class='info'>🔌 API Response Code: {$api_code}</div>";
            
            if ($api_code === 403) {
                echo "<div class='error'>🚫 API ЗАБЛОКОВАНО CLOUDFLARE</div>";
            } elseif ($api_code === 401) {
                echo "<div class='success'>🔒 API доступний (потрібна авторизація)</div>";
            } elseif ($api_code < 500) {
                echo "<div class='success'>✅ API endpoint працює</div>";
            }
        }
        
        echo "<hr>";
    }
    
    // Тест серверного IP
    echo "<h3>🌐 Інформація про сервер</h3>";
    echo "<div class='info'>Server IP: " . $_SERVER['SERVER_ADDR'] . "</div>";
    echo "<div class='info'>User Agent: " . $_SERVER['HTTP_USER_AGENT'] . "</div>";
    echo "<div class='info'>WordPress Version: " . get_bloginfo('version') . "</div>";
    
    // Рекомендації
    echo "<h3>💡 Рекомендації</h3>";
    echo "<ul>";
    echo "<li>Якщо всі endpoints повертають 403 – зверніться до RozetkaPay для додавання IP у whitelist</li>";
    echo "<li>Якщо працює лише sandbox – використовуйте його для тестування</li>";
    echo "<li>Якщо альтернативні endpoints працюють – оновіть URL у коді</li>";
    echo "<li>Спробуйте налаштувати проксі-сервер</li>";
    echo "</ul>";
    
    exit;
});

// Додати в functions.php для безпечної діагностики
add_action('init', function() {
    if (!isset($_GET['check_banks_safe']) || !current_user_can('manage_options')) {
        return;
    }
    
    echo '<h1>Безпечна перевірка банків RozetkaPay API</h1>';
    echo '<style>body{font-family:monospace;margin:20px;} .success{color:green;} .error{color:red;} .warning{color:orange;}</style>';
    
    // 1. Перевіряємо налаштування
    $settings = get_option('woocommerce_rp_payparts_settings', array());
    $api_user = $settings['api_user'] ?? '';
    $api_pass = $settings['api_pass'] ?? '';
    $test_mode = ($settings['test_mode'] ?? 'no') === 'yes';
    
    echo '<h2>1. Налаштування плагіна</h2>';
    echo '<p><strong>Режим:</strong> ' . ($test_mode ? 'Тестовий' : 'Продакшн') . '</p>';
    echo '<p><strong>API User:</strong> ' . (!empty($api_user) ? substr($api_user, 0, 8) . '***' : '<span class="error">НЕ ЗАДАНО</span>') . '</p>';
    echo '<p><strong>API Pass:</strong> ' . (!empty($api_pass) ? '***задано***' : '<span class="error">НЕ ЗАДАНО</span>') . '</p>';
    
    if (empty($api_user) || empty($api_pass)) {
        echo '<p class="error">Зупинка: API-ключі не налаштовані</p>';
        exit;
    }
    
    // 2. Перевіряємо доступність API
    echo '<h2>2. Тестування доступності API</h2>';
    
    $base_url = $test_mode ? 
        'https://api-epdev.rozetkapay.com/api/payparts/v1' : 
        'https://api.rozetkapay.com/api/payparts/v1';
        
    $url = $base_url . '/banks/info?include_fees=true';
    
    echo '<p><strong>URL:</strong> ' . $url . '</p>';
    
    $args = array(
        'method' => 'GET',
        'timeout' => 15,
        'headers' => array(
            'Authorization' => 'Basic ' . base64_encode($api_user . ':' . $api_pass),
            'Content-Type' => 'application/json',
            'User-Agent' => 'WordPress/' . get_bloginfo('version') . ' RozetkaPay/1.0'
        ),
        'sslverify' => true
    );
    
    echo '<p>Відправляємо запит...</p>';
    
    $response = wp_remote_get($url, $args);
    
    if (is_wp_error($response)) {
        echo '<p class="error">Помилка WordPress HTTP API: ' . $response->get_error_message() . '</p>';
        exit;
    }
    
    $http_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    $headers = wp_remote_retrieve_headers($response);
    
    echo '<p><strong>HTTP код:</strong> ' . $http_code . '</p>';
    
    switch ($http_code) {
        case 200:
            echo '<p class="success">✅ Успішна відповідь від API</p>';
            break;
        case 401:
            echo '<p class="error">❌ Невірні API-ключі (401 Unauthorized)</p>';
            exit;
        case 403:
            echo '<p class="error">❌ Доступ заборонено (403 Forbidden). Можливо, IP заблоковано</p>';
            exit;
        case 404:
            echo '<p class="error">❌ Endpoint не знайдено (404). Перевірте URL</p>';
            exit;
        case 500:
            echo '<p class="error">❌ Помилка сервера (500)</p>';
            exit;
        default:
            echo '<p class="warning">⚠️ Неочікуваний код відповіді: ' . $http_code . '</p>';
            break;
    }
    
    // 3. Парсимо відповідь
    echo '<h2>3. Аналіз відповіді API</h2>';
    
    if (empty($response_body)) {
        echo '<p class="error">Порожня відповідь від API</p>';
        exit;
    }
    
    echo '<p><strong>Довжина відповіді:</strong> ' . strlen($response_body) . ' байт</p>';
    
    $banks_data = json_decode($response_body, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo '<p class="error">Помилка парсингу JSON: ' . json_last_error_msg() . '</p>';
        echo '<p><strong>Перші 500 символів відповіді:</strong></p>';
        echo '<pre>' . htmlspecialchars(substr($response_body, 0, 500)) . '</pre>';
        exit;
    }
    
    if (!is_array($banks_data)) {
        echo '<p class="error">API повернув не масив</p>';
        echo '<p><strong>Тип даних:</strong> ' . gettype($banks_data) . '</p>';
        echo '<pre>' . htmlspecialchars(print_r($banks_data, true)) . '</pre>';
        exit;
    }
    
    // 4. Показуємо банки
    echo '<h2>4. Знайдені банки (' . count($banks_data) . ')</h2>';
    
    if (empty($banks_data)) {
        echo '<p class="warning">Список банків порожній</p>';
    } else {
        foreach ($banks_data as $index => $bank) {
            echo '<div style="border:1px solid #ddd; padding:15px; margin:10px 0; border-radius:5px;">';
            echo '<h3 style="margin-top:0;">Банк #' . ($index + 1) . '</h3>';
            
            // Основна інформація
            $name = $bank['name'] ?? 'Не вказано';
            echo '<p><strong>Name (slug):</strong> <span style="background:#e7f3ff; padding:2px 6px; border-radius:3px;">' . htmlspecialchars($name) . '</span></p>';
            
            // Людське ім’я
            $display_name = '';
            $bank_names = [
                'fuib' => 'FUIB',
                'monobank' => 'Monobank', 
                'privatbank' => 'ПриватБанк',
                'rozetkapay' => 'RozetkaPay',
                'izibank' => 'izibank',
                'stub-rozetkapay' => 'RozetkaPay (тестовий)'
            ];
            $display_name = $bank_names[$name] ?? ucfirst(str_replace('-', ' ', $name));
            echo '<p><strong>Відображувана назва:</strong> ' . htmlspecialchars($display_name) . '</p>';
            
            // Ліміти
            if (!empty($bank['limits'])) {
                $min = $bank['limits']['min_amount'] ?? 'N/A';
                $max = $bank['limits']['max_amount'] ?? 'N/A';
                echo '<p><strong>Ліміти:</strong> ' . $min . ' - ' . $max . ' грн</p>';
            }
            
            // Періоди з валідацією
            if (!empty($bank['periods']) && is_array($bank['periods'])) {
                $all_periods = array_map(function($p) { 
                    return $p['period'] ?? '?';
                }, $bank['periods']);
                
                $filtered_periods = rp_payparts_validate_bank_terms($name, $all_periods);
                
                echo '<p><strong>Усі періоди:</strong> ' . implode(', ', $all_periods) . '</p>';
                echo '<p><strong>Після валідації:</strong> ' . implode(', ', $filtered_periods) . '</p>';
                
                if (count($filtered_periods) !== count($all_periods)) {
                    echo '<p class="warning">⚠️ Деякі періоди відфільтровані (мінімум для ' . $name . ': ' . rp_payparts_get_bank_min_period($name) . ' міс)</p>';
                }
            }
            
            // Повні дані (згорнуто)
            echo '<details style="margin-top:10px;">';
            echo '<summary style="cursor:pointer; color:#0073aa;">Показати повні дані JSON</summary>';
            echo '<pre style="background:#f5f5f5; padding:10px; margin:10px 0; overflow:auto; max-height:300px;">' . 
                 htmlspecialchars(json_encode($bank, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre>';
            echo '</details>';
            
            echo '</div>';
        }
    }
    
    // 5. Перевіряємо кеш
    echo '<h2>5. Стан кешу</h2>';
    $cached_banks = get_transient('rp_payparts_banks');
    if ($cached_banks !== false) {
        echo '<p class="warning">У кеші знайдено ' . count($cached_banks) . ' банків</p>';
        echo '<p><a href="?check_banks_safe=1&clear_cache=1" style="color:#d63638;">Очистити кеш і перевірити ще раз</a></p>';
    } else {
        echo '<p class="success">Кеш порожній – дані отримано напряму з API</p>';
    }
    
    // Очищення кешу, якщо запитано
    if (isset($_GET['clear_cache'])) {
        delete_transient('rp_payparts_banks');
        echo '<p class="success">✅ Кеш очищено</p>';
    }
    
    echo '<hr>';
    echo '<p><strong>Перевірку завершено.</strong> Якщо FUIB не знайдено в списку вище, значить він недоступний через ваші API-ключі.</p>';
    
    exit;
});
