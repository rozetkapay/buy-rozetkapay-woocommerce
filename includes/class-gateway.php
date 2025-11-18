<?php
/**
 * RozetkaPay Payparts Gateway Class - ВИПРАВЛЕНА ВЕРСІЯ з обробкою повернень
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Переконуємося, що WC_Payment_Gateway існує
if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
    return;
}

class RP_Payparts_Gateway extends WC_Payment_Gateway {
    public $order_ref_prefix;
    public $enable_status_polling;

    private $api_client;
    
    // Оголошуємо всі властивості класу для PHP 8.2+ – мають бути public для WooCommerce
    public $api_user;
    public $api_pass;
    public $test_mode;
    public $min_amount;
    public $max_amount;
    public $allowed_banks;
    public $allowed_terms;
    public $send_pending_email;
    public $send_success_email;
    public $cart_message;
    public $checkout_message;
    public $display_style;
    public $debug_mode;
    public $auto_redirect;

    public function __construct() {
        $this->id                 = 'rp_payparts';
        $this->method_title       = __( 'RozetkaPay Оплата частинами', 'rp-payparts' );
        $this->method_description = __( 'Прийом платежів частинами через RozetkaPay', 'rp-payparts' );
        $this->title              = __( 'Оплата частинами', 'rp-payparts' );
        $this->has_fields         = true;
        $this->supports           = array( 'products' );

        $this->init_form_fields();
        $this->init_settings();

        // Налаштовуваний префікс external_id
        $this->order_ref_prefix = $this->get_option( 'order_ref_prefix', 'roz1' );

        // Налаштування: увімкнути переопитування статусів
        $this->enable_status_polling = $this->get_option( 'enable_status_polling', 'yes' );

        // Основні налаштування
        $this->enabled      = $this->get_option( 'enabled' );
        $this->title        = $this->get_option( 'title', __( 'Оплата частинами', 'rp-payparts' ) );
        $this->description  = $this->get_option( 'description' );
        
        // API налаштування
        $this->api_user     = $this->get_option( 'api_user' );
        $this->api_pass     = $this->get_option( 'api_pass' );
        $this->test_mode    = 'yes' === $this->get_option('test_mode', 'no');
        
        // Налаштування платежів
        $this->min_amount   = floatval( $this->get_option( 'min_amount', 0 ) );
        $this->max_amount   = floatval( $this->get_option( 'max_amount', 0 ) );
        $this->allowed_banks = array_filter( array_map( 'trim', explode( ',', $this->get_option( 'allowed_banks', '' ) ) ) );
        $this->allowed_terms = array_filter( array_map( 'intval', explode( ',', $this->get_option( 'allowed_terms', '' ) ) ) );
        $this->auto_confirm = $this->get_option( 'auto_confirm', 'no' );
        
        // Email налаштування
        $this->send_pending_email = 'yes' === $this->get_option( 'send_pending_email' );
        $this->send_success_email = 'yes' === $this->get_option( 'send_success_email' );
        
        // Налаштування відображення
        $this->cart_message = $this->get_option( 'cart_message' );
        $this->checkout_message = $this->get_option( 'checkout_message' );
        $this->display_style = $this->get_option( 'display_style', 'slider' );
        
        // Додаткові налаштування
        $this->debug_mode = 'yes' === $this->get_option( 'debug_mode' );
        $this->auto_redirect = $this->get_option( 'auto_redirect', 'rozetkapay_only' );

        // Правильна реєстрація callback за стандартом WooCommerce
        add_action( 'woocommerce_api_' . $this->id, array( $this, 'handle_callback' ) );
        
        // Інші хуки
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'store_selection' ) );
        
        // Додаємо валідацію налаштувань
        add_action( 'woocommerce_settings_saved', array( $this, 'validate_admin_settings' ) );
        
        // Показувати повідомлення в кошику
        if ( $this->cart_message ) {
            add_action( 'woocommerce_before_cart', array( $this, 'display_cart_message' ) );
            add_action( 'woocommerce_before_checkout_form', array( $this, 'display_cart_message' ) );
        }
        
        // Підключаємо стилі на фронтенді
        if ( ! is_admin() ) {
            add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_styles' ), 20 );
        }
        
        // Хук для показу інструкції на thank you сторінці
        add_action( 'woocommerce_thankyou_rp_payparts', array( $this, 'thankyou_page_instruction' ) );
    }

    /**
     * ВИПРАВЛЕНИЙ метод обробки callback від RozetkaPay Payparts
     */
    public function handle_callback() {
        header('Content-Type: text/plain; charset=utf-8');
        
        error_log('RozetkaPay Payparts Callback: === STARTED ===');
        error_log('RozetkaPay Callback: Request method: ' . $_SERVER['REQUEST_METHOD']);
        error_log('RozetkaPay Callback: Request URI: ' . $_SERVER['REQUEST_URI']);
        
        // GET-запити відхиляємо
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            error_log('RozetkaPay Callback: GET request - returning error');
            http_response_code(400);
            echo 'Empty payload';
            exit;
        }
        
        // Отримуємо payload
        $payload = file_get_contents('php://input');
        
        if (empty($payload) && !empty($_POST)) {
            $payload = json_encode($_POST);
        }
        
        if (empty($payload)) {
            error_log('RozetkaPay Callback: Empty payload');
            http_response_code(400);
            echo 'Empty payload';
            exit;
        }
        
        error_log('RozetkaPay Callback: Payload length: ' . strlen($payload));
        error_log('RozetkaPay Callback RAW data: ' . $payload); // RAW-логування
        
        // ВИПРАВЛЕНО: правильне отримання підпису
        $signature = '';
        $possible_headers = [
            'HTTP_X_SIGNATURE',
            'HTTP_SIGNATURE', 
            'HTTP_X_ROZETKAPAY_SIGNATURE',
            'HTTP_AUTHORIZATION'
        ];
        
        foreach ($possible_headers as $header) {
            if (isset($_SERVER[$header]) && !empty($_SERVER[$header])) {
                $signature = $_SERVER[$header];
                error_log("RozetkaPay Callback: Got signature from $header");
                break;
            }
        }
        
        if (empty($signature) && function_exists('getallheaders')) {
            $headers = getallheaders();
            if (isset($headers['X-Signature'])) {
                $signature = $headers['X-Signature'];
            } elseif (isset($headers['Authorization'])) {
                $signature = $headers['Authorization'];
            }
        }
        
        // Перевіряємо тестовий режим
        $gateway_settings = get_option('woocommerce_rp_payparts_settings', array());
        $test_mode = isset($gateway_settings['test_mode']) && $gateway_settings['test_mode'] === 'yes';
        
        error_log('RozetkaPay Callback: Test mode: ' . ($test_mode ? 'YES' : 'NO'));
        
        // ВИПРАВЛЕНО: правильна перевірка підпису
        if (!$test_mode) {
            if (empty($signature)) {
                error_log('RozetkaPay Callback Error: Missing signature in production mode');
                http_response_code(403);
                echo 'Missing signature';
                exit;
            }
            
            $api = $this->get_api_client();
            if (!$api || !$api->verify_callback_enhanced($payload, $signature)) {
                error_log('RozetkaPay Callback Error: Invalid signature');
                http_response_code(403);
                echo 'Invalid signature';
                exit;
            }
            error_log('RozetkaPay Callback: Signature verified successfully');
        } else {
            error_log('RozetkaPay Callback: TEST MODE - skipping signature verification');
        }
        
        // Парсимо JSON
        $data = json_decode($payload, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('RozetkaPay Callback Error: Invalid JSON - ' . json_last_error_msg());
            http_response_code(400);
            echo 'Invalid JSON';
            exit;
        }
        
        error_log('RozetkaPay Callback: Successfully decoded JSON');
        error_log('RozetkaPay Callback decoded data: ' . print_r($data, true));
        
        // Тестовий callback
        if (isset($data['test']) && $data['test'] === true) {
            error_log('RozetkaPay Callback: Test callback processed');
            http_response_code(200);
            echo 'OK';
            exit;
        }
        
        // ВИПРАВЛЕНО: отримання даних для PayParts
        $external_id = null;
        $status = null;
        $operation_id = null;
        
        // Перевіряємо різні варіанти структури даних
        if (isset($data['external_id'])) {
            $external_id = $data['external_id'];
        } elseif (isset($data['details']['external_id'])) {
            $external_id = $data['details']['external_id'];
        } elseif (isset($data['order_reference'])) {
            $external_id = $data['order_reference'];
        }
        
        if (isset($data['status'])) {
            $status = $data['status'];
        } elseif (isset($data['details']['status'])) {
            $status = $data['details']['status'];
        } elseif (isset($data['details']['status_code'])) {
            $status = $data['details']['status_code'];
        }
        
        if (isset($data['operation_id'])) {
            $operation_id = $data['operation_id'];
        } elseif (isset($data['details']['operation_id'])) {
            $operation_id = $data['details']['operation_id'];
        }
        
        if (!$external_id || !$status) {
            error_log('RozetkaPay Callback Error: Missing external_id or status');
            error_log('Available data: ' . print_r($data, true));
            http_response_code(400);
            echo 'Missing external_id or status';
            exit;
        }
        
        error_log("RozetkaPay Callback: external_id=$external_id, status=$status, operation_id=$operation_id");
        
        // Пошук замовлення
        $order = $this->find_order_by_reference($external_id, $operation_id);
        
        if (!$order) {
            error_log('RozetkaPay Callback Error: Order not found for external_id=' . $external_id);
            http_response_code(404);
            echo 'Order not found';
            exit;
        }
        
        $order_id = $order->get_id();
        error_log("RozetkaPay Callback: Found order #$order_id");
        
        // Перевірка на дублювання подій
        if ($operation_id && $order->get_meta('_rp_seen_event_' . $operation_id, true)) {
            error_log('RozetkaPay Callback: Duplicate event ' . $operation_id);
            http_response_code(200);
            echo 'OK';
            exit;
        }
        
        // ВИПРАВЛЕНО: визначення типу операції (повернення або звичайний платіж)
        $details = isset($data['details']) && is_array($data['details']) ? $data['details'] : array();
        $method = isset($details['method']) ? strtolower($details['method']) : '';
        $amount = isset($details['amount']) ? floatval($details['amount']) : 0;
        
        $is_refund = ($method === 'refund') || 
                     in_array(strtolower($status), array('refund', 'refunded', 'refund_successful', 'refunding'), true) ||
                     (strpos(strtolower($status), 'refund') !== false);
        
        error_log("RozetkaPay Callback: method=$method, is_refund=" . ($is_refund ? 'YES' : 'NO') . ", amount=$amount");
        
        // НОВЕ: обробка повернень PayParts
        if ($is_refund) {
            $this->process_callback_refund($order, $amount, $details);
            
            // Позначаємо подію як оброблену
            if ($operation_id) {
                $order->update_meta_data('_rp_seen_event_' . $operation_id, current_time('mysql'));
                $order->save();
            }
            
            error_log("RozetkaPay Callback: Refund processed for order #$order_id");
            http_response_code(200);
            echo 'OK';
            exit;
        }
        
        // Обробка звичайних статусів
        $status_lower = strtolower($status);
        
        switch ($status_lower) {
            case 'success':
            case 'approved':
            case 'paid':
            case 'completed':
                if ($order->has_status(array('pending', 'on-hold'))) {
                    $order->payment_complete();
                    $order->add_order_note('PayParts: платіж успішно проведено через RozetkaPay (callback)');
                    error_log("RozetkaPay Callback: Order #$order_id marked as completed");
                }
                break;
                
            case 'fail':
            case 'failure':
            case 'failed':
            case 'rejected':
            case 'declined':
                if (!$order->has_status('failed')) {
                    $order->update_status('failed', 'PayParts: платіж відхилено через RozetkaPay (callback)');
                    error_log("RozetkaPay Callback: Order #$order_id marked as failed");
                }
                break;
                
            case 'pending':
            case 'processing':
                if (!$order->has_status(array('pending', 'on-hold', 'processing'))) {
                    $order->update_status('on-hold', 'PayParts: платіж очікує обробки (callback)');
                    error_log("RozetkaPay Callback: Order #$order_id marked as pending");
                }
                break;
                
            case 'cancelled':
            case 'canceled':
                if (!$order->has_status('cancelled')) {
                    $order->update_status('cancelled', 'PayParts: платіж скасовано (callback)');
                    error_log("RozetkaPay Callback: Order #$order_id marked as cancelled");
                }
                break;
                
            default:
                error_log("RozetkaPay Callback: Unknown status '$status' for order #$order_id");
                $order->add_order_note('PayParts: невідомий статус платежу: ' . $status);
        }
        
        // Позначаємо подію як оброблену
        if ($operation_id) {
            $order->update_meta_data('_rp_seen_event_' . $operation_id, current_time('mysql'));
            $order->save();
        }
        
        error_log('RozetkaPay Callback: === COMPLETED SUCCESSFULLY ===');
        http_response_code(200);
        echo 'OK';
        exit;
    }

    /**
     * НОВИЙ метод для пошуку замовлення за різними критеріями
     */
    private function find_order_by_reference($external_id, $operation_id = null) {
        // Спочатку намагаємося знайти за ID замовлення
        $order_id = intval(preg_replace('/[^0-9]/', '', $external_id));
        if ($order_id > 0) {
            $order = wc_get_order($order_id);
            if ($order && $order->get_payment_method() === 'rp_payparts') {
                return $order;
            }
        }
        
        // Пошук за мета-полем _rp_reference
        $orders = wc_get_orders(array(
            'limit' => 1,
            'return' => 'objects',
            'payment_method' => 'rp_payparts',
            'meta_key' => '_rp_reference',
            'meta_value' => $external_id,
            'meta_compare' => '=',
        ));
        
        if ($orders) {
            return current($orders);
        }
        
        // Пошук за operation_id, якщо є
        if ($operation_id) {
            $orders = wc_get_orders(array(
                'limit' => 1,
                'return' => 'objects',
                'payment_method' => 'rp_payparts',
                'meta_key' => '_rp_payparts_payment_id',
                'meta_value' => $operation_id,
                'meta_compare' => '=',
            ));
            
            if ($orders) {
                return current($orders);
            }
        }
        
        return null;
    }

    /**
     * НОВИЙ метод для обробки повернень через callback
     */
    private function process_callback_refund($order, $refund_amount, $details = array()) {
        $order_id = $order->get_id();
        $order_total = floatval($order->get_total());
        
        // Якщо сума повернення не вказана – повертаємо повну суму
        if ($refund_amount <= 0) {
            $refund_amount = $order_total;
        }
        
        error_log("PayParts Callback Refund: processing order #$order_id, amount=$refund_amount of $order_total");
        
        // Перевіряємо, чи ми вже обробляли це повернення
        $refund_id_key = isset($details['rrn']) ? $details['rrn'] : 'callback_' . current_time('timestamp');
        
        if ($order->get_meta('_rp_callback_refund_' . $refund_id_key, true)) {
            error_log("PayParts Callback Refund: already processed for key $refund_id_key");
            return;
        }
        
        // Отримуємо суму вже існуючих повернень
        $existing_refunds = $order->get_refunds();
        $total_refunded = 0;
        
        foreach ($existing_refunds as $existing_refund) {
            $total_refunded += abs($existing_refund->get_amount());
        }
        
        // Створюємо повернення тільки якщо потрібно
        $amount_to_refund = $refund_amount - $total_refunded;
        
        if ($amount_to_refund > 0) {
            $refund_id = wc_create_refund(array(
                'amount' => $amount_to_refund,
                'reason' => 'RozetkaPay PayParts refund (callback)',
                'order_id' => $order_id,
                'refund_payment' => false,
                'restock_items' => false,
            ));
            
            if ($refund_id && !is_wp_error($refund_id)) {
                error_log("PayParts Callback Refund: created WC_Refund #$refund_id for amount $amount_to_refund");
                
                // Зберігаємо RRN, якщо є
                if (isset($details['rrn']) && $details['rrn']) {
                    update_post_meta($refund_id, '_rp_refund_rrn', $details['rrn']);
                }
                
                // Позначаємо, що повернення оброблено
                $order->update_meta_data('_rp_callback_refund_' . $refund_id_key, current_time('mysql'));
                
                // Визначаємо статус замовлення
                $total_after_refund = $total_refunded + $amount_to_refund;
                if ($total_after_refund >= $order_total) {
                    $order->update_status('refunded', 'PayParts: повне повернення оброблено через callback');
                    error_log("PayParts Callback Refund: order #$order_id status changed to refunded");
                } else {
                    $order->add_order_note(sprintf(
                        'PayParts: часткове повернення %s грн через callback (всього повернено: %s грн)', 
                        $amount_to_refund, 
                        $total_after_refund
                    ));
                }
                
                $order->save();
            } else {
                error_log('PayParts Callback Refund: Failed to create WC_Refund: ' . 
                    (is_wp_error($refund_id) ? $refund_id->get_error_message() : 'unknown error'));
            }
        } else {
            error_log("PayParts Callback Refund: no new refund needed, already refunded $total_refunded");
            
            // У будь-якому разі позначаємо як оброблений
            $order->update_meta_data('_rp_callback_refund_' . $refund_id_key, current_time('mysql'));
            $order->save();
        }
    }

    /**
     * Показати інструкцію на thank you сторінці
     */
    public function thankyou_page_instruction( $order_id ) {
        $instruction_data = WC()->session->get( 'rp_show_mobile_instruction' );
        
        if ( ! $instruction_data || $instruction_data['order_id'] != $order_id ) {
            return;
        }
        
        // Підключаємо скрипт
        wp_enqueue_script( 
            'rp-mobile-instruction', 
            RP_PAYPARTS_PLUGIN_URL . 'assets/js/mobile-instruction.js', 
            array( 'jquery' ), 
            RP_PAYPARTS_VERSION, 
            true 
        );
        
        wp_localize_script( 'rp-mobile-instruction', 'rp_mobile_instruction', $instruction_data );
        
        // Очищаємо прапорець, щоб не показувати повторно
        WC()->session->set( 'rp_show_mobile_instruction', null );
        
        if ( $this->debug_mode ) {
            $order = wc_get_order( $order_id );
            if ( $order ) {
                $order->add_order_note( 'Показано інструкцію для мобільного застосунку: ' . $instruction_data['bank_name'] );
            }
        }
    }

    /**
     * Показати повідомлення в кошику
     */
    public function display_cart_message() {
        if ( 'yes' !== $this->enabled || ! WC()->cart || WC()->cart->is_empty() ) {
            return;
        }
        
        $cart_total = WC()->cart->total;
        
        // Перевіримо ліміти з реальних даних банків
        $limits = $this->get_banks_limits();
        
        if ( $limits['min_amount'] > 0 && $cart_total < $limits['min_amount'] ) {
            return;
        }
        
        if ( $limits['max_amount'] > 0 && $cart_total > $limits['max_amount'] ) {
            return;
        }
        
        // Отримуємо розрахунки
        $calculations = $this->get_cart_message_calculations( $cart_total );
        
        if ( ! $calculations ) {
            return;
        }
        
        // Формуємо повідомлення
        $message = '';
        
        // Якщо задано кастомне повідомлення – використовуємо його з заміною плейсхолдерів
        if ( ! empty( $this->cart_message ) ) {
            $message = $this->cart_message;
            
            // Замінюємо плейсхолдери на реальні значення
            $replacements = array(
                '{min}' => number_format( $calculations['min_payment'], 0, ',', ' ' ),
                '{max}' => number_format( $calculations['max_payment'], 0, ',', ' ' ),
                '{cart_total}' => number_format( $cart_total, 0, ',', ' ' ),
                '{min_term}' => $calculations['min_term'],
                '{max_term}' => $calculations['max_term'],
            );
            
            $message = str_replace( array_keys( $replacements ), array_values( $replacements ), $message );
        } else {
            // Створюємо базове повідомлення
            if ( $calculations['min_payment'] === $calculations['max_payment'] ) {
                // Якщо лише один варіант платежу
                $message = sprintf( 
                    __( 'Доступна оплата частинами — %s грн на місяць', 'rp-payparts' ),
                    number_format( $calculations['min_payment'], 0, ',', ' ' )
                );
            } else {
                // Якщо декілька варіантів
                $message = sprintf( 
                    __( 'Розстрочка від %s до %s грн/міс', 'rp-payparts' ),
                    number_format( $calculations['min_payment'], 0, ',', ' ' ),
                    number_format( $calculations['max_payment'], 0, ',', ' ' )
                );
            }
        }
        
        // Виводимо повідомлення
        if ( ! empty( $message ) ) {
            echo '<div class="woocommerce-info rp-payparts-cart-message">';
            echo ' ' . wp_kses_post( $message );
            echo '</div>';
        }
    }

    /**
     * Отримати розрахунки для повідомлення в кошику
     */
    private function get_cart_message_calculations( $cart_total ) {
        $options = $this->get_installment_options();
        
        if ( empty( $options ) ) {
            return null;
        }
        
        $all_terms = array();
        foreach ( $options as $key => $label ) {
            if ( strpos( $key, '|' ) !== false ) {
                list( $bank_slug, $term ) = explode( '|', $key );
                $term = intval( $term );
                if ( $term > 0 ) {
                    $all_terms[] = $term;
                }
            }
        }
        
        if ( empty( $all_terms ) ) {
            return null;
        }
        
        $all_terms = array_unique( $all_terms );
        sort( $all_terms );
        
        $min_term = min( $all_terms );
        $max_term = max( $all_terms );
        
        return array(
            'min_payment' => round( $cart_total / $max_term ),
            'max_payment' => round( $cart_total / $min_term ),
            'min_term' => $min_term,
            'max_term' => $max_term,
            'terms_count' => count( $all_terms ),
        );
    }

    /**
     * Підключення CSS-стилів для фронтенду
     */
    public function enqueue_frontend_styles() {
        if ( ! $this->is_available() ) {
            return;
        }

        if ( is_cart() || is_checkout() ) {
            wp_enqueue_style( 
                'rozetkapay-payparts-frontend', 
                RP_PAYPARTS_PLUGIN_URL . 'assets/css/frontend.css', 
                array(), 
                RP_PAYPARTS_VERSION 
            );
        }
    }

    /**
     * Ініціалізація полів форми
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => __( 'Увімкнути/Вимкнути', 'rp-payparts' ),
                'type'    => 'checkbox',
                'label'   => __( 'Увімкнути RozetkaPay оплату частинами', 'rp-payparts' ),
                'default' => 'no',
            ),
            'title' => array(
                'title'       => __( 'Назва методу', 'rp-payparts' ),
                'type'        => 'text',
                'description' => __( 'Назва методу оплати, яку бачить користувач під час оформлення замовлення.', 'rp-payparts' ),
                'default'     => __( 'Оплата частинами', 'rp-payparts' ),
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __( 'Опис на сторінці оформлення', 'rp-payparts' ),
                'type'        => 'textarea',
                'description' => __( 'Опис методу оплати, який відображається на сторінці оформлення замовлення.', 'rp-payparts' ),
                'default'     => __( 'Оберіть зручний план оплати частинами нижче.', 'rp-payparts' ),
                'desc_tip'    => true,
            ),
            
            'enable_status_polling' => array(
                'title'       => __( 'Включити переопитування статусів', 'rp-payparts' ),
                'type'        => 'checkbox',
                'label'       => __( 'Опитування Rozetka по payment info кожні 10 хвилин', 'rp-payparts' ),
                'default'     => 'yes',
                'desc_tip'    => true,
                'description' => __( 'Якщо вимкнено, крон-завдання пропускає замовлення цього методу.', 'rp-payparts' ),
            ),

            'order_ref_prefix' => array(
                'title'       => __( 'Префікс номера для Rozetka', 'rp-payparts' ),
                'type'        => 'text',
                'default'     => 'roz1',
                'desc_tip'    => true,
                'description' => __( 'Формат external_id: {prefix}-{номер замовлення}. Залишіть порожнім — лише номер.', 'rp-payparts' ),
            ),

            'api_section' => array(
                'title' => __( ' Налаштування API', 'rp-payparts' ),
                'type'  => 'title',
                'description' => __( 'Введіть ваші API ключі від RozetkaPay.', 'rp-payparts' ),
            ),
            'api_user' => array(
                'title'       => __( 'API Користувач', 'rp-payparts' ),
                'type'        => 'text',
                'description' => __( 'Ваш API логін від RozetkaPay', 'rp-payparts' ),
                'desc_tip'    => true,
            ),
            'api_pass' => array(
                'title'       => __( 'API Пароль', 'rp-payparts' ),
                'type'        => 'password',
                'description' => __( 'Ваш API пароль від RozetkaPay', 'rp-payparts' ),
                'desc_tip'    => true,
            ),
            
            'payment_section' => array(
                'title' => __( ' Налаштування платежів', 'rp-payparts' ),
                'type'  => 'title',
                'description' => __( 'Налаштування лімітів та умов платежів.', 'rp-payparts' ),
            ),
            'min_amount' => array(
                'title'       => __( 'Мінімальна сума', 'rp-payparts' ),
                'type'        => 'number',
                'description' => __( 'Мінімальна сума замовлення для розстрочки (0 = використовувати ліміти API)', 'rp-payparts' ),
                'default'     => '0',
                'desc_tip'    => true,
                'custom_attributes' => array(
                    'step' => '0.01',
                    'min'  => '0',
                ),
            ),
            'max_amount' => array(
                'title'       => __( 'Максимальна сума', 'rp-payparts' ),
                'type'        => 'number',
                'description' => __( 'Максимальна сума замовлення для розстрочки (0 = використовувати ліміти API)', 'rp-payparts' ),
                'default'     => '0',
                'desc_tip'    => true,
                'custom_attributes' => array(
                    'step' => '0.01',
                    'min'  => '0',
                ),
            ),
            'allowed_banks' => array(
                'title'       => __( 'Дозволені банки', 'rp-payparts' ),
                'type'        => 'text',
                'description' => __( 'Список банків через кому (залишіть порожнім для всіх доступних банків):', 'rp-payparts' ) . '<br>' .
                                 '<div class="rp-banks-list">' .
                                 '<div class="rp-bank-item"><code>abank</code> — ABank</div>' .
                                 '<div class="rp-bank-item"><code>monobank</code> — Monobank</div>' .
                                 '<div class="rp-bank-item"><code>privatbank</code> — ПриватБанк</div>' .
                                 '<div class="rp-bank-item"><code>rozetkapay</code> — RozetkaPay</div>' .
                                 '<div class="rp-bank-item"><code>izibank</code> — izibank</div>' .
                                 '</div>' .
                                 '<p class="rp-example"><strong>Приклад:</strong> <code>abank,rozetkapay,privatbank,monobank</code></p>',
                'desc_tip'    => false,
                'placeholder' => 'rozetkapay,privatbank,monobank',
            ),
            'allowed_terms' => array(
                'title'       => __( 'Дозволені терміни', 'rp-payparts' ),
                'type'        => 'text',
                'description' => __( 'Список термінів в місяцях через кому (залишіть порожнім для всіх доступних термінів):', 'rp-payparts' ) . '<br>' .
                                 '<div class="rp-terms-list">' .
                                 '<div class="rp-term-item"><code>2</code> — 2 місяці</div>' .
                                 '<div class="rp-term-item"><code>3</code> — 3 місяці</div>' .
                                 '<div class="rp-term-item"><code>6</code> — 6 місяців</div>' .
                                 '<div class="rp-term-item"><code>12</code> — 12 місяців</div>' .
                                 '<div class="rp-term-item"><code>24</code> — 24 місяці</div>' .
                                 '</div>' .
                                 '<p class="rp-example"><strong>Приклад:</strong> <code>3,6,12,24</code> <em>(допустимі: від 2 до 25 місяців)</em></p>',
                'desc_tip'    => false,
                'placeholder' => '3,6,12,24',
            ),
            'display_section' => array(
                'title' => __( ' Налаштування відображення', 'rp-payparts' ),
                'type'  => 'title',
                'description' => __( 'Налаштування зовнішнього вигляду.', 'rp-payparts' ),
            ),
            'display_style' => array(
                'title'       => __( 'Стиль відображення', 'rp-payparts' ),
                'type'        => 'select',
                'description' => __( 'Оберіть спосіб відображення варіантів оплати', 'rp-payparts' ),
                'default'     => 'slider',
                'desc_tip'    => true,
                'options'     => array(
                    'cards'  => __( 'Картки (класичний)', 'rp-payparts' ),
                    'slider' => __( 'Повзунок (сучасний)', 'rp-payparts' ),
                    'list'   => __( 'Список (компактний)', 'rp-payparts' ),
                ),
            ),
            'cart_message' => array(
                'title'       => __( 'Повідомлення в кошику', 'rp-payparts' ),
                'type'        => 'textarea',
                'description' => __( 'Повідомлення, що показується в кошику. Доступні плейсхолдери:', 'rp-payparts' ) . '<br>' .
                                 '<div class="rp-placeholders-list">' .
                                 '<span class="rp-placeholder"><code>{min}</code> — мінімальний щомісячний платіж</span>' .
                                 '<span class="rp-placeholder"><code>{max}</code> — максимальний щомісячний платіж</span>' .
                                 '<span class="rp-placeholder"><code>{cart_total}</code> — загальна сума кошика</span>' .
                                 '<span class="rp-placeholder"><code>{min_term}</code> — мінімальний термін</span>' .
                                 '<span class="rp-placeholder"><code>{max_term}</code> — максимальний термін</span>' .
                                 '</div>',
                'desc_tip'    => false,
                'placeholder' => 'Розстрочка від {min} до {max} грн/міс',
            ),
            'checkout_message' => array(
                'title'       => __( 'Додаткове повідомлення на checkout', 'rp-payparts' ),
                'type'        => 'textarea',
                'description' => __( 'Додаткова інформація на сторінці оформлення замовлення. Показується під основним описом методу оплати.', 'rp-payparts' ),
                'desc_tip'    => false,
            ),
            'debug_section' => array(
                'title' => __( ' Налаштування відладки', 'rp-payparts' ),
                'type'  => 'title',
                'description' => __( 'Додаткова інформація для відладки роботи плагіна.', 'rp-payparts' ),
            ),
            'debug_mode' => array(
                'title'       => __( 'Режим відладки', 'rp-payparts' ),
                'type'        => 'checkbox',
                'label'       => __( 'Увімкнути детальне логування в замітки замовлень', 'rp-payparts' ),
                'description' => __( 'Включіть для діагностики проблем з перенаправленням та API запитами', 'rp-payparts' ),
                'default'     => 'no',
                'desc_tip'    => true,
            ),
        
            'auto_confirm' => array(
                'title'       => __( 'Автопідтвердження платежу', 'rp-payparts' ),
                'type'        => 'checkbox',
                'label'       => __( 'Автоматично підтверджувати оплату після підписання договору клієнтом', 'rp-payparts' ),
                'default'     => 'no',
                'description' => __( 'Якщо увімкнено, статус у кабінеті RozetkaPay перейде з «Блокування» у «Оплата» без вашого ручного підтвердження.', 'rp-payparts' ),
                'desc_tip'    => true,
            ),
        );
    }

    /**
     * Створення API-клієнта
     */
    private function get_api_client() {
        if ( ! isset( $this->api_client ) ) {
            $this->api_client = new RP_Payparts_API( $this->api_user, $this->api_pass, $this->test_mode );
        }
        return $this->api_client;
    }

    /**
     * Валідація періоду для конкретного банку
     */
    private function validate_bank_period( $bank_slug, $period ) {
        $bank_min_periods = array(
            'abank' => 2,           
            'monobank' => 3,
            'izibank' => 3,
            'privatbank' => 2,
            'rozetkapay' => 2,
        );
        
        $min_period = isset( $bank_min_periods[ $bank_slug ] ) ? $bank_min_periods[ $bank_slug ] : 2;
        
        return $period >= $min_period && $period <= 25;
    }

    /**
     * Отримати варіанти розстрочки з фільтрацією за лімітами банків
     */
    private function get_installment_options() {
        // Отримуємо поточну суму кошика
        $cart_total = WC()->cart ? WC()->cart->total : 0;
        
        if ( $this->test_mode ) {
            // У тестовому режимі застосовуємо фільтрацію до статичних даних
            $test_options = array(
                'abank|2' => array('label' => 'ABank — 2 місяці', 'min_amount' => 1000),      
                'abank|3' => array('label' => 'ABank — 3 місяці', 'min_amount' => 1000),
                'abank|6' => array('label' => 'ABank — 6 місяців', 'min_amount' => 1000),
                'abank|12' => array('label' => 'ABank — 12 місяців', 'min_amount' => 1000),
                'monobank|3' => array('label' => 'Monobank — 3 місяці', 'min_amount' => 1),
                'monobank|6' => array('label' => 'Monobank — 6 місяців', 'min_amount' => 1),
                'monobank|12' => array('label' => 'Monobank — 12 місяців', 'min_amount' => 1),
                'privatbank|2' => array('label' => 'ПриватБанк — 2 місяці', 'min_amount' => 500),
                'privatbank|3' => array('label' => 'ПриватБанк — 3 місяці', 'min_amount' => 500),
                'privatbank|6' => array('label' => 'ПриватБанк — 6 місяців', 'min_amount' => 500),
                'privatbank|12' => array('label' => 'ПриватБанк — 12 місяців', 'min_amount' => 500),
                'rozetkapay|2' => array('label' => 'RozetkaPay — 2 місяці', 'min_amount' => 200),
                'rozetkapay|3' => array('label' => 'RozetkaPay — 3 місяці', 'min_amount' => 200),
                'rozetkapay|6' => array('label' => 'RozetkaPay — 6 місяців', 'min_amount' => 200),
                'rozetkapay|12' => array('label' => 'RozetkaPay — 12 місяців', 'min_amount' => 200),
                'izibank|3' => array('label' => 'izibank — 3 місяці', 'min_amount' => 800),
                'izibank|6' => array('label' => 'izibank — 6 місяців', 'min_amount' => 800),
                'izibank|12' => array('label' => 'izibank — 12 місяців', 'min_amount' => 800),
            );
            
            $filtered_options = array();
            foreach ( $test_options as $key => $data ) {
                if ( $cart_total >= $data['min_amount'] ) {
                    $filtered_options[$key] = $data['label'];
                }
            }
            
            if ( $this->debug_mode && $cart_total > 0 ) {
                error_log( "RozetkaPay: Test mode filtering - cart total: {$cart_total}, filtered options: " . count( $filtered_options ) );
            }
            
            return $filtered_options;
        }

        // Прагнемо дістати з кешу
        $banks = get_transient( 'rp_payparts_banks' );
        
        if ( false === $banks ) {
            // Завантажуємо з API
            $api = $this->get_api_client();
            $banks = $api->fetch_banks();
            
            if ( ! empty( $banks ) && ! is_wp_error( $banks ) ) {
                set_transient( 'rp_payparts_banks', $banks, HOUR_IN_SECONDS );
            } else {
                return array();
            }
        }

        $options = array();
        
        if ( is_array( $banks ) ) {
            foreach ( $banks as $bank ) {
                if ( ! isset( $bank['name'] ) ) {
                    continue;
                }
                
                $slug = sanitize_title( $bank['name'] );
                
                // Фільтр за дозволеними банками
                if ( ! empty( $this->allowed_banks ) && ! in_array( $slug, $this->allowed_banks, true ) ) {
                    continue;
                }
                
                // Отримуємо ліміти банку
                $bank_min_amount = 0;
                $bank_max_amount = 0;
                
                if ( isset( $bank['limits'] ) && is_array( $bank['limits'] ) ) {
                    $bank_min_amount = isset( $bank['limits']['min_amount'] ) ? floatval( $bank['limits']['min_amount'] ) : 0;
                    $bank_max_amount = isset( $bank['limits']['max_amount'] ) ? floatval( $bank['limits']['max_amount'] ) : 0;
                }
                
                // КРИТИЧНА ПЕРЕВІРКА: пропускаємо банк, якщо сума кошика менша за мінімум
                if ( $bank_min_amount > 0 && $cart_total < $bank_min_amount ) {
                    if ( $this->debug_mode ) {
                        error_log( "RozetkaPay: Bank {$slug} filtered out - cart total {$cart_total} < min {$bank_min_amount}" );
                    }
                    continue;
                }
                
                // Перевіряємо максимум також
                if ( $bank_max_amount > 0 && $cart_total > $bank_max_amount ) {
                    if ( $this->debug_mode ) {
                        error_log( "RozetkaPay: Bank {$slug} filtered out - cart total {$cart_total} > max {$bank_max_amount}" );
                    }
                    continue;
                }
                
                // Обробляємо періоди
                $periods = array();
                
                if ( ! empty( $bank['periods'] ) && is_array( $bank['periods'] ) ) {
                    foreach ( $bank['periods'] as $period_data ) {
                        if ( isset( $period_data['period'] ) ) {
                            $period = intval( $period_data['period'] );
                            
                            if ( $this->validate_bank_period( $slug, $period ) ) {
                                $periods[] = $period;
                            }
                        }
                    }
                }

                foreach ( $periods as $period ) {
                    $period = intval( $period );
                    
                    // Фільтр за дозволеними термінами
                    if ( ! empty( $this->allowed_terms ) && ! in_array( $period, $this->allowed_terms, true ) ) {
                        continue;
                    }
                    
                    if ( $period > 0 ) {
                        $bank_name = rp_payparts_get_bank_name( $slug );
                        
                        $label = sprintf( '%s — %d %s', 
                            esc_html( $bank_name ), 
                            $period, 
                            $this->get_month_declension( $period )
                        );
                        
                        $options[ "$slug|$period" ] = $label;
                    }
                }
            }
        }
        
        if ( $this->debug_mode && $cart_total > 0 ) {
            error_log( "RozetkaPay: Production mode filtering - cart total: {$cart_total}, filtered options: " . count( $options ) );
        }
        
        return $options;
    }

    /**
     * Отримати правильне відмінювання слова «місяць»
     */
    private function get_month_declension( $number ) {
        return rp_payparts_get_month_declension( $number );
    }

    /**
     * Поля оплати на сторінці checkout
     */
    public function payment_fields() {
        // Основний опис
        if ( $this->description ) {
            echo wpautop( wp_kses_post( $this->description ) );
        }
        
        // Додаткове повідомлення на checkout
        if ( $this->checkout_message ) {
            echo '<div class="rp-checkout-message">' . wpautop( wp_kses_post( $this->checkout_message ) ) . '</div>';
        }
        
        // Статус налаштування
        if ( empty( $this->api_user ) || empty( $this->api_pass ) ) {
            echo '<div class="rp-payparts-warning">';
            echo '<p><strong>⚠️ Потрібне налаштування</strong></p>';
            echo '<p>Адміністратору: Будь ласка, налаштуйте API ключі в налаштуваннях плагіна.</p>';
            echo '</div>';
            return;
        }
        
        // Отримуємо варіанти оплати
        $options = $this->get_installment_options();
        
        if ( empty( $options ) ) {
            echo '<div class="rp-payparts-warning">';
            echo '<p><strong>⚠️ Немає доступних варіантів</strong></p>';
            if ( WC()->cart && WC()->cart->total > 0 ) {
                $cart_total = WC()->cart->total;
                echo '<p>Сума замовлення: <strong>' . number_format( $cart_total, 0, ',', ' ' ) . ' грн</strong></p>';
                echo '<p>Перевірте мінімальні ліміти банків або зверніться до адміністратора.</p>';
            }
            echo '</div>';
            return;
        }
        
        // Nonce-поле для безпеки
        wp_nonce_field( 'rp_payparts_nonce', 'rp_payparts_nonce_field' );
        
        // Вибір стилю відображення
        if ( 'slider' === $this->display_style ) {
            $this->render_slider_interface( $options );
        } elseif ( 'list' === $this->display_style ) {
            $this->render_list_interface( $options );
        } else {
            $this->render_cards_interface( $options );
        }
    }

    /**
     * Рендер інтерфейсу карток
     */
    private function render_cards_interface( $options ) {
        echo '<div class="rp-payparts-container">';
        echo '<div class="rp-payparts-header">';
        echo '<span class="rp-payparts-icon"> </span>';
        echo '<h4>' . __( 'Оберіть план оплати частинами', 'rp-payparts' ) . '</h4>';
        echo '</div>';
        
        echo '<div class="rp-payparts-options">';
        foreach ( $options as $key => $label ) {
            $this->render_payment_option( $key, $label );
        }
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * Рендер інтерфейсу повзунка
     */
    private function render_slider_interface( $options ) {
        $css_path = RP_PAYPARTS_PLUGIN_PATH . 'assets/css/slider.css';
        $js_path  = RP_PAYPARTS_PLUGIN_PATH . 'assets/js/slider.js';
        
        // ПРИМУСОВО підключаємо стилі і скрипти
        wp_enqueue_style( 
            'rozetkapay-payparts-slider', 
            RP_PAYPARTS_PLUGIN_URL . 'assets/css/slider.css', 
            array(), 
            RP_PAYPARTS_VERSION
        );
        
        wp_enqueue_script( 
            'rozetkapay-payparts-slider', 
            RP_PAYPARTS_PLUGIN_URL . 'assets/js/slider.js', 
            array( 'jquery' ), 
            RP_PAYPARTS_VERSION,
            true 
        );
        
        // Групуємо за банками та їх термінами З ВАЛІДАЦІЄЮ
        $banks_data = array();
        $all_terms = array();

        foreach ( $options as $key => $label ) {
            list( $bank_slug, $term ) = explode( '|', $key );
            $term = intval( $term );
            
            // КРИТИЧНО ВАЖЛИВО: пропускаємо неприпустимі періоди для повзунка
            if ( ! $this->validate_bank_period( $bank_slug, $term ) ) {
                continue;
            }
            
            if ( ! isset( $banks_data[ $bank_slug ] ) ) {
                $bank_name_parts = explode( ' — ', $label );
                $bank_name = $bank_name_parts[0];
                $banks_data[ $bank_slug ] = array(
                    'name' => $bank_name,
                    'slug' => $bank_slug,
                    'terms' => array()
                );
            }
            
            $banks_data[ $bank_slug ]['terms'][ $term ] = $key;
            $all_terms[] = $term;
        }

        // Видаляємо банки без доступних періодів
        $banks_data = array_filter( $banks_data, function( $bank ) {
            return ! empty( $bank['terms'] );
        } );

        // Отримуємо унікальні терміни та сортуємо
        $unique_terms = array_unique( $all_terms );
        sort( $unique_terms );

        // Перевіряємо, що маємо дані
        if ( empty( $banks_data ) || empty( $unique_terms ) ) {
            echo '<div class="rp-payparts-warning">';
            echo '<p>Немає доступних варіантів для вибраного банку</p>';
            echo '</div>';
            return;
        }
        
        $cart_total = WC()->cart ? WC()->cart->total : 1000;
        
        // Готуємо дані для JavaScript
        wp_localize_script( 'rozetkapay-payparts-slider', 'rozetkapay_slider_data', array(
            'banks' => $banks_data,
            'terms' => $unique_terms,  
            'cart_total' => $cart_total,
            'currency' => get_woocommerce_currency(),
            'plugin_url' => RP_PAYPARTS_PLUGIN_URL,
            'messages' => array(
                'months' => __( 'місяців', 'rp-payparts' ),
                'per_month' => __( 'грн/міс', 'rp-payparts' ),
                'select_bank' => __( 'Оберіть банк', 'rp-payparts' ),
                'not_available' => __( 'Недоступно', 'rp-payparts' ),
                'available' => __( 'Доступно', 'rp-payparts' )
            )
        ) );
        
        // Значення за замовчуванням
        $bank_keys = array_keys( $banks_data );
        $default_bank = $bank_keys[0];
        $default_term = $unique_terms[0];
        $default_key = isset( $banks_data[ $default_bank ]['terms'][ $default_term ] ) ? $banks_data[ $default_bank ]['terms'][ $default_term ] : '';
        $monthly_payment = round( $cart_total / $default_term );
        
        ?>
        <div class="rp-payparts-slider-container">
            
            <!-- Вибір банку -->
            <?php if ( count( $banks_data ) > 1 ) : ?>
                <div class="rp-bank-selector">
                    <h4><?php esc_html_e( 'Оберіть банк', 'rp-payparts' ); ?></h4>
                    <div class="rp-bank-options">
                        <?php foreach ( $banks_data as $bank_slug => $bank_info ) : ?>
                            <label class="rp-bank-option <?php echo $bank_slug === $default_bank ? 'active' : ''; ?>" data-bank="<?php echo esc_attr( $bank_slug ); ?>">
                                <input type="radio" 
                                       name="rp_selected_bank" 
                                       value="<?php echo esc_attr( $bank_slug ); ?>"
                                       <?php checked( $bank_slug, $default_bank ); ?>>
                                <div class="rp-bank-card">
                                    <div class="rp-bank-logo">
                                        <img src="<?php echo esc_url( RP_PAYPARTS_PLUGIN_URL . 'assets/images/' . $bank_slug . '-logo.png' ); ?>" 
                                             alt="<?php echo esc_attr( $bank_info['name'] ); ?>" 
                                             onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                        <div class="rp-bank-fallback" style="display: none;">
                                            <?php echo esc_html( mb_substr( $bank_info['name'], 0, 2 ) ); ?>
                                        </div>
                                    </div>
                                    <div class="rp-bank-name"><?php echo esc_html( $bank_info['name'] ); ?></div>
                                    <div class="rp-bank-terms">
                                        <?php 
                                        $bank_term_labels = array();
                                        foreach ( $bank_info['terms'] as $term => $key ) {
                                            $bank_term_labels[] = $term . ' ' . $this->get_month_declension( $term );
                                        }
                                        echo esc_html( implode( ', ', $bank_term_labels ) );
                                        ?>
                                    </div>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Слайдер термінів -->
            <div class="rp-slider-section">
                <div class="rp-slider-header">
                    <h4 class="rp-slider-title">
                        <?php esc_html_e( 'Термін розстрочки', 'rp-payparts' ); ?>
                    </h4>
                    <div class="rp-slider-value">
                        <span class="rp-slider-months"><?php echo esc_html( $default_term ); ?></span>
                        <span><?php echo esc_html( $this->get_month_declension( $default_term ) ); ?></span>
                        <span class="rp-slider-payment"><?php echo number_format( $monthly_payment, 0, ',', ' ' ); ?> грн/міс</span>
                    </div>
                </div>
                
                <div class="rp-payparts-slider-wrapper">
                    <input type="range" 
                           class="rp-payparts-slider" 
                           id="rp-slider-range"
                           min="0" 
                           max="<?php echo count( $unique_terms ) - 1; ?>" 
                           step="1" 
                           value="0">
                    
                    <!-- Шкала з правильним позиціонуванням -->
                    <div class="rp-slider-track">
                        <?php 
                        $terms_count = count( $unique_terms );
                        foreach ( $unique_terms as $index => $term ) : 
                            $position = $terms_count > 1 ? ( $index / ( $terms_count - 1 ) ) * 100 : 50;
                        ?>
                            <div class="rp-slider-mark" style="left: <?php echo $position; ?>%;">
                                <div class="rp-slider-mark-line"></div>
                                <div class="rp-slider-mark-label <?php echo 0 === $index ? 'active' : ''; ?>">
                                    <?php echo esc_html( $term ); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Інформація про вибраний банк -->
                <div class="rp-current-bank-info">
                    <div class="rp-bank-logo">
                        <img src="<?php echo esc_url( RP_PAYPARTS_PLUGIN_URL . 'assets/images/' . $default_bank . '-logo.png' ); ?>" 
                             alt="Bank Logo" 
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-block';">
                        <span class="rp-bank-fallback" style="display: none;">
                            <?php echo esc_html( mb_substr( $banks_data[ $default_bank ]['name'], 0, 2 ) ); ?>
                        </span>
                    </div>
                    <div>
                        <div class="rp-current-bank-name"><?php echo esc_html( $banks_data[ $default_bank ]['name'] ); ?></div>
                        <div class="rp-bank-features">
                            <span class="rp-bank-feature available"><?php esc_html_e( 'Доступно', 'rp-payparts' ); ?></span>
                            <span class="rp-bank-feature"><?php esc_html_e( 'Без переплат', 'rp-payparts' ); ?></span>
                            <span class="rp-bank-feature"><?php esc_html_e( 'Швидке оформлення', 'rp-payparts' ); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Приховане поле для передачі вибраного значення -->
            <input type="hidden" name="rp_payparts_option" value="<?php echo esc_attr( $default_key ); ?>" required>
        </div>
        <?php
    }
    
    /**
     * Рендер інтерфейсу списку
     */
    private function render_list_interface( $options ) {
        echo '<div class="rp-payparts-list-container">';
        echo '<h4>Оберіть план оплати частинами</h4>';
        echo '<div class="rp-payparts-list">';
        foreach ( $options as $key => $label ) {
            echo '<label class="rp-payparts-list-item">';
            echo '<input type="radio" name="rp_payparts_option" value="' . esc_attr( $key ) . '" required>';
            echo '<span>' . esc_html( $label ) . '</span>';
            echo '</label>';
        }
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * Рендер однієї картки вибору
     */
    private function render_payment_option( $key, $label ) {
        echo '<label class="rp-payparts-option" for="rp_option_' . esc_attr( $key ) . '">';
        echo '<input type="radio" id="rp_option_' . esc_attr( $key ) . '" name="rp_payparts_option" value="' . esc_attr( $key ) . '" required>';
        echo '<div class="rp-option-content">';
        echo '<span class="rp-bank-name">' . esc_html( $label ) . '</span>';
        echo '</div>';
        echo '<div class="rp-option-check">✓</div>';
        echo '</label>';
    }

    /**
     * Зберігаємо вибір клієнта
     */
    public function store_selection( $order_id ) {
        if ( ! isset( $_POST['rp_payparts_option'] ) || ! isset( $_POST['rp_payparts_nonce_field'] ) ) {
            return;
        }
        
        if ( ! wp_verify_nonce( wp_unslash( $_POST['rp_payparts_nonce_field'] ), 'rp_payparts_nonce' ) ) {
            return;
        }
        
        $option = sanitize_text_field( wp_unslash( $_POST['rp_payparts_option'] ) );
        
        if ( empty( $option ) || ! strpos( $option, '|' ) ) {
            return;
        }
        
        $options = $this->get_installment_options();
        if ( ! isset( $options[ $option ] ) ) {
            return;
        }
        
        update_post_meta( $order_id, '_rp_payparts_option', $option );
        
        if ( isset( $_POST['rp_selected_bank'] ) ) {
            $selected_bank = sanitize_text_field( wp_unslash( $_POST['rp_selected_bank'] ) );
            update_post_meta( $order_id, '_rp_payparts_selected_bank', $selected_bank );
        }
        
        if ( $this->debug_mode ) {
            $order = wc_get_order( $order_id );
            if ( $order ) {
                $order->add_order_note( sprintf( 
                    'RozetkaPay Debug: Збережено вибір – %s', 
                    $option 
                ) );
            }
        }
    }

    /**
     * Обробка платежу
     */
    public function process_payment( $order_id ) {
        if ( $this->debug_mode ) {
            error_log( 'RozetkaPay DEBUG: process_payment() викликано для замовлення ' . $order_id );
        }
        
        $order = wc_get_order( $order_id );
        // Переконуємось, що external_id (reference) збережено для PayParts
        if ( function_exists('rp_build_reference') && isset( $order ) && $order ) {
            $external_id_tmp = $order->get_meta( '_rp_reference', true );
            if ( empty( $external_id_tmp ) ) {
                $external_id_tmp = rp_build_reference( $order, $this->order_ref_prefix );
            }
            // необов’язковий окремий ключ для зв’язку payparts
            $order->update_meta_data( '_rp_payparts_external_id', $external_id_tmp );
            $order->save();
        }

        if ( function_exists('rp_build_reference') && $order ) { rp_build_reference( $order, $this->order_ref_prefix ); }
        
        if ( ! $order ) {
            wc_add_notice( __( 'Помилка отримання замовлення.', 'rp-payparts' ), 'error' );
            return array( 'result' => 'fail' );
        }
        
        if ( $this->debug_mode ) {
            $order->add_order_note( sprintf( 
                'RozetkaPay: Розпочато обробку платежу для замовлення #%s', 
                $order_id 
            ) );
        }
        
        $selection = get_post_meta( $order_id, '_rp_payparts_option', true );
        
        if ( ! $selection ) {
            wc_add_notice( __( 'Будь ласка, оберіть варіант оплати частинами.', 'rp-payparts' ), 'error' );
            return array( 'result' => 'fail' );
        }
        
        if ( ! strpos( $selection, '|' ) ) {
            wc_add_notice( __( 'Некоректний вибір оплати частинами.', 'rp-payparts' ), 'error' );
            return array( 'result' => 'fail' );
        }
        
        $selection_parts = explode( '|', $selection );
        if ( count( $selection_parts ) !== 2 ) {
            wc_add_notice( __( 'Некоректний формат вибору оплати.', 'rp-payparts' ), 'error' );
            return array( 'result' => 'fail' );
        }
        
        list( $bank_id, $term ) = $selection_parts;
        
        if ( empty( $bank_id ) || empty( $term ) || ! is_numeric( $term ) ) {
            wc_add_notice( __( 'Некоректні дані оплати частинами.', 'rp-payparts' ), 'error' );
            return array( 'result' => 'fail' );
        }
        
        $options = $this->get_installment_options();
        if ( ! isset( $options[ $selection ] ) ) {
            wc_add_notice( __( 'Вибраний варіант оплати недоступний.', 'rp-payparts' ), 'error' );
            return array( 'result' => 'fail' );
        }
        
        if ( $this->debug_mode ) {
            $order->add_order_note( sprintf( 
                'RozetkaPay Debug: Обрано варіант %s, банк: %s, термін: %s міс.', 
                $selection, $bank_id, $term 
            ) );
        }
        
        // Тестовий режим
        if ( $this->test_mode ) {
            $order->update_status( 'on-hold', __( 'Тестова оплата частинами створена', 'rp-payparts' ) );
            
            update_post_meta( $order_id, '_rp_payparts_bank', $bank_id );
            update_post_meta( $order_id, '_rp_payparts_term', $term );
            update_post_meta( $order_id, '_rp_payparts_test_mode', 'yes' );
            
            WC()->cart->empty_cart();
            
            if ( 'rozetkapay' === $bank_id ) {
                $test_payment_url = 'https://checkout-credits.rozetkapay.com/app/test/demo-payment-' . $order_id;
                
                $order->add_order_note( '🚀 RozetkaPay TEST: ПРИМУСОВЕ перенаправлення на: ' . $test_payment_url );
                
                update_post_meta( $order_id, '_rp_payparts_test_redirect_url', $test_payment_url );
                
                return array(
                    'result' => 'success',
                    'redirect' => $test_payment_url,
                );
            }
            
            if ( $this->debug_mode ) {
                $bank_name = rp_payparts_get_bank_name( $bank_id );
                $order->add_order_note( sprintf( 
                    'Банк %s – залишаємося на thank you (тестовий режим)', 
                    $bank_name 
                ) );
            }
            
            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url( $order ),
            );
        }
        
        return $this->process_real_payment( $order, $bank_id, $term );
    }

    /**
     * Обробка реального платежу
     */
    private function process_real_payment( $order, $bank_id, $term ) {
        try {
            $api = $this->get_api_client();
            
            if ( ! $api ) {
                throw new Exception( __( 'Помилка ініціалізації API клієнта', 'rp-payparts' ) );
            }
            
            $payment_data = $this->prepare_payment_data( $order, $bank_id, $term );
            $result = $api->create_payment( $payment_data );
            
            if ( is_wp_error( $result ) ) {
                throw new Exception( $result->get_error_message() );
            }
            
            if ( ! is_array( $result ) || ! isset( $result['details'] ) ) {
                throw new Exception( __( 'Некоректна відповідь від сервера', 'rp-payparts' ) );
            }
            
            if ( ! isset( $result['details']['status_code'] ) ) {
                throw new Exception( __( 'Відсутній статус у відповіді сервера', 'rp-payparts' ) );
            }
            
            $status = $result['details']['status_code'];
            $redirect_statuses = array( 'pending', 'waiting_for_redirect', 'created' );
            
            if ( ! in_array( $status, $redirect_statuses, true ) ) {
                $error_message = isset( $result['details']['status_description'] ) 
                    ? $result['details']['status_description'] 
                    : __( 'Не вдалося створити платіж частинами', 'rp-payparts' );
                
                throw new Exception( $error_message );
            }
            
            if ( isset( $result['details']['operation_id'] ) ) {
                update_post_meta( $order->get_id(), '_rp_payparts_payment_id', $result['details']['operation_id'] );
            }
            
            if ( isset( $result['action']['value'] ) ) {
                update_post_meta( $order->get_id(), '_rp_payparts_payment_url', $result['action']['value'] );
            }
            
            update_post_meta( $order->get_id(), '_rp_payparts_bank', $bank_id );
            update_post_meta( $order->get_id(), '_rp_payparts_term', $term );
            
            $order->update_status( 'on-hold', __( 'Очікується оплата частинами через RozetkaPay', 'rp-payparts' ) );
            
            WC()->cart->empty_cart();
            
            if ( 'rozetkapay' === $bank_id ) {
                if ( isset( $result['action']['value'] ) && isset( $result['action']['type'] ) && $result['action']['type'] === 'url' ) {
                    $redirect_url = $result['action']['value'];
                    
                    if ( $this->debug_mode ) {
                        $order->add_order_note( 'RozetkaPay: Перенаправлення на сторінку оплати: ' . $redirect_url );
                    }
                    
                    return array( 
                        'result' => 'success', 
                        'redirect' => esc_url_raw( $redirect_url )
                    );
                }
            } elseif ( in_array( $bank_id, array( 'privatbank', 'monobank', 'abank' ), true ) ) {
                $bank_name = rp_payparts_get_bank_name( $bank_id );
                
                WC()->session->set( 'rp_show_mobile_instruction', array(
                    'bank_id' => $bank_id,
                    'bank_name' => $bank_name,
                    'order_id' => $order->get_id()
                ) );
                
                if ( $this->debug_mode ) {
                    $order->add_order_note( sprintf( 
                        'Банк %s: встановлено прапорець для показу мобільної інструкції', 
                        $bank_name 
                    ) );
                }
            } else {
                if ( $this->debug_mode ) {
                    $bank_name = rp_payparts_get_bank_name( $bank_id );
                    $order->add_order_note( sprintf( 
                        'Банк %s: перехід на thank you сторінку (новий банк)', 
                        $bank_name 
                    ) );
                }
            }
            
            return array( 
                'result' => 'success', 
                'redirect' => $this->get_return_url( $order ) 
            );
            
        } catch ( Exception $e ) {
            if ( $this->debug_mode ) {
                $order->add_order_note( 'RozetkaPay Error: ' . $e->getMessage() );
            }
            
            wc_add_notice( 
                sprintf( __( 'Помилка створення платежу: %s', 'rp-payparts' ), $e->getMessage() ), 
                'error' 
            );
            
            return array( 'result' => 'fail' );
        }
    }

    /**
     * Підготовка даних для API
     */
    private function prepare_payment_data( $order, $bank_id, $term ) {
        $products = array();
        $total_amount = 0;
        
        foreach ( $order->get_items() as $item ) {
            $line_total = $item->get_subtotal();
            $total_amount += $line_total;
            
            $products[] = array(
                'name' => $item->get_name(),
                'quantity' => intval( $item->get_quantity() ),
                'price' => floatval( $line_total ),
            );
        }
        
        $shipping_total = $order->get_shipping_total();
        if ( $shipping_total > 0 ) {
            $total_amount += $shipping_total;
            $products[] = array(
                'name' => __( 'Доставка', 'rp-payparts' ),
                'quantity' => 1,
                'price' => floatval( $shipping_total ),
            );
        }
        
        $tax_total = $order->get_total_tax();
        if ( $tax_total > 0 ) {
            $total_amount += $tax_total;
            $products[] = array(
                'name' => __( 'Податки', 'rp-payparts' ),
                'quantity' => 1,
                'price' => floatval( $tax_total ),
            );
        }
        
        $order_total = $order->get_total();
        if ( abs( $total_amount - $order_total ) > 0.01 ) {
            $total_amount = $order_total;
        }
        
        if ( $this->debug_mode ) {
            $order->add_order_note( sprintf( 
                'RozetkaPay Debug: Сума для API – %.2f UAH (товари: %.2f, доставка: %.2f, податки: %.2f)', 
                $total_amount, 
                $order->get_subtotal(),
                $shipping_total,
                $tax_total
            ) );
        }
        
        return array(
            'bank_name' => $bank_id,
            'mode' => 'direct',
            'auto_confirm_after_success' => ( $this->auto_confirm === 'yes' ),
            'external_id' => function_exists('rp_build_reference') ? rp_build_reference( $order, $this->order_ref_prefix ) : (string) $order->get_id(),
            'currency' => $order->get_currency(),
            'parts_count' => intval( $term ),
            'amount' => floatval( $total_amount ),
            'description' => sprintf( 
                __( 'Оплата замовлення №%s на %s частин', 'rp-payparts' ), 
                $order->get_order_number(),
                $term
            ),
            'customer' => array(
                'first_name' => $order->get_billing_first_name(),
                'last_name' => $order->get_billing_last_name(),
                'email' => $order->get_billing_email(),
                'phone' => $this->format_phone( $order->get_billing_phone() ),
            ),
            'products' => $products,
            'callback_url' => home_url( '/wc-api/' . $this->id ),
            'success_url' => $this->get_return_url( $order ),
            'failure_url' => wc_get_checkout_url(),
            'result_url' => home_url( '/' ),
        );
    }
    
    /**
     * Форматування номера телефону
     */
    private function format_phone( $phone ) {
        if ( empty( $phone ) ) {
            return '';
        }
        
        $phone = preg_replace( '/[^0-9+]/', '', $phone );
        
        if ( strpos( $phone, '380' ) === 0 ) {
            $phone = '+' . $phone;
        }
        
        if ( strpos( $phone, '80' ) === 0 ) {
            $phone = '+3' . $phone;
        }
        
        if ( strpos( $phone, '0' ) === 0 ) {
            $phone = '+38' . $phone;
        }
        
        return $phone;
    }

    /**
     * Перевірка доступності
     */
    public function is_available() {
        if ( 'yes' !== $this->enabled ) {
            return false;
        }
        
        if ( ! $this->test_mode && ( empty( $this->api_user ) || empty( $this->api_pass ) ) ) {
            return false;
        }
        
        if ( WC()->cart && ! WC()->cart->is_empty() ) {
            $cart_total = WC()->cart->total;
            
            $limits = $this->get_banks_limits();
            
            $effective_min = $this->min_amount > 0 ? $this->min_amount : $limits['min_amount'];
            if ( $effective_min > 0 && $cart_total < $effective_min ) {
                return false;
            }
            
            $effective_max = $this->max_amount > 0 ? $this->max_amount : $limits['max_amount'];
            if ( $effective_max > 0 && $cart_total > $effective_max ) {
                return false;
            }
        }
        
        return parent::is_available();
    }

    /**
     * Отримати ліміти банків з API
     */
    private function get_banks_limits() {
        return rp_payparts_get_banks_limits();
    }
    
    /**
     * Опції адмінки
     */
    public function admin_options() {
        echo '<h2>' . esc_html( $this->get_method_title() ) . '</h2>';
        
        echo '<div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">';
        echo '<h3>ℹ️ Інформація про плагін</h3>';
        echo '<p><strong>Версія:</strong> ' . RP_PAYPARTS_VERSION . '</p>';
        echo '<p><strong>Статус:</strong> ' . ( $this->enabled === 'yes' ? '✅ Активний' : '❌ Неактивний' ) . '</p>';
        echo '<p><strong>Режим:</strong> ' . ( $this->test_mode ? '🧪 Тестовий' : '🚀 Продакшн' ) . '</p>';
        
        echo '<p><strong>Callback URL:</strong> <code>' . home_url( '/wc-api/' . $this->id ) . '</code></p>';
        
        $banks_info = $this->get_banks_info_for_admin();
        if ( ! empty( $banks_info ) ) {
            echo '<p><strong>Доступні банки:</strong> ' . esc_html( $banks_info ) . '</p>';
        }
        
        echo '<p><strong>Логіка перенаправлення:</strong> RozetkaPay → сторінка оплати, інші банки → thank you сторінка з інструкціями</p>';
        echo '</div>';
        
        echo '<table class="form-table">' . $this->generate_settings_html( $this->get_form_fields(), false ) . '</table>';
    }

    /**
     * Отримати інформацію про банки для адмінки
     */
    private function get_banks_info_for_admin() {
        if ( $this->test_mode ) {
            return 'ABank, Monobank, ПриватБанк, RozetkaPay, izibank (тестовий режим)';
        }
        
        $banks = get_transient( 'rp_payparts_banks' );
        if ( false === $banks || ! is_array( $banks ) ) {
            return 'Завантажуються з API...';
        }
        
        $bank_names = array();
        foreach ( $banks as $bank ) {
            if ( isset( $bank['name'] ) ) {
                $bank_names[] = rp_payparts_get_bank_name( $bank['name'] );
            }
        }
        
        return ! empty( $bank_names ) ? implode( ', ', $bank_names ) : 'API недоступний';
    }

    /**
     * Іконка платіжного методу
     */
    public function get_icon() {
        $icon_html = '<img src="' . RP_PAYPARTS_PLUGIN_URL . 'assets/images/rozetkapay-logo.png" alt="RozetkaPay" style="max-height: 24px;" />';
        return apply_filters( 'woocommerce_gateway_icon', $icon_html, $this->id );
    }

    /**
     * Валідація налаштувань адмінки
     */
    public function validate_admin_settings() {
        if ( ! isset( $_POST['woocommerce_rp_payparts_enabled'] ) ) {
            return;
        }
        
        $section = isset( $_GET['section'] ) ? sanitize_text_field( wp_unslash( $_GET['section'] ) ) : '';
        if ( $section !== 'rp_payparts' ) {
            return;
        }
        
        $this->validate_fields();
    }

    /**
     * Валідація полів адмінки
     */
    public function validate_fields() {
        $errors = array();
        
        $enabled = isset( $_POST['woocommerce_rp_payparts_enabled'] ) && $_POST['woocommerce_rp_payparts_enabled'] === '1';
        $test_mode = isset( $_POST['woocommerce_rp_payparts_test_mode'] ) && $_POST['woocommerce_rp_payparts_test_mode'] === '1';
        $api_user = isset( $_POST['woocommerce_rp_payparts_api_user'] ) ? sanitize_text_field( wp_unslash( $_POST['woocommerce_rp_payparts_api_user'] ) ) : '';
        $api_pass = isset( $_POST['woocommerce_rp_payparts_api_pass'] ) ? sanitize_text_field( wp_unslash( $_POST['woocommerce_rp_payparts_api_pass'] ) ) : '';
        $min_amount = isset( $_POST['woocommerce_rp_payparts_min_amount'] ) ? floatval( $_POST['woocommerce_rp_payparts_min_amount'] ) : 0;
        $max_amount = isset( $_POST['woocommerce_rp_payparts_max_amount'] ) ? floatval( $_POST['woocommerce_rp_payparts_max_amount'] ) : 0;
        $allowed_banks = isset( $_POST['woocommerce_rp_payparts_allowed_banks'] ) ? sanitize_text_field( wp_unslash( $_POST['woocommerce_rp_payparts_allowed_banks'] ) ) : '';
        $allowed_terms = isset( $_POST['woocommerce_rp_payparts_allowed_terms'] ) ? sanitize_text_field( wp_unslash( $_POST['woocommerce_rp_payparts_allowed_terms'] ) ) : '';
        
        if ( $enabled && ! $test_mode ) {
            if ( empty( $api_user ) ) {
                $errors[] = __( 'Для продакшн режиму необхідно вказати API користувача (Payparts)', 'rp-payparts' );
            }
            
            if ( empty( $api_pass ) ) {
                $errors[] = __( 'Для продакшн режиму необхідно вказати API пароль (Payparts)', 'rp-payparts' );
            }
        }
        
        if ( $min_amount > 0 && $max_amount > 0 && $min_amount >= $max_amount ) {
            $errors[] = __( 'Мінімальна сума повинна бути менше максимальної', 'rp-payparts' );
        }
        
        if ( ! empty( $allowed_banks ) ) {
            $valid_banks = array( 'abank', 'monobank', 'privatbank', 'rozetkapay', 'izibank' );
            $input_banks = array_map( 'trim', explode( ',', $allowed_banks ) );
            
            foreach ( $input_banks as $bank ) {
                if ( ! empty( $bank ) && ! in_array( $bank, $valid_banks, true ) ) {
                    $errors[] = sprintf( 
                        __( 'Невідомий банк: %s. Доступні: %s', 'rp-payparts' ), 
                        $bank, 
                        implode( ', ', $valid_banks ) 
                    );
                }
            }
        }
        
        if ( ! empty( $allowed_terms ) ) {
            $input_terms = array_map( 'trim', explode( ',', $allowed_terms ) );
            
            foreach ( $input_terms as $term ) {
                if ( ! empty( $term ) && ( ! is_numeric( $term ) || intval( $term ) < 2 || intval( $term ) > 25 ) ) {
                    $errors[] = sprintf( 
                        __( 'Некоректний термін: %s. Допустимі: 2-25 місяців', 'rp-payparts' ), 
                        $term 
                    );
                }
            }
        }
        
        if ( ! empty( $errors ) ) {
            foreach ( $errors as $error ) {
                WC_Admin_Settings::add_error( $error );
            }
            return false;
        }
        
        return true;
    }

    /**
     * Обробка опцій адмінки
     */
    public function process_admin_options() {
        rp_payparts_clear_cache();
        
        $result = parent::process_admin_options();
        
        if ( $this->debug_mode ) {
            rp_payparts_log( 'Settings updated by user: ' . wp_get_current_user()->user_login );
        }
        
        $raw = $this->get_option( 'order_ref_prefix', 'roz1' );
        $clean = preg_replace( '/[^a-zA-Z0-9_\-\.]/', '', (string) $raw );
        $this->settings['order_ref_prefix'] = $clean;
        update_option( $this->get_option_key(), $this->settings );
        $this->order_ref_prefix = $clean;
        
        return $result;
    }

    /**
     * Тест підключення до API
     */
    public function test_api_connection() {
        if ( empty( $this->api_user ) || empty( $this->api_pass ) ) {
            return array(
                'success' => false,
                'message' => __( 'Не вказані API ключі', 'rp-payparts' ),
            );
        }
        
        $api = $this->get_api_client();
        return $api->test_connection();
    }

    /**
     * Отримати статистику використання
     */
    public function get_usage_stats() {
        $orders = wc_get_orders( array(
            'payment_method' => 'rp_payparts',
            'limit' => -1,
            'date_created' => '>' . ( time() - 30 * DAY_IN_SECONDS ),
        ) );
        
        $stats = array(
            'total_orders' => count( $orders ),
            'total_amount' => 0,
            'banks_usage' => array(),
            'terms_usage' => array(),
        );
        
        foreach ( $orders as $order ) {
            $stats['total_amount'] += $order->get_total();
            
            $option = get_post_meta( $order->get_id(), '_rp_payparts_option', true );
            if ( ! empty( $option ) && strpos( $option, '|' ) !== false ) {
                list( $bank, $term ) = explode( '|', $option );
                
                if ( ! isset( $stats['banks_usage'][ $bank ] ) ) {
                    $stats['banks_usage'][ $bank ] = 0;
                }
                $stats['banks_usage'][ $bank ]++;
                
                if ( ! isset( $stats['terms_usage'][ $term ] ) ) {
                    $stats['terms_usage'][ $term ] = 0;
                }
                $stats['terms_usage'][ $term ]++;
            }
        }
        
        return $stats;
    }

    /**
     * Отримати правильний callback URL
     */
    public function get_callback_url() {
        return home_url( '/wc-api/' . $this->id );
    }
}
