<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the ERP account prices screen layout (erp_account_prices.php):
 * the price list and the product search in a form column, the account's
 * discount form and a Summary of the list in a sticky status column written
 * after it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function erp_account_prices_layout_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/erp_account_prices.php');
}

function test_erp_account_prices_has_form_and_status_columns()
{
	$source = erp_account_prices_layout_source();

	$form = strpos($source, '<div class="col-12 lg:col-8 2xl:col-9 order-1">');
	$status = strpos($source, '<div class="col-12 lg:col-4 2xl:col-3 order-2">');

	pg_assert_true($form !== false, 'the form column is there');
	pg_assert_true($status !== false, 'the status column is there');
	pg_assert_true($form < $status, 'form column before status column');
	pg_assert_contains('<div class="position-sticky my-7" style="top:3.3rem;">', $source, 'the status column sticks below the top bar');

	foreach (array('id="erp_prices"', 'role="search"') as $part) {
		$at = strpos($source, $part);
		pg_assert_true($at !== false && $at > $form && $at < $status, $part . ' sits in the form column');
	}

	$discount = strpos($source, 'name="account_discount"');
	pg_assert_true($discount !== false && $discount > $status, 'the discount field sits in the status column');
}

function test_erp_account_prices_status_column_reads_the_counts()
{
	$source = erp_account_prices_layout_source();
	$status = substr($source, (int) strpos($source, '<div class="col-12 lg:col-4 2xl:col-3 order-2">'));

	pg_assert_contains("lang('Summary')", $status, 'the summary card has its title');
	foreach (array('$summary_products', '$summary_priced', '$summary_discounted') as $count) {
		pg_assert_contains($count, $status, $count . ' is shown');
	}
	pg_assert_contains('<span class="pg-btn-label">', $status, 'the Save label is in its span');
}
