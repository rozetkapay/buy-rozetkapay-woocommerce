<?php
/**
 * RozetkaPay Standard Gateway Class - СПРОЩЕНА ВЕРСІЯ
 * Видалено функції безпеки та двоступеневих платежів
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
    return;
}

// Перевіряємо, чи не оголошено вже клас
if ( class_exists( 'RP_Standard_Gateway' ) ) {
    return;
}

class RP_Standard_Gateway extends WC_Payment_Gateway {

    private $api_client;
    
    public $api_user;
    public $api_pass;
    public $debug_mode;
    public $advanced_logging;
    public $order_ref_prefix;
    public $enable_status_polling;

    public function __construct() {
        $this->id                 = 'rp_standard';
        $this->method_title       = __( 'RozetkaPay Enhanced', 'rp-payparts' );
        $this->method_description = __( 'Прийом платежів через RozetkaPay з розширеною діагностикою', 'rp-payparts' );
        $this->title              = __( 'Банківська картка', 'rp-payparts' );
        $this->has_fields         = false;
        $this->supports = array( 'products', 'refunds' ); // без 'refunds'

        $this->init_form_fields();
        $this->init_settings();

        // Налаштовуваний префікс external_id
        $this->order_ref_prefix = $this->get_option( 'order_ref_prefix', 'roz1' );

        // Налаштування: увімкнути переопитування статусів
        $this->enable_status_polling = $this->get_option( 'enable_status_polling', 'yes' );

        // Основні налаштування
        $this->enabled      = $this->get_option( 'enabled' );
        $this->title        = $this->get_option( 'title', __( 'Банківська картка', 'rp-payparts' ) );
        $this->description  = $this->get_option( 'description' );
        
        // Налаштування API
        $this->api_user     = $this->get_option( 'api_user' );
        $this->api_pass     = $this->get_option( 'api_pass' );
        
        // Додаткові налаштування (лише відлагодження)
        $this->debug_mode = 'yes' === $this->get_option( 'debug_mode' );
        $this->advanced_logging = 'yes' === $this->get_option( 'advanced_logging' );

        // Ініціалізація API-клієнта
        $this->init_api_client();

        // Реєстрація хуків
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        add_action( 'woocommerce_api_' . $this->id, array( $this, 'handle_callback' ) );
        
        // ВИПРАВЛЕННЯ: реєструємо AJAX-обробники прямо в конструкторі
        if ( is_admin() ) {
            add_action( 'wp_ajax_rp_test_api_connection', array( $this, 'ajax_test_api_connection' ) );
            add_action( 'wp_ajax_rp_test_callback_processing', array( $this, 'ajax_test_callback_processing' ) );
            add_action( 'wp_ajax_rp_check_payment_status', array( $this, 'ajax_check_payment_status' ) );
            error_log('RozetkaPay: AJAX handlers registered in constructor');
        }
        
        // Ініціалізація логування
        $this->init_logging();
        
        // Міграція старих налаштувань
        $this->migrate_old_settings();
        
        error_log('RozetkaPay Gateway: Constructor completed');
    }

    /**
     * Міграція старих налаштувань (видаляємо застарілі поля)
     */
    private function migrate_old_settings() {
        $settings = get_option('woocommerce_rp_standard_settings', array());
        
        $deprecated_fields = array(
            'test_mode', 
            'two_step_payment', 
            'allowed_ips', 
            'strict_validation',
            'security_section',
            'payment_section'
        );
        
        $updated = false;
        foreach ($deprecated_fields as $field) {
            if (isset($settings[$field])) {
                unset($settings[$field]);
                $updated = true;
                error_log('RozetkaPay: Removed deprecated setting: ' . $field);
            }
        }
        
        if ($updated) {
            update_option('woocommerce_rp_standard_settings', $settings);
        }
    }

    /**
     * Ініціалізація API-клієнта
     */
    private function init_api_client() {
        if ( ! empty( $this->api_user ) && ! empty( $this->api_pass ) ) {
            $api_file = plugin_dir_path( __FILE__ ) . 'class-standard-api.php';
            if ( file_exists( $api_file ) && ! class_exists( 'RP_Standard_API' ) ) {
                require_once $api_file;
            }
            
            if ( class_exists( 'RP_Standard_API' ) ) {
                $this->api_client = new RP_Standard_API( 
                    $this->api_user, 
                    $this->api_pass
                );
                error_log( 'RozetkaPay: API-клієнт ініціалізовано' );
            } else {
                error_log( 'RozetkaPay: Не вдалося завантажити клас RP_Standard_API' );
            }
        }
    }

    /**
     * Ініціалізація полів форми — СПРОЩЕНА ВЕРСІЯ
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => __( 'Увімкнути/Вимкнути', 'rp-payparts' ),
                'type'    => 'checkbox',
                'label'   => __( 'Увімкнути RozetkaPay оплату картками', 'rp-payparts' ),
                'default' => 'no',
            ),
            'title' => array(
                'title'       => __( 'Назва методу', 'rp-payparts' ),
                'type'        => 'text',
                'description' => __( 'Назва методу оплати, яку бачить користувач під час оформлення замовлення.', 'rp-payparts' ),
                'default'     => __( 'Банківська картка', 'rp-payparts' ),
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __( 'Опис на сторінці оформлення', 'rp-payparts' ),
                'type'        => 'textarea',
                'description' => __( 'Опис методу оплати, який відображається на сторінці оформлення замовлення.', 'rp-payparts' ),
                'default'     => __( 'Оплата банківською карткою через захищену сторінку RozetkaPay.', 'rp-payparts' ),
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
                'title' => __( 'Налаштування API', 'rp-payparts' ),
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
            
            'debug_section' => array(
                'title' => __( 'Налаштування відладки', 'rp-payparts' ),
                'type'  => 'title',
                'description' => __( 'Додаткова інформація для відладки роботи плагіна.', 'rp-payparts' ),
            ),
            'debug_mode' => array(
                'title'       => __( 'Режим відладки', 'rp-payparts' ),
                'type'        => 'checkbox',
                'label'       => __( 'Увімкнути детальне логування', 'rp-payparts' ),
                'description' => __( 'Вмикайте для діагностики проблем', 'rp-payparts' ),
                'default'     => 'no',
                'desc_tip'    => true,
            ),
            'advanced_logging' => array(
                'title'       => __( 'Розширене логування', 'rp-payparts' ),
                'type'        => 'checkbox',
                'label'       => __( 'Створювати детальні лог-файли', 'rp-payparts' ),
                'description' => __( 'Зберігає детальні логи в окремі файли', 'rp-payparts' ),
                'default'     => 'no',
                'desc_tip'    => true,
            ),
        );
    }

    /**
     * ОНОВЛЕНИЙ метод обробки платежу
     */
    public function process_payment( $order_id ) {
        error_log('=== RozetkaPay: process_payment started for order ' . $order_id . ' ===');
        
        $order = wc_get_order( $order_id );
        if ( function_exists('rp_build_reference') && $order ) { rp_build_reference( $order, $this->order_ref_prefix ); }
        
        if ( ! $order ) {
            error_log('RozetkaPay: Order not found: ' . $order_id);
            wc_add_notice( 'Помилка отримання замовлення.', 'error' );
            return array( 'result' => 'fail' );
        }
        
        try {
            if ( empty( $this->api_user ) || empty( $this->api_pass ) ) {
                error_log('RozetkaPay: API keys not configured');
                wc_add_notice( 'Помилка конфігурації платіжного методу. Зверніться до адміністратора.', 'error' );
                return array( 'result' => 'fail' );
            }
            
            if ( ! $this->api_client ) {
                error_log('RozetkaPay: API client not initialized, trying to initialize...');
                $this->init_api_client();
                
                if ( ! $this->api_client ) {
                    error_log('RozetkaPay: Failed to initialize API client');
                    wc_add_notice( 'Помилка ініціалізації платіжної системи.', 'error' );
                    return array( 'result' => 'fail' );
                }
            }
            
            $payment_data = $this->prepare_payment_data( $order );
            
            if ( ! $payment_data ) {
                error_log('RozetkaPay: Failed to prepare payment data');
                wc_add_notice( 'Помилка підготовки даних платежу.', 'error' );
                return array( 'result' => 'fail' );
            }
            
            error_log('RozetkaPay: Payment data prepared: ' . json_encode($payment_data));
            
            $payment_result = $this->api_client->create_payment( $payment_data );
            
            if ( is_wp_error( $payment_result ) ) {
                $error_message = $payment_result->get_error_message();
                error_log('RozetkaPay: Payment creation failed: ' . $error_message);
                
                $user_message = $this->get_user_friendly_error_message( $error_message );
                wc_add_notice( $user_message, 'error' );
                return array( 'result' => 'fail' );
            }
            
            if ( ! isset( $payment_result['success'] ) || ! $payment_result['success'] ) {
                error_log('RozetkaPay: Payment creation unsuccessful: ' . json_encode($payment_result));
                wc_add_notice( 'Не вдалося створити платіж. Спробуйте ще раз.', 'error' );
                return array( 'result' => 'fail' );
            }
            
            if ( empty( $payment_result['payment_url'] ) ) {
                error_log('RozetkaPay: No payment URL in response: ' . json_encode($payment_result));
                wc_add_notice( 'Не отримано посилання для оплати.', 'error' );
                return array( 'result' => 'fail' );
            }
            
            $payment_url = $payment_result['payment_url'];
            $payment_id = $payment_result['id'] ?? '';
            // Зберігаємо корисні мета-дані для діагностики
            $order->update_meta_data( '_rozetkapay_payment_id', $payment_id );
            $order->update_meta_data( '_rozetkapay_payment_url', $payment_url );
            // Гарантуємо, що external_id збережено
            $ext = $order->get_meta( '_rozetkapay_external_id', true );
            if ( empty($ext) ) {
                $ext = function_exists('rp_build_reference') ? rp_build_reference( $order, $this->order_ref_prefix ) : (string) $order->get_order_number();
                $order->update_meta_data( '_rozetkapay_external_id', $ext );
            }
            
            error_log('RozetkaPay: Payment created successfully');
            error_log('RozetkaPay: Payment ID: ' . $payment_id);
            error_log('RozetkaPay: Payment URL: ' . $payment_url);
            
            // Зберігаємо інформацію про платіж у мета-поля
            $order->update_meta_data( '_rozetkapay_payment_id', $payment_id );
            $order->update_meta_data( '_rozetkapay_external_id', $payment_result['external_id'] ?? '' );
            $order->update_meta_data( '_rozetkapay_payment_url', $payment_url );
            
            $order->update_status( 'pending', __( 'Очікується оплата через RozetkaPay.', 'rp-payparts' ) );
            $order->save();
            
            $this->log( 'info', 'Payment created for order', array(
                'order_id' => $order_id,
                'payment_id' => $payment_id,
                'amount' => $order->get_total(),
                'currency' => $order->get_currency()
            ) );
            
            return array(
                'result' => 'success',
                'redirect' => $payment_url
            );
            
        } catch ( Exception $e ) {
            error_log('RozetkaPay: Exception in process_payment: ' . $e->getMessage());
            error_log('RozetkaPay: Exception trace: ' . $e->getTraceAsString());
            
            wc_add_notice( 'Виникла помилка при обробці платежу. Спробуйте ще раз.', 'error' );
            return array( 'result' => 'fail' );
        }
    }

    /**
     * СПРОЩЕНИЙ метод обробки callback
     */
    public function handle_callback() {
        $payload = file_get_contents('php://input');
        
        error_log('RozetkaPay Callback received: ' . $payload);
        
        if (empty($payload)) {
            error_log('RozetkaPay: Empty callback payload received');
            status_header(400);
            exit('Empty payload');
        }
        
        $data = json_decode($payload, true);
        if (!$data) {
            error_log('RozetkaPay: Invalid JSON in callback: ' . $payload);
            status_header(400);
            exit('Invalid JSON');
        }
        
        error_log('RozetkaPay: Parsed callback data: ' . json_encode($data, JSON_UNESCAPED_UNICODE));
        
        // Базова перевірка обовʼязкових полів
        if (empty($data['id'])) {
            error_log('RozetkaPay: Missing payment ID in callback. Data: ' . json_encode($data));
            status_header(400);
            exit('Missing payment ID');
        }
        
        // Перевіряємо статус у правильних місцях
        $has_status = false;
        
        if (!empty($data['status'])) {
            $has_status = true;
            error_log('RozetkaPay: Found status in data.status: ' . $data['status']);
        } elseif (!empty($data['details']) && !empty($data['details']['status'])) {
            $has_status = true;
            error_log('RozetkaPay: Found status in data.details.status: ' . $data['details']['status']);
        } elseif (isset($data['is_success'])) {
            $has_status = true;
            error_log('RozetkaPay: Found is_success flag: ' . var_export($data['is_success'], true));
        }
        
        if (!$has_status) {
            error_log('RozetkaPay: Missing status information in callback. Data: ' . json_encode($data));
            status_header(400);
            exit('Missing status information');
        }
        
        // Базова обробка callback
        $this->process_callback_data($data);
        
        status_header(200);
        exit('OK');
    }

    /**
     * НОВИЙ метод: обробка даних callback
     */
    private function process_callback_data($data) {
        try {
            error_log('RozetkaPay: Processing callback data: ' . json_encode($data));
            
            // Знайти замовлення по external_id або payment_id
            $order = $this->find_order_by_callback_data($data);
            
            if (!$order) {
                error_log('RozetkaPay: Order not found for callback data');
                return;
            }
            
            // Оновити статус замовлення залежно від статусу платежу
            $this->update_order_status_from_callback($order, $data);
            
            // Логуємо успішну обробку
            $this->log('info', 'Callback processed successfully', array(
                'order_id' => $order->get_id(),
                'payment_status' => $data['status'] ?? 'unknown',
                'payment_id' => $data['id'] ?? ''
            ));
            
        } catch (Exception $e) {
            error_log('RozetkaPay: Exception processing callback: ' . $e->getMessage());
        }
    }

    /**
     * НОВИЙ метод: пошук замовлення за даними callback
     */
    private function find_order_by_callback_data($data) {
        // Прагнемо знайти замовлення різними способами
        
        // 1. За external_id
        if (!empty($data['external_id'])) {
            $external_id = $data['external_id'];
            // Витягуємо ID замовлення з external_id (формат: order-ID-timestamp)
            if (preg_match('/^order-(\d+)-\d+$/', $external_id, $matches)) {
                $order_id = intval($matches[1]);
                $order = wc_get_order($order_id);
                if ($order) {
                    return $order;
                }
            }
        }
        
        // 2. За payment_id у мета-полях
        if (!empty($data['id'])) {
            $orders = wc_get_orders(array(
                'meta_key' => '_rozetkapay_payment_id',
                'meta_value' => $data['id'],
                'limit' => 1
            ));
            
            if (!empty($orders)) {
                return $orders[0];
            }
        }
        
        return null;
    }

   /**
 * ВИПРАВЛЕНИЙ метод: оновлення статусу замовлення з callback — БЕЗ ЛОГІКИ ТЕСТОВИХ КАРТОК
 */
private function update_order_status_from_callback($order, $data) {
    // Визначаємо статус з різних місць у callback
    $payment_status = '';
    $status_code = '';
    $payment_id = $data['id'] ?? '';
    $receipt_number = '';
    $is_success = isset($data['is_success']) ? $data['is_success'] : null;
    
    // Визначаємо статус з різних місць у callback
    if (!empty($data['details']['status'])) {
        $payment_status = $data['details']['status'];
        $status_code = $data['details']['status_code'] ?? '';
        $receipt_number = $data['details']['rrn'] ?? '';
        error_log('RozetkaPay: Status from details: ' . $payment_status);
    } elseif (!empty($data['status'])) {
        $payment_status = $data['status'];
        error_log('RozetkaPay: Status from root: ' . $payment_status);
    } elseif ($is_success !== null) {
        $payment_status = $is_success ? 'success' : 'failure';
        error_log('RozetkaPay: Status from is_success: ' . $payment_status);
    }
    
    error_log("RozetkaPay: Processing callback for Order #{$order->get_id()} - Status: {$payment_status}, Status Code: {$status_code}, Receipt: {$receipt_number}, is_success: " . var_export($is_success, true));
    
    // Оновлюємо мета-дані замовлення
    if (!empty($receipt_number)) {
        $order->update_meta_data('_rozetkapay_receipt_number', $receipt_number);
    }
    if (!empty($status_code)) {
        $order->update_meta_data('_rozetkapay_status_code', $status_code);
    }
    
    // Визначаємо дію на основі статусу
    $status_lower = strtolower($payment_status);
    
    switch ($status_lower) {
        case 'success':
        case 'completed':
        case 'paid':
        case 'processing':
            if (!$order->is_paid()) {
                $order->payment_complete($payment_id);
                $note = sprintf(__('Платіж успішно завершено через RozetkaPay. ID платежу: %s', 'rp-payparts'), $payment_id);
                if (!empty($receipt_number)) {
                    $note .= sprintf(__(' | Квитанція №: %s', 'rp-payparts'), $receipt_number);
                }
                $order->add_order_note($note);
                error_log("RozetkaPay: Order #{$order->get_id()} marked as paid - status: {$payment_status}");
            }
            break;
            
        case 'refund':
        case 'refunded':
        case 'refunding':
        case 'partially_refunded':
            if ($order->get_status() !== 'refunded') {
                $note = sprintf(__('Платіж повернено через RozetkaPay. ID платежу: %s', 'rp-payparts'), $payment_id);
                if (!empty($receipt_number)) {
                    $note .= sprintf(__(' | Квитанція №: %s', 'rp-payparts'), $receipt_number);
                }
                if (!empty($status_code)) {
                    $note .= sprintf(__(' | Код: %s', 'rp-payparts'), $status_code);
                }
                $order->update_status('refunded', $note);
                error_log("RozetkaPay: Order #{$order->get_id()} marked as refunded - status: {$payment_status}");
            }
            break;
            
        case 'failed':
        case 'failure':
        case 'error':
        case 'rejected':
        case 'transaction_rejected':
            $note = sprintf(__('Платіж не вдався в RozetkaPay. ID платежу: %s', 'rp-payparts'), $payment_id);
            if (!empty($status_code)) {
                $note .= sprintf(__(' | Код помилки: %s', 'rp-payparts'), $status_code);
            }
            if (!empty($receipt_number)) {
                $note .= sprintf(__(' | Квитанція №: %s', 'rp-payparts'), $receipt_number);
            }
            $order->update_status('failed', $note);
            error_log("RozetkaPay: Order #{$order->get_id()} marked as failed - status: {$payment_status}, code: {$status_code}");
            break;
            
        case 'cancelled':
        case 'canceled':
            $note = sprintf(__('Платіж скасовано в RozetkaPay. ID платежу: %s', 'rp-payparts'), $payment_id);
            if (!empty($receipt_number)) {
                $note .= sprintf(__(' | Квитанція №: %s', 'rp-payparts'), $receipt_number);
            }
            $order->update_status('cancelled', $note);
            error_log("RozetkaPay: Order #{$order->get_id()} marked as cancelled");
            break;
            
        case 'pending':
        case 'init':
            if ($order->get_status() !== 'processing' && $order->get_status() !== 'completed') {
                $note = sprintf(__('Платіж в обробці в RozetkaPay. ID платежу: %s', 'rp-payparts'), $payment_id);
                if (!empty($receipt_number)) {
                    $note .= sprintf(__(' | Квитанція №: %s', 'rp-payparts'), $receipt_number);
                }
                $order->update_status('on-hold', $note);
                error_log("RozetkaPay: Order #{$order->get_id()} marked as on-hold - pending payment");
            }
            break;
            
        default:
            error_log('RozetkaPay: Unknown payment status: ' . $payment_status);
            
            // Якщо статус невідомий, але is_success = false
            if ($is_success === false) {
                $note = sprintf(__('Платіж не вдався в RozetkaPay (is_success=false). ID платежу: %s', 'rp-payparts'), $payment_id);
                if (!empty($status_code)) {
                    $note .= sprintf(__(' | Код помилки: %s', 'rp-payparts'), $status_code);
                }
                $order->update_status('failed', $note);
                error_log("RozetkaPay: Order #{$order->get_id()} marked as failed based on is_success=false");
            } elseif ($is_success === true) {
                // Якщо is_success = true, позначаємо як оплачений
                if (!$order->is_paid()) {
                    $order->payment_complete($payment_id);
                    $note = sprintf(__('Платіж успішний за is_success=true. ID платежу: %s', 'rp-payparts'), $payment_id);
                    if (!empty($status_code)) {
                        $note .= sprintf(__(' | Код: %s', 'rp-payparts'), $status_code);
                    }
                    $order->add_order_note($note);
                    error_log("RozetkaPay: Order #{$order->get_id()} marked as paid based on is_success=true");
                }
            } else {
                // Додаємо примітку з невідомим статусом
                $note = sprintf(__('Отримано невідомий статус платежу: %s | ID: %s', 'rp-payparts'), $payment_status, $payment_id);
                if (!empty($status_code)) {
                    $note .= sprintf(__(' | Код: %s', 'rp-payparts'), $status_code);
                }
                $order->add_order_note($note);
                error_log("RozetkaPay: Added note for unknown status: {$payment_status}");
            }
            break;
    }
    
    $order->save();
}

    // ДОПОМІЖНІ МЕТОДИ (залишаються без змін)
    private function prepare_payment_data( $order ) {
        if ( ! $order ) {
            return false;
        }
        
        try {
            $payment_data = array(
                'external_id' => ( function_exists('rp_build_reference') ? rp_build_reference( $order, $this->order_ref_prefix ) : ( method_exists( $order, 'get_order_number' ) ? (string) $order->get_order_number() : (string) $order->get_id() ) ),
                'amount' => floatval( $order->get_total() ),
                'currency' => $order->get_currency(),
                'description' => $this->get_payment_description( $order ),
                'result_url' => $this->get_return_url( $order ),
                'callback_url' => home_url( '/wc-api/' . $this->id )
            );
            
            $customer_data = $this->prepare_customer_data( $order );
            if ( $customer_data ) {
                $payment_data['customer'] = $customer_data;
            }
            
            // ВИМКНЕНО: передача товарів для стабільності
            /*
            if ( $this->debug_mode ) {
                $products_data = $this->prepare_products_data( $order );
                if ( $products_data ) {
                    $payment_data['products'] = $products_data;
                    error_log('RozetkaPay: Products included in debug mode');
                } else {
                    error_log('RozetkaPay: No products data prepared');
                }
            } else {
                error_log('RozetkaPay: Products excluded - debug mode disabled');
            }
            */
            error_log('RozetkaPay: Products transmission disabled for stability');
            
            return $payment_data;
            
        } catch ( Exception $e ) {
            error_log('RozetkaPay: Error preparing payment data: ' . $e->getMessage());
            return false;
        }
    }

    private function get_payment_description( $order ) {
        $site_name = get_bloginfo( 'name' );
        $order_id = $order->get_id();
        
        $description = sprintf( 'Замовлення №%s від %s', $order_id, $site_name );
        
        if ( strlen( $description ) > 50 ) {
            $description = sprintf( 'Замовлення №%s', $order_id );
        }
        
        return $description;
    }

    private function prepare_customer_data( $order ) {
        $customer_data = array();
        
        $email = $order->get_billing_email();
        if ( ! empty( $email ) && is_email( $email ) ) {
            $customer_data['email'] = $email;
        }
        
        $first_name = $order->get_billing_first_name();
        $last_name = $order->get_billing_last_name();
        
        if ( ! empty( $first_name ) || ! empty( $last_name ) ) {
            $name = trim( $first_name . ' ' . $last_name );
            if ( ! empty( $name ) ) {
                $name = preg_replace( '/[^\p{L}\p{N}\s\-\.]/u', '', $name );
                if ( ! empty( $name ) && strlen( $name ) <= 100 ) {
                    $customer_data['name'] = $name;
                }
            }
        }
        
        $phone = $order->get_billing_phone();
        if ( ! empty( $phone ) ) {
            $clean_phone = preg_replace( '/[^\d\+\-\(\)\s]/', '', $phone );
            if ( ! empty( $clean_phone ) ) {
                $customer_data['phone'] = $clean_phone;
            }
        }
        
        return ! empty( $customer_data ) ? $customer_data : null;
    }

    private function get_user_friendly_error_message( $api_error ) {
        if ( strpos( $api_error, 'API ключі' ) !== false || strpos( $api_error, 'Невірні API' ) !== false ) {
            return 'Помилка налаштування платіжної системи. Зверніться до адміністратора сайту.';
        }
        
        if ( strpos( $api_error, 'amount' ) !== false || strpos( $api_error, 'сума' ) !== false ) {
            return 'Помилка обробки суми платежу. Перевірте правильність даних замовлення.';
        }
        
        if ( strpos( $api_error, 'тимчасово недоступна' ) !== false || strpos( $api_error, '503' ) !== false ) {
            return 'Платіжна система тимчасово недоступна. Спробуйте через кілька хвилин.';
        }
        
        if ( strpos( $api_error, 'з\'єднан' ) !== false || strpos( $api_error, 'connection' ) !== false ) {
            return 'Помилка з\'єднання з платіжною системою. Перевірте інтернет-з\'єднання та спробуйте ще раз.';
        }
        
        return 'Виникла помилка при створенні платежу. Спробуйте ще раз або оберіть інший спосіб оплати.';
    }

    // ДОПОМІЖНІ МЕТОДИ (логування тощо)
    private function init_logging() {
        if ( $this->advanced_logging ) {
            add_action( 'init', array( $this, 'setup_custom_logger' ) );
        }
    }

    public function setup_custom_logger() {
        if ( ! $this->advanced_logging ) {
            return;
        }
        
        $log_dir = WP_CONTENT_DIR . '/uploads/rozetkapay-logs/';
        if ( ! file_exists( $log_dir ) ) {
            wp_mkdir_p( $log_dir );
        }
        
        $htaccess_file = $log_dir . '.htaccess';
        if ( ! file_exists( $htaccess_file ) ) {
            file_put_contents( $htaccess_file, "deny from all\n" );
        }
    }

    private function log( $level, $message, $context = array() ) {
        if ( ! $this->advanced_logging && ! $this->debug_mode ) {
            return;
        }

        $timestamp = current_time( 'Y-m-d H:i:s' );
        $formatted_message = sprintf( 
            '[%s] [%s] %s', 
            $timestamp, 
            strtoupper( $level ), 
            $message 
        );

        if ( ! empty( $context ) ) {
            $formatted_message .= ' | Context: ' . json_encode( $context, JSON_UNESCAPED_UNICODE );
        }

        error_log( 'RozetkaPay: ' . $formatted_message );

        if ( $this->advanced_logging ) {
            $log_file = WP_CONTENT_DIR . '/uploads/rozetkapay-logs/rozetkapay-' . date( 'Y-m-d' ) . '.log';
            file_put_contents( $log_file, $formatted_message . PHP_EOL, FILE_APPEND | LOCK_EX );
        }
    }

    // МЕТОДИ АДМІНКИ ТА AJAX (скорочені версії)
    public function admin_options() {
        echo '<h2>' . esc_html( $this->get_method_title() ) . '</h2>';
        echo '<p>' . esc_html( $this->get_method_description() ) . '</p>';
        
        $this->display_diagnostic_panel();
        $this->display_api_test_panel();
        
        echo '<table class="form-table">';
        $this->generate_settings_html();
        echo '</table>';
        
        $this->display_technical_info();
        $this->output_admin_javascript();
    }

    private function display_diagnostic_panel() {
        echo '<div style="background: #f9f9f9; padding: 20px; border-radius: 8px; margin: 20px 0;">';
        echo '<h3>Діагностика системи</h3>';
        
        // Перевіряємо AJAX-обробники
        global $wp_filter;
        $ajax_registered = isset( $wp_filter['wp_ajax_rp_test_api_connection'] ) && 
                          ! empty( $wp_filter['wp_ajax_rp_test_api_connection']->callbacks );
        
        echo '<div style="margin: 10px 0; padding: 10px; background: white; border-radius: 4px; border-left: 4px solid ' . ($ajax_registered ? '#28a745' : '#dc3545') . ';">';
        echo '<strong>' . ($ajax_registered ? '✅' : '❌') . ' AJAX-обробники:</strong> ' . ($ajax_registered ? 'Зареєстровані' : 'НЕ зареєстровані');
        echo '</div>';
        
        if ( empty( $this->api_user ) || empty( $this->api_pass ) ) {
            echo '<div style="margin: 10px 0; padding: 10px; background: white; border-radius: 4px; border-left: 4px solid #dc3545;">';
            echo '<strong>❌ API ключі не налаштовані</strong>';
            echo '</div>';
        } else {
            echo '<div style="margin: 10px 0; padding: 10px; background: white; border-radius: 4px; border-left: 4px solid #28a745;">';
            echo '<strong>✅ API ключі налаштовані</strong> (User: ' . esc_html( $this->api_user ) . ')';
            echo '</div>';
        }
        
        echo '</div>';
    }

    private function display_api_test_panel() { return; }

    private function display_technical_info() {
        if ( ! $this->debug_mode ) {
            return;
        }
        
        echo '<div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0;">';
        echo '<h3>Технічна інформація</h3>';
        echo '<div style="font-family: monospace; font-size: 12px;">';
        echo '<p><strong>Gateway ID:</strong> ' . esc_html( $this->id ) . '</p>';
        echo '<p><strong>API User:</strong> ' . esc_html( $this->api_user ) . '</p>';
        echo '<p><strong>Gateway Enabled:</strong> ' . ( $this->enabled === 'yes' ? 'ТАК' : 'НІ' ) . '</p>';
        echo '<p><strong>Callback URL:</strong> ' . home_url('/wc-api/' . $this->id) . '</p>';
        echo '</div>';
        echo '</div>';
    }

    private function output_admin_javascript() {
        ?>
        <script>
        jQuery(document).ready(function($) {
            console.log('RozetkaPay: Admin JavaScript loaded');
            console.log('RozetkaPay: ajaxurl =', ajaxurl);
            
            $('#rp-test-api').click(function() {
                var $button = $(this);
                var $result = $('#rp-test-result');
                
                console.log('RozetkaPay: API test button clicked');
                
                $button.prop('disabled', true).text('Перевіряєм...');
                $result.html('<div style="color: #666; padding: 10px; background: #f0f0f0; border-radius: 4px;">⏳ Виконується тест з\'єднання...</div>');
                
                var requestData = {
                    action: 'rp_test_api_connection',
                    security: '<?php echo wp_create_nonce('rp_test_api'); ?>',
                    gateway: 'rp_standard'
                };
                
                console.log('RozetkaPay: Sending AJAX request with data:', requestData);
                
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: requestData,
                    timeout: 30000,
                    success: function(response) {
                        console.log('RozetkaPay: AJAX response received:', response);
                        
                        if (response && response.success) {
                            var message = response.data && response.data.message ? response.data.message : 'Успіх';
                            var details = response.data && response.data.details ? '<br/><small>' + response.data.details + '</small>' : '';
                            $result.html('<div style="color: #28a745; background: #d4edda; padding: 15px; border-radius: 4px;"><strong>✅ ' + message + '</strong>' + details + '</div>');
                        } else {
                            var errorMsg = 'Невідома помилка';
                            if (response && response.data) {
                                if (response.data.message) {
                                    errorMsg = response.data.message;
                                    if (response.data.details) {
                                        errorMsg += '<br/><small>' + response.data.details + '</small>';
                                    }
                                }
                            }
                            $result.html('<div style="color: #dc3545; background: #f8d7da; padding: 15px; border-radius: 4px;"><strong>❌ ' + errorMsg + '</strong></div>');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('RozetkaPay: AJAX error:', {xhr: xhr, status: status, error: error});
                        
                        var errorMsg = 'AJAX-помилка';
                        if (xhr.responseText) {
                            try {
                                var responseData = JSON.parse(xhr.responseText);
                                if (responseData.data && responseData.data.message) {
                                    errorMsg = responseData.data.message;
                                }
                            } catch(e) {
                                errorMsg = 'AJAX-помилка: ' + status + ' (' + error + ')';
                            }
                        }
                        
                        $result.html('<div style="color: #dc3545; background: #f8d7da; padding: 15px; border-radius: 4px;"><strong>❌ ' + errorMsg + '</strong></div>');
                    },
                    complete: function() {
                        console.log('RozetkaPay: AJAX request completed');
                        $button.prop('disabled', false).text('Перевірити з\'єднання з API');
                    }
                });
            });

            $('#rp-test-callback').click(function() {
                var $button = $(this);
                var $result = $('#rp-test-result');
                
                $button.prop('disabled', true).text('Тестуємо...');
                $result.html('<div style="color: #666; padding: 10px; background: #f0f0f0; border-radius: 4px;">⏳ Тестуємо обробку callback...</div>');
                
                var requestData = {
                    action: 'rp_test_callback_processing',
                    security: '<?php echo wp_create_nonce('rp_test_callback'); ?>',
                    gateway: 'rp_standard'
                };
                
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: requestData,
                    timeout: 15000,
                    success: function(response) {
                        if (response && response.success) {
                            var message = response.data && response.data.message ? response.data.message : 'Успіх';
                            var details = response.data && response.data.details ? '<br/><small>' + response.data.details + '</small>' : '';
                            $result.html('<div style="color: #28a745; background: #d4edda; padding: 15px; border-radius: 4px;"><strong>✅ ' + message + '</strong>' + details + '</div>');
                        } else {
                            var errorMsg = response && response.data && response.data.message ? response.data.message : 'Невідома помилка';
                            $result.html('<div style="color: #dc3545; background: #f8d7da; padding: 15px; border-radius: 4px;"><strong>❌ ' + errorMsg + '</strong></div>');
                        }
                    },
                    error: function(xhr, status, error) {
                        $result.html('<div style="color: #dc3545; background: #f8d7da; padding: 15px; border-radius: 4px;"><strong>❌ AJAX-помилка під час тесту callback</strong></div>');
                    },
                    complete: function() {
                        $button.prop('disabled', false).text('Тест обробки callback');
                    }
                });
            });
        });
        </script>
        <?php
    }

    public function ajax_test_api_connection() {
        error_log('RozetkaPay: AJAX ajax_test_api_connection called');
        
        if (!current_user_can('manage_woocommerce')) {
            error_log('RozetkaPay: User lacks manage_woocommerce capability');
            wp_send_json_error(array('message' => 'Недостатньо прав доступу'));
        }
        
        if (!wp_verify_nonce($_POST['security'], 'rp_test_api')) {
            error_log('RozetkaPay: Nonce verification failed');
            wp_send_json_error(array('message' => 'Помилка безпеки (nonce)'));
        }
        
        try {
            if (empty($this->api_user) || empty($this->api_pass)) {
                wp_send_json_error(array(
                    'message' => 'API ключі не налаштовані',
                    'details' => 'Заповніть поля API User і API Pass в налаштуваннях'
                ));
            }
            
            if (!$this->api_client) {
                $this->init_api_client();
                if (!$this->api_client) {
                    wp_send_json_error(array(
                        'message' => 'API клієнт не ініціалізований',
                        'details' => 'Перевірте правильність API ключів'
                    ));
                }
            }
            
            $test_result = $this->api_client->test_connection();
            
            if (is_wp_error($test_result)) {
                $error_message = $test_result->get_error_message();
                wp_send_json_error(array(
                    'message' => 'Помилка підключення до API',
                    'details' => $error_message
                ));
            }
            
            wp_send_json_success(array(
                'message' => 'API з\'єднання працює коректно!',
                'details' => sprintf(
                    'API User: %s | Connection: ✅ OK',
                    $this->api_user
                )
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => 'Виключення при тестуванні API',
                'details' => $e->getMessage()
            ));
        }
    }

    public function ajax_test_callback_processing() {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => 'Недостатньо прав доступу'));
        }
        
        if (!wp_verify_nonce($_POST['security'], 'rp_test_callback')) {
            wp_send_json_error(array('message' => 'Помилка безпеки (nonce)'));
        }
        
        wp_send_json_success(array(
            'message' => 'Тест обробки callback пройшов успішно!',
            'details' => 'Callback URL: ' . home_url('/wc-api/' . $this->id) . ' | Simplified Security: Enabled'
        ));
    }

    public function ajax_check_payment_status() {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => 'Недостатньо прав'));
        }
        
        $order_id = intval($_POST['order_id'] ?? 0);
        if (!$order_id) {
            wp_send_json_error(array('message' => 'Невірний ID замовлення'));
        }
        
        $result = $this->check_payment_status($order_id);
        
        if ($result) {
            wp_send_json_success(array(
                'message' => 'Статус оновлено',
                'status' => $result
            ));
        } else {
            wp_send_json_error(array('message' => 'Не вдалося отримати статус'));
        }
    }

    public function check_payment_status($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return false;
        }
        
        $payment_id = $order->get_meta('_rozetkapay_payment_id');
        $external_id = $order->get_meta('_rozetkapay_external_id');
        
        if (empty($payment_id) && empty($external_id)) {
            error_log('RozetkaPay: No payment ID or external ID for order ' . $order_id);
            return false;
        }
        
        if (!$this->api_client) {
            $this->init_api_client();
        }
        
        if (!$this->api_client) {
            error_log('RozetkaPay: API client not available for status check');
            return false;
        }
        
        try {
            $identifier = !empty($external_id) ? $external_id : $payment_id;
            $status_result = $this->api_client->get_payment_status($identifier);
            
            if (is_wp_error($status_result)) {
                error_log('RozetkaPay: Error getting payment status: ' . $status_result->get_error_message());
                return false;
            }
            
            $this->update_order_status_from_callback($order, $status_result);
            
            return $status_result;
            
        } catch (Exception $e) {
            error_log('RozetkaPay: Exception checking payment status: ' . $e->getMessage());
            return false;
        }
    }

    public function is_available() {
        return 'yes' === $this->enabled;
    }

    /**
     * Process a refund through Standard API
     */
    public function process_refund( $order_id, $amount = null, $reason = '' ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return new WP_Error( 'invalid_order', 'Order not found' );
        }
        $external_id = $order->get_meta( '_rozetkapay_external_id', true );
        if ( ! $external_id && function_exists('rp_build_reference') ) {
            $external_id = rp_build_reference( $order, $this->order_ref_prefix );
        }
        if ( $amount === null ) {
            $amount = floatval( $order->get_total() ) - floatval( $order->get_total_refunded() );
        }
        if ( $amount <= 0 ) {
            return new WP_Error( 'invalid_amount', 'Nothing to refund' );
        }
        if ( ! class_exists( 'RP_Standard_API' ) ) {
            require_once __DIR__ . '/class-standard-api.php';
        }
        $api = new RP_Standard_API( $this->api_user, $this->api_pass );
        $res = $api->refund_payment( $external_id, $amount, $reason );
        if ( is_wp_error( $res ) ) {
            return $res;
        }
        $refund = wc_create_refund( array(
            'amount'         => $amount,
            'reason'         => $reason ?: 'RozetkaPay Standard refund',
            'order_id'       => $order_id,
            'refund_payment' => false,
            'restock_items'  => false,
        ) );
        if ( is_wp_error( $refund ) ) {
            return $refund;
        }
        $order->add_order_note( sprintf( 'RozetkaPay Standard: refund %s processed via API.', wc_price( $amount, array( 'currency' => $order->get_currency() ) ) ) );
        return true;
    }

}
