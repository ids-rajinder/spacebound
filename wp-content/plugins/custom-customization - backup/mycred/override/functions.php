<?php

if(!function_exists('mycred_part_woo_ajax_handler')){
    function mycred_part_woo_ajax_handler() {
        global $mycred_partial_payment;
    
        if ( is_page( (int) get_option( 'woocommerce_checkout_page_id' ) ) 
            && isset( $_POST['action'] ) 
            && isset( $_POST['token'] ) 
            && $_POST['action'] === 'mycred-new-partial-payment' 
            && wp_verify_nonce( $_POST['token'], 'mycred-partial-payment-new' )
        ) {
            //check if any coupon is applied before so then return error only if max is less then 100
            if ($mycred_partial_payment['max'] < 100 && count(WC()->cart->get_coupons()) >= 1) {
                # code...
                wp_send_json_error( __( 'Please remove previous coupon to apply new discount.', 'mycredpartwoo' ) );
            }
    
        $settings      = mycred_part_woo_settings();
        $user_id       = get_current_user_id();
        $mycred        = mycred( $settings['point_type'] );
    
        // Excluded from usage
        if ( $mycred->exclude_user( $user_id ) ) wp_send_json_error( __( 'You are not allowed to use this feature.', 'mycredpartwoo' ) );
    
        $balance       = $mycred->get_users_balance( $user_id );
        $amount        = $mycred->number( abs( $_POST['amount'] ) );
    
        // Invalid amount
        if ( $amount == $mycred->zero() ) wp_send_json_error( __( 'Amount can not be zero.', 'mycredpartwoo' ) );
    
        // Too high amount
        if ( $balance < $amount ) wp_send_json_error( __( 'Insufficient Funds.', 'mycredpartwoo' ) );
    
        $total         = mycred_part_woo_get_total();
    
        $value         = number_format( ( $settings['exchange'] * $amount ), 2, '.', '' );
    
        if ( $value > ( ( $total/ 100 ) * $mycred_partial_payment['max'] ) )
            wp_send_json_error( __( 'The amount can not be greater than the maximum amount.', 'mycredpartwoo' ) );
    
        // Create a Woo Coupon
        $coupon_code   = $user_id . time();
        $new_coupon_id = wp_insert_post( array(
            'post_title'   => $coupon_code,
            'post_content' => '',
            'post_status'  => 'publish',
            'post_author'  => 1,
            'post_type'    => 'shop_coupon'
        ) );
    
        if ( $new_coupon_id === NULL || is_wp_error( $new_coupon_id ) )
            wp_send_json_error( __( 'Failed to complete transaction. Error 1. Please contact support.', 'mycredpartwoo' ) );
    
        // Update Coupon details
        update_post_meta( $new_coupon_id, 'discount_type', 'fixed_cart' );
        update_post_meta( $new_coupon_id, 'coupon_amount', $value );
        update_post_meta( $new_coupon_id, 'individual_use', 'no' );
        update_post_meta( $new_coupon_id, 'product_ids', '' );
        update_post_meta( $new_coupon_id, 'exclude_product_ids', '' );
    
        // Make sure you set usage_limit to 1 to prevent duplicate usage!!!
        update_post_meta( $new_coupon_id, 'usage_limit', 1 );
        update_post_meta( $new_coupon_id, 'usage_limit_per_user', 1 );
        update_post_meta( $new_coupon_id, 'limit_usage_to_x_items', '' );
        update_post_meta( $new_coupon_id, 'usage_count', '' );
        update_post_meta( $new_coupon_id, 'expiry_date', '' );
        update_post_meta( $new_coupon_id, 'apply_before_tax', ( ( $settings['before_tax'] == 'no' ) ? 'yes' : 'no' ) ); // setting
        update_post_meta( $new_coupon_id, 'free_shipping', ( ( $settings['free_shipping'] == 'no' ) ? 'no' : 'yes' ) ); // setting
        update_post_meta( $new_coupon_id, 'product_categories', array() );
        update_post_meta( $new_coupon_id, 'exclude_product_categories', array() );
        update_post_meta( $new_coupon_id, 'exclude_sale_items', ( ( $settings['sale_items'] == 'no' ) ? 'yes' : 'no' ) ); // setting
        update_post_meta( $new_coupon_id, 'minimum_amount', '' );
        update_post_meta( $new_coupon_id, 'customer_email', array() );
        update_post_meta( $new_coupon_id, '__ids_custom_one_time_coupon', 1 );
    
        $applied = WC()->cart->add_discount( $coupon_code );
    
        if ( $applied === true ) {
            
            if($settings['log'] == '') 
            $settings['log'] = 'Partial Payment';
            
            
            
            // Deduct amount only if coupon was successfully applied
            $mycred->add_creds(
                'partial_payment',
                $user_id,
                0 - $amount,
                $settings['log'],
                $new_coupon_id,
                '',
                $settings['point_type']
            );
    
            wc_clear_notices();
            wc_add_notice( __( 'Payment Successfully Applied.', 'mycredpartwoo' ) );
    
            wp_send_json_success();
    
        }
    
        // Delete the coupon
        wp_trash_post( $new_coupon_id );
    
        wp_send_json_error( __( 'Failed to complete transaction. Error 2. Please contact support.', 'mycredpartwoo' ) );
    
        }
    
    }
}



#
#
# Overriding because we have to change currency for all point based products
if(!function_exists('woocommerce_mini_cart')){
    function woocommerce_mini_cart( $args = array() ) {

        $defaults = array(
            'list_class' => '',
        );

        $args = wp_parse_args( $args, $defaults );

        $isPointBasedCart = myCredCheckoutMofication()->isPointBasedCart();


        if( $isPointBasedCart ){
            add_filter( 'woocommerce_currency_symbol', [ handPointBasedItemCheckout(), 'changeCurrencySymbolToPoints'], 10, 2);
            add_filter( 'woocommerce_price_format', [handPointBasedItemCheckout(), 'changeCurrencySymbolPositionToRight'], 10, 2 );
            add_filter( 'formatted_woocommerce_price', [handPointBasedItemCheckout(), 'changingPointBaseditemPriceToIntforMiniCart'], 10, 5 );
        }
        wc_get_template( 'cart/mini-cart.php', $args );
        if( myCredCheckoutMofication()->isPointBasedCart()){
            remove_filter( 'woocommerce_currency_symbol', [ handPointBasedItemCheckout(), 'changeCurrencySymbolToPoints'], 10, 2);
            remove_filter( 'woocommerce_price_format', [handPointBasedItemCheckout(),'changeCurrencySymbolPositionToRight'], 10, 2 );
            add_filter( 'formatted_woocommerce_price', [handPointBasedItemCheckout(), 'changingPointBaseditemPriceToIntforMiniCart'], 10, 5 );
        }

    }
}

if(!function_exists('mycred_part_woo_settings')){
    function mycred_part_woo_settings() {
        global $mycred_partial_payment;
        if ( ! is_array( $mycred_partial_payment ) ) {
            $default = array(
                'position'      => 'after',
                'multiple'      => 'yes',
                'title'         => 'Partial Payment',
                'desc'          => 'Pay parts of your order using your points.',
                'button'        => 'Apply Discount',
                'min'           => 0,
                'max'           => 100,
                'step'          => 100,
                'default'       => 0,
                'point_type'    => MYCRED_DEFAULT_TYPE_KEY,
                'exchange'      => 1,
                'log'           => '',
                'log_refund'    => 'Partial payment',
                'refund_message' => 'Your partial payment of %cred_f% was refunded to your account.',
                'undo'          => 'yes',
                'rewards'       => 1,
                'before_tax'    => 'yes',
                'free_shipping' => 'no',
                'sale_items'    => 'no',
                'selecttype'    => 'input',
                'checkout_total' => 'no',
                'checkout_total_label' => 'Point Cost',
                'checkout_balance' => 'no',
                'checkout_balance_label' => 'Your Balance'
            );
            $saved   = get_option( 'mycred_partial_payments_woo', $default );
            $value = shortcode_atts( $default, $saved );
            return apply_filters( 'bb_mycred_payment_modification', $value );
        }
        return apply_filters( 'bb_mycred_payment_modification', $mycred_partial_payment );
    }
}