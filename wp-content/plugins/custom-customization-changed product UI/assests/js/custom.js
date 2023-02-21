(function (jQuery) {

    function addValidationEvent(e, $thisbutton, isVariation = false) {
  
      if ($thisbutton.data('allowed')) {
        $thisbutton.data('allowed', false);
        return true;
      }

      var data = {};
  
      // Fetch changes that are directly added by calling $thisbutton.data( key, value )
      jQuery.each($thisbutton.data(), function (key, value) {
        data[key] = value;
      });
  
      // Fetch data attributes in $thisbutton. Give preference to data-attributes because they can be directly modified by javascript
      // while `.data` are jquery specific memory stores.
      jQuery.each($thisbutton[0].dataset, function (key, value) {
        data[key] = value;
      });
  
      // Adding variation ID for variation only because dataset is given preference
      if (isVariation && !data['variation_id'] && $thisbutton.data('variation_id')) {
        data['variation_id'] = $thisbutton.data('variation_id');
      }
      // Trigger event.
      // jQuery( document.body ).trigger( 'adding_to_cart', [ $thisbutton, data ] );
  
      data['action'] = 'validate_product_before_add_to_cart';
  
  
      jQuery.ajax({
        type: 'POST',
        url: bbCustomObject.ajaxURL,
        data: data,
        success: function (response) {
  
          // Point to Non-Point product transition
          if (response.status === true) {
            $thisbutton.data('allowed', true);
            $thisbutton.trigger('click');
            return;
          }
  
          var type = response.type || "";
          switch (type) {
            // Not enough blance 
            case 1:
              swal({
                // text: response.message,
                icon: "info",
                className: "ids-custom-swal",
                content: {
                  element: 'div',
                  attributes: {
                    innerHTML: response.message,
                  },
                }
              });
  
              isVariation ? $thisbutton.removeClass('loading') : jQuery(document.body).trigger('ajax_request_not_sent.adding_to_cart', [false, false, $thisbutton]);
              break;
      
            case 2:
              if (swal({
                  title: "Are you sure?",
                  text: response.message,
                  icon: "info",
                  buttons: true,
                  sucessMode: true,
                  className: "ids-custom-swal",
                })
                .then((willDelete) => {
                  if (willDelete) {
                    $thisbutton.data('allowed', true);
                    $thisbutton.trigger('click');
                  } else {
                    $thisbutton.removeClass('loading');
                    // swal("Your selected product is cancelled");
                  }
                }));
              break;
  
            default:
              $thisbutton.data('allowed', true);
              $thisbutton.trigger('click');
              break;
          }
  
        },
        error: function (e) {
          $thisbutton.data('allowed', true);
          $thisbutton.trigger('click');
        },
        dataType: 'json'
      });
  
  
      if (!isVariation) {
        function showLoder() {
          $thisbutton.removeClass('added');
          $thisbutton.addClass('loading');
          jQuery(document.body).off('ajax_request_not_sent.adding_to_cart', showLoder);
        }
        jQuery(document.body).on('ajax_request_not_sent.adding_to_cart', showLoder);
      }
  
      return false;
    }
  
  
    jQuery(document).on('wc_variation_form.wvs', '.variations_form:not(.wvs-pro-loaded)', function (event, form) {
      jQuery(this).closest('.custom-wrapping').find('.wvs_add_to_cart_button').on('click', function (event) {
        $value = addValidationEvent(event, jQuery(this), true);
        if ($value !== true) {
          event.preventDefault(); // Don't move it
          event.stopPropagation(); // Don't move it
          event.stopImmediatePropagation();
          jQuery(this).removeClass('added');
          jQuery(this).addClass('loading');
        }
        return false;
      });
    });
  

    jQuery(document).ready(function () {
      
      function showMessage(message,loadingClass, icontype) {
        return  swal({
          html: message,
          icon: icontype,
          className: "ids-custom-swal",
          button: true,
          content: {
            element: 'div',
            attributes: {
              innerHTML: message,
            },
          }
        }).then((willDelete) => {
            jQuery(loadingClass).removeClass('loading')
        });
      }
  
  
      function enrollTicket() {
        var currentElem = jQuery(this);
        var ticketID = currentElem.attr('data-ticket-id');
  
        currentElem.addClass('loading');
        jQuery.ajax({
          type: 'POST',
          url: bbCustomObject.ajaxURL,
          datType: 'json',
          data: {
            'action': '_ids_enroll_tickets',
            'ticketID': ticketID
          },
        }).then(function (response) {
          currentElem.removeClass('loading');
          var icontype = 'success';
          if (!response.status) {
            return showMessage(response.message);
          }
  
          var closestTr = currentElem.closest('tr');
          closestTr.find('[data-name="ticket"]').html(response.data.name);
          closestTr.find('[data-name="expiry-date"]').html(response.data.expiryDate);
          closestTr
            .find('[data-name="status"]')
            .html(response.data.buttonHTML);
  
          closestTr
            .find('.ticket-btn')
            .bind('click', showTicketMessage)
          return showMessage(response.message, 'success',icontype);
        }).catch(function (err) {
          currentElem.removeClass('loading');
          if (err) {
            console.log(err);
            swal("Oh noes!", "Sorry something went wrong! Try again.", "error");
          } else {
            swal.stopLoading();
            swal.close();
          }
        });
      }
  
      function showTicketMessage() {
        var status = jQuery(this).attr('data-ticket-status');
        var message = jQuery(this).parent().find('.message-info').html();
        var icontype = 'info';
        var loadingClass = '.ticket-'+status.toLowerCase();
        jQuery(this).addClass('loading');
        return showMessage(message, loadingClass, icontype);
      }
  
      jQuery('.ticket-raffle-ended, .ticket-active, .ticket-winning, .ticket-redeemed').click(showTicketMessage);
      jQuery('.ticket-enroll').click(enrollTicket);
  
      jQuery(document.body).on('should_send_ajax_request.adding_to_cart', addValidationEvent);
      jQuery('#EnrollForRaffle').click(function () {
        jQuery('#EnrollForRaffle').addClass('loading');
        jQuery.ajax({
          type: 'POST',
          url: bbCustomObject.ajaxURL,
          data: {
            'action': '_ids_fetch_tickets'
          },
          success: function (response) {
            jQuery('#EnrollForRaffle').removeClass('loading');
            if (!response.status) { 
              var icontype = 'info';
              return showMessage(response.message,'',icontype);
            }
  
            var slider = document.createElement("select");
            slider.innerHTML = "";
            jQuery.each(response.data, function (i, info) {
              slider.innerHTML += '<option value="' + info.value + '">  ' + info.label + '</option>';
            });
            swal({
                text: 'SELECT YOUR TICKET',
                content: slider,
                buttons : ["cancel", "Enroll!"],
              })
            .then(name => {
              if(name){
                var value = jQuery(slider).val();
                if (!value) throw null;
                return jQuery.ajax({
                  type: 'POST',
                  url: bbCustomObject.ajaxURL,
                  datType: 'json',
                  data: {
                    'action': '_ids_enroll_tickets',
                    'ticketID': jQuery(slider).val()
                  },
                });
              }
            })
            .then(response => {
              var icontype = 'success';
              if (!response.status) {
                var icontype = 'info';
                return showMessage(response.message,icontype);
              }
              return showMessage(response.message, 'success',icontype);
            })
            .catch(err => {
              if (err) {
                var message = 'Submission of your ticket is cancelled!';
                var icontype = 'info';
                return showMessage(message,'',icontype);
              } else {
                swal.stopLoading();
                swal.close();
              }
            });
          },
          error: function (er) {
            console.log('Error', er);
          },
        });
      });

      // To highlight reward section on redirect reward page text 'more about point'
      function highlightElement(elementTohighlight) {
        var opacity = 100;
        var color = "221, 128, 255" // has to be in this format since we use rgba
        var interval = setInterval(function() {
          opacity -= 3;
          if (opacity <= 0) clearInterval(interval);
          jQuery(elementTohighlight).css({background: "rgba("+color+", "+opacity/100+")"});
        }, 50)
      }

      var urlParam = window.location.href;
      if(urlParam.includes('?what-are-points')){
        jQuery("html, body").animate({ scrollTop: 300 }, 500);
        elementTohighlight = '#ids-custom-points-info, #ids-custom-point-star';
        highlightElement(elementTohighlight);
      }


      // let searchParams = new URLSearchParams(window.location.search)
      //  var UrlticketCode  = searchParams.get('ticketCode');
      // if(UrlticketCode){
      //   jQuery("td:contains("+UrlticketCode+")").attr('id', 'highLightTicketCode');
      //   elementTohighlight = '#highLightTicketCode';
      //   jQuery("html, body").animate({
      //     scrollTop: jQuery(elementTohighlight).offset().top,
      //     scrollLeft: jQuery(elementTohighlight).offset().left
      //   }, 2500);
      //   highlightElement(elementTohighlight);
      // }

      jQuery('a').click(function(event) {
          var idCheck = jQuery(this).attr('id');
          if (idCheck == 'bb-not-enough-balance-for-reorder') {
            jQuery(this).addClass('loading');
            event.preventDefault();
            var message = 'you dont have enough point balance to order again with same items';
            var loadingClass = '.button';
            var icontype = 'info';
            return showMessage(message, loadingClass,icontype);
          } 
      });
      
      // Adding function to turn off notifications until session not reset 
      jQuery('.woocommerce-info ').click(function() {  
        var type = jQuery(this).attr('data-__idsNoticeType');
        if(!type){
          return;
        }
        jQuery.ajax({ 
          type: 'POST',
          url: bbCustomObject.ajaxURL,
          data: {
            action: '_ids_turnOffNotification',
            type: type
          }
        });
      });
    });

  })(jQuery);

//To change price on quantity change
(function($){
  function addToCartPricing(){
    itemQty = $('.qty[name=quantity]').val();
    productPrice = $('.single_add_to_cart_button').attr('data-attr-price') || 0;
    if($('*').hasClass('bb_single_page_point_based_product') ){
      $('.bb-price').html('<span class="price">'+ (itemQty * productPrice) + ' POINTS' + '</span>');
    }else{
      $('.bb-price').html('<span class="price"> $'+ (itemQty * productPrice).toFixed(2) + '</span>');
    }
    // $('.woocommerce-Price-amount, .bb-price').html('<span class="price">$' + (itemQty * productPrice).toFixed(2) + '</span>');

  }

  $(document).on('change', '.qty[name=quantity]', addToCartPricing);


  $(document).on('found_variation', 'form.variations_form', function(event, variation) {
      $('.single_add_to_cart_button').attr('data-attr-price', variation.display_price)
      // if (itemQty <= 1) {
      //     return $('.single_add_to_cart_button .product-price').html(variation.price_html);
      // }
      addToCartPricing();
  });
}) (jQuery);  