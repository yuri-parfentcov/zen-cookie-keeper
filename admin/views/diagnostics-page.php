<?php
/**
 * Diagnostics screen.
 *
 * @package Zen_Cookie_Keeper
 * @var array $snapshot
 */

if (!defined('ABSPATH')) {
    exit;
}
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables are local to Admin::view(), which includes this file; they are not global.
?>
<div class="wrap zenck-wrap">
    <h1><?php esc_html_e('Diagnostics', 'zen-cookie-keeper'); ?></h1>
    <p><button class="button" id="zenck-refresh-diag"><?php esc_html_e('Refresh', 'zen-cookie-keeper'); ?></button></p>

    <table class="widefat striped" style="max-width:760px">
        <tbody>
            <tr>
                <th><?php esc_html_e('Front cache', 'zen-cookie-keeper'); ?></th>
                <td><?php echo esc_html($snapshot['cache']['label']); ?>
                    <?php if (!empty($snapshot['cache']['blocks_render_write'])) : ?>
                        — <em><?php esc_html_e('writing via the uncached sync endpoint (correct).', 'zen-cookie-keeper'); ?></em>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('Proxy / CDN in front', 'zen-cookie-keeper'); ?></th>
                <td><?php echo $snapshot['behind_proxy'] ? esc_html__('Yes — real client IP read from forwarded headers.', 'zen-cookie-keeper') : esc_html__('No', 'zen-cookie-keeper'); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Cookie domain', 'zen-cookie-keeper'); ?></th>
                <td><code><?php echo esc_html($snapshot['cookie_domain'] ? $snapshot['cookie_domain'] : __('(host-only)', 'zen-cookie-keeper')); ?></code></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Collision detection', 'zen-cookie-keeper'); ?></th>
                <td>
                    <?php if (empty($snapshot['collisions'])) : ?>
                        <?php esc_html_e('No conflicting plugins detected.', 'zen-cookie-keeper'); ?>
                    <?php else : ?>
                        <span class="zenck-warn"><?php esc_html_e('Warning — these also set tracking cookies and may conflict:', 'zen-cookie-keeper'); ?></span>
                        <ul><?php foreach ($snapshot['collisions'] as $label) : ?><li><?php echo esc_html($label); ?></li><?php endforeach; ?></ul>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('JS-rewrite of managed cookies', 'zen-cookie-keeper'); ?></th>
                <td><?php echo $snapshot['js_rewrite'] ? esc_html__('Suspected — a managed cookie may be rewritten from JavaScript, which drops it under the cap. Check your tag setup.', 'zen-cookie-keeper') : esc_html__('None detected.', 'zen-cookie-keeper'); ?></td>
            </tr>
        </tbody>
    </table>

    <h2><?php esc_html_e('Recent operations', 'zen-cookie-keeper'); ?></h2>
    <table class="widefat striped" id="zenck-ops" style="max-width:760px">
        <thead><tr>
            <th><?php esc_html_e('When', 'zen-cookie-keeper'); ?></th>
            <th><?php esc_html_e('Op', 'zen-cookie-keeper'); ?></th>
            <th><?php esc_html_e('Cookie', 'zen-cookie-keeper'); ?></th>
            <th><?php esc_html_e('Result', 'zen-cookie-keeper'); ?></th>
        </tr></thead>
        <tbody>
        <?php if (empty($snapshot['recent_ops'])) : ?>
            <tr><td colspan="4"><?php esc_html_e('No operations yet.', 'zen-cookie-keeper'); ?></td></tr>
        <?php else : foreach ($snapshot['recent_ops'] as $op) : ?>
            <tr>
                <td><?php echo esc_html($op['created_at']); ?></td>
                <td><?php echo esc_html($op['op_type']); ?></td>
                <td><code><?php echo esc_html($op['cookie_name']); ?></code></td>
                <td><?php echo esc_html($op['result']); ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
