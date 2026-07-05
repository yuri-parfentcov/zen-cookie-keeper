/**
 * Zen Cookie Keeper — JS companion.
 *
 * Read-only by design. It NEVER writes a managed cookie via document.cookie
 * (that would instantly drop the cookie under the ITP/ETP cap). It only:
 *   - reads the current Google Consent Mode v2 state from the dataLayer,
 *   - reads captured cookie values (_ga, _fbp, _ttp, …) read-only,
 *   - reads landing click-params from the URL,
 * and POSTs them to the plugin's /sync endpoint, which replies with the
 * server-set Set-Cookie headers. It re-syncs when consent changes.
 */
(function () {
	'use strict';

	if (typeof window.ZenCookieKeeper === 'undefined') {
		return;
	}
	var cfg = window.ZenCookieKeeper;
	var SENT_KEY = 'zenck_sent';

	function readCookie(name) {
		var m = document.cookie.match(
			'(?:^|; )' + name.replace(/([.*+?^${}()|[\]\\])/g, '\\$1') + '=([^;]*)'
		);
		return m ? decodeURIComponent(m[1]) : null;
	}

	function readCaptured() {
		var out = {};
		var names = cfg.captureNames || [];
		for (var i = 0; i < names.length; i++) {
			var v = readCookie(names[i]);
			if (v !== null) {
				out[names[i]] = v;
			}
		}
		return out;
	}

	function readLanding() {
		var out = {};
		var params = cfg.clickParams || [];
		var sp;
		try {
			sp = new URLSearchParams(window.location.search);
		} catch (e) {
			return out;
		}
		for (var i = 0; i < params.length; i++) {
			var v = sp.get(params[i]);
			if (v) {
				out[params[i]] = v;
			}
		}
		return out;
	}

	/**
	 * Attribution for an ad landing: the page path, the referrer host and the
	 * five UTM params. Sent only alongside a non-empty landing (i.e. an ad
	 * click), never on ordinary page views.
	 */
	function readAttrib() {
		var out = { page: window.location.pathname || '' };
		try {
			if (document.referrer) {
				out.ref = new URL(document.referrer).hostname;
			}
		} catch (e) {}
		try {
			var sp = new URLSearchParams(window.location.search);
			var utm = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];
			for (var i = 0; i < utm.length; i++) {
				var v = sp.get(utm[i]);
				if (v) {
					out[utm[i]] = v;
				}
			}
		} catch (e) {}
		return out;
	}

	/**
	 * Derive Consent Mode v2 state. Reads google's consent state if exposed,
	 * else scans the dataLayer for the latest consent default/update calls.
	 */
	function readConsent() {
		var state = { analytics_storage: 'denied', ad_storage: 'denied' };

		// Preferred: gtag('get') style via google_tag_data consent API.
		try {
			if (
				window.google_tag_data &&
				window.google_tag_data.ics &&
				typeof window.google_tag_data.ics.getConsentState === 'function'
			) {
				var ics = window.google_tag_data.ics;
				// 1 = granted, 2 = denied in the ics model.
				state.analytics_storage =
					ics.getConsentState('analytics_storage') === 1 ? 'granted' : 'denied';
				state.ad_storage =
					ics.getConsentState('ad_storage') === 1 ? 'granted' : 'denied';
				return state;
			}
		} catch (e) {}

		// Fallback: walk the dataLayer for consent calls.
		var dl = window.dataLayer || [];
		for (var i = 0; i < dl.length; i++) {
			var entry = dl[i];
			if (
				entry &&
				entry[0] === 'consent' &&
				(entry[1] === 'default' || entry[1] === 'update') &&
				entry[2]
			) {
				if (entry[2].analytics_storage) {
					state.analytics_storage = entry[2].analytics_storage;
				}
				if (entry[2].ad_storage) {
					state.ad_storage = entry[2].ad_storage;
				}
			}
		}
		return state;
	}

	/**
	 * Whether the visitor has made an EXPLICIT consent decision (a Consent Mode
	 * "update" has fired), as opposed to sitting on the pre-interaction default.
	 * The server uses this so a transient default-denied on a return visit never
	 * purges the durable anchor.
	 */
	function hasExplicitConsent() {
		var dl = window.dataLayer || [];
		for (var i = 0; i < dl.length; i++) {
			var e = dl[i];
			if (e && e[0] === 'consent' && e[1] === 'update') {
				return true;
			}
		}
		return false;
	}

	function fingerprint(payload) {
		// Cheap stable key so we don't re-POST an identical state repeatedly.
		return JSON.stringify([payload.explicit, payload.consent, payload.captured, payload.landing, payload.attrib]);
	}

	function alreadySent(fp) {
		try {
			return window.sessionStorage.getItem(SENT_KEY) === fp;
		} catch (e) {
			return false;
		}
	}

	function markSent(fp) {
		try {
			window.sessionStorage.setItem(SENT_KEY, fp);
		} catch (e) {}
	}

	function sync() {
		var landing = readLanding();
		var payload = {
			token: cfg.token,
			cm: 1, // companion marker
			explicit: hasExplicitConsent() ? 1 : 0,
			consent: readConsent(),
			consent_version: cfg.consentVersion || '',
			captured: readCaptured(),
			landing: landing
		};
		// Only attach attribution on an actual ad landing.
		if (Object.keys(landing).length) {
			payload.attrib = readAttrib();
		}

		var fp = fingerprint(payload);
		if (alreadySent(fp)) {
			return;
		}
		markSent(fp);

		try {
			fetch(cfg.endpoint, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				credentials: 'same-origin',
				keepalive: true,
				body: JSON.stringify(payload)
			}).catch(function () {});
		} catch (e) {}
	}

	// Fire as early as possible, then again after the pixels have run so late
	// captures (_ga/_fbp written by gtag) are picked up.
	sync();
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () {
			setTimeout(sync, 1200);
		});
	} else {
		setTimeout(sync, 1200);
	}
	window.addEventListener('load', function () {
		setTimeout(sync, 2500);
	});

	// Re-sync when consent changes (drives withdrawal): patch dataLayer.push.
	try {
		var dl = (window.dataLayer = window.dataLayer || []);
		var origPush = dl.push;
		dl.push = function () {
			var r = origPush.apply(this, arguments);
			for (var i = 0; i < arguments.length; i++) {
				var a = arguments[i];
				if (a && a[0] === 'consent' && a[1] === 'update') {
					setTimeout(sync, 50);
					break;
				}
			}
			return r;
		};
	} catch (e) {}
})();
