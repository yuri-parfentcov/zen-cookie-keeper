<?php
/**
 * Cache-plugin exclusions for Zen Cookie Keeper.
 *
 * Registers do-not-cache rules so the /sync REST endpoint and the anchor cookie
 * are never served from cache by the popular page-cache plugins. The endpoint is
 * a POST (already uncached by sane caches), but these belt-and-braces rules
 * cover GET probes and aggressive configurations.
 *
 * @package Zen_Cookie_Keeper
 */

if (!defined('ABSPATH')) {
    exit;
}

class Zen_Cookie_Keeper_Cache_Integrations {

    public function init_hooks() {
        // WP Rocket: never cache our route, and treat the anchor as a
        // cache-varying cookie.
        add_filter('rocket_cache_reject_uri', array($this, 'rocket_reject_uri'));
        add_filter('rocket_cache_reject_cookies', array($this, 'rocket_reject_cookies'));
        add_filter('rocket_cache_mandatory_cookies', array($this, 'rocket_reject_cookies'));

        // LiteSpeed: force no-cache on our REST namespace.
        add_action('litespeed_init', array($this, 'litespeed_nocache'));

        // W3TC / generic: flag the request as non-cacheable on our routes.
        add_action('init', array($this, 'maybe_donotcache'), 1);
    }

    private function is_our_route() {
        $uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
        return strpos($uri, '/' . rest_get_url_prefix() . '/' . Zen_Cookie_Keeper_Rest::NS) !== false
            || strpos($uri, 'zen-cookie-keeper/v1') !== false;
    }

    public function rocket_reject_uri($uris) {
        $uris[] = '/' . rest_get_url_prefix() . '/zen-cookie-keeper/v1/(.*)';
        return $uris;
    }

    public function rocket_reject_cookies($cookies) {
        $cookies[] = preg_quote(ZEN_COOKIE_KEEPER_ANCHOR_NAME, '/');
        return $cookies;
    }

    public function litespeed_nocache() {
        if ($this->is_our_route() && function_exists('do_action')) {
            // Third-party hook published by the LiteSpeed Cache plugin.
            do_action('litespeed_control_set_nocache', 'zen cookie keeper sync endpoint'); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
        }
    }

    public function maybe_donotcache() {
        // Shared third-party interoperability constant — intentionally unprefixed.
        if ($this->is_our_route() && !defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
        }
    }
}
