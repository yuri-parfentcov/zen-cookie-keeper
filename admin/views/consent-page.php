<?php
/**
 * Consent screen.
 *
 * @package Zen_Cookie_Keeper
 * @var bool   $enforce
 * @var string $version
 * @var int    $retention seconds
 * @var bool   $botgate
 * @var array  $denylist
 * @var array  $allowlist
 */

if (!defined('ABSPATH')) {
    exit;
}
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables are local to Admin::view(), which includes this file; they are not global.
require_once ZEN_COOKIE_KEEPER_PATH . 'admin/views/_help-blocks.php';
$retention_days = max(1, (int) round($retention / DAY_IN_SECONDS));
?>
<div class="wrap zenck-wrap">
    <h1><?php esc_html_e('Consent', 'zen-cookie-keeper'); ?></h1>

    <?php zen_cookie_keeper_help('consent'); ?>

    <h2><?php esc_html_e('Consent Mode mapping', 'zen-cookie-keeper'); ?></h2>
    <table class="widefat striped" style="max-width:640px">
        <thead><tr><th><?php esc_html_e('Consent Mode signal', 'zen-cookie-keeper'); ?></th><th><?php esc_html_e('Our bucket', 'zen-cookie-keeper'); ?></th></tr></thead>
        <tbody>
            <tr><td><code>analytics_storage</code></td><td><span class="zenck-badge zenck-badge-analytics">analytics</span></td></tr>
            <tr><td><code>ad_storage</code></td><td><span class="zenck-badge zenck-badge-advertising">advertising</span></td></tr>
        </tbody>
    </table>
    <p class="description"><?php esc_html_e('Any cookie banner that supports Google Consent Mode (Cookiebot, Complianz, Borlabs and others) feeds these signals. The JS companion delivers the current state to the server on each request.', 'zen-cookie-keeper'); ?></p>

    <form id="zenck-consent-form">
        <table class="form-table">
            <tr>
                <th><?php esc_html_e('Enforce consent', 'zen-cookie-keeper'); ?></th>
                <td>
                    <label class="zenck-switch">
                        <input type="checkbox" id="zenck-enforce" <?php checked($enforce); ?>>
                        <span class="zenck-slider"></span>
                    </label>
                    <p class="description"><?php esc_html_e('On by default. The plugin sets nothing until the matching bucket is granted. Turning this off is a compliance risk.', 'zen-cookie-keeper'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="zenck-version"><?php esc_html_e('Consent text version', 'zen-cookie-keeper'); ?></label></th>
                <td>
                    <input type="text" id="zenck-version" class="regular-text" value="<?php echo esc_attr($version); ?>" placeholder="2026-06-01">
                    <p class="description"><?php esc_html_e('Recorded with each consent decision as the legal basis for restoration.', 'zen-cookie-keeper'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="zenck-retention"><?php esc_html_e('Record retention (days)', 'zen-cookie-keeper'); ?></label></th>
                <td><input type="number" id="zenck-retention" class="small-text" min="1" value="<?php echo esc_attr($retention_days); ?>"></td>
            </tr>
        </table>
        <p><button type="submit" class="button button-primary"><?php esc_html_e('Save consent settings', 'zen-cookie-keeper'); ?></button> <span class="zenck-result" id="zenck-consent-result"></span></p>
    </form>

    <h2><?php esc_html_e('Withdrawal &amp; erasure', 'zen-cookie-keeper'); ?></h2>
    <p class="description"><?php esc_html_e('Withdrawal happens automatically when your banner flips a bucket to denied. For an explicit erasure request (GDPR Art. 17), paste the visitor\'s anchor token to remove everything stored under it.', 'zen-cookie-keeper'); ?></p>
    <p>
        <input type="text" id="zenck-erasure-token" class="regular-text" placeholder="<?php esc_attr_e('anchor token', 'zen-cookie-keeper'); ?>">
        <button class="button button-secondary" id="zenck-erasure-btn"><?php esc_html_e('Erase records', 'zen-cookie-keeper'); ?></button>
        <span class="zenck-result" id="zenck-erasure-result"></span>
    </p>

    <h2><?php esc_html_e('Bot-gating (optional)', 'zen-cookie-keeper'); ?></h2>
    <p class="description"><?php esc_html_e('When enabled, durable restoration is withheld from clients flagged as bots, so analytics is not inflated by durable bot identities. Off by default. Uses an inbound JA4 fingerprint header if your infrastructure injects one, plus basic heuristics.', 'zen-cookie-keeper'); ?></p>
    <form id="zenck-botgate-form">
        <table class="form-table">
            <tr>
                <th><?php esc_html_e('Enable bot-gating', 'zen-cookie-keeper'); ?></th>
                <td>
                    <label class="zenck-switch">
                        <input type="checkbox" id="zenck-botgate" <?php checked($botgate); ?>>
                        <span class="zenck-slider"></span>
                    </label>
                </td>
            </tr>
            <tr>
                <th><label for="zenck-deny"><?php esc_html_e('JA4 deny list', 'zen-cookie-keeper'); ?></label></th>
                <td><textarea id="zenck-deny" class="large-text code" rows="4" placeholder="<?php esc_attr_e('one fingerprint per line', 'zen-cookie-keeper'); ?>"><?php echo esc_textarea(implode("\n", $denylist)); ?></textarea></td>
            </tr>
            <tr>
                <th><label for="zenck-allow"><?php esc_html_e('JA4 allow list', 'zen-cookie-keeper'); ?></label></th>
                <td><textarea id="zenck-allow" class="large-text code" rows="4"><?php echo esc_textarea(implode("\n", $allowlist)); ?></textarea></td>
            </tr>
        </table>
        <p><button type="submit" class="button button-primary"><?php esc_html_e('Save bot-gating', 'zen-cookie-keeper'); ?></button> <span class="zenck-result" id="zenck-botgate-result"></span></p>
    </form>
</div>
