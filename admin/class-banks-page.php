<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Admin page: RozetkaPay — Доступні банки (PayParts)
 */
class RP_Payparts_Banks_Page {

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'add_menu' ] );
    }

    public static function add_menu() {
        add_submenu_page(
            'woocommerce',
            __('RozetkaPay: Доступні банки', 'rp-payparts'),
            __('RozetkaPay: Доступні банки', 'rp-payparts'),
            'manage_woocommerce',
            'rp-payparts-banks',
            [ __CLASS__, 'render_page' ]
        );
    }

    protected static function get_settings() {
        $settings = get_option('woocommerce_rp_payparts_settings', []);
        return is_array($settings) ? $settings : [];
    }

    protected static function fetch_banks($login, $password, $include_fees = null) {
        $url = 'https://api.rozetkapay.com/api/payparts/v1/banks/info';
        if ( ! empty($include_fees) ) {
            $url .= ( strpos($url,'?')===false ? '?' : '&' ) . 'include_fees=' . urlencode($include_fees);
        }

        $auth = base64_encode( $login . ':' . $password );

        if ( function_exists('wp_remote_get') ) {
            $resp = wp_remote_get( $url, [
                'headers' => [
                    'Authorization' => 'Basic ' . $auth,
                    'Accept'        => 'application/json',
                    'Content-Type'  => 'application/json',
                ],
                'timeout' => 30,
            ] );
            if ( is_wp_error($resp) ) {
                return [ 'error' => $resp->get_error_message() ];
            }
            $code = wp_remote_retrieve_response_code($resp);
            $body = wp_remote_retrieve_body($resp);
        } else {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_HTTPHEADER     => [
                    'Authorization: Basic ' . $auth,
                    'Accept: application/json',
                    'Content-Type: application/json',
                ],
            ]);
            $body = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err  = curl_error($ch);
            curl_close($ch);
            if ($err) { return [ 'error' => $err ]; }
        }

        if ( (int)$code !== 200 ) {
            return [ 'error' => 'HTTP ' . $code, 'body' => $body ];
        }

        $data = json_decode($body, true);
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return [ 'error' => 'Invalid JSON', 'body' => $body ];
        }
        return $data;
    }

    protected static function filter_banks($banks, $amount = null, $term = null) {
        if ( ! is_array($banks) ) return [];
        $out = [];
        foreach ($banks as $b) {
            if ( ! isset($b['name']) ) continue;
            $ok = true;
            if ( $amount !== null ) {
                $min = isset($b['limits']['min_amount']) ? (float)$b['limits']['min_amount'] : 0;
                $max = isset($b['limits']['max_amount']) ? (float)$b['limits']['max_amount'] : PHP_FLOAT_MAX;
                if ( $amount < $min || $amount > $max ) $ok = false;
            }
            if ( $ok && $term !== null ) {
                $periods = array_map('intval', $b['available_periods'] ?? []);
                if ( ! in_array( (int)$term, $periods, true ) ) $ok = false;
            }
            if ( $ok ) $out[] = $b;
        }
        return $out;
    }

    public static function render_page() {
        if ( ! current_user_can('manage_woocommerce') ) { wp_die(__('Access denied', 'rp-payparts')); }

        $settings = self::get_settings();
        $login    = $settings['api_user'] ?? '';
        $password = $settings['api_pass'] ?? '';

        $amount = isset($_GET['amount']) ? floatval($_GET['amount']) : null;
        $term   = isset($_GET['term']) ? intval($_GET['term']) : null;
        $include_fees = isset($_GET['include_fees']) ? sanitize_text_field($_GET['include_fees']) : null;

        $banks = [];
        $error = null;

        if ( ! empty($login) && ! empty($password) ) {
            $resp = self::fetch_banks($login, $password, $include_fees);
            if ( isset($resp['error']) ) {
                $error = $resp;
            } else {
                $banks = self::filter_banks($resp, $amount, $term);
            }
        } else {
            $error = ['error' => __('Вкажіть логін і пароль API в налаштуваннях способу оплати PayParts.', 'rp-payparts')];
        }

        ?>
        <div class="wrap">
            <h1>RozetkaPay — Доступні банки (PayParts)</h1>
            <p class="description">Ендпоінт: <code>/api/payparts/v1/banks/info</code> • Авторизація: <strong>BasicAuth</strong> (login:password)</p>

            <form method="get" style="margin:16px 0;">
                <input type="hidden" name="page" value="rp-payparts-banks"/>
                <label>
                    Сума (UAH):
                    <input type="number" step="0.01" name="amount" value="<?php echo esc_attr($amount); ?>" style="width:140px;">
                </label>
                &nbsp;&nbsp;
                <label>
                    Термін (місяців):
                    <input type="number" name="term" value="<?php echo esc_attr($term); ?>" style="width:100px;">
                </label>
                &nbsp;&nbsp;
                <label>
                    include_fees:
                    <input type="text" name="include_fees" value="<?php echo esc_attr($include_fees); ?>" style="width:140px;">
                </label>
                &nbsp;&nbsp;
                <button class="button button-primary">Оновити</button>
                &nbsp;
                <a class="button" href="<?php echo esc_url( admin_url('admin.php?page=rp-payparts-banks') ); ?>">Скинути</a>
            </form>

            <?php if ($error): ?>
                <div class="notice notice-error"><p><strong>Помилка:</strong> <?php echo esc_html( is_array($error)? ($error['error'] ?? 'Error') : $error ); ?></p>
                <?php if (is_array($error) && isset($error['body'])): ?>
                    <details><summary>Відповідь сервера</summary><pre><?php echo esc_html($error['body']); ?></pre></details>
                <?php endif; ?>
                </div>
            <?php endif; ?>

            <table class="widefat striped" style="margin-top:12px;">
                <thead>
                    <tr>
                        <th style="width:40px;">#</th>
                        <th>Банк</th>
                        <th>Продукт</th>
                        <th>Ліміти (грн)</th>
                        <th>Доступні періоди</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($banks)): ?>
                    <tr><td colspan="5">Немає даних або не знайдено під задані фільтри.</td></tr>
                <?php else: foreach ($banks as $i => $b): ?>
                    <tr>
                        <td><?php echo (int)$i+1; ?></td>
                        <td>
                            <strong><?php echo esc_html($b['name'] ?? ''); ?></strong>
                            <?php if (!empty($b['logo_url'])): ?>
                                <div><a href="<?php echo esc_url($b['logo_url']); ?>" target="_blank" rel="noreferrer">logo</a></div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($b['product_name'] ?? ''); ?></td>
                        <td>
                            від <strong><?php echo (int)($b['limits']['min_amount'] ?? 0); ?></strong><br/>
                            до <strong><?php echo (int)($b['limits']['max_amount'] ?? 0); ?></strong>
                        </td>
                        <td>
                            <?php foreach ((array)($b['available_periods'] ?? []) as $p): ?>
                                <span style="display:inline-block;padding:2px 6px;border:1px solid #ddd;border-radius:10px;margin:2px;"><?php echo (int)$p; ?> міс.</span>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}

RP_Payparts_Banks_Page::init();
