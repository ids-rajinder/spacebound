<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
class customizeSingleProductPage{

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
    add_action( 'woocommerce_single_product_summary' , [$this, 'addEditSummarySingleProductPage'],6);
    add_action( 'woocommerce_single_product_summary' , [$this, 'addPriceLable'], 7);
    add_action( 'woocommerce_single_product_summary' , [$this, 'showShortDiscription'], 12);
    add_action( 'woocommerce_single_product_summary' , [$this, 'relatedProducts'], 50);
    add_action( 'woocommerce_single_product_summary', [$this, 'showDealsPromo'], 35);  
    add_action( 'woocommerce_after_single_product', [$this, 'displayRatedBestFeaturedTopProducts']);

    #Filter hooks
    add_filter( 'woocommerce_before_single_product_summary', [$this, 'socialShareButtons'], 100 );
    add_filter( 'woocommerce_product_tabs', [$this, 'customizeSingleProductSummaryTabs'], 98);
    add_filter( 'woocommerce_sale_flash', [$this, 'showTotalDiscountBadge'], 15, 3 );
    add_action( 'woocommerce_single_product_summary' , [$this, 'addTextAfterAddToCart'], 1);

  }

  #To show tagged products on footer 
  function displayRatedBestFeaturedTopProducts(){
    echo do_shortcode('[elementor-template id="16309"]');
  }

  function addTextAfterAddToCart(){
    global $product;
    if( $product->get_type() === 'composite'){
      add_filter( 'woocommerce_composite_add_to_cart_button', [$this, 'addClassAfterAddToCartBtn'] ); 
      return;
    }
    add_filter( 'woocommerce_after_add_to_cart_button', [$this, 'addClassAfterAddToCartBtn'] ); 
  }

  function addClassAfterAddToCartBtn() {
    if($this->isPointBasedProduct()){
      echo '<span class="bb_single_page_point_based_product"></span>';
    }
    echo $this->addStockStatus();
   return; 
  }

  #Checking if point based product
  function isPointBasedProduct(){
    $productID = get_the_ID();
    $isPointBased = get_post_meta($productID, 'purchasable_by_points_only', true) === 'yes' ? true : false;
    return $isPointBased;
  }

  #To show discount badges on the products
  public function showTotalDiscountBadge( $html, $post, $product ) {
    $extrText = '';
    if( $product->is_type('variable')){
      $discountedPriceArray = [];
      $extrText = 'upto';
      // Get all variation prices
      $prices = $product->get_variation_prices();
      // Loop through variation prices
      foreach( $prices['price'] as $key => $price ){
        // Only on sale variations
        if( $prices['regular_price'][$key] !== $price ){
            // Calculate and set in the array the percentage for each variation on sale
            $discountedPriceArray[] = floatval($prices['regular_price'][$key]) - floatval($prices['sale_price'][$key]);
        }
      }
      $discountedPrice = 0;
      // We keep the highest value
      if(!empty($discountedPriceArray)){
        $discountedPrice = max($discountedPriceArray);
      }
    }elseif( $product->is_type('grouped') ){
      $discountedPriceArray = [];
      // Get all variation prices
      $children_ids = $product->get_children();

      // Loop through variation prices
      foreach( $children_ids as $child_id ){
          $child_product = wc_get_product($child_id);
          $regular_price = 0;
          $sale_price = 0;
          if(!empty($child_product)){
            $regular_price = (float) $child_product->get_regular_price();
            $sale_price    = (float) $child_product->get_sale_price();
          }
          if ( $sale_price != 0 || ! empty($sale_price) ) {
            // Calculate and set in the array the percentage for each child on sale
            $discountedPriceArray[] = $regular_price - $sale_price;
          }
      }
      $discountedPrice = 0;
      // We keep the highest value
      if(!empty($discountedPriceArray)){
        $discountedPrice = max($percentages);
      }
    } else {
      $regular_price = (float) $product->get_regular_price();
      $sale_price    = (float) $product->get_sale_price();

      if ( $sale_price != 0 || ! empty($sale_price) ) {
        $discountedPrice    = $regular_price - $sale_price ;
      } else {
        return $html;
      }
    }
    if( $discountedPrice > 0){
      return 
        '<div class="product-labels labels-rectangular custom_badges_single_product">
          <span class="onsale product-label">' . esc_html__( 'SAVE '.$extrText, 'woocommerce' ). '  
          '.wc_price($discountedPrice) . '
          </span>
        </div>';
    }else{
      return $html;
    }
  }

  #To show stock status
  public function addStockStatus(){
    global $product;
    $stcokStatus = $product->get_stock_status();
    return '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<div class="single_product_stock_status"><span>'.$stcokStatus.'</span></div>';
  }

  #To show deals promo banner
  public function showDealsPromo(){
    echo do_shortcode('[elementor-template id="15986"]');
  }

  #To show social sharing buttons and realted tags.
  public function socialShareButtons() {
    ob_start();
    #Shortcode to show social buttons
    echo do_shortcode('[elementor-template id="15949"]'); 

    #To show related tags
    global $product;
    $tagsArray = $product->tag_ids;
    $productTag = [];
    if(!empty($tagsArray)){
      foreach($tagsArray as $tagID){
        $tagTerm =  get_term($tagID);
        $tagUrl  = get_tag_link($tagTerm->term_id);
        $productTag[] =  '<a href="'.$tagUrl.'">'.$tagTerm->name.' </a>';
      }
    }
    if(!empty($productTag)){
      ?>
      <span class="single-page-tags"><b>Tags: </b> <?php echo implode(',', $productTag); ?></span>
      <?php
    }
    echo ob_get_clean();
  }

  #Modifying elementor's tabs settings to show related products under tabs on single product page
  public function modifyTabsParameters($settings){
    global $product, $woocommerce_loop;
    $currentTags = get_the_terms( get_the_ID(), 'product_tag' );
    if(empty($currentTags)){
      return false;
    }
    # In case no tag is present we get false
    if(!$currentTags){
      $currentTags = [];
    }
    $newTabItems = [];
    $tabsItems = count($settings['tabs_items']);
    $maxLoop = min(count($currentTags), $tabsItems);
    for($i = 0; $i < $maxLoop; $i++){
      $newTabItem['taxonomies'] = $currentTags[$i]->term_id;
      $newTabItem['title'] = $currentTags[$i]->name;
      $newTabItem['exclude'][] = get_the_ID();
      $newTabItems[] = $newTabItem;
    }
    $settings['tabs_items'] = $newTabItems;
    return $settings;
  }

  #Adding related products shortcode
  public function relatedProducts() {
    $result = add_filter('bb_woodmart_elementor_products_tabs_template_settings', [$this, 'modifyTabsParameters']);
    if($result !== false){ 
      if($this->isPointBasedProduct()){
        ?>
        <div class="bb_point_based_related_product_slider">
        <?php
      }
      echo do_shortcode('[elementor-template id="16055"]');
      if($this->isPointBasedProduct()){
        ?>
        </div>
        <?php
      }
    }
    return;
  }

 #Removinh unnecessary tabs and adding new on single product page.
  public function customizeSingleProductSummaryTabs( $tabs ) {
    // 1) Removing tabs
    unset( $tabs['shipping'] );     
    unset( $tabs['more_seller_product'] ); 
    unset( $tabs['seller'] );  
    unset( $tabs['woodmart_additional_tab'] ); 
    unset( $tabs['additional_information'] ); 

    //_specifications
    $tabs['_specifications'] = array(
        'title'     => __( 'Specification', 'woocommerce' ),
        'priority'  => 10,
        'callback'  => [$this, 'productSpecification'],
    );

    #To use in future
    // Adds the qty pricing  tab
    // $tabs['_refer_friend'] = array(
    //     'title'     => __( 'Refer a  friend', 'woocommerce' ),
    //     'priority'  => 110,
    //     'callback'  => [$this, 'referFriend']
    // );
    return $tabs;
  }

  #Single product info
  public function productSpecification() {
    ob_start();
    $flavours = $this->getProductInfo()['flavours'];
    $feelings = $this->getProductInfo()['feelings'];
    $flavours = !empty($flavours) ? implode(', ' , array_column($flavours, 'name')) : '';
    $feelings = !empty($feelings) ? implode(', ' , array_column($feelings, 'name')) : '';
    ?>
    <table class="custom-single-product-specifications">
      <tbody>
        <tr>
          <th>NAME  <span class="tooltip-icon wd-add-img-msg"><span class="tooltip-hover">Product Name</span></span></th>
          <td><?php echo$this->getProductInfo()['product_title']; ?></td>
        </tr>
        <?php if(!empty($flavours)){ ?>
          <tr>
            <th>FLAVOURS</th>
            <td><?php echo $flavours; ?></td>
          </tr>
        <?php } ?>
        <?php if(!empty($feelings)){ ?>
          <tr>
            <th>FEELINGS</th>
            <td><?php echo $feelings; ?></td>
          </tr>
        <?php } ?>
      </tbody>
    </table>
    <?php
    echo ob_get_clean();
  }

  #Collecting product all info one funtion
  public function getProductInfo(){
    global $product;
    $data['product_id'] = $product->get_id();
    $data['product_title'] = $product->get_title();
    $data['price'] = $product->get_regular_price();
    $data['sale_price'] = $product->get_sale_price();
    $data['product_cat'] =  $this->getTopLevelCat($product->get_id());
    $data['product_tags'] =   $product->tag_ids;
    $data['product_discription'] =   $product->get_description();
    $data['flavours'] = get_field('_flavours'); 
    $data['feelings'] = get_field('_feelings'); 
    $data['_flavour_icon'] = get_field('_flavour_icon'); 
    $data['_thc'] = get_field('_thc'); 
    return $data;
  }

  #Getting parent catagory of product
  function getTopLevelCat ( $product_id ) {
    $product_category = wp_get_post_terms( $product_id, 'product_cat' );
    $catagoryName = 'N/A';
    if(!empty($product_category)){
      foreach( $product_category as $cat ){
        if( 0 === $cat->parent ){
          $parentCatName =  $cat->name;
          $link = get_term_link( $cat->term_id, 'product_cat' );
        }else{
          $parentcats = get_term($cat->parent, 'product_cat');
          $parentCatName =  $parentcats->name;
          $link = get_term_link( $parentcats->term_id, 'product_cat' );
        }
        break;
      }
      $catagoryName = '<a href="'.$link.'">'.$parentCatName.'</a>';
    }
    return $catagoryName;
  }

  #To add edit summary on single product page
  public function addEditSummarySingleProductPage(){
    ob_start();
    $lable = isset($lable['lable']) && !empty($lable['lable']) ? $lable['lable'] : 'By';
    $seller_id = get_post_field( 'post_author', $this->getProductInfo()['product_id']);
    $author  = get_user_by( 'id', $seller_id );
    $shopUrl = esc_url(get_author_posts_url(get_the_author_meta("ID")));

    if(is_plugin_active('dokan-pro/dokan-pro.php')){
      $vendor	= dokan()->vendor->get($seller_id);
      $store_info = dokan_get_store_info( $seller_id );
      $shopUrl = $vendor->get_shop_url();
    }
    $productCatgories = $this->getProductInfo()['product_cat'];
    $tagsArray = $this->getProductInfo()['product_tags'];
    $productTag = '';
    $productType =  !empty($productTag) ? $productTag : $productCatgories;
    $productType = !empty($productType) ? explode(',', $productType)[0] : 'N/A';

    if ( !empty( $store_info['store_name'] ) ) { 
      $storeName = '<a href= "'.$shopUrl.'">'.$vendor->get_shop_name().'</a>';
    }elseif(!empty($shopUrl)){
      $storeName = '<a href= "'.$shopUrl.'">'.$author->display_name.'</a>';
    }else{
      $storeName = 'N/A';
    } ?>
    <span class="store_name_details"><b>By: </b> <?php echo $storeName; ?></span> <span class="singal-page-divider"></span> <span class="single-products-cat"><b>Type: </b> <?php echo $productType; ?></span><br>

    <?php 
    echo ob_get_clean();
  }

  #To add single product price lable
  public function addPriceLable(){
    global $product;
    if( !is_product()){
      return;
    }
   $salePrice =  $this->getProductInfo()['sale_price'];
   $price = empty($salePrice) ? '<b>Price</b' : '<b class="regular_price_lable">WAS</b> <b class="sale_price_lable">DEAL PRICE</b>';
    remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_rating', 10 );

    $rating_count   = $product->get_rating_count();
    $review_count   = $product->get_review_count();
    $average        = $product->get_average_rating();
    $suffixText     = $review_count > 1 ? 's' : '';

    ob_start(); 
    if(!empty($review_count)){
      ?>
      <a href="#reviews" class="woocommerce-review-link" rel="nofollow">
        <span class="bb-star-rating" >
          <?php echo wc_get_rating_html( $average, $rating_count ) ."<b>({$review_count}) Review{$suffixText} </b>"; ?>
        </span>
      </a>
      <?php 
    } ?>
    <div class ="single-product-summary">
      <span class ="single-product-price-lable"><?php echo $price; ?></span>
    <div>
    <?php
    echo ob_get_clean(); 
  }

  #To add single product short discription
  public function showShortDiscription(){
    if( !is_product()){
      return;
    }
    remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_excerpt', 20 );
    $productInfo = $this->getProductInfo();
    global $post;
    ob_start(); 
    ?>
    <span class ="single-product-short-description">
      <?php  if(!empty ( $post->post_excerpt  ) ){ ?>
        <b>WHY IT’S SPECIAL</b>
        <?php echo do_shortcode('[elementor-template id="15936"]'); 
        if(!empty($this->getProductInfo()['product_discription'])){
          ?>
          <a href="#tab-description">Learn more</a>
          <?php 
        }
      }
      $theBasicThc = $productInfo['_thc']; 
      ?>
      <?php if((!empty($theBasicThc) && ($theBasicThc->name != 'Select' )) || !empty($productInfo['flavours']) || !empty($productInfo['feelings'])){ ?>
        <div class="products-thc-wrapper">
          <?php if(!empty( $productInfo['_thc'])){ ?>
            <b>THE BASIC</b>
            <div class="products-thc-items mb-8">
              <?php 
                $theBasicIcon = get_field('_specification_icons', $theBasicThc->taxonomy . '_' . $theBasicThc->term_id);
                ?>
                <span class="product-strain-<?php echo $theBasicIcon; ?>">THC <?php echo $theBasicThc->name; ?></span>
            </div>
          <?php } ?>
          <?php if(!empty( $productInfo['flavours'])){
              $flavouArray = bbSvgIcons()->svgIconHtml($productInfo['flavours']);
            ?>
            <b>FLAVOUR & AROMA</b>
            <div class="products-thc-items">
              <?php foreach($flavouArray as  $flavourLable => $flavourIcon){ 
                //$flavourIcon = get_field('_specification_icons', $flavour->taxonomy . '_' . $flavour->term_id);
                ?>
                <span class="product-strain-speciality"><?php echo $flavourIcon; ?><span><?php echo $flavourLable; ?></span></span>
              <?php } ?>
            </div>
          <?php } ?>
          <?php if(!empty( $productInfo['feelings'])){ 
            $feelingsArray = bbSvgIcons()->svgIconHtml($productInfo['feelings']);
          ?>
            <b>FEELINGS</b>
            <div class="products-thc-items">
              <?php foreach($feelingsArray as $feelingsLable => $feelingsIcon){ 
                //$flavourIcon = get_field('_specification_icons', $feelings->taxonomy . '_' . $feelings->term_id);
                ?>
                <span class="product-strain-speciality"><?php echo $feelingsIcon; ?><span><?php echo $feelingsLable; ?></span></span>
              <?php } ?>
            </div>
          <?php } ?>
        </div>
      <?php } ?>
    </span>
    <?php
    echo ob_get_clean(); 
  }

  public function showRewardProducts(){
    if ( function_exists( 'mycred_get_types' ) ) {
      $product_id = 1;
      $types      = mycred_get_types();
      $reward     = (array) get_post_meta( $product_id, 'mycred_reward', true );
      if ( ! empty( $reward ) ) {
        echo 'Purchase reward:
    ';
        foreach ( $reward as $point_type => $amount ) {
          if ( $amount != '' && array_key_exists( $point_type, $types ) )
            printf( '%s %s
    ', $amount, $types[ $point_type ] );
        }
      }
    }    
  }

  /* Get a unique instance of this object.
  *
  * @return object
  */
  public static function get_instance() {
    if ( null === self::$instance ) {
      self::$instance = new customizeSingleProductPage();
    }
    return self::$instance;
  }


}
