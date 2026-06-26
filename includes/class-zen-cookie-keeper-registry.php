<?php
/**
 * Cookie registry / catalog for Zen Cookie Keeper.
 *
 * The default catalog ships preconfigured. The live catalog is the defaults
 * merged with per-cookie admin overrides (status / lifetime / httponly) and any
 * admin-defined custom cookies. The anchor is deliberately NOT in this catalog —
 * its lifecycle is computed from what is enabled here (see Anchor class).
 *
 * @package Zen_Cookie_Keeper
 */

if (!defined('ABSPATH')) {
    exit;
}

class Zen_Cookie_Keeper_Registry {

    const BUCKET_ANALYTICS   = 'analytics';
    const BUCKET_ADVERTISING = 'advertising';

    /**
     * Preconfigured default catalog. Lifetimes are in seconds.
     *
     * @return array name => spec
     */
    public static function default_catalog() {
        $y2  = 2 * YEAR_IN_SECONDS;
        $d90 = 90 * DAY_IN_SECONDS;
        $d30 = 30 * DAY_IN_SECONDS;

        return array(
            '_ga' => array(
                'name' => '_ga', 'platform' => 'GA4', 'bucket' => 'analytics',
                'source' => 'capture', 'param' => array(), 'lifetime' => $y2,
                'httponly' => false, 'status' => true,
                'label' => __('Google Analytics 4 client_id', 'zen-cookie-keeper'),
            ),
            '_gcl_aw' => array(
                'name' => '_gcl_aw', 'platform' => 'Google Ads', 'bucket' => 'advertising',
                'source' => 'mint', 'param' => array('gclid', 'wbraid', 'gbraid'), 'lifetime' => $d90,
                'httponly' => false, 'status' => true,
                'label' => __('Google Ads click id', 'zen-cookie-keeper'),
            ),
            '_gcl_dc' => array(
                'name' => '_gcl_dc', 'platform' => 'Google Ads', 'bucket' => 'advertising',
                'source' => 'mint', 'param' => array('dclid'), 'lifetime' => $d90,
                'httponly' => false, 'status' => true,
                'label' => __('Campaign Manager click id', 'zen-cookie-keeper'),
            ),
            '_fbc' => array(
                'name' => '_fbc', 'platform' => 'Meta', 'bucket' => 'advertising',
                'source' => 'mint', 'param' => array('fbclid'), 'lifetime' => $d90,
                'httponly' => false, 'status' => true,
                'label' => __('Meta click id', 'zen-cookie-keeper'),
            ),
            '_fbp' => array(
                'name' => '_fbp', 'platform' => 'Meta', 'bucket' => 'advertising',
                'source' => 'capture', 'param' => array(), 'lifetime' => $d90,
                'httponly' => false, 'status' => true,
                'label' => __('Meta browser id', 'zen-cookie-keeper'),
            ),
            '_uetmsclkid' => array(
                'name' => '_uetmsclkid', 'platform' => 'Microsoft Ads', 'bucket' => 'advertising',
                'source' => 'mint', 'param' => array('msclkid'), 'lifetime' => $d90,
                'httponly' => false, 'status' => true,
                'label' => __('Microsoft Ads click id', 'zen-cookie-keeper'),
            ),
            '_ttp' => array(
                'name' => '_ttp', 'platform' => 'TikTok', 'bucket' => 'advertising',
                'source' => 'capture', 'param' => array('ttclid'), 'lifetime' => $d90,
                'httponly' => false, 'status' => true,
                'label' => __('TikTok browser id', 'zen-cookie-keeper'),
            ),
            'li_fat_id' => array(
                'name' => 'li_fat_id', 'platform' => 'LinkedIn', 'bucket' => 'advertising',
                'source' => 'mint', 'param' => array('li_fat_id'), 'lifetime' => $d30,
                'httponly' => false, 'status' => true,
                'label' => __('LinkedIn click id', 'zen-cookie-keeper'),
            ),
        );
    }

    /**
     * Cookies that are explicitly unsupported, with a reason for the UI so the
     * client does not read their absence as a gap.
     *
     * @return array
     */
    public static function unsupported() {
        return array(
            '_ga_*' => __('GA4 session cookie (GS2.1 format). Restoration is brittle and unnecessary — we restore user identity (_ga client_id), never the session.', 'zen-cookie-keeper'),
        );
    }

    /**
     * The live catalog: defaults + overrides + custom cookies.
     *
     * @return array name => spec
     */
    public static function get_catalog() {
        $catalog   = self::default_catalog();
        $overrides = get_option('zen_cookie_keeper_cookie_overrides', array());
        $custom    = get_option('zen_cookie_keeper_custom_cookies', array());

        if (is_array($overrides)) {
            foreach ($overrides as $name => $ov) {
                if (!isset($catalog[$name]) || !is_array($ov)) {
                    continue;
                }
                if (isset($ov['status'])) {
                    $catalog[$name]['status'] = (bool) $ov['status'];
                }
                if (isset($ov['lifetime'])) {
                    $catalog[$name]['lifetime'] = max(0, (int) $ov['lifetime']);
                }
                if (isset($ov['httponly'])) {
                    $catalog[$name]['httponly'] = (bool) $ov['httponly'];
                }
            }
        }

        if (is_array($custom)) {
            foreach ($custom as $name => $spec) {
                if (!is_array($spec) || $name === '') {
                    continue;
                }
                $catalog[$name] = wp_parse_args($spec, array(
                    'name'     => $name,
                    'platform' => 'Custom',
                    'bucket'   => 'analytics',
                    'source'   => 'capture',
                    'param'    => array(),
                    'lifetime' => 90 * DAY_IN_SECONDS,
                    'httponly' => false,
                    'status'   => true,
                    'custom'   => true,
                    'label'    => $name,
                ));
            }
        }

        return $catalog;
    }

    public static function get_cookie($name) {
        $catalog = self::get_catalog();
        return isset($catalog[$name]) ? $catalog[$name] : null;
    }

    /**
     * Enabled cookies, optionally restricted to a consent bucket.
     *
     * @return array name => spec
     */
    public static function enabled_cookies($bucket = null) {
        $out = array();
        foreach (self::get_catalog() as $name => $spec) {
            if (empty($spec['status'])) {
                continue;
            }
            if (null !== $bucket && $spec['bucket'] !== $bucket) {
                continue;
            }
            $out[$name] = $spec;
        }
        return $out;
    }

    /**
     * Group the catalog by platform for the registry table UI.
     *
     * @return array platform => [name => spec]
     */
    public static function grouped() {
        $groups = array();
        foreach (self::get_catalog() as $name => $spec) {
            $groups[$spec['platform']][$name] = $spec;
        }
        return $groups;
    }

    /**
     * All click-parameters across the catalog (full coverage list).
     *
     * @return string[]
     */
    public static function all_click_params() {
        $params = array();
        foreach (self::get_catalog() as $spec) {
            if (!empty($spec['param']) && is_array($spec['param'])) {
                $params = array_merge($params, $spec['param']);
            }
        }
        return array_values(array_unique($params));
    }

    /**
     * Validate a custom-cookie spec submitted from the admin form.
     *
     * @return array|WP_Error Normalised spec, or error.
     */
    public static function validate_custom($spec) {
        $name = isset($spec['name']) ? sanitize_text_field($spec['name']) : '';
        if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', $name)) {
            return new WP_Error('zenck_bad_name', __('Cookie name must be 1–64 chars of letters, digits, underscore or hyphen.', 'zen-cookie-keeper'));
        }
        $bucket = isset($spec['bucket']) ? sanitize_text_field($spec['bucket']) : '';
        if (!in_array($bucket, array(self::BUCKET_ANALYTICS, self::BUCKET_ADVERTISING), true)) {
            return new WP_Error('zenck_bad_bucket', __('A consent bucket (analytics or advertising) is required — otherwise there is nothing to gate the cookie on.', 'zen-cookie-keeper'));
        }
        $source = isset($spec['source']) ? sanitize_text_field($spec['source']) : 'capture';
        if (!in_array($source, array('mint', 'capture', 'both'), true)) {
            $source = 'capture';
        }
        $param = array();
        if (!empty($spec['param'])) {
            $raw = is_array($spec['param']) ? $spec['param'] : explode(',', (string) $spec['param']);
            foreach ($raw as $p) {
                $p = sanitize_text_field(trim($p));
                if ($p !== '' && preg_match('/^[A-Za-z0-9_-]{1,64}$/', $p)) {
                    $param[] = $p;
                }
            }
        }
        $lifetime = isset($spec['lifetime']) ? max(0, (int) $spec['lifetime']) : 90 * DAY_IN_SECONDS;

        return array(
            'name'     => $name,
            'platform' => 'Custom',
            'bucket'   => $bucket,
            'source'   => $source,
            'param'    => $param,
            'lifetime' => $lifetime,
            'httponly' => !empty($spec['httponly']),
            'status'   => true,
            'custom'   => true,
            'label'    => $name,
        );
    }
}
