<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the resumable search index pass (includes/fn/site_search.php):
 * the stages in order, the cursor read back or started anew, and the skip
 * rule for the item a killed tick left unfinished.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/site_search.php');

function test_search_index_resume_new_pass()
{
	foreach (array('missing' => null, 'garbage' => 'x', 'unknown stage' => array('stage' => 'nope', 'started' => 5),
		'no start' => array('stage' => 'files', 'started' => 0), 'finished' => array('stage' => 'sweep', 'started' => 5, 'done' => true)) as $label => $state) {
		$next = pg_search_index_next($state, 1000);
		pg_assert_same(1000, $next['started'], $label . ': starts now');
		pg_assert_same('pages', $next['stage'], $label . ': at the first stage');
		pg_assert_same('', $next['after'], $label . ': from the first item');
	}
}

function test_search_index_resume_keeps_its_place()
{
	$next = pg_search_index_next(array('started' => 500, 'stage' => 'designer', 'after' => '42', 'current' => '43', 'skipped' => array('pages 7'), 'carry' => array('linked' => array('a.pdf'))), 1000);

	pg_assert_same(500, $next['started'], 'the pass keeps its start time');
	pg_assert_same('designer', $next['stage'], 'stage');
	pg_assert_same(array('linked' => array('a.pdf')), $next['carry'], 'what the stages hand on');
	pg_assert_same('42', $next['after'], 'last finished item');
	pg_assert_same('43', $next['current'], 'the unfinished item');
	pg_assert_same(array('pages 7'), $next['skipped'], 'earlier skips');
}

function test_search_index_resume_skips_the_item_a_tick_died_in()
{
	$state = pg_search_index_next(array('started' => 500, 'stage' => 'pages', 'after' => '6', 'current' => '7'), 1000);
	$begin = pg_search_index_begin($state, '7');

	pg_assert_true($begin['skip'], 'the item the last tick died in is skipped');
	pg_assert_same('7', $begin['state']['after'], 'and counted as passed');
	pg_assert_same('', $begin['state']['current'], 'nothing is in progress');
	pg_assert_same(array('pages 7'), $begin['state']['skipped'], 'and named');

	$other = pg_search_index_begin($begin['state'], '8');
	pg_assert_false($other['skip'], 'the next item runs');
	pg_assert_same('8', $other['state']['current'], 'and is marked started');

	$done = pg_search_index_finish($other['state'], '8');
	pg_assert_same('8', $done['after'], 'finished');
	pg_assert_same('', $done['current'], 'nothing in progress');
}

function test_search_index_resume_stages_in_order()
{
	pg_assert_same(array('pages', 'designer', 'files', 'sweep'), pg_search_index_stages());

	$state = pg_search_index_next(null, 1000);
	$seen = array($state['stage']);

	while (true) {
		$state = pg_search_index_advance(pg_search_index_finish($state, '9'));
		if ($state['done']) break;
		pg_assert_same('', $state['after'], 'a stage starts from its first item');
		$seen[] = $state['stage'];
	}

	pg_assert_same(pg_search_index_stages(), $seen, 'every stage once, the sweep last');
	pg_assert_same(1000, $state['started'], 'the start time survives the pass');
}

function test_search_index_resume_wiring()
{
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/update_search_index.php');

	pg_assert_contains('$search_index_state = pg_search_index_next(pg_search_index_state_read(), time());', $src, 'the pass is read back');
	pg_assert_contains("\$timestamp = \$search_index_state['started'];", $src, 'rows carry the pass start');
	pg_assert_contains("AND (page.page_id > '\" . (int) \$search_index_state['after'] . \"')", $src, 'pages resume after the last one');
	pg_assert_contains("AND (files.id > '\" . (int) \$search_index_state['after'] . \"')", $src, 'files resume after the last one');
	pg_assert_same(3, substr_count($src, '$search_index_step = $search_index_item('), 'each stage loop asks before every item');
	pg_assert_same(3, substr_count($src, "if (\$search_index_step === 'skip') {"), 'each stage loop skips the item a run ended inside');

	// The cursor is written before an item is read, so a run killed inside
	// it leaves it named as current.
	$item = substr($src, strpos($src, '$search_index_item = function'));
	$item = substr($item, 0, strpos($item, '};'));
	pg_assert_true(strpos($item, 'pg_search_index_begin(') < strpos($item, '$search_index_save();', strpos($item, 'pg_search_index_begin(')), 'saved after the item is marked started');

	// The sweep, the Site Log line and the completion marker only at the end
	// of the pass; an unfinished dispatched pass is due again in a minute.
	$end = substr($src, strpos($src, '$search_index_done = '));
	$sweep = strpos($end, 'db(pg_search_index_sweep_sql($timestamp));');
	$ran = strpos($end, "pg_cron_ran('update_search_index');");
	$again = strpos($end, "pg_cron_due_again('update_search_index', 60);");
	pg_assert_true($sweep !== false && $ran !== false && $again !== false && $sweep < $ran && $ran < $again, 'sweep and marker in the done branch');
	pg_assert_contains("if (\$search_index_done) {", $end);

	// The panel window asks for the next request while the pass is unfinished.
	pg_assert_contains('update_search_index.php?continue=1', $src);
}
