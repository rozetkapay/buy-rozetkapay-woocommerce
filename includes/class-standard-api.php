<?php
/**
 * RozetkaPay Standard API Client
 * ВИПРАВЛЕНА ВЕРСІЯ З ПІДТРИМКОЮ ПОВЕРНЕНЬ
 * 
 * Файл: includes/class-standard-api.php
 */

if (!defined('ABSPATH')) {
    exit;
}

// ✅ Перевіряємо, чи не оголошений вже клас
if ( class_exists( 'RP_Standard_API' ) ) {
    return;
}

class RP_Standard_API {
    
    private $base_url = 'https://api.rozetkapay.com/api';
    private $api_user;
    private $api_pass;
    
    public function __construct($api_user, $api_pass) {
        $this->api_user = $api_user;
        $this->api_pass = $api_pass;
        
        error_log("=== RP_Standard_API: Ініціалізація ===");
        error_log("API User: " . substr($api_user, 0, 8) . "***");
    }
    
    /**
     * ✅ Тестування з’єднання
     */
    public function test_connection() {
        error_log("RP_Standard_API: Тестування з’єднання...");
        
        $url = $this->base_url . '/payments/v1/status';
        
        $args = array(
            'method' => 'GET',
            'timeout' => 15,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($this->api_user . ':' . $this->api_pass),
                'Content-Type' => 'application/json',
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . ' RozetkaPay/1.0'
            ),
            'sslverify' => true
        );
        
        error_log("RP_Standard_API: Відправка тестового запиту на " . $url);
        
        $response = wp_remote_get($url, $args);
        
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            error_log("RP_Standard_API: ❌ Помилка WordPress: " . $error_message);
            return new WP_Error('connection_error', 'Помилка з\'єднання: ' . $error_message);
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        error_log("RP_Standard_API: HTTP код: " . $http_code);
        error_log("RP_Standard_API: Відповідь: " . $response_body);
        
        switch ($http_code) {
            case 200:
                error_log("RP_Standard_API: ✅ Тест з’єднання успішний (HTTP 200)");
                return true;
                
            case 401:
                error_log("RP_Standard_API: ❌ Невірні API ключі (HTTP 401)");
                return new WP_Error('auth_error', 'Невірні API ключі. Перевірте API User і API Pass.');
                
            case 403:
                error_log("RP_Standard_API: ❌ Доступ заборонено (HTTP 403)");
                return new WP_Error('permission_error', 'Доступ заборонено. Перевірте права API ключів.');
                
            case 404:
                error_log("RP_Standard_API: Endpoint не знайдено, пробуємо створити тестовий платіж...");
                return $this->test_with_minimal_payment();
                
            case 500:
            case 502:
            case 503:
                error_log("RP_Standard_API: ❌ Сервер RozetkaPay тимчасово недоступний");
                return new WP_Error('server_error', 'Сервер RozetkaPay тимчасово недоступний. Спробуйте пізніше.');
                
            default:
                error_log("RP_Standard_API: ❌ Неочікуваний HTTP код: " . $http_code);
                if ($http_code < 500) {
                    return $this->test_with_minimal_payment();
                }
                return new WP_Error('unexpected_response', 'Неочікувана відповідь сервера (HTTP ' . $http_code . ')');
        }
    }

    /**
     * ✅ Альтернативний спосіб тестування через мінімальний платіж
     */
    private function test_with_minimal_payment() {
        error_log("RP_Standard_API: Альтернативне тестування через створення платежу...");
        
        $test_data = array(
            'mode' => 'hosted',
            'external_id' => 'connection-test-' . time() . '-' . rand(1000, 9999),
            'amount' => 0.01,
            'currency' => 'UAH',
            'description' => 'API Connection test',
            'result_url' => home_url(),
            'callback_url' => home_url('/wc-api/rp_standard')
        );
        
        $result = $this->send_post_request('/payments/v1/new', $test_data);
        
        if ($result['success']) {
            error_log("RP_Standard_API: ✅ Альтернативний тест успішний — API працює коректно");
            return true;
        } else {
            error_log("RP_Standard_API: ❌ Альтернативний тест неуспішний: " . $result['error']);
            
            if (isset($result['http_code'])) {
                switch ($result['http_code']) {
                    case 401:
                        return new WP_Error('auth_error', 'Невірні API ключі');
                    case 403:
                        return new WP_Error('permission_error', 'Недостатньо прав для створення платежів');
                    case 422:
                        error_log("RP_Standard_API: ✅ HTTP 422 під час тесту — це нормально, отже API доступний");
                        return true;
                    default:
                        return new WP_Error('api_error', $result['error']);
                }
            }
            
            return new WP_Error('connection_test_failed', $result['error']);
        }
    }

    /**
     * ✅ Створення платежу
     */
    public function create_payment($payment_data) {
        error_log("=== RP_Standard_API: Створення платежу ===");
        error_log("Початкові дані: " . json_encode($payment_data));
        
        if (empty($payment_data['external_id']) || empty($payment_data['amount'])) {
            $error = 'Відсутні обов’язкові поля: external_id або amount';
            error_log("RP_Standard_API ERROR: " . $error);
            return new WP_Error('missing_fields', $error);
        }
        
        $normalized_data = $this->normalize_payment_data($payment_data);
        
        if (!$normalized_data) {
            return new WP_Error('validation_error', 'Помилка валідації даних платежу');
        }
        
        $result = $this->send_post_request('/payments/v1/new', $normalized_data);
        
        if (!$result['success']) {
            error_log("RP_Standard_API: ❌ Помилка створення платежу: " . $result['error']);
            return new WP_Error('api_error', $result['error']);
        }
        
        $response = $result['response'];
        
        if (!isset($response['action']['value']) || empty($response['action']['value'])) {
            error_log("RP_Standard_API: ❌ Відсутній URL для редиректу у відповіді API");
            error_log("RP_Standard_API: Структура відповіді: " . json_encode($response));
            return new WP_Error('missing_redirect_url', 'API не повернув URL для оплати');
        }
        
        $payment_url = $response['action']['value'];
        
        error_log("RP_Standard_API: ✅ Платіж успішно створений");
        error_log("RP_Standard_API: ✅ URL для оплати: " . $payment_url);
        
        return array(
            'success' => true,
            'is_success' => true,
            'id' => $response['id'] ?? '',
            'external_id' => $response['external_id'] ?? '',
            'payment_url' => $payment_url,
            'action' => array(
                'value' => $payment_url
            ),
            'details' => $response['details'] ?? array(),
            'response' => $response
        );
    }
    
    /**
     * Нормалізація даних платежу - ВИПРАВЛЕНА ВЕРСІЯ
     */
    private function normalize_payment_data($data) {
        if (empty($data['external_id']) || empty($data['amount'])) {
            error_log("RP_Standard_API: Відсутні обов’язкові поля");
            return false;
        }
        
        $unique_external_id = (string) $data['external_id'];
        
        $normalized = array(
            'mode' => 'hosted',
            'external_id' => $unique_external_id,
            'amount' => floatval($data['amount']),
            'currency' => 'UAH',
            'description' => $this->sanitize_description($data['description'] ?? ''),
            'result_url' => $data['result_url'],
            'callback_url' => $data['callback_url']
        );
        
        if (isset($data['customer']) && is_array($data['customer'])) {
            $customer = array();
            
            if (!empty($data['customer']['email'])) {
                $customer['email'] = $data['customer']['email'];
            }
            
            if (!empty($data['customer']['name'])) {
                $clean_name = $this->sanitize_text($data['customer']['name']);
                if (!empty($clean_name)) {
                    $customer['name'] = $clean_name;
                }
            }
            
            if (!empty($customer)) {
                $normalized['customer'] = $customer;
            }
        }
        
        // ✅ СПРОЩЕНО: передаємо товари як є (як в офіційному плагіні)
        if (isset($data['products']) && is_array($data['products']) && !empty($data['products'])) {
            error_log('RozetkaPay API: Products received: ' . json_encode($data['products']));
            $normalized['products'] = $data['products']; // Передаємо як є, без змін
            error_log('RozetkaPay API: Products passed as-is to API');
        }
        error_log("RP_Standard_API: Фінальні дані: " . json_encode($normalized));
        return $normalized;
    } 

    /**
     * Очищення опису платежу
     */
    private function sanitize_description($description) {
        if (empty($description)) {
            return 'Оплата через RozetkaPay';
        }
        
        $clean = strip_tags($description);
        $clean = preg_replace('/\s+/', ' ', $clean);
        // Дозволяємо українські символи
        $clean = preg_replace('/[^a-zA-Zа-яА-Яі�є�Ї�0-9\s\-_\.\#№]/u', '', $clean);
        
        if (strlen($clean) > 100) {
            $clean = substr($clean, 0, 97) . '...';
        }
        
        $clean = trim($clean);
        if (empty($clean)) {
            $clean = 'Оплата замовлення';
        }
        
        error_log("RP_Standard_API: Опис: '" . $clean . "'");
        return $clean;
    }
    
    /**
     * Очищення тексту (імена, адреси)
     */
    private function sanitize_text($text) {
        if (empty($text)) {
            return '';
        }
        
        $clean = strip_tags(trim($text));
        $clean = preg_replace('/\s+/', ' ', $clean);
        $clean = preg_replace('/[^\p{L}\p{N}\s\-_\.]/u', '', $clean);
        
        if (strlen($clean) > 100) {
            $clean = substr($clean, 0, 100);
        }
        
        return trim($clean);
    }
    
    /**
     * JSON-кодування з нормалізацією і жорсткою помилкою при провалі
     */
    private function encode_json_or_fail( $payload ) {
        $normalize = function( &$v ) use (&$normalize) {
            if (is_string($v)) {
                $v = wp_check_invalid_utf8($v, true);
                // Прибираємо невидимі керуючі символи
                $v = preg_replace('/\p{C}+/u', '', $v);
                $v = trim($v);
            } elseif (is_float($v) && !is_finite($v)) {
                $v = 0;
            } elseif (is_object($v)) {
                // Перетворюємо в масив за можливості
                if (method_exists($v, 'jsonSerialize')) {
                    $v = $v->jsonSerialize();
                } else {
                    $v = (array) $v;
                }
            } elseif (is_array($v)) {
                foreach ($v as &$vv) { $normalize($vv); }
            }
        };
        $normalize($payload);
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
        }
        $json = wp_json_encode( $payload, $flags );
        if ( ! $json ) {
            $msg = function_exists('json_last_error_msg') ? json_last_error_msg() : 'json encode error';
            error_log('RP_Standard_API: ❌ Не вдалося закодувати payload у JSON: ' . $msg);
            // Прагнемо знайти проблемне поле (простий пошук)
            $offender = $this->find_json_offender( $payload );
            if ($offender) { error_log('RP_Standard_API: Проблемний шлях у payload: ' . $offender); }
            return false;
        }
        return $json;
    }

    /**
     * Діагностика: знаходить шлях до першого значення, яке не кодується в JSON
     */
    private function find_json_offender( $data, $path = '$' ) {
        if (is_array($data)) {
            foreach ($data as $k => $v) {
                $p = $this->find_json_offender($v, $path.'['.json_encode($k).']');
                if ($p) { return $p; }
            }
            return null;
        }
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) { $flags |= JSON_INVALID_UTF8_SUBSTITUTE; }
        $ok = json_encode($data, $flags);
        return ($ok === false || $ok === null) ? ($path.' (type='.gettype($data).')') : null;
    }

    /**
     * Відправка POST-запиту
     */
    public function send_post_request($endpoint, $data) {
        $url = $this->base_url . $endpoint;
        
        $args = array(
            'method' => 'POST',
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($this->api_user . ':' . $this->api_pass),
                'Content-Type' => 'application/json',
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . ' RozetkaPay/1.0'
            ),
            'body' => ($__json = $this->encode_json_or_fail($data)),
            'data_format' => 'body',
            'sslverify' => true
        );
        
        error_log("RP_Standard_API: Відправка запиту на " . $url);
        error_log("RP_Standard_API: Заголовки: " . json_encode($args['headers']));
        error_log('RP_Standard_API: Довжина даних: ' . (is_string($args['body']) ? strlen($args['body']) : var_export($args['body'], true)));
        
        if ($__json === false) {
            return array('success'=>false,'error'=>'JSON encode failed, request not sent');
        }
        $response = wp_remote_post($url, $args);
        
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            error_log("RP_Standard_API: Помилка WordPress: " . $error_message);
            return array(
                'success' => false,
                'error' => 'Помилка з\'єднання: ' . $error_message
            );
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        error_log("RP_Standard_API: HTTP код: " . $http_code);
        error_log("RP_Standard_API: Відповідь: " . $response_body);
        
        // Якщо HTTP 500, пробуємо ще раз через 2 секунди
        if ($http_code === 500) {
            error_log("RP_Standard_API: HTTP 500 - повторна спроба через 2 сек...");
            sleep(2);
            
            if ($__json === false) {
                return array('success'=>false,'error'=>'JSON encode failed, request not sent');
            }
            $response = wp_remote_post($url, $args);
            if (!is_wp_error($response)) {
                $http_code = wp_remote_retrieve_response_code($response);
                $response_body = wp_remote_retrieve_body($response);
                error_log("RP_Standard_API: Повторний запит - HTTP: " . $http_code);
            }
        }
        
        $parsed_response = json_decode($response_body, true);
        
        if ($http_code === 200) {
            error_log("RP_Standard_API: ✅ Успішна відповідь");
            return array(
                'success' => true,
                'response' => $parsed_response
            );
        }
        
        $error_message = $this->get_error_message($http_code, $parsed_response);
        error_log("RP_Standard_API: ❌ Помилка: " . $error_message);
        
        return array(
            'success' => false,
            'error' => $error_message,
            'http_code' => $http_code,
            'response' => $parsed_response
        );
    }
    
    /**
     * Отримання зрозумілого повідомлення про помилку
     */
    private function get_error_message($http_code, $parsed_response) {
        $message = '';
        if (isset($parsed_response['message'])) {
            $message = $parsed_response['message'];
        }
        
        switch ($http_code) {
            case 400:
                return 'Невірні дані запиту: ' . $message;
            case 401:
                return 'Невірні API ключі';
            case 403:
                return 'Доступ заборонено. Перевірте права API ключів або ліміти акаунта';
            case 422:
                return 'Помилка валідації даних: ' . $message;
            case 500:
                if (strpos($message, 'amount') !== false) {
                    return 'Проблема із сумою платежу. Можливо, перевищено ліміт акаунта: ' . $message;
                }
                return 'Внутрішня помилка сервера RozetkaPay. Спробуйте пізніше або зверніться до підтримки: ' . $message;
            case 503:
                return 'Сервіс тимчасово недоступний';
            default:
                return 'Помилка API (HTTP ' . $http_code . '): ' . $message;
        }
    }
    
    /**
     * ✅ Повернення платежу
     */
    public function refund_payment($order_id, $amount, $currency = 'UAH', $reason = '') {
        error_log("RP_Standard_API: Ініціація повернення для замовлення " . $order_id);
        
        $refund_data = array(
            'external_id' => 'refund-' . $order_id . '-' . time(),
            'amount' => floatval($amount),
            'currency' => $currency,
            'reason' => !empty($reason) ? $reason : 'Refund requested'
        );
        
        $result = $this->send_post_request('/payments/v1/refund', $refund_data);
        
        if ($result['success']) {
            error_log("RP_Standard_API: ✅ Повернення успішно ініційовано");
            return $result['response'];
        } else {
            error_log("RP_Standard_API: ❌ Помилка повернення: " . $result['error']);
            return new WP_Error('refund_error', $result['error']);
        }
    }

    /**
     * ✅ Отримання інформації про акаунт
     */
    public function get_account_info() {
        error_log("RP_Standard_API: Отримання інформації про акаунт...");
        
        $url = $this->base_url . '/account/info';
        
        $args = array(
            'method' => 'GET',
            'timeout' => 15,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($this->api_user . ':' . $this->api_pass),
                'Content-Type' => 'application/json',
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . ' RozetkaPay/1.0'
            ),
            'sslverify' => true
        );
        
        $response = wp_remote_get($url, $args);
        
        if (is_wp_error($response)) {
            return new WP_Error('connection_error', $response->get_error_message());
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($http_code === 200) {
            $data = json_decode($response_body, true);
            return $data;
        }
        
        return new WP_Error('account_info_error', 'Не вдалося отримати інформацію про акаунт');
    }

    /**
     * ✅ НОВЕ: Отримання статусу платежу з підтримкою повернень
     */
    public function get_payment_status( $external_id ) {
        if ( empty( $external_id ) ) {
            return new WP_Error( 'missing_external_id', 'Empty external_id' );
        }
        
        // Використовуємо основний endpoint для отримання інформації про платіж
        $result = $this->make_request( 'GET', '/payments/v1/info?external_id=' . urlencode( (string) $external_id ) );
        
        if ( is_wp_error( $result ) ) {
            error_log( 'RP_Standard_API: Помилка отримання статусу платежу - ' . $result->get_error_message() );
            return $result;
        }
        
        // Перевіряємо наявність інформації про повернення у відповіді
        if ( isset( $result['refunds'] ) || isset( $result['refund_amount'] ) || isset( $result['refunded'] ) ) {
            error_log( 'RP_Standard_API: Знайдена інформація про повернення у відповіді API' );
            error_log( 'RP_Standard_API: Структура відповіді: ' . json_encode( array_keys( $result ) ) );
        }
        
        return $result;
    }

    /**
     * ✅ НОВЕ: Отримання детальної інформації про повернення
     */
    public function get_refunds_info( $external_id ) {
        if ( empty( $external_id ) ) {
            return new WP_Error( 'missing_external_id', 'Empty external_id' );
        }
        
        error_log( 'RP_Standard_API: Запит інформації про повернення для external_id: ' . $external_id );
        
        // Пробуємо декілька можливих endpoints для отримання інформації про повернення
        $endpoints_to_try = array(
            '/payments/v1/refunds?external_id=' . urlencode( $external_id ),
            '/payments/v1/info?external_id=' . urlencode( $external_id ) . '&include_refunds=true',
            '/refunds/v1/list?external_id=' . urlencode( $external_id ),
            '/payments/v1/' . urlencode( $external_id ) . '/refunds'
        );
        
        foreach ( $endpoints_to_try as $endpoint ) {
            error_log( 'RP_Standard_API: Пробуємо endpoint: ' . $endpoint );
            
            $result = $this->make_request( 'GET', $endpoint );
            
            if ( ! is_wp_error( $result ) && ! empty( $result ) ) {
                error_log( 'RP_Standard_API: ✅ Успішна відповідь від endpoint: ' . $endpoint );
                error_log( 'RP_Standard_API: Структура відповіді: ' . json_encode( array_keys( is_array( $result ) ? $result : array() ) ) );
                return $result;
            } else {
                error_log( 'RP_Standard_API: ❌ Endpoint не спрацював: ' . $endpoint . ( is_wp_error( $result ) ? ' - ' . $result->get_error_message() : '' ) );
            }
        }
        
        // Якщо спеціальні endpoints не працюють, пробуємо отримати з основної інформації про платіж
        $payment_info = $this->get_payment_status( $external_id );
        
        if ( ! is_wp_error( $payment_info ) && is_array( $payment_info ) ) {
            // Шукаємо інформацію про повернення в основній відповіді
            $refund_data = array();
            
            if ( isset( $payment_info['refunds'] ) && is_array( $payment_info['refunds'] ) ) {
                $refund_data['refunds'] = $payment_info['refunds'];
            }
            
            if ( isset( $payment_info['refund_amount'] ) ) {
                $refund_data['total_refunded'] = floatval( $payment_info['refund_amount'] );
            }
            
            if ( isset( $payment_info['refunded'] ) ) {
                $refund_data['is_refunded'] = intval( $payment_info['refunded'] );
            }
            
            // Перевіряємо інші можливі поля
            $possible_refund_fields = array( 'refund_status', 'refund_details', 'refunded_amount', 'partial_refunds' );
            foreach ( $possible_refund_fields as $field ) {
                if ( isset( $payment_info[ $field ] ) ) {
                    $refund_data[ $field ] = $payment_info[ $field ];
                }
            }
            
            if ( ! empty( $refund_data ) ) {
                error_log( 'RP_Standard_API: ✅ Знайдена інформація про повернення в основній відповіді' );
                error_log( 'RP_Standard_API: Дані про повернення: ' . json_encode( $refund_data ) );
                return $refund_data;
            }
        }
        
        error_log( 'RP_Standard_API: ❌ Не вдалося отримати інформацію про повернення' );
        return new WP_Error( 'no_refund_info', 'Не вдалося отримати інформацію про повернення для цього платежу' );
    }

    private function make_request( $method, $endpoint, $data = null ) {
        $url = $this->base_url . $endpoint;
        
        $args = array(
            'method' => strtoupper( $method ),
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($this->api_user . ':' . $this->api_pass),
                'Content-Type' => 'application/json',
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . ' RozetkaPay/1.0'
            ),
            'sslverify' => true
        );

        if ( $data && in_array( strtoupper( $method ), [ 'POST', 'PUT', 'PATCH' ], true ) ) {
            $args['body'] = wp_json_encode( $data );
        }

        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        if ( $code >= 400 ) {
            return new WP_Error( 
                'api_error', 
                sprintf( 'API returned error %d: %s', $code, $body )
            );
        }

        $decoded = json_decode( $body, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new WP_Error( 'json_error', 'Invalid JSON response: ' . json_last_error_msg() );
        }

        return $decoded;
    }


    /**
     * ✅ Підтвердження двоетапного платежу
     */
    public function confirm_payment($payment_id, $amount = null) {
        error_log("RP_Standard_API: Підтвердження платежу " . $payment_id);
        
        $confirm_data = array(
            'action' => 'confirm'
        );
        
        if ($amount !== null) {
            $confirm_data['amount'] = floatval($amount);
        }
        
        $result = $this->send_post_request('/payments/v1/' . urlencode($payment_id) . '/confirm', $confirm_data);
        
        if ($result['success']) {
            error_log("RP_Standard_API: ✅ Платіж успішно підтверджений");
            return $result['response'];
        } else {
            error_log("RP_Standard_API: ❌ Помилка підтвердження платежу: " . $result['error']);
            return new WP_Error('confirm_error', $result['error']);
        }
    }

    /**
     * ✅ Скасування зарезервованого платежу
     */
    public function cancel_payment($payment_id) {
        error_log("RP_Standard_API: Скасування платежу " . $payment_id);
        
        $cancel_data = array(
            'action' => 'cancel'
        );
        
        $result = $this->send_post_request('/payments/v1/' . urlencode($payment_id) . '/cancel', $cancel_data);
        
        if ($result['success']) {
            error_log("RP_Standard_API: ✅ Платіж успішно скасовано");
            return $result['response'];
        } else {
            error_log("RP_Standard_API: ❌ Помилка скасування платежу: " . $result['error']);
            return new WP_Error('cancel_error', $result['error']);
        }
    }

    /**
     * ✅ Валідація callback-даних (БЕЗ ПЕРЕВІРКИ SIGNATURE)
     */
    public function validate_callback_data($data) {
        if (!is_array($data)) {
            return false;
        }
        
        // Перевіряємо обов’язкові поля
        $required_fields = array('id', 'external_id', 'status');
        
        foreach ($required_fields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                error_log("RP_Standard_API: Відсутнє обов’язкове поле в callback: " . $field);
                return false;
            }
        }
        
        // Перевіряємо валідність статусу
        $valid_statuses = array('success', 'failure', 'processing', 'pending', 'cancelled');
        if (!in_array($data['status'], $valid_statuses)) {
            error_log("RP_Standard_API: Невірний статус у callback: " . $data['status']);
            return false;
        }
        
        return true;
    }

    /**
     * ✅ Безпечне логування (без чутливих даних)
     */
    public function log_safe($level, $message, $context = array()) {
        // Прибираємо чутливі дані з контексту
        if (isset($context['api_pass'])) {
            $context['api_pass'] = '***';
        }
        if (isset($context['authorization'])) {
            $context['authorization'] = 'Basic ***';
        }
        
        $log_message = "[" . strtoupper($level) . "] " . $message;
        if (!empty($context)) {
            $log_message .= " | Context: " . json_encode($context);
        }
        
        error_log("RP_Standard_API: " . $log_message);
    }
}
