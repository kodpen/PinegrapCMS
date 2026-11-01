<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the bookshop template's events (includes/design_templates/
 * pinegrap-bookshop.php): the events are records of a collection form the
 * staff add to on the New Event page, its first events are dated from the
 * day the template is opened, and the home page and the events page list
 * them from today on, the nearest first, so a past event drops out. No
 * fixed date is left in the template's trees.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _bookshop_events_tpl()
{
	static $tpl = null;
	if ($tpl === null) {
		$tpl = _pg_tpl_include(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-bookshop.php');
	}
	return $tpl;
}

function _bookshop_events_page($key)
{
	foreach (_bookshop_events_tpl()['pages'] as $p) {
		if (isset($p['key']) && $p['key'] === $key) {
			return $p;
		}
	}
	return null;
}

// Every node of a tree, in document order.
function _bookshop_events_nodes($node)
{
	$out = array();
	if (!is_array($node)) {
		return $out;
	}
	if (isset($node['type'])) {
		$out[] = $node;
	}
	foreach ((isset($node['children']) && is_array($node['children'])) ? $node['children'] : array() as $child) {
		$out = array_merge($out, _bookshop_events_nodes($child));
	}
	return $out;
}

function _bookshop_events_attr($node, $name)
{
	foreach ((isset($node['props']['_attrs']) && is_array($node['props']['_attrs'])) ? $node['props']['_attrs'] : array() as $a) {
		if (isset($a['name']) && $a['name'] === $name) {
			return (string) $a['value'];
		}
	}
	return null;
}

// The fields of the events form: name => the control's node.
function _bookshop_events_fields()
{
	$fields = array();
	foreach (_bookshop_events_nodes(_bookshop_events_tpl()['widgets']['event_form']['tree']) as $n) {
		$tag = isset($n['props']['tag']) ? $n['props']['tag'] : '';
		$name = _bookshop_events_attr($n, 'name');
		if (in_array($tag, array('input', 'select', 'textarea'), true) && $name !== null) {
			$fields[$name] = $n;
		}
	}
	return $fields;
}

// The New Event page: a collection form whose first events are the
// template's, each on its weekday within the next three weeks.
function test_bookshop_events_form()
{
	$tpl = _bookshop_events_tpl();
	pg_assert_true(version_compare($tpl['version'], '1.3.0', '>='), 'the version moved');

	$page = _bookshop_events_page('event_new');
	pg_assert_true(is_array($page), 'the New Event page');
	if (!is_array($page)) {
		return;
	}
	pg_assert_same('private', $page['folder'], 'in the staff area');
	pg_assert_same(1, $page['form']['collection'], 'a collection form');
	pg_assert_same('{{tab:events}}', $page['form']['confirmation_page_id'], 'sending it opens the events page');
	pg_assert_contains('"templateWidget":"event_form"', json_encode($page['tree']), 'the form on its page');

	$fields = _bookshop_events_fields();
	$names = array_keys($fields);
	sort($names);
	pg_assert_same(array('event_date', 'event_time', 'kind', 'place', 'summary', 'title'), $names, 'the form\'s fields');
	pg_assert_same('date', isset($fields['event_date']) ? _bookshop_events_attr($fields['event_date'], 'type') : null, 'the date is a date field');
	$kinds = array();
	foreach (isset($fields['kind']['children']) ? $fields['kind']['children'] : array() as $option) {
		$value = _bookshop_events_attr($option, 'value');
		if ($value !== null && $value !== '') {
			$kinds[] = $value;
		}
	}

	$records = $page['form']['sample_records'];
	pg_assert_same(5, count($records), 'five first events');
	$today = new DateTime('today');
	$last = (new DateTime('today'))->modify('+21 days');
	foreach ($records as $r) {
		$keys = array_keys($r);
		sort($keys);
		pg_assert_same($names, $keys, $r['title'] . ': one value for each field');
		$day = DateTime::createFromFormat('!Y-m-d', $r['event_date']);
		pg_assert_true($day instanceof DateTime, $r['title'] . ': a date');
		if (!($day instanceof DateTime)) {
			continue;
		}
		pg_assert_true($day > $today && $day <= $last, $r['title'] . ': still to come, within three weeks');
		$weekday = (int) $day->format('N');
		$evening = ($r['kind'] === lang('Reading night') || $r['kind'] === lang('Book club'));
		pg_assert_same($evening ? 4 : 6, $weekday, $r['title'] . ': ' . ($evening ? 'a Thursday evening' : 'a Saturday'));
		pg_assert_true(in_array($r['kind'], $kinds, true), $r['title'] . ': a kind the form offers');
		pg_assert_true((bool) preg_match('/^\d{2}:\d{2}$/', $r['event_time']), $r['title'] . ': a time');
	}
}

// The home page's next three events and the events page's list: the events
// form's records from today on, the nearest first, drawn from its fields.
function test_bookshop_events_lists()
{
	$tpl = _bookshop_events_tpl();
	$fields = array_keys(_bookshop_events_fields());
	$today_on = array(array('field' => 'event_date', 'operator' => 'is greater than or equal to', 'value' => '', 'dynamic_value' => 'current date'));
	foreach (array('home_events' => array('home', 3), 'events_list' => array('events', 0)) as $key => $want) {
		$w = isset($tpl['widgets'][$key]) ? $tpl['widgets'][$key] : null;
		pg_assert_true(is_array($w), $key . ' exists');
		if (!is_array($w)) {
			continue;
		}
		$cfg = $w['config'];
		pg_assert_same($want[0], $w['page'], $key . ': its page');
		pg_assert_same('form_list_view', $cfg['regionType'], $key . ': a list of the form\'s records');
		pg_assert_same('{{tab:event_new}}', $cfg['custom_form_page_id'], $key . ': the events form');
		pg_assert_same('event_date', $cfg['order_by_field'], $key . ': by date');
		pg_assert_same('ASC', $cfg['order_by_direction'], $key . ': the nearest first');
		pg_assert_same($today_on, $cfg['filters'], $key . ': from today on');
		pg_assert_same($want[1], isset($cfg['max_results']) ? $cfg['max_results'] : 0, $key . ': ' . ($want[1] ? 'the next ' . $want[1] : 'every one'));
		pg_assert_true(!empty($cfg['empty_message']), $key . ': a sentence while there is none');

		$loops = 0;
		$bound = array();
		foreach (_bookshop_events_nodes($w['tree']) as $n) {
			$loops += ($n['type'] === 'loop_area') ? 1 : 0;
			if (isset($n['props']['_bindings']['text'])) {
				$bound[] = $n['props']['_bindings']['text'];
			}
		}
		pg_assert_same(1, $loops, $key . ': one card for each event');
		pg_assert_same(array(), array_values(array_diff($bound, $fields)), $key . ': every value is a field of the form');
		foreach (array('title', 'event_date', 'event_time', 'kind', 'place') as $f) {
			pg_assert_true(in_array($f, $bound, true), $key . ': shows ' . $f);
		}

		$page = _bookshop_events_page($want[0]);
		pg_assert_contains('"templateWidget":"' . $key . '"', json_encode($page['tree']), $key . ': placed on ' . $want[0]);
	}
	// The installer's preview draws both pages from their trees, the sample
	// card repeated.
	foreach (array('home', 'events') as $key) {
		$html = pg_design_template_preview_html('pinegrap-bookshop', $key);
		pg_assert_true(substr_count($html, h(lang('Poems after Dark'))) >= 3, $key . ': the preview shows the sample event');
		pg_assert_false(strpos(preg_replace('/href="[^"]*"/', '', $html), '^^') !== false, $key . ': no ^^ outside an address');
	}
	pg_assert_contains('{{page:event_new}}', json_encode($tpl['shared']['staff_bar']['tree']), 'the staff bar opens the New Event page');
	pg_assert_contains('{{page:event_new}}', json_encode(_bookshop_events_page('staff')['tree']), 'and so does the staff area');
}

// No event date is written into a page, a widget or a shared part: every
// date on the site comes from a record.
function test_bookshop_events_no_fixed_dates()
{
	$tpl = _bookshop_events_tpl();
	$trees = array();
	foreach ($tpl['pages'] as $p) {
		$trees['page ' . $p['key']] = $p['tree'];
	}
	foreach ($tpl['widgets'] as $key => $w) {
		$trees['widget ' . $key] = $w['tree'];
	}
	foreach ($tpl['shared'] as $key => $s) {
		$trees['shared ' . $key] = $s['tree'];
	}
	foreach ($trees as $where => $tree) {
		pg_assert_false((bool) preg_match('/\b20\d\d-\d\d-\d\d/', json_encode($tree)), $where . ': no fixed date');
	}
}
