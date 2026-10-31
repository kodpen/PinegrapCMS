<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for lead_source on the external API's customer writes
 * (includes/api/resources/customers.php, includes/api/schema.php): both
 * routes declare it within the varchar(50) column, the value a write would
 * store or the refusal it answers, and the Customer object reads it back.
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

require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/customers.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');

// Referral source rows as pg_referral_sources() returns them.
function api_customer_lead_source_test_sources()
{
	return array(
		array('name' => 'Search Engine', 'code' => 'search'),
		array('name' => 'Friend', 'code' => 'friend'),
	);
}

// The route row of an id.
function api_customer_lead_source_test_route($id)
{
	foreach (api_schema() as $route) {
		if (isset($route['id']) && ($route['id'] === $id)) {
			return $route;
		}
	}

	return null;
}

// Both writes take lead_source as a body string no longer than the column.
function test_api_customer_lead_source_routes_declare_the_param()
{
	foreach (array('customers.create', 'customers.update') as $id) {
		$route = api_customer_lead_source_test_route($id);
		pg_assert_true(is_array($route), $id);

		$found = null;

		foreach ($route['params'] as $param) {
			if ($param['name'] === 'lead_source') {
				$found = $param;
			}
		}

		pg_assert_true(is_array($found), $id . ' lead_source');
		pg_assert_same('body', $found['in'], $id);
		pg_assert_same('string', $found['type'], $id);
		pg_assert_same(50, $found['max_length'], $id);
		pg_assert_true(!empty($found['description']), $id . ' description');
	}
}

// Not sent: nothing to check and nothing written.
function test_api_customer_lead_source_not_sent()
{
	pg_assert_same(null, api_customer_lead_source(array('first_name' => 'Ada'), api_customer_lead_source_test_sources(), ''));
}

// A code is stored as it is; a name in another case with spaces as its code.
function test_api_customer_lead_source_code_and_name()
{
	$sources = api_customer_lead_source_test_sources();

	pg_assert_same(array('value' => 'search', 'error' => ''), api_customer_lead_source(array('lead_source' => 'search'), $sources, ''));
	pg_assert_same(array('value' => 'friend', 'error' => ''), api_customer_lead_source(array('lead_source' => '  fRiEnD '), $sources, ''));
	pg_assert_same(array('value' => 'search', 'error' => ''), api_customer_lead_source(array('lead_source' => ' search engine'), $sources, 'friend'));
}

// '' empties the stored value.
function test_api_customer_lead_source_empty_clears()
{
	pg_assert_same(array('value' => '', 'error' => ''), api_customer_lead_source(array('lead_source' => ''), api_customer_lead_source_test_sources(), 'friend'));
}

// The old free text a record already holds is kept on update.
function test_api_customer_lead_source_keeps_the_stored_text()
{
	pg_assert_same(
		array('value' => 'Fair 2019', 'error' => ''),
		api_customer_lead_source(array('lead_source' => 'Fair 2019'), api_customer_lead_source_test_sources(), 'Fair 2019')
	);
}

// An unknown value on create is refused, naming it.
function test_api_customer_lead_source_unknown_refused()
{
	$check = api_customer_lead_source(array('lead_source' => 'Fair 2019'), api_customer_lead_source_test_sources(), '');

	pg_assert_true($check['error'] !== '', 'refused');
	pg_assert_contains('Fair 2019', $check['error']);
}

// A value that is no text is refused.
function test_api_customer_lead_source_non_string_refused()
{
	$check = api_customer_lead_source(array('lead_source' => array('search')), api_customer_lead_source_test_sources(), '');

	pg_assert_true($check['error'] !== '', 'refused');
}

// The Customer object reads lead_source back, and its keys are the schema's.
function test_api_customer_lead_source_presenter_matches_schema()
{
	$row = array(
		'id' => '7', 'member_id' => null, 'first_name' => 'Ada', 'last_name' => null,
		'email_address' => null, 'company' => null, 'mobile_phone' => null,
		'business_phone' => null, 'home_phone' => null, 'business_address_1' => null,
		'business_city' => null, 'business_state' => null, 'business_zip_code' => null,
		'business_country' => null, 'lead_source' => 'search',
	);

	$present = api_customer_present($row);

	pg_assert_same(array_keys(api_customer_schema()), array_keys($present));
	pg_assert_same('search', $present['lead_source']);
	pg_assert_same('string?', api_customer_schema()['lead_source']);
	pg_assert_contains('contacts.lead_source', api_customer_select());
}
