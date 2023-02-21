<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
class mycred_woo_reward_product {
		
    public function __construct() {
      add_action( 'woocommerce_single_product_summary', array( $this , 'woocommerce_before_add_to_cart_button') );

      add_action( 'woocommerce_order_status_completed',  array( $this , 'mycred_pro_reward_order_percentage' ));

      add_action( 'woocommerce_checkout_before_customer_details',  array( $this , 'woocommerce_review_order_before_order_total' ), 10);

      add_action( 'woocommerce_before_cart_table',  array( $this , 'woocommerce_review_order_before_order_total' ), 10);

      add_filter( 'woocommerce_get_item_data', array( $this ,  'woocommerce_get_item_data'), 10, 2 );	
      
      add_action( 'wp_head', array( $this ,  'wp_head') );	
      
      add_action( 'woocommerce_before_add_to_cart_quantity',  array( $this ,'display_dropdown_variation_add_cart' ));


    }

  
    public function display_dropdown_variation_add_cart() {

      global $product;

      if ( $product->is_type('variable') ) {
        
        ?>
        <script>
        jQuery(document).ready(function($) {
            
          function call_rewards_points(){
              if( '' != jQuery('input.variation_id').val() && 0 != jQuery('input.variation_id').val() ) {
                  var var_id = jQuery('input.variation_id').val();
                  
                  if(typeof(mycred_variable_rewards) != 'undefined' && mycred_variable_rewards != null){
                  
                  total_couunt = Object.keys(mycred_variable_rewards[var_id]).length;
                  
                  }
                  count = 1;
                  template = '';
                  if(typeof(mycred_variable_rewards) != 'undefined' && mycred_variable_rewards != null){	
                  
                  jQuery.each( mycred_variable_rewards[var_id], function( index, value ) {
                  
                  template += '<span class="rewards_span"> Earn ' + value + ' ' + mycred_point_types[index] + '</span>';

                  });

                  document.getElementById("rewards_points_wrap").innerHTML = template;
                  }
              }
          }
          call_rewards_points();			
          jQuery('input.variation_id').change( function(){ 
              call_rewards_points()
          });
          
        });
        </script>
        <?php

      }

    }

    public function wp_head() {
    
      if ( is_product() ) {
          
      $mycred_rewards_array = array();	
          
      $product = wc_get_product( get_the_ID() );
      
      if( $product->is_type( 'variable' ) ) {	
            
      $available_variations = $product->get_available_variations();	
      $mycred = mycred_get_types();
        foreach ($available_variations as $variation) {
          $variation_id = $variation['variation_id'];
          $mycred_rewards = get_post_meta( $variation_id, '_mycred_reward', true ); 
          if (!empty($mycred_rewards )){
          $mycred_rewards_array[$variation_id] = $mycred_rewards;
          }
        }
      }
          
      if ( !empty($mycred_rewards_array ) ) { 
        ?>
        <script type="text/javascript">
          var mycred_variable_rewards = <?php echo json_encode( $mycred_rewards_array ); ?>;
          var mycred_point_types = <?php echo json_encode( $mycred ); ?>;
        </script>
        <?php 
        }
      }
        
    }
    
    public function woocommerce_get_item_data( $item_data, $cart_item ) {
        
        $product = wc_get_product( $cart_item['product_id'] );
        if( $product->is_type( 'variable' ) ) {
            $mycred_rewards = get_post_meta( $cart_item['variation_id'], '_mycred_reward', true ); 
        } else {
            $mycred_rewards = get_post_meta( $cart_item['product_id'], 'mycred_reward', true ); 
        }
         
        if($mycred_rewards){

            if ( (is_cart() && 'yes'==get_option('reward_cart_product_meta')) || (is_checkout() && 'yes'==get_option('reward_checkout_product_meta')) ) {
                foreach( $mycred_rewards as $mycred_reward_key => $mycred_reward_value ) {	
                    $value = '<span class="reward_span">'. $mycred_reward_value .' ' .mycred_get_point_type_name($mycred_reward_key) .'</span>'	;

                    $item_data[] = array(
                        'key'     => '<span style="reward_span">Earn</span>',
                        'value'   => __( $value, 'mycredpartwoo' ),
                        'display' => '',
                    );

                }
            } 

        }

        return $item_data;
    }

    public function woocommerce_review_order_before_order_total() {  

        do_action( 'woocommerce_set_cart_cookies',  true );

            
        $total_reward_point = array();
        $message = '';
        
        foreach ( WC()->cart->get_cart() as $cart_item ) {
            // var_dump($cart_item);
            
            
        $product = wc_get_product( $cart_item['product_id'] );
        if( $product->is_type( 'variable' ) ) {
            $mycred_rewards = get_post_meta( $cart_item['variation_id'], '_mycred_reward', true ); 
        } else {
            $mycred_rewards = get_post_meta( $cart_item['product_id'], 'mycred_reward', true ); 
        }
            if($mycred_rewards){
                
                foreach( $mycred_rewards as $mycred_reward_key => $mycred_reward_value ){ 
                    
                    if (isset($total_reward_point[$mycred_reward_key])) {
                        
                        $total_reward_point[$mycred_reward_key]['total'] = $total_reward_point[$mycred_reward_key]['total'] + $mycred_reward_value * $cart_item['quantity'];
                        
                    }else{
                        
                        $total_reward_point[$mycred_reward_key] = array( 'name' => $mycred_reward_key ,'total' => $mycred_reward_value * $cart_item['quantity']);
                    }
                }
            }	
        }

        $message .= __( "Earn ", 'mycredpartwoo' );
        $i = 1;
        $count = count($total_reward_point);
        
        //print_r($total_reward_point);
        //wp_die();
        
        if ( ! empty($total_reward_point) ) {
            foreach( $total_reward_point as $mycred_reward_key => $mycred_reward_value ){
            
                $mycred_point_type_name = mycred_get_point_type_name( $mycred_reward_key );
                $mycred = mycred( $mycred_reward_key );

                if(1==$count) {
                    $message .= $mycred->format_creds( $mycred_reward_value['total'] ) .' '. $mycred_point_type_name;
                }else {
                    if($i<$count) {
                        $message .= $mycred->format_creds( $mycred_reward_value['total'] ) .' '. $mycred_point_type_name .', ';
                    } else {
                        $message .= ' and ' . $mycred->format_creds( $mycred_reward_value['total'] ) .' '. $mycred_point_type_name;
                    }
                }

                $i++;
                
            }
        }
                
        wc_clear_notices();

        $reward_points_global = get_option('reward_points_global', true);

        //wp_die(WC()->cart->get_subtotal());

        if ( 'yes'===$reward_points_global ) {
            /*** mufaddal start work from here */
            $type = get_option('mycred_point_type', true);
            $reward_points_global_type = get_option('reward_points_global_type', true);
            $exchange_rate = get_option('reward_points_exchange_rate', true);
            $reward_points_global_message = get_option('reward_points_global_message', true);
            $reward_points_global_type_val = get_option('reward_points_global_type_val', true);
            $reward_points_global_type_val = (float) $reward_points_global_type_val;
            $cost = WC()->cart->get_subtotal();

            # User ID default to logged in user ID
            $userID = get_current_user_id();
            if( empty($userID)){
              return;
            }
            $rankID = myCredCheckoutMofication()->getUserRank($userID);

            # User doesn't have any rank associated with me
            if( empty($rankID) ){
              return;
            }

            $pointMultipler = get_post_meta( $rankID, 'points_multipler', true);
            $pointMultipler = floatval($pointMultipler);
            #Getting applied coupons
            $discountCouponArray = WC()->cart->coupon_discount_totals;
            $discountTotal=0;
            foreach($discountCouponArray as $value){
              $discountTotal += $value;
            }
            #Deducting points when discount coupons applied multiple with pointMultipler based on rank 
            // In case double XP weekend is activated
            $extraPointsMultiple = 1;
            if( !empty(woodmart_get_opt( 'weekend_double_xp' )) ){
                $extraPointsMultiple = 2;
            }
            $pointsDeduction = intval(ceil($discountTotal * $pointMultipler * $extraPointsMultiple));
            // $pointsDeduction = intval(ceil($discountTotal));
 
            if ('fixed'===$reward_points_global_type) {

                $reward = number_format($reward_points_global_type_val, 2, '.', '');

            }

            if ('percentage'===$reward_points_global_type) {

                $reward = $cost * ( $reward_points_global_type_val / 100 );
                $reward = number_format($reward, 2, '.', '');

            }

            if ('exchange'===$reward_points_global_type) {
                
                $reward = ( $cost/$exchange_rate );
                $reward = number_format($reward, 2, '.', '');

            }
            
            
            # Adding reward multiple which is saved in post meta
            # In case we get a empty value then not showing the error
            $reward = $this->multiplePointsBasedOnRank($reward);
            if(empty( floatval($reward))){
                return;
            }

            $reward = intval($reward-$pointsDeduction);
            
            $message = str_replace("{points}", $reward, $reward_points_global_message);
            $message = str_replace("{type}", $type, $message);
            $message = str_replace("mycred_default", "Points", $message);
            if ($cost > 0 && !empty($reward_points_global_message)) {
              # In case use closed the notification, then it shouldn't appear next time
              if(!bbCustomNotification()->isNotifictionTurnedOf('myCredPoints')){
                wc_print_notice( __( $message, 'mycredpartwoo' ) ,  $notice_type = 'notice' , [
                  '__idsNoticeType' => 'myCredPoints'
                ]); 
              }
            }				

        } else {

            if ( (is_cart() && 'yes'==get_option('reward_cart_product_total')) || (is_checkout() && 'yes'==get_option('reward_checkout_product_total')) ) {
                if ( ! empty($total_reward_point) ) {
                    wc_print_notice(  __( $message, 'mycredpartwoo' ) ,  $notice_type = 'notice' ); 
                }
            }
        }
    }

    #
    # Multiple points based on the one configured in the rank meta
    #
    public function multiplePointsBasedOnRank($currentPoints, $userID = false){

        # User ID default to logged in user ID
        if(empty($userID)){
            $userID = get_current_user_id();
            if( empty($userID)){
                return $currentPoints;
            }
        }

        $rankID = myCredCheckoutMofication()->getUserRank($userID);

        // 
        # User doesn't have any rank associated with me
        if( empty($rankID) ){
            return $currentPoints;
        }

        $pointMultipler = get_post_meta( $rankID, 'points_multipler', true);
        $pointMultipler = floatval($pointMultipler);
        $currentPoints  = intval($currentPoints * $pointMultipler);

        // In case double XP weekend is activated
        if( !empty(woodmart_get_opt( 'weekend_double_xp' )) ){
            $currentPoints = 2 * $currentPoints;
        }
        return number_format($currentPoints, 2, '.', '');
    }
    
    public function woocommerce_before_add_to_cart_button(){
        
        $product = wc_get_product( get_the_ID() );
        
        
             
        if( get_option( 'reward_single_page_product' ) == 'yes' ) {
            if( $product->is_type( 'simple' ) ) {		
            $mycred_rewards = get_post_meta( get_the_ID(), 'mycred_reward', true );
                
                $i = 1;

                if(!empty($mycred_rewards)) {
                    $count = count($mycred_rewards);
                }

                if($mycred_rewards){

                    echo '<div id="rewards_points_wrap">';
                    foreach($mycred_rewards as $mycred_reward_key => $mycred_reward_value) {
                        
                        $mycred_point_type_name = mycred_get_point_type_name($mycred_reward_key);
                        
                        echo '<span class="rewards_span"> ' . __( 'Earn ' . $mycred_reward_value . ' ' . $mycred_point_type_name  , 'mycredpartwoo' ) . '</span>';
                    }
                    echo'</div>';
                }
                
            } else {
                echo '<div id="rewards_points_wrap"></div>';
            }
        }
    
        
    }
    
    public function mycred_pro_reward_order_percentage( $order_id ) {

        # In case of point based product not allowing rewards to be added
        # TODO: Find why this is happening.

        if( myCredCheckoutMofication()->isPointBasedOrder($order_id) ){
            return;
        }

        # In case of sub-order don't create the ticket
        $post = get_post( $order_id );
        if($post->post_parent !== 0){
          return;
        }        


        
        $reward_points_global = get_option('reward_points_global', true);

        if ( 'yes'===$reward_points_global ) {
            //wp_die('pls stop');
            $reward_points_global_type = get_option('reward_points_global_type', true);
            $reward_points_global_type_val = get_option('reward_points_global_type_val', true);
            $exchange_rate = get_option('reward_points_exchange_rate', true);
            $reward_points_global_message = get_option('reward_points_global_message', true);
            $type = get_option('mycred_point_type', true);
        }

        if ( ! function_exists( 'mycred' ) ) return;

        // Get Order
        $order   = new WC_Order( $order_id );
        $cost    = $order->get_subtotal();
        $user_id = get_post_meta($order_id, '_customer_user', true);
        $payment_method = get_post_meta( $order_id, '_payment_method', true );

        // Do not payout if order was paid using points
        if ( $payment_method == 'mycred' ) return;
        
        // Load myCRED
        $mycred = mycred();

        // Make sure user only gets points once per order
        if ( $mycred->has_entry( 'reward', $order_id, $user_id ) ) return;

        // percentage based point
        if ( isset($reward_points_global_type) && 'percentage'===$reward_points_global_type ) {
            
            // Reward example 25% in points.
            $points = (float) $reward_points_global_type_val;
            $reward  = $cost * ( $points / 100 );
            $reward = number_format($reward, 2, '.', '');

        } 

        // fixed point
        if ( isset($reward_points_global_type) && 'fixed'===$reward_points_global_type ) {
            
            // Reward example 25% in points.
            $points = (float) $reward_points_global_type_val;
            $reward = number_format($points, 2, '.', '');

        }

        // exchange rate based points
        if ( isset($reward_points_global_type) && 'exchange'===$reward_points_global_type ) {
            
            // Reward example 25% in points.
            $points = (float) $exchange_rate;
            $reward  = ($cost/$points);
            $reward = number_format($reward, 2, '.', '');
            //wp_die('rewards in exchange rate '. $reward);

        }

        # Adding reward multiple which is saved in post meta
        # In case we get a empty value then not showing the error
        $reward = $this->multiplePointsBasedOnRank($reward, $user_id);
        if(!empty( floatval($reward))){
          // Add reward
          // Ugly hack
          $this->tempUserId = $user_id;
          add_filter('bb_mycred_payment_modification', [$this, 'correctMycredDiscountScore']);
          $mycred->add_creds('reward', $user_id, $reward, 'Reward for store purchase', $order_id, array( 'ref_type' => 'post' ), $type );
          remove_filter('bb_mycred_payment_modification', [$this, 'correctMycredDiscountScore']);
        }

        if ( 'yes'===$reward_points_global ) {				
            add_filter('mycred_exclude_user', array($this, 'stop_points_for_single_product'), 10, 3);				
        }

    }

    public function correctMycredDiscountScore($paramArray){
      if( !empty($paramArray['exchange'])){

        # We have used intval inside multiplePointsBasedOnRank.
        $reward = $this->multiplePointsBasedOnRank(10, $this->tempUserId) / 10;
        $paramArray['exchange'] = 1 / $reward;
      }
      return $paramArray;
    }

    public function stop_points_for_single_product( $false, $user_id, $obj) {
        return true;
    }
    
}

$mycred_woo_reward_product = new mycred_woo_reward_product();