<?php
/**
 * Activation / deactivation for Zen Cookie Keeper.
 *
 * Creates the custom tables, seeds default options (consent-first: enforcement
 * ON out of the box), stamps the DB version, and schedules the cleanup cron.
 *
 * @package Zen_Cookie_Keeper
 */

if (!defined('ABSPATH')) {
    exit;
}

class Zen_Cookie_Keeper_Activator {

    const CRON_HOOK = 'zen_cookie_keeper_cleanup';

    /**
     * Run on plugin activation.
     */
    public static function activate() {
        Zen_Cookie_Keeper_Schema::create_tables();
        self::seed_options();

        update_option('zen_cookie_keeper_db_version', ZEN_COOKIE_KEEPER_DB_VERSION);

        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK);
        }
    }

    /**
     * Run on plugin deactivation. Leaves data intact (uninstall.php cleans up);
     * only clears the scheduled cron so it does not fire while inactive.
     */
    public static function deactivate() {
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::CRON_HOOK);
        }
        wp_clear_scheduled_hook(self::CRON_HOOK);
    }

    /**
     * Seed default options without clobbering values an admin already set.
     */
    private static function seed_options() {
        require_once ZEN_COOKIE_KEEPER_PATH . 'includes/class-zen-cookie-keeper-formats.php';

        $defaults = array(
            // Consent-first: enforcement is ON by default. Nothing is set
            // without the relevant bucket granted.
            'zen_cookie_keeper_enforce_consent'   => 1,
            // Per-cookie overrides keyed by cookie name: status/lifetime/httponly.
            'zen_cookie_keeper_cookie_overrides'  => array(),
            // Admin-defined custom cookies.
            'zen_cookie_keeper_custom_cookies'    => array(),
            // Updatable mint format rules (absorbs platform format drift).
            'zen_cookie_keeper_mint_formats'      => Zen_Cookie_Keeper_Formats::default_rules(),
            // The consent-text version that restoration is the legal basis for.
            'zen_cookie_keeper_consent_version'   => '',
            // Optional bot-gating module — OFF by default so it never surprises
            // a legitimate visitor; admin opts in.
            'zen_cookie_keeper_bot_gate_enabled'  => 0,
            'zen_cookie_keeper_bot_ja4_denylist'  => array(),
            'zen_cookie_keeper_bot_ja4_allowlist' => array(),
            // Retention (days) for recorded ad-click sessions on the stats screen.
            'zen_cookie_keeper_click_retention_days' => 365,
            // Retention (days) for restore-history rows on the report screen.
            'zen_cookie_keeper_restore_retention_days' => 365,
        );

        foreach ($defaults as $key => $value) {
            if (get_option($key, '__zenck_missing__') === '__zenck_missing__') {
                add_option($key, $value);
            }
        }

        // Rotating site token — a second binding for the public /sync endpoint
        // beyond the (cacheable, refreshable) REST nonce.
        if (get_option('zen_cookie_keeper_site_token', '') === '') {
            add_option('zen_cookie_keeper_site_token', wp_generate_password(40, false, false));
        }
    }
}
