<?php
/**
 * RozetkaPay API Client - ВИПРАВЛЕНА ВЕРСІЯ з підтримкою повернень
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RP_Payparts_API {
    private $api_user;
    private $api_pass;
    private $test_mode;
    private $api_base;
    private $logger;
    
    public function __construct( $api_user, $api_pass, $test_mode = false ) {
        $this->api_user = $api_user;
        $this->api_pass = $api_pass;
        $this->test_mode = false;
        $this->api_base = 'https://api.rozetkapay.com/api/payparts/v1';
        $this->logger = wc_get_logger();
    }
    
    /**
     * Get authorization headers
     */
    private function get_auth_headers() {
        $token = base64_encode( $this->api_user . ':' . $this->api_pass );
        return [
            'Authorization' => 'Basic ' . $token,
            'Content-Type'  => 'application/json',
            'User-Agent'    => 'RozetkaPay-Payparts/2.0.3 WordPress/' . get_bloginfo( 'version' ),
        ];
    }
    
    /**
     * Make HTTP request
     */
    private function make_request( $method, $endpoint, $data = null ) {
        $url = $this->api_base . $endpoint;
        $headers = $this->get_auth_headers();
        
        $args = [
            'method'  => strtoupper( $method ),
            'headers' => $headers,
            'timeout' => 30,
        ];

        if ( $data && in_array( strtoupper( $method ), [ 'POST', 'PUT', 'PATCH' ], true ) ) {
            $args['body'] = wp_json_encode( $data );
        }

        $this->logger->debug( 
            sprintf( '%s request to %s', $method, $endpoint ), 
            [ 'source' => 'rp-payparts-api', 'data' => $data ] 
        );

        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            $this->logger->error( 
                'HTTP request failed: ' . $response->get_error_message(), 
                [ 'source' => 'rp-payparts-api' ] 
            );
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        $this->logger->debug( 
            sprintf( 'API response: %d', $code ), 
            [ 'source' => 'rp-payparts-api', 'body' => $body ] 
        );

        if ( $code >= 400 ) {
            return new WP_Error( 
                'api_error', 
                sprintf( 'API returned error %d: %s', $code, $body ),
                [ 'status_code' => $code, 'response_body' => $body ]
            );
        }

        $decoded = json_decode( $body, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new WP_Error( 
                'json_error', 
                'Invalid JSON response: ' . json_last_error_msg(),
                [ 'response_body' => $body ]
            );
        }

        return $decoded;
    }
    
    /**
     * Fetch available banks
     */
    public function fetch_banks() {
        $result = $this->make_request( 'GET', '/banks/info?include_fees=true' );
        
        $this->logger->debug( 'Bank response: ' . print_r( $result, true ), [ 'source' => 'rp-payparts-api' ] );
        
        if ( is_wp_error( $result ) ) {
            $this->logger->error( 
                'Failed to fetch banks: ' . $result->get_error_message(), 
                [ 'source' => 'rp-payparts-api' ] 
            );
            return [];
        }

        return is_array( $result ) ? $result : [];
    }
    
    /**
     * Create payment
     */
    public function create_payment( $payload ) {
        $result = $this->make_request( 'POST', '/order/create', $payload );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        // Логуємо відповідь API для відладки
        $this->logger->debug( 
            'Payment creation response: ' . print_r( $result, true ), 
            [ 'source' => 'rp-payparts-api' ] 
        );

        return $result;
    }

    /**
     * ВИПРАВЛЕНИЙ метод отримання інформації про операцію PayParts
     */
    public function get_operation_info( $external_id, $operation_id = null ) {
        if ( empty( $external_id ) ) {
            return new WP_Error( 'missing_external_id', 'external_id is required for PayParts' );
        }
        
        $endpoint = '/info/operation';
        $params = array(
            'external_id' => urlencode( (string) $external_id )
        );
        
        if ( ! empty( $operation_id ) ) {
            $params['operation_id'] = urlencode( (string) $operation_id );
        }
        
        $url_params = http_build_query( $params );
        $full_endpoint = $endpoint . '?' . $url_params;
        
        $this->logger->debug( 
            'PayParts API: Requesting operation info', 
            [ 'source' => 'rp-payparts-api', 'endpoint' => $full_endpoint, 'params' => $params ] 
        );
        
        $result = $this->make_request( 'GET', $full_endpoint );
        
        if ( is_wp_error( $result ) ) {
            $this->logger->error( 
                'PayParts API: Failed to get operation info - ' . $result->get_error_message(), 
                [ 'source' => 'rp-payparts-api', 'external_id' => $external_id, 'operation_id' => $operation_id ] 
            );
            return $result;
        }
        
        $this->logger->debug( 
            'PayParts API: Operation info response', 
            [ 'source' => 'rp-payparts-api', 'response' => $result ] 
        );
        
        return $result;
    }

    /**
     * ВИПРАВЛЕНИЙ метод отримання всіх операцій за external_id
     */
    public function get_operations_info( $external_id ) {
        if ( empty( $external_id ) ) {
            return new WP_Error( 'missing_external_id', 'external_id is required' );
        }
        
        $endpoint = '/info?external_id=' . urlencode( (string) $external_id );
        
        $this->logger->debug( 
            'PayParts API: Requesting operations info', 
            [ 'source' => 'rp-payparts-api', 'external_id' => $external_id ] 
        );
        
        $result = $this->make_request( 'GET', $endpoint );
        
        if ( is_wp_error( $result ) ) {
            $this->logger->error( 
                'PayParts API: Failed to get operations info - ' . $result->get_error_message(), 
                [ 'source' => 'rp-payparts-api', 'external_id' => $external_id ] 
            );
            return $result;
        }
        
        $this->logger->debug( 
            'PayParts API: Operations info response', 
            [ 'source' => 'rp-payparts-api', 'response' => $result ] 
        );
        
        return $result;
    }

    /**
     * ВИПРАВЛЕНИЙ метод get_payment_status для зворотної сумісності з CRON
     */
    public function get_payment_status( $operation_id, $external_id = '' ) {
        // Для PayParts API пріоритет у external_id
        if ( ! empty( $external_id ) ) {
            if ( ! empty( $operation_id ) ) {
                // Якщо є обидва параметри, використовуємо більш точний запит
                return $this->get_operation_info( $external_id, $operation_id );
            } else {
                // Якщо тільки external_id, отримуємо всі операції
                return $this->get_operations_info( $external_id );
            }
        }
        
        // Якщо тільки operation_id, намагаємося знайти через пошук замовлень
        if ( ! empty( $operation_id ) ) {
            // Шукаємо замовлення з таким operation_id
            $orders = wc_get_orders( array(
                'limit' => 1,
                'return' => 'objects',
                'payment_method' => 'rp_payparts',
                'meta_key' => '_rp_payparts_payment_id',
                'meta_value' => $operation_id,
                'meta_compare' => '=',
            ) );
            
            if ( $orders ) {
                $order = current( $orders );
                $external_id = $order->get_meta( '_rp_reference', true ) ?: (string) $order->get_id();
                return $this->get_operation_info( $external_id, $operation_id );
            }
        }
        
        return new WP_Error( 'missing_params', 'Need external_id or valid operation_id for PayParts status' );
    }

    /**
     * ПОКРАЩЕНА перевірка підпису callback за специфікацією RozetkaPay
     */
    public function verify_callback_enhanced( $payload, $signature ) {
        try {
            if ( empty( $payload ) || empty( $signature ) ) {
                $this->logger->error( 
                    'PayParts: Callback verification failed - empty payload or signature', 
                    [ 'source' => 'rp-payparts-api' ] 
                );
                return false;
            }
            
            // Прибираємо можливі префікси з підпису
            $signature = str_replace( array( 'sha1=', 'SHA1=', 'signature=' ), '', trim( $signature ) );
            
            // Формула RozetkaPay: signature = base64url_encode( sha1( password + base64url(json_body) + password, true ) )
            $body_base64url = rtrim( strtr( base64_encode( $payload ), '+/', '-_' ), '=' );
            $string_to_sign = $this->api_pass . $body_base64url . $this->api_pass;
            $hash_binary = sha1( $string_to_sign, true );
            $expected_signature = rtrim( strtr( base64_encode( $hash_binary ), '+/', '-_' ), '=' );
            
            // Прибираємо можливі відмінності у padding
            $signature_clean = rtrim( $signature, '=' );
            $expected_clean = rtrim( $expected_signature, '=' );
            
            $is_valid = hash_equals( $expected_clean, $signature_clean );
            
            if ( $is_valid ) {
                $this->logger->debug( 
                    'PayParts: Callback signature verified successfully', 
                    [ 'source' => 'rp-payparts-api' ] 
                );
            } else {
                $this->logger->error( 
                    'PayParts: Callback signature verification failed', 
                    [ 
                        'source' => 'rp-payparts-api',
                        'expected' => $expected_clean,
                        'received' => $signature_clean,
                        'payload_length' => strlen( $payload ),
                        'string_to_sign_length' => strlen( $string_to_sign )
                    ] 
                );
            }
            
            return $is_valid;
            
        } catch ( Exception $e ) {
            $this->logger->error( 
                'PayParts: Callback verification exception - ' . $e->getMessage(), 
                [ 'source' => 'rp-payparts-api' ] 
            );
            return false;
        }
    }

    /**
     * Старий метод для зворотної сумісності (НЕ ВИКОРИСТОВУЄТЬСЯ)
     */
    public function verify_callback( $payload, $signature ) {
        // Перенаправляємо на новий метод
        return $this->verify_callback_enhanced( $payload, $signature );
    }

    public function test_connection() {
        if ( empty( $this->api_user ) || empty( $this->api_pass ) ) {
            return [
                'success' => false,
                'message' => __( 'Не вказані API ключі', 'rp-payparts' ),
            ];
        }
        
        $result = $this->make_request( 'GET', '/banks/info' );
        
        if ( is_wp_error( $result ) ) {
            return [
                'success' => false,
                'message' => $result->get_error_message(),
            ];
        }

        return [
            'success' => true,
            'message' => __( 'Підключення до API успішне', 'rp-payparts' ),
            'banks_count' => is_array( $result ) ? count( $result ) : 0,
        ];
    }

    /**
     * Test callback URL
     */
    public function test_callback_url( $callback_url ) {
        // Проста перевірка доступності callback URL
        $response = wp_remote_get( $callback_url, array(
            'timeout' => 10,
            'headers' => array(
                'User-Agent' => 'RozetkaPay-Test/1.0'
            )
        ) );

        if ( is_wp_error( $response ) ) {
            return array(
                'success' => false,
                'message' => 'Callback URL недоступний: ' . $response->get_error_message()
            );
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        
        // 400 — це нормально для callback URL без даних
        if ( in_array( $response_code, array( 200, 400, 405 ), true ) ) {
            return array(
                'success' => true,
                'message' => 'Callback URL доступний (код: ' . $response_code . ')'
            );
        }

        return array(
            'success' => false,
            'message' => 'Callback URL повертає код: ' . $response_code
        );
    }

    /**
     * Get API limits from banks data
     */
    public function get_api_limits() {
        $banks = $this->fetch_banks();
        
        if ( is_wp_error( $banks ) || empty( $banks ) ) {
            return array(
                'min_amount' => 0,
                'max_amount' => 0
            );
        }

        $min_amounts = array();
        $max_amounts = array();

        foreach ( $banks as $bank ) {
            if ( isset( $bank['min_amount'] ) && $bank['min_amount'] > 0 ) {
                $min_amounts[] = floatval( $bank['min_amount'] );
            }
            if ( isset( $bank['max_amount'] ) && $bank['max_amount'] > 0 ) {
                $max_amounts[] = floatval( $bank['max_amount'] );
            }
        }

        return array(
            'min_amount' => ! empty( $min_amounts ) ? min( $min_amounts ) : 0,
            'max_amount' => ! empty( $max_amounts ) ? max( $max_amounts ) : 0
        );
    }

    /**
     * Get detailed banks info for debugging
     */
    public function get_banks_debug_info() {
        $banks = $this->fetch_banks();
        
        if ( is_wp_error( $banks ) ) {
            return 'Error: ' . $banks->get_error_message();
        }

        if ( empty( $banks ) ) {
            return 'No banks returned from API';
        }

        $debug_info = array();
        foreach ( $banks as $bank ) {
            $bank_info = array(
                'name' => $bank['name'] ?? 'Unknown',
                'periods' => 'N/A',
                'min_amount' => $bank['min_amount'] ?? 'N/A',
                'max_amount' => $bank['max_amount'] ?? 'N/A'
            );

            // Намагаємося отримати періоди з різних можливих структур
            if ( isset( $bank['periods'] ) && is_array( $bank['periods'] ) ) {
                $periods = array();
                foreach ( $bank['periods'] as $period_data ) {
                    if ( isset( $period_data['period'] ) ) {
                        $periods[] = $period_data['period'];
                    }
                }
                $bank_info['periods'] = implode( ', ', $periods );
            } elseif ( isset( $bank['available_periods'] ) ) {
                $bank_info['periods'] = implode( ', ', $bank['available_periods'] );
            } elseif ( isset( $bank['terms'] ) ) {
                $bank_info['periods'] = implode( ', ', $bank['terms'] );
            }

            $debug_info[] = sprintf( 
                '%s: periods=[%s], min=%s, max=%s',
                $bank_info['name'],
                $bank_info['periods'],
                $bank_info['min_amount'],
                $bank_info['max_amount']
            );
        }

        return implode( "\n", $debug_info );
    }

    /**
     * НОВИЙ метод для відладки відповідей API
     */
    public function debug_api_response( $external_id, $operation_id = null ) {
        if ( ! $this->logger ) {
            return 'Logger not available';
        }
        
        $debug_info = array();
        
        // Тест підключення
        $test_result = $this->test_connection();
        $debug_info['connection_test'] = $test_result;
        
        if ( ! empty( $external_id ) ) {
            // Отримуємо інформацію про операцію
            if ( ! empty( $operation_id ) ) {
                $operation_info = $this->get_operation_info( $external_id, $operation_id );
                $debug_info['operation_info'] = $operation_info;
            }
            
            // Отримуємо всі операції
            $operations_info = $this->get_operations_info( $external_id );
            $debug_info['operations_info'] = $operations_info;
        }
        
        $this->logger->debug( 
            'PayParts API Debug Info', 
            [ 'source' => 'rp-payparts-api', 'debug_data' => $debug_info ] 
        );
        
        return $debug_info;
    }
    
    /**
     * НОВИЙ метод для відладки підпису callback
     */
    public function debug_signature_verification( $payload, $signature ) {
        $debug_info = array();
        
        // Оригінальні дані
        $debug_info['original_payload_length'] = strlen( $payload );
        $debug_info['original_signature'] = $signature;
        
        // Очищений підпис
        $clean_signature = str_replace( array( 'sha1=', 'SHA1=', 'signature=' ), '', trim( $signature ) );
        $debug_info['clean_signature'] = $clean_signature;
        
        // Base64URL-кодування payload
        $body_base64url = rtrim( strtr( base64_encode( $payload ), '+/', '-_' ), '=' );
        $debug_info['payload_base64url'] = $body_base64url;
        $debug_info['payload_base64url_length'] = strlen( $body_base64url );
        
        // Рядок для підпису
        $string_to_sign = $this->api_pass . $body_base64url . $this->api_pass;
        $debug_info['string_to_sign_length'] = strlen( $string_to_sign );
        $debug_info['password_length'] = strlen( $this->api_pass );
        
        // SHA1-хеш
        $hash_binary = sha1( $string_to_sign, true );
        $debug_info['hash_binary_length'] = strlen( $hash_binary );
        
        // Очікуваний підпис
        $expected_signature = rtrim( strtr( base64_encode( $hash_binary ), '+/', '-_' ), '=' );
        $debug_info['expected_signature'] = $expected_signature;
        
        // Порівняння
        $signature_clean = rtrim( $clean_signature, '=' );
        $expected_clean = rtrim( $expected_signature, '=' );
        $debug_info['signature_match'] = hash_equals( $expected_clean, $signature_clean );
        
        $this->logger->debug( 
            'PayParts: Signature Debug Info', 
            [ 'source' => 'rp-payparts-api', 'signature_debug' => $debug_info ] 
        );
        
        return $debug_info;
    }
    
    /**
     * НОВИЙ метод для генерації тестового підпису
     */
    public function generate_test_signature( $payload ) {
        if ( empty( $this->api_pass ) ) {
            return new WP_Error( 'missing_password', 'API password required for signature generation' );
        }
        
        // Алгоритм RozetkaPay
        $body_base64url = rtrim( strtr( base64_encode( $payload ), '+/', '-_' ), '=' );
        $string_to_sign = $this->api_pass . $body_base64url . $this->api_pass;
        $hash_binary = sha1( $string_to_sign, true );
        $signature = rtrim( strtr( base64_encode( $hash_binary ), '+/', '-_' ), '=' );
        
        return $signature;
    }
    
    /**
     * Перевірка доступності API endpoint
     */
    public function check_api_availability() {
        $endpoints_to_check = array(
            '/banks/info' => 'Banks info endpoint',
            '/info' => 'Payment info endpoint'
        );
        
        $results = array();
        
        foreach ( $endpoints_to_check as $endpoint => $description ) {
            $start_time = microtime( true );
            $result = $this->make_request( 'GET', $endpoint );
            $end_time = microtime( true );
            
            $results[ $endpoint ] = array(
                'description' => $description,
                'success' => ! is_wp_error( $result ),
                'response_time' => round( ( $end_time - $start_time ) * 1000, 2 ), // у мілісекундах
                'error' => is_wp_error( $result ) ? $result->get_error_message() : null,
                'status' => is_wp_error( $result ) ? 'error' : 'ok'
            );
        }
        
        return $results;
    }
    
    /**
     * Отримати інформацію про версію API
     */
    public function get_api_version_info() {
        $info = array(
            'api_base' => $this->api_base,
            'test_mode' => $this->test_mode,
            'user_agent' => 'RozetkaPay-Payparts/2.0.3 WordPress/' . get_bloginfo( 'version' ),
            'php_version' => PHP_VERSION,
            'wordpress_version' => get_bloginfo( 'version' ),
            'woocommerce_version' => defined( 'WC_VERSION' ) ? WC_VERSION : 'Not detected'
        );
        
        return $info;
    }
    
    /**
     * Очищення кешу API
     */
    public function clear_api_cache() {
        delete_transient( 'rp_payparts_banks' );
        delete_transient( 'rp_payparts_api_limits' );
        
        $this->logger->debug( 
            'PayParts API: Cache cleared', 
            [ 'source' => 'rp-payparts-api' ] 
        );
        
        return true;
    }
    
    /**
     * Отримати кешовані дані
     */
    public function get_cached_data( $key ) {
        return get_transient( 'rp_payparts_' . $key );
    }
    
    /**
     * Зберегти дані в кеш
     */
    public function set_cached_data( $key, $data, $expiration = HOUR_IN_SECONDS ) {
        return set_transient( 'rp_payparts_' . $key, $data, $expiration );
    }
    
    /**
     * Валідація налаштувань API
     */
    public function validate_api_settings() {
        $errors = array();
        
        if ( empty( $this->api_user ) ) {
            $errors[] = 'API User is required';
        }
        
        if ( empty( $this->api_pass ) ) {
            $errors[] = 'API Password is required';
        }
        
        if ( strlen( $this->api_pass ) < 8 ) {
            $errors[] = 'API Password seems too short';
        }
        
        // Перевірка формату API користувача
        if ( ! empty( $this->api_user ) && ! preg_match( '/^[a-zA-Z0-9_\-\.]+$/', $this->api_user ) ) {
            $errors[] = 'API User contains invalid characters';
        }
        
        return empty( $errors ) ? true : $errors;
    }
    
    /**
     * Отримати детальну інформацію про помилку API
     */
    public function get_last_api_error() {
        // Цей метод можна розширити для зберігання останньої помилки
        return get_transient( 'rp_payparts_last_api_error' );
    }
    
    /**
     * Зберегти інформацію про помилку API
     */
    private function store_api_error( $error_info ) {
        set_transient( 'rp_payparts_last_api_error', $error_info, 300 ); // 5 хвилин
    }
    
    /**
     * Розширене логування для відладки
     */
    private function debug_log( $message, $data = null ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            $log_entry = '[RozetkaPay PayParts API] ' . $message;
            if ( $data ) {
                $log_entry .= ' Data: ' . print_r( $data, true );
            }
            error_log( $log_entry );
        }
        
        // Також логуємо через WooCommerce logger
        if ( $this->logger ) {
            $this->logger->debug( $message, [ 'source' => 'rp-payparts-api', 'data' => $data ] );
        }
    }
}
