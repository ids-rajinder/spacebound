<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
class bbInlineVariationLoad{

  /**
  * The one, true instance of this object.
  *
  * @static
  * @access private
  * @var null|object
  */
  private static $instance = null;

  public function __construct(){
    #To add custom summary on single product page
    add_action('wp_head', [$this, 'overRideVariationAjax']);

  }

  #Overriding function to preventing to send ajax call to load variation products data
  #Rather than we are geting data from already saved script in "content-product-base.php" file
  public function overRideVariationAjax(){
    ?>
    <script>
    (function(){
      jQuery(document).ready(function(){
        jQuery('body').on('woo_variation_swatches_form_init', '.variations_form', function(event, self){
          if(typeof(bbCachedVairationJSON) === 'undefined'){
            return;
          }
          var product_id = self.$form.data('product_id');
          if( !product_id || !bbCachedVairationJSON[product_id]){
            return;
          }

          self.init = function($form){
            var _this = self;
            var product_id = $form.data('product_id');
            if (this.useAjax) {
              $form.data('product_variations', bbCachedVairationJSON[product_id]);
              _this.variationData = $form.data('product_variations');
              _this.useAjax = false;
            } 
            this.afterGalleryInit($form);
          }
        });
      });
    })();
    </script>
    <?php
  }

  #To get and return variation products data to save in script
  function wvs_pro_get_available_variations_modified($productId){
    $product_id     = absint( $productId );
    $use_transient  = wc_string_to_bool( woo_variation_swatches()->get_option( 'use_transient' ) );
    $transient_name = sprintf( 'wvs_archive_available_variations_%s', $product_id );
    $cache          = new Woo_Variation_Swatches_Cache( $transient_name, 'wvs_archive_template' );
  
    // Clear cache
    if ( isset( $_GET['wvs_clear_transient'] ) ) {
      $cache->delete_transient();
    }
  
    // Create cache
    if ( $use_transient && $transient_data = $cache->get_transient( $transient_name ) ) {
  
      if ( ! empty( $transient_data ) ) {
        return $transient_data;
      }
    }
  
    $variable_product = wc_get_product( $product_id );
  
    if ( ! $variable_product || !method_exists($variable_product, 'get_available_variations') ) {
      return false;
    }
  
    $data = apply_filters( 'wvs_pro_get_available_variations', array_values( $variable_product->get_available_variations() ), $variable_product );
    // Set cache
    if ( $use_transient ) {
      $cache->set_transient( $data, DAY_IN_SECONDS );
    }
  
    return  $data ? $data : false;
  }

  /* Get a unique instance of this object.
  *
  * @return object
  */
  public static function get_instance() {
    if ( null === self::$instance ) {
      self::$instance = new bbInlineVariationLoad();
    }
    return self::$instance;
  }


}
