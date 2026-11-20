<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * What Site Settings › E-mail sending shows when Mailchimp refuses the
 * MailChimp card (includes/settings/mail_sending.save.php): Mailchimp's own
 * reason, never the request mailchimp_request() appends to its message,
 * which carries the API key the card never prints back.
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

function test_mail_settings_mailchimp_reason_leaves_the_key_out()
{
	// Built at run time, so no key-shaped literal sits in the repository.
	$key = str_repeat('k', 32) . '-us6';
	$request = array('path' => '/lists/abc?fields=id', 'key' => $key);
	$message = lang('MailChimp Error') . ': Resource Not Found. The requested resource could not be found.' . "\n"
		. lang('Response') . ': ' . print_r(array('status' => 404), true) . "\n"
		. lang('Request') . ': ' . print_r($request, true);

	$reason = pg_mail_settings_mailchimp_reason($message, $key);

	pg_assert_same(lang('MailChimp Error') . ': Resource Not Found. The requested resource could not be found.', $reason, 'Mailchimp\'s reason alone');
	pg_assert_false(strpos($reason, $key) !== false, 'the key is not shown');

	// A connection failure: the cURL lines stay, the request goes.
	$message = lang('MailChimp Error') . "\n" . lang('cURL Error Number') . ': 28.' . "\n" . lang('cURL Error Message') . ': timed out.' . "\n" . lang('Request') . ': ' . print_r($request, true);
	$reason = pg_mail_settings_mailchimp_reason($message, $key);

	pg_assert_contains('timed out', $reason, 'the cURL reason stays');
	pg_assert_false(strpos($reason, $key) !== false, 'the key is not shown after a cURL failure');
	// A key quoted anywhere else in the text is masked too.
	pg_assert_false(strpos(pg_mail_settings_mailchimp_reason('Bad key ' . $key, $key), $key) !== false, 'a quoted key is masked');
}

function test_mail_settings_mailchimp_reason_used_by_every_refusal()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/mail_sending.save.php');

	pg_assert_same(0, substr_count($source, "h(\$mailchimp_answer['message'])"), 'no refusal prints the whole message');
	pg_assert_same(5, substr_count($source, 'pg_mail_settings_mailchimp_reason($mailchimp_answer[\'message\'], $mailchimp_key_post)'), 'each of the five refusals shows the reason');
}
