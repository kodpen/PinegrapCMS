<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source test for Workspace controls that sat inside another control: the
 * channel list's pin inside the channel link, and the search palette's
 * account link inside a person option.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function ws_nested_test_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/' . $path);
}

// The text between $from and the first $to after it.
function ws_nested_test_between($source, $from, $to)
{
	$start = strpos($source, $from);

	if ($start === false) {
		return '';
	}

	$end = strpos($source, $to, $start + strlen($from));

	return ($end === false) ? substr($source, $start) : substr($source, $start, $end - $start);
}

function test_ws_channel_pin_is_no_button_inside_the_link()
{
	$source = ws_nested_test_source('pinegrap/assets/js/workspace_side.js');
	// The pin, "⋯" and Join are built by one helper, rowShortcut().
	$shortcut = ws_nested_test_between($source, 'function rowShortcut(className, iconName, title, handler) {', 'return node;');
	$row = ws_nested_test_between($source, 'function channelLink(channel, box, sortable) {', 'return node;');

	pg_assert_true($shortcut !== '', 'the row\'s shortcuts are built by rowShortcut()');
	pg_assert_contains("rowShortcut('ws-pin'", $row, 'the pin is still built in channelLink()');
	pg_assert_false(strpos($shortcut . $row, "'role', 'button'") !== false, 'no button role inside the channel link');
	pg_assert_contains("node.setAttribute('aria-hidden', 'true');", $shortcut, 'the pin stays out of the link name');
	pg_assert_false(strpos($shortcut, 'tabIndex') !== false, 'and out of the Tab order');
}

function test_ws_palette_account_link_leaves_the_option()
{
	$source = ws_nested_test_source('pinegrap/assets/js/workspace_palette.js');
	$account = ws_nested_test_between($source, "var account = el('a',", 'row.appendChild(account);');

	pg_assert_true($account !== '', 'the account link is still built in addRow()');
	pg_assert_contains("account.tabIndex = -1;", $account, 'the link is no Tab stop inside the listbox');
	pg_assert_contains("account.setAttribute('aria-hidden', 'true');", $account, 'the link stays out of the option name');
}

function test_ws_palette_shift_enter_opens_the_account()
{
	$source = ws_nested_test_source('pinegrap/assets/js/workspace_palette.js');
	$key = ws_nested_test_between($source, 'function onKey(event)', '// ── Going where a line points');

	pg_assert_contains('event.shiftKey', $key, 'Shift+Enter is read in onKey()');
	pg_assert_contains('hasAccount(', $key, 'only a line that shows the account link');
	pg_assert_contains('go(', $key, 'it opens the account');

	$hint = ws_nested_test_source('pinegrap/includes/workspace/palette.php');

	pg_assert_contains('Shift+Enter', ws_nested_test_between($hint, "'pal_hint'", "\n"), 'the foot hint names Shift+Enter');
}
