<?php


if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// $message = bbCustomMessage()->getMessage('earnTicketPoint');

// if($message !== false){

// }

class bbCustomNotification{

  /**
  * The one, true instance of this object.
  *
  * @static
  * @access private
  * @var null|object
  */
  private static $instance = null;


  public function __construct(){

    #checking page id after redirect
    add_action ('template_redirect', [$this, 'addinghookdoubleXPreventAlert']);

    #
    // #Showing alert when Double XP event is on.
    add_action('wp_footer', [$this ,'doubleXPeventAlert'],10);

    #
    #To hide double Xp notification when user clicked on hide once
    add_action( 'wp_ajax__ids_turnOffNotification', [$this, 'turnOffNotification'] );
    add_action( 'wp_ajax_nopriv__ids_turnOffNotification', [$this, 'turnOffNotification'] );


    // add_filter( 'woocommerce_update_cart_validation', [$this, 'resetNotificationStatusFilter'], 999, 4 ); 
    // add_filter( 'woocommerce_add_to_cart_validation', [$this, 'resetNotificationStatusFilter'], 999, 5 );

    // add_action( 'woocommerce_remove_cart_item', [$this, 'resetNotificationStatus'], 10, 2 );

  }

  public function resetNotificationStatusFilter($status){
    if( $status === true){
      $this->resetNotifiction('myCredPoints');
    }
    return $status;
  }

  public function resetNotificationStatus(){
    $this->resetNotifiction('myCredPoints');
  }


  public function turnOffNotification(){

    if( empty($_POST) || empty($_POST['type'])){
      die;
    }

    
    $type = $_POST['type'];

    $existingArray = WC()->session->get('__turned_of_notification');

    if( empty($existingArray)){
      $existingArray = [];
    }

    # Set session is not configured.
    # For non-logged in user, if they have not added anythin in cart yet.
    if ( ! WC()->session->has_session() ) {
      WC()->session->set_customer_session_cookie( true );
    }


    if( !in_array($type, $existingArray)){
      $existingArray[] = $type;
      WC()->session->set('__turned_of_notification', $existingArray);
    }

    die;
  }

  public function resetNotifiction($key){
    $existingArray = WC()->session->get('__turned_of_notification');
    if( empty($existingArray)){
      return;
    }      
    $newArray = array_diff($existingArray, [$key]);
    WC()->session->set('__turned_of_notification', $newArray);
  }


  public function addinghookdoubleXPreventAlert(){
    if(is_cart() || is_checkout()){
     return false;
    }
    return true;
  } 

  public function isNotifictionTurnedOf($key){
    $existingArray = WC()->session->get( '__turned_of_notification');
    if(empty($existingArray)){
      return false;
    }
    return in_array($key, $existingArray);
  }

  public function doubleXPeventAlert(){
    if ($this->addinghookdoubleXPreventAlert() == false){
      return;
    }
    if(empty(woodmart_get_opt( 'weekend_double_xp' )) ){
     return;
    }

    if($this->isNotifictionTurnedOf('doubleXpEvent')){
      return;
    }

    $url = bbCustomVariables()->shopPagelink();
    $confirmationMessage = bbCustomMessage()->getMessage( "doubleXpEvent", [
      'SHOPPAGE_LINK' => $url
    ]); 
    ob_start();?>
    <div class="elementor-section-wrap">
      <section class="wd-negative-gap elementor-section elementor-top-section elementor-element elementor-element-a14fa9c elementor-section-boxed elementor-section-height-default elementor-section-height-default wd-section-disabled" data-id="a14fa9c" data-element_type="section">
        <div class="elementor-container elementor-column-gap-default">
            <div class="elementor-row">
              <div class="elementor-column elementor-col-100 elementor-top-column elementor-element elementor-element-e746ff9" data-id="e746ff9" data-element_type="column">
                  <div class="elementor-column-wrap elementor-element-populated">
                    <div class="elementor-widget-wrap">
                        <div class="elementor-element elementor-element-5e300e3 color-scheme-inherit text-left elementor-widget elementor-widget-text-editor" data-id="5e300e3" data-element_type="widget" data-widget_type="text-editor.default">
                          <div class="elementor-widget-container">
                              <div class="elementor-text-editor elementor-clearfix">
                                <div class="woocommerce-info ids-custom-doublxp-notice" data-__idsNoticeType="doubleXpEvent"> <?php  echo $confirmationMessage; ?>
          <span class="elementor-button-content-wrapper"></div>
                              </div>
                          </div>
                        </div>
                    </div>
                  </div>
              </div>
            </div>
        </div>
      </section>
    </div>
    <?php echo ob_get_clean();
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
      self::$instance = new bbCustomNotification();
    }
    return self::$instance;
  }

}
