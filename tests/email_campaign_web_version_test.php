<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the web version of a campaign e-mail (view_email_campaign.php)
 * opened by its recipient's reference code: only a value shaped like a code
 * the campaign send writes is looked up, a recipient still waiting for the
 * send (an empty code) is never matched, and a recipient whose campaign is
 * gone ends on the "could not be found" error.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function email_campaign_web_version_test_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_email_campaign.php');
}

// Letters and digits, one to ten, as generate_email_recipient_reference_code()
// writes them: nothing the column's collation could read as the empty code.
function test_email_recipient_code_valid()
{
	if (!function_exists('pg_email_recipient_code_valid')) {
		pg_assert_true(false, 'pg_email_recipient_code_valid() exists');

		return;
	}

	foreach (array('EP31LNI5JH', 'ep31lni5jh', 'A', '0123456789') as $code) {
		pg_assert_true(pg_email_recipient_code_valid($code), 'a code: ' . $code);
	}

	// NUL, U+0001 and the zero-width space are ignored by utf8mb4_unicode_ci:
	// a value made only of them equalled the empty code.
	foreach (array('', "\0", "\x01", "\u{200B}", ' ', 'ABC DEF', 'ABCDEFGHIJK', "ABC\n", "ABC\0", 'AB-CD', 'ÇAĞRI') as $code) {
		pg_assert_false(pg_email_recipient_code_valid($code), 'not a code: ' . json_encode($code));
	}

	foreach (array(null, array('ABC'), 12345, true) as $value) {
		pg_assert_false(pg_email_recipient_code_valid($value), 'not text: ' . gettype($value));
	}

	// Every code the generator writes passes.
	pg_assert_true(pg_email_recipient_code_valid(str_repeat('Z', 10)), 'ten of the last letter');
}

// The screen looks up only a value shaped like a code, never the empty code
// of a recipient whose campaign is not sent yet.
function test_email_campaign_web_version_lookup()
{
	$source = email_campaign_web_version_test_source();
	$check = strpos($source, 'pg_email_recipient_code_valid($_GET[\'r\'])');
	$lookup = strpos($source, 'FROM email_recipients');

	pg_assert_true($check !== false, 'the reference code is checked');
	pg_assert_true(($check !== false) && ($lookup !== false) && ($check < $lookup), 'before the recipient lookup');
	pg_assert_contains("(reference_code <> '')", $source, 'a recipient still waiting for the send is never matched');
}

// A recipient whose campaign is gone (deleted, or a starter row with no
// campaign) ends on the "could not be found" error, not on the values of a
// missing row.
function test_email_campaign_web_version_missing_campaign()
{
	$source = email_campaign_web_version_test_source();
	$read = strpos($source, '$type = $row[\'type\'];');

	pg_assert_true($read !== false, 'the campaign read is found');

	if ($read === false) {
		return;
	}

	$fetch = strrpos(substr($source, 0, $read), '$row = mysqli_fetch_assoc($result);');
	$between = ($fetch === false) ? '' : substr($source, $fetch, $read - $fetch);

	pg_assert_true($fetch !== false, 'the campaign row is fetched');
	pg_assert_contains('if (!$row) {', $between, 'the row is checked before its first value is read');
	pg_assert_contains("output_error(lang('The e-mail campaign could not be found.", $between, 'with the not found error');
}

// The starter dumps ship no campaign, so they ship no recipient either: a
// recipient row of a starter carried a code that anyone can read here.
function test_email_campaign_web_version_starters_ship_no_recipient()
{
	foreach (array('english_default', 'turkish_default') as $dump) {
		$sql = (string) file_get_contents(PG_FUNCTIONS_DIR . '/data/backups/' . $dump . '/sql.sql');

		pg_assert_true(strpos($sql, 'CREATE TABLE `email_recipients`') !== false, $dump . ' creates the recipients table');
		pg_assert_false(strpos($sql, 'INSERT INTO `email_recipients`') !== false, $dump . ' ships no recipient');
	}
}
