<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for the SMTP Settings password boxes. The screen filled the
 * boxes from the stored SYSTEM_SMTP_PASSWORD and CAMPAIGN_SMTP_PASSWORD, so
 * both passwords sat in the page source and in the session's form state.
 * They are drawn empty now (an empty box keeps the saved value), with a
 * placeholder telling that a password is saved.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The screen's GET branch, from the "not submitted" check to the posted one.
function smtp_settings_password_hidden_get_branch()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/smtp_settings.php');
	$from = strpos($source, 'if (!$_POST) {');
	$to = ($from === false) ? false : strpos($source, '} else {' . "\n" . '    validate_token_field();', $from);

	return (($from === false) || ($to === false)) ? '' : substr($source, $from, $to - $from);
}

function test_smtp_settings_password_hidden_not_assigned()
{
	$branch = smtp_settings_password_hidden_get_branch();

	pg_assert_true($branch !== '', 'the GET branch is found');

	// The list the boxes are filled from names no password.
	$list_from = strpos($branch, '$config_keys = [');
	$list_to = ($list_from === false) ? false : strpos($branch, '];', $list_from);
	$list = (($list_from === false) || ($list_to === false)) ? '' : substr($branch, $list_from, $list_to - $list_from);

	pg_assert_true($list !== '', 'the filled boxes are listed');
	pg_assert_same(false, strpos($list, 'SMTP_PASSWORD'), 'no stored password fills a box');
	pg_assert_same(false, strpos($branch, "assign_field_value('system_smtp_password'"), 'the system password is not assigned');
	pg_assert_same(false, strpos($branch, "assign_field_value('campaign_smtp_password'"), 'the campaign password is not assigned');
}

function test_smtp_settings_password_hidden_placeholder()
{
	$branch = smtp_settings_password_hidden_get_branch();

	foreach (array('system', 'campaign') as $kind) {
		pg_assert_true(
			preg_match("/'name'=>'" . $kind . "_smtp_password'[^\\]]*'placeholder'=>/", $branch) === 1,
			'the ' . $kind . ' password box says when a password is saved'
		);
	}

	pg_assert_contains("lang('Leave blank to keep the saved secret')", $branch, 'the placeholder is the existing sentence');
}
