<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The ERP lists (accounts, invoices, delivery notes, tills, incoming
 * e-invoices, account sync) on the list table driver, printed whole in
 * browser mode; the drawer opened by a row button; the aging report a plain
 * report table; the bulk ids of the account sync screen.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_ERP_ENTRY')) {
	define('PG_ERP_ENTRY', true);
}

require_once PG_FUNCTIONS_DIR . '/includes/fn/list_table.php';
require_once PG_FUNCTIONS_DIR . '/includes/erp/edoc/account_sync.php';

function erp_lists_td13_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function test_erp_lists_td13_printed()
{
	$files = array(
		'erp_accounts' => 'erp_accounts.php',
		'erp_invoices' => 'erp_invoices.php',
		'erp_waybills' => 'erp_waybills.php',
		'erp_cash' => 'erp_cash.php',
		'erp_inbox' => 'erp_inbox.php',
	);

	foreach ($files as $list => $file) {
		$source = erp_lists_td13_source($file);

		pg_assert_true(in_array($list, pg_list_table_printed_lists(), true), $list . ' keeps a heading sort');
		pg_assert_false(strpos($source, 'class="chart') !== false, $file . ': no DataTables table left');
		pg_assert_false(strpos($source, 'btn-data-control') !== false, $file . ': the name opens the record, no View/Edit button');
		pg_assert_contains("pg_list_state('" . $list . "'", $source, $file . ': sort kept under its list name');
		pg_assert_contains("pg_list_table_search_box('" . $list . "')", $source, $file . ': search box');
		pg_assert_contains('data-pg-open>', $source, $file . ': the name opens the record');
		pg_assert_contains("pg_js_bundle_tags('assets/js/pg_list_table.min.js')", $source, $file . ': driver loaded');
	}

	foreach (array('differ', 'provider_only', 'erp_only', 'same', 'ignored') as $tab) {
		pg_assert_true(in_array('erp_account_sync_' . $tab, pg_list_table_printed_lists(), true), 'account sync view ' . $tab . ' keeps a heading sort');
	}

	$sync = erp_lists_td13_source('erp_account_sync.php');
	pg_assert_false(strpos($sync, 'class="chart') !== false, 'account sync: no DataTables table left');
	pg_assert_contains("\$ids_field = 'sync_rows[]';", $sync, 'account sync: the put-aside view posts typed ids');
	pg_assert_contains("'id' => 'card-' . (int) \$row['id'],", $sync, 'account sync: a card row says it is a card');
	pg_assert_contains("'id' => 'account-' . (int) \$account['id'],", $sync, 'account sync: an account row says it is an account');
}

function test_erp_lists_td13_drawer_button()
{
	$drawer = erp_lists_td13_source('includes/erp/drawer.php');
	$script = erp_lists_td13_source('assets/js/erp_lists.js');

	pg_assert_contains('function erp_drawer_button($id)', $drawer, 'the drawer has a row button');
	pg_assert_false(strpos($drawer, 'function erp_drawer_row_attributes') !== false, 'a row click no longer opens the drawer');
	pg_assert_contains("closest('button[data-erp-drawer-id]')", $script, 'the script listens to the button');

	foreach (array('erp_accounts.php', 'erp_invoices.php', 'erp_waybills.php') as $file) {
		pg_assert_contains("'actions' => pg_list_cell(erp_drawer_button(\$id)),", erp_lists_td13_source($file), $file . ': Quick look in the actions column');
	}
}

function test_erp_lists_td13_invoice_bulk_in_bar()
{
	$source = erp_lists_td13_source('erp_invoices.php');

	pg_assert_contains("'ids_field' => \$edoc_bulk ? 'invoice_ids[]' : '',", $source, 'the selected ids reach the bulk handler');
	pg_assert_contains("pg_list_table_html(\$list['source'], \$list['page'], lang('Invoices'), \$output_bulk_menu)", $source, 'the bulk menu sits in the selection bar');
	pg_assert_contains("'data-pg-sort-value' => \$is_draft ? ('~' . \$id)", $source, 'a draft sorts after the numbered documents');
}

function test_erp_lists_td13_aging_plain_report()
{
	$source = erp_lists_td13_source('erp_aging.php');

	pg_assert_contains('<table class="table table-hover align-middle mb-0" data-pg-sort>', $source, 'aging: a plain sortable report');
	pg_assert_contains('<tfoot>', $source, 'aging: the totals row stays');
	pg_assert_false(strpos($source, 'class="chart') !== false, 'aging: no DataTables table left');
}

function test_erp_lists_td13_sort_columns()
{
	$columns = array(
		'name' => array('heading' => 'Name', 'kind' => 'link'),
		'total' => array('heading' => 'Total', 'kind' => 'money'),
		'note' => array('heading' => 'Note', 'sortable' => false),
		'thumb' => array('heading' => 'Picture', 'kind' => 'image'),
		'actions' => array('heading' => 'Action', 'kind' => 'actions'),
	);

	pg_assert_same(array('name' => 'Name', 'total' => 'Total'), pg_list_table_printed_sort_columns($columns), 'only the columns that sort');
}

function test_erp_lists_td13_account_sync_bulk_ids()
{
	pg_assert_same(array('card' => array(3, 4), 'account' => array(7)),
		erp_edoc_accounts_bulk_ids(array('card_ids' => array('3', '4', '3'), 'account_ids' => array('7'))), 'plain id lists, each id once');
	pg_assert_same(array('card' => array(12), 'account' => array(12, 5)),
		erp_edoc_accounts_bulk_ids(array('sync_rows' => array('card-12', 'account-12', 'account-5'))), 'typed ids: a card and an account with the same number');
	pg_assert_same(array('card' => array(), 'account' => array()),
		erp_edoc_accounts_bulk_ids(array('card_ids' => array(array('1'), '0', '-2', 'x'), 'sync_rows' => array('card-0', 'user-3', array('card-1'), 'card-2x'))), 'nothing else is a row');
}
