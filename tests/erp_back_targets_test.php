<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the ERP list and detail screens going back to the filtered list
 * a row was opened from: each list passes its own address as send_to, and
 * each detail screen carries it through its reloads and forms to the back
 * arrow, the breadcrumb and Save/Delete.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function erp_back_targets_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

// detail screen => array(list file, breadcrumb label, list url expression)
function erp_back_targets_screens()
{
	return array(
		'edit_erp_account.php' => array('erp_accounts.php', 'Accounts', '$list_url'),
		'edit_erp_invoice.php' => array('erp_invoices.php', 'Invoices', '$list_url'),
		'edit_erp_invoice_draft.php' => array('erp_invoices.php', 'Invoices', '$list_url'),
		'edit_erp_expense.php' => array('erp_expenses.php', 'Expenses', '$list_url'),
		'edit_erp_quote.php' => array('erp_quotes.php', 'Quotes', '$list_url'),
		'edit_erp_purchase_order.php' => array('erp_purchase_orders.php', 'Purchase orders', '$list_url'),
		'erp_cheque.php' => array('erp_cheques.php', 'Cheques and notes', "OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/erp_cheques.php'"),
	);
}

function test_erp_lists_open_rows_with_send_to()
{
	$lists = array(
		'erp_accounts.php' => 'edit_erp_account.php',
		'erp_invoices.php' => 'edit_erp_invoice',
		'erp_expenses.php' => 'edit_erp_expense.php',
		'erp_quotes.php' => 'edit_erp_quote.php',
		'erp_purchase_orders.php' => 'edit_erp_purchase_order.php',
		'erp_cheques.php' => 'erp_cheque.php',
	);

	foreach ($lists as $file => $screen) {
		$source = erp_back_targets_source($file);

		pg_assert_contains($screen, $source, $file . ' links its rows');
		pg_assert_contains("'&send_to=' . urlencode(REQUEST_URL)", $source, $file . ' passes its own address as send_to');
	}
}

function test_erp_drawer_open_in_full_passes_send_to()
{
	$source = erp_back_targets_source('assets/js/erp_lists.js');

	pg_assert_contains("'send_to=' + encodeURIComponent(window.location.pathname + window.location.search)", $source, 'Open in full comes back to the filtered list');
}

function test_erp_detail_screens_go_back_to_send_to()
{
	foreach (erp_back_targets_screens() as $file => $screen) {
		$source = erp_back_targets_source($file);

		pg_assert_contains("\$send_to = pg_send_to_url('');", $source, $file . ' reads send_to');
		pg_assert_contains("'cancel' => array('enable' => 'true', 'url' => pg_send_to_url('" . $screen[0] . "'))", $source, $file . ' back arrow goes to send_to');
		pg_assert_contains("array('label' => lang('" . $screen[1] . "'), 'url' => pg_send_to_list_url(" . $screen[2] . "))", $source, $file . ' breadcrumb keeps the filtered list');
	}
}

function test_erp_detail_screens_keep_send_to_through_reloads_and_forms()
{
	foreach (erp_back_targets_screens() as $file => $screen) {
		$source = erp_back_targets_source($file);
		$self = preg_quote($file, '/');

		pg_assert_false(preg_match('/action="' . $self . '"/', $source) === 1, $file . ' has no form that drops send_to');
		pg_assert_false(preg_match("/'\\/" . $self . "\\?id=' \\. \\$\\w+(\\)|;)/", $source) === 1, $file . ' has no reload that drops send_to');
		pg_assert_true((strpos($source, '$send_to_query') !== false), $file . ' builds its reloads with send_to');
	}
}

function test_erp_save_and_delete_go_to_send_to()
{
	$account = erp_back_targets_source('edit_erp_account.php');
	pg_assert_contains("go(pg_send_to_url(PATH . SOFTWARE_DIRECTORY . '/erp_accounts.php'));", $account, 'Save Account goes to the filtered list');
	pg_assert_false(strpos($account, "go(PATH . SOFTWARE_DIRECTORY . '/erp_accounts.php');") !== false, 'no bare list after Save Account');

	$draft = erp_back_targets_source('edit_erp_invoice_draft.php');
	pg_assert_contains('go(pg_send_to_url($list_url));', $draft, 'deleting a draft goes to the filtered register');
	pg_assert_contains("'/edit_erp_invoice.php?id=' . \$invoice_id . \$send_to_query)", $draft, 'the issued document keeps send_to');
}

function test_erp_send_to_list_url_keeps_the_register_only()
{
	$list = '/pinegrap/erp_invoices.php';
	$had = array_key_exists('send_to', $_REQUEST);
	$old = $had ? $_REQUEST['send_to'] : null;

	try {
		$_REQUEST['send_to'] = '/pinegrap/erp_invoices.php?account_id=5&bucket=31_60';
		pg_assert_same('/pinegrap/erp_invoices.php?account_id=5&bucket=31_60', pg_send_to_list_url($list), 'the aging view of the register');

		$_REQUEST['send_to'] = '/pinegrap/erp_aging.php';
		pg_assert_same($list, pg_send_to_list_url($list), 'another screen leaves the breadcrumb on the register');
	} finally {
		if ($had) {
			$_REQUEST['send_to'] = $old;
		} else {
			unset($_REQUEST['send_to']);
		}
	}
}
