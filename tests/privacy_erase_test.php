<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for erasing a person (includes/fn/privacy.php): which tables lose
 * the person's rows and which keep them with the personal columns replaced,
 * how the rows are found, the SET list, the token an address becomes and the
 * audit line.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/source_reader.php');

// escape() asks the connection class; without a database it falls back to
// addslashes() once the class exists.
function privacy_erase_test_db_stub()
{
	if (!class_exists('db')) {
		class db
		{
			public static $con;
		}
	}
}

// A scope with something in every list, as pg_privacy_erase_scope() builds it.
function privacy_erase_test_scope()
{
	return array(
		'user_ids'         => array(7),
		'contact_ids'      => array(12),
		'emails'           => array('ali@example.com'),
		'tokens'           => array(str_repeat('a', 64)),
		'phones'           => array('905321234567'),
		'order_ids'        => array(30, 31),
		'form_ids'         => array(40),
		'account_ids'      => array(50),
		'conversation_ids' => array(60),
		'file_ids'         => array(70),
	);
}

// The field list is what an erasure promises the person and the operator: a
// table or a column added to it or taken from it changes what is kept, so it
// is pinned here and changed together with this test.
function test_privacy_erase_tables_are_pinned()
{
	$actions = array();
	$fields = array();

	foreach (pg_privacy_erase_tables() as $table => $spec) {
		$actions[$table] = $spec['action'];

		if ($spec['fields']) {
			$fields[$table] = $spec['fields'];
		}
	}

	pg_assert_same(
		array(
			'orders' => 'anonymise', 'ship_tos' => 'anonymise', 'order_documents' => 'anonymise',
			'order_returns' => 'anonymise', 'gift_cards' => 'anonymise', 'erp_accounts' => 'anonymise',
			'erp_invoices' => 'anonymise', 'erp_quotes' => 'anonymise', 'erp_waybills' => 'anonymise',
			'comments' => 'anonymise', 'consent_log' => 'anonymise', 'ws_approval_guests' => 'anonymise',
			'files' => 'delete', 'form_data' => 'delete', 'form_signatures' => 'delete', 'forms' => 'delete',
			'watchers' => 'delete', 'wishlist_items' => 'delete', 'stock_notify' => 'delete',
			'email_recipients' => 'delete', 'mail_outbox' => 'delete', 'sms_outbox' => 'delete',
			'chat_messages' => 'delete', 'chat_conversations' => 'delete', 'address_book' => 'delete',
			'push_subscriptions' => 'delete', 'user_passkeys' => 'delete', 'user_identities' => 'delete',
			'user_mfa_recovery' => 'delete', 'user_mfa' => 'delete', 'auth_tokens' => 'delete',
			'opt_in' => 'delete', 'contacts_contact_groups_xref' => 'delete', 'contacts' => 'delete',
			'user' => 'delete',
		),
		$actions,
		'tables, actions and order'
	);

	pg_assert_same(
		array(
			'orders' => array(
				'billing_salutation' => '', 'billing_first_name' => '{name}', 'billing_last_name' => '',
				'billing_company' => '', 'billing_address_1' => '', 'billing_address_2' => '',
				'billing_city' => '', 'billing_state' => '', 'billing_zip_code' => '',
				'billing_phone_number' => '', 'billing_fax_number' => '', 'billing_email_address' => '{token}',
				'cardholder' => '', 'card_number' => '', 'expiration_month' => '', 'expiration_year' => '',
				'card_verification_number' => '', 'custom_field_1' => '', 'custom_field_2' => '',
				'http_referer' => '', 'ip_address' => 0, 'user_id' => 0, 'contact_id' => 0,
			),
			'ship_tos' => array(
				'ship_to_name' => '', 'salutation' => '', 'first_name' => '{name}', 'last_name' => '',
				'company' => '', 'address_1' => '', 'address_2' => '', 'city' => '', 'state' => '',
				'zip_code' => '', 'phone_number' => '',
			),
			'order_documents' => array('ip' => '', 'user_agent' => ''),
			'order_returns' => array('customer_note' => '', 'requested_by' => 0),
			'gift_cards' => array('from_name' => '', 'message' => '', 'recipient_email_address' => ''),
			'erp_accounts' => array(
				'title' => '{name}', 'tax_number' => '', 'tax_office' => '', 'email' => '{token}',
				'invoice_email' => '', 'phone' => '', 'address' => '', 'district' => '', 'city' => '',
				'state' => '', 'postcode' => '', 'einvoice_alias' => '', 'einvoice_aliases' => '',
				'notes' => '', 'contact_id' => 0,
			),
			'erp_invoices' => array(
				'account_title' => '{name}', 'account_tax_number' => '', 'account_tax_office' => '',
				'account_address' => '', 'account_district' => '', 'account_city' => '', 'account_state' => '',
				'account_postcode' => '', 'account_email' => '{token}',
			),
			'erp_quotes' => array('form_data' => ''),
			'erp_waybills' => array(
				'ship_to_title' => '{name}', 'ship_to_address' => '', 'ship_to_district' => '',
				'ship_to_city' => '', 'ship_to_state' => '',
			),
			'comments' => array('name' => '', 'created_user_id' => 0),
			'consent_log' => array('user_id' => 0, 'contact_id' => 0, 'ip' => '', 'user_agent' => ''),
			'ws_approval_guests' => array('name' => '', 'email' => '{token}', 'decided_ip' => '', 'decided_agent' => ''),
		),
		$fields,
		'replaced columns'
	);
}

// How each table's rows are found; a find column changed is a different set
// of rows deleted or rewritten.
function test_privacy_erase_find_columns_are_pinned()
{
	$find = array();

	foreach (pg_privacy_erase_tables() as $table => $spec) {
		$find[$table] = $spec['find'];
	}

	$user = array('user_id' => 'user_ids');

	pg_assert_same(
		array(
			'orders' => array('id' => 'order_ids'),
			'ship_tos' => array('order_id' => 'order_ids'),
			'order_documents' => array('order_id' => 'order_ids'),
			'order_returns' => array('order_id' => 'order_ids'),
			'gift_cards' => array('order_id' => 'order_ids', 'recipient_email_address' => 'emails'),
			'erp_accounts' => array('id' => 'account_ids'),
			'erp_invoices' => array('account_id' => 'account_ids'),
			'erp_quotes' => array('account_id' => 'account_ids'),
			'erp_waybills' => array('account_id' => 'account_ids'),
			'comments' => array('created_user_id' => 'user_ids'),
			'consent_log' => array('person_token' => 'tokens', 'user_id' => 'user_ids', 'contact_id' => 'contact_ids'),
			'ws_approval_guests' => array('email' => 'emails'),
			'files' => array('id' => 'file_ids'),
			'form_data' => array('form_id' => 'form_ids', 'order_id' => 'order_ids'),
			'form_signatures' => array('form_id' => 'form_ids', 'user_id' => 'user_ids'),
			'forms' => array('id' => 'form_ids'),
			'watchers' => array('user_id' => 'user_ids', 'email_address' => 'emails'),
			'wishlist_items' => $user,
			'stock_notify' => array('user_id' => 'user_ids', 'email_address' => 'emails'),
			'email_recipients' => array('contact_id' => 'contact_ids', 'email_address' => 'emails'),
			'mail_outbox' => array('recipient' => 'emails'),
			'sms_outbox' => array('recipient' => 'phones'),
			'chat_messages' => array('conversation_id' => 'conversation_ids'),
			'chat_conversations' => array('id' => 'conversation_ids'),
			'address_book' => array('user' => 'user_ids'),
			'push_subscriptions' => $user,
			'user_passkeys' => $user,
			'user_identities' => $user,
			'user_mfa_recovery' => $user,
			'user_mfa' => $user,
			'auth_tokens' => $user,
			'opt_in' => array('contact_id' => 'contact_ids'),
			'contacts_contact_groups_xref' => array('contact_id' => 'contact_ids'),
			'contacts' => array('id' => 'contact_ids'),
			'user' => $user,
		),
		$find,
		'find columns'
	);
}

// What the runner and the writer rely on: every scope list named exists, an
// anonymised table replaces something and a deleted one nothing, one e-mail
// column at most per table (written one address at a time), and every table
// has a name on the screen.
function test_privacy_erase_tables_are_consistent()
{
	$scope_keys = array_keys(privacy_erase_test_scope());
	$labels = pg_privacy_erase_labels();

	foreach (pg_privacy_erase_tables() as $table => $spec) {
		pg_assert_true(in_array($spec['action'], array('delete', 'anonymise'), true), $table . ' action');
		pg_assert_same(($spec['action'] === 'anonymise'), (bool) $spec['fields'], $table . ' fields match the action');
		pg_assert_true((bool) $spec['find'], $table . ' is found by something');
		pg_assert_same(array(), array_diff(array_values($spec['find']), $scope_keys), $table . ' scope keys');
		pg_assert_true(count(array_keys($spec['fields'], '{token}', true)) <= 1, $table . ' one token column at most');
		pg_assert_true(isset($labels[$table]) && is_string($labels[$table]) && ($labels[$table] !== ''), $table . ' label');
	}

	pg_assert_same(array_keys(pg_privacy_erase_tables()), array_keys($labels), 'a label for each table, in order');

	// The account and the contact go last, so an erasure stopped half-way
	// still finds the person when it is run again.
	$order = array_keys(pg_privacy_erase_tables());
	pg_assert_same(array('contacts', 'user'), array_slice($order, -2), 'contact and account last');
}

// Nothing to match is no condition at all, never one that matches every row.
function test_privacy_erase_where_with_an_empty_scope_is_empty()
{
	$empty = array_fill_keys(array_keys(privacy_erase_test_scope()), array());

	foreach (pg_privacy_erase_tables() as $table => $spec) {
		pg_assert_same('', pg_privacy_erase_where($spec['find'], $empty), $table);
	}

	pg_assert_same('', pg_privacy_erase_where(array('user_id' => 'user_ids'), array()), 'no scope at all');
	pg_assert_same('', pg_privacy_erase_where(array(), privacy_erase_test_scope()), 'no find column');
}

function test_privacy_erase_where_joins_the_find_columns()
{
	privacy_erase_test_db_stub();

	$scope = privacy_erase_test_scope();

	pg_assert_same('(`id` IN (30, 31))', pg_privacy_erase_where(array('id' => 'order_ids'), $scope), 'ids');
	pg_assert_same(
		"(`user_id` IN (7) OR `email_address` IN ('ali@example.com'))",
		pg_privacy_erase_where(array('user_id' => 'user_ids', 'email_address' => 'emails'), $scope),
		'id or address'
	);

	$scope['user_ids'] = array();
	pg_assert_same(
		"(`email_address` IN ('ali@example.com'))",
		pg_privacy_erase_where(array('user_id' => 'user_ids', 'email_address' => 'emails'), $scope),
		'an empty list is left out'
	);

	$scope['emails'] = array("o'neil@example.com");
	pg_assert_contains("o\\'neil@example.com", pg_privacy_erase_where(array('email_address' => 'emails'), $scope), 'an address is escaped');
}

// An address becomes its person token; a token stays, so a second run keeps
// it; anything else becomes empty.
function test_privacy_erase_token()
{
	$token = pg_privacy_person_token('ali@example.com', 'k1');

	pg_assert_same($token, pg_privacy_erase_token('Ali@Example.com', 'k1'), 'an address');
	pg_assert_same($token, pg_privacy_erase_token($token, 'k2'), 'a token stays');
	pg_assert_same('', pg_privacy_erase_token('', 'k1'), 'empty');
	pg_assert_same('', pg_privacy_erase_token(null, 'k1'), 'null');
	pg_assert_same('', pg_privacy_erase_token('not an address', 'k1'), 'not an address');
	pg_assert_same('', pg_privacy_erase_token('ali@example.com', ''), 'no key');
}

function test_privacy_erase_set()
{
	privacy_erase_test_db_stub();

	pg_assert_same(
		"`billing_first_name` = 'Deleted customer', `billing_last_name` = '', `billing_email_address` = 'abc', `user_id` = 0",
		pg_privacy_erase_set(array('billing_first_name' => '{name}', 'billing_last_name' => '', 'billing_email_address' => '{token}', 'user_id' => 0), 'Deleted customer', 'abc'),
		'markers, texts and numbers'
	);

	pg_assert_same("`name` = 'Silinmiş m\\'üşteri'", pg_privacy_erase_set(array('name' => '{name}'), "Silinmiş m'üşteri", ''), 'the name is escaped');
	pg_assert_same("`email` = ''", pg_privacy_erase_set(array('email' => '{token}'), 'x', ''), 'no token');
}

// The audit line names the person by token and ids only, and its counts are
// not taken for secrets (auth_tokens, user_passkeys).
function test_privacy_erase_audit_entry()
{
	$tables = array(
		'orders'      => array('action' => 'anonymise', 'rows' => 2),
		'auth_tokens' => array('action' => 'delete', 'rows' => 3),
		'user'        => array('action' => 'delete', 'rows' => 1),
	);

	$entry = pg_privacy_erase_audit_entry(privacy_erase_test_scope(), $tables);

	pg_assert_same(
		array(
			'person'      => str_repeat('a', 64),
			'user_ids'    => '7',
			'contact_ids' => '12',
			'deleted'     => array('auth_tokens' => 3, 'user' => 1),
			'anonymised'  => array('orders' => 2),
		),
		$entry,
		'entry'
	);

	pg_assert_same($entry, pg_audit_redact($entry), 'nothing masked');
	pg_assert_false(strpos(json_encode($entry), '@') !== false, 'no address');
}

// One audit line for the whole erasure: a line per row would keep in its
// before side what was just erased.
function test_privacy_erase_writes_no_line_per_row()
{
	$source = '';

	foreach (get_defined_functions()['user'] as $function) {
		if ((strpos($function, 'pg_privacy_erase') === 0) || (strpos($function, '_pg_privacy_erase') === 0)) {
			$source .= pg_test_function_source($function);
		}
	}

	pg_assert_contains('function pg_privacy_erase_run(', $source, 'the runner is read');
	pg_assert_false(strpos($source, 'pg_audit_record') !== false, 'no pg_audit_record()');
	pg_assert_false(strpos($source, 'pg_audit_fetch') !== false, 'no pg_audit_fetch()');
	pg_assert_false(strpos($source, 'pg_delete_file_record') !== false, 'files without their audit line');
	pg_assert_same(1, substr_count($source, 'pg_audit('), 'one pg_audit() call');
}
