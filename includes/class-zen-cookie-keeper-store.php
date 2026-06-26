<?php
/**
 * Data store (repository) for Zen Cookie Keeper.
 *
 * This is the ONLY class that runs SQL. Every user value is passed through
 * $wpdb->prepare; the only interpolated identifiers are fixed table names that
 * come from Zen_Cookie_Keeper_Schema (never from user input). This is a custom
 * table repository for which there is no core API, so direct queries are
 * intentional; results are not cached because they are per-request identity
 * lookups that must be live.
 *
 * @package Zen_Cookie_Keeper
 */

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
// phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter

class Zen_Cookie_Keeper_Store {

    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function t($key) {
        return Zen_Cookie_Keeper_Schema::table($key);
    }

    private function now() {
        return current_time('mysql', true); // GMT
    }

    /* ---------------------------------------------------------------------
     * Anchors
     * ------------------------------------------------------------------- */

    /**
     * Insert a new anchor row.
     *
     * @return int|false Inserted row id, or false on failure.
     */
    public function insert_anchor($anchor_hash, $cookie_domain, $ip_hash, $ua_hash, $is_bot, $ja4, $ttl_seconds) {
        global $wpdb;
        $now     = $this->now();
        $expires = gmdate('Y-m-d H:i:s', time() + (int) $ttl_seconds);

        $ok = $wpdb->insert(
            $this->t('anchors'),
            array(
                'anchor_hash'     => $anchor_hash,
                'cookie_domain'   => $cookie_domain,
                'site_id'         => (int) get_current_blog_id(),
                'ip_hash'         => $ip_hash,
                'user_agent_hash' => $ua_hash,
                'is_bot'          => $is_bot ? 1 : 0,
                'ja4'             => $ja4,
                'created_at'      => $now,
                'last_seen_at'    => $now,
                'expires_at'      => $expires,
            ),
            array('%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s')
        );

        return $ok ? (int) $wpdb->insert_id : false;
    }

    public function get_anchor_by_hash($anchor_hash) {
        global $wpdb;
        $sql = $wpdb->prepare(
            'SELECT * FROM ' . $this->t('anchors') . ' WHERE anchor_hash = %s LIMIT 1',
            $anchor_hash
        );
        return $wpdb->get_row($sql, ARRAY_A);
    }

    public function touch_anchor($anchor_id, $ttl_seconds) {
        global $wpdb;
        $expires = gmdate('Y-m-d H:i:s', time() + (int) $ttl_seconds);
        return $wpdb->update(
            $this->t('anchors'),
            array('last_seen_at' => $this->now(), 'expires_at' => $expires),
            array('id' => (int) $anchor_id),
            array('%s', '%s'),
            array('%d')
        );
    }

    /**
     * Fully remove an anchor and all of its child rows (Art. 17 erasure).
     */
    public function purge_anchor($anchor_id) {
        global $wpdb;
        $anchor_id = (int) $anchor_id;
        $wpdb->delete($this->t('values'),  array('anchor_id' => $anchor_id), array('%d'));
        $wpdb->delete($this->t('consent'), array('anchor_id' => $anchor_id), array('%d'));
        $wpdb->delete($this->t('anchors'), array('id' => $anchor_id), array('%d'));
    }

    /* ---------------------------------------------------------------------
     * Cookie values (store-once)
     * ------------------------------------------------------------------- */

    /**
     * Store a cookie value exactly once. If a row already exists for
     * (anchor_id, cookie_name) the stored value and first_seen_ts are NEVER
     * overwritten — that is the whole point of the durable identity.
     *
     * @return string 'inserted' | 'exists' | 'failed'
     */
    public function store_value_once($anchor_id, $cookie_name, $value, $bucket, $source, $first_seen_ts, $declared_lifetime) {
        global $wpdb;
        $anchor_id = (int) $anchor_id;

        $existing = $this->get_value($anchor_id, $cookie_name);
        if ($existing) {
            return 'exists';
        }

        $expires = gmdate('Y-m-d H:i:s', (int) $first_seen_ts + (int) $declared_lifetime);

        $ok = $wpdb->insert(
            $this->t('values'),
            array(
                'anchor_id'         => $anchor_id,
                'cookie_name'       => $cookie_name,
                'cookie_value'      => $value,
                'bucket'            => $bucket,
                'source'            => $source,
                'first_seen_ts'     => (int) $first_seen_ts,
                'declared_lifetime' => (int) $declared_lifetime,
                'expires_at'        => $expires,
            ),
            array('%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s')
        );

        return $ok ? 'inserted' : 'failed';
    }

    public function get_value($anchor_id, $cookie_name) {
        global $wpdb;
        $sql = $wpdb->prepare(
            'SELECT * FROM ' . $this->t('values') . ' WHERE anchor_id = %d AND cookie_name = %s LIMIT 1',
            (int) $anchor_id,
            $cookie_name
        );
        return $wpdb->get_row($sql, ARRAY_A);
    }

    public function get_values($anchor_id) {
        global $wpdb;
        $sql = $wpdb->prepare(
            'SELECT * FROM ' . $this->t('values') . ' WHERE anchor_id = %d',
            (int) $anchor_id
        );
        return $wpdb->get_results($sql, ARRAY_A);
    }

    public function mark_restored($value_id) {
        global $wpdb;
        return $wpdb->update(
            $this->t('values'),
            array('last_restored_at' => $this->now()),
            array('id' => (int) $value_id),
            array('%s'),
            array('%d')
        );
    }

    /**
     * Delete stored values for an anchor, optionally restricted to a bucket
     * (used by consent withdrawal for a single bucket).
     */
    public function delete_values($anchor_id, $bucket = null) {
        global $wpdb;
        $anchor_id = (int) $anchor_id;
        if (null === $bucket) {
            return $wpdb->delete($this->t('values'), array('anchor_id' => $anchor_id), array('%d'));
        }
        return $wpdb->delete(
            $this->t('values'),
            array('anchor_id' => $anchor_id, 'bucket' => $bucket),
            array('%d', '%s')
        );
    }

    /* ---------------------------------------------------------------------
     * Consent records
     * ------------------------------------------------------------------- */

    public function insert_consent($anchor_id, $analytics, $advertising, $version, $source, $retention_seconds) {
        global $wpdb;
        $expires = gmdate('Y-m-d H:i:s', time() + (int) $retention_seconds);
        return $wpdb->insert(
            $this->t('consent'),
            array(
                'anchor_id'       => (int) $anchor_id,
                'analytics'       => $analytics ? 1 : 0,
                'advertising'     => $advertising ? 1 : 0,
                'consent_version' => $version,
                'signal_source'   => $source,
                'recorded_at'     => $this->now(),
                'expires_at'      => $expires,
            ),
            array('%d', '%d', '%d', '%s', '%s', '%s', '%s')
        );
    }

    public function latest_consent($anchor_id) {
        global $wpdb;
        $sql = $wpdb->prepare(
            'SELECT * FROM ' . $this->t('consent') . ' WHERE anchor_id = %d ORDER BY recorded_at DESC, id DESC LIMIT 1',
            (int) $anchor_id
        );
        return $wpdb->get_row($sql, ARRAY_A);
    }

    public function consent_history($anchor_id, $limit = 50) {
        global $wpdb;
        $sql = $wpdb->prepare(
            'SELECT * FROM ' . $this->t('consent') . ' WHERE anchor_id = %d ORDER BY recorded_at DESC, id DESC LIMIT %d',
            (int) $anchor_id,
            (int) $limit
        );
        return $wpdb->get_results($sql, ARRAY_A);
    }

    /* ---------------------------------------------------------------------
     * Audit trail + ops log
     * ------------------------------------------------------------------- */

    public function insert_audit($anchor_id, $action, $meta = '') {
        global $wpdb;
        if (is_array($meta)) {
            $meta = wp_json_encode($meta);
        }
        return $wpdb->insert(
            $this->t('audit'),
            array(
                'anchor_id'  => $anchor_id ? (int) $anchor_id : null,
                'action'     => $action,
                'meta'       => (string) $meta,
                'created_at' => $this->now(),
            ),
            array('%d', '%s', '%s', '%s')
        );
    }

    public function insert_op($op_type, $cookie_name, $result, $anchor_id = null) {
        global $wpdb;
        return $wpdb->insert(
            $this->t('ops'),
            array(
                'op_type'     => $op_type,
                'cookie_name' => (string) $cookie_name,
                'result'      => (string) $result,
                'anchor_id'   => $anchor_id ? (int) $anchor_id : null,
                'created_at'  => $this->now(),
            ),
            array('%s', '%s', '%s', '%d', '%s')
        );
    }

    public function recent_ops($limit = 50) {
        global $wpdb;
        $sql = $wpdb->prepare(
            'SELECT * FROM ' . $this->t('ops') . ' ORDER BY created_at DESC, id DESC LIMIT %d',
            (int) $limit
        );
        return $wpdb->get_results($sql, ARRAY_A);
    }

    /* ---------------------------------------------------------------------
     * Cron purge (storage limitation)
     * ------------------------------------------------------------------- */

    /**
     * Delete everything past its TTL. Returns a per-table count for logging.
     *
     * @return array
     */
    public function purge_expired($ops_retention_days = 7, $audit_retention_days = 365) {
        global $wpdb;
        $now = $this->now();

        $deleted = array();

        $deleted['values'] = $wpdb->query(
            $wpdb->prepare('DELETE FROM ' . $this->t('values') . ' WHERE expires_at < %s', $now)
        );
        $deleted['consent'] = $wpdb->query(
            $wpdb->prepare('DELETE FROM ' . $this->t('consent') . ' WHERE expires_at < %s', $now)
        );
        $deleted['anchors'] = $wpdb->query(
            $wpdb->prepare('DELETE FROM ' . $this->t('anchors') . ' WHERE expires_at < %s', $now)
        );

        $ops_cut = gmdate('Y-m-d H:i:s', time() - ((int) $ops_retention_days * DAY_IN_SECONDS));
        $deleted['ops'] = $wpdb->query(
            $wpdb->prepare('DELETE FROM ' . $this->t('ops') . ' WHERE created_at < %s', $ops_cut)
        );

        $audit_cut = gmdate('Y-m-d H:i:s', time() - ((int) $audit_retention_days * DAY_IN_SECONDS));
        $deleted['audit'] = $wpdb->query(
            $wpdb->prepare('DELETE FROM ' . $this->t('audit') . ' WHERE created_at < %s', $audit_cut)
        );

        // Orphan sweep: values/consent whose anchor was purged above.
        $anchors = $this->t('anchors');
        $wpdb->query('DELETE v FROM ' . $this->t('values') . ' v LEFT JOIN ' . $anchors . ' a ON v.anchor_id = a.id WHERE a.id IS NULL');
        $wpdb->query('DELETE c FROM ' . $this->t('consent') . ' c LEFT JOIN ' . $anchors . ' a ON c.anchor_id = a.id WHERE a.id IS NULL');

        return $deleted;
    }

    /**
     * Count rows per table (for the Status screen).
     *
     * @return array
     */
    public function counts() {
        global $wpdb;
        $out = array();
        foreach (Zen_Cookie_Keeper_Schema::table_keys() as $key) {
            $out[$key] = (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . $this->t($key));
        }
        return $out;
    }
}

// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching
// phpcs:enable WordPress.DB.DirectDatabaseQuery.SchemaChange
// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
// phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter
