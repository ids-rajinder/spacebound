<?php
/*
Plugin Name:Custom Customization For Buybulk
Plugin URI: 
Description: Configure the options
Author: IDS
Version: 1.0
Author URI:
*/

ini_set("memory_limit", "-1");

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

define( 'CUSTOM_BBS_PATH', plugin_dir_path( __FILE__ ) );
define( 'CUSTOM_BBS_URL', plugin_dir_url( __FILE__ ) );

class customCustomization{

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

  private $errors = false;

  public function __construct(){
    $this->plugin_path = CUSTOM_BBS_PATH;
    $this->plugin_url  = CUSTOM_BBS_URL;

    if( empty($_GET['dev-user'])){
      add_filter( 'wvs_variable_item', [$this, 'wvs_variable_item_filter'], 10, 5);
    }


    // Show tag name with color in home page
    add_shortcode('colored-tag-name', [$this, 'coloredTagName']);
    add_shortcode('colored-tag-subtabs', [$this, 'subCatcoloredTagName']);
    
    $this->error_reporting();

    #Adding custom css files
    add_action( 'wp_enqueue_scripts', [$this,'addingCssfiles']);
     # Adding frontend scripts
    add_action('wp_head',  [$this,'addCustomJsToRemoveClasses'], 999999);

    add_filter('body_class', [$this, 'addProductMobileClass']);

    add_filter( 'woocommerce_email_order_items_args', [$this, 'add_product_thumbnail_to_wc_emails'],10,1 );

    add_action('pre_get_posts', [$this, 'my_pre_get_posts']);

    #Add the field to the checkout page
    add_action( 'woocommerce_after_checkout_billing_form', [$this,'signatureRequiredCheckoutField']);

    #Update value of field
    add_action( 'woocommerce_checkout_update_order_meta', [$this, 'signatureRequiredUpdateOrderMeta'] );

    #To show signature fields value
    add_action( 'woocommerce_admin_order_data_after_billing_address', [$this, 'showSignatureRequiredField'], 10, 1 );
    add_action( 'woocommerce_order_details_after_order_table', [$this, 'showSignatureRequiredFieldAfterOrder'], 5, 1 );
    
    #To show "E-Transfer" Text after order details
    add_action( 'woocommerce_order_details_after_order_table',[$this, 'showEmtInstructionsAfterOrderTable' ], 10, 1);

  }
  
  public function showEmtInstructionsAfterOrderTable( $order){
    if(!is_plugin_active('woocommerce-email-money-transfer-gateway/gateway-interac.php') || myCredCheckoutMofication()->isPointBasedOrder($order->get_id())){
      return;
    }
    $emtWc = new WC_Gateway_emt;
    if ( is_wc_endpoint_url( 'order-received' ) || !$emtWc->instructions || 'emt' !== $order->get_payment_method() ||
      !( $order->has_status('on-hold') ||  $order->has_status('processing')  || $order->has_status('pending') ) ) {
      return;
    }
    echo wpautop( wptexturize( $emtWc->get_instructions( $order ) ) ) . PHP_EOL;
  }

  #To add option of signature required checkbox
  public function signatureRequiredCheckoutField( $checkout ) {
    woocommerce_form_field(
      'signature_required', 
      array(
        'type'  => 'checkbox',
        'class' => array(
          'my-field-class form-row-wide',
        ),
        'value' => 'Yes',
        'label' => __( '<b>Signature Required ? </b>(Check to enable signature required while delivery of package)' ),
      ), $checkout->get_value( 'signature_required' )
    );
  }

  #To update orde meta for signature required feild
  public function signatureRequiredUpdateOrderMeta( $order_id ) {
    if ( ! empty( $_POST['signature_required'] ) ) {
      update_post_meta( $order_id, 'signature_required', sanitize_text_field( $_POST['signature_required'] ) );
    }
  }

  #Display Signature required field value on the order edit page and after order details
  public function showSignatureRequiredField($order){
    $value = get_post_meta( $order->get_id(), 'signature_required', true );
    $signatureValue = $value === "1" ? "Yes" : "No";
    echo '<p><strong>'.__('Signature Required').':</strong> <br/>' . $signatureValue . '</p>';
  }

  #Display Signature required field after order details
  public function showSignatureRequiredFieldAfterOrder($order){
    if(myCredCheckoutMofication()->isPointBasedOrder($order->get_id())){
      return;
    }
    
    $value = get_post_meta( $order->get_id(), 'signature_required', true );
    $signatureValue = $value === "1" ? "Yes" : "No";
    ?>
      <table style="width:100%" class="cs-signature-required">
        <tbody>
          <tr>
            <td><h3>Signature Required</h3></td>
            <td style="text-align:right"><h3><?php echo $value === "1" ? "Yes" : "No"; ?></h3></td>
          </tr>
        </tbody>
      </table>
    <?php
  }

  function my_pre_get_posts($query) {
    if ($query->is_author() && $query->is_main_query()) {
      $query->set('post_type', 'product');
    }
  }

  #To Resize email images
  public function add_product_thumbnail_to_wc_emails( $args ) {
    $args['show_image'] = true;
    $args['image_size'] = array( 100, 100 );
    return $args;
  }

  #To add custom class for mobile responsive designing
  public function addProductMobileClass($classes){
    $currentPageID = get_queried_object_id();
    if($currentPageID != 3588 && !is_single()){
      $classes[] = 'products-mobile-grid';
    }
    return $classes;
  }
  
  /*To remove extra added classes from products container */
  public function addCustomJsToRemoveClasses(){ ?>
    <script>
      jQuery(document).on({
        mouseenter: function (){
          jQuery('.product-grid-item').removeClass( "add-small-content" );
          jQuery('.woodmart-add-btn').removeClass( "wd-style-icon wd-action-btn wd-add-cart-btn" );
          jQuery('.woodmart-add-btn').tooltip("disable");
        }
      }, ".product-grid-item"); 
    </script>
    <?php
  }

  public function addingCssfiles(){
    $folder = $this->plugin_url.'assests/css';
    $cssVersion = time();

    wp_enqueue_style('ids-custom-products', $folder . '/custom-products.css'. '?' .$cssVersion , array(), '0.1.0', 'all');
    wp_enqueue_style('ids-custom-animations', $folder . '/custom-animations.css'. '?' .$cssVersion , array(), '0.1.0', 'all');
    wp_enqueue_style('ids-custom-style', $folder . '/custom-style.css'. '?' .$cssVersion , array(), '0.1.0', 'all');
    wp_enqueue_style( 'ids-custom-products-style',  $folder . '/custom-products-style.css'. '?' .$cssVersion , array(), '0.1.0', 'all');
  }

  public function coloredTagName($args){
    if(empty($args['slug'])){
      return '';
    }
    $term = get_term_by('slug', $args['slug'], 'product_tag', ARRAY_A);

    if(empty($term)){
      return '';
    }

    $tagColor = get_field('color', $term['taxonomy'] . '_' . $term['term_id'] );
  
    if( empty($tagColor)){
      return $term['name'];
    }

    return "<span class='tab-custom-border-color' style='color:{$tagColor};border-bottom: 1px solid {$tagColor};'>
      {$term['name']}   </span>";
  }

  public function subCatcoloredTagName($args){

    if(empty($args['slug'])){
      return '';
    }

    if(empty($args['name'])){
      return '';
    }

    $term = get_term_by('slug', $args['slug'], 'product_tag', ARRAY_A);

    if(empty($term)){
      return '';
    }

    $tagColor = get_field('color', $term['taxonomy'] . '_' . $term['term_id'] );
  
    if( empty($tagColor)){
      return $term['name'];
    }

    $subCatName = str_replace("_", " ",$args['name']);

    return "<span class='tab-custom-border-color' style='color:{$tagColor};border-bottom: 1px solid {$tagColor};'>
      {$subCatName} </span>";
  }

  function wvs_variable_item_filter($data, $type, $options, $args, $saved_attribute = array() ) {

    $product              = $args['product'];
    $attribute            = $args['attribute'];
    $data                 = '';
    $is_archive           = ( isset( $args['is_archive'] ) && $args['is_archive'] );
    $show_archive_tooltip = (bool) woo_variation_swatches()->get_option( 'show_tooltip_on_archive' );
    $linkable_attribute   = ( (bool) woo_variation_swatches()->get_option( 'linkable_attribute' ) && ( woo_variation_swatches()->get_option( 'trigger_catalog_mode' ) == 'hover' ) );

    $product_url    = $product->get_permalink();
    $attribute_name = wc_variation_attribute_name( $attribute );

    if ( ! empty( $options ) ) {
      $name          = uniqid( wc_variation_attribute_name( $attribute ) );
      $display_count = 0;

      if ( $product && taxonomy_exists( $attribute ) ) {

        $terms       = wc_get_product_terms( $product->get_id(), $attribute, array( 'fields' => 'all' ) );
        $total_terms = count( $terms );
        foreach ( $terms as $term ) {
          if ( in_array( $term->slug, $options, true ) ) {

            $term_id = $term->term_id;

            $type = isset( $saved_attribute['terms'][ $term_id ]['type'] ) ? $saved_attribute['terms'][ $term_id ]['type'] : $type;

            $is_selected             = ( sanitize_title( $args['selected'] ) == $term->slug );
            $selected_class          = $is_selected ? 'selected' : '';
            $screen_reader_html_attr = $is_selected ? ' aria-checked="true"' : ' aria-checked="false"';
            $data_url                = '';
            if ( $linkable_attribute && $is_archive ) {
              $url      = esc_url( add_query_arg( $attribute_name, $term->slug, $product_url ) );
              $data_url = sprintf( ' data-url="%s"', $url );
            }

            // Tooltip
            // from attributes
            $default_tooltip_type = get_term_meta( $term_id, 'show_tooltip', true );
            $default_tooltip_type = empty( $default_tooltip_type ) ? 'text' : $default_tooltip_type;

            // from product attribute
            $default_tooltip_type = ( isset( $saved_attribute['show_tooltip'] ) && ! empty( $saved_attribute['show_tooltip'] ) ) ? $saved_attribute['show_tooltip'] : $default_tooltip_type;

            // from attribute
            $default_tooltip_text = trim( get_term_meta( $term_id, 'tooltip_text', true ) );
            // from attribute fallback
            $default_tooltip_text = empty( $default_tooltip_text ) ? trim( apply_filters( 'wvs_variable_item_tooltip', $term->name, $term, $args ) ) : $default_tooltip_text;

            // from attribute
            $default_tooltip_image = trim( get_term_meta( $term_id, 'tooltip_image', true ) );


            // from product attribute item
            $tooltip_type  = ( isset( $saved_attribute['terms'][ $term_id ] ) && ! empty( $saved_attribute['terms'][ $term_id ]['tooltip_type'] ) ) ? trim( $saved_attribute['terms'][ $term_id ]['tooltip_type'] ) : $default_tooltip_type;
            $tooltip_text  = ( isset( $saved_attribute['terms'][ $term_id ] ) && ! empty( $saved_attribute['terms'][ $term_id ]['tooltip_text'] ) ) ? trim( $saved_attribute['terms'][ $term_id ]['tooltip_text'] ) : $default_tooltip_text;
            $tooltip_image = ( isset( $saved_attribute['terms'][ $term_id ] ) && ! empty( $saved_attribute['terms'][ $term_id ]['tooltip_image'] ) ) ? trim( $saved_attribute['terms'][ $term_id ]['tooltip_image'] ) : $default_tooltip_image;

            // from product attribute item

            if ( isset( $saved_attribute['terms'][ $term_id ] ) && empty( $saved_attribute['terms'][ $term_id ]['tooltip_type'] ) ) {
              $tooltip_type = $default_tooltip_type;
              $tooltip_text = $default_tooltip_text;
            }

            $show_tooltip = ! empty( $tooltip_type ) || $tooltip_type !== 'no';

            if ( $is_archive ) {
              $show_tooltip = $show_archive_tooltip;
            }

            $tooltip_html_attr = '';
            $tooltip_html_attr .= ( $show_tooltip && $tooltip_text && $tooltip_type == 'text' ) ? sprintf( ' data-wvstooltip="%s"', esc_attr( $tooltip_text ) ) : '';

            $tooltip_image_width = absint( woo_variation_swatches()->get_option( 'tooltip_image_width' ) );

            $tooltip_image_size = apply_filters( 'wvs_tooltip_image_size', array(
              $tooltip_image_width,
              $tooltip_image_width
            ) );
            // $tooltip_image_width = apply_filters( 'wvs_tooltip_image_width', sprintf( '%dpx', $tooltip_image_width ) );

            $tooltip_html_image = ( $show_tooltip && $tooltip_type == 'image' && $tooltip_image ) ? wp_get_attachment_image_src( $tooltip_image, $tooltip_image_size ) : false;

            if ( wp_is_mobile() ) {
              $tooltip_html_attr .= ( $show_tooltip ) ? ' tabindex="2"' : '';
            }

            // More...
            if ( $is_archive && wvs_archive_swatches_has_more( $display_count ) ) {
              $data .= wvs_archive_swatches_more( $product->get_id(), $total_terms );
              break;
            }

            if ( ! empty( $tooltip_html_image ) ):
              // $tooltip_html_attr .= sprintf( ' style="--tooltip-background: url(\'%s\'); --tooltip-width: %spx; --tooltip-height: %spx;"', $tooltip_html_image[ 0 ], $tooltip_html_image[ 1 ], $tooltip_html_image[ 2 ] );
              $tooltip_html_attr .= sprintf( ' style="--tooltip-background: url(\'%s\'); --tooltip-width: %spx; --tooltip-height: %spx;"', $tooltip_html_image[0], $tooltip_image_width, $tooltip_image_width );
              $selected_class    .= ' wvs-has-image-tooltip';
            endif;

            $extraClass = "";

            if(is_shop()){
              $extraClass = "variable-item"; 
            }

            $data .= sprintf( '<li %1$s class="variable-item %2$s-variable-item '.$extraClass.' %2$s-variable-item-%3$s %4$s" data-title="%5$s" title="%5$s" data-value="%3$s" role="radio" tabindex="0">
              <div class="">
              <div class="variable-item-contents">', $data_url . $screen_reader_html_attr . $tooltip_html_attr, esc_attr( $type ), esc_attr( $term->slug ), esc_attr( $selected_class ), esc_html( $term->name ) );

            switch ( $type ):
              case 'color':
                $global_color           = sanitize_hex_color( get_term_meta( $term->term_id, 'product_attribute_color', true ) );
                $global_is_dual         = (bool) ( get_term_meta( $term->term_id, 'is_dual_color', true ) === 'yes' );
                $global_secondary_color = sanitize_hex_color( get_term_meta( $term->term_id, 'secondary_color', true ) );

                $color           = ( isset( $saved_attribute['terms'][ $term_id ] ) && ! empty( $saved_attribute['terms'][ $term_id ]['color'] ) ) ? $saved_attribute['terms'][ $term_id ]['color'] : $global_color;
                $is_dual         = ( isset( $saved_attribute['terms'][ $term_id ] ) && isset( $saved_attribute['terms'][ $term_id ]['is_dual_color'] ) && ( $saved_attribute['terms'][ $term_id ]['is_dual_color'] ) === 'yes' ) ? $saved_attribute['terms'][ $term_id ]['is_dual_color'] : $global_is_dual;
                $secondary_color = ( isset( $saved_attribute['terms'][ $term_id ] ) && ! empty( $saved_attribute['terms'][ $term_id ]['secondary_color'] ) ) ? $saved_attribute['terms'][ $term_id ]['secondary_color'] : $global_secondary_color;

                if ( $is_dual ) {
                  $data .= sprintf( '<span class="variable-item-span variable-item-span-%1$s variable-item-span-dual-color" style="background: linear-gradient(-45deg, %2$s 0%%, %2$s 50%%, %3$s 50%%, %3$s 100%%);"></span>', esc_attr( $type ), esc_attr( $secondary_color ), esc_attr( $color ) );
                } else {
                  $data .= sprintf( '<span class="variable-item-span variable-item-span-%s" style="background-color:%s;"></span>', esc_attr( $type ), esc_attr( $color ) );
                }

                break;

              case 'image':

                $global_attachment_id = apply_filters( 'wvs_product_global_attribute_image_id', absint( get_term_meta( $term->term_id, 'product_attribute_image', true ) ), $term, $args );

                $attachment_id = ( isset( $saved_attribute['terms'][ $term_id ] ) && ! empty( $saved_attribute['terms'][ $term_id ]['image_id'] ) ) ? $saved_attribute['terms'][ $term_id ]['image_id'] : $global_attachment_id;

                $global_image_size = woo_variation_swatches()->get_option( 'attribute_image_size' );

                $image_size = ( isset( $saved_attribute['image_size'] ) && ! empty( $saved_attribute['image_size'] ) ) ? $saved_attribute['image_size'] : $global_image_size;

                $image_html = wp_get_attachment_image_src( $attachment_id, apply_filters( 'wvs_product_attribute_image_size', $image_size, $attribute, $product ) );

                $data .= sprintf( '<img aria-hidden="true" alt="%s" src="%s" width="%d" height="%d" />', esc_attr( $term->name ), esc_url( $image_html[0] ), $image_html[1], $image_html[2] );

                break;

              case 'button':
                $data .= sprintf( '<span class="variable-item-span variable-item-span-%s">%s</span>', esc_attr( $type ), esc_html( $term->name ) );
                break;

              case 'radio':
                $id   = uniqid( $term->slug );
                $data .= sprintf( '<input name="%1$s" id="%2$s" class="wvs-radio-variable-item" %3$s  type="radio" value="%4$s" data-value="%4$s" /><label for="%2$s">%5$s</label>', $name, $id, checked( sanitize_title( $args['selected'] ), $term->slug, false ), esc_attr( $term->slug ), esc_html( $term->name ) );
                break;

              default:
                $data .= apply_filters( 'wvs_variable_default_item_content', '', $term, $args );
                break;
            endswitch;

            if ( (bool) woo_variation_swatches()->get_option( 'show_variation_stock_info' ) ) {
              $data .= '<span class="wvs-stock-left-info" data-wvs-stock-info=""></span>';
            }
            $data .= '</div>';
            $data .= '</div>';



            $data .= $this->outputSingleProductVariationInfo($data, $product, $term);

            $data .= '</li>';
            $display_count ++;
          }
        }
      } else {
        // Custom Attributes

        $terms = ! empty( $saved_attribute['terms'] ) ? (array) $saved_attribute['terms'] : array();
        // $total_terms = count( $terms );
        $total_terms = count( $options );
        // foreach ( $terms as $term_id => $term )

        foreach ( $options as $option ) {

          $term_id = trim( $option );
          $term    = $terms[ $option ];

          $type = isset( $term['type'] ) ? $term['type'] : $saved_attribute['type'];

          $is_selected             = ( sanitize_title( $args['selected'] ) == $term_id );
          $selected_class          = $is_selected ? 'selected' : '';
          $screen_reader_html_attr = $is_selected ? ' aria-checked="true"' : ' aria-checked="false"';
          $data_url                = '';
          if ( $linkable_attribute && $is_archive ) {
            $url      = esc_url( add_query_arg( $attribute_name, $term_id, $product_url ) );
            $data_url = sprintf( ' data-url="%s"', $url );
          }
          // Tooltip

          $default_tooltip_type = ( isset( $saved_attribute['show_tooltip'] ) && ! empty( $saved_attribute['show_tooltip'] ) ) ? $saved_attribute['show_tooltip'] : 'text';
          $default_tooltip_text = trim( apply_filters( 'wvs_color_variable_item_tooltip', $term_id, $term, $args ) );

          // from product attribute item
          $tooltip_type = ( isset( $term['tooltip_type'] ) && ! empty( $term['tooltip_type'] ) ) ? trim( $term['tooltip_type'] ) : $default_tooltip_type;
          $tooltip_text = ( isset( $term['tooltip_text'] ) && ! empty( $term['tooltip_text'] ) ) ? trim( $term['tooltip_text'] ) : $default_tooltip_text;

          if ( isset( $term['tooltip_type'] ) && empty( $term['tooltip_type'] ) ) {
            $tooltip_type = $default_tooltip_type;
            $tooltip_text = $default_tooltip_text;
          }

          $tooltip_image = ( isset( $term['tooltip_image'] ) && ! empty( $term['tooltip_image'] ) ) ? trim( $term['tooltip_image'] ) : false;

          $show_tooltip = ! empty( $tooltip_type ) || $tooltip_type !== 'no';

          if ( $is_archive ) {
            $show_tooltip = $show_archive_tooltip;
          }

          $tooltip_html_attr = '';
          $tooltip_html_attr .= ( $show_tooltip && $tooltip_text && $tooltip_type == 'text' ) ? sprintf( ' data-wvstooltip="%s"', esc_attr( $tooltip_text ) ) : '';

          $tooltip_image_width = absint( woo_variation_swatches()->get_option( 'tooltip_image_width' ) );

          $tooltip_image_size = apply_filters( 'wvs_tooltip_image_size', array(
            $tooltip_image_width,
            $tooltip_image_width
          ) );

          $tooltip_html_image = ( $show_tooltip && $tooltip_type == 'image' && $tooltip_image ) ? wp_get_attachment_image_src( $tooltip_image, $tooltip_image_size ) : false;

          if ( wp_is_mobile() ) {
            $tooltip_html_attr .= ( $show_tooltip ) ? ' tabindex="2"' : '';
          }
          
          // More...
          if ( $is_archive && wvs_archive_swatches_has_more( $display_count ) ) {
            $data .= wvs_archive_swatches_more( $product->get_id(), $total_terms );
            break;
          }

          if ( ! empty( $tooltip_html_image ) ):
            $tooltip_html_attr .= sprintf( ' style="--tooltip-background: url(\'%s\'); --tooltip-width: %spx; --tooltip-height: %spx;"', $tooltip_html_image[0], $tooltip_image_width, $tooltip_image_width );
            $selected_class    .= ' wvs-has-image-tooltip';
          endif;

          $data .= sprintf( '<li %1$s class="%2$s-variable-item-%3$s %4$s" data-title="%5$s" title="%5$s" data-value="%5$s"  role="radio" tabindex="0">
            <div class="variable-item %2$s-variable-item">
            <div class="variable-item-contents">', $data_url . $screen_reader_html_attr . $tooltip_html_attr, esc_attr( $type ), sanitize_title( $term_id ), esc_attr( $selected_class ), esc_html( $term_id ) );

          switch ( $type ):
            case 'color':

              $color           = $term['color'];
              $is_dual         = $term['is_dual_color'];
              $secondary_color = $term['secondary_color'];

              if ( $is_dual ) {
                $data .= sprintf( '<span class="variable-item-span variable-item-span-color variable-item-span-dual-color" style="background: linear-gradient(-45deg, %1$s 0%%, %1$s 50%%, %2$s 50%%, %2$s 100%%);"></span>', esc_attr( $secondary_color ), esc_attr( $color ) );
              } else {
                $data .= sprintf( '<span class="variable-item-span variable-item-span-color" style="background-color:%s;"></span>', esc_attr( $color ) );
              }
              break;

            case 'image':

              $attachment_id = $term['image_id'];

              $global_image_size = woo_variation_swatches()->get_option( 'attribute_image_size' );

              $image_size = ( isset( $saved_attribute['image_size'] ) && ! empty( $saved_attribute['image_size'] ) ) ? $saved_attribute['image_size'] : $global_image_size;

              $image_html = wp_get_attachment_image_src( $attachment_id, apply_filters( 'wvs_product_attribute_image_size', $image_size, $attribute, $product ) );

              $data .= sprintf( '<img aria-hidden="true" alt="%s" src="%s" width="%d" height="%d" />', esc_attr( $term_id ), esc_url( $image_html[0] ), $image_html[1], $image_html[2] );

              break;

            case 'button':
              $data .= sprintf( '<span class="variable-item-span variable-item-span-button">%s</span>', esc_html( $term_id ) );
              break;

            case 'radio':
              $id   = uniqid( sanitize_title( $term_id ) );
              $data .= sprintf( '<input name="%1$s" id="%2$s" class="wvs-radio-variable-item" %3$s type="radio" value="%4$s" data-value="%4$s" /><label for="%2$s">%5$s</label>', $name, $id, checked( sanitize_title( $args['selected'] ), $term_id, true ), esc_attr( $term_id ), esc_html( $term_id ) );
              break;

            default:
              $data .= apply_filters( 'wvs_variable_default_item_content', '', $term_id, $args );
              break;
          endswitch;

          if ( (bool) woo_variation_swatches()->get_option( 'show_variation_stock_info' ) ) {
            $data .= '<span class="wvs-stock-left-info" data-wvs-stock-info=""></span>';
          }
          $data .= '</div>';
          $data .= '</div>';
          $data .= '</li>';
          $display_count ++;
        }
        
      }
    }
    return $data;
  }

  // Function to get the client IP address
  function getClientIP() {
    $ipaddress = '';
    if (getenv('HTTP_CLIENT_IP'))
        $ipaddress = getenv('HTTP_CLIENT_IP');
    else if(getenv('HTTP_X_FORWARDED_FOR'))
        $ipaddress = getenv('HTTP_X_FORWARDED_FOR');
    else if(getenv('HTTP_X_FORWARDED'))
        $ipaddress = getenv('HTTP_X_FORWARDED');
    else if(getenv('HTTP_FORWARDED_FOR'))
        $ipaddress = getenv('HTTP_FORWARDED_FOR');
    else if(getenv('HTTP_FORWARDED'))
       $ipaddress = getenv('HTTP_FORWARDED');
    else if(getenv('REMOTE_ADDR'))
        $ipaddress = getenv('REMOTE_ADDR');
    else
        $ipaddress = 'UNKNOWN';
    return $ipaddress;
  }


  function wvs_variable_item( $type, $options, $args, $saved_attribute = array() ) {

    $product              = $args['product'];
    $attribute            = $args['attribute'];
    $data                 = '';
    $is_archive           = ( isset( $args['is_archive'] ) && $args['is_archive'] );
    $show_archive_tooltip = (bool) woo_variation_swatches()->get_option( 'show_tooltip_on_archive' );
    $linkable_attribute   = ( (bool) woo_variation_swatches()->get_option( 'linkable_attribute' ) && ( woo_variation_swatches()->get_option( 'trigger_catalog_mode' ) == 'hover' ) );

    $product_url    = $product->get_permalink();
    $attribute_name = wc_variation_attribute_name( $attribute );

    if ( ! empty( $options ) ) {
      $name          = uniqid( wc_variation_attribute_name( $attribute ) );
      $display_count = 0;
      if ( $product && taxonomy_exists( $attribute ) ) {

        $terms       = wc_get_product_terms( $product->get_id(), $attribute, array( 'fields' => 'all' ) );
        $total_terms = count( $terms );
        foreach ( $terms as $term ) {
          if ( in_array( $term->slug, $options, true ) ) {

            $term_id = $term->term_id;

            $type = isset( $saved_attribute['terms'][ $term_id ]['type'] ) ? $saved_attribute['terms'][ $term_id ]['type'] : $type;

            $is_selected             = ( sanitize_title( $args['selected'] ) == $term->slug );
            $selected_class          = $is_selected ? 'selected' : '';
            $screen_reader_html_attr = $is_selected ? ' aria-checked="true"' : ' aria-checked="false"';
            $data_url                = '';
            if ( $linkable_attribute && $is_archive ) {
              $url      = esc_url( add_query_arg( $attribute_name, $term->slug, $product_url ) );
              $data_url = sprintf( ' data-url="%s"', $url );
            }

            // Tooltip
            // from attributes
            $default_tooltip_type = get_term_meta( $term_id, 'show_tooltip', true );
            $default_tooltip_type = empty( $default_tooltip_type ) ? 'text' : $default_tooltip_type;

            // from product attribute
            $default_tooltip_type = ( isset( $saved_attribute['show_tooltip'] ) && ! empty( $saved_attribute['show_tooltip'] ) ) ? $saved_attribute['show_tooltip'] : $default_tooltip_type;

            // from attribute
            $default_tooltip_text = trim( get_term_meta( $term_id, 'tooltip_text', true ) );
            // from attribute fallback
            $default_tooltip_text = empty( $default_tooltip_text ) ? trim( apply_filters( 'wvs_variable_item_tooltip', $term->name, $term, $args ) ) : $default_tooltip_text;

            // from attribute
            $default_tooltip_image = trim( get_term_meta( $term_id, 'tooltip_image', true ) );


            // from product attribute item
            $tooltip_type  = ( isset( $saved_attribute['terms'][ $term_id ] ) && ! empty( $saved_attribute['terms'][ $term_id ]['tooltip_type'] ) ) ? trim( $saved_attribute['terms'][ $term_id ]['tooltip_type'] ) : $default_tooltip_type;
            $tooltip_text  = ( isset( $saved_attribute['terms'][ $term_id ] ) && ! empty( $saved_attribute['terms'][ $term_id ]['tooltip_text'] ) ) ? trim( $saved_attribute['terms'][ $term_id ]['tooltip_text'] ) : $default_tooltip_text;
            $tooltip_image = ( isset( $saved_attribute['terms'][ $term_id ] ) && ! empty( $saved_attribute['terms'][ $term_id ]['tooltip_image'] ) ) ? trim( $saved_attribute['terms'][ $term_id ]['tooltip_image'] ) : $default_tooltip_image;

            // from product attribute item

            if ( isset( $saved_attribute['terms'][ $term_id ] ) && empty( $saved_attribute['terms'][ $term_id ]['tooltip_type'] ) ) {
              $tooltip_type = $default_tooltip_type;
              $tooltip_text = $default_tooltip_text;
            }

            $show_tooltip = ! empty( $tooltip_type ) || $tooltip_type !== 'no';

            if ( $is_archive ) {
              $show_tooltip = $show_archive_tooltip;
            }

            $tooltip_html_attr = '';
            $tooltip_html_attr .= ( $show_tooltip && $tooltip_text && $tooltip_type == 'text' ) ? sprintf( ' data-wvstooltip="%s"', esc_attr( $tooltip_text ) ) : '';

            $tooltip_image_width = absint( woo_variation_swatches()->get_option( 'tooltip_image_width' ) );

            $tooltip_image_size = apply_filters( 'wvs_tooltip_image_size', array(
              $tooltip_image_width,
              $tooltip_image_width
            ) );

            $tooltip_html_image = ( $show_tooltip && $tooltip_type == 'image' && $tooltip_image ) ? wp_get_attachment_image_src( $tooltip_image, $tooltip_image_size ) : false;

            if ( wp_is_mobile() ) {
              $tooltip_html_attr .= ( $show_tooltip ) ? ' tabindex="2"' : '';
            }

            // More...
            if ( $is_archive && wvs_archive_swatches_has_more( $display_count ) ) {
              $data .= wvs_archive_swatches_more( $product->get_id(), $total_terms );
              break;
            }

            if ( ! empty( $tooltip_html_image ) ):

              $tooltip_html_attr .= sprintf( ' style="--tooltip-background: url(\'%s\'); --tooltip-width: %spx; --tooltip-height: %spx;"', $tooltip_html_image[0], $tooltip_image_width, $tooltip_image_width );
              $selected_class    .= ' wvs-has-image-tooltip';
            endif;


            $data .= sprintf( '<li %1$s class=" %2$s-variable-item-%3$s %4$s" data-title="%5$s" title="%5$s" data-value="%3$s" role="radio" tabindex="0">
              <div class="variable-item %2$s-variable-item">
              <div class="variable-item-contents">', $data_url . $screen_reader_html_attr . $tooltip_html_attr, esc_attr( $type ), esc_attr( $term->slug ), esc_attr( $selected_class ), esc_html( $term->name ) );

            switch ( $type ):
              case 'color':
                $global_color           = sanitize_hex_color( get_term_meta( $term->term_id, 'product_attribute_color', true ) );
                $global_is_dual         = (bool) ( get_term_meta( $term->term_id, 'is_dual_color', true ) === 'yes' );
                $global_secondary_color = sanitize_hex_color( get_term_meta( $term->term_id, 'secondary_color', true ) );

                $color           = ( isset( $saved_attribute['terms'][ $term_id ] ) && ! empty( $saved_attribute['terms'][ $term_id ]['color'] ) ) ? $saved_attribute['terms'][ $term_id ]['color'] : $global_color;
                $is_dual         = ( isset( $saved_attribute['terms'][ $term_id ] ) && isset( $saved_attribute['terms'][ $term_id ]['is_dual_color'] ) && ( $saved_attribute['terms'][ $term_id ]['is_dual_color'] ) === 'yes' ) ? $saved_attribute['terms'][ $term_id ]['is_dual_color'] : $global_is_dual;
                $secondary_color = ( isset( $saved_attribute['terms'][ $term_id ] ) && ! empty( $saved_attribute['terms'][ $term_id ]['secondary_color'] ) ) ? $saved_attribute['terms'][ $term_id ]['secondary_color'] : $global_secondary_color;

                if ( $is_dual ) {
                  $data .= sprintf( '<span class="variable-item-span variable-item-span-%1$s variable-item-span-dual-color" style="background: linear-gradient(-45deg, %2$s 0%%, %2$s 50%%, %3$s 50%%, %3$s 100%%);"></span>', esc_attr( $type ), esc_attr( $secondary_color ), esc_attr( $color ) );
                } else {
                  $data .= sprintf( '<span class="variable-item-span variable-item-span-%s" style="background-color:%s;"></span>', esc_attr( $type ), esc_attr( $color ) );
                }

                break;

              case 'image':

                $global_attachment_id = apply_filters( 'wvs_product_global_attribute_image_id', absint( get_term_meta( $term->term_id, 'product_attribute_image', true ) ), $term, $args );

                $attachment_id = ( isset( $saved_attribute['terms'][ $term_id ] ) && ! empty( $saved_attribute['terms'][ $term_id ]['image_id'] ) ) ? $saved_attribute['terms'][ $term_id ]['image_id'] : $global_attachment_id;

                $global_image_size = woo_variation_swatches()->get_option( 'attribute_image_size' );

                $image_size = ( isset( $saved_attribute['image_size'] ) && ! empty( $saved_attribute['image_size'] ) ) ? $saved_attribute['image_size'] : $global_image_size;

                $image_html = wp_get_attachment_image_src( $attachment_id, apply_filters( 'wvs_product_attribute_image_size', $image_size, $attribute, $product ) );

                $data .= sprintf( '<img aria-hidden="true" alt="%s" src="%s" width="%d" height="%d" />', esc_attr( $term->name ), esc_url( $image_html[0] ), $image_html[1], $image_html[2] );

                break;

              case 'button':
                $data .= sprintf( '<span class="variable-item-span variable-item-span-%s">%s</span>', esc_attr( $type ), esc_html( $term->name ) );
                break;

              case 'radio':
                $id   = uniqid( $term->slug );
                $data .= sprintf( '<input name="%1$s" id="%2$s" class="wvs-radio-variable-item" %3$s  type="radio" value="%4$s" data-value="%4$s" /><label for="%2$s">%5$s</label>', $name, $id, checked( sanitize_title( $args['selected'] ), $term->slug, false ), esc_attr( $term->slug ), esc_html( $term->name ) );
                break;

              default:
                $data .= apply_filters( 'wvs_variable_default_item_content', '', $term, $args );
                break;
            endswitch;

            if ( (bool) woo_variation_swatches()->get_option( 'show_variation_stock_info' ) ) {
              $data .= '<span class="wvs-stock-left-info" data-wvs-stock-info=""></span>';
            }

            $data .= '</div>';
            $data .= '</div>';

            $data .= $this->outputSingleProductVariationInfo($data);

            $data .= '</li>';

            $display_count ++;
          }
        }
      } else {
        // Custom Attributes

        $terms = ! empty( $saved_attribute['terms'] ) ? (array) $saved_attribute['terms'] : array();
        // $total_terms = count( $terms );
        $total_terms = count( $options );
        // foreach ( $terms as $term_id => $term )

        foreach ( $options as $option ) {

          $term_id = trim( $option );
          $term    = $terms[ $option ];

          $type = isset( $term['type'] ) ? $term['type'] : $saved_attribute['type'];

          $is_selected             = ( sanitize_title( $args['selected'] ) == $term_id );
          $selected_class          = $is_selected ? 'selected' : '';
          $screen_reader_html_attr = $is_selected ? ' aria-checked="true"' : ' aria-checked="false"';
          $data_url                = '';
          if ( $linkable_attribute && $is_archive ) {
            $url      = esc_url( add_query_arg( $attribute_name, $term_id, $product_url ) );
            $data_url = sprintf( ' data-url="%s"', $url );
          }
          // Tooltip

          $default_tooltip_type = ( isset( $saved_attribute['show_tooltip'] ) && ! empty( $saved_attribute['show_tooltip'] ) ) ? $saved_attribute['show_tooltip'] : 'text';
          $default_tooltip_text = trim( apply_filters( 'wvs_color_variable_item_tooltip', $term_id, $term, $args ) );

          // from product attribute item
          $tooltip_type = ( isset( $term['tooltip_type'] ) && ! empty( $term['tooltip_type'] ) ) ? trim( $term['tooltip_type'] ) : $default_tooltip_type;
          $tooltip_text = ( isset( $term['tooltip_text'] ) && ! empty( $term['tooltip_text'] ) ) ? trim( $term['tooltip_text'] ) : $default_tooltip_text;

          if ( isset( $term['tooltip_type'] ) && empty( $term['tooltip_type'] ) ) {
            $tooltip_type = $default_tooltip_type;
            $tooltip_text = $default_tooltip_text;
          }

          $tooltip_image = ( isset( $term['tooltip_image'] ) && ! empty( $term['tooltip_image'] ) ) ? trim( $term['tooltip_image'] ) : false;

          $show_tooltip = ! empty( $tooltip_type ) || $tooltip_type !== 'no';

          if ( $is_archive ) {
            $show_tooltip = $show_archive_tooltip;
          }

          $tooltip_html_attr = '';
          $tooltip_html_attr .= ( $show_tooltip && $tooltip_text && $tooltip_type == 'text' ) ? sprintf( ' data-wvstooltip="%s"', esc_attr( $tooltip_text ) ) : '';

          $tooltip_image_width = absint( woo_variation_swatches()->get_option( 'tooltip_image_width' ) );

          $tooltip_image_size = apply_filters( 'wvs_tooltip_image_size', array(
            $tooltip_image_width,
            $tooltip_image_width
          ) );

          $tooltip_html_image = ( $show_tooltip && $tooltip_type == 'image' && $tooltip_image ) ? wp_get_attachment_image_src( $tooltip_image, $tooltip_image_size ) : false;

          if ( wp_is_mobile() ) {
            $tooltip_html_attr .= ( $show_tooltip ) ? ' tabindex="2"' : '';
          }


          // More...
          if ( $is_archive && wvs_archive_swatches_has_more( $display_count ) ) {
            $data .= wvs_archive_swatches_more( $product->get_id(), $total_terms );
            break;
          }

          if ( ! empty( $tooltip_html_image ) ):

            $tooltip_html_attr .= sprintf( ' style="--tooltip-background: url(\'%s\'); --tooltip-width: %spx; --tooltip-height: %spx;"', $tooltip_html_image[0], $tooltip_image_width, $tooltip_image_width );
            $selected_class    .= ' wvs-has-image-tooltip';
          endif;

          $data .= sprintf( '<li %1$s class="%2$s-variable-item-%3$s %4$s" data-title="%5$s" title="%5$s" data-value="%5$s"  role="radio" tabindex="0">
            <div class="variable-item %2$s-variable-item">
            <div class="variable-item-contents">', $data_url . $screen_reader_html_attr . $tooltip_html_attr, esc_attr( $type ), sanitize_title( $term_id ), esc_attr( $selected_class ), esc_html( $term_id ) );

          switch ( $type ):
            case 'color':

              $color           = $term['color'];
              $is_dual         = $term['is_dual_color'];
              $secondary_color = $term['secondary_color'];

              if ( $is_dual ) {
                $data .= sprintf( '<span class="variable-item-span variable-item-span-color variable-item-span-dual-color" style="background: linear-gradient(-45deg, %1$s 0%%, %1$s 50%%, %2$s 50%%, %2$s 100%%);"></span>', esc_attr( $secondary_color ), esc_attr( $color ) );
              } else {
                $data .= sprintf( '<span class="variable-item-span variable-item-span-color" style="background-color:%s;"></span>', esc_attr( $color ) );
              }
              break;

            case 'image':

              $attachment_id = $term['image_id'];

              $global_image_size = woo_variation_swatches()->get_option( 'attribute_image_size' );

              $image_size = ( isset( $saved_attribute['image_size'] ) && ! empty( $saved_attribute['image_size'] ) ) ? $saved_attribute['image_size'] : $global_image_size;

              $image_html = wp_get_attachment_image_src( $attachment_id, apply_filters( 'wvs_product_attribute_image_size', $image_size, $attribute, $product ) );

              $data .= sprintf( '<img aria-hidden="true" alt="%s" src="%s" width="%d" height="%d" />', esc_attr( $term_id ), esc_url( $image_html[0] ), $image_html[1], $image_html[2] );

              break;

            case 'button':
              $data .= sprintf( '<span class="variable-item-span variable-item-span-button">%s</span>', esc_html( $term_id ) );
              break;

            case 'radio':
              $id   = uniqid( sanitize_title( $term_id ) );
              $data .= sprintf( '<input name="%1$s" id="%2$s" class="wvs-radio-variable-item" %3$s type="radio" value="%4$s" data-value="%4$s" /><label for="%2$s">%5$s</label>', $name, $id, checked( sanitize_title( $args['selected'] ), $term_id, true ), esc_attr( $term_id ), esc_html( $term_id ) );
              break;

            default:
              $data .= apply_filters( 'wvs_variable_default_item_content', '', $term_id, $args );
              break;
          endswitch;

          if ( (bool) woo_variation_swatches()->get_option( 'show_variation_stock_info' ) ) {
            $data .= '<span class="wvs-stock-left-info" data-wvs-stock-info=""></span>';
          }
          $data .= '</div>';
          $data .= '</div>';
          $data .= $this->outputSingleProductVariationInfo($data);
          $data .= '</li>';
          $display_count ++;
        }
      }
    }
    return apply_filters( 'wvs_variable_item', $data, $type, $options, $args, $saved_attribute );
  }

  public function isDebug(){
    return $this->getClientIP() === '86.106.143.44' || !empty($_GET['debug-dev']);
  }

  #
  # TODO: Optimise following code, it might slow down once we have lot of variations
  #
  public function outputSingleProductVariationInfo($data, $product, $term){

    if(empty($term)){
      return;
    }

    $termName = $term->name;


    foreach(wc_get_attribute_taxonomies() as $tax ){
      $taxonomy = wc_attribute_taxonomy_name( $tax->attribute_name );
      $showCustomAttribute = $tax->attribute_id ? get_option( "bb_show_attribute-{$tax->attribute_id}" ) : '';

      if($showCustomAttribute != 'show'){
        continue;
      }
      
      if( $term->taxonomy ===  $taxonomy){

        $unit = $tax->attribute_id ? get_option( "bb_custom_attributes_unit-{$tax->attribute_id}" ) : '';
        if( empty($unit) ){
          return;
        }
        break;
      }
    }

    ob_start();

    $variations = $product->get_children();
    $symbol = get_woocommerce_currency_symbol();
    foreach ($variations as $value) {
        $single_variation = new WC_Product_Variation($value);
        $attributes =  $single_variation->get_variation_attributes();
        // attribute_pa_weight
        if( empty($attributes[ 'attribute_' . $taxonomy ]) ){
          continue;
        }

        $price =  $single_variation->price;

        # 2.5G is having slug 2-5-g
        $taxonomyItem = get_term_by('slug', $attributes[ 'attribute_' . $taxonomy ], $taxonomy, ARRAY_A);
        if( empty($taxonomyItem)){
          continue;
        }

        $taxonomyItemName = $taxonomyItem['name'];

        if( strtolower( $termName ) === strtolower( $taxonomyItemName) ){

          $symbol = get_woocommerce_currency_symbol();

          $perGram = $price/ floatval( $termName );
      
          $perGram = wc_price($perGram);
          ?>
          <div class="custom-content">
            <div class="isw-variation-price">
              <span class="woocommerce-Price-amount amount">
                <?php echo wc_price($price); ?>
              </span>
            </div>
            <div class="isw-lead-price">
              <span class="woocommerce-Price-amount amount">
                <!-- <span class="woocommerce-Price-currencySymbol">$</span>6.80 -->
                <?php echo $perGram; ?>
              </span>/<?php echo $unit; ?>
            </div>
          </div>
          <?php

        }
    }
    return ob_get_clean();
  }

  /**
   *  Function Name : error_reporting
   *  Working       : This function is used for php error_reporting.
  */
  public function error_reporting(){
    if( $this->errors === true || isset($_GET['development_mode'])){
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
      self::$instance = new customCustomization();
    }
    return self::$instance;
  }

}

function customCustomization(){
  return customCustomization::get_instance();
}

add_action( 'plugins_loaded', 'customCustomization' );

#
#Including class to override function to prevent ajax calls to load variable products data
function bbInlineVariationLoad(){
  require_once CUSTOM_BBS_PATH . '/bb-inline-variation-load.php';
  return bbInlineVariationLoad::get_instance();
}
add_action( 'plugins_loaded', 'bbInlineVariationLoad' );

#
#Including customm varibale file 
function bbCustomVariables(){
  require_once CUSTOM_BBS_PATH . '/config.php';
  return bbCustomVariables::get_instance();
}
add_action ('plugins_loaded', 'bbCustomMessage');

#
#Including custom svg icons file 
function bbSvgIcons(){
  require_once CUSTOM_BBS_PATH . '/bb-svgIcons.php';
  return bbSvgIcons::get_instance();
}
add_action ('plugins_loaded', 'bbSvgIcons');

#TODO: Add check for theme dependence 
function themeCustomSettingOption(){
  # Adding custom backend option
  require_once CUSTOM_BBS_PATH . 'mycred/theme-custom-settings.php';
  return themeCustomSettingOption::get_instance();
}

add_action ('after_setup_theme', 'themeCustomSettingOption');


# bb-custom-attributes
function bbCustomAttributes(){
  # Adding custom backend option
  require_once CUSTOM_BBS_PATH . '/bb-custom-attributes.php';
  return bbCustomAttributes::get_instance();
}
add_action ('plugins_loaded', 'bbCustomAttributes');

# MyCred modification on points price
function myCredSettingCustomization(){
  # Adding custom backend option
  require_once CUSTOM_BBS_PATH . 'mycred/mycred-setting-customization.php';
  return myCredSettingCustomization::get_instance();
}
add_action ('plugins_loaded', 'myCredSettingCustomization');


function myCredCheckoutMofication(){
  # Include the secondary file here
  require_once CUSTOM_BBS_PATH . 'mycred/my-cred-checkout-modification.php';
  return myCredCheckoutMofication::get_instance();
}

# We are overriding the myCred rewards file, which is also hooked in plugins_loaded
# So making sure that our function is getting called first.
add_action( 'plugins_loaded', 'myCredCheckoutMofication', 9 );


function myCredProfilePageModification(){
  # Include the secondary file here
  require_once CUSTOM_BBS_PATH . 'mycred/my-cred-my-profile-modification.php';
  return myCredProfilePageModification::get_instance();
}

# We aare overriding global varaible
add_action( 'plugins_loaded', 'myCredProfilePageModification', 20 );

# Woocommerce template override
function templateOverride(){
  # Adding custom backend option
  require_once CUSTOM_BBS_PATH . 'woocommerce/template/override-function.php';
  return templateOverride::get_instance();
}
add_action ('plugins_loaded', 'templateOverride');



function handPointBasedItemCheckout(){
  require_once CUSTOM_BBS_PATH . 'mycred/handle-point-based-item-checkout.php';
  return handPointBasedItemCheckout::get_instance();
}

# We aare overriding global varaible
add_action( 'plugins_loaded', 'handPointBasedItemCheckout', 20 );


function raffleDrawStatsCustomization(){
  require_once CUSTOM_BBS_PATH . 'mycred/raffle-winning-stats.php';
  return raffleDrawStatsCustomization::get_instance();
}
add_action ('plugins_loaded', 'raffleDrawStatsCustomization');

# MyCredwoocommerce email modification for point based order
function myCredEmailCustomization(){
  require_once CUSTOM_BBS_PATH . 'mycred/mycred-email-customization.php';
  return myCredEmailCustomization::get_instance();
}
add_action ('plugins_loaded', 'myCredEmailCustomization');

# File to show custom messages to users
function bbCustomMessage(){
  require_once CUSTOM_BBS_PATH . '/bb-custom-messages.php';
  return bbCustomMessage::get_instance();
}
add_action ('plugins_loaded', 'bbCustomMessage');

# File to show custom notifications to users
function bbCustomNotification(){
  require_once CUSTOM_BBS_PATH . 'mycred/custom-notifications-manager.php';
  return bbCustomNotification::get_instance();
}
add_action ('plugins_loaded', 'bbCustomNotification');


# Including file to Customize dokan
function customizingDokan(){
  require_once CUSTOM_BBS_PATH . '/customize-dokan.php';
  return customizingDokan::get_instance();
}
add_action ('plugins_loaded', 'customizingDokan');

#Including file to override theme's functions
require_once CUSTOM_BBS_PATH . 'override-theme-functions.php';

# Including file to Customize single product page
function customizeSingleProductPage(){
  require_once CUSTOM_BBS_PATH . '/customize-single-product-page.php';
  return customizeSingleProductPage::get_instance();
}
add_action ('plugins_loaded', 'customizeSingleProductPage');

require_once CUSTOM_BBS_PATH . 'mycred/override/functions.php';