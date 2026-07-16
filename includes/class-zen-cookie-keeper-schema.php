<?php
/**
 * Database schema definition for Zen Cookie Keeper.
 *
 * Centralises table names and the dbDelta CREATE statements so that the
 * activator, the store (repository) and uninstall.php all share one source of
 * truth. Seven tables, all keyed by anchor_id, all carrying TTL columns that
 * drive the cron purge.
 *
 * @package Zen_Cookie_Keeper
 */

if (!defined('ABSPATH')) {
    exit;
}

class Zen_Cookie_Keeper_Schema {

    /**
     * Fully-qualified table name for a logical table key.
     *
     * @param string $key One of: anchors, values, consent, audit, ops, clicks, restores.
     * @return string
     */
    public static function table($key) {
        global $wpdb;
        return $wpdb->prefix . 'zen_cookie_keeper_' . $key;
    }

    /**
     * All logical table keys.
     *
     * @return string[]
     */
    public static function table_keys() {
        return array('anchors', 'values', 'consent', 'audit', 'ops', 'clicks', 'restores');
    }

    /**
     * Run dbDelta for all tables. Idempotent — safe to call on every upgrade.
     */
    public static function create_tables() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $anchors  = self::table('anchors');
        $values   = self::table('values');
        $consent  = self::table('consent');
        $audit    = self::table('audit');
        $ops      = self::table('ops');
        $clicks   = self::table('clicks');
        $restores = self::table('restores');

        // 1. Anchors / identities. The browser holds only the raw token; we
        //    store its SHA-256. IP and UA are hashed — never stored raw.
        $sql_anchors = "CREATE TABLE $anchors (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            anchor_hash CHAR(64) NOT NULL,
            cookie_domain VARCHAR(191) NOT NULL DEFAULT '',
            site_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            ip_hash CHAR(64) NOT NULL DEFAULT '',
            user_agent_hash CHAR(64) NOT NULL DEFAULT '',
            is_bot TINYINT(1) NOT NULL DEFAULT 0,
            ja4 VARCHAR(64) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            last_seen_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            expires_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            PRIMARY KEY  (id),
            UNIQUE KEY anchor_hash (anchor_hash),
            KEY expires_at (expires_at),
            KEY last_seen_at (last_seen_at),
            KEY site_domain (site_id, cookie_domain)
        ) $charset_collate;";

        // 2. Stored cookie values (store-once). first_seen_ts is the literal
        //    first-seen marker and is never recomputed.
        $sql_values = "CREATE TABLE $values (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            anchor_id BIGINT UNSIGNED NOT NULL,
            cookie_name VARCHAR(64) NOT NULL,
            cookie_value VARCHAR(512) NOT NULL DEFAULT '',
            bucket VARCHAR(16) NOT NULL DEFAULT '',
            source VARCHAR(16) NOT NULL DEFAULT '',
            first_seen_ts BIGINT UNSIGNED NOT NULL DEFAULT 0,
            declared_lifetime INT UNSIGNED NOT NULL DEFAULT 0,
            last_restored_at DATETIME DEFAULT NULL,
            expires_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            PRIMARY KEY  (id),
            UNIQUE KEY anchor_cookie (anchor_id, cookie_name),
            KEY expires_at (expires_at),
            KEY bucket (bucket)
        ) $charset_collate;";

        // 3. Consent records — append-only history (legal basis / audit trail).
        $sql_consent = "CREATE TABLE $consent (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            anchor_id BIGINT UNSIGNED NOT NULL,
            analytics TINYINT(1) NOT NULL DEFAULT 0,
            advertising TINYINT(1) NOT NULL DEFAULT 0,
            consent_version VARCHAR(32) NOT NULL DEFAULT '',
            signal_source VARCHAR(32) NOT NULL DEFAULT '',
            recorded_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            expires_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            PRIMARY KEY  (id),
            KEY anchor_id (anchor_id),
            KEY recorded_at (recorded_at),
            KEY expires_at (expires_at)
        ) $charset_collate;";

        // 4. Audit trail — lifecycle events (anchor_mint/withdraw/erasure/purge).
        $sql_audit = "CREATE TABLE $audit (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            anchor_id BIGINT UNSIGNED DEFAULT NULL,
            action VARCHAR(32) NOT NULL DEFAULT '',
            meta TEXT,
            created_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            PRIMARY KEY  (id),
            KEY anchor_id (anchor_id),
            KEY action (action),
            KEY created_at (created_at)
        ) $charset_collate;";

        // 5. Ops log — recent operations for diagnostics; short TTL.
        $sql_ops = "CREATE TABLE $ops (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            op_type VARCHAR(16) NOT NULL DEFAULT '',
            cookie_name VARCHAR(64) NOT NULL DEFAULT '',
            result VARCHAR(32) NOT NULL DEFAULT '',
            anchor_id BIGINT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            PRIMARY KEY  (id),
            KEY op_created (op_type, created_at),
            KEY created_at (created_at)
        ) $charset_collate;";

        // 6. Ad-click sessions — one row per ad-originated landing (deduped per
        //    anchor + platform + click id). Rich attribution for the stats screen;
        //    TTL-driven like the rest, purged by the daily cron.
        $sql_clicks = "CREATE TABLE $clicks (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            anchor_id BIGINT UNSIGNED NOT NULL,
            platform VARCHAR(32) NOT NULL DEFAULT '',
            click_param VARCHAR(32) NOT NULL DEFAULT '',
            click_id VARCHAR(512) NOT NULL DEFAULT '',
            landing_path VARCHAR(255) NOT NULL DEFAULT '',
            referrer_host VARCHAR(191) NOT NULL DEFAULT '',
            utm_source VARCHAR(128) NOT NULL DEFAULT '',
            utm_medium VARCHAR(128) NOT NULL DEFAULT '',
            utm_campaign VARCHAR(191) NOT NULL DEFAULT '',
            utm_term VARCHAR(191) NOT NULL DEFAULT '',
            utm_content VARCHAR(191) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            expires_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            PRIMARY KEY  (id),
            KEY anchor_platform (anchor_id, platform),
            KEY platform_created (platform, created_at),
            KEY created_at (created_at),
            KEY expires_at (expires_at)
        ) $charset_collate;";

        // 7. Restore history — one row per re-emitted cookie (the report that
        //    proves ITP/ETP survival). Deliberately carries NO cookie value:
        //    only the event dimensions (cookie, bucket, platform, reason) and
        //    the recovered identity's age. TTL-driven like clicks.
        $sql_restores = "CREATE TABLE $restores (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            anchor_id BIGINT UNSIGNED NOT NULL,
            cookie_name VARCHAR(64) NOT NULL DEFAULT '',
            bucket VARCHAR(16) NOT NULL DEFAULT '',
            platform VARCHAR(32) NOT NULL DEFAULT '',
            reason VARCHAR(16) NOT NULL DEFAULT '',
            value_age INT UNSIGNED NOT NULL DEFAULT 0,
            remaining_lifetime INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            expires_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            PRIMARY KEY  (id),
            KEY cookie_created (cookie_name, created_at),
            KEY anchor_id (anchor_id),
            KEY created_at (created_at),
            KEY expires_at (expires_at)
        ) $charset_collate;";

        dbDelta($sql_anchors);
        dbDelta($sql_values);
        dbDelta($sql_consent);
        dbDelta($sql_audit);
        dbDelta($sql_ops);
        dbDelta($sql_clicks);
        dbDelta($sql_restores);
    }
}
