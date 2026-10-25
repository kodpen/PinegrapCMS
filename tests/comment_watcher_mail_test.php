<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the addresses a watcher e-mail about a new comment goes to
 * (pg_comment_watcher_addresses() in includes/fn/comments.php): every
 * watcher once, never the comment's own writer, and the query in
 * send_comment_email_to_watchers() (includes/fn/mail.php) that gives it the
 * writer. The e-mail is sent when the comment is posted and again when a
 * moderator or the scheduled job publishes it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function comment_watcher_mail_test_row($user_id, $user_email, $watcher_email)
{
	return array('user_id' => $user_id, 'user_email_address' => $user_email, 'watcher_email_address' => $watcher_email);
}

// A watcher connected to a user is written to at the user's address, any
// other at its own; the writer is left out.
function test_comment_watcher_addresses()
{
	$watchers = array(
		comment_watcher_mail_test_row('4', 'writer@example.com', ''),
		comment_watcher_mail_test_row('5', 'ann@example.com', ''),
		comment_watcher_mail_test_row('0', '', 'list@example.com'),
		comment_watcher_mail_test_row('6', 'bob@example.com', 'old@example.com'),
	);

	pg_assert_same(array('ann@example.com', 'list@example.com', 'bob@example.com'), pg_comment_watcher_addresses($watchers, 4, 'writer@example.com'), 'everyone but the writer');
	pg_assert_same(array('writer@example.com', 'ann@example.com', 'list@example.com', 'bob@example.com'), pg_comment_watcher_addresses($watchers, 0, ''), 'a guest\'s comment: every watcher');
	pg_assert_same(array('ann@example.com', 'list@example.com', 'bob@example.com'), pg_comment_watcher_addresses($watchers, '4', ''), 'the writer\'s own watcher row, by user');

	// A row that is no user of the writer but carries their address.
	$by_address = array(
		comment_watcher_mail_test_row('0', '', ' Writer@Example.com '),
		comment_watcher_mail_test_row('5', 'ann@example.com', ''),
	);
	pg_assert_same(array('ann@example.com'), pg_comment_watcher_addresses($by_address, 4, 'writer@example.com'), 'the writer\'s address, whatever its case');

	$twice = array(
		comment_watcher_mail_test_row('5', 'ann@example.com', ''),
		comment_watcher_mail_test_row('0', '', 'ANN@example.com'),
		comment_watcher_mail_test_row('7', '', ''),
	);
	pg_assert_same(array('ann@example.com'), pg_comment_watcher_addresses($twice, 0, ''), 'one e-mail per address, none to a row without one');

	pg_assert_same(array(), pg_comment_watcher_addresses(null, 4, 'writer@example.com'), 'no watchers');
	pg_assert_same(array('ann@example.com'), pg_comment_watcher_addresses(array('x', comment_watcher_mail_test_row(array(), 'ann@example.com', null)), array(), null), 'what is no row or no value');
}

// The sender reads the writer and each watcher's user, and lets the list
// above choose the addresses.
function test_comment_watcher_mail_sender()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/mail.php');
	$start = strpos($source, 'function send_comment_email_to_watchers(');
	$end = ($start === false) ? false : strpos($source, "\nfunction ", $start + 1);
	$body = (($start === false) || ($end === false)) ? '' : substr($source, $start, $end - $start);

	pg_assert_contains('comments.created_user_id', $body, 'the writer');
	pg_assert_contains('LEFT JOIN user AS writer ON comments.created_user_id = writer.user_id', $body, 'and their address');
	pg_assert_contains('watchers.user_id', $body, 'each watcher\'s user');
	pg_assert_contains("foreach (pg_comment_watcher_addresses(\$watchers, \$writer_user_id, \$writer_email) as \$to) {", $body, 'the addresses come from the list');
}
