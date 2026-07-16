<?php
/**
 * Plugin Name: Zen Cookie Keeper
 * Plugin URI: https://zenrepublic.agency/zen-cookie-keeper
 * Description: Sets and restores first-party marketing and analytics cookies server-side so they survive Safari ITP / Firefox ETP capping — consent-gated, no server-side GTM, no container. Manages cookies that gtag and ad pixels already create; it does not forward events and makes no outbound third-party calls.
 * Version: 1.2.0
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Author: Zen Republic Agency
 * Author URI: https://zenrepublic.agency
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: zen-cookie-keeper
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

define('ZEN_COOKIE_KEEPER_VERSION', '1.2.0');
define('ZEN_COOKIE_KEEPER_FILE', __FILE__);
define('ZEN_COOKIE_KEEPER_PATH', plugin_dir_path(__FILE__));
define('ZEN_COOKIE_KEEPER_URL', plugin_dir_url(__FILE__));
define('ZEN_COOKIE_KEEPER_BASENAME', plugin_basename(__FILE__));
define('ZEN_COOKIE_KEEPER_TEXT_DOMAIN', 'zen-cookie-keeper');
define('ZEN_COOKIE_KEEPER_DB_VERSION', '3');

// The durable, server-set, HttpOnly anchor cookie name. Deliberately chosen so
// it is NOT matched by the front cache's guest cookie-strip regex, so it always
// reaches PHP on the incoming request.
if (!defined('ZEN_COOKIE_KEEPER_ANCHOR_NAME')) {
    define('ZEN_COOKIE_KEEPER_ANCHOR_NAME', 'zenck_anchor');
}

require_once ZEN_COOKIE_KEEPER_PATH . 'includes/class-zen-cookie-keeper-schema.php';
require_once ZEN_COOKIE_KEEPER_PATH . 'includes/class-zen-cookie-keeper-activator.php';
require_once ZEN_COOKIE_KEEPER_PATH . 'includes/class-zen-cookie-keeper.php';

register_activation_hook(__FILE__, array('Zen_Cookie_Keeper_Activator', 'activate'));
register_deactivation_hook(__FILE__, array('Zen_Cookie_Keeper_Activator', 'deactivate'));

add_action('plugins_loaded', array('Zen_Cookie_Keeper', 'get_instance'));

// Plugins-list row — link straight to the status screen.
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
    $url  = admin_url('admin.php?page=zen-cookie-keeper');
    $html = '<a href="' . esc_url($url) . '">' . esc_html__('Settings', 'zen-cookie-keeper') . '</a>';
    array_unshift($links, $html);
    return $links;
});
