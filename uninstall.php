<?php
/**
 * Uninstall cleanup for Zen Cookie Keeper.
 *
 * Drops the custom tables and removes every option/transient the plugin set,
 * across all sites on multisite. Runs only on true delete.
 *
 * @package Zen_Cookie_Keeper
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Remove all plugin data for the current site.
 */
function zen_cookie_keeper_uninstall_site() {
    global $wpdb;

    // Drop custom tables.
    $keys = array('anchors', 'values', 'consent', 'audit', 'ops', 'clicks');
    foreach ($keys as $key) {
        $table = $wpdb->prefix . 'zen_cookie_keeper_' . $key;
        // Table identifier is code-controlled (fixed prefix + key), not user input.
        $wpdb->query("DROP TABLE IF EXISTS `{$table}`"); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
    }

    // Delete options.
    $options = array(
        'zen_cookie_keeper_db_version',
        'zen_cookie_keeper_enforce_consent',
        'zen_cookie_keeper_cookie_overrides',
        'zen_cookie_keeper_custom_cookies',
        'zen_cookie_keeper_mint_formats',
        'zen_cookie_keeper_consent_version',
        'zen_cookie_keeper_consent_retention',
        'zen_cookie_keeper_bot_gate_enabled',
        'zen_cookie_keeper_bot_ja4_denylist',
        'zen_cookie_keeper_bot_ja4_allowlist',
        'zen_cookie_keeper_site_token',
        'zen_cookie_keeper_domain_overrides',
        'zen_cookie_keeper_ops_retention_days',
        'zen_cookie_keeper_audit_retention_days',
        'zen_cookie_keeper_click_retention_days',
    );
    foreach ($options as $option) {
        delete_option($option);
    }

    // Sweep any stray transients.
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_zenck\_%' OR option_name LIKE '\_transient\_timeout\_zenck\_%'"); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

    // Clear the cron event.
    wp_clear_scheduled_hook('zen_cookie_keeper_cleanup');
}

if (is_multisite()) {
    $zen_cookie_keeper_sites = get_sites(array('fields' => 'ids', 'number' => 0));
    foreach ($zen_cookie_keeper_sites as $blog_id) {
        switch_to_blog($blog_id);
        zen_cookie_keeper_uninstall_site();
        restore_current_blog();
    }
} else {
    zen_cookie_keeper_uninstall_site();
}
