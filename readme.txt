=== Zen Cookie Keeper ===
Contributors: zenrepublic
Tags: cookies, consent, first-party, analytics, attribution
Requires at least: 6.2
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Restore first-party analytics and ad cookies server-side so they survive Safari ITP and Firefox ETP capping. Consent-gated, no server-side GTM.

== Description ==

Safari (ITP) and Firefox (ETP) delete first-party cookies written by JavaScript after 7 days — 24 hours when a visitor arrives from an ad click with a decorated URL. Google Analytics and the advertising pixels all write their cookies from JavaScript, so a returning Safari visitor looks brand-new, paid-ad attribution breaks before the sale, and analytics over-reports "Direct" traffic.

The durable fix is to write the cookie with a server `Set-Cookie` from your own first-party origin. The browser then grants it the full intended lifetime. The industry does this with server-side Google Tag Manager, which needs a separate always-on container, a custom subdomain, SSL, and a recurring bill — for what is architecturally a single HTTP header.

**Zen Cookie Keeper performs that server write from inside WordPress.** No container, no subdomain, no extra bill.

It does this three ways:

* **Mint** — builds the value of an ad-click cookie (`_fbc`, `_gcl_aw`, `_gcl_dc`, `_uetmsclkid`, `li_fat_id`) server-side from the click parameter on the landing URL (`fbclid`, `gclid`, `wbraid`, `gbraid`, `dclid`, `msclkid`, `li_fat_id`). A server write, so it is exempt from the 24-hour link-decoration cap.
* **Capture** — for cookies whose value is generated in the browser (`_ga`, `_fbp`, `_ttp`), a small read-only companion reads what the pixel wrote and posts it once; the plugin stores it and re-emits it server-side later.
* **Restore** — re-emits a stored value whenever the browser's copy is missing or has expired, up to the lifetime declared in your privacy policy.

A single durable, HttpOnly **anchor** cookie ties a visitor's stored values and consent record together. It is set by the server, never written or read from JavaScript, and is strictly-necessary/functional (it carries no marketing meaning of its own).

**Consent-first by design.** Consent is purpose-specific: analytics and advertising are gated separately, read from Google Consent Mode v2 (so it works with any consent banner that supports Consent Mode — Cookiebot, Complianz, Borlabs and others). Nothing is set until the matching bucket is granted. Enforcement is ON out of the box.

**What it does not do (so scope is clear):**

* It does not forward events to platforms (no CAPI / Measurement Protocol). It manages cookies, not event transport.
* It does not proxy or mask analytics requests against ad blockers.
* It does not restore the GA4 session cookie (`_ga_<id>`) — only user identity (`_ga` client_id).
* It does not, by itself, fix bot inflation. An optional bot-gating module (off by default) can withhold durable restoration from clients flagged as bots.

== Installation ==

1. Upload the `zen-cookie-keeper` folder to `/wp-content/plugins/`, or install through the Plugins screen.
2. Activate the plugin.
3. Go to **Cookie Keeper → Cookies** and enable the cookies you use; set each lifetime to match your privacy policy.
4. Make sure your cookie banner has Google Consent Mode enabled (most do). The plugin reads consent from it automatically.
5. Add the one-line anchor-cookie entry shown on the Overview screen to your privacy policy.
6. (Optional) For earliest load on busy sites, copy `mu-loader/zen-cookie-keeper-mu.php` into `wp-content/mu-plugins/`.

**Page caching:** on sites with a page cache or CDN, the plugin writes cookies through an uncached REST endpoint (a POST your cache passes through), not the page render, so it keeps working. The Diagnostics screen confirms this with a self-test.

== Frequently Asked Questions ==

= Does this phone home or send my data anywhere? =
No. The plugin makes no outbound third-party HTTP calls. It reads the incoming request and sets first-party cookies on your own domain. Nothing leaves your server.

= Will it set cookies without consent? =
No. Consent enforcement is on by default; analytics cookies require analytics consent and advertising cookies require advertising consent, read from Google Consent Mode.

= Does it work behind Varnish / a CDN / a page-cache plugin? =
Yes. The cookie write goes through a POST endpoint that caches pass to PHP, and the response is marked non-cacheable. Use the Diagnostics self-test to confirm on your setup.

= Why can't it restore the GA4 session cookie (_ga_<id>)? =
Its GS2.1 format is fragile to parse and restoration is brittle and unnecessary. The plugin restores user identity (`_ga` client_id) only, never the session.

== External services ==

This plugin does not connect to any external service. It makes no outbound third-party HTTP requests; it only reads the incoming request and sets first-party cookies on your own domain.

It can optionally consume an inbound `X-JA4` TLS-fingerprint request header for its optional bot-gating module, if your own server infrastructure injects one. That is request data your server already has — no data is sent anywhere.

== Changelog ==

= 1.2.0 =
* New: Restore History screen. Every server re-emission of a stored cookie is recorded as a dated event with the cookie, platform, consent bucket, reason ("missing" — the browser had lost the cookie and it was brought back, or "divergent" — the browser held a different value and it was corrected) and the age of the recovered identity.
* New: filter the history by date range and cookie, a daily per-cookie chart, totals with the missing/divergent split and average recovered-identity age, and a CSV export.
* No cookie values are stored in the history — only event dimensions. Rows honour a configurable retention window (default 365 days) and are removed by the daily cleanup. Erasure and uninstall remove them too.

= 1.1.0 =
* New: Ad Clicks screen. Every visit that lands with an ad platform click id (Google Ads gclid/wbraid/gbraid/dclid, Microsoft Ads msclkid, Meta fbclid, TikTok ttclid, LinkedIn) is recorded once per session, with landing path, referrer and UTM attribution.
* New: filter the recorded clicks by date range and platform, a daily per-platform chart, totals, and a CSV export of the list.
* Recording is gated on advertising consent, matching the ad-cookie policy — nothing is stored without it. Rows honour a configurable retention window (default 365 days) and are removed by the daily cleanup. Erasure and uninstall remove them too.

= 1.0.4 =
* Admin: more compact Cookies registry screen — fixed-width tables so columns line up across every platform block instead of each block sizing its own columns.
* Admin: removed the prominent accent rule under each platform title.
* Admin: added an inline "What Source means" explainer (mint vs capture) to the Cookies screen.

= 1.0.1 =
* Fix: never purge the durable anchor on a transient pre-interaction "default denied" Consent Mode signal on return visits. The anchor and stored values are only removed on an explicit consent withdrawal (a user-driven Consent Mode update), as intended.

= 1.0.0 =
* Initial release: server-side mint / capture / restore of first-party marketing and analytics cookies; HttpOnly anchor with computed lifecycle; per-bucket Consent Mode v2 gating with consent record, withdrawal and erasure; cache-aware uncached write path with exclusions for the major page-cache plugins; cookie registry with custom cookies; diagnostics and self-test; optional JA4/heuristic bot-gating; multisite/multi-domain cookie scoping; daily storage-limitation cleanup.

== Upgrade Notice ==

= 1.2.0 =
Adds the Restore History report screen (missing/divergent split, per-cookie chart, filters and CSV export). Adds one database table on upgrade.

= 1.1.0 =
Adds the Ad Clicks stats screen (per-platform totals, chart, filters and CSV export). Consent-gated; adds one database table on upgrade.

= 1.0.1 =
Fixes anchor durability on return visits. Recommended.

= 1.0.0 =
Initial release.
