<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source test for look A's figure cards: the shared label, value and note
 * of a figure outside the report block draw the report figure's values, and
 * the commissions figures, the invoice's settlement figures, the receipt's
 * amount and the Marketplace Products hero use them instead of their own
 * sizes.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function look_figure_cards_test_source($path)
{
	$file = dirname(__DIR__) . '/pinegrap/' . $path;

	return is_file($file) ? (string) file_get_contents($file) : '';
}

/**
 * The declarations of the first rule whose selector is exactly $selector
 * inside $css, or ''.
 */
function look_figure_cards_test_rule($css, $selector)
{
	if (!preg_match('/(?:^|\n)' . preg_quote($selector, '/') . " \\{\n(.*?)\n\\}/s", $css, $match)) {
		return '';
	}

	return $match[1];
}

function look_figure_cards_test_block()
{
	$css = look_figure_cards_test_source('assets/css/backend.src.css');
	$start = strpos($css, 'Look A: figure cards.');

	if ($start === false) {
		return '';
	}

	$end = strpos($css, '/* ======', $start + 40);

	return substr($css, $start, ($end === false) ? strlen($css) - $start : $end - $start);
}

// The label, value and note carry the report figure's numbers: the same
// declarations, the value's size as the token that holds the report's 26 px.
function test_figure_classes_draw_the_report_figure()
{
	$css = look_figure_cards_test_source('assets/css/backend.src.css');
	$block = look_figure_cards_test_block();

	pg_assert_true($block !== '', 'the block is found');
	pg_assert_contains('--pg-text-figure: 1.625rem;', $css, 'the figure size is 26 px');
	pg_assert_contains('--pg-text-figure-sm: 1.3125rem;', $css, 'and 21 px on a phone');

	$pairs = array(
		'.pg-report-fig-name' => '.pg-figure-label',
		'.pg-report-fig-note' => '.pg-figure-note',
		'.pg-report-fig' => '.pg-figure',
	);

	foreach ($pairs as $report => $shared) {
		$want = look_figure_cards_test_rule($css, $report);

		pg_assert_true($want !== '', $report . ' is found');
		pg_assert_same($want, look_figure_cards_test_rule($block, $shared), $shared . ' draws as ' . $report);
	}

	$value = str_replace("  display: block;\n", '', look_figure_cards_test_rule($block, '.pg-figure-value'));
	$report_value = str_replace('font-size: 26px;', 'font-size: var(--pg-text-figure);', look_figure_cards_test_rule($css, '.pg-report-fig-value'));

	pg_assert_same($report_value, $value, 'the value draws as the report value');
	pg_assert_contains("@media (max-width: 575.98px) {\n  .pg-figures {\n    grid-template-columns: repeat(2, minmax(0, 1fr));", $block, 'two cards a row on a phone');
	pg_assert_contains('.pg-figure-value.is-good {' . "\n  color: var(--pg-success-fg-emphasis);", $block, 'a good value in the success ink');
	pg_assert_contains('.pg-figure-value.is-bad {' . "\n  color: var(--pg-danger-fg-emphasis);", $block, 'a bad value in the danger ink');
}

function test_figure_screens_use_the_shared_classes()
{
	$commissions = look_figure_cards_test_source('view_commissions.php');

	pg_assert_contains('<div class="pg-figures">', $commissions, 'commissions: the figure grid');
	pg_assert_same(4, substr_count($commissions, '<div class="pg-card pg-figure">'), 'commissions: four figure cards');
	pg_assert_true(strpos($commissions, 'fs-2xl') === false, 'commissions: no size of its own');

	$invoice = look_figure_cards_test_source('edit_erp_invoice.php');
	$start = strpos($invoice, "lang('Settlement')");
	$settlement = substr($invoice, (int) $start, (int) strpos($invoice, '$output_settlements', (int) $start) - (int) $start);

	pg_assert_same(4, substr_count($settlement, 'class="pg-figure-label"'), 'invoice: four settlement labels');
	pg_assert_same(4, substr_count($settlement, 'class="pg-figure-value'), 'invoice: four settlement values');
	pg_assert_true(strpos($settlement, 'h5 mb-0') === false, 'invoice: no heading size on a figure');

	$receipt = look_figure_cards_test_source('erp_receipt.php');

	pg_assert_contains('<div class="pg-figure-value \' . ($is_cancelled', $receipt, 'receipt: the amount is a figure value');

	$products = look_figure_cards_test_source('marketplace_products.php');

	pg_assert_same(12, substr_count($products, '<div class="mpp-stat"><b class="pg-figure-value'), 'marketplace products: twelve hero counts');
	pg_assert_same(3, substr_count($products, '<b class="pg-figure-value is-good"'), 'marketplace products: a good count in each hero');
	pg_assert_true(strpos($products, '.mpp-stat b') === false, 'marketplace products: no size of its own');
}
