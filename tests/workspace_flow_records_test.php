<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the box of the workspace's flows that reads the records of a
 * list page (kind list_records, includes/workspace/flow_records.php): the
 * records the page's RSS feed would give, picked by the feed's own query
 * (pg_sw_form_feed_pick(), split out of pg_sw_form_feed_parts()), a page not
 * open to everyone refused, and the window of "only records new since the
 * last run": from the start moment of the last run that sent (in) to this
 * run's start moment (out), the first run from the moment its version was
 * published, a record's date the later of when it was made and the list's
 * one publication-date field, the records dated before the window that were
 * changed inside it counted as left out.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');
require_once(__DIR__ . '/helpers/source_reader.php');

// A function's source without its comment lines.
function workspace_flow_records_test_source($function)
{
	return (string) preg_replace('~^\s*(//|\*|/\*\*).*$~m', '', pg_test_function_source($function));
}

// One record row as the box reads it.
function workspace_flow_records_test_row($id, $date, $modified = 0)
{
	return array('id' => $id, 'date' => $date, 'modified' => $modified);
}

// The feed reads its records through the pick, with the query it had before
// the split: complete records of the form under the list's filters, the
// newest 50 by the moment they were made.
function test_workspace_flow_records_feed_pick_unchanged()
{
	$clause = array('join' => ' LEFT JOIN x ON 1', 'where' => " AND (x.a = 'b')");
	$expected = "SELECT forms.id, forms.reference_code, forms.address_name, forms.submitted_timestamp
         FROM forms
          LEFT JOIN x ON 1
         WHERE forms.page_id = '7'
           AND forms.complete = 1
            AND (x.a = 'b')
         ORDER BY forms.submitted_timestamp DESC
         LIMIT 50";
	$squash = function ($sql) {
		return trim((string) preg_replace('/\s+/', ' ', $sql));
	};

	pg_assert_same($squash($expected), $squash(pg_sw_form_feed_pick_sql(7, $clause, array())), 'the feed\'s query');

	$source = workspace_flow_records_test_source('pg_sw_form_feed_parts');
	pg_assert_contains('pg_sw_form_feed_pick($page_id, $page_name, $feed)', $source, 'the feed calls the pick with no extras');
	pg_assert_false(strpos($source, 'SELECT forms.id') !== false, 'the feed has no query of its own');
	pg_assert_contains('<pubDate>\' . date(\'r\', (int)$record[\'submitted_timestamp\'])', $source, 'an item is dated as before');

	// The box's extras widen the select and the condition, never the feed's.
	$extra = pg_sw_form_feed_pick_sql(7, $clause, array('select' => ', forms.last_modified_timestamp', 'join' => ' LEFT JOIN y ON 1', 'where' => ' AND 1', 'limit' => 500));
	pg_assert_contains('forms.last_modified_timestamp', $extra);
	pg_assert_contains('LIMIT 500', $extra);
	pg_assert_contains("forms.complete = 1", $extra);
}

// A page is read only when everybody may open it and it lists records with
// a feed: a missing page, a folder not open to everyone or a page with no
// public list is refused, at the box's save and again each run.
function test_workspace_flow_records_private_page_refused()
{
	$page = array('page_id' => 4, 'page_name' => 'news', 'page_folder' => 2);
	$list = array('type' => 'form_list_view', 'custom_form_page_id' => 9);

	pg_assert_same('', ws_flow_records_page_refusal($page, 'public', $list));
	pg_assert_true(ws_flow_records_page_refusal(null, 'public', $list) !== '', 'no page');
	pg_assert_true(ws_flow_records_page_refusal($page, 'private', $list) !== '', 'a private folder');
	pg_assert_true(ws_flow_records_page_refusal($page, 'registration', $list) !== '', 'a folder for members');
	pg_assert_true(ws_flow_records_page_refusal($page, 'public', null) !== '', 'no list open to everyone');
	pg_assert_true(ws_flow_records_page_refusal($page, 'public', array('type' => 'form_item_view', 'custom_form_page_id' => 9)) !== '', 'a record page is no list');

	// Checked at the run too, not only when the box is saved.
	pg_assert_contains('ws_flow_records_page(', workspace_flow_records_test_source('ws_step_list_records_check'));
	pg_assert_contains('ws_step_list_records_check(', workspace_flow_records_test_source('ws_step_list_records_do'));
}

// The window holds a record made at the earlier start and leaves out one
// made at this start.
function test_workspace_flow_records_window_edges()
{
	$split = ws_flow_records_split(array(
		workspace_flow_records_test_row(1, 1000),
		workspace_flow_records_test_row(2, 1500),
		workspace_flow_records_test_row(3, 2000),
		workspace_flow_records_test_row(4, 999),
	), 1000, 2000);

	pg_assert_same(array(1, 2), array_column($split['in'], 'id'), 'at the earlier start in, at this start out');
	pg_assert_same(0, $split['left_out_count']);
}

// A record published while a run works is past that run's start: the run
// leaves it, and the next run, whose window opens at that start, gives it.
function test_workspace_flow_records_published_while_running()
{
	$start = 2000;
	$record = workspace_flow_records_test_row(5, 2003);

	pg_assert_same(array(), ws_flow_records_split(array($record), 1000, $start)['in'], 'not in the run that was working');

	$from = ws_flow_records_edge(array(array('started_at' => $start, 'flow_status' => 'done')), 500);
	pg_assert_same($start, $from, 'the next window opens at that start');
	pg_assert_same(array(5), array_column(ws_flow_records_split(array($record), $from, 3000)['in'], 'id'));
}

// A record dated before the window is not sent; it is counted as left out
// only when it was changed inside the window (set live then, as a draft
// usually is), not the whole archive.
function test_workspace_flow_records_left_out()
{
	$split = ws_flow_records_split(array(
		workspace_flow_records_test_row(6, 500, 1500),
		workspace_flow_records_test_row(7, 400, 900),
		workspace_flow_records_test_row(8, 300, 2500),
	), 1000, 2000);

	pg_assert_same(array(), $split['in']);
	pg_assert_same(1, $split['left_out_count'], 'changed inside the window');
	pg_assert_same(array(6), array_column($split['left_out'], 'id'));
}

// With no earlier run that sent, the window opens when the flow's version
// was published: a new digest never mails the archive.
function test_workspace_flow_records_first_run()
{
	pg_assert_same(1200, ws_flow_records_edge(array(), 1200), 'no earlier run');
	pg_assert_same(array(), ws_flow_records_split(array(workspace_flow_records_test_row(9, 1100)), 1200, 1300)['in'], 'the archive stays');
}

// A start that fires twice runs once: its anchor is claimed by one UPDATE
// before its run is written, and the flow's run keyed by that anchor run.
// An anchor run that started no flow run, or whose run failed or was
// cancelled, is no edge: its records go to the next run.
function test_workspace_flow_records_start_twice()
{
	$execute = workspace_flow_records_test_source('ws_scheduled_execute');
	$claim = strpos($execute, 'SET running_at');
	$record = strpos($execute, 'ws_scheduled_record_run(');
	pg_assert_true(($claim !== false) && ($record !== false) && ($claim < $record), 'claimed before the run is written');
	pg_assert_contains("'sched:'", workspace_flow_records_test_source('ws_flow_anchor_start'));

	pg_assert_same(1000, ws_flow_records_edge(array(
		array('started_at' => 3000, 'flow_status' => ''),
		array('started_at' => 2000, 'flow_status' => 'failed'),
		array('started_at' => 1500, 'flow_status' => 'cancelled'),
		array('started_at' => 1000, 'flow_status' => 'done'),
	), 10), 'only a run that sent is an edge');
	pg_assert_same(2000, ws_flow_records_edge(array(array('started_at' => 2000, 'flow_status' => 'waiting')), 10), 'an open run counts');
	pg_assert_same(2000, ws_flow_records_edge(array(array('started_at' => 2000, 'flow_status' => 'running')), 10));

	// After a failed run, the next one gives both windows' records.
	$from = ws_flow_records_edge(array(array('started_at' => 2000, 'flow_status' => 'failed'), array('started_at' => 1000, 'flow_status' => 'done')), 10);
	pg_assert_same(array(1, 2), array_column(ws_flow_records_split(array(
		workspace_flow_records_test_row(1, 1500),
		workspace_flow_records_test_row(2, 2500),
	), $from, 3000)['in'], 'id'));

	// An anchor run's flow run: by its start key, or by the anchor run a
	// Run now's context names.
	pg_assert_same(12, ws_flow_records_run_sched('sched:12', ''));
	pg_assert_same(13, ws_flow_records_run_sched('tok:abc', '{"trigger":"schedule","sched_run":13}'));
	pg_assert_same(0, ws_flow_records_run_sched('manual', '{"trigger":"manual"}'));
	pg_assert_same(0, ws_flow_records_run_sched('tok:abc', 'not json'));
}

// The moment a record went live: the list's one "date field is at most now"
// filter names it, and the record's date is the later of that and when it
// was made. None or two such filters on different fields: the moment it was
// made only. The same field twice (a list and its detail page both holding
// the filter, merged) is one field.
function test_workspace_flow_records_live_field()
{
	$live = array('field' => 'publish_date', 'operator' => 'is less than or equal to', 'value' => '', 'dynamic_value' => 'current date and time');

	pg_assert_same('publish_date', ws_flow_records_live_field(array(
		array('field' => 'status', 'operator' => 'is equal to', 'value' => 'Live', 'dynamic_value' => ''),
		array('field' => 'publish_date', 'operator' => 'is greater than or equal to', 'value' => '2000-01-01 00:00:00', 'dynamic_value' => ''),
		$live,
	)));
	pg_assert_same('publish_date', ws_flow_records_live_field(array(array('field' => 'Publish_Date', 'operator' => 'is less than', 'value' => '', 'dynamic_value' => 'current date') + $live)), 'its name as the filters compare it');
	pg_assert_same('', ws_flow_records_live_field(array()), 'none');
	pg_assert_same('', ws_flow_records_live_field(array($live, array('field' => 'go_live') + $live)), 'two');
	pg_assert_same('publish_date', ws_flow_records_live_field(array($live, $live)), 'the same filter twice');
	pg_assert_same('publish_date', ws_flow_records_live_field(array($live, array('field' => ' Publish_Date ', 'operator' => 'is less than', 'dynamic_value' => 'current date') + $live)), 'the same field twice, other case and spacing');
	pg_assert_same('', ws_flow_records_live_field(array($live, $live, array('field' => 'go_live') + $live)), 'two fields, one of them twice');
	pg_assert_same('', ws_flow_records_live_field(array(array('value' => '2026-01-01') + $live)), 'a fixed value is no moment of going live');
	pg_assert_same('', ws_flow_records_live_field(array(array('operator' => 'is greater than') + $live)));
	pg_assert_same('', ws_flow_records_live_field(array('x', null)));

	$at = strtotime('2026-11-10 09:00:00');
	pg_assert_same($at, ws_flow_records_date(100, '2026-11-10 09:00:00'), 'set live after it was made');
	pg_assert_same($at, ws_flow_records_date($at, '2026-01-01 00:00:00'), 'made after its date');
	pg_assert_same(100, ws_flow_records_date(100, ''), 'no date');
	pg_assert_same(100, ws_flow_records_date(100, 'soon'), 'not a date');
}

// A step kind of its own: a page and the option, nothing filled from the
// run, the count, the left-out count and the list for a For each box;
// classed as a read; the option asks for a flow that starts at a time.
function test_workspace_flow_records_kind()
{
	$kinds = ws_step_kinds();

	pg_assert_true(isset($kinds['list_records']));
	pg_assert_same('list_records', $kinds['list_records']['type']);
	pg_assert_same(array(), $kinds['list_records']['texts']);
	pg_assert_same(array('count', 'left_out', 'records'), array_column($kinds['list_records']['outputs'], 'key'));
	pg_assert_same(array('done', 'failed'), ws_flow_kinds()['list_records']['outcomes']);
	pg_assert_same(WS_TIER_READ, ws_flow_box_tiers()['list_records']);
	pg_assert_false(in_array('list_records', ws_flow_member_kinds(), true), 'not offered to a role-3 member\'s own flow');

	pg_assert_same(array('page_id' => 4, 'only_new' => 1), ws_step_config_normalize('list_records', array('page_id' => '4', 'only_new' => 'yes', 'x' => 1)));
	pg_assert_same(array('page_id' => 0, 'only_new' => 0), ws_step_config_normalize('list_records', array('page_id' => -3)));
	pg_assert_true(count(ws_step_config_problems('list_records', array('page_id' => 0, 'only_new' => 0), 'Read')) === 1, 'needs its page');
	pg_assert_same(array(), ws_step_config_problems('list_records', array('page_id' => 4, 'only_new' => 1), 'Read'));

	pg_assert_true(count(ws_flow_records_time_problems(array('page_id' => 4, 'only_new' => 1), 'Read', false)) === 1, 'only new needs a start at a time');
	pg_assert_same(array(), ws_flow_records_time_problems(array('page_id' => 4, 'only_new' => 1), 'Read', true));
	pg_assert_same(array(), ws_flow_records_time_problems(array('page_id' => 4, 'only_new' => 0), 'Read', false));
	pg_assert_true(ws_flow_records_starts_at_time(array('nodes' => array(array('kind' => 'schedule')))));
	pg_assert_false(ws_flow_records_starts_at_time(array('nodes' => array(array('kind' => 'manual')))));
	pg_assert_contains('ws_flow_records_time_problems(', workspace_flow_records_test_source('ws_flow_graph_check'));

	// Run, Try, and the window never read through the action row.
	pg_assert_contains('ws_step_list_records_do(', workspace_flow_records_test_source('ws_step_run'));
	pg_assert_contains('ws_step_list_records_try(', workspace_flow_records_test_source('ws_flow_box_try'));
	pg_assert_false(strpos(workspace_flow_records_test_source('ws_flow_records_window'), 'ws_step_action_row') !== false);
	pg_assert_contains("'last_run_at' => 0", workspace_flow_records_test_source('ws_step_action_row'), 'the action row is as it was');
}

// What it gives: the count, the left-out count and the list as JSON, whole
// items only while they fit.
function test_workspace_flow_records_data()
{
	$items = array(
		array('title' => 'A', 'summary' => 's', 'picture' => '', 'link' => 'https://x/a', 'date' => '2026-11-16 09:00'),
		array('title' => 'B', 'summary' => 's', 'picture' => '', 'link' => 'https://x/b', 'date' => '2026-11-16 10:00'),
	);
	$data = ws_flow_records_data($items, 3, 100000);

	pg_assert_same(2, $data['count']);
	pg_assert_same(3, $data['left_out']);
	pg_assert_same($items, json_decode($data['records'], true));
	pg_assert_same(1, count(json_decode(ws_flow_records_data($items, 0, 120)['records'], true)), 'whole items only');
	pg_assert_same(2, ws_flow_records_data($items, 0, 120)['count'], 'the count is every record');
}

// Try draws what it would read as the box's texts: one record a line with
// its date, then the titles left out; nothing when there is none.
function test_workspace_flow_records_try_texts()
{
	pg_assert_same(array(), ws_flow_records_try_texts(array(), array()));

	$texts = ws_flow_records_try_texts(array(array('title' => 'A', 'date' => '2026-11-16 09:00')), array('Old'));

	pg_assert_same(array('records', 'left_out'), array_column($texts, 'key'));
	pg_assert_contains('• A (2026-11-16 09:00)', $texts[0]['text']);
	pg_assert_contains('• Old', $texts[1]['text']);
	pg_assert_contains('ws_flow_records_try_texts(', workspace_flow_records_test_source('ws_step_list_records_try'));
}

// The canvas: the box in the palette's Records group while a list page is
// offered, its form (page and only new) and line, its icon; the lists come
// from ws_flow_js_config().
function test_workspace_flow_records_canvas()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_flow.js');
	$icons = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');

	pg_assert_contains("list_records: { name: 'fl_kind_list_records', group: 'system', into: true, out: ['done', 'failed'] }", $js);
	pg_assert_contains("['list_records', 'fl_kind_list_records']", $js);
	pg_assert_contains("((item[0] !== 'list_records') || (((FL.steps || {}).list_pages || []).length > 0))", $js, 'offered while a page is');
	pg_assert_contains("if (node.kind === 'list_records') {\n            recordsFields(box, node);", $js);
	pg_assert_contains("config = { page_id: (pages.length === 1) ? pages[0].id : 0, only_new: 0 };", $js);
	pg_assert_contains("t('fl_records_only_new_help')", $js, 'the note under the option');
	pg_assert_contains('list_records: [', $icons, 'its icon');

	$config = workspace_flow_records_test_source('ws_flow_js_config');
	pg_assert_contains("\$steps['list_pages'] = function_exists('ws_flow_records_pages') ? ws_flow_records_pages() : array();", $config);
	pg_assert_contains("\$steps['mail_groups'] = function_exists('ws_flow_mail_groups') ? ws_flow_mail_groups(\$viewer) : array();", $config);
}
