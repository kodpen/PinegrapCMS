<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the notice of a shared channel (assets/js/workspace.js): while a
 * link is open the share bar is a full row that stands out; once none is,
 * the row goes and a small button with an exclamation mark in the channel's
 * head opens the same Manage.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The body of a method of the screen object in workspace.js, by its name
// and its parameter list.
function workspace_share_notice_method($name, $params)
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');
	preg_match('/\n        ' . preg_quote($name, '/') . ': function \(' . preg_quote($params, '/') . '\) \{\n(.*?)\n        \},\n/s', $js, $match);

	return $match[1] ?? '';
}

function test_the_share_bar_is_a_row_only_while_a_link_is_open()
{
	$bar = workspace_share_notice_method('shareBar', 'channel');

	pg_assert_contains("if (!open.length) {\n                return null;", $bar, 'no row without an open link');
	pg_assert_contains('ws-share-bar-open theme-warning', $bar, 'the open row stands out');
	pg_assert_false(strpos($bar, "t('share_bar_closed')") !== false, 'the closed sentence is no longer a row');
}

function test_with_no_link_open_the_head_carries_the_button()
{
	$button = workspace_share_notice_method('shareHeadButton', 'channel');

	pg_assert_contains("info.guests.some(function (guest) { return guest.open; })", $button, 'only when no link is open');
	pg_assert_contains("'bi-exclamation-circle', t('share_bar_closed')", $button, 'an exclamation mark named by the closed sentence');
	pg_assert_contains('self.shareManage(channel)', $button, 'it opens the same Manage');

	$draw = workspace_share_notice_method('drawCenter', '');
	pg_assert_contains('self.shareHeadButton(channel)', $draw, 'the head draws it');
}

function test_a_change_while_open_redraws_both()
{
	$notice = workspace_share_notice_method('drawShareNotice', '');

	pg_assert_contains("querySelectorAll('.ws-share-bar, .ws-head-share')", $notice, 'the old row and button go first');
	pg_assert_contains('this.shareBar(channel)', $notice, 'the row as it now stands');
	pg_assert_contains('this.shareHeadButton(channel)', $notice, 'the button as it now stands');
}

function test_the_open_bar_and_the_button_have_their_look()
{
	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/backend.src.css');

	pg_assert_contains('.ws-share-bar-open {', $css, 'the open row has its edge');
	pg_assert_contains('.ws-head-share {', $css, 'the head button its colour');
}
