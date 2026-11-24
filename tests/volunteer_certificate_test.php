<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the volunteer's hours certificate and the thank-you e-mails
 * (includes/fn/volunteer_certificate.php): the signed code, the period a
 * volunteer chooses, when a code still holds, which milestone is thanked,
 * and the pages that carry them.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/volunteer_certificate.php');

function test_volunteer_certificate_code_round_trip()
{
	$code = pg_volunteer_certificate_code(42, '2026-01-01', '2026-11-24', 3030, 'key');

	pg_assert_true((bool) preg_match('/^[0-9A-Z]+-[0-9A-Z]+-[0-9A-Z]+-[0-9A-Z]+-[0-9A-F]{12}$/', $code), 'five parts');
	pg_assert_same(array('volunteer_id' => 42, 'from' => '2026-01-01', 'to' => '2026-11-24', 'minutes' => 3030), pg_volunteer_certificate_parse($code, 'key'));
	pg_assert_same(array('volunteer_id' => 42, 'from' => '2026-01-01', 'to' => '2026-11-24', 'minutes' => 3030), pg_volunteer_certificate_parse(' ' . strtolower($code) . ' ', 'key'), 'any letter case');
}

function test_volunteer_certificate_code_refuses_changes()
{
	$code = pg_volunteer_certificate_code(42, '2026-01-01', '2026-11-24', 3030, 'key');
	$parts = explode('-', $code);
	$more = $parts;
	$more[3] = strtoupper(base_convert('9000', 10, 36));

	pg_assert_same(null, pg_volunteer_certificate_parse(implode('-', $more), 'key'), 'more minutes');
	pg_assert_same(null, pg_volunteer_certificate_parse($code, 'other key'), 'another site');
	pg_assert_same(null, pg_volunteer_certificate_parse($code, ''), 'no key');
	pg_assert_same(null, pg_volunteer_certificate_parse(array($code), 'key'), 'an array is no code');
	pg_assert_same(null, pg_course_certificate_parse($code, 'key'), 'not a course code');
	pg_assert_same('', pg_volunteer_certificate_code(42, '2026-02-01', '2026-01-01', 60, 'key'), 'start after the end');
	pg_assert_same('', pg_volunteer_certificate_code(42, '2026-01-01', '2026-01-31', 0, 'key'), 'no hours, no code');
}

function test_volunteer_certificate_period()
{
	pg_assert_same(array('from' => '2026-01-01', 'to' => '2026-11-24', 'error' => ''), pg_volunteer_certificate_period(array(), '2026-11-24'), 'this year by default');
	pg_assert_same(array('from' => '2025-03-01', 'to' => '2026-11-24', 'error' => ''), pg_volunteer_certificate_period(array('from' => '2025-03-01', 'to' => '2027-01-01'), '2026-11-24'), 'a day after today becomes today');
	pg_assert_same('period', pg_volunteer_certificate_period(array('from' => '2026-05-01', 'to' => '2026-04-01'), '2026-11-24')['error'], 'start after the end');
	pg_assert_same('period', pg_volunteer_certificate_period(array('from' => '2026-02-30'), '2026-11-24')['error'], 'no such day');
	pg_assert_same('period', pg_volunteer_certificate_period(array('from' => '2006-01-01'), '2026-11-24')['error'], 'longer than ten years');
	pg_assert_same('period', pg_volunteer_certificate_period(array('from' => array('x')), '2026-11-24')['error'], 'an array is no day');
}

function test_volunteer_certificate_holds()
{
	$parsed = array('volunteer_id' => 4, 'from' => '2026-01-01', 'to' => '2026-06-30', 'minutes' => 600);

	pg_assert_true(pg_volunteer_certificate_holds($parsed, true, 600));
	pg_assert_true(pg_volunteer_certificate_holds($parsed, true, 660), 'hours approved later in the period keep it');
	pg_assert_false(pg_volunteer_certificate_holds($parsed, true, 540), 'fewer approved hours than stated');
	pg_assert_false(pg_volunteer_certificate_holds($parsed, false, 600), 'an erased volunteer');
	pg_assert_false(pg_volunteer_certificate_holds(null, true, 600));
}

function test_volunteer_thanks_due()
{
	pg_assert_same(0, pg_volunteer_thanks_due(49 * 60 + 59, 0));
	pg_assert_same(50, pg_volunteer_thanks_due(50 * 60, 0));
	pg_assert_same(0, pg_volunteer_thanks_due(80 * 60, 50), 'once each');
	pg_assert_same(100, pg_volunteer_thanks_due(120 * 60, 50));
	pg_assert_same(250, pg_volunteer_thanks_due(300 * 60, 0), 'two passed at once: one e-mail, the highest');
	pg_assert_same(0, pg_volunteer_thanks_due(900 * 60, 250));
}

function test_volunteer_hours_number()
{
	pg_assert_same('50', pg_volunteer_hours_number(3000));
	pg_assert_same('0', pg_volunteer_hours_number(-5));
}

function test_volunteer_certificate_pages()
{
	pg_assert_contains("'volunteer_certificate.php'", pg_test_function_source('waf_sensitive_scripts'), 'the certificate page on the sensitive allowance');

	$page = (string) file_get_contents(PG_FUNCTIONS_DIR . '/volunteer_certificate.php');
	pg_assert_contains('pg_volunteer_page_throttled()', $page, 'the page is rate limited');
	pg_assert_contains("\$_SESSION['volunteer']['ids']", $page, 'a signed link opened in this browser');
	pg_assert_contains('pg_volunteer_account_ids(', $page, 'or the member\'s own profiles');
	pg_assert_contains("header('Cache-Control: no-store, private')", $page);

	pg_assert_contains('pg_volunteer_thanks_send(', pg_test_function_source('pg_volunteer_hours_approve'), 'approval thanks at the milestones');
	pg_assert_contains('thanked_hours < ?', pg_test_function_source('pg_volunteer_thanks_send'), 'the milestone is claimed before the e-mail');
	pg_assert_contains('Get my certificate', pg_test_function_source('pg_volunteer_page_hours_html'));
}
