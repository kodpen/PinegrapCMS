<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the edit pencil on calendar events drawn by the calendar system
 * widgets: who may edit an event (the rule edit_calendar_event.php applies),
 * the edit screen's address with the occurrence's number, the calendar tone
 * of the chip, and that both calendar renderers draw it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Staff (role 0-2) edit every event, whatever their calendars and rights.
function test_widget_calendar_edit_chip_rule_staff()
{
	foreach (array(0, 1, 2) as $role) {
		pg_assert_true(pg_sw_calendar_event_edit_rule($role, false, false, array(), array(4), true), 'role ' . $role);
	}
}

// A user (role 3) needs the calendars right and one of the event's calendars.
function test_widget_calendar_edit_chip_rule_user_calendars()
{
	pg_assert_false(pg_sw_calendar_event_edit_rule(3, false, true, array(4), array(4), true), 'no calendars right');
	pg_assert_true(pg_sw_calendar_event_edit_rule(3, true, true, array(2, 4), array(4, 9), true), 'shared calendar');
	pg_assert_true(pg_sw_calendar_event_edit_rule(3, true, true, array('4'), array('4'), true), 'ids as strings');
	pg_assert_false(pg_sw_calendar_event_edit_rule(3, true, true, array(2), array(4, 9), true), 'no shared calendar');
	pg_assert_false(pg_sw_calendar_event_edit_rule(3, true, true, array(), array(4), true), 'no calendar of their own');
	pg_assert_false(pg_sw_calendar_event_edit_rule(3, true, true, array(4), array(), true), 'event in no calendar');
}

// A published event also needs the right to publish events; a draft does not.
function test_widget_calendar_edit_chip_rule_publish_right()
{
	pg_assert_false(pg_sw_calendar_event_edit_rule(3, true, false, array(4), array(4), true), 'published, no publish right');
	pg_assert_true(pg_sw_calendar_event_edit_rule(3, true, false, array(4), array(4), false), 'draft, no publish right');
}

// The edit screen's address carries the occurrence's number before send_to,
// and an address without extra parameters is the one it always was.
function test_widget_calendar_edit_chip_backend_url_params()
{
	$saved = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : null;
	$_SERVER['REQUEST_URI'] = '/events?date=10-01-2026&view=monthly';

	try {
		$back = '&send_to=' . urlencode('/events?date=10-01-2026&view=monthly');

		$with = pg_sw_backend_edit_url('edit_calendar_event.php', '12', array('recurrence_number' => 3));
		pg_assert_contains('/edit_calendar_event.php?id=12&recurrence_number=3' . $back, $with, 'with the occurrence');
		pg_assert_same(strlen($with) - strlen($back), strpos($with, $back), 'send_to is last');

		$plain = pg_sw_backend_edit_url('edit_product.php', 7);
		pg_assert_contains('/edit_product.php?id=7' . $back, $plain, 'without parameters');
		pg_assert_same(false, strpos($plain, 'recurrence_number'), 'no stray parameter');
	} finally {
		if ($saved === null) {
			unset($_SERVER['REQUEST_URI']);
		} else {
			$_SERVER['REQUEST_URI'] = $saved;
		}
	}
}

// The pencil lands in the event row's first element with the calendar tone,
// its address escaped once.
function test_widget_calendar_edit_chip_tone_and_host()
{
	$row = '<div class="d-flex gap-3"><p>23</p><h3><a href="/event?id=12">Talk</a></h3></div>';
	$out = pg_sw_add_edit_chip($row, '/software/edit_calendar_event.php?id=12&recurrence_number=3', 'Edit the event', 'calendar');

	pg_assert_contains('pg-edit-chip-calendar', $out, 'calendar tone');
	pg_assert_contains('<div class="d-flex gap-3 pg-edit-host"', $out, 'row is the host');
	pg_assert_contains('href="/software/edit_calendar_event.php?id=12&amp;recurrence_number=3"', $out, 'address escaped once');
	pg_assert_same(1, substr_count($out, 'href="/software/edit_calendar_event.php'), 'one pencil');
}

// Both calendar renderers draw the pencil through the same helper, and the
// helper asks the calendar rule before it draws.
function test_widget_calendar_edit_chip_renderers_call_it()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets.php');

	$list = strpos($source, 'function _render_system_widget_calendar_view(');
	$event = strpos($source, 'function _render_system_widget_calendar_event_view(');
	$end = strpos($source, 'function pg_sw_edit_chips_on(');
	pg_assert_true(($list !== false) && ($event !== false) && ($end !== false) && ($list < $event) && ($event < $end), 'renderers found');

	$list_body = substr($source, $list, $event - $list);
	$event_body = substr($source, $event, $end - $event);
	pg_assert_same(1, substr_count($list_body, '_pg_sw_calendar_event_edit_chip('), 'calendar_view rows');
	pg_assert_same(1, substr_count($event_body, '_pg_sw_calendar_event_edit_chip('), 'calendar_event_view');

	$helper = strpos($source, 'function _pg_sw_calendar_event_edit_chip(');
	$helper_body = substr($source, $helper, strpos($source, "\n}\n", $helper) - $helper);
	pg_assert_contains('pg_sw_can_edit_calendar_event(', $helper_body, 'permission asked');
	pg_assert_contains("'edit_calendar_event.php'", $helper_body, 'edit screen');
	pg_assert_contains("'calendar'", $helper_body, 'calendar tone');
	pg_assert_contains("lang('Edit the event')", $helper_body, 'translated title');
}
