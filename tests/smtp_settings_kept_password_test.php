<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * An empty SMTP password box on SMTP Settings keeps the saved password only
 * for the server it was typed for: SMTP AUTH sends the password itself, so a
 * kept password would reach any new host or port a later save names.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_smtp_password_kept_only_for_the_same_server()
{
	$stored = array('host' => 'smtp.example.com', 'port' => '587', 'password' => 's3cret');

	$posted = function ($changes) use ($stored) {
		return array_merge($stored, array('password' => ''), $changes);
	};

	pg_assert_same('s3cret', pg_smtp_password_kept($posted(array()), $stored), 'same server');
	pg_assert_same('s3cret', pg_smtp_password_kept($posted(array('host' => ' SMTP.Example.com ')), $stored), 'host case and spaces');
	pg_assert_same('s3cret', pg_smtp_password_kept($posted(array('port' => '')), $stored), 'an empty port is 587');
	pg_assert_same('', pg_smtp_password_kept($posted(array('host' => 'smtp.attacker.example')), $stored), 'another host');
	pg_assert_same('', pg_smtp_password_kept($posted(array('port' => '25')), $stored), 'another port');
	pg_assert_same('', pg_smtp_password_kept($posted(array('host' => '')), $stored), 'host cleared');
	pg_assert_same('typed', pg_smtp_password_kept($posted(array('host' => 'smtp.other.example', 'password' => 'typed')), $stored), 'a typed password');
	pg_assert_same('', pg_smtp_password_kept($posted(array()), array_merge($stored, array('password' => ''))), 'nothing saved');

	$implicit = array_merge($stored, array('port' => '465'));
	pg_assert_same('', pg_smtp_password_kept($posted(array('port' => '587')), $implicit), 'implicit TLS dropped');
}

// The save checks the kept password before anything is written, and drops it
// when the server changed.
function test_smtp_settings_save_uses_the_kept_password_check()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/smtp_settings.php');
	$from = strpos($source, 'function smtp_settings_save(');
	$body = ($from === false) ? '' : substr($source, $from, 6000);

	pg_assert_true($body !== '', 'the save function is found');

	$check = strpos($body, 'pg_smtp_password_kept(');
	$transport = strpos($body, 'pg_mail_transport_settings_save(');

	pg_assert_true($check !== false, 'the save asks pg_smtp_password_kept()');
	pg_assert_true(($transport !== false) && ($check < $transport), 'the check comes before any setting is written');
	pg_assert_contains("lang('The SMTP server or its port changed. Enter the password again.')", $body, 'the refusal names the cause');
}
