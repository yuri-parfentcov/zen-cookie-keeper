<?php
/**
 * Restore engine for Zen Cookie Keeper.
 *
 * Re-emits a previously stored value, keyed by the anchor, whenever the incoming
 * cookie is missing (expired) or diverges from the stored one. Restoration is
 * capped at the DECLARED lifetime (the value in the privacy policy), counted
 * from first_seen_ts — never a technical maximum, and never resurrected past it.
 *
 * @package Zen_Cookie_Keeper
 */

if (!defined('ABSPATH')) {
    exit;
}

class Zen_Cookie_Keeper_Restore {

    /**
     * Plan the cookies to re-emit.
     *
     * @param int   $anchor_id
     * @param array $incoming  cookie_name => current value in the browser (from
     *                         the companion's read-only report). Missing key = absent.
     * @param array $granted   ['analytics'=>bool,'advertising'=>bool]
     * @param array $exclude   cookie_name => anything. Names minted earlier in
     *                         the SAME request: they are already being emitted
     *                         and must not be logged/counted as restores.
     * @return array List of emit specs for the Emitter.
     */
    public static function plan($anchor_id, $incoming, $granted, $exclude = array()) {
        $store  = Zen_Cookie_Keeper_Store::instance();
        $stored = $store->get_values($anchor_id);
        $specs  = array();

        if (!$stored) {
            return $specs;
        }

        $catalog = Zen_Cookie_Keeper_Registry::get_catalog();

        foreach ($stored as $row) {
            $name = $row['cookie_name'];

            // Just stored by the mint step of this very request — not a restore.
            if (isset($exclude[$name])) {
                continue;
            }

            // Bucket must currently be granted.
            $bucket = $row['bucket'];
            if (empty($granted[$bucket])) {
                continue;
            }

            // Cookie must still be enabled in the catalog.
            if (empty($catalog[$name]) || empty($catalog[$name]['status'])) {
                continue;
            }

            // Remaining lifetime from first-seen; skip if already past declared.
            $age       = time() - (int) $row['first_seen_ts'];
            $remaining = (int) $row['declared_lifetime'] - $age;
            if ($remaining <= 0) {
                continue;
            }

            // Only re-emit when the browser's copy is missing or divergent.
            $current = isset($incoming[$name]) ? $incoming[$name] : null;
            if ($current === $row['cookie_value']) {
                continue;
            }

            $specs[] = array(
                'name'  => $name,
                'value' => $row['cookie_value'],
                'opts'  => array(
                    'max_age'  => $remaining,
                    'httponly' => !empty($catalog[$name]['httponly']),
                ),
            );

            $store->mark_restored($row['id']);
            $store->insert_op('restore', $name, 'emitted', $anchor_id);

            // Durable history event for the Restore History report. 'missing'
            // = the browser had lost the cookie (ITP/expiry) and we brought it
            // back; 'divergent' = it held a different value and we corrected
            // it to the stored one. No cookie value is recorded here.
            $store->record_restore(
                $anchor_id,
                array(
                    'cookie_name'        => $name,
                    'bucket'             => $bucket,
                    'platform'           => isset($catalog[$name]['platform']) ? $catalog[$name]['platform'] : '',
                    'reason'             => (null === $current) ? 'missing' : 'divergent',
                    'value_age'          => $age,
                    'remaining_lifetime' => $remaining,
                ),
                self::retention_seconds()
            );
        }

        return $specs;
    }

    /**
     * Retention window for restore-history rows, in seconds (default 365 days).
     *
     * @return int
     */
    public static function retention_seconds() {
        $days = (int) get_option('zen_cookie_keeper_restore_retention_days', 365);
        if ($days < 1) {
            $days = 1;
        }
        return $days * DAY_IN_SECONDS;
    }
}
