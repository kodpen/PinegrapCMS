<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for an order's profit on the order screens
 * (includes/erp/order_profit_view.php): who sees it, the Profit card's cost
 * lines and their sources, the "Sold at a loss" filter and the stored
 * margin's text.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/order_profit_view.php');

function test_erp_order_profit_view_access()
{
	// Accounting off: nobody sees profit, a designer neither.
	pg_assert_same(false, erp_order_profit_access(false, array('role' => 1)), 'Accounting off shows no profit');
	pg_assert_same(true, erp_order_profit_access(true, array('role' => 2)), 'a manager sees profit');
	pg_assert_same(false, erp_order_profit_access(true, array('role' => 3)), 'a user without the ERP right sees none');
	pg_assert_same(true, erp_order_profit_access(true, array('role' => 3, 'manage_erp' => 1)), 'a user with the ERP right sees it');
	pg_assert_same(false, erp_order_profit_access(true, null), 'no user, no profit');
}

function test_erp_order_profit_view_card_lines()
{
	// The card's lines: product always, other kinds only with an amount or a row,
	// each with its distinct sources in the order the rows came.
	$profit = array('cost_product' => 3000, 'cost_payment' => 250, 'cost_marketplace' => 0, 'cost_shipping' => 0, 'cost_other' => 0);
	$costs = array(
		array('kind' => 'product', 'source' => 'average', 'amount' => 2000),
		array('kind' => 'product', 'source' => 'bundle', 'amount' => 500),
		array('kind' => 'product', 'source' => 'average', 'amount' => 500),
		array('kind' => 'payment', 'source' => 'rate', 'amount' => 250),
		array('kind' => 'shipping', 'source' => 'label', 'amount' => 0),
	);
	pg_assert_same(array(
		array('kind' => 'product', 'amount' => 3000, 'sources' => array('average', 'bundle')),
		array('kind' => 'payment', 'amount' => 250, 'sources' => array('rate')),
		array('kind' => 'shipping', 'amount' => 0, 'sources' => array('label')),
	), erp_order_profit_card_lines($profit, $costs), 'card lines by kind with their sources');

	pg_assert_same(array(
		array('kind' => 'product', 'amount' => 0, 'sources' => array()),
	), erp_order_profit_card_lines(array('cost_product' => 0), array()), 'an incomplete order still names product cost');
}

function test_erp_order_profit_view_filter()
{
	// The filter.
	pg_assert_same('', erp_order_profit_filter_sql(''), 'no filter by default');
	pg_assert_same('', erp_order_profit_filter_sql('any'), 'any is no filter');
	pg_assert_true(strpos(erp_order_profit_filter_sql('loss'), 'erp_order_profit.net < 0') !== false, 'sold at a loss reads net below zero');
}

function test_erp_order_profit_view_margin_text()
{
	// A stored margin: DECIMAL comes back as a string, NULL as a dash.
	pg_assert_same('—', erp_order_profit_margin_text(null), 'no margin is a dash');
	pg_assert_same('—', erp_order_profit_margin_text(''), 'an empty margin is a dash');
	pg_assert_same(erp_profit_margin_text(-12.5), erp_order_profit_margin_text('-12.5'), 'a stored margin prints as the profit report prints it');
}

function test_erp_order_profit_view_state_badge()
{
	// The state badge.
	pg_assert_same('danger', erp_order_profit_state_badge('incomplete')['theme'], 'incomplete is red');
	pg_assert_same('warning', erp_order_profit_state_badge('estimate')['theme'], 'estimate is amber');
	pg_assert_same('success', erp_order_profit_state_badge('final')['theme'], 'final is green');
}
