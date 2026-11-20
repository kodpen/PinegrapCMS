<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Workspace writing box on a phone: below 768px the field
 * takes the full width on top and the row of tools with Send sits under it,
 * as on a wide screen, with no switch that hides the tools; the row wraps
 * and Send keeps a narrow pad, so it is never cut at 375 px or in the
 * narrow discussion panel. Pins assets/js/workspace.js and
 * assets/css/backend.src.css.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function workspace_composer_phone_test_css()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/backend.src.css');
}

function workspace_composer_phone_test_js()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');
}

// The rules of every phone block (@media (max-width: 767.98px)) of the sheet.
function workspace_composer_phone_test_phone_css()
{
	$css = workspace_composer_phone_test_css();
	$out = '';
	$from = 0;

	while (($at = strpos($css, '@media (max-width: 767.98px) {', $from)) !== false) {
		$depth = 0;
		$len = strlen($css);

		for ($i = strpos($css, '{', $at); $i < $len; $i++) {
			if ($css[$i] === '{') {
				$depth++;
			} elseif ($css[$i] === '}') {
				$depth--;

				if ($depth === 0) {
					break;
				}
			}
		}

		$out .= substr($css, $at, $i - $at + 1) . "\n";
		$from = $i;
	}

	return $out;
}

// The declarations of the first rule whose selector is exactly $selector.
function workspace_composer_phone_test_rule($css, $selector)
{
	if (!preg_match('/(?:^|\n)\s*' . preg_quote($selector, '/') . '\s*\{([^}]*)\}/', $css, $m)) {
		return null;
	}

	return $m[1];
}

function test_workspace_composer_phone_has_no_tools_switch()
{
	$js = workspace_composer_phone_test_js();
	$css = workspace_composer_phone_test_css();

	foreach (array('ws-composer-more', 'is-tools-open', 'composer_tools') as $name) {
		pg_assert_true(strpos($js, $name) === false, $name . ' is gone from workspace.js');
		pg_assert_true(strpos($css, $name) === false, $name . ' is gone from backend.src.css');
	}
}

function test_workspace_composer_phone_field_on_top_tools_under_it()
{
	$phone = workspace_composer_phone_test_phone_css();
	pg_assert_true($phone !== '', 'the sheet has a phone block');

	$box = workspace_composer_phone_test_rule($phone, '.ws-composer');
	pg_assert_true($box !== null, 'the phone block styles the box');
	pg_assert_true(strpos($box, 'display') === false, 'the box is not made one row on a phone');
	pg_assert_true(strpos($phone, '.ws-composer > :is(.ws-rich, textarea)') === false, 'the field is not squeezed into a row');
	pg_assert_true(strpos($phone, '.ws-composer-tools > :not(') === false, 'no tool is hidden on a phone');

	$js = workspace_composer_phone_test_js();
	$input = strpos($js, '            box.appendChild(input);');
	$tools = strpos($js, "el('div', 'ws-composer-tools", $input === false ? 0 : $input);
	pg_assert_true($input !== false, 'composer() puts the field in the box');
	pg_assert_true($tools !== false, 'the row of tools is built after the field');
}

function test_workspace_composer_phone_send_never_cut()
{
	$css = workspace_composer_phone_test_css();
	$row = workspace_composer_phone_test_rule($css, '.ws-composer-tools');
	pg_assert_true($row !== null && strpos($row, 'flex-wrap: wrap') !== false, 'the row of tools wraps where it has no room');

	$send = workspace_composer_phone_test_rule(workspace_composer_phone_test_phone_css(), '.ws-composer-tools .btn.px-5');
	pg_assert_true($send !== null && strpos($send, 'padding-inline: .6rem') !== false, 'Send keeps a narrow pad on a phone');
}

function test_workspace_composer_phone_keyboard_rules_stay()
{
	$js = workspace_composer_phone_test_js();
	pg_assert_contains("body.classList.toggle('ws-keyboard'", $js, 'the box still rides on the keyboard');
	pg_assert_contains('ctxFollow(attach);', $js, 'the menus of the box still follow their button');
}
