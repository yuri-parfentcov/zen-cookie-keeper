# Zen Cookie Keeper

**Keep your analytics and ad tracking working in Safari and Firefox — without paying for server-side Google Tag Manager.**

A free WordPress plugin that makes your marketing and analytics cookies last as long as they should, so returning visitors are recognised and your paid-ad results are measured correctly.

---

## The problem

Safari and Firefox now **delete tracking cookies after 7 days** — and after just **24 hours** when someone arrives from clicking an ad.

The cookies that Google Analytics, Google Ads, Meta (Facebook), Microsoft, TikTok and LinkedIn rely on are all created by JavaScript in the browser, which is exactly what these browsers cut short. The result, on a large and growing share of your traffic:

- A visitor who comes back a week later looks like a **brand-new person**.
- **Paid-ad attribution breaks before the sale happens** — you can't tell which ad actually worked.
- Your analytics **over-reports "Direct" traffic** and under-credits your real marketing.

You're paying for ads and analytics that quietly stop measuring after a few days.

## The usual "fix" — and why it's overkill

The known solution is to set those cookies from your **server** instead of from JavaScript. When a cookie comes from your own server, the browser trusts it and keeps it for the full intended lifetime.

The industry does this with **server-side Google Tag Manager**, which requires a separate always-on server, a custom subdomain, an SSL certificate, and a monthly bill — a lot of moving parts for what is really one small instruction to the browser.

## How Zen Cookie Keeper solves it

**It performs that same server-side cookie write from inside your WordPress site.** No extra server, no subdomain, no subscription.

It keeps your cookies alive in three ways:

- **Mint** — for ad-click cookies (like Google Ads and Meta click IDs), it rebuilds the cookie on your server straight from the click information already in the visitor's landing-page link. Because your server sets it, the 24-hour ad-click limit doesn't apply.
- **Capture** — for cookies whose value is generated in the browser (like the Google Analytics visitor ID), a tiny helper reads the value the tracking tag already created and hands it to the plugin, which then re-sets it from your server.
- **Restore** — whenever the browser has dropped or expired a cookie, the plugin puts it back, up to the lifetime you set to match your privacy policy.

Everything is tied together by a single, secure **anchor cookie** that your server manages automatically — it carries no marketing data itself and is never touched by JavaScript.

## See where your ad clicks come from

Because the plugin already recognises every visit that arrives from a paid ad, it can also **keep a simple record of those ad clicks** for you — no analytics account or extra tag required.

The **Ad Clicks** screen shows:

- **Totals** — how many ad clicks you've had, broken down by platform (Google Ads, Microsoft Ads, Meta, TikTok, LinkedIn).
- **A daily chart** — clicks over time, coloured by platform, so you can see trends and spikes at a glance.
- **A filterable list** — narrow it down by date range and platform, with the campaign, source, landing page and referrer for each click.
- **CSV export** — download the filtered list to open in a spreadsheet or share with your team.

Each visit is counted **once per session**, and clicks are recorded only when the visitor has given **advertising consent** — the same rule as the ad cookies. You choose how long the records are kept (365 days by default); anything older is cleaned up automatically.

## Privacy and consent come first

- **Nothing is set until the visitor consents.** Analytics and advertising are handled separately, and the plugin reads consent from your existing cookie banner through **Google Consent Mode v2** — so it works with Cookiebot, Complianz, Borlabs and other popular banners. Consent enforcement is **on by default**.
- **Your data never leaves your site.** The plugin makes **no outside connections** — it simply reads each visit and sets first-party cookies on your own domain.
- **You stay in control of lifetimes.** Set each cookie's lifespan on the admin screen to match exactly what your privacy policy says.

## What it does *not* do (so the scope is clear)

- It does **not** send events to ad platforms (it's not a CAPI / Measurement Protocol tool) — it manages cookies, not data forwarding.
- It does **not** hide or proxy your analytics to dodge ad blockers.
- It does **not** restore the Google Analytics *session* cookie — only the visitor-identity cookie, which is what matters for recognising returning people.
- It does **not** by itself fix bot traffic, though an optional module (off by default) can withhold restoration from clients flagged as bots.

## Works with caching

On sites behind a page cache or CDN (Varnish, WP Rocket, Cloudflare, etc.), the plugin writes cookies through a path your cache passes straight through, so it keeps working on fast, cached pages. A built-in **Diagnostics** self-test confirms it's set up correctly on your server.

## Installation

1. Upload the `zen-cookie-keeper` folder to `/wp-content/plugins/`, or install it from the Plugins screen (**Add New → Upload Plugin**) using the release zip.
2. Activate the plugin.
3. Go to **Cookie Keeper → Cookies**, enable the cookies you use, and set each lifetime to match your privacy policy.
4. Make sure your cookie banner has Google Consent Mode turned on (most do) — the plugin reads consent from it automatically.
5. Add the one-line anchor-cookie note shown on the **Overview** screen to your privacy policy.

## Frequently asked questions

**Does it send my data anywhere?**
No. It makes no outside connections. It reads the incoming visit and sets first-party cookies on your own domain — nothing leaves your server.

**Will it set cookies without consent?**
No. Consent enforcement is on by default: analytics cookies need analytics consent, advertising cookies need advertising consent.

**Does it work behind a CDN or page cache?**
Yes — use the Diagnostics self-test to confirm on your setup.

**Why can't it restore the Google Analytics session cookie?**
Its format is fragile and restoring it is unreliable and unnecessary. The plugin restores the visitor-identity cookie only, which is what's needed to recognise returning visitors.

**Where does the Ad Clicks data come from, and is it private?**
It's built entirely from visits to your own site — no third-party account or outside call. A click is only recorded after the visitor gives advertising consent, it's stored on your own server, removed on erasure or uninstall, and automatically deleted once it passes the retention period you set.

---

## License

GPL v2 or later. © Zen Republic Agency.
