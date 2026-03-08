<?php
/**
 * Main payment gateway class for RozetkaPay integration.
 *
 * @package RozetkaPay Gateway
 */

if (!defined("ABSPATH")) {
    exit(); // Exit if accessed directly.
}

/**
 * RozetkaPay Gateway Class.
 */
class RozetkaPay_Gateway extends WC_Payment_Gateway
{
    /**
     * Whether checkout gateway is enabled.
     *
     * @var bool
     */
    public bool $cart_checkout_gateway_enabled = true;

    /**
     * Whether "one click button" is enabled.
     *
     * @var bool
     */
    public bool $one_click_button_enabled = true;

    /**
     * "One click button" view mode.
     *
     * @var string|null
     */
    public ?string $one_click_button_view_mode = null;

    /**
     * Constructor for the gateway.
     */
    public function __construct()
    {
        $this->id = RozetkaPay_Const::ID_PAYMENT_GATEWAY;
        $this->method_title = __(
            "RozetkaPay",
            "buy-with-rozetkapay-gateway-for-woocommerce",
        );
        $this->method_description = __(
            "Accept payments via Buy with RozetkaPay",
            "buy-with-rozetkapay-gateway-for-woocommerce",
        );
        $this->icon =
            ROZETKAPAY_GATEWAY_PLUGIN_URL . "assets/img/rozetkapay-logo.svg"; // Path to icon.
        $this->has_fields = false;

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables.
        $this->enabled = $this->get_option("enabled");
        $this->cart_checkout_gateway_enabled =
            $this->get_option("cart_checkout_gateway_enabled", "yes") === "yes";
        $this->title = __(
            "RozetkaPay",
            "buy-with-rozetkapay-gateway-for-woocommerce",
        );
        $this->description = __(
            "Pay via Buy with RozetkaPay",
            "buy-with-rozetkapay-gateway-for-woocommerce",
        );
        $this->one_click_button_enabled =
            $this->get_option("one_click_button_enabled", "yes") === "yes";
        $this->one_click_button_view_mode = $this->get_option(
            "one_click_button_view_mode",
            "black",
        );
        $this->login = $this->get_option("login");
        $this->password = $this->get_option("password");

        $this->supports = ["products", "refunds"];

        // Save settings.
        add_action("woocommerce_update_options_payment_gateways_" . $this->id, [
            $this,
            "process_admin_options",
        ]);
    }

    /**
     * Initialize gateway settings form fields.
     */
    public function init_form_fields(): void
    {
        $this->form_fields = [
            "enabled" => [
                "title" => __(
                    "Enable/Disable",
                    "buy-with-rozetkapay-gateway-for-woocommerce",
                ),
                "type" => "checkbox",
                "label" => __(
                    "Enable RozetkaPay Payment",
                    "buy-with-rozetkapay-gateway-for-woocommerce",
                ),
                "default" => "yes",
            ],
            "cart_checkout_gateway_enabled" => [
                "title" => __(
                    "Enable/Disable cart checkout payment gateway",
                    "buy-with-rozetkapay-gateway-for-woocommerce",
                ),
                "type" => "checkbox",
                "label" => __(
                    "Enable/Disable cart checkout payment gateway",
                    "buy-with-rozetkapay-gateway-for-woocommerce",
                ),
                "default" => "",
            ],
            "one_click_button_enabled" => [
                "title" => __(
                    "Enable/Disable `Pay one-click` button",
                    "buy-with-rozetkapay-gateway-for-woocommerce",
                ),
                "type" => "checkbox",
                "label" => __(
                    "Enable/Disable `Pay one-click` button",
                    "buy-with-rozetkapay-gateway-for-woocommerce",
                ),
                "default" => "yes",
            ],
            "one_click_button_view_mode" => [
                "title" => __(
                    "`Pay one-click` button view mode",
                    "buy-with-rozetkapay-gateway-for-woocommerce",
                ),
                "type" => "select",
                "description" => __(
                    "Select button view mode",
                    "buy-with-rozetkapay-gateway-for-woocommerce",
                ),
                "default" => "black",
                "options" => [
                    "black" => __(
                        "Black",
                        "buy-with-rozetkapay-gateway-for-woocommerce",
                    ),
                    "white" => __(
                        "White",
                        "buy-with-rozetkapay-gateway-for-woocommerce",
                    ),
                ],
            ],
            "login" => [
                "title" => __(
                    "API Login",
                    "buy-with-rozetkapay-gateway-for-woocommerce",
                ),
                "type" => "text",
                "description" => __(
                    "Enter your RozetkaPay API login",
                    "buy-with-rozetkapay-gateway-for-woocommerce",
                ),
                "default" => "",
            ],
            "password" => [
                "title" => __(
                    "API Password",
                    "buy-with-rozetkapay-gateway-for-woocommerce",
                ),
                "type" => "password",
                "description" => __(
                    "Enter your RozetkaPay API password",
                    "buy-with-rozetkapay-gateway-for-woocommerce",
                ),
                "default" => "",
            ],
        ];
    }

    /**
     * Process the payment and redirect to RozetkaPay Express Checkout.
     *
     * @param int $order_id WooCommerce order ID.
     *
     * @return array
     */
    public function process_payment($order_id): array
    {
        $order = wc_get_order($order_id);

        if (!$order) {
            wc_add_notice(
                __(
                    "Invalid order",
                    "buy-with-rozetkapay-gateway-for-woocommerce",
                ),
                "error",
            );

            return ["result" => "fail"];
        }

        // Prepare payment data.
        $payment_data = [
            "mode" => RozetkaPay_Const::PAYMENT_MODE,
            "amount" => $order->get_total(),
            "currency" => $order->get_currency(),
            "external_id" => (string) $order->get_id(),
            "init_recurrent_payment" => false,
            "confirm" => true,
            /* translators: order number (id) description */
            "description" => sprintf(
                __(
                    "RozetkaPay order #%s",
                    "buy-with-rozetkapay-gateway-for-woocommerce",
                ),
                $order->get_order_number(),
            ),
            "callback_url" => site_url(
                "?wc-api=" .
                    RozetkaPay_Helper::get_class_name(
                        RozetkaPay_Callback::class,
                    ),
            ),
            "result_url" => $order->get_checkout_order_received_url(),
            "customer" => null,
            "products" => $this->build_products_array_from_order($order),
        ];

        // Create payment through API.
        $response = RozetkaPay_API::create_payment(
            $payment_data,
            $this->login,
            $this->password,
        );

        if (is_wp_error($response)) {
            wc_add_notice($response->get_error_message(), "error");

            return ["result" => "fail"];
        }

        // Mark order as pending payment.
        $order->update_status(
            RozetkaPay_Const::ORDER_STATUS_CREATED,
            __(
                "Awaiting RozetkaPay payment",
                "buy-with-rozetkapay-gateway-for-woocommerce",
            ),
        );

        // Redirect to payment page.
        return [
            "result" => "success",
            "redirect" => esc_url_raw($response["action"]["value"]),
        ];
    }

    /**
     * Process refound.
     *
     * @param int|string $order_id Order ID.
     * @param int|float  $amount Amount of refound.
     * @param string     $reason Reason of refound.
     */
    public function process_refund($order_id, $amount = null, $reason = "")
    {
        $order = wc_get_order($order_id);
        $amount = (float) $amount;

        $response = RozetkaPay_API::refund_payment(
            [
                "external_id" => (string) $order->get_id(),
                "amount" => $amount,
                "callback_url" => WC()->api_request_url(
                    RozetkaPay_Helper::get_class_name(
                        RozetkaPay_Callback::class,
                    ),
                ),
                "currency" => $order->get_currency(),
                "payload" => $reason,
            ],
            $this->login,
            $this->password,
        );

        if (
            !is_wp_error($response) &&
            !empty($response["is_success"]) &&
            true === $response["is_success"]
        ) {
            $message = __(
                "Refund was initialized. Waiting for a response from RozetkaPay.",
                "buy-with-rozetkapay-gateway-for-woocommerce",
            );

            $order->add_order_note($message);

            return new WP_Error("refund_pending", $message);
        }

        return new WP_Error(
            "refund_failed",
            __(
                "Refund was failed",
                "buy-with-rozetkapay-gateway-for-woocommerce",
            ),
        );
    }

    /**
     * Check is on available payment gateway.
     */
    public function is_available(): bool
    {
        if ("yes" !== $this->enabled) {
            return false;
        }

        if (!$this->cart_checkout_gateway_enabled) {
            return false;
        }

        if (
            !in_array(
                get_woocommerce_currency(),
                RozetkaPay_Const::PAYMENT_CURRENCIES,
                true,
            )
        ) {
            return false;
        }

        if (WC()->cart && WC()->cart->get_total("edit") <= 0) {
            return false;
        }

        return true;
    }

    /**
     * Build products array with specific data for RozetkaPay payment action.
     *
     * @param WC_Abstract_Order $order Order object.
     *
     * @return array
     */
    private function build_products_array_from_order(
        WC_Abstract_Order $order,
    ): array {
        $products = [];

        foreach ($order->get_items() as $item) {
            $product = $item->get_product();

            $data = [
                "id" => (string) $item->get_id(),
                "name" => $item->get_name(),
                "currency" => $order->get_currency(),
                "quantity" => (string) $item->get_quantity(),
            ];

            if ($product instanceof WC_Product) {
                $description = $product->get_short_description();

                if (empty($description)) {
                    $description = $product->get_description();
                }

                $image = get_the_post_thumbnail_url(
                    $product->get_id(),
                    "thumbnail",
                );

                if (!$image) {
                    $image =
                        ROZETKAPAY_GATEWAY_PLUGIN_URL .
                        RozetkaPay_Const::PRODUCT_DEFAULT_IMAGE_PATH;
                }

                $data["url"] = get_permalink($product->get_id());
                $data["net_amount"] = $product->get_price();
                $data["description"] = !empty($description)
                    ? $description
                    : "-";
                $data["image"] = $image;
            }

            $products[] = $data;
        }

        return $products;
    }
}
