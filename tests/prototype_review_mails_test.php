<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the approver's answer mail and "Answer by" reminder of a
 * prototype link asking approval (includes/fn/prototype_review_mails.php):
 * the undecided pages, the reminder pass's hours and day, and the mails'
 * words.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_prototype_review_mails_undecided_pages()
{
	$fp = str_repeat('a', 40);
	$old = str_repeat('b', 40);
	$newest = array(
		11 => array('verdict' => 'approved', 'fingerprint' => $fp),
		12 => array('verdict' => 'changes_requested', 'fingerprint' => $fp),
		13 => array('verdict' => 'approved', 'fingerprint' => $old),
	);
	$prints = array(11 => $fp, 12 => $fp, 13 => $fp, 14 => $fp);

	pg_assert_same(array(13, 14), pg_proto_undecided_pages(array(11, 12, 13, 14), $newest, $prints), 'a page without a verdict and one changed since are undecided');
	pg_assert_same(array(), pg_proto_undecided_pages(array(11, 12), $newest, $prints), 'approved and changes asked are decisions');
	pg_assert_same(array(), pg_proto_undecided_pages(array(), $newest, $prints), 'no pages');
	pg_assert_same(array(15), pg_proto_undecided_pages(array(15), $newest, array()), 'a page without a fingerprint and no verdict');
}

function test_prototype_review_mails_reminder_pass_open()
{
	$morning = mktime(9, 30, 0, 11, 24, 2026);
	$night = mktime(3, 0, 0, 11, 24, 2026);
	$evening = mktime(20, 5, 0, 11, 24, 2026);
	$yesterday = mktime(10, 0, 0, 11, 23, 2026);

	pg_assert_true(pg_proto_reminder_pass_open($morning, 0), 'a first pass in the day hours');
	pg_assert_true(pg_proto_reminder_pass_open($morning, $yesterday), 'the first pass of the day');
	pg_assert_false(pg_proto_reminder_pass_open($morning + 600, $morning), 'once a day');
	pg_assert_false(pg_proto_reminder_pass_open($night, $yesterday), 'never at night');
	pg_assert_false(pg_proto_reminder_pass_open($evening, $yesterday), 'not after 20:00');
}

function test_prototype_review_mails_words()
{
	$answer = pg_proto_answer_mail_words('Ayla', 'Shop', 3, 'https://example.com/prototype.php?t=x');

	pg_assert_contains('Shop', $answer['subject'], 'the subject names the design');
	pg_assert_contains('Ayla', $answer['body'], 'the approver is greeted');
	pg_assert_contains('3', $answer['body'], 'the answers are counted');
	pg_assert_contains('https://example.com/prototype.php?t=x', $answer['body'], 'the link is given');

	$one = pg_proto_answer_mail_words('Ayla', 'Shop', 1, '');
	pg_assert_false(strpos($one['body'], 'http') !== false, 'no address when the site cannot read it back');

	$reminder = pg_proto_reminder_mail_words('Ayla', 'Shop', '2026-11-25', 2, 'https://example.com/prototype.php?t=x');

	pg_assert_contains('2026-11-25', $reminder['body'], 'the day is named');
	pg_assert_contains('2', $reminder['body'], 'the undecided pages are counted');
	pg_assert_contains('https://example.com/prototype.php?t=x', $reminder['body'], 'the link is given');
}
