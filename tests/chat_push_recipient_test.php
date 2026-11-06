<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Who a chat message wakes on their devices (pg_chat_push_recipient() in
 * chat.php): the other account of a panel conversation; on a site
 * conversation the operator when the visitor or member writes, and the
 * signed-in member who started it when staff answer. An anonymous visitor
 * has no account and wakes nobody. chat.php is a URL-called file, so the
 * function is read out of the source.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function chat_push_recipient_load()
{
	if (function_exists('chat_push_recipient_under_test')) {
		return true;
	}

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/chat.php');

	if (!preg_match('/^function pg_chat_push_recipient\(.*?^\}/ms', $source, $match)) {
		return false;
	}

	eval(str_replace('function pg_chat_push_recipient(', 'function chat_push_recipient_under_test(', $match[0]));

	return true;
}

function test_chat_push_recipient_wakes_the_member_staff_answer()
{
	pg_assert_true(chat_push_recipient_load(), 'pg_chat_push_recipient() exists in chat.php');

	if (!function_exists('chat_push_recipient_under_test')) {
		return;
	}

	$member = array('channel' => 'site', 'initiator_user_id' => '12', 'target_user_id' => '3');

	pg_assert_same(3, chat_push_recipient_under_test($member, 12, 3), 'the member writes: the operator is woken');
	pg_assert_same(12, chat_push_recipient_under_test($member, 3, 3), 'the operator answers: the member is woken');
	pg_assert_same(12, chat_push_recipient_under_test($member, 1, 3), 'an administrator answers: the member is woken');

	$visitor = array('channel' => 'site', 'initiator_user_id' => '0', 'target_user_id' => '3');

	pg_assert_same(3, chat_push_recipient_under_test($visitor, 0, 3), 'an anonymous visitor writes: the operator is woken');
	pg_assert_same(0, chat_push_recipient_under_test($visitor, 3, 3), 'the operator answers an anonymous visitor: nobody');
	pg_assert_same(0, chat_push_recipient_under_test($visitor, 1, 3), 'an administrator answers an anonymous visitor: nobody');

	$unassigned = array('channel' => 'site', 'initiator_user_id' => '0', 'target_user_id' => '0');

	pg_assert_same(5, chat_push_recipient_under_test($unassigned, 0, 5), 'no target: the configured operator');

	$backend = array('channel' => 'backend', 'initiator_user_id' => '4', 'target_user_id' => '9');

	pg_assert_same(9, chat_push_recipient_under_test($backend, 4, 0), 'panel conversation: the target');
	pg_assert_same(4, chat_push_recipient_under_test($backend, 9, 0), 'panel conversation: the initiator');
}
