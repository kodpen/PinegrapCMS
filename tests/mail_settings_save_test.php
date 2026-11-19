<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * What Site Settings › E-mail sending writes (includes/settings/mail_sending.save.php,
 * includes/fn/mail_settings.php):
 * one save writes config.php, a kept SMTP password is checked before anything
 * is written, and each button runs after a successful save with the values
 * it saved. The test e-mail goes out on the pane read after the save, whose
 * constants hold the new settings.
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

// The category's save (Site Settings › E-mail sending).
function mail_settings_save_screen()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/mail_sending.save.php');
}

// The category's cards.
function mail_settings_save_cards()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/mail_sending.php');
}

// A button's branch of the save, from its post_value() check to the next
// branch or the end.
function mail_settings_save_branch($post_name)
{
	$source = mail_settings_save_screen();
	$from = strpos($source, "post_value('" . $post_name . "')");
	$to = ($from === false) ? false : strpos($source, '} elseif', $from);

	return ($from === false) ? '' : substr($source, $from, (($to === false) ? strlen($source) : $to) - $from);
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
	pg_assert_contains('$mail_saved = pg_mail_settings_save($_POST, $liveform);', mail_settings_save_screen(), 'the category saves through it');
	pg_assert_same(0, substr_count(mail_settings_save_screen(), 'pg_write_config_file('), 'the category writes nothing itself');
	// The campaign texts and MailChimp are config row columns, written once,
	// after the mail settings were saved.
	$screen = mail_settings_save_screen();
	pg_assert_same(1, substr_count($screen, 'UPDATE config'), 'the config row is written in one place');
	pg_assert_true(strpos($screen, 'UPDATE config') > strpos($screen, '$mail_saved = pg_mail_settings_save('), 'the config row is written after the mail settings');
	pg_assert_true(strpos($screen, "'path' => '/ping'") < strpos($screen, '$mail_saved = pg_mail_settings_save('), 'Mailchimp is asked before anything is written');
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
	$source = mail_settings_save_screen();
	$save = strpos($source, '$mail_saved = pg_mail_settings_save(');
	$stop = strpos($source, 'if ($mail_saved !== false) {');

	pg_assert_true(($save !== false) && ($stop > $save), 'the boxes are saved first, and a failed save stops');

	foreach (array('generate_dkim_key' => 'pg_mail_settings_dkim_generate($mail_saved', 'test_dkim' => 'pg_mail_settings_dkim_test($mail_saved') as $button => $job) {
		$branch = mail_settings_save_branch($button);

		pg_assert_contains($job, $branch, $button . ' runs its job with the saved values');
		pg_assert_true(strpos($source, "post_value('" . $button . "')") > $stop, $button . ': only after a successful save');
	}

	foreach (array('pg_mail_settings_dkim_generate', 'pg_mail_settings_dkim_test') as $name) {
		$body = mail_settings_save_function($name);
		pg_assert_contains("\$selector = \$saved['dkim_selector'];", $body, $name . ': the saved selector');
		pg_assert_contains("\$domain = \$saved['dkim_domain'];", $body, $name . ': the saved domain');
		pg_assert_false(strpos($body, 'DKIM_SELECTOR') !== false, $name . ': the constant loaded before the save is not read');
		pg_assert_false(strpos($body, 'DKIM_DOMAIN') !== false, $name . ': the constant loaded before the save is not read');
	}
}

// The posting request only sets the flag; the pane read after it sends.
function test_mail_settings_save_test_mail_waits_for_the_next_view()
{
	$source = mail_settings_save_screen();
	$branch = mail_settings_save_branch('send_system_email');
	$send = mail_settings_save_function('pg_mail_settings_send_test');
	$unset = strpos($send, "unset(\$_SESSION['smtp_settings_send_test']);");
	$mail = strpos($send, "'type'               => 'system'");
	$cards = mail_settings_save_cards();
	$guard = strpos($cards, "if (empty(\$_GET['search'])) {");
	$read = strpos($cards, 'pg_mail_settings_send_test($liveform);');

	pg_assert_contains("\$_SESSION['smtp_settings_send_test'] = true;", $branch, 'the test is queued by its button');
	pg_assert_true(strpos($source, "post_value('send_system_email')") > strpos($source, 'if ($mail_saved !== false) {'), 'the test is queued only after a successful save');
	pg_assert_false(strpos($source, 'email(') !== false, 'the posting request does not send: its constants hold the old settings');
	pg_assert_true(($guard !== false) && ($read > $guard), 'the cards send the test, never while the search index draws them');
	pg_assert_true(($unset !== false) && ($mail !== false) && ($unset < $mail), 'the flag is cleared before sending, so a reload does not send again');
}

// The labels say the buttons save first.
function test_mail_settings_save_labels_say_they_save()
{
	$source = mail_settings_save_cards();

	pg_assert_contains("lang('Save and send a test')", $source);
	pg_assert_contains("lang('Save and test')", $source);
	pg_assert_contains("lang('Save and generate a DKIM key')", $source);
}

// The System card's account select: this server first, then each account
// connected for sending, the stored one chosen, one to reconnect marked.
function test_mail_settings_connection_field_lists_the_accounts()
{
	$accounts = array(
		array('id' => 7, 'provider' => 'google', 'label' => 'news@example.com', 'status' => 'active'),
		array('id' => 9, 'provider' => 'microsoft', 'label' => 'shop@example.com', 'status' => 'reauth'),
	);

	$field = pg_mail_settings_connection_field('system_mail_connection', 9, $accounts);

	pg_assert_contains('<select name="system_mail_connection" id="setting_system_mail_connection" class="form-control">', $field, 'named as the save reads it');
	pg_assert_true(strpos($field, '<option value="">') < strpos($field, '<option value="7"'), 'this server comes first');
	pg_assert_contains('<option value="9" selected>', $field, 'the stored account is chosen');
	pg_assert_false(strpos($field, '<option value="7" selected>') !== false, 'only the stored account is chosen');
	pg_assert_contains('shop@example.com', $field, 'each account by its address');
	pg_assert_same(1, substr_count($field, ' - '), 'only the account to reconnect is marked');
	pg_assert_contains('<option value="">', pg_mail_settings_connection_field('campaign_mail_connection', 0, array()), 'no account: this server alone');
}
