<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Updates and maintenance category of Site Settings: the Update Channel,
 * Cron Jobs, Alerts and Maintenance Mode cards left General for it, with the
 * fields they post and the columns their save writes unchanged, the old links
 * to the four cards land on it, and the links inside the software name it.
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
require_once(PG_FUNCTIONS_DIR . '/get_help_url.php');

function settings_maintenance_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

// One category's entry in pg_settings_categories(), cut out of the source:
// the list asks the database, so it is read rather than called.
function settings_maintenance_test_category($key)
{
	$source = settings_maintenance_test_source('includes/settings/registry.php');
	$start = strpos($source, "\n        '" . $key . "' => array(");

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\n        ),\n", $start);

	return substr($source, $start, $end - $start);
}

// The field names a card file posts.
function settings_maintenance_test_names($source)
{
	preg_match_all('/\bname="([a-z0-9_]+)(?:\[\])?"/', $source, $match);
	$names = array_values(array_unique($match[1]));
	sort($names);

	return $names;
}

function settings_maintenance_test_sections()
{
	return array('pgset-channel', 'pgset-cron', 'pgset-alerts', 'pgset-maintenance');
}

function test_settings_maintenance_category_is_last()
{
	$source = settings_maintenance_test_source('includes/settings/registry.php');
	$api = strpos($source, "\n        'api' => array(");
	$maintenance = strpos($source, "\n        'maintenance' => array(");
	$end = strpos($source, "\n    );\n", $maintenance);

	pg_assert_true($maintenance !== false, 'a maintenance category');
	pg_assert_true($api < $maintenance, 'read after API');
	pg_assert_same(false, strpos(substr($source, $maintenance + 10, $end - $maintenance - 10), "\n        '"), 'the last one');
}

function test_settings_maintenance_category_holds_its_four_cards()
{
	$maintenance = settings_maintenance_test_category('maintenance');
	$general = settings_maintenance_test_category('general');

	pg_assert_contains("'label' => lang('Updates and maintenance')", $maintenance, 'its label');

	$at = 0;

	foreach (settings_maintenance_test_sections() as $section) {
		$here = strpos($maintenance, "'" . $section . "'");
		pg_assert_true(($here !== false) && ($here > $at), $section . ' in reading order');
		$at = $here;
		pg_assert_same(false, strpos($general, "'" . $section . "'"), $section . ' no longer in General');
	}

	pg_assert_contains("'job_dispatch_job' => 1", $maintenance, 'the job list travels with Cron Jobs');
	pg_assert_same(false, strpos($general, 'job_dispatch_job'), 'and leaves General');
}

function test_settings_maintenance_old_links_land_on_the_cards()
{
	$anchors = pg_settings_legacy_anchors();

	foreach (settings_maintenance_test_sections() as $section) {
		pg_assert_same(array('maintenance', $section), $anchors[$section], 'settings_general.php#' . $section);
	}
}

function test_settings_maintenance_cards_moved_out_of_general()
{
	$general = settings_maintenance_test_source('includes/settings/general.php');
	$cards = settings_maintenance_test_source('includes/settings/maintenance.php');

	foreach (settings_maintenance_test_sections() as $section) {
		pg_assert_same(false, strpos($general, 'id="' . $section . '"'), $section . ' not drawn on General');
		pg_assert_contains('id="' . $section . '"', $cards, $section . ' drawn here');
	}

	pg_assert_contains('id="cron_jobs"', $cards, 'the setup dialog goes with Cron Jobs');
	pg_assert_same(false, strpos($general, 'id="cron_jobs"'), 'and leaves General');

	$names = settings_maintenance_test_names($cards);

	foreach (array('software_update_channel', 'update_backup', 'job_dispatch_enabled', 'ops_alerts', 'ops_alert_recipients', 'maintenance_mode', 'maintenance_until', 'maintenance_allow_ips') as $name) {
		pg_assert_true(in_array($name, $names, true), $name . ' posted here');
	}

	pg_assert_same(array(), array_values(array_intersect($names, settings_maintenance_test_names($general))), 'no field drawn in both');
}

function test_settings_maintenance_save_writes_what_the_cards_post()
{
	$save = settings_maintenance_test_source('includes/settings/maintenance.save.php');
	$general = settings_maintenance_test_source('includes/settings/general.save.php');

	foreach (array('job_dispatch_enabled', 'ops_alerts', 'ops_alert_recipients', 'maintenance_mode', 'maintenance_until', 'maintenance_allow_ips', 'update_backup', 'software_update_channel') as $column) {
		pg_assert_contains($column . ' = ', $save, $column . ' written here');
		pg_assert_same(false, strpos($general, $column . ' = '), $column . ' no longer written by General');
	}

	// The end time is read in the stored time zone: this save carries none.
	pg_assert_contains("\$maintenance_timezone = (string) \$row['timezone'];", $save, 'the stored time zone');
	pg_assert_same(false, strpos($save, "post_value('timezone')"), 'not a posted one');
}

function test_settings_maintenance_has_a_page_menu_case_and_help()
{
	$page = settings_maintenance_test_source('settings_maintenance.php');

	pg_assert_contains("define('PG_SETTINGS_KEY', 'maintenance');", $page, 'the page path of the category');
	pg_assert_contains("include('includes/settings/screen.php');", $page, 'drawn by the shared screen');
	pg_assert_contains("case 'settings_maintenance.php':", settings_maintenance_test_source('includes/fn/output.php'), 'marks the Site Settings row');
	pg_assert_same('https://pinegrap.com/site-settings#updates-and-maintenance', get_help_url(array('screen' => 'settings_maintenance')), 'its help topic');
}

function test_settings_maintenance_links_name_the_new_place()
{
	pg_assert_same(false, strpos(settings_maintenance_test_source('software_update.php'), "('general', 'pgset-channel')"), 'software_update.php returns to the new place');
	pg_assert_contains("pg_settings_return_url('maintenance', 'pgset-channel')", settings_maintenance_test_source('software_update.php'), 'to the Update Channel card');
	pg_assert_contains("'settings_maintenance.php#pgset-cron'", settings_maintenance_test_source('includes/fn/ops_alerts.php'), 'the stalled job alert');

	$status = settings_maintenance_test_source('includes/fn/system_status.php');
	pg_assert_contains('/settings_maintenance.php#pgset-alerts', $status, 'the alerts tile');
	pg_assert_contains('/settings_maintenance.php#pgset-maintenance', $status, 'the maintenance tile');
}
