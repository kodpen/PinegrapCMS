<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings > Accounting, the Documents, Sales and Web orders rows drawn
 * open: the pane's switch and radio rows (includes/fn/accounting_settings_fold.php),
 * the three-way choices as radio rows posting the values the selects did,
 * every switch a pg-switch, no popover, and the field names that stay.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/accounting_settings_fold.php');

function settings_accounting_open_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

// The part of accounting.php from one card's id to the start of the next card.
function settings_accounting_open_test_card($id, $next)
{
	$pane = settings_accounting_open_test_source('includes/settings/accounting.php');
	$start = strpos($pane, '<div id="' . $id . '" class="pg-set-card">');
	$end = strpos($pane, '<div id="' . $next . '" class="pg-set-card">');

	pg_assert_true(($start !== false) && ($end !== false) && ($start < $end), $id . ' is drawn before ' . $next);

	return substr($pane, $start, $end - $start);
}

function test_settings_accounting_switch_is_a_pg_switch_with_its_line()
{
	$html = pg_accounting_settings_switch('erp_stock_documents', true, 'Invoices change stock', 'Off, only the costs are kept & nothing moves.');

	pg_assert_contains('<input type="checkbox" class="pg-switch" value="1" checked="checked" id="erp_stock_documents" name="erp_stock_documents" aria-describedby="erp_stock_documents_help"/>', $html, 'a checked pg-switch posting 1');
	pg_assert_contains('<label class="pg-text-2" for="erp_stock_documents">Invoices change stock</label>', $html, 'its label');
	pg_assert_contains('<p class="pg-field-help pg-text-meta fg-secondary mb-0" id="erp_stock_documents_help">Off, only the costs are kept &amp; nothing moves.</p>', $html, 'one line of help, escaped');

	$off = pg_accounting_settings_switch('erp_invoice_mail_auto', false, 'E-mail', '');
	pg_assert_false(strpos($off, 'checked') !== false, 'off is not checked');
	pg_assert_false(strpos($off, 'aria-describedby') !== false, 'no help, no reference');
	pg_assert_false(strpos($off, 'pg-field-help') !== false, 'no help line');
}

function test_settings_accounting_choice_is_radio_rows_with_one_line_each()
{
	$html = pg_accounting_settings_choice('erp_shipping_tax', 'Tax on shipping', array(
		array('value' => '', 'label' => 'By the store\'s country', 'line' => 'Untaxed in the United States.', 'on' => false),
		array('value' => 'included', 'label' => 'Included', 'line' => 'Inside the amount.', 'on' => true),
		array('value' => 'none', 'label' => 'Not taxed', 'line' => '', 'on' => false),
	), 'How an order\'s charges are written.');

	pg_assert_contains('<fieldset aria-describedby="erp_shipping_tax_help"><legend class="form-label pg-field-label">Tax on shipping</legend>', $html, 'a fieldset named by its legend');
	pg_assert_contains('<input value="" class="radio" type="radio" id="erp_shipping_tax_default" name="erp_shipping_tax" aria-describedby="erp_shipping_tax_default_help"/>', $html, 'the empty value keeps its radio, its id spelled out');
	pg_assert_contains('<label for="erp_shipping_tax_default">By the store&#039;s country</label>', $html, 'its label, escaped');
	pg_assert_contains('<div class="form-text mb-3" id="erp_shipping_tax_default_help">Untaxed in the United States.</div>', $html, 'its line after its field');
	pg_assert_contains('<input value="included" class="radio" type="radio" id="erp_shipping_tax_included" name="erp_shipping_tax" aria-describedby="erp_shipping_tax_included_help" checked="checked"/>', $html, 'the chosen one checked');
	pg_assert_contains('<input value="none" class="radio" type="radio" id="erp_shipping_tax_none" name="erp_shipping_tax"/>', $html, 'no line, no reference');
	pg_assert_same(3, substr_count($html, '<div class="form-field">'), 'one row a choice');
	pg_assert_same(1, substr_count($html, 'checked="checked"'), 'one chosen');
	pg_assert_contains('<p class="pg-field-help pg-text-meta fg-secondary mb-0" id="erp_shipping_tax_help">How an order&#039;s charges are written.</p>', $html, 'the choice\'s own line under it');

	$bare = pg_accounting_settings_choice('local_sale_prices', 'Prices on the till', array(
		array('value' => 'gross', 'label' => 'With tax', 'line' => 'a', 'on' => true),
	));
	pg_assert_contains('<fieldset><legend', $bare, 'no help, no reference');
	pg_assert_false(strpos($bare, 'pg-field-help') !== false, 'no help line');
}

function test_settings_accounting_sales_and_orders_drawn_open()
{
	$sales = settings_accounting_open_test_card('pgset-erp-sales', 'pgset-erp-orders');
	$orders = settings_accounting_open_test_card('pgset-erp-orders', 'pgset-erp-accounts');
	$documents = settings_accounting_open_test_card('pgset-erp', 'pgset-erp-sales');

	foreach (array('Documents' => $documents, 'Sales' => $sales, 'Web orders' => $orders) as $row => $html) {
		pg_assert_false(strpos($html, '<div class="switch">') !== false, $row . ': no Bootstrap switch');
		pg_assert_false(strpos($html, 'popover') !== false, $row . ': no popover');
		pg_assert_false(strpos($html, 'collapse-switcher') !== false, $row . ': nothing folds inside the row');
	}

	// The three-way choices are radio rows posting what the selects did.
	pg_assert_contains("pg_accounting_settings_choice('local_sale_prices', lang('Prices on the till')", $sales, 'till prices as radio rows');
	pg_assert_false(strpos($sales, 'name="local_sale_prices"') !== false, 'no select left');
	pg_assert_contains("pg_accounting_settings_switch('erp_stock_documents', \$erp_stock_documents_checked !== ''", $sales, 'Invoices change stock a pg-switch');
	pg_assert_contains('name="erp_walkin_account_id"', $sales, 'the walk-in account stays a select');

	pg_assert_contains("pg_accounting_settings_choice('erp_auto_invoice_on', lang('The invoice of a web order')", $orders, 'the web order\'s invoice as radio rows');
	pg_assert_contains("pg_accounting_settings_choice('erp_shipping_tax', lang('Tax on shipping and surcharges')", $orders, 'tax on shipping as radio rows');

	foreach (array('erp_auto_invoice_tax_number_only', 'erp_auto_invoice_marketplace', 'erp_invoice_mail_auto') as $name) {
		pg_assert_contains("pg_accounting_settings_switch('" . $name . "'", $orders, $name . ' a pg-switch');
	}

	foreach (array("'off'", "'order_paid'", "'order_shipped'", "'included'", "'none'") as $value) {
		pg_assert_contains("'value' => " . $value, $orders, $value . ' still posted');
	}

	// The message follows its switch; the filters follow the choice.
	pg_assert_true(strpos($orders, "pg_accounting_settings_switch('erp_invoice_mail_auto'") < strpos($orders, 'name="erp_invoice_mail_message"'), 'the message under its switch');
	pg_assert_true(strpos($orders, "pg_accounting_settings_choice('erp_auto_invoice_on'") < strpos($orders, 'name="erp_auto_invoice_min_total"'), 'the filters under the choice');
}
