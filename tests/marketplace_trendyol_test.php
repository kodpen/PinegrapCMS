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
require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/listings.php');

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
	pg_assert_true(mp_can('trendyol', 'create_listings'));
	pg_assert_true(mp_can('trendyol', 'read_task'));
	pg_assert_true(mp_can('trendyol', 'push_stock_price'));

	// Nothing queues work it cannot do yet.
	pg_assert_false(mp_can('trendyol', 'pull_orders'));
}

function test_marketplace_trendyol_is_keyed_on_barcodes_and_n11_is_not()
{
	pg_assert_true(mp_provider_keys_by_barcode('trendyol'));
	pg_assert_false(mp_provider_keys_by_barcode('n11'));
	pg_assert_false(mp_provider_keys_by_barcode('nope'));

	$providers = mp_providers();

	pg_assert_true(!empty($providers['n11']['shipment_template']), 'n11 listings name a cargo template');
	pg_assert_true(empty($providers['trendyol']['shipment_template']), 'Trendyol listings do not');
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
	// Renk is their "slicer": it differs between the products of one article,
	// so it is answered per product like the "varianter".
	pg_assert_same(1, $rows[1]['is_variant']);
	pg_assert_same(array(array('id' => '686230', 'value' => 'Siyah'), array('id' => '686234', 'value' => 'Kırmızı')), $rows[1]['values']);

	pg_assert_same('180', $rows[2]['attribute_id']);
	pg_assert_same(0, $rows[2]['mandatory']);
	pg_assert_same(1, $rows[2]['custom_allowed']);
	pg_assert_same(array(), $rows[2]['values'], 'a free-text attribute with no list');

	// One list call, two pages for Beden, one each for the other two.
	pg_assert_same(5, count($calls));
	pg_assert_same(trendyol_test_values_url(14609, 293, 1), $calls[2]['url']);
}

// The fixture category's definitions, values included, as the cache keeps them.
function trendyol_test_category_definitions()
{
	$calls = array();

	$result = mp_trendyol_category_attributes(trendyol_test_account(), '14609',
		trendyol_test_transport(trendyol_test_attribute_routes(), $calls));

	return $result['ok'] ? $result['data']['attributes'] : array();
}

// An article whose products are its colours: each product sends its own
// colour, not the one value chosen for the article.
function test_marketplace_trendyol_slicer_colour_is_read_from_each_product()
{
	$definitions = trendyol_test_category_definitions();

	pg_assert_same(3, count($definitions));

	// Answers given on the mapping screen: a material, and a colour for the
	// article as a whole.
	$chosen = array('180' => 'Pamuk', '47' => '686234');

	$black = mp_listing_attributes_of($definitions, $chosen, array(
		array('name' => 'Beden', 'value' => 'M'),
		array('name' => 'Renk', 'value' => 'Siyah'),
	));

	$red = mp_listing_attributes_of($definitions, $chosen, array(
		array('name' => 'Beden', 'value' => 'M'),
		array('name' => 'Koltuk Rengi', 'value' => 'Kırmızı'),
	));

	pg_assert_same(array(
		array('id' => '293', 'value_id' => '4874', 'custom_value' => ''),
		array('id' => '47', 'value_id' => '686230', 'custom_value' => ''),
		array('id' => '180', 'value_id' => '', 'custom_value' => 'Pamuk'),
	), $black, 'the own colour wins over the one chosen for the article');

	pg_assert_same(array('id' => '47', 'value_id' => '686234', 'custom_value' => ''), $red[1],
		'an option under another name is matched on its value');
}

// A product with no colour option of its own takes the colour chosen for the
// article, so a single-colour article lists as before; with neither, the
// mandatory colour refuses the product.
function test_marketplace_trendyol_slicer_falls_back_to_the_articles_value()
{
	$definitions = trendyol_test_category_definitions();

	$own = array(array('name' => 'Beden', 'value' => 'S'));

	$attributes = mp_listing_attributes_of($definitions, array('47' => '686230'), $own);

	pg_assert_same(array(
		array('id' => '293', 'value_id' => '4873', 'custom_value' => ''),
		array('id' => '47', 'value_id' => '686230', 'custom_value' => ''),
	), $attributes);

	pg_assert_same(null, mp_listing_attributes_of($definitions, array(), $own));
}

// Items as mp_listing_items() builds them, from each product's own options.
function trendyol_test_variant_items($definitions, $chosen, $products)
{
	$items = array();

	foreach ($products as $code => $own) {
		$items[] = array('stock_code' => $code, 'attributes' => mp_listing_attributes_of($definitions, $chosen, $own));
	}

	return $items;
}

// Sizes under a name the category does not use, with labels not on its list:
// both fall back to the article's Beden M and would go out as the same row.
function test_marketplace_trendyol_unmatched_sizes_clash_on_the_articles_value()
{
	$definitions = trendyol_test_category_definitions();

	$items = trendyol_test_variant_items($definitions, array('293' => 'M', '47' => '686230', '180' => 'Pamuk'), array(
		'CHAIR-M' => array(array('name' => 'Size', 'value' => 'Medium'), array('name' => 'Renk', 'value' => 'Siyah')),
		'CHAIR-L' => array(array('name' => 'Size', 'value' => 'Large'), array('name' => 'Renk', 'value' => 'Siyah')),
	));

	pg_assert_same(array(0, 1), mp_listing_variant_clash($definitions, $items));
}

// Colours under another name, with labels not on the list: both take the Renk
// stored for the article, and the shared Beden M leaves nothing apart.
function test_marketplace_trendyol_unmatched_colours_clash_on_the_stored_colour()
{
	$definitions = trendyol_test_category_definitions();

	$items = trendyol_test_variant_items($definitions, array('47' => '686230', '180' => 'Pamuk'), array(
		'CHAIR-MOCHA' => array(array('name' => 'Beden', 'value' => 'M'), array('name' => 'Colour', 'value' => 'Mocha')),
		'CHAIR-GREEN' => array(array('name' => 'Beden', 'value' => 'M'), array('name' => 'Colour', 'value' => 'Green')),
	));

	pg_assert_same(array(0, 1), mp_listing_variant_clash($definitions, $items));
}

// A single-size article split by colour: the article's Tek Ebat is shared, the
// colours differ, so it lists.
function test_marketplace_trendyol_single_size_colour_split_article_does_not_clash()
{
	$definitions = trendyol_test_category_definitions();

	$items = trendyol_test_variant_items($definitions, array('293' => '4872'), array(
		'CHAIR-BLACK' => array(array('name' => 'Renk', 'value' => 'Siyah')),
		'CHAIR-RED'   => array(array('name' => 'Renk', 'value' => 'Kırmızı')),
	));

	pg_assert_same('4872', $items[1]['attributes'][0]['value_id']);
	pg_assert_same(null, mp_listing_variant_clash($definitions, $items));
}

// One product has nothing to be confused with; free text is compared without
// case, and items without variant values are not compared at all.
function test_marketplace_trendyol_variant_clash_edges()
{
	$definitions = trendyol_test_category_definitions();

	$one = trendyol_test_variant_items($definitions, array('293' => 'M', '47' => '686230'), array(
		'CHAIR' => array(array('name' => 'Renk', 'value' => 'Siyah')),
	));

	pg_assert_same(null, mp_listing_variant_clash($definitions, $one));

	$custom = array(
		array('stock_code' => 'A', 'attributes' => array(array('id' => '293', 'value_id' => '', 'custom_value' => 'XL '))),
		array('stock_code' => 'B', 'attributes' => array(array('id' => '180', 'value_id' => '', 'custom_value' => 'Pamuk'))),
		array('stock_code' => 'C', 'attributes' => array(array('id' => '293', 'value_id' => '', 'custom_value' => 'xl'))),
	);

	pg_assert_same(array(0, 2), mp_listing_variant_clash($definitions, $custom));

	$plain = array(
		array('stock_code' => 'A', 'attributes' => array(array('id' => '180', 'value_id' => '', 'custom_value' => 'Pamuk'))),
		array('stock_code' => 'B', 'attributes' => array(array('id' => '180', 'value_id' => '', 'custom_value' => 'Pamuk'))),
	);

	pg_assert_same(null, mp_listing_variant_clash($definitions, $plain));
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

/* -------------------------------------------------------------- listings */

// One product of an article, the shape mp_listing_items() assembles for a
// barcode-keyed provider.
function trendyol_test_item($overrides = array())
{
	return array_merge(array(
		'product_id'  => 11,
		'code'        => '8680000000017',
		'stock_code'  => 'PG-KASK-S',
		'barcode'     => '8680000000017',
		'brand'       => 'Örnek',
		'group_code'  => 'PG-KASK',
		'title'       => 'Muay Thai Kaskı',
		'description' => '<p>Darbe emici köpük.</p>',
		'category_id' => '14609',
		'price_major' => 79.95,
		'quantity'    => 5,
		'tax_rate'    => 20.0,
		'images'      => array('https://shop.example/kask-1.jpg', 'https://shop.example/kask-2.jpg'),
		'attributes'  => array(
			array('id' => '293', 'value_id' => '4873', 'custom_value' => ''),
			array('id' => '180', 'value_id' => '', 'custom_value' => 'Deri'),
			array('id' => '47', 'value_id' => '', 'custom_value' => ''),
		),
	), $overrides);
}

function trendyol_test_listing_account()
{
	$account = trendyol_test_account();
	$account['preparing_day'] = 2;

	return $account;
}

function trendyol_test_listing_routes()
{
	return array(
		'https://apigw.trendyol.com/integration/product/brands/by-name?name=' . rawurlencode('Örnek')
			=> array(200, trendyol_test_fixture('brands-by-name.json')),
		'https://apigw.trendyol.com/integration/product/sellers/123456/v2/products'
			=> array(200, trendyol_test_fixture('v2-products-created.json')),
	);
}

function test_marketplace_trendyol_listing_body_has_their_field_names()
{
	$body = mp_trendyol_listing_body(trendyol_test_listing_account(), array(trendyol_test_item()), array('Örnek' => 1791));

	pg_assert_true($body['ok'], $body['error']);

	pg_assert_same(array(
		'barcode'        => '8680000000017',
		'title'          => 'Muay Thai Kaskı',
		'productMainId'  => 'PG-KASK',
		'brandId'        => 1791,
		'categoryId'     => 14609,
		'quantity'       => 5,
		'stockCode'      => 'PG-KASK-S',
		'description'    => '<p>Darbe emici köpük.</p>',
		'listPrice'      => 79.95,
		'salePrice'      => 79.95,
		'vatRate'        => 20,
		'images'         => array(array('url' => 'https://shop.example/kask-1.jpg'), array('url' => 'https://shop.example/kask-2.jpg')),
		'attributes'     => array(
			array('attributeId' => 293, 'attributeValueId' => 4873),
			array('attributeId' => 180, 'customAttributeValue' => 'Deri'),
		),
		'deliveryOption' => array('deliveryDuration' => 2),
	), $body['data']['items'][0], 'an attribute with neither a value id nor text is left out');
}

function test_marketplace_trendyol_listing_body_trims_title_and_pictures()
{
	$images = array();

	for ($i = 1; $i <= 10; $i++) {
		$images[] = 'https://shop.example/p' . $i . '.jpg';
	}

	$account = trendyol_test_account();

	$body = mp_trendyol_listing_body($account, array(trendyol_test_item(array(
		'title'  => str_repeat('Ş', 120),
		'images' => $images,
	))), array('Örnek' => 1791));

	pg_assert_true($body['ok'], $body['error']);
	pg_assert_same(100, mb_strlen($body['data']['items'][0]['title']));
	pg_assert_same(8, count($body['data']['items'][0]['images']), 'their ceiling is eight a barcode');
	pg_assert_false(isset($body['data']['items'][0]['deliveryOption']), 'no days to prepare, their default applies');
}

function test_marketplace_trendyol_listing_body_refuses_what_they_would()
{
	$account = trendyol_test_listing_account();
	$brands = array('Örnek' => 1791);

	$cases = array(
		'no brand'          => array(array('brand' => ''), 'has no brand'),
		'unknown brand'     => array(array('brand' => 'Başka'), 'no brand called Başka'),
		'http picture'      => array(array('images' => array('http://shop.example/a.jpg')), 'over https'),
		'no picture'        => array(array('images' => array()), 'has no picture'),
		'no description'    => array(array('description' => '  '), 'has no description'),
		'long description'  => array(array('description' => str_repeat('a', 30001)), '30,000'),
		'no title'          => array(array('title' => ''), 'has no title'),
		'no price'          => array(array('price_major' => 0), 'has no price'),
		'long model code'   => array(array('group_code' => str_repeat('M', 41)), 'model code'),
		'long stock code'   => array(array('stock_code' => str_repeat('S', 101)), 'stock code'),
		'barcode with space' => array(array('code' => '868 0000'), 'barcode 868 0000'),
		'long barcode'      => array(array('code' => str_repeat('1', 41)), 'at most 40'),
		'no barcode'        => array(array('code' => ''), 'needs a barcode'),
	);

	foreach ($cases as $label => $case) {
		$body = mp_trendyol_listing_body($account, array(trendyol_test_item($case[0])), $brands);

		pg_assert_false($body['ok'], $label);
		pg_assert_contains($case[1], $body['error'], $label);
		pg_assert_false($body['retry'], $label . ' is not retried');
	}

	// Turkish letters, dots, dashes and underscores are theirs to accept.
	pg_assert_same('', mp_trendyol_barcode_problem('ÇİĞ-ö.ü_1'));
}

function test_marketplace_trendyol_vat_rate_rounds_down_to_theirs()
{
	pg_assert_same(20, mp_trendyol_vat_rate(array('tax_rate' => 20.0)));
	pg_assert_same(10, mp_trendyol_vat_rate(array('tax_rate' => 18.0)));
	pg_assert_same(1, mp_trendyol_vat_rate(array('tax_rate' => 8.5)));
	pg_assert_same(0, mp_trendyol_vat_rate(array('tax_rate' => 0.5)));
	pg_assert_same(20, mp_trendyol_vat_rate(array('tax_rate' => 25.0)));
}

// The rate inside the price that is sent wins over the product's own: 0 for
// a product the shop does not tax (tests/marketplace_vat_rate_test.php has
// every tax state). Without it the product's own rate is declared.
function test_marketplace_trendyol_vat_rate_follows_the_applied_rate()
{
	pg_assert_same(0, mp_trendyol_vat_rate(array('taxable' => false, 'tax_rate' => 20.0, 'vat_rate' => 0.0)));
	pg_assert_same(10, mp_trendyol_vat_rate(array('taxable' => true, 'tax_rate' => null, 'vat_rate' => 18.0)));
	pg_assert_same(20, mp_trendyol_vat_rate(array('taxable' => true, 'tax_rate' => 20.0, 'vat_rate' => null)));
	pg_assert_same(20, mp_trendyol_vat_rate(array('tax_rate' => 20.0)));
}

function test_marketplace_trendyol_brand_match_prefers_the_exact_name()
{
	$answer = json_decode(trendyol_test_fixture('brands-by-name.json'), true);

	pg_assert_same(1791, mp_trendyol_brand_match($answer, 'Örnek'));
	pg_assert_same(1792, mp_trendyol_brand_match($answer, 'örnek spor'), 'one case-only match is taken');
	pg_assert_same(0, mp_trendyol_brand_match($answer, 'Örne'), 'no partial names');
	pg_assert_same(0, mp_trendyol_brand_match(array(array('id' => 1, 'name' => 'ABC'), array('id' => 2, 'name' => 'abc')), 'Abc'), 'two case-only matches are a guess');
	pg_assert_same(5, mp_trendyol_brand_match(array('brands' => array(array('id' => 5, 'name' => 'X'))), 'X'));
	pg_assert_same(0, mp_trendyol_brand_match(array(), 'X'));
}

function test_marketplace_trendyol_create_listings_looks_up_the_brand_and_posts()
{
	$calls = array();

	$items = array(
		trendyol_test_item(),
		trendyol_test_item(array('product_id' => 12, 'code' => '8680000000024', 'stock_code' => 'PG-KASK-M',
			'attributes' => array(array('id' => '293', 'value_id' => '4874', 'custom_value' => '')))),
	);

	$result = mp_trendyol_create_listings(trendyol_test_listing_account(), $items,
		trendyol_test_transport(trendyol_test_listing_routes(), $calls));

	pg_assert_true($result['ok'], $result['error']);
	pg_assert_same('fa75dfd5-6ce6-4730-a09e-97563500000-1529391724', $result['data']['task_id']);
	pg_assert_same(2, $result['data']['sent']);

	pg_assert_same(2, count($calls), 'one brand lookup for two products of one brand, then one create');
	pg_assert_same('GET', $calls[0]['method']);
	pg_assert_same('POST', $calls[1]['method']);
	pg_assert_contains('Content-Type: application/json', $calls[1]['options']['headers']);
	pg_assert_contains('User-Agent: 123456 - SelfIntegration', $calls[1]['options']['headers']);

	$sent = json_decode($calls[1]['options']['body'], true);

	pg_assert_same(array('items'), array_keys($sent));
	pg_assert_same(array('8680000000017', '8680000000024'), array_column($sent['items'], 'barcode'));
	pg_assert_same(array('PG-KASK', 'PG-KASK'), array_column($sent['items'], 'productMainId'), 'one model code groups the variants');
	pg_assert_same(1791, $sent['items'][1]['brandId']);
	pg_assert_contains('Muay Thai Kaskı', $calls[1]['options']['body'], 'Turkish text is sent unescaped');
}

function test_marketplace_trendyol_create_listings_checks_before_calling()
{
	$calls = array();

	$result = mp_trendyol_create_listings(trendyol_test_listing_account(),
		array(trendyol_test_item(array('images' => array('http://shop.example/a.jpg')))),
		trendyol_test_transport(trendyol_test_listing_routes(), $calls));

	pg_assert_false($result['ok']);
	pg_assert_same(0, count($calls), 'a picture on http is refused without a call');
}

function test_marketplace_trendyol_create_listings_unknown_brand_is_refused_before_creating()
{
	$routes = trendyol_test_listing_routes();
	$routes['https://apigw.trendyol.com/integration/product/brands/by-name?name=' . rawurlencode('Örnek')]
		= array(200, trendyol_test_fixture('brands-by-name-empty.json'));

	$calls = array();

	$result = mp_trendyol_create_listings(trendyol_test_listing_account(), array(trendyol_test_item()),
		trendyol_test_transport($routes, $calls));

	pg_assert_false($result['ok']);
	pg_assert_contains('no brand called Örnek', $result['error']);
	pg_assert_same(1, count($calls), 'only the lookup was made');
}

function test_marketplace_trendyol_create_listings_without_a_batch_id_is_retried()
{
	$routes = trendyol_test_listing_routes();
	$routes['https://apigw.trendyol.com/integration/product/sellers/123456/v2/products'] = array(200, '{}');

	$calls = array();

	$result = mp_trendyol_create_listings(trendyol_test_listing_account(), array(trendyol_test_item()),
		trendyol_test_transport($routes, $calls));

	pg_assert_false($result['ok']);
	pg_assert_true($result['retry']);
	pg_assert_contains('batch id', $result['error']);
}

function test_marketplace_trendyol_create_listings_needs_lira()
{
	// BASE_CURRENCY_CODE is not defined under the test runner, which reads as
	// the lira; the refusal itself is the body check's.
	pg_assert_true(!defined('BASE_CURRENCY_CODE') || strtoupper(BASE_CURRENCY_CODE) === 'TRY');

	$body = mp_trendyol_listing_body(trendyol_test_account(), array(trendyol_test_item()), null);

	pg_assert_true($body['ok'], 'the first pass checks everything but the brand id');
	pg_assert_same(0, $body['data']['items'][0]['brandId']);
}

/* --------------------------------------------------------------- batches */

function test_marketplace_trendyol_batch_result_per_barcode()
{
	$calls = array();
	$url = 'https://apigw.trendyol.com/integration/product/sellers/123456/products/batch-requests/fa75dfd5-6ce6-4730-a09e-97563500000-1529391724';

	$result = mp_trendyol_read_task(trendyol_test_account(), 'fa75dfd5-6ce6-4730-a09e-97563500000-1529391724',
		trendyol_test_transport(array($url => array(200, trendyol_test_fixture('batch-create-completed.json'))), $calls));

	pg_assert_true($result['ok'], $result['error']);
	pg_assert_true($result['data']['finished']);
	pg_assert_same('COMPLETED', $result['data']['status']);

	pg_assert_same(array(
		'8680000000017' => array('ok' => true, 'status' => 'SUCCESS', 'error' => ''),
		'8680000000024' => array('ok' => false, 'status' => 'FAILED', 'error' => 'Görsel indirilemedi; Kategori özelliği eksik: Renk'),
		'8680000000031' => array('ok' => false, 'status' => 'FAILED', 'error' => 'Trendyol refused this product without saying why.'),
	), $result['data']['results'], 'the barcode is read from requestItem or from its product');
}

function test_marketplace_trendyol_batch_in_progress_is_not_finished()
{
	$read = mp_trendyol_batch_results(json_decode(trendyol_test_fixture('batch-create-in-progress.json'), true));

	pg_assert_false($read['finished']);
	pg_assert_same('IN_PROGRESS', $read['status']);
	pg_assert_same(array(), $read['results']);
}

function test_marketplace_trendyol_batch_without_status_waits_for_every_line()
{
	$decoded = json_decode(trendyol_test_fixture('batch-inventory.json'), true);

	$read = mp_trendyol_batch_results($decoded);

	pg_assert_false($read['finished'], 'one line has no verdict yet');

	$decoded['items'][1]['status'] = 'FAILED';
	$decoded['items'][1]['failureReasons'] = array('Stok negatif olamaz');

	$read = mp_trendyol_batch_results($decoded);

	pg_assert_true($read['finished']);
	pg_assert_same('Stok negatif olamaz', $read['results']['8680000000024']['error']);
	pg_assert_true($read['results']['8680000000017']['ok']);

	pg_assert_false(mp_trendyol_batch_results(array('items' => array()))['finished'], 'an empty answer is not a finished batch');
}

function test_marketplace_trendyol_read_task_refuses_an_odd_task_id()
{
	$calls = array();

	$result = mp_trendyol_read_task(trendyol_test_account(), '../products', trendyol_test_transport(array(), $calls));

	pg_assert_false($result['ok']);
	pg_assert_same(0, count($calls));
}

/* ------------------------------------------------------- stock and price */

// One product as mp_product_row() hands it to the connector.
function trendyol_test_stock_row($overrides = array())
{
	return array_merge(array(
		'product_id'  => 11,
		'remote_code' => '8680000000017',
		'remote_id'   => '',
		'name'        => 'PG-KASK-S',
		'title'       => 'Muay Thai Kaskı',
		'price'       => 7995,
		'price_major' => 79.95,
		'quantity'    => 4,
		'tracked'     => true,
		'tax_rate'    => 20.0,
	), $overrides);
}

function trendyol_test_stock_account($flags = array())
{
	return array_merge(trendyol_test_account(), array('push_stock' => 1, 'push_price' => 1), $flags);
}

function trendyol_test_stock_url($host = 'https://apigw.trendyol.com')
{
	return $host . '/integration/inventory/sellers/123456/products/price-and-inventory';
}

function test_marketplace_trendyol_stock_body_sends_quantity_and_both_prices()
{
	$built = mp_trendyol_stock_body(array(
		trendyol_test_stock_row(),
		trendyol_test_stock_row(array('product_id' => 12, 'remote_code' => '8680000000024', 'quantity' => 25000, 'price_major' => 1249.899)),
		trendyol_test_stock_row(array('product_id' => 13, 'remote_code' => '8680000000031', 'quantity' => -3)),
	), true, true);

	pg_assert_same(array(), $built['refused']);
	pg_assert_same(array(
		array('barcode' => '8680000000017', 'quantity' => 4, 'salePrice' => 79.95, 'listPrice' => 79.95),
		array('barcode' => '8680000000024', 'quantity' => 20000, 'salePrice' => 1249.9, 'listPrice' => 1249.9),
		array('barcode' => '8680000000031', 'quantity' => 0, 'salePrice' => 79.95, 'listPrice' => 79.95),
	), $built['items'], 'above their ceiling the ceiling is sent; below zero is zero');
}

function test_marketplace_trendyol_stock_body_follows_the_account_flags()
{
	$rows = array(
		trendyol_test_stock_row(),
		trendyol_test_stock_row(array('product_id' => 12, 'remote_code' => '8680000000024', 'tracked' => false)),
	);

	$stock_only = mp_trendyol_stock_body($rows, true, false);

	pg_assert_same(array(array('barcode' => '8680000000017', 'quantity' => 4)), $stock_only['items'],
		'an untracked product has nothing to say when prices are not sent');
	pg_assert_same(array(), $stock_only['refused']);

	$price_only = mp_trendyol_stock_body($rows, false, true);

	pg_assert_same(array(
		array('barcode' => '8680000000017', 'salePrice' => 79.95, 'listPrice' => 79.95),
		array('barcode' => '8680000000024', 'salePrice' => 79.95, 'listPrice' => 79.95),
	), $price_only['items'], 'no quantity at all, so their stock stands');

	$with_both = mp_trendyol_stock_body($rows, true, true);

	pg_assert_false(isset($with_both['items'][1]['quantity']), 'zero is never sent for an untracked product');
}

function test_marketplace_trendyol_stock_body_leaves_out_what_they_would_refuse()
{
	$built = mp_trendyol_stock_body(array(
		trendyol_test_stock_row(array('product_id' => 21, 'remote_code' => '', 'name' => 'NOCODE')),
		trendyol_test_stock_row(array('product_id' => 22, 'remote_code' => 'has space', 'name' => 'SPACE')),
		trendyol_test_stock_row(array('product_id' => 23, 'price_major' => 0, 'name' => 'FREE')),
		trendyol_test_stock_row(array('product_id' => 24, 'remote_code' => '8680000000048')),
		trendyol_test_stock_row(array('product_id' => 25, 'remote_code' => '8680000000048', 'name' => 'TWIN')),
	), true, true);

	pg_assert_same(array(21, 22, 23, 25), array_keys($built['refused']));
	pg_assert_contains('NOCODE: Trendyol needs a barcode', $built['refused'][21]);
	pg_assert_contains('SPACE: the barcode has space cannot be used', $built['refused'][22]);
	pg_assert_contains('FREE has no price', $built['refused'][23]);
	pg_assert_contains('TWIN: another product in the same batch', $built['refused'][25]);

	pg_assert_same(array(array('barcode' => '8680000000048', 'quantity' => 4, 'salePrice' => 79.95, 'listPrice' => 79.95)),
		$built['items'], 'the first product under a barcode goes, and the one refused for no price does not hold it');
}

function test_marketplace_trendyol_push_posts_the_batch_and_answers_the_task()
{
	$calls = array();
	$transport = trendyol_test_transport(array(
		trendyol_test_stock_url() => array(200, trendyol_test_fixture('price-and-inventory-accepted.json')),
	), $calls);

	$result = mp_trendyol_push_stock_price(trendyol_test_stock_account(), array(
		trendyol_test_stock_row(),
		trendyol_test_stock_row(array('product_id' => 21, 'remote_code' => '')),
	), $transport);

	pg_assert_true($result['ok'], $result['error']);
	pg_assert_same('fa75dfd5-6ce6-4730-a09e-97563500000-1529854840', $result['data']['task_id']);
	pg_assert_same(1, $result['data']['sent']);
	pg_assert_same(array(21), array_keys($result['data']['refused']));

	pg_assert_same(1, count($calls));
	pg_assert_same('POST', $calls[0]['method']);
	pg_assert_contains('Content-Type: application/json', $calls[0]['options']['headers']);
	pg_assert_same('{"items":[{"barcode":"8680000000017","quantity":4,"salePrice":79.95,"listPrice":79.95}]}',
		$calls[0]['options']['body']);
}

function test_marketplace_trendyol_push_uses_the_stage_host()
{
	$calls = array();
	$transport = trendyol_test_transport(array(
		trendyol_test_stock_url('https://stageapigw.trendyol.com') => array(200, trendyol_test_fixture('price-and-inventory-accepted.json')),
	), $calls);

	$result = mp_trendyol_push_stock_price(
		trendyol_test_stock_account(array('credentials' => array_merge(trendyol_test_account()['credentials'], array('environment' => 'stage')))),
		array(trendyol_test_stock_row()), $transport);

	pg_assert_true($result['ok'], $result['error']);
}

function test_marketplace_trendyol_push_with_nothing_left_makes_no_call()
{
	$calls = array();
	$transport = trendyol_test_transport(array(), $calls);

	// Stock only: the untracked product has nothing to say, the other is refused.
	$result = mp_trendyol_push_stock_price(trendyol_test_stock_account(array('push_price' => 0)), array(
		trendyol_test_stock_row(array('product_id' => 21, 'remote_code' => '')),
		trendyol_test_stock_row(array('product_id' => 22, 'tracked' => false)),
	), $transport);

	pg_assert_true($result['ok'], $result['error']);
	pg_assert_same('', $result['data']['task_id'], 'no task: nothing was left to send');
	pg_assert_same(0, $result['data']['sent']);
	pg_assert_same(array(21), array_keys($result['data']['refused']));

	// Every product refused.
	$result = mp_trendyol_push_stock_price(trendyol_test_stock_account(), array(
		trendyol_test_stock_row(array('price_major' => 0)),
	), $transport);

	pg_assert_true($result['ok'], $result['error']);
	pg_assert_same('', $result['data']['task_id']);
	pg_assert_same(array(11), array_keys($result['data']['refused']));

	$result = mp_trendyol_push_stock_price(trendyol_test_stock_account(array('push_price' => 0, 'push_stock' => 0)),
		array(trendyol_test_stock_row()), $transport);

	pg_assert_false($result['ok']);
	pg_assert_contains('neither stock nor price', $result['error']);

	pg_assert_false(mp_trendyol_push_stock_price(trendyol_test_stock_account(), array(), $transport)['ok']);

	pg_assert_same(0, count($calls));
}

function test_marketplace_trendyol_push_repeat_refusal_waits_out_the_window()
{
	$calls = array();
	$transport = trendyol_test_transport(array(
		trendyol_test_stock_url() => array(400, trendyol_test_fixture('error-400-repeat.json')),
	), $calls);

	$result = mp_trendyol_push_stock_price(trendyol_test_stock_account(), array(trendyol_test_stock_row()), $transport);

	pg_assert_false($result['ok']);
	pg_assert_true($result['retry'], 'asked again, not failed for good');
	pg_assert_same(900, $result['data']['retry_after']);
	pg_assert_contains('last 15 minutes', $result['error']);

	pg_assert_true(mp_trendyol_is_repeat_refusal('15 dakika boyunca aynı isteği tekrarlı olarak atamazsınız!'));
	pg_assert_true(mp_trendyol_is_repeat_refusal('You cannot send the same request for 15 minutes'));
	pg_assert_false(mp_trendyol_is_repeat_refusal('Kategori bulunamadı'));
}

function test_marketplace_trendyol_push_other_refusals_pass_through()
{
	$calls = array();
	$transport = trendyol_test_transport(array(
		trendyol_test_stock_url() => array(400, trendyol_test_fixture('error-400.json')),
	), $calls);

	$result = mp_trendyol_push_stock_price(trendyol_test_stock_account(), array(trendyol_test_stock_row()), $transport);

	pg_assert_false($result['ok']);
	pg_assert_false($result['retry']);
	pg_assert_same('Kategori bulunamadı', $result['error']);

	$transport = trendyol_test_transport(array(trendyol_test_stock_url() => array(200, '{}')), $calls);

	$result = mp_trendyol_push_stock_price(trendyol_test_stock_account(), array(trendyol_test_stock_row()), $transport);

	pg_assert_false($result['ok']);
	pg_assert_true($result['retry'], 'accepted without a batch id is asked again');
}

function test_marketplace_trendyol_push_caps_the_batch_at_their_ceiling()
{
	$rows = array();

	for ($i = 0; $i < 1005; $i++) {
		$rows[] = trendyol_test_stock_row(array('product_id' => 1000 + $i, 'remote_code' => 'PG' . $i));
	}

	$calls = array();
	$transport = trendyol_test_transport(array(
		trendyol_test_stock_url() => array(200, trendyol_test_fixture('price-and-inventory-accepted.json')),
	), $calls);

	$result = mp_trendyol_push_stock_price(trendyol_test_stock_account(), $rows, $transport);

	pg_assert_true($result['ok'], $result['error']);
	pg_assert_same(1000, $result['data']['sent']);
	pg_assert_same(1000, count(json_decode($calls[0]['options']['body'], true)['items']));
}

function test_marketplace_trendyol_inventory_batch_listing_fewer_lines_than_its_count_is_not_finished()
{
	$read = mp_trendyol_batch_results(json_decode(trendyol_test_fixture('batch-inventory-partial.json'), true));

	pg_assert_false($read['finished'], 'two of three lines listed');
	pg_assert_same(array(), $read['results']);

	$decoded = json_decode(trendyol_test_fixture('batch-inventory-partial.json'), true);
	$decoded['itemCount'] = 2;

	pg_assert_true(mp_trendyol_batch_results($decoded)['finished']);
}
