<?php

if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly
}

if ( ! class_exists( 'WC_Gateway_Borgun_RPG_Subscriptions' ) ) {
  class WC_Gateway_Borgun_RPG_Subscriptions extends WC_Gateway_Borgun_RPG {
    function __construct() {
      parent::__construct();

      add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, array( $this, 'scheduled_subscription_payment' ), 10, 2 );
      add_filter( 'woocommerce_subscription_payment_meta', array( $this, 'add_subscription_payment_meta' ), 10, 2 );
      add_action( 'woocommerce_scheduled_subscription_expiration', array( $this, 'borgun_rpg_subscription_expiration' ), 10, 1);
    }

    /**
     * Process the payment and return the result
     *
     * Get and update the order being processed
     * Return success and redirect URL (in this case the thanks page)
     *
     * @access public
     * @param  int $order_id
     *
     * @return array
     */
    public function process_payment( $order_id ) {
      $subscriptions = wcs_get_subscriptions_for_order( $order_id, array( 'order_type' => 'any' ) );
      if ( !empty($subscriptions) ) {
        $card_token = sanitize_text_field( $_POST['borgun-rpg-card-token'] );
        $multitoken_response = $this->api->create_multitoken($card_token);
        $multitoken = (isset($multitoken_response->Token) && $multitoken_response->Token ) ? sanitize_text_field($multitoken_response->Token) : '' ;
        $payment_method = ['type'=>'multi', 'token'=>$multitoken];

        foreach ( $subscriptions as $subscription ) {
          $subscription->update_meta_data('_' . $this->id . '_token_transaction_id', $multitoken);
          $subscription->save();
        }

        $order = wc_get_order( $order_id );
        $order->update_meta_data('_' . $this->id . '_token_transaction_id', $multitoken);
        $order->save();
      }
      return parent::process_payment( $order_id );
    }

    /**
     * Subscription payment meta
     *
     * @param array $payment_meta
     * @param $subscription WC_Subscription
     *
     * @return array
     */
    public function add_subscription_payment_meta( $payment_meta, $subscription ) {
      $value = $subscription->get_meta('_' . $this->id . '_token_transaction_id', true);
      $payment_meta[ $this->id ] = array(
        'post_meta' => array(
          '_borgun_rpg_token_transaction_id' => array(
            'value' => $value,
            'label' => __( 'Teya RPG Token Transaction ID', 'borgun_rpg' ),
            'disabled' => (!empty($value)) ? true : false 
          ),
        ),
      );

      return $payment_meta;
    }

    /**
     * Return Borgun RPG subsctiption payment method
     *
     * @param WC_Order $order WC_Order object
     * @param string $card_token Card token
     *
     * @return array
     */
    public function get_borgun_rpg_payment_method( $order, $card_token ='' ) {
      $subscriptions = $this->get_subscriptions_from_order( $order );
      if(!empty($subscriptions)){
        $token = $order->get_meta('_' . $this->id . '_token_transaction_id', true);
        if(empty($token)){
          foreach( $subscriptions as $subscription_id => $subscription_obj ){
            $token = $subscription_obj->get_meta('_' . $this->id . '_token_transaction_id', true);
            if(!empty($token)) break;
          }
        }
        return (!empty($token)) ? ['type'=>'multi', 'token'=>$token] : [];
      }

      return parent::get_borgun_rpg_payment_method( $order, $card_token );
    }

    /**
     * Return order subscriptions
     *
     * @param WC_Order $order WC_Order object
     *
     * @return mixed
     */
    protected function get_subscriptions_from_order( $order ) {
      if ( $this->order_contains_subscription( $order ) ) {
        $subscriptions = wcs_get_subscriptions_for_order( $order, array('order_type'=>'any') );
        if ( $subscriptions ) {
          return $subscriptions;
        }
      }
      return false;
    }

    /**
     * Check if the order includes a subscription
     *
     * @param WC_Order $order WC_Order object
     *
     * @return bool
     */
    protected function order_contains_subscription( $order ) {
      return function_exists( 'wcs_order_contains_subscription' ) && ( wcs_order_contains_subscription( $order, ['any']) );
    }

    /**
     * Scheduled subscription payment action callback
     *
     * @param float $amount_to_charge    Order total
     * @param WC_Order $order    WC_Order object
     *
     * @return void
     */
    public function scheduled_subscription_payment( $amount_to_charge, $order ) {
      $result = $this->process_subscription_payment( $order, $amount_to_charge );
      if(isset($result->error)){
        $order->add_order_note( sprintf( __( 'Teya subscription renewal failed - %s', 'borgun_rpg' ), $result->error) );
      }
      elseif(isset($result->Message)){
        $order->add_order_note( sprintf( __( 'Teya subscription renewal payment response - %s', 'borgun_rpg' ), $result->Message) );
      }
      else{
        if(isset($result->TransactionId)){
          if($result->TransactionStatus != 'Accepted'){
            $order->add_order_note( sprintf( __( 'Teya subscription renewal failed. TransactionStatus - %s', 'borgun_rpg' ), $result->TransactionStatus ) );
          }
          else{
            $order->add_order_note( sprintf( __( 'Subscription renewed. Transaction Id: %s', 'borgun_rpg' ), esc_attr($result->TransactionId) ) );
            $order->update_status( 'processing' );
          }
        }
      }
    }

    /**
     * Teya rpg subscription payment
     *
     * @param float $amount_to_charge    Order total
     * @param WC_Order $order    WC_Order object
     *
     * @return void
     */
    public function process_subscription_payment( $order = '', $amount = 0 ) {
      $payment_method = $this->get_borgun_rpg_payment_method( $order );
      if (empty($payment_method)) {
        WC_Gateway_Borgun_RPG::log( sprintf( __( 'Teya PRG - Subscription: %s', 'borgun_rpg' ), 'Token Transaction ID not found' ) );
        return new WP_Error( 'borgun_error', __( 'Token Transaction ID not found', 'borgun_rpg' ) );
      }

      try {
        WC_Gateway_Borgun_RPG::log( sprintf( __( 'Teya PRG - scheduled_subscription_payment', 'borgun_rpg' ) ) );
        $response = $this->api->create_payment($order, $payment_method);
        WC_Gateway_Borgun_RPG::log( sprintf( __( 'Teya PRG - scheduled_subscription_payment, response: %s', 'borgun_rpg' ), wc_print_r($response, true) ) );
        return $response;
      } catch ( Exception $e ) {
        WC_Gateway_Borgun_RPG::log( sprintf( __( 'Teya PRG - Subscription Payment error: %s', 'borgun_rpg' ), wc_print_r($e, true) ) );
        return new WP_Error( 'borgun_error', $e->getMessage() );
      }
    }

    /**
     * WC subscription expiration callback
     *
     * @param int $subscription_id    Subscription ID
     *
     * @return void
     */
    public function borgun_rpg_subscription_expiration($subscription_id) {
      $subscription = wcs_get_subscription( $subscription_id );
      if(!$subscription) return;

      $token = $subscription->get_meta('_borgun_rpg_token_transaction_id', true);
      if(empty($token)) return;

      $response = $this->api->disable_multitoken($token);
    }
  }
}
