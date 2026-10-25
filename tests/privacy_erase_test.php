<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for erasing a person (includes/fn/privacy.php): which tables lose
 * the person's rows, which keep them with the personal columns replaced, and
 * which columns those are.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The field list is what an erasure promises the person and the operator: a
// column added to or taken from it changes what is kept, so it is pinned here
// and changed together with this test.
function test_privacy_erase_tables_are_pinned()
{
	$tables = pg_privacy_erase_tables();
	$actions = array();
	$fields = array();

	foreach ($tables as $table => $spec) {
		$actions[$table] = $spec['action'];

		if ($spec['fields']) {
			$fields[$table] = $spec['fields'];
		}
	}

	pg_assert_same(
		array(
			'orders' => 'anonymise', 'form_data_order' => 'delete', 'ship_tos' => 'anonymise',
			'order_documents' => 'anonymise', 'order_returns' => 'anonymise', 'gift_cards' => 'anonymise',
			'erp_accounts' => 'anonymise', 'erp_invoices' => 'anonymise', 'erp_quotes' => 'anonymise',
			'erp_waybills' => 'anonymise', 'comments' => 'anonymise', 'consent_log' => 'anonymise',
			'ws_approval_guests' => 'anonymise',
			'forms' => 'delete', 'form_signatures' => 'delete', 'watchers' => 'delete',
			'wishlist_items' => 'delete', 'stock_notify' => 'delete', 'email_recipients' => 'delete',
			'mail_outbox' => 'delete', 'sms_outbox' => 'delete', 'chat_conversations' => 'delete',
			'address_book' => 'delete', 'push_subscriptions' => 'delete', 'user_passkeys' => 'delete',
			'user_identities' => 'delete', 'user_mfa' => 'delete', 'auth_tokens' => 'delete',
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
			'comments' => array('name' => '{anonymous}', 'created_user_id' => 0),
			'consent_log' => array('user_id' => 0, 'contact_id' => 0, 'ip' => '', 'user_agent' => ''),
			'ws_approval_guests' => array(
				'name' => '{anonymous}', 'email' => '{token}', 'decided_ip' => '', 'decided_agent' => '',
			),
		),
		$fields,
		'replaced columns'
	);
}
