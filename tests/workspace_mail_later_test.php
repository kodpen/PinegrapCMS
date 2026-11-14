<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for Send later (includes/workspace/mail_later.php): the typed time
 * read in the writer's time zone, the limits, the hour a zone skips. No
 * database.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

function test_workspace_mail_later_parse_reads_the_writers_zone()
{
	$now = gmmktime(12, 0, 0, 11, 14, 2026);

	$istanbul = ws_mail_later_parse('2026-11-20T09:30', 'Europe/Istanbul', $now);
	pg_assert_same(array(true, gmmktime(6, 30, 0, 11, 20, 2026)), array($istanbul['ok'], $istanbul['at']), '09:30 in Istanbul is 06:30 UTC');

	$berlin = ws_mail_later_parse('2026-11-20 09:30:45', 'Europe/Berlin', $now);
	pg_assert_same(gmmktime(8, 30, 0, 11, 20, 2026), $berlin['at'], 'a space and seconds; seconds dropped; Berlin in winter');

	pg_assert_same(gmmktime(9, 30, 0, 11, 20, 2026), ws_mail_later_parse('2026-11-20T09:30', '', $now)['at'], 'no zone reads as UTC');
	pg_assert_same(gmmktime(9, 30, 0, 11, 20, 2026), ws_mail_later_parse('2026-11-20T09:30', 'Mars/Olympus', $now)['at'], 'an unknown zone reads as UTC');
}

function test_workspace_mail_later_parse_refuses()
{
	$now = gmmktime(12, 0, 0, 11, 14, 2026);

	pg_assert_same('missing', ws_mail_later_parse('', 'UTC', $now)['error'], 'nothing typed');
	pg_assert_same('missing', ws_mail_later_parse(array('x'), 'UTC', $now)['error'], 'not text');
	pg_assert_same('format', ws_mail_later_parse('20.11.2026 09:30', 'UTC', $now)['error'], 'another format');
	pg_assert_same('format', ws_mail_later_parse('2026-02-30T09:30', 'UTC', $now)['error'], 'a day that does not exist');
	pg_assert_same('format', ws_mail_later_parse('2027-03-28T02:30', 'Europe/Berlin', $now)['error'], 'the hour Berlin skips in spring');
	pg_assert_same('past', ws_mail_later_parse('2026-11-14T11:00', 'UTC', $now)['error'], 'in the past');
	pg_assert_same('past', ws_mail_later_parse('2026-11-14T12:04', 'UTC', $now)['error'], 'less than five minutes ahead');
	pg_assert_true(ws_mail_later_parse('2026-11-14T12:05', 'UTC', $now)['ok'], 'five minutes ahead');
	pg_assert_true(ws_mail_later_parse('2027-01-13T12:00', 'UTC', $now)['ok'], 'sixty days ahead');
	pg_assert_same('too_far', ws_mail_later_parse('2027-01-13T12:01', 'UTC', $now)['error'], 'past sixty days');

	foreach (array('missing', 'format', 'past', 'too_far', 'other') as $error) {
		pg_assert_true(ws_mail_later_error_text($error) !== '', $error . ' is told');
	}
}
