<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Backups card of Site Settings sits in Updates and maintenance: it left
 * General with the fields it posts, the columns its save writes, the remote
 * test and the cloud account return, and the old links to it land there.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_SETTINGS_MENU')) {
	define('PG_SETTINGS_MENU', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/settings/registry.php');

function settings_backup_card_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

// One category's entry in pg_settings_categories(), cut out of the source.
function settings_backup_card_test_category($key)
{
	$source = settings_backup_card_test_source('includes/settings/registry.php');
	$start = strpos($source, "\n        '" . $key . "' => array(");

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\n        ),\n", $start);

	return substr($source, $start, $end - $start);
}

function settings_backup_card_test_names($source)
{
	preg_match_all('/\bname="([a-z0-9_]+)(?:\[\])?"/', $source, $match);
	$names = array_values(array_unique($match[1]));
	sort($names);

	return $names;
}

function test_settings_backup_card_is_listed_in_maintenance()
{
	$maintenance = settings_backup_card_test_category('maintenance');
	$general = settings_backup_card_test_category('general');

	$cron = strpos($maintenance, "'pgset-cron'");
	$backup = strpos($maintenance, "'pgset-backup'");
	$alerts = strpos($maintenance, "'pgset-alerts'");

	pg_assert_true($backup !== false, 'Backups is a section of Updates and maintenance');
	pg_assert_true(($cron < $backup) && ($backup < $alerts), 'between Cron Jobs and Alerts');
	pg_assert_contains("'pgset-backup'      => pg_settings_keywords(", $maintenance, 'with its search words');
	pg_assert_same(false, strpos($general, 'pgset-backup'), 'no longer in General');
}

function test_settings_backup_card_old_links_land_on_it()
{
	$anchors = pg_settings_legacy_anchors();

	pg_assert_same(array('maintenance', 'pgset-backup'), $anchors['pgset-backup'], 'settings_general.php#pgset-backup');
	pg_assert_contains("'settings_maintenance.php#pgset-backup'", settings_backup_card_test_source('includes/fn/ops_alerts.php'), 'the remote copy alert');
}

function test_settings_backup_card_is_drawn_once_in_maintenance()
{
	$general = settings_backup_card_test_source('includes/settings/general.php');
	$cards = settings_backup_card_test_source('includes/settings/maintenance.php');

	pg_assert_same(false, strpos($general, 'id="pgset-backup"'), 'not drawn on General');
	pg_assert_same(false, strpos($general, 'backup_'), 'General keeps no backup field');
	pg_assert_contains('id="pgset-backup"', $cards, 'drawn here');
	pg_assert_true(strpos($cards, 'id="pgset-cron"') < strpos($cards, 'id="pgset-backup"'), 'after Cron Jobs');
	pg_assert_true(strpos($cards, 'id="pgset-backup"') < strpos($cards, 'id="pgset-alerts"'), 'before Alerts');
	pg_assert_contains('<a href="backups.php"', $cards, 'the link to the Backup Manager goes with it');

	$names = settings_backup_card_test_names($cards);

	foreach (array('backup_keep', 'backup_remote_type', 'backup_ftp_host', 'backup_ftp_password', 'backup_s3_bucket', 'backup_s3_secret_key', 'backup_remote_test') as $name) {
		pg_assert_true(in_array($name, $names, true), $name . ' posted here');
	}
}

function test_settings_backup_card_connect_returns_to_the_new_place()
{
	$cards = settings_backup_card_test_source('includes/settings/maintenance.php');

	pg_assert_contains("pg_settings_url('maintenance') . '?backup_connect=' . \$backup_cloud_type . '#pgset-backup'", $cards, 'the account connection comes back to the card');
	pg_assert_contains("\$_GET['backup_connect']", $cards, 'and is read there');

	// A connection started before the move still comes back to General.
	$general = settings_backup_card_test_source('settings_general.php');
	pg_assert_contains("isset(\$_GET['backup_connect'])", $general, 'the old return is caught');
	pg_assert_contains("'settings_maintenance.php?' . http_build_query(array('backup_connect' => ", $general, 'and sent on to the card');
}

function test_settings_backup_card_save_moved_with_it()
{
	$save = settings_backup_card_test_source('includes/settings/maintenance.save.php');
	$general = settings_backup_card_test_source('includes/settings/general.save.php');

	foreach (array('backup_keep = ', 'backup_remote_type = ', 'backup_remote_settings = ', "post_value('backup_remote_test')", 'pg_backup_remote_send(') as $part) {
		pg_assert_contains($part, $save, $part . ' here');
		pg_assert_same(false, strpos($general, $part), $part . ' no longer in General');
	}

	// The stored row the empty retention box and the kept secrets read.
	pg_assert_true(strpos($save, 'SELECT * FROM config') < strpos($save, "\$row['backup_keep']"), 'reads the stored row first');
	pg_assert_true(strpos($save, '$sql_backup_settings = \'\';') < strpos($save, 'db("UPDATE config'), 'built before the update');
	pg_assert_contains('" . $sql_backup_settings . "', $save, 'and written by it');
}
