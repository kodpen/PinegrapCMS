<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the claim of a scheduled comment by the general job
 * (pg_comment_schedule_claim_sql() in includes/fn/comments.php): the UPDATE
 * that publishes it changes the row only while it is still unpublished and
 * still carries the time the job read, and job.php sends the comment e-mails
 * only for a comment its own run claimed, so two overlapping runs never mail
 * the same comment twice.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_comment_schedule_claim_sql_is_conditional()
{
	pg_assert_true(function_exists('pg_comment_schedule_claim_sql'), 'the claim builder exists');

	$sql = preg_replace('/\s+/', ' ', pg_comment_schedule_claim_sql(42, '2026-11-05 09:30:00'));

	pg_assert_true(strpos($sql, 'UPDATE comments SET') === 0, 'it updates the comment');
	pg_assert_true(strpos($sql, "published = '1'") !== false, 'it publishes the comment');
	pg_assert_true(strpos($sql, "(id = '42')") !== false, 'it names the comment');
	pg_assert_true(strpos($sql, "(published = '0')") !== false, 'only a comment still unpublished is claimed');
	pg_assert_true(strpos($sql, "(publish_date_and_time = '2026-11-05 09:30:00')") !== false, 'only while it carries the time the job read');
}

function test_comment_schedule_claim_sql_escapes_values()
{
	$sql = pg_comment_schedule_claim_sql('7 OR 1=1', "x' OR '1'='1");

	pg_assert_true(strpos($sql, "(id = '7')") !== false, 'the id is an integer');
	pg_assert_true(strpos($sql, "x' OR") === false, 'the time is escaped');
}

function test_job_mails_only_claimed_comments()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/job.php');

	$claim = strpos($source, 'pg_comment_schedule_claim_sql(');
	$check = strpos($source, 'mysqli_affected_rows(db::$con) !== 1', ($claim === false) ? 0 : $claim);
	$mail = strpos($source, 'send_comment_email_to_administrators(');

	pg_assert_true($claim !== false, 'job.php claims each scheduled comment');
	pg_assert_true(($check !== false) && ($claim < $check) && ($check < $mail), 'job.php checks the claim before any e-mail');
}
