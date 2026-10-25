<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the one product group writer (pg_catalog_group_create() and
 * pg_catalog_group_clouds(), includes/fn/ecommerce.php). Run here: what the
 * writer settles before it writes (the parent, the text the address is made
 * from, the rich text with {path}, the products), the INSERT it builds from
 * the data each caller really hands it and the product links, held to the
 * statements POST /product-groups and a Workspace create built before they
 * shared it, the statements it runs in
 * their order, and the API's own query helpers taking the API's statements.
 * Read from the source, since they need a database: both callers are thin
 * wrappers around it, every create is scored for SEO and announced once as
 * product_group.created, a Workspace product create is scored too, and
 * add_product_group.php and the variant set builder keep their own code.
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

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/changes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/product_groups.php');

// e() asks the connection class; without a database it falls back to
// addslashes() once the class exists.
function catalog_group_writer_test_db_stub()
{
	if (!class_exists('db')) {
		class db
		{
			public static $con;
		}
	}
}

// Stand-ins for the external API's query helpers (includes/api/db.php, which
// only an API request loads and no test loads): each records the statement it
// was handed and answers as the real one would.
if (!function_exists('api_query')) {
	function api_query($sql)
	{
		$GLOBALS['catalog_group_writer_test_api_calls'][] = array('api_query', $sql);

		return true;
	}

	function api_exec($sql)
	{
		$GLOBALS['catalog_group_writer_test_api_calls'][] = array('api_exec', $sql);

		return 1;
	}

	function api_value($sql)
	{
		$GLOBALS['catalog_group_writer_test_api_calls'][] = array('api_value', $sql);

		return '3';
	}

	function api_rows($sql)
	{
		$GLOBALS['catalog_group_writer_test_api_calls'][] = array('api_rows', $sql);

		return array(array('page_id' => '8', 'product_group_id' => '3'));
	}

	function api_insert_id()
	{
		$GLOBALS['catalog_group_writer_test_api_calls'][] = array('api_insert_id', '');

		return 42;
	}
}

// pg_catalog_query() answered as array(answer, ''), or array(null, message)
// when the statement reached the db() family: that has no connection here and
// ends in output_error(), which would print its page and end the whole run
// with exit code 0, so it is made to throw for the call.
function catalog_group_writer_test_query($sql, $answer, $opts)
{
	$throws = pg_error_throws();
	pg_error_throws(true);

	try {
		return array(pg_catalog_query($sql, $answer, $opts), '');
	} catch (RuntimeException $exception) {
		return array(null, $exception->getMessage());
	} finally {
		pg_error_throws($throws);
	}
}

// A top of the catalogue that counts how often it was asked.
function catalog_group_writer_test_top($id, &$asked)
{
	return function () use ($id, &$asked) {
		$asked++;

		return $id;
	};
}

// Whitespace folded to single spaces, so a statement can be compared without
// caring how it is wrapped in the source.
function catalog_group_writer_test_flat($sql)
{
	return trim(preg_replace('/\s+/', ' ', (string) $sql));
}

function catalog_group_writer_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

// The body of one function in a source file, up to the next top-level
// function. Enough to ask what a function calls without loading the file.
function catalog_group_writer_test_body($source, $name)
{
	$start = strpos($source, 'function ' . $name . '(');

	if ($start === false) {
		return '';
	}

	$next = strpos($source, "\nfunction ", $start + 1);

	return ($next === false) ? substr($source, $start) : substr($source, $start, $next - $start);
}

// The part of a Workspace writer that runs for one action: from its
// "if ($action === '<action>')" to the next one.
function catalog_group_writer_test_branch($body, $action)
{
	$start = strpos($body, "if (\$action === '" . $action . "')");

	if ($start === false) {
		return '';
	}

	$next = strpos($body, "if (\$action === '", $start + 1);

	return ($next === false) ? substr($body, $start) : substr($body, $start, $next - $start);
}

// The statement api_product_groups_create() built before the writer, kept
// here as it was so that the writer is held to it. $params as the API
// received them; $name trimmed, $parent_id resolved, $full prepared.
function catalog_group_writer_test_api_before($params, $name, $parent_id, $short, $full, $address, $owner_id)
{
	return "INSERT INTO product_groups (name, enabled, parent_id, short_description, full_description, details, code,
			keywords, image_name, display_type, address_name, title, meta_description, meta_keywords, attributes, user, timestamp)
		VALUES (
			'" . escape($name) . "',
			'" . (!empty($params['enabled']) ? '1' : '0') . "',
			'" . $parent_id . "',
			'" . escape($short) . "',
			'" . escape($full) . "',
			'', '',
			'" . escape(isset($params['keywords']) ? (string)$params['keywords'] : '') . "',
			'',
			'browse',
			'" . escape($address) . "',
			'" . escape(isset($params['title']) ? (string)$params['title'] : '') . "',
			'" . escape(isset($params['meta_description']) ? (string)$params['meta_description'] : '') . "',
			'" . escape(isset($params['meta_keywords']) ? (string)$params['meta_keywords'] : '') . "',
			'1',
			'" . (int)$owner_id . "',
			UNIX_TIMESTAMP())";
}

// The statement ws_change_write_product_group() built on a create before the
// writer, kept as it was.
function catalog_group_writer_test_workspace_before($values, $parent, $short, $full, $address, $viewer_id)
{
	return "INSERT INTO product_groups (name, enabled, parent_id, short_description, full_description, details, code, keywords, image_name,
                display_type, address_name, title, meta_description, meta_keywords, attributes, user, timestamp)
            VALUES (
                '" . e($values['name']) . "',
                '" . (!empty($values['enabled']) ? '1' : '0') . "',
                '" . $parent . "',
                '" . e($short) . "',
                '" . e($full) . "',
                '', '',
                '" . e((string) ($values['keywords'] ?? '')) . "',
                '',
                'browse',
                '" . e($address) . "',
                '" . e((string) ($values['title'] ?? '')) . "',
                '" . e((string) ($values['meta_description'] ?? '')) . "',
                '" . e((string) ($values['meta_keywords'] ?? '')) . "',
                '1',
                '" . (int) $viewer_id . "',
                UNIX_TIMESTAMP())";
}

function test_catalog_group_writer_writes_the_row_the_api_wrote()
{
	catalog_group_writer_test_db_stub();

	$requests = array(
		'every field' => array(
			'name' => 'Mugs', 'enabled' => true, 'short_description' => 'Coffee mugs', 'description' => '<p>Big</p>',
			'keywords' => 'mug, cup', 'title' => 'Mugs and cups', 'meta_description' => 'All our mugs', 'meta_keywords' => 'mugs',
		),
		'name only, off' => array('name' => 'Plates'),
		'quotes and an unset flag' => array('name' => "O'Neil \"Tea\"", 'enabled' => 0, 'keywords' => "it's", 'title' => "a\\b"),
	);

	// The description the editor sent, and what the API stored of it with the
	// site at /shop/.
	$requests['every field']['description'] = '<p><a href="/shop/mugs">Big</a> <img src="/shop/files/mug.png"></p>';
	$prepared = array(
		'every field' => '<p><a href="{path}mugs">Big</a> <img src="{path}files/mug.png"></p>',
		'name only, off' => '',
		'quotes and an unset flag' => '',
	);

	foreach ($requests as $label => $params) {
		$name = trim((string) $params['name']);
		$short = isset($params['short_description']) ? (string) $params['short_description'] : '';
		$address = 'Mugs_1';
		$asked = 0;

		// The mapping POST /product-groups hands the writer, run as it is.
		$input = pg_catalog_group_create_input(
			api_product_group_create_data($params, $name, 12, array()),
			array('id' => '7', 'username' => 'owner'),
			catalog_group_writer_test_top(1, $asked),
			'/shop/');

		pg_assert_same(
			catalog_group_writer_test_flat(catalog_group_writer_test_api_before($params, $name, 12, $short, $prepared[$label], $address, 7)),
			catalog_group_writer_test_flat(pg_catalog_group_insert_sql($input['data'], $input['user_id'], $input['parent_id'], $address)),
			$label);

		pg_assert_same(($short !== '') ? $short : $name, $input['address_source'], $label . ': the address is made from the short description, else the name');
		pg_assert_same(0, $asked, $label . ': a parent named, the top is not asked');
	}

	$data = api_product_group_create_data(array('name' => 'Mugs'), 'Mugs', 12, array(3, 5));

	pg_assert_same(12, $data['parent_id'], 'the parent the handler settled');
	pg_assert_same(array(3, 5), $data['product_ids'], 'the products the handler checked');
}

function test_catalog_group_writer_writes_the_row_the_workspace_wrote()
{
	catalog_group_writer_test_db_stub();

	$proposals = array(
		'every field' => array(
			'name' => 'Mugs', 'enabled' => true, 'parent_id' => array(4), 'short_description' => 'Coffee mugs',
			'full_description' => '<p>Big</p>', 'keywords' => 'mug', 'title' => 'Mugs', 'meta_description' => 'All mugs',
			'meta_keywords' => 'mugs', 'product_ids' => array(3, 5),
		),
		'name only' => array('name' => 'Plates'),
		'quotes, switched off' => array('name' => "O'Neil", 'enabled' => false, 'meta_keywords' => "it's"),
	);

	$proposals['every field']['full_description'] = '<p>Big <a href="/cups" title="cups">cups</a></p>';
	$prepared = array(
		'every field' => '<p>Big <a href="{path}cups" title="cups">cups</a></p>',
		'name only' => '',
		'quotes, switched off' => '',
	);

	foreach ($proposals as $label => $values) {
		$short = (string) ($values['short_description'] ?? '');
		$asked = 0;

		// A proposal with no parent goes under the top of the catalogue, 9.
		$input = pg_catalog_group_create_input(ws_change_group_create_data($values), 21, catalog_group_writer_test_top(9, $asked), '/');
		$parent = empty($values['parent_id']) ? 9 : 4;

		pg_assert_same(
			catalog_group_writer_test_flat(catalog_group_writer_test_workspace_before($values, $parent, $short, $prepared[$label], 'Addr', 21)),
			catalog_group_writer_test_flat(pg_catalog_group_insert_sql($input['data'], $input['user_id'], $input['parent_id'], 'Addr')),
			$label);

		pg_assert_same(($short !== '') ? $short : $values['name'], $input['address_source'], $label . ': the address is made from the short description, else the name');
		pg_assert_same(empty($values['parent_id']) ? 1 : 0, $asked, $label . ': the top is asked only when no parent is named');
	}

	$data = ws_change_group_create_data($proposals['every field']);

	pg_assert_same(4, $data['parent_id'], 'the one parent of the proposal');
	pg_assert_same(array(3, 5), $data['product_ids'], 'the products of the proposal');
	pg_assert_same(0, ws_change_group_create_data($proposals['name only'])['parent_id'], 'no parent: the writer files it at the top');
	pg_assert_same(array(), ws_change_group_create_data($proposals['name only'])['product_ids'], 'no products');
}

function test_catalog_group_writer_files_products_as_both_callers_did()
{
	pg_assert_same(
		"INSERT INTO products_groups_xref (product, product_group, sort_order) VALUES ('5', '9', '0')",
		catalog_group_writer_test_flat(pg_catalog_group_xref_sql(9, '5')),
		'one link, sorted first');
}

function test_catalog_group_writer_settles_parent_user_and_products_first()
{
	catalog_group_writer_test_db_stub();

	$parents = array(
		'a parent named' => array(array('parent_id' => 5), 5, 0),
		'parent 0: the top' => array(array('parent_id' => 0), 1, 1),
		'no parent: the top' => array(array(), 1, 1),
		'a negative parent: the top' => array(array('parent_id' => -3), 1, 1),
	);

	foreach ($parents as $label => $case) {
		$asked = 0;
		$input = pg_catalog_group_create_input($case[0] + array('name' => 'Mugs'), 7, catalog_group_writer_test_top(1, $asked), '/');

		pg_assert_same($case[1], $input['parent_id'], $label);
		pg_assert_same($case[2], $asked, $label . ': times the top was asked');
	}

	$asked = 0;

	pg_assert_same(0, pg_catalog_group_create_input(array('name' => 'Mugs'), 7, catalog_group_writer_test_top(0, $asked), '/')['parent_id'], 'an empty catalogue: no top');

	$top = catalog_group_writer_test_top(1, $asked);

	pg_assert_same(7, pg_catalog_group_create_input(array('name' => 'Mugs'), array('id' => '7'), $top, '/')['user_id'], 'the person or key owner as an array');
	pg_assert_same(21, pg_catalog_group_create_input(array('name' => 'Mugs'), '21', $top, '/')['user_id'], 'a user id');
	pg_assert_same(0, pg_catalog_group_create_input(array('name' => 'Mugs'), array(), $top, '/')['user_id'], 'nobody named');

	pg_assert_same(array(3, 5), pg_catalog_group_create_input(array('name' => 'Mugs', 'product_ids' => array('3', 5)), 7, $top, '/')['product_ids'], 'the products as ids');
	pg_assert_same(array(), pg_catalog_group_create_input(array('name' => 'Mugs'), 7, $top, '/')['product_ids'], 'no products');

	pg_assert_same('Coffee mugs', pg_catalog_group_create_input(array('name' => 'Mugs', 'short_description' => 'Coffee mugs'), 7, $top, '/')['address_source'], 'the address from the short description');
	pg_assert_same('Mugs', pg_catalog_group_create_input(array('name' => 'Mugs', 'short_description' => ''), 7, $top, '/')['address_source'], 'from the name without one');

	$input = pg_catalog_group_create_input(array('name' => 'Mugs', 'full_description' => '<a href="/shop/a">a</a><a href="https://example.com/shop/b">b</a><input value="/shop/c">'), 7, $top, '/shop/');

	pg_assert_same('<a href="{path}a">a</a><a href="https://example.com/shop/b">b</a><input value="{path}c">', $input['data']['full_description'], 'the site path stored as {path}, other addresses left alone');
}

function test_catalog_group_writer_writes_the_row_then_files_each_product()
{
	catalog_group_writer_test_db_stub();

	$asked = 0;
	$input = pg_catalog_group_create_input(array('name' => 'Mugs', 'parent_id' => 4, 'product_ids' => array(3, 5)), 7, catalog_group_writer_test_top(1, $asked), '/');
	$ran = array();

	$id = pg_catalog_group_write($input, 'Mugs', function ($sql, $answer) use (&$ran) {
		$ran[] = array($answer, catalog_group_writer_test_flat($sql));

		return ($answer === 'insert') ? 42 : 1;
	});

	pg_assert_same(42, $id, 'the new id');
	pg_assert_same(array(
		array('insert', catalog_group_writer_test_flat(pg_catalog_group_insert_sql($input['data'], 7, 4, 'Mugs'))),
		array('exec', catalog_group_writer_test_flat(pg_catalog_group_xref_sql(42, 3))),
		array('exec', catalog_group_writer_test_flat(pg_catalog_group_xref_sql(42, 5))),
	), $ran, 'the row, then one link per product under the new id');

	$ran = array();

	$id = pg_catalog_group_write($input, 'Mugs', function ($sql, $answer) use (&$ran) {
		$ran[] = $answer;

		return 0;
	});

	pg_assert_same(0, $id, 'no row, no id');
	pg_assert_same(array('insert'), $ran, 'and no product filed');
}

function test_catalog_group_writer_api_create_is_a_thin_wrapper()
{
	$body = catalog_group_writer_test_body(catalog_group_writer_test_source('includes/api/resources/product_groups.php'), 'api_product_groups_create');

	pg_assert_contains("pg_catalog_group_create(api_product_group_create_data(\$params, \$name, \$parent_id, \$product_ids), \$app['owner'], array('api' => true));", $body, 'writes through the shared writer, the request mapped by the tested helper, stamped with the key owner, through the API\'s own query helpers');
	pg_assert_false(strpos($body, 'INSERT INTO') !== false, 'no INSERT of its own');
	pg_assert_false(strpos($body, "'product_group.created'") !== false, 'no announcement of its own: the writer makes the one');
	pg_assert_contains("api_fail(409, 'name_taken'", $body, 'a taken name is still 409');
	pg_assert_contains("api_fail_server(lang('The product group could not be created.'))", $body, 'a row not written is still 500');
	pg_assert_contains("api_dry_run_stop('created', 'product_group', array('name' => \$name, 'parent_id' => \$parent_id, 'products' => count(\$product_ids)));", $body, 'the same rehearsal answer');
	pg_assert_true(strpos($body, 'api_dry_run_stop(') < strpos($body, 'pg_catalog_group_create('), 'the rehearsal stops before the writer');
	pg_assert_contains("'Product group ({var:1}) was added through the API by the application {var:2} (key {var:3}).'", $body, 'the same Site Log line');
	pg_assert_contains('api_ok(api_product_group_present($row, api_product_group_extras(array($id), true), true), 201);', $body, 'the same answer');
}

function test_catalog_group_writer_workspace_create_is_a_thin_wrapper()
{
	$body = catalog_group_writer_test_body(catalog_group_writer_test_source('includes/workspace/changes.php'), 'ws_change_write_product_group');
	$create = catalog_group_writer_test_branch($body, 'create');

	pg_assert_contains('pg_catalog_group_create(ws_change_group_create_data($values), $viewer)', $create, 'writes through the shared writer');
	pg_assert_false(strpos($create, 'INSERT INTO') !== false, 'no INSERT of its own');
	pg_assert_contains("'product group ({var:1}) was created'", $create, 'the same Site Log line');
	pg_assert_contains("lang('The product group could not be created.')", $create, 'the same refusal when the row was not written');
	pg_assert_false(strpos($create, 'pg_announce(') !== false, 'no announcement of its own: the writer makes the one');
}

function test_catalog_group_writer_clouds_twins_delegate()
{
	$api = catalog_group_writer_test_body(catalog_group_writer_test_source('includes/api/resources/product_groups.php'), 'api_product_group_clouds');
	$workspace = catalog_group_writer_test_body(catalog_group_writer_test_source('includes/workspace/changes.php'), 'ws_change_group_clouds');

	pg_assert_contains("return pg_catalog_group_clouds(\$group_id, \$clear_only, array('api' => true));", $api, 'the API twin');
	pg_assert_contains('return pg_catalog_group_clouds($group_id, $clear_only);', $workspace, 'the Workspace twin');

	foreach (array('API' => $api, 'Workspace' => $workspace) as $label => $twin) {
		pg_assert_false(strpos($twin, 'search_results_pages') !== false, $label . ' twin keeps no copy of the rebuild');
	}

	$writer = catalog_group_writer_test_body(catalog_group_writer_test_source('includes/fn/ecommerce.php'), 'pg_catalog_group_clouds');

	pg_assert_contains('search_results_pages', $writer, 'the rebuild lives in the writer');
	pg_assert_contains('delete_tag_cloud_keywords_for_search_results_page(', $writer, 'clouds taken down');
	pg_assert_contains('if (!$clear_only)', $writer, 'built again unless the group is on its way out');
}

function test_catalog_group_writer_scores_seo_and_announces_once()
{
	$writer = catalog_group_writer_test_body(catalog_group_writer_test_source('includes/fn/ecommerce.php'), 'pg_catalog_group_create');

	pg_assert_contains("pg_catalog_seo_rescore('product_group', \$id);", $writer, 'the new group is scored');
	pg_assert_same(1, substr_count($writer, "pg_announce('product_group.created'"), 'announced once, by the writer');
	pg_assert_true(strpos($writer, 'pg_catalog_group_clouds(') < strpos($writer, 'pg_catalog_seo_rescore('), 'scored after the clouds are built');
	pg_assert_true(strpos($writer, 'pg_catalog_seo_rescore(') < strpos($writer, 'pg_announce('), 'announced once the row is complete');

	// Nobody around the writer announces the create a second time.
	foreach (array('includes/api/resources/product_groups.php', 'includes/workspace/changes.php') as $relative) {
		pg_assert_false(strpos(catalog_group_writer_test_source($relative), "'product_group.created'") !== false, $relative . ' leaves the announcement to the writer');
	}

	$rescore = catalog_group_writer_test_body(catalog_group_writer_test_source('includes/fn/ecommerce.php'), 'pg_catalog_seo_rescore');

	pg_assert_contains('pg_seo_schema_ready()', $rescore, 'only once the SEO columns are there');
	pg_assert_contains('pg_seo_recalculate($type, array((int) $id));', $rescore, 'the record scored now, as the API scores a product');

	$api = catalog_group_writer_test_body(catalog_group_writer_test_source('includes/api/resources/products.php'), 'api_products_rescore');

	pg_assert_contains("pg_catalog_seo_rescore('product', \$product_id);", $api, 'the API\'s product rescore is the same helper');
}

function test_catalog_group_writer_workspace_product_create_is_scored()
{
	$body = catalog_group_writer_test_body(catalog_group_writer_test_source('includes/workspace/changes.php'), 'ws_change_write_product');
	$create = catalog_group_writer_test_branch($body, 'create');

	pg_assert_contains("pg_catalog_seo_rescore('product', \$id);", $create, 'the new product is scored');
	pg_assert_true(strpos($create, 'pg_pb_create_product(') < strpos($create, 'pg_catalog_seo_rescore('), 'after the product is written');
	pg_assert_true(strpos($create, 'pg_catalog_seo_rescore(') < strpos($create, "pg_announce('product.created'"), 'before it is announced');
}

function test_catalog_group_writer_api_queries_fail_the_api_way()
{
	catalog_group_writer_test_db_stub();

	$api = array('api' => true);
	$GLOBALS['catalog_group_writer_test_api_calls'] = array();

	pg_assert_same(array(1, ''), catalog_group_writer_test_query('UPDATE a', 'exec', $api), 'a write');
	pg_assert_same(array('3', ''), catalog_group_writer_test_query('SELECT b', 'value', $api), 'a value');
	pg_assert_same(array(array(array('page_id' => '8', 'product_group_id' => '3')), ''), catalog_group_writer_test_query('SELECT c', 'rows', $api), 'rows');
	pg_assert_same(array(42, ''), catalog_group_writer_test_query('INSERT d', 'insert', $api), 'an insert answers the new id');

	pg_assert_same(array(
		array('api_exec', 'UPDATE a'),
		array('api_value', 'SELECT b'),
		array('api_rows', 'SELECT c'),
		array('api_exec', 'INSERT d'),
		array('api_insert_id', ''),
	), $GLOBALS['catalog_group_writer_test_api_calls'], 'every statement through the API\'s helpers, which answer a failure with its JSON 500');

	// Elsewhere the db() family runs them.
	foreach (array('exec', 'value', 'rows', 'insert') as $answer) {
		$GLOBALS['catalog_group_writer_test_api_calls'] = array();

		pg_assert_same(array(null, 'No database connection.'), catalog_group_writer_test_query('SELECT e', $answer, array()), $answer . ': through the db() family');
		pg_assert_same(array(), $GLOBALS['catalog_group_writer_test_api_calls'], $answer . ': not through the API\'s helpers');
	}

	$writer = catalog_group_writer_test_body(catalog_group_writer_test_source('includes/fn/ecommerce.php'), 'pg_catalog_group_create');

	pg_assert_contains('return pg_catalog_query($sql, $answer, $opts);', $writer, 'the row and its links through the chosen helpers');
	pg_assert_contains('return pg_catalog_group_top_id($opts);', $writer, 'the top through the same helpers');
	pg_assert_contains("pg_catalog_group_write(\$input, prepare_catalog_item_address_name(\$input['address_source']), \$run);", $writer, 'the address made by the catalogue\'s rule, then the row written');
	pg_assert_contains('pg_catalog_group_clouds($id, false, $opts);', $writer, 'the clouds through the same helpers');
}

function test_catalog_group_writer_leaves_the_group_screen_and_variant_sets_alone()
{
	// add_product_group.php and the variant set builder keep their own code:
	// a screen with images, codes and details, and a 'select' group.
	pg_assert_false(strpos(catalog_group_writer_test_source('add_product_group.php'), 'pg_catalog_group_create(') !== false, 'add_product_group.php');
	pg_assert_false(strpos(catalog_group_writer_test_body(catalog_group_writer_test_source('product_builder.php'), 'pg_pb_create_group'), 'pg_catalog_group_create(') !== false, 'pg_pb_create_group()');
}

function test_catalog_group_writer_event_names_the_workspace()
{
	$source = catalog_group_writer_test_source('includes/api/outbound/webhooks.php');

	pg_assert_true(strpos($source, "'product_group.created' => 'A product group was created through the API or the Workspace'") !== false, 'the catalogue says who announces it');
}
