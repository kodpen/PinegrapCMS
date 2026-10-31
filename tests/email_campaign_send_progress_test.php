<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Send E-mail Campaign window: it is drawn once and a fetch
 * loop sends the next batches through the script's JSON mode instead of a
 * meta refresh every second (kept only inside <noscript>); the JSON mode
 * refuses a bad token in JSON; the progress bar is named, its percent sits
 * beside it, and the count and the finish are announced in a status line.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function email_campaign_send_progress_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/send_email_campaign.php');
}

function test_email_campaign_send_refreshes_only_without_script()
{
	$src = email_campaign_send_progress_source();

	pg_assert_true(substr_count($src, 'http-equiv="refresh"') === 1, 'one meta refresh is left');
	pg_assert_true((bool) preg_match('/<noscript>\s*<meta http-equiv="refresh"/', $src), 'the meta refresh sits inside <noscript>');
	pg_assert_contains("format=json", $src, 'the window fetches the JSON mode');
	pg_assert_contains('fetch(', $src, 'the window sends the next batch with fetch');
}

function test_email_campaign_send_json_mode_refuses_in_json()
{
	$src = email_campaign_send_progress_source();

	pg_assert_contains("(\$_GET['format'] ?? '') === 'json'", $src, 'the JSON mode is chosen by ?format=json');
	pg_assert_contains("hash_equals(\$session_token, \$get_token)", $src, 'the JSON mode compares the token itself');
	pg_assert_contains('403', $src, 'a bad token is refused with 403');
	foreach (array("'sent'", "'total'", "'done'", "'error'") as $key) {
		pg_assert_contains($key, $src, 'the JSON answer carries ' . $key);
	}
	pg_assert_contains('pg_json_encode_answer(', $src, 'the answer is encoded with the shared helper');
}

function test_email_campaign_send_bar_is_named_and_announced()
{
	$src = email_campaign_send_progress_source();

	pg_assert_contains('<h3 id="campaign_send_heading">', $src, 'the sending heading has an id');
	pg_assert_contains('role="progressbar" aria-labelledby="campaign_send_heading"', $src, 'the bar is named by the heading');
	pg_assert_false((bool) preg_match('/role="progressbar"[^>]*>[^<]*\'\s*\.\s*lang\(/', $src), 'the percent text is not inside the bar');
	pg_assert_contains('<span id="campaign_send_percent"', $src, 'the percent sits beside the bar');
	pg_assert_contains('id="campaign_send_status" role="status"', $src, 'the count is announced in a status line');
	pg_assert_contains("lang('Campaign Sent')", $src, 'the finish is announced');
	pg_assert_contains('.focus()', $src, 'focus moves to Close when the campaign is done');
}
