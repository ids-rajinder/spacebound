<?php


if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use WeDevs\Dokan\Walkers\TaxonomyDropdown;


class customizingDokan{

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

     #
    #Adding filter to enabling coupons, also when users adding normal products from multi-vendors. 
    add_filter( 'dokan_ensure_vendor_coupon', '__return_false' );

    // #Adding cutom fields to Dokan frontend Dashboard for vendor
    add_action( 'dokan_product_edit_after_inventory_variants', [$this, 'renderACFFields'], 10, 2 );
    add_action( 'dokan_new_product_after_product_tags',[$this,'renderACFFieldsForAddProductPopUP'],10 );

    //  #
    // # These are the hooks for dokan plugin where we want to add new icon and color for product box's footer and save the data
    // #
    add_action( 'dokan_process_product_meta', [$this, 'updateACFField'] , 10, 1);
    add_action( 'dokan_new_product_added'   , [$this, 'updateACFField'] , 10, 2);

    #
    #Removing 'Become a vendor' button from my account page.
    if (class_exists('Dokan_Pro')){
      remove_action( 'woocommerce_after_my_account', array( Dokan_Pro::init(), 'dokan_account_migration_button' ) );
    }


    #
    #Adding Admin option in Dokan settings -> Selling option ,to hide options from vendor dashboard.
    add_filter( 'dokan_settings_fields', [$this,'AddingOptionsinSelling'], 10);

    #
    #To remove selected functions by admin from Dokan Dashbaord menu
    add_filter( 'dokan_get_dashboard_nav',[$this,'removeSelectedOptionFromVendorDashboard'], 16);

    #
    #To remove setting and sub-settings from vendor dashboard
    add_filter( 'dokan_get_dashboard_settings_nav',[$this,'removingeSettingsVendorDashboard'],15);

   
  }

  public function AddingOptionsinSelling($settingsFields){

    $settingsFields ['dokan_selling']['remove_tab'] = array(
        'name'    => 'remove_tab',
        'label'   => __( 'Hide Vendor Dashboard Menu', 'dokan' ),
        'desc'    => __( 'Select the dashboard menu to hide from seller', 'IDS' ),
        'type'    => 'multicheck',
        'default' => array( 'products' => 'Products'),
        'options' => array(
            'products' => 'Products',
            'orders'   => 'Orders',
            'withdraw' => 'Withdraw',
            'coupons'  => 'Coupons', 
            'reviews'  => 'Reviews',
            'reports'  => 'Reports',
            'store'    => 'Store', 
            'payment'  => 'Payment',
            'shipping' => 'Shipping',
            'social'   => 'Social',
            'seo'      => 'SEO',
            'staffs'   => 'Staff',
            'return-request' =>'Return Request',
            'rma'     =>   'RMA (Vendor sub-settings)'
          ),
        );
    return $settingsFields;
  }

  // Dokan Dashbaord menu removing function 

  public function removeSelectedOptionFromVendorDashboard($urls){

    $menus = dokan_get_option('remove_tab','dokan_selling');

    if ( ! empty( $menus ) ) {
        foreach ( $menus as $key => $value ) {
            if ( isset( $urls[ $value ] ) ) {
                unset( $urls[ $value ] );
            }
        }
    }
    return $urls;
  }

  #To remove settings and sub-setting from vendor dashbaord
  function removingeSettingsVendorDashboard($subSettings){

    $menus = dokan_get_option('remove_tab','dokan_selling');
        
        if ( ! empty( $menus ) ) {
          foreach ( $menus as $key => $value ) {
              if ( isset( $subSettings[ $value ] ) ) {
                  unset( $subSettings[ $value ] );
              }
          }
      }
    return $subSettings;
  }

  /* This function is used to update the fields */
  public function updateACFField($productId){

    $postdata = $_POST;

    #
    # Does product ID exists
    #
    if(empty( $_POST['_product_color'] )){
      return;
    }

    if ( isset( $postdata['_product_color'] ) && ! empty( $postdata['_product_color'] ) ) {
      $tags_ids = array_map( 'absint', (array) $postdata['_product_color'] );
      wp_set_object_terms( $productId, $tags_ids, '_product_color' );

    }
  }

  public function renderACFFieldsForAddProductPopUP(){
    $post_id = get_the_ID();
    $post = get_post( $post_id);
    return $this->renderACFFields($post, $post_id);
  }

  public function renderACFFields($post, $post_id){
    ?>
    <div class="dokan-product-deal-setting dokan-edit-row ">
        <div class="dokan-section-heading" data-togglehandler="dokan_product_deal_setting">
          <h2><i class="fa fa-cubes" aria-hidden="true"></i> Add category style </h2>
          <p>Select a tag to add color and icon for the Products.</p>
          <a href="#" class="dokan-section-toggle">
            <i class="fa fa-sort-desc fa-flip-vertical" aria-hidden="true"></i>
          </a>
          <div class="dokan-clearfix"></div>
        </div>

        <div class="dokan-section-content">
          <div class="dokan-form-group">
            <?php
            require_once DOKAN_LIB_DIR.'/class.taxonomy-walker.php';
            $selected_tag = wp_get_post_terms( $post_id, '_product_color', array( 'fields' => 'ids') );
            $selected = ( $selected_tag ) ? $selected_tag : array();

            $drop_down_tags = wp_dropdown_categories( array(
                'show_option_none' => __( '', 'dokan-lite' ),
                'hierarchical'     => 1,
                'hide_empty'       => 0,
                'name'             => '_product_color[]',
                'id'               => '_product_color',
                'taxonomy'         => '_product_color',
                'title_li'         => '',
                'class'            => 'product_tags dokan-form-control dokan-select2',
                'exclude'          => '',
                'selected'         => $selected_tag,
                'echo'             => 0,
                'walker'           => new TaxonomyDropdown()
            ) );

            echo str_replace( '<select', '<select data-placeholder="'.esc_attr__( 'Select product tags', 'dokan-lite' ).'" multiple="multiple" ', $drop_down_tags ); // phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped
            ?>
          </div>
        </div>
    </div>
    <?php
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
      self::$instance = new customizingDokan();
    }
    return self::$instance;
  }

}