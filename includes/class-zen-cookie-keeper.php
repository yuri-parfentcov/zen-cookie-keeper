<?php
/**
 * Main orchestrator (singleton) for Zen Cookie Keeper.
 *
 * Loads dependencies and registers hooks. Front-end work is confined to an early
 * REST POST handler and an enqueue; there is zero per-request work on paths
 * where nothing is enabled or no consent has been granted.
 *
 * @package Zen_Cookie_Keeper
 */

if (!defined('ABSPATH')) {
    exit;
}

class Zen_Cookie_Keeper {

    private static $instance = null;

    /** @var Zen_Cookie_Keeper_Rest */
    public $rest;
    /** @var Zen_Cookie_Keeper_Public */
    public $public;
    /** @var Zen_Cookie_Keeper_Admin */
    public $admin;
    /** @var Zen_Cookie_Keeper_Cron */
    public $cron;
    /** @var Zen_Cookie_Keeper_Cache_Integrations */
    public $cache;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    private function load_dependencies() {
        $dir = ZEN_COOKIE_KEEPER_PATH . 'includes/';
        $files = array(
            'class-zen-cookie-keeper-store.php',
            'class-zen-cookie-keeper-ip.php',
            'class-zen-cookie-keeper-sites.php',
            'class-zen-cookie-keeper-formats.php',
            'class-zen-cookie-keeper-registry.php',
            'class-zen-cookie-keeper-emitter.php',
            'class-zen-cookie-keeper-anchor.php',
            'class-zen-cookie-keeper-consent.php',
            'class-zen-cookie-keeper-mint.php',
            'class-zen-cookie-keeper-capture.php',
            'class-zen-cookie-keeper-restore.php',
            'class-zen-cookie-keeper-bot-gate.php',
            'class-zen-cookie-keeper-cache-detector.php',
            'class-zen-cookie-keeper-cache-integrations.php',
            'class-zen-cookie-keeper-rest.php',
            'class-zen-cookie-keeper-public.php',
            'class-zen-cookie-keeper-diagnostics.php',
            'class-zen-cookie-keeper-cron.php',
        );
        foreach ($files as $file) {
            require_once $dir . $file;
        }
        if (is_admin()) {
            require_once $dir . 'class-zen-cookie-keeper-admin.php';
        }
    }

    private function init_hooks() {
        // Translations are auto-loaded by WordPress for the plugin slug (no
        // manual load_plugin_textdomain needed since WP 4.6).
        // Lazy schema upgrade.
        add_action('init', array($this, 'maybe_upgrade_db'));

        // REST endpoints (the primary cookie-write channel).
        $this->rest = new Zen_Cookie_Keeper_Rest();
        add_action('rest_api_init', array($this->rest, 'register_routes'));

        // Front-end companion + render fallback.
        $this->public = new Zen_Cookie_Keeper_Public();
        $this->public->init_hooks();

        // Cache-plugin exclusions for the /sync route.
        $this->cache = new Zen_Cookie_Keeper_Cache_Integrations();
        $this->cache->init_hooks();

        // Cron cleanup.
        $this->cron = new Zen_Cookie_Keeper_Cron();
        $this->cron->init_hooks();

        // Admin.
        if (is_admin()) {
            $this->admin = new Zen_Cookie_Keeper_Admin();
            $this->admin->init_hooks();
        }
    }

    /**
     * Run dbDelta when the stored DB version is behind the code version. Cheap
     * string compare on every request; dbDelta only on a mismatch.
     */
    public function maybe_upgrade_db() {
        if (get_option('zen_cookie_keeper_db_version') === ZEN_COOKIE_KEEPER_DB_VERSION) {
            return;
        }
        Zen_Cookie_Keeper_Schema::create_tables();
        update_option('zen_cookie_keeper_db_version', ZEN_COOKIE_KEEPER_DB_VERSION);
    }
}
