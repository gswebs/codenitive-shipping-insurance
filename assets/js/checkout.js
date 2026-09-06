(function ($) {
	'use strict';
	$(document.body).on('change', 'input[name="codenitive_shipping_insurance"], input[name="codenitive_shipping_insurance_choice"]', function () {
		var isChoice = this.name === 'codenitive_shipping_insurance_choice';
		var enabled = !isChoice && this.checked ? 'yes' : 'no';
		var choice = isChoice ? this.value : '';
		var $checkboxes = $('input[type="checkbox"][name="codenitive_shipping_insurance"]');
		var $choices = $('input[type="radio"][name="codenitive_shipping_insurance_choice"]');

		// CheckoutWC keeps desktop and mobile summaries in the same checkout form.
		// Synchronize both copies before CheckoutWC serializes the form.
		if (isChoice) {
			$choices.prop('checked', false).filter(function () {
				return this.value === choice;
			}).prop('checked', true);
		} else {
			$checkboxes.prop('checked', enabled === 'yes');
		}

		if (!window.codenitiveInsurance) {
			return;
		}

		$('.codenitive-insurance').addClass('is-loading');

		if (!codenitiveInsurance.isCart) {
			// CheckoutWC and the native WooCommerce checkout both serialize the
			// checkout form when this event runs. PHP saves the selection before
			// either checkout calculates its new totals.
			$(document.body).trigger('update_checkout');
			return;
		}

		$.post(codenitiveInsurance.ajaxUrl, {
			nonce: codenitiveInsurance.nonce,
			enabled: enabled,
			choice: choice
		}).done(function () {
			window.location.reload();
		}).fail(function () {
			$('.codenitive-insurance').removeClass('is-loading');
		});
	});

	$(document.body).on('updated_checkout', function () {
		$('.codenitive-insurance').removeClass('is-loading');
	});
})(jQuery);
