<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * When Site Settings › E-mail sending offers and sends the test e-mail
 * (pg_mail_settings_ready()): an SMTP server email() sends through counts,
 * a relay without a login included.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once PG_FUNCTIONS_DIR . '/includes/fn/mail_settings.php';

function test_mail_settings_ready_smtp_server()
{
	pg_assert_true(pg_mail_settings_smtp_ready('smtp.example.com', 'noreply@example.com', 'secret'), 'a server with its login');
	// email() sends through a host without a username and signs in to none.
	pg_assert_true(pg_mail_settings_smtp_ready('localhost', '', ''), 'a relay without a login');
	pg_assert_false(pg_mail_settings_smtp_ready('smtp.example.com', 'noreply@example.com', ''), 'a username without its password');
	pg_assert_false(pg_mail_settings_smtp_ready('', 'noreply@example.com', 'secret'), 'no host');
}

function test_mail_settings_ready_reads_the_smtp_rule()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/mail_settings.php');
	$from = strpos($source, 'function pg_mail_settings_ready(');
	$body = substr($source, $from, strpos($source, "\nfunction ", $from + 1) - $from);

	pg_assert_contains('pg_mail_settings_smtp_ready(', $body, 'the ready check uses the SMTP rule');
}
