<?php
/**
 * Diagnostics for Zen Cookie Keeper.
 *
 * Surfaces the things that silently break a server-side cookie write: a front
 * cache intercepting the page render, a proxy hiding the real client IP, another
 * plugin/sGTM also setting the same cookies (collision), and managed cookies
 * being rewritten from JS (which drops them under the cap).
 *
 * @package Zen_Cookie_Keeper
 */

if (!defined('ABSPATH')) {
    exit;
}

class Zen_Cookie_Keeper_Diagnostics {

    /**
     * Known plugins that also create/set GA/ad cookies — a collision risk where
     * two writers fight over the same cookie.
     */
    private static function collision_plugins() {
        return array(
            'duracelltomi-google-tag-manager/google-tag-manager.php' => 'GTM4WP (Google Tag Manager for WordPress)',
            'google-site-kit/google-site-kit.php'                    => 'Site Kit by Google',
            'pixelyoursite/pixelyoursite.php'                        => 'PixelYourSite',
            'official-facebook-pixel/facebook-for-wordpress.php'     => 'Meta Pixel',
            'pys-pro/pys-pro.php'                                    => 'PixelYourSite Pro',
        );
    }

    /**
     * Detect active collision plugins.
     *
     * @return array slug => label
     */
    public static function detect_collisions() {
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $hits = array();
        foreach (self::collision_plugins() as $plugin => $label) {
            if (is_plugin_active($plugin)) {
                $hits[$plugin] = $label;
            }
        }
        return $hits;
    }

    /**
     * Whether the companion has recently reported a managed cookie being
     * rewritten from JS (logged as a divergence op). Heuristic from the ops log.
     *
     * @return bool
     */
    public static function js_rewrite_suspected() {
        $ops = Zen_Cookie_Keeper_Store::instance()->recent_ops(100);
        foreach ($ops as $op) {
            if ($op['op_type'] === 'capture' && $op['result'] === 'rejected') {
                return true;
            }
        }
        return false;
    }

    /**
     * A consolidated diagnostics snapshot for the admin screen.
     *
     * @return array
     */
    public static function snapshot() {
        $cache = Zen_Cookie_Keeper_Cache_Detector::status();
        return array(
            'cache'        => $cache,
            'behind_proxy' => Zen_Cookie_Keeper_IP::behind_proxy(),
            'real_ip'      => Zen_Cookie_Keeper_IP::client_ip(),
            'collisions'   => self::detect_collisions(),
            'js_rewrite'   => self::js_rewrite_suspected(),
            'cookie_domain'=> Zen_Cookie_Keeper_Sites::cookie_domain(),
            'counts'       => Zen_Cookie_Keeper_Store::instance()->counts(),
            'recent_ops'   => Zen_Cookie_Keeper_Store::instance()->recent_ops(30),
        );
    }
}
