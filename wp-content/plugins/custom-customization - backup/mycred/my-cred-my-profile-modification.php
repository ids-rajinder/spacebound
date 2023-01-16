<?php


if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class myCredProfilePageModification{

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

  private $acfDateFormat = 'Ymd';
  
  // private $defaultRankID = '6010';
  
  public function __construct(){
    $this->plugin_path = CUSTOM_BBS_PATH;
    $this->plugin_url  = CUSTOM_BBS_URL;

    add_action( 'woocommerce_account_my-coupons_endpoint', array( $this , 'beforeCouponCodeRender'), 1 ); 
    add_action( 'woocommerce_account_my-coupons_endpoint', array( $this , 'afterCouponCodeRender'), 99 ); 


    # After WC order is completed, creating the coupons
    add_action( 'woocommerce_order_status_completed', [$this, 'createPointBasedItemsOnceOrderIsCompleted'] );
    add_action( 'woocommerce_order_status_completed', [$this, 'createRandomTicket'] );

    #Adding shortcode to show total spending in real money on my account page
    add_shortcode( 'my_total_spendings', [$this,'totalSpending']);


    add_action( 'woocommerce_account_my-tickets_endpoint', array( $this , 'showMyTicket') ); 
    
    # Reedem button functionality
    # Fetching available tickets
    add_action( 'wp_ajax__ids_fetch_tickets', [$this, 'checkingTicketExist'] );
    add_action( 'wp_ajax_nopriv__ids_fetch_tickets', [$this, 'checkingTicketExist'] );

    # Reedem request functionality
    add_action( 'wp_ajax__ids_enroll_tickets', [$this, 'idsEnrollTickets'] );
    

    add_filter( 'woocommerce_account_menu_items', array( $this , 'add_copouns_tab') );  

    add_action( 'init',							  array( $this , 'my_account_copouns_url_rewrite') );
    add_filter( 'query_vars', 					  array( $this , 'copouns_query_vars'), 0 );



    // Add the custom columns to the book post type:
    add_filter( 'manage_ticket_posts_columns', [$this, 'set_custom_edit_tickets_columns'] );


    // Add the data to the custom columns for the book post type:
    add_action( 'manage_ticket_posts_custom_column' , [$this, 'custom_tickets_column'], 10, 2 );


  }

  # Current date is saved in Ymd format in ACF date field
  # Changing the date to timestamp
  public function getACFDateToTimeStamp($dateString){
    if(empty($dateString)){
      return 0;
    }

    $date = DateTime::createFromFormat( $this->acfDateFormat, $dateString);

    if(empty($date)){
      return 0;
    }

    return strtotime($date->format('Y-m-d'));
  }

  function set_custom_edit_tickets_columns($columns) {
    unset( $columns['author'] );
    $columns['type'] = 'Type';    
    $columns['orderId'] = 'Order ID';
    $columns['ticket_code'] = 'Code';
    $columns['orderTotal'] = 'Order Total';
    $columns['user'] = 'User';
    $columns['status'] = 'Status';
    // $columns['result'] = 'Result';
    return $columns;
  }

  function idsEnrollTickets(){

    if(empty($_POST['ticketID'])){
      wp_send_json([
        'status' => false,
        'message' => "Please select ticket",
      ]);
    }
    $ticketID =($_POST['ticketID']);    
    $userId = get_current_user_id();
    if( empty($userId) ){
      wp_send_json([
        'status' => false,
        'message' => "Invalid access",
      ]);
    }

    // Is current user ownwer of the ticket
    if( $userId != get_post_meta( $ticketID, 'user', true ) ){
      wp_send_json([
        'status' => false,
        'message' => "Invalid access",
      ]);
    }


    // Default to mothly raffle ticket

    $type = get_post_meta( $ticketID, 'type', true);
    $status = 'Active';

    $ticketTitle = 'Monthly raffle ticket -- ' . date('F, Y');
    $startDate = strtotime("first day of this month");
    $endDate = strtotime("last day of this month");

    if($type === 'Weekly'){
      $startDate = strtotime( 'monday this week' );
      $endDate = strtotime("-1 day", strtotime( 'monday next week' ));
      $ticketTitle = 'Weekly raffle ticket --(' . date('d M', $startDate ) . ' - ' . date('d M', $endDate ) . ')';
    }
 
    $updatedTicketID = wp_update_post( [
      'post_title'   => $ticketTitle,
      'ID' => $ticketID
    ] );

    if( $updatedTicketID != $ticketID){
      wp_send_json([
        'status' => false,
        'message' => "Server error, please try again.",
      ]);
    }
  
    # Add meta coupons
    update_post_meta( $ticketID, 'start_date',  date( $this->acfDateFormat, $startDate) );
    update_post_meta( $ticketID, 'end_date',    date( $this->acfDateFormat, $endDate) );
    update_post_meta( $ticketID, 'ticket_status', $status );

    wp_send_json([
      'status' => true,
      'message' => "You have succuesfully enrolled in current {$type} raffle.",
      'data' => [
        'name' => $ticketTitle,
        'status' => $status,
        'expiryDate' => date('F j, Y', $endDate),
        'buttonHTML' => $this->getTicketButtonHTML($ticketID)
      ]
    ]);
  }



#Calling ajax to check 
public function checkingTicketExist(){  
  global $woocommerce;

  # User is not logged in
  $userId = get_current_user_id();
  if( empty($userId) ){
    $loginURL =  get_permalink( get_option('woocommerce_myaccount_page_id') );
    $loginMessage = bbCustomMessage()->getMessage( "loginToredeemTicket", [
      'LOGIN_LINK' => $loginURL
    ]); 
    wp_send_json([
      'status' => false,
      'message' => $loginMessage,
    ]);
  }

  # Getting user tickets
  // $tickets = $this->getTicketsForGivenUser( $userId );
  $tickets = $this->getTicketsForGivenUser( $userId , [
    'key' => 'ticket_status',
    'value' => 'Not active'
  ]);
  $response = [];
  foreach ($tickets as $ticket) {
    $code = get_post_meta($ticket->ID, 'ticket_code', true);
    $response[] = [
      'value' => $ticket->ID,
      'label' => "{$ticket->post_title} ({$code})"
    ];
  }

  if(!count($response)){
    // $message = "Sorry! You don't have any ticket. Purchase now";
    wp_send_json([
      'status' => false,
      'message' => "Sorry! You don't have any ticket. Purchase",
    ]);
  }
  wp_send_json([
    'status' => true,
    'data' => $response,
  ]);
  
}


  function custom_tickets_column( $column, $postID ) {
    switch ( $column ) {
      case 'type' :
        $orderId = get_post_meta($postID, '_order_id', true);
        if( myCredCheckoutMofication()->isPointBasedOrder( $orderId )){   
          echo 'Purchased';
          return;
        } 
        echo 'Free';
        break;
      case 'orderId' :
        $orderId   = get_post_meta($postID, '_order_id', true);
        $order     = wc_get_order($orderId);
        if($order){
          $viewOrder = $order->get_view_order_url();
          echo '<a href="'.$viewOrder.'">'.$orderId.'</a>';
        }else{
          echo '<span>N/A</span>';
        }
        break;
      
      case 'user' :
        $userID =  get_post_meta($postID, 'user', true);
        $user = get_user_by( 'id', $userID );
        if(!empty($user)){
          echo $user->first_name . ' ' . $user->last_name;
          return;
        }
        echo $userID;
        break;
      case 'status' :
        $ticketID = get_post_meta($postID, '_itemMetaHash', true); 
        $ticketStatus = get_post_meta( $postID , 'ticket_status', true );
        $startTime =  get_post_meta( $postID , 'start_date', true );
        $endTime =  get_post_meta( $postID , 'end_date', true );      
        $startTime = $this->getACFDateToTimeStamp($startTime);
        $endTime = $this->getACFDateToTimeStamp($endTime); 
        if( $ticketStatus === 'Active'){
          # Expire
          if( $endTime < time() ){
            # Is the user winner
            if( !empty( get_post_meta( $postID , 'is_winning_ticket', true )) ){
              if( !empty( get_post_meta( $postID , 'is_redeemed', true )) ){
                echo '<b>Redeemed</b>';
                return;
              }
              echo '<b>Winning</b>';
              return;
            }
            echo '<b>Raffle Ended</b>';
            return;
          }
          echo 'Active';
          return;
        }
        echo 'Not Active';
        return;
        break;

      case 'ticket_code' :
        $orderId = get_post_meta($postID, '_order_id', true);
        echo get_post_meta($postID, 'ticket_code', true);
        break;
        
      case 'orderTotal' :
        $orderId = get_post_meta($postID, '_order_id', true); 
        $ticketID = get_post_meta($postID, '_itemMetaHash', true); 
        $order   = wc_get_order( $orderId );
        if( myCredCheckoutMofication()->isPointBasedOrder( $orderId )){   
          $orderTotal = $order->get_subtotal();
          echo '<b>'.floatval($orderTotal).' Points</b>';
          return;
        }
        if($order){
          $orderTotal = $order->get_total();
          echo '<b>'.wc_price($orderTotal).'</b>';
        }else{
          echo '<b>N/A</b>';
        }
        break;
    }
  }

  
	public function add_copouns_tab( $items ) {
		 
		$new_items = array();
		$new_items['my-tickets'] = __( 'My tickets', 'mycredpartwoo' );

		// Add the new item after `edit-account`.
		return $this->my_custom_insert_after_helper( $items, $new_items, 'edit-account' );
	}
  
  
	public function my_custom_insert_after_helper( $items, $new_items, $after ) {
		// Search for the item position and +1 since is after the selected item key.
		$position = array_search( $after, array_keys( $items ) ) + 1;

		// Insert the new item.
		$array = array_slice( $items, 0, $position, true );
		$array += $new_items;
		$array += array_slice( $items, $position, count( $items ) - $position, true );

		return $array;
	}


  
	public function my_account_copouns_url_rewrite() {
		
		add_rewrite_endpoint( 'my-tickets', EP_ROOT | EP_PAGES );
	}
  
	public function copouns_query_vars( $vars ) {
	
		$vars[] = 'my-tickets';
		
		return $vars;
	}

  public function showMyTicket(){

    $userID = get_current_user_id();
    if( empty($userID) ){
      return;
    }
    $tickets = $this->getTicketsForGivenUser( $userID );?>
      <div class="mycred_coupons_badge_rank_container">
      <table class="woocommerce-orders-table woocommerce-MyAccount-orders shop_table shop_table_responsive my_account_orders account-orders-table">
        <thead>
          <tr>
            <th class="woocommerce-orders-table__header woocommerce-orders-table__header-order-number">
              <span class="nobr"><?php echo __( 'Sno', 'mycredpartwoo' ); ?></span>
            </th>
            <th class="woocommerce-orders-table__header woocommerce-orders-table__header-order-date">
              <span class="nobr"><?php echo __( 'Ticket', 'mycredpartwoo' ); ?></span>
            </th>
            <th class="woocommerce-orders-table__header woocommerce-orders-table__header-order-date">
              <span class="nobr"><?php echo __( 'Type', 'mycredpartwoo' ); ?></span>
            </th>
            <th class="woocommerce-orders-table__header woocommerce-orders-table__header-order-status">
              <span class="nobr"><?php echo __( 'Code', 'mycredpartwoo' ); ?>
              </span>
            </th>
            <th class="woocommerce-orders-table__header woocommerce-orders-table__header-order-status">
              <span class="nobr"><?php echo __( 'Purchased on', 'mycredpartwoo' ); ?>
              </span>
            </th>
            <th class="woocommerce-orders-table__header woocommerce-orders-table__header-order-total">
              <span class="nobr"><?php echo __( 'Expiry date', 'mycredpartwoo' ); ?></span>
            </th>
            <th class="woocommerce-orders-table__header woocommerce-orders-table__header-order-actions">
              <span class="nobr"><?php echo __( 'Status', 'mycredpartwoo' ); ?></span>
            </th>
          </tr>
        </thead>
        <tbody>
          <?php
            if(count($tickets) >= 1){ 
              $sno = 1; 
              foreach ( $tickets as $ticket ) { 
                $ticketID = $ticket->ID;
                $readableStartTime = $readableEndTime = "";
                $startTime =  get_post_meta( $ticketID , 'start_date', true );
                $endTime =  get_post_meta( $ticketID , 'end_date', true ); 
                $startTime = $this->getACFDateToTimeStamp($startTime);
                $endTime = $this->getACFDateToTimeStamp($endTime);
                $title =  $ticket->post_title;      
                $ticketCode =  get_post_meta( $ticketID , 'ticket_code', true );
                $orderId = get_post_meta($ticketID, '_order_id', true);
                $ticketType =  get_post_meta( $ticketID , 'ticket_type', true );
                if($startTime && $endTime){
                  $readableStartTime = date('F j, Y',$startTime);
                  $readableEndTime = date('F j, Y',$endTime);
                }
                $ticketStatus = get_post_meta( $ticketID , 'ticket_status', true );
                $order = wc_get_order( $orderId );
                $orderDate = $order ? date('F j, Y',strtotime($order->order_date)) : 'N/A';
         
                ?>
                <tr class="woocommerce-orders-table__row woocommerce-orders-table__row--status-completed order">
                  <td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-number" data-name="sno">
                    <?php echo $sno;?>
                  </td>
                  <td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-date" data-name="ticket">
                  <?php echo $title; ?>
                  </td>
                  <td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-date" data-name="type">
                  <?php echo (myCredCheckoutMofication()->isPointBasedOrder( $orderId ))? 'Purchased':'Free'; ?>
                  </td>
                  <td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-status" data-name="code">
                  <?php  echo $ticketCode;?> 
                  </td>
                  <td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-total" data-name="Purchased on ">            
                  <?php echo  $orderDate; ?>
                  </td>
                  <td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-total" data-name="expiry-date">            
                  <?php echo (!empty($readableEndTime) && $ticketStatus !== 'Not active')? $readableEndTime: '--'; ?>
                  </td>
                  <td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-actions" data-name="status">
                  <?php echo $this->getTicketButtonHTML($ticketID); ?>										
                  </td>
                </tr>
                <?php 
              $sno++; } 
            } else{ ?>
            <tr class="">
              <td class="no_ticket_found" colspan="10">
                <div class="woocommerce-message woocommerce-message--info woocommerce-Message woocommerce-Message--info woocommerce-info">
                <a class="woocommerce-Button button" href="<?php echo esc_url( get_permalink( get_page_by_title( 'Rewards' ) ) ); ?>">Browse Tickets</a>
                No ticket found.	</div>
                <?php //echo __( 'No ticket found.', 'mycredpartwoo' ); ?>
              </td>
            </tr>
          <?php } ?>
        </tbody>
      </table>
    <?php 
  }

  function getTicketButtonHTML($ticketID){

    ob_start();

    $ticket = get_post($ticketID);

    $startTime =  get_post_meta( $ticketID , 'start_date', true );
    $endTime =  get_post_meta( $ticketID , 'end_date', true ); 
    $startTime = $this->getACFDateToTimeStamp($startTime);
    $endTime = $this->getACFDateToTimeStamp($endTime);
    $title =  $ticket->post_title;  
    $trimmedTitle = str_replace('ticket', '', $title);    
    $ticketCode =  get_post_meta( $ticketID , 'ticket_code', true );
    $isRedeemed =  get_post_meta( $ticketID , 'is_redeemed', true );
    $isRedeemed = (!empty($isRedeemed))? $isRedeemed[0] : '';
    $label = $this->getTicketStatus($ticketID);  
    $contactLink = get_permalink(bbCustomVariables()->contactPageID());      
    $rewardLink = get_permalink(bbCustomVariables()->rewardPageID());      
    $readableStartTime = $readableEndTime ='';
    if($startTime && $endTime){
      $readableStartTime = date('F j, Y',($startTime));
      $readableEndTime = date('F j, Y',($endTime));
    }
    $modifiedLabel = $label === 'Raffle Ended' ? 'Raffle-ended' : $label;
    $message = bbCustomMessage()->getMessage( "ticket{$modifiedLabel}", [
      'TITLE' => $trimmedTitle, 
      'CODE' => $ticketCode, 
      'CONTACT_LINK' => $contactLink,
      'END_DATE' => $readableEndTime,
      'REWARD_LINK' => $rewardLink
    ]); 

    echo "<a
      class='ticket-btn ticket-".strtolower($modifiedLabel)." woocommerce-button button view' 
      data-ticket-title='{$trimmedTitle}'
      data-ticket-code='{$ticketCode}'
      data-ticket-id='{$ticketID}' 
      data-start-date='{$readableStartTime}'  
      data-end-date='{$readableEndTime}' 
      data-is-redeemed='{$isRedeemed}'
      data-ticket-status='{$modifiedLabel}'
      >{$label}</a><div class='message-info' style='display:none;'>{$message}</div>";

    return ob_get_clean();

  }

  function getTicketStatus($ticketID){
    $ticketStatus = get_post_meta( $ticketID , 'ticket_status', true );
    $startTime =  get_post_meta( $ticketID , 'start_date', true );
    $endTime =  get_post_meta( $ticketID , 'end_date', true );      
    $startTime = $this->getACFDateToTimeStamp($startTime);
    $endTime = $this->getACFDateToTimeStamp($endTime); 
    if( $ticketStatus === 'Active'){
      # Expire
      if( $endTime < time() ){
        # Is the user winner
        if( !empty( get_post_meta( $ticketID , 'is_winning_ticket', true )) ){
          $isRedeemed =  get_post_meta( $ticketID , 'is_redeemed', true );
          if( !empty($isRedeemed ) ){
            return 'Redeemed';
          }
          return 'Winning';
        }
        return 'Raffle Ended';
      }
      return 'Active';
    }
    return 'Enroll';
  }

  function totalSpending() {
    if( !is_user_logged_in() ){
      return;
    }

    $userID = get_current_user_id();
    $orders = get_posts(array(
      'numberposts' => -1,
      'meta_key'    => '_customer_user',
      'meta_value'  => $userID,
      'post_type'   => 'shop_order', // ['shop_order', 'shop_order_refund']
      'post_status' => 'wc-completed',
    ) );

    # Getting total of each user
    $total = 0;
    foreach( $orders as $orderPost ){

      # In case of sub-order don't update the rank
      $post = get_post( $orderPost->ID);
      if($post->post_parent !== 0){
        continue;
      }

      $order = wc_get_order($orderPost->ID);
      $total += floatval( $order->get_total() );
    }

    return '<div class="bb_total_spendings_ids">'.wc_price($total).'</div>';

  }
  
  function randomCode($length = 12) {
    $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
    $pass = array(); //remember to declare $pass as an array
    $alphaLength = strlen($alphabet) - 1; //put the length -1 in cache
    for ($i = 0; $i < $length; $i++) {
        $n = rand(0, $alphaLength);
        $pass[] = $alphabet[$n];
    }
    return implode($pass); //turn the array into a string
  }


  public function getCouponCodeFromItemID($itemID, $orderID = ''){

    $queryArgs = [
      'post_type'  => 'shop_coupon',
      'meta_query' => [
        [
          'key'   => '_itemMetaHash',
          'value' => $itemID,
        ],
      ]
    ];
    $coupons = get_posts( $queryArgs );

    $couponCodes = [];
    foreach($coupons as $coupon){
      $couponCodes[] = $coupons[0]->post_name;
    }

    return implode(', ', $couponCodes);
  }

  public function getTicketObjectFromItemID($itemID, $orderID = ''){

    $queryArgs = [
      'post_type'  => 'ticket',
      'meta_query' => [
        [
          'key'   => '_order_id',
          'value' => $orderID,
        ],        
        [
          'key'   => '_itemMetaHash',
          'value' => $itemID,
        ],
      ]
    ];
    return get_posts( $queryArgs );
  }
  
  public function getTicketsForGivenUser($userID, $extraMetaQuery = []){
    $metaQuery = [
      [
        'key'   => 'user',
        'value' => $userID,
      ]
    ];
    if( !empty($extraMetaQuery) ){
      $metaQuery[] = $extraMetaQuery;
    }
    $queryArgs = [
      'post_type'  => 'ticket',
      'posts_per_page' => -1,
      'meta_query' => $metaQuery
    ];
    return get_posts( $queryArgs );
  }

  public function getTicketObjFromOrderId($orderID){

    $queryArgs = [
      'post_type'  => 'ticket',
      'meta_query' => [
        [
          'key'   => '_order_id',
          'value' => $orderID,
        ],
        [
          'key' => '_itemMetaHash',
          'compare' => 'NOT EXISTS',

        ]
      ]
    ];
    return get_posts( $queryArgs );
  }


  // type = Weekly, Monthly
  public function createTicketForUser($userID, $type = 'Monthly'){
    $ticketTitle = 'Monthly raffle ticket';
    if($type === 'Weekly'){
      $ticketTitle = 'Weekly raffle ticket';
    }


    $ticketCode	 	 =  strtoupper(  $this->randomCode() );
    $description   = "You have purchased a {$type} raffel ticket.";
      
    $ticket = array(
      'post_title'   => $ticketTitle,
      'post_content' => '',
      'post_excerpt' => $description,  
      'post_status'  => 'publish',
      'post_author'  => 1,
      'post_type'    => 'ticket'
    );    
  
    $newTicketID = wp_insert_post( $ticket );
  
    // Add meta coupons
    update_post_meta( $newTicketID, 'type',        $type );
    update_post_meta( $newTicketID, 'user',          $userID );
    update_post_meta( $newTicketID, 'ticket_code',   $ticketCode );
    update_post_meta( $newTicketID, 'ticket_status', 'Not active' );
    return $newTicketID;
  }

  #
  # Random ticket is created once order is completed by admin
  # For point based order this shouldn't happen
  #
  # In case of sub-order, we not going to create the ticket.
  public function createRandomTicket( $postID ){

    $order = wc_get_order( $postID );

    # In case of sub-order don't create the ticket
    $post = get_post( $postID );
    if($post->post_parent !== 0){
      return;
    }

    # For point based order we don't need to create the ticket
    if( myCredCheckoutMofication()->isPointBasedOrder( $order->ID ) ){
      return;
    }

    # Have we created ticket in past
    if( get_post_meta($postID, '__order_completed_randome_ticket', true) == '1'){
      return;
    }


    $user = $order->get_user();
    # For guest user return from here, since rank calculation doesn't make sense
    if(empty($user)){
      return;
    }
    $ticketType = mt_rand(0,1) === 1 ? 'Monthly' : 'Weekly';
    $ticketID = $this->createTicketForUser($user->ID, $ticketType);
    update_post_meta( $ticketID, '_order_id', $postID );	

    update_post_meta($postID, '__order_completed_randome_ticket', '1');    

  }


  #
  # Creating coupon code or ticket for point based order
  #
  public function createPointBasedItemsOnceOrderIsCompleted($postID){

    $order = wc_get_order( $postID );
    $user = $order->get_user();

    # For non point based order
    if( !myCredCheckoutMofication()->isPointBasedOrder( $order->ID ) ){
      return;
    }

    # Have we created ticket in past
    if( get_post_meta($postID, '__order_completed_once', true) == '1'){
      return;
    }

    # For guest user return from here, since rank calculation doesn't make sense
    if(empty($user)){
      return;
    }

    # Get and Loop Over Order Items
    foreach ( $order->get_items() as $itemID => $item ) {

      # Making coupon in case purchasable product is selected
      $productID = $item->get_product_id();
      // $productID = '6420';
      if( get_post_meta($productID, 'purchasable_by_points_only', true) === 'yes' ){

        # Is coupon type product
        $productType = get_post_meta($productID, 'coupon_type', true);
        if( in_array($productType, ['fixed_coupon_code', 'percentage_coupon_code']) ){

          $couponType = 'fixed_coupon_code' === $productType ? 'fixed' : 'percent';

          $amount = get_post_meta($productID, 'coupon_discount_value', true);
          if(empty($amount)){
            continue;
          }
        
          # $coupon_code
          for($i=0; $i< $item->get_quantity(); $i++){

            $couponCode	 	 = $this->randomCode();;
            $d_amount      = $couponType === 'percent' ? $amount."%" : get_woocommerce_currency_symbol() . $amount;
            $description   = "You have purchased a coupon for {$d_amount}.";
            $customerEmail 	 = $user->user_email;
            $coupon = array(
              'post_title'   => $couponCode."_".get_current_user_id(),
              'post_content' => '',
              'post_excerpt' => $description,  
              'post_status'  => 'publish',
              'post_author'  => 1,
              'post_type'    => 'shop_coupon'
            );    

            //TODO: Add check so that coupon code is only created once.
            $newCouponID = wp_insert_post( $coupon );
          
            // Add meta coupons
            update_post_meta( $newCouponID, 'discount_type',       $couponType );
            update_post_meta( $newCouponID, 'coupon_amount',       $amount );
            update_post_meta( $newCouponID, 'individual_use',      'no' );
            update_post_meta( $newCouponID, 'product_ids',         '' );
            update_post_meta( $newCouponID, 'exclude_product_ids', '' );
            update_post_meta( $newCouponID, 'usage_limit',         '1' );
            update_post_meta( $newCouponID, 'date_expires', 	     '' );
            update_post_meta( $newCouponID, 'apply_before_tax',    '' );
            update_post_meta( $newCouponID, 'free_shipping',       'no' );
            update_post_meta( $newCouponID, 'customer_email',      $customerEmail );	
            update_post_meta( $newCouponID, 'reference_type',      '_bb_custom_bought' );	
            update_post_meta( $newCouponID, 'user_id',     		     $user->ID );	
            update_post_meta( $newCouponID, '_itemMetaHash',       $itemID );	
            update_post_meta( $newCouponID, '_order_id',           $postID );	
    
          }
        }

        # Is ticket type product
        if( in_array($productType, ['monthly_raffle_ticket', 'weekly_raffle_ticket']) ){
          # Creating tickets
          for($i=0; $i< $item->get_quantity(); $i++){
            $ticketType = 'monthly_raffle_ticket' === $productType ? 'Monthly' : 'Weekly';
            $ticketID = $this->createTicketForUser($user->ID, $ticketType);
            update_post_meta( $ticketID, '_itemMetaHash',       $itemID );	
            update_post_meta( $ticketID, '_order_id',           $postID );	
          }
        }        
      }
    }

    update_post_meta($postID, '__order_completed_once', '1');
  }

  public function beforeCouponCodeRender(){
    add_action( 'pre_get_posts', [$this, 'showRankBasedCoupon'] );
  }


  #
  # Modifying product based query, It will modify all product based query
  # Calling this only in shop page, home page ajax tabs and reward page
  public function showRankBasedCoupon($query){
 
    // Only run for shop_coupon
    if( empty($query->query_vars['post_type']) && $query->query_vars['post_type'] !== 'shop_coupon'){ 
      return $query; 
    }

    $userID = get_current_user_id();
    if( empty($userID)){
      return $query;
    }

    $rankObject = myCredCheckoutMofication()->getUserRank($userID, 'OBJECT');
    // Define an additional tax query 
    $tempQuery= [
      'relation' => 'OR',
      $query->get( 'meta_query'),
      [
        'key' => 'rank_based_coupon',
        'value' => $rankObject->post->post_title,        
      ]
    ];

    // Set the new merged tax query
    $query->set( 'meta_query', $tempQuery );
    return $query;
  }

  // Reseting the post query
  public function afterCouponCodeRender(){
    remove_action( 'pre_get_posts', [$this, 'showRankBasedCoupon'] );
  }

  # 
  # Hide message for point base product
  # Passing 0 for point based cart
  public function modifyRewardsPointsGlobal($value, $name){
    return $this->isPointBasedCart()? 0 :$value;
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
      self::$instance = new myCredProfilePageModification();
    }
    return self::$instance;
  }

}