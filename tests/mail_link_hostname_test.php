<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that links written into e-mails are built from the hostname setting.
 * HOSTNAME and $_SERVER['HTTP_HOST'] are the Host header of the request that
 * sends the mail, which the client chooses: a member's affiliate sign-up or an
 * anonymous comment could point the administrator's "approve" or "edit" link
 * at any host. Redirects back to the visitor's own page keep using HOSTNAME.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The lines of a product file that put the request's host after the scheme,
// leaving out redirects (a Location header goes back to the requester).
function pg_test_mail_link_request_host_lines($file)
{
	$lines = array();

	foreach (file(PG_FUNCTIONS_DIR . '/' . $file) as $number => $line) {
		if (
			preg_match('/URL_SCHEME\s*\.\s*(\$_SERVER\[\'HTTP_HOST\'\]|HOSTNAME\b(?!_))/', $line)
			&& (stripos($line, 'Location:') === false)
		) {
			$lines[] = $file . ':' . ($number + 1);
		}
	}

	return $lines;
}

// Screens and helpers that e-mail a link to the site: account and password
// mails, the affiliate approval request and the comment notifications.
function test_mail_link_hostname_mail_screens_use_hostname_setting()
{
	$files = array(
		'add_user.php',
		'import_users.php',
		'reset_password.php',
		'forgot_password.php',
		'affiliate_sign_up_form.php',
		'add_comment.php',
		'email_a_friend.php',
		'includes/fn/mail.php',
		'includes/fn/widgets_account.php',
	);

	foreach ($files as $file) {
		pg_assert_same(array(), pg_test_mail_link_request_host_lines($file), $file);
	}
}

// The affiliate approval link and the comment links name the setting.
function test_mail_link_hostname_admin_links_name_the_setting()
{
	$affiliate = file_get_contents(PG_FUNCTIONS_DIR . '/affiliate_sign_up_form.php');
	$comment = file_get_contents(PG_FUNCTIONS_DIR . '/add_comment.php');

	pg_assert_true(strpos($affiliate, "URL_SCHEME . HOSTNAME_SETTING . PATH . SOFTWARE_DIRECTORY . '/approve_affiliate.php?id='") !== false, 'affiliate approval link');
	pg_assert_true(strpos($comment, "URL_SCHEME . HOSTNAME_SETTING . PATH . SOFTWARE_DIRECTORY . '/edit_comment.php?id='") !== false, 'comment edit link');
	pg_assert_same(3, substr_count($comment, 'URL_SCHEME . HOSTNAME_SETTING . PATH'), 'add_comment.php mail links');
}

// The Workspace's scheduled actions store and send the site's address too:
// a new web check starts from it and is run later by the job, and a
// webhook's payload names the site to another service. Both leave the
// request, so neither takes the host the operator's request happened to name.
function test_mail_link_hostname_workspace_scheduled_actions_use_the_setting()
{
	pg_assert_same(array(), pg_test_mail_link_request_host_lines('includes/workspace/scheduled.php'));
	pg_assert_same(2, substr_count(file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/scheduled.php'), 'URL_SCHEME . HOSTNAME_SETTING . (defined(\'PATH\') ? PATH : \'/\')'), 'the web check address and the webhook site');
}
