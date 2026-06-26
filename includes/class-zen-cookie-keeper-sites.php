<?php
/**
 * Cookie-domain scoping + multi-domain / multisite helpers.
 *
 * The cookie Domain attribute must be the registrable domain (eTLD+1) so the
 * restored cookie is visible to gtag/pixels on apex and www alike. We derive it
 * from the site's own host (stripping a leading www) which is correct for the
 * real deployments and avoids a Public Suffix List dependency; an admin can
 * override per-host via the sites screen.
 *
 * @package Zen_Cookie_Keeper
 */

if (!defined('ABSPATH')) {
    exit;
}

class Zen_Cookie_Keeper_Sites {

    /**
     * The current request host (lower-cased, no port).
     *
     * @return string
     */
    public static function request_host() {
        $host = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '';
        if ($host === '') {
            $host = wp_parse_url(home_url(), PHP_URL_HOST);
        }
        $host = strtolower((string) $host);
        $host = preg_replace('/:\d+$/', '', $host);
        return preg_replace('/[^a-z0-9.\-]/', '', $host);
    }

    /**
     * The cookie Domain attribute for the current request, e.g. ".example.com".
     * Honors a per-host admin override if present.
     *
     * @return string
     */
    public static function cookie_domain() {
        $host      = self::request_host();
        $overrides = get_option('zen_cookie_keeper_domain_overrides', array());
        if (is_array($overrides) && isset($overrides[$host]) && $overrides[$host] !== '') {
            return $overrides[$host];
        }

        $bare = preg_replace('/^www\./', '', $host);
        if ($bare === '' || !str_contains($bare, '.')) {
            // localhost or single-label host: omit Domain (host-only cookie).
            return '';
        }
        return '.' . $bare;
    }

    /**
     * Registrable-domain string (no leading dot) for storage/diagnostics.
     *
     * @return string
     */
    public static function registrable_domain() {
        return ltrim(self::cookie_domain(), '.');
    }
}
