<?php
/**
 * Bot-gating module for Zen Cookie Keeper (optional, default OFF).
 *
 * Restoration grants durable identity to everyone equally, bots included. When
 * enabled, this module withholds durable restoration from clients flagged as
 * bots, so analytics is not inflated by durable bot identities. It consumes an
 * optional inbound JA4 fingerprint header (which upstream infrastructure such as
 * HAProxy may inject) plus lightweight heuristics. No external lookups.
 *
 * @package Zen_Cookie_Keeper
 */

if (!defined('ABSPATH')) {
    exit;
}

class Zen_Cookie_Keeper_Bot_Gate {

    /**
     * Whether the module is switched on.
     */
    public static function enabled() {
        return (bool) get_option('zen_cookie_keeper_bot_gate_enabled', 0);
    }

    /**
     * Inbound JA4 TLS fingerprint, if upstream injected one.
     *
     * @return string
     */
    public static function inbound_ja4() {
        if (!empty($_SERVER['HTTP_X_JA4'])) {
            return sanitize_text_field(wp_unslash($_SERVER['HTTP_X_JA4']));
        }
        return '';
    }

    /**
     * Decide whether the current request is a bot.
     *
     * @param array $ctx Optional context: ['has_companion_marker'=>bool].
     * @return array ['is_bot'=>bool, 'reason'=>string, 'ja4'=>string]
     */
    public static function evaluate($ctx = array()) {
        $ja4 = self::inbound_ja4();

        if (!self::enabled()) {
            return array('is_bot' => false, 'reason' => '', 'ja4' => $ja4);
        }

        // 1. JA4 allow/deny lists take precedence.
        if ($ja4 !== '') {
            $deny  = (array) get_option('zen_cookie_keeper_bot_ja4_denylist', array());
            $allow = (array) get_option('zen_cookie_keeper_bot_ja4_allowlist', array());
            if (in_array($ja4, $allow, true)) {
                return array('is_bot' => false, 'reason' => 'ja4_allow', 'ja4' => $ja4);
            }
            if (in_array($ja4, $deny, true)) {
                return array('is_bot' => true, 'reason' => 'ja4_deny', 'ja4' => $ja4);
            }
        }

        // 2. User-agent heuristic.
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
        if ($ua === '' || preg_match('/bot|crawl|spider|slurp|headless|bingpreview|gptbot|python-requests|curl|wget|facebookexternalhit/i', $ua)) {
            return array('is_bot' => true, 'reason' => 'ua', 'ja4' => $ja4);
        }

        // 3. Missing companion marker (a genuine browser sync carries it).
        if (empty($ctx['has_companion_marker'])) {
            return array('is_bot' => true, 'reason' => 'no_companion', 'ja4' => $ja4);
        }

        // 4. Per-IP request cadence — too many syncs in a short window.
        if (self::cadence_exceeded()) {
            return array('is_bot' => true, 'reason' => 'cadence', 'ja4' => $ja4);
        }

        return array('is_bot' => false, 'reason' => '', 'ja4' => $ja4);
    }

    /**
     * Sliding-window per-IP counter via transient. Returns true if the request
     * rate looks automated.
     */
    private static function cadence_exceeded() {
        $ip_hash = Zen_Cookie_Keeper_IP::hash_ip();
        if ($ip_hash === '') {
            return false;
        }
        $key   = 'zenck_cad_' . substr($ip_hash, 0, 32);
        $count = (int) get_transient($key);
        $count++;
        set_transient($key, $count, MINUTE_IN_SECONDS);
        return $count > 60; // > 60 syncs/minute from one IP.
    }
}
