<?php


if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class myCredSettingCustomization{

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
  private $wcLoaded = false;
  

  public function __construct(){
    $this->plugin_path = CUSTOM_BBS_PATH;
    $this->plugin_url  = CUSTOM_BBS_URL;


    #
    # Changing points value based on the item in the CART
    # For Non-Point based product it will work based on backend settings
    # For Point based product we are going to have 1 as the conversion rate
    add_action( 'woocommerce_cart_loaded_from_session', [$this, 'modifyPointsValue']); 
   

    #
    # Removing Point based product from shop page
    add_action( 'woocommerce_product_query', [$this, 'hidingPointBasedItem'], 10, 2);


    #
    # Removing Point based product from the tabs ajax call, there appears in home page
    add_action( 'wp_ajax_woodmart_get_products_tab_shortcode', [$this, 'addQueryModification'], 9);
    add_action( 'wp_ajax_nopriv_woodmart_get_products_tab_shortcode', [$this, 'addQueryModification'], 9);


    #
    # Showing Point based product in Reward page only inside elementor widget with name 'wd_products'
    add_action ('template_redirect', [$this, 'addProductCheckOnRewardPage']);

    #
    # Updating rank of user based on the budget they have spent.
    add_action( 'woocommerce_order_status_completed', [$this, 'orderChangedRecalculateRank'] );

    #
    # In case order is trashed need to change the rank
    add_action( 'before_delete_post', [$this, 'orderDeletedRecalculateRank'], 99, 2 );

    # After user registered
    add_action('user_register',[$this, 'assigningBasicRankToNewUser']);

  }

  #
  #
  # Assigning minimum rank to the new created user
  function assigningBasicRankToNewUser($user_id){
    mycred_save_users_rank( $user_id,  myCredCheckoutMofication()->getBasicRank());
  }
 

  #
  # Order is trashed, updated user rank
  function orderDeletedRecalculateRank( $postID, $post ) {
      
    if ( 'shop_order' !== $post->post_type ) {
      return;
    }

    $this->orderChangedRecalculateRank($postID);
  
    // My custom stuff for deleting my custom post type here
  }


  function orderChangedRecalculateRank($postID){
    $order = wc_get_order( $postID );
    $user = $order->get_user();


    # For guest user return from here, since rank calculation doesn't make sense
    if(empty($user)){
      return;
    }

    $this->calculateUserRank($user->ID);
  }

  function calculateUserRank( $userID ){

    $currentUserRank = myCredCheckoutMofication()->getUserRank($userID);

    // TODO: Placed check for user rank.

    // Get all customer orders
    $orders = get_posts(array(
      'numberposts' => -1,
      'meta_key'    => '_customer_user',
      'meta_value'  => $userID,
      // 'post_status'   => wc_get_order_types(),// ['wc-pending', 'wc-processing', 'wc-on-hold', 'wc-completed', 'wc-cancelled', 'wc-refunded', 'wc-failed']
      // 'post_type'   => wc_get_order_types(), // ['shop_order', 'shop_order_refund']
      'post_type'   => 'shop_order', // ['shop_order', 'shop_order_refund']
      'post_status' => 'wc-completed',

    ) );

    # Getting total of each user
    $total = 0;
    foreach( $orders as $orderPost ){

      # In case of sub-order don't update the rank
      $post = get_post( $orderPost->ID);
      if($post->post_parent !== 0){
        continue;
      }

      $order = wc_get_order($orderPost->ID);
      $total += floatval( $order->get_total() );
    }

    $ranks = mycred_get_ranks();
    # Assigning user rank based on total amount spent in past
    foreach($ranks as $rank){
      
      // TODO: Uncomment the following otherwise rank will configured again and again
      if($rank->post_id == $currentUserRank){
        // echo 'Current user rank is having the best rank already'; die;
        return;
      }

      $minimum = $rank->minimum;
    //   $this->debug($minimum);
    // die;
      # Update user rank
      if( $minimum <= $total){
        mycred_save_users_rank( $userID, $rank->post_id);
        return;
      }

    }
  } 
  public function addProductCheckOnRewardPage(){
    if( bbCustomVariables()->rewardPageID() == get_queried_object_id() ){
      add_action( 'elementor/frontend/widget/before_render', [$this, 'onlyShowingCouponProductInRewardPage'] );
    }
  }

  public function onlyShowingCouponProductInRewardPage($object){
    if( $object->get_name() === 'wd_products'){
      add_action( 'pre_get_posts', [$this, 'showAndHidePointBasedProductBasedOnRewardPage'] );
      add_action( 'elementor/frontend/widget/after_render', [$this, 'removeShowingCouponProductInRewardPage'] );
      add_action( 'woocommerce_after_shop_loop_item_title', [$this, 'addAddToCartAfterTitleInRewards'] );
    }
  }

  public function changeRewardPageAddToCart(){
    return 'Shop now';
  }

  #
  # In reward page we need Add-To-Cart button after the title
  public function addAddToCartAfterTitleInRewards(){
    add_filter('woocommerce_product_add_to_cart_text',  [$this, 'changeRewardPageAddToCart']);
    ?>
      <div class="wd-bottom-actions wd-bottom-actions-modified">
        <div class="woodmart-add-btn">
          <?php do_action( 'woocommerce_after_shop_loop_item' ); ?>
        </div>
     </div> 
    <?php   
    remove_filter('woocommerce_product_add_to_cart_text',  [$this, 'changeRewardPageAddToCart']);
  }

  #
  # Removing the hooks, once filteration is completed
  public function removeShowingCouponProductInRewardPage(){
    remove_action( 'pre_get_posts', [$this, 'showAndHidePointBasedProductBasedOnRewardPage'] );
    remove_action( 'elementor/frontend/widget/before_render', [$this, 'onlyShowingCouponProductInRewardPage'] );    
    remove_action( 'elementor/frontend/widget/after_render', [$this, 'removeShowingCouponProductInRewardPage'] );
    remove_action( 'woocommerce_after_shop_loop_item_title', [$this, 'addAddToCartAfterTitleInRewards'] );
  }

  public function addQueryModification(){
    add_action( 'pre_get_posts', [$this, 'showAndHidePointBasedProductBasedOnRewardPage'] );
  }


  #
  # Modifying product based query, It will modify all product based query
  # Calling this only in shop page, home page ajax tabs and reward page
  public function showAndHidePointBasedProductBasedOnRewardPage($query){

    $onlyShowPointBasedProducts = false;

    if(bbCustomVariables()->rewardPageID() == get_queried_object_id()){
      $onlyShowPointBasedProducts = true;
    }

    // echo '<pre>';
    //   print_r( $query );
    // echo '</pre>';
 
    if( isset($query->query_vars['post_type']) && $query->query_vars['post_type'] == 'product'){     // run only for the Event post type

      $meta_query = $query->get( 'meta_query');

      if( empty($meta_query)){
        $meta_query = [];
      }

      if( is_single() ){
        return $query;
      }

      // Define an additional tax query 
      $tempQuery= [
        'relation' => 'OR',
        [
          'key' => 'purchasable_by_points_only',
          'value'    => 'yes',
          'compare' => '!=',
        ],
        [
          'key' => 'purchasable_by_points_only',
          'compare' => 'NOT EXISTS',        
        ]
      ];
      

      if( $onlyShowPointBasedProducts ){
        $tempQuery = [
          'key' => 'purchasable_by_points_only',
          'value'    => 'yes',
        ];
      }

      $meta_query[] = $tempQuery;

      // Set the new merged tax query
      $query->set( 'meta_query', $meta_query );
  
    }
    return $query;
  }

  public function hidingPointBasedItem( $q, $query ) {
    // Get any existing Tax query
    $meta_query = $q->get( 'meta_query');

    $pageID = get_queried_object_id();
    
    $productCat = $q->get('product_cat');
    if( $productCat === 'rewards' ){
      return $q;
    }

    // Define an additional tax query 
    $meta_query[] = [
      'relation' => 'OR',
      [
        'key' => 'purchasable_by_points_only',
        'value'    => 'yes',
        'compare' => '!=',
      ],
      [
        'key' => 'purchasable_by_points_only',
        'compare' => 'NOT EXISTS',        
      ]
    ];
    
    // Set the new merged tax query
    $q->set( 'meta_query', $meta_query );

  }

  public function modifyPointsValue(){

    if(is_admin()){
      return;
    }
    global $mycred_partial_payment;

    if( is_user_logged_in() && $this->isCartHavingPointsProductOnly()){
      $mycred_partial_payment['exchange'] = '1';
    }
  }

  # This function will return 
  # true - In case all product are purchasable by point
  # false - In case any product is not purchasable by point
  public function isCartHavingPointsProductOnly(){   

    if ( !class_exists( 'woocommerce' ) ) { 
      return false; 
    }

    if (!function_exists('WC')){
      return false;
    }

    global $woocommerce;

    $cart = WC()->cart->get_cart();
    
    if ( empty( $cart ) ){ 
      return false;
    }

    foreach ($cart as $cartItemKey => $cartItem) {
      if( strtolower( get_post_meta($cartItem['product_id'], 'purchasable_by_points_only', true) ) === 'yes' ){
        continue;
      }
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
      self::$instance = new myCredSettingCustomization();
    }
    return self::$instance;
  }

}
