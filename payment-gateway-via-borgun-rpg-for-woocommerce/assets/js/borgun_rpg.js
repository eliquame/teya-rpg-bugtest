jQuery( function( $ ) {
	'use strict';
  $(document).ready(function(){
      $('body').on('click' ,'#place_order' ,function(event) {
        if($('#payment_method_borgun_rpg').is(":checked")){
          event.preventDefault();
          var hasErrors = false;
          $("div.error").hide();
          $("ul.error-message").html('');

          var publicToken = borgun_data.key;
          var pan_value = $('#borgun_rpg-card-number').val();
          var pan = pan_value.replace(/\s+/g, '');
          var expiry_value = $('#borgun_rpg-card-expiry').val();
          var expiry = expiry_value.split('/');
          var expMonth = '00';
          if(0 in expiry){
            expMonth = expiry[0].replace(/\s+/g, '');
          }
          var expYear = '00';
          if(1 in expiry){
            expYear = expiry[1].replace(/\s+/g, '');
          }
          var cvc = $('#borgun_rpg-card-cvc').val();
          BAPIjs.setPublicToken(publicToken);

          // Preemptive input validation.
          if (BAPIjs.isValidCardNumber(pan) === false) {
              $("ul.error-message").append('<li>Invalid card number</li>');
              hasErrors = true;
          }
          if (BAPIjs.isValidExpDate(expMonth, expYear) === false) {
              $("ul.error-message").append('<li>Invalid expiration date</li>');
              hasErrors = true;
          }
          if (BAPIjs.isValidCVC(cvc) === false) {
              $("ul.error-message").append('<li>Invalid cvc number</li>');
              hasErrors = true;
          }

          if (hasErrors) {
              $("div.error").show();
          }
          else{
            var borgunResponseHandler = function(status, data) {
                if (status.statusCode === 201) {
                    // OK
                    var token = data.Token,
                        $form = $('form.checkout');

                    if(!$form.length)
                      $form = $('form#order_review');

                    $form.find('#borgun-rpg-card-token').val(data.Token);
                    $form.submit();
                } else if (status.statusCode === 401) {
                    // Unauthorized
                    $("ul.error-message").append('<li>Unauthorized received from TeyaPaymentAPI</li>');
                    $("div.error").show();
                } else if (status.statusCode) {
                    $("ul.error-message").append('<li>Error received from server ' + status.statusCode + ' - ' + status.message + '.</li>');
                    $("div.error").show();
                } else {
                    $("ul.error-message").append('<li>Unable to connect to server ' + status.message + '.</li>');
                    $("div.error").show();
                }
            };

            BAPIjs.getToken({
                'pan': pan,
                'expMonth': expMonth,
                'expYear': expYear,
                'cvc': cvc,
                'tokenLifetime': 900
            }, borgunResponseHandler);
          }
        }
      });

     var $tdsIframe = $('.borgun-rpg-tds');
     if($tdsIframe.length){
        var loader = '<span class="loader"><svg version="1.1" id="loader-1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" width="40px" height="40px" viewBox="0 0 50 50" style="enable-background:new 0 0 50 50;" xml:space="preserve"> <path fill="#000" d="M43.935,25.145c0-10.318-8.364-18.683-18.683-18.683c-10.318,0-18.683,8.365-18.683,18.683h4.068c0-8.071,6.543-14.615,14.615-14.615c8.072,0,14.615,6.543,14.615,14.615H43.935z"><animateTransform attributeType="xml" attributeName="transform" type="rotate" from="0 25 25"  to="360 25 25" dur="0.9s" repeatCount="indefinite"/></path></svg></span>';
       $tdsIframe.append(loader);
       setTimeout(function(){
          var request_data = {
            'action' : 'get_borgun_data',
            'data' : 'orderID=' + borgun_data.order_id + '&nonce=' + borgun_data.nonce
          }
          $.ajax({
            url: borgun_data.ajax_url,
            type: "POST",
            dataType: 'json',
            data: request_data,
            success: function(  response ) {
              if(response['status'] == "success"){
                location.reload();
              }else if(response['status'] == "error"){
                if(response['message']){
                  $('.woocommerce-notices-wrapper').append('<ul class="woocommerce-error" role="alert"><li>' + response['message'] + '</li></ul>');
                }
                $('.borgun-rpg-tds').remove();
              }
            }
          });
       }, borgun_data.ajax_delay);
     }
  })
});
