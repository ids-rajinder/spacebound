<?php


if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use XTS\Options;

class themeCustomSettingOption{

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

    $this->addCustomSettingSection();

  }

  public function addCustomSettingSection(){
    /**
     * IDS Custom settings.
     */
    Options::add_section(
      array(
        'id'       => 'ids-custom-settings',
        'name'     => esc_html__( 'IDS Custom settings', 'woodmart' ),
        'priority' => 160,
        'icon'     => 'dashicons dashicons-hammer',
      )
    );

    Options::add_field(
      array(
        'id'          => 'weekend_double_xp',
        'name'        => esc_html__( 'Enable weekend double xp mode', 'woodmart' ),
        'description' => esc_html__( 'If enabled then the user will earn doulbe point during checkout.', 'woodmart' ),
        'type'        => 'switcher',
        'section'     => 'ids-custom-settings',
        'default'     => false,
        'priority'    => 10,
      )
    );

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
      self::$instance = new themeCustomSettingOption();
    }
    return self::$instance;
  }

}
