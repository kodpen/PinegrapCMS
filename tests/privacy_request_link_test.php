<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the download link of an information request's answer
 * (includes/fn/privacy_request_link.php, privacy_download.php): the
 * signature over the request, the file and the end, the 7 days, the file
 * name, the link's state in the drawer, the page's one sentence and
 * headers, no attachment, and nothing of the link in the API.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/privacy_requests.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/privacy.php');

function privacy_request_link_test_query($id, $end, $nonce, $signature)
{
	return array('r' => (string) $id, 'e' => (string) $end, 'n' => $nonce, 's' => $signature);
}

// The file is named by the request, the end and a random part, and read back.
function test_privacy_request_link_file_name()
{
	$nonce = str_repeat('ab', 16);

	pg_assert_same('7-1800000000-' . $nonce . '.json', pg_privacy_link_file_name(7, 1800000000, $nonce), 'the name');
	pg_assert_same(array('id' => 7, 'expires_at' => 1800000000, 'nonce' => $nonce), pg_privacy_link_file_parse('7-1800000000-' . $nonce . '.json'), 'read back');
	pg_assert_same('', pg_privacy_link_file_name(7, 1800000000, '../x'), 'no path in a name');
	pg_assert_same('', pg_privacy_link_file_name(0, 1800000000, $nonce), 'no request');
	pg_assert_same(null, pg_privacy_link_file_parse('7-1800000000-' . $nonce . '.json.tmp'), 'a half-written file is not a link');
	pg_assert_same(null, pg_privacy_link_file_parse('.htaccess'), 'another file');
	pg_assert_same(604800, pg_privacy_link_ttl(), '7 days');
}

// The signature covers the request, the file and the end, under its own prefix.
function test_privacy_request_link_signature()
{
	$key = 'test-key';
	$nonce = str_repeat('ab', 16);
	$end = 1800000000;
	$signature = pg_privacy_link_signature(7, $nonce, $end, $key);

	pg_assert_same(64, strlen($signature), 'a sha256 HMAC');
	pg_assert_false($signature === pg_privacy_link_signature(8, $nonce, $end, $key), 'another request');
	pg_assert_false($signature === pg_privacy_link_signature(7, str_repeat('cd', 16), $end, $key), 'another file');
	pg_assert_false($signature === pg_privacy_link_signature(7, $nonce, $end + 1, $key), 'another end');
	pg_assert_false($signature === pg_privacy_link_signature(7, $nonce, $end, 'other-key'), 'another key');
	pg_assert_false($signature === hash_hmac('sha256', 'privacy-request-proof:7:' . $end . ':' . $nonce, $key), 'not a proof link');
	pg_assert_same('', pg_privacy_link_signature(7, $nonce, $end, ''), 'no key, no link');

	pg_assert_same(null, pg_privacy_link_parse(privacy_request_link_test_query(7, $end, $nonce, 'abc')), 'a short signature');
	pg_assert_same(null, pg_privacy_link_parse(privacy_request_link_test_query('07', $end, $nonce, $signature)), 'a padded id');
	pg_assert_same(null, pg_privacy_link_parse(array('r' => array('7'), 'e' => (string) $end, 'n' => $nonce, 's' => $signature)), 'an array');
	pg_assert_same(null, pg_privacy_link_parse(privacy_request_link_test_query(7, $end, '../../config', $signature)), 'a path as the file');
}

// Past 7 days, a changed signature and a withdrawn link all fail alike.
function test_privacy_request_link_valid()
{
	$key = 'test-key';
	$nonce = str_repeat('ab', 16);
	$now = 1800000000;
	$end = $now + 3600;
	$signature = pg_privacy_link_signature(7, $nonce, $end, $key);
	$link = pg_privacy_link_parse(privacy_request_link_test_query(7, $end, $nonce, $signature));
	$answered = array('kind' => 'information', 'status' => 'answered');

	pg_assert_true(pg_privacy_link_valid($link, $answered, $key, $now, true), 'a working link');
	pg_assert_false(pg_privacy_link_valid($link, $answered, $key, $end, true), 'at its end');
	pg_assert_false(pg_privacy_link_valid($link, $answered, $key, $end + 86400, true), 'past 7 days');

	$changed = $link;
	$changed['s'] = str_repeat('0', 64);
	pg_assert_false(pg_privacy_link_valid($changed, $answered, $key, $now, true), 'a changed signature');

	$moved = $link;
	$moved['e'] = $end + 86400;
	pg_assert_false(pg_privacy_link_valid($moved, $answered, $key, $now, true), 'a moved end');

	pg_assert_false(pg_privacy_link_valid($link, $answered, $key, $now, false), 'withdrawn: the file is gone');
	pg_assert_false(pg_privacy_link_valid($link, null, $key, $now, true), 'no request');
	pg_assert_false(pg_privacy_link_valid($link, array('kind' => 'erasure', 'status' => 'answered'), $key, $now, true), 'another kind');
	pg_assert_false(pg_privacy_link_valid($link, array('kind' => 'information', 'status' => 'open'), $key, $now, true), 'not answered');
	pg_assert_false(pg_privacy_link_valid(null, $answered, $key, $now, true), 'not a link');

	$far = pg_privacy_link_signature(7, $nonce, $now + (30 * 86400), $key);
	pg_assert_false(pg_privacy_link_valid(pg_privacy_link_parse(privacy_request_link_test_query(7, $now + (30 * 86400), $nonce, $far)), $answered, $key, $now, true), 'never longer than 7 days');
}

// The drawer: active until the end, ended, withdrawn; the last download since the link.
function test_privacy_request_link_state()
{
	$now = 1800000000;
	$history = array(
		array('action' => 'download', 'created_at' => $now - 100),
		array('action' => 'download', 'created_at' => $now - 500),
		array('action' => 'link', 'created_at' => $now - 1000),
		array('action' => 'download', 'created_at' => $now - 9000),
		array('action' => 'link', 'created_at' => $now - 10000),
		array('action' => 'answer', 'created_at' => $now - 10000),
	);

	pg_assert_same(array('state' => 'active', 'expires_at' => $now + 60, 'downloaded_at' => $now - 100), pg_privacy_link_state($history, $now + 60, $now), 'active, downloaded');
	pg_assert_same(array('state' => 'ended', 'expires_at' => 0, 'downloaded_at' => $now - 100), pg_privacy_link_state($history, null, $now), 'ended');
	pg_assert_same(array('state' => 'active', 'expires_at' => $now + 60, 'downloaded_at' => 0), pg_privacy_link_state(array_slice($history, 2), $now + 60, $now), 'not downloaded through the new link');

	$withdrawn = array_merge(array(array('action' => 'revoke', 'created_at' => $now - 50)), $history);
	pg_assert_same(array('state' => 'withdrawn', 'expires_at' => 0, 'downloaded_at' => $now - 100), pg_privacy_link_state($withdrawn, null, $now), 'withdrawn after a download');
	pg_assert_same(array('state' => 'none', 'expires_at' => 0, 'downloaded_at' => 0), pg_privacy_link_state(array(array('action' => 'answer', 'created_at' => $now)), null, $now), 'no link');
}

// The page: one sentence with 410 for every failure, no-store, noindex, a download.
function test_privacy_request_link_page()
{
	$page = (string) file_get_contents(PG_FUNCTIONS_DIR . '/privacy_download.php');

	pg_assert_contains("lang('This link has ended. Ask the site for a new one.')", $page, 'the one sentence');
	pg_assert_same(1, substr_count($page, 'http_response_code('), 'one answer for every failure');
	pg_assert_contains('http_response_code(410);', $page, 'gone');
	pg_assert_contains("header('Cache-Control: no-store, private');", $page, 'no-store');
	pg_assert_contains("header('X-Robots-Tag: noindex, nofollow');", $page, 'noindex');
	pg_assert_contains("header('Content-Disposition: attachment; filename=", $page, 'a download');
	pg_assert_true(strpos($page, "pg_audit('download'") < strpos($page, 'readfile('), 'logged before it is sent');
	pg_assert_true(strpos($page, 'pg_privacy_link_valid(') < strpos($page, 'readfile('), 'checked before a byte');
	pg_assert_true(in_array('privacy_download.php', privacy_request_form_link_test_sensitive_scripts(), true), 'on the sensitive allowance');
}

// The answer carries the link under its text and no attachment; the link stays out of the kept answer.
function test_privacy_request_link_mail()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/privacy_requests.php');
	$start = strpos($source, 'function pg_privacy_request_send(');
	$send = substr($source, $start, strpos($source, "\n}\n", $start) - $start);

	pg_assert_contains("\$text . \"\\n\\n\" . pg_privacy_link_mail_lines(\$link['url'])", $send, 'the link under the text');
	pg_assert_contains("'answer'    => mb_substr(\$text, 0, 60000, 'UTF-8')", $send, 'the kept answer has no link');
	pg_assert_false(stripos($send, 'attach') !== false, 'no attachment');

	$lines = pg_privacy_link_mail_lines('https://example.com/privacy_download.php?r=1');
	pg_assert_same('https://example.com/privacy_download.php?r=1', substr($lines, -strlen('https://example.com/privacy_download.php?r=1')), 'the link last');
}

// The API's steps keep nothing of a link line but its action and time.
function test_privacy_request_link_not_in_api()
{
	$row = array('id' => 7, 'kind' => 'information', 'status' => 'answered', 'source' => 'staff', 'result' => 'sent', 'contact_id' => 0, 'user_id' => 0, 'assigned_user_id' => 0,
		'email' => 'a@example.com', 'name' => '', 'verified_at' => 1, 'received_at' => 1, 'due_at' => 2, 'closed_at' => 3, 'message' => '', 'answer' => 'Hello,');
	$history = array(array('created_at' => 5, 'action' => 'link', 'user_id' => 2, 'username' => 'admin', 'after_json' => '{"expires_at":604805}'));
	$out = api_privacy_request_detail_present($row, $history, 10);

	pg_assert_same(array('action', 'at', 'user_id', 'user_name', 'kind', 'status', 'result'), array_keys($out['steps'][0]), 'no end, file or signature');
	pg_assert_false(strpos(json_encode($out), 'privacy_download') !== false, 'no link');
}

function privacy_request_form_link_test_sensitive_scripts()
{
	if (!function_exists('waf_sensitive_scripts')) {
		$waf = (string) file_get_contents(PG_FUNCTIONS_DIR . '/waf.php');
		$start = strpos($waf, 'function waf_sensitive_scripts()');
		$body = substr($waf, $start, strpos($waf, "\n}\n", $start) - $start);
		preg_match_all("/'([a-z_]+\\.php)'/", $body, $match);

		return $match[1];
	}

	return waf_sensitive_scripts();
}

// An erasure ends the person's link: the anonymised request no longer serves
// its file, and the erasure deletes the file with the row's address.
function test_privacy_request_link_ends_with_erasure()
{
	$key = 'test-key';
	$nonce = str_repeat('ab', 16);
	$now = 1800000000;
	$end = $now + 3600;
	$link = pg_privacy_link_parse(privacy_request_link_test_query(7, $end, $nonce, pg_privacy_link_signature(7, $nonce, $end, $key)));

	pg_assert_true(pg_privacy_link_valid($link, array('kind' => 'information', 'status' => 'answered', 'email' => 'a@example.com'), $key, $now, true), 'the address kept');
	pg_assert_false(pg_privacy_link_valid($link, array('kind' => 'information', 'status' => 'answered', 'email' => pg_privacy_person_token('a@example.com', $key)), $key, $now, true), 'the person erased');

	pg_assert_true(function_exists('_pg_privacy_erase_apply_privacy_requests'), 'the erasure has its own step for requests');

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/privacy.php');
	$start = strpos($source, 'function _pg_privacy_erase_apply_privacy_requests(');
	$body = ($start === false) ? '' : substr($source, $start, strpos($source, "\n}\n", $start) - $start);
	pg_assert_true(strpos($body, 'pg_privacy_link_delete(') !== false, 'it deletes the files');
	pg_assert_true(strpos($body, 'pg_privacy_link_delete(') < strpos($body, '_pg_privacy_erase_write('), 'before the rows lose what finds them');

	$page = (string) file_get_contents(PG_FUNCTIONS_DIR . '/privacy_download.php');
	pg_assert_contains('SELECT id, kind, status, email FROM privacy_requests', $page, 'the page reads the address');
}
