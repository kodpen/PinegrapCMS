<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the HTTP manners of the external API (includes/api/manners.php):
 * HEAD routed as GET, Allow naming HEAD, the Location of a 201, the
 * Content-Language of an error, the exposed headers and the Basic challenge.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/manners.php');

// HEAD finds the GET route of the same address; every other method stays.
function test_api_manners_head_is_routed_as_get()
{
	pg_assert_same('GET', api_route_method('HEAD'), 'HEAD');
	pg_assert_same('GET', api_route_method('head'), 'lower case');
	pg_assert_same('GET', api_route_method('GET'), 'GET');
	pg_assert_same('POST', api_route_method('POST'), 'POST');
	pg_assert_same('PATCH', api_route_method('patch'), 'PATCH');
}

// A 405 names HEAD wherever it names GET, once.
function test_api_manners_allow_names_head_beside_get()
{
	pg_assert_same(array('GET', 'HEAD', 'POST', 'PATCH'), api_allow_methods(array('GET', 'POST', 'PATCH')), 'GET and writes');
	pg_assert_same(array('POST', 'PATCH'), api_allow_methods(array('POST', 'PATCH')), 'no GET, no HEAD');
	pg_assert_same(array('GET', 'HEAD'), api_allow_methods(array('GET', 'GET')), 'GET twice');
}

// The patterns of GET /products/{id} and GET /customers/{id}/notes/{note_id},
// as api_route_pattern() writes them for integer ids.
function api_manners_test_patterns()
{
	return array(
		'#^/products/([0-9]+)$#i',
		'#^/customers/([0-9]+)/notes/([0-9]+)$#i',
	);
}

// A created record with a GET route gets its address.
function test_api_manners_location_of_a_created_record()
{
	$patterns = api_manners_test_patterns();

	pg_assert_same('/products/12', api_location_path('/products', array('id' => 12, 'name' => 'Pen'), $patterns), 'integer id');
	pg_assert_same('/products/12', api_location_path('/products/', array('id' => '12'), $patterns), 'string id, trailing slash');
	pg_assert_same('/customers/5/notes/9', api_location_path('/customers/5/notes', array('id' => 9), $patterns), 'nested');
}

// No id, an id no GET route reads, or an address with no GET route: no Location.
function test_api_manners_no_location_without_a_get_route()
{
	$patterns = api_manners_test_patterns();

	pg_assert_same('', api_location_path('/webhooks/subscribe', array('subscribed' => true), $patterns), 'no id');
	pg_assert_same('', api_location_path('/auth/sessions', array('id' => 4), $patterns), 'no GET route');
	pg_assert_same('', api_location_path('/products', array('id' => 'abc'), $patterns), 'id the route does not read');
	pg_assert_same('', api_location_path('/products', array('id' => '1/2'), $patterns), 'id with a slash');
	pg_assert_same('', api_location_path('/products', array('id' => ''), $patterns), 'empty id');
	pg_assert_same('', api_location_path('/products', array('id' => null), $patterns), 'null id');
	pg_assert_same('', api_location_path('/products', 'text', $patterns), 'not a record');
}

// Only an answer with a sentence in error.message says its language.
function test_api_manners_content_language_on_an_error()
{
	$error = array('error' => array('code' => 'not_found', 'message' => 'Ürün bulunamadı.', 'request_id' => 'r1'));

	pg_assert_same('tr', api_answer_language($error, 'tr'), 'error with a message');
	pg_assert_same('', api_answer_language(array('id' => 1, 'name' => 'Kalem'), 'tr'), 'a record');
	pg_assert_same('', api_answer_language(array('error' => array('code' => 'x', 'message' => '')), 'tr'), 'empty message');
	pg_assert_same('', api_answer_language(array('error' => 'x'), 'tr'), 'error not an object');
	pg_assert_same('', api_answer_language(array(), 'en'), 'empty body');
}

// An answer open to other origins exposes the API's own headers; a list the
// answer names itself (MCP's) stays; a same-origin answer gets none.
function test_api_manners_exposed_headers()
{
	$open = api_with_exposed_headers(array('Access-Control-Allow-Origin' => '*'));

	pg_assert_true(isset($open['Access-Control-Expose-Headers']), 'added');

	foreach (array('X-Request-Id', 'Content-Language', 'Location', 'ETag', 'X-RateLimit-Remaining') as $name) {
		pg_assert_true(strpos($open['Access-Control-Expose-Headers'], $name) !== false, $name);
	}

	$own = api_with_exposed_headers(array('Access-Control-Allow-Origin' => '*', 'Access-Control-Expose-Headers' => 'WWW-Authenticate'));

	pg_assert_same('WWW-Authenticate', $own['Access-Control-Expose-Headers'], 'its own list stays');
	pg_assert_same(array('ETag' => '"a"'), api_with_exposed_headers(array('ETag' => '"a"')), 'same origin');
}

// The Basic challenge goes to every 401 but a script-made call's.
function test_api_manners_basic_challenge()
{
	pg_assert_true(api_basic_challenge_wanted(''), 'no header');
	pg_assert_true(api_basic_challenge_wanted('fetch'), 'another value');
	pg_assert_false(api_basic_challenge_wanted('XMLHttpRequest'), 'script-made');
	pg_assert_false(api_basic_challenge_wanted(' xmlhttprequest '), 'any case and spaces');
}
