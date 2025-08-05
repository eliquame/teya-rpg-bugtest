<?php
/*
 * Plugin Name: Payment gateway via Teya RPG for WooCommerce
 * Plugin URI: https://profiles.wordpress.org/tacticais/
 * Description: Extends WooCommerce with a <a href="https://docs.borgun.is/paymentgateways/bapi/" target="_blank">Teya RPG</a> gateway.
 * Version: 1.0.37
 * Author: Tactica
 * Author URI: http://tactica.is
 * Text Domain: borgun_rpg
 * Domain Path: /languages
 * Requires PHP: 7.0
 * Requires at least: 4.4
 * WC requires at least: 3.2.3
 * License: GNU General Public License v3.0
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

require_once 'includes/class-borgun-rpg-api.php';

define( 'BORGUN_RPG_VERSION', '1.0.37' );
define( 'BORGUN_RPG_URL', plugin_dir_url( __FILE__ ) );
define( 'BORGUN_RPG_DIR', plugin_dir_path( __FILE__ ) );


// Make sure WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) return;

/**
 * Declare plugin compatibility with WooCommerce HPOS.
 *
 */
add_action(
  'before_woocommerce_init',
  function() {
    if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
      \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
  }
);

add_action( 'plugins_loaded', 'wc_gateway_borgun_rpg_init', 11 );

function wc_gateway_borgun_rpg_init() {
  if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}
  require_once 'includes/class-wc-gateway-borgun-rpg.php';
  require_once 'includes/class-wc-borgun-rpg-intent-controller.php';
  if ( class_exists( 'WC_Subscriptions_Order' ) ) {
    require_once 'includes/class-wc-gateway-borgun-rpg-subscriptions.php';

    if(  empty( get_option( 'borgun-rpg-notice-dismissed' ) ) ) {
      add_action( 'admin_notices', 'borgun_rpg_admin_notice_warning' );
    }
	}

  add_filter( 'woocommerce_payment_gateways', 'wc_gateway_borgun_rpg_add_to_gateways' );
}

function wc_gateway_borgun_rpg_add_to_gateways( $gateways ) {
  if ( class_exists( 'WC_Subscriptions_Order' ) ) {
    $gateways[] = 'WC_Gateway_Borgun_RPG_Subscriptions';
  } else {
    $gateways[] = 'WC_Gateway_Borgun_RPG';
  }
  return $gateways;
}

function borgun_rpg_admin_notice_warning(){
  ?>
  <div class="notice notice-warning  borgun-rpg-notice is-dismissible">
      <p><?php _e( 'WooCommerce Subscriptions uses WordPress’s built-in <a href="https://codex.wordpress.org/Category:WP-Cron_Functions" target="_blank">WP-Cron</a> scheduling system for scheduling payment related tasks.'); ?></p>
      <p><?php _e( 'For popular sites, the accuracy of WP-Cron’s scheduling service is not a problem. However, sites with less traffic may find scheduled payments are charged at an unacceptable length of time after it was due.', 'borgun_rpg' ); ?></p>
      <p><?php _e( 'Create a manual cron job by following the instructions on this <a href="//wp.tutsplus.com/articles/insights-into-wp-cron-an-introduction-to-scheduling-tasks-in-wordpress/">WP Tuts+ tutorial</a>. Set it to trigger every 10 seconds. This is a more reliable and flexible solution, but also more difficult to implement.', 'borgun_rpg' ); ?></p>
  </div>
  <?php
}

add_action( 'wp_ajax_nopriv_get_borgun_data', 'get_borgun_data' );
add_action( 'wp_ajax_get_borgun_data', 'get_borgun_data' );
function get_borgun_data() {
    $response = array(
      'status' => '',
      'message' => '',
    );
    if( !empty($_POST['data'])) {
      $args = [];
      parse_str($_POST['data'], $data);

      $user_id = get_current_user_id();
      if( !wp_verify_nonce($data['nonce'], 'borgun_ajax')){
        $response = array(
            'status' => 'error',
            'message' => __( 'Unexpected error.', 'tactica-customer-portal' ),
          );
        echo json_encode( $response );
        die();
      }

      $order_id = ( isset($data['orderID']) ) ? (int)$data['orderID'] : null;
      if($order_id){
        $order = wc_get_order($order_id);
        $borgun_tds_method = $order->get_meta('borgun_tds_enrollment', true);
        $wc_logger = wc_get_logger();
        if(!empty($borgun_tds_method) && !empty($order)){
          $order->delete_meta_data('borgun_tds_enrollment');
          $api = new Borgun_RPG_Api();
          $wc_logger->log( 'info', sprintf( __( 'Teya PRG - secondMpiEnrollment, request: %s', 'borgun_rpg' ), wc_print_r($borgun_tds_method, true) ), array( 'source' => 'borgun_rpg' ) );
          $api_response = $api->secondMpiEnrollment($borgun_tds_method);
          $wc_logger->log( 'info', sprintf( __( 'Teya PRG - secondMpiEnrollment, response: %s', 'borgun_rpg' ), wc_print_r($api_response, true) ), array( 'source' => 'borgun_rpg' ) );
          if( !empty($api_response) && isset( $api_response->RedirectToACSForm ) && !empty($api_response->RedirectToACSForm) ){
            $html = sanitize_text_field($api_response->RedirectToACSForm);
            $order->update_meta_data( 'borgun_secure_form', $html);
            $response['status'] = 'success';
          }else{
            $response['status'] = 'error';

            $error_message = '';
            if(isset($api_response->Message)){
               $error_message = $api_response->Message;
            }elseif( isset($api_response->MdErrorMessage) ){
              $error_message = $api_response->MdErrorMessage;
            }
            if(!empty($error_message)){
              $error_message = __('TDS2 3DS: ', 'borgun_rpg') . $error_message;
            }else{
              $error_message = __('TDS2 3DSecure failed', 'borgun_rpg');
            }
            $response['message'] = $error_message;
            $order->add_order_note( $error_message );
            $order->update_status( 'failed' );
          }
          $order->save();
        }else{
          $wc_logger->log( 'info', __( 'Teya PRG - secondMpiEnrollment canceled -empty args', 'borgun_rpg' ), array( 'source' => 'borgun_rpg' ) );
        }
      }
    }

    echo json_encode( $response );
    die();
}

add_action( 'admin_enqueue_scripts', 'borgun_rpg_admin_assets' );
function borgun_rpg_admin_assets() {
  wp_enqueue_script( 'borgun-rpg-notice-update', BORGUN_RPG_URL .'assets/admin/js/notice_update.js', array( 'jquery' ), BORGUN_RPG_VERSION);
}

add_action( 'wp_enqueue_scripts', 'borgun_rpg_assets' );
function borgun_rpg_assets() {
  if (function_exists('is_woocommerce')){
    if( is_checkout() || is_checkout_pay_page() ){
      wp_register_style( 'borgun-rpg-styles', BORGUN_RPG_URL . 'assets/css/styles.css', [], BORGUN_RPG_VERSION);
      wp_enqueue_style( 'borgun-rpg-styles' );
    }
  }
}

add_action( 'switch_theme', 'borgun_rpg_notice_reset' );
function borgun_rpg_notice_reset() {
    delete_option( 'borgun-rpg-notice-dismissed' );
}

add_action("wp_ajax_borgun_rpg_notice_dismiss", "borgun_rpg_notice_dismiss");
function borgun_rpg_notice_dismiss(){
  update_option( 'borgun-rpg-notice-dismissed', 1);
}

add_action( 'woocommerce_blocks_loaded', 'borgun_rpg_woocommerce_blocks_support' );
function borgun_rpg_woocommerce_blocks_support() {
  if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {

    require_once BORGUN_RPG_DIR . 'includes/class-wc-gateway-borgun-rpg-registration.php';
    add_action(
      'woocommerce_blocks_payment_method_type_registration',
      function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
        $payment_method_registry->register( new PaymentMethodBorgunRPG );
      }
    );
  }
}