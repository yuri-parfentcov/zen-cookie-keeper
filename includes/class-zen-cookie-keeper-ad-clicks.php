<?php
/**
 * Ad-click session recorder for Zen Cookie Keeper.
 *
 * When an ad landing arrives it carries a click parameter that unambiguously
 * identifies the ad platform (gclid / wbraid / gbraid / dclid → Google Ads,
 * msclkid → Microsoft Ads, fbclid → Meta, ttclid → TikTok, li_fat_id →
 * LinkedIn). The cookie pipeline uses those params to mint platform cookies;
 * this class additionally records the landing as a queryable, dated session in
 * the clicks table — deduped per anchor + platform + click id (one per session).
 *
 * Both write paths reuse this: the authoritative REST /sync handler and the
 * uncached send_headers render fallback. Recording is gated on advertising
 * consent by the caller, matching the ad-cookie mint gate.
 *
 * @package Zen_Cookie_Keeper
 */

if (!defined('ABSPATH')) {
    exit;
}

class Zen_Cookie_Keeper_Ad_Clicks {

    /**
     * Record one session per detected platform for this landing. Landing keys
     * are click params already validated against the registry allowlist.
     *
     * @param int   $anchor_id
     * @param array $landing param => value (e.g. ['gclid' => 'abc'])
     * @param array $attrib  sanitised attribution (page/ref/utm_*)
     */
    public static function record($anchor_id, $landing, $attrib = array()) {
        if (empty($landing) || !is_array($landing)) {
            return;
        }

        $store  = Zen_Cookie_Keeper_Store::instance();
        $ttl    = self::retention_seconds();
        $attrib = is_array($attrib) ? $attrib : array();
        $seen   = array();

        foreach ($landing as $param => $value) {
            $map = Zen_Cookie_Keeper_Registry::platform_for_param($param);
            if (!$map) {
                continue;
            }
            $platform = $map['platform'];
            // One row per platform per landing, even if several of its params
            // are present (e.g. gclid + wbraid both map to Google Ads).
            if (isset($seen[$platform])) {
                continue;
            }
            $seen[$platform] = true;

            $result = $store->record_click_once(
                $anchor_id,
                array(
                    'platform'      => $platform,
                    'click_param'   => (string) $param,
                    'click_id'      => (string) $value,
                    'landing_path'  => isset($attrib['page']) ? $attrib['page'] : '',
                    'referrer_host' => isset($attrib['ref']) ? $attrib['ref'] : '',
                    'utm_source'    => isset($attrib['utm_source']) ? $attrib['utm_source'] : '',
                    'utm_medium'    => isset($attrib['utm_medium']) ? $attrib['utm_medium'] : '',
                    'utm_campaign'  => isset($attrib['utm_campaign']) ? $attrib['utm_campaign'] : '',
                    'utm_term'      => isset($attrib['utm_term']) ? $attrib['utm_term'] : '',
                    'utm_content'   => isset($attrib['utm_content']) ? $attrib['utm_content'] : '',
                ),
                $ttl
            );

            if ($result === 'inserted') {
                $store->insert_op('click', $map['cookie'], $platform, $anchor_id);
            }
        }
    }

    /**
     * Retention window for click rows, in seconds (default 365 days).
     *
     * @return int
     */
    public static function retention_seconds() {
        $days = (int) get_option('zen_cookie_keeper_click_retention_days', 365);
        if ($days < 1) {
            $days = 1;
        }
        return $days * DAY_IN_SECONDS;
    }

    /**
     * Clean + length-bound an attribution payload. Accepts a page path, a
     * referrer host, and the five UTM params; drops anything else.
     *
     * @param mixed $raw
     * @return array
     */
    public static function sanitize_attrib($raw) {
        if (!is_array($raw)) {
            return array();
        }

        $out = array();

        if (isset($raw['page'])) {
            // Path only — strip any host/scheme a client might send.
            $path = wp_parse_url((string) $raw['page'], PHP_URL_PATH);
            $path = is_string($path) ? $path : '';
            $path = sanitize_text_field($path);
            if ($path !== '') {
                $out['page'] = substr($path, 0, 255);
            }
        }

        if (isset($raw['ref'])) {
            $ref = (string) $raw['ref'];
            // Reduce a full URL to its host; accept a bare host too.
            $host = wp_parse_url($ref, PHP_URL_HOST);
            if (!is_string($host) || $host === '') {
                $host = $ref;
            }
            $host = sanitize_text_field($host);
            if ($host !== '' && preg_match('/^[A-Za-z0-9.\-:]{1,191}$/', $host)) {
                $out['ref'] = $host;
            }
        }

        $limits = array(
            'utm_source'   => 128,
            'utm_medium'   => 128,
            'utm_campaign' => 191,
            'utm_term'     => 191,
            'utm_content'  => 191,
        );
        foreach ($limits as $key => $max) {
            if (!isset($raw[$key])) {
                continue;
            }
            $val = sanitize_text_field((string) $raw[$key]);
            if ($val !== '') {
                $out[$key] = substr($val, 0, $max);
            }
        }

        return $out;
    }

    /**
     * Build a sanitised attribution array from the current PHP request globals
     * (used by the render fallback, which has no JSON body).
     *
     * @return array
     */
    public static function attrib_from_request() {
        $raw = array();

        if (isset($_SERVER['REQUEST_URI'])) {
            $raw['page'] = esc_url_raw(wp_unslash($_SERVER['REQUEST_URI']));
        }
        if (isset($_SERVER['HTTP_REFERER'])) {
            $raw['ref'] = esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER']));
        }
        // A click-param landing is a public, unauthenticated GET; there is no
        // nonce to verify. Values are read-only attribution, sanitised inline.
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        foreach (array('utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content') as $k) {
            if (isset($_GET[$k])) {
                $raw[$k] = sanitize_text_field(wp_unslash($_GET[$k]));
            }
        }
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        return self::sanitize_attrib($raw);
    }
}
