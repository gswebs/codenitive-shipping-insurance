(function ($) {
	'use strict';
	$(document.body).on('change', 'input[name="codenitive_shipping_insurance"]', function () {
		var enabled = this.checked ? 'yes' : 'no';
		var $checkboxes = $('input[type="checkbox"][name="codenitive_shipping_insurance"]');

		// CheckoutWC keeps desktop and mobile summaries in the same checkout form.
		// Synchronize both copies before CheckoutWC serializes the form.
		$checkboxes.prop('checked', enabled === 'yes');

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
			enabled: enabled
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
