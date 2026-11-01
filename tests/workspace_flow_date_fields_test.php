<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Flows editor's date and time fields on the panel's one
 * date-and-time control: every date, time and datetime-local input
 * assets/js/workspace_flow.js builds goes through the dateField helper
 * workspace.js lends it (pgDateField()), keeps the ISO value the flow's
 * config stores, and listens to the change event the drawn box fires.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function workspace_flow_date_source($path)
{
	return (string)file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

function test_workspace_kit_lends_the_date_field_helper()
{
	$src = workspace_flow_date_source('assets/js/workspace.js');

	pg_assert_true(strpos($src, 'dateField: pgDateField,') !== false, 'the kit lends pgDateField()');
	pg_assert_true(strpos($src, 'dateShown: dateShown,') !== false, 'the kit lends dateShown()');
}

function test_flow_date_inputs_carry_their_hook()
{
	$src = workspace_flow_date_source('assets/js/workspace_flow.js');

	pg_assert_same(0, preg_match_all("/\\.type = '(date|time|datetime-local)'/", $src), 'no date, time or datetime-local input is made without the kit\'s dateField()');
	pg_assert_same(0, preg_match("/time: 'time'/", $src), 'the template setup\'s time answer is not a bare native time input');
	pg_assert_same(1, preg_match('/function dateInput\(type, value, onValue\) \{/', $src), 'one helper builds the date and time inputs');
	pg_assert_same(1, preg_match("/K\\.dateField\\(input, type\\);/", $src), 'the helper hooks its input through the kit');
	pg_assert_true(strpos($src, "input.addEventListener('change', function () { onValue(input.value); });") !== false, 'the helper reads the value on the change the drawn box fires');
	pg_assert_same(5, preg_match_all("/dateInput\\('(date|time|datetime-local)', /", $src), 'the schedule date, time and last day, the condition hours and the template time go through the helper');
	pg_assert_same(1, preg_match("/dateInput\\(\\(kind === 'date'\\) \\? 'date' : 'datetime-local', /", $src), 'a record field of a date or a date and time kind keeps its native store');
	pg_assert_true(strpos($src, "String(value || '').replace(' ', 'T').slice(0, 16)") !== false, 'a date and time value still goes in as Y-m-d\\TH:i');
	pg_assert_true(strpos($src, "typed.replace('T', ' ').slice(0, 16) + ':00'") !== false, 'and is still stored as Y-m-d H:i:s');
}
