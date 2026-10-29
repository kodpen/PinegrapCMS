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
		'return_ids'       => array(80),
		'audit_ids'        => array(90),
		'cell_ids'         => array(95),
		'revision_form_ids' => array(40, 96),
		'wtable_row_ids'   => array(97),
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
			'ship_tos' => 'anonymise', 'order_documents' => 'anonymise',
			'order_returns' => 'anonymise', 'gift_cards' => 'anonymise', 'erp_accounts' => 'anonymise',
			'erp_invoices' => 'anonymise', 'erp_quotes' => 'anonymise', 'erp_waybills' => 'anonymise',
			'comments' => 'anonymise', 'consent_log' => 'anonymise', 'ws_approval_guests' => 'anonymise',
			'audit_log' => 'anonymise', 'record_revisions' => 'delete', 'files' => 'delete', 'form_data_cells' => 'anonymise',
			'form_data' => 'delete', 'form_signatures' => 'delete', 'forms' => 'delete',
			'watchers' => 'delete', 'wishlist_items' => 'delete', 'stock_notify' => 'delete',
			'email_recipients' => 'delete', 'mail_outbox' => 'delete', 'sms_outbox' => 'delete',
			'chat_messages' => 'delete', 'chat_conversations' => 'delete', 'address_book' => 'delete',
			'push_subscriptions' => 'delete', 'user_passkeys' => 'delete', 'user_identities' => 'delete',
			'user_mfa_recovery' => 'delete', 'user_mfa' => 'delete', 'auth_tokens' => 'delete',
			'opt_in' => 'delete', 'contacts_contact_groups_xref' => 'delete', 'ws_wtable_cells' => 'anonymise', 'orders' => 'anonymise', 'contacts' => 'delete',
			'user' => 'delete',
		),
		$actions,
		'tables, actions and order'
	);

	$expected = array(
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
			'audit_log' => array('label' => ''),
			'form_data_cells' => array('data' => ''),
			'ws_wtable_cells' => array('cells' => ''),
	);

	ksort($expected);
	ksort($fields);
	pg_assert_same($expected, $fields, 'replaced columns');
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

	$expected = array(
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
			'audit_log' => array('id' => 'audit_ids'),
			'record_revisions' => array('form_id' => 'revision_form_ids'),
			'files' => array('id' => 'file_ids'),
			'form_data_cells' => array('id' => 'cell_ids'),
			'form_data' => array('form_id' => 'form_ids', 'order_id' => 'order_ids'),
			'form_signatures' => array('form_id' => 'form_ids'),
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
			'ws_wtable_cells' => array('id' => 'wtable_row_ids'),
			'contacts' => array('id' => 'contact_ids'),
			'user' => $user,
	);

	ksort($expected);
	ksort($find);
	pg_assert_same($expected, $find, 'find columns');
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
	// still finds the person when it is run again; orders just before them,
	// since the person's other records are found through the orders.
	$order = array_keys(pg_privacy_erase_tables());
	pg_assert_same(array('orders', 'contacts', 'user'), array_slice($order, -3), 'orders, contact and account last');

	foreach (pg_privacy_erase_tables() as $key => $spec) {
		pg_assert_true(!isset($spec['table']) || (is_string($spec['table']) && ($spec['table'] !== $key)), $key . ' table');
	}
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

// What is replaced in the trail's lines about the person: the field list's
// columns inside the JSON, by record type.
function test_privacy_erase_audit_fields_are_pinned()
{
	$tables = pg_privacy_erase_tables();

	pg_assert_same(
		array(
			'user'         => array('user_username' => '{name}', 'user_email' => '{token}', 'user_password_hint' => '', 'user_google_id' => ''),
			'order'        => $tables['orders']['fields'],
			'order_return' => $tables['order_returns']['fields'],
			'contact'      => pg_privacy_erase_audit_fields()['contact'],
			'person'       => array(),
		),
		pg_privacy_erase_audit_fields(),
		'audit fields'
	);
}

// A line keeps who did what and when, and its other columns; the person's
// columns are replaced, a user's or an export's label is cleared, an order
// keeps its number, and a second run changes nothing.
function test_privacy_erase_audit_row()
{
	$token = pg_privacy_person_token('ali@example.com', 'k1');

	$user = array(
		'entity_type' => 'user',
		'label'       => 'ali',
		'before_json' => '{"user_username":"ali","user_email":"Ali@example.com","user_role":"3","user_password_hint":"***"}',
		'after_json'  => null,
	);

	$changes = pg_privacy_erase_audit_row($user, 'Deleted customer', 'k1');

	pg_assert_same(array('before_json', 'label'), array_keys($changes), 'user: before side and label');
	pg_assert_same(
		array('user_username' => 'Deleted customer', 'user_email' => $token, 'user_role' => '3', 'user_password_hint' => ''),
		json_decode($changes['before_json'], true),
		'user: replaced columns, the role kept'
	);
	pg_assert_same('', $changes['label'], 'user: label cleared');

	$user = array_merge($user, $changes);
	pg_assert_same(array(), pg_privacy_erase_audit_row($user, 'Deleted customer', 'k1'), 'user: second run');

	$order = array(
		'entity_type' => 'order',
		'label'       => '1001',
		'before_json' => '{"status":"complete","billing_first_name":"Ali","billing_email_address":"ali@example.com","card_number":"***","total":"12345"}',
		'after_json'  => '{"status":"exported"}',
	);

	$changes = pg_privacy_erase_audit_row($order, 'Deleted customer', 'k1');

	pg_assert_same(array('before_json'), array_keys($changes), 'order: only the side that names the person, the number kept');
	pg_assert_same(
		array('status' => 'complete', 'billing_first_name' => 'Deleted customer', 'billing_email_address' => $token, 'card_number' => '', 'total' => '12345'),
		json_decode($changes['before_json'], true),
		'order: replaced columns'
	);

	$long = array('truncated' => true, 'length' => 3000, 'sha1' => 'x', 'start' => 'Ali ...');
	$return = array('entity_type' => 'order_return', 'label' => '1001', 'before_json' => null, 'after_json' => json_encode(array('status' => 'requested', 'customer_note' => $long)));
	pg_assert_same(
		array('status' => 'requested', 'customer_note' => ''),
		json_decode(pg_privacy_erase_audit_row($return, 'x', 'k1')['after_json'], true),
		'return: a compacted note is replaced whole'
	);

	$export = array('entity_type' => 'person', 'label' => 'ali@example.com', 'before_json' => null, 'after_json' => '{"format":"json","person":"' . $token . '"}');
	pg_assert_same(array('label' => ''), pg_privacy_erase_audit_row($export, 'x', 'k1'), 'export: label cleared, token kept');

	$contact = array(
		'entity_type' => 'contact',
		'label'       => 'ali@example.com',
		'before_json' => '{"first_name":"Ali","last_name":"Veli","email_address":"ali@example.com","mobile_phone":"555","opt_in":"1","lead_source":"Web"}',
		'after_json'  => '{"opt_in":"0"}',
	);

	$changes = pg_privacy_erase_audit_row($contact, 'Deleted customer', 'k1');

	pg_assert_same(array('before_json', 'label'), array_keys($changes), 'contact: the side that names the person and the label');
	pg_assert_same(
		array('first_name' => 'Deleted customer', 'last_name' => '', 'email_address' => $token, 'mobile_phone' => '', 'opt_in' => '1', 'lead_source' => 'Web'),
		json_decode($changes['before_json'], true),
		'contact: the token in place of the address, opt-in kept'
	);
	pg_assert_same('', $changes['label'], 'contact: label cleared');
	pg_assert_same(array(), pg_privacy_erase_audit_row(array_merge($contact, $changes), 'Deleted customer', 'k1'), 'contact: second run');

	foreach (array('first_name', 'last_name', 'company', 'email_address', 'mobile_phone', 'home_address_1', 'business_address_1', 'tax_number', 'description', 'image') as $column) {
		pg_assert_true(isset(pg_privacy_erase_audit_fields()['contact'][$column]), 'contact: ' . $column . ' is replaced');
	}

	pg_assert_same(array(), pg_privacy_erase_audit_row(array('entity_type' => 'product', 'label' => 'Shoe', 'before_json' => '{"name":"Shoe"}'), 'x', 'k1'), 'another type');
	pg_assert_same(array(), pg_privacy_erase_audit_row(array('entity_type' => 'order', 'label' => '1', 'before_json' => 'not json', 'after_json' => '[]'), 'x', 'k1'), 'nothing to read');
}

// Whose records the scope takes: a counter sale is the buyer's, not the
// cashier's; a submission is the person's by contact or when their account
// sent it from the site for nobody else, and only the address is cleared from
// somebody else's; the shared walk-in account is never the person's; text
// messages go by the person's own numbers.
function test_privacy_erase_scope_takes_only_the_persons_records()
{
	$scope = pg_test_function_source('pg_privacy_erase_scope');

	pg_assert_contains("\$by_user = \"(\" . \$by_user . \" AND (type <> 'local'))\";", $scope, 'orders by account leave counter sales out');
	pg_assert_contains(" AND (contact_id = 0) AND (ip_address <> 0))", $scope, 'submissions by account: sent from the site for nobody else');
	pg_assert_false(strpos($scope, "_pg_privacy_export_in('id', _pg_privacy_export_ids(\$named))") !== false, 'no submission taken whole for a field value');
	pg_assert_contains("SELECT id FROM form_data WHERE \" . _pg_privacy_export_in('data', \$scope['emails'])", $scope, 'fields holding the address');
	pg_assert_contains("(id <> '\" . \$walkin . \"')", $scope, 'walk-in account left out');
	pg_assert_contains("_pg_privacy_export_in('id', \$scope['order_ids']) . \" AND \" . \$mine", $scope, 'an order names an ERP account only when it is the person\'s by contact or address');
	pg_assert_false(strpos($scope, 'FROM address_book') !== false, 'no address book numbers');
	pg_assert_false(strpos($scope, 'FROM ship_tos') !== false, 'no shipment numbers');
}

// The role and self checks also cover accounts holding the person's address
// that are not tied to them.
function test_privacy_erase_checks_accounts_holding_the_address()
{
	$erase = pg_test_function_source('pg_privacy_erase');

	pg_assert_contains("_pg_privacy_export_users(_pg_privacy_export_in('user_email', \$found['person']['emails']))", $erase, 'accounts by address');
	pg_assert_contains("pg_privacy_export_allowed(\$operator['role'] ?? 3, \$operator['id'] ?? 0, \$users)", $erase, 'role check over them');
	pg_assert_contains("isset(\$users[(int) (\$operator['id'] ?? 0)])", $erase, 'self check over them');
}

// A workflow table cell names the person when a person cell holds their
// account, or a record link points at their contact, account or a current
// account in the scope; a cell of a column the table no longer has is read
// as either. Other cells, kinds and ids are not theirs.
function test_privacy_wtable_cell_hits()
{
	$scope = privacy_erase_test_scope();
	$types = array('1' => 'person', '2' => 'link', '3' => 'number', '4' => 'text', '5' => 'link', '6' => 'person');

	pg_assert_same(array('1', '2'), pg_privacy_wtable_cell_hits($types, array(
		'1' => 7,
		'2' => array('kind' => 'contact', 'id' => 12),
		'3' => 7,
		'4' => '7',
		'5' => array('kind' => 'order', 'id' => 30),
		'6' => 8,
	), $scope), 'person and contact link');

	pg_assert_same(array('2', '5'), pg_privacy_wtable_cell_hits($types, array(
		'2' => array('kind' => 'user_account', 'id' => 7),
		'5' => array('kind' => 'erp_account', 'id' => 50),
	), $scope), 'account and current account links');

	pg_assert_same(array(), pg_privacy_wtable_cell_hits($types, array(
		'1' => null,
		'2' => array('kind' => 'contact', 'id' => 13),
		'5' => array('kind' => 'erp_account', 'id' => 51),
		'6' => 'x',
	), $scope), 'nobody else');

	pg_assert_same(array('9', '10'), pg_privacy_wtable_cell_hits($types, array(
		'9'  => 7,
		'10' => array('kind' => 'contact', 'id' => 12),
		'11' => 12,
		'12' => 'text',
	), $scope), 'cells of a deleted column');

	$scope['user_ids'] = array();
	pg_assert_same(array(), pg_privacy_wtable_cell_hits($types, array('1' => 7), $scope), 'no account in the scope');
}

// The erasure empties only the cells that name the person; the row and its
// other cells stay with the table's owner.
function test_privacy_wtable_cells_erased()
{
	pg_assert_same(
		array('3' => 7, '4' => 'note'),
		pg_privacy_wtable_cells_erased(array('1' => 7, '2' => array('kind' => 'contact', 'id' => 12), '3' => 7, '4' => 'note'), array('1', '2')),
		'two cells emptied'
	);
	pg_assert_same(array('3' => 1), pg_privacy_wtable_cells_erased(array('3' => 1), array()), 'nothing to empty');
}

// Rows are narrowed in SQL by the ids as JSON writes an integer value (after
// a colon, before a comma or the closing brace), then read in PHP; no id is
// no condition at all.
function test_privacy_wtable_rows_where()
{
	privacy_erase_test_db_stub();

	$scope = privacy_erase_test_scope();
	$where = pg_privacy_wtable_rows_where($scope);

	foreach (array(7, 12, 50) as $id) {
		pg_assert_contains("`cells` LIKE '%:" . $id . ",%'", $where, 'id ' . $id . ' before a comma');
		pg_assert_contains("`cells` LIKE '%:" . $id . "}%'", $where, 'id ' . $id . ' before the brace');
	}

	pg_assert_true(strpos($where, ':30,') === false, 'an order id is not looked for');
	pg_assert_same('', pg_privacy_wtable_rows_where(array('user_ids' => array(), 'contact_ids' => array(), 'account_ids' => array())), 'no ids');
	pg_assert_same('', pg_privacy_wtable_rows_where(array()), 'no scope');
}
