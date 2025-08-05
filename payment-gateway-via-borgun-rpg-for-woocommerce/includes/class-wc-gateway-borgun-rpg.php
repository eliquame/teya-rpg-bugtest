<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WC_Gateway_Borgun_RPG extends WC_Payment_Gateway_CC {

  /**
   * Whether or not logging is enabled
   *
   * @var bool
   */
  public static $log_enabled = false;

  /**
   * Logger instance
   *
   * @var WC_Logger
   */
  public static $log = false;

  /**
   * Gateway testmode
   *
   * @var string
   */
  private $testmode;

  /**
   * Enable payments logs
   *
   * @var string
   */
  private $debug;

  /**
   * Teya RPG API class
   *
   * @var Borgun_RPG_Api
   */
  public $api;

  public function __construct(){
    $this->id                 = 'borgun_rpg';
    $this->icon               = BORGUN_RPG_URL . '/teya.png';
    $this->has_fields         = false;
    $this->method_title       = 'Teya RPG';
    $this->method_description = 'Teya RPG';
    // Load the form fields
    $this->init_form_fields();
    $this->init_settings();
    $this->enabled            = $this->get_option( 'enabled' );
    $this->title              = $this->get_option( 'title' );
    $this->description        = $this->get_option( 'description' );
    $this->testmode           = $this->get_option( 'testmode' );

    $this->debug              = 'yes' === $this->get_option( 'debug', 'no' );
    self::$log_enabled        = $this->debug;

    $this->api = new Borgun_RPG_Api();

    add_action( 'woocommerce_update_options_payment_gateways_borgun_rpg', array( $this, 'process_admin_options' ) );
    add_action( 'wp_enqueue_scripts', array($this, 'add_borgun_payment_library_script'));

    $this->supports           = array(
      'products',
      'subscriptions',
      'refunds',
      'subscription_cancellation',
      'subscription_suspension',
      'subscription_reactivation',
      'subscription_amount_changes',
      'subscription_date_changes',
      'subscription_payment_method_change',
      'subscription_payment_method_change_customer',
      'subscription_payment_method_change_admin',
      'multiple_subscriptions'
    );
  }

  public function init_form_fields() {
    $this->form_fields = array(
      'enabled'            => array(
        'title'       => __( 'Enable/Disable', 'borgun_rpg' ),
        'label'       => __( 'Enable Teya RPG', 'borgun_rpg' ),
        'type'        => 'checkbox',
        'description' => '',
        'default'     => 'no'
      ),
      'title'              => array(
        'title'       => __( 'Title', 'borgun_rpg' ),
        'type'        => 'text',
        'description' => __( 'This controls the title which the user sees during checkout.', 'borgun_rpg' ),
        'default'     => __( 'Teya RPG', 'borgun_rpg' ),
      ),
      'description'        => array(
        'title'       => __( 'Description', 'borgun_rpg' ),
        'type'        => 'textarea',
        'description' => __( 'This controls the description which the user sees during checkout.', 'borgun_rpg' ),
        'default'     => __( 'Pay with your credit card via Teya.', 'borgun_rpg' ),
      ),
      'testmode'           => array(
        'title'       => __( 'Test Mode', 'borgun_rpg' ),
        'label'       => __( 'Enable Test Mode', 'borgun_rpg' ),
        'type'        => 'checkbox',
        'description' => __( 'Place the payment gateway in development mode.', 'borgun_rpg' ),
        'default'     => 'no'
      ),
      'enabled_3d_secure' => array(
        'title'       => __( 'Enable/Disable 3D secure', 'borgun_rpg' ),
        'label'       => __( 'Enable 3D secure', 'borgun_rpg' ),
        'type'        => 'checkbox',
        'description' => '',
        'default'     => 'no'
      ),
      'merchantid'         => array(
        'title'       => __( 'Merchant ID', 'borgun_rpg' ),
        'type'        => 'text',
        'description' => __( 'This is the ID supplied by Teya.', 'borgun_rpg' ),
        'default'     => ''
      ),
      'publickey'          => array(
        'title'       => __( 'Public Key', 'borgun_rpg' ),
        'type'        => 'text',
        'description' => __( 'This is the Public Key supplied by Teya.', 'borgun_rpg' ),
        'default'     => ''
      ),
      'privatekey'          => array(
        'title'       => __( 'Private Key', 'borgun_rpg' ),
        'type'        => 'text',
        'description' =>  __( 'This is the Private Key supplied by Teya.', 'borgun_rpg' ),
        'default'     => ''
      ),
      'debug' => array(
        'title'       => __( 'Debug', 'borgun_rpg' ),
        'label'       => __( 'Enable Debug Mode', 'borgun_rpg' ),
        'type'        => 'checkbox',
        'default'     => 'no',
        'desc_tip'    => true,
      ),
    );
  }

  public function admin_options() {
    ?>
    <h3><?php echo esc_html( $this->get_method_title() ); ?></h3>
    <?php
    if ( $this->is_valid_for_use() )
      echo '<table class="form-table">' . $this->generate_settings_html( $this->get_form_fields(), false ) . '</table>'; // WPCS: XSS ok.
    else
      echo sprintf(
        '<div class="inline error"><p><strong>%s</strong>: %s</p></div>',
        __( 'Gateway Disabled', 'woocommerce' ),
        __( 'Current Store currency is not supported by Teya RPG. Allowed values are GBP, USD, EUR, DKK, NOK, SEK, CHF, CAD, HUF, BHD, AUD, RUB, PLN, RON, HRK, CZK and ISK.', 'borgun_rpg' )
      );
  }

  //Check if this gateway is enabled and available in the user's country
  function is_valid_for_use() {
    if ( ! in_array( get_woocommerce_currency(), array(
      'ISK',
      'GBP',
      'USD',
      'EUR',
      'DKK',
      'NOK',
      'SEK',
      'CHF',
      'CAD',
      'HUF',
      'BHD',
      'AUD',
      'RUB',
      'PLN',
      'RON',
      'HRK',
      'CZK',
    ) )
    ) {
      return false;
    }

    return true;
  }

  /**
  * Processes and saves options.
  * If there is an error thrown, will continue to save and validate fields, but will leave the erroring field out.
  *
  * @return bool was anything saved?
  */
  public function process_admin_options() {
    $saved = parent::process_admin_options();

    // Maybe clear logs.
    if(!$this->debug){
      if ( empty( self::$log ) ) {
        self::$log = wc_get_logger();
      }
      self::$log->clear( 'borgun_rpg' );
    }

    return $saved;
  }

  /**
   * Logging method.
   *
   * @param string $message Log message.
   * @param string $level Optional. Default 'info'. Possible values:
   *                      emergency|alert|critical|error|warning|notice|info|debug.
   */
  public static function log( $message, $level = 'info' ) {
    if ( self::$log_enabled ) {
      if ( empty( self::$log ) ) {
        self::$log = wc_get_logger();
      }
      self::$log->log( $level, $message, array( 'source' => 'borgun_rpg' ) );
    }
  }

  /**
   * Get gateway icon.
   *
   * @return string
   */
  public function get_icon() {
    $icon_html = '<img class="wc-borgun-rpg-payment-gateway-checkout-logo" src="' . $this->icon . '" alt="' . esc_html( $this->get_method_title() ) . '" />';
    return apply_filters( 'woocommerce_gateway_icon', $icon_html, $this->id );
  }

  public function add_borgun_payment_library_script(){
    global $wp;
    if($this->testmode == 'yes'){
      wp_enqueue_script( 'borgun_payment_js', 'https://test.borgun.is/resources/js/borgunpayment-js/borgunpayment.v1.min.js', [], BORGUN_RPG_VERSION, ['strategy' => 'async']);
    } else {
      wp_enqueue_script( 'borgun_payment_js', 'https://ecommerce.borgun.is/resources/js/borgunpayment-js/borgunpayment.v1.min.js', [], BORGUN_RPG_VERSION, ['strategy' => 'async']);
    }
    $order_id = ( !empty( $wp->query_vars['order-pay']) ) ? (int) $wp->query_vars['order-pay'] : '';
    $borgun_args = [];
    $borgun_args['key'] = $this->get_option( 'publickey' );
    $borgun_args['ajax_url'] = admin_url( 'admin-ajax.php' );
    $borgun_args['nonce'] = wp_create_nonce( 'borgun_ajax' );
    $borgun_args['order_id'] = $order_id;
    $borgun_args['ajax_delay'] = 5000;
    wp_enqueue_script( 'borgun_rpg_js', BORGUN_RPG_URL.'assets/js/borgun_rpg.js', ['jquery', 'borgun_payment_js'], BORGUN_RPG_VERSION);
    wp_localize_script( 'borgun_rpg_js','borgun_data', $borgun_args );
  }

  public function payment_fields(){
    $this->form();
    print '<input type="hidden" id="borgun-rpg-card-token" name="borgun-rpg-card-token">';
    echo '<div class="error"><ul class="error-message"></ul></div>';
  }

  public function process_payment( $order_id ) {
    $order = wc_get_order( $order_id );
    $card_token = sanitize_text_field( $_POST['borgun-rpg-card-token'] );

    if($this->api->is_use_3d_secure()){
      $mpi_enrollment = $this->mpi_enrollment( $order, ['token'=>$card_token] );
      if(in_array($mpi_enrollment['md_status'], [1, 2, 3, 4])){
        /*
        * 1   - Authenticated Continue transaction  Cardholder successfully authenticated.
        * 2,3 - Not participating Continue transaction  Cardholder not enrolled in 3DSecure or issuer of the card is not participating in 3DSecure
        * 4   - Attempt Continue transaction  3DSecure attempt recognized by card issuer.
        */
        $args = [ 'token'=>$card_token,
          'XId'=>(isset($mpi_enrollment['mpi_xid'])) ? $mpi_enrollment['mpi_xid']: '',
          'mpi_token'=>(isset($mpi_enrollment['mpi_token'])) ? $mpi_enrollment['mpi_token']: ''
        ];
        $payment = $this->create_payment($order, $args, true);
        if($payment['success']){
          $order->add_order_note( $payment['message'] );
          $order->payment_complete($payment['transaction_id']);
          WC()->cart->empty_cart();

          return array(
            'result'    => 'success',
            'redirect'  => $this->get_return_url( $order )
          );
        }else{
          $order->add_order_note( $payment['error'] );
          $order->update_status( 'failed' );
          throw new Exception($payment['error']);
        }
      }elseif($mpi_enrollment['md_status']==50 || $mpi_enrollment['md_status']==9){
        /*
         * 50 - An extra authentication step is required before 3DSecure procedure is started
         * 9 - Pending, MdStatus in enrollment response when merchant should start 3DSecure procedure.
        */
        $verification_html = (isset($mpi_enrollment['verification_html']) && !empty($mpi_enrollment['verification_html'])) ? $mpi_enrollment['verification_html']: '';


        if(!empty($verification_html)){
          $this->save_borgun_rpg_payment_method($order, ['type'=>'single', 'token'=>$card_token]);
          $mpi_token = (isset($mpi_enrollment['mpi_token'])) ? $mpi_enrollment['mpi_token']: '';
          $this->save_borgun_rpg_mpi_token($order, $mpi_token);

          WC()->session->set( 'wc_borgun_rpg_verification_' . $order_id, $verification_html);
          WC()->session->set( 'wc_borgun_rpg_md_status_' . $order_id, $mpi_enrollment['md_status']);
          WC()->session->save_data();
          $checkout_url = wc_get_checkout_url();
          $checkout_url = substr($checkout_url, 0, strrpos($checkout_url, "/"));
          return array(
            'result'=>'success',
            'redirect'=>add_query_arg(
              array(
                'order'=>$order_id
              ),
              $checkout_url . WC_AJAX::get_endpoint( 'wc_borgun_rpg_verify_intent' )
            )
          );
        }else{
          throw new Exception(__("MPI(Merchant Plugin Interface) verification data not provided", 'borgun_rpg'));
        }
      }else{
        throw new Exception(__("MPI(Merchant Plugin Interface) authentication isn't available", 'borgun_rpg'));
      }
    }else{
      $payment = $this->create_payment($order, ['token'=>$card_token], false);
      if($payment['success']){
        $order->add_order_note( $payment['message'] );
        $order->payment_complete($payment['transaction_id']);
        WC()->cart->empty_cart();

        return array(
          'result'=>'success',
          'redirect'=>$this->get_return_url( $order )
        );
      }else{
        $order->add_order_note( $payment['error'] );
        $order->update_status( 'failed' );
        throw new Exception($payment['error']);
      }
    }
  }

  /**
   * Save card token for intent page payment
   *
   * @param WC_Order $order WC_Order object
   * @param array $payment_method Teya RPG payment method
   * 
   * @return void
   */
  protected function save_borgun_rpg_payment_method( $order, $payment_method ) {
    $order->update_meta_data('_' . $this->id . '_payment_method', $payment_method);
    $order->save();
	}

  /**
   * Save MPI token for intent page payment
   *
   * @param WC_Order $order WC_Order object
   * @param array $mpi_token MPI token
   * 
   * @return void
   */
  protected function save_borgun_rpg_mpi_token( $order, $mpi_token ) {
    $order->update_meta_data('_' . $this->id . '_mpi_token', $mpi_token);
    $order->save();
  }

  /**
   * Refund payment
   *
   * @param int $order_id WC Order id
   * @param float $amount Amount
   * @param string $reason Refund reason
   * 
   * @return bool
   */
  public function process_refund( $order_id, $amount = NULL, $reason = '' ) {
    $order = wc_get_order( $order_id );
    if(method_exists($order,'get_transaction_id')){
      $transaction_id = $order->get_transaction_id();
    }else{
      $transaction_id = $order->get_meta('_transaction_id', true);
    }

    $response = $this->api->refund_payment( $transaction_id, $amount, $reason );
    WC_Gateway_Borgun_RPG::log( sprintf( __( 'Teya PRG - Refund response: %s', 'borgun_rpg' ), wc_print_r($response, true) ) );
    if(!$response){
      if(isset($response->Message)){
        return new WP_Error( 'borgun_rpg_refund_error', $response->Message);
      }
      if(isset($response->error)){
        return new WP_Error( 'borgun_rpg_refund_error', $response->error );
      }
    }
    else{
      $order->update_status('refunded', __('Refund success', 'borgun_rpg'));
    }

    return true;
  }

  /**
   * Executed between the "Checkout" and "Thank you" pages.
   *
   * @param WC_Order $order WC_Order object
   *
   * @return mixed
   */
  public function verify_intent_after_checkout( $order ) {
    $response = ['success'=>false];
    $order_id  = $order->get_id();

    WC_Gateway_Borgun_RPG::log( __('Successful redirect to intent checkout', 'borgun_rpg') );

    $verification = WC()->session->get( 'wc_borgun_rpg_verification_' . $order_id );
    $mpi_md_status = WC()->session->get( 'wc_borgun_rpg_md_status_' . $order_id );
    if(!empty($verification)){
      WC_Gateway_Borgun_RPG::log(__( '3Ds verification: redirected user to external verification server', 'borgun_rpg'));
      WC()->session->set( 'wc_borgun_rpg_verification_' . $order_id, null );
      WC()->session->set( 'wc_borgun_rpg_md_status_' . $order_id, null );
      WC()->session->save_data();
      echo $verification . PHP_EOL;
      if($mpi_md_status == 9){
        exit;
      }
    }

    // Read external mpi enrollment response
    if( isset($_POST['PaRes']) || isset($_POST['cres']) || isset($_POST['MD']) ) {
      $mpi_token = $order->get_meta('_' . $this->id . '_mpi_token', true);
      $payment_method =  $this->get_borgun_rpg_payment_method($order);

      // Remove saved metas
      $order->delete_meta_data('_' . $this->id . '_payment_method');
      $order->delete_meta_data('_' . $this->id . '_mpi_token');
      $order->save();

      if(empty($mpi_token) ){
         return new WP_Error('error', __( 'MPI Token not found', 'borgun_rpg'));
      }

      $error_message = '';
      $secure = [];
      if( isset($_POST['PaRes']) && !empty($_POST['PaRes']) )
        $secure['PARes'] = sanitize_text_field($_POST['PaRes']);
      if( isset($_POST['cres']) && !empty($_POST['cres']) )
          $secure['cres'] = sanitize_text_field($_POST['cres']);
      if( isset($_POST['MD']) && !empty($_POST['MD']) )
          $secure['MD'] = sanitize_text_field($_POST['MD']);

      $time = current_time('timestamp');
      if(get_transient('borgun_rpg_payment_' . $order_id . '_processing')){
        return new WP_Error('error', __( 'Duplicate request', 'borgun_rpg'));
      }else{
        set_transient( 'borgun_rpg_payment_' . $order_id . '_processing', $time, 300);
      }

      $payment_args = [];
      $payment_args['mpi_token'] = $mpi_token;
      $mpi_validation = $this->mpi_validation($secure);
      if(!empty($mpi_validation)){
          if(isset($mpi_validation['CAVV']) ){
            $payment_args['CAVV'] = $mpi_validation['CAVV'];
          }
          if(isset($mpi_validation['XId']) ){
            $payment_args['XId'] = $mpi_validation['XId'];
          }
      }

      if((isset($payment_args['CAVV']) && isset($payment_args['XId'])) ){
        $payment_args['payment_method'] = $payment_method;

        $payment = $this->create_payment($order, $payment_args, true);
        if($payment['success']){
          $order->add_order_note( $payment['message'] );
          $order->payment_complete($payment['transaction_id']);
          WC()->cart->empty_cart();
          $response['success'] = true;
          $response['redirect_to'] = $order->get_checkout_order_received_url();
        }else{
          $order->add_order_note( $payment['error'] );
          $order->update_status( 'failed' );
          $error_message = $payment['error'];
        }
      }else{
        $response_details = [];
        if($mpi_validation->MdStatus == 0 ){
          $response_details []= __('Cardholder did not finish the 3DSecure procedure successfully(MdStatus:0)','borgun_rpg');
        }elseif($mpi_validation->MdStatus == 8){
          $response_details[] = __('3DS attempt was blocked by MPI(MdStatus:8)','borgun_rpg');
        }elseif($mpi_validation->MdStatus == 7){
          $response_details[] = __('MPI/Our error(MdStatus:7)','borgun_rpg');
        }
        if( isset($mpi_validation->MdErrorMessage) && $mpi_validation->MdErrorMessage ){
          $error_message =  sprintf( __( 'MdErrorMessage: %s ', 'borgun_rpg' ), $mpi_validation->MdErrorMessage );
          $response_details[] = sprintf( __( 'MdErrorMessage: %s ', 'borgun_rpg' ), $mpi_validation->MdErrorMessage );
        }
        if( isset($mpi_validation->EnrollmentStatus) && $mpi_validation->EnrollmentStatus )
          $response_details[] = sprintf( __( 'EnrollmentStatus: %s ', 'borgun_rpg' ), $mpi_validation->EnrollmentStatus );
        if( isset($mpi_validation->AuthenticationStatus) && $mpi_validation->AuthenticationStatus )
          $response_details[] = sprintf( __( 'AuthenticationStatus: %s ', 'borgun_rpg' ), $mpi_validation->AuthenticationStatus );

        if(empty($error_message))
          $error_message = __('3DSecure procedure failed(error:3)', 'borgun_rpg');
        if( !empty($response_details) ) $order->add_order_note( implode("\n", $response_details) );
      }

      delete_transient( 'borgun_rpg_payment_' . $order_id . '_processing' );

      if(!empty($error_message)){
        wc_add_notice( $error_message, 'error' );
        return new WP_Error('error', $error_message);
      }
    }

    return $response;
  }

  /**
   * Return MPI Enrollment response
   *
   * @param array $args MPI Enrollment request data array 
   * 
   * @return array
   */
  public function mpi_enrollment($order, $args){
    $response = [];
    $payment_method = $this->get_borgun_rpg_payment_method($order, $args['token']);
    $mpi_enrollment = $this->api->mpiEnrollment($order, $payment_method);
    WC_Gateway_Borgun_RPG::log( sprintf( __( 'Teya PRG - Enrollment response: %s', 'borgun_rpg' ), wc_print_r($mpi_enrollment, true) ) );

    $md_status = ( isset($mpi_enrollment->MdStatus) && $mpi_enrollment->MdStatus ) ? $mpi_enrollment->MdStatus : null;

    $response['md_status'] = $md_status;
    $response['mpi_token'] = ( isset($mpi_enrollment->MPIToken) && !empty($mpi_enrollment->MPIToken) ) ? sanitize_text_field($mpi_enrollment->MPIToken) : '';
    $response['mpi_xid'] = ( isset($mpi_enrollment->XId) && !empty($mpi_enrollment->XId) ) ? sanitize_text_field($mpi_enrollment->XId) : '';
    if(in_array($md_status, [1, 2, 3, 4])){
      /*
      * 1 - Authenticated Continue transaction  Cardholder successfully authenticated.
      * 2,3 - Not participating Continue transaction  Cardholder not enrolled in 3DSecure or issuer of the card is not participating in 3DSecure
      * 4 - Attempt Continue transaction  3DSecure attempt recognized by card issuer.
      */
    }elseif($md_status==50){
      $verification_html = ( isset($mpi_enrollment->TDSMethodContent) && !empty($mpi_enrollment->TDSMethodContent) ) ? $mpi_enrollment->TDSMethodContent : '';
      $verification_html = (!empty($verification_html)) ? '<div style="display:none;">' . $verification_html .'</div>': '';
      $response['verification_html'] = $verification_html;
      $borgun_secure_form = str_replace('<link href="https://mpi.borgun.is/mdpaympi/static/mpi.css" rel="stylesheet" type="text/css">','', $borgun_secure_form);
      echo '<div style="display:none;">' . $borgun_secure_form .'</div>';
    }elseif($md_status==9){
      $verification_html = ( isset($mpi_enrollment->RedirectToACSForm) && !empty($mpi_enrollment->RedirectToACSForm) ) ? $mpi_enrollment->RedirectToACSForm : '';
      $verification_html = (!empty($verification_html)) ? str_replace('<link href="https://mpi.borgun.is/mdpaympi/static/mpi.css" rel="stylesheet" type="text/css">','', $verification_html) : '';
      $response['verification_html'] = $verification_html;
    }
    WC_Gateway_Borgun_RPG::log( sprintf( __( 'mpi_enrollment,response: %s', 'borgun_rpg' ), wc_print_r($response, true) ) );
    return $response;
  }

  /**
   * Return MPI Validation results
   *
   * @param array $args MPIValidation args array
   * 
   * @return array
   */
  public function mpi_validation($args){
    $response = $request_args = [];
    if(isset($args['PARes'])){
      $request_args['PARes'] = $args['PARes'];
    }
    if(isset($args['cres'])){
      $request_args['cres'] = $args['cres'];
    }
    if(isset($args['MD'])){
      $request_args['MD'] = $args['MD'];
    }
    $validation_response = $this->api->mpiValidation($request_args);
    WC_Gateway_Borgun_RPG::log( sprintf( __( 'Teya PRG - mpiValidation response: %s', 'borgun_rpg' ), wc_print_r($validation_response, true) ) );
    if(isset($validation_response->AuthenticationStatus) && $validation_response->AuthenticationStatus == 'Y'){
      if( (isset($validation_response->CAVV) && $validation_response->CAVV) )
        $response['CAVV'] = sanitize_text_field($validation_response->CAVV);
      if( (isset($validation_response->XId) && $validation_response->XId) )
        $response['XId'] = sanitize_text_field($validation_response->XId);
    }elseif(in_array($validation_response->MdStatus,[1,2,3,4,5,6,91,92,93,94,95,96,97,99]) ){
      /* MDStatus  Recommended action  Notes
      1 - Authenticated Continue transaction  Cardholder successfully authenticated.
      2,3 - Not participating Continue transaction  Cardholder not enrolled in 3DSecure or issuer of the card is not participating in 3DSecure
      4 - Attempt Continue transaction  3DSecure attempt recognized by card issuer.
      5 - Authentication unavailable  Continue transaction if risk manageable or retry 3DSecure procedure Issuer is unable to process 3DSecure request. Merchant can decide to continue with transaction if merchant considers risk as low. Please see Notes on ISK for a special case when processing ISK.
      6 - 3DSecure error  Continue transaction if risk manageable or retry 3DSecure procedure Invalid field in 3-D Secure message generation, error message received or directory server fails to validate the merchant.
      91 - Network error  Continue transaction if risk manageable or retry 3DSecure procedure Network error, connection to directory server times out.
      92 - Directory error  Continue transaction if risk manageable or retry 3DSecure procedure Directory response read timeout or other failure.
      93 - Configuration error  Continue transaction if risk manageable or retry 3DSecure procedure Service is disabled, invalid configuration, etc.
      94 - Input error  Continue transaction if risk manageable or retry 3DSecure procedure Merchant request had errors
      95 - No directory error Continue transaction if risk manageable No directory server found configured for PAN/card type.
      97 - Unable to locate live transaction  Continue transaction if risk manageable or retry 3DSecure procedure Unable to locate live transaction, too late or already processed.
      96 - No directory error Continue transaction if risk manageable No version 2 directory server found configured for PAN/card type and flow requires version 2 processing.
      99 - System error Continue transaction if risk manageable or retry 3DSecure procedure System error
      */
      if( (isset($validation_response->MPIToken) && $validation_response->MPIToken) )
        $response['mpi_token'] = sanitize_text_field($validation_response->MPIToken);
    }

    return $response;
  }

  /**
   * Return Borgun RPG payment method
   *
   * @param WC_Order $order WC_Order object
   * @param string $card_token Card token
   * 
   * @return array
   */
  public function get_borgun_rpg_payment_method($order, $card_token=''){
    if(empty($card_token)){
      return $order->get_meta('_' . $this->id . '_payment_method', true);
    }
    return ['type'=>'single', 'token'=>$card_token];
  }

  /**
   * Borgun RPG payment
   *
   * @param WC_Order $order WC_Order object
   * @param array $args Payment args
   * 
   * @return array
   */
  public function create_payment($order, $args, $secure = true) {
    $response = $payment_args = [];
    $payment_args['payment_method'] = (isset($args['payment_method'])) ? $args['payment_method'] : $this->get_borgun_rpg_payment_method($order, $args['token']);
    if($secure){
      if(isset($args['XId'])) $payment_args['XId'] = $args['XId'];
      if(isset($args['mpi_token'])) $payment_args['mpi_token'] = $args['mpi_token'];
      if(isset($payment_args['CAVV'])) $payment_args['CAVV'] = $args['CAVV'];

      $payment = $this->api->create_payment_with_3d_secure($order, $payment_args);
      WC_Gateway_Borgun_RPG::log( sprintf( __( 'Teya PRG - create_payment_with_3d_secure, response: %s', 'borgun_rpg' ), wc_print_r($payment, true) ) );
    }else{
      $payment = $this->api->create_payment($order, $payment_args['payment_method']);
      WC_Gateway_Borgun_RPG::log( sprintf( __( 'Teya PRG - create_payment, response: %s', 'borgun_rpg' ), wc_print_r($payment, true) ) );
    }

    if(isset($payment->TransactionStatus) && $payment->TransactionStatus == 'Accepted'){
      $response = array( 'success'=>true,
        'message'=>sprintf( __( 'Payment %s(%s)', 'borgun_rpg' ), $payment->TransactionStatus, $payment->TransactionId),
        'transaction_id'=>$payment->TransactionId
      );
    }else{
      $error_message = '';
      if(isset($payment->Message) && !empty($payment->Message) ){
        $error_message = __('Payment error: ', 'borgun_rpg') . sanitize_text_field($payment->Message);
      }elseif(isset($payment->error) && !empty($payment->error) ){
        $error_message = __('Payment error: ', 'borgun_rpg') . sanitize_text_field($payment->error);
      }
      if(empty($error_message))
        $error_message = sanitize_text_field($payment->TransactionStatus);
      $response = array( 'success'=>false, 'error'=>$error_message );
    }

    return $response;
  }
}

