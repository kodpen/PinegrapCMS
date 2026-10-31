<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Kodpen API client base (includes/fn/vendor_api.php): the
 * switch line the hosting platform reads and the refusal while it is off,
 * the base address and its test override, the headers, timeouts and caps of
 * a call, the application/problem+json reader against the licence vectors,
 * and the canonical site name against the vendor's site_host.json vectors
 * (tests/fixtures/vendor_api/, kept byte for byte).
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

// The switch stays off until the release that turns it on, and its line keeps
// the one form the hosting platform reads the file for: the on form appears
// nowhere in the file, comments included.
function test_vendor_api_switch_is_off_on_its_own_line()
{
	pg_assert_same(false, PG_VENDOR_API_V1, 'the switch is off');

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/vendor_api.php');

	pg_assert_same(1, preg_match_all("/^[ \\t]*define\\('PG_VENDOR_API_V1', (true|false)\\);[ \\t]*\\r?$/m", $source, $lines), 'one switch line');
	pg_assert_same('false', isset($lines[1][0]) ? $lines[1][0] : null, 'in its off form');
	pg_assert_same(0, preg_match("/^[ \\t]*define\\('PG_VENDOR_API_V1', true\\);[ \\t]*\\r?$/m", $source), 'the platform reads it as off');
	pg_assert_same(false, strpos($source, "'PG_VENDOR_API_V1', true"), 'the on form nowhere in the file');
	pg_assert_same(1, substr_count($source, "define('PG_VENDOR_API_V1'"), 'defined once');
}

function test_vendor_api_base_and_its_test_override()
{
	pg_assert_same('https://api.kodpen.com/v1', pg_vendor_api_base_from(null), 'not set: the production server');

	foreach (array('https://localhost:8443/v1', 'https://api.kodpen.test/v1', 'https://dev.example.com/kodpen/v1', 'https://kodpen_api/v1') as $override) {
		pg_assert_same($override, pg_vendor_api_base_from($override), 'taken: ' . $override);
	}

	// Anything else calls nothing, the production server neither.
	foreach (array('', 'http://localhost/v1', 'https://localhost/v2', 'https://localhost/v1/', 'https://localhost', 'api.kodpen.com/v1',
		'https:///v1', 'https://user@host/v1', 'https://user:pass@host/v1', 'https://host/v1?x=1', 'https://host/v1#a',
		'https://ho st/v1', "https://host/v1\n", "https://host\r\n/v1", 'HTTPS://host/v1', 'https://host:/v1', true, 1) as $override) {
		pg_assert_same('', pg_vendor_api_base_from($override), 'refused: ' . var_export($override, true));
	}

	if (!defined('PG_VENDOR_API_URL')) {
		pg_assert_same('https://api.kodpen.com/v1', pg_vendor_api_base());
		pg_assert_same('https://api.kodpen.com/v1/licenses/verify', pg_vendor_api_url('/licenses/verify'));
	}
}

function vendor_api_test_vectors($name)
{
	return json_decode((string) file_get_contents(dirname(__FILE__) . '/fixtures/vendor_api/' . $name . '.json'), true);
}

// A vector case's answer in the shape pg_curl_run() gives it.
function vendor_api_test_answer($response)
{
	return array(
		'ok'           => ($response['status'] >= 200) && ($response['status'] < 300),
		'status'       => $response['status'],
		'content_type' => isset($response['content_type']) ? $response['content_type'] : '',
		'body'         => isset($response['body']) ? json_encode($response['body']) : '',
	);
}

// The vendor's vectors are kept byte for byte; a change comes with the
// vendor's new copy and new sums here.
function test_vendor_api_vectors_are_the_vendors_bytes()
{
	$sums = array(
		'licence_mapping' => '82bf0e49f188e66c8b3090d033f467990704dace0b81974789486dad574ef816',
		'site_host'       => 'c6f8e9cae7c716a8ca95be05d4bf7998d198c0bc813dddaae5cc3956e2f67873',
		'update_steps'    => '4116b7068b9630f8e149368fba15988b654fd02dd42626487bcf776d1cc945e5',
	);

	foreach ($sums as $name => $sum) {
		pg_assert_same($sum, hash_file('sha256', dirname(__FILE__) . '/fixtures/vendor_api/' . $name . '.json'), $name);
	}
}

// Every case of the server's own rule: the canonical name, or null for a
// refused one. A name with a byte outside ASCII cannot be told without intl.
function test_vendor_api_canonical_site_follows_the_vectors()
{
	$vectors = vendor_api_test_vectors('site_host');
	$intl = function_exists('idn_to_ascii') && defined('INTL_IDNA_VARIANT_UTS46');

	pg_assert_true(count($vectors['cases']) >= 30, 'the vectors are read');

	foreach ($vectors['cases'] as $case) {
		$expected = (!$intl && preg_match('/[\x80-\xFF]/', $case['input'])) ? false : $case['canonical'];

		pg_assert_same($expected, pg_vendor_api_canonical_site($case['input']), $case['id']);
	}
}

function test_vendor_api_canonical_site_without_intl()
{
	foreach (vendor_api_test_vectors('site_host')['cases'] as $case) {
		$expected = preg_match('/[\x80-\xFF]/', $case['input']) ? false : $case['canonical'];

		pg_assert_same($expected, pg_vendor_api_canonical_site($case['input'], false), $case['id']);
	}

	pg_assert_same(null, pg_vendor_api_canonical_site(null), 'not a name');
	pg_assert_same('www', pg_vendor_api_canonical_site('www.'), 'the trailing dot goes before www. is looked for');
	pg_assert_same(null, pg_vendor_api_canonical_site('www..'), 'nothing left after www.');
	pg_assert_same('www', pg_vendor_api_canonical_site('WWW'), 'www alone is a name');
	pg_assert_same(null, pg_vendor_api_canonical_site(str_repeat('a.', 126) . 'ab'), 'over 253 characters');
	pg_assert_same(str_repeat('a.', 125) . 'abc', pg_vendor_api_canonical_site(str_repeat('a.', 125) . 'abc'), '253 characters');
}

// The key field warns for every local vector and for no other name the
// server accepts.
function test_vendor_api_local_site_names()
{
	foreach (vendor_api_test_vectors('site_host')['cases'] as $case) {
		if ($case['canonical'] !== null) {
			pg_assert_same(in_array('local', $case['covers'], true), pg_vendor_api_site_local($case['canonical']), $case['id']);
		}
	}

	foreach (array('127.0.0.1', '172.16.0.1', '172.31.255.255', '10.255.255.255', 'a.b.test', 'shop.local') as $name) {
		pg_assert_true(pg_vendor_api_site_local($name), 'local: ' . $name);
	}

	foreach (array('172.32.0.1', '172.15.0.1', '192.169.0.1', '11.0.0.1', '128.0.0.1', 'test', 'local', 'shop.testing',
		'mytest', 'localhost.example.com', 'example.local.com', '10.0.0.256', '') as $name) {
		pg_assert_false(pg_vendor_api_site_local($name), 'not local: ' . $name);
	}
}

// HOSTNAME_SETTING goes out as it is (the server canonicalises it), unless
// it cannot be a header value.
function test_vendor_api_site_header_value()
{
	foreach (array('example.com', 'WWW.Example.com:8080', 'bücher.example', str_repeat('a', 253)) as $name) {
		pg_assert_same($name, pg_vendor_api_site_value($name), 'as it is: ' . $name);
	}

	foreach (array('', str_repeat('a', 254), "example.com\r\nX-Evil: 1", "example.com\n", "exa\tmple.com", "a\x00b", "a\x7Fb", null, true) as $name) {
		pg_assert_same('', pg_vendor_api_site_value($name), 'left out: ' . var_export($name, true));
	}
}

function test_vendor_api_env_header_value()
{
	pg_assert_same(
		'edition=Community Edition; php=8.3.6; db=MariaDB 10.11.6; server=nginx/1.24.0; channel=stable',
		pg_vendor_api_env_value(array('edition' => 'Community Edition', 'php' => '8.3.6', 'db' => 'MariaDB 10.11.6', 'server' => 'nginx/1.24.0', 'channel' => 'stable')));

	pg_assert_same('edition=; php=; db=; server=; channel=', pg_vendor_api_env_value(array()), 'every name, even without a value');

	// No value can end the header or start a pair of its own.
	$env = pg_vendor_api_env_value(array('edition' => "Pro;\r\nX-Evil: 1", 'php' => "8.3\n", 'db' => 'MySQL 8.0.36; os=Linux', 'server' => "Apache\x00", 'channel' => 'beta'));

	pg_assert_same('edition=ProX-Evil: 1; php=8.3; db=MySQL 8.0.36 os=Linux; server=Apache; channel=beta', $env);

	$long = pg_vendor_api_env_value(array('edition' => str_repeat('e', 600), 'php' => str_repeat('p', 600), 'db' => str_repeat('d', 600), 'server' => str_repeat('s', 600), 'channel' => str_repeat('c', 600)));

	pg_assert_true(strlen($long) < 512, 'under 512 bytes: ' . strlen($long));
	pg_assert_contains('edition=' . str_repeat('e', 64) . ';', $long, 'a value is cut to 64 bytes');

	pg_assert_same('Apache/2.4.58', pg_vendor_api_server_software('Apache/2.4.58 (Ubuntu) OpenSSL/3.0.13'), 'up to the first space');
	pg_assert_same('Apache', pg_vendor_api_server_software('Apache(Unix)'), 'or the first (');
	pg_assert_same('Microsoft-IIS/10.0', pg_vendor_api_server_software('Microsoft-IIS/10.0'));
	pg_assert_same('LiteSpeed', pg_vendor_api_server_software('LiteSpeed'));
	pg_assert_same('', pg_vendor_api_server_software(null), 'unset');
}

function test_vendor_api_version_only_as_a_release_number()
{
	foreach (array('2026.11.0', '2026.4.10', '2026.12.123') as $version) {
		pg_assert_same($version, pg_vendor_api_version_value($version), $version);
	}

	foreach (array('', '2026.11', '2026.11.0-beta.202611011200', "2026.11.0\n", '12026.11.0', '2026.100.0', '2026.11.1234', 'SETUP', true, null, 2026) as $version) {
		pg_assert_same('', pg_vendor_api_version_value($version), 'left out: ' . var_export($version, true));
	}
}

// A JSON call: the licence vectors' transport (Accept, no redirect, connect
// 4 s, 8 s in all) plus the site's headers, under a 64 KB cap.
function test_vendor_api_json_call_options()
{
	$options = pg_vendor_api_options('example.com', 'edition=CE; php=8.3.6; db=MySQL 8.0.36; server=nginx; channel=stable', array(
		'body'    => '{"version":"2026.11.0","channel":"stable"}',
		'headers' => array('Authorization: Bearer ABCDEFGHJKMNPQRS'),
	));

	pg_assert_same(array(
		'Accept: application/json, application/problem+json',
		'Content-Type: application/json',
		'X-Pinegrap-Site: example.com',
		'X-Pinegrap-Env: edition=CE; php=8.3.6; db=MySQL 8.0.36; server=nginx; channel=stable',
		'Authorization: Bearer ABCDEFGHJKMNPQRS',
	), $options['headers']);
	pg_assert_same(8, $options['timeout']);
	pg_assert_same(4, $options['connect_timeout']);
	pg_assert_same(0, $options['follow']);
	pg_assert_same(65536, $options['cap']);
	pg_assert_same('{"version":"2026.11.0","channel":"stable"}', $options['body']);

	$request = vendor_api_test_vectors('licence_mapping')['request'];
	$names = array();

	foreach ($options['headers'] as $line) {
		$names[] = strtolower(substr($line, 0, strpos($line, ':')));
	}

	foreach (array_keys($request['headers']) as $name) {
		pg_assert_contains(strtolower($name), $names, 'the vector\'s header ' . $name);
	}

	pg_assert_contains('Accept: ' . $request['headers']['Accept'], $options['headers'], 'the vector\'s Accept');
	pg_assert_same($request['connect_timeout_s'], $options['connect_timeout'], 'the vector\'s connect timeout');
	pg_assert_same($request['total_timeout_s'], $options['timeout'], 'the vector\'s total timeout');
	pg_assert_same($request['follow_redirects'], $options['follow'] > 0, 'the vector\'s redirects');

	$curl = pg_curl_run_options('POST', 'https://api.kodpen.com/v1/licenses/verify', $options, array('user_agent' => 'Pinegrap/test', 'proxy' => '', 'resolve' => ''));

	pg_assert_same(false, $curl['CURLOPT_FOLLOWLOCATION'], 'no redirect');
	pg_assert_same(4, $curl['CURLOPT_CONNECTTIMEOUT']);
	pg_assert_same(8, $curl['CURLOPT_TIMEOUT']);
	pg_assert_same('CURLPROTO_HTTPS', $curl['CURLOPT_PROTOCOLS']);
}

function test_vendor_api_file_call_options()
{
	$sink = fopen('php://memory', 'w+b');
	$options = pg_vendor_api_options('', '', array('json' => false, 'timeout' => 120, 'cap' => 31000000, 'sink' => $sink));

	pg_assert_same(array(), $options['headers'], 'no Accept line, no site headers without values');
	pg_assert_same(120, $options['timeout']);
	pg_assert_same(4, $options['connect_timeout']);
	pg_assert_same(31000000, $options['cap']);
	pg_assert_same($sink, $options['sink']);
	pg_assert_false(isset($options['body']), 'no body');

	fclose($sink);
}

// Only the status and the code of a problem object count: never its title,
// detail or Retry-After, and never an HTML page or an empty body.
function test_vendor_api_problem_reader_on_the_licence_vectors()
{
	$problems = array(
		'invalid_license_401'     => 'invalid_license',
		'missing_credentials_401' => 'missing_credentials',
		'seats_full_403'          => 'seats_full',
		'access_blocked_403'      => 'access_blocked',
		'not_live_503'            => 'not_live',
		'rate_limited_429'        => 'rate_limited',
		'server_error_500'        => 'server_error',
		'unavailable_503'         => 'unavailable',
		'validation_failed_422'   => 'validation_failed',
		'not_found_404'           => 'not_found',
	);

	$read = 0;

	foreach (vendor_api_test_vectors('licence_mapping')['cases'] as $case) {
		if ($case['response']['kind'] !== 'http') {
			continue;
		}

		$expected = isset($problems[$case['id']]) ? array('status' => $case['response']['status'], 'code' => $problems[$case['id']]) : null;

		pg_assert_same($expected, pg_vendor_api_problem(vendor_api_test_answer($case['response'])), $case['id']);
		$read++;
	}

	pg_assert_true($read >= 30, 'the vectors are read');
}

function test_vendor_api_problem_reader_edges()
{
	$body = '{"type":"https://api.kodpen.com/problems/seats_full","title":"Site rights full","status":403,"code":"seats_full","detail":"Every site right of this license is in use."}';

	pg_assert_same(array('status' => 403, 'code' => 'seats_full'), pg_vendor_api_problem(array('status' => 403, 'content_type' => 'application/problem+json; charset=utf-8', 'body' => $body)), 'with a charset');
	pg_assert_same(array('status' => 403, 'code' => 'seats_full'), pg_vendor_api_problem(array('status' => 403, 'content_type' => ' Application/Problem+JSON ', 'body' => $body)), 'any case');

	pg_assert_same(null, pg_vendor_api_problem(array('status' => 403, 'content_type' => 'application/json', 'body' => $body)), 'plain JSON is not a problem');
	pg_assert_same(null, pg_vendor_api_problem(array('status' => 200, 'content_type' => 'application/problem+json', 'body' => $body)), 'not an error status');
	pg_assert_same(null, pg_vendor_api_problem(array('status' => 0, 'content_type' => '', 'body' => '')), 'no answer');
	pg_assert_same(null, pg_vendor_api_problem(array('status' => 403, 'content_type' => 'application/problem+json', 'body' => '[{"code":"seats_full"}]')), 'not an object');

	foreach (array('"Seats Full"', '""', '5', 'null', '"seats-full"', '"' . str_repeat('a', 65) . '"') as $code) {
		pg_assert_same(null, pg_vendor_api_problem(array('status' => 403, 'content_type' => 'application/problem+json', 'body' => '{"code":' . $code . '}')), 'not a code: ' . $code);
	}

	pg_assert_same(null, pg_vendor_api_problem(array()), 'nothing');
	pg_assert_same('application/problem+json', pg_vendor_api_media_type('application/problem+json; charset=utf-8'));
	pg_assert_same('', pg_vendor_api_media_type(null));
}

// While the switch is off nothing is called: the refusal comes before cURL,
// so it has no cURL error number (the address would fail at once anyway:
// plain http is not a protocol pg_curl_run() speaks).
function test_vendor_api_switch_off_calls_nothing()
{
	$answer = pg_vendor_api_request('POST', 'http://192.0.2.1/v1/licenses/verify', array('body' => '{}', 'site_required' => true));

	pg_assert_false($answer['ok']);
	pg_assert_same(0, $answer['status']);
	pg_assert_same(0, $answer['errno'], 'refused before cURL');
	pg_assert_contains('switched off', $answer['error']);
	pg_assert_same('', $answer['content_type']);
}
