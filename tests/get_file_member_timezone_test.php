<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Guard over get_file.php's initialize_user(): whether a signed-in visitor
 * is an active member (USER_MEMBER, read by check_view_access() for files in
 * membership folders) is decided on the site's calendar day. get_file.php
 * never runs init.php, so the site time zone has to be set before that
 * date('Y-m-d'); on the server's zone a membership that ended yesterday on
 * the site still opened comment, form and record files for the hours the two
 * days differ.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_get_file_member_decided_in_site_timezone()
{
	$source = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/get_file.php');

	$start = strpos($source, 'function initialize_user()');
	pg_assert_true($start !== false, 'get_file.php has initialize_user()');

	$member = strpos($source, "define('USER_MEMBER'", (int) $start);
	pg_assert_true($member !== false, 'initialize_user() defines USER_MEMBER');

	$body = substr($source, (int) $start, (int) $member - (int) $start);

	pg_assert_true(strpos($body, 'initialize_timezone();') !== false,
		'the site time zone is set before the active-member date check');
}
