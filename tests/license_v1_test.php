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
	pg_assert_same(lang('Premium'), pg_license_type_name('premium'), 'premium');
	pg_assert_same(lang('Enterprise'), pg_license_type_name('enterprise'), 'enterprise');
	pg_assert_same(lang('Demo'), pg_license_type_name('demo'), 'demo');
	pg_assert_same('partner', pg_license_type_name('partner'), 'an unknown type by its code');
	pg_assert_same('&lt;b&gt;', pg_license_type_name('<b>'), 'escaped');
	pg_assert_same('', pg_license_type_name(''), 'no type');
	pg_assert_same('', pg_license_type_name(null), 'not a string');

	pg_assert_contains('3 / 5', pg_license_card_details(3, 5), 'the site rights as sent');
	pg_assert_contains('0 / 1', pg_license_card_details(0, 1), 'none held');
	pg_assert_same('', pg_license_card_details(null, null), 'no counts: nothing');
	pg_assert_same('', pg_license_card_details(1, null), 'one count missing: nothing');
}

// The card's row names the type it holds, so a Demo or Enterprise licence
// never reads "Premium"; without a type (the old server, an unusable answer)
// it keeps the paid licence's name (#4928).
function test_license_v1_card_title_names_the_type()
{
	pg_assert_same('Pinegrap ' . lang('Demo') . ' · ' . lang('Active'), pg_license_card_title('demo', lang('Active')), 'demo');
	pg_assert_same('Pinegrap ' . lang('Enterprise') . ' · ' . lang('Active'), pg_license_card_title('enterprise', lang('Active')), 'enterprise');
	pg_assert_same('Pinegrap ' . lang('Premium') . ' · ' . lang('Suspended'), pg_license_card_title('premium', lang('Suspended')), 'premium');
	pg_assert_same(false, strpos(pg_license_card_title('demo', 'x'), 'Premium'), 'a demo licence is not called Premium');
	pg_assert_same(false, strpos(pg_license_card_title('enterprise', 'x'), 'Premium'), 'an enterprise licence is not called Premium');
	pg_assert_same('Pinegrap Premium™ · x', pg_license_card_title('', 'x'), 'no type: the name as before');
	pg_assert_same('Pinegrap &lt;i&gt; · &lt;s&gt;', pg_license_card_title('<i>', '<s>'), 'escaped');
}

// The expiry date is written in the panel's language and the site's date
// order, like the panel's other dates: "Sal 1 Ara 2026", not "01 Dec 2026"
// (#4929). Run in a PHP process of its own with the panel in Turkish.
function test_license_v1_expiry_date_follows_the_panel_language()
{
	if (!function_exists('exec') || !defined('PHP_BINARY') || (PHP_BINARY === '')) {
		pg_assert_true(true, 'PHP cannot be started again');
		return;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pglicexp');

	if ($file === false) {
		pg_assert_true(true, 'no temporary file');
		return;
	}

	$script = <<<'PHP'
define('SOFTWARE_LANGUAGE', 'tr');
define('DATE_FORMAT', 'day_month');
define('TIME_FORMAT', 'twenty_four_hours');
date_default_timezone_set('Europe/Istanbul');
require $pg_dir . '/functions.php';

echo json_encode(array('text' => pg_license_expiry_text(new DateTime('2026-12-01'))));
PHP;

	file_put_contents($file, '<?php' . "\n" . '$pg_dir = ' . var_export(PG_FUNCTIONS_DIR, true) . ";\n" . $script . "\n");

	$lines = array();
	$code = 1;
	@exec(escapeshellarg(PHP_BINARY) . ' -d display_errors=stderr -d error_reporting=-1 ' . escapeshellarg($file) . ' 2>&1', $lines, $code);
	@unlink($file);

	$result = json_decode(implode("\n", $lines), true);

	pg_assert_same(0, $code, implode("\n", $lines));
	pg_assert_same('Sal 1 Ara 2026', is_array($result) ? ($result['text'] ?? null) : null, 'Turkish, day first');
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
