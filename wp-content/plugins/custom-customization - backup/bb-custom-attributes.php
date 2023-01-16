<?php


if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class bbCustomAttributes{

  /**
  * The one, true instance of this object.
  *
  * @static
  * @access private
  * @var null|object
  */
  private static $instance = null;

  private $variation_id = null;

  public function getShowAttribute($id){
    return "bb_show_attribute-{$id}";
  }

  public function getUnitAttribute($id){
    return "bb_custom_attributes_unit-{$id}";
  }

  public function __construct(){

    #Adding custom fields on backend attributes 
    add_action( 'woocommerce_after_add_attribute_fields', [$this, 'addingCustomAttributeField']);
    add_action( 'woocommerce_after_edit_attribute_fields', [$this, 'addingCustomAttributeField'] );

    #Saving data of custom attribute fields
    add_action( 'woocommerce_attribute_added',[$this, 'saveCustomAttributes'] );
    add_action( 'woocommerce_attribute_updated', [$this, 'saveCustomAttributes'] );

    #Delete custom attributes
    add_action( 'woocommerce_attribute_deleted',[$this , 'deleteCustomAttributes'] );


    # $args = apply_filters( 'woocommerce_quantity_input_args', wp_parse_args( $args, $defaults ), $product );
    add_filter( 'woocommerce_quantity_input_args', [$this, 'addProduct'], 10, 2 );



    # $passed_validation = apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, $quantity, $variation_id, $variations );

    # Adding validation 
    add_filter( 'woocommerce_add_to_cart_validation', [$this, 'wcAddToCartValidation'], 10, 5 );

    add_action( 'woocommerce_before_add_to_cart_quantity', [$this, 'startQuantityWrapper'] );


    # TODO: Add Security check to display updating by directly changing value in console
    # cho apply_filters( 'woocommerce_cart_item_quantity', $product_quantity, $cart_item_key, $cart_item );
    add_filter( 'woocommerce_cart_item_quantity', [$this, 'manageItemQuantity'], 10, 3  );
  }

  public function manageItemQuantity($product_quantity, $cart_item_key, $cart_item){

    # When reordering then pa_quantity is available otherwise attribute_pa_quantity is available.
    if( empty($cart_item['variation']) || (empty($cart_item['variation']['attribute_pa_quantity']) && empty($cart_item['variation']['pa_quantity']))){ 
      return $product_quantity;
    }

    
    $qnty = $cart_item['quantity'];
    return sprintf( '%d <input type="hidden" name="cart[%s][qty]" value="%d" />', $qnty, $cart_item_key, $qnty);
  }

  public function startQuantityWrapper(){
    global $product;
    if(empty($product)){
      return;
    }
    if( empty($product->get_attribute( 'pa_quantity' ))){
      return;
    }

    echo '<div style="display:none;">';
    add_action( 'woocommerce_after_add_to_cart_quantity', [$this, 'closeQuantityWrapper'] );    
  }

  public function closeQuantityWrapper(){
    echo '</div>';
    remove_action( 'woocommerce_after_add_to_cart_quantity', [$this, 'closeQuantityWrapper'] );
  }

  public function addProduct($args, $product){
    $args['bbProduct'] = $product;
    return $args;
  }

  public function wcAddToCartValidation($validation, $product_id, $quantity, $variation_id = false, $variations = false){
    
    $this->variation_id = $variation_id;

    #We didn't get variation id by the hook used for this function
    #so we have to get variation id from Ajax header request
    # In case of wvs_add_variation_to_cart
    if(!empty($_REQUEST['action'])  && $_REQUEST['action'] === 'wvs_add_variation_to_cart' && !empty($_REQUEST['variation_id'])){
      $this->variation_id = intval($_REQUEST['variation_id']);
    }

    if(!empty($this->variation_id)){
      add_filter('woocommerce_add_to_cart_quantity', [$this, 'cartQuantity'], 10, 2);
    }
    return $validation;
  }

  public function cartQuantity($quantity, $productId){

 
    # Reseting variation info
    $variationID = $this->variation_id;
    $this->variation_id = null;


    # Remove filter hook
    remove_filter('woocommerce_add_to_cart_quantity', [$this, 'cartQuantity'], 10, 2);
    if( empty($variationID)){
      return $quantity;
    }


    try{

      $productData = wc_get_product( $variationID );

      if( empty($productData)){
        return $quantity;
      }
      
      
      $paQuantity = $productData->get_attribute( 'pa_quantity' );

      if( empty($paQuantity)){
        return $quantity;
      }
      return $paQuantity;

    }catch(Exception $e){      
    }

    return $quantity;

  }

  public function addingCustomAttributeField() {
    $id = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;
    $attributeName = $this->getShowAttribute($id);
    $unitAttributeName = $this->getUnitAttribute($id);
    $showAttributeValue = $id ? get_option( $attributeName ) : '';
    $unitValue = $id ? get_option( $unitAttributeName ) : '';

  
    ?>
      <tr class="form-field form-required">
        <th scope="row" valign="top">
          <label for="<?php echo $attributeName; ?>">Display custom swatch units</label>
        </th>
        <td>
          <input 
            name="bb_attribute_show_on_product" 
            id="bb_show_attribute" 
            type="checkbox" 
            value="show" 
            <?php echo(!empty($showAttributeValue)) ? 'checked="checked"' :'';?>>
          <p class="description">Enable this if you want to show unit on variable products.</p>
        </td>
      </tr>

      <div id="bb-custom-unit" style="display: none;">
        <tr class="form-field form-required" id="bb-custom-unit-row" style="display: none;">
          <th scope="row" valign="top">
            <label for="<?php echo $unitAttributeName; ?>">Unit</label>
          </th>
          <td>
            <input 
              name="bb_custom_unit" 
              id="<?php echo $unitAttributeName; ?>" 
              type="text" 
              value="<?php echo esc_attr( $unitValue ); ?>">
          </br></br>
            <!-- <p class="description">Enable this if you want to show unit on variable products.</p> -->
          </td>
        </tr>
      </div>
    <?php
  }

  
  public function saveCustomAttributes( $id ) {

    if ( !is_admin() ) {
      return;
    }

    if( isset( $_POST['bb_attribute_show_on_product'] ) ){
      update_option( $this->getShowAttribute($id), sanitize_text_field( $_POST['bb_attribute_show_on_product'] ) );
    }

    if( isset( $_POST['bb_custom_unit'] ) ){
      update_option( $this->getUnitAttribute($id), sanitize_text_field( $_POST['bb_custom_unit'] ) );
    }

  }


  public function deleteCustomAttributes( $id ) {
    delete_option( $this->getShowAttribute($id));
    delete_option( $this->getUnitAttribute($id) );
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
      self::$instance = new bbCustomAttributes();
    }
    return self::$instance;
  }

}