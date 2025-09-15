<?php
/**
 * One-click buy button template for product view page on front side.
 *
 * @package RozetkaPay Gateway
 *
 * @var string $view_mode
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

global $product;

if ( 'white' === $view_mode ) {
	$css_class = 'white';
	$img_color = 'black';
} else {
	$css_class = '';
	$img_color = 'white';
}

?>
<?php wp_nonce_field( 'rozetkapay_one_click_action', 'rozetkapay_one_click_nonce' ); ?>
<input type="hidden" name="<?php echo esc_html( RozetkaPay_Const::ID_BUY_ONE_CLICK ); ?>" value="" />
<div class="btn-rozetka-wrapper">
	<button
		class="button alt wp-element-button btn-rozetka <?php echo esc_html( $css_class ); ?> view-variant_2"
		onclick="return rozetkapay_one_click(this);"
	>
		<span><?php esc_html_e( 'Купити з', 'buy-rozetkapay-woocommerce' ); ?></span>
		<img
			src="<?php echo esc_url( ROZETKAPAY_GATEWAY_PLUGIN_URL ); ?>assets/img/rozetka_ec_logo_variant_2_<?php echo esc_html( $img_color ); ?>.svg"
			class="img-responsive"
			alt="<?php esc_html_e( 'Buy via RozetkaPay', 'buy-rozetkapay-woocommerce' ); ?>"
		>
	</button>
</div>
<script type="text/javascript">
	function rozetkapay_one_click(_this) {
		let product_id = <?php echo esc_js( $product->get_id() ); ?>;
		let roc = jQuery('input[name=<?php echo esc_js( RozetkaPay_Const::ID_BUY_ONE_CLICK ); ?>]');

		roc.prop({value: product_id});
		jQuery(_this).closest('form').submit();
		roc.prop({value: ''});

		return false;
	}
</script>
