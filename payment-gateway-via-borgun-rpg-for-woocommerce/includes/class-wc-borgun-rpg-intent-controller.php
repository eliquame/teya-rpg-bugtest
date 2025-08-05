<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/**
 * BorgunRPG_Intent_Controller class.
 *
 * Handles in-checkout AJAX calls, related to Payment Intents.
 */
class BorgunRPG_Intent_Controller {
  /**
   * Holds an instance of the gateway class.
   *
   * @var WC_Gateway_Borgun_RPG
   */
  protected $gateway;

  /**
   * Class constructor, adds the necessary hooks.
   *
   */
  public function __construct() {
    add_action( 'wc_ajax_wc_borgun_rpg_verify_intent', array( $this, 'verify_intent' ) );
  }

  /**
   * Handles successful Payment Intent authentications.
   *
   */
  public function verify_intent() {
    try {
      $order = $this->get_order_from_request();
    } catch ( WC_Data_Exception $e ) {
      $redirect_url = add_query_arg( array(
        'borgun-rpg-verification-failed' => true,
        'message' => $e->getMessage()
      ), wc_get_checkout_url() );
      wp_safe_redirect( $redirect_url );
      exit;
    }
    $gateway = $this->get_gateway( $order );
    $verify_intent = $gateway->verify_intent_after_checkout( $order );
    if( is_wp_error( $verify_intent ) ){
      $message = '';
      foreach ( $verify_intent->get_error_messages() as $error_message ) {
        if(!empty($message)) $message .= "\r\n";
        $message .= $error_message;
      }
      $redirect_url = add_query_arg( array(
        'borgun-rpg-verification-failed' => true,
        'message' => $message
      ), $order->get_checkout_payment_url( false ) );
      wp_safe_redirect( $redirect_url );
      exit;
    }elseif($verify_intent['success']){
      $redirect_url = esc_url_raw( wp_unslash($verify_intent['redirect_to']) );
      wp_safe_redirect( $redirect_url );
      exit;
    }else{
      $redirect_url = wc_get_checkout_url();
      wp_safe_redirect( $redirect_url );
      exit;
    }
  }

  /**
   * Loads the order from the current request.
   *
   * @throws WP_Error An exception if there is no order ID or the order does not exist.
   * @return WC_Order
   */
  protected function get_order_from_request() {

    // Load the order ID.
    $order_id = null;
    if ( isset( $_GET['order'] ) && absint( $_GET['order'] ) ) {
      $order_id = absint( $_GET['order'] );
    }

    $order = wc_get_order( $order_id );
    if ( ! $order ) {
      throw new WC_Data_Exception( 'missing-order', __( 'Missing order ID for payment confirmation', 'borgun_rpg' ), 400, array( 'order_id' => $order_id ));
    }
    if( $this->is_paid_order($order) ){
      throw new WC_Data_Exception( 'paid-order', __( 'Order is paid', 'borgun_rpg' ), 400, array( 'order_id' => $order_id ));
    }

    return $order;
  }

  /**
   * Returns an instantiated gateway.
   *
   * @param WC_Order $order
   * 
   * @return WC_Gateway_Borgun_RPG
   */
  protected function get_gateway( $order ) {
    if ( ! isset( $this->gateway ) ) {
      if ( $this->is_subscription_intent($order) ){
        $class_name = 'WC_Gateway_Borgun_RPG_Subscriptions';
      }else{
        $class_name = 'WC_Gateway_Borgun_RPG';
      }

      $this->gateway = new $class_name();
    }
    return $this->gateway;
  }

  protected function is_subscription_intent($order){
    $subscription = false;
    if ( class_exists( 'WC_Subscriptions_Order' ) && function_exists( 'wcs_get_subscriptions_for_order' ) ) {
      $order_id = $order->get_id();
      $subscriptions_ids = wcs_get_subscriptions_for_order( $order_id, array( 'order_type' => 'any' ) );
      if( !empty($subscriptions_ids) ){
        foreach( $subscriptions_ids as $subscription_id => $subscription_obj ){
          $parent_id = $subscription_obj->get_parent_id();
          $subscription_order = $subscription_obj->get_parent();
          if($parent_id == $order_id || $subscription_order->get_id() == $order_id){
            $subscription = true;
            break;
          }
        }
      }
    }

    return $subscription;
  }
  
  /**
   * Check if order is paid
   *
   * @param WC_Order $order
   * 
   * @return bool
   */
  protected function is_paid_order($order) {
    global $wpdb;
    $order_id = $order->get_id();
    $order_status = $wpdb->get_var( $wpdb->prepare( "SELECT post_status from $wpdb->posts WHERE ID =  %d", $order_id ) );
    return ($order->is_paid() || in_array( $order_status, wc_get_is_paid_statuses() ) ) ? true : false ;
  }
}

new BorgunRPG_Intent_Controller();
