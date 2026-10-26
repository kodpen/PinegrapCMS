<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the calendar view's grid lists (includes/fn/widgets_lists_calendar.php):
 * the column heads, the weeks, the days and the events of a day as lists a
 * designer nests into a month or week grid, drawn by the finish pass from
 * the rows the calendar renderer publishes.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _widget_cal_node($type, $props, $children = array())
{
	return array('type' => $type, 'props' => $props, 'children' => $children);
}

function _widget_cal_box($tag, $class, $children = array(), $extra = array())
{
	return _widget_cal_node('semantic', array('tag' => $tag, 'cssClass' => $class) + $extra, $children);
}

function _widget_cal_text($tag, $class, $token, $extra = array())
{
	return _widget_cal_box($tag, $class, array(), array('text' => 'Sample', '_bindings' => array('text' => $token)) + $extra);
}

function _widget_cal_list($source, $children, $props = array())
{
	return _widget_cal_node('loop_area', array('source' => $source) + $props, $children);
}

// One event as _pg_sw_calendar_event_values() gives it.
function _widget_cal_event($title, $time = '', $url = '')
{
	$vals = array();
	foreach (pg_sw_list_calendar_sources()['calendar.events']['tokens'] as $pair) $vals[$pair[0]] = '';
	return array_merge($vals, array('__event_title' => $title, '__event_time' => $time, '__event_url' => $url));
}

// A month grid as a designer builds it: a table, the heads from the
// weekdays list, a row per week, a cell per day holding its number and its
// events. $cell_extra joins the cell's children.
function _widget_cal_grid_tree($cell_extra = array())
{
	$event = _widget_cal_box('li', 'ev', array(
		_widget_cal_box('a', 'evl', array(), array('href' => '#', 'text' => 'Event', '_bindings' => array('href' => '__event_url', 'text' => '__event_title'))),
	));
	$cell = _widget_cal_box('td', 'day', array_merge(array(
		_widget_cal_text('div', 'num', '__day_number'),
		_widget_cal_box('ul', 'evs', array(_widget_cal_list('calendar.events', array($event)))),
	), $cell_extra));
	return _widget_cal_node('root', array(), array(
		_widget_cal_box('table', 'grid', array(
			_widget_cal_box('thead', '', array(_widget_cal_box('tr', '', array(
				_widget_cal_list('calendar.weekdays', array(_widget_cal_text('th', 'head', '__weekday_short'))),
			)))),
			_widget_cal_box('tbody', '', array(
				_widget_cal_list('calendar.weeks', array(_widget_cal_box('tr', 'week', array(
					_widget_cal_list('calendar.days', array($cell)),
				)))),
			)),
		)),
	));
}

// What the calendar renderer does with a tree that has no event loop: the
// tree drawn in a calendar_view scope, the grid rows published, the static
// keys filled, swept, then the finish pass.
function _widget_cal_finished($tree, $rows)
{
	$scope = pg_sw_finish_open(array('sid' => 41, 'kind' => 'calendar_view', 'email' => false));
	try {
		$html = trim(_render_tree_node($tree, 0, 0));
		pg_sw_list_publish('calendar', $rows);
		$html = pg_sw_sweep_tokens(_pg_sw_fill_tokens($html, pg_sw_escape_token_values(array('__week_key' => '0'))));
	} finally {
		pg_sw_finish_close();
	}
	return pg_sw_finish_widget_html($html, $scope);
}

function _widget_cal_texts($html, $class)
{
	preg_match_all('#<[a-z][a-z0-9]* class="' . preg_quote($class, '#') . '"[^>]*>(.*?)</#s', $html, $m);
	return $m[1];
}

// October 2026 from Monday: Thursday the 1st, so the grid opens on Monday
// 28 September and closes on Sunday 1 November, five weeks.
function _widget_cal_october($events = array(), $week_start = 'monday')
{
	$lead  = ($week_start === 'sunday') ? 4 : 3;
	$trail = ($week_start === 'sunday') ? 0 : 1;
	return pg_sw_list_calendar_rows(mktime(0, 0, 0, 9, 31 - $lead, 2026), mktime(0, 0, 0, 10, 31 + $trail, 2026),
		10, $events, $week_start, 'd.m.Y', '2026-10-26');
}

function test_widget_lists_calendar_sources()
{
	$sources = pg_sw_list_calendar_sources();
	pg_assert_same(array('calendar.weekdays', 'calendar.weeks', 'calendar.days', 'calendar.events'), array_keys($sources));
	foreach ($sources as $source => $d) {
		pg_assert_same(array('family', 'key', 'label', 'arg', 'arg_required', 'tokens'), array_keys($d), $source . ' shape');
		pg_assert_same('calendar', $d['family']);
		pg_assert_true(isset(pg_sw_list_sources()[$source]), $source . ' is a list source');
	}
	pg_assert_same(array_keys($sources), array_keys(pg_sw_kind_lists('calendar_view')));
	pg_assert_same(array(), pg_sw_kind_lists('calendar_event_view'));
	pg_assert_true(!isset(pg_sw_kind_lists('form_item_view')['calendar.days']), 'no grid in a form view');

	// Every row carries exactly its source's tokens.
	$rows = _widget_cal_october(array('2026-10-05' => array(_widget_cal_event('A'))));
	foreach (array('calendar.weekdays' => 0, 'calendar.weeks' => 0, 'calendar.days' => 0, 'calendar.events' => 20261005) as $source => $key) {
		$tokens = array();
		foreach ($sources[$source]['tokens'] as $pair) $tokens[] = $pair[0];
		pg_assert_true(!empty($rows[$source][$key]), $source . ' gives rows');
		foreach ($rows[$source][$key] as $row) pg_assert_same($tokens, array_keys($row), $source . ' row keys');
	}
	// The event values come from the calendar's own function.
	$vals = _pg_sw_calendar_event_values(array('id' => 1, 'name' => 'A', 'short_description' => '', 'event_start_date' => '2026-10-05',
		'event_start_time' => '10:00:00', 'all_day' => 0, 'recurrence_number' => 0, 'calendars' => array()), null, '', 'd.m.Y', array());
	$tokens = array();
	foreach ($sources['calendar.events']['tokens'] as $pair) $tokens[] = $pair[0];
	pg_assert_same($tokens, array_keys($vals), 'the events list carries the event loop tokens');
}

function test_widget_lists_calendar_rows()
{
	$rows = _widget_cal_october(array(
		'2026-09-28' => array(_widget_cal_event('Before')),
		'2026-10-05' => array(_widget_cal_event('A', '10:00', '/e?id=1'), _widget_cal_event('B')),
	));
	pg_assert_same(5, count($rows['calendar.weeks'][0]));
	pg_assert_same(array('1', '40'), array_values($rows['calendar.weeks'][0][0]));
	pg_assert_same('44', $rows['calendar.weeks'][0][4]['__week_number']);
	pg_assert_same(35, count($rows['calendar.days'][0]));
	pg_assert_same(7, count($rows['calendar.days'][3]));
	pg_assert_true(!isset($rows['calendar.days'][6]), 'no sixth week');

	$first = $rows['calendar.days'][1][0];
	pg_assert_same('20260928', $first['__day_key']);
	pg_assert_same('28', $first['__day_number']);
	pg_assert_same('28.09.2026', $first['__day_date']);
	pg_assert_same('1', $first['__day_is_outside']);
	pg_assert_same('0', $first['__day_event_count'], 'a padding day holds no events');
	pg_assert_true(!isset($rows['calendar.events'][20260928]));

	$fifth = $rows['calendar.days'][2][0];
	pg_assert_same(array('20261005', '5', '', '', '2'), array($fifth['__day_key'], $fifth['__day_number'], $fifth['__day_is_outside'], $fifth['__day_is_today'], $fifth['__day_event_count']));
	pg_assert_same('1', $rows['calendar.days'][5][0]['__day_is_today'], 'Monday 26 October is today');
	pg_assert_same(array('/e?id=1', '#'), array($rows['calendar.events'][20261005][0]['__event_url'], $rows['calendar.events'][20261005][1]['__event_url']));

	pg_assert_same(lang('Mon'), $rows['calendar.weekdays'][0][0]['__weekday_short']);
	pg_assert_same(lang('Sunday'), $rows['calendar.weekdays'][0][6]['__weekday_name']);

	// From Sunday: the first column is Sunday, the first week still ISO 40.
	$rows = _widget_cal_october(array(), 'sunday');
	pg_assert_same(lang('Sunday'), $rows['calendar.weekdays'][0][0]['__weekday_name']);
	pg_assert_same('20260927', $rows['calendar.days'][1][0]['__day_key']);
	pg_assert_same('40', $rows['calendar.weeks'][0][0]['__week_number']);
	pg_assert_same(35, count($rows['calendar.days'][0]));

	// A week (no focus month): nothing is outside.
	$week = pg_sw_list_calendar_rows(mktime(0, 0, 0, 9, 28, 2026), mktime(0, 0, 0, 10, 4, 2026), null,
		array('2026-09-28' => array(_widget_cal_event('W'))), 'monday', 'd.m.Y', '2026-10-26');
	pg_assert_same(1, count($week['calendar.weeks'][0]));
	pg_assert_same('', $week['calendar.days'][1][0]['__day_is_outside']);
	pg_assert_same('W', $week['calendar.events'][20260928][0]['__event_title']);
}

function test_widget_lists_calendar_grid_end_to_end()
{
	$out = _widget_cal_finished(_widget_cal_grid_tree(), _widget_cal_october(array(
		'2026-10-05' => array(_widget_cal_event('Tom & Jerry', '', '/e?id=1&recurrence_number=2'), _widget_cal_event('B')),
		'2026-10-06' => array(_widget_cal_event('C')),
	)));
	pg_assert_same(false, strpos($out, 'pg-list'), 'no span left');
	pg_assert_same(false, strpos($out, '^^'), 'no token left');
	pg_assert_same(false, strpos($out, pg_sw_marker_nonce()), 'no marker left');
	pg_assert_same(array(lang('Mon'), lang('Tue'), lang('Wed'), lang('Thu'), lang('Fri'), lang('Sat'), lang('Sun')), _widget_cal_texts($out, 'head'));
	pg_assert_same(5, substr_count($out, 'class="week"'));
	pg_assert_same(35, substr_count($out, 'class="day"'));
	$nums = _widget_cal_texts($out, 'num');
	pg_assert_same(array('28', '29', '30', '1', '2', '3', '4', '5', '6'), array_slice($nums, 0, 9));
	pg_assert_same(array('Tom &amp; Jerry', 'B', 'C'), _widget_cal_texts($out, 'evl'), 'events in their days, escaped once');
	pg_assert_true(strpos($out, 'href="/e?id=1&amp;recurrence_number=2"') !== false, 'the event link');
	pg_assert_same(35, substr_count($out, 'class="evs"'), 'a day without events keeps its box');
	preg_match_all('/\bid="([^"]+)"/', $out, $ids);
	pg_assert_same(count($ids[1]), count(array_unique($ids[1])), 'every id distinct');
}

// A day's own design: the number only inside the month, a word on an
// empty day, a mark on today, each a condition on the day row.
function test_widget_lists_calendar_day_conditions()
{
	$cond = function ($node, $token, $op, $value = null) {
		$node['props']['_bindings']['show_if'] = $token;
		$node['props']['_bindParams'] = array('show_if' => ($value === null) ? array('op' => $op) : array('op' => $op, 'value' => $value));
		return $node;
	};
	$tree = _widget_cal_grid_tree(array(
		$cond(_widget_cal_box('span', 'free', array(), array('text' => 'Free')), '__day_event_count', 'eq', '0'),
		$cond(_widget_cal_box('span', 'today', array(), array('text' => 'Today')), '__day_is_today', 'not_empty'),
		$cond(_widget_cal_text('span', 'inside', '__day_number'), '__day_is_outside', 'empty'),
	));
	$out = _widget_cal_finished($tree, _widget_cal_october(array('2026-10-05' => array(_widget_cal_event('A')))));
	pg_assert_same(34, count(_widget_cal_texts($out, 'free')), 'every day but the 5th is free');
	pg_assert_same(array('Today'), _widget_cal_texts($out, 'today'));
	$inside = _widget_cal_texts($out, 'inside');
	pg_assert_same(31, count($inside), 'the days of October');
	pg_assert_same(array('1', '31'), array($inside[0], $inside[30]));
}

// A days list outside a weeks list lists every day of the grid (a CSS grid
// of seven columns); an events list outside a day draws nothing.
function test_widget_lists_calendar_flat_days()
{
	$tree = _widget_cal_node('root', array(), array(
		_widget_cal_box('div', 'g', array(_widget_cal_list('calendar.days', array(_widget_cal_text('div', 'cell', '__day_number'))))),
		_widget_cal_box('div', 'stray', array(_widget_cal_list('calendar.events', array(_widget_cal_text('i', 'e', '__event_title'))))),
	));
	$out = _widget_cal_finished($tree, _widget_cal_october(array('2026-10-05' => array(_widget_cal_event('A')))));
	pg_assert_same(35, count(_widget_cal_texts($out, 'cell')));
	pg_assert_same(array(), _widget_cal_texts($out, 'e'));
}

// Nothing published (the upcoming view): every grid list draws nothing.
function test_widget_lists_calendar_nothing_published()
{
	$out = _widget_cal_finished(_widget_cal_grid_tree(), array());
	pg_assert_same(0, substr_count($out, 'class="week"'));
	pg_assert_same(array(), _widget_cal_texts($out, 'head'));
	pg_assert_same(false, strpos($out, 'pg-l'));
}
