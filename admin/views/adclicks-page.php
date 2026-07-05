<?php
/**
 * Ad Clicks stats screen.
 *
 * Totals + per-platform breakdown, a daily chart, a filterable list and a CSV
 * export of ad-originated sessions (one row per anchor + platform + click id).
 *
 * @package Zen_Cookie_Keeper
 * @var string $from      Start date (Y-m-d).
 * @var string $to        End date (Y-m-d).
 * @var string $platform  Selected platform filter ('' = all).
 * @var array  $platforms Ad platforms for the dropdown.
 * @var array  $totals    ['total'=>int, 'by_platform'=>array<string,int>].
 * @var array  $series    Rows of ['day'=>..,'platform'=>..,'n'=>..].
 * @var array  $rows      Recent click rows (raw store rows).
 * @var int    $count     Total rows matching the filter.
 * @var int    $retention Retention window in days.
 */

if (!defined('ABSPATH')) {
    exit;
}
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables are local to Admin::view(), which includes this file; they are not global.

$zenck_export_url = add_query_arg(
    array(
        'action'   => 'zen_cookie_keeper_export_clicks',
        '_wpnonce' => wp_create_nonce('zen_cookie_keeper_export'),
        'from'     => $from,
        'to'       => $to,
        'platform' => $platform,
    ),
    admin_url('admin-post.php')
);
?>
<div class="wrap zenck-wrap zenck-adclicks">
    <h1><span class="dashicons dashicons-chart-bar"></span> <?php esc_html_e('Ad Clicks', 'zen-cookie-keeper'); ?></h1>

    <p class="zenck-lead">
        <?php esc_html_e('Every visit that lands with an ad platform click id (Google Ads gclid/wbraid/gbraid, Microsoft Ads msclkid, Meta fbclid, TikTok ttclid, LinkedIn) is recorded once per session. Advertising consent is required, matching the ad-cookie policy.', 'zen-cookie-keeper'); ?>
    </p>

    <div class="zenck-cards" id="zenck-adclicks-cards">
        <div class="zenck-card">
            <h2><?php esc_html_e('Total ad clicks', 'zen-cookie-keeper'); ?></h2>
            <p class="zenck-big" data-zenck-total><?php echo (int) $totals['total']; ?></p>
            <p class="zenck-note" data-zenck-range>
                <?php echo esc_html(sprintf(
                    /* translators: 1: start date, 2: end date */
                    __('%1$s to %2$s', 'zen-cookie-keeper'),
                    $from,
                    $to
                )); ?>
            </p>
        </div>
        <?php foreach ($platforms as $zenck_pf) : ?>
            <div class="zenck-card zenck-card-platform" data-zenck-platform-card="<?php echo esc_attr($zenck_pf); ?>">
                <h2><?php echo esc_html($zenck_pf); ?></h2>
                <p class="zenck-big" data-zenck-platform-count="<?php echo esc_attr($zenck_pf); ?>">
                    <?php echo isset($totals['by_platform'][$zenck_pf]) ? (int) $totals['by_platform'][$zenck_pf] : 0; ?>
                </p>
                <p class="zenck-note"><?php esc_html_e('sessions', 'zen-cookie-keeper'); ?></p>
            </div>
        <?php endforeach; ?>
    </div>

    <form class="zenck-adclicks-filter" id="zenck-adclicks-filter">
        <label>
            <span><?php esc_html_e('From', 'zen-cookie-keeper'); ?></span>
            <input type="date" name="from" value="<?php echo esc_attr($from); ?>">
        </label>
        <label>
            <span><?php esc_html_e('To', 'zen-cookie-keeper'); ?></span>
            <input type="date" name="to" value="<?php echo esc_attr($to); ?>">
        </label>
        <label>
            <span><?php esc_html_e('Platform', 'zen-cookie-keeper'); ?></span>
            <select name="platform">
                <option value=""><?php esc_html_e('All platforms', 'zen-cookie-keeper'); ?></option>
                <?php foreach ($platforms as $zenck_pf) : ?>
                    <option value="<?php echo esc_attr($zenck_pf); ?>" <?php selected($platform, $zenck_pf); ?>>
                        <?php echo esc_html($zenck_pf); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit" class="button button-primary"><?php esc_html_e('Apply', 'zen-cookie-keeper'); ?></button>
        <span id="zenck-adclicks-status" class="zenck-result"></span>

        <a href="<?php echo esc_url($zenck_export_url); ?>" id="zenck-adclicks-export" class="button button-secondary">
            <?php esc_html_e('Export CSV', 'zen-cookie-keeper'); ?>
        </a>
    </form>

    <div class="zenck-chart-wrap">
        <canvas id="zenck-adclicks-chart" height="260" role="img"
            aria-label="<?php esc_attr_e('Daily ad clicks by platform', 'zen-cookie-keeper'); ?>"></canvas>
        <div id="zenck-adclicks-legend" class="zenck-chart-legend"></div>
    </div>

    <h2><?php esc_html_e('Recorded clicks', 'zen-cookie-keeper'); ?></h2>
    <p class="zenck-note" id="zenck-adclicks-count">
        <?php echo esc_html(sprintf(
            /* translators: 1: rows shown, 2: total rows */
            __('Showing %1$d of %2$d', 'zen-cookie-keeper'),
            count($rows),
            (int) $count
        )); ?>
    </p>
    <table class="widefat striped zenck-adclicks-table" id="zenck-adclicks-table">
        <thead>
            <tr>
                <th><?php esc_html_e('When (UTC)', 'zen-cookie-keeper'); ?></th>
                <th><?php esc_html_e('Platform', 'zen-cookie-keeper'); ?></th>
                <th><?php esc_html_e('Campaign', 'zen-cookie-keeper'); ?></th>
                <th><?php esc_html_e('Source / Medium', 'zen-cookie-keeper'); ?></th>
                <th><?php esc_html_e('Landing', 'zen-cookie-keeper'); ?></th>
                <th><?php esc_html_e('Referrer', 'zen-cookie-keeper'); ?></th>
                <th><?php esc_html_e('Click id', 'zen-cookie-keeper'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rows)) : ?>
                <tr class="zenck-empty-row"><td colspan="7"><?php esc_html_e('No ad clicks recorded in this window yet.', 'zen-cookie-keeper'); ?></td></tr>
            <?php else : ?>
                <?php foreach ($rows as $zenck_row) : ?>
                    <tr>
                        <td><?php echo esc_html($zenck_row['created_at']); ?></td>
                        <td><?php echo esc_html($zenck_row['platform']); ?></td>
                        <td><?php echo esc_html($zenck_row['utm_campaign']); ?></td>
                        <td><?php echo esc_html(trim($zenck_row['utm_source'] . ' / ' . $zenck_row['utm_medium'], ' /')); ?></td>
                        <td class="zenck-ellip"><?php echo esc_html($zenck_row['landing_path']); ?></td>
                        <td><?php echo esc_html($zenck_row['referrer_host']); ?></td>
                        <td class="zenck-ellip"><?php echo esc_html($zenck_row['click_id']); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <form class="zenck-adclicks-retention" id="zenck-adclicks-retention">
        <h2><?php esc_html_e('Retention', 'zen-cookie-keeper'); ?></h2>
        <p class="zenck-note"><?php esc_html_e('Recorded clicks older than this are removed by the daily cleanup (storage limitation).', 'zen-cookie-keeper'); ?></p>
        <label>
            <span><?php esc_html_e('Keep for (days)', 'zen-cookie-keeper'); ?></span>
            <input type="number" name="retention_days" min="1" step="1" value="<?php echo (int) $retention; ?>">
        </label>
        <button type="submit" class="button button-secondary"><?php esc_html_e('Save', 'zen-cookie-keeper'); ?></button>
        <span id="zenck-adclicks-retention-result" class="zenck-result"></span>
    </form>

    <script type="application/json" id="zenck-adclicks-data">
        <?php echo wp_json_encode(array(
            'from'     => $from,
            'to'       => $to,
            'platform' => $platform,
            'totals'   => $totals,
            'series'   => $series,
        )); ?>
    </script>
</div>
