<?php
/**
 * Zen Cookie Keeper — optional mu-plugin loader.
 *
 * Copy this file to wp-content/mu-plugins/ to load Zen Cookie Keeper as a
 * must-use plugin, so its hooks register before regular plugins. This is purely
 * a load-order optimisation; the plugin itself must still be installed in
 * wp-content/plugins/zen-cookie-keeper/. The plugin guards against double
 * loading, so it is safe to keep both.
 *
 * @package Zen_Cookie_Keeper
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('ZEN_COOKIE_KEEPER_VERSION')) {
    $zen_cookie_keeper_main = WP_PLUGIN_DIR . '/zen-cookie-keeper/zen-cookie-keeper.php';
    if (file_exists($zen_cookie_keeper_main)) {
        require_once $zen_cookie_keeper_main;
    }
    unset($zen_cookie_keeper_main);
}
