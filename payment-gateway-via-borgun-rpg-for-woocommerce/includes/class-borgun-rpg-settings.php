<?php 
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Borgun_RPG_Helper{
  
  const ENDPOINT_TEST = 'https://test.borgun.is/rpg';
	const ENDPOINT_LIVE = 'https://ecommerce.borgun.is/rpg';
  
	private $testmode;
  private $public_key;
  private $private_key;
  private $merchant_id;
  private $use_3d_secure;

  public function __construct(){
    $plugin_options = get_option('woocommerce_borgun_rpg_settings');
    $this->testmode = (isset($plugin_options['testmode'])) ? $plugin_options['testmode'] : 'no';
    $this->public_key = (isset($plugin_options['publickey'])) ? $plugin_options['publickey'] : '';
    $this->private_key = (isset($plugin_options['privatekey'])) ? $plugin_options['privatekey'] : '';
    $this->merchant_id = (isset($plugin_options['merchantid'])) ? $plugin_options['merchantid'] : '';
    $this->use_3d_secure = (isset($plugin_options['enabled_3d_secure'])) ? $plugin_options['enabled_3d_secure'] : 'no';
  }

  public function getEndpoint($type,$id = ''){
    $endpoints = array(
      'payment' => '/api/payment',
      'multitoken' => '/api/token/multi',
      'refund_payment' => '/api/payment/'.$id.'/refund',
      'mpi_enrollment' => '/api/mpi/v2/enrollment',
      'mpi_validation' => '/api/mpi/v2/validation',
    );
    if($this->testmode == 'yes'){
      return self::ENDPOINT_TEST.$endpoints[$type];
    }
    return self::ENDPOINT_LIVE.$endpoints[$type];
  }

  public function getPublicKey(){
    return $this->public_key;
  }

  public function getPrivateKey(){
    return $this->private_key;
  }

  public function getCurrencyCode($key){
    $currencies = [
      'ISK' => 352,
      'GBP' => 826,
      'USD' => 840,
      'EUR' => 978,
      'DKK' => 208,
      'NOK' => 578,
      'SEK' => 752,
      'CHF' => 756,
      'CAD' => 124,
      'HUF' => 348,
      'BHD' => '048',
      'AUD' => '036',
      'RUB' => 643,
      'PLN' => 985,
      'RON' => 946,
      'HRK' => 191,
      'CZK' => 203,
    ];
    return $currencies[$key];
  }

  public function is_use_3d_secure(){
    $use_3d_secure = FALSE;
    if($this->use_3d_secure == 'yes'){
      $use_3d_secure = TRUE;
    }
    return $use_3d_secure;
  }

  public function getCardData($card_number,$card_expiry,$card_cvc){
    $data = array();
    $data['pan'] = str_replace(' ','',$card_number);
    $data_expiry = explode('/',$card_expiry);
    $data['month'] = trim($data_expiry[0]);
    $data['year'] = trim($data_expiry[1]);
    $data['cvc'] = trim($card_cvc);
    return $data;
  }

  public function getOrderId($order_id){
    $str = mb_strlen($order_id);
    $string = str_pad('WC',12-$str,'0');
    return $string.$order_id;
  }

  public function getPayload(){
    $payload = 'ANY';
    if($this->testmode == 'yes'){
      $payload = 'TESTING';
    }
    
    return $payload;
  }

  public function getIntentPaymentUrl($order_id){
    $checkout_url = wc_get_checkout_url();
    $checkout_url = substr($checkout_url, 0, strrpos($checkout_url, "/"));
    return add_query_arg(
      array(
        'order'=>$order_id
      ),
      $checkout_url . WC_AJAX::get_endpoint( 'wc_borgun_rpg_verify_intent' )
    );
  }
}