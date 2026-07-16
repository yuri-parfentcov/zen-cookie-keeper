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

	/* ----- Ad Clicks: filter, chart, export, retention ----- */
	(function () {
		var $filter = $('#zenck-adclicks-filter');
		if (!$filter.length) {
			return; // Not on the Ad Clicks screen.
		}

		var PLATFORM_COLORS = {
			'Google Ads': '#34A853',
			'Microsoft Ads': '#F25022',
			'Meta': '#1877F2',
			'TikTok': '#EE1D52',
			'LinkedIn': '#0A66C2'
		};
		var FALLBACK = ['#8E44AD', '#16A085', '#D35400', '#2C3E50', '#7F8C8D', '#C0392B'];

		function colorFor(platform, idx) {
			return PLATFORM_COLORS[platform] || FALLBACK[idx % FALLBACK.length];
		}

		function esc(v) {
			return v === null || v === undefined ? '' : String(v);
		}

		function currentFilters() {
			return {
				from: $filter.find('[name="from"]').val() || '',
				to: $filter.find('[name="to"]').val() || '',
				platform: $filter.find('[name="platform"]').val() || ''
			};
		}

		function updateExportLink(f) {
			var base = cfg.exportBase || '';
			var q = [
				'action=zen_cookie_keeper_export_clicks',
				'_wpnonce=' + encodeURIComponent(cfg.exportNonce || ''),
				'from=' + encodeURIComponent(f.from),
				'to=' + encodeURIComponent(f.to),
				'platform=' + encodeURIComponent(f.platform)
			].join('&');
			$('#zenck-adclicks-export').attr('href', base + (base.indexOf('?') === -1 ? '?' : '&') + q);
		}

		/* --- Cards --- */
		function renderCards(totals, from, to) {
			$('[data-zenck-total]').text(totals && totals.total ? totals.total : 0);
			$('[data-zenck-range]').text(from + ' → ' + to);
			var by = (totals && totals.by_platform) || {};
			$('[data-zenck-platform-count]').each(function () {
				var pf = $(this).attr('data-zenck-platform-count');
				$(this).text(by[pf] ? by[pf] : 0);
			});
		}

		/* --- Table --- */
		function renderTable(rows, count) {
			var $tbody = $('#zenck-adclicks-table tbody').empty();
			if (!rows || !rows.length) {
				$('<tr class="zenck-empty-row"><td colspan="7"></td></tr>')
					.find('td').text('No ad clicks recorded in this window yet.').end()
					.appendTo($tbody);
			} else {
				for (var i = 0; i < rows.length; i++) {
					var r = rows[i];
					var sm = esc(r.source) + (r.medium ? ' / ' + esc(r.medium) : '');
					sm = sm.replace(/^ \/ /, '');
					var $tr = $('<tr>');
					[
						esc(r.created_at), esc(r.platform), esc(r.campaign), sm,
						esc(r.landing), esc(r.referrer), esc(r.click_id)
					].forEach(function (val, idx) {
						var $td = $('<td>').text(val);
						if (idx === 4 || idx === 6) {
							$td.addClass('zenck-ellip');
						}
						$tr.append($td);
					});
					$tbody.append($tr);
				}
			}
			$('#zenck-adclicks-count').text('Showing ' + (rows ? rows.length : 0) + ' of ' + (count || 0));
		}

		/* --- Chart: stacked daily bars per platform --- */
		function drawChart(series) {
			var canvas = document.getElementById('zenck-adclicks-chart');
			if (!canvas || !canvas.getContext) {
				return;
			}
			var wrap = canvas.parentNode;
			var cssW = Math.max(320, wrap.clientWidth || 640);
			var cssH = 260;
			var ratio = window.devicePixelRatio || 1;
			canvas.width = cssW * ratio;
			canvas.height = cssH * ratio;
			canvas.style.width = cssW + 'px';
			canvas.style.height = cssH + 'px';
			var ctx = canvas.getContext('2d');
			ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
			ctx.clearRect(0, 0, cssW, cssH);

			series = series || [];
			// Pivot into days[] and platforms[] with a counts map.
			var dayOrder = [];
			var daySeen = {};
			var pfSeen = {};
			var counts = {}; // day -> platform -> n
			for (var i = 0; i < series.length; i++) {
				var d = String(series[i].day);
				var p = String(series[i].platform);
				var n = parseInt(series[i].n, 10) || 0;
				if (!daySeen[d]) { daySeen[d] = true; dayOrder.push(d); }
				pfSeen[p] = true;
				if (!counts[d]) { counts[d] = {}; }
				counts[d][p] = (counts[d][p] || 0) + n;
			}
			dayOrder.sort();
			var platforms = Object.keys(pfSeen).sort();

			var padL = 34, padR = 10, padT = 12, padB = 28;
			var plotW = cssW - padL - padR;
			var plotH = cssH - padT - padB;

			// Max stacked total per day.
			var maxV = 0;
			for (var di = 0; di < dayOrder.length; di++) {
				var tot = 0;
				var cd = counts[dayOrder[di]] || {};
				for (var pk in cd) { if (cd.hasOwnProperty(pk)) { tot += cd[pk]; } }
				if (tot > maxV) { maxV = tot; }
			}
			if (maxV <= 0) { maxV = 1; }

			var textColor = '#50575e';
			var gridColor = '#dcdcde';

			// Axes / gridlines (4 steps).
			ctx.strokeStyle = gridColor;
			ctx.fillStyle = textColor;
			ctx.font = '11px sans-serif';
			ctx.textAlign = 'right';
			ctx.textBaseline = 'middle';
			var steps = 4;
			for (var s = 0; s <= steps; s++) {
				var val = Math.round(maxV * s / steps);
				var y = padT + plotH - (plotH * s / steps);
				ctx.beginPath();
				ctx.moveTo(padL, y);
				ctx.lineTo(cssW - padR, y);
				ctx.stroke();
				ctx.fillText(String(val), padL - 6, y);
			}

			if (!dayOrder.length) {
				ctx.textAlign = 'center';
				ctx.fillText('No data in range', padL + plotW / 2, padT + plotH / 2);
				renderLegend(platforms);
				return;
			}

			var slot = plotW / dayOrder.length;
			var barW = Math.max(2, Math.min(28, slot * 0.7));
			ctx.textAlign = 'center';
			ctx.textBaseline = 'top';

			for (var d2 = 0; d2 < dayOrder.length; d2++) {
				var day = dayOrder[d2];
				var cx = padL + slot * d2 + slot / 2;
				var yBase = padT + plotH;
				var stack = counts[day] || {};
				for (var pi = 0; pi < platforms.length; pi++) {
					var pf = platforms[pi];
					var v = stack[pf] || 0;
					if (v <= 0) { continue; }
					var h = (v / maxV) * plotH;
					ctx.fillStyle = colorFor(pf, pi);
					ctx.fillRect(cx - barW / 2, yBase - h, barW, h);
					yBase -= h;
				}
				// Sparse x labels (~ up to 8).
				var every = Math.ceil(dayOrder.length / 8);
				if (d2 % every === 0) {
					ctx.fillStyle = textColor;
					ctx.fillText(day.slice(5), cx, padT + plotH + 6);
				}
			}
			renderLegend(platforms);
		}

		function renderLegend(platforms) {
			var $legend = $('#zenck-adclicks-legend').empty();
			for (var i = 0; i < platforms.length; i++) {
				var $item = $('<span class="zenck-legend-item">');
				$('<span class="zenck-legend-swatch">').css('background', colorFor(platforms[i], i)).appendTo($item);
				$('<span>').text(platforms[i]).appendTo($item);
				$legend.append($item);
			}
		}

		var lastSeries = [];

		function refresh() {
			var f = currentFilters();
			var $status = $('#zenck-adclicks-status');
			$status.text('…').removeClass('zenck-ok zenck-warn');
			updateExportLink(f);
			post('refresh_adclicks', f, function (res) {
				if (!res || !res.success) {
					flash($status, false, res && res.data && res.data.message);
					return;
				}
				var d = res.data;
				renderCards(d.totals, d.from, d.to);
				renderTable(d.rows, d.count);
				lastSeries = d.series || [];
				drawChart(lastSeries);
				$status.text('');
			});
		}

		$filter.on('submit', function (e) {
			e.preventDefault();
			refresh();
		});

		$(document).on('submit', '#zenck-adclicks-retention', function (e) {
			e.preventDefault();
			post('save_adclicks', {
				retention_days: parseInt($(this).find('[name="retention_days"]').val(), 10) || 1
			}, function (res) {
				flash($('#zenck-adclicks-retention-result'), !!res.success);
			});
		});

		var resizeTimer;
		$(window).on('resize', function () {
			clearTimeout(resizeTimer);
			resizeTimer = setTimeout(function () { drawChart(lastSeries); }, 150);
		});

		// Initial paint from the server-embedded bootstrap data.
		$(function () {
			var raw = document.getElementById('zenck-adclicks-data');
			if (raw) {
				try {
					var boot = JSON.parse(raw.textContent || '{}');
					lastSeries = boot.series || [];
					updateExportLink(currentFilters());
					drawChart(lastSeries);
				} catch (e) {}
			}
		});
	})();

	/* ----- Restore History: filter, chart, export, retention ----- */
	(function () {
		var $filter = $('#zenck-restores-filter');
		if (!$filter.length) {
			return; // Not on the Restore History screen.
		}

		var COLORS = ['#2271B1', '#34A853', '#D63638', '#DBA617', '#8E44AD', '#16A085', '#D35400', '#2C3E50', '#7F8C8D', '#C0392B'];

		function colorFor(idx) {
			return COLORS[idx % COLORS.length];
		}

		function esc(v) {
			return v === null || v === undefined ? '' : String(v);
		}

		function currentFilters() {
			return {
				from: $filter.find('[name="from"]').val() || '',
				to: $filter.find('[name="to"]').val() || '',
				cookie: $filter.find('[name="cookie"]').val() || ''
			};
		}

		function updateExportLink(f) {
			var base = cfg.exportBase || '';
			var q = [
				'action=zen_cookie_keeper_export_restores',
				'_wpnonce=' + encodeURIComponent(cfg.exportNonce || ''),
				'from=' + encodeURIComponent(f.from),
				'to=' + encodeURIComponent(f.to),
				'cookie=' + encodeURIComponent(f.cookie)
			].join('&');
			$('#zenck-restores-export').attr('href', base + (base.indexOf('?') === -1 ? '?' : '&') + q);
		}

		/* --- Cards --- */
		function renderCards(totals, from, to) {
			totals = totals || {};
			var by = totals.by_reason || {};
			$('[data-zenck-r-total]').text(totals.total || 0);
			$('[data-zenck-r-range]').text(from + ' → ' + to);
			$('[data-zenck-r-missing]').text(by.missing || 0);
			$('[data-zenck-r-divergent]').text(by.divergent || 0);
			$('[data-zenck-r-avgage]').text(Math.round((totals.avg_age || 0) / 86400));
		}

		/* --- Table --- */
		function renderTable(rows, count) {
			var $tbody = $('#zenck-restores-table tbody').empty();
			if (!rows || !rows.length) {
				$('<tr class="zenck-empty-row"><td colspan="7"></td></tr>')
					.find('td').text('No restores recorded in this window yet.').end()
					.appendTo($tbody);
			} else {
				for (var i = 0; i < rows.length; i++) {
					var r = rows[i];
					var $tr = $('<tr>');
					[
						esc(r.created_at), esc(r.cookie), esc(r.platform), esc(r.bucket),
						esc(r.reason), esc(r.age_days), esc(r.remaining_days)
					].forEach(function (val) {
						$tr.append($('<td>').text(val));
					});
					$tbody.append($tr);
				}
			}
			$('#zenck-restores-count').text('Showing ' + (rows ? rows.length : 0) + ' of ' + (count || 0));
		}

		/* --- Chart: stacked daily bars per cookie --- */
		function drawChart(series) {
			var canvas = document.getElementById('zenck-restores-chart');
			if (!canvas || !canvas.getContext) {
				return;
			}
			var wrap = canvas.parentNode;
			var cssW = Math.max(320, wrap.clientWidth || 640);
			var cssH = 260;
			var ratio = window.devicePixelRatio || 1;
			canvas.width = cssW * ratio;
			canvas.height = cssH * ratio;
			canvas.style.width = cssW + 'px';
			canvas.style.height = cssH + 'px';
			var ctx = canvas.getContext('2d');
			ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
			ctx.clearRect(0, 0, cssW, cssH);

			series = series || [];
			// Pivot into days[] and cookies[] with a counts map.
			var dayOrder = [];
			var daySeen = {};
			var ckSeen = {};
			var counts = {}; // day -> cookie -> n
			for (var i = 0; i < series.length; i++) {
				var d = String(series[i].day);
				var c = String(series[i].cookie_name);
				var n = parseInt(series[i].n, 10) || 0;
				if (!daySeen[d]) { daySeen[d] = true; dayOrder.push(d); }
				ckSeen[c] = true;
				if (!counts[d]) { counts[d] = {}; }
				counts[d][c] = (counts[d][c] || 0) + n;
			}
			dayOrder.sort();
			var cookies = Object.keys(ckSeen).sort();

			var padL = 34, padR = 10, padT = 12, padB = 28;
			var plotW = cssW - padL - padR;
			var plotH = cssH - padT - padB;

			// Max stacked total per day.
			var maxV = 0;
			for (var di = 0; di < dayOrder.length; di++) {
				var tot = 0;
				var cd = counts[dayOrder[di]] || {};
				for (var ck in cd) { if (cd.hasOwnProperty(ck)) { tot += cd[ck]; } }
				if (tot > maxV) { maxV = tot; }
			}
			if (maxV <= 0) { maxV = 1; }

			var textColor = '#50575e';
			var gridColor = '#dcdcde';

			// Axes / gridlines (4 steps).
			ctx.strokeStyle = gridColor;
			ctx.fillStyle = textColor;
			ctx.font = '11px sans-serif';
			ctx.textAlign = 'right';
			ctx.textBaseline = 'middle';
			var steps = 4;
			for (var s = 0; s <= steps; s++) {
				var val = Math.round(maxV * s / steps);
				var y = padT + plotH - (plotH * s / steps);
				ctx.beginPath();
				ctx.moveTo(padL, y);
				ctx.lineTo(cssW - padR, y);
				ctx.stroke();
				ctx.fillText(String(val), padL - 6, y);
			}

			if (!dayOrder.length) {
				ctx.textAlign = 'center';
				ctx.fillText('No data in range', padL + plotW / 2, padT + plotH / 2);
				renderLegend(cookies);
				return;
			}

			var slot = plotW / dayOrder.length;
			var barW = Math.max(2, Math.min(28, slot * 0.7));
			ctx.textAlign = 'center';
			ctx.textBaseline = 'top';

			for (var d2 = 0; d2 < dayOrder.length; d2++) {
				var day = dayOrder[d2];
				var cx = padL + slot * d2 + slot / 2;
				var yBase = padT + plotH;
				var stack = counts[day] || {};
				for (var ci = 0; ci < cookies.length; ci++) {
					var cname = cookies[ci];
					var v = stack[cname] || 0;
					if (v <= 0) { continue; }
					var h = (v / maxV) * plotH;
					ctx.fillStyle = colorFor(ci);
					ctx.fillRect(cx - barW / 2, yBase - h, barW, h);
					yBase -= h;
				}
				// Sparse x labels (~ up to 8).
				var every = Math.ceil(dayOrder.length / 8);
				if (d2 % every === 0) {
					ctx.fillStyle = textColor;
					ctx.fillText(day.slice(5), cx, padT + plotH + 6);
				}
			}
			renderLegend(cookies);
		}

		function renderLegend(cookies) {
			var $legend = $('#zenck-restores-legend').empty();
			for (var i = 0; i < cookies.length; i++) {
				var $item = $('<span class="zenck-legend-item">');
				$('<span class="zenck-legend-swatch">').css('background', colorFor(i)).appendTo($item);
				$('<span>').text(cookies[i]).appendTo($item);
				$legend.append($item);
			}
		}

		var lastSeries = [];

		function refresh() {
			var f = currentFilters();
			var $status = $('#zenck-restores-status');
			$status.text('…').removeClass('zenck-ok zenck-warn');
			updateExportLink(f);
			post('refresh_restores', f, function (res) {
				if (!res || !res.success) {
					flash($status, false, res && res.data && res.data.message);
					return;
				}
				var d = res.data;
				renderCards(d.totals, d.from, d.to);
				renderTable(d.rows, d.count);
				lastSeries = d.series || [];
				drawChart(lastSeries);
				$status.text('');
			});
		}

		$filter.on('submit', function (e) {
			e.preventDefault();
			refresh();
		});

		$(document).on('submit', '#zenck-restores-retention', function (e) {
			e.preventDefault();
			post('save_restores', {
				retention_days: parseInt($(this).find('[name="retention_days"]').val(), 10) || 1
			}, function (res) {
				flash($('#zenck-restores-retention-result'), !!res.success);
			});
		});

		var resizeTimer;
		$(window).on('resize', function () {
			clearTimeout(resizeTimer);
			resizeTimer = setTimeout(function () { drawChart(lastSeries); }, 150);
		});

		// Initial paint from the server-embedded bootstrap data.
		$(function () {
			var raw = document.getElementById('zenck-restores-data');
			if (raw) {
				try {
					var boot = JSON.parse(raw.textContent || '{}');
					lastSeries = boot.series || [];
					updateExportLink(currentFilters());
					drawChart(lastSeries);
				} catch (e) {}
			}
		});
	})();

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
