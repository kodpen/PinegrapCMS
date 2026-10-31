<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_curl_run_options() in includes/fn/update.php: the cURL options
 * pg_curl_run() sets for a call to a fixed vendor host, by name, before
 * pg_curl_tls() is applied on top of them; and for pg_curl_run_take(), the
 * write callback of a call with a body cap or a file sink.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/update.php');

function curl_run_test_env($proxy = '', $resolve = '')
{
	return array('user_agent' => 'Pinegrap/test', 'proxy' => $proxy, 'resolve' => $resolve);
}

function test_curl_run_get_defaults()
{
	pg_assert_same(array(
		'CURLOPT_URL'            => 'https://example.com/a',
		'CURLOPT_RETURNTRANSFER' => true,
		'CURLOPT_USERAGENT'      => 'Pinegrap/test',
		'CURLOPT_TIMEOUT'        => 10,
		'CURLOPT_CONNECTTIMEOUT' => 10,
		'CURLOPT_FOLLOWLOCATION' => false,
		'CURLOPT_PROTOCOLS'      => 'CURLPROTO_HTTPS',
	), pg_curl_run_options('GET', 'https://example.com/a', array(), curl_run_test_env()));
}

function test_curl_run_post_with_body_headers_and_timeouts()
{
	$options = pg_curl_run_options('post', 'https://example.com/api', array(
		'body'            => '{"a":1}',
		'headers'         => array('Content-Type: application/json'),
		'timeout'         => 20,
		'connect_timeout' => 5,
		'forbid_reuse'    => true,
	), curl_run_test_env());

	pg_assert_same(true, $options['CURLOPT_POST']);
	pg_assert_same('{"a":1}', $options['CURLOPT_POSTFIELDS']);
	pg_assert_same(array('Content-Type: application/json'), $options['CURLOPT_HTTPHEADER']);
	pg_assert_same(20, $options['CURLOPT_TIMEOUT']);
	pg_assert_same(5, $options['CURLOPT_CONNECTTIMEOUT']);
	pg_assert_same(true, $options['CURLOPT_FORBID_REUSE']);
	pg_assert_false(isset($options['CURLOPT_CUSTOMREQUEST']), 'POST needs no custom method');
}

function test_curl_run_connect_timeout_never_above_timeout()
{
	$options = pg_curl_run_options('GET', 'https://example.com/', array('timeout' => 5), curl_run_test_env());

	pg_assert_same(5, $options['CURLOPT_CONNECTTIMEOUT']);
}

function test_curl_run_other_methods_and_head()
{
	$options = pg_curl_run_options('patch', 'https://example.com/', array('body' => 'x'), curl_run_test_env());

	pg_assert_same('PATCH', $options['CURLOPT_CUSTOMREQUEST']);
	pg_assert_same('x', $options['CURLOPT_POSTFIELDS']);

	$options = pg_curl_run_options('DELETE', 'https://example.com/', array(), curl_run_test_env());

	pg_assert_same('DELETE', $options['CURLOPT_CUSTOMREQUEST']);
	pg_assert_false(isset($options['CURLOPT_POSTFIELDS']), 'no body, no POSTFIELDS');

	$options = pg_curl_run_options('HEAD', 'https://example.com/', array('body' => 'ignored'), curl_run_test_env());

	pg_assert_same(true, $options['CURLOPT_NOBODY']);
	pg_assert_false(isset($options['CURLOPT_POSTFIELDS']), 'HEAD sends no body');

	$options = pg_curl_run_options('GET', 'https://example.com/', array('body' => 'ignored'), curl_run_test_env());

	pg_assert_false(isset($options['CURLOPT_POSTFIELDS']), 'GET sends no body');
}

function test_curl_run_redirects_only_when_asked_and_only_https()
{
	$options = pg_curl_run_options('GET', 'https://example.com/', array('follow' => 3), curl_run_test_env());

	pg_assert_same(true, $options['CURLOPT_FOLLOWLOCATION']);
	pg_assert_same(3, $options['CURLOPT_MAXREDIRS']);
	pg_assert_same('CURLPROTO_HTTPS', $options['CURLOPT_REDIR_PROTOCOLS']);
}

function test_curl_run_size_limit_and_encoding()
{
	$options = pg_curl_run_options('GET', 'https://example.com/', array(
		'max_size' => 1024,
		'encoding' => true,
	), curl_run_test_env());

	pg_assert_same(1024, $options['CURLOPT_MAXFILESIZE']);
	pg_assert_same('', $options['CURLOPT_ENCODING']);
}

function test_curl_run_proxy_when_set()
{
	$options = pg_curl_run_options('GET', 'https://example.com/', array(), curl_run_test_env('10.0.0.1:3128'));

	pg_assert_same(true, $options['CURLOPT_HTTPPROXYTUNNEL']);
	pg_assert_same('CURLPROXY_HTTP', $options['CURLOPT_PROXYTYPE']);
	pg_assert_same('10.0.0.1:3128', $options['CURLOPT_PROXY']);

	$options = pg_curl_run_options('GET', 'https://example.com/', array(), curl_run_test_env());

	pg_assert_false(isset($options['CURLOPT_PROXY']), 'no proxy option without PROXY_ADDRESS');
}

function test_curl_run_pin_only_when_resolved()
{
	$options = pg_curl_run_options('GET', 'https://example.com/', array(), curl_run_test_env('', 'example.com:443:93.184.216.34'));

	pg_assert_same(array('example.com:443:93.184.216.34'), $options['CURLOPT_RESOLVE']);

	$options = pg_curl_run_options('GET', 'https://example.com/', array(), curl_run_test_env());

	pg_assert_false(isset($options['CURLOPT_RESOLVE']), 'no pin by default');
}

function test_curl_run_never_sets_tls_options()
{
	// Verification belongs to pg_curl_tls() alone, so ALLOW_INSECURE_UPDATE_TLS
	// and CURL_CA_BUNDLE reach every call and nothing here can override them.
	$options = pg_curl_run_options('POST', 'https://example.com/', array(
		'body' => 'x', 'follow' => 2, 'max_size' => 10, 'encoding' => true, 'forbid_reuse' => true,
	), curl_run_test_env('proxy:1', 'example.com:443:1.2.3.4'));

	foreach (array_keys($options) as $name) {
		pg_assert_false(strpos($name, 'SSL') !== false || $name === 'CURLOPT_CAINFO', $name);
	}
}

function test_curl_run_pin_line()
{
	pg_assert_same('example.com:443:1.2.3.4', pg_curl_run_pin('https://example.com/x', '1.2.3.4'));
	pg_assert_same('example.com:8443:1.2.3.4', pg_curl_run_pin('https://example.com:8443/x', '1.2.3.4'));
	pg_assert_same('example.com:80:1.2.3.4', pg_curl_run_pin('http://example.com/', '1.2.3.4'));
	pg_assert_same('', pg_curl_run_pin('not a url', '1.2.3.4'));
}

function test_curl_run_basic_credentials_only_when_given()
{
	$options = pg_curl_run_options('POST', 'https://example.com/oauth/token', array(
		'body'    => 'grant_type=password',
		'userpwd' => 'id:secret',
	), curl_run_test_env());

	pg_assert_same('id:secret', $options['CURLOPT_USERPWD']);

	$options = pg_curl_run_options('GET', 'https://example.com/', array('userpwd' => ''), curl_run_test_env());

	pg_assert_false(isset($options['CURLOPT_USERPWD']), 'no credentials option when none given');
}

// A POST without a body goes out as a bare POST (no Content-Length, no
// body): CURLOPT_POST without fields makes libcurl send an empty chunked
// body with Expect: 100-continue, which the e-document hand-over to GİB
// (İşbaşı eInvoiceWithJson) was never tested with.
function test_curl_run_post_without_body_is_bare()
{
	$options = pg_curl_run_options('POST', 'https://example.com/send?id=1', array(), curl_run_test_env());

	pg_assert_same('POST', $options['CURLOPT_CUSTOMREQUEST']);
	pg_assert_false(isset($options['CURLOPT_POST']), 'no CURLOPT_POST without a body');
	pg_assert_false(isset($options['CURLOPT_POSTFIELDS']), 'no POSTFIELDS without a body');

	$options = pg_curl_run_options('POST', 'https://example.com/send', array('body' => ''), curl_run_test_env());

	pg_assert_same(true, $options['CURLOPT_POST']);
	pg_assert_same('', $options['CURLOPT_POSTFIELDS']);
}

// Both licence checks (output.php, dashboard widget 14) post the same JSON
// shape to the licence server: 10 s timeouts, a fresh connection, the
// explicit Content-Length they always sent. The status check followed
// redirects, now at most 5 and https only; widget 14 follows none.
function test_curl_run_license_request_options()
{
	$data = '{"hostname":"example.com"}';

	pg_assert_same(array(
		'body'            => $data,
		'headers'         => array('Content-Type: application/json', 'Content-Length: 26'),
		'timeout'         => 10,
		'connect_timeout' => 10,
		'forbid_reuse'    => true,
		'follow'          => 0,
	), pg_license_request_options($data, 0));

	$options = pg_curl_run_options('POST', 'https://www.kodpen.com/api2', pg_license_request_options($data, 5), curl_run_test_env());

	pg_assert_same(true, $options['CURLOPT_POST']);
	pg_assert_same($data, $options['CURLOPT_POSTFIELDS']);
	pg_assert_same(true, $options['CURLOPT_FORBID_REUSE']);
	pg_assert_same(5, $options['CURLOPT_MAXREDIRS']);
	pg_assert_same('CURLPROTO_HTTPS', $options['CURLOPT_REDIR_PROTOCOLS']);
}

// An address an operator types (migration's source site) may be plain http
// on the same host or the LAN; 'allow_http' admits http for that request
// only, never for a redirect.
function test_curl_run_allow_http_widens_the_request_only()
{
	pg_assert_same('CURLPROTO_HTTPS', pg_curl_run_options('POST', 'http://192.168.1.5/api.php', array('body' => '{}'), curl_run_test_env())['CURLOPT_PROTOCOLS']);

	$options = pg_curl_run_options('POST', 'http://192.168.1.5/api.php', array('body' => '{}', 'allow_http' => true, 'follow' => 2), curl_run_test_env());

	pg_assert_same('CURLPROTO_HTTP|CURLPROTO_HTTPS', $options['CURLOPT_PROTOCOLS']);
	pg_assert_same('CURLPROTO_HTTPS', $options['CURLOPT_REDIR_PROTOCOLS']);

	pg_assert_same('CURLPROTO_HTTPS', pg_curl_run_options('GET', 'https://example.com/', array('allow_http' => false), curl_run_test_env())['CURLOPT_PROTOCOLS']);
}

// Mailchimp's API answered a PATCH now and then with an empty reply (cURL
// error 52) until the request was forced down to HTTP/1.0; 'http_version'
// keeps that, and no other call sets a version.
function test_curl_run_http_version_only_when_asked()
{
	$options = pg_curl_run_options('PATCH', 'https://us1.api.mailchimp.com/3.0/x', array('body' => '{}', 'http_version' => '1.0'), curl_run_test_env());

	pg_assert_same('CURL_HTTP_VERSION_1_0', $options['CURLOPT_HTTP_VERSION']);

	$options = pg_curl_run_options('GET', 'https://example.com/', array('http_version' => '1.1'), curl_run_test_env());

	pg_assert_same('CURL_HTTP_VERSION_1_1', $options['CURLOPT_HTTP_VERSION']);

	$options = pg_curl_run_options('GET', 'https://example.com/', array('http_version' => '3'), curl_run_test_env());

	pg_assert_false(isset($options['CURLOPT_HTTP_VERSION']), 'an unknown version is left to libcurl');

	$options = pg_curl_run_options('GET', 'https://example.com/', array(), curl_run_test_env());

	pg_assert_false(isset($options['CURLOPT_HTTP_VERSION']), 'no version by default');
}

// 'headers' already names the request's header lines, so the response's own
// header block is asked for with 'response_headers'.
function test_curl_run_response_headers_only_when_asked()
{
	$options = pg_curl_run_options('POST', 'https://example.com/', array('body' => '{}', 'headers' => array('Accept: application/json'), 'response_headers' => true), curl_run_test_env());

	pg_assert_same(true, $options['CURLOPT_HEADER']);
	pg_assert_same(array('Accept: application/json'), $options['CURLOPT_HTTPHEADER']);

	$options = pg_curl_run_options('GET', 'https://example.com/', array('headers' => array('Accept: application/json')), curl_run_test_env());

	pg_assert_false(isset($options['CURLOPT_HEADER']), 'request headers do not ask for the response headers');
}

function test_curl_run_split_cuts_at_the_header_size()
{
	$head = "HTTP/1.1 429 Too Many Requests\r\nRetry-After: 120\r\n\r\n";

	pg_assert_same(array($head, '{"a":1}'), pg_curl_run_split($head . '{"a":1}', strlen($head)));
	pg_assert_same(array($head, ''), pg_curl_run_split($head, strlen($head)));
	pg_assert_same(array('', 'body'), pg_curl_run_split('body', 0));
}

// A cap or a sink reads the body through a write callback, which would take
// the header block with it: the block is not asked for then, and the cap
// itself is no cURL option (libcurl's own size limit trusts Content-Length).
function test_curl_run_cap_or_sink_reads_only_the_body()
{
	$sink = fopen('php://memory', 'w+b');

	foreach (array(array('cap' => 100), array('sink' => $sink), array('cap' => 100, 'sink' => $sink)) as $take) {
		$options = pg_curl_run_options('GET', 'https://example.com/a', array_merge(array('response_headers' => true), $take), curl_run_test_env());

		pg_assert_false(isset($options['CURLOPT_HEADER']), 'no header block with ' . implode(', ', array_keys($take)));
	}

	pg_assert_same(pg_curl_run_options('GET', 'https://example.com/a', array(), curl_run_test_env()), pg_curl_run_options('GET', 'https://example.com/a', array('cap' => 100), curl_run_test_env()), 'the cap adds no cURL option');

	fclose($sink);
}

function curl_run_test_taken($cap, $sink = null)
{
	return array('cap' => $cap, 'sink' => $sink, 'body' => '', 'received' => 0, 'capped' => false, 'write_failed' => false);
}

// The cap counts what arrives, whatever the server announced: the piece that
// would go past it ends the transfer (cURL error 23) and is not kept.
function test_curl_run_take_keeps_the_body_up_to_the_cap()
{
	$taken = curl_run_test_taken(10);

	pg_assert_same(4, pg_curl_run_take($taken, 'abcd'));
	pg_assert_same(4, pg_curl_run_take($taken, 'efgh'));
	pg_assert_same(0, pg_curl_run_take($taken, 'ijk'), 'past the cap');
	pg_assert_same('abcdefgh', $taken['body']);
	pg_assert_same(8, $taken['received']);
	pg_assert_true($taken['capped']);

	$taken = curl_run_test_taken(8);

	pg_assert_same(8, pg_curl_run_take($taken, 'abcdefgh'), 'up to the cap is taken');
	pg_assert_false($taken['capped']);

	$taken = curl_run_test_taken(0);

	pg_assert_same(100000, pg_curl_run_take($taken, str_repeat('x', 100000)), 'no cap');
	pg_assert_same(100000, $taken['received']);
}

function test_curl_run_take_writes_to_the_sink()
{
	$sink = fopen('php://memory', 'w+b');
	$taken = curl_run_test_taken(8, $sink);

	pg_assert_same(3, pg_curl_run_take($taken, 'abc'));
	pg_assert_same(3, pg_curl_run_take($taken, 'def'));
	pg_assert_same(0, pg_curl_run_take($taken, 'ghi'), 'past the cap');
	pg_assert_same('', $taken['body'], 'nothing kept in memory');
	pg_assert_same(6, $taken['received']);
	pg_assert_true($taken['capped']);
	pg_assert_false($taken['write_failed']);

	rewind($sink);
	pg_assert_same('abcdef', stream_get_contents($sink), 'the file holds what was taken');
	fclose($sink);
}

// A full disk is a failed write, told apart from the cap.
function test_curl_run_take_reports_a_failed_write()
{
	$sink = fopen('php://memory', 'rb');
	$taken = curl_run_test_taken(0, $sink);

	pg_assert_same(0, pg_curl_run_take($taken, 'abc'));
	pg_assert_true($taken['write_failed']);
	pg_assert_false($taken['capped']);
	pg_assert_same(0, $taken['received']);
	fclose($sink);
}

// A certificate error is told in the panel language; the data/config.php
// advice only to an administrator the caller shows it to.
function test_curl_tls_hint_text()
{
	$cause = ' The certificate could not be verified: this server is most likely missing an up-to-date CA bundle.';
	$advice = ' Point CURL_CA_BUNDLE at a cacert.pem in data/config.php, or as a last resort set ALLOW_INSECURE_UPDATE_TLS to true there.';

	pg_assert_same('', pg_curl_tls_hint_text(28, true), 'not a certificate error');
	pg_assert_same($cause, pg_curl_tls_hint_text(60, false));
	pg_assert_same($cause . $advice, pg_curl_tls_hint_text(77, true));
	pg_assert_same($cause, pg_curl_tls_hint(35), 'no advice unless the caller asks for it');

	if (!defined('USER_ROLE')) {
		pg_assert_same($cause, pg_curl_tls_hint(60, true), 'no advice without a signed-in administrator');
	}

	// Every text goes through lang().
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/update.php');
	pg_assert_true(strpos($source, "' The certificate could not be verified. This server") === false, 'the old English sentence is gone');
	pg_assert_contains("lang('The certificate could not be verified: this server is most likely missing an up-to-date CA bundle.')", $source);
}
