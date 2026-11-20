<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the course certificate (includes/fn/course_certificate.php,
 * certificate.php): a member who did not complete is refused; a code made
 * for another course, a changed code or a reset completion does not
 * match; the public check is rate limited before any code is read; the
 * completion is announced as course.completed for webhooks and flows.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/course_blocks.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/course_certificate.php');

// A member's certificate request that passes every check, with $change.
function _test_course_certificate(array $change = array())
{
	return pg_course_certificate_refusal(array_merge(array(
		'on' => true, 'signed_in' => true, 'course' => true, 'certificate' => true, 'completed' => true,
	), $change));
}

// (Z13) A member who did not complete the course is refused; so is a
// visitor, a course without "Certificate" and the switch off.
function test_course_certificate_member_refused()
{
	pg_assert_same(null, _test_course_certificate(), 'a member who completed it gets the certificate');
	pg_assert_same(array(403, 'not_complete'), _test_course_certificate(array('completed' => false)), 'a member who did not complete');
	pg_assert_same(array(401, 'sign_in'), _test_course_certificate(array('signed_in' => false, 'completed' => false)), 'a visitor');
	pg_assert_same(array(404, 'certificate'), _test_course_certificate(array('certificate' => false)), '"Certificate" off');
	pg_assert_same(array(404, 'course'), _test_course_certificate(array('course' => false, 'certificate' => false)), 'no such course');
	pg_assert_same(array(404, 'off'), _test_course_certificate(array('on' => false, 'signed_in' => false)), 'the switch off first');

	$page = (string) file_get_contents(PG_FUNCTIONS_DIR . '/certificate.php');
	pg_assert_contains('pg_course_certificate_refusal(', $page);
	pg_assert_contains("'completed' => \$progress['completed_at'] > 0", $page, 'the completion is read for the signed-in member only');
	pg_assert_contains('pg_course_member_progress((int) USER_ID', $page);
}

// (Z14) A code names one completion of one course: a code made for another
// course, a changed code and a completion reset and written again are
// refused.
function test_course_certificate_code_of_another_course_refused()
{
	$key = 'test-key';
	$course = array('id' => 7, 'name' => 'Watercolour basics', 'certificate' => 1);
	$at = 1795000000;
	$code = pg_course_certificate_code(41, 7, $at, $key);
	$parsed = pg_course_certificate_parse($code, $key);

	pg_assert_true(pg_course_certificate_matches($parsed, $course, $at), 'the code of the member\'s completion matches');

	$other = pg_course_certificate_parse(pg_course_certificate_code(41, 8, $at, $key), $key);
	pg_assert_false(pg_course_certificate_matches($other, $course, $at), 'a code of another course against this course');
	pg_assert_false(pg_course_certificate_matches($other, array('id' => 8, 'certificate' => 1), 0), 'a code of a course the member did not complete');

	$changed = preg_replace('/^7-/', '8-', $code);
	pg_assert_same(null, pg_course_certificate_parse($changed, $key), 'a code whose course was changed by hand');
	pg_assert_same(null, pg_course_certificate_parse($code, 'another-key'), 'a code of another site');

	pg_assert_false(pg_course_certificate_matches($parsed, $course, $at + 60), 'a completion reset and written again');
	pg_assert_false(pg_course_certificate_matches($parsed, $course, 0), 'a completion reset');
	pg_assert_false(pg_course_certificate_matches($parsed, array('certificate' => 0) + $course, $at), '"Certificate" turned off');
	pg_assert_false(pg_course_certificate_matches(null, $course, $at), 'no code');
	pg_assert_true(pg_course_certificate_matches(pg_course_certificate_parse(strtolower($code), $key), $course, $at), 'a code typed in small letters');
}

// (Z14) The check is rate limited per address, and over the rate no code
// is read.
function test_course_certificate_check_rate()
{
	pg_assert_same(null, pg_course_check_refusal(array('on' => true, 'throttled' => false, 'valid' => true)));
	pg_assert_same(array(429, 'rate'), pg_course_check_refusal(array('on' => true, 'throttled' => true, 'valid' => true)), 'over the rate');
	pg_assert_same(array(429, 'rate'), pg_course_check_refusal(array('on' => true, 'throttled' => true, 'valid' => false)), 'the rate before the code');
	pg_assert_same(array(404, 'invalid'), pg_course_check_refusal(array('on' => true, 'throttled' => false, 'valid' => false)));
	pg_assert_same(array(404, 'off'), pg_course_check_refusal(array('on' => false, 'throttled' => true, 'valid' => true)));

	$rate = pg_course_rate('check');
	pg_assert_same(20, $rate['limit']);
	pg_assert_same(600, $rate['window']);
	pg_assert_same('course_check', $rate['counter'], 'its own counter, apart from views and presses');
	pg_assert_same('course_press', pg_course_rate('press')['counter']);
	pg_assert_same('course_view', pg_course_rate('view')['counter']);

	$page = (string) file_get_contents(PG_FUNCTIONS_DIR . '/certificate.php');
	$throttle = strpos($page, "pg_course_throttled('check')");
	pg_assert_true($throttle !== false, 'the check counts against its rate');
	pg_assert_true($throttle < strpos($page, 'pg_course_certificate_lookup('), 'the rate is counted before a code is read');
	pg_assert_contains('$throttled ? null : pg_course_certificate_lookup(', $page, 'over the rate no code is read');
}

// (Z10) The completion is announced once, by the press that wrote it, for
// webhooks and flows.
function test_course_certificate_completed_event()
{
	pg_assert_true(api_webhook_event_is_valid('course.completed'), 'a webhook may subscribe to it');
	pg_assert_true(in_array('course.completed', pg_event_workspace_events(), true), 'kept for the Workspace');

	$writer = pg_test_function_source('pg_course_mark_done');
	pg_assert_contains("if (\$out['completed'] && function_exists('pg_announce'))", $writer, 'only a new completion row is announced');
	pg_assert_contains("pg_announce('course.completed'", $writer);

	require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');
	pg_assert_same(array('contact', 884), ws_events_record_of('course.completed', array('id' => 3, 'contact_id' => 884)));
	pg_assert_same(array('', 0), ws_events_record_of('course.completed', array('id' => 3, 'contact_id' => null)), 'a member with no contact');
	pg_assert_true(isset(ws_flow_event_outputs()['course.completed']), 'the start box gives its values');
}

// (Z13) The "Course progress" block links to the certificate once the
// completion row is there and the course gives one.
function test_course_certificate_link_in_progress_block()
{
	$view = pg_test_function_source('pg_course_member_view');
	pg_assert_contains("!empty(\$course['certificate']) && (\$progress['completed_at'] > 0)", $view);
	pg_assert_contains("'/certificate.php?course='", $view);
	pg_assert_true(isset(pg_course_blocks_labels()['Certificate']), 'the link\'s text is translated');

	$script = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_course.js');
	pg_assert_contains('showCertificate(block, count || bar, course.certificate)', $script);
	pg_assert_contains("text(labels, 'Certificate')", $script);
}
