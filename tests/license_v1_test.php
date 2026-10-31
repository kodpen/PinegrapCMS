<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the licence check on the Kodpen API (includes/fn/output.php):
 * pg_license_map_v1() against every case of the vendor's licence_mapping.json
 * (tests/fixtures/vendor_api/, kept byte for byte), the retry each case gets
 * from pg_license_check_due(), the request the check builds, the key's
 * normal form and alphabet, the card's type and site rights, the error
 * texts, and the site-name warning of the key field against site_host.json.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/vendor_api.php');

// The vectors, decoded with objects kept, so an answer body is re-encoded
// exactly as the server would send it ({} stays an object, [] an array).
function license_v1_test_vectors($name)
{
	return json_decode((string) file_get_contents(dirname(__DIR__) . '/tests/fixtures/vendor_api/' . $name));
}

// A vector's response as pg_curl_run() answers it.
function license_v1_test_answer($response)
{
	if ($response->kind === 'transport_error') {
		return array('ok' => false, 'status' => 0, 'body' => '', 'errno' => (int) $response->curl_errno,
			'error' => (string) $response->error, 'content_type' => '');
	}

	$body = isset($response->body_text) ? (string) $response->body_text : json_encode($response->body);
	$status = (int) $response->status;

	return array('ok' => ($status >= 200) && ($status < 300), 'status' => $status, 'body' => $body, 'errno' => 0,
		'error' => '', 'content_type' => (string) $response->content_type);
}

function test_license_v1_map_follows_every_vector()
{
	$vectors = license_v1_test_vectors('licence_mapping.json');

	pg_assert_same(41, count($vectors->cases), 'every case read');

	foreach ($vectors->cases as $case) {
		$expect = (array) $case->expect->session;

		pg_assert_same($expect, pg_license_map_v1(license_v1_test_answer($case->response)), $case->id);
	}
}

// Answered: trusted 12 hours; unusable (status ''): asked again after 15 minutes.
function test_license_v1_map_gives_each_case_its_retry()
{
	$vectors = license_v1_test_vectors('licence_mapping.json');
	$asked = 1000000;

	foreach ($vectors->cases as $case) {
		$session = pg_license_map_v1(license_v1_test_answer($case->response));
		$wait = (int) $vectors->retry->{$case->expect->retry}->next_check_after_s;

		pg_assert_false(pg_license_check_due(false, $session['status'], $asked, $asked + $wait - 1), $case->id . ': not before ' . $wait . ' s');
		pg_assert_true(pg_license_check_due(false, $session['status'], $asked, $asked + $wait + 1), $case->id . ': after ' . $wait . ' s');
	}
}

function test_license_v1_map_reads_nothing_but_an_answer()
{
	$blank = array('status' => '', 'error_code' => '', 'P_end_d' => '', 'type' => '', 'sites_used' => null, 'sites_total' => null);

	pg_assert_same($blank, pg_license_map_v1(array()), 'no answer at all');
	pg_assert_same($blank, pg_license_map_v1(array('ok' => false, 'status' => 0, 'body' => '', 'errno' => 0,
		'error' => 'The Kodpen API client is switched off.', 'content_type' => '')), 'a call refused before cURL');

	// A schema-valid body cut short by the transfer is no answer.
	$body = '{"license":{"status":"active","valid_until":"2027-01-01","type":"premium","sites":{"used":1,"total":1}}}';
	pg_assert_same($blank, pg_license_map_v1(array('status' => 200, 'body' => $body, 'errno' => 23, 'content_type' => 'application/json')), 'a transfer error');
	pg_assert_same('success', pg_license_map_v1(array('status' => 200, 'body' => $body, 'errno' => 0, 'content_type' => 'application/json; charset=utf-8'))['status'], 'a charset parameter');
	pg_assert_same('', pg_license_map_v1(array('status' => 200, 'body' => str_replace('2027-01-01', '2027-02-30', $body), 'errno' => 0, 'content_type' => 'application/json'))['status'], 'a day that does not exist');
	pg_assert_same('', pg_license_map_v1(array('status' => 200, 'body' => str_replace('"used":1', '"used":-1', $body), 'errno' => 0, 'content_type' => 'application/json'))['status'], 'a negative count');
}

// The built call equals the vectors' request block.
function test_license_v1_request_matches_the_vectors()
{
	$request = license_v1_test_vectors('licence_mapping.json')->request;
	$options = pg_license_v1_options('ABCD2345EFGH6789', '2026.11.0', 'stable');
	$built = pg_vendor_api_options('shop.example.com', '', $options);

	pg_assert_same('POST', $request->method);
	pg_assert_same($request->url, 'https://api.kodpen.com/v1/licenses/verify');
	pg_assert_same(true, in_array('Authorization: Bearer ABCD2345EFGH6789', $built['headers'], true), 'the key as the bearer');
	pg_assert_same(true, in_array('X-Pinegrap-Site: shop.example.com', $built['headers'], true), 'the site name');
	pg_assert_same(true, in_array('Content-Type: ' . $request->headers->{'Content-Type'}, $built['headers'], true), 'a JSON body');
	pg_assert_same(true, in_array('Accept: ' . $request->headers->Accept, $built['headers'], true), 'JSON or a problem object');
	pg_assert_same((int) $request->connect_timeout_s, $built['connect_timeout'], 'connect timeout');
	pg_assert_same((int) $request->total_timeout_s, $built['timeout'], 'total timeout');
	pg_assert_same(0, $built['follow'], 'no redirect');
	pg_assert_same(array('version' => '2026.11.0', 'channel' => 'stable'), json_decode($built['body'], true), 'the body');
	pg_assert_true(!empty($options['site_required']), 'never sent without the site name');

	pg_assert_same(array('channel' => 'beta'), json_decode(pg_license_v1_options('ABCD2345EFGH6789', '', 'beta')['body'], true), 'no release number: no version');
}

function test_license_v1_key_normal_form_and_alphabet()
{
	pg_assert_same('ABCD2345EFGH6789', pg_license_key_normalize('abcd-2345-efgh-6789'), 'dashes out, upper case');
	pg_assert_same('ABCD2345EFGH6789', pg_license_key_normalize(" abcd 2345\tEFGH-6789 "), 'blanks out');
	pg_assert_same('', pg_license_key_normalize(null), 'nothing');

	foreach (array('ABCD2345EFGH6789', '1234567890123456', 'ZZZZYYYYXXXXWWWW', '0000000000000000') as $key) {
		pg_assert_true(pg_license_key_valid($key), 'valid: ' . $key);
	}

	foreach (array('', 'ABCD2345EFGH678', 'ABCD2345EFGH67890', 'ABCD2345EFGH678I', 'ABCD2345EFGH678L', 'ABCD2345EFGH678O',
		'ABCD2345EFGH678U', 'abcd2345efgh6789', 'ABCD-2345-EFGH-67', "ABCD2345EFGH6789\n", null, 1234567890123456) as $key) {
		pg_assert_false(pg_license_key_valid($key), 'refused: ' . var_export($key, true));
	}
}

function test_license_v1_card_shows_type_and_site_rights()
{
	pg_assert_contains(lang('Premium'), pg_license_card_details('premium', 1, 1), 'premium');
	pg_assert_contains(lang('Enterprise'), pg_license_card_details('enterprise', 3, 5), 'enterprise');
	pg_assert_contains(lang('Demo'), pg_license_card_details('demo', 1, 1), 'demo');
	pg_assert_contains('3 / 5', pg_license_card_details('enterprise', 3, 5), 'the site rights as sent');
	pg_assert_contains('0 / 1', pg_license_card_details('premium', 0, 1), 'none held');
	pg_assert_contains('partner', pg_license_card_details('partner', 2, 10), 'an unknown type by its code');
	pg_assert_contains('&lt;b&gt;', pg_license_card_details('<b>', 1, 1), 'escaped');
	pg_assert_same(false, strpos(pg_license_card_details('', 1, 1), 'pg-lic-type'), 'no type: no type line');
	pg_assert_same(false, strpos(pg_license_card_details('premium', null, null), ' / '), 'no counts: no sites line');
	pg_assert_same('', pg_license_card_details('', null, null), 'nothing at all');
}

function test_license_v1_error_texts()
{
	pg_assert_same(lang('Every site right of this license is in use; to use it on a new site, first have one of its sites released.'), pg_license_error_notice('seats_full'));
	pg_assert_same(lang('Service access is closed for this site.'), pg_license_error_notice('access_blocked'));
	pg_assert_same(lang('Your license is suspended. You cannot benefit from some features.'), pg_license_error_notice('suspended'));
	pg_assert_same(lang('Your license is not valid. You cannot benefit from some features.'), pg_license_error_notice('no_match'));
	pg_assert_same('', pg_license_error_notice(''), 'a technical failure has its own text');
}

// Every local site_host vector warns about the site right, every other
// non-null one does not; a refused name warns that checks will fail.
function test_license_v1_site_warning_follows_site_host()
{
	foreach (license_v1_test_vectors('site_host.json')->cases as $case) {
		$warning = pg_license_site_warning($case->input, true);

		if ($case->canonical === null) {
			pg_assert_same('none', $warning, $case->id);
		} elseif (pg_vendor_api_site_local($case->canonical)) {
			pg_assert_same('local', $warning, $case->id);
		} else {
			pg_assert_same('', $warning, $case->id);
		}
	}

	pg_assert_same('local', pg_license_site_warning('localhost:8080', true), 'a local name with a port');
	pg_assert_same('local', pg_license_site_warning('172.20.0.3', true), '172.16/12');
	pg_assert_same('', pg_license_site_warning('172.32.0.3', true), 'outside 172.16/12');
	pg_assert_same('', pg_license_site_warning("b\xC3\xBCcher.example", false), 'non-ASCII without intl: no text');
}
