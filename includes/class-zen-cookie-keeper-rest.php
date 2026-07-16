<?php
/**
 * REST controller for Zen Cookie Keeper.
 *
 * The public /sync endpoint is the primary cookie-write channel. It is a POST,
 * so a front cache passes it to PHP uncached; its response is JSON (not
 * text/html) and is marked no-store, so the Set-Cookie headers survive the
 * cache layer. The full pipeline runs here: bot-gate → consent → anchor →
 * withdrawal → capture → mint → restore → emit.
 *
 * @package Zen_Cookie_Keeper
 */

if (!defined('ABSPATH')) {
    exit;
}

class Zen_Cookie_Keeper_Rest {

    const NS = 'zen-cookie-keeper/v1';

    public function register_routes() {
        register_rest_route(self::NS, '/sync', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'handle_sync'),
            'permission_callback' => array($this, 'check_public'),
        ));

        register_rest_route(self::NS, '/selftest', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'handle_selftest'),
            'permission_callback' => array($this, 'check_public'),
        ));
    }

    /* ---------------------------------------------------------------------
     * Permission / protection for the public endpoints.
     * ------------------------------------------------------------------- */

    /**
     * Validate the rotating site token and apply a per-IP rate limit. The token
     * is localised to the page; combined with per-cookie format validation,
     * anchor binding and rate limiting this protects the endpoint against forged
     * input without depending on a (cache-stale) nonce.
     *
     * @return true|WP_Error
     */
    public function check_public($request) {
        $token    = (string) $request->get_param('token');
        $expected = (string) get_option('zen_cookie_keeper_site_token', '');

        if ($expected === '' || !hash_equals($expected, $token)) {
            return new WP_Error('zenck_bad_token', __('Invalid request token.', 'zen-cookie-keeper'), array('status' => 403));
        }

        if ($this->rate_limited()) {
            return new WP_Error('zenck_rate', __('Too many requests.', 'zen-cookie-keeper'), array('status' => 429));
        }
        return true;
    }

    /**
     * Lightweight per-IP rate limit (separate from the bot-gate cadence).
     */
    private function rate_limited() {
        $ip_hash = Zen_Cookie_Keeper_IP::hash_ip();
        if ($ip_hash === '') {
            return false;
        }
        $key   = 'zenck_rl_' . substr($ip_hash, 0, 32);
        $count = (int) get_transient($key);
        $count++;
        set_transient($key, $count, MINUTE_IN_SECONDS);
        return $count > 120;
    }

    /* ---------------------------------------------------------------------
     * /sync
     * ------------------------------------------------------------------- */

    public function handle_sync($request) {
        $store = Zen_Cookie_Keeper_Store::instance();

        // --- Parse + sanitise the request -------------------------------
        $signals = $this->sanitize_signals($request->get_param('consent'));
        $version = sanitize_text_field((string) $request->get_param('consent_version'));
        if ($version === '') {
            $version = (string) get_option('zen_cookie_keeper_consent_version', '');
        }
        $captured = $this->sanitize_captured($request->get_param('captured'));
        $landing  = $this->sanitize_landing($request->get_param('landing'));
        $attrib   = $this->sanitize_attrib($request->get_param('attrib'));
        $marker   = (bool) $request->get_param('cm'); // companion marker

        // --- Bot gate ---------------------------------------------------
        $bot = Zen_Cookie_Keeper_Bot_Gate::evaluate(array('has_companion_marker' => $marker));
        if ($bot['is_bot']) {
            $store->insert_op('skip', '', 'bot:' . $bot['reason']);
            return $this->respond(array('set' => array(), 'anchor' => false, 'bot' => true));
        }

        // --- Consent → buckets ------------------------------------------
        $granted  = Zen_Cookie_Keeper_Consent::granted_buckets($signals);
        // Whether the consent state is an EXPLICIT user decision (a Consent Mode
        // "update") rather than the pre-interaction "default". On a return visit
        // the CMP emits its denied default first and only then re-applies the
        // stored consent, so a denied-without-explicit signal must never be
        // treated as a withdrawal.
        $explicit = (bool) $request->get_param('explicit');
        $exists   = Zen_Cookie_Keeper_Anchor::resolve();

        // Nothing consented with payload → anchor carries nothing.
        if (!Zen_Cookie_Keeper_Anchor::should_exist($granted)) {
            $specs = array();
            // Purge ONLY on an explicit, enforced withdrawal — never on a
            // transient default-denied that precedes the CMP restoring consent.
            if ($exists && $explicit && Zen_Cookie_Keeper_Consent::enforce_enabled()) {
                $specs[] = Zen_Cookie_Keeper_Anchor::purge((int) $exists['id']);
            }
            return $this->emit_and_respond($specs, array('set' => array(), 'anchor' => false));
        }

        // --- Resolve or mint the anchor ---------------------------------
        $emit = array();
        if ($exists) {
            $anchor_id = (int) $exists['id'];
            $store->touch_anchor($anchor_id, Zen_Cookie_Keeper_Anchor::ttl_for($granted));
        } else {
            $minted = Zen_Cookie_Keeper_Anchor::mint($granted, false, $bot['ja4']);
            if (!$minted) {
                return new WP_Error('zenck_anchor_fail', __('Could not establish identity.', 'zen-cookie-keeper'), array('status' => 500));
            }
            $anchor_id = $minted['id'];
            $emit[]    = $minted['spec'];
        }

        // --- Consent record (only when changed) -------------------------
        if (Zen_Cookie_Keeper_Consent::changed_since_last($anchor_id, $granted)) {
            Zen_Cookie_Keeper_Consent::record($anchor_id, $granted, $version);
        }

        // --- Withdrawal: only on an EXPLICIT consent change -------------
        // A denied bucket that comes from the pre-interaction default (not an
        // explicit update) must not delete already-stored values.
        if ($explicit) {
            foreach (array('analytics', 'advertising') as $bucket) {
                if (empty($granted[$bucket])) {
                    Zen_Cookie_Keeper_Consent::handle_withdrawal($anchor_id, $bucket);
                }
            }
        }

        // --- Ad-click session record (once per anchor + platform + click id) --
        // An ad landing carries a click param (gclid / msclkid / fbclid / …).
        // Recording is gated on the advertising bucket, exactly like the ad
        // cookie mint below, so it inherits the same consent posture.
        if (!empty($landing) && !empty($granted['advertising'])) {
            Zen_Cookie_Keeper_Ad_Clicks::record($anchor_id, $landing, $attrib);
        }

        $set     = array();
        $catalog = Zen_Cookie_Keeper_Registry::get_catalog();

        // --- Capture ----------------------------------------------------
        foreach ($captured as $name => $value) {
            if (empty($catalog[$name]) || empty($catalog[$name]['status'])) {
                continue;
            }
            $spec = $catalog[$name];
            if (empty($granted[$spec['bucket']])) {
                continue;
            }
            if (!in_array($spec['source'], array('capture', 'both'), true)) {
                continue;
            }
            Zen_Cookie_Keeper_Capture::receive($anchor_id, $name, $value, $spec);
        }

        // --- Mint -------------------------------------------------------
        foreach ($catalog as $name => $spec) {
            if (empty($spec['status']) || empty($granted[$spec['bucket']])) {
                continue;
            }
            if (!in_array($spec['source'], array('mint', 'both'), true)) {
                continue;
            }
            // Skip if already stored (restore handles re-emit).
            if ($store->get_value($anchor_id, $name)) {
                continue;
            }
            $value = Zen_Cookie_Keeper_Mint::mint($name, $landing);
            if ($value === '') {
                continue;
            }
            $store->store_value_once($anchor_id, $name, $value, $spec['bucket'], 'mint', time(), (int) $spec['lifetime']);
            $store->insert_op('mint', $name, 'minted', $anchor_id);
            $emit[] = array(
                'name'  => $name,
                'value' => $value,
                'opts'  => array('max_age' => (int) $spec['lifetime'], 'httponly' => !empty($spec['httponly'])),
            );
            $set[] = $name;
        }

        // --- Restore ----------------------------------------------------
        // Anything just minted in this same request is excluded inside plan()
        // (it is already in $emit, and it must not be logged as a restore).
        $restore_specs = Zen_Cookie_Keeper_Restore::plan($anchor_id, $captured, $granted, array_flip($set));
        foreach ($restore_specs as $spec) {
            $emit[] = $spec;
            $set[]  = $spec['name'];
        }

        return $this->emit_and_respond($emit, array(
            'set'    => array_values(array_unique($set)),
            'anchor' => true,
        ));
    }

    /* ---------------------------------------------------------------------
     * /selftest — confirm a server-set cookie round-trips the cache layer.
     * ------------------------------------------------------------------- */

    public function handle_selftest($request) {
        $probe = (string) $request->get_param('probe');
        // Echo back whether we saw the probe cookie the client claims to hold.
        $seen = isset($_COOKIE['zenck_selftest']) ? sanitize_text_field(wp_unslash($_COOKIE['zenck_selftest'])) : '';

        $token = wp_generate_password(12, false, false);
        $specs = array(array(
            'name'  => 'zenck_selftest',
            'value' => $token,
            'opts'  => array('max_age' => 300, 'httponly' => false),
        ));

        return $this->emit_and_respond($specs, array(
            'issued'    => $token,
            'saw_probe' => ($probe !== '' && $seen === $probe),
            'had_cookie' => $seen !== '',
        ));
    }

    /* ---------------------------------------------------------------------
     * Helpers
     * ------------------------------------------------------------------- */

    private function emit_and_respond($specs, $body) {
        if (!empty($specs)) {
            Zen_Cookie_Keeper_Emitter::emit($specs);
        } else {
            Zen_Cookie_Keeper_Emitter::no_cache_headers();
        }
        return $this->respond($body);
    }

    private function respond($body) {
        $response = new WP_REST_Response($body, 200);
        $response->header('Cache-Control', 'no-store, private, max-age=0');
        return $response;
    }

    private function sanitize_signals($raw) {
        if (!is_array($raw)) {
            return array();
        }
        $out = array();
        foreach (array('analytics_storage', 'ad_storage', 'ad_user_data', 'ad_personalization') as $k) {
            if (isset($raw[$k])) {
                $out[$k] = sanitize_text_field((string) $raw[$k]);
            }
        }
        return $out;
    }

    private function sanitize_captured($raw) {
        if (!is_array($raw)) {
            return array();
        }
        $out = array();
        foreach ($raw as $name => $value) {
            $name = sanitize_text_field((string) $name);
            if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', $name)) {
                continue;
            }
            // Value validated per-cookie at capture time; here just bound length.
            $value = (string) $value;
            if (strlen($value) <= 512) {
                $out[$name] = $value;
            }
        }
        return $out;
    }

    private function sanitize_landing($raw) {
        if (!is_array($raw)) {
            return array();
        }
        $allowed = Zen_Cookie_Keeper_Registry::all_click_params();
        $out     = array();
        foreach ($raw as $name => $value) {
            $name = sanitize_text_field((string) $name);
            if (!in_array($name, $allowed, true)) {
                continue;
            }
            $value = sanitize_text_field((string) $value);
            if ($value !== '' && strlen($value) <= 256 && preg_match('/^[A-Za-z0-9_.\-]+$/', $value)) {
                $out[$name] = $value;
            }
        }
        return $out;
    }

    /**
     * Sanitise the attribution object that accompanies an ad landing: page path,
     * referrer host and the five UTM parameters. Only honoured server-side when
     * a click param is present (this method just cleans + length-bounds it).
     *
     * @return array
     */
    private function sanitize_attrib($raw) {
        return Zen_Cookie_Keeper_Ad_Clicks::sanitize_attrib($raw);
    }
}
