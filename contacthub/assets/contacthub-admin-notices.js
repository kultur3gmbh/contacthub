(function () {
	'use strict';
	var config = window.CONTACTHUB_NOTICE_CONFIG || {};
	var hidden = Array.isArray(config.hidden) ? config.hidden : [];
	var keywords = /(licen[cs]e|lizenz|upgrade|premium|pro version|activation key|aktivierungsschl[uü]ssel|renew|verl[aä]nger)/i;

	function keyFor(value) {
		var hash = 5381;
		for (var index = 0; index < value.length; index++) hash = ((hash << 5) + hash) ^ value.charCodeAt(index);
		return ('00000000' + (hash >>> 0).toString(16)).slice(-8);
	}

	document.querySelectorAll('.notice:not(.notice-error):not(.update-nag)').forEach(function (notice) {
		if (notice.closest('.contacthub-wrap') || !keywords.test(notice.textContent || '')) return;
		var normalized = (notice.className + '|' + notice.textContent).replace(/\s+/g, ' ').trim().slice(0, 1000);
		var key = keyFor(normalized);
		if (hidden.indexOf(key) !== -1) { notice.remove(); return; }
		var link = document.createElement('button');
		link.type = 'button'; link.className = 'button-link'; link.textContent = config.hideLabel || 'Hide this reminder';
		link.style.marginLeft = '12px';
		link.addEventListener('click', function () {
			var data = new URLSearchParams(); data.set('action', 'contacthub_dismiss_admin_notice'); data.set('nonce', config.nonce); data.set('key', key); data.set('excerpt', normalized.slice(0, 180));
			fetch(config.ajaxUrl, {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'}, body: data.toString()});
			notice.remove();
		});
		var paragraph = notice.querySelector('p') || notice;
		paragraph.appendChild(link);
	});
}());
