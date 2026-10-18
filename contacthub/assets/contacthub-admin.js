(function () {
	'use strict';
	var i18n = (window.CONTACTHUB_CONFIG && CONTACTHUB_CONFIG.i18n) || {};
	function tr(key, fallback) { return i18n[key] || fallback; }
	function format(template, value) { return String(template).replace(/%[sd]/, value); }

	document.addEventListener('click', function (event) {
		var resetOpen = event.target.closest('[data-contacthub-reset-open]');
		if (resetOpen) {
			var resetForm = resetOpen.closest('.contacthub-reset-form');
			var resetModal = resetForm && resetForm.parentNode.querySelector('.contacthub-reset-modal');
			if (resetModal) resetModal.hidden = false;
			return;
		}
		var resetCancel = event.target.closest('.contacthub-reset-cancel');
		if (resetCancel) {
			resetCancel.closest('.contacthub-reset-modal').hidden = true;
			return;
		}
		var resetConfirm = event.target.closest('.contacthub-reset-confirm');
		if (resetConfirm) {
			var modal = resetConfirm.closest('.contacthub-reset-modal');
			var form = modal && modal.parentNode.querySelector('.contacthub-reset-form');
			if (form) form.submit();
			return;
		}
		if (!event.target.closest('.contacthub-token-entry')) {
			document.querySelectorAll('.contacthub-token-suggestions').forEach(function (list) { list.hidden = true; });
		}
		var invoiceLink = event.target.closest('a.contacthub-invoice-preview');
		if (invoiceLink) {
			event.preventDefault();
			return openPreviewFrame(invoiceLink.dataset.title || tr('invoice', 'Invoice'), invoiceLink.href, '', invoiceLink.href);
		}
		var button = event.target.closest('button');
		if (!button) return;
		if (button.classList.contains('contacthub-settings-color-swatch')) {
			var colorField = button.closest('.contacthub-settings-color-field');
			var colorInput = colorField && colorField.querySelector('input[type="color"]');
			if (colorInput) {
				colorInput.value = button.dataset.color;
				var colorValue = colorField.querySelector('.contacthub-settings-color-value');
				if (colorValue) colorValue.textContent = button.dataset.color;
				colorInput.dispatchEvent(new Event('change', {bubbles: true}));
			}
			return;
		}
		if (button.classList.contains('contacthub-tab')) return activateTab(button);
		if (button.classList.contains('contacthub-open-consent')) return openConsent(button.closest('.contacthub-marketing'));
		if (button.classList.contains('contacthub-close-consent')) return closeConsent(button.closest('.contacthub-marketing'));
		if (button.classList.contains('contacthub-save-consent')) return saveConsent(button.closest('.contacthub-marketing'));
		if (button.classList.contains('contacthub-open-bulk')) return openBulkMarketing();
		if (button.classList.contains('contacthub-close-bulk')) return closeBulkMarketing();
		if (button.classList.contains('contacthub-save-bulk')) return saveBulkMarketing();
		if (button.classList.contains('contacthub-open-export')) return openContactOutput('export');
		if (button.classList.contains('contacthub-open-print')) return openContactOutput('print');
		if (button.classList.contains('contacthub-close-output')) return closeContactOutput();
		if (button.classList.contains('contacthub-open-new-person')) return openNewPerson();
		if (button.classList.contains('contacthub-close-new-person')) return closeNewPerson();
		if (button.classList.contains('contacthub-save-new-person')) return saveNewPerson();
		if (button.classList.contains('contacthub-open-new-company')) return openNewCompany();
		if (button.classList.contains('contacthub-close-new-company')) return closeNewCompany();
		if (button.classList.contains('contacthub-save-new-company')) return saveNewCompany();
		if (button.classList.contains('contacthub-preview-broadcast')) return previewBroadcast(button);
		if (button.classList.contains('contacthub-preview-order')) return previewRecord(button, 'contacthub_preview_order', 'order_id', button.dataset.order, tr('order', 'Order'));
		if (button.classList.contains('contacthub-preview-event')) return previewRecord(button, 'contacthub_preview_event', 'event_id', button.dataset.event, tr('event', 'Event'));
		if (button.classList.contains('contacthub-close-preview')) return closePreviewFrame();
		if (button.classList.contains('contacthub-open-roles')) return toggleLocalModal(button.closest('.contacthub-master-data').querySelector('.contacthub-roles-modal'), true);
		if (button.classList.contains('contacthub-close-roles')) return toggleLocalModal(button.closest('.contacthub-roles-modal'), false);
		if (button.classList.contains('contacthub-save-roles')) return saveRoles(button);
		if (button.classList.contains('contacthub-open-user-account')) return toggleLocalModal(button.closest('.contacthub-master-data').querySelector('.contacthub-user-account-modal'), true);
		if (button.classList.contains('contacthub-close-user-account')) return toggleLocalModal(button.closest('.contacthub-user-account-modal'), false);
		if (button.classList.contains('contacthub-save-user-account')) return saveUserAccount(button);
		if (button.classList.contains('contacthub-tag-suggestion')) return manageTag(button, 'add');
		if (button.classList.contains('contacthub-tags-summary')) return openTags(button.closest('.contacthub-tags-card'));
		if (button.classList.contains('contacthub-close-tags')) return closeTags(button.closest('.contacthub-tags-card'));
		if (button.classList.contains('contacthub-role-suggestion')) return addRole(button);
		if (button.classList.contains('contacthub-remove-tag')) return manageTag(button, 'remove');
		if (button.classList.contains('contacthub-remove-role')) return button.closest('.contacthub-role-token').remove();
		if (button.classList.contains('contacthub-save-address')) return saveAddress(button.closest('.contacthub-address-form'));
		if (button.classList.contains('contacthub-salutation-value')) return openSalutation(button.closest('.contacthub-salutation-field'));
		if (button.classList.contains('contacthub-cancel-salutation')) return closeSalutation(button.closest('.contacthub-salutation-field'));
		if (button.classList.contains('contacthub-add-channel')) return addContactChannel(button.closest('.contacthub-channel-group'));
		if (button.classList.contains('contacthub-channel-value')) return openContactChannel(button.closest('.contacthub-channel-row'));
		if (button.classList.contains('contacthub-cancel-channel')) return closeContactChannel(button.closest('.contacthub-channel-row'));
		if (button.classList.contains('contacthub-save-channel')) return saveContactChannel(button.closest('.contacthub-channel-row'));
		if (button.classList.contains('contacthub-delete-channel')) return deleteContactChannel(button.closest('.contacthub-channel-row'));
		if (button.classList.contains('contacthub-edit-address-summary')) {
			var addressTab = document.querySelector('.contacthub-tab[aria-controls$="-addresses"]');
			if (addressTab) {
				activateTab(addressTab);
				var addressPanel = document.getElementById(addressTab.getAttribute('aria-controls'));
				var firstAddressInput = addressPanel ? addressPanel.querySelector('.contacthub-address-form input:not([disabled])') : null;
				if (firstAddressInput) firstAddressInput.focus();
			}
			return;
		}

		var field = button.closest('.contacthub-editable');
		if (!field) return;
		if (button.classList.contains('contacthub-edit')) startEditing(field);
		if (button.classList.contains('contacthub-cancel')) stopEditing(field);
		if (button.classList.contains('contacthub-save')) saveField(field);
		if (button.classList.contains('contacthub-validate-vat')) validateVat(field);
	});

	document.addEventListener('keydown', function (event) {
		if (event.key === 'Escape' && event.target.classList.contains('contacthub-token-input')) {
			closeTokenSuggestions(event.target);
			return;
		}
		if (event.key === 'Enter' && event.target.classList.contains('contacthub-tag-input')) {
			event.preventDefault();
			return manageTag(event.target, 'commit');
		}
		if (event.key === 'Enter' && event.target.classList.contains('contacthub-role-input')) {
			event.preventDefault();
			return addRole(event.target);
		}
		var tab = event.target.closest('.contacthub-tab');
		if (!tab || ['ArrowLeft', 'ArrowRight', 'Home', 'End'].indexOf(event.key) === -1) return;
		var tabs = Array.prototype.slice.call(tab.closest('[role="tablist"]').querySelectorAll('.contacthub-tab'));
		var index = tabs.indexOf(tab);
		if (event.key === 'Home') index = 0;
		else if (event.key === 'End') index = tabs.length - 1;
		else index = (index + (event.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length;
		event.preventDefault();
		activateTab(tabs[index]);
		tabs[index].focus();
	});

	document.addEventListener('change', function (event) {
		if (event.target.classList.contains('contacthub-salutation-select')) saveSalutation(event.target);
		if (event.target.classList.contains('contacthub-new-salutation')) event.target.dataset.autoAssigned = '0';
		if (event.target.classList.contains('contacthub-consent-decision')) updateConsentConfirmation(event.target.closest('.contacthub-marketing'));
		if (event.target.classList.contains('contacthub-select-all')) {
			document.querySelectorAll('.contacthub-person-select').forEach(function (checkbox) { checkbox.checked = event.target.checked; });
			updateBulkSelection();
		}
		if (event.target.classList.contains('contacthub-person-select') || event.target.id === 'contacthub-bulk-action') updateBulkSelection();
	});

	document.addEventListener('input', function (event) {
		if (!event.target.classList.contains('contacthub-new-first-name')) return;
		updateNewPersonSalutationSuggestion(event.target.closest('.contacthub-new-person-modal'));
	});

	document.addEventListener('click', function (event) {
		var suggestion = event.target.closest('.contacthub-salutation-suggestion');
		if (!suggestion) return;
		var field = suggestion.closest('.contacthub-salutation-field');
		var select = field.querySelector('.contacthub-salutation-select');
		select.value = suggestion.dataset.value;
		saveSalutation(select);
	});

	function saveSalutation(select) {
		var field = select.closest('.contacthub-salutation-field');
		var message = field.querySelector('.contacthub-field-message');
		var data = new URLSearchParams();
		data.set('action', 'contacthub_update_contact_field'); data.set('nonce', CONTACTHUB_CONFIG.nonce);
		data.set('contact_id', field.dataset.contact); data.set('field', 'anrede'); data.set('value', select.value);
		select.disabled = true; message.textContent = tr('saving', 'Saving…');
		fetch(CONTACTHUB_CONFIG.ajaxUrl, requestOptions(data)).then(parseResponse).then(function (result) {
			if (!result.success) throw new Error(result.data && result.data.message ? result.data.message : tr('saveFailed', 'Saving failed.'));
			message.textContent = tr('saved', 'Saved.'); message.classList.add('is-success');
			var value = field.querySelector('.contacthub-salutation-value');
			if (value) { value.dataset.value = select.value; value.textContent = result.data.display; }
			var suggestion = field.querySelector('.contacthub-salutation-suggestion'); if (suggestion) suggestion.remove();
			closeSalutation(field, true);
		}).catch(function (error) { message.textContent = error.message; }).finally(function () { select.disabled = false; });
	}

	function openSalutation(field) {
		if (!field) return;
		var editor = field.querySelector('.contacthub-salutation-editor');
		var value = field.querySelector('.contacthub-salutation-value');
		if (editor) editor.hidden = false;
		if (value) value.hidden = true;
		var select = editor && editor.querySelector('select');
		if (select) select.focus();
	}

	function closeSalutation(field, keepValue) {
		if (!field) return;
		var editor = field.querySelector('.contacthub-salutation-editor');
		var value = field.querySelector('.contacthub-salutation-value');
		var select = editor && editor.querySelector('select');
		if (editor) editor.hidden = true;
		if (value) value.hidden = false;
		if (!keepValue && select && value) select.value = value.dataset.value || '';
	}

	function openContactChannel(row) {
		if (!row) return;
		var editor = row.querySelector('.contacthub-channel-editor');
		var summary = row.querySelector('.contacthub-channel-summary');
		if (editor) editor.hidden = false;
		if (summary) summary.hidden = true;
		var input = row.querySelector('.contacthub-channel-input, .contacthub-phone-national');
		if (input) input.focus();
	}

	function closeContactChannel(row) {
		if (!row) return;
		if (row.classList.contains('is-new')) {
			var list = row.parentNode;
			row.remove();
			var empty = list && list.querySelector('.contacthub-channel-empty');
			if (empty && !list.querySelector('.contacthub-channel-row')) empty.hidden = false;
			return;
		}
		var editor = row.querySelector('.contacthub-channel-editor');
		var summary = row.querySelector('.contacthub-channel-summary');
		if (editor) editor.hidden = true;
		if (summary) summary.hidden = false;
	}

	function addContactChannel(group) {
		if (!group || group.querySelector('.contacthub-channel-row.is-new')) return;
		var template = group.querySelector('.contacthub-channel-template');
		var list = group.querySelector('.contacthub-channel-list');
		if (!template || !list) return;
		var row = template.content.firstElementChild.cloneNode(true);
		list.appendChild(row);
		var empty = list.querySelector('.contacthub-channel-empty');
		if (empty) empty.hidden = true;
		openContactChannel(row);
	}

	function saveContactChannel(row) {
		if (!row) return;
		var button = row.querySelector('.contacthub-save-channel');
		var group = row.closest('.contacthub-channel-group');
		var message = group.querySelector('.contacthub-channel-message');
		var type = row.dataset.channelType;
		var data = new URLSearchParams();
		data.set('action', 'contacthub_save_contact_channel');
		data.set('nonce', CONTACTHUB_CONFIG.nonce);
		data.set('contact_id', row.dataset.contact);
		data.set('channel_id', row.dataset.channelId || '');
		data.set('channel_type', type);
		data.set('channel_label', row.querySelector('.contacthub-channel-label-select').value);
		data.set('value', type === 'phone' ? composePhone(row.querySelector('.contacthub-phone-input-group')) : row.querySelector('.contacthub-channel-input').value);
		data.set('is_primary', row.querySelector('.contacthub-channel-primary-input').checked ? '1' : '0');
		button.disabled = true;
		message.classList.remove('is-success');
		message.textContent = tr('saving', 'Saving…');
		fetch(CONTACTHUB_CONFIG.ajaxUrl, requestOptions(data)).then(parseResponse).then(function (result) {
			if (!result.success) throw new Error(result.data && result.data.message ? result.data.message : tr('saveFailed', 'Saving failed.'));
			row.dataset.channelId = result.data.id;
			row.dataset.primary = result.data.is_primary ? '1' : '0';
			row.classList.remove('is-new');
			var value = row.querySelector('.contacthub-channel-value');
			value.dataset.value = result.data.value;
			value.textContent = result.data.display;
			row.querySelector('.contacthub-channel-label').textContent = result.data.label_text;
			if (type === 'email') row.querySelector('.contacthub-channel-marketing').textContent = format(tr('marketingStatus', 'Marketing: %s'), result.data.marketing_label);
			if (result.data.is_primary) {
				group.querySelectorAll('.contacthub-channel-row').forEach(function (other) {
					var own = other === row;
					other.dataset.primary = own ? '1' : '0';
					var badge = other.querySelector('.contacthub-primary-badge'); if (badge) badge.hidden = !own;
					var primary = other.querySelector('.contacthub-channel-primary-input'); if (primary) { primary.checked = own; primary.disabled = own; }
				});
			}
			var deleteButton = row.querySelector('.contacthub-delete-channel');
			if (deleteButton) deleteButton.hidden = !!result.data.is_primary;
			button.disabled = false;
			closeContactChannel(row);
			message.textContent = tr('saved', 'Saved.'); message.classList.add('is-success');
			window.setTimeout(function () { message.textContent = ''; message.classList.remove('is-success'); }, 2500);
		}).catch(function (error) { button.disabled = false; message.textContent = error.message; });
	}

	function deleteContactChannel(row) {
		if (!row || !window.confirm(tr('confirmDeleteChannel', 'Remove this contact detail?'))) return;
		var group = row.closest('.contacthub-channel-group');
		var message = group.querySelector('.contacthub-channel-message');
		var data = new URLSearchParams();
		data.set('action', 'contacthub_delete_contact_channel'); data.set('nonce', CONTACTHUB_CONFIG.nonce);
		data.set('contact_id', row.dataset.contact); data.set('channel_id', row.dataset.channelId);
		fetch(CONTACTHUB_CONFIG.ajaxUrl, requestOptions(data)).then(parseResponse).then(function (result) {
			if (!result.success) throw new Error(result.data && result.data.message ? result.data.message : tr('saveFailed', 'Saving failed.'));
			row.remove(); message.textContent = result.data.message; message.classList.add('is-success');
		}).catch(function (error) { message.textContent = error.message; });
	}

	document.addEventListener('submit', function (event) {
		if (!event.target.classList.contains('contacthub-output-form')) return;
		var form = event.target;
		var selectedScope = form.querySelector('input[name="scope"]:checked');
		var selected = selectedContactIds();
		if (selectedScope && selectedScope.value === 'selected' && !selected.length) {
			event.preventDefault();
			form.querySelector('.contacthub-modal-message').textContent = tr('chooseOutputScope', 'Choose whether selected people or all filtered people should be used.');
			return;
		}
		form.querySelector('input[name="selected_ids"]').value = selected.join(',');
		window.setTimeout(closeContactOutput, 250);
	});

	document.addEventListener('input', function (event) {
		if (event.target.classList.contains('contacthub-phone-national')) detectPhonePrefix(event.target);
		if (event.target.classList.contains('contacthub-token-input')) updateTokenSuggestions(event.target);
		if (event.target.classList.contains('contacthub-new-company-name')) {
			var modal = event.target.closest('.contacthub-new-person-modal');
			var hidden = modal ? modal.querySelector('.contacthub-new-company-id') : null;
			var option = Array.prototype.slice.call(document.querySelectorAll('#contacthub-new-company-options option')).find(function (item) {
				return item.value.toLocaleLowerCase() === event.target.value.trim().toLocaleLowerCase();
			});
			if (hidden) hidden.value = option ? option.dataset.companyId : '';
		}
	});

	document.addEventListener('blur', function (event) {
		if (!event.target.classList.contains('contacthub-phone-national')) return;
		formatPhoneNational(event.target);
	}, true);

	document.addEventListener('focusin', function (event) {
		if (event.target.classList.contains('contacthub-token-input')) updateTokenSuggestions(event.target);
	});

	function activateTab(tab) {
		var container = tab.closest('[data-tabs]');
		if (!container) return;
		container.querySelectorAll('.contacthub-tab').forEach(function (item) {
			var selected = item === tab;
			item.classList.toggle('is-active', selected);
			item.setAttribute('aria-selected', selected ? 'true' : 'false');
			item.tabIndex = selected ? 0 : -1;
		});
		container.querySelectorAll('.contacthub-tab-panel').forEach(function (panel) {
			var selected = panel.id === tab.getAttribute('aria-controls');
			panel.hidden = !selected;
			panel.classList.toggle('is-active', selected);
		});
	}

	function startEditing(field) {
		if (!field || field.classList.contains('is-editing')) return;
		var valueNode = field.querySelector('.contacthub-field-value');
		var multiline = field.dataset.multiline === '1';
		var isPhone = field.dataset.type === 'phone';
		var input = document.createElement(multiline ? 'textarea' : 'input');
		input.className = 'contacthub-input';
		input.value = valueNode.dataset.value || '';
		field.dataset.verifiedOriginal = field.dataset.verified || '0';
		if (!multiline) input.type = field.dataset.field === 'domain' ? 'url' : (field.dataset.field === 'email' ? 'email' : 'text');
		field.classList.add('is-editing');
		field.querySelector('.contacthub-field-row').hidden = true;
		var editor = document.createElement('div');
		editor.className = 'contacthub-editor';
		if (isPhone) editor.appendChild(buildPhoneGroup(valueNode.dataset.value || ''));
		else editor.appendChild(input);
		var validateButton = field.dataset.field === 'uid' ? '<button type="button" class="button contacthub-validate-vat">' + tr('validateVat', 'Validate VAT ID') + '</button>' : '';
		editor.insertAdjacentHTML('beforeend', '<div class="contacthub-editor-actions"><button type="button" class="button button-primary contacthub-save">' + tr('save', 'Save') + '</button>' + validateButton + '<button type="button" class="button contacthub-cancel">' + tr('cancel', 'Cancel') + '</button></div>');
		field.querySelector('.contacthub-field-message').before(editor);
		(isPhone ? editor.querySelector('.contacthub-phone-national') : input).focus();
	}

	function validateVat(field) {
		var input = field.querySelector('.contacthub-input');
		var button = field.querySelector('.contacthub-validate-vat');
		var message = field.querySelector('.contacthub-field-message');
		var data = new URLSearchParams();
		data.set('action', 'contacthub_validate_vat');
		data.set('nonce', CONTACTHUB_CONFIG.nonce);
		data.set('value', field.dataset.type === 'phone' ? composePhone(field.querySelector('.contacthub-phone-input-group')) : input.value);
		if (field.dataset.entity === 'contact') data.set('contact_id', field.dataset.contact);
		else data.set('company_id', field.dataset.company);
		button.disabled = true;
		message.classList.remove('is-success');
		message.textContent = tr('checkingVat', 'Checking EU VIES…');
		fetch(CONTACTHUB_CONFIG.ajaxUrl, requestOptions(data))
			.then(parseResponse)
			.then(function (result) {
				message.textContent = result.data && result.data.message ? result.data.message : tr('noUsableResponse', 'No usable response was received.');
				message.classList.toggle('is-success', !!result.success);
				button.disabled = false;
				setVatCheck(field, !!result.success);
			})
			.catch(function (error) {
				message.textContent = error.message || tr('vatValidationFailed', 'VAT ID validation could not be completed.');
				button.disabled = false;
			});
	}

	function stopEditing(field, keepVerification) {
		var editor = field.querySelector('.contacthub-editor');
		if (editor) editor.remove();
		if (!keepVerification && field.dataset.field === 'uid') setVatCheck(field, field.dataset.verifiedOriginal === '1');
		field.classList.remove('is-editing', 'is-saving');
		field.querySelector('.contacthub-field-row').hidden = false;
		field.querySelector('.contacthub-field-message').textContent = '';
	}

	function saveField(field) {
		var input = field.querySelector('.contacthub-input');
		var saveButton = field.querySelector('.contacthub-save');
		var message = field.querySelector('.contacthub-field-message');
		var isContact = field.dataset.entity === 'contact';
		var data = new URLSearchParams();
		data.set('action', isContact ? 'contacthub_update_contact_field' : 'contacthub_update_company_field');
		data.set('nonce', CONTACTHUB_CONFIG.nonce);
		data.set(isContact ? 'contact_id' : 'company_id', isContact ? field.dataset.contact : field.dataset.company);
		data.set('field', field.dataset.field);
		data.set('value', field.dataset.type === 'phone' ? composePhone(field.querySelector('.contacthub-phone-input-group')) : input.value);
		field.classList.add('is-saving');
		saveButton.disabled = true;
		message.textContent = tr('saving', 'Saving…');
		fetch(CONTACTHUB_CONFIG.ajaxUrl, requestOptions(data))
			.then(parseResponse)
			.then(function (result) {
				if (!result.success) throw new Error(result.data && result.data.message ? result.data.message : tr('saveFailed', 'Saving failed.'));
				var valueNode = field.querySelector('.contacthub-field-value');
				valueNode.dataset.value = result.data.value;
				valueNode.textContent = result.data.display;
				if (field.dataset.field === 'email') {
					var mailLink = field.querySelector('.contacthub-mail-link');
					if (mailLink) mailLink.href = 'mailto:' + result.data.value;
				}
				if (field.dataset.field === 'uid') setVatCheck(field, !!result.data.verified);
				if (field.dataset.field === 'first_name' && result.data.salutation) {
					var salutation = document.querySelector('.contacthub-salutation-field[data-contact="' + field.dataset.contact + '"] .contacthub-salutation-select');
					if (salutation && !salutation.value) salutation.value = result.data.salutation;
				}
				stopEditing(field, true);
				message.textContent = tr('saved', 'Saved.');
				message.classList.add('is-success');
				window.setTimeout(function () { message.textContent = ''; message.classList.remove('is-success'); }, 2500);
			})
			.catch(function (error) {
				field.classList.remove('is-saving');
				saveButton.disabled = false;
				message.textContent = error.message;
				message.classList.remove('is-success');
			});
	}

	function setVatCheck(field, verified) {
		var check = field.querySelector('.contacthub-vat-check');
		if (!check) return;
		check.hidden = !verified;
		field.dataset.verified = verified ? '1' : '0';
	}

	function openConsent(card) {
		card.querySelector('.contacthub-consent-modal').hidden = false;
		document.body.classList.add('contacthub-modal-open');
		card.querySelector('.contacthub-consent-confirmed').checked = false;
		card.querySelector('.contacthub-modal-message').textContent = '';
		updateConsentConfirmation(card);
		card.querySelector('.contacthub-consent-decision').focus();
	}

	function closeConsent(card) {
		card.querySelector('.contacthub-consent-modal').hidden = true;
		document.body.classList.remove('contacthub-modal-open');
	}

	function updateConsentConfirmation(card) {
		var revoke = card.querySelector('.contacthub-consent-decision').value === 'revoke';
		card.querySelector('.contacthub-confirm span').textContent = revoke
			? tr('confirmWithdrawal', 'I confirm that the withdrawal or unsubscribe has actually been documented.')
			: tr('confirmConsent', 'I confirm that explicit consent has been given.');
	}

	function saveConsent(card) {
		var button = card.querySelector('.contacthub-save-consent');
		var message = card.querySelector('.contacthub-modal-message');
		var data = new URLSearchParams();
		data.set('action', 'contacthub_update_marketing_consent');
		data.set('nonce', CONTACTHUB_CONFIG.nonce);
		data.set('contact_id', card.dataset.contact);
		data.set('decision', card.querySelector('.contacthub-consent-decision').value);
		data.set('source', card.querySelector('.contacthub-consent-source').value);
		data.set('date', card.querySelector('.contacthub-consent-date').value);
		data.set('note', card.querySelector('.contacthub-consent-note').value);
		data.set('confirmed', card.querySelector('.contacthub-consent-confirmed').checked ? '1' : '0');
		button.disabled = true;
		message.classList.remove('is-success');
		message.textContent = tr('savingConfirmed', 'Saving with confirmation…');
		fetch(CONTACTHUB_CONFIG.ajaxUrl, requestOptions(data))
			.then(parseResponse)
			.then(function (result) {
				if (!result.success) throw new Error(result.data && result.data.message ? result.data.message : tr('saveFailed', 'Saving failed.'));
				message.textContent = result.data.message;
				message.classList.add('is-success');
				window.setTimeout(function () { window.location.reload(); }, 900);
			})
			.catch(function (error) {
				button.disabled = false;
				message.textContent = error.message;
			});
	}

	function selectedContactIds() {
		return Array.prototype.slice.call(document.querySelectorAll('.contacthub-person-select:checked')).map(function (checkbox) { return checkbox.value; });
	}

	function updateBulkSelection() {
		var selected = selectedContactIds();
		var checkboxes = document.querySelectorAll('.contacthub-person-select');
		var selectAll = document.querySelector('.contacthub-select-all');
		var action = document.getElementById('contacthub-bulk-action');
		var button = document.querySelector('.contacthub-open-bulk');
		var label = document.querySelector('.contacthub-bulk-selection');
		if (selectAll) {
			selectAll.checked = checkboxes.length > 0 && selected.length === checkboxes.length;
			selectAll.indeterminate = selected.length > 0 && selected.length < checkboxes.length;
		}
		if (label) label.textContent = selected.length === 1 ? tr('onePersonSelected', '1 person selected') : format(tr('peopleSelected', '%d people selected'), selected.length);
		if (button) button.disabled = !selected.length || !action || action.value !== 'marketable';
		var outputModal = document.querySelector('.contacthub-output-modal');
		if (outputModal) {
			var selectedOption = outputModal.querySelector('input[name="scope"][value="selected"]');
			var selectedLabel = outputModal.querySelector('.contacthub-output-selected-label');
			if (selectedOption) selectedOption.disabled = !selected.length;
			if (selectedLabel) selectedLabel.textContent = selected.length === 1 ? tr('onePersonSelected', '1 person selected') : format(tr('peopleSelected', '%d people selected'), selected.length);
		}
	}

	function openContactOutput(mode) {
		var modal = document.querySelector('.contacthub-output-modal');
		if (!modal) return;
		var form = modal.querySelector('.contacthub-output-form');
		var isPrint = mode === 'print';
		var selected = selectedContactIds();
		modal.hidden = false;
		document.body.classList.add('contacthub-modal-open');
		modal.querySelector('#contacthub-output-title').textContent = isPrint ? tr('printContactList', 'Print contact list') : tr('exportContacts', 'Export contacts');
		modal.querySelector('.contacthub-output-columns').hidden = false;
		modal.querySelector('.contacthub-submit-output').textContent = isPrint ? tr('printContactList', 'Print contact list') : tr('exportContacts', 'Export contacts');
		form.querySelector('input[name="action"]').value = isPrint ? 'contacthub_print_contacts' : 'contacthub_export_contacts';
		form.target = isPrint ? '_blank' : '_self';
		form.querySelector('input[name="selected_ids"]').value = selected.join(',');
		var selectedOption = form.querySelector('input[name="scope"][value="selected"]');
		selectedOption.disabled = !selected.length;
		if (!selected.length && selectedOption.checked) form.querySelector('input[name="scope"][value="all"]').checked = true;
		updateBulkSelection();
	}

	function closeContactOutput() {
		var modal = document.querySelector('.contacthub-output-modal');
		if (modal) modal.hidden = true;
		document.body.classList.remove('contacthub-modal-open');
	}

	function openBulkMarketing() {
		var ids = selectedContactIds();
		var modal = document.querySelector('.contacthub-bulk-modal');
		if (!ids.length || !modal) return;
		modal.hidden = false;
		document.body.classList.add('contacthub-modal-open');
		modal.querySelector('.contacthub-bulk-confirmed').checked = false;
		modal.querySelector('.contacthub-modal-message').textContent = '';
		modal.querySelector('.contacthub-bulk-modal-count strong').textContent = ids.length === 1 ? tr('onePersonSelected', '1 person selected') : format(tr('peopleSelected', '%d people selected'), ids.length);
		modal.querySelector('.contacthub-bulk-source').focus();
	}

	function closeBulkMarketing() {
		var modal = document.querySelector('.contacthub-bulk-modal');
		if (modal) modal.hidden = true;
		document.body.classList.remove('contacthub-modal-open');
	}

	function saveBulkMarketing() {
		var modal = document.querySelector('.contacthub-bulk-modal');
		var ids = selectedContactIds();
		if (!modal || !ids.length) return;
		var button = modal.querySelector('.contacthub-save-bulk');
		var message = modal.querySelector('.contacthub-modal-message');
		var data = new URLSearchParams();
		data.set('action', 'contacthub_bulk_marketing_consent');
		data.set('nonce', CONTACTHUB_CONFIG.nonce);
		ids.forEach(function (id) { data.append('contact_ids[]', id); });
		data.set('source', modal.querySelector('.contacthub-bulk-source').value);
		data.set('date', modal.querySelector('.contacthub-bulk-date').value);
		data.set('note', modal.querySelector('.contacthub-bulk-note').value);
		data.set('confirmed', modal.querySelector('.contacthub-bulk-confirmed').checked ? '1' : '0');
		button.disabled = true;
		message.classList.remove('is-success');
		message.textContent = tr('checkingContacts', 'Checking the selected contacts…');
		fetch(CONTACTHUB_CONFIG.ajaxUrl, requestOptions(data))
			.then(parseResponse)
			.then(function (result) {
				if (!result.success) throw new Error(result.data && result.data.message ? result.data.message : tr('bulkEditFailed', 'Bulk editing failed.'));
				message.textContent = result.data.message;
				message.classList.add('is-success');
				window.setTimeout(function () { window.location.reload(); }, 1600);
			})
			.catch(function (error) {
				button.disabled = false;
				message.textContent = error.message;
			});
	}

	function openNewPerson() {
		var modal = document.querySelector('.contacthub-new-person-modal');
		if (!modal) return;
		modal.hidden = false;
		document.body.classList.add('contacthub-modal-open');
		modal.querySelector('.contacthub-modal-message').textContent = '';
		modal.querySelector('.contacthub-new-first-name').focus();
		updateNewPersonSalutationSuggestion(modal);
	}

	function updateNewPersonSalutationSuggestion(modal) {
		if (!modal) return;
		var input = modal.querySelector('.contacthub-new-first-name');
		var select = modal.querySelector('.contacthub-new-salutation');
		if (!select || !input) return;
		var first = String(input.value || '').trim().split(/[\s-]+/)[0].toLocaleLowerCase();
		var suggestions = CONTACTHUB_CONFIG.salutationSuggestions || {};
		var value = suggestions[first] || '';
		if (!select.value || select.dataset.autoAssigned === '1') {
			select.value = value;
			select.dataset.autoAssigned = value ? '1' : '0';
		}
	}

	function closeNewPerson() {
		var modal = document.querySelector('.contacthub-new-person-modal');
		if (modal) modal.hidden = true;
		document.body.classList.remove('contacthub-modal-open');
	}

	function saveNewPerson() {
		var modal = document.querySelector('.contacthub-new-person-modal');
		if (!modal) return;
		var button = modal.querySelector('.contacthub-save-new-person');
		var message = modal.querySelector('.contacthub-modal-message');
		var data = new URLSearchParams();
		data.set('action', 'contacthub_create_contact');
		data.set('nonce', CONTACTHUB_CONFIG.nonce);
		data.set('first_name', modal.querySelector('.contacthub-new-first-name').value);
		data.set('last_name', modal.querySelector('.contacthub-new-last-name').value);
		data.set('email', modal.querySelector('.contacthub-new-email').value);
		data.set('salutation', modal.querySelector('.contacthub-new-salutation').value);
		var companyName = modal.querySelector('.contacthub-new-company-name');
		var companyId = modal.querySelector('.contacthub-new-company-id');
		var companyPosition = modal.querySelector('.contacthub-new-company-position');
		if (companyName) data.set('company_name', companyName.value);
		if (companyId) data.set('company_id', companyId.value);
		if (companyPosition) data.set('company_position', companyPosition.value);
		button.disabled = true;
		message.classList.remove('is-success');
		message.textContent = tr('creatingPerson', 'Creating person…');
		fetch(CONTACTHUB_CONFIG.ajaxUrl, requestOptions(data))
			.then(parseResponse)
			.then(function (result) {
				if (!result.success) throw new Error(result.data && result.data.message ? result.data.message : tr('createFailed', 'Creation failed.'));
				message.textContent = result.data.message;
				message.classList.add('is-success');
				window.location.href = result.data.redirect;
			})
			.catch(function (error) {
				button.disabled = false;
				message.textContent = error.message;
			});
	}

	function openNewCompany() {
		var modal = document.querySelector('.contacthub-new-company-modal');
		if (!modal) return;
		modal.hidden = false;
		document.body.classList.add('contacthub-modal-open');
		modal.querySelector('.contacthub-modal-message').textContent = '';
		modal.querySelector('.contacthub-new-company-name-only').focus();
	}

	function closeNewCompany() {
		var modal = document.querySelector('.contacthub-new-company-modal');
		if (modal) modal.hidden = true;
		document.body.classList.remove('contacthub-modal-open');
	}

	function saveNewCompany() {
		var modal = document.querySelector('.contacthub-new-company-modal');
		if (!modal) return;
		var button = modal.querySelector('.contacthub-save-new-company');
		var message = modal.querySelector('.contacthub-modal-message');
		var data = new URLSearchParams();
		data.set('action', 'contacthub_create_company');
		data.set('nonce', CONTACTHUB_CONFIG.nonce);
		data.set('name', modal.querySelector('.contacthub-new-company-name-only').value);
		button.disabled = true;
		message.textContent = tr('creatingCompany', 'Creating company…');
		fetch(CONTACTHUB_CONFIG.ajaxUrl, requestOptions(data)).then(parseResponse).then(function (result) {
			if (!result.success) throw new Error(result.data && result.data.message ? result.data.message : tr('createFailed', 'Creation failed.'));
			window.location.href = result.data.redirect;
		}).catch(function (error) {
			button.disabled = false;
			message.textContent = error.message;
		});
	}

	function manageTag(trigger, operation) {
		var card = trigger.closest('.contacthub-tags-card');
		var manager = card ? card.querySelector('.contacthub-tag-manager') : null;
		if (!card || !manager) return;
		var tagId = '';
		var tagName = '';
		var message = manager.querySelector('.contacthub-tag-message');
		if (operation === 'commit') {
			var input = manager.querySelector('.contacthub-tag-input');
			tagName = input ? input.value.trim() : '';
			if (!tagName) {
				message.textContent = tr('enterTagName', 'Please enter a tag name.');
				return;
			}
			var option = Array.prototype.slice.call(manager.querySelectorAll('.contacthub-tag-suggestion')).find(function (item) {
				return item.dataset.name.toLocaleLowerCase() === tagName.toLocaleLowerCase();
			});
			if (option && option.dataset.tag) {
				tagId = option.dataset.tag;
				if (manager.querySelector('.contacthub-tag[data-tag="' + tagId + '"]')) {
					message.textContent = tr('tagAlreadyAssigned', 'This tag is already assigned to the person.');
					input.value = '';
					return;
				}
				operation = 'add';
			} else {
				operation = 'create';
			}
		}
		if (operation === 'add') {
			tagId = tagId || trigger.dataset.tag;
			tagName = trigger.dataset.name || tagName;
			if (manager.querySelector('.contacthub-tag[data-tag="' + tagId + '"]')) {
				message.textContent = tr('tagAlreadyAssigned', 'This tag is already assigned to the person.');
				return;
			}
		}
		if (operation === 'remove') tagId = trigger.dataset.tag;
		var data = new URLSearchParams();
		data.set('action', 'contacthub_manage_contact_tag');
		data.set('nonce', CONTACTHUB_CONFIG.nonce);
		data.set('contact_id', card.dataset.contact);
		data.set('operation', operation);
		data.set('tag_id', tagId);
		data.set('tag_name', tagName);
		trigger.disabled = true;
		message.classList.remove('is-success');
		message.textContent = tr('runningTagAction', 'Running tag action…');
		fetch(CONTACTHUB_CONFIG.ajaxUrl, requestOptions(data))
			.then(parseResponse)
			.then(function (result) {
				if (!result.success) throw new Error(result.data && result.data.message ? result.data.message : tr('tagActionFailed', 'Tag action failed.'));
				message.textContent = result.data.message;
				message.classList.add('is-success');
				var savedId = String(result.data.tag_id || tagId);
				var savedName = result.data.tag_name || tagName;
				var summary = card.querySelector('.contacthub-tags-summary');
				if (result.data.operation === 'remove') {
					var managerToken = manager.querySelector('.contacthub-tag[data-tag="' + savedId + '"]'); if (managerToken) managerToken.remove();
					var summaryToken = summary && summary.querySelector('.contacthub-tag[data-tag="' + savedId + '"]'); if (summaryToken) summaryToken.remove();
				} else {
					var inputField = manager.querySelector('.contacthub-token-field');
					var input = manager.querySelector('.contacthub-tag-input');
					if (!manager.querySelector('.contacthub-tag[data-tag="' + savedId + '"]')) {
						var token = document.createElement('span'); token.className = 'contacthub-tag'; token.dataset.tag = savedId; token.appendChild(document.createTextNode(savedName));
						var remove = document.createElement('button'); remove.type = 'button'; remove.className = 'button-link contacthub-remove-tag'; remove.dataset.tag = savedId; remove.setAttribute('aria-label', tr('removeTag', 'Remove tag from this person')); remove.textContent = '×'; token.appendChild(remove);
						inputField.insertBefore(token, input);
					}
					if (summary) {
						var muted = summary.querySelector('.contacthub-muted'); if (muted) muted.remove();
						if (!summary.querySelector('.contacthub-tag[data-tag="' + savedId + '"]')) { var summaryTag = document.createElement('span'); summaryTag.className = 'contacthub-tag'; summaryTag.dataset.tag = savedId; summaryTag.textContent = savedName; summary.appendChild(summaryTag); }
					}
				}
				var tagInput = manager.querySelector('.contacthub-tag-input'); if (tagInput) tagInput.value = '';
				trigger.disabled = false;
				if (summary && !summary.querySelector('.contacthub-tag')) { var none = document.createElement('span'); none.className = 'contacthub-muted'; none.textContent = tr('noTags', 'No tags assigned yet.'); summary.appendChild(none); }
				window.setTimeout(function () { message.textContent = ''; message.classList.remove('is-success'); }, 2000);
			})
			.catch(function (error) {
				trigger.disabled = false;
				message.textContent = error.message;
			});
	}

	function openTags(card) {
		if (!card) return;
		var summary = card.querySelector('.contacthub-tags-summary');
		var manager = card.querySelector('.contacthub-tag-manager');
		if (summary) summary.hidden = true;
		if (manager) manager.hidden = false;
		var input = manager && manager.querySelector('.contacthub-tag-input');
		if (input) input.focus();
	}

	function closeTags(card) {
		if (!card) return;
		var summary = card.querySelector('.contacthub-tags-summary');
		var manager = card.querySelector('.contacthub-tag-manager');
		if (summary) summary.hidden = false;
		if (manager) manager.hidden = true;
	}

	function previewBroadcast(button) {
		var data = new URLSearchParams();
		data.set('action', 'contacthub_preview_broadcast');
		data.set('nonce', CONTACTHUB_CONFIG.nonce);
		data.set('contact_id', button.dataset.contact);
		data.set('broadcast_id', button.dataset.broadcast);
		button.disabled = true;
		fetch(CONTACTHUB_CONFIG.ajaxUrl, requestOptions(data))
			.then(parseResponse)
			.then(function (result) {
				if (!result.success) throw new Error(result.data && result.data.message ? result.data.message : tr('broadcastLoadFailed', 'The broadcast could not be loaded.'));
				openPreviewFrame(result.data.title || tr('broadcast', 'Broadcast'), '', result.data.html || '');
				button.disabled = false;
			})
			.catch(function (error) {
				button.disabled = false;
				window.alert(error.message);
			});
	}

	function previewRecord(button, action, idKey, id, fallbackTitle) {
		var data = new URLSearchParams();
		data.set('action', action);
		data.set('nonce', CONTACTHUB_CONFIG.nonce);
		data.set(idKey, id);
		button.disabled = true;
		fetch(CONTACTHUB_CONFIG.ajaxUrl, requestOptions(data))
			.then(parseResponse)
			.then(function (result) {
				if (!result.success) throw new Error(result.data && result.data.message ? result.data.message : tr('previewLoadFailed', 'The preview could not be loaded.'));
				openPreviewFrame(result.data.title || fallbackTitle, '', result.data.html || '', result.data.edit_url || '');
				button.disabled = false;
			})
			.catch(function (error) {
				button.disabled = false;
				window.alert(error.message);
			});
	}

	function toggleLocalModal(modal, open) {
		if (!modal) return;
		modal.hidden = !open;
		document.body.classList.toggle('contacthub-modal-open', open);
		if (open) {
			var focusTarget = modal.querySelector('.contacthub-role-input,input,button');
			if (focusTarget) focusTarget.focus();
		}
	}

	function selectedRoles(modal) {
		return Array.prototype.slice.call(modal.querySelectorAll('.contacthub-role-token')).map(function (token) { return token.dataset.role; });
	}

	function updateTokenSuggestions(input) {
		var entry = input.closest('.contacthub-token-entry');
		var suggestions = entry ? entry.querySelector('.contacthub-token-suggestions') : null;
		if (!suggestions) return;
		var query = input.value.trim().toLocaleLowerCase();
		var matches = 0;
		suggestions.querySelectorAll('.contacthub-token-suggestion').forEach(function (suggestion) {
			var assigned = suggestion.dataset.tag
				? !!entry.querySelector('.contacthub-tag[data-tag="' + suggestion.dataset.tag + '"]')
				: !!entry.querySelector('.contacthub-role-token[data-role="' + suggestion.dataset.role + '"]');
			var visible = !!query && !assigned && suggestion.dataset.name.toLocaleLowerCase().indexOf(query) !== -1 && matches < 10;
			suggestion.hidden = !visible;
			if (visible) matches += 1;
		});
		suggestions.hidden = matches === 0;
	}

	function closeTokenSuggestions(input) {
		var entry = input ? input.closest('.contacthub-token-entry') : null;
		var suggestions = entry ? entry.querySelector('.contacthub-token-suggestions') : null;
		if (suggestions) suggestions.hidden = true;
	}

	function addRole(trigger) {
		var picker = trigger.closest('.contacthub-role-picker');
		if (!picker) return;
		var input = picker.querySelector('.contacthub-role-input');
		var message = picker.querySelector('.contacthub-role-picker-message');
		var value = trigger.dataset.name || input.value.trim();
		var option = trigger.classList.contains('contacthub-role-suggestion') ? trigger : Array.prototype.slice.call(picker.querySelectorAll('.contacthub-role-suggestion')).find(function (item) {
			return item.dataset.name.toLocaleLowerCase() === value.toLocaleLowerCase();
		});
		if (!option || !option.dataset.role) {
			message.textContent = tr('selectSuggestedRole', 'Please select a suggested WordPress role.');
			return;
		}
		if (picker.querySelector('.contacthub-role-token[data-role="' + option.dataset.role + '"]')) {
			message.textContent = tr('roleAlreadyAssigned', 'This role is already assigned.');
			input.value = '';
			return;
		}
		var token = document.createElement('span');
		token.className = 'contacthub-role-token';
		token.dataset.role = option.dataset.role;
		token.appendChild(document.createTextNode(option.dataset.name));
		var remove = document.createElement('button');
		remove.type = 'button';
		remove.className = 'button-link contacthub-remove-role';
		remove.setAttribute('aria-label', format(tr('removeRole', 'Remove role %s'), option.dataset.name));
		remove.innerHTML = '&times;';
		token.appendChild(remove);
		picker.querySelector('.contacthub-token-field').insertBefore(token, input);
		input.value = '';
		closeTokenSuggestions(input);
		message.textContent = '';
		input.focus();
	}

	function saveRoles(button) {
		var modal = button.closest('.contacthub-roles-modal');
		var panel = modal.querySelector('.contacthub-modal-panel');
		var roles = selectedRoles(modal);
		if (!roles.length || !window.confirm(tr('confirmSaveRoles', 'Save the selected roles and the resulting permissions for this account?'))) return;
		var data = new URLSearchParams();
		data.set('action', 'contacthub_update_user_roles');
		data.set('nonce', CONTACTHUB_CONFIG.nonce);
		data.set('contact_id', panel.dataset.contact);
		data.set('user_id', panel.dataset.user);
		roles.forEach(function (role) { data.append('roles[]', role); });
		saveAccountRequest(button, modal, data);
	}

	function saveUserAccount(button) {
		var modal = button.closest('.contacthub-user-account-modal');
		var panel = modal.querySelector('.contacthub-modal-panel');
		var mode = panel.dataset.mode || 'create';
		var question = mode === 'link' ? tr('confirmLinkAccount', 'Link the existing account to this person?') : tr('confirmCreateAccount', 'Create the new user account now?');
		if (!window.confirm(question)) return;
		var data = new URLSearchParams();
		data.set('action', 'contacthub_create_user_account');
		data.set('nonce', CONTACTHUB_CONFIG.nonce);
		data.set('contact_id', panel.dataset.contact);
		data.set('mode', mode);
		if (mode === 'create') {
			data.set('username', modal.querySelector('.contacthub-account-username').value);
			data.set('send_invite', modal.querySelector('.contacthub-account-invite').checked ? '1' : '0');
			selectedRoles(modal).forEach(function (role) { data.append('roles[]', role); });
		}
		saveAccountRequest(button, modal, data);
	}

	function saveAccountRequest(button, modal, data) {
		var message = modal.querySelector('.contacthub-modal-message');
		button.disabled = true;
		message.classList.remove('is-success');
		message.textContent = tr('saving', 'Saving…');
		fetch(CONTACTHUB_CONFIG.ajaxUrl, requestOptions(data))
			.then(parseResponse)
			.then(function (result) {
				if (!result.success) throw new Error(result.data && result.data.message ? result.data.message : tr('saveFailed', 'Saving failed.'));
				message.textContent = result.data.message;
				message.classList.add('is-success');
				window.setTimeout(function () { window.location.reload(); }, 700);
			})
			.catch(function (error) {
				button.disabled = false;
				message.textContent = error.message;
			});
	}

	function openPreviewFrame(title, url, html, fallbackUrl) {
		closePreviewFrame();
		var modal = document.createElement('div');
		modal.className = 'contacthub-consent-modal contacthub-preview-modal';
		modal.innerHTML = '<div class="contacthub-preview-panel" role="dialog" aria-modal="true"><div class="contacthub-preview-header"><h2></h2><div><a class="button contacthub-preview-external" target="_blank" rel="noopener">' + tr('openNewWindow', 'Open in new window') + '</a> <button type="button" class="button contacthub-close-preview">' + tr('close', 'Close') + '</button></div></div><iframe class="contacthub-preview-frame" title="' + tr('preview', 'Preview') + '"></iframe></div>';
		modal.querySelector('h2').textContent = title;
		var external = modal.querySelector('.contacthub-preview-external');
		if (fallbackUrl || url) external.href = fallbackUrl || url;
		else external.hidden = true;
		var frame = modal.querySelector('.contacthub-preview-frame');
		if (html) {
			frame.setAttribute('sandbox', 'allow-downloads allow-popups');
			frame.srcdoc = html;
		}
		else frame.src = url;
		document.body.appendChild(modal);
		document.body.classList.add('contacthub-modal-open');
		modal.querySelector('.contacthub-close-preview').focus();
	}

	function closePreviewFrame() {
		var modal = document.querySelector('.contacthub-preview-modal');
		if (modal) modal.remove();
		if (!document.querySelector('.contacthub-consent-modal:not([hidden])')) document.body.classList.remove('contacthub-modal-open');
	}

	function saveAddress(form) {
		var button = form.querySelector('.contacthub-save-address');
		var message = form.querySelector('.contacthub-address-message');
		var data = new URLSearchParams();
		data.set('action', 'contacthub_update_customer_address');
		data.set('nonce', CONTACTHUB_CONFIG.nonce);
		data.set('contact_id', form.dataset.contact);
		data.set('address_type', form.dataset.addressType);
		form.querySelectorAll('[data-key]').forEach(function (field) { data.set(field.dataset.key, field.value); });
		var phoneGroup = form.querySelector('.contacthub-phone-input-group');
		if (phoneGroup) data.set('phone', composePhone(phoneGroup));
		var syncToWooCommerce = form.querySelector('.contacthub-sync-to-woocommerce');
		if (syncToWooCommerce && syncToWooCommerce.checked) data.set('sync_to_woocommerce', '1');
		button.disabled = true;
		message.classList.remove('is-success');
		message.textContent = tr('savingAddress', 'Saving address…');
		fetch(CONTACTHUB_CONFIG.ajaxUrl, requestOptions(data))
			.then(parseResponse)
			.then(function (result) {
				if (!result.success) throw new Error(result.data && result.data.message ? result.data.message : tr('saveFailed', 'Saving failed.'));
				message.textContent = result.data.message;
				message.classList.add('is-success');
				if (form.dataset.addressType === 'contact' && result.data.display) {
					var summary = document.querySelector('.contacthub-address-summary-value');
					if (summary) summary.textContent = result.data.display;
				}
				button.disabled = false;
				window.setTimeout(function () { message.textContent = ''; message.classList.remove('is-success'); }, 3000);
			})
			.catch(function (error) {
				button.disabled = false;
				message.textContent = error.message;
			});
	}

	function buildPhoneGroup(value) {
		var parsed = parsePhone(value);
		var group = document.createElement('span');
		group.className = 'contacthub-phone-input-group';
		var select = document.createElement('select');
		select.className = 'contacthub-phone-prefix';
		select.setAttribute('aria-label', tr('countryCallingCode', 'Country calling code'));
		(CONTACTHUB_CONFIG.phoneCountries || []).forEach(function (country) {
			var option = document.createElement('option');
			option.value = country.code;
			option.textContent = country.label + ' ' + country.code;
			option.selected = country.code === parsed.code;
			select.appendChild(option);
		});
		var input = document.createElement('input');
		input.type = 'tel';
		input.className = 'contacthub-input contacthub-phone-national';
		input.value = parsed.national;
		input.placeholder = tr('phoneNumber', 'Phone number');
		group.appendChild(select);
		group.appendChild(input);
		return group;
	}

	function parsePhone(value) {
		var normalized = String(value || '').trim().replace(/^00/, '+').replace(/[^0-9+]/g, '');
		var countries = (CONTACTHUB_CONFIG.phoneCountries || []).slice().sort(function (a, b) { return b.code.length - a.code.length; });
		for (var i = 0; i < countries.length; i++) {
			if (normalized.indexOf(countries[i].code) === 0) return {code: countries[i].code, national: normalized.slice(countries[i].code.length)};
		}
		return {code: '+43', national: normalized.replace(/^\+/, '').replace(/^0/, '')};
	}

	function detectPhonePrefix(input) {
		var raw = input.value.trim().replace(/^00/, '+').replace(/[^0-9+]/g, '');
		if (raw.charAt(0) !== '+') return;
		var group = input.closest('.contacthub-phone-input-group');
		var countries = (CONTACTHUB_CONFIG.phoneCountries || []).slice().sort(function (a, b) { return b.code.length - a.code.length; });
		for (var i = 0; i < countries.length; i++) {
			if (raw.indexOf(countries[i].code) === 0) {
				group.querySelector('.contacthub-phone-prefix').value = countries[i].code;
				var national = raw.slice(countries[i].code.length);
				if (countries[i].code !== '+39') national = national.replace(/^0/, '');
				input.value = national;
				return;
			}
		}
	}

	function formatPhoneNational(input) {
		var group = input.closest('.contacthub-phone-input-group');
		if (!group) return;
		var code = group.querySelector('.contacthub-phone-prefix').value || '+43';
		var national = input.value.replace(/\D/g, '');
		if (code !== '+39') national = national.replace(/^0/, '');
		if (code !== '+43' || !national) {
			input.value = national;
			return;
		}
		var prefix = '';
		['316', '463', '512', '662', '732'].some(function (known) {
			if (national.indexOf(known) === 0) { prefix = known; return true; }
			return false;
		});
		if (!prefix) {
			var mobile = national.match(/^(65[0-9]|66[0-9]|67[0-9]|68[0-9]|69[0-9]|710|720|730|740|780|800|810|820|828|900|901|930|931|939)/);
			if (mobile) prefix = mobile[1];
		}
		if (!prefix && national.charAt(0) === '1') prefix = '1';
		if (!prefix) {
			var local = national.match(/^[2-7][0-9]{3}/);
			if (local) prefix = local[0];
		}
		input.value = prefix && national.length > prefix.length ? prefix + ' ' + national.slice(prefix.length) : national;
	}

	function composePhone(group) {
		if (!group) return '';
		var code = group.querySelector('.contacthub-phone-prefix').value || '+43';
		var national = group.querySelector('.contacthub-phone-national').value.replace(/\D/g, '');
		if (code !== '+39') national = national.replace(/^0/, '');
		return national ? code + national : '';
	}

	function requestOptions(data) {
		return {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'}, body: data.toString()};
	}

	function parseResponse(response) {
		return response.json().catch(function () { throw new Error(tr('invalidServerResponse', 'The server returned an invalid response.')); });
	}

	document.addEventListener('change', function (event) {
		if (event.target.matches('[data-contacthub-merge-field]')) {
			var mergeField = event.target.getAttribute('data-contacthub-merge-field');
			var mergeTarget = document.querySelector('[data-contacthub-merge-target="' + mergeField + '"]');
			if (mergeTarget) mergeTarget.value = event.target.getAttribute('data-contacthub-merge-value') || '';
			return;
		}
		if (event.target.matches('.contacthub-settings-color-field input[type="color"]')) {
			var colorValue = event.target.closest('.contacthub-settings-color-field').querySelector('.contacthub-settings-color-value');
			if (colorValue) colorValue.textContent = event.target.value;
			return;
		}
		if (event.target.matches('.contacthub-select-all-tags')) {
			document.querySelectorAll('.contacthub-tag-bulk-form input[name="tag_ids[]"]').forEach(function (checkbox) { checkbox.checked = event.target.checked; });
			return;
		}
		if (!event.target.matches('[data-contacthub-delete-on-uninstall]') || !event.target.checked) return;
		var message = tr('confirmDeleteOnUninstall', 'Do you really want ContactHub-owned customer data to be permanently deleted when the plugin is uninstalled?');
		if (!window.confirm(message)) event.target.checked = false;
	});

	document.addEventListener('submit', function (event) {
		if (event.target.matches('.contacthub-tag-bulk-form')) {
			var selected = event.target.querySelectorAll('input[name="tag_ids[]"]:checked');
			var action = event.target.querySelector('[name="bulk_action"]').value;
			if (action !== 'delete' || !selected.length || !window.confirm(tr('confirmBulkDeleteTags', 'Delete the selected tags and all their contact assignments?'))) {
				event.preventDefault();
				return;
			}
			event.target.querySelector('[name="delete_confirmed"]').value = '1';
			return;
		}
		if (event.target.matches('.contacthub-delete-tag-form')) {
			var form = event.target;
			var template = tr('confirmDeleteTag', 'Delete the tag “%1$s”? It is currently assigned to %2$d people. The people themselves will not be deleted.');
			var question = template.replace('%1$s', form.dataset.name || '').replace('%2$d', form.dataset.count || '0');
			if (!window.confirm(question)) {
				event.preventDefault();
				return;
			}
			form.querySelector('[name="delete_confirmed"]').value = '1';
			return;
		}
		if (!event.target.matches('.contacthub-inline-action-form') || !event.target.querySelector('.contacthub-reset-test-data')) return;
	});

	document.addEventListener('click', function (event) {
		var link = event.target.closest('.contacthub-delete-tag-link');
		if (!link) return;
		var template = tr('confirmDeleteTag', 'Delete the tag “%1$s”? It is currently assigned to %2$d people. The people themselves will not be deleted.');
		var question = template.replace('%1$s', link.dataset.name || '').replace('%2$d', link.dataset.count || '0');
		if (!window.confirm(question)) event.preventDefault();
	});

	document.addEventListener('change', function (event) {
		if (!event.target.matches('.contacthub-file-drop input[type="file"]')) return;
		var drop = event.target.closest('.contacthub-file-drop');
		var name = drop && drop.querySelector('.contacthub-file-drop-name');
		if (name) name.textContent = event.target.files.length ? event.target.files[0].name : '';
		if (drop) drop.classList.toggle('has-file', !!event.target.files.length);
	});

	document.addEventListener('dragover', function (event) {
		var drop = event.target.closest('.contacthub-file-drop');
		if (!drop) return;
		event.preventDefault();
		drop.classList.add('is-dragging');
	});

	document.addEventListener('dragleave', function (event) {
		var drop = event.target.closest('.contacthub-file-drop');
		if (drop) drop.classList.remove('is-dragging');
	});

	document.addEventListener('drop', function (event) {
		var drop = event.target.closest('.contacthub-file-drop');
		if (!drop) return;
		event.preventDefault();
		drop.classList.remove('is-dragging');
		var input = drop.querySelector('input[type="file"]');
		if (!input || !event.dataTransfer || !event.dataTransfer.files.length) return;
		input.files = event.dataTransfer.files;
		input.dispatchEvent(new Event('change', { bubbles: true }));
	});
}());
