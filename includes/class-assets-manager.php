<?php
/**
 * Менеджер ресурсів (Assets Manager) для RozetkaPay Payparts
 * Керування CSS та JS файлами плагіна
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RP_Payparts_Assets_Manager {

    /**
     * Конструктор
     */
    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_styles' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ) );
        
        // Створення відсутніх файлів ресурсів під час init
        add_action( 'init', array( $this, 'ensure_assets_exist' ) );
    }

    /**
     * Підключення стилів для фронтенду
     */
    public function enqueue_frontend_styles() {
        // Підключаємо тільки на потрібних сторінках
        if ( ! $this->should_load_frontend_assets() ) {
            return;
        }

        // Основні стилі
        wp_enqueue_style( 
            'rozetkapay-payparts-frontend', 
            RP_PAYPARTS_PLUGIN_URL . 'assets/css/frontend.css', 
            array(), 
            $this->get_asset_version( 'assets/css/frontend.css' )
        );

        // Додаткові стилі для мобільної версії
        wp_enqueue_style( 
            'rozetkapay-payparts-mobile', 
            RP_PAYPARTS_PLUGIN_URL . 'assets/css/mobile.css', 
            array( 'rozetkapay-payparts-frontend' ), 
            $this->get_asset_version( 'assets/css/mobile.css' ),
            'screen and (max-width: 768px)'
        );

        // Підключаємо JavaScript для checkout
        if ( is_checkout() ) {
            wp_enqueue_script( 
                'rozetkapay-payparts-checkout', 
                RP_PAYPARTS_PLUGIN_URL . 'assets/js/frontend.js', 
                array( 'jquery' ), 
                $this->get_asset_version( 'assets/js/frontend.js' ), 
                true 
            );

            // Локалізація для JS
            wp_localize_script( 'rozetkapay-payparts-checkout', 'rpPayparts', array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'rp_payparts_nonce' ),
                'assetsUrl' => RP_PAYPARTS_PLUGIN_URL . 'assets/',
                'enableAdvanced' => false, // Увімкнути розширені функції
                'strings' => array(
                    'selectOption' => __( 'Будь ласка, оберіть варіант оплати', 'rp-payparts' ),
                    'calculating' => __( 'Розраховуємо...', 'rp-payparts' ),
                    'error' => __( 'Виникла помилка', 'rp-payparts' ),
                ),
            ) );
        }
    }

    /**
     * Підключення стилів для адмінки
     */
    public function enqueue_admin_styles( $hook ) {
        // Підключаємо тільки на сторінках налаштувань WooCommerce
        if ( ! $this->should_load_admin_assets( $hook ) ) {
            return;
        }

        wp_enqueue_style( 
            'rozetkapay-payparts-admin', 
            RP_PAYPARTS_PLUGIN_URL . 'assets/css/admin.css', 
            array(), 
            $this->get_asset_version( 'assets/css/admin.css' )
        );

        wp_enqueue_script( 
            'rozetkapay-payparts-admin', 
            RP_PAYPARTS_PLUGIN_URL . 'assets/js/admin.js', 
            array( 'jquery' ), 
            $this->get_asset_version( 'assets/js/admin.js' ), 
            true 
        );
        
        // Локалізація для адмін JS
        wp_localize_script( 'rozetkapay-payparts-admin', 'rpPaypartsAdmin', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'rp_admin_nonce' ),
            'strings' => array(
                'confirmDelete' => __( 'Ви впевнені?', 'rp-payparts' ),
                'saved' => __( 'Збережено', 'rp-payparts' ),
                'error' => __( 'Помилка', 'rp-payparts' ),
            ),
        ) );
    }

    /**
     * Перевірка чи потрібно завантажувати фронтенд ресурси
     */
    private function should_load_frontend_assets() {
        // Завантажуємо тільки на специфічних сторінках
        if ( is_cart() || is_checkout() ) {
            return true;
        }
        
        // Перевіряємо ендпоінти WooCommerce
        if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-received' ) ) {
            return true;
        }

        // Перевіряємо шорткоди WooCommerce
        if ( is_singular() ) {
            global $post;
            if ( $post && ( 
                has_shortcode( $post->post_content, 'woocommerce_checkout' ) ||
                has_shortcode( $post->post_content, 'woocommerce_cart' ) ||
                has_shortcode( $post->post_content, 'rp_payparts_widget' )
            ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Перевірка чи потрібно завантажувати адмін ресурси
     */
    private function should_load_admin_assets( $hook ) {
        // Сторінки налаштувань WooCommerce
        $wc_admin_pages = array(
            'woocommerce_page_wc-settings',
            'woocommerce_page_wc-status',
        );

        if ( in_array( $hook, $wc_admin_pages, true ) ) {
            return true;
        }

        // Перевіряємо параметри сторінки
        $page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
        $tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : '';
        $section = isset( $_GET['section'] ) ? sanitize_text_field( wp_unslash( $_GET['section'] ) ) : '';

        // Сторінка налаштувань нашого плагіна
        if ( 'wc-settings' === $page && 'checkout' === $tab && ( empty( $section ) || 'rp_payparts' === $section ) ) {
            return true;
        }

        return false;
    }

    /**
     * Отримати версію файлу для cache busting
     */
    private function get_asset_version( $asset_path ) {
        $full_path = RP_PAYPARTS_PLUGIN_PATH . $asset_path;
        
        if ( file_exists( $full_path ) ) {
            return filemtime( $full_path );
        }
        
        return RP_PAYPARTS_VERSION;
    }

    /**
     * Перевірити наявність всіх необхідних assets
     */
    public function ensure_assets_exist() {
        // Виконуємо тільки для адміністраторів
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        $this->create_directories();
        $this->create_default_assets();
    }

    /**
     * Створення необхідних директорій
     */
    private function create_directories() {
        $directories = array(
            RP_PAYPARTS_PLUGIN_PATH . 'assets/',
            RP_PAYPARTS_PLUGIN_PATH . 'assets/css/',
            RP_PAYPARTS_PLUGIN_PATH . 'assets/js/',
            RP_PAYPARTS_PLUGIN_PATH . 'assets/images/',
        );

        foreach ( $directories as $dir ) {
            if ( ! file_exists( $dir ) ) {
                wp_mkdir_p( $dir );
            }
        }
    }

    /**
     * Створення базових asset-файлів, якщо вони відсутні
     */
    private function create_default_assets() {
        $default_files = array(
            'assets/css/frontend.css' => $this->get_default_frontend_css(),
            'assets/css/mobile.css' => $this->get_default_mobile_css(),
            'assets/css/admin.css' => $this->get_default_admin_css(),
            'assets/css/slider.css' => $this->get_default_slider_css(),
            'assets/js/frontend.js' => $this->get_default_frontend_js(),
            'assets/js/admin.js' => $this->get_default_admin_js(),
            'assets/js/slider.js' => $this->get_default_slider_js(),
        );

        foreach ( $default_files as $file_path => $content ) {
            $full_path = RP_PAYPARTS_PLUGIN_PATH . $file_path;
            
            if ( ! file_exists( $full_path ) ) {
                $result = file_put_contents( $full_path, $content );
                
                if ( false === $result ) {
                    error_log( "RozetkaPay Payparts: Не вдалося створити файл ресурсу: {$full_path}" );
                }
            }
        }
    }

    /**
     * Базовий контент для frontend.css
     */
    private function get_default_frontend_css() {
        return '/* RozetkaPay Payparts Frontend Styles - Generated */
.rp-payparts-container {
    border: 1px solid #e1e5e9;
    border-radius: 8px;
    padding: 20px;
    margin: 15px 0;
    background: #f8f9fa;
}

.rp-payparts-option {
    display: flex;
    align-items: center;
    padding: 15px;
    border: 2px solid #e9ecef;
    border-radius: 6px;
    margin-bottom: 10px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.rp-payparts-option:hover {
    border-color: #007cba;
}

.rp-payparts-option input[type="radio"]:checked + .rp-option-content {
    color: #007cba;
}

.woocommerce-info.rp-payparts-cart-message {
    background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%) !important;
    border-left: 4px solid #0ea5e9 !important;
}
';
    }

    /**
     * Базовий контент для mobile.css
     */
    private function get_default_mobile_css() {
        return '/* RozetkaPay Payparts Mobile Styles - Generated */
@media (max-width: 768px) {
    .rp-payparts-container {
        padding: 15px;
        margin: 10px 0;
    }
    
    .rp-payparts-option {
        padding: 12px;
    }
}
';
    }

    /**
     * Базовий контент для admin.css
     */
    private function get_default_admin_css() {
        return '/* RozetkaPay Payparts Admin Styles - Generated */
.rp-admin-info-box {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    padding: 15px;
    margin: 15px 0;
}
';
    }

    /**
     * Базовий контент для slider.css
     */
    private function get_default_slider_css() {
        return '/* RozetkaPay Payparts Slider Styles - Generated */
.rp-payparts-slider-container {
    background: #fff;
    border: 1px solid #e1e5e9;
    border-radius: 8px;
    padding: 20px;
    margin: 15px 0;
}

.rp-payparts-slider {
    width: 100%;
    height: 6px;
    border-radius: 3px;
    background: #e9ecef;
    outline: none;
    -webkit-appearance: none;
}
';
    }

    /**
     * Базовий контент для frontend.js
     */
    private function get_default_frontend_js() {
        return '/* RozetkaPay Payparts Frontend JavaScript - Generated */
(function($) {
    "use strict";
    
    $(document).ready(function() {
        // Обробка базових опцій оплати
        $(document).on("change", "input[name=\"rp_payparts_option\"]", function() {
            $(".rp-payparts-option").removeClass("selected");
            $(this).closest(".rp-payparts-option").addClass("selected");
        });
    });
})(jQuery);
';
    }

    /**
     * Базовий контент для admin.js
     */
    private function get_default_admin_js() {
        return '/* RozetkaPay Payparts Admin JavaScript - Generated */
jQuery(document).ready(function($) {
    "use strict";
    
    // Базовий функціонал адмінки
    console.log("RozetkaPay Payparts Admin loaded");
});
';
    }

    /**
     * Базовий контент для slider.js
     */
    private function get_default_slider_js() {
        return '/* RozetkaPay Payparts Slider JavaScript - Generated */
jQuery(document).ready(function($) {
    "use strict";
    
    // Базовий функціонал слайдера
    console.log("RozetkaPay Payparts Slider loaded");
});
';
    }

    /**
     * Перевірка наявності критично важливих файлів
     */
    public function check_critical_assets() {
        $critical_files = array(
            'assets/css/frontend.css',
            'assets/js/frontend.js',
        );

        $missing_files = array();
        
        foreach ( $critical_files as $file ) {
            if ( ! file_exists( RP_PAYPARTS_PLUGIN_PATH . $file ) ) {
                $missing_files[] = $file;
            }
        }

        if ( ! empty( $missing_files ) && current_user_can( 'manage_options' ) ) {
            add_action( 'admin_notices', function() use ( $missing_files ) {
                echo '<div class="notice notice-warning is-dismissible">';
                echo '<p><strong>RozetkaPay Payparts:</strong> ';
                printf( 
                    esc_html__( 'Відсутні критичні файли: %s', 'rp-payparts' ), 
                    esc_html( implode( ', ', $missing_files ) )
                );
                echo '</p></div>';
            } );
        }

        return empty( $missing_files );
    }
}
