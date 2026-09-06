(function ($) {
	'use strict';

	var $form = $('#csi-settings-form');
	var $rows = $('#csi-coverage-rows');

	function activateTab(tab) {
		$('.csi-tab').removeClass('is-active').attr('aria-selected', 'false');
		$('.csi-tab[data-tab="' + tab + '"]').addClass('is-active').attr('aria-selected', 'true');
		$('.csi-panel').removeClass('is-active');
		$('.csi-panel[data-panel="' + tab + '"]').addClass('is-active');
	}

	function refreshMode() {
		var mode = $form.find('input[name$="[display_type]"]:checked').val() || 'toggle';

		$form.attr('data-insurance-mode', mode);
		$('.csi-choice-card').removeClass('is-selected');
		$form.find('input[name$="[display_type]"]:checked').closest('.csi-choice-card').addClass('is-selected');
	}

	function addCoverageRow() {
		var currency = $('.csi-coverage-row .csi-money span').first().text() || '$';
		var row = '<div class="csi-coverage-row">' +
			'<button type="button" class="csi-drag" aria-label="Drag to reorder">⋮⋮</button>' +
			'<div class="csi-money"><span>' + $('<div>').text(currency).html() + '</span><input type="number" min="0.01" step="0.01" name="codenitive_shipping_insurance[coverage_amount][]" placeholder="500" required></div>' +
			'<div class="csi-money"><span>' + $('<div>').text(currency).html() + '</span><input type="number" min="0" step="0.01" name="codenitive_shipping_insurance[coverage_fee][]" placeholder="30.00" required></div>' +
			'<button type="button" class="button-link-delete csi-remove-row">Remove</button>' +
			'</div>';

		$rows.append(row);
		$rows.children().last().find('input').first().trigger('focus');
	}

	$('.csi-tab').on('click', function () {
		activateTab($(this).data('tab'));
	});

	$form.on('change', 'input[name$="[display_type]"]', refreshMode);
	$('#csi-add-coverage').on('click', addCoverageRow);
	$rows.on('click', '.csi-remove-row', function () {
		$(this).closest('.csi-coverage-row').remove();
	});

	if ($.fn.sortable) {
		$rows.sortable({
			handle: '.csi-drag',
			placeholder: 'csi-coverage-placeholder'
		});
	}

	activateTab('general');
	refreshMode();
})(jQuery);
