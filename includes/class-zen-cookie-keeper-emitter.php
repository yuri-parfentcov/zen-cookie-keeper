<?php
/**
 * Low-level Set-Cookie builder for Zen Cookie Keeper.
 *
 * Centralises every cookie write so the HttpOnly / Secure / SameSite / Domain /
 * Max-Age attributes are consistent and so we can append multiple Set-Cookie
 * headers on one response (header(..., false)). Managed cookies are server-set
 * ONLY — nothing here is ever exposed to JavaScript for writing.
 *
 * @package Zen_Cookie_Keeper
 */

if (!defined('ABSPATH')) {
    exit;
}

class Zen_Cookie_Keeper_Emitter {

    /**
     * Build a single Set-Cookie header value.
     *
     * @param string $name
     * @param string $value
     * @param array  $opts  max_age(int seconds), httponly(bool), domain(string),
     *                      path(string), samesite(string), secure(bool).
     * @return string
     */
    public static function build($name, $value, $opts = array()) {
        $opts = wp_parse_args($opts, array(
            'max_age'  => 0,
            'httponly' => false,
            'domain'   => Zen_Cookie_Keeper_Sites::cookie_domain(),
            'path'     => '/',
            'samesite' => 'Lax',
            'secure'   => is_ssl(),
        ));

        // Cookie value must not contain control or separator bytes.
        $value = (string) $value;

        $parts   = array();
        $parts[] = rawurlencode($name) . '=' . rawurlencode($value);
        $parts[] = 'Path=' . $opts['path'];

        if (!empty($opts['domain'])) {
            $parts[] = 'Domain=' . $opts['domain'];
        }

        // Max-Age + an Expires twin for ancient clients.
        $max_age = (int) $opts['max_age'];
        $parts[] = 'Max-Age=' . $max_age;
        $parts[] = 'Expires=' . gmdate('D, d-M-Y H:i:s T', time() + $max_age);

        if (!empty($opts['samesite'])) {
            $parts[] = 'SameSite=' . $opts['samesite'];
        }
        if (!empty($opts['secure'])) {
            $parts[] = 'Secure';
        }
        if (!empty($opts['httponly'])) {
            $parts[] = 'HttpOnly';
        }

        return implode('; ', $parts);
    }

    /**
     * Emit a list of cookie specs as Set-Cookie headers on the PHP response.
     * Used by the send_headers fallback path.
     *
     * @param array $specs Each: ['name'=>, 'value'=>, 'opts'=>[]].
     */
    public static function emit($specs) {
        if (headers_sent()) {
            return;
        }
        foreach ($specs as $spec) {
            $header = self::build($spec['name'], $spec['value'], isset($spec['opts']) ? $spec['opts'] : array());
            header('Set-Cookie: ' . $header, false);
        }
        self::no_cache_headers();
    }

    /**
     * Mark the current response uncacheable so a cache layer cannot store a
     * page carrying a Set-Cookie and replay it to other visitors.
     */
    public static function no_cache_headers() {
        if (headers_sent()) {
            return;
        }
        header('Cache-Control: no-store, private, max-age=0');
        header('Vary: Cookie', false);

        // Cooperate with common page-cache plugins. DONOTCACHEPAGE is a shared,
        // third-party interoperability constant (defined by WP Super Cache, W3TC,
        // Batcache and others); it is intentionally unprefixed.
        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
        }
    }

    /**
     * Build an expiry (deletion) spec for a cookie name.
     *
     * @return array
     */
    public static function expire_spec($name, $httponly = false) {
        return array(
            'name'  => $name,
            'value' => '',
            'opts'  => array('max_age' => 0, 'httponly' => $httponly),
        );
    }
}
