<?php
/**
 * Restore History report screen.
 *
 * Totals + per-reason breakdown, a daily per-cookie chart, a filterable list
 * and a CSV export of restore events (one row per server re-emission of a
 * stored cookie value). This is the report that proves ITP/ETP survival:
 * 'missing' = the browser had lost the cookie and the server brought it back,
 * 'divergent' = the browser held a different value and it was corrected.
 *
 * @package Zen_Cookie_Keeper
 * @var string $from      Start date (Y-m-d).
 * @var string $to        End date (Y-m-d).
 * @var string $cookie    Selected cookie filter ('' = all).
 * @var array  $cookies   Catalog cookie names for the dropdown.
 * @var array  $totals    ['total'=>int,'by_cookie'=>array,'by_reason'=>array,'avg_age'=>int].
 * @var array  $series    Rows of ['day'=>..,'cookie_name'=>..,'n'=>..].
 * @var array  $rows      Recent restore rows (raw store rows).
 * @var int    $count     Total rows matching the filter.
 * @var int    $retention Retention window in days.
 */

if (!defined('ABSPATH')) {
    exit;
}
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables are local to Admin::view(), which includes this file; they are not global.

$zenck_export_url = add_query_arg(
    array(
        'action'   => 'zen_cookie_keeper_export_restores',
        '_wpnonce' => wp_create_nonce('zen_cookie_keeper_export'),
        'from'     => $from,
        'to'       => $to,
        'cookie'   => $cookie,
    ),
    admin_url('admin-post.php')
);

$zenck_missing   = isset($totals['by_reason']['missing']) ? (int) $totals['by_reason']['missing'] : 0;
$zenck_divergent = isset($totals['by_reason']['divergent']) ? (int) $totals['by_reason']['divergent'] : 0;
$zenck_avg_days  = (int) round(((int) $totals['avg_age']) / DAY_IN_SECONDS);
?>
<div class="wrap zenck-wrap zenck-restores">
    <h1><span class="dashicons dashicons-backup"></span> <?php esc_html_e('Restore History', 'zen-cookie-keeper'); ?></h1>

    <p class="zenck-lead">
        <?php esc_html_e('Every time the server re-emits a stored cookie it is recorded here. "Missing" means the browser had lost the cookie (ITP/ETP capping or expiry) and the durable copy brought it back; "divergent" means the browser held a different value and it was corrected. The identity age shows how old the recovered value was — proof of attribution beyond the 7-day JavaScript cap.', 'zen-cookie-keeper'); ?>
    </p>

    <div class="zenck-cards" id="zenck-restores-cards">
        <div class="zenck-card">
            <h2><?php esc_html_e('Total restores', 'zen-cookie-keeper'); ?></h2>
            <p class="zenck-big" data-zenck-r-total><?php echo (int) $totals['total']; ?></p>
            <p class="zenck-note" data-zenck-r-range>
                <?php echo esc_html(sprintf(
                    /* translators: 1: start date, 2: end date */
                    __('%1$s to %2$s', 'zen-cookie-keeper'),
                    $from,
                    $to
                )); ?>
            </p>
        </div>
        <div class="zenck-card">
            <h2><?php esc_html_e('Missing — recovered', 'zen-cookie-keeper'); ?></h2>
            <p class="zenck-big" data-zenck-r-missing><?php echo (int) $zenck_missing; ?></p>
            <p class="zenck-note"><?php esc_html_e('cookie was gone, brought back', 'zen-cookie-keeper'); ?></p>
        </div>
        <div class="zenck-card">
            <h2><?php esc_html_e('Divergent — corrected', 'zen-cookie-keeper'); ?></h2>
            <p class="zenck-big" data-zenck-r-divergent><?php echo (int) $zenck_divergent; ?></p>
            <p class="zenck-note"><?php esc_html_e('value differed, corrected', 'zen-cookie-keeper'); ?></p>
        </div>
        <div class="zenck-card">
            <h2><?php esc_html_e('Avg. identity age', 'zen-cookie-keeper'); ?></h2>
            <p class="zenck-big" data-zenck-r-avgage><?php echo (int) $zenck_avg_days; ?></p>
            <p class="zenck-note"><?php esc_html_e('days old when recovered', 'zen-cookie-keeper'); ?></p>
        </div>
    </div>

    <form class="zenck-adclicks-filter zenck-restores-filter" id="zenck-restores-filter">
        <label>
            <span><?php esc_html_e('From', 'zen-cookie-keeper'); ?></span>
            <input type="date" name="from" value="<?php echo esc_attr($from); ?>">
        </label>
        <label>
            <span><?php esc_html_e('To', 'zen-cookie-keeper'); ?></span>
            <input type="date" name="to" value="<?php echo esc_attr($to); ?>">
        </label>
        <label>
            <span><?php esc_html_e('Cookie', 'zen-cookie-keeper'); ?></span>
            <select name="cookie">
                <option value=""><?php esc_html_e('All cookies', 'zen-cookie-keeper'); ?></option>
                <?php foreach ($cookies as $zenck_ck) : ?>
                    <option value="<?php echo esc_attr($zenck_ck); ?>" <?php selected($cookie, $zenck_ck); ?>>
                        <?php echo esc_html($zenck_ck); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit" class="button button-primary"><?php esc_html_e('Apply', 'zen-cookie-keeper'); ?></button>
        <span id="zenck-restores-status" class="zenck-result"></span>

        <a href="<?php echo esc_url($zenck_export_url); ?>" id="zenck-restores-export" class="button button-secondary">
            <?php esc_html_e('Export CSV', 'zen-cookie-keeper'); ?>
        </a>
    </form>

    <div class="zenck-chart-wrap">
        <canvas id="zenck-restores-chart" height="260" role="img"
            aria-label="<?php esc_attr_e('Daily restores by cookie', 'zen-cookie-keeper'); ?>"></canvas>
        <div id="zenck-restores-legend" class="zenck-chart-legend"></div>
    </div>

    <h2><?php esc_html_e('Recorded restores', 'zen-cookie-keeper'); ?></h2>
    <p class="zenck-note" id="zenck-restores-count">
        <?php echo esc_html(sprintf(
            /* translators: 1: rows shown, 2: total rows */
            __('Showing %1$d of %2$d', 'zen-cookie-keeper'),
            count($rows),
            (int) $count
        )); ?>
    </p>
    <table class="widefat striped zenck-adclicks-table zenck-restores-table" id="zenck-restores-table">
        <thead>
            <tr>
                <th><?php esc_html_e('When (UTC)', 'zen-cookie-keeper'); ?></th>
                <th><?php esc_html_e('Cookie', 'zen-cookie-keeper'); ?></th>
                <th><?php esc_html_e('Platform', 'zen-cookie-keeper'); ?></th>
                <th><?php esc_html_e('Bucket', 'zen-cookie-keeper'); ?></th>
                <th><?php esc_html_e('Reason', 'zen-cookie-keeper'); ?></th>
                <th><?php esc_html_e('Identity age (days)', 'zen-cookie-keeper'); ?></th>
                <th><?php esc_html_e('Lifetime left (days)', 'zen-cookie-keeper'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rows)) : ?>
                <tr class="zenck-empty-row"><td colspan="7"><?php esc_html_e('No restores recorded in this window yet.', 'zen-cookie-keeper'); ?></td></tr>
            <?php else : ?>
                <?php foreach ($rows as $zenck_row) : ?>
                    <tr>
                        <td><?php echo esc_html($zenck_row['created_at']); ?></td>
                        <td><?php echo esc_html($zenck_row['cookie_name']); ?></td>
                        <td><?php echo esc_html($zenck_row['platform']); ?></td>
                        <td><?php echo esc_html($zenck_row['bucket']); ?></td>
                        <td><?php echo esc_html($zenck_row['reason']); ?></td>
                        <td><?php echo (int) round(((int) $zenck_row['value_age']) / DAY_IN_SECONDS); ?></td>
                        <td><?php echo (int) round(((int) $zenck_row['remaining_lifetime']) / DAY_IN_SECONDS); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <form class="zenck-adclicks-retention zenck-restores-retention" id="zenck-restores-retention">
        <h2><?php esc_html_e('Retention', 'zen-cookie-keeper'); ?></h2>
        <p class="zenck-note"><?php esc_html_e('Restore events older than this are removed by the daily cleanup (storage limitation).', 'zen-cookie-keeper'); ?></p>
        <label>
            <span><?php esc_html_e('Keep for (days)', 'zen-cookie-keeper'); ?></span>
            <input type="number" name="retention_days" min="1" step="1" value="<?php echo (int) $retention; ?>">
        </label>
        <button type="submit" class="button button-secondary"><?php esc_html_e('Save', 'zen-cookie-keeper'); ?></button>
        <span id="zenck-restores-retention-result" class="zenck-result"></span>
    </form>

    <script type="application/json" id="zenck-restores-data">
        <?php echo wp_json_encode(array(
            'from'   => $from,
            'to'     => $to,
            'cookie' => $cookie,
            'totals' => $totals,
            'series' => $series,
        )); ?>
    </script>
</div>
