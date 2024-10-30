setTimeout(function () {
  (function( $ ) {
    'use strict';

    $(document).on("keyup", "#search-crypto-ivendpay", function() {
      var value = this.value.toLowerCase().trim();

      if (value.length) {
        $('#listCoins .form__radio.hidden-coins').show();
        $('.showMoreCoinsIvendPay').addClass('no-clicked');

        $("#listCoins .form__radio:not(.bpay)").show().filter(function() {
          return $(this).text().toLowerCase().trim().indexOf(value) == -1;
        }).hide();
      } else {
        $("#listCoins .form__radio").show();
        $('#listCoins .form__radio.hidden-coins').hide();
        $('.showMoreCoinsIvendPay').removeClass('no-clicked');
        $("#listCoins .form__radio.hidden-coins input").each(function() {
          let $elem = $(this);
          if ($elem.is(':checked')) {
            $elem.closest('.form__radio').show();
          }
        });
      }
    });

    $(document).on('click', '.showMoreCoinsIvendPay:not(.no-clicked)', function() {
      if ($(this).data('action') === 'show') {
        $('#listCoins .form__radio.hidden-coins').show();
      } else {
        $('#listCoins .form__radio.hidden-coins').hide();
      }

      $('.showMoreCoinsIvendPay').toggle();
      $("#listCoins .form__radio.hidden-coins input").each(function() {
        let $elem = $(this);
        if ($elem.is(':checked')) {
          $elem.closest('.form__radio').show();
        }
      });
    });

  })( jQuery );

}, 3000);
