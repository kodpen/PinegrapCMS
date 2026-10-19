<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Schema step for version 2026.11.0. Loaded by includes/migrations/runner.php when an
// installation is behind this version; never requested on its own. The function
// must be safe to run twice: every ADD / CREATE asks first and skips what exists.
if (!defined('INSTALL_OR_UPDATE')) {
	exit;
}

// 2026.11.0 - the release that follows 2026.4.10, the first calendar-versioned
// number (docs/memory/release/version-numbering.md).
//
// One entry point, one step per subsystem, called in the order the work was
// done. The numbers in the comments are labels only (general work from 11.0.1,
// ERP 11.0.58-11.0.69 and from 11.0.90, API 11.0.70-11.0.79, the workspace
// 11.0.80-11.0.89 and 11.0.110-11.0.119); the order of the calls is what runs.
function upgrade_to_2026_11_0() {

	upgrade_2026_11_0_ops_alerts();             // 11.0.20
	upgrade_2026_11_0_update_backup();          // 11.0.21

}

// Operations alerts (2026.11.0, 11.0.20; includes/fn/ops_alerts.php, the
// Alerts card under Site Settings, General).
//
// A failed or missing backup, a scheduled task that stopped, changed software
// files, a burst of PHP errors, low disk space, failing mail or webhooks and a
// certificate about to run out are told on the panel bell, by e-mail and on
// subscribed devices. On after the upgrade, both channels included, to the
// site's own address while no recipient is typed: the point is that nobody has
// to have thought of it. The four thresholds are per check, 0 switching that
// check off. The alerts themselves are notification rows (action 'ops'), so
// there is no table.
function upgrade_2026_11_0_ops_alerts() {

	install_add_column('config', 'ops_alerts', "TINYINT(1) UNSIGNED NOT NULL DEFAULT 1");

	install_add_column('config', 'ops_alert_email', "TINYINT(1) UNSIGNED NOT NULL DEFAULT 1");

	install_add_column('config', 'ops_alert_push', "TINYINT(1) UNSIGNED NOT NULL DEFAULT 1");

	install_add_column('config', 'ops_alert_recipients', "TEXT NULL");

	install_add_column('config', 'ops_alert_error_rate', "INT UNSIGNED NOT NULL DEFAULT 50");

	install_add_column('config', 'ops_alert_disk_percent', "TINYINT UNSIGNED NOT NULL DEFAULT 10");

	install_add_column('config', 'ops_alert_mail_failures', "INT UNSIGNED NOT NULL DEFAULT 10");

	install_add_column('config', 'ops_alert_cert_days', "TINYINT UNSIGNED NOT NULL DEFAULT 14");

	install_note('Operations alerts: a failed or missing backup, a scheduled task that stopped, changed software files, a burst of PHP errors, low disk space, failing mail or webhooks and an expiring certificate are now told on the panel bell, by e-mail to the site address and on subscribed devices. They are set under Site Settings, General, Alerts.');

}

// The backup an update takes before it replaces the files (2026.11.0, 11.0.21;
// pg_update_backup_create() in includes/fn/update.php, the switch on the
// Update Channel card).
//
// On by default: the files the package replaces and the database go into
// data/backups/pre-update-<version>-<time>.zip, and Repair Software rolls the
// files back from it. Off is for a site too large to back up within one
// request; then the update replaces the files without a backup, as before.
function upgrade_2026_11_0_update_backup() {

	install_add_column('config', 'update_backup', "TINYINT(1) UNSIGNED NOT NULL DEFAULT 1");

	install_note('Software update: before an update replaces the files, the files it replaces and the database are backed up to data/backups/pre-update-….zip, and Repair Software can roll the files back. It can be switched off under Site Settings, General, Update Channel.');

}
