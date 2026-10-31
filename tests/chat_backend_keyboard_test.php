<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source checks for the panel chat window's keyboard use
 * (assets/js/chat_backend.src.js, its .min.js twin and the window's styles
 * in chat.php): list rows open from the keyboard, opening moves focus into
 * the window, Escape and the close button give it back to the launcher,
 * and the 15 s list refresh keeps the focused control. A unit test cannot
 * press keys in the window, so these pin the source.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function chat_backend_keyboard_source($file = 'chat_backend.src.js')
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/' . $file);
}

// The body of one function in the window's script, from its declaration to
// the next function declared at the same indent.
function chat_backend_keyboard_function($name)
{
	$source = chat_backend_keyboard_source();
	$start = strpos($source, "\n    function " . $name . '(');
	if ($start === false) {
		return '';
	}
	$end = strpos($source, "\n    function ", $start + 1);

	return $end === false ? substr($source, $start) : substr($source, $start, $end - $start);
}

function test_chat_rows_are_buttons_that_answer_enter_and_space()
{
	$activatable = chat_backend_keyboard_function('activatable');

	pg_assert_true($activatable !== '', 'activatable() is found in chat_backend.src.js');
	pg_assert_contains("setAttribute('role', 'button')", $activatable, 'a row is announced as a button');
	pg_assert_contains("setAttribute('tabindex', '0')", $activatable, 'a row is in the tab order');
	pg_assert_contains("event.key === 'Enter'", $activatable, 'Enter opens a row');
	pg_assert_contains("event.key === ' '", $activatable, 'Space opens a row');
	pg_assert_contains("setAttribute('data-pg-chat-key'", $activatable, 'a row carries the key focus is restored by');

	pg_assert_contains("activatable(row, 'user:'", chat_backend_keyboard_function('userRow'), 'a user row opens from the keyboard');
	pg_assert_contains("activatable(row, 'ai'", chat_backend_keyboard_function('aiRow'), 'the AI Chat row opens from the keyboard');
	pg_assert_contains("activatable(row, 'conversation:'", chat_backend_keyboard_function('renderConversations'), 'a conversation row opens from the keyboard');
}

function test_chat_window_takes_and_returns_focus()
{
	$toggle = chat_backend_keyboard_function('togglePanel');

	pg_assert_contains("focusKey('tab:' + state.tab)", $toggle, 'opening the window focuses the active tab');
	pg_assert_contains('launcher.focus()', $toggle, 'closing the window gives focus back to the launcher');
	pg_assert_contains("launcher.setAttribute('aria-expanded'", $toggle, 'the launcher says whether the window is open');

	$source = chat_backend_keyboard_source();
	pg_assert_true(
		preg_match("/panel\\.addEventListener\\('keydown'[\\s\\S]{0,600}event\\.key [!=]== 'Escape'[\\s\\S]{0,600}togglePanel\\(\\)/", $source) === 1,
		'Escape inside the window closes it');
}

function test_chat_list_refresh_keeps_the_focused_control()
{
	$refresh = chat_backend_keyboard_function('refreshLists');

	pg_assert_contains('focusedKey()', $refresh, 'the refresh remembers the focused control');
	pg_assert_contains('focusKey(', $refresh, 'the refresh focuses it again after the redraw');
	pg_assert_contains('state.listBody', $refresh, 'a tick redraws the rows only, not the whole window');

	pg_assert_contains("'tab:online'", chat_backend_keyboard_function('renderListView'), 'tabs carry a focus key');
	pg_assert_contains("'close'", chat_backend_keyboard_function('renderListView'), 'the close button carries a focus key');
}

function test_chat_min_twin_has_the_keyboard_code()
{
	$min = chat_backend_keyboard_source('chat_backend.min.js');

	// strpos, not pg_assert_contains: a failure would print the whole bundle.
	pg_assert_true(strpos($min, 'data-pg-chat-key') !== false, 'chat_backend.min.js carries the focus keys');
	pg_assert_true(strpos($min, '"Escape"') !== false, 'chat_backend.min.js closes on Escape');
	pg_assert_true(strpos($min, 'aria-expanded') !== false, 'chat_backend.min.js sets the launcher state');
}

function test_chat_row_has_a_focus_ring_on_panel_tokens()
{
	$chat = (string) file_get_contents(PG_FUNCTIONS_DIR . '/chat.php');

	pg_assert_true(
		preg_match('/#pg-chat-root \.pg-chat-row:focus-visible \{[^}]*var\(--pg-focus-ring-width\)[^}]*var\(--pg-focus-ring-color\)/', $chat) === 1,
		'a focused chat row draws the panel focus ring');
}

function test_chat_window_keeps_tab_inside_only_in_full_screen()
{
	$source = chat_backend_keyboard_source();
	pg_assert_true(
		preg_match("/panel\\.addEventListener\\('keydown'[^\\n]*\\n\\s*if \\(event\\.key !== 'Tab'[^\\n]*!isFullscreenLayout\\(\\)/", $source) === 1,
		'Tab wraps inside the window only while it fills a phone screen');
	pg_assert_contains('last.focus()', $source, 'Shift+Tab on the first control goes to the last');
	pg_assert_contains('first.focus()', $source, 'Tab on the last control goes to the first');
	pg_assert_true(strpos($source, 'The window stays non-modal: Tab leaves it') === false, 'the old non-modal note is gone');
}
