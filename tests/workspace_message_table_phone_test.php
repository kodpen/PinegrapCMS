<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a table written in a Workspace message on a phone (the section
 * "Tables and checklists written in a message" of
 * assets/css/backend.src.css): its frame scrolls sideways and keeps a swipe
 * at its end, and below 576px the table takes its columns' natural width
 * with cells wrapping at a readable width, instead of being laid out at its
 * narrowest with a sentence one word per line and the right-hand column a
 * sliver at the frame's edge. Rules outside the frame (calculation blocks,
 * the table editor) are left alone.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function workspace_message_table_phone_test_css()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/backend.src.css');
}

// The inner text of every @media block opened by $head.
function workspace_message_table_phone_test_media($css, $head)
{
	$out = '';
	$from = 0;

	while (($at = strpos($css, $head, $from)) !== false) {
		$open = strpos($css, '{', $at);
		$depth = 0;
		$len = strlen($css);

		for ($i = $open; $i < $len; $i++) {
			if ($css[$i] === '{') {
				$depth++;
			} elseif ($css[$i] === '}') {
				$depth--;

				if ($depth === 0) {
					break;
				}
			}
		}

		$out .= substr($css, $open + 1, $i - $open - 1) . "\n";
		$from = $i;
	}

	return $out;
}

// The declarations of the first rule whose selector is exactly $selector.
function workspace_message_table_phone_test_rule($css, $selector)
{
	if (!preg_match('/(?:^|\n)\s*' . preg_quote($selector, '/') . '\s*\{([^}]*)\}/', $css, $m)) {
		return null;
	}

	return $m[1];
}

function test_workspace_message_table_frame_scrolls_sideways()
{
	$rule = workspace_message_table_phone_test_rule(workspace_message_table_phone_test_css(), '.ws-table-wrap');

	pg_assert_true($rule !== null, 'the frame has its own rule');
	pg_assert_true(strpos($rule, 'overflow-x: auto') !== false, 'the frame scrolls sideways');
	pg_assert_true(strpos($rule, 'max-width: 100%') !== false, 'the frame never grows past the message');
	pg_assert_true(strpos($rule, 'overscroll-behavior-x: contain') !== false, 'a swipe at the frame\'s end stays in it');
}

function test_workspace_message_table_takes_natural_width_on_a_phone()
{
	$phone = workspace_message_table_phone_test_media(workspace_message_table_phone_test_css(), '@media (max-width: 575.98px) {');

	$table = workspace_message_table_phone_test_rule($phone, '.ws-table-wrap .table.ws-table');
	pg_assert_true($table !== null && strpos($table, 'width: max-content') !== false, 'on a phone the table takes its columns\' natural width');

	$cell = workspace_message_table_phone_test_rule($phone, '.ws-table-wrap > .ws-table > :not(caption) > * > *');
	pg_assert_true($cell !== null && (bool) preg_match('/max-width:\s*\d+(\.\d+)?rem/', $cell), 'on a phone a cell wraps at a set width');

	$long = workspace_message_table_phone_test_rule($phone, ".ws-table-wrap > .ws-table > :not(caption) > * > *,\n  .ws-table-wrap > .ws-table code");
	pg_assert_true($long !== null && strpos($long, 'overflow-wrap: anywhere') !== false, 'on a phone a link or code word longer than the cell breaks inside it');
}

function test_workspace_message_table_wide_rules_unchanged()
{
	$css = workspace_message_table_phone_test_css();
	$table = workspace_message_table_phone_test_rule($css, '.ws-table-wrap .table.ws-table');

	pg_assert_true($table !== null && strpos($table, 'width: auto') !== false, 'a wide screen keeps the table\'s automatic width');

	$cell = workspace_message_table_phone_test_rule($css, '.ws-table > :not(caption) > * > *');
	pg_assert_true($cell !== null && strpos($cell, 'max-width: 24rem') !== false, 'a wide screen keeps the 24rem cell');
}
