<?php


if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class handPointBasedItemCheckout{

  /**
  * The one, true instance of this object.
  *
  * @static
  * @access private
  * @var null|object
  */
  private static $instance = null;
  private $plugin_path;
  private $plugin_url;

  public function __construct(){
    $this->plugin_path = CUSTOM_BBS_PATH;
    $this->plugin_url  = CUSTOM_BBS_URL;

    #Getting place order button
	  add_filter( 'woocommerce_order_button_html', [$this, 'changeWoocommerceOrderButtonHTML'] );

    #injecting submitted data when custo place order button clicked.
    add_action( 'woocommerce_after_template_part', [$this, 'injectSubmitData'], 10, 4); 

    #changing currency for PointBased Products.
    add_filter( 'woocommerce_currency_symbol', [$this, 'changeCurrencyForPointBasedProduct'], 10, 2);

    #Adding filter 
    add_action( 'woocommerce_order_details_before_order_table_items', [$this, 'addingFilterTochangeCurrency'], 10, 2);

    #Removing 'Required' attribute from billing address for Pointbased cart.
    add_filter( 'woocommerce_billing_fields', [$this, 'disableBillingFOrPointBasedCart'], 10, 1 );

    #To change Currency position to 'Right' for Point Based products.
    add_filter( 'woocommerce_price_format', [$this,'changeCurrencyPositionToRight'], 10, 2 ); 

    #Shwoing 'what will be the point balance of user after placing the order' on checkout page
    add_action( 'woocommerce_review_order_after_order_total', [$this, 'showsPointsBalaceAfterOrderplaced'], 10 ); 
    add_action( 'woocommerce_cart_totals_after_order_total',  [$this, 'showsPointsBalaceAfterOrderplaced'], 10 );
    
    # Shwowing correct price in order table
    add_filter( 'woocommerce_get_formatted_order_total', [$this, 'showPointBasedTotal'], 10, 4 );
    
    #Changing pointbased item's price and total from float to integer.
    add_filter( 'formatted_woocommerce_price', [$this, 'changingPointBaseditemPriceToInt'], 10, 5 ); 

  }

  public function changingPointBaseditemPriceToInt( $numberFormat,$price, $decimals, $decimal_separator, $thousand_separator ){
  
    $productID = get_the_ID();
    if(get_post_meta($productID, 'purchasable_by_points_only', true) === 'yes'){
      return intval($price);
    }
    if( ( is_cart() || is_checkout() ) && myCredCheckoutMofication()->isPointBasedCart()){
      return intval($price);
    }
    
    $orderId = templateOverride()->getOrderIdOnConfirmation();
    if(myCredCheckoutMofication()->isPointBasedOrder( $orderId )){
      return intval($price);
    }
    return $numberFormat;
  }

  public function showPointBasedTotal($formatted_total, $order, $tax_display, $display_refunded ){
    $isPointBasedOrder= myCredCheckoutMofication()->isPointBasedOrder( $order->get_id() ) ;
    if($isPointBasedOrder){
      return '<span class="woocommerce-Price-amount amount">'.intval(($order->get_subtotal()))."\n".bbCustomVariables()->getPointsCurrency().'</span>';
    }
    return $formatted_total;
  }
  
  public function addingFilterTochangeCurrency($order){
    $orderId = templateOverride()->getOrderIdOnConfirmation();
    if( !myCredCheckoutMofication()->isPointBasedOrder( $orderId )){
      return;
    }
    add_filter( 'woocommerce_currency_symbol', [$this, 'changeCurrencySymbolToPoints'], 10, 2);
    add_filter( 'woocommerce_price_format', [$this,'changeCurrencySymbolPositionToRight'], 10, 2 ); 
  }

  public function changingPointBaseditemPriceToIntforMiniCart( $numberFormat,$price, $decimals, $decimal_separator, $thousand_separator ){
    return intval($price);
  }

  public function showsPointsBalaceAfterOrderplaced(){
    ob_start();
    global $mycred_partial_payment;
		$mycred       = mycred( $mycred_partial_payment['point_type'] );
		$show_total         = $mycred_partial_payment['checkout_total'];
    if ( ( $show_total == 'both' ) || ( $show_total == 'cart' && is_cart() ) || ( $show_total == 'checkout' && is_checkout() )){

      $the_cart       = WC()->cart;
      $the_cart_total = $the_cart->total;
      $pointBalance   = ( is_user_logged_in() ) ? $mycred->get_users_balance( get_current_user_id() ) : 0;
      $cost           = $mycred->number( $the_cart_total );

      if ( $mycred_partial_payment['exchange'] != 1 ) {
        $cost = $mycred->number( ( $the_cart_total / $mycred_partial_payment['exchange'] ) );
        $cost = apply_filters( 'mycred_woo_order_cost', $cost, $the_cart, true, $mycred );
      }
    }
    ?>
    <tr class="total ids-custom-balance" <?php echo(!is_user_logged_in()||($pointBalance - $cost) < 0 ) ? 'style="display:none"': "" ;?>>
      <th><b><?php echo "Your Point balance after checkout" ?></b></th>
      <td >
        <div class="current-balance ids-custom-balance" 
        <?php 
          if (($pointBalance < $cost)
          || (($pointBalance - $cost) <= 0 )
          || ($pointBalance <= 0)){
            echo 'style="color:red;"';
          }
        ?>>
          <strong>
            <?php 
              echo ( $pointBalance < $cost ) ? "you don't have enough balance to pay full amount of order with points" : ($mycred->format_creds($pointBalance - $cost)); 
              // echo ($pointBalance)."\n".bbCustomVariables()->getPointsCurrency();
            ?>
            </strong>
        </div>
      </td>
    </tr>
    <tr class="total ids_custom_cost">
      <th>
        <b>Point Cost</b><br>
        <small>
          <a href="<?php echo get_permalink(bbCustomVariables()->rewardPageID()).'?what-are-points'; ?> " target="_blank">more about points <i class="fas fa-arrow-right"></i></a>
        </small>
    </th>
      <td >
        <div class="current-balance order-total-in-points">
          <b class="mycred-funds" style="color:<?php echo ($pointBalance < $cost ) ? 'red': ''?>"><?php echo $mycred->format_creds( $cost ); ?></b> 
        </div>
      </td>
    </tr>
    <?php echo ob_get_clean();
  }

  public function disableBillingFOrPointBasedCart($address_fields){
    if(myCredCheckoutMofication()->isPointBasedCart()){
      $address_fields['billing_first_name']['required'] = false;
      $address_fields['billing_last_name']['required'] = false;
      $address_fields['billing_company']['required'] = false;
      $address_fields['billing_country']['required'] = false;
      $address_fields['billing_address_1']['required'] = false;
      $address_fields['billing_address_2']['required'] = false;
      $address_fields['billing_city']['required'] = false;
      $address_fields['billing_state']['required'] = false;
      $address_fields['billing_postcode']['required'] = false;
      $address_fields['billing_email']['required'] = false;
      $address_fields['billing_phone']['required'] = false;
      return $address_fields;
    }
    return $address_fields;
  }

  public function changeCurrencyPositionToRight($format, $currency_pos) {
    
    global $wp;
    #
    #Making currency position to the right of the amount.
    # In case we are in cart and checkout page
    if( ( is_cart() || is_checkout() ) && myCredCheckoutMofication()->isPointBasedCart()){
      return  bbCustomVariables()->pointCurrencyFormat();
    }

    # In case of order-received 
    if( !empty( is_wc_endpoint_url('order-received') ) ){
      $orderId  = absint( $wp->query_vars['order-received'] );
      if( myCredCheckoutMofication()->isPointBasedOrder( $orderId ) ){
        return  bbCustomVariables()->pointCurrencyFormat();
      }
      if(empty($orderId)){
        return $format;
      }
    }

    # In case inside loop of product
    $productID = get_the_ID();
    if(get_post_meta($productID, 'purchasable_by_points_only', true) === 'yes'){
      return bbCustomVariables()->pointCurrencyFormat();
    }
    return $format;
  }

  
  public function changeCurrencySymbolPositionToRight($format, $currency_pos) {
    return bbCustomVariables()->pointCurrencyFormat();
  }

  public function changeCurrencyForPointBasedProduct($symbol, $currency){

    global $wp;

    # In case we are in cart and checkout page
    if( ( is_cart() || is_checkout() ) && myCredCheckoutMofication()->isPointBasedCart()){
      return bbCustomVariables()->getPointsCurrency();
    }

    # In case of order-received 
    if( !empty( is_wc_endpoint_url('order-received') ) ){
      $orderId  = absint( $wp->query_vars['order-received'] );
      if( myCredCheckoutMofication()->isPointBasedOrder( $orderId ) ){
        return bbCustomVariables()->getPointsCurrency();
      }
      if(empty($orderId)){
        return $symbol;
      }
    }

    # In case inside loop of product
    $productID = get_the_ID();
    if(get_post_meta($productID, 'purchasable_by_points_only', true) === 'yes'){
      return bbCustomVariables()->getPointsCurrency();
    }
    return $symbol;
  }

  public function changeCurrencySymbolToPoints( $symbol, $currency ) {
    return bbCustomVariables()->getPointsCurrency();
  }

  public function injectSubmitData($template_name, $template_path, $located, $args ){
    if($template_name === 'checkout/mycred-partial-payments.php'){
        if ( ! WC()->cart->needs_payment() ) {
            ?> 
            <script>
              jQuery('#place_order').trigger('click');
            </script>
            <?php
        }
    }
  }

  public function changeWoocommerceOrderButtonHTML($buttonHTML){
    if ( !WC()->cart->needs_payment() ||  !myCredCheckoutMofication()->isPointBasedCart()) {
        return $buttonHTML;
    }
    ob_start();

    $cartAmount = intval( WC()->cart->get_cart_contents_total() );
    ?>
    <style>
    #mycred-partial-payment-woo{
        display: none;
    }
    </style>
    <button type="button" class="button alt" name="custom_woocommerce_checkout_place_order" id="custom_place_order" value="Place order" data-value="Place order">Place order</button>
    <div <?php echo bbCustomVariables()->CssDisplayNone(); ?>>
      <?php echo $buttonHTML; ?>
    </div>
    <script>
        jQuery('#custom_place_order').click(function(){

          if(  jQuery('#mycred-apply-partial-payment').attr('data-custom-disabled') ){
            return;
          }

          var termAccepted = jQuery('.woocommerce-terms-and-conditions-wrapper input[name="terms"]').prop('checked');
          var message    = 'Please read and accept the terms and conditions to proceed with your order.';
          if( !termAccepted ){
            jQuery('#custom_place_order').addClass('loading')
            swal({
              html: message,
              icon: 'info',
              className: "ids-custom-swal",
              button: true,
              content: {
                element: 'div',
                attributes: {
                  innerHTML: message,
                },
              }
            }).then(function(willDelete){
              jQuery('#custom_place_order').removeClass('loading')
            });
            return;
          }
          // jQuery("#custom_place_order ").css("pointer-events", "none");
          jQuery('#mycred-range-selector input').val("<?php echo $cartAmount; ?>").trigger('change'); 
          jQuery('#mycred-apply-partial-payment')
            .removeAttr('disabled')
            .trigger('click')
            .attr('data-custom-disabled', true);
        });
    </script>
    <?php
    echo ob_get_clean(); 
  }

  /**
   *  Function Name : error_reporting
   *  Working       : This function is used for php error_reporting.
  */
  public function error_reporting(){
    if( $this->errors === true ){
      ini_set('display_errors', 1);
      ini_set('display_startup_errors', 1);
      error_reporting(E_ALL); 
    }    
  }


  /**
   *  Function Name : debug
   *  Working       : It is used to debug the code, and printing the array passed to it
   *  Params        : Array needed to be print. 
  */
  public function debug($var){
    echo "<pre>";
      print_r($var);
    echo "</pre>";
  }

  /**
   *  Function Name : debugDump
   *  Working       : It is used to debug the code, and printing the array passed to it
   *  Params        : Array needed to be print. 
  */
  public function debugDump($var){
    echo "<pre>";
      var_dump($var);
    echo "</pre>";
  }

  /**
  * Get a unique instance of this object.
  *
  * @return object
  */
  public static function get_instance() {
    if ( null === self::$instance ) {
      self::$instance = new handPointBasedItemCheckout();
    }
    return self::$instance;
  }

}