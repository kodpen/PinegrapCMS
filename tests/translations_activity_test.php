<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Translations screen's automation and "Activity" helpers in
 * includes/translate/activity.php: the values the automation dialog saves,
 * the filter of the updates, what an update covered, how long it took, and
 * which failed texts "Try again with the failed ones" sends.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/translate/activity.php');

function test_translations_activity_automation_values()
{
	$options = array('manual' => 'By hand', 'google' => 'Google', 'claude' => 'Claude', 'chrome' => 'Browser');

	$v = pg_tr_automation_values(array('engine' => 'claude', 'fallback_engine' => 'google', 'auto_update' => '1'), $options);
	pg_assert_same(true, $v['ok'], 'a known engine');
	pg_assert_same('claude', $v['engine'], 'engine kept');
	pg_assert_same('google', $v['fallback_engine'], 'fallback kept');
	pg_assert_same(1, $v['auto_update'], 'translate on save on');

	$v = pg_tr_automation_values(array('engine' => 'claude', 'fallback_engine' => 'claude'), $options);
	pg_assert_same('', $v['fallback_engine'], 'the engine is not its own fallback');
	pg_assert_same(0, $v['auto_update'], 'not posted is off');

	$v = pg_tr_automation_values(array('engine' => 'google', 'fallback_engine' => 'manual'), $options);
	pg_assert_same('', $v['fallback_engine'], 'by hand is no fallback');

	$v = pg_tr_automation_values(array('engine' => 'google', 'fallback_engine' => 'deepl'), $options);
	pg_assert_same('', $v['fallback_engine'], 'an unknown fallback is none');

	$v = pg_tr_automation_values(array('engine' => 'manual', 'fallback_engine' => 'google', 'auto_update' => '1'), $options);
	pg_assert_same(true, $v['ok'], 'by hand is an engine of the list');
	pg_assert_same(0, $v['auto_update'], 'translate on save is off with by hand');

	pg_assert_same(false, pg_tr_automation_values(array('engine' => 'import'), $options + array('import' => 'CSV'))['ok'], 'CSV import is no engine');
	pg_assert_same(false, pg_tr_automation_values(array('engine' => 'deepl'), $options)['ok'], 'an engine not in the list');
	pg_assert_same(false, pg_tr_automation_values(array('engine' => array('claude')), $options)['ok'], 'an engine sent as a list');
	pg_assert_same(false, pg_tr_automation_values(null, $options)['ok'], 'nothing posted');
}

function test_translations_activity_filter_and_open()
{
	pg_assert_same('', pg_tr_activity_filter_sql('all'), 'all');
	pg_assert_same('', pg_tr_activity_filter_sql('anything'), 'unknown is all');
	pg_assert_same(true, strpos(pg_tr_activity_filter_sql('running'), "'running'") !== false, 'running lists open states');
	pg_assert_same(true, strpos(pg_tr_activity_filter_sql('failed'), 'failed_count > 0') !== false, 'failed counts failed texts');

	foreach (array('queued', 'sent', 'running') as $status) {
		pg_assert_same(true, pg_tr_activity_open($status), $status . ' is open');
	}

	foreach (array('done', 'failed', 'cancelled', '') as $status) {
		pg_assert_same(false, pg_tr_activity_open($status), $status . ' is closed');
	}
}

function test_translations_activity_scope_label()
{
	$groups = array('catalog' => array('label' => 'Products'), 'forms' => array('label' => 'Forms'), 'pictures' => array('label' => 'Images'));
	$pages = array(4 => 'Home');

	pg_assert_same('All pages', pg_tr_activity_scope_label('all', $groups, $pages), 'the whole site');
	pg_assert_same('Products', pg_tr_activity_scope_label('group:catalog', $groups, $pages), 'a group');
	pg_assert_same('Home', pg_tr_activity_scope_label('page:4', $groups, $pages), 'a page by name');
	pg_assert_same('One page', pg_tr_activity_scope_label('page:9', $groups, $pages), 'a page no longer listed');
	pg_assert_same('Forms', pg_tr_activity_scope_label('records:12', $groups, $pages), 'a record save');
	pg_assert_same('Images', pg_tr_activity_scope_label('file:3', $groups, $pages), 'a picture');
	pg_assert_same('One page', pg_tr_activity_scope_label('group:nope', $groups, $pages), 'an unknown group');
}

function test_translations_activity_took()
{
	pg_assert_same(null, pg_tr_activity_took(array('created_at' => 100, 'claimed_at' => 0, 'finished_at' => 0)), 'still open');
	pg_assert_same(50, pg_tr_activity_took(array('created_at' => 100, 'claimed_at' => 0, 'finished_at' => 150)), 'from its opening');
	pg_assert_same(30, pg_tr_activity_took(array('created_at' => 100, 'claimed_at' => 120, 'finished_at' => 150)), 'from its hand-over');
	pg_assert_same(0, pg_tr_activity_took(array('created_at' => 200, 'claimed_at' => 0, 'finished_at' => 150)), 'never negative');

	pg_assert_same('—', pg_tr_activity_duration_text(null), 'none');
	pg_assert_same('4 s', pg_tr_activity_duration_text(4), 'seconds');
	pg_assert_same('3 min', pg_tr_activity_duration_text(170), 'minutes rounded');
	pg_assert_same('2 h', pg_tr_activity_duration_text(7000), 'hours rounded');
}

function test_translations_activity_retry_ids()
{
	$statuses = array(1 => null, 2 => 'machine', 3 => 'reviewed', 4 => null);

	pg_assert_same(array(1, 4), pg_tr_activity_retry_ids(array(1, 2, 3, 4, 5), $statuses), 'only the pending ones that still exist');
	pg_assert_same(array(1), pg_tr_activity_retry_ids(array('1', 1, 0), $statuses), 'ids read once, zero dropped');
	pg_assert_same(array(), pg_tr_activity_retry_ids(array(), $statuses), 'nothing failed');
}
