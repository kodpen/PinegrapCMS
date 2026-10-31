<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for the SMTP Settings action buttons. Send Test, Test DKIM and
 * Generate DKIM Key sit beside editable boxes, so each saves the boxes first
 * and acts on what was saved: the test e-mail goes out after the redirect
 * (the SMTP constants of the posting request still hold the old values),
 * Test DKIM checks the selector and domain just saved, and Generate DKIM Key
 * saves the selector and domain its TXT record names. Before, Send Test and
 * Test DKIM used the stored settings and dropped what was typed, and
 * Generate DKIM Key never saved the pair it built the record for.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function smtp_settings_test_buttons_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/smtp_settings.php');
}

// The posted branch of the action, from its isset() check to the redirect
// that ends it.
function smtp_settings_test_buttons_branch($post_name)
{
	$source = smtp_settings_test_buttons_source();
	$post = strpos($source, '} else {' . "\n" . '    validate_token_field();');
	$from = ($post === false) ? false : strpos($source, "isset(\$_POST['" . $post_name . "'])", $post);
	$to = ($from === false) ? false : strpos($source, 'exit;', $from);

	return (($from === false) || ($to === false)) ? '' : substr($source, $from, $to - $from);
}

function test_smtp_settings_test_buttons_save_is_one_function()
{
	$source = smtp_settings_test_buttons_source();

	pg_assert_contains('function smtp_settings_save($liveform_settings)', $source, 'the save is a function the actions can run');
	pg_assert_contains('smtp_settings_save($liveform_settings);', smtp_settings_test_buttons_branch('submit_save'), 'Save runs the same save');
	pg_assert_same(1, substr_count($source, 'pg_write_config_file('), 'config.php is written in one place');
}

function test_smtp_settings_test_buttons_generate_saves_first()
{
	$branch = smtp_settings_test_buttons_branch('generate_dkim_key');
	$save = strpos($branch, '$saved = smtp_settings_save($liveform_settings);');
	$stop = strpos($branch, 'if ($saved !== false)');
	$make = strpos($branch, 'pg_dkim_key_pair($selector, $domain)');

	pg_assert_true($save !== false, 'Generate DKIM Key saves the boxes');
	pg_assert_true(($stop !== false) && ($stop > $save) && ($make > $stop), 'a failed save stops before the pair is made');
	pg_assert_contains("\$selector = \$saved['dkim_selector'];", $branch, 'the pair is made for the saved selector');
	pg_assert_contains("\$domain = \$saved['dkim_domain'];", $branch, 'the pair is made for the saved domain');
}

function test_smtp_settings_test_buttons_test_dkim_uses_the_saved_pair()
{
	$branch = smtp_settings_test_buttons_branch('test_dkim');
	$save = strpos($branch, '$saved = smtp_settings_save($liveform_settings);');
	$stop = strpos($branch, 'if ($saved !== false)');
	$lookup = strpos($branch, 'dns_get_record(');

	pg_assert_true($save !== false, 'Test DKIM saves the boxes');
	pg_assert_true(($stop !== false) && ($stop > $save) && ($lookup > $stop), 'a failed save stops before the lookup');
	pg_assert_contains("\$selector = \$saved['dkim_selector'];", $branch, 'the saved selector is checked');
	pg_assert_contains("\$domain = \$saved['dkim_domain'];", $branch, 'the saved domain is checked');
	pg_assert_false(strpos($branch, 'DKIM_SELECTOR') !== false, 'the constant loaded before the save is not read');
	pg_assert_false(strpos($branch, 'DKIM_DOMAIN') !== false, 'the constant loaded before the save is not read');
}

function test_smtp_settings_test_buttons_send_test_runs_after_the_redirect()
{
	$source = smtp_settings_test_buttons_source();
	$branch = smtp_settings_test_buttons_branch('send_system_email');
	$save = strpos($branch, 'if (smtp_settings_save($liveform_settings) !== false)');
	$flag = strpos($branch, "\$_SESSION['smtp_settings_send_test'] = true;");

	pg_assert_true($save !== false, 'Send Test saves the boxes');
	pg_assert_true(($flag !== false) && ($flag > $save), 'the test is queued only after a successful save');
	pg_assert_false(strpos($branch, 'email(') !== false, 'the posting request does not send: its constants hold the old settings');

	$get = strpos($source, 'if (!$_POST) {');
	$post = strpos($source, 'validate_token_field();');
	$read = strpos($source, "if (!empty(\$_SESSION['smtp_settings_send_test']))");
	$unset = strpos($source, "unset(\$_SESSION['smtp_settings_send_test']);");
	$send = strpos($source, "'type' => 'system'");

	pg_assert_true(($read !== false) && ($read > $get) && ($read < $post), 'the next page view sends the test');
	pg_assert_true(($unset !== false) && ($unset > $read) && ($unset < $send), 'the flag is cleared before sending, so a reload does not send again');
	pg_assert_true(($send !== false) && ($send < $post), 'the test e-mail is sent on the page view');
}

function test_smtp_settings_test_buttons_labels_say_they_save()
{
	$source = smtp_settings_test_buttons_source();

	pg_assert_contains("lang('Save and send a test')", $source);
	pg_assert_contains("lang('Save and test')", $source);
	pg_assert_contains("lang('Save and generate a DKIM key')", $source);
	pg_assert_false(strpos($source, "lang('Send Test')") !== false, 'Send Test is relabelled');
	pg_assert_false(strpos($source, "lang('Test DKIM')") !== false, 'Test DKIM is relabelled');
	pg_assert_false(strpos($source, "lang('Generate DKIM Key')") !== false, 'Generate DKIM Key is relabelled');
}
