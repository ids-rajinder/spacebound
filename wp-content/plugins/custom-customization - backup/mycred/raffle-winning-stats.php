<?php


if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class raffleDrawStatsCustomization{

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
  private $raffleEndWeekDay ;
  private $raffleStartWeekDay;

  private $reviewWinners = [
    'best_review_winner_all_monthly' => 'Best Review Winners (Monthly)',
    'best_review_winner_all_time' => 'Best Review Winners (All Time)',
  ];

  public function __construct(){
    $this->plugin_path = CUSTOM_BBS_PATH;
    $this->plugin_url  = CUSTOM_BBS_URL;

    #TODO Implement check in better way
    if( !function_exists('mycred_count_all_ref_instances')){
      return;
    }

    add_shortcode( 'reedemedRaffleTicketWeek', [$this ,  'reedemedTicketWeek'] );
    add_shortcode( 'ticketReedemedMonthly', [$this ,  'reedemedMonthly'] );
    add_shortcode( 'ticketReedemedWeekly', [$this ,  'reedemedWeekly'] );
    add_shortcode( 'bestReviewMonthly', [$this ,  'reviewMonthly'] );
    add_shortcode( 'bestReviewWeekly', [$this ,  'reviewWeekly'] );
    add_shortcode( 'winningTicketNumbers', [$this ,  'winningTickets'] );

    // return apply_filters( 'mycred_all_references', $references );
    add_filter( 'mycred_all_references', [$this, 'addCustomOption']); 

  }

  public function addCustomOption($references){
    foreach($this->reviewWinners as $refKey => $refValue){
      $references[$refKey] = $refValue;
    }
    return $references;
  }

  public function reedemedTicketWeek(){
    ob_start();

    $date=date("Y-m-d");

    $monday = strtotime("last monday");
    $monday = date('w', $monday)==date('w') ? $monday+7*86400 : $monday;
    $sunday = strtotime(date("Y-m-d",$monday)." +6 days");
    $this->raffleStartWeekDay = date("l, jS F, Y",$monday);
    $this->raffleEndWeekDay = date("l, jS F, Y",$sunday);

    // $this->raffleEndWeekDay = date('l, jS F, Y', strtotime($date.$monday));
    // $days_ago = date('Y-m-d', strtotime('+6 days', strtotime($this->raffleEndWeekDay)));
    // $this->raffleStartWeekDay = date("l, jS F", strtotime($days_ago));
    $lastmonth = date("F, Y",strtotime("0 month"));
    ?>
    <p>
      REDEEMED RAFFLE TICKETS:<br>
      Month: <?php echo $lastmonth; ?><br>
      Week: <?php echo $this->raffleStartWeekDay .' - '.$this->raffleEndWeekDay; ?>
    </p>
    <?php return ob_get_clean();
  }

  public function reedemedWeekly(){
    $endDate = strtotime( $this->raffleEndWeekDay ); 
    $startDate = strtotime($this->raffleStartWeekDay );
    $ticketTitle = 'Weekly raffle ticket --(' .date('d M',$startDate) . ' - ' . date('d M',$endDate ). ')';
    $queryArgs = [
      'post_type'  => 'ticket',
      'numberposts' => -1,
      'fields' => 'ids',
      "title" => $ticketTitle,
    ]; 
  
    return count(get_posts( $queryArgs ));
  }

  public function reedemedMonthly(){
    $ticketTitle = 'Monthly raffle ticket -- ' . date('F, Y' ,strtotime("0 month"));
    $queryArgs = [
      'post_type'  => 'ticket',
      'numberposts' => -1,
      'fields' => 'ids',
      "title" => $ticketTitle,
    ];
    return count(get_posts( $queryArgs ));
  }

  public function reviewMonthly(){
    $references = mycred_count_all_ref_instances( -1, 'DESC', 'all' );
    return !empty($references['best_review_winner_all_monthly']) ? $references['best_review_winner_all_monthly'] : "0";
  }
  
  public function reviewWeekly(){
    $references = mycred_count_all_ref_instances( -1, 'DESC', 'all' );
    return !empty($references['best_review_winner_all_time']) ? $references['best_review_winner_all_time'] : "0";
  }


  public function winningTickets(){
    ob_start();

    $queryArgs = [
      'post_type'  => 'ticket',
      'numberposts' => 6,
      'fields' => 'ids',
      'orderby' => 'ID',
      'order' => 'DESC',
      'meta_query' => [
        'relation' => 'AND',
        [
          'key'   => 'is_winning_ticket',
          'value' => '',
          'compare' => '!='
        ],
        [
          'key'   => 'display_on_reward_page',
          'value' => 'Yes',
        ]
      ]
    ];
    $tickets = get_posts( $queryArgs );
    foreach ( $tickets as $ticketID ) { ?>
     <div class="elementor-element elementor-element-aaceaaf color-scheme-inherit text-left elementor-widget elementor-widget-text-editor" data-id="95317d3" data-element_type="widget" data-widget_type="text-editor.default">
        <div class="elementor-widget-container">
          <div class="elementor-text-editor elementor-clearfix">
            <p><?php echo '#' . get_post_meta($ticketID, 'ticket_code', true); ?></p>
          </div>
        </div>
      </div>
    <?php }
 
    return ob_get_clean();

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
      self::$instance = new raffleDrawStatsCustomization();
    }
    return self::$instance;
  }

}