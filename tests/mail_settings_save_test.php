<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * What SMTP Settings writes (smtp_settings.php, includes/fn/mail_settings.php):
 * one save writes config.php, a kept SMTP password is checked before anything
 * is written, and each button runs after a successful save with the values
 * it saved. The test e-mail goes out on the next page view, whose constants
 * hold the new settings.
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

function mail_settings_save_module()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/mail_settings.php');
}

function mail_settings_save_screen()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/smtp_settings.php');
}

// The posted branch of a button, from its isset() check to the redirect
// that ends it.
function mail_settings_save_branch($post_name)
{
	$source = mail_settings_save_screen();
	$post = strpos($source, 'validate_token_field();');
	$from = ($post === false) ? false : strpos($source, "isset(\$_POST['" . $post_name . "'])", $post);
	$to = ($from === false) ? false : strpos($source, 'exit;', $from);

	return (($from === false) || ($to === false)) ? '' : substr($source, $from, $to - $from);
}

// A function's body, from its declaration to the next one.
function mail_settings_save_function($name)
{
	$source = mail_settings_save_module();
	$from = strpos($source, 'function ' . $name . '(');
	$to = ($from === false) ? false : strpos($source, "\nfunction ", $from + 1);

	return ($from === false) ? '' : substr($source, $from, (($to === false) ? strlen($source) : $to) - $from);
}

function test_mail_settings_save_is_one_function()
{
	pg_assert_same(1, substr_count(mail_settings_save_module(), 'pg_write_config_file('), 'config.php is written in one place');
	pg_assert_contains('$saved = pg_mail_settings_save($_POST, $liveform_settings);', mail_settings_save_screen(), 'the screen saves through it');
	pg_assert_same(0, substr_count(mail_settings_save_screen(), 'pg_write_config_file('), 'the screen writes nothing itself');
	pg_assert_same(array(
		'SYSTEM_SMTP_HOSTNAME', 'SYSTEM_SMTP_USERNAME', 'SYSTEM_SMTP_PASSWORD', 'SYSTEM_SMTP_PORT',
		'EMAIL_CAMPAIGN_JOB', 'EMAIL_CAMPAIGN_JOB_NUMBER_OF_EMAILS',
		'CAMPAIGN_SMTP_HOSTNAME', 'CAMPAIGN_SMTP_USERNAME', 'CAMPAIGN_SMTP_PASSWORD', 'CAMPAIGN_SMTP_PORT',
		'DKIM_DOMAIN', 'DKIM_SELECTOR',
		'SYSTEM_MAIL_CONNECTION', 'CAMPAIGN_MAIL_CONNECTION',
	), pg_mail_settings_keys(), 'the defines SMTP Settings wrote, no more');
}

// The kept password is checked before anything is written, and dropped when
// the server changed.
function test_mail_settings_save_uses_the_kept_password_check()
{
	$body = mail_settings_save_function('pg_mail_settings_save');
	$check = strpos($body, 'pg_smtp_password_kept(');
	$transport = strpos($body, 'pg_mail_transport_settings_save(');

	pg_assert_true($check !== false, 'the save asks pg_smtp_password_kept()');
	pg_assert_true(($transport !== false) && ($check < $transport), 'the check comes before any setting is written');
	pg_assert_contains("lang('The SMTP server or its port changed. Enter the password again.')", $body, 'the refusal names the cause');
	pg_assert_contains("\$liveform->mark_error(\$kind . '_smtp_password',", $body, 'the refusal marks the password box');
}

// Each button's job runs only after a successful save, with what it saved.
function test_mail_settings_save_buttons_run_after_the_save()
{
	foreach (array('generate_dkim_key' => 'pg_mail_settings_dkim_generate($saved', 'test_dkim' => 'pg_mail_settings_dkim_test($saved') as $button => $job) {
		$branch = mail_settings_save_branch($button);
		$save = strpos($branch, '$saved = smtp_settings_save($liveform_settings);');
		$stop = strpos($branch, 'if ($saved !== false)');
		$run = strpos($branch, $job);

		pg_assert_true($save !== false, $button . ' saves the boxes');
		pg_assert_true(($stop !== false) && ($stop > $save) && ($run > $stop), $button . ': a failed save stops before the job');
	}

	foreach (array('pg_mail_settings_dkim_generate', 'pg_mail_settings_dkim_test') as $name) {
		$body = mail_settings_save_function($name);
		pg_assert_contains("\$selector = \$saved['dkim_selector'];", $body, $name . ': the saved selector');
		pg_assert_contains("\$domain = \$saved['dkim_domain'];", $body, $name . ': the saved domain');
		pg_assert_false(strpos($body, 'DKIM_SELECTOR') !== false, $name . ': the constant loaded before the save is not read');
		pg_assert_false(strpos($body, 'DKIM_DOMAIN') !== false, $name . ': the constant loaded before the save is not read');
	}
}

// The posting request only sets the flag; the next page view sends.
function test_mail_settings_save_test_mail_waits_for_the_next_view()
{
	$source = mail_settings_save_screen();
	$branch = mail_settings_save_branch('send_system_email');
	$send = mail_settings_save_function('pg_mail_settings_send_test');
	$unset = strpos($send, "unset(\$_SESSION['smtp_settings_send_test']);");
	$mail = strpos($send, "'type'               => 'system'");
	$get = strpos($source, 'if (!$_POST) {');
	$post = strpos($source, 'validate_token_field();');
	$read = strpos($source, 'pg_mail_settings_send_test($liveform);');

	pg_assert_true(strpos($branch, "\$_SESSION['smtp_settings_send_test'] = true;") > strpos($branch, 'smtp_settings_save('), 'the test is queued only after a successful save');
	pg_assert_false(strpos($branch, 'email(') !== false, 'the posting request does not send: its constants hold the old settings');
	pg_assert_true(($read !== false) && ($read > $get) && ($read < $post), 'the next page view sends the test');
	pg_assert_true(($unset !== false) && ($mail !== false) && ($unset < $mail), 'the flag is cleared before sending, so a reload does not send again');
}

// The labels say the buttons save first.
function test_mail_settings_save_labels_say_they_save()
{
	$source = mail_settings_save_screen();

	pg_assert_contains("lang('Save and send a test')", $source);
	pg_assert_contains("lang('Save and test')", $source);
	pg_assert_contains("lang('Save and generate a DKIM key')", $source);
}
