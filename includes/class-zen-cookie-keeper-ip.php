<?php
/**
 * Real client IP resolution + hashing for Zen Cookie Keeper.
 *
 * Behind a proxy/CDN the socket IP is the proxy, not the visitor, so we read
 * the forwarded headers for any origin/diagnostic logic. We never store a raw
 * IP — only a salted hash, for storage limitation.
 *
 * @package Zen_Cookie_Keeper
 */

if (!defined('ABSPATH')) {
    exit;
}

class Zen_Cookie_Keeper_IP {

    /**
     * Best-effort real client IP.
     *
     * @return string
     */
    public static function client_ip() {
        $candidates = array();

        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $candidates[] = sanitize_text_field(wp_unslash($_SERVER['HTTP_CF_CONNECTING_IP']));
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // First hop is the client.
            $xff   = sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR']));
            $parts = explode(',', $xff);
            $candidates[] = trim($parts[0]);
        }
        if (!empty($_SERVER['REMOTE_ADDR'])) {
            $candidates[] = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
        }

        foreach ($candidates as $ip) {
            $ip = trim((string) $ip);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
        return '';
    }

    /**
     * Whether a forwarding proxy/CDN appears to sit in front of the origin.
     *
     * @return bool
     */
    public static function behind_proxy() {
        return !empty($_SERVER['HTTP_X_FORWARDED_FOR'])
            || !empty($_SERVER['HTTP_CF_CONNECTING_IP'])
            || !empty($_SERVER['HTTP_X_REAL_IP']);
    }

    /**
     * Stable per-site salt for hashing (auth salt is per-install, secret).
     *
     * @return string
     */
    private static function salt() {
        return wp_salt('auth');
    }

    public static function hash_ip($ip = null) {
        if (null === $ip) {
            $ip = self::client_ip();
        }
        if ($ip === '') {
            return '';
        }
        return hash('sha256', self::salt() . '|ip|' . $ip);
    }

    public static function hash_ua($ua = null) {
        if (null === $ua) {
            $ua = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
        }
        if ($ua === '') {
            return '';
        }
        return hash('sha256', self::salt() . '|ua|' . $ua);
    }
}
