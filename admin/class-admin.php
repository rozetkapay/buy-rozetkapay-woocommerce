<?php
/**
 * Admin Class - ВИПРАВЛЕНА ВЕРСІЯ
 *
 * @package RP_Payparts
 * @version 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Адмін-функціонал
 */
class RP_Payparts_Admin {

    /**
     * Конструктор
     */
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'admin_init' ] );
        add_action( 'admin_notices', [ $this, 'admin_notices' ] );
        add_filter( 'plugin_action_links_' . RP_PAYPARTS_PLUGIN_BASENAME, [ $this, 'plugin_action_links' ] );
        add_action( 'woocommerce_admin_order_data_after_payment_info', [ $this, 'display_order_payment_info' ] );
        
        // ✅ ДОДАЄМО AJAX-ДІЇ
        add_action( 'wp_ajax_rp_test_connection', [ $this, 'ajax_test_connection' ] );
        add_action( 'wp_ajax_rp_clear_cache', [ $this, 'ajax_clear_cache' ] );
    }

    /**
     * Додавання меню в адмінці
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __( 'RozetkaPay Оплата частинами', 'rp-payparts' ),
            __( 'RozetkaPay Частинами', 'rp-payparts' ),
            'manage_woocommerce',
            'rp-payparts',
            [ $this, 'admin_page' ]
        );
    }

    /**
     * Адмін-сторінка
     */
    public function admin_page() {
        $active_tab = $_GET['tab'] ?? 'overview';
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'RozetkaPay Оплата частинами', 'rp-payparts' ); ?></h1>
            
            <nav class="nav-tab-wrapper">
                <a href="?page=rp-payparts&tab=overview" 
                   class="nav-tab <?php echo $active_tab === 'overview' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Огляд', 'rp-payparts' ); ?>
                </a>
                <a href="?page=rp-payparts&tab=orders" 
                   class="nav-tab <?php echo $active_tab === 'orders' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Замовлення', 'rp-payparts' ); ?>
                </a>
                <a href="?page=rp-payparts&tab=settings" 
                   class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Налаштування', 'rp-payparts' ); ?>
                </a>
            </nav>

            <div class="tab-content">
                <?php
                switch ( $active_tab ) {
                    case 'orders':
                        $this->render_orders_tab();
                        break;
                    case 'settings':
                        $this->render_settings_tab();
                        break;
                    default:
                        $this->render_overview_tab();
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Вивід вкладки «Огляд» – ОНОВЛЕНА ВЕРСІЯ для двох методів
     */
    private function render_overview_tab() {
        $payparts_gateway  = rp_payparts_get_gateway();
        $standard_gateway  = $this->get_standard_gateway();
        $stats             = $this->get_payment_stats();
        ?>
        <div class="rp-admin-overview">
            <div class="rp-stats-grid">
                <div class="rp-stat-card">
                    <h3><?php esc_html_e( 'Всього платежів', 'rp-payparts' ); ?></h3>
                    <div class="rp-stat-number"><?php echo esc_html( $stats['total_payments'] ); ?></div>
                    <div class="rp-stat-breakdown">
                        <?php echo esc_html( $stats['payparts_payments'] ); ?> частинами | 
                        <?php echo esc_html( $stats['standard_payments'] ); ?> картками
                    </div>
                </div>
                
                <div class="rp-stat-card">
                    <h3><?php esc_html_e( 'Успішних платежів', 'rp-payparts' ); ?></h3>
                    <div class="rp-stat-number"><?php echo esc_html( $stats['successful_payments'] ); ?></div>
                    <div class="rp-stat-breakdown">
                        <?php echo esc_html( $stats['successful_payparts'] ); ?> частинами | 
                        <?php echo esc_html( $stats['successful_standard'] ); ?> картками
                    </div>
                </div>
                
                <div class="rp-stat-card">
                    <h3><?php esc_html_e( 'Загальна сума', 'rp-payparts' ); ?></h3>
                    <div class="rp-stat-number"><?php echo wp_kses_post( wc_price( $stats['total_amount'] ) ); ?></div>
                    <div class="rp-stat-breakdown">
                        <?php echo wp_kses_post( wc_price( $stats['payparts_amount'] ) ); ?> частинами<br>
                        <?php echo wp_kses_post( wc_price( $stats['standard_amount'] ) ); ?> картками
                    </div>
                </div>
                
                <div class="rp-stat-card">
                    <h3><?php esc_html_e( 'Очікують оплати', 'rp-payparts' ); ?></h3>
                    <div class="rp-stat-number"><?php echo esc_html( $stats['pending_payments'] ); ?></div>
                    <div class="rp-stat-breakdown">
                        <?php echo esc_html( $stats['pending_payparts'] ); ?> частинами | 
                        <?php echo esc_html( $stats['pending_standard'] ); ?> картками
                    </div>
                </div>
            </div>

            <!-- Статус методів оплати -->
            <div class="rp-payment-methods-status">
                <h3><?php esc_html_e( 'Статус методів оплати', 'rp-payparts' ); ?></h3>
                
                <div class="rp-methods-grid">
                    <!-- RozetkaPay Payparts -->
                    <div class="rp-method-card <?php echo $payparts_gateway && 'yes' === $payparts_gateway->enabled ? 'rp-status-active' : 'rp-status-inactive'; ?>">
                        <h4>💳 RozetkaPay Оплата частинами</h4>
                        <?php if ( $payparts_gateway && 'yes' === $payparts_gateway->enabled ) : ?>
                            <p>✅ <?php esc_html_e( 'Активний і працює', 'rp-payparts' ); ?></p>
                            <p><strong><?php esc_html_e( 'Режим:', 'rp-payparts' ); ?></strong> 
                               <?php echo $payparts_gateway->test_mode ? esc_html__( 'Продакшн', 'rp-payparts' ) : esc_html__( 'Продакшн', 'rp-payparts' ); ?>
                            </p>
                        <?php else : ?>
                            <p>⚠️ <?php esc_html_e( 'Неактивний', 'rp-payparts' ); ?></p>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=rp_payparts' ) ); ?>" 
                               class="button button-primary">
                                <?php esc_html_e( 'Активувати', 'rp-payparts' ); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <!-- RozetkaPay Standard -->
                    <div class="rp-method-card <?php echo $standard_gateway && 'yes' === $standard_gateway->enabled ? 'rp-status-active' : 'rp-status-inactive'; ?>">
                        <h4>🏦 RozetkaPay Банківські картки</h4>
                        <?php if ( $standard_gateway && 'yes' === $standard_gateway->enabled ) : ?>
                            <p>✅ <?php esc_html_e( 'Активний і працює', 'rp-payparts' ); ?></p>
                            <p><strong><?php esc_html_e( 'Режим:', 'rp-payparts' ); ?></strong> 
                               <?php echo $standard_gateway->test_mode ? esc_html__( 'Продакшн', 'rp-payparts' ) : esc_html__( 'Продакшн', 'rp-payparts' ); ?>
                            </p>
                            <p><strong><?php esc_html_e( 'Тип:', 'rp-payparts' ); ?></strong> 
                               <?php echo isset( $standard_gateway->two_step_payment ) && $standard_gateway->two_step_payment ? esc_html__( 'Двоетапна оплата', 'rp-payparts' ) : esc_html__( 'Одноетапна оплата', 'rp-payparts' ); ?>
                            </p>
                        <?php else : ?>
                            <p>⚠️ <?php esc_html_e( 'Неактивний', 'rp-payparts' ); ?></p>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=rp_standard' ) ); ?>" 
                               class="button button-primary">
                                <?php esc_html_e( 'Активувати', 'rp-payparts' ); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="rp-quick-actions">
                <h3><?php esc_html_e( 'Швидкі дії', 'rp-payparts' ); ?></h3>
                <div class="rp-actions-grid">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=rp_payparts' ) ); ?>" 
                       class="button">
                        <?php esc_html_e( 'Налаштування частинами', 'rp-payparts' ); ?>
                    </a>
                    
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=rp_standard' ) ); ?>" 
                       class="button">
                        <?php esc_html_e( 'Налаштування картками', 'rp-payparts' ); ?>
                    </a>
                    
                    <button type="button" class="button" id="rp-test-connection" data-nonce="<?php echo wp_create_nonce( 'rp_admin_nonce' ); ?>">
                        <?php esc_html_e( 'Тест підключення API', 'rp-payparts' ); ?>
                    </button>
                    
                    <button type="button" class="button" id="rp-clear-cache" data-nonce="<?php echo wp_create_nonce( 'rp_admin_nonce' ); ?>">
                        <?php esc_html_e( 'Очистити кеш', 'rp-payparts' ); ?>
                    </button>
                </div>
                
                <!-- Область для повідомлень -->
                <div id="rp-admin-messages" style="margin-top: 15px;"></div>
            </div>
        </div>

        <style>
        .rp-admin-overview {
            margin-top: 20px;
        }
        .rp-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .rp-stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        .rp-stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #3b82f6;
            margin: 10px 0;
        }
        .rp-stat-breakdown {
            font-size: 12px;
            color: #666;
            margin-top: 8px;
        }
        .rp-actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        .rp-status-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .rp-status-active {
            border-left: 4px solid #10b981;
        }
        .rp-status-inactive {
            border-left: 4px solid #f59e0b;
        }
        .rp-methods-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .rp-method-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .rp-method-card h4 {
            margin: 0 0 15px 0;
            font-size: 16px;
        }
        
        /* Стилі для повідомлень */
        .rp-admin-notice {
            padding: 12px 16px;
            border-radius: 6px;
            margin: 10px 0;
            font-weight: 500;
        }
        .rp-admin-notice.success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }
        .rp-admin-notice.error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }
        .rp-admin-notice.loading {
            background: #dbeafe;
            color: #1e40af;
            border-left: 4px solid #3b82f6;
        }
        
        /* Анімація для кнопок */
        .rp-actions-grid .button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        </style>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Тест підключення API
            $('#rp-test-connection').on('click', function() {
                var $button   = $(this);
                var $messages = $('#rp-admin-messages');
                var nonce     = $button.data('nonce');
                
                // Блокуємо кнопку
                $button.prop('disabled', true).text('<?php esc_html_e( 'Тестування...', 'rp-payparts' ); ?>');
                
                // Показуємо повідомлення про завантаження
                $messages.html('<div class="rp-admin-notice loading">🔄 <?php esc_html_e( 'Тестуємо підключення до API...', 'rp-payparts' ); ?></div>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'rp_test_connection',
                        nonce: nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $messages.html('<div class="rp-admin-notice success">✅ ' + response.data.message + '</div>');
                            if (response.data.banks_count) {
                                $messages.append('<div class="rp-admin-notice success">🏦 <?php esc_html_e( 'Знайдено банків:', 'rp-payparts' ); ?> ' + response.data.banks_count + '</div>');
                            }
                        } else {
                            $messages.html('<div class="rp-admin-notice error">❌ ' + response.data + '</div>');
                        }
                    },
                    error: function() {
                        $messages.html('<div class="rp-admin-notice error">❌ <?php esc_html_e( 'Помилка підключення', 'rp-payparts' ); ?></div>');
                    },
                    complete: function() {
                        // Розблоковуємо кнопку
                        $button.prop('disabled', false).text('<?php esc_html_e( 'Тест підключення API', 'rp-payparts' ); ?>');
                    }
                });
            });
            
            // Очищення кешу
            $('#rp-clear-cache').on('click', function() {
                var $button   = $(this);
                var $messages = $('#rp-admin-messages');
                var nonce     = $button.data('nonce');
                
                // Блокуємо кнопку
                $button.prop('disabled', true).text('<?php esc_html_e( 'Очищаємо...', 'rp-payparts' ); ?>');
                
                // Показуємо повідомлення про завантаження
                $messages.html('<div class="rp-admin-notice loading">🔄 <?php esc_html_e( 'Очищаємо кеш...', 'rp-payparts' ); ?></div>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'rp_clear_cache',
                        nonce: nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $messages.html('<div class="rp-admin-notice success">✅ ' + response.data + '</div>');
                        } else {
                            $messages.html('<div class="rp-admin-notice error">❌ ' + response.data + '</div>');
                        }
                    },
                    error: function() {
                        $messages.html('<div class="rp-admin-notice error">❌ <?php esc_html_e( 'Помилка очищення кешу', 'rp-payparts' ); ?></div>');
                    },
                    complete: function() {
                        // Розблоковуємо кнопку
                        $button.prop('disabled', false).text('<?php esc_html_e( 'Очистити кеш', 'rp-payparts' ); ?>');
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Вивід вкладки «Замовлення»
     */
    private function render_orders_tab() {
        $orders = $this->get_payparts_orders();
        ?>
        <div class="rp-orders-tab">
            <h3><?php esc_html_e( 'Замовлення з оплатою частинами', 'rp-payparts' ); ?></h3>
            
            <?php if ( empty( $orders ) ) : ?>
                <p><?php esc_html_e( 'Замовлень з оплатою частинами поки немає.', 'rp-payparts' ); ?></p>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Замовлення', 'rp-payparts' ); ?></th>
                            <th><?php esc_html_e( 'Клієнт', 'rp-payparts' ); ?></th>
                            <th><?php esc_html_e( 'Сума', 'rp-payparts' ); ?></th>
                            <th><?php esc_html_e( 'Банк', 'rp-payparts' ); ?></th>
                            <th><?php esc_html_e( 'Термін', 'rp-payparts' ); ?></th>
                            <th><?php esc_html_e( 'Статус', 'rp-payparts' ); ?></th>
                            <th><?php esc_html_e( 'Дата', 'rp-payparts' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $orders as $order ) : ?>
                            <?php $option_data = rp_payparts_parse_option( get_post_meta( $order->get_id(), '_rp_payparts_option', true ) ); ?>
                            <tr>
                                <td>
                                    <a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>">
                                        #<?php echo esc_html( $order->get_order_number() ); ?>
                                    </a>
                                </td>
                                <td><?php echo esc_html( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ); ?></td>
                                <td><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></td>
                                <td><?php echo $option_data ? esc_html( $option_data['bank_name'] ) : '—'; ?></td>
                                <td><?php echo $option_data ? esc_html( $option_data['term_text'] ) : '—'; ?></td>
                                <td>
                                    <span class="rp-status rp-status-<?php echo esc_attr( $order->get_status() ); ?>">
                                        <?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html( $order->get_date_created()->date_i18n( 'd.m.Y H:i' ) ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <style>
        .rp-status {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        .rp-status-completed { background: #dcfce7; color: #166534; }
        .rp-status-processing { background: #dbeafe; color: #1e40af; }
        .rp-status-on-hold { background: #fef3c7; color: #92400e; }
        .rp-status-cancelled { background: #fecaca; color: #991b1b; }
        </style>
        <?php
    }

    /**
     * Вивід вкладки «Налаштування»
     */
    private function render_settings_tab() {
        ?>
        <div class="rp-settings-tab">
            <p><?php esc_html_e( 'Налаштування плагіна доступні в', 'rp-payparts' ); ?> 
               <a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=rp_payparts' ) ); ?>">
                   <?php esc_html_e( 'налаштуваннях WooCommerce', 'rp-payparts' ); ?>
               </a>
            </p>
        </div>
        <?php
    }

    /**
     * Отримати стандартний gateway – НОВИЙ МЕТОД
     */
    private function get_standard_gateway() {
        if ( ! class_exists( 'WC_Payment_Gateways' ) ) {
            return null;
        }
        
        $gateways = WC()->payment_gateways()->get_available_payment_gateways();
        return isset( $gateways['rp_standard'] ) ? $gateways['rp_standard'] : null;
    }

    /**
     * Отримати статистику платежів – ОНОВЛЕНА ВЕРСІЯ для двох методів
     */
    private function get_payment_stats() {
        // Отримуємо замовлення для обох методів
        $payparts_orders = wc_get_orders( [
            'payment_method' => 'rp_payparts',
            'limit'          => -1,
        ] );
        
        $standard_orders = wc_get_orders( [
            'payment_method' => 'rp_standard',
            'limit'          => -1,
        ] );

        $stats = [
            'total_payments'      => count( $payparts_orders ) + count( $standard_orders ),
            'payparts_payments'   => count( $payparts_orders ),
            'standard_payments'   => count( $standard_orders ),
            'successful_payments' => 0,
            'successful_payparts' => 0,
            'successful_standard' => 0,
            'total_amount'        => 0,
            'payparts_amount'     => 0,
            'standard_amount'     => 0,
            'pending_payments'    => 0,
            'pending_payparts'    => 0,
            'pending_standard'    => 0,
        ];

        // Обробляємо замовлення Payparts
        foreach ( $payparts_orders as $order ) {
            $stats['payparts_amount'] += $order->get_total();
            
            if ( $order->has_status( [ 'completed', 'processing' ] ) ) {
                $stats['successful_payparts']++;
            } elseif ( $order->has_status( 'on-hold' ) ) {
                $stats['pending_payparts']++;
            }
        }
        
        // Обробляємо замовлення Standard
        foreach ( $standard_orders as $order ) {
            $stats['standard_amount'] += $order->get_total();
            
            if ( $order->has_status( [ 'completed', 'processing' ] ) ) {
                $stats['successful_standard']++;
            } elseif ( $order->has_status( [ 'on-hold', 'pending' ] ) ) {
                $stats['pending_standard']++;
            }
        }
        
        // Підрахунок загальних значень
        $stats['successful_payments'] = $stats['successful_payparts'] + $stats['successful_standard'];
        $stats['total_amount']        = $stats['payparts_amount'] + $stats['standard_amount'];
        $stats['pending_payments']    = $stats['pending_payparts'] + $stats['pending_standard'];

        return $stats;
    }

    /**
     * Отримати замовлення з оплатою частинами
     */
    private function get_payparts_orders() {
        return wc_get_orders( [
            'payment_method' => 'rp_payparts',
            'limit'          => 50,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ] );
    }

    /**
     * Ініціалізація в адмінці
     */
    public function admin_init() {
        // Підключаємо адмін-скрипти/стилі на потрібних сторінках
        $screen = get_current_screen();
        if ( $screen && strpos( $screen->id, 'rp-payparts' ) !== false ) {
            wp_enqueue_script( 'jquery' );
        }
    }

    /**
     * AJAX: Тест підключення до API
     */
    public function ajax_test_connection() {
        // Перевірка nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'rp_admin_nonce' ) ) {
            wp_send_json_error( __( 'Помилка безпеки', 'rp-payparts' ) );
        }

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( __( 'Недостатньо прав доступу', 'rp-payparts' ) );
        }

        $gateway = rp_payparts_get_gateway();
        if ( ! $gateway ) {
            wp_send_json_error( __( 'Шлюз не знайдено', 'rp-payparts' ) );
        }

        // Створюємо API-клієнт
        $api_client = new RP_Payparts_API( $gateway->api_user, $gateway->api_pass, false );
        
        // Отримуємо результат тесту
        $result = $api_client->test_connection();

        if ( $result['success'] ) {
            // Додатково пробуємо отримати банки для детальнішої інформації
            $banks = $api_client->fetch_banks();
            if ( ! is_wp_error( $banks ) && is_array( $banks ) ) {
                $result['banks_count'] = count( $banks );
                
                // Оновлюємо кеш
                set_transient( 'rp_payparts_banks', $banks, HOUR_IN_SECONDS );
            }
            
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( $result['message'] );
        }
    }

    /**
     * AJAX: Очищення кешу
     */
    public function ajax_clear_cache() {
        // Перевірка nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'rp_admin_nonce' ) ) {
            wp_send_json_error( __( 'Помилка безпеки', 'rp-payparts' ) );
        }

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( __( 'Недостатньо прав доступу', 'rp-payparts' ) );
        }

        // Очищаємо всі transients плагіна
        rp_payparts_clear_cache();
        
        // Додатково очищаємо специфічні кеші
        delete_transient( 'rp_payparts_banks' );
        delete_transient( 'rozetkapay_payparts_banks' );
        
        // Очищаємо кеш об'єктів WordPress
        wp_cache_flush();

        wp_send_json_success( __( 'Кеш успішно очищено', 'rp-payparts' ) );
    }

    /**
     * Посилання дій плагіна на сторінці плагінів
     */
    public function plugin_action_links( $links ) {
        $settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=rp_payparts' ) ) . '">' . __( 'Налаштування', 'rp-payparts' ) . '</a>';
        array_unshift( $links, $settings_link );
        return $links;
    }

    /**
     * Відображення інформації про оплату в замовленні
     */
    public function display_order_payment_info( $order ) {
        if ( $order->get_payment_method() !== 'rp_payparts' ) {
            return;
        }

        $payment_data     = rp_payparts_get_order_payment_data( $order->get_id() );
        $installment_info = rp_payparts_get_order_installment_summary( $order->get_id() );

        if ( ! $payment_data && ! $installment_info ) {
            return;
        }

        ?>
        <div class="rp-order-payment-info">
            <h4><?php esc_html_e( 'Інформація про оплату частинами', 'rp-payparts' ); ?></h4>
            
            <?php if ( $installment_info ) : ?>
                <p><strong><?php esc_html_e( 'Банк:', 'rp-payparts' ); ?></strong> <?php echo esc_html( $installment_info['bank_name'] ); ?></p>
                <p><strong><?php esc_html_e( 'Термін:', 'rp-payparts' ); ?></strong> <?php echo esc_html( $installment_info['term_text'] ); ?></p>
                <p><strong><?php esc_html_e( 'Щомісячний платіж:', 'rp-payparts' ); ?></strong> <?php echo esc_html( $installment_info['monthly_payment_formatted'] ); ?></p>
            <?php endif; ?>

            <?php if ( $payment_data['payment_id'] ) : ?>
                <p><strong><?php esc_html_e( 'ID операції:', 'rp-payparts' ); ?></strong> <code><?php echo esc_html( $payment_data['payment_id'] ); ?></code></p>
            <?php endif; ?>

            <?php if ( $payment_data['transaction_id'] ) : ?>
                <p><strong><?php esc_html_e( 'ID транзакції:', 'rp-payparts' ); ?></strong> <code><?php echo esc_html( $payment_data['transaction_id'] ); ?></code></p>
            <?php endif; ?>

            <?php if ( $payment_data['payment_url'] && $order->has_status( 'on-hold' ) ) : ?>
                <p>
                    <strong><?php esc_html_e( 'Посилання для оплати:', 'rp-payparts' ); ?></strong><br>
                    <a href="<?php echo esc_url( $payment_data['payment_url'] ); ?>" target="_blank" class="button button-small">
                        <?php esc_html_e( 'Відкрити сторінку оплати', 'rp-payparts' ); ?>
                    </a>
                </p>
            <?php endif; ?>
        </div>

        <style>
        .rp-order-payment-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
            border-left: 4px solid #3b82f6;
        }
        .rp-order-payment-info h4 {
            margin-top: 0;
            color: #1e293b;
        }
        .rp-order-payment-info code {
            background: #e5e7eb;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
        }
        </style>
        <?php
    }

    /**
     * Адмін-повідомлення
     */
    public function admin_notices() {
        // Перевіряємо, чи активний WooCommerce
        if ( ! class_exists( 'WooCommerce' ) ) {
            ?>
            <div class="notice notice-error">
                <p>
                    <?php 
                    printf(
                        /* translators: %s: Plugin name */
                        esc_html__( '%s потребує активного WooCommerce для роботи.', 'rp-payparts' ),
                        '<strong>RozetkaPay Payparts</strong>'
                    );
                    ?>
                </p>
            </div>
            <?php
            return;
        }

        // Перевіряємо, чи налаштований шлюз
        $gateway = rp_payparts_get_gateway();
        if ( $gateway && 'yes' === $gateway->enabled && ( empty( $gateway->api_user ) || empty( $gateway->api_pass ) ) ) {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p>
                    <?php esc_html_e( 'RozetkaPay Payparts: Будь ласка, налаштуйте API ключі для роботи плагіна.', 'rp-payparts' ); ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=rp_payparts' ) ); ?>">
                        <?php esc_html_e( 'Налаштувати зараз', 'rp-payparts' ); ?>
                    </a>
                </p>
            </div>
            <?php
        }
    }
}

// Ініціалізація адмін-класу в адмін-панелі
if ( is_admin() ) {
    new RP_Payparts_Admin();
}
