/* Zen Cookie Keeper — admin UI. */
(function ($) {
	'use strict';

	var cfg = window.ZenCookieKeeperAdmin || {};

	function post(action, data, done) {
		data = data || {};
		data.action = 'zen_cookie_keeper_' + action;
		data.nonce = cfg.nonce;
		$.post(cfg.ajaxUrl, data)
			.done(function (res) {
				done(res);
			})
			.fail(function () {
				done({ success: false, data: { message: cfg.i18n.error } });
			});
	}

	function flash($el, ok, msg) {
		$el.text(msg || (ok ? cfg.i18n.saved : cfg.i18n.error))
			.removeClass('zenck-ok zenck-warn')
			.addClass(ok ? 'zenck-ok' : 'zenck-warn');
		setTimeout(function () {
			$el.text('');
		}, 3500);
	}

	/* ----- Registry: toggles + overrides ----- */
	$(document).on('change', '.zenck-toggle', function () {
		var $row = $(this).closest('tr');
		post('toggle_cookie', {
			name: $row.data('cookie'),
			status: this.checked ? 1 : 0
		}, function () {});
	});

	$(document).on('change', '.zenck-lifetime, .zenck-httponly', function () {
		var $row = $(this).closest('tr');
		post('save_override', {
			name: $row.data('cookie'),
			lifetime: parseInt($row.find('.zenck-lifetime').val(), 10) * 86400,
			httponly: $row.find('.zenck-httponly').is(':checked') ? 1 : 0
		}, function () {});
	});

	$(document).on('click', '.zenck-delete-custom', function (e) {
		e.preventDefault();
		var name = $(this).data('cookie');
		post('delete_custom', { name: name }, function () {
			location.reload();
		});
	});

	/* ----- Custom cookie modal ----- */
	$(document).on('click', '#zenck-add-custom-btn', function () {
		$('#zenck-custom-modal').show();
	});
	$(document).on('click', '#zenck-c-cancel', function () {
		$('#zenck-custom-modal').hide();
	});
	$(document).on('click', '#zenck-c-save', function () {
		post('add_custom', {
			name: $('#zenck-c-name').val(),
			bucket: $('#zenck-c-bucket').val(),
			source: $('#zenck-c-source').val(),
			param: $('#zenck-c-param').val(),
			lifetime_days: parseInt($('#zenck-c-lifetime').val(), 10) || 90,
			httponly: $('#zenck-c-httponly').is(':checked') ? 1 : 0
		}, function (res) {
			if (res.success) {
				location.reload();
			} else {
				$('#zenck-custom-err').text(res.data && res.data.message ? res.data.message : cfg.i18n.error);
			}
		});
	});

	/* ----- Formats ----- */
	$(document).on('click', '#zenck-save-formats', function () {
		post('save_formats', { formats: $('#zenck-formats').val() }, function (res) {
			flash($('#zenck-formats-result'), !!res.success, res.data && res.data.message);
		});
	});

	/* ----- Consent ----- */
	$(document).on('change', '#zenck-enforce', function () {
		if (!this.checked && !window.confirm(cfg.i18n.confirmDisableEnforce)) {
			this.checked = true;
		}
	});
	$(document).on('submit', '#zenck-consent-form', function (e) {
		e.preventDefault();
		post('save_consent', {
			enforce: $('#zenck-enforce').is(':checked') ? 1 : 0,
			version: $('#zenck-version').val(),
			retention_days: parseInt($('#zenck-retention').val(), 10) || 1
		}, function (res) {
			flash($('#zenck-consent-result'), !!res.success);
		});
	});

	/* ----- Bot-gating ----- */
	$(document).on('submit', '#zenck-botgate-form', function (e) {
		e.preventDefault();
		post('save_botgate', {
			enabled: $('#zenck-botgate').is(':checked') ? 1 : 0,
			denylist: $('#zenck-deny').val(),
			allowlist: $('#zenck-allow').val()
		}, function (res) {
			flash($('#zenck-botgate-result'), !!res.success);
		});
	});

	/* ----- Erasure ----- */
	$(document).on('click', '#zenck-erasure-btn', function () {
		if (!window.confirm(cfg.i18n.confirmErasure)) {
			return;
		}
		post('erasure', { token: $('#zenck-erasure-token').val() }, function (res) {
			flash($('#zenck-erasure-result'), !!res.success, res.data && res.data.message);
		});
	});

	/* ----- Domain override ----- */
	$(document).on('submit', '#zenck-domain-form', function (e) {
		e.preventDefault();
		post('save_domain', {
			host: $('#zenck-d-host').val(),
			domain: $('#zenck-d-domain').val()
		}, function (res) {
			flash($('#zenck-domain-result'), !!res.success);
		});
	});

	/* ----- Diagnostics refresh ----- */
	$(document).on('click', '#zenck-refresh-diag', function () {
		location.reload();
	});

	/* ----- Self-test (runs against the public REST endpoint) ----- */
	$(document).on('click', '#zenck-selftest', function () {
		var $r = $('#zenck-selftest-result');
		$r.text('…').removeClass('zenck-ok zenck-warn');

		function call(probe) {
			return fetch(cfg.selftestUrl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				credentials: 'same-origin',
				body: JSON.stringify({ token: cfg.siteToken, probe: probe || '' })
			}).then(function (r) {
				return r.json();
			});
		}

		// First call issues a probe cookie; second call confirms it came back.
		call('').then(function (first) {
			var issued = first && first.issued ? first.issued : '';
			return call(issued).then(function (second) {
				if (second && second.saw_probe) {
					$r.text('✓ ' + 'cookie set and survived').addClass('zenck-ok');
				} else {
					$r.text('✗ cookie did not come back — a cache layer may be intercepting it').addClass('zenck-warn');
				}
			});
		}).catch(function () {
			$r.text('✗ self-test request failed').addClass('zenck-warn');
		});
	});
})(jQuery);
