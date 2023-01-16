<?php


if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class myCredEmailCustomization{

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


    add_action( 'woocommerce_email_order_details', [$this, 'pointBasedEmailSetting'], 10, 4 );

    add_action( 'woocommerce_email_order_meta', [$this, 'displayTicketInfo'], 2, 4 );

		// allow other plugins to add additional product information here.
		add_action( 'woocommerce_order_item_meta_start', [$this, 'orderItemMeta'], 10, 4 );

    add_action( 'woocommerce_email_before_order_table',[$this, 'addFilterToModifyOrderItemTotal'], 10, 4 );

    add_action( 'woocommerce_email_after_order_table', [$this, 'showSignatureRequiredField'], 10, 4 ); 

  }

  public function showSignatureRequiredField( $order, $sent_to_admin, $plain_text, $email) {
    if(myCredCheckoutMofication()->isPointBasedOrder($order->get_id())){
      return;
    }
    $value = get_post_meta( $order->get_id(), 'signature_required', true );
    ?>
      <table style="width:100%">
        <tbody>
          <tr>
            <td><h3 style="font-weight:100">Signature Required</h3></td>
            <td style="text-align:right"><h3 style="font-weight:100"><?php echo $value === "1" ? "Yes" : "No"; ?></h3></td>
          </tr>
        </tbody>
      </table>
    <?php
  }

  # get_order_item_totals
  public function addFilterToModifyOrderItemTotal($order, $sent_to_admin, $plain_text, $email){
    add_filter( 'woocommerce_get_order_item_totals', [$this, 'modifyOrderItemTotal'], 10, 3 );
    add_action( 'woocommerce_email_after_order_table', [$this, 'removeFilterToModifyOrderItemTotal'], 10, 4 );
  }

  public function removeFilterToModifyOrderItemTotal($order, $sent_to_admin, $plain_text, $email){
    remove_filter( 'woocommerce_get_order_item_totals', [$this, 'modifyOrderItemTotal'], 10, 3 );
  }

  public function modifyOrderItemTotal($totalRows, $object, $tax_display ){
    $orderID = wc_get_order_id_by_order_key($object->order_key);
    if( !myCredCheckoutMofication()->isPointBasedOrder( $orderID )){
      return $totalRows;
    }
    
    if( empty( $totalRows ['cart_subtotal']) || empty($totalRows ['cart_subtotal']['label'])){
      return $totalRows;
    }
  
    $totalRows ['cart_subtotal']['label'] = 'Total';
    unset($totalRows['discount']);
    unset($totalRows['shipping']);
    unset($totalRows['order_total']);
    return $totalRows;

  }

  public function orderItemMeta($itemID, $item, $order, $plain_text){

    $orderID = $order->get_id();
    if( $order->get_status() !== "completed" || !myCredCheckoutMofication()->isPointBasedOrder( $orderID )){
      return;
    }

    # Coupon Code
    $couponCode = myCredProfilePageModification()->getCouponCodeFromItemID($itemID, $orderID);
    if(!empty($couponCode)){
      echo "<br/>Coupon Code: <b>{$couponCode}</b>";
    }  
    
    # Raffel tickets
    $ticketObjects = myCredProfilePageModification()->getTicketObjectFromItemID($itemID, $orderID);
    foreach($ticketObjects as $ticket){
      $raffelCode = get_post_meta($ticket->ID, 'ticket_code', true);
      if( !empty($raffelCode)){
        echo "<br/>{$ticket->post_title}: <b> {$raffelCode}</b>";
      }
    }

  }

  public function displayTicketInfo($order, $sent_to_admin, $plain_text, $email){
    ob_start();
    $ticketObj = myCredProfilePageModification()->getTicketObjFromOrderId(  $order->get_id() );
    foreach($ticketObj as $singleTicket){
      $TicketNamewthDate = ($singleTicket->post_title) ;
      $TicketArray = (explode("--",$TicketNamewthDate)); ?>
      <table cellspacing="0" cellpadding="6" style="width: 100%; font-family: wedges; background: url(https://buybulkshrooms.com/wp-content/uploads/2021/07/ticket-t-1.jpg); background-repeat: no-repeat; background-size:cover;" >
        <tbody style="background: #0000006b;">
          <tr>
            <th scope="col" colspan="3" style="text-align:center; color:#fff;background: #ff00ac47;">
              <h2 style="font-weight:100;color:#fff;text-align:center;font-size:30px;">Congratulations! For getting a free</h2> 
            </th>
          </tr>
          <tr>
            <td style="text-align:center; vertical-align:middle;">
              <h4 style="-webkit-background-clip: text;
              font-size: 45px;
              color: #f3e215;
              text-align: center;
              margin-bottom: 0px;
              text-transform: uppercase;
              line-height: 54px;
              font-weight: bold;
              padding-top: 0px;
              margin-top: 20px;"> <?php echo $TicketArray[0]; ?></h4>
              <!-- <span style="text-align:center;font-weight:100;color:#fff;"><?php //echo $TicketArray[1]; ?></span> -->
              <h3 style="text-align:center;font-weight:100;color:#fff;"><b> Ticket code - <?php echo get_post_meta($singleTicket->ID, 'ticket_code', true); ?></b></h3>
            </td>
          </tr>
        </tbody>
      </table><br>
      <?php
    }
    echo ob_get_clean();
  }

  public function pointBasedEmailSetting($order, $sentToAdmin, $plainText, $email ){

    if( !myCredCheckoutMofication()->isPointBasedOrder( $order->get_id()) ){
      return;
    }

     # Removing default WC address email template
    $wcEmails = WC_Emails::instance();
    remove_action( 'woocommerce_email_customer_details', array( $wcEmails, 'email_addresses' ), 20, 3 );
   
    # Currency correction
    add_filter( 'woocommerce_currency_symbol', [$this, 'changeCurrencyForPointBasedProduct'], 10, 2);
    add_filter( 'woocommerce_price_format', [$this,'changeCurrencyPositionToRight'], 10, 2 ); 
    add_action( 'woocommerce_email_footer', [$this, 'resetCurrency'] );
  }

  public function resetCurrency(){

    # Adding default WC address email template
    $wcEmails = WC_Emails::instance();
    add_action( 'woocommerce_email_customer_details', array( $wcEmails, 'email_addresses' ), 20, 3 );

    # Removing correction
    remove_filter( 'woocommerce_currency_symbol', [$this, 'changeCurrencyForPointBasedProduct'], 10, 2);
    remove_filter( 'woocommerce_price_format', [$this,'changeCurrencyPositionToRight'], 10, 2 ); 

  }

  public function changeCurrencyForPointBasedProduct($symbol, $currency){
    return bbCustomVariables()->getPointsCurrency();
  }


  public function changeCurrencyPositionToRight($format, $currency_pos) {
    return bbCustomVariables()->getPointsCurrencyFormat();
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
      self::$instance = new myCredEmailCustomization();
    }
    return self::$instance;
  }

}


// function mycred_part_woo_settings(){
//   return myCredSettingCustomization()->mycred_part_woo_settings();
// }