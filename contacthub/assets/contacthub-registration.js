(function () {
	'use strict';
	function setVatButtonState(button, verified) {
		if (!button) return;
		var defaultLabel = button.dataset.defaultLabel || button.textContent.trim() || 'Validate VAT ID';
		var successLabel = button.dataset.successLabel || 'VAT ID successfully validated';
		button.dataset.defaultLabel = defaultLabel;
		button.classList.toggle('is-valid', Boolean(verified));
		button.textContent = verified ? '✓' : defaultLabel;
		button.setAttribute('aria-label', verified ? successLabel : defaultLabel);
		button.title = verified ? successLabel : '';
	}

	function shippingMatchesBilling(form) {
		var keys = ['first_name', 'last_name', 'company', 'address_1', 'address_2', 'postcode', 'city', 'state', 'country'];
		return keys.every(function (key) {
			var shipping = form.querySelector('[name="shipping_' + key + '"]');
			var billing = form.querySelector('[name="billing_' + key + '"]');
			return !shipping || !billing || String(shipping.value || '').trim() === String(billing.value || '').trim();
		});
	}

	function configureShippingFields(form, enabled, clearCopied) {
		var panel = form.querySelector('.contacthub-shipping-account-fields');
		if (!panel) return;
		if (enabled && clearCopied && shippingMatchesBilling(form)) {
			panel.querySelectorAll('[name^="shipping_"]').forEach(function (field) {
				if (field.name !== 'shipping_country') field.value = '';
			});
		}
		panel.hidden = !enabled;
		panel.querySelectorAll('input, select, textarea').forEach(function (field) {
			field.disabled = !enabled;
			var key = field.name.replace(/^shipping_/, '');
			field.required = enabled && ['first_name', 'last_name', 'address_1', 'postcode', 'city', 'country'].indexOf(key) >= 0;
		});
	}
	document.addEventListener('toggle', function (event) {
		if (!event.target.matches || !event.target.matches('.contacthub-shipping-section')) return;
		var state = event.target.querySelector('[name="shipping_different"]');
		if (state) state.value = event.target.open ? '1' : '0';
	}, true);

	document.addEventListener('invalid', function (event) {
		var details = event.target.closest ? event.target.closest('details') : null;
		if (details) details.open = true;
	}, true);

	document.addEventListener('input', function (event) {
		if (event.target.name !== 'vat_id') return;
		var field = event.target.closest('.contacthub-vat-field');
		var message = field ? field.querySelector('.contacthub-vat-message') : null;
		if (message) {
			message.textContent = '';
			message.classList.remove('is-success');
		}
		event.target.dataset.verified = '0';
		setVatButtonState(field ? field.querySelector('.contacthub-vat-button') : null, false);
	});

	document.addEventListener('input', function (event) {
		if (!event.target.matches('.contacthub-profile-phone .contacthub-phone-national')) return;
		var input = event.target;
		var raw = input.value.trim().replace(/^00/, '+').replace(/[^0-9+]/g, '');
		if (raw.charAt(0) !== '+') return;
		var select = input.closest('.contacthub-profile-phone').querySelector('.contacthub-phone-prefix');
		var options = Array.prototype.slice.call(select.options).sort(function (left, right) { return right.value.length - left.value.length; });
		for (var i = 0; i < options.length; i++) {
			if (raw.indexOf(options[i].value) === 0) {
				select.value = options[i].value;
				var national = raw.slice(options[i].value.length);
				if (options[i].value !== '+39') national = national.replace(/^0/, '');
				input.value = national;
				return;
			}
		}
	});

	document.addEventListener('blur', function (event) {
		if (!event.target.matches('.contacthub-profile-phone .contacthub-phone-national')) return;
		var input = event.target;
		var select = input.closest('.contacthub-profile-phone').querySelector('.contacthub-phone-prefix');
		var national = input.value.replace(/\D/g, '');
		if (select.value !== '+39') national = national.replace(/^0/, '');
		if (select.value === '+43' && national) {
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
		} else {
			input.value = national;
		}
	}, true);

	document.addEventListener('click', function (event) {
		var copyAddress = event.target.closest('.contacthub-copy-profile-address');
		if (copyAddress) {
			var form = copyAddress.closest('form');
			var profile = {};
			try { profile = JSON.parse(copyAddress.dataset.profile || '{}'); } catch (ignore) {}
			Object.keys(profile).forEach(function (key) {
				var field = form.querySelector('[name="billing_' + key + '"]');
				if (field) field.value = profile[key] || '';
			});
			return;
		}
		var registrationToggle = event.target.closest('.contacthub-registration-toggle');
		if (registrationToggle) {
			var target = document.getElementById(registrationToggle.getAttribute('aria-controls'));
			if (!target) return;
			var willOpen = target.hidden;
			target.hidden = !willOpen;
			registrationToggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
			registrationToggle.textContent = willOpen ? (registrationToggle.dataset.closeLabel || 'Close registration') : (registrationToggle.dataset.openLabel || 'Register now');
			if (willOpen) target.scrollIntoView({behavior: 'smooth', block: 'start'});
			return;
		}
		var button = event.target.closest('.contacthub-vat-button');
		if (!button) return;
		var field = button.closest('.contacthub-vat-field');
		var input = field ? field.querySelector('[name="vat_id"]') : null;
		var message = field ? field.querySelector('.contacthub-vat-message') : null;
		var config = typeof CONTACTHUB_ACCOUNT !== 'undefined' ? CONTACTHUB_ACCOUNT : (typeof CONTACTHUB_REGISTRATION !== 'undefined' ? CONTACTHUB_REGISTRATION : null);
		if (!input || !message || !config) return;
		var data = new URLSearchParams();
		data.set('action', 'contacthub_registration_validate_vat');
		data.set('nonce', config.vatNonce);
		data.set('value', input.value);
		button.disabled = true;
		message.classList.remove('is-success');
		message.textContent = config.i18n.checkingVat;
		fetch(config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
			body: data.toString()
		}).then(function (response) {
			return response.json().then(function (result) {
				if (!response.ok || !result.success) throw new Error(result.data && result.data.message ? result.data.message : config.i18n.vatValidationFailed);
				return result;
			});
		}).then(function (result) {
			message.textContent = result.data.message;
			message.classList.add('is-success');
			input.dataset.verified = '1';
			setVatButtonState(button, true);
		}).catch(function (error) {
			message.textContent = error.message || config.i18n.vatValidationFailed;
		}).finally(function () {
			button.disabled = false;
		});
	});

	document.addEventListener('submit', function (event) {
		var form = event.target.closest ? event.target.closest('.contacthub-account-ajax-form') : null;
		if (!form || typeof CONTACTHUB_ACCOUNT === 'undefined') return;
		event.preventDefault();
		var button = form.querySelector('[type="submit"]');
		var message = form.querySelector('.contacthub-account-message');
		var original = button ? button.textContent : '';
		var data = new FormData(form);
		data.set('action', form.dataset.ajaxAction);
		data.set('nonce', CONTACTHUB_ACCOUNT.nonce);
		if (button) {
			button.disabled = true;
			button.textContent = CONTACTHUB_ACCOUNT.i18n.saving;
		}
		if (message) {
			message.textContent = CONTACTHUB_ACCOUNT.i18n.saving;
			message.classList.remove('is-success', 'is-error');
		}
		fetch(CONTACTHUB_ACCOUNT.ajaxUrl, {method: 'POST', credentials: 'same-origin', body: data})
			.then(function (response) {
				return response.json().then(function (result) {
					if (!response.ok || !result.success) throw new Error(result.data && result.data.message ? result.data.message : CONTACTHUB_ACCOUNT.i18n.saveFailed);
					return result;
				});
			})
			.then(function (result) {
				if (message) {
					message.textContent = result.data.message;
					message.classList.add('is-success');
				}
				var vatInput = form.querySelector('[name="vat_id"]');
				var vatButton = form.querySelector('.contacthub-vat-button');
				if (vatInput && result.data.vatVerified) vatInput.dataset.verified = '1';
				setVatButtonState(vatButton, Boolean(result.data.vatVerified));
			})
			.catch(function (error) {
				if (message) {
					message.textContent = error.message || CONTACTHUB_ACCOUNT.i18n.saveFailed;
					message.classList.add('is-error');
				}
			})
			.finally(function () {
				if (button) {
					button.disabled = false;
					button.textContent = original;
				}
			});
	}, true);

	document.addEventListener('change', function (event) {
		if (!event.target.matches('.contacthub-shipping-toggle input')) return;
		configureShippingFields(event.target.closest('form'), event.target.checked, event.target.checked);
	});

	document.querySelectorAll('.contacthub-shipping-toggle input').forEach(function (toggle) {
		configureShippingFields(toggle.closest('form'), toggle.checked, false);
	});

	document.querySelectorAll('.contacthub-vat-field').forEach(function (field) {
		var input = field.querySelector('[name="vat_id"]');
		setVatButtonState(field.querySelector('.contacthub-vat-button'), Boolean(input && input.dataset.verified === '1'));
	});

	var redirectNotice = document.querySelector('[data-contacthub-redirect]');
	if (redirectNotice) {
		var delay = Math.max(0, parseInt(redirectNotice.dataset.contacthubDelay || '0', 10));
		window.setTimeout(function () {
			window.location.assign(redirectNotice.dataset.contacthubRedirect);
		}, delay * 1000);
	}

	var accountRegistration = document.querySelector('.contacthub-account-registration-form');
	if (accountRegistration && accountRegistration.querySelector('.contacthub-registration-notice')) {
		accountRegistration.hidden = false;
		var accountToggle = document.querySelector('.contacthub-registration-toggle');
		if (accountToggle) accountToggle.setAttribute('aria-expanded', 'true');
	}

	var accountOverview = document.querySelector('.contacthub-account-overview');
	if (accountOverview) {
		var sibling = accountOverview.previousElementSibling;
		while (sibling && sibling.tagName === 'P') {
			sibling.classList.add('contacthub-default-dashboard-text');
			sibling = sibling.previousElementSibling;
		}
		Array.prototype.slice.call(accountOverview.parentNode.children).forEach(function (node) {
			if (node !== accountOverview && node.children.length === 0 && node.textContent.trim().toLocaleUpperCase() === 'ÜBERSICHTS-BEREICH') node.hidden = true;
		});
	}

	var addressForm = document.querySelector('.contacthub-address-account-form');
	if (addressForm) {
		var accountContent = addressForm.closest('.woocommerce-MyAccount-content') || addressForm.parentNode;
		Array.prototype.slice.call(accountContent.querySelectorAll('label')).forEach(function (label) {
			if (!label.classList.contains('contacthub-vat-field') && /EU\s*Umsatzsteuer[\s-]*Identifikationsnummer/i.test(label.textContent)) {
				var row = label.closest('.form-row, p, .woocommerce-form-row') || label;
				row.hidden = true;
			}
		});
	}
}());
