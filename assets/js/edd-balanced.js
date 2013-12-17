/*global document: false */
/*global $, jQuery, balanced */

var edd_global_vars, form$, error, token, state, error_text, uid;


function edd_balanced_response_handler(response) {

    if (response.status != 201) {
        // Re-enable the submit button
        jQuery('#edd_purchase_form #edd-purchase-button').attr("disabled", false);

		if (typeof response.error.description == 'undefined' ) {
			error_text = 'An unknown error occurred.';
		} else {
			error_text = response.error.description;
		}

        error = '<div class="edd_errors"><p class="edd_error">' + error_text + '</p></div>';

        // Show the errors on the form
        jQuery('#edd-balanced-payment-errors').html(error);

        jQuery('.edd-cart-ajax').hide();

        if (edd_global_vars.complete_purchase) {
            jQuery('#edd-purchase-button').val(edd_global_vars.complete_purchase);
        } else {
            jQuery('#edd-purchase-button').val('Purchase');
        }

    } else {
        form$ = jQuery('#edd_purchase_form');

        token = response.data.uri;
		uid = response.data.id;

        jQuery('#edd_purchase_form #edd_cc_fields input[type="text"]').each(function () {
            jQuery(this).removeAttr('name');
        });

        // Insert the token into the form
        form$.append('<input type="hidden" name="balancedToken" value="' + token + '" />');
        form$.append('<input type="hidden" name="balancedUID" value="' + uid + '" />');

        // Submit
        form$.get(0).submit();
    }
}


function edd_balanced_process_card() {

    // Disable the submit button
    jQuery('#edd_purchase_form #edd-purchase-button').attr('disabled', 'disabled');

    if (jQuery('.billing-country').val() === 'US') {
        state = jQuery('#card_state_us').val();
    } else if (jQuery('.billing-country').val() === 'CA') {
        state = jQuery('#card_state_ca').val();
    } else {
        state = jQuery('#card_state_other').val();
    }

    if (jQuery('#card_state_us').val() !== 'undefined') {

        if (jQuery('.billing-country').val() === 'US') {
            state = jQuery('#card_state_us').val();
        } else if (jQuery('.billing-country').val() === 'CA') {
            state = jQuery('#card_state_ca').val();
        } else {
            state = jQuery('#card_state_other').val();
        }
    } else {
        state = jQuery('.card_state').val();
    }

    var creditCardData = {
        card_number:            jQuery('.card-number').val(),
        expiration_month:       jQuery('.card-expiry-month').val(),
        expiration_year:        jQuery('.card-expiry-year').val(),
        security_code:          jQuery('.card-cvc').val()
    };

    // createToken returns immediately - the supplied callback submits the form if there are no errors
    balanced.card.create(creditCardData, edd_balanced_response_handler);

    return false; // Submit from callback
}


jQuery(document).ready(function ($) {
    // non ajaxed
    $('body').on('submit', '#edd_purchase_form', function (event) {
        if ($('input[name="edd-gateway"]').val() === 'balanced') {
            event.preventDefault();
            edd_balanced_process_card();
        }
    });
});
