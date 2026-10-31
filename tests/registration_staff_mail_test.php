<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The registration and membership staff notices (REGISTRATION_EMAIL_ADDRESS,
 * MEMBERSHIP_EMAIL_ADDRESS) mail the visitor's confirmation screen. In the
 * e-mail its "Continue" link is on the site's own host (HOSTNAME_SETTING),
 * not the sign-up request's Host header, and subject and body are built in
 * the software language. The visitor's own screen keeps HOSTNAME.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

/**
 * The part of a file's source between two markers, '' when either is missing.
 *
 * @param string $file
 * @param string $from
 * @param string $to
 * @return string
 */
function registration_staff_mail_part($file, $from, $to)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
	$start = strpos($source, $from);
	$end = ($start === false) ? false : strpos($source, $to, $start);

	return (($start === false) || ($end === false)) ? '' : substr($source, $start, $end - $start);
}

// Both notices: built under the staff-language hold, on the site's host.
function test_registration_staff_mail_notices_use_setting_and_staff_language()
{
	$notices = array(
		'REGISTRATION_EMAIL_ADDRESS' => 'get_registration_confirmation_screen(HOSTNAME_SETTING)',
		'MEMBERSHIP_EMAIL_ADDRESS' => 'get_membership_confirmation_screen(HOSTNAME_SETTING)',
	);

	foreach ($notices as $constant => $body) {
		$part = registration_staff_mail_part('includes/fn/auth.php', 'if (' . $constant . ') {', '$form->remove();');

		pg_assert_contains('pg_staff_lang(function ()', $part, $constant . ' built in the software language');
		pg_assert_contains($body, $part, $constant . ' body on the site host');
		pg_assert_true(strpos($part, 'pg_staff_lang(') < strpos($part, "lang('Registration Confirmation')"), $constant . ' subject inside the builder');
		pg_assert_true(strpos($part, 'pg_staff_lang(') < strpos($part, $body), $constant . ' body inside the builder');
	}
}

// The default content takes the host as an argument; without one it stays
// on the request host, so the visitor's screen is unchanged.
function test_registration_staff_mail_content_host_argument()
{
	foreach (array('registration', 'membership') as $kind) {
		$file = 'get_' . $kind . '_confirmation_screen_content.php';
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_contains('function get_' . $kind . '_confirmation_screen_content($host = null)', $source, $file);
		pg_assert_contains('$host = HOSTNAME;', $source, $file . ' default host');
		pg_assert_contains("URL_SCHEME . \$host . h(pg_safe_redirect_path(", $source, $file . ' link host');
		pg_assert_false((bool) preg_match('/URL_SCHEME\s*\.\s*HOSTNAME\b/', $source), $file . ' no fixed request host');
	}
}

// The wrappers hand the host to the default content and, on a designed
// confirmation page, to its system region; with no host the designed page
// still renders its own system content as before.
function test_registration_staff_mail_wrappers_pass_host()
{
	foreach (array('registration', 'membership') as $kind) {
		$part = registration_staff_mail_part('includes/fn/forms.php', 'function get_' . $kind . '_confirmation_screen(', "\n}\n");

		pg_assert_contains('function get_' . $kind . '_confirmation_screen($host = null)', $part, $kind);
		pg_assert_contains('output_header_secure() . get_' . $kind . '_confirmation_screen_content($host) . output_footer_secure()', $part, $kind . ' default screen');
		pg_assert_contains("\$system_content = (\$host === null) ? '' : get_" . $kind . '_confirmation_screen_content($host);', $part, $kind . ' designed page');
	}
}
