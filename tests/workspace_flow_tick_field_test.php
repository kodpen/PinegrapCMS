<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the ticks of a flow box's settings (field() in
 * assets/js/workspace_flow.js): a checkbox stands before its label on one
 * line with the help under the label, drawn with the look's .pg-checkbox,
 * and every tick handed to field() is such a checkbox.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function pg_test_flow_tick_source()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_flow.js');

	pg_assert_true(is_string($source) && $source !== '', 'workspace_flow.js is read');

	return str_replace("\r\n", "\n", $source);
}

// The body of field(), from its signature to the next function.
function pg_test_flow_field_body($source)
{
	$start = strpos($source, 'function field(label, control, help) {');

	pg_assert_true($start !== false, 'field() is found');

	$end = strpos($source, "\n    function ", $start + 10);

	return substr($source, $start, $end - $start);
}

function test_workspace_flow_tick_field_stands_before_its_label()
{
	$body = pg_test_flow_field_body(pg_test_flow_tick_source());

	pg_assert_contains("control.type === 'checkbox'", $body, 'a checkbox takes its own branch');

	$tick = strpos($body, 'if (tick) {');
	$control = strpos($body, 'line.appendChild(control);', $tick);
	$caption = strpos($body, 'words.appendChild(caption);', $tick);

	pg_assert_true($tick !== false && $control !== false && $caption !== false, 'the tick branch draws the box and its label');
	pg_assert_true($control < $caption, 'the box comes before its label');
	pg_assert_contains('words.appendChild(note);', $body, 'the help goes under the label');
}

function test_workspace_flow_tick_field_uses_the_look_checkbox()
{
	$source = pg_test_flow_tick_source();

	pg_assert_same(0, preg_match_all("/el\\('input', 'check'\\)/", $source), 'no tick of the old check class is drawn');

	// Every tick handed to field() is built as a .pg-checkbox.
	preg_match_all("/field\\([^;]*?, (\\w+)(?:, [^;]*)?\\)\\);/", $source, $calls);

	$ticks = array();

	foreach (array_unique($calls[1]) as $name) {
		if (preg_match('/var ' . $name . " = el\\('input', '([^']*)'\\);\\s*\\n\\s*" . $name . "\\.type = 'checkbox';/", $source, $made)) {
			$ticks[$name] = $made[1];
		}
	}

	pg_assert_true(count($ticks) >= 5, 'the ticks handed to field() are found');

	foreach ($ticks as $name => $class) {
		pg_assert_same('pg-checkbox', $class, $name . ' is a .pg-checkbox');
	}
}
