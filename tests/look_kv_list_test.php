<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source test for look A's label and value list (.pg-kv): the recipe sits in
 * its own look block and reads tokens only, and the status columns of Edit
 * Invoice, Receipt and Marketplace Settings draw their facts with it instead
 * of their own grid rows.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function look_kv_list_test_source($path)
{
	$file = dirname(__DIR__) . '/pinegrap/' . $path;

	return is_file($file) ? (string) file_get_contents($file) : '';
}

function look_kv_list_test_block()
{
	$css = look_kv_list_test_source('assets/css/backend.src.css');
	$start = strpos($css, 'Look A: label and value list.');

	if ($start === false) {
		return '';
	}

	$end = strpos($css, '/* ======', $start + 40);

	return substr($css, $start, ($end === false) ? strlen($css) - $start : $end - $start);
}

// The recipe: a two-column grid, the label in the third text, the value at
// the right edge in the medium weight; sizes, weights and colours as tokens.
function test_kv_recipe_reads_tokens()
{
	$block = look_kv_list_test_block();

	pg_assert_true($block !== '', 'the block is found');
	pg_assert_contains('@layer custom {', $block, 'the block is layered');
	pg_assert_contains("grid-template-columns: fit-content(55%) minmax(0, 1fr);", $block, 'label column up to about half the card');
	pg_assert_contains('font-size: var(--pg-text-2);', $block, 'the list is 13 px');
	pg_assert_contains('color: var(--pg-fg-3);', $block, 'the label is the third text');
	pg_assert_contains('font-weight: var(--pg-weight-medium);', $block, 'the value is medium');
	pg_assert_contains('text-align: end;', $block, 'the value at the right edge');
	pg_assert_same(0, preg_match('/#[0-9a-f]{3,8}\b|rgba?\(|--bs-|font-weight: [0-9]|font-size: [0-9]/i', $block), 'no literal colour, size, weight or Bootstrap variable');
}

// The three status columns take the list and drop their own grid rows.
function test_kv_screens_use_the_recipe()
{
	$invoice = look_kv_list_test_source('edit_erp_invoice.php');

	pg_assert_same(3, substr_count($invoice, '<dl class="pg-kv'), 'invoice: the main, foreign currency and internet sale lists');
	pg_assert_same(2, substr_count($invoice, 'class="pg-kv pg-kv-rule"'), 'invoice: two lists under a hairline');
	pg_assert_true(strpos($invoice, 'lg:col-12 my-3">') === false, 'invoice: no grid cell per status row');

	$receipt = look_kv_list_test_source('erp_receipt.php');

	pg_assert_same(1, substr_count($receipt, '<dl class="pg-kv">'), 'receipt: one list');
	pg_assert_true(strpos($receipt, 'form-label fg-secondary') === false, 'receipt: no form label as a status label');

	$marketplace = look_kv_list_test_source('marketplace_settings.php');

	pg_assert_same(1, substr_count($marketplace, '<dl class="pg-kv">'), 'marketplace: the count list');
	pg_assert_true(strpos($marketplace, '<dt class="col-8') === false, 'marketplace: no grid columns on the labels');
}
