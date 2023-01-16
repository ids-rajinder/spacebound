  <?php


if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class bbCustomVariables{

  /**
  * The one, true instance of this object.
  *
  * @static
  * @access private
  * @var null|object
  */
  private static $instance = null;
  private $shopPageID = 10;
  // private $rewardPageID = 3588;
  private $rewardPageID = 3588;
  private $contactPageID = 651;
  private $shopPage ;

  private $pointsCurrency = 'Points';
  public $cssTohide = 'style="display:none"';
  private $format   = '%2$s&nbsp;%1$s';

  #Number of coupons user can use while checkout to get discount for normal products. 
  private $maxCouponCount = 3 ;

  public function __construct() {
    $this->shopPage = wc_get_page_permalink( 'shop' );
  }

  public function shopPagelink(){
    return $this->shopPage;
  }
 public function rewardPageID(){
   return $this->rewardPageID;
 }

 public function shopPageID(){
  return $this->shopPageID;
}

public function contactPageID(){
  return $this->contactPageID;
}

public function maxCouponCount(){
  return $this->maxCouponCount;
}

public function getPointsCurrencyFormat(){
  return $this->format;
}

public function getPointsCurrency(){
  return $this->pointsCurrency;
}

public function CssDisplayNone(){
  return $this->cssTohide;
}

public function pointCurrencyFormat(){
  return $this->format;
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
      self::$instance = new bbCustomVariables();
    }
    return self::$instance;
  }

}
