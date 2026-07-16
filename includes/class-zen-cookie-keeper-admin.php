<?php
/**
 * Admin controller for Zen Cookie Keeper.
 *
 * Menu + screens (Overview / Cookies / Consent / Diagnostics / Sites) and all
 * settings mutations via admin-ajax, each guarded by a nonce + manage_options
 * capability and sanitising its own input. No Settings API group is used so each
 * save touches only its own option (no whole-group wipe risk).
 *
 * @package Zen_Cookie_Keeper
 */

if (!defined('ABSPATH')) {
    exit;
}

class Zen_Cookie_Keeper_Admin {

    const NONCE = 'zen_cookie_keeper_admin';
    const CAP   = 'manage_options';

    public function init_hooks() {
        add_action('admin_menu', array($this, 'menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue'));

        $ajax = array(
            'toggle_cookie', 'save_override', 'add_custom', 'delete_custom',
            'save_consent', 'save_botgate', 'save_formats', 'save_domain',
            'erasure', 'refresh_diagnostics', 'rotate_token',
            'refresh_adclicks', 'save_adclicks',
            'refresh_restores', 'save_restores',
        );
        foreach ($ajax as $action) {
            add_action('wp_ajax_zen_cookie_keeper_' . $action, array($this, 'ajax_' . $action));
        }

        // CSV exports are file downloads, so they go through admin-post (not AJAX).
        add_action('admin_post_zen_cookie_keeper_export_clicks', array($this, 'export_clicks'));
        add_action('admin_post_zen_cookie_keeper_export_restores', array($this, 'export_restores'));
    }

    /* ----------------------------------------------------------------- Menu */

    public function menu() {
        add_menu_page(
            __('Zen Cookie Keeper', 'zen-cookie-keeper'),
            __('Cookie Keeper', 'zen-cookie-keeper'),
            self::CAP,
            'zen-cookie-keeper',
            array($this, 'render_overview'),
            'dashicons-shield',
            58
        );
        $subs = array(
            'zen-cookie-keeper'             => __('Overview', 'zen-cookie-keeper'),
            'zen-cookie-keeper-cookies'     => __('Cookies', 'zen-cookie-keeper'),
            'zen-cookie-keeper-consent'     => __('Consent', 'zen-cookie-keeper'),
            'zen-cookie-keeper-adclicks'    => __('Ad Clicks', 'zen-cookie-keeper'),
            'zen-cookie-keeper-restores'    => __('Restore History', 'zen-cookie-keeper'),
            'zen-cookie-keeper-diagnostics' => __('Diagnostics', 'zen-cookie-keeper'),
            'zen-cookie-keeper-sites'       => __('Sites', 'zen-cookie-keeper'),
        );
        $methods = array(
            'zen-cookie-keeper'             => 'render_overview',
            'zen-cookie-keeper-cookies'     => 'render_cookies',
            'zen-cookie-keeper-consent'     => 'render_consent',
            'zen-cookie-keeper-adclicks'    => 'render_adclicks',
            'zen-cookie-keeper-restores'    => 'render_restores',
            'zen-cookie-keeper-diagnostics' => 'render_diagnostics',
            'zen-cookie-keeper-sites'       => 'render_sites',
        );
        foreach ($subs as $slug => $title) {
            add_submenu_page('zen-cookie-keeper', $title, $title, self::CAP, $slug, array($this, $methods[$slug]));
        }
    }

    public function enqueue($hook) {
        if (strpos($hook, 'zen-cookie-keeper') === false) {
            return;
        }
        wp_enqueue_style(
            'zen-cookie-keeper-admin',
            ZEN_COOKIE_KEEPER_URL . 'admin/css/zen-cookie-keeper-admin.css',
            array(),
            ZEN_COOKIE_KEEPER_VERSION
        );
        wp_enqueue_script(
            'zen-cookie-keeper-admin',
            ZEN_COOKIE_KEEPER_URL . 'admin/js/zen-cookie-keeper-admin.js',
            array('jquery'),
            ZEN_COOKIE_KEEPER_VERSION,
            true
        );
        wp_localize_script('zen-cookie-keeper-admin', 'ZenCookieKeeperAdmin', array(
            'ajaxUrl'      => admin_url('admin-ajax.php'),
            'nonce'        => wp_create_nonce(self::NONCE),
            'selftestUrl'  => esc_url_raw(rest_url(Zen_Cookie_Keeper_Rest::NS . '/selftest')),
            'siteToken'    => (string) get_option('zen_cookie_keeper_site_token', ''),
            'exportBase'   => esc_url_raw(admin_url('admin-post.php')),
            'exportNonce'  => wp_create_nonce('zen_cookie_keeper_export'),
            'i18n'    => array(
                'confirmDisableEnforce' => __('Turning off consent enforcement means cookies may be set without consent. This is a compliance risk and can fail review. Are you sure?', 'zen-cookie-keeper'),
                'confirmErasure'        => __('Permanently erase all stored data under this identifier? This cannot be undone.', 'zen-cookie-keeper'),
                'saved'                 => __('Saved.', 'zen-cookie-keeper'),
                'error'                 => __('Something went wrong.', 'zen-cookie-keeper'),
            ),
        ));
    }

    /* ------------------------------------------------------- Render screens */

    private function view($file, $data = array()) {
        $data['admin'] = $this;
        extract($data, EXTR_SKIP);
        include ZEN_COOKIE_KEEPER_PATH . 'admin/views/' . $file . '.php';
    }

    public function render_overview() {
        $this->view('overview-page', array(
            'cache'      => Zen_Cookie_Keeper_Cache_Detector::status(),
            'grouped'    => Zen_Cookie_Keeper_Registry::grouped(),
            'enforce'    => Zen_Cookie_Keeper_Consent::enforce_enabled(),
            'counts'     => Zen_Cookie_Keeper_Store::instance()->counts(),
        ));
    }

    public function render_cookies() {
        $this->view('registry-page', array(
            'grouped'     => Zen_Cookie_Keeper_Registry::grouped(),
            'unsupported' => Zen_Cookie_Keeper_Registry::unsupported(),
            'formats'     => Zen_Cookie_Keeper_Formats::rules(),
        ));
    }

    public function render_consent() {
        $this->view('consent-page', array(
            'enforce'   => Zen_Cookie_Keeper_Consent::enforce_enabled(),
            'version'   => get_option('zen_cookie_keeper_consent_version', ''),
            'retention' => (int) get_option('zen_cookie_keeper_consent_retention', 2 * YEAR_IN_SECONDS),
            'botgate'   => (bool) get_option('zen_cookie_keeper_bot_gate_enabled', 0),
            'denylist'  => (array) get_option('zen_cookie_keeper_bot_ja4_denylist', array()),
            'allowlist' => (array) get_option('zen_cookie_keeper_bot_ja4_allowlist', array()),
        ));
    }

    public function render_adclicks() {
        $to      = gmdate('Y-m-d');
        $from    = gmdate('Y-m-d', time() - (29 * DAY_IN_SECONDS));
        $data    = $this->adclicks_data($from, $to, '');

        $this->view('adclicks-page', array(
            'from'      => $from,
            'to'        => $to,
            'platform'  => '',
            'platforms' => Zen_Cookie_Keeper_Registry::ad_platforms(),
            'totals'    => $data['totals'],
            'series'    => $data['series'],
            'rows'      => $data['rows'],
            'count'     => $data['count'],
            'retention' => (int) get_option('zen_cookie_keeper_click_retention_days', 365),
        ));
    }

    public function render_restores() {
        $to   = gmdate('Y-m-d');
        $from = gmdate('Y-m-d', time() - (29 * DAY_IN_SECONDS));
        $data = $this->restores_data($from, $to, '');

        $this->view('restores-page', array(
            'from'      => $from,
            'to'        => $to,
            'cookie'    => '',
            'cookies'   => array_keys(Zen_Cookie_Keeper_Registry::get_catalog()),
            'totals'    => $data['totals'],
            'series'    => $data['series'],
            'rows'      => $data['rows'],
            'count'     => $data['count'],
            'retention' => (int) get_option('zen_cookie_keeper_restore_retention_days', 365),
        ));
    }

    public function render_diagnostics() {
        $this->view('diagnostics-page', array('snapshot' => Zen_Cookie_Keeper_Diagnostics::snapshot()));
    }

    public function render_sites() {
        $this->view('sites-page', array(
            'host'      => Zen_Cookie_Keeper_Sites::request_host(),
            'domain'    => Zen_Cookie_Keeper_Sites::cookie_domain(),
            'overrides' => (array) get_option('zen_cookie_keeper_domain_overrides', array()),
            'multisite' => is_multisite(),
        ));
    }

    /* ------------------------------------------------------- AJAX plumbing */

    private function guard() {
        check_ajax_referer(self::NONCE, 'nonce');
        if (!current_user_can(self::CAP)) {
            wp_send_json_error(array('message' => __('Unauthorized', 'zen-cookie-keeper')), 403);
        }
    }

    /**
     * Read a raw POST value. The nonce is verified and the capability checked in
     * guard() (called at the top of every ajax_* handler before this runs); each
     * caller sanitises the returned value for its specific type.
     *
     * @return mixed
     */
    private function post($key, $default = '') {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in guard().
        if (!isset($_POST[$key])) {
            return $default;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Verified in guard(); caller sanitises per type.
        return wp_unslash($_POST[$key]);
    }

    public function ajax_toggle_cookie() {
        $this->guard();
        $name   = sanitize_text_field($this->post('name'));
        $status = (bool) (int) $this->post('status');

        $overrides = (array) get_option('zen_cookie_keeper_cookie_overrides', array());
        $custom    = (array) get_option('zen_cookie_keeper_custom_cookies', array());

        if (isset($custom[$name])) {
            $custom[$name]['status'] = $status;
            update_option('zen_cookie_keeper_custom_cookies', $custom);
        } else {
            if (!isset($overrides[$name]) || !is_array($overrides[$name])) {
                $overrides[$name] = array();
            }
            $overrides[$name]['status'] = $status;
            update_option('zen_cookie_keeper_cookie_overrides', $overrides);
        }
        wp_send_json_success(array('name' => $name, 'status' => $status));
    }

    public function ajax_save_override() {
        $this->guard();
        $name     = sanitize_text_field($this->post('name'));
        $lifetime = max(0, (int) $this->post('lifetime'));
        $httponly = (bool) (int) $this->post('httponly');

        $custom = (array) get_option('zen_cookie_keeper_custom_cookies', array());
        if (isset($custom[$name])) {
            $custom[$name]['lifetime'] = $lifetime;
            $custom[$name]['httponly'] = $httponly;
            update_option('zen_cookie_keeper_custom_cookies', $custom);
        } else {
            $overrides = (array) get_option('zen_cookie_keeper_cookie_overrides', array());
            if (!isset($overrides[$name]) || !is_array($overrides[$name])) {
                $overrides[$name] = array();
            }
            $overrides[$name]['lifetime'] = $lifetime;
            $overrides[$name]['httponly'] = $httponly;
            update_option('zen_cookie_keeper_cookie_overrides', $overrides);
        }
        wp_send_json_success(array('name' => $name, 'lifetime' => $lifetime, 'httponly' => $httponly));
    }

    public function ajax_add_custom() {
        $this->guard();
        $spec = array(
            'name'     => sanitize_text_field($this->post('name')),
            'bucket'   => sanitize_text_field($this->post('bucket')),
            'source'   => sanitize_text_field($this->post('source')),
            'param'    => sanitize_text_field($this->post('param')),
            'lifetime' => (int) $this->post('lifetime_days') * DAY_IN_SECONDS,
            'httponly' => (bool) (int) $this->post('httponly'),
        );
        $validated = Zen_Cookie_Keeper_Registry::validate_custom($spec);
        if (is_wp_error($validated)) {
            wp_send_json_error(array('message' => $validated->get_error_message()));
        }
        $custom = (array) get_option('zen_cookie_keeper_custom_cookies', array());
        $custom[$validated['name']] = $validated;
        update_option('zen_cookie_keeper_custom_cookies', $custom);
        wp_send_json_success(array('name' => $validated['name']));
    }

    public function ajax_delete_custom() {
        $this->guard();
        $name   = sanitize_text_field($this->post('name'));
        $custom = (array) get_option('zen_cookie_keeper_custom_cookies', array());
        unset($custom[$name]);
        update_option('zen_cookie_keeper_custom_cookies', $custom);
        wp_send_json_success(array('name' => $name));
    }

    public function ajax_save_consent() {
        $this->guard();
        $enforce   = (bool) (int) $this->post('enforce');
        $version   = sanitize_text_field($this->post('version'));
        $retention = max(DAY_IN_SECONDS, (int) $this->post('retention_days') * DAY_IN_SECONDS);

        update_option('zen_cookie_keeper_enforce_consent', $enforce ? 1 : 0);
        update_option('zen_cookie_keeper_consent_version', $version);
        update_option('zen_cookie_keeper_consent_retention', $retention);
        wp_send_json_success();
    }

    public function ajax_save_botgate() {
        $this->guard();
        $enabled = (bool) (int) $this->post('enabled');
        $deny    = $this->lines_to_array($this->post('denylist'));
        $allow   = $this->lines_to_array($this->post('allowlist'));

        update_option('zen_cookie_keeper_bot_gate_enabled', $enabled ? 1 : 0);
        update_option('zen_cookie_keeper_bot_ja4_denylist', $deny);
        update_option('zen_cookie_keeper_bot_ja4_allowlist', $allow);
        wp_send_json_success();
    }

    public function ajax_save_formats() {
        $this->guard();
        $json    = (string) $this->post('formats');
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            wp_send_json_error(array('message' => __('Invalid JSON.', 'zen-cookie-keeper')));
        }
        $clean = array();
        foreach ($decoded as $name => $rule) {
            $name = sanitize_text_field($name);
            if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', $name) || !is_array($rule)) {
                continue;
            }
            $entry = array();
            if (isset($rule['source'])) {
                $entry['source'] = in_array($rule['source'], array('mint', 'capture', 'both'), true) ? $rule['source'] : 'capture';
            }
            if (isset($rule['param']) && is_array($rule['param'])) {
                $entry['param'] = array_values(array_filter(array_map('sanitize_text_field', $rule['param'])));
            }
            if (isset($rule['template'])) {
                $entry['template'] = sanitize_text_field($rule['template']);
            }
            if (isset($rule['value_regex'])) {
                $regex = (string) $rule['value_regex'];
                // Reject a regex that does not compile.
                if (@preg_match($regex, '') !== false) {
                    $entry['value_regex'] = $regex;
                }
            }
            $clean[$name] = $entry;
        }
        update_option('zen_cookie_keeper_mint_formats', $clean);
        wp_send_json_success();
    }

    public function ajax_save_domain() {
        $this->guard();
        $host   = sanitize_text_field($this->post('host'));
        $domain = sanitize_text_field($this->post('domain'));
        $overrides = (array) get_option('zen_cookie_keeper_domain_overrides', array());
        if ($host !== '') {
            if ($domain === '') {
                unset($overrides[$host]);
            } else {
                $overrides[$host] = $domain;
            }
            update_option('zen_cookie_keeper_domain_overrides', $overrides);
        }
        wp_send_json_success();
    }

    public function ajax_erasure() {
        $this->guard();
        $token = sanitize_text_field($this->post('token'));
        if (!Zen_Cookie_Keeper_Anchor::is_valid_token($token)) {
            wp_send_json_error(array('message' => __('Enter a valid anchor token.', 'zen-cookie-keeper')));
        }
        $row = Zen_Cookie_Keeper_Store::instance()->get_anchor_by_hash(Zen_Cookie_Keeper_Anchor::hash_token($token));
        if (!$row) {
            wp_send_json_error(array('message' => __('No record found for that identifier.', 'zen-cookie-keeper')));
        }
        Zen_Cookie_Keeper_Consent::handle_erasure((int) $row['id']);
        wp_send_json_success();
    }

    public function ajax_refresh_diagnostics() {
        $this->guard();
        wp_send_json_success(Zen_Cookie_Keeper_Diagnostics::snapshot());
    }

    public function ajax_rotate_token() {
        $this->guard();
        $token = wp_generate_password(40, false, false);
        update_option('zen_cookie_keeper_site_token', $token);
        wp_send_json_success(array('token' => $token));
    }

    /* ----------------------------------------------------------- Ad clicks */

    /**
     * Gather the stats bundle for a filter window. Dates are calendar days
     * (Y-m-d) expanded to full GMT-day bounds against the GMT created_at column.
     *
     * @return array{totals:array, series:array, rows:array, count:int}
     */
    private function adclicks_data($from_date, $to_date, $platform) {
        $store = Zen_Cookie_Keeper_Store::instance();
        $from  = $from_date . ' 00:00:00';
        $to    = $to_date . ' 23:59:59';
        return array(
            'totals' => $store->click_totals($from, $to, $platform),
            'series' => $store->click_timeseries($from, $to, $platform),
            'rows'   => $store->click_list($from, $to, $platform, 200, 0),
            'count'  => $store->click_list_count($from, $to, $platform),
        );
    }

    private function sanitize_date($val, $fallback) {
        $val = sanitize_text_field((string) $val);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $val) ? $val : $fallback;
    }

    private function sanitize_platform($val) {
        $val = sanitize_text_field((string) $val);
        if ($val === '') {
            return '';
        }
        return in_array($val, Zen_Cookie_Keeper_Registry::ad_platforms(), true) ? $val : '';
    }

    /**
     * Shape a stored click row into the display fields the table/JS use.
     *
     * @return array
     */
    private function format_click_row($row) {
        return array(
            'created_at' => (string) $row['created_at'],
            'platform'   => (string) $row['platform'],
            'campaign'   => (string) $row['utm_campaign'],
            'source'     => (string) $row['utm_source'],
            'medium'     => (string) $row['utm_medium'],
            'landing'    => (string) $row['landing_path'],
            'referrer'   => (string) $row['referrer_host'],
            'click_id'   => (string) $row['click_id'],
        );
    }

    public function ajax_refresh_adclicks() {
        $this->guard();
        $to       = $this->sanitize_date($this->post('to'), gmdate('Y-m-d'));
        $from     = $this->sanitize_date($this->post('from'), gmdate('Y-m-d', time() - (29 * DAY_IN_SECONDS)));
        $platform = $this->sanitize_platform($this->post('platform'));

        $data = $this->adclicks_data($from, $to, $platform);
        wp_send_json_success(array(
            'from'     => $from,
            'to'       => $to,
            'platform' => $platform,
            'totals'   => $data['totals'],
            'series'   => $data['series'],
            'rows'     => array_map(array($this, 'format_click_row'), $data['rows']),
            'count'    => (int) $data['count'],
        ));
    }

    public function ajax_save_adclicks() {
        $this->guard();
        $days = max(1, (int) $this->post('retention_days'));
        update_option('zen_cookie_keeper_click_retention_days', $days);
        wp_send_json_success(array('retention' => $days));
    }

    /* ------------------------------------------------------ Restore history */

    /**
     * Gather the restore-history bundle for a filter window. Dates are calendar
     * days (Y-m-d) expanded to full GMT-day bounds against the GMT created_at
     * column, matching the ad-clicks screen.
     *
     * @return array{totals:array, series:array, rows:array, count:int}
     */
    private function restores_data($from_date, $to_date, $cookie) {
        $store = Zen_Cookie_Keeper_Store::instance();
        $from  = $from_date . ' 00:00:00';
        $to    = $to_date . ' 23:59:59';
        return array(
            'totals' => $store->restore_totals($from, $to, $cookie),
            'series' => $store->restore_timeseries($from, $to, $cookie),
            'rows'   => $store->restore_list($from, $to, $cookie, 200, 0),
            'count'  => $store->restore_list_count($from, $to, $cookie),
        );
    }

    /**
     * Allowlist a cookie-name filter against the live catalog ('' = all).
     */
    private function sanitize_cookie_filter($val) {
        $val = sanitize_text_field((string) $val);
        if ($val === '') {
            return '';
        }
        $catalog = Zen_Cookie_Keeper_Registry::get_catalog();
        return isset($catalog[$val]) ? $val : '';
    }

    /**
     * Shape a stored restore row into the display fields the table/JS use.
     * Ages/lifetimes are surfaced in whole days (the report granularity).
     *
     * @return array
     */
    private function format_restore_row($row) {
        return array(
            'created_at'     => (string) $row['created_at'],
            'cookie'         => (string) $row['cookie_name'],
            'platform'       => (string) $row['platform'],
            'bucket'         => (string) $row['bucket'],
            'reason'         => (string) $row['reason'],
            'age_days'       => (int) round(((int) $row['value_age']) / DAY_IN_SECONDS),
            'remaining_days' => (int) round(((int) $row['remaining_lifetime']) / DAY_IN_SECONDS),
        );
    }

    public function ajax_refresh_restores() {
        $this->guard();
        $to     = $this->sanitize_date($this->post('to'), gmdate('Y-m-d'));
        $from   = $this->sanitize_date($this->post('from'), gmdate('Y-m-d', time() - (29 * DAY_IN_SECONDS)));
        $cookie = $this->sanitize_cookie_filter($this->post('cookie'));

        $data = $this->restores_data($from, $to, $cookie);
        wp_send_json_success(array(
            'from'   => $from,
            'to'     => $to,
            'cookie' => $cookie,
            'totals' => $data['totals'],
            'series' => $data['series'],
            'rows'   => array_map(array($this, 'format_restore_row'), $data['rows']),
            'count'  => (int) $data['count'],
        ));
    }

    public function ajax_save_restores() {
        $this->guard();
        $days = max(1, (int) $this->post('retention_days'));
        update_option('zen_cookie_keeper_restore_retention_days', $days);
        wp_send_json_success(array('retention' => $days));
    }

    /**
     * Stream the filtered restore-history list as a CSV download (admin-post,
     * same pattern and nonce as the ad-clicks export).
     */
    public function export_restores() {
        if (!current_user_can(self::CAP)) {
            wp_die(esc_html__('Unauthorized', 'zen-cookie-keeper'), '', array('response' => 403));
        }
        check_admin_referer('zen_cookie_keeper_export');

        // Filters arrive on the query string; the nonce above (check_admin_referer)
        // authenticates the request and each value is strictly sanitised here.
        $to     = $this->sanitize_date(isset($_GET['to']) ? sanitize_text_field(wp_unslash($_GET['to'])) : '', gmdate('Y-m-d'));
        $from   = $this->sanitize_date(isset($_GET['from']) ? sanitize_text_field(wp_unslash($_GET['from'])) : '', gmdate('Y-m-d', time() - (29 * DAY_IN_SECONDS)));
        $cookie = $this->sanitize_cookie_filter(isset($_GET['cookie']) ? sanitize_text_field(wp_unslash($_GET['cookie'])) : '');

        $store   = Zen_Cookie_Keeper_Store::instance();
        $from_dt = $from . ' 00:00:00';
        $to_dt   = $to . ' 23:59:59';

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=zenck-restores-' . gmdate('Ymd-His') . '.csv');

        // php://output is the correct sink for a streamed download; WP_Filesystem
        // does not apply to a live HTTP response body.
        $handle = fopen('php://output', 'w'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
        fputcsv($handle, array(
            'restored_at_utc', 'cookie', 'platform', 'bucket', 'reason',
            'value_age_seconds', 'remaining_lifetime_seconds',
        ));

        $limit  = 1000;
        $offset = 0;
        do {
            $rows = $store->restore_list($from_dt, $to_dt, $cookie, $limit, $offset);
            foreach ($rows as $r) {
                fputcsv($handle, array(
                    $r['created_at'], $r['cookie_name'], $r['platform'], $r['bucket'],
                    $r['reason'], $r['value_age'], $r['remaining_lifetime'],
                ));
            }
            $offset += $limit;
        } while (count($rows) === $limit);

        fclose($handle); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        exit;
    }

    /**
     * Stream the filtered ad-click list as a CSV download. Uses admin-post
     * because a file download cannot ride an AJAX JSON response.
     */
    public function export_clicks() {
        if (!current_user_can(self::CAP)) {
            wp_die(esc_html__('Unauthorized', 'zen-cookie-keeper'), '', array('response' => 403));
        }
        check_admin_referer('zen_cookie_keeper_export');

        // Filters arrive on the query string; the nonce above (check_admin_referer)
        // authenticates the request and each value is strictly sanitised here.
        $to       = $this->sanitize_date(isset($_GET['to']) ? sanitize_text_field(wp_unslash($_GET['to'])) : '', gmdate('Y-m-d'));
        $from     = $this->sanitize_date(isset($_GET['from']) ? sanitize_text_field(wp_unslash($_GET['from'])) : '', gmdate('Y-m-d', time() - (29 * DAY_IN_SECONDS)));
        $platform = $this->sanitize_platform(isset($_GET['platform']) ? sanitize_text_field(wp_unslash($_GET['platform'])) : '');

        $store   = Zen_Cookie_Keeper_Store::instance();
        $from_dt = $from . ' 00:00:00';
        $to_dt   = $to . ' 23:59:59';

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=zenck-adclicks-' . gmdate('Ymd-His') . '.csv');

        // php://output is the correct sink for a streamed download; WP_Filesystem
        // does not apply to a live HTTP response body.
        $handle = fopen('php://output', 'w'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
        fputcsv($handle, array(
            'recorded_at_utc', 'platform', 'click_param', 'click_id', 'landing_path',
            'referrer_host', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
        ));

        $limit  = 1000;
        $offset = 0;
        do {
            $rows = $store->click_list($from_dt, $to_dt, $platform, $limit, $offset);
            foreach ($rows as $r) {
                fputcsv($handle, array(
                    $r['created_at'], $r['platform'], $r['click_param'], $r['click_id'],
                    $r['landing_path'], $r['referrer_host'], $r['utm_source'], $r['utm_medium'],
                    $r['utm_campaign'], $r['utm_term'], $r['utm_content'],
                ));
            }
            $offset += $limit;
        } while (count($rows) === $limit);

        fclose($handle); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        exit;
    }

    private function lines_to_array($text) {
        $lines = preg_split('/[\r\n,]+/', (string) $text);
        $out   = array();
        foreach ($lines as $line) {
            $line = sanitize_text_field(trim($line));
            if ($line !== '') {
                $out[] = $line;
            }
        }
        return array_values(array_unique($out));
    }

    /* ----------------------------------------------------------- View utils */

    public static function human_lifetime($seconds) {
        $seconds = (int) $seconds;
        if ($seconds % YEAR_IN_SECONDS === 0) {
            $years = $seconds / YEAR_IN_SECONDS;
            /* translators: %d: number of years */
            return sprintf(_n('%d year', '%d years', $years, 'zen-cookie-keeper'), $years);
        }
        $days = max(1, (int) round($seconds / DAY_IN_SECONDS));
        /* translators: %d: number of days */
        return sprintf(_n('%d day', '%d days', $days, 'zen-cookie-keeper'), $days);
    }
}
