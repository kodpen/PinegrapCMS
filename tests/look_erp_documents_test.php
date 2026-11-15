<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source test for the ERP invoice, quote and order documents in look A: the
 * screens built on the invoice form take the edit page width, their cards
 * and the shared forms' titles are the look's section title, the fields sit
 * on the 12-column grid by their kind, and the quick-look drawer carries no
 * style block of its own.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function look_erp_documents_test_read($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

function test_erp_document_screens_take_the_edit_width()
{
	foreach (array('add_erp_manual_invoice.php', 'add_erp_purchase_order.php', 'add_erp_quote.php', 'edit_erp_invoice_draft.php', 'edit_erp_quote.php', 'edit_erp_purchase_order.php') as $file) {
		pg_assert_true((bool) preg_match("/'extra classes'\\s*=>\\s*'[^']*\\bpg-measure-edit\\b/", look_erp_documents_test_read($file)), $file . ' takes the edit width');
	}
}

function test_erp_document_cards_have_the_look_title()
{
	foreach (array('includes/erp/invoice_form.php', 'includes/erp/quote_templates.php', 'edit_erp_quote.php', 'edit_erp_purchase_order.php') as $file) {
		$source = look_erp_documents_test_read($file);
		pg_assert_same(false, strpos($source, 'text-uppercase h5 fg-primary fw-bold'), $file . ' keeps no upper-case card title');
		pg_assert_contains('pg-title-section', $source, $file . ' titles its cards with the section title');
	}

	$form = look_erp_documents_test_read('includes/erp/invoice_form.php');
	pg_assert_same(false, strpos($form, 'text-transform:none'), 'the lines card undoes no upper-case title');
	pg_assert_contains('<div class="card pg-card my-7" data-erp-totals>', $form, 'the totals card is the look card');
}

function test_erp_document_status_columns_hold_look_cards()
{
	foreach (array('edit_erp_quote.php', 'edit_erp_purchase_order.php') as $file) {
		pg_assert_true((bool) preg_match('#2xl:col-3 order-2">\s*<div class="position-sticky">#', look_erp_documents_test_read($file)), $file . ' puts its status cards in the column\'s box');
	}
}

function test_erp_document_fields_sit_on_the_grid()
{
	$form = look_erp_documents_test_read('includes/erp/invoice_form.php');
	$waybill = look_erp_documents_test_read('includes/erp/waybill_form.php');

	// A date, a rate 3 columns; a select 6; a code 4; a name the whole row.
	foreach (array(
		array($form, 'col-12 md:col-3 my-3">' . "\n" . '                    <label for="issue_date"'),
		array($form, 'col-12 md:col-6 my-3">' . "\n" . '                    <label for="currency"'),
		array($form, 'col-12 md:col-3 my-3">' . "\n" . '                    <label for="exchange_rate"'),
		array($form, 'col-12 md:col-4 my-3">' . "\n" . '                    <label for="supplier_invoice_no"'),
		array($waybill, 'col-12 md:col-6 my-3">' . "\n" . '                    <label for="account_id"'),
		array($waybill, 'col-12 md:col-3 my-3">' . "\n" . '                    <label for="ship_time"'),
		array($waybill, 'col-12 my-3">' . "\n" . '                    <label for="carrier_title"'),
		array($waybill, 'col-12 pg-field-span-4 my-3">' . "\n" . '                    <label for="driver_tckn"'),
	) as $case) {
		pg_assert_contains($case[1], $case[0], 'a field takes the span of its kind');
	}
}

function test_erp_drawer_has_no_style_block()
{
	$drawer = look_erp_documents_test_read('includes/erp/drawer.php');
	pg_assert_same(false, strpos($drawer, '<style'), 'the drawer prints no style block');
	pg_assert_contains('<h6 class="pg-group-label mb-3">', $drawer, 'a drawer section is titled with the group label');
	pg_assert_contains('.pg-erp-drawer { --pg-drawer-width: min(560px, 100vw); }', look_erp_documents_test_read('assets/css/backend.src.css'), 'the stylesheet sizes the drawer');
}
