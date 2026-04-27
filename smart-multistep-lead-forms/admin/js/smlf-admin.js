jQuery(document).ready(function($) {
	let stepCounter = 1;
	const i18n = smlf_admin_obj.i18n || {};

	function addStep(stepData) {
		const data = stepData || {};
		const stepId = parseInt(data.step_id || stepCounter, 10);
		const stepTitle = data.title || i18n.step + ' ' + stepId;

		const $step = $('<div/>', {
			'class': 'smlf-step',
			'data-step': stepId
		});

		const $header = $('<div/>', { 'class': 'smlf-step-header' });
		$header.append($('<span/>', { 'class': 'step-title-display', text: i18n.step + ' ' + stepId }));
		$header.append($('<input/>', {
			type: 'text',
			'class': 'smlf-step-title-input',
			value: stepTitle,
			css: { marginLeft: '10px', width: '200px' }
		}));
		$header.append($('<button/>', {
			'class': 'button smlf-remove-step',
			text: i18n.remove
		}));

		const $logic = $('<div/>', {
			'class': 'smlf-step-logic',
			css: {
				marginBottom: '10px',
				padding: '5px',
				background: '#e9f0f5',
				border: '1px solid #ccd0d4'
			}
		});
		const $logicLabel = $('<label/>', { css: { fontSize: '12px' }, text: i18n.condition_prefix + ' ' });
		$logicLabel.append($('<input/>', {
			type: 'number',
			'class': 'smlf-logic-target',
			value: data.logic_target || '',
			placeholder: '#',
			css: { width: '50px' }
		}));
		$logicLabel.append(document.createTextNode(' ' + i18n.condition_middle + ' '));
		$logicLabel.append($('<input/>', {
			type: 'text',
			'class': 'smlf-logic-value',
			value: data.logic_value || '',
			placeholder: i18n.condition_placeholder
		}));
		$logic.append($logicLabel);
		const $terminalLabel = $('<label/>', {
			text: ' ' + i18n.terminal_reset,
			css: { display: 'block', marginTop: '8px', fontSize: '12px' }
		});
		$terminalLabel.prepend($('<input/>', {
			type: 'checkbox',
			'class': 'smlf-step-terminal',
			checked: data.terminal === 'reset'
		}));
		$logic.append($terminalLabel);

		$step.append($header, $logic, $('<div/>', { 'class': 'smlf-fields-dropzone' }));
		$('#smlf-steps-container').append($step);

		if (Array.isArray(data.fields)) {
			data.fields.forEach(function(field) {
				$step.find('.smlf-fields-dropzone').append(createFieldItem(field));
			});
		}

		stepCounter = Math.max(stepCounter, stepId + 1);
		initSortable();
		renderPreview();
	}

	function createFieldItem(fieldData) {
		const data = fieldData || {};
		const type = data.type || 'text';
		const label = data.label || getDefaultLabel(type);
		const fieldId = data.field_id || '';

		const $item = $('<div/>', {
			'class': 'smlf-field-item',
			'data-type': type,
			'data-field-id': fieldId,
			css: {
				background: '#fff',
				border: '1px solid #ddd',
				padding: '10px',
				marginBottom: '5px'
			}
		});

		$item.append($('<strong/>', { text: label }));
		$item.append($('<button/>', {
			'class': 'button-link smlf-remove-field',
			text: 'x',
			css: { float: 'right', color: 'red' }
		}));

		const $settings = $('<div/>', {
			'class': 'smlf-field-settings',
			css: { marginTop: '10px' }
		});
		const $label = $('<label/>', { text: i18n.label + ': ' });
		$label.append($('<input/>', {
			type: 'text',
			'class': 'field-label',
			value: label
		}));
		$settings.append($label);

		const $requiredLabel = $('<label/>', {
			text: ' ' + i18n.required,
			css: { display: 'block', marginTop: '5px' }
		});
		$requiredLabel.prepend($('<input/>', {
			type: 'checkbox',
			'class': 'field-required',
			checked: !!parseInt(data.required || 0, 10)
		}));
		$settings.append($requiredLabel);

		if (type === 'cards' || type === 'radio') {
			const $optionsLabel = $('<label/>', {
				text: i18n.options + ': ',
				css: { display: 'block', marginTop: '5px' }
			});
			$optionsLabel.append($('<input/>', {
				type: 'text',
				'class': 'field-options',
				value: data.options || i18n.option_1 + ', ' + i18n.option_2,
				css: { width: '100%' }
			}));
			$settings.append($optionsLabel);
		}

		if (type === 'message') {
			$item.find('.field-required').closest('label').hide();
		}

		$item.append($settings);
		return $item;
	}

	$('#smlf-add-step').on('click', function(e) {
		e.preventDefault();
		addStep();
	});

	$('.smlf-draggable-blocks li').on('click', function(e) {
		e.preventDefault();
		addFieldToActiveStep($(this).data('type'), $(this).text());
	});

	$(document).on('click', '.smlf-remove-step', function(e) {
		e.preventDefault();
		$(this).closest('.smlf-step').remove();
		renderPreview();
	});

	function initSortable() {
		$('.smlf-draggable-blocks li').draggable({
			connectToSortable: '.smlf-fields-dropzone',
			helper: 'clone',
			revert: 'invalid',
			appendTo: 'body',
			zIndex: 100000
		});

		$('.smlf-fields-dropzone').sortable({
			revert: true,
			items: '.smlf-field-item',
			placeholder: 'smlf-field-placeholder',
			receive: function(event, ui) {
				const $item = ui.item;
				const type = $item.data('type');
				const text = $item.text();
				$item.replaceWith(createFieldItem({
					type: type,
					label: text,
					required: type === 'email' ? 1 : 0
				}));
				renderPreview();
			}
		});
	}

	function addFieldToActiveStep(type, text) {
		let $dropzone = $('.smlf-step').last().find('.smlf-fields-dropzone');

		if (!$dropzone.length) {
			addStep();
			$dropzone = $('.smlf-step').last().find('.smlf-fields-dropzone');
		}

		$dropzone.append(createFieldItem({
			type: type,
			label: text || getDefaultLabel(type),
			required: type === 'email' ? 1 : 0
		}));
		renderPreview();
	}

	$(document).on('click', '.smlf-remove-field', function(e) {
		e.preventDefault();
		$(this).closest('.smlf-field-item').remove();
		renderPreview();
	});

	if (typeof window.smlf_existing_form_data !== 'undefined' && Array.isArray(window.smlf_existing_form_data.steps) && window.smlf_existing_form_data.steps.length > 0) {
		$('#smlf-form-title').val(window.smlf_existing_form_data.title || 'New Form');
		window.smlf_existing_form_data.steps.forEach(function(step) {
			addStep(step);
		});
	} else if ($('#smlf-steps-container').length > 0) {
		addStep();
	}

	$(document).on('input change', '#smlf-form-title, .smlf-step input, .smlf-field-item input', renderPreview);

	$('#smlf-load-template').on('click', function(e) {
		e.preventDefault();
		loadTemplate(smlf_admin_obj.template);
	});

	$('#smlf-save-form').on('click', function(e) {
		e.preventDefault();

		const title = $('#smlf-form-title').val();
		const steps = collectSteps();
		const urlParams = new URLSearchParams(window.location.search);
		const formId = urlParams.get('id') || (window.smlf_existing_form_data ? window.smlf_existing_form_data.id : 0);
		const $button = $(this);

		$button.prop('disabled', true);

		$.post(smlf_admin_obj.ajax_url, {
			action: 'smlf_save_form_admin',
			nonce: smlf_admin_obj.nonce,
			form_id: formId,
			form_data: JSON.stringify({
				title: title,
				steps: steps
			})
		}).done(function(response) {
			if (response.success) {
				alert(i18n.save_success);
				if (!formId && response.data.form_id) {
					window.location.href = window.location.href + '&id=' + response.data.form_id;
				}
				return;
			}

			alert((response.data && response.data.message) || i18n.save_error);
		}).fail(function() {
			alert(i18n.save_error);
		}).always(function() {
			$button.prop('disabled', false);
		});
	});

	function collectSteps() {
		const steps = [];

		$('.smlf-step').each(function() {
			const $step = $(this);
			const fields = [];

			$step.find('.smlf-field-item').each(function(index) {
				const $field = $(this);
				const type = $field.data('type');
				const existingId = $field.data('field-id');

				fields.push({
					field_id: existingId || 'field_' + $step.data('step') + '_' + (index + 1),
					type: type,
					label: $field.find('.field-label').val(),
					options: (type === 'cards' || type === 'radio') ? $field.find('.field-options').val() : '',
					required: $field.find('.field-required').is(':checked') ? 1 : 0
				});
			});

			steps.push({
				step_id: $step.data('step'),
				title: $step.find('.smlf-step-title-input').val(),
				logic_target: $step.find('.smlf-logic-target').val(),
				logic_value: $step.find('.smlf-logic-value').val(),
				terminal: $step.find('.smlf-step-terminal').is(':checked') ? 'reset' : '',
				fields: fields
			});
		});

		return steps;
	}

	function loadTemplate(template) {
		if (!template || !Array.isArray(template.steps)) {
			return;
		}

		$('#smlf-form-title').val(template.title || '');
		$('#smlf-steps-container').empty();
		stepCounter = 1;
		template.steps.forEach(function(step) {
			addStep(step);
		});
		renderPreview();
	}

	function renderPreview() {
		const $preview = $('#smlf-builder-preview');
		if (!$preview.length) {
			return;
		}

		const title = $('#smlf-form-title').val();
		const steps = collectSteps();
		$preview.empty();

		const $shell = $('<div/>', { 'class': 'smlf-preview-shell' });
		$shell.append($('<h3/>', { text: title }));

		steps.forEach(function(step, stepIndex) {
			const $step = $('<div/>', { 'class': 'smlf-preview-step' });
			$step.append($('<div/>', { 'class': 'smlf-preview-step-title', text: step.title || i18n.step + ' ' + (stepIndex + 1) }));

			step.fields.forEach(function(field) {
				const $field = $('<div/>', { 'class': 'smlf-preview-field smlf-preview-field-' + field.type });
				if (field.type === 'message') {
					$field.append($('<div/>', { 'class': 'smlf-preview-message', text: field.label }));
				} else {
					$field.append($('<label/>', { text: field.label + (field.required ? ' *' : '') }));
				}

				if (field.type === 'text' || field.type === 'email' || field.type === 'phone') {
					$field.append($('<div/>', { 'class': 'smlf-preview-input' }));
				} else if (field.type === 'textarea') {
					$field.append($('<div/>', { 'class': 'smlf-preview-textarea' }));
				} else if (field.type === 'file') {
					$field.append($('<div/>', { 'class': 'smlf-preview-file', text: i18n.drag_files }));
				} else if (field.type === 'cards' || field.type === 'radio') {
					const $cards = $('<div/>', { 'class': 'smlf-preview-cards' });
					String(field.options || '').split(',').map(function(option) {
						return option.trim();
					}).filter(Boolean).forEach(function(option) {
						$cards.append($('<span/>', { text: option }));
					});
					$field.append($cards);
				}

				$step.append($field);
			});

			const $nav = $('<div/>', { 'class': 'smlf-preview-nav' });
			if (stepIndex > 0) {
				$nav.append($('<button/>', { type: 'button', text: i18n.back }));
			}
			$nav.append($('<button/>', { type: 'button', text: step.terminal === 'reset' ? i18n.reset : (stepIndex < steps.length - 1 ? i18n.next : i18n.submit) }));
			$step.append($nav);
			$shell.append($step);
		});

		$preview.append($shell);
	}

	function getDefaultLabel(type) {
		const map = {
			text: i18n.text_input,
			email: i18n.email_input,
			phone: i18n.phone_input,
			textarea: i18n.long_text,
			file: i18n.file_upload,
			message: i18n.message_text,
			cards: i18n.clickable_cards,
			radio: i18n.radio_buttons
		};
		return map[type] || type;
	}

	renderPreview();
});
