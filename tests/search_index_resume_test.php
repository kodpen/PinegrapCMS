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

	pg_assert_contains('$search_index_saved_state = pg_search_index_state_read();', $src, 'the pass is read back');
	pg_assert_contains('$search_index_state = pg_search_index_next($search_index_saved_state, time());', $src, 'and carried on');
	pg_assert_contains("\$timestamp = \$search_index_state['started'];", $src, 'rows carry the pass start');
	pg_assert_contains(": \"(page.page_id > '\" . (int) \$search_index_state['after'] . \"')\")", $src, 'pages resume after the last one');
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
	pg_assert_contains('<input type="hidden" name="continue" value="1">', $src);
}

// Parts inside an item: a page's own render, its calendar events and classic
// records; a record list host's records. The budget is checked after each
// part, a tick out of time pauses the item at its last finished part.
function test_search_index_parts_pause_and_resume()
{
	$state = pg_search_index_next(array('started' => 500, 'stage' => 'pages', 'after' => '6', 'current' => ''), 1000);
	$state = pg_search_index_begin($state, '7')['state'];

	pg_assert_same('run', pg_search_index_part_step($state, 'news', false), 'the page itself is read');
	$state = pg_search_index_part_done($state, 'news');
	pg_assert_same('run', pg_search_index_part_step($state, 'event?id=1', false), 'then its first event');
	$state = pg_search_index_part_done($state, 'event?id=1');
	$state = pg_search_index_pause($state);

	pg_assert_true($state['paused'], 'the item is paused');
	pg_assert_same('7', $state['current'], 'still the item under way');
	pg_assert_same('event?id=1', $state['part'], 'at its last finished part');

	$saved = json_decode(json_encode($state), true);
	$next = pg_search_index_next($saved, 2000);
	$begin = pg_search_index_begin($next, '7', null);

	pg_assert_false($begin['skip'], 'a paused item is resumed, not skipped');
	pg_assert_false($begin['keep'], 'nothing to keep');
	pg_assert_same(array(), $begin['state']['skipped'], 'and nothing named');
	pg_assert_false($begin['state']['paused'], 'the pause is used up');
	pg_assert_same('done', pg_search_index_part_step($begin['state'], 'news', $begin['state']['part'] !== ''), 'the page itself is not read again');
	pg_assert_same('done', pg_search_index_part_step($begin['state'], 'event?id=1', true), 'nor the event the carry holds');
	pg_assert_same('run', pg_search_index_part_step($begin['state'], 'event?id=2', false), 'the next event is read');

	$done = pg_search_index_finish($begin['state'], '7');
	pg_assert_same('', $done['part'], 'a finished item leaves no part');
	pg_assert_same(array(), $done['dead_parts'], 'nor dead parts');
}

function test_search_index_parts_death_inside_a_part()
{
	$state = pg_search_index_next(array('started' => 500, 'stage' => 'designer', 'after' => '2', 'current' => '3', 'part' => 'blog/a'), 1000);
	$marker = array('started' => 500, 'stage' => 'designer', 'item' => '3', 'part' => 'blog/c');
	$begin = pg_search_index_begin($state, '3', $marker);

	pg_assert_false($begin['skip'], 'the item is read on');
	pg_assert_false($begin['keep'], 'no rows kept by page');
	pg_assert_same(array('blog/c'), $begin['state']['dead_parts'], 'the part the tick died in');
	pg_assert_same(array('designer 3 blog/c'), $begin['state']['skipped'], 'is named');
	pg_assert_same('done', pg_search_index_part_step($begin['state'], 'blog/a', true), 'parts written before the checkpoint stay done');
	pg_assert_same('run', pg_search_index_part_step($begin['state'], 'blog/b', false), 'a part after the checkpoint is read again');
	pg_assert_same('skip', pg_search_index_part_step($begin['state'], 'blog/c', false), 'only the part the tick died in is skipped');
	pg_assert_same('run', pg_search_index_part_step($begin['state'], 'blog/d', false), 'the rest is read');

	// A marker of another pass, stage or item says nothing.
	foreach (array(array('started' => 499) + $marker, array('stage' => 'pages') + $marker, array('item' => '4') + $marker) as $other) {
		pg_assert_true(pg_search_index_begin($state, '3', $other)['skip'], 'a stale marker is ignored');
	}

	// The same part named again: the tick died after it was skipped, outside
	// any part.
	$again = pg_search_index_begin(pg_search_index_next(json_decode(json_encode($begin['state']), true), 1000), '3', $marker);
	pg_assert_true($again['skip'], 'a part already skipped does not hold the item twice');
	pg_assert_true($again['keep'], 'and the item keeps its rows');
}

function test_search_index_parts_death_outside_parts_keeps_rows()
{
	$state = pg_search_index_next(array('started' => 500, 'stage' => 'pages', 'after' => '6', 'current' => '7'), 1000);
	$begin = pg_search_index_begin($state, '7', null);

	pg_assert_true($begin['skip'], 'the item is skipped as before');
	pg_assert_true($begin['keep'], 'and keeps its rows');

	pg_assert_same("UPDATE search_items SET timestamp = '500' WHERE page_id IN ('7')", pg_search_index_keep_pages_sql(array(7), 500), 'a page item keeps by its page id');
	pg_assert_same("UPDATE search_items SET timestamp = '500' WHERE page_id IN ('12', '14')", pg_search_index_keep_pages_sql(array('12', 14, 12, 0, -3), 500), 'ids once, positive');
	pg_assert_same('', pg_search_index_keep_pages_sql(array(), 500), 'no page, no query');

	$host = array('page' => array('id' => 3), 'lists' => array(array('detail_page_id' => 12), array('detail_page_id' => '14'), array('detail_page_id' => 12), array()));
	pg_assert_same(array(12, 14), pg_search_index_host_pages($host), "a host keeps by its lists' detail pages");
	pg_assert_same(array(), pg_search_index_host_pages(array()), 'a broken host keeps nothing');
}

function test_search_index_parts_old_cursor()
{
	// A cursor written before items had parts.
	$old = array('started' => 500, 'stage' => 'pages', 'after' => '6', 'current' => '7', 'skipped' => array(), 'carry' => array());
	$next = pg_search_index_next($old, 1000);

	pg_assert_same('', $next['part'], 'no checkpoint');
	pg_assert_false($next['paused'], 'not paused');
	pg_assert_same(array(), $next['dead_parts'], 'no dead parts');

	$begin = pg_search_index_begin($next, '7');
	pg_assert_true($begin['skip'], 'the item it ended inside is skipped as before');
	pg_assert_same(array('pages 7'), $begin['state']['skipped'], 'and named');
	pg_assert_same('run', pg_search_index_part_step(pg_search_index_begin($begin['state'], '8')['state'], 'next', false), 'the next item reads its parts');
}

function test_search_index_parts_wiring()
{
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/update_search_index.php');

	pg_assert_same(4, substr_count($src, '= $search_index_part('), 'every part asks before it is read: page, event, classic record, visual editor record');
	pg_assert_contains('pg_search_index_marker_write(', $src, 'a small marker names the part being read');
	pg_assert_contains('pg_search_index_marker_read()', $src, 'and is read back when the item resumes');
	pg_assert_contains('db(pg_search_index_keep_pages_sql(array($page[\'id\']), $timestamp));', $src, 'a skipped page item keeps its rows');
	pg_assert_contains('db(pg_search_index_keep_pages_sql(pg_search_index_host_pages($designer_host), $timestamp));', $src, 'a skipped host keeps its records');
	pg_assert_contains("pg_search_index_keep_item(encode_url_path(\$file['name']), \$timestamp);", $src, 'a skipped file keeps its row');
	pg_assert_contains('ORDER BY calendar_events.id ASC', $src, 'events in a fixed order');
}
