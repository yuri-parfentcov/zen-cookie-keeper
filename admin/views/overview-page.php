<?php
/**
 * Overview / Status screen.
 *
 * @package Zen_Cookie_Keeper
 * @var array $cache   Cache status from the detector.
 * @var array $grouped Catalog grouped by platform.
 * @var bool  $enforce Whether consent enforcement is on.
 * @var array $counts  Row counts per table.
 */

if (!defined('ABSPATH')) {
    exit;
}
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables are local to Admin::view(), which includes this file; they are not global.
require_once ZEN_COOKIE_KEEPER_PATH . 'admin/views/_help-blocks.php';

$enabled_total = 0;
foreach ($grouped as $cookies) {
    foreach ($cookies as $spec) {
        if (!empty($spec['status'])) {
            $enabled_total++;
        }
    }
}
?>
<div class="wrap zenck-wrap">
    <h1><span class="dashicons dashicons-shield"></span> <?php esc_html_e('Zen Cookie Keeper', 'zen-cookie-keeper'); ?></h1>

    <?php zen_cookie_keeper_help('why'); ?>
    <?php zen_cookie_keeper_help('replaces'); ?>

    <div class="zenck-cards">
        <div class="zenck-card">
            <h2><?php esc_html_e('Active cookies', 'zen-cookie-keeper'); ?></h2>
            <p class="zenck-big"><?php echo (int) $enabled_total; ?></p>
            <p><?php esc_html_e('managed cookies enabled across platforms.', 'zen-cookie-keeper'); ?></p>
            <a href="<?php echo esc_url(admin_url('admin.php?page=zen-cookie-keeper-cookies')); ?>"><?php esc_html_e('Manage cookies →', 'zen-cookie-keeper'); ?></a>
        </div>

        <div class="zenck-card">
            <h2><?php esc_html_e('Consent enforcement', 'zen-cookie-keeper'); ?></h2>
            <p class="zenck-big <?php echo $enforce ? 'zenck-ok' : 'zenck-warn'; ?>">
                <?php echo $enforce ? esc_html__('ON', 'zen-cookie-keeper') : esc_html__('OFF', 'zen-cookie-keeper'); ?>
            </p>
            <p>
                <?php echo $enforce
                    ? esc_html__('Nothing is set without consent. Recommended.', 'zen-cookie-keeper')
                    : esc_html__('Cookies may be set without consent — compliance risk.', 'zen-cookie-keeper'); ?>
            </p>
        </div>

        <div class="zenck-card">
            <h2><?php esc_html_e('Cache mode', 'zen-cookie-keeper'); ?></h2>
            <p class="zenck-big"><?php echo esc_html($cache['label']); ?></p>
            <?php if (!empty($cache['blocks_render_write'])) : ?>
                <p class="zenck-note"><?php esc_html_e('A front cache is present, so cookies are written through the uncached sync endpoint (POST), not the page render. This is expected and handled automatically.', 'zen-cookie-keeper'); ?></p>
            <?php else : ?>
                <p><?php esc_html_e('No page cache detected.', 'zen-cookie-keeper'); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <h2><?php esc_html_e('Safari survival self-test', 'zen-cookie-keeper'); ?></h2>
    <p><?php esc_html_e('Confirms a server-set cookie round-trips through your cache layer and comes back on the next request.', 'zen-cookie-keeper'); ?></p>
    <p>
        <button class="button button-secondary" id="zenck-selftest"><?php esc_html_e('Run self-test', 'zen-cookie-keeper'); ?></button>
        <span id="zenck-selftest-result" class="zenck-result"></span>
    </p>

    <h2><?php esc_html_e('Storage', 'zen-cookie-keeper'); ?></h2>
    <table class="widefat striped zenck-counts">
        <tbody>
            <tr><td><?php esc_html_e('Anchors (identities)', 'zen-cookie-keeper'); ?></td><td><?php echo (int) $counts['anchors']; ?></td></tr>
            <tr><td><?php esc_html_e('Stored cookie values', 'zen-cookie-keeper'); ?></td><td><?php echo (int) $counts['values']; ?></td></tr>
            <tr><td><?php esc_html_e('Consent records', 'zen-cookie-keeper'); ?></td><td><?php echo (int) $counts['consent']; ?></td></tr>
        </tbody>
    </table>

    <div class="zenck-policy-note">
        <h3><?php esc_html_e('Privacy-policy line for the anchor cookie', 'zen-cookie-keeper'); ?></h3>
        <p><?php esc_html_e('The anchor is the only cookie this plugin creates that your CMP will not already describe. Add one line to your privacy policy:', 'zen-cookie-keeper'); ?></p>
        <code class="zenck-copy"><?php echo esc_html(sprintf(
            /* translators: %s: anchor cookie name */
            __('%s — strictly necessary, functional. First-party identity cookie used to keep your marketing and analytics cookies durable. Not used for tracking by itself.', 'zen-cookie-keeper'),
            ZEN_COOKIE_KEEPER_ANCHOR_NAME
        )); ?></code>
    </div>
</div>
