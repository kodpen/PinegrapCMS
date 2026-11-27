<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure parts of includes/fn/quickbooks.php: the company id,
 * the environment and its addresses, the discovery document's addresses,
 * the company read, the chart of accounts and which account fits each
 * role, the tax codes offered, QuickBooks' error words and the stored
 * state; for the return parameter pg_connect_finish() keeps; and for the
 * provider and purpose definitions, the registry row and the page's cards.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/quickbooks.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/accounting_sync.php');

// A query answer of accounts, shaped as the accounting API sends it.
function quickbooks_test_accounts_answer()
{
	return array('QueryResponse' => array('Account' => array(
		array('Id' => '79', 'Name' => 'Sales of Product Income', 'AcctNum' => '4000', 'AccountType' => 'Income', 'AccountSubType' => 'SalesOfProductIncome', 'Active' => true),
		array('Id' => '4', 'Name' => 'Undeposited Funds', 'AccountType' => 'Other Current Asset', 'AccountSubType' => 'UndepositedFunds', 'Active' => true),
		array('Id' => '35', 'Name' => 'Checking', 'AccountType' => 'Bank', 'AccountSubType' => 'Checking', 'Active' => true),
		array('Id' => '36', 'Name' => 'Old savings', 'AccountType' => 'Bank', 'Active' => false),
		array('Id' => '7', 'Name' => 'Advertising', 'AccountType' => 'Expense', 'Active' => true),
		array('Id' => 'x1', 'Name' => 'Broken', 'AccountType' => 'Bank'),
		'not an account',
	), 'startPosition' => 1, 'maxResults' => 7));
}

function test_quickbooks_realm_and_environment()
{
	pg_assert_true(quickbooks_realm_valid('9130354875123456'), 'digits');
	pg_assert_true(!quickbooks_realm_valid('9130 3548'), 'no space');
	pg_assert_true(!quickbooks_realm_valid("123\n"), 'no newline');
	pg_assert_true(!quickbooks_realm_valid(123), 'a string');
	pg_assert_same('sandbox', quickbooks_environment_value('sandbox'));
	pg_assert_same('production', quickbooks_environment_value('other'), 'anything else is production');
	pg_assert_same('https://sandbox-quickbooks.api.intuit.com/v3/company/', quickbooks_api_base('sandbox'));
	pg_assert_same('https://quickbooks.api.intuit.com/v3/company/', quickbooks_api_base('production'));
	pg_assert_true(strpos(quickbooks_discovery_url('sandbox'), 'openid_sandbox_configuration') !== false, 'the sandbox document');
}

function test_quickbooks_sign_in_from_discovery()
{
	$documented = quickbooks_sign_in_documented();

	pg_assert_same($documented, quickbooks_sign_in(null), 'nothing read: the documented addresses');

	$read = quickbooks_sign_in(array(
		'issuer'                 => 'https://oauth.platform.intuit.com/op/v1',
		'authorization_endpoint' => 'https://appcenter.intuit.com/connect/oauth2/v2',
		'token_endpoint'         => 'https://evil.example.com/token',
		'revocation_endpoint'    => 'http://developer.api.intuit.com/v2/oauth2/tokens/revoke',
	));

	pg_assert_same('https://appcenter.intuit.com/connect/oauth2/v2', $read['authorize'], 'an Intuit address is taken');
	pg_assert_same($documented['token'], $read['token'], 'a foreign host is not');
	pg_assert_same($documented['revoke'], $read['revoke'], 'nor plain HTTP');
	pg_assert_same($read, quickbooks_sign_in($read), 'what was kept reads as itself');

	pg_assert_true(!quickbooks_intuit_url('https://intuit.com.evil.example/x'), 'a look-alike host');
	pg_assert_true(!quickbooks_intuit_url('https://user@appcenter.intuit.com/x'), 'no user part');
	pg_assert_true(quickbooks_discovery_due(0, 1000), 'never read');
	pg_assert_true(!quickbooks_discovery_due(1000, 1000 + 3600), 'read an hour ago');
	pg_assert_true(quickbooks_discovery_due(1000, 1000 + 86400), 'a day old');
	pg_assert_true(quickbooks_discovery_due(5000, 1000), 'a time ahead of the clock');
}

function test_quickbooks_company_from_api()
{
	pg_assert_same(array('name' => 'Craig\'s Design and Landscaping', 'country' => 'US'),
		quickbooks_company_from_api(array('CompanyInfo' => array('CompanyName' => 'Craig\'s Design and Landscaping', 'Country' => 'US'))));
	pg_assert_same(array('name' => 'Legal Ltd', 'country' => 'GB'),
		quickbooks_company_from_api(array('CompanyInfo' => array('CompanyName' => ' ', 'LegalName' => 'Legal Ltd', 'CompanyAddr' => array('Country' => 'gb')))), 'the legal name and the address\'s country');
	pg_assert_same('US', quickbooks_company_from_api(array('CompanyInfo' => array('Country' => 'USA')))['country'], 'USA is US');
	pg_assert_same(array('name' => '', 'country' => ''), quickbooks_company_from_api('nope'));
}

function test_quickbooks_accounts_and_roles()
{
	$accounts = quickbooks_accounts_from_api(quickbooks_test_accounts_answer());

	pg_assert_same(array('79', '4', '35', '36', '7'), array_map('strval', array_keys($accounts)), 'numeric ids only');
	pg_assert_same(false, $accounts['36']['active'], 'inactive kept as such');
	pg_assert_same(array('79' => '4000 — Sales of Product Income'), quickbooks_accounts_for_role($accounts, 'income'));
	pg_assert_same(array('35' => 'Checking', '4' => 'Undeposited Funds'), quickbooks_accounts_for_role($accounts, 'clearing'), 'bank or other current asset');
	pg_assert_same(array('35' => 'Checking'), quickbooks_accounts_for_role($accounts, 'bank'), 'an active bank account');
	pg_assert_true(!quickbooks_account_fits('other', $accounts['35']), 'no other role');
	pg_assert_same('12', quickbooks_account_label(array('id' => '12', 'number' => '', 'name' => '')), 'the id for a missing name');
}

function test_quickbooks_tax_codes_for_sales()
{
	$answer = array('QueryResponse' => array('TaxCode' => array(
		array('Id' => '3', 'Name' => '20.0% S', 'Description' => 'Standard rate', 'Active' => true,
			'SalesTaxRateList' => array('TaxRateDetail' => array(array('TaxRateRef' => array('value' => '4'))))),
		array('Id' => '5', 'Name' => 'No VAT', 'Description' => 'No VAT', 'Active' => true,
			'SalesTaxRateList' => array('TaxRateDetail' => array(array('TaxRateRef' => array('value' => '9'))))),
		array('Id' => '6', 'Name' => 'Purchases only', 'Active' => true, 'PurchaseTaxRateList' => array('TaxRateDetail' => array(array('TaxRateRef' => array('value' => '8'))))),
		array('Id' => '7', 'Name' => 'Old', 'Active' => false, 'SalesTaxRateList' => array('TaxRateDetail' => array(array()))),
		array('Id' => 'TAX', 'Name' => 'TAX', 'SalesTaxRateList' => array('TaxRateDetail' => array(array()))),
	)));

	pg_assert_same(array('3' => '20.0% S — Standard rate', '5' => 'No VAT'), quickbooks_tax_codes_for_sales($answer), 'active sales codes, US pseudo-codes left out');
	pg_assert_true(quickbooks_tax_us(array('country' => 'US')), 'a US company');
	pg_assert_true(!quickbooks_tax_us(array('country' => 'GB')), 'another');
}

function test_quickbooks_error_message()
{
	$fault = array('Fault' => array('type' => 'ValidationFault', 'Error' => array(
		array('Message' => 'Duplicate Name Exists Error', 'Detail' => 'The name supplied already exists.', 'code' => '6240'))));
	pg_assert_same('HTTP 400: Duplicate Name Exists Error: The name supplied already exists. (6240)', quickbooks_error_message(400, $fault, ''));
	pg_assert_same('HTTP 401: AuthenticationFailed (3200)', quickbooks_error_message(401, array('fault' => array('error' => array(array('message' => 'AuthenticationFailed', 'code' => '3200')))), ''), 'lower-case fault');
	pg_assert_same('HTTP 400: invalid_grant', quickbooks_error_message(400, array('error' => 'invalid_grant'), ''), 'the sign-in\'s error');
	pg_assert_same('timed out', quickbooks_error_message(0, null, 'timed out'), 'the transport');
}

function test_quickbooks_state_parse()
{
	$empty = quickbooks_state_parse(null);

	pg_assert_same('production', $empty['environment']);
	pg_assert_same(quickbooks_sign_in_documented(), $empty['sign_in']);
	pg_assert_same('', $empty['realm_id']);
	pg_assert_same(array('id' => '', 'number' => '', 'name' => ''), $empty['accounts']['income']);

	$state = quickbooks_state_parse(array(
		'environment' => 'sandbox', 'sign_in_read_at' => 50, 'connection_id' => '4', 'realm_id' => '9130354875',
		'company_name' => 'Sandbox Company', 'country' => 'gb', 'connected_at' => 60,
		'accounts' => array('income' => array('id' => '79', 'number' => '4000', 'name' => 'Sales', 'type' => 'Income'), 'bank' => array('id' => 'nope')),
	));

	pg_assert_same('sandbox', $state['environment']);
	pg_assert_same(4, $state['connection_id']);
	pg_assert_same('GB', $state['country']);
	pg_assert_same(array('id' => '79', 'number' => '4000', 'name' => 'Sales'), $state['accounts']['income'], 'only id, number and name');
	pg_assert_same('', $state['accounts']['bank']['id'], 'a bad id is none');
	pg_assert_same($state, quickbooks_state_parse($state), 'a parsed state parses to itself');

	$no_realm = quickbooks_state_parse(array('realm_id' => 'x', 'company_name' => 'X', 'accounts' => array('income' => array('id' => '79'))));
	pg_assert_same('', $no_realm['company_name'], 'no company, no name');
	pg_assert_same('', $no_realm['accounts']['income']['id'], 'no company, no account');

	$forgotten = quickbooks_state_forgotten($state);
	pg_assert_same('sandbox', $forgotten['environment'], 'the environment stays');
	pg_assert_same(50, $forgotten['sign_in_read_at'], 'the addresses stay');
	pg_assert_same('', $forgotten['realm_id'], 'the company goes');
	pg_assert_same(0, $forgotten['connection_id'], 'the connection goes');
}

function test_quickbooks_return_params_kept()
{
	$provider = include(PG_FUNCTIONS_DIR . '/includes/connect/providers/quickbooks.php');

	pg_assert_same(array('realmId' => '9130354875'), pg_connect_return_params($provider, array('code' => 'c', 'state' => 's', 'realmId' => '9130354875')));
	pg_assert_same(array(), pg_connect_return_params($provider, array('realmId' => '91303<script>')), 'a value off its pattern');
	pg_assert_same(array(), pg_connect_return_params($provider, array('realmId' => array('1'))), 'not a string');
	pg_assert_same(array(), pg_connect_return_params(array('label' => 'Other'), array('realmId' => '1')), 'a provider that names none keeps none');
}

function test_quickbooks_target()
{
	pg_assert_same(array('service' => 'quickbooks', 'company' => '9130354875'), pg_accounting_sync_quickbooks_target(array('realm_id' => '9130354875'), true));
	pg_assert_same(null, pg_accounting_sync_quickbooks_target(array('realm_id' => '9130354875'), false), 'no connection');
	pg_assert_same(null, pg_accounting_sync_quickbooks_target(array('realm_id' => ''), true), 'no company');
	pg_assert_same(40, pg_accounting_sync_pace('quickbooks'));
}

function test_quickbooks_definitions()
{
	$provider = include(PG_FUNCTIONS_DIR . '/includes/connect/providers/quickbooks.php');
	$purpose = include(PG_FUNCTIONS_DIR . '/includes/connect/purposes/quickbooks.php');

	pg_assert_same(false, $provider['identity'], 'one connection, named by the company');
	pg_assert_same(false, $provider['listed'], 'set up on its own page');
	pg_assert_same('basic', $provider['client_auth'], 'HTTP Basic at the token endpoint');
	pg_assert_same(quickbooks_sign_in_documented()['token'], $provider['token_url'], 'the documented address without a stored one');
	pg_assert_same('quickbooks', $provider['connect_purpose']);
	pg_assert_true(strpos(implode(' ', $provider['setup']), 'App Assessment Questionnaire') !== false, 'the setup lines tell of the questionnaire');
	pg_assert_same('site', $purpose['owner'], 'owned by the site');
	pg_assert_same(array('com.intuit.quickbooks.accounting'), $purpose['scopes']['quickbooks']);

	$registry = pg_connections_registry();
	pg_assert_same(array('kind' => 'account', 'provider' => 'quickbooks', 'purpose' => 'quickbooks', 'attention' => true), $registry['quickbooks']['state']);
	pg_assert_same('edocuments', $registry['quickbooks']['group'], 'beside Xero');
	pg_assert_same('US', pg_connections_transfer_fields()['quickbooks'][0]);
}

function test_quickbooks_page_cards()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/fn/connections_screen.php');

	pg_assert_same(array('status', 'details', 'uses'), pg_connections_screen_page_cards('quickbooks', array('manager' => true, 'quickbooks' => true)), 'its details for who may open Accounting');
	pg_assert_same(array('status', 'uses'), pg_connections_screen_page_cards('quickbooks', array('manager' => true, 'quickbooks' => false)), 'not without Accounting');
	pg_assert_true(pg_connections_screen_page_here('quickbooks', array()), 'its settings are on its page');
}
