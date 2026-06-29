=== Buy with RozetkaPay Gateway for WooCommerce ===
Contributors: rozetkapay
Tags: woocommerce, payment gateway, rozetkapay, checkout, ecommerce, one-click
Requires at least: 6.2
Tested up to: 6.8
Requires PHP: 7.3
WC requires at least: 8.0
WC tested up to: 9.8
Stable tag: 1.0.9
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html

== Description ==

**Buy with RozetkaPay Gateway for WooCommerce** is a payment gateway plugin that allows your WooCommerce store to accept payments via RozetkaPay. It supports both standard checkout payments and a fast "Pay one-click" button for a seamless customer experience.

**Key Features:**
* Accept payments via RozetkaPay on WooCommerce checkout page
* "Pay one-click" button on product and cart pages
* Refund support directly from WooCommerce admin
* Handles RozetkaPay payment and refund callbacks
* Order meta box with RozetkaPay payment details
* Payment logs (requests, callbacks, errors) with admin interface
* Ready for translation (localization support)
* Custom branded styles and RozetkaPay logo

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/buy-rozetkapay-woocommerce` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Make sure WooCommerce is active and your site meets the minimum requirements (PHP 7.3+, WooCommerce 8.0+).

== Configuration ==

1. Go to `WooCommerce > Settings > Payments`.
2. Find **RozetkaPay** in the list and click **Manage**.
3. Configure the following options:
    * **Enable/Disable**: Activate or deactivate RozetkaPay as a payment method.
    * **API Login**: Enter your RozetkaPay API login.
    * **API Password**: Enter your RozetkaPay API password.
    * **Enable/Disable checkout payment gateway**: Show/hide RozetkaPay on the checkout page.
    * **Enable/Disable "Pay one-click" button**: Show/hide the fast payment button on product/cart pages.
    * **"Pay one-click" button style**: Choose black or white button style.

== Usage ==

* Customers can select RozetkaPay at checkout and complete payment via the RozetkaPay interface.
* If enabled, the "Pay one-click" button appears on product and cart pages for instant payment.
* Refunds can be processed from the WooCommerce admin panel and will be sent to RozetkaPay automatically.

== Admin Features ==

* **Order Meta Box**: View RozetkaPay payment details, receipts, and resend callbacks on the order edit page.
* **Logs**: Access RozetkaPay logs (`WooCommerce > RozetkaPay Logs`) for requests, callbacks, and errors. Logs can be cleared from the admin panel.
* **Payment Info Page**: Detailed RozetkaPay payment information for each order.

== Logs ==

* Logs are stored in the `logs/` directory inside the plugin folder.
* Types: `requests`, `callbacks`, `errors` (view and clear via admin panel).

== Localization ==

* Translation-ready. `.pot` file included in the `languages/` directory.
* Use the provided template to create your own translations.

== Assets ==

* Custom styles and images are located in the `assets/` directory.
* Includes branded button and RozetkaPay logo.

== Frequently Asked Questions ==

= Does this plugin support refunds? =
Yes, you can process refunds from the WooCommerce admin and RozetkaPay will be notified automatically.

= Is the plugin translation-ready? =
Yes, all strings are translatable and a `.pot` file is included.

= Where are the payment logs stored? =
Logs are stored in the `logs/` directory inside the plugin folder and can be viewed/cleared from the admin panel.

= What are the minimum requirements? =
* WordPress 6.2+
* WooCommerce 8.0+
* PHP 7.3+

== Screenshots ==

1. RozetkaPay payment option on WooCommerce checkout
2. "Pay one-click" button on product page
3. RozetkaPay order meta box in admin
4. RozetkaPay logs page in admin

== Changelog ==

= 1.0.4 =
* Initial public release

== Upgrade Notice ==

= 1.0.4 =
Initial public release with full WooCommerce and RozetkaPay integration.

== Support ==

For support, contact [RozetkaPay](https://rozetkapay.com/) or open an issue in the repository.
