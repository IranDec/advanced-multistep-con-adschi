jQuery(document).ready(function($) {

	$('.smlf-form-wrapper').each(function() {
		const $wrapper = $(this);
		const formId = $wrapper.data('form-id');
		const $form = $wrapper.find('.smlf-form-actual');
		const $steps = $wrapper.find('.smlf-form-step');
		const totalSteps = $steps.length;
		let currentStep = 0;
		let leadId = null;

		// Anti-bot verify
		$wrapper.find('.smlf-btn-verify').on('click', function(e) {
			e.preventDefault();
			const isChecked = $wrapper.find('#smlf-bot-check-' + formId).is(':checked');
			if (!isChecked) {
				alert('Please check the box.');
				return;
			}

			$.post(smlf_public_obj.ajax_url, {
				action: 'smlf_verify_bot',
				nonce: smlf_public_obj.nonce,
				form_id: formId
			}, function(response) {
				if (response.success) {
					$wrapper.find('.smlf-anti-bot-overlay').fadeOut(300, function() {
						$wrapper.find('.smlf-progress-bar-container').fadeIn();
						$form.fadeIn();
						updateProgress();
					});
				}
			});
		});

		function updateProgress() {
			if (totalSteps > 1) {
				const percentage = ((currentStep) / (totalSteps - 1)) * 100;
				$wrapper.find('.smlf-progress-bar').css('width', percentage + '%');
			} else {
				$wrapper.find('.smlf-progress-bar').css('width', '100%');
			}
		}

		function showStep(index) {
			$steps.hide();
			$steps.eq(index).fadeIn(300);
			currentStep = index;
			updateProgress();
		}

		// Auto-save on blur/change of critical fields
		$form.find('.smlf-critical-field').on('blur change', function() {
			if ($(this).val().trim() !== '') {
				savePartialLead();
			}
		});

		// Instant advance on card click
		$form.find('.smlf-card input[type="radio"]').on('change', function() {
			if ($(this).is(':checked')) {
				savePartialLead();
				if (currentStep < totalSteps - 1) {
					setTimeout(function() {
						showStep(currentStep + 1);
					}, 200);
				}
			}
		});

		$wrapper.find('.smlf-btn-next').on('click', function(e) {
			e.preventDefault();
			// Basic validation hook can go here
			savePartialLead();
			if (currentStep < totalSteps - 1) {
				showStep(currentStep + 1);
			}
		});

		$wrapper.find('.smlf-btn-prev').on('click', function(e) {
			e.preventDefault();
			if (currentStep > 0) {
				showStep(currentStep - 1);
			}
		});

		function savePartialLead() {
			const formData = $form.serializeArray();
			$.post(smlf_public_obj.ajax_url, {
				action: 'smlf_save_partial',
				nonce: smlf_public_obj.nonce,
				form_id: formId,
				lead_id: leadId,
				data: formData
			}, function(response) {
				if (response.success && response.data.lead_id) {
					leadId = response.data.lead_id;
				}
			});
		}

		// Final Submit
		$wrapper.find('.smlf-btn-submit').on('click', function(e) {
			e.preventDefault();

			const $btn = $(this);
			$btn.prop('disabled', true).text('Submitting...');

			const formData = $form.serializeArray();

			$.post(smlf_public_obj.ajax_url, {
				action: 'smlf_submit_form',
				nonce: smlf_public_obj.nonce,
				form_id: formId,
				lead_id: leadId,
				data: formData
			}, function(response) {
				if (response.success) {
					$wrapper.find('.smlf-progress-bar-container').hide();
					$form.hide();
					$wrapper.find('.smlf-success-message').fadeIn();
				} else {
					alert('Error submitting form.');
					$btn.prop('disabled', false).text('Submit');
				}
			});
		});

	});

});
