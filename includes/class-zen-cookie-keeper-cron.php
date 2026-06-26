<?php
/**
 * Cron / storage-limitation cleanup for Zen Cookie Keeper.
 *
 * A daily job purges expired anchors, stored values and consent records, and
 * trims the ops/audit logs. Retention honours the declared lifetimes (storage
 * limitation, GDPR Art. 5(1)(e)).
 *
 * @package Zen_Cookie_Keeper
 */

if (!defined('ABSPATH')) {
    exit;
}

class Zen_Cookie_Keeper_Cron {

    public function init_hooks() {
        add_action(Zen_Cookie_Keeper_Activator::CRON_HOOK, array($this, 'run'));
    }

    public function run() {
        $ops_days   = (int) get_option('zen_cookie_keeper_ops_retention_days', 7);
        $audit_days = (int) get_option('zen_cookie_keeper_audit_retention_days', 365);

        $deleted = Zen_Cookie_Keeper_Store::instance()->purge_expired($ops_days, $audit_days);
        Zen_Cookie_Keeper_Store::instance()->insert_audit(null, 'cron_purge', $deleted);
    }
}
