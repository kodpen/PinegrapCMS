<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Trendyol marketplace connector
 * (includes/api/outbound/connectors/trendyol.php): the request it builds, how
 * it reads Trendyol's answers and refusals, the connection test, the category
 * tree, and a category's attributes with their paged values.
 *
 * Trendyol is never called. The connector's functions take a transport with
 * the signature of api_http_request(); the one here answers from the saved
 * responses in tests/fixtures/trendyol/, which follow the response shapes in
 * Trendyol's public V2 documentation (developers.trendyol.com, October 2026)
 * and have not been recorded from a live account.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The connector files sit behind init.php's gate and only declare functions
// behind it, so the gate is answered here.
if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/connectors/base.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/connectors/trendyol.php');

function trendyol_test_account($credentials = array())
{
	return array(
		'id'       => 7,
		'provider' => 'trendyol',
		'credentials' => array_merge(array(
			'seller_id'   => '123456',
			'api_key'     => 'key-abc',
			'api_secret'  => 'secret:xyz',
			'environment' => 'production',
		), $credentials),
	);
}

function trendyol_test_fixture($name)
{
	return (string) file_get_contents(dirname(__FILE__) . '/fixtures/trendyol/' . $name);
}

// A transport that answers each URL from $routes (url => array(status, body))
// and writes every call it was given into $calls. A URL with no route is
// answered 404, so a test that sends the wrong address fails visibly.
function trendyol_test_transport($routes, &$calls)
{
	return function ($method, $url, $options) use ($routes, &$calls) {

		$calls[] = array('method' => $method, 'url' => $url, 'options' => $options);

		if (isset($routes[$url])) {
			return array('ok' => ($routes[$url][0] >= 200 && $routes[$url][0] < 300), 'status' => $routes[$url][0],
				'body' => $routes[$url][1], 'error' => '', 'duration_ms' => 1);
		}

		return array('ok' => false, 'status' => 404, 'body' => '{"errors":[{"message":"no route in test"}]}',
			'error' => '', 'duration_ms' => 1);
	};
}

function trendyol_test_values_url($category_id, $attribute_id, $page)
{
	return 'https://apigw.trendyol.com/integration/product/categories/' . $category_id
		. '/attributes/' . $attribute_id . '/values?page=' . $page . '&size=1000';
}

function trendyol_test_attribute_routes()
{
	return array(
		'https://apigw.trendyol.com/integration/product/categories/14609/attributes'
			=> array(200, trendyol_test_fixture('category-attributes-14609.json')),
		trendyol_test_values_url(14609, 293, 0) => array(200, trendyol_test_fixture('attribute-values-14609-293-page0.json')),
		trendyol_test_values_url(14609, 293, 1) => array(200, trendyol_test_fixture('attribute-values-14609-293-page1.json')),
		trendyol_test_values_url(14609, 47, 0)  => array(200, trendyol_test_fixture('attribute-values-14609-47.json')),
		trendyol_test_values_url(14609, 180, 0) => array(200, trendyol_test_fixture('attribute-values-empty.json')),
	);
}

/* ------------------------------------------------------------- registry */

function test_marketplace_trendyol_is_a_provider_with_its_fields()
{
	$providers = mp_providers();

	pg_assert_true(isset($providers['trendyol']), 'trendyol is listed');
	pg_assert_same('Trendyol', mp_provider_name('trendyol'));
	pg_assert_same(array('seller_id', 'api_key', 'api_secret', 'environment'), array_keys($providers['trendyol']['fields']));
	pg_assert_true(!empty($providers['trendyol']['fields']['api_secret']['secret']), 'the secret is a password field');
	pg_assert_true(mp_provider_field_is_select($providers['trendyol']['fields']['environment']));
	pg_assert_false(mp_provider_field_is_select($providers['trendyol']['fields']['seller_id']));
}

function test_marketplace_trendyol_connector_offers_only_what_it_does()
{
	pg_assert_true(mp_can('trendyol', 'test_credentials'));
	pg_assert_true(mp_can('trendyol', 'category_tree'));
	pg_assert_true(mp_can('trendyol', 'category_attributes'));

	// Nothing queues work it cannot do yet.
	pg_assert_false(mp_can('trendyol', 'push_stock_price'));
	pg_assert_false(mp_can('trendyol', 'create_listings'));
	pg_assert_false(mp_can('trendyol', 'pull_orders'));
}

function test_marketplace_provider_credentials_whitelist_the_select()
{
	$values = mp_provider_credentials('trendyol', array(
		'seller_id'   => ' 123456 ',
		'api_key'     => 'k',
		'api_secret'  => 's',
		'environment' => 'stage',
		'unrelated'   => 'dropped',
	));

	pg_assert_same(array('seller_id' => '123456', 'api_key' => 'k', 'api_secret' => 's', 'environment' => 'stage'), $values);

	$values = mp_provider_credentials('trendyol', array('environment' => 'https://evil.example'));

	pg_assert_same('production', $values['environment'], 'an unknown option falls back to the default');
	pg_assert_same('', $values['api_key'], 'a missing text field is empty');

	$values = mp_provider_credentials('trendyol', array('api_key' => array('x')));

	pg_assert_same('', $values['api_key'], 'a posted list is not a value');

	pg_assert_same(array(), mp_provider_credentials('nope', array('api_key' => 'k')));
	pg_assert_same(array('app_key' => 'a', 'app_secret' => 'b'), mp_provider_credentials('n11', array('app_key' => 'a', 'app_secret' => 'b')));
}

/* -------------------------------------------------------------- request */

function test_marketplace_trendyol_headers_carry_basic_auth_and_the_seller_agent()
{
	$headers = mp_trendyol_headers(trendyol_test_account());

	pg_assert_contains('Authorization: Basic ' . base64_encode('key-abc:secret:xyz'), $headers);
	pg_assert_contains('User-Agent: 123456 - SelfIntegration', $headers);
	pg_assert_contains('Accept: application/json', $headers);
}

function test_marketplace_trendyol_environment_picks_the_host()
{
	pg_assert_same('https://apigw.trendyol.com', mp_trendyol_base(trendyol_test_account()));
	pg_assert_same('https://stageapigw.trendyol.com', mp_trendyol_base(trendyol_test_account(array('environment' => 'stage'))));

	// Anything but 'stage', and a row stored before the field existed, is live.
	pg_assert_same('production', mp_trendyol_environment(trendyol_test_account(array('environment' => 'STAGE'))));
	pg_assert_same('production', mp_trendyol_environment(array('credentials' => array())));
}

function test_marketplace_trendyol_seller_id_is_digits_only()
{
	pg_assert_same('123456', mp_trendyol_seller_id(trendyol_test_account(array('seller_id' => ' 123456 '))));
	pg_assert_same('', mp_trendyol_seller_id(trendyol_test_account(array('seller_id' => '12/../34'))));
	pg_assert_same('', mp_trendyol_seller_id(trendyol_test_account(array('seller_id' => ''))));
}

function test_marketplace_trendyol_bad_credentials_are_refused_before_any_call()
{
	$calls = array();
	$transport = trendyol_test_transport(array(), $calls);

	$result = mp_trendyol_test_credentials(trendyol_test_account(array('seller_id' => 'abc')), $transport);

	pg_assert_false($result['ok']);
	pg_assert_contains('seller ID is a number', $result['error']);

	$result = mp_trendyol_category_tree(trendyol_test_account(array('api_secret' => '')), $transport);

	pg_assert_false($result['ok']);
	pg_assert_contains('API key and secret are missing', $result['error']);

	pg_assert_same(0, count($calls), 'nothing was sent');
}

/* ------------------------------------------------------------ the answer */

function test_marketplace_trendyol_read_names_the_cause_of_each_refusal()
{
	$auth = mp_trendyol_read(array('status' => 401, 'body' => trendyol_test_fixture('error-401.json'), 'error' => ''));

	pg_assert_false($auth['ok']);
	pg_assert_false($auth['retry']);
	pg_assert_contains('did not accept the API key and secret', $auth['error']);

	$agent = mp_trendyol_read(array('status' => 403, 'body' => '', 'error' => ''));

	pg_assert_contains('Check the seller ID', $agent['error']);
	pg_assert_false($agent['retry']);

	$limit = mp_trendyol_read(array('status' => 429, 'body' => 'too.many.requests', 'error' => ''));

	pg_assert_contains('rate limiting', $limit['error']);
	pg_assert_true($limit['retry']);

	$stage = mp_trendyol_read(array('status' => 503, 'body' => '<html></html>', 'error' => ''), 'stage');

	pg_assert_contains('IP address', $stage['error']);

	// The same 503 from the live environment is an outage, not a whitelist.
	$live = mp_trendyol_read(array('status' => 503, 'body' => '<html></html>', 'error' => ''), 'production');

	pg_assert_contains('not JSON', $live['error']);
	pg_assert_true($live['retry']);
}

function test_marketplace_trendyol_read_transport_failure_is_retried()
{
	$result = mp_trendyol_read(array('status' => 0, 'body' => '', 'error' => 'Could not resolve host: apigw.trendyol.com'));

	pg_assert_false($result['ok']);
	pg_assert_true($result['retry']);
	pg_assert_same('Could not resolve host: apigw.trendyol.com', $result['error']);
}

function test_marketplace_trendyol_read_takes_the_message_from_the_envelope()
{
	$result = mp_trendyol_read(array('status' => 400, 'body' => trendyol_test_fixture('error-400.json'), 'error' => ''));

	pg_assert_false($result['ok']);
	pg_assert_false($result['retry']);
	pg_assert_same('Kategori bulunamadı', $result['error']);

	pg_assert_same('category.not.found', mp_trendyol_error_message(array('errors' => array(array('key' => 'category.not.found')))));
	pg_assert_same('ClientApiBusinessException', mp_trendyol_error_message(array('exception' => 'ClientApiBusinessException', 'errors' => array())));
	pg_assert_same('Ürün bulunamadı', mp_trendyol_error_message(array('exception' => 'X', 'message' => 'Ürün bulunamadı')));
	pg_assert_same('', mp_trendyol_error_message(array('categories' => array())));

	$bare = mp_trendyol_read(array('status' => 404, 'body' => '{}', 'error' => ''));

	pg_assert_same('Trendyol answered 404.', $bare['error']);
}

function test_marketplace_trendyol_read_non_json_success_is_an_error()
{
	$result = mp_trendyol_read(array('status' => 200, 'body' => '<html>maintenance</html>', 'error' => ''));

	pg_assert_false($result['ok']);
	pg_assert_contains('not JSON', $result['error']);
}

/* ------------------------------------------------------- connection test */

function test_marketplace_trendyol_connection_test_reads_the_sellers_products()
{
	$calls = array();
	$url = 'https://apigw.trendyol.com/integration/product/sellers/123456/products/approved?page=0&size=1';
	$transport = trendyol_test_transport(array($url => array(200, trendyol_test_fixture('products-approved.json'))), $calls);

	$result = mp_trendyol_test_credentials(trendyol_test_account(), $transport);

	pg_assert_true($result['ok'], $result['error']);
	pg_assert_same(3, $result['data']['products']);
	pg_assert_same('production', $result['data']['environment']);

	pg_assert_same(1, count($calls));
	pg_assert_same('GET', $calls[0]['method']);
	pg_assert_same($url, $calls[0]['url']);
	pg_assert_contains('User-Agent: 123456 - SelfIntegration', $calls[0]['options']['headers']);
	pg_assert_same('marketplace', $calls[0]['options']['agent']);
}

function test_marketplace_trendyol_connection_test_uses_the_stage_host()
{
	$calls = array();
	$url = 'https://stageapigw.trendyol.com/integration/product/sellers/123456/products/approved?page=0&size=1';
	$transport = trendyol_test_transport(array($url => array(503, '')), $calls);

	$result = mp_trendyol_test_credentials(trendyol_test_account(array('environment' => 'stage')), $transport);

	pg_assert_false($result['ok']);
	pg_assert_same($url, $calls[0]['url']);
	pg_assert_contains('IP address', $result['error']);
}

function test_marketplace_trendyol_connection_test_rejects_an_unrelated_answer()
{
	$calls = array();
	$url = 'https://apigw.trendyol.com/integration/product/sellers/123456/products/approved?page=0&size=1';
	$transport = trendyol_test_transport(array($url => array(200, '{"categories":[]}')), $calls);

	$result = mp_trendyol_test_credentials(trendyol_test_account(), $transport);

	pg_assert_false($result['ok']);
	pg_assert_contains('product list', $result['error']);
}

/* --------------------------------------------------------- category tree */

function test_marketplace_trendyol_category_tree_is_flattened_with_paths_and_leaves()
{
	$calls = array();
	$transport = trendyol_test_transport(array(
		'https://apigw.trendyol.com/integration/product/product-categories'
			=> array(200, trendyol_test_fixture('product-categories.json')),
	), $calls);

	$result = mp_trendyol_category_tree(trendyol_test_account(), $transport);

	pg_assert_true($result['ok'], $result['error']);

	pg_assert_same(array('368', '1228', '1237', '1238', '1229', '403', '14600', '14609'),
		array_column($result['data']['categories'], 'remote_id'), 'depth first, the node without an id skipped');

	$rows = array();

	foreach ($result['data']['categories'] as $row) {
		$rows[$row['remote_id']] = $row;
	}

	pg_assert_same(array('remote_id' => '1237', 'parent_id' => '1228', 'name' => 'Ata Altın',
		'path' => 'Aksesuar > Altın > Ata Altın', 'is_leaf' => 1), $rows['1237']);

	pg_assert_same('', $rows['368']['parent_id'], 'a top category has no parent');
	pg_assert_same(0, $rows['368']['is_leaf']);
	pg_assert_same(0, $rows['1228']['is_leaf']);
	pg_assert_same(1, $rows['1229']['is_leaf'], 'no subCategories at all is a leaf');
	pg_assert_same('Spor & Outdoor > Dövüş Sporları > Muay Thai Kaskı', $rows['14609']['path']);

	// The tree is large; a capped body would be invalid JSON.
	pg_assert_true($calls[0]['options']['max_body'] >= 33554432, 'the body cap fits the whole tree');
}

function test_marketplace_trendyol_empty_category_tree_is_an_error()
{
	$calls = array();
	$transport = trendyol_test_transport(array(
		'https://apigw.trendyol.com/integration/product/product-categories' => array(200, '{"categories":[]}'),
	), $calls);

	$result = mp_trendyol_category_tree(trendyol_test_account(), $transport);

	pg_assert_false($result['ok']);
	pg_assert_contains('no categories', $result['error']);
}

/* ------------------------------------------------------------ attributes */

function test_marketplace_trendyol_category_attributes_with_paged_values()
{
	$calls = array();
	$transport = trendyol_test_transport(trendyol_test_attribute_routes(), $calls);

	$result = mp_trendyol_category_attributes(trendyol_test_account(), '14609', $transport);

	pg_assert_true($result['ok'], $result['error']);

	$rows = $result['data']['attributes'];

	// The repeated Renk entry and the entry without an attribute are dropped.
	pg_assert_same(3, count($rows));

	pg_assert_same(array('attribute_id' => '293', 'name' => 'Beden', 'mandatory' => 1, 'is_variant' => 1,
		'custom_allowed' => 0, 'sort_order' => 0,
		'values' => array(
			array('id' => '4872', 'value' => 'Tek Ebat'),
			array('id' => '4873', 'value' => 'S'),
			array('id' => '4874', 'value' => 'M'),
		)), $rows[0], 'both pages, the repeat counted once');

	pg_assert_same('47', $rows[1]['attribute_id']);
	pg_assert_same(1, $rows[1]['mandatory']);
	pg_assert_same(0, $rows[1]['is_variant']);
	pg_assert_same(array(array('id' => '686230', 'value' => 'Siyah'), array('id' => '686234', 'value' => 'Kırmızı')), $rows[1]['values']);

	pg_assert_same('180', $rows[2]['attribute_id']);
	pg_assert_same(0, $rows[2]['mandatory']);
	pg_assert_same(1, $rows[2]['custom_allowed']);
	pg_assert_same(array(), $rows[2]['values'], 'a free-text attribute with no list');

	// One list call, two pages for Beden, one each for the other two.
	pg_assert_same(5, count($calls));
	pg_assert_same(trendyol_test_values_url(14609, 293, 1), $calls[2]['url']);
}

function test_marketplace_trendyol_attribute_value_budget_ends_with_an_error()
{
	// A count that never ends: every page answers "page 0 of 1000".
	$routes = array(
		'https://apigw.trendyol.com/integration/product/categories/14609/attributes'
			=> array(200, trendyol_test_fixture('category-attributes-14609.json')),
	);

	for ($page = 0; $page < 60; $page++) {
		$routes[trendyol_test_values_url(14609, 293, $page)] = array(200,
			'{"totalPages":1000,"content":[{"attributeValueId":' . (5000 + $page) . ',"attributeValue":"v' . $page . '"}]}');
	}

	$calls = array();

	$result = mp_trendyol_category_attributes(trendyol_test_account(), '14609', trendyol_test_transport($routes, $calls));

	pg_assert_false($result['ok']);
	pg_assert_contains('more attribute values', $result['error']);
	pg_assert_same(1 + mp_trendyol_value_call_budget(), count($calls), 'stops at the budget');
	pg_assert_true(mp_trendyol_value_call_budget() < 50, 'under the per-endpoint limit with the list call');
}

function test_marketplace_trendyol_attribute_value_failure_fails_the_category()
{
	$routes = trendyol_test_attribute_routes();
	$routes[trendyol_test_values_url(14609, 47, 0)] = array(429, 'too.many.requests');

	$calls = array();

	$result = mp_trendyol_category_attributes(trendyol_test_account(), '14609', trendyol_test_transport($routes, $calls));

	pg_assert_false($result['ok'], 'no half-filled attribute list');
	pg_assert_true($result['retry']);
	pg_assert_contains('rate limiting', $result['error']);
}

function test_marketplace_trendyol_category_attributes_need_a_numeric_category()
{
	$calls = array();

	$result = mp_trendyol_category_attributes(trendyol_test_account(), '14609/../1', trendyol_test_transport(array(), $calls));

	pg_assert_false($result['ok']);
	pg_assert_same(0, count($calls));
}

function test_marketplace_trendyol_value_page_without_a_count_reads_a_short_page_as_last()
{
	$read = mp_trendyol_value_page(array('content' => array(array('attributeValueId' => '12', 'attributeValue' => ' Mavi '))));

	pg_assert_same(array('values' => array(array('id' => '12', 'value' => 'Mavi')), 'total_pages' => 0), $read);

	$calls = array();
	$budget = 10;

	$routes = array(trendyol_test_values_url(1, 2, 0) => array(200, '{"content":[{"attributeValueId":12,"attributeValue":"Mavi"}]}'));

	$result = mp_trendyol_attribute_values(trendyol_test_account(), '1', '2', $budget, trendyol_test_transport($routes, $calls));

	pg_assert_true($result['ok'], $result['error']);
	pg_assert_same(1, count($calls));
	pg_assert_same(9, $budget);
}
