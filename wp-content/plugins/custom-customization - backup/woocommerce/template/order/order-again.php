<?php
/**
 * Order again button
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/order/order-again.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 3.5.0
 */

defined( 'ABSPATH' ) || exit;
  global $woocommerce; 
  $orderId = $order->get_id();
  $userID = get_current_user_id();
  $isPointBasedorder =  myCredCheckoutMofication()->isPointBasedOrder( $orderId );  
  $insufficentBalance = false;     
  $previousOrderTotal = $order->get_subtotal();
  $pointBalance   = ( is_user_logged_in() ) ? mycred_get_users_balance($userID) : 0;
  if($pointBalance < $previousOrderTotal){
    $insufficentBalance = true;
  }
  $disableClassForPoint = ($isPointBasedorder && $insufficentBalance == true) ? "bb-not-enough-balance-for-reorder" : "";
  # Showing Ticket information in the order
  if( !$isPointBasedorder ){  

    $Ticket = myCredProfilePageModification()->getTicketObjFromOrderId( $order->get_id() );
    $ticketID = ($Ticket)?$Ticket[0]->ID:'';
    $ticketCode = get_post_meta($ticketID, 'ticket_code', true);
    $TitleArray = ($ticketCode)?explode('-',$Ticket[0]->post_title):'';

    ?>
    <div class="custom-ticket" style="display:<?php echo(empty($Ticket))? 'none' : ''; ?>">
        <div class="custom-row">
            <div class="col-t">
            <h4>Congratulations!<br> you got a free</h4>
            <!-- <p>12-4-2021</p> -->
            <!-- <span>No.</span> -->
            <h1><?php echo $TitleArray[0]; ?></h1>
            <h2>CODE - ( <?php echo $ticketCode; ?> )</h2>
            </div>
        </div>
        <div class ="status">
            <a href="<?php echo get_permalink( get_page_by_title( 'my-account' ) ).'my-tickets?ticketCode='.$ticketCode; ?>">Check status</a>
        </div>
    </div>
    <?php
  }

 ?>

<p class="order-again">
	<a href="<?php echo esc_url( $order_again_url ); ?>" class="button " id = "<?php echo $disableClassForPoint; ?>" disabled><?php esc_html_e( 'Order again', 'woocommerce' ); ?></a>
</p>
