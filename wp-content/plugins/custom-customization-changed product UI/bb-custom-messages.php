<?php


if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// $message = bbCustomMessage()->getMessage('earnTicketPoint');

// if($message !== false){

// }

class bbCustomMessage{

  /**
  * The one, true instance of this object.
  *
  * @static
  * @access private
  * @var null|object
  */
  private static $instance = null;

  private $message = [
    'loggedIn' => [

      // Profile page ticket section
      'ticketWinning' => '<h3>Congratulations!</h3>you are the winner of <b>{{TITLE}}</b> with ticket code- <b>{{CODE}}</b>.<br> <a href="{{CONTACT_LINK}}">Contact us</a> to claim your reward.',
      'ticketRaffle-ended' => '<b>{{TITLE}}</b> for ticket- <b>{{CODE}}</b> has been Ended on <b>{{END_DATE}}</b>.Better luck next time.</br> <a href="{{REWARD_LINK}}">Click here</a> to buy a new ticket.',
      'ticketActive' => 'You have already Enrolled with ticket code - <b>{{CODE}}</b> for <b>{{TITLE}}</b><br>Result will be announced on <b>{{END_DATE}}</b>',
      'ticketRedeemed' => 'You have already <strong>redeemed</strong> your reward for <b>{{TITLE}}</b> with ticket code - <b>{{CODE}}</b>',


      #Showing message on thankyou on sucsessfully purchasing point based products.
      'pointThankYouConfirmation' => 'Your purchased item\'s codes are sent to your email or you can review your items on <a href="{{ACCOUNT_LINK}}">My account</a> page or <strong>below</strong>.',

      #Showing message on thankyou when user get a free raffle ticket on purchasing normal products.
      'ticketThankYouConfirmation' => 'Free Raffle ticket will be given to you once order is completed.<br> It will appear in the confirmation email or you can check it on 
       <a href="{{ACCOUNT_LINK}}my-tickets">My Tickets</a> page.',

      #Shwoing comfirmation message, on adding normal products to cart ,if cart have point based products.
       'errorMessage' => [
        "Normal product were removed from the cart, because you have add a point specific product.",
        "Point specific product were removed from the cart, because you have add a normal product.",          
      ],

       #Shwoing comfirmation message, on adding point based products to cart ,if cart have normal products
      'confirmationMessage' => [
        'Adding a point based product will remove all the existing product in the cart, are you sure you want to proceed?',
        'Adding a normal product will remove all the existing point based product in the cart, are you sure you want to proceed?',
      ],

    ],
    'nonLoggedIn' => [
      'loginMessageToAddPintBasedItem' => '<a href="{{LOGIN_LINK}}">Login </a> to your account to purchase point based products.',
      'loginToredeemTicket' => '<a href="{{LOGIN_LINK}}"> Login </a>to your account to reedem ticket',
      'doubleXpEvent' => '<strong>Double XP</strong>Event is on. Get double points on each purchase. &nbsp;<a id ="DoubleXpEvent" href="{{SHOPPAGE_LINK}}" >Purchase now </a>',
      'noProductSelectedFromVaritions' => 'Please select some product options before adding this product to your cart.',
    ],
  ];

  public function getBasicMessage($key){
    $loggedInMessage = $this->message['loggedIn'];
    $nonLoggedInMessage = $this->message['nonLoggedIn'];
    // $bbglobalMessage = $this->message['bbGlobalmessage'];

    if( !isset($loggedInMessage[$key]) && !isset($nonLoggedInMessage[$key])  ){
      return false;
    }
 
    # Nonlogged is consider as default
    if( !isset($loggedInMessage[$key])){
      return $nonLoggedInMessage[$key];
    }

    # Logged
    $user = is_user_logged_in();
    if(empty($user)){
      return isset($nonLoggedInMessage[$key]) ? $nonLoggedInMessage[$key] : false;
    }
    
    return $loggedInMessage[$key];
  }

  public function getMessage($key, $mapping = []){
    $message = $this->getBasicMessage($key);
    if($message === false){
      return $message;
    }

    foreach( $mapping as $key => $value ){
      $message = str_replace("{{{$key}}}", $value, $message);
    }
    return $message;
  }

  public function __construct(){

    #showing for getting ticket on thankyou pgae  #woocommerce_order_details_after_order_table
    add_action( 'woocommerce_before_thankyou', [$this, 'showCouponMesssageOnthankyouPage'], 10, 4); 

  }

  public function showCouponMesssageOnthankyouPage($order_get_id){
    ob_start();
    $ordeID = wc_get_order_id_by_order_key( $_GET['key'] );
    $url = get_permalink( get_option( 'woocommerce_myaccount_page_id' )); 
    if(empty($ordeID)){
      return;
    }

    if( myCredCheckoutMofication()->isPointBasedOrder($ordeID)){ 


      $confirmationMessage = bbCustomMessage()->getMessage( "pointThankYouConfirmation", [
        'ACCOUNT_LINK' => $url
      ]); 

      if( $confirmationMessage !== false){
        ?>
        <div class="ids-ticket-massage">
          <span><?php echo $confirmationMessage; ?></span>
        </div>
        <?php
      }
    ?>

    <?php }
    if( !myCredCheckoutMofication()->isPointBasedOrder($ordeID) && is_user_logged_in()){ 
      $confirmationMessage = bbCustomMessage()->getMessage( "ticketThankYouConfirmation", [
        'ACCOUNT_LINK' => $url
      ]); 
      ?>
    <div class="ids-ticket-massage">
      <span><?php echo  $confirmationMessage ?></span>
    </div>
    <?php
    } 
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
      self::$instance = new bbCustomMessage();
    }
    return self::$instance;
  }

}
