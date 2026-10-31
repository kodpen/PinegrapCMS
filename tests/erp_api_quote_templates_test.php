<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the sales quote template routes of the external API
 * (includes/erp/api.php, includes/erp/api_quote_templates.php) and the checks
 * they share with the panel (includes/erp/quote_templates.php), with no
 * database: the route rows and their scopes, the rehearsable writes, the
 * object's fields, the API line list read as template lines, the save and
 * delete refusals, and the two events.
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

require_once(PG_FUNCTIONS_DIR . '/includes/erp/api.php');
require_once(__DIR__ . '/helpers/source_reader.php');

// The ERP routes by id.
function erp_api_quote_templates_test_routes()
{
	$routes = array();

	foreach (erp_api_routes() as $route) {
		$routes[$route['id']] = $route;
	}

	return $routes;
}

// One line as a client sends it to POST /erp/quotes.
function erp_api_quote_templates_test_line($extra = array())
{
	return array_merge(array(
		'product_id' => 12,
		'description' => 'Installation',
		'quantity' => 2,
		'unit' => 'hur',
		'unit_price' => 150000,
		'discount_rate' => 5,
		'tax_rate' => 20,
	), $extra);
}

// No existing template of that name.
function erp_api_quote_templates_test_none()
{
	return function ($scope, $owner_id, $name) {
		return 0;
	};
}

function test_erp_api_quote_template_routes_exist()
{
	$routes = erp_api_quote_templates_test_routes();
	$expected = array(
		'erp.quote_templates.list' => array('GET', '/erp/quote-templates', 'erp:read'),
		'erp.quote_templates.get' => array('GET', '/erp/quote-templates/{id}', 'erp:read'),
		'erp.quote_templates.save' => array('POST', '/erp/quote-templates', 'erp:write'),
		'erp.quote_templates.delete' => array('DELETE', '/erp/quote-templates/{id}', 'erp:write'),
		'erp.quote_templates.delete_post' => array('POST', '/erp/quote-templates/{id}/delete', 'erp:write'),
	);

	foreach ($expected as $id => $row) {
		pg_assert_true(isset($routes[$id]), $id . ' is declared');
		pg_assert_same($row[0], $routes[$id]['method'] ?? '', $id . ' method');
		pg_assert_same($row[1], $routes[$id]['path'] ?? '', $id . ' path');
		pg_assert_same($row[2], $routes[$id]['scope'] ?? '', $id . ' scope');
	}
}

// Every write can be rehearsed, and the POST twin of the delete runs the
// same handler.
function test_erp_api_quote_template_writes_can_be_rehearsed()
{
	$routes = erp_api_quote_templates_test_routes();

	foreach (array('erp.quote_templates.save', 'erp.quote_templates.delete', 'erp.quote_templates.delete_post') as $id) {
		pg_assert_true(!empty($routes[$id]['dry_run']), $id . ' takes X-Dry-Run');
	}

	pg_assert_same($routes['erp.quote_templates.delete']['handler'], $routes['erp.quote_templates.delete_post']['handler'], 'the POST twin runs the same handler');

	foreach (array('erp_api_quote_templates_save', 'erp_api_quote_templates_delete') as $handler) {
		$source = pg_test_function_source($handler);
		$check = strpos($source, '_check(');
		$stop = strpos($source, 'api_dry_run_stop(');
		$write = strpos($source, ($handler === 'erp_api_quote_templates_save') ? 'erp_quote_template_save(' : 'erp_quote_template_delete(');

		pg_assert_true(($check !== false) && ($stop !== false) && ($write !== false), $handler . ' checks, stops and writes');
		pg_assert_true(($check < $stop) && ($stop < $write), $handler . ': the stop sits after the check and above the write');
	}

	// The save answers 201 for a new template, so the route declares it.
	pg_assert_same(201, $routes['erp.quote_templates.save']['status'] ?? 0, 'a new template answers 201');
	pg_assert_same('ErpQuoteTemplate', $routes['erp.quote_templates.get']['returns'] ?? '', 'one template');
}

function test_erp_api_quote_template_presenter_matches_its_schema()
{
	$objects = erp_openapi_objects();
	pg_assert_same('erp_api_quote_template_schema', $objects['ErpQuoteTemplate'] ?? '', 'the object is registered');

	$row = array(
		'id' => 4,
		'name' => 'Installation pack',
		'scope' => 'personal',
		'user_id' => 7,
		'updated_at' => 1793404800,
		'form_data' => json_encode(array('lines' => array(array('product_id' => 12, 'description' => 'Installation', 'quantity' => 2, 'unit_code' => 'C62', 'unit_price' => 150000)))),
	);

	$presented = erp_api_quote_template_present($row, 7);
	$schema = erp_api_quote_template_schema();

	pg_assert_same(array_keys($schema), array_keys($presented), 'the presenter gives the schema\'s fields');
	pg_assert_true($presented['mine'], 'the owner\'s own template');
	pg_assert_false(erp_api_quote_template_present($row, 8)['mine'], 'not another user\'s');
	pg_assert_same(150000, $presented['lines'][0]['unit_price'], 'money in minor units');
	pg_assert_same('2026-10-31T00:00:00Z', $presented['updated_at'], 'ISO-8601 UTC');
	pg_assert_same(array_keys($schema['lines'][0]), array_keys($presented['lines'][0]), 'a line gives the schema\'s fields');
	pg_assert_false(isset($presented['user_id']), 'no owner id on the wire');
}

// The line list of POST /erp/quotes, read as template lines.
function test_erp_quote_template_lines_from_api()
{
	$read = erp_quote_template_lines_from_api(array(
		erp_api_quote_templates_test_line(),
		array('description' => '', 'unit_price' => 0),
		erp_api_quote_templates_test_line(array('product_id' => 0, 'description' => 'Visit', 'unit' => '', 'unit_price' => 99)),
	));

	pg_assert_same('', $read['error'], 'accepted');
	pg_assert_same(2, count($read['lines']), 'the empty row is dropped');
	pg_assert_same(150000, $read['lines'][0]['unit_price'], 'minor units kept');
	pg_assert_same('HUR', $read['lines'][0]['unit_code'], 'unit is the unit code');
	pg_assert_same(2.0, $read['lines'][0]['quantity'], 'quantity');
	pg_assert_same(5.0, $read['lines'][0]['discount_rate'], 'discount');
	pg_assert_same('C62', $read['lines'][1]['unit_code'], 'a piece when left out');
	pg_assert_same(1.0, erp_quote_template_lines_from_api(array(array('description' => 'x', 'unit_price' => 1)))['lines'][0]['quantity'], 'one when quantity is left out');

	$none = erp_quote_template_lines_from_api(array(array('description' => ' ', 'unit_price' => 5)));
	pg_assert_true($none['error'] !== '', 'no line is refused');
	pg_assert_same('lines', $none['field'], 'on lines');

	pg_assert_true(erp_quote_template_lines_from_api(array())['error'] !== '', 'an empty list is refused');

	$decimal = erp_quote_template_lines_from_api(array(erp_api_quote_templates_test_line(array('unit_price' => '19.99'))));
	pg_assert_same('lines[0].unit_price', $decimal['field'], 'a decimal price is refused');

	$unknown = erp_quote_template_lines_from_api(array(erp_api_quote_templates_test_line(array('account_id' => 3))));
	pg_assert_same('lines[0].account_id', $unknown['field'], 'a template keeps lines only');

	$text = erp_quote_template_lines_from_api(array(erp_api_quote_templates_test_line(array('tax_rate' => 'twenty'))));
	pg_assert_same('lines[0].tax_rate', $text['field'], 'a rate must be a number');

	pg_assert_same('lines[0]', erp_quote_template_lines_from_api(array('x'))['field'], 'a line is an object');
}

function test_erp_quote_template_save_check_refusals()
{
	$lines = array(array('product_id' => 12, 'description' => 'Installation', 'quantity' => 1.0, 'unit_code' => 'C62', 'unit_price' => 100));
	$none = erp_api_quote_templates_test_none();

	pg_assert_true(erp_quote_template_save_check('Pack', 'site', $lines, 7, true, $none)['error'] !== '', 'the read-only right writes none');
	pg_assert_true(erp_quote_template_save_check('Pack', 'team', $lines, 7, false, $none)['error'] !== '', 'an unknown scope');
	pg_assert_same('scope', erp_quote_template_save_check('Pack', 'team', $lines, 7, false, $none)['field'], 'on scope');
	pg_assert_same('name', erp_quote_template_save_check("  \t ", 'site', $lines, 7, false, $none)['field'], 'an empty name');
	pg_assert_same('lines', erp_quote_template_save_check('Pack', 'site', array(), 7, false, $none)['field'], 'no lines');

	$ok = erp_quote_template_save_check("  Install   pack ", 'personal', $lines, 7, false, $none);
	pg_assert_same('', $ok['error'], 'accepted');
	pg_assert_same('Install pack', $ok['name'], 'the name is tidied');
	pg_assert_same(7, $ok['owner_id'], 'a personal template is its maker\'s');
	pg_assert_same(0, $ok['replaces'], 'a new one');
	pg_assert_same(1, count(erp_quote_template_lines($ok['form_data'])), 'the encoded lines');
	pg_assert_same(0, erp_quote_template_save_check('Pack', 'site', $lines, 7, false, $none)['owner_id'], 'a site template has no owner');
	pg_assert_same(100, mb_strlen(erp_quote_template_save_check(str_repeat('a', 120), 'site', $lines, 7, false, $none)['name']), 'cut to the column');
}

// A same-name template in the same scope and owner is written over; the
// lookup is asked with the scope and the owner the row would carry.
function test_erp_quote_template_save_check_overwrites_same_name()
{
	$lines = array(array('description' => 'Installation', 'unit_price' => 100));
	$asked = array();
	$lookup = function ($scope, $owner_id, $name) use (&$asked) {
		$asked[] = array($scope, $owner_id, $name);
		return 31;
	};

	$check = erp_quote_template_save_check('Pack', 'personal', $lines, 7, false, $lookup);
	pg_assert_same(31, $check['replaces'], 'the same-name template is replaced');
	pg_assert_same(array('personal', 7, 'Pack'), $asked[0], 'looked up in the same scope and owner');

	erp_quote_template_save_check('Pack', 'site', $lines, 7, false, $lookup);
	pg_assert_same(array('site', 0, 'Pack'), $asked[1], 'a site name is shared');

	pg_assert_contains('erp_quote_template_save_check(', pg_test_function_source('erp_quote_template_save'), 'the panel save asks the same check');
	pg_assert_contains('erp_quote_template_delete_check(', pg_test_function_source('erp_quote_template_delete'), 'the panel delete asks the same check');
}

function test_erp_quote_template_delete_check()
{
	$personal = array('id' => 4, 'name' => 'Pack', 'scope' => 'personal', 'user_id' => 7);
	$site = array('id' => 5, 'name' => 'Site pack', 'scope' => 'site', 'user_id' => 0);

	pg_assert_same(404, erp_quote_template_delete_check(null, 7, false)['status'], 'not found');
	pg_assert_same(404, erp_quote_template_delete_check($personal, 8, false)['status'], 'another user\'s personal template is not seen');
	pg_assert_same('', erp_quote_template_delete_check($personal, 7, false)['error'], 'its owner deletes it');
	pg_assert_same('', erp_quote_template_delete_check($site, 8, false)['error'], 'anyone who may write quotes deletes a site one');
	pg_assert_same(422, erp_quote_template_delete_check($site, 8, true)['status'], 'not the read-only right');
}

function test_erp_quote_template_events()
{
	$events = erp_webhook_events();

	pg_assert_true(isset($events['erp.quote_template.saved']), 'saved is listed');
	pg_assert_true(isset($events['erp.quote_template.deleted']), 'deleted is listed');
	pg_assert_contains("erp_event('erp.quote_template.saved'", pg_test_function_source('erp_quote_template_save'), 'the save announces');
	pg_assert_contains("erp_event('erp.quote_template.deleted'", pg_test_function_source('erp_quote_template_delete'), 'the delete announces');
}
