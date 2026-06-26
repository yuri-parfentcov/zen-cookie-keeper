<?php
/**
 * Front-end integration for Zen Cookie Keeper.
 *
 * Enqueues the JS companion (early, in <head>) which delivers the Consent Mode
 * state + captured cookie values + landing click-params to the /sync endpoint,
 * from which the server replies with the Set-Cookie headers. Also provides the
 * send_headers fallback for installs with no front cache where PHP runs on the
 * page render.
 *
 * @package Zen_Cookie_Keeper
 */

if (!defined('ABSPATH')) {
    exit;
}

class Zen_Cookie_Keeper_Public {

    public function init_hooks() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue'), 1);
        add_action('send_headers', array($this, 'maybe_emit_on_render'));
    }

    /**
     * Enqueue the companion in the head so it executes as early as possible
     * (it runs on cached pages too — the tag is baked into the cached HTML and
     * the POST it fires bypasses the cache).
     */
    public function enqueue() {
        // Nothing to do if nothing is enabled.
        if (empty(Zen_Cookie_Keeper_Registry::enabled_cookies())) {
            return;
        }

        wp_register_script(
            'zen-cookie-keeper-companion',
            ZEN_COOKIE_KEEPER_URL . 'public/js/zen-cookie-keeper-companion.js',
            array(),
            ZEN_COOKIE_KEEPER_VERSION,
            false // in <head>, run early.
        );

        wp_localize_script('zen-cookie-keeper-companion', 'ZenCookieKeeper', array(
            'endpoint'       => esc_url_raw(rest_url(Zen_Cookie_Keeper_Rest::NS . '/sync')),
            'token'          => (string) get_option('zen_cookie_keeper_site_token', ''),
            'captureNames'   => array_values(array_keys($this->capture_names())),
            'clickParams'    => Zen_Cookie_Keeper_Registry::all_click_params(),
            'consentVersion' => (string) get_option('zen_cookie_keeper_consent_version', ''),
            'enforce'        => Zen_Cookie_Keeper_Consent::enforce_enabled() ? 1 : 0,
        ));

        wp_enqueue_script('zen-cookie-keeper-companion');
    }

    /**
     * Enabled cookies whose source includes capture (the companion reads these
     * from document.cookie, read-only, and posts them once).
     */
    private function capture_names() {
        $out = array();
        foreach (Zen_Cookie_Keeper_Registry::enabled_cookies() as $name => $spec) {
            if (in_array($spec['source'], array('capture', 'both'), true)) {
                $out[$name] = true;
            }
        }
        return $out;
    }

    /**
     * Fallback for installs with NO front cache: when a landing arrives with a
     * click-param and enforcement is disabled (admin accepted responsibility),
     * mint + emit server-side on the render so the click cookie is set even with
     * JS disabled. On any cached/proxied install this is a no-op (the write
     * would be stripped) and the /sync POST is the real path.
     */
    public function maybe_emit_on_render() {
        if (is_admin() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }
        if (Zen_Cookie_Keeper_Consent::enforce_enabled()) {
            return; // No server-side consent signal here; do not write without it.
        }
        $status = Zen_Cookie_Keeper_Cache_Detector::status();
        if ($status['blocks_render_write']) {
            return;
        }

        // Read click params from the current URL (server-side).
        $landing = array();
        foreach (Zen_Cookie_Keeper_Registry::all_click_params() as $p) {
            // A click-param on the landing URL is public, read-only context with
            // no state change; it is strictly format-validated below. No nonce
            // applies to an inbound ad-click landing.
            if (!isset($_GET[$p])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                continue;
            }
            $v = sanitize_text_field(wp_unslash($_GET[$p])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            if ($v !== '' && preg_match('/^[A-Za-z0-9_.\-]+$/', $v)) {
                $landing[$p] = $v;
            }
        }
        if (empty($landing)) {
            return;
        }

        $granted = array('analytics' => true, 'advertising' => true);
        $anchor  = Zen_Cookie_Keeper_Anchor::resolve();
        if ($anchor) {
            $anchor_id = (int) $anchor['id'];
        } else {
            $minted = Zen_Cookie_Keeper_Anchor::mint($granted);
            if (!$minted) {
                return;
            }
            $anchor_id = $minted['id'];
            $emit[]    = $minted['spec'];
        }

        $emit  = isset($emit) ? $emit : array();
        $store = Zen_Cookie_Keeper_Store::instance();
        foreach (Zen_Cookie_Keeper_Registry::get_catalog() as $name => $spec) {
            if (empty($spec['status']) || !in_array($spec['source'], array('mint', 'both'), true)) {
                continue;
            }
            if ($store->get_value($anchor_id, $name)) {
                continue;
            }
            $value = Zen_Cookie_Keeper_Mint::mint($name, $landing);
            if ($value === '') {
                continue;
            }
            $store->store_value_once($anchor_id, $name, $value, $spec['bucket'], 'mint', time(), (int) $spec['lifetime']);
            $store->insert_op('mint', $name, 'minted_render', $anchor_id);
            $emit[] = array(
                'name'  => $name,
                'value' => $value,
                'opts'  => array('max_age' => (int) $spec['lifetime'], 'httponly' => !empty($spec['httponly'])),
            );
        }

        if (!empty($emit)) {
            Zen_Cookie_Keeper_Emitter::emit($emit);
        }
    }
}
