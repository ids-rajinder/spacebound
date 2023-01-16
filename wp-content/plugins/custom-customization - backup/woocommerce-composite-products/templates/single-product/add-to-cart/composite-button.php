<?php
/**
 * Composite add-to-cart button template
 *
 * Override this template by copying it to 'yourtheme/woocommerce/single-product/add-to-cart/composite-button.php'.
 *
 * On occasion, this template file may need to be updated and you (the theme developer) will need to copy the new files to your theme to maintain compatibility.
 * We try to do this as little as possible, but it does happen.
 * When this occurs the version of the template file will be bumped and the readme will list any important changes.
 *
 * @version 8.2.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $product;

#To get base price of composite
$basePrice = $product->get_composite_price_data()['base_price'];

?>
<button type="submit" data-attr-price="<?php echo $basePrice; ?>" name="add-to-cart" value="<?php echo esc_attr( $product->get_id() ); ?>" class="single_add_to_cart_button composite_add_to_cart_button button alt"><?php echo esc_html( $product->single_add_to_cart_text() ); ?><?php echo is_product() ? '<span class="bb-price"><span class="price">'.wc_price($basePrice).'</span>' : ''?></button>
