<?php
/**
 * Consent manager for Zen Cookie Keeper.
 *
 * Consent is purpose-specific: analytics and advertising are gated separately,
 * sourced from Google Consent Mode v2 (analytics_storage ↔ analytics,
 * ad_storage ↔ advertising) delivered to the server by the JS companion. A
 * master "enforce consent" switch sits on top and is ON by default.
 *
 * @package Zen_Cookie_Keeper
 */

if (!defined('ABSPATH')) {
    exit;
}

class Zen_Cookie_Keeper_Consent {

    /**
     * Whether enforcement is active (consent-first default: ON).
     */
    public static function enforce_enabled() {
        return (bool) get_option('zen_cookie_keeper_enforce_consent', 1);
    }

    /**
     * Map a Consent Mode v2 state to our two buckets.
     *
     * @param array $signals e.g. ['analytics_storage'=>'granted','ad_storage'=>'denied']
     * @return array ['analytics'=>bool,'advertising'=>bool]
     */
    public static function map_signals($signals) {
        $signals = is_array($signals) ? $signals : array();
        $granted = function ($key) use ($signals) {
            return isset($signals[$key]) && strtolower((string) $signals[$key]) === 'granted';
        };
        return array(
            'analytics'   => $granted('analytics_storage'),
            'advertising' => $granted('ad_storage'),
        );
    }

    /**
     * The effective granted buckets after applying the master switch. When
     * enforcement is OFF the admin has explicitly accepted responsibility, so
     * both buckets are treated as granted.
     *
     * @return array ['analytics'=>bool,'advertising'=>bool]
     */
    public static function granted_buckets($signals) {
        if (!self::enforce_enabled()) {
            return array('analytics' => true, 'advertising' => true);
        }
        return self::map_signals($signals);
    }

    /**
     * Record a consent decision keyed by the anchor (the legal basis for
     * subsequent restoration). Retention follows the longest declared lifetime
     * so the record outlives what it authorises.
     */
    public static function record($anchor_id, $granted, $version, $source = 'consent_mode') {
        $retention = max(
            Zen_Cookie_Keeper_Anchor::ttl_for($granted),
            (int) get_option('zen_cookie_keeper_consent_retention', 2 * YEAR_IN_SECONDS)
        );
        Zen_Cookie_Keeper_Store::instance()->insert_consent(
            $anchor_id,
            !empty($granted['analytics']),
            !empty($granted['advertising']),
            (string) $version,
            (string) $source,
            $retention
        );
    }

    /**
     * Has the granted state changed versus the last recorded decision?
     * Used to avoid writing an identical consent row on every sync.
     *
     * @return bool
     */
    public static function changed_since_last($anchor_id, $granted) {
        $last = Zen_Cookie_Keeper_Store::instance()->latest_consent($anchor_id);
        if (!$last) {
            return true;
        }
        return ((int) $last['analytics'] !== (int) !empty($granted['analytics']))
            || ((int) $last['advertising'] !== (int) !empty($granted['advertising']));
    }

    /**
     * Withdrawal (Art. 7(3)) for one bucket: stop restoring it and purge its
     * stored values. The anchor lives on while another consented bucket remains.
     */
    public static function handle_withdrawal($anchor_id, $bucket) {
        $store = Zen_Cookie_Keeper_Store::instance();
        $store->delete_values($anchor_id, $bucket);
        $store->insert_audit($anchor_id, 'withdraw', array('bucket' => $bucket));
    }

    /**
     * Erasure (Art. 17): full removal of everything under the anchor.
     */
    public static function handle_erasure($anchor_id) {
        $store = Zen_Cookie_Keeper_Store::instance();
        $store->insert_audit($anchor_id, 'erasure');
        $store->purge_anchor($anchor_id);
    }
}
