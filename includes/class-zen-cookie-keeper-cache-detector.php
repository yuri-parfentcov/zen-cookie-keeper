<?php
/**
 * Cache-mode detection for Zen Cookie Keeper.
 *
 * On a page cache or proxying CDN, PHP does not run on a cache hit, so a
 * Set-Cookie on the page render is never emitted (and a guest cache may strip
 * it). This detector identifies the front cache so the Status screen can warn,
 * and so the cookie write is routed through the uncached /sync POST instead of
 * the page render.
 *
 * @package Zen_Cookie_Keeper
 */

if (!defined('ABSPATH')) {
    exit;
}

class Zen_Cookie_Keeper_Cache_Detector {

    /**
     * Detect the active front cache.
     *
     * @return string varnish|litespeed|wp_rocket|wp_super_cache|w3tc|cloudflare|none
     */
    public static function detect() {
        if (defined('LSCWP_V') || isset($_SERVER['HTTP_X_LSCACHE'])) {
            return 'litespeed';
        }
        if (defined('WP_ROCKET_VERSION')) {
            return 'wp_rocket';
        }
        if (defined('WPCACHEHOME')) {
            return 'wp_super_cache';
        }
        if (defined('W3TC')) {
            return 'w3tc';
        }
        if (!empty($_SERVER['HTTP_CF_RAY']) || !empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            return 'cloudflare';
        }
        if (self::looks_like_varnish()) {
            return 'varnish';
        }
        return 'none';
    }

    /**
     * Heuristic for a Varnish/proxy front (the X-Cache / Via / Age signals).
     */
    public static function looks_like_varnish() {
        $hints = array('HTTP_X_VARNISH', 'HTTP_X_CACHE', 'HTTP_VIA');
        foreach ($hints as $h) {
            if (!empty($_SERVER[$h]) && stripos(sanitize_text_field(wp_unslash($_SERVER[$h])), 'varnish') !== false) {
                return true;
            }
        }
        // X-Forwarded-For present but no known CDN → likely a reverse proxy
        // (Varnish/nginx) in front; route through /sync to be safe.
        return !empty($_SERVER['HTTP_X_VARNISH']);
    }

    /**
     * Human label + whether it blocks the page-render write (always true for a
     * shared front cache: the plugin then relies on the /sync POST, which is
     * uncached).
     *
     * @return array ['mode'=>, 'label'=>, 'blocks_render_write'=>bool]
     */
    public static function status() {
        $mode   = self::detect();
        $labels = array(
            'varnish'        => 'Varnish / reverse proxy',
            'litespeed'      => 'LiteSpeed Cache',
            'wp_rocket'      => 'WP Rocket',
            'wp_super_cache' => 'WP Super Cache',
            'w3tc'           => 'W3 Total Cache',
            'cloudflare'     => 'Cloudflare',
            'none'           => 'No page cache detected',
        );
        return array(
            'mode'                => $mode,
            'label'               => isset($labels[$mode]) ? $labels[$mode] : $mode,
            'blocks_render_write' => ($mode !== 'none'),
        );
    }
}
