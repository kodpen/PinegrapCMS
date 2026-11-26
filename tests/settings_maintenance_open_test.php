<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings > Updates and maintenance, the rows drawn open: one column,
 * group labels, pg-switch rows with one line of help, the update channel and
 * the sales watch's sensitivity as pg-seg, the alert thresholds two a row,
 * Storage's move tool as the group Move files, and the two S3 sets drawn
 * alike but kept apart; the names the save reads stay.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/maintenance_settings_open.php');

// The pane's source without the Scheduled jobs card, drawn by its own step.
function settings_maintenance_open_test_pane()
{
	$pane = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/maintenance.php');
	$cron = strpos($pane, '// ── Scheduled jobs ──');
	$backup = strpos($pane, '// ── Backups ──');

	return (($cron !== false) && ($backup > $cron)) ? substr($pane, 0, $cron) . substr($pane, $backup) : $pane;
}

function test_settings_maintenance_open_switch()
{
	$on = pg_maintenance_settings_switch('update_backup', true, 'Back up before updating', 'The three newest are kept.');

	pg_assert_contains('<input type="checkbox" class="pg-switch" value="1" checked="checked" id="update_backup" name="update_backup" aria-describedby="update_backup_help"/>', $on, 'a pg-switch posting 1 under its name');
	pg_assert_contains('<label class="pg-text-2" for="update_backup">Back up before updating</label>', $on, 'its label');
	pg_assert_contains('<p class="pg-field-help pg-text-meta fg-secondary mb-0" id="update_backup_help">The three newest are kept.</p>', $on, 'one line of help tied to it');

	$off = pg_maintenance_settings_switch('ops_alert_push', false, 'A & B', '', ' data-x="1"');

	pg_assert_false(strpos($off, 'checked') !== false, 'off');
	pg_assert_false(strpos($off, 'aria-describedby') !== false, 'no help, no tie');
	pg_assert_contains('A &amp; B', $off, 'the label is escaped');
	pg_assert_contains(' data-x="1"/>', $off, 'more attributes');
}

function test_settings_maintenance_open_seg()
{
	$seg = pg_maintenance_settings_seg('Update channel', 'software_update_channel', array('stable' => 'Stable', 'beta' => 'Beta'), 'beta', 'Beta gets new versions earlier.');

	pg_assert_contains('<legend class="form-label pg-field-label">Update channel</legend>', $seg, 'the legend');
	pg_assert_contains('<input value="stable" class="visually-hidden" type="radio" id="software_update_channel_stable" name="software_update_channel"/>', $seg, 'a radio a value, its id the name and the value');
	pg_assert_contains('<input value="beta" class="visually-hidden" type="radio" id="software_update_channel_beta" name="software_update_channel" checked="checked"/>', $seg, 'the current one checked');
	pg_assert_contains('aria-describedby="software_update_channel_help"', $seg, 'the fieldset tied to its line');
	pg_assert_contains('id="software_update_channel_help">Beta gets new versions earlier.</p>', $seg, 'one line of help');

	$plain = pg_maintenance_settings_seg('Sensitivity', 'sales_watch_level', array('low' => 'Low'), 'none');
	pg_assert_false(strpos($plain, 'checked') !== false, 'an unknown current value checks none');
	pg_assert_false(strpos($plain, '_help') !== false, 'no help, no line');
}

function test_settings_maintenance_open_group()
{
	pg_assert_same('
                    <div class="col-12"><h3 class="pg-group-label mb-0">Move files</h3></div>', pg_maintenance_settings_group('Move files'), 'a group label across the column');
}

function test_settings_maintenance_open_pane_uses_switch_and_seg()
{
	$pane = settings_maintenance_open_test_pane();

	pg_assert_contains("require_once(PG_FUNCTIONS_DIR . '/includes/fn/maintenance_settings_open.php');", $pane, 'the module is loaded');
	pg_assert_same(false, strpos($pane, '<div class="switch">'), 'no old switch is left');
	pg_assert_same(false, strpos($pane, '<select name="software_update_channel"'), 'the channel is no select');
	pg_assert_same(false, strpos($pane, '<select name="sales_watch_level"'), 'nor the sensitivity');
	pg_assert_contains("pg_maintenance_settings_seg(lang('Update channel'), 'software_update_channel',", $pane, 'the channel as pg-seg');
	pg_assert_contains("pg_maintenance_settings_seg(lang('Sensitivity'), 'sales_watch_level',", $pane, 'the sensitivity as pg-seg');

	foreach (array('update_backup', 'maintenance_mode', 'ops_alerts', 'ops_alert_email', 'ops_alert_push', 'sales_watch_close_products', 'object_storage_enabled', 'object_storage_path_style', 'backup_s3_path_style', 'backup_ftp_tls') as $name) {
		pg_assert_contains("pg_maintenance_settings_switch('" . $name . "',", $pane, $name . ' is a pg-switch');
	}
}

function test_settings_maintenance_open_groups_and_thresholds()
{
	$pane = settings_maintenance_open_test_pane();

	pg_assert_contains("pg_maintenance_settings_group(lang('Move files'))", $pane, 'Storage\'s move tool is a group of its own');

	foreach (array('ops_alert_error_rate', 'ops_alert_disk_percent', 'ops_alert_mail_failures', 'ops_alert_cert_days') as $name) {
		pg_assert_true(preg_match('/<div class="col-12 md:col-6"><div>\s*<label for="' . $name . '"/', $pane) === 1, $name . ' two a row from 768 px');
	}

	pg_assert_same(false, strpos($pane, 'popover'), 'no popover');
}

// The backup's S3 destination and the object store keep their own settings,
// but are drawn alike: the same labels in the same order.
function test_settings_maintenance_open_s3_sets_drawn_alike()
{
	$pane = settings_maintenance_open_test_pane();
	$labels = array();

	foreach (array('backup_s3_' => array('endpoint', 'region', 'bucket', 'prefix', 'access_key', 'secret_key'), 'object_storage_' => array('endpoint', 'region', 'bucket', 'prefix', 'key_id', 'secret')) as $prefix => $fields) {
		$set = array();

		foreach ($fields as $field) {
			pg_assert_true(preg_match('/<label for="' . $prefix . $field . '" class="form-label">\' \. lang\(\'([^\']+)\'\)/', $pane, $match) === 1, $prefix . $field . ' has its label');
			$set[] = $match[1];
		}

		$labels[] = $set;
	}

	pg_assert_same($labels[0], $labels[1], 'the same labels in the same order');
	pg_assert_contains('name="backup_s3_secret_key" id="backup_s3_secret_key" class="form-control" value=""', $pane, 'the backup secret drawn empty');
	pg_assert_contains('name="object_storage_secret" id="object_storage_secret" class="form-control" value=""', $pane, 'the store secret drawn empty');
}
