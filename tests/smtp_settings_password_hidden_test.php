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

// The cards of Site Settings › E-mail sending.
function smtp_settings_password_hidden_get_branch()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/mail_sending.php');
}

function test_smtp_settings_password_hidden_not_assigned()
{
	$branch = smtp_settings_password_hidden_get_branch();

	pg_assert_true($branch !== '', 'the cards are found');
	pg_assert_same(false, strpos($branch, "\$mail_value('SYSTEM_SMTP_PASSWORD')"), 'the system password is not drawn');
	pg_assert_same(false, strpos($branch, "\$mail_value('CAMPAIGN_SMTP_PASSWORD')"), 'the campaign password is not drawn');

	foreach (array('system', 'campaign') as $kind) {
		pg_assert_true(
			preg_match('/<input type="password" name="' . $kind . '_smtp_password"[^>]* value=""/', $branch) === 1,
			'the ' . $kind . ' password box is empty'
		);
	}
}

function test_smtp_settings_password_hidden_placeholder()
{
	$branch = smtp_settings_password_hidden_get_branch();

	pg_assert_contains("' . \$mail_system_kept . '>", $branch, 'the system password box says when a password is saved');
	pg_assert_contains("' . \$mail_campaign_kept . '>", $branch, 'the campaign password box says when a password is saved');
	pg_assert_contains("lang('Leave blank to keep the saved secret')", $branch, 'the placeholder is the existing sentence');
}
