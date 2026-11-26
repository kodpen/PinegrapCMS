<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for an outside juror's link and a not eligible entry's correction
 * link that need no database (includes/fn/application_jurors.php): the
 * tokens, where a link stands, who a reminder goes to, the fields a
 * correction opens and the answers it takes; and the gates of
 * application_juror.php and application_fix.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/source_reader.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/application_my_reviews.php');

function test_application_juror_token_is_stable_per_row_and_key()
{
	$key = str_repeat('k', 32);
	$token = pg_application_juror_token(7, 1800000000, $key);

	pg_assert_same(64, strlen($token), '64 hex characters');
	pg_assert_same($token, pg_application_juror_token(7, 1800000000, $key), 'the same link can be mailed again');
	pg_assert_true($token !== pg_application_juror_token(8, 1800000000, $key), 'another juror, another link');
	pg_assert_true($token !== pg_application_juror_token(7, 1800000001, $key), 'a juror added again later, another link');
	pg_assert_true($token !== pg_application_juror_token(7, 1800000000, str_repeat('x', 32)), 'another site key');
	pg_assert_same('', pg_application_juror_token(7, 1800000000, ''), 'no key, no link');
	pg_assert_same('', pg_application_juror_token(0, 1800000000, $key), 'no row, no link');
}

function test_application_link_token_checked_against_its_hash()
{
	$token = pg_application_juror_token(7, 1800000000, str_repeat('k', 32));

	pg_assert_true(pg_application_link_token_valid($token, hash('sha256', $token)), 'the right token');
	pg_assert_false(pg_application_link_token_valid($token, hash('sha256', 'other')), 'another hash');
	pg_assert_false(pg_application_link_token_valid(strtoupper($token), hash('sha256', strtoupper($token))), 'only lower-case hex');
	pg_assert_false(pg_application_link_token_valid('', ''), 'no token');
	pg_assert_false(pg_application_link_token_valid($token, ''), 'a row with no hash opens nothing');
}

function test_application_juror_link_states()
{
	$juror = array('user_id' => 0, 'revoked_at' => 0);
	$now = 1800000000;

	pg_assert_same('invalid', pg_application_juror_link_state(null, 1, 0, $now), 'not found');
	pg_assert_same('invalid', pg_application_juror_link_state(array('user_id' => 4, 'revoked_at' => 0), 1, 0, $now), 'a panel user has no link');
	pg_assert_same('withdrawn', pg_application_juror_link_state(array('user_id' => 0, 'revoked_at' => $now - 5), 1, 0, $now), 'removed');
	pg_assert_same('open', pg_application_juror_link_state($juror, 1, $now - 10, $now), 'open programme: a past round end does not stop it');
	pg_assert_same('open', pg_application_juror_link_state($juror, 2, $now + 60, $now), 'closed but the round runs');
	pg_assert_same('expired', pg_application_juror_link_state($juror, 2, $now, $now), 'closed and the round ended');
	// Closing ends the entries, not the reviewing: with no round end set the
	// link stays open, or jurors could never score a closed programme's
	// round, and every round from the second runs on a closed programme.
	pg_assert_same('open', pg_application_juror_link_state($juror, 2, 0, $now), 'closed with no round end');
}

function test_application_reminder_goes_to_user_or_juror_with_their_link()
{
	$my = 'https://example.com/pinegrap/my_reviews.php';
	$user = pg_application_reminder_recipient(array('user_id' => 3, 'user_email' => 'u@example.com', 'email' => ''), $my);
	$juror = pg_application_reminder_recipient(array('user_id' => 0, 'user_email' => null, 'email' => 'j@example.com', 'juror_url' => 'https://example.com/pinegrap/application_juror.php?t=abc'), $my);
	$keyless = pg_application_reminder_recipient(array('user_id' => 0, 'user_email' => null, 'email' => 'j@example.com', 'juror_url' => ''), $my);
	$gone = pg_application_reminder_recipient(array('user_id' => 3, 'user_email' => null, 'email' => ''), $my);

	pg_assert_same(array('to' => 'u@example.com', 'link' => $my), $user, 'a panel user: My reviews');
	pg_assert_same(array('to' => 'j@example.com', 'link' => 'https://example.com/pinegrap/application_juror.php?t=abc'), $juror, 'a juror: their page');
	pg_assert_same('', $keyless['to'], 'a juror with no link is not mailed');
	pg_assert_same('', $gone['to'], 'a deleted user is not mailed');
}

function test_application_fix_fields_and_states()
{
	$reasons = array(
		array('rule' => 0, 'field' => 'cv', 'code' => 'missing'),
		array('rule' => 1, 'field' => 'age', 'code' => 'below'),
		array('rule' => 2, 'field' => 'cv', 'code' => 'missing'),
		'junk',
	);
	pg_assert_same(array('cv', 'age'), pg_application_fix_fields($reasons), 'each named field once, in order');

	$now = 1800000000;
	$entry = array('eligible' => 2);
	pg_assert_same('invalid', pg_application_fix_link_state(null, 1, 0, $now));
	pg_assert_same('open', pg_application_fix_link_state($entry, 1, $now + 60, $now));
	pg_assert_same('open', pg_application_fix_link_state($entry, 1, 0, $now), 'no closing time set');
	pg_assert_same('expired', pg_application_fix_link_state($entry, 1, $now, $now), 'the closing time passed');
	pg_assert_same('expired', pg_application_fix_link_state($entry, 2, $now + 60, $now), 'closed');
	pg_assert_same('done', pg_application_fix_link_state(array('eligible' => 1), 2, 0, $now), 'corrected, or let in, says so');
	pg_assert_same('done', pg_application_fix_link_state(array('eligible' => 3), 1, $now + 60, $now));
}

function test_application_fix_input_keeps_only_allowed_answers()
{
	$fields = array(
		array('id' => 5, 'type' => 'text box', 'options' => array()),
		array('id' => 6, 'type' => 'pick list', 'options' => array('a' => 'A', 'b' => 'B')),
		array('id' => 7, 'type' => 'check box', 'options' => array('x' => 'X', 'y' => 'Y')),
		array('id' => 8, 'type' => 'file upload', 'options' => array()),
	);
	$answers = pg_application_fix_input($fields, array(5 => '  21 ', 6 => 'c', 7 => array('x', 'z', 'x'), 8 => 'ignored', 9 => 'not opened'));

	pg_assert_same(array(5 => array('21'), 6 => array(), 7 => array('x')), $answers);

	$files = pg_application_fix_files(array('name' => array(8 => 'cv.pdf'), 'type' => array(8 => 'application/pdf'), 'tmp_name' => array(8 => '/tmp/x'), 'error' => array(8 => 0), 'size' => array(8 => 10)));
	pg_assert_same(array(8 => array('name' => 'cv.pdf', 'type' => 'application/pdf', 'tmp_name' => '/tmp/x', 'error' => 0, 'size' => 10)), $files);
	pg_assert_same(array(), pg_application_fix_files(null));
}

function test_application_juror_page_opens_only_the_links_reviews()
{
	$page = (string) file_get_contents(PG_FUNCTIONS_DIR . '/application_juror.php');

	pg_assert_contains('pg_application_juror_find($aj_token)', $page, 'the token is the credential');
	pg_assert_contains("pg_application_juror_review(\$aj_id, (int) \$aj_reviewer['id'])", $page, 'a review of this juror only');
	pg_assert_true(strpos($page, "if (\$aj_state !== 'open') {\n    \$aj_words") < strpos($page, 'pg_application_review_post('), 'a closed link writes nothing');
	pg_assert_false(strpos($page, 'validate_user(') !== false, 'no panel sign-in');
	pg_assert_contains("header('Referrer-Policy: no-referrer');", $page);

	$read = pg_test_function_source('pg_application_juror_review');
	pg_assert_contains('application_reviewers.id = ? AND application_reviewers.user_id = 0 AND application_reviewers.revoked_at = 0', $read, 'by the link\'s row, not removed');
	pg_assert_contains('application_reviews.status IN (0, 1, 2)', $read, 'a conflict is not opened');

	$fix = (string) file_get_contents(PG_FUNCTIONS_DIR . '/application_fix.php');
	pg_assert_contains('pg_application_fix_find($af_token)', $fix);
	pg_assert_true(strpos($fix, "if (\$af_state !== 'open') {") < strpos($fix, 'validate_token_field();'), 'a closed link writes nothing');

	$waf = (string) file_get_contents(PG_FUNCTIONS_DIR . '/waf.php');
	$start = strpos($waf, 'function waf_sensitive_scripts()');
	$list = substr($waf, $start, strpos($waf, "\n}\n", $start) - $start);
	pg_assert_contains("'application_juror.php'", $list, 'the juror page on the sensitive allowance');
	pg_assert_contains("'application_fix.php'", $list, 'the correction page on the sensitive allowance');
}

function test_application_removed_juror_keeps_the_hash_so_the_page_says_withdrawn()
{
	$remove = pg_test_function_source('pg_application_reviewer_remove');

	pg_assert_contains('UPDATE application_reviewers SET revoked_at = UNIX_TIMESTAMP() WHERE id = ?', $remove);
	pg_assert_false(strpos($remove, 'token_hash = NULL') !== false, 'the hash stays');
}
