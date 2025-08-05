<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

require_once 'class-borgun-rpg-settings.php';

class Borgun_RPG_Api{

  private $settings;


  public function __construct(){
    $this->settings = new Borgun_RPG_Helper();
  }


  public function create_payment($order, $payment_method){
    $total = $order->get_total()*100;
    $total = (int)round($total, 0);

    $token_type = (isset($payment_method['type']) && $payment_method['type'] == 'multi') ? 'TokenMulti': 'TokenSingle';
    $token = (isset($payment_method['token'])) ? $payment_method['token']: '';

    $payment_data = (object)[
      "TransactionType" => "Sale",
      "Amount" => $total,
      "Currency" => $this->settings->getCurrencyCode($order->get_currency()),
      "TransactionDate"  => $order->get_date_created()->date('Y-m-dTH:i:s'),
      "OrderId"  =>  $this->settings->getOrderId($order->get_order_number()),
      "PaymentMethod" =>(object)[
        "PaymentType" => $token_type,
        "Token" => $token
      ],
      "Metadata" => (object)["Payload" => $this->settings->getPayload() ],
    ];

    $response = $this->request_post($payment_data,'payment');
    return $response;
  }

  public function create_payment_with_3d_secure($order, $args){

    $total = $order->get_total()*100;
    $total = (int)round($total, 0);

    $payment_method = $args['payment_method'];
    $token_type = (isset($payment_method['type']) && $payment_method['type'] == 'multi') ? 'TokenMulti': 'TokenSingle';
    $token = (isset($payment_method['token'])) ? $payment_method['token']: '';

    $payment_data = (object)[
      "TransactionType" => "Sale",
      "Amount" => $total,
      "Currency" => $this->settings->getCurrencyCode($order->get_currency()),
      "TransactionDate"  => $order->get_date_created()->date('Y-m-dTH:i:s'),
      "OrderId"  =>  $this->settings->getOrderId($order->get_order_number()),
      "PaymentMethod" =>(object)[
        "PaymentType" => $token_type,
        "Token" => $token,
      ],
      "Metadata" => (object)["Payload" => $this->settings->getPayload() ],
    ];

    if(isset($args['CAVV']) && !empty($args['CAVV'])){
      $payment_data->ThreeDSecure = (object)[
        "DataType" => "Manual",
        "SecurityLevelInd" => "2",
        "CAVV" => $args['CAVV'],
        "Xid" => $args['XId'],
      ];
    }else{
      $payment_data->ThreeDSecure = (object)[
        "DataType" => "Token",
        "MpiToken" => $args['mpi_token'],
        "Xid" => $args['XId'],
      ];
    }

    $response = $this->request_post($payment_data,'payment');
    return $response;
  }

  public function refund_payment($transaction_id,$amount,$reason = ''){
    $total = $amount*100;
    $total = (int)round($total, 0);
    $payment_data = (object)[
      'PartialAmount' => $total
    ];
    $response = $this->request_put($transaction_id, $payment_data, 'refund_payment');
    return $response;
  }

  private function request_post($data,$type){
    $response = wp_safe_remote_post(
      $this->settings->getEndpoint($type),
      array(
        'method'  => 'POST',
        'headers' => array('Authorization'=> 'Basic ' . base64_encode( $this->settings->getPrivateKey() . ':' ),'Content-Type' =>'application/json'),
        'body'    => json_encode($data),
        'timeout' => 70,
      )
    );

    return json_decode( $response['body'] );
  }

  private function request_put($transaction_id,$data,$type){
    $response = wp_remote_request(
      $this->settings->getEndpoint($type,$transaction_id),
      array(
        'method'  => 'PUT',
        'headers' => array('Authorization'=> 'Basic ' . base64_encode( $this->settings->getPrivateKey() . ':' ),'Content-Type' =>'application/json'),
        'body'    => json_encode($data),
        'timeout' => 70,
      )
    );

    if(!is_wp_error($response) && ($response['response']['code'] == 200 || $response['response']['code'] == 201)) {
      return json_decode($response['body']);
    }
    else {
      return false;
    }
  }

  public function create_multitoken($card_token){
    $token_data = (object)[
      "TokenSingle" => $card_token,
      "Metadata" => (object)["Payload" => $this->settings->getPayload()],
    ];
    $response = $this->request_post($token_data,'multitoken');
    return $response;
  }

  public function multitoken_info($card_token){
    $url = $this->settings->getEndpoint('multitoken').'/'.$card_token;
    $response = wp_safe_remote_get(
      $url,
      array(
        'headers' => array('Authorization'=> 'Basic ' . base64_encode( $this->settings->getPrivateKey() . ':' ),'Content-Type' =>'application/json'),
        'timeout' => 70,
        'body'    => '',
      )
    );

    return json_decode( $response['body'] );
  }

  public function disable_multitoken($card_token){
    $url = $this->settings->getEndpoint('multitoken').'/'.$card_token.'/disable';
    $response = wp_safe_remote_post(
      $url,
      array(
        'method'  => 'PUT',
        'headers' => array('Authorization'=> 'Basic ' . base64_encode( $this->settings->getPrivateKey() . ':' ),'Content-Type' =>'application/json', 'Content-Length'=>0),
        'timeout' => 70,
        'body'    => '',
      )
    );

    return json_decode( $response['body'] );
  }

  public function mpiEnrollment($order, $payment_method, $override_exponent = false) {
    $multiplier = 100;
    $exponent = 2;

    if( $override_exponent == true ) {
      $multiplier = 1;
      $exponent = 0;
    }

    // Set default exponent 0 if ISK
    $currency = $order->get_currency();
    if( $currency == 'ISK') {
      $multiplier = 1;
      $exponent = 0;

      if( $override_exponent == true ) {
        $multiplier = 100;
        $exponent = 2;
      }
    }

    $total = $order->get_total()*$multiplier;
    $total = (int)round($total, 0);

    $token_type = (isset($payment_method['type']) && $payment_method['type'] == 'multi') ? 'TokenMulti': 'TokenSingle';
    $token = (isset($payment_method['token'])) ? $payment_method['token']: '';

    $intent_payment_page_url = $this->settings->getIntentPaymentUrl($order->get_id());

    $payment_data = (object)[
      'CardDetails' => (object)[
        "PaymentType" => $token_type,
        "Token" => $token,
      ],
      "PurchAmount" => $total,
      "Exponent" => $exponent,
      "Currency" => $this->settings->getCurrencyCode( $order->get_currency() ),
      "TermUrl" => $intent_payment_page_url,
      "TDS2ThreeDSMethodNotificationURL" => $intent_payment_page_url
    ];

    $response = $this->request_post($payment_data,'mpi_enrollment');
    return $response;
  }

  public function secondMpiEnrollment($args){
    $api_args = (object)[
      'XId'=>$args['XId'],
      'TxId'=>$args['TxId'],
      'TDS2ThreeDSCompInd'=>"Y"
    ];
    $response = $this->request_post($api_args,'mpi_enrollment');
    return $response;
  }

  public function mpiValidation($args){
    $api_args = [];
    if(isset($args['PaRes']))
      $api_args['PARes'] = $args['PaRes'];

    if(isset($args['cres']))
      $api_args['cres'] = $args['cres'];

    if(isset($args['MD']))
        $api_args['MD'] = $args['MD'];

    $api_args = (object)$api_args;
    $response = $this->request_post($api_args, 'mpi_validation');

    return $response;
  }

  public function is_use_3d_secure(){
    return $this->settings->is_use_3d_secure();
  }
}
