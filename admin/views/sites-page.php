<?php
/**
 * Sites / domains screen.
 *
 * @package Zen_Cookie_Keeper
 * @var string $host
 * @var string $domain
 * @var array  $overrides
 * @var bool   $multisite
 */

if (!defined('ABSPATH')) {
    exit;
}
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables are local to Admin::view(), which includes this file; they are not global.
?>
<div class="wrap zenck-wrap">
    <h1><?php esc_html_e('Sites &amp; domains', 'zen-cookie-keeper'); ?></h1>
    <p class="description"><?php esc_html_e('The cookie domain decides which hosts can read the restored cookies. By default it is your registrable domain (so apex and www share cookies). Override it here only if you serve several distinct domains from one install.', 'zen-cookie-keeper'); ?></p>

    <table class="widefat striped" style="max-width:640px">
        <tbody>
            <tr><th><?php esc_html_e('Current request host', 'zen-cookie-keeper'); ?></th><td><code><?php echo esc_html($host); ?></code></td></tr>
            <tr><th><?php esc_html_e('Resolved cookie domain', 'zen-cookie-keeper'); ?></th><td><code><?php echo esc_html($domain ? $domain : __('(host-only)', 'zen-cookie-keeper')); ?></code></td></tr>
            <tr><th><?php esc_html_e('Multisite', 'zen-cookie-keeper'); ?></th><td><?php echo $multisite ? esc_html__('Yes', 'zen-cookie-keeper') : esc_html__('No', 'zen-cookie-keeper'); ?></td></tr>
        </tbody>
    </table>

    <h2><?php esc_html_e('Per-host cookie-domain override', 'zen-cookie-keeper'); ?></h2>
    <form id="zenck-domain-form">
        <p>
            <label><?php esc_html_e('Host', 'zen-cookie-keeper'); ?> <input type="text" id="zenck-d-host" class="regular-text" value="<?php echo esc_attr($host); ?>"></label>
        </p>
        <p>
            <label><?php esc_html_e('Cookie domain (e.g. .example.com; leave blank to remove)', 'zen-cookie-keeper'); ?> <input type="text" id="zenck-d-domain" class="regular-text" value="<?php echo esc_attr(isset($overrides[$host]) ? $overrides[$host] : ''); ?>"></label>
        </p>
        <p><button type="submit" class="button button-primary"><?php esc_html_e('Save override', 'zen-cookie-keeper'); ?></button> <span class="zenck-result" id="zenck-domain-result"></span></p>
    </form>

    <?php if (!empty($overrides)) : ?>
        <h3><?php esc_html_e('Current overrides', 'zen-cookie-keeper'); ?></h3>
        <table class="widefat striped" style="max-width:640px">
            <thead><tr><th><?php esc_html_e('Host', 'zen-cookie-keeper'); ?></th><th><?php esc_html_e('Cookie domain', 'zen-cookie-keeper'); ?></th></tr></thead>
            <tbody>
            <?php foreach ($overrides as $h => $d) : ?>
                <tr><td><code><?php echo esc_html($h); ?></code></td><td><code><?php echo esc_html($d); ?></code></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
