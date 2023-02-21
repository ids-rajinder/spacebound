<?php


if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class templateOverride{

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
  
  // public $cssTohide = 'style="display:none"';

  // public function CssDisplayNone(){
  //   return $this->cssTohide;
  // }

 

  public function __construct(){
    $this->plugin_path = CUSTOM_BBS_PATH;
    $this->plugin_url  = CUSTOM_BBS_URL;

    #Overriding wocommerce Templates.
    add_filter( 'wc_get_template', [$this, 'loadCustomWCTemplate'], 10, 5); 
    // add_action( 'woocommerce_order_details_before_order_table_items', [$this, 'AddingFilterToGetTemplate'], 10, 2);
    // add_action('woocommerce_email_order_details', [$this, 'orderID'], 5, 4 );
  }
 
public function orderID( $order, $sent_to_admin, $plain_text, $email ) {
  return $order->id;
}
  public function loadCustomWCTemplate($located, $template_name, $args, $template_path, $default_path){

    switch($template_name){
      case 'checkout/payment.php':
        return $this->plugin_path .  'woocommerce/template/checkout/payment.php';
      break;

      case 'cart/cart-shipping.php':
        if( !myCredCheckoutMofication()->isPointBasedCart()){
          return $located;
        }
        return $this->plugin_path .  'woocommerce/template/cart/cart-shipping.php';
      break; 

      case 'checkout/form-billing.php':
        // if( !myCredCheckoutMofication()->isPointBasedCart()){
        //   return $located;
        // }
        return $this->plugin_path .  'woocommerce/template/checkout/form-billing.php';
      break;

      case 'checkout/form-shipping.php':
        if( !myCredCheckoutMofication()->isPointBasedCart()){
          return $located;
        }
        return $this->plugin_path .  'woocommerce/template/checkout/form-shipping.php';
      break;   

      case 'checkout/form-coupon.php':
        if( !myCredCheckoutMofication()->isPointBasedCart()){
          return $located;
        }
        return $this->plugin_path .  'woocommerce/template/checkout/form-coupon.php';
      break; 

      case 'order/order-details-customer.php':
        $orderId = $this->getOrderIdOnConfirmation();
        if( !myCredCheckoutMofication()->isPointBasedOrder( $orderId )){   
          return $located;
        } 
        return $this->plugin_path .  'woocommerce/template/order/order-details-customer.php';
      break;    

      case 'checkout/form-checkout.php':
        if( !myCredCheckoutMofication()->isPointBasedCart()){
          return $located;
        }
        return $this->plugin_path .  'woocommerce/template/checkout/form-checkout.php';
      break;   

      case 'checkout/thankyou.php':
        $orderId = $this->getOrderIdOnConfirmation();
        # Making sure that automatic coupon is applied next time
        if(WC()->session != null){
          WC()->session->set('default_coupon_code_applied', false);
        }
        
        if( !myCredCheckoutMofication()->isPointBasedOrder( $orderId )){   
          return $located;
        } 
        return $this->plugin_path .  'woocommerce/template/checkout/thankyou.php';
      break;   

      case 'order/order-details.php':
        $orderId = $this->getOrderIdOnConfirmation();
        if( !myCredCheckoutMofication()->isPointBasedOrder( $orderId )){   
          return $located;
        } 
        return $this->plugin_path .  'woocommerce/template/order/order-details.php';
      break;

      case 'cart/cart-totals.php':
        if( !myCredCheckoutMofication()->isPointBasedCart()){
          return $located;
        }
        return $this->plugin_path .  'woocommerce/template/cart/cart-totals.php';
      break; 

      case 'checkout/review-order.php':
        if( !myCredCheckoutMofication()->isPointBasedCart()){
          return $located;
        }
        return $this->plugin_path .  'woocommerce/template/checkout/review-order.php';
      break; 

      case 'order/order-again.php':
        $orderId = $this->getOrderIdOnConfirmation();
        $order = wc_get_order( $orderId );
        if($order){
          $order_status  = $order->get_status();
        }
        return $this->plugin_path .  'woocommerce/template/order/order-again.php';
      break;

      case 'single-product/add-to-cart/variation-add-to-cart-button.php':
        return $this->plugin_path .  'woocommerce/template/single-product/add-to-cart/variation-add-to-cart-button.php';
      break;
      
      case 'single-product/add-to-cart/simple.php':
        return $this->plugin_path .  'woocommerce/template/single-product/add-to-cart/simple.php';
      break;

      case 'single-product/add-to-cart/composite-button.php':
        return $this->plugin_path .  'woocommerce-composite-products/templates/single-product/add-to-cart/composite-button.php';
      break;
      
    }
    return $located;
  }

public function getOrderIdOnConfirmation(){
  global $wp;
  $orderId = false;
  if( !empty( $wp->query_vars['order-received']) ){
    $orderId  = absint( $wp->query_vars['order-received'] );
  }

  # View order page is passing the ID of the order with different variable.
  if( empty($orderId) && !empty($wp->query_vars['view-order'])){
    $orderId  = absint( $wp->query_vars['view-order'] );
  } 
  return $orderId;
}

  
public function AddingFilterToGetTemplate($order){
  add_filter( 'wc_get_template', [$this, 'ToGetTemplate'], 10, 5); 
}

public function ToGetTemplate($located, $template_name, $args, $template_path, $default_path){
  return $this->loadCustomWCTemplate($located, $template_name, $args, $template_path, $default_path);
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
      self::$instance = new templateOverride();
    }
    return self::$instance;
  }

}