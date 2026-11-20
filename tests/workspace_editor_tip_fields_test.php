<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the fields of the writing box's tips on a phone
 * (assets/js/workspace_editor.js). iOS Safari zooms the page in when a field
 * under 16px takes the focus and leaves it zoomed; form-control-sm is 14px.
 * Run under Node: tipFieldClass() answers the full-size field on a touch
 * screen and the small one elsewhere. The rest holds the wiring: the link
 * tip's Text and address fields, the find tip's two fields and the fields of
 * the insert menu's windows ask it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/workspace_editor_link_keys_test.php');

// A touch screen gets the 16px field, a mouse the small one.
function test_workspace_editor_tip_field_class()
{
	$answer = workspace_editor_link_keys_test_run(array('tipFieldClass'), 'cases.map(function (touch) { return tipFieldClass(touch); })', array(true, false));

	if ($answer === null) {
		return;
	}

	pg_assert_same('form-control', $answer[0], 'A touch screen gets the full-size field');
	pg_assert_same('form-control form-control-sm', $answer[1], 'A mouse gets the small field');
}

// The windows of the insert menu: the block's title, the table's formula
// line, the code block's language, the calculation's decimals and unit.
function test_workspace_editor_window_fields_ask_the_class()
{
	$checks = array(
		'titled'     => "field = el('input', tipFieldClass(touchScreen()));",
		'openTable'  => "el('input', tipFieldClass(touchScreen()) + ' ws-sheet-formula')",
		'openCode'   => "el('input', tipFieldClass(touchScreen()) + ' ws-code-language')",
		'openCalc'   => "el('select', tipFieldClass(touchScreen()))",
	);

	foreach ($checks as $name => $needle) {
		$part = workspace_editor_lists_test_function($name);
		pg_assert_true(strpos($part, $needle) !== false, $name . ' builds its field from tipFieldClass()');
		pg_assert_same(false, strpos($part, "'form-control form-control-sm"), 'No small field is left in ' . $name);
	}
}

// The link tip and the find tip build their fields from it.
function test_workspace_editor_tip_fields_ask_the_class()
{
	$link = workspace_editor_lists_test_function('openLink');
	$find = workspace_editor_lists_test_function('openFind');

	pg_assert_same(2, substr_count($link, "el('input', tipFieldClass(touchScreen()))"), 'The link tip\'s Text and address fields');
	pg_assert_same(false, strpos($link, "el('input', 'form-control form-control-sm')"), 'No small field is left in the link tip');
	pg_assert_same(2, substr_count($find, "el('input', tipFieldClass(touchScreen()))"), 'The find tip\'s find and replace fields');
	pg_assert_same(false, strpos($find, "el('input', 'form-control form-control-sm')"), 'No small field is left in the find tip');
}
