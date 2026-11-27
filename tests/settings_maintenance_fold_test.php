<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings > Updates and maintenance as folded rows: the rows' order
 * and names, each part's line of state with its pill, the sales watch in a
 * card of its own that still stops with Operations alerts, and the names
 * that stay (card ids, field names, the save's marks, the search words).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_SETTINGS_ENTRY')) {
	define('PG_SETTINGS_ENTRY', true);
}

if (!defined('PG_SETTINGS_MENU')) {
	define('PG_SETTINGS_MENU', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/settings/fold.php');
require_once(PG_FUNCTIONS_DIR . '/includes/settings/registry.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/maintenance_settings_fold.php');

function settings_maintenance_fold_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

// One category's entry in pg_settings_categories(), cut out of the source.
function settings_maintenance_fold_test_category($key)
{
	$source = settings_maintenance_fold_test_source('includes/settings/registry.php');
	$start = strpos($source, "\n        '" . $key . "' => array(");

	return ($start === false) ? '' : substr($source, $start, strpos($source, "\n        ),\n", $start) - $start);
}

function test_settings_maintenance_fold_order_and_faces()
{
	pg_assert_same(array('channel', 'maintenance', 'cron', 'backup', 'storage', 'alerts', 'sales-watch', 'chat-notify'), pg_maintenance_fold_order(), 'the rows in the description\'s order');

	$names = array();

	foreach (pg_maintenance_fold_order() as $key) {
		$face = pg_maintenance_fold_face($key);
		$names[] = $face['name'];
		pg_assert_true(strpos($face['icon'], 'bi-') === 0, $key . ': an icon');
	}

	pg_assert_same(array('Update channel', 'Maintenance mode', 'Scheduled jobs', 'Backups', 'Storage', 'Operations alerts', 'Sales watch', 'Chat notifications'), $names, 'the rows\' names');
}

function test_settings_maintenance_fold_channel()
{
	$stable = pg_maintenance_fold_channel('stable', true, true);
	pg_assert_same('Stable · backs up before updating', $stable['line'], 'stable with the backup');
	pg_assert_same(null, $stable['pill'], 'no pill on stable');

	$beta = pg_maintenance_fold_channel('beta', true, false);
	pg_assert_same('Beta', $beta['line'], 'beta without the backup');
	pg_assert_same('pg-tone-amber', $beta['pill'][1], 'an amber pill on beta');

	pg_assert_same('Stable', pg_maintenance_fold_channel('stable', false, true)['line'], 'no backup words before its column');
}

function test_settings_maintenance_fold_mode()
{
	$now = mktime(12, 0, 0, 1, 15, 2026);

	pg_assert_same('Ready once the upgrade has run', pg_maintenance_fold_mode(false, true, 0, $now)['line'], 'before the upgrade');
	pg_assert_same('Off', pg_maintenance_fold_mode(true, false, 0, $now)['line'], 'off');
	pg_assert_same(null, pg_maintenance_fold_mode(true, false, 0, $now)['pill'], 'no pill when off');

	$until = pg_maintenance_fold_mode(true, true, mktime(14, 0, 0, 1, 15, 2026), $now);
	pg_assert_same('On until 14:00', $until['line'], 'on until today\'s time');
	pg_assert_same(array('On', 'pg-tone-amber'), $until['pill'], 'an amber pill');

	pg_assert_same('On until 16.01.2026 09:30', pg_maintenance_fold_mode(true, true, mktime(9, 30, 0, 1, 16, 2026), $now)['line'], 'the day too when it is not today');
	pg_assert_same('On', pg_maintenance_fold_mode(true, true, 0, $now)['line'], 'on with no end time');

	$ended = pg_maintenance_fold_mode(true, true, mktime(11, 0, 0, 1, 15, 2026), $now);
	pg_assert_same('Ended at 11:00: visitors see the site', $ended['line'], 'past its end time');
	pg_assert_same(null, $ended['pill'], 'no On pill once it no longer holds');
}

function test_settings_maintenance_fold_cron()
{
	$jobs = array(
		'job'     => array('dispatch' => false),
		'backup'  => array('dispatch' => true),
		'seo'     => array('dispatch' => true),
		'webhook' => array('dispatch' => true),
	);

	pg_assert_same(2, pg_maintenance_fold_cron_count(array('backup', 'seo', 'seo', 'job', 'gone'), $jobs), 'only jobs that can run with the general job, once each');
	pg_assert_same(1, pg_maintenance_fold_cron_count(array('backup', 'seo'), $jobs, array('seo')), 'a job config.php switched off does not run with it');

	$now = 1000000;

	$never = pg_maintenance_fold_cron(0, $now, 600, true, 2);
	pg_assert_same('Not scheduled: nothing runs on its own', $never['line'], 'never ran');
	pg_assert_true($never['attention'], 'asks for attention');

	$stopped = pg_maintenance_fold_cron($now - 700, $now, 600, true, 2);
	pg_assert_true(strpos($stopped['line'], 'Stopped: the general job last ran ') === 0, 'stopped');
	pg_assert_true($stopped['attention'], 'a stopped job asks for attention');

	$six = pg_maintenance_fold_cron($now - 30, $now, 600, true, 6);
	pg_assert_same('Runs every minute · 6 jobs with it', $six['line'], 'six jobs with it');
	pg_assert_false($six['attention'], 'no attention while it runs');

	pg_assert_same('Runs every minute · 1 job with it', pg_maintenance_fold_cron($now - 30, $now, 600, true, 1)['line'], 'one job');
	pg_assert_same('Runs every minute · no job with it', pg_maintenance_fold_cron($now - 30, $now, 600, false, 6)['line'], 'the master off: none runs with it');
}

function test_settings_maintenance_fold_backup()
{
	$now = mktime(12, 0, 0, 1, 15, 2026);

	$none = pg_maintenance_fold_backup('', '', 0, $now);
	pg_assert_same('No backup yet · On this server', $none['line'], 'no backup yet');
	pg_assert_true($none['attention'], 'asks for attention');

	$ftp = pg_maintenance_fold_backup('ftp', '', mktime(3, 0, 0, 1, 15, 2026), $now);
	pg_assert_same('FTP · last backup 03:00', $ftp['line'], 'the place and the time');
	pg_assert_false($ftp['attention'], 'all well');

	pg_assert_same('Google Drive · last backup 11.01.2026 03:00', pg_maintenance_fold_backup('gdrive', '', mktime(3, 0, 0, 1, 11, 2026), $now)['line'], 'an older one with its day');

	$failed = pg_maintenance_fold_backup('s3', 'Access denied', mktime(3, 0, 0, 1, 15, 2026), $now);
	pg_assert_true($failed['attention'], 'a failed remote copy asks for attention');
	pg_assert_same('pg-tone-rose', $failed['pill'][1], 'with a rose pill');
}

function test_settings_maintenance_fold_storage()
{
	pg_assert_same('Files on the disk', pg_maintenance_fold_storage(false, 'site', 'to', false, 10, null)['line'], 'no store in use');
	pg_assert_same('Files on the disk', pg_maintenance_fold_storage(true, 'site', '', false, 0, true)['line'], 'a store, nothing moved');
	pg_assert_same('Moving files: 1,240 left', pg_maintenance_fold_storage(true, 'site', 'to', false, 1240, true)['line'], 'moving');
	pg_assert_same('Moving files paused: 5 left', pg_maintenance_fold_storage(true, 'site', 'to', true, 5, true)['line'], 'paused');
	pg_assert_same('In the bucket site', pg_maintenance_fold_storage(true, 'site', 'to', false, 0, true)['line'], 'all moved');
	pg_assert_same('Bringing files back: 3 left', pg_maintenance_fold_storage(true, 'site', 'back', false, 3, true)['line'], 'bringing back');
	pg_assert_same('Files on the disk', pg_maintenance_fold_storage(true, 'site', 'back', false, 0, true)['line'], 'all brought back');

	$failed = pg_maintenance_fold_storage(true, 'site', 'to', false, 0, false);
	pg_assert_true($failed['attention'], 'a failed last test asks for attention');
	pg_assert_false(pg_maintenance_fold_storage(true, 'site', 'to', false, 0, null)['attention'], 'no test yet is no fault');
}

function test_settings_maintenance_fold_alerts()
{
	pg_assert_same(2, pg_maintenance_fold_recipient_count('a@example.com, b@example.com,'), 'two addresses');
	pg_assert_same(1, pg_maintenance_fold_recipient_count(''), 'empty is the site address');

	pg_assert_same('By e-mail to 2 addresses and to devices', pg_maintenance_fold_alerts(true, true, true, 'a@example.com,b@example.com', true)['line'], 'both ways');
	pg_assert_same('By e-mail to 1 address', pg_maintenance_fold_alerts(true, true, true, '', false)['line'], 'e-mail alone');
	pg_assert_same('To devices', pg_maintenance_fold_alerts(true, true, false, '', true)['line'], 'devices alone');
	pg_assert_same('On the bell only', pg_maintenance_fold_alerts(true, true, false, '', false)['line'], 'neither way');
	pg_assert_same('Off', pg_maintenance_fold_alerts(true, false, true, '', true)['line'], 'off');
	pg_assert_same('Ready once the upgrade has run', pg_maintenance_fold_alerts(false, true, true, '', true)['line'], 'before the upgrade');
}

function test_settings_maintenance_fold_sales_watch()
{
	$now = mktime(12, 0, 0, 12, 20, 2026);
	$settings = pg_sales_watch_settings_normalize(null);

	pg_assert_same('Watching, normal sensitivity', pg_maintenance_fold_sales(true, $settings, $now)['line'], 'the defaults');

	$off = pg_maintenance_fold_sales(false, $settings, $now);
	pg_assert_same('Off with Operations alerts', $off['line'], 'stops with Operations alerts');

	$high = pg_sales_watch_settings_normalize(array('level' => 'high', 'paused' => array('site' => $now + 3600)));
	pg_assert_same('Watching, high sensitivity · 1 channel paused', pg_maintenance_fold_sales(true, $high, $now)['line'], 'a channel paused');

	// The store paused through 2 January: the pause ends at the next midnight.
	$paused = pg_sales_watch_settings_normalize(array('paused' => array('store' => mktime(0, 0, 0, 1, 3, 2027))));
	pg_assert_same('Paused until 02.01.2027', pg_maintenance_fold_sales(true, $paused, $now)['line'], 'paused through its last day');
	pg_assert_same(0, pg_maintenance_fold_sales_pause_day($paused, mktime(0, 0, 0, 1, 3, 2027)), 'over at that midnight');

	$none = pg_sales_watch_settings_normalize(array('off' => pg_sales_watch_alert_names()));
	pg_assert_same('Off', pg_maintenance_fold_sales(true, $none, $now)['line'], 'every alert switched off');
}

function test_settings_maintenance_fold_chat()
{
	$kinds = array('slack' => array('label' => 'Slack'), 'teams' => array('label' => 'Microsoft Teams'));

	pg_assert_same('None', pg_maintenance_fold_chat(array(), $kinds)['line'], 'no target');
	pg_assert_same('Shop team, Microsoft Teams', pg_maintenance_fold_chat(array(
		'a' => array('kind' => 'slack', 'label' => 'Shop team'),
		'b' => array('kind' => 'teams', 'label' => ''),
	), $kinds)['line'], 'by name, the service\'s when it has none');

	$five = array();

	for ($i = 1; $i <= 5; $i++) {
		$five[] = array('kind' => 'slack', 'label' => 'T' . $i);
	}

	pg_assert_same('T1, T2, T3 and 2 more', pg_maintenance_fold_chat($five, $kinds)['line'], 'at most three names');
}

function test_settings_maintenance_fold_pane_draws_the_rows()
{
	$pane = settings_maintenance_fold_test_source('includes/settings/maintenance.php');

	pg_assert_contains("require_once(PG_FUNCTIONS_DIR . '/includes/fn/maintenance_settings_fold.php');", $pane, 'the module is loaded');
	pg_assert_contains('pg_settings_fold($maintenance_rows)', $pane, 'drawn as folded rows');
	pg_assert_same(1, substr_count($pane, '$pg_settings_cards[]'), 'one entry: the rows');

	foreach (pg_maintenance_fold_order() as $key) {
		pg_assert_contains('id="pgset-' . $key . '"', $pane, $key . ' keeps its card id');
	}

	// The save's marks and buttons stay as they were.
	foreach (array('name="object_storage_card"', 'name="object_storage_test"', 'name="backup_remote_test"', 'name="sales_watch_card"', "pg_maintenance_settings_switch('ops_alerts',", 'name="job_dispatch_enabled"') as $mark) {
		pg_assert_contains($mark, $pane, $mark);
	}
}

function test_settings_maintenance_fold_sales_watch_has_its_own_card()
{
	$pane = settings_maintenance_fold_test_source('includes/settings/maintenance.php');
	$sales = strpos($pane, 'id="pgset-sales-watch"');
	$alerts = strpos($pane, 'id="pgset-alerts"');

	pg_assert_true(($sales !== false) && ($alerts !== false) && ($alerts < $sales), 'a card of its own after Alerts');
	pg_assert_same(false, strpos($pane, '$output_ops_alert_settings .='), 'no longer added to the Alerts card');
	pg_assert_contains('data-pg-set-goto="pgset-alerts"', $pane, 'off with Operations alerts, with the way to that row');
	pg_assert_contains('getElementById("sales_watch_fields")', $pane, 'its fields still follow the Operations alerts switch');

	$category = settings_maintenance_fold_test_category('maintenance');
	pg_assert_contains("'pgset-sales-watch' => lang('Sales watch')", $category, 'a section of the pane');
	pg_assert_contains("'pgset-sales-watch' => pg_settings_keywords(", $category, 'with its search words');

	$at = 0;

	foreach (pg_maintenance_fold_order() as $key) {
		$here = strpos($category, "'pgset-" . $key . "'");
		pg_assert_true(($here !== false) && ($here > $at), 'pgset-' . $key . ' listed in the rows\' order');
		$at = $here;
	}
}

function test_settings_maintenance_fold_sales_watch_listed_only_with_the_shop()
{
	// The card is drawn only with the shop on (prep.php's $sales_watch_ready):
	// with it off, no chip or search hit may name a card that is not there.
	$prep = settings_maintenance_fold_test_source('includes/settings/prep.php');
	pg_assert_contains("defined('ECOMMERCE') && (ECOMMERCE === true)", $prep, 'the card waits for the shop');

	$source = settings_maintenance_fold_test_source('includes/settings/registry.php');
	pg_assert_true(preg_match("/if \(!defined\('ECOMMERCE'\) \|\| \(ECOMMERCE !== true\)\) \{\s+unset\(\\\$categories\['maintenance'\]\['sections'\]\['pgset-sales-watch'\], \\\$categories\['maintenance'\]\['keywords'\]\['pgset-sales-watch'\]\);/", $source) === 1, 'left out of the list with the shop off');
}
