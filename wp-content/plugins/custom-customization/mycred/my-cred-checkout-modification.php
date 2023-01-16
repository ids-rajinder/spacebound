<?php


if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class myCredCheckoutMofication{

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
  private $errorMessage = [];
  public function __construct(){
    $this->userID = '';
    $this->plugin_path = CUSTOM_BBS_PATH;
    $this->plugin_url  = CUSTOM_BBS_URL;
    $this->errorMessage = [
      'errorMessage' => bbCustomMessage()->getMessage( 'errorMessage'),
      'confirmationMessage' => bbCustomMessage()->getMessage( 'confirmationMessage')
    ];
    require_once CUSTOM_BBS_PATH . 'mycred/override/mycred-reward-product.php';

    #
    # Not allowing higer rank coupon code to be used by other lower rank user
    add_filter( 'woocommerce_coupon_is_valid', [$this, 'isValidCouponeCodeForGivenRank'], 10, 3 );
    add_filter( 'woocommerce_coupon_is_valid', [$this, 'isValidCouponForGivenUser'], 10, 3 );
    
    #
    # Disable coupon code for point based products
    add_filter( 'woocommerce_coupon_is_valid', [$this, 'isValidCouponForGivenCart'], 10, 3 );

    #
    # Validation coupon code counts
    add_filter( 'woocommerce_coupon_is_valid', [$this, 'validCouponCodeCount'], 10, 3 );
    
    #
    # This was disable the myCred functionality. Mycred is also using coupon based appraoch
    # add_filter( 'woocommerce_coupons_enabled', [$this, 'hideCouponCodeForPointBasedItem'] );

    #
    # TODO: Change this hook, right now this should only work for checkout page(check is palced inside the function)
    # add_action( 'template_redirect', [$this, 'autoApplyDiscountCouponToPointBasedProducts'], 10 );

    #
    # Adding discount coupon based upon the current user rank
    add_action('woocommerce_before_cart', [$this, 'autoApplyDiscountCoupon']);
    add_action('woocommerce_before_checkout_form', [$this, 'autoApplyDiscountCoupon']);

  
    # Adding discount coupon for first order
    add_action('woocommerce_before_cart', [$this, 'autoApplyFirstOrderDiscountCoupon']);
    add_action('woocommerce_before_checkout_form', [$this, 'autoApplyFirstOrderDiscountCoupon']);

    # Allowing specific gateway based on current user rank
    add_filter( 'woocommerce_available_payment_gateways', [$this, 'allowingSpecificPaymentGatewayOnly'] );

    # For variation products
    add_filter( 'woocommerce_add_to_cart_validation', [$this, 'limitMaximumLimitOfTheBudget'], 10, 5 );

    # Removing nor-cart products
    add_filter( 'woocommerce_add_cart_item_data', [$this, 'removeNonPointProduct'], 10,  3);

		add_action( 'woocommerce_add_to_cart', [$this, 'removeAnyCouponCodeForPointBasedCart']);
		add_action( 'woocommerce_cart_item_removed', [$this, 'removeAnyCouponCodeForPointBasedCart']);
		add_action( 'woocommerce_cart_item_restored', [$this, 'removeAnyCouponCodeForPointBasedCart']);

    #
    # Hiding point message
    add_filter('option_reward_points_global_type_val', [$this, 'modifyRewardsPointsGlobal'], 10, 2);

    #
    # Add to cart javascript validation
    add_action( 'wp_ajax_validate_product_before_add_to_cart', [$this, 'addToCartJSValidationCheck'] );
    add_action( 'wp_ajax_nopriv_validate_product_before_add_to_cart', [$this, 'addToCartJSValidationCheck'] );

    # Adding frontend scripts
    add_action('wp_enqueue_scripts', [$this, 'loadScripts']);

    # Validation on cart item update
    add_filter( 'woocommerce_update_cart_validation', [$this, 'validateCartItemQuantity'], 10, 4 ); 

    # Mark order status has completed for Point based order
    add_action( 'woocommerce_before_thankyou', [$this, 'pointBasedConfirmation'] );

    # Avoding function from running in wp-admin area
    if( is_admin() && ! wp_doing_ajax()){
      return;
    }
    # Not allowing payment gateway for point specific products
    # Woocoomerce backend home page is not opening, giving error in get_cart() function
    # 
    add_filter( 'woocommerce_available_payment_gateways', [$this, 'disablePaymentGatewayForPointSpecificProducts'] );

    #To auto apply first order discount coupon after registration  while checkout
    add_action( 'user_register', [$this, 'applyCouponAfterRegister'], 10, 1 );
  }

  public function applyCouponAfterRegister( $userID ) {
    $this->userID = $userID;
    if(is_checkout() && !empty($userID)){
      $this->autoApplyFirstOrderDiscountCoupon();
    }
  }

  # Making order completed for point based order
  public function pointBasedConfirmation( $orderId ) { 
 
    if(!$orderId){
      return;
    }

    if(!$this->isPointBasedOrder($orderId)){
      return;
    }

    $order = wc_get_order( $orderId );
    if( $order->get_status() !== 'completed'){
      $order->update_status( 'completed' );
    }  
  }

  public function loadScripts(){

    $filePath = 'assests/js/custom.js';
    // $jsVersion = date("ymd-Gis", $this->plugin_path. $filePath );
    $jsVersion = time();
    wp_enqueue_script( 'bb__sweetalert_min_js', $this->plugin_url.'assests/js/sweetalert.min.js');   
    wp_enqueue_script( 'bb__custom__js', $this->plugin_url. $filePath . '?' . $jsVersion , array('jquery', 'wc-add-to-cart'));
    wp_localize_script( 'bb__custom__js', 'bbCustomObject', [
      'ajaxURL' => admin_url( 'admin-ajax.php')
    ]);   

  }

  public function validateCartItemQuantity($status, $cart_item_key, $values, $quantity){

    if(empty( $values['product_id'] ) || empty( $values['quantity'] )){
      echo 'Product ID and Quantity not found'; die;
      return false;
    }

    $existingQuanity = $values['quantity'];
    if( $existingQuanity >= $quantity){
      return $status;
    }

    $quantityToAdd = $quantity - $existingQuanity;
    $productID = $values['product_id'];
    $variationID = !empty($values['variation_id'])? $values['variation_id'] : false;
    $rangeStatus = $this->isNewProductInRangeOfUser( $values['product_id'], $quantityToAdd, $variationID);

    if( $rangeStatus === true ){
      return $status;
    } 
    $product = wc_get_product( $productID );
    $productName = $product->get_title();
    wc_add_notice(  __( "Unable to updated quanity for {$productName}! \"{$rangeStatus}\"", "woocommerce" ), "error" );
    return false;
  }

  # 
  # This function will return true in case user can add the item in cart.
  # Will retur error message otherwise
  function isNewProductInRangeOfUser($product_id, $quantity, $variation_id = false){
    
    $userRankID = $this->getUserRank();
    $pointBasedCart = $this->isPointBasedCart();
    $pointBasedItem = get_post_meta($product_id, 'purchasable_by_points_only', true) === 'yes';

    $maximumAllowedLimit = get_post_meta( $userRankID, 'maximum_allowed_limit', true);

    # For point based cart limit is based on number of points that user has inside his account.
    // $pointBasedCart ||
    if( $pointBasedItem){
      $userID = get_current_user_id();
      $loginURL =  get_permalink( get_option('woocommerce_myaccount_page_id') );
      if( empty($userID) ){
        $loginMessage = bbCustomMessage()->getMessage( "loginMessageToAddPintBasedItem", [
          'LOGIN_LINK' => $loginURL
        ]); 
        return $loginMessage;  
      }
      $maximumAllowedLimit = mycred_get_users_balance( $userID );
    }

    if( $maximumAllowedLimit == '-1'){
      return true;
    }

    if( $variation_id === false && !empty($_POST['variation_id']) ){
      $variation_id = intval( $_POST['variation_id'] );
    }

    $product_id = $variation_id === false ?  $product_id : $variation_id;

    if (empty($product_id)){
      return;
    }

    $product = wc_get_product($product_id);
    
    #
    #If Product with quantity variation selected then quantity will be change according to selected variation
    if( !empty($product->get_attribute( 'pa_quantity' ))){
      $quantity = intval($product->get_attribute( 'pa_quantity' ));
    }

    if( $product instanceof WC_Product ){
     $price = $product->get_price();
    }
    // $price   = $product->get_price();

    # This will productotal including the coupon code part.
    $WCcart        = WC()->cart;
    $total = $WCcart->get_cart_contents_total();
     #coupon's Discount Excluded from the total if Coupon Applies
     $TotalDiscount = $WCcart ->get_cart_discount_total();
    if(!empty($TotalDiscount)){
      $total = $total + $TotalDiscount;
    }
    # In case of point based cart, if non-point item is added, then we are going to remove the cart item later and vica versa
    if( ($pointBasedCart && !$pointBasedItem) || (!$pointBasedCart && $pointBasedItem) ){
      $total = 0;
    }

    $newCartTotal = floatval( $total ) + ($quantity * floatval($price));
    
    if( $newCartTotal > $maximumAllowedLimit){
      #changing message for point based products
      // $pointBasedCart ||
      if( $pointBasedItem){
        return "You can’t add products more than {$maximumAllowedLimit} points";
      } 
      // $maximumAllowedLimit = wc_price($maximumAllowedLimit);
      return "You can’t add products more than $".$maximumAllowedLimit;      
    }
    return true;
  }

  public function addToCartJSValidationCheck(){

    global $woocommerce;
   
    $productID = $_POST['product_id'];
    $quantity = $_POST['quantity'];
    $emptyVariationIdMessage = bbCustomMessage()->getMessage( "noProductSelectedFromVaritions");

    if(empty($productID) || empty($quantity) || (isset($_POST['variation_id'])) && (empty($_POST['variation_id']))){
      wp_send_json([
        'status' => false,
        'message' => $emptyVariationIdMessage,
        'type' => 1
      ]);
    }

    $pointBasedCart = $this->isPointBasedCart();
    $pointBasedItem = get_post_meta($productID, 'purchasable_by_points_only', true) === 'yes';
    $errorMessage = $this->errorMessage['confirmationMessage'];

     // $rangeStatus = $this->isNewProductInRangeOfUser( $productID, $quantity, $variation_id);
     $rangeStatus = $this->isNewProductInRangeOfUser( $productID, $quantity);
     if( $rangeStatus !== true ){
       wp_send_json([
         'status' => false,
         'message' => $rangeStatus,
         'type' => 1
       ]);
     }

    # In case of point based cart, if non-point item is added, then we are going to remove the cart item later and vica versa
    if( count($woocommerce->cart->get_cart()) && 
        (($pointBasedCart && !$pointBasedItem) || (!$pointBasedCart && $pointBasedItem))
    ){
      #Check if user is logged in and Allowed balance is greater than the cart total.
      #showing meassage for if cart have normal product and user try to purchase point based product and vice versa.
      $user_Id       = get_current_user_id();
      $Mypointsbalance = mycred_get_users_balance( $user_Id );
      if(is_user_logged_in() ){
        $message = $pointBasedItem ?  $errorMessage[0] : $errorMessage[1];
        wp_send_json([
          'status' => false,
          'message' => $message,
          'type' => 2
        ]);
      }
      wp_send_json([
        'status' => true
      ]);   
    }
  }

  # 
  # Hide message for point base product
  # Passing 0 for point based cart
  public function modifyRewardsPointsGlobal($value, $name){
    return $this->isPointBasedCart()? 0 :$value;
  }

  # Mycred coupon code is auto-applied now and I have palced check so that only one coupon is applied
  # Therefore changing remove any existing coupon code in case cart is modified.
  function removeAnyCouponCodeForPointBasedCart(){
    if( $this->isPointBasedCart() ){
      foreach ( WC()->cart->get_coupons() as $code => $coupon ){
        WC()->cart->remove_coupon( $code );
        wc_add_notice(  __( "Coupon code removed.", "woocommerce" ), "error" );
      }
    }
  }

  #
  # Hide all payment gateway in case point specific product is selected
  # Purchase will be done by points only
  function disablePaymentGatewayForPointSpecificProducts( $available_gateways ) {
    return $this->isPointBasedCart() ? [] : $available_gateways;
  }

  
  #
  # Helper function will return if there any point based product in the cart
  public function isPointBasedCart(){
    global $woocommerce;

    # TODO: This check shouln't be here ideally
    # But  get_cart() is called before cart is loaded andy it is emptying details.
    # Cart is not loaded
    if(!did_action('woocommerce_cart_loaded_from_session')){
      return false;
    }

    foreach ($woocommerce->cart->get_cart() as $cartItemKey => $cartItem) {
      if( get_post_meta($cartItem['product_id'], 'purchasable_by_points_only', true) === 'yes'){
       return true;
      }
    }
    return false;    
  }


  #
  # Helper function will return if there any point based product in the order
  public function isPointBasedOrder($orderId){
    if(empty($orderId)){
      return false;
    }
    $order = wc_get_order( $orderId );
    if($order !== false){
      $items = $order->get_items();
      foreach ( $items as $item ) {
        if( get_post_meta($item->get_product_id(), 'purchasable_by_points_only', true) === 'yes'){
          return true;
        }
      }
    }
    return false; 
  }


  #
  # Removing other type of products
  # 
  public function removeNonPointProduct($cartItemData, $productID, $variationID){
    global $woocommerce;
    $productType = get_post_meta( $productID, 'purchasable_by_points_only', true) === 'yes'? 'yes' : 'no';
    $removed = false;
    foreach ($woocommerce->cart->get_cart() as $cartItemKey => $cartItem) {
      $byPointsOnly = get_post_meta($cartItem['product_id'], 'purchasable_by_points_only', true) === 'yes'? 'yes' : 'no';;
      if( strtolower($byPointsOnly) === strtolower($productType) ){
        continue;
      }
      
      $removed = true;
      $woocommerce->cart->remove_cart_item($cartItemKey);
    }


    # Showing error notice to user in case cart product are switched from normal product to point based product
    # Only showing the error message once
    if( $removed ){
      $noticeType = "error";
      $errorMessage = $this->errorMessage['point_cart_toggle'];
      $noticeArray =  WC()->session->get( 'wc_notices' ); 
      if( !empty($noticeArray[ $noticeType ]) ){
        $update = false;
        foreach( $noticeArray[ $noticeType ] as $noticeIndex => $notice){
          if( in_array($notice['notice'], $errorMessage)){
            unset( $noticeArray[ $noticeType ][$noticeIndex] );
            $update = true;
          }
        }
        if($update){  
          WC()->session->set('wc_notices', $noticeArray);     
        }
      }
      $message = $productType === 'yes' ? $errorMessage[0] : $errorMessage[1];
      wc_add_notice(  __( $message, "woocommerce" ), $noticeType );
    } 

    return $cartItemData;
  }

  #
  # Placing maximum limit on budget based on rank of the user
  #
  function limitMaximumLimitOfTheBudget( $passed, $product_id, $quantity, $variation_id = false, $variations = false) {
 
    $rangeStatus = $this->isNewProductInRangeOfUser( $product_id, $quantity, $variation_id);
    if( $rangeStatus === true ){
      return $passed;
    }

    wc_add_notice( __( $rangeStatus, "woocommerce" ), "error" );  
    return false;
  }

  public function getBasicRank( $type = 'ID' ){
    $ranks = mycred_get_ranks();
    usort($ranks, function($a, $b) {
      return ($a->minimum - $b->minimum) ;
    });
    if( empty($ranks)){
      return false;
    }
    return $type === 'OBJECT' ? $ranks[0] : $ranks[0]->post_id;;
  }

  #
  # For logged in user return the default Rank ID
  # For logged in user return there associated Rank ID
  public function getUserRank($userID = false, $type = 'ID'){
    # User ID default to logged in user ID
    # Assigning Basic RankID to non logged in user.
    $basicRank = $this->getBasicRank($type);
    $userID = $userID === false ? get_current_user_id() : $userID;
    if( empty($userID)){
      return $basicRank;
    }
    $rank = mycred_get_users_rank( $userID );
    if( !empty($rank) && !empty($rank->post_id)){
      return $type === 'OBJECT' ? $rank : $rank->post_id;
    }
    return $basicRank;
   }


  function allowingSpecificPaymentGatewayOnly( $available_gateways ) {

    # User ID default to logged in user ID
    $userID = get_current_user_id();
    // if( empty($userID)){
    //   return $available_gateways;
    // }

    $rankPostID = $this->getUserRank( $userID );
    $gateWayAllowed = get_post_meta( $rankPostID, 'payment_gateway_allowed', true);
    foreach($available_gateways as $key => $value){
      if( !in_array($key, $gateWayAllowed)){
        unset($available_gateways[ $key ]);
      }
    }

    return $available_gateways;
    
  }

  // # Adding one time coupon code
  // function autoApplyDiscountCouponToPointBasedProducts() {

  //   if(!$this->isPointBasedCart()){
  //     return;
  //   }

  //   # TODO: In case user is not logged in then through error
  //   # Point based products can be only added by logged in user and if they have that many points in there account
	// 	global $mycred_partial_payment;

	// 	if ( is_page( (int) get_option( 'woocommerce_checkout_page_id' ) )  ){

  //     global $woocommerce; 
      
  //     # Coupon code is already present
  //     if( count( WC()->cart->get_coupons() ) ){
  //       return;
  //     }

	// 		// check if any coupon is applied before so then return error only if max is less then 100
	// 		if ($mycred_partial_payment['max'] < 100 && count(WC()->cart->get_coupons()) >= 1) {
  //       wc_add_notice(  __( "Please remove previous coupon to apply new discount.", "woocommerce" ), "error" );
  //       return;
	// 		}

  //     $settings      = mycred_part_woo_settings();
  //     $user_id       = get_current_user_id();
  //     $mycred        = mycred( $settings['point_type'] );

	// 	  // Excluded from usage
	//    	if ( $mycred->exclude_user( $user_id ) ){
  //       wc_add_notice(  __( "You are not allowed to use this feature.", "woocommerce" ), "error" );  
  //       return;
  //     } 

  // 		$balance       = $mycred->get_users_balance( $user_id );
	//   	$total         = mycred_part_woo_get_total();
  //     // TODO: Change amount to cart amount
	//   	$amount        = $mycred->number( abs( $total ) );

  //     // Invalid amount
  //     if ( $amount == $mycred->zero() ) {
  //       wc_add_notice(  __( "Amount can not be zero.", "woocommerce" ), "error" );        
  //       return;
  //     }

	// 	  // Too high amount
	// 	  if ( $balance < $amount ) {
  //       wc_add_notice(  __( "Insufficient Funds.", "woocommerce" ), "error" ); 
  //       return;        
  //     }

	//   	$value         = number_format( ( $settings['exchange'] * $amount ), 2, '.', '' );
  //     if ( $value > ( ( $total/ 100 ) * $mycred_partial_payment['max'] ) ){
  //       wc_add_notice(  __( "The amount can not be greater than the maximum amount.", "woocommerce" ), "error" ); 
  //       return;       
  //     }

  //     // Create a Woo Coupon
  //     $coupon_code   = $user_id . time();
  //     $new_coupon_id = wp_insert_post( array(
  //       'post_title'   => $coupon_code,
  //       'post_content' => '',
  //       'post_status'  => 'publish',
  //       'post_author'  => 1,
  //       'post_type'    => 'shop_coupon'
  //     ) );

  //     if ( $new_coupon_id === NULL || is_wp_error( $new_coupon_id ) ){
  //       wc_add_notice(  __( "Failed to complete transaction. Error 1. Please contact support.", "woocommerce" ), "error" ); 
  //       return;   
  //     }

  //     // Update Coupon details
  //     update_post_meta( $new_coupon_id, 'discount_type', 'fixed_cart' );
  //     update_post_meta( $new_coupon_id, 'coupon_amount', $value );
  //     update_post_meta( $new_coupon_id, 'individual_use', 'no' );
  //     update_post_meta( $new_coupon_id, 'product_ids', '' );
  //     update_post_meta( $new_coupon_id, 'exclude_product_ids', '' );

  //     // Make sure you set usage_limit to 1 to prevent duplicate usage!!!
  //     update_post_meta( $new_coupon_id, 'usage_limit', 1 );
  //     update_post_meta( $new_coupon_id, 'usage_limit_per_user', 1 );
  //     update_post_meta( $new_coupon_id, 'limit_usage_to_x_items', '' );
  //     update_post_meta( $new_coupon_id, 'usage_count', '' );
  //     update_post_meta( $new_coupon_id, 'expiry_date', '' );
  //     update_post_meta( $new_coupon_id, 'apply_before_tax', ( ( $settings['before_tax'] == 'no' ) ? 'yes' : 'no' ) ); // setting
  //     update_post_meta( $new_coupon_id, 'free_shipping', ( ( $settings['free_shipping'] == 'no' ) ? 'no' : 'yes' ) ); // setting
  //     update_post_meta( $new_coupon_id, 'product_categories', array() );
  //     update_post_meta( $new_coupon_id, 'exclude_product_categories', array() );
  //     update_post_meta( $new_coupon_id, 'exclude_sale_items', ( ( $settings['sale_items'] == 'no' ) ? 'yes' : 'no' ) ); // setting
  //     update_post_meta( $new_coupon_id, 'minimum_amount', '' );
  //     update_post_meta( $new_coupon_id, 'customer_email', array() );
  //     update_post_meta( $new_coupon_id, '__ids_custom_one_time_coupon', 1 );

  //     $applied = WC()->cart->add_discount( $coupon_code );

  //     if ( $applied === true ) {
        
  //       if($settings['log'] == '') 
  //       $settings['log'] = 'Partial Payment';
        
  //       // Deduct amount only if coupon was successfully applied
  //       $mycred->add_creds(
  //         'partial_payment',
  //         $user_id,
  //         0 - $amount,
  //         $settings['log'],
  //         $new_coupon_id,
  //         '',
  //         $settings['point_type']
  //       );

  //       wc_clear_notices();
  //       wc_add_notice( __( 'Payment Successfully Applied.', 'mycredpartwoo' ) );
  //       return;
  //     }

	// 	  // Delete the coupon
	// 	  wp_trash_post( $new_coupon_id );
  //     wc_add_notice(  __( "Failed to complete transaction. Error 2. Please contact support.", "woocommerce" ), "error" );   
	// 	}
  // }


  #No of count of total orders placed by a user
  function isFirstOrderByUser(){
    // # User ID default to logged in user ID
    $userID = !empty($this->userID) ? $this->userID : get_current_user_id();

    if(empty($userID)){
      return false;
    }
    $customer = new WC_Customer( $userID ); 
    $orderCount =  $customer->get_order_count();
    return $orderCount <= 0;
  }

  # Adding one time coupon code
  function autoApplyFirstOrderDiscountCoupon(){

    if($this->isPointBasedCart()){
      return;
    }

    if(!$this->isFirstOrderByUser()){
      return;
    }

    # Coupon code is applied in past once
    if( WC()->session->get('default_coupon_code_applied') ){
      return;
    }    

    $discountCouponData = get_posts( array(
      'posts_per_page' => 1,
      'orderby'        => 'modified',
      'order'          => 'desc',
      'post_type'      => 'shop_coupon',
      'post_status'    => 'publish',
      'meta_key'       => '_first_order_discount',
      'meta_value'     => 'yes',
      'fields'         => 'ids'
    ) );

    if(empty($discountCouponData)){
      return;
    }

    $couponID = $discountCouponData[0];
    $wc_coupon = new WC_Coupon( $couponID );
    
    if (!$wc_coupon || !$wc_coupon->is_valid()) {
      return;
    }

    $coupon_code = $wc_coupon->get_code();
    if (!$coupon_code) {
      return;
    }
    $coupon_code = wc_format_coupon_code($coupon_code);
    global $woocommerce;
    if (!$woocommerce->cart->has_discount($coupon_code)) {
      if (!$woocommerce->cart->apply_coupon($coupon_code)) {
        $woocommerce->wc_print_notices();
        return;
      }
      WC()->session->set('default_coupon_code_applied', true);
      wc_print_notice( __( 'First order Discount coupon applied.', 'woocommerce' ), 'success' );
    }
  }

  # Adding one time coupon code
  function autoApplyDiscountCoupon() {

    if($this->isPointBasedCart()){
      return;
    }

    # User ID default to logged in user ID
    $userID = get_current_user_id();
    if( empty($userID)){
      return;
    }

    $rankID = $this->getUserRank( $userID );
    $discountCode = get_post_meta( $rankID, 'discount_coupon', true);

    if(empty($discountCode)){
      return;
    }
    
    $wc_coupon = new WC_Coupon( $discountCode ); // get intance of wc_coupon which code is "DEMO-90JOURS"

    # Coupon code is applied in past once
    $status = WC()->session->get('default_coupon_code_applied');
    if( $status ){
      return;
    }

    if (!$wc_coupon || !$wc_coupon->is_valid()) {
      return;
    }

    $coupon_code = $wc_coupon->get_code();
    if (!$coupon_code) {
      return;
    }

    global $woocommerce;
    if (!$woocommerce->cart->has_discount($coupon_code)) {
        // You can call apply_coupon() without checking if the coupon already has been applied,
        // because the function apply_coupon() will itself make sure to not re-add it if it was applied before.
        // However this if-check prevents the customer getting a error message saying
        // “The coupon has already been applied” every time the cart is updated.
        if (!$woocommerce->cart->apply_coupon($coupon_code)) {
          $woocommerce->wc_print_notices();
          return;
        }
        WC()->session->set('default_coupon_code_applied', true);
        wc_print_notice( __( 'Coupon applied based on rank.', 'woocommerce' ), 'success' );
    }
  }

  #
  # Hide coupon code option for Point based Items
  // function hideCouponCodeForPointBasedItem( $enabled ) {

  //   if(!did_action('woocommerce_cart_loaded_from_session')){
  //     return $enabled;
  //   }

  //   if($this->isPointBasedCart()){
  //     return false;
  //   }
  //   return $enabled;
  // }

  # 
  #  Maximum of 3 coupon code allowed
  #  1. Rank based coupon code
  #  2. Percentage coupon code/ Fixed Coupon code - Purchased
  #  3. Percentage coupon code/ Fixed Coupon code
  #   OR First order discount coupon
  public function validCouponCodeCount($status, $coupon, $obj){

    $maxCouponCount = bbCustomVariables()->maxCouponCount();
    $cartCoupons = WC()->cart->get_applied_coupons();

    $activeCouponCode = strtolower( $coupon->get_code() );
    $activeCouponId = $coupon->get_id();

    if(in_array($activeCouponCode, $cartCoupons)){
      return $status;
    }
    
    #To prevent to use first order discount coupon by older users 
    $firstOrderDiscount = get_post_meta( $activeCouponId, '_first_order_discount', true) === 'yes';
    if(!$this->isFirstOrderByUser() && $firstOrderDiscount){
      wc_print_notice( __( "You are not eligible to use this coupon.", 'woocommerce' ), 'error' );      
      return false; 
    }

    $isMyCredCoupon = get_post_meta($activeCouponId, '__ids_custom_one_time_coupon', true);
    if( !empty($isMyCredCoupon)){
      return $status;
    }

    // Is Point based item
    $isPointBasedCoupon = !empty(get_post_meta( $activeCouponId, '_itemMetaHash', true));
    $rankBasedMeta = get_post_meta( $activeCouponId, 'rank_based_coupon', true) ;
    $isRankBasedCoupon = !empty($rankBasedMeta) && $rankBasedMeta != '- Select -';
    foreach( $cartCoupons as $couponCode){
      # These are created by mycred partial payment, custom meta is added inside the coupon code
      $couponObj = new WC_Coupon($couponCode);
      $isCouponObjMyCredCoupon = get_post_meta( $couponObj->get_id(), '__ids_custom_one_time_coupon', true);
      if( !empty($isCouponObjMyCredCoupon)){
        continue;
      }

      $rankBasedObjMeta = get_post_meta( $couponObj->get_id(), 'rank_based_coupon', true) ;
      $isCouponObjRankBased = !empty($rankBasedObjMeta) && $rankBasedObjMeta != '- Select -';      
      if( $isCouponObjRankBased && $isRankBasedCoupon ){
        wc_print_notice( __( "Maximum one rank based coupon code is allowed.", 'woocommerce' ), 'error' );      
        return false; 
      }

      $icCouponObjPointBased = !empty(get_post_meta( $couponObj->get_id(), '_itemMetaHash', true)); 
      if( $icCouponObjPointBased && $isPointBasedCoupon ){
        wc_print_notice( __( "Maximum one paid coupon code allowed.", 'woocommerce' ), 'error' );      
        return false; 
      }

      $isCouponObjfirstOrderDiscount = get_post_meta( $couponObj->get_id(), '_first_order_discount', true) === 'yes'; 
      if( $isCouponObjfirstOrderDiscount && $firstOrderDiscount ){
        wc_print_notice( __( "Maximum one first order discount coupon code allowed.", 'woocommerce' ), 'error' );      
        return false; 
      }

      if( !$isCouponObjRankBased && !$icCouponObjPointBased && !$isPointBasedCoupon && !$isRankBasedCoupon && !$firstOrderDiscount){
        wc_print_notice( __( "Maximum one simple coupon code allowed.", 'woocommerce' ), 'error' ); 
        return false; 
      }
    }
    return $status;
  }

  #
  # Disable coupone code for Point based Items
  public function isValidCouponForGivenCart($status, $coupon, $obj){
    if($this->isPointBasedCart()){
      return !empty(get_post_meta( $coupon->get_id(), '__ids_custom_one_time_coupon', true));
    }
    return $status;
  }

  #
  # This check is for coupon that are created by myCred
  # We are making sure that coupon code is only accessible by the user.
  #
  public function isValidCouponForGivenUser($status, $coupon, $obj){
    # In case coupone is not present, this check can be removed. 
    # As if now I am not sure what kind of parameter I will get here.
    if( empty($coupon) || empty($coupon->get_id())){
      return $status;
    }

    $couponCodeID = $coupon->get_id();
    $referenceType = get_post_meta( $couponCodeID, 'reference_type', true);

    if( $referenceType !== 'rank') {
      return $status;
    }
    return get_current_user_id() == get_post_meta( $couponCodeID, 'user_id', true);

  }

  public function getCouponCodeArrayBasedOnRank(){
    $ranks = mycred_get_ranks( 'publish', -1, 'DESC' );
  
    $rankCouponeCodeArray = [];
    foreach($ranks as $rank){
      $rankCouponeCodeArray[  $rank->post_id ] = get_post_meta( $rank->post_id, 'discount_coupon', true);
    }
    return $rankCouponeCodeArray = array_filter($rankCouponeCodeArray);
  }

  #
  # Is user using a coupone which is associated with the rank
  # 
  public function isValidCouponeCodeForGivenRank($status, $coupon, $obj){

    # In case coupone is not present, this check can be removed. 
    # As if now I am not sure what kind of parameter I will get here.
    if( empty($coupon) || empty($coupon->get_id())){
      return $status;
    }
    # User ID default to logged in user ID
    $userID = get_current_user_id();
    $rankCouponeCodeArray = $this->getCouponCodeArrayBasedOnRank();
    $activeCouponCode = strtolower(  $coupon->get_code() );   
    if(empty($userID) && in_array($activeCouponCode, $rankCouponeCodeArray)){
      return false;
    }

    if( !in_array($activeCouponCode, $rankCouponeCodeArray) ){
      return $status;
    }

    $userRankID = $this->getUserRank( $userID );
    if(  empty($rankCouponeCodeArray[ $userRankID ])
     ||  $rankCouponeCodeArray[ $userRankID ] != $activeCouponCode ){
      return false;
    }
    return true;
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
      self::$instance = new myCredCheckoutMofication();
    }
    return self::$instance;
  }

}