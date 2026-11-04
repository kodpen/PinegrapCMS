<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The panel chat's send rules and window labels: pg_chat_check_body()
 * refuses an empty, non-string or too long body, pg_chat_send() checks the
 * body and the rate limit before it writes a conversation row, the list
 * hides empty rows and carries the peer's username, and the window script
 * names its icon buttons, asks before closing and keeps the unread count
 * moving inside a conversation. chat.php is a URL-called file, so its
 * functions are read out of the source.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function chat_send_checks_php()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/chat.php');
}

function chat_send_checks_js()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/chat_backend.src.js');
}

function chat_send_checks_function($name)
{
	preg_match('/^function ' . preg_quote($name, '/') . '\(.*?^\}/ms', chat_send_checks_php(), $match);

	return isset($match[0]) ? $match[0] : '';
}

function chat_send_checks_load()
{
	if (!function_exists('chat_send_checks_body')) {
		eval(str_replace('function pg_chat_max_length(', 'function chat_send_checks_max(', chat_send_checks_function('pg_chat_max_length')));
		eval(str_replace(
			array('function pg_chat_check_body(', 'pg_chat_max_length()'),
			array('function chat_send_checks_body(', 'chat_send_checks_max()'),
			chat_send_checks_function('pg_chat_check_body')
		));
	}
}

function test_chat_body_check_refuses_empty_arrays_and_long_bodies()
{
	chat_send_checks_load();

	$ok = chat_send_checks_body("  hello \n");
	pg_assert_same('hello', $ok['body'], 'body is trimmed');
	pg_assert_same('', $ok['error'], 'a normal body passes');

	pg_assert_true(chat_send_checks_body('   ')['error'] !== '', 'a blank body is refused');
	pg_assert_true(chat_send_checks_body(array('x'))['error'] !== '', 'an array body is refused');
	pg_assert_same('', chat_send_checks_body(array('x'))['body'], 'an array body is not turned into "Array"');
	pg_assert_same('', chat_send_checks_body(str_repeat('ş', 2000))['error'], '2000 multibyte characters pass');
	pg_assert_true(chat_send_checks_body(str_repeat('a', 2001))['error'] !== '', '2001 characters are refused');
	pg_assert_same('42', chat_send_checks_body(42)['body'], 'a number is kept as text');
}

function test_chat_send_checks_before_writing_a_row()
{
	$send = chat_send_checks_function('pg_chat_send');
	$check = strpos($send, 'pg_chat_check_body(');
	$rate = strpos($send, 'pg_chat_rate_limited()');
	$insert = strpos($send, 'INSERT INTO chat_conversations');

	pg_assert_true($check !== false && $rate !== false && $insert !== false, 'pg_chat_send has the check, the rate limit and the insert');
	pg_assert_true($check < $insert && $rate < $insert, 'both run before the conversation row is written');
	pg_assert_false(strpos($send, '(string) $body') !== false, 'no direct string cast of the body is left');

	pg_assert_contains('pg_chat_check_body(', chat_send_checks_function('pg_chat_site_send'), 'the site send shares the check');
}

function test_chat_list_hides_empty_rows_and_carries_username()
{
	$list = chat_send_checks_function('pg_chat_conversation_list');

	pg_assert_contains('AND c.last_message_id > 0', $list, 'conversations with no message are not listed');
	pg_assert_contains("'username' => (string) \$row['peer_username']", $list, 'the peer carries its username');
}

function test_chat_window_strings_and_config()
{
	$strings = chat_send_checks_function('pg_chat_panel_strings');

	foreach (array('sound', 'close_confirm', 'unread_conversations', 'chars_left', 'emoji_groups') as $key) {
		pg_assert_contains("'" . $key . "' =>", $strings, 'string ' . $key . ' is sent to the window');
	}

	pg_assert_contains("'max_length' => pg_chat_max_length()", chat_send_checks_php(), 'the limit reaches the window config');
}

function test_chat_window_script_labels_confirm_unread_maxlength()
{
	$js = chat_send_checks_js();

	pg_assert_contains("soundButton.setAttribute('aria-pressed'", $js, 'the bell reports its state');
	pg_assert_contains("soundButton.setAttribute('aria-label', STR.sound)", $js, 'the bell has a name');
	pg_assert_contains("launcher.setAttribute('aria-label', count > 0", $js, 'the launcher name says the unread count');
	pg_assert_contains('STR.emoji_groups', $js, 'emoji tabs get their names');
	pg_assert_contains("STR.closed_short", $js, 'closed conversations are tagged in the list');
	pg_assert_contains("closeConversationButton.addEventListener('click', confirmClose)", $js, 'closing asks first');
	pg_assert_contains("if (state.conversation.status !== 'closed')", $js, 'a closed conversation has no close button');
	pg_assert_contains('state.timers.badge = setInterval(checkUnread, POLL.list * 1000);', $js, 'the unread count is checked inside a conversation');
	pg_assert_contains('textarea.maxLength = config.max_length', $js, 'the composer carries the server limit');
	pg_assert_false(strpos($js, "'btn btn-sm btn-link fg-secondary p-0 me-3'") !== false, 'every header icon button has the target-size class');
}
