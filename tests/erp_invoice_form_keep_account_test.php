<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The draft invoice, quote and purchase order editors keep their stored
 * account in the Account/Supplier list after it turned passive: one extra
 * option marked Passive, so the document does not open on "Choose an
 * account" and a plain save is not refused.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/invoice_form.php');

function test_erp_invoice_form_keep_account_adds_passive_option()
{
	$options = array(lang('Choose an account') => '', 'account_3' => array('label' => 'Active Ltd', 'value' => '3'));
	$kept = erp_invoice_form_keep_account($options, array('id' => 7, 'title' => 'Old <Co>', 'status' => 'passive'));

	pg_assert_same(3, count($kept), 'one option is added');
	pg_assert_same('7', $kept['account_7']['value'], 'the kept option carries the stored id');
	pg_assert_same(h('Old <Co>') . ' (' . lang('Passive') . ')', $kept['account_7']['label'], 'the title is escaped and marked Passive');
}

function test_erp_invoice_form_keep_account_leaves_listed_or_missing()
{
	$options = array(lang('Choose an account') => '', 'account_3' => array('label' => 'Active Ltd', 'value' => '3'));

	pg_assert_same($options, erp_invoice_form_keep_account($options, array('id' => 3, 'title' => 'Active Ltd', 'status' => 'active')), 'a listed account is not added twice');
	pg_assert_same($options, erp_invoice_form_keep_account($options, null), 'an account that no longer exists adds nothing');
	pg_assert_same($options, erp_invoice_form_keep_account($options, array('id' => 0, 'title' => 'x')), 'id 0 adds nothing');
}

function test_erp_invoice_form_keep_account_passed_by_the_edit_screens_only()
{
	$dir = PG_FUNCTIONS_DIR . '/';
	$form = (string) file_get_contents($dir . 'includes/erp/invoice_form.php');
	pg_assert_contains("erp_invoice_form_keep_account(\$account_options, erp_account(\$keep_account_id))", $form);

	pg_assert_contains("'keep_account_id' => (int) \$invoice['account_id']", (string) file_get_contents($dir . 'edit_erp_invoice_draft.php'));
	pg_assert_contains("'keep_account_id' => (int) \$quote['account_id']", (string) file_get_contents($dir . 'edit_erp_quote.php'));
	pg_assert_contains("'keep_account_id' => (int) \$order['account_id']", (string) file_get_contents($dir . 'edit_erp_purchase_order.php'));

	foreach (array('add_erp_invoice.php', 'add_erp_manual_invoice.php', 'add_erp_quote.php', 'add_erp_purchase_order.php') as $file) {
		if (is_file($dir . $file)) {
			pg_assert_true(strpos((string) file_get_contents($dir . $file), 'keep_account_id') === false, $file . ' stays active-only');
		}
	}
}
