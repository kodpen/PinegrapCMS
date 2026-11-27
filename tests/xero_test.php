<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure parts of includes/fn/xero.php: the sign-in's event
 * read from the access token, the organisations of GET /connections and the
 * one picked, the chart of accounts and which account fits each role, the
 * labels, Xero's error words and the stored state; and for the provider and
 * purpose definitions and the registry row.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/xero.php');

// An unsigned token carrying the claims, shaped as Xero's access token is.
function xero_test_jwt($claims)
{
	$part = function ($value) {
		return rtrim(strtr(base64_encode(json_encode($value)), '+/', '-_'), '=');
	};

	return $part(array('alg' => 'RS256', 'typ' => 'JWT')) . '.' . $part($claims) . '.c2ln';
}

// A GET /connections answer: two organisations and a practice, the second
// organisation allowed in the sign-in whose event is given.
function xero_test_connections($event)
{
	return array(
		array('id' => 'e1eede29-f875-4a5d-8470-17f6a29a88b1', 'tenantId' => '70784A63-D24B-46A9-A4DB-0E70A274B056', 'authEventId' => 'd99ecdfe-391d-43d2-b834-17636ba90e8d',
			'tenantType' => 'ORGANISATION', 'tenantName' => 'Demo Company (NZ)', 'createdDateUtc' => '2026-11-01T10:00:00.0000000', 'updatedDateUtc' => '2026-11-01T10:00:00.0000000'),
		array('id' => '0bdf4fd8-11a4-4a5c-9b2f-6f1d6a2c1a01', 'tenantId' => 'a3a3b7d4-5d0f-4a8c-8d62-1c0a3f0b2e11', 'authEventId' => $event,
			'tenantType' => 'ORGANISATION', 'tenantName' => '  Corner   Shop <Ltd> ', 'createdDateUtc' => '2026-11-20T10:00:00.0000000', 'updatedDateUtc' => '2026-11-20T10:00:00.0000000'),
		array('id' => '5c5b0cfe-1111-4a5c-9b2f-6f1d6a2c1a02', 'tenantId' => '9b0f3a24-77a1-4e2c-9a51-3a6e4c1d2f33', 'authEventId' => $event,
			'tenantType' => 'PRACTICE', 'tenantName' => 'Practice HQ'),
		array('id' => 'broken', 'tenantId' => 'not-a-uuid', 'tenantType' => 'ORGANISATION', 'tenantName' => 'Broken'),
		'not a connection',
	);
}

// A GET /Accounts answer, shaped as the accounting API sends it.
function xero_test_accounts_answer()
{
	return array('Id' => '7f3e0f2b-5c1d-4d77-9c35-0f2d61a0e001', 'Status' => 'OK', 'Accounts' => array(
		array('AccountID' => 'BD9E85E0-0478-433D-AE9F-0B3C4F04BFE4', 'Code' => '090', 'Name' => 'Business Bank Account', 'Type' => 'BANK', 'Class' => 'ASSET', 'Status' => 'ACTIVE',
			'EnablePaymentsToAccount' => false, 'BankAccountNumber' => '121212'),
		array('AccountID' => '7d05a53d-613d-4eb2-a2fc-dcb6adb80b80', 'Code' => '', 'Name' => 'Card Payouts', 'Type' => 'BANK', 'Class' => 'ASSET', 'Status' => 'ACTIVE'),
		array('AccountID' => 'e0a9b8c6-1d2f-4e3a-9b4c-5d6e7f8a9b01', 'Code' => '200', 'Name' => 'Sales', 'Type' => 'REVENUE', 'Class' => 'REVENUE', 'Status' => 'ACTIVE'),
		array('AccountID' => 'e0a9b8c6-1d2f-4e3a-9b4c-5d6e7f8a9b02', 'Code' => '260', 'Name' => 'Other Revenue', 'Type' => 'OTHERINCOME', 'Class' => 'REVENUE', 'Status' => 'ACTIVE'),
		array('AccountID' => 'e0a9b8c6-1d2f-4e3a-9b4c-5d6e7f8a9b03', 'Code' => '210', 'Name' => 'Old Sales', 'Type' => 'SALES', 'Class' => 'REVENUE', 'Status' => 'ARCHIVED'),
		array('AccountID' => 'e0a9b8c6-1d2f-4e3a-9b4c-5d6e7f8a9b04', 'Code' => '610', 'Name' => 'Accounts Receivable', 'Type' => 'CURRENT', 'Class' => 'ASSET', 'Status' => 'ACTIVE'),
		array('AccountID' => 'e0a9b8c6-1d2f-4e3a-9b4c-5d6e7f8a9b05', 'Code' => '615', 'Name' => 'Stripe Clearing', 'Type' => 'CURRENT', 'Class' => 'ASSET', 'Status' => 'ACTIVE',
			'EnablePaymentsToAccount' => true),
		array('AccountID' => 'nope', 'Code' => '999', 'Name' => 'No id'),
	));
}

function test_xero_uuid_valid()
{
	pg_assert_true(xero_uuid_valid('70784a63-d24b-46a9-a4db-0e70a274b056'), 'lower case');
	pg_assert_true(xero_uuid_valid('70784A63-D24B-46A9-A4DB-0E70A274B056'), 'upper case');
	pg_assert_false(xero_uuid_valid('70784a63d24b46a9a4db0e70a274b056'), 'no dashes');
	pg_assert_false(xero_uuid_valid("70784a63-d24b-46a9-a4db-0e70a274b056\n"), 'a trailing line break');
	pg_assert_false(xero_uuid_valid(array()), 'not a string');
}

function test_xero_token_auth_event()
{
	pg_assert_same('d99ecdfe-391d-43d2-b834-17636ba90e8d',
		xero_token_auth_event(xero_test_jwt(array('xero_userid' => 'x', 'authentication_event_id' => 'D99ECDFE-391D-43D2-B834-17636BA90E8D'))), 'read, lower case');
	pg_assert_same('', xero_token_auth_event(xero_test_jwt(array('sub' => 'x'))), 'none named');
	pg_assert_same('', xero_token_auth_event(xero_test_jwt(array('authentication_event_id' => 'abc'))), 'not an identifier');
	pg_assert_same('', xero_token_auth_event('opaque-token'), 'not a token');
}

function test_xero_tenants_from_api()
{
	$event = 'f1e2d3c4-b5a6-4978-8a9b-0c1d2e3f4a5b';
	$tenants = xero_tenants_from_api(xero_test_connections($event), $event);

	pg_assert_same(array('70784a63-d24b-46a9-a4db-0e70a274b056', 'a3a3b7d4-5d0f-4a8c-8d62-1c0a3f0b2e11'), array_keys($tenants), 'organisations only, by lower-case id');
	pg_assert_same(array('name' => 'Demo Company (NZ)', 'fresh' => false), $tenants['70784a63-d24b-46a9-a4db-0e70a274b056'], 'allowed before');
	pg_assert_same(array('name' => 'Corner Shop', 'fresh' => true), $tenants['a3a3b7d4-5d0f-4a8c-8d62-1c0a3f0b2e11'], 'allowed now, the name on one line without tags');

	$unknown = xero_tenants_from_api(xero_test_connections($event), '');
	pg_assert_false($unknown['a3a3b7d4-5d0f-4a8c-8d62-1c0a3f0b2e11']['fresh'], 'no event known: none is fresh');

	pg_assert_same(array(), xero_tenants_from_api(null, $event), 'no answer');
	pg_assert_same(array(), xero_tenants_from_api(array('Status' => 401), $event), 'an error answer');
}

function test_xero_tenant_pick()
{
	$two = array(
		'70784a63-d24b-46a9-a4db-0e70a274b056' => array('name' => 'Demo', 'fresh' => false),
		'a3a3b7d4-5d0f-4a8c-8d62-1c0a3f0b2e11' => array('name' => 'Shop', 'fresh' => true),
	);

	pg_assert_same('70784a63-d24b-46a9-a4db-0e70a274b056', xero_tenant_pick($two, '70784a63-d24b-46a9-a4db-0e70a274b056'), 'the one chosen before stays');
	pg_assert_same('a3a3b7d4-5d0f-4a8c-8d62-1c0a3f0b2e11', xero_tenant_pick($two, ''), 'else the one allowed now');
	pg_assert_same('a3a3b7d4-5d0f-4a8c-8d62-1c0a3f0b2e11', xero_tenant_pick($two, '9b0f3a24-77a1-4e2c-9a51-3a6e4c1d2f33'), 'one chosen before and gone');

	$two['70784a63-d24b-46a9-a4db-0e70a274b056']['fresh'] = true;
	pg_assert_same('', xero_tenant_pick($two, ''), 'two allowed now: the page asks');

	$two['70784a63-d24b-46a9-a4db-0e70a274b056']['fresh'] = false;
	$two['a3a3b7d4-5d0f-4a8c-8d62-1c0a3f0b2e11']['fresh'] = false;
	pg_assert_same('', xero_tenant_pick($two, ''), 'none allowed now among two: the page asks');

	pg_assert_same('70784a63-d24b-46a9-a4db-0e70a274b056', xero_tenant_pick(array('70784a63-d24b-46a9-a4db-0e70a274b056' => array('name' => 'Demo', 'fresh' => false)), ''), 'the only one');
	pg_assert_same('', xero_tenant_pick(array(), '70784a63-d24b-46a9-a4db-0e70a274b056'), 'none');
}

function test_xero_accounts_from_api()
{
	$accounts = xero_accounts_from_api(xero_test_accounts_answer());

	pg_assert_same(7, count($accounts), 'the one without an identifier is left out');
	pg_assert_same(array(
		'id' => 'bd9e85e0-0478-433d-ae9f-0b3c4f04bfe4', 'code' => '090', 'name' => 'Business Bank Account',
		'type' => 'BANK', 'class' => 'ASSET', 'status' => 'ACTIVE', 'payments' => false,
	), $accounts['bd9e85e0-0478-433d-ae9f-0b3c4f04bfe4'], 'typed, by lower-case id');
	pg_assert_true($accounts['e0a9b8c6-1d2f-4e3a-9b4c-5d6e7f8a9b05']['payments'], 'takes payments');
	pg_assert_same(array(), xero_accounts_from_api(array('Title' => 'Unauthorized')), 'an error answer');
}

function test_xero_account_fits()
{
	$accounts = xero_accounts_from_api(xero_test_accounts_answer());

	pg_assert_same(array('e0a9b8c6-1d2f-4e3a-9b4c-5d6e7f8a9b01', 'e0a9b8c6-1d2f-4e3a-9b4c-5d6e7f8a9b02'),
		array_keys(xero_accounts_for_role($accounts, 'income')), 'active revenue accounts, by label; the archived one is left out');
	pg_assert_same(array('bd9e85e0-0478-433d-ae9f-0b3c4f04bfe4', 'e0a9b8c6-1d2f-4e3a-9b4c-5d6e7f8a9b05', '7d05a53d-613d-4eb2-a2fc-dcb6adb80b80'),
		array_keys(xero_accounts_for_role($accounts, 'clearing')), 'bank accounts and one that takes payments');
	pg_assert_same(array('bd9e85e0-0478-433d-ae9f-0b3c4f04bfe4', '7d05a53d-613d-4eb2-a2fc-dcb6adb80b80'),
		array_keys(xero_accounts_for_role($accounts, 'bank')), 'bank accounts only');
	pg_assert_same(array(), xero_accounts_for_role($accounts, 'other'), 'no such role');
	pg_assert_false(xero_account_fits('income', null), 'no account');
}

function test_xero_account_label()
{
	pg_assert_same('200 — Sales', xero_account_label(array('id' => 'x', 'code' => '200', 'name' => 'Sales')), 'code and name');
	pg_assert_same('Card Payouts', xero_account_label(array('id' => 'x', 'code' => '', 'name' => 'Card Payouts')), 'a bank account without a code');
	pg_assert_same('e0a9b8c6-1d2f-4e3a-9b4c-5d6e7f8a9b01', xero_account_label(array('id' => 'e0a9b8c6-1d2f-4e3a-9b4c-5d6e7f8a9b01', 'code' => '', 'name' => '')), 'nothing but the id');
}

// A GET /TaxRates answer: two sales rates, a purchase rate, an archived
// rate and one without a tax type.
function xero_test_tax_rates_answer()
{
	return array('TaxRates' => array(
		array('Name' => '20% (VAT on Income)', 'TaxType' => 'OUTPUT2', 'CanApplyToRevenue' => true, 'DisplayTaxRate' => 20.0, 'EffectiveRate' => 20.0, 'Status' => 'ACTIVE'),
		array('Name' => 'No VAT', 'TaxType' => 'NONE', 'CanApplyToRevenue' => true, 'DisplayTaxRate' => 0.0, 'EffectiveRate' => 0.0, 'Status' => 'ACTIVE'),
		array('Name' => '20% (VAT on Expenses)', 'TaxType' => 'INPUT2', 'CanApplyToRevenue' => false, 'DisplayTaxRate' => 20.0, 'EffectiveRate' => 20.0, 'Status' => 'ACTIVE'),
		array('Name' => 'Old rate', 'TaxType' => 'TAX001', 'CanApplyToRevenue' => true, 'DisplayTaxRate' => 17.5, 'EffectiveRate' => 17.5, 'Status' => 'ARCHIVED'),
		array('Name' => 'Broken', 'CanApplyToRevenue' => true, 'Status' => 'ACTIVE'),
	));
}

function test_xero_tax_rates_from_api()
{
	$rates = xero_tax_rates_from_api(xero_test_tax_rates_answer());

	pg_assert_same(array('OUTPUT2', 'NONE', 'INPUT2', 'TAX001'), array_keys($rates), 'the one without a tax type is left out');
	pg_assert_same(array('type' => 'OUTPUT2', 'name' => '20% (VAT on Income)', 'rate' => 20.0, 'status' => 'ACTIVE', 'revenue' => true), $rates['OUTPUT2'], 'typed, by tax type');
	pg_assert_false($rates['INPUT2']['revenue'], 'a purchase rate');
	pg_assert_same(array(), xero_tax_rates_from_api(array('Title' => 'Unauthorized')), 'an error answer');
	pg_assert_same(array(), xero_tax_rates_from_api(array('TaxRates' => array(array('TaxType' => 'BAD TYPE', 'Status' => 'ACTIVE')))), 'a tax type with a space');
}

function test_xero_tax_rates_for_sales()
{
	$rates = xero_tax_rates_from_api(xero_test_tax_rates_answer());
	$offered = xero_tax_rates_for_sales($rates);

	pg_assert_same(array('OUTPUT2', 'NONE'), array_keys($offered), 'active sales rates only, by label');
	pg_assert_same('20% (VAT on Income) — 20%', $offered['OUTPUT2'], 'name and rate');
	pg_assert_same('Old rate — 17.5%', xero_tax_rate_label($rates['TAX001']), 'a rate with decimals');
	pg_assert_same('X — 0%', xero_tax_rate_label(array('type' => 'X', 'name' => '', 'rate' => 0.0)), 'no name: the tax type');
	pg_assert_same(array(), xero_tax_rates_for_sales('none'), 'nothing read');
}

function test_xero_error_message()
{
	pg_assert_same('HTTP 400: A validation exception occurred Account code \'999\' is not a valid code. Contact name is required.', xero_error_message(400, array(
		'ErrorNumber' => 10, 'Type' => 'ValidationException', 'Message' => 'A validation exception occurred',
		'Elements' => array(array('ValidationErrors' => array(
			array('Message' => 'Account code \'999\' is not a valid code.'),
			array('Message' => 'Contact name is required.'),
			array('Message' => 'Contact name is required.'),
		))),
	), 'HTTP 400'), 'the accounting API\'s words, each once');
	pg_assert_same('HTTP 403: AuthorizationUnsuccessful', xero_error_message(403, array('Type' => null, 'Title' => 'Forbidden', 'Detail' => 'AuthorizationUnsuccessful', 'Status' => 403), ''), 'problem details');
	pg_assert_same('Could not resolve host', xero_error_message(0, null, 'Could not resolve host'), 'the transport\'s words');
	pg_assert_same('HTTP 503', xero_error_message(503, null, ''), 'nothing said');
	pg_assert_same(250, mb_strlen(xero_error_message(400, array('Message' => str_repeat('a', 400)), '')), 'clipped');
}

function test_xero_state_parse()
{
	$empty = xero_state_parse(null);

	pg_assert_same(array(
		'connection_id' => 0, 'tenant_id' => '', 'tenant_name' => '', 'tenants' => array(),
		'accounts' => array('income' => array('id' => '', 'code' => '', 'name' => ''), 'clearing' => array('id' => '', 'code' => '', 'name' => ''), 'bank' => array('id' => '', 'code' => '', 'name' => '')),
		'connected_at' => 0,
	), $empty, 'nothing stored');

	$state = xero_state_parse(array(
		'connection_id' => '12',
		'tenant_id'     => 'A3A3B7D4-5D0F-4A8C-8D62-1C0A3F0B2E11',
		'tenant_name'   => 'Corner Shop',
		'tenants'       => array('A3A3B7D4-5D0F-4A8C-8D62-1C0A3F0B2E11' => 'Corner Shop', 'bad' => 'Bad', '70784a63-d24b-46a9-a4db-0e70a274b056' => array('x')),
		'accounts'      => array(
			'income'   => array('id' => 'E0A9B8C6-1D2F-4E3A-9B4C-5D6E7F8A9B01', 'code' => '200', 'name' => 'Sales', 'type' => 'REVENUE'),
			'clearing' => array('id' => 'not-an-id', 'code' => '615', 'name' => 'Stripe Clearing'),
			'other'    => array('id' => 'e0a9b8c6-1d2f-4e3a-9b4c-5d6e7f8a9b05'),
		),
		'connected_at'  => -5,
	));

	pg_assert_same(12, $state['connection_id'], 'an integer');
	pg_assert_same(0, $state['connected_at'], 'never below zero');
	pg_assert_same('a3a3b7d4-5d0f-4a8c-8d62-1c0a3f0b2e11', $state['tenant_id'], 'lower case');
	pg_assert_same(array('a3a3b7d4-5d0f-4a8c-8d62-1c0a3f0b2e11' => 'Corner Shop'), $state['tenants'], 'only identifiers with a name');
	pg_assert_same(array('id' => 'e0a9b8c6-1d2f-4e3a-9b4c-5d6e7f8a9b01', 'code' => '200', 'name' => 'Sales'), $state['accounts']['income'], 'id, code and name kept');
	pg_assert_same(array('id' => '', 'code' => '', 'name' => ''), $state['accounts']['clearing'], 'not an identifier: none chosen');
	pg_assert_same(array('income', 'clearing', 'bank'), array_keys($state['accounts']), 'the three roles only');

	$no_tenant = xero_state_parse(array('tenant_id' => '', 'tenant_name' => 'Old', 'accounts' => array('income' => array('id' => 'e0a9b8c6-1d2f-4e3a-9b4c-5d6e7f8a9b01'))));
	pg_assert_same('', $no_tenant['tenant_name'], 'no organisation, no name');
	pg_assert_same('', $no_tenant['accounts']['income']['id'], 'no organisation, no account');

	pg_assert_same($state, xero_state_parse($state), 'a parsed state parses to itself');
}

function test_xero_definitions()
{
	$provider = include(PG_FUNCTIONS_DIR . '/includes/connect/providers/xero.php');
	$purpose = include(PG_FUNCTIONS_DIR . '/includes/connect/purposes/xero.php');

	pg_assert_same(false, $provider['identity'], 'the account is not named by the identity token');
	pg_assert_same(false, $provider['listed'], 'set up on its own page');
	pg_assert_same('basic', $provider['client_auth'], 'HTTP Basic at the token endpoint');
	pg_assert_true(in_array('offline_access', $provider['identity_scopes'], true), 'a refresh token is asked');
	pg_assert_same('xero', $provider['connect_purpose'], 'connected for its purpose');
	pg_assert_same('site', $purpose['owner'], 'owned by the site');
	pg_assert_same(array('accounting.settings', 'accounting.contacts', 'accounting.invoices', 'accounting.payments'), $purpose['scopes']['xero'], 'granular scopes');

	$registry = pg_connections_registry();
	pg_assert_same(array('kind' => 'account', 'provider' => 'xero', 'purpose' => 'xero', 'attention' => true), $registry['xero']['state'], 'read from the site\'s account');
	pg_assert_same('edocuments', $registry['xero']['group'], 'beside Paraşüt');
	pg_assert_same(array('NZ', array('identity', 'contact', 'address', 'order_payment')), pg_connections_transfer_fields()['xero'], 'what it will receive');
}

function test_xero_page_cards()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/fn/connections_screen.php');

	pg_assert_same(array('status', 'details', 'uses'), pg_connections_screen_page_cards('xero', array('manager' => true, 'xero' => true)), 'its details for who may open Accounting');
	pg_assert_same(array('status', 'uses'), pg_connections_screen_page_cards('xero', array('manager' => true, 'xero' => false)), 'not without Accounting');
	pg_assert_true(pg_connections_screen_page_here('xero', array()), 'its settings are on its page');
}
