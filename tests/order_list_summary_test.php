<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * All Orders' figure strip and Summary (view_orders.php, includes/fn/order_list.php):
 * the bars' lengths, the Summary parts printed with their figures as text,
 * the strip as one card with Summary closed and read when it opens, the
 * period navigator in the one bar.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function order_list_summary_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

function test_order_list_summary_bars_follow_the_largest()
{
	$bars = pg_order_list_summary_bars(array(
		array('label' => 'Chair', 'value' => 92),
		array('label' => 'Desk', 'value' => 61),
		array('label' => 'Lamp', 'value' => 1),
		array('label' => 'Gift', 'value' => 0),
	));

	pg_assert_same(100, $bars[0]['share'], 'the largest fills the track');
	pg_assert_same(66, $bars[1]['share'], 'the others in proportion, rounded');
	pg_assert_same(2, $bars[2]['share'], 'a value above zero keeps a sliver');
	pg_assert_same(0, $bars[3]['share'], 'zero draws nothing');
	pg_assert_same('Desk', $bars[1]['label'], 'the label kept');
	pg_assert_same(array(), pg_order_list_summary_bars(array()), 'no rows, no bars');
	pg_assert_same(0, pg_order_list_summary_bars(array(array('label' => 'x', 'value' => 0)))[0]['share'], 'all zero');
}

function test_order_list_summary_part_prints_the_figures_as_text()
{
	$html = pg_order_list_summary_part_html('Top 5 products', pg_order_list_summary_bars(array(
		array('label' => 'Chair <b>', 'value' => 1200),
	)), 'No orders in this range.');

	pg_assert_contains('<h3 class="pg-group-label" id="pg_orders_summary_', $html, 'the part is titled');
	pg_assert_contains('Chair &lt;b&gt;', $html, 'the name escaped');
	pg_assert_contains('<b class="pg-orders-summary-value">' . h(pg_format_number(1200, 0)) . '</b>', $html, 'the figure as text, in the panel number format');
	pg_assert_contains('class="pg-orders-summary-track" aria-hidden="true"><i style="width: 100%"></i>', $html, 'the bar is decoration');
	pg_assert_contains('<ul class="pg-orders-summary-bars" aria-labelledby="pg_orders_summary_', $html, 'the list named by its title');

	$empty = pg_order_list_summary_part_html('Payment methods', array(), 'No orders in this range.');
	pg_assert_contains('<p class="pg-figure-note">No orders in this range.</p>', $empty, 'the empty sentence');
	pg_assert_false(strpos($empty, '<ul') !== false, 'no list without rows');

	$both = pg_order_list_summary_html(array(array('label' => 'A', 'value' => 1)), array(array('label' => 'Card', 'value' => 3)));
	pg_assert_same(2, substr_count($both, 'class="pg-orders-summary-part"'), 'two parts');
	pg_assert_contains('<div class="pg-orders-summary-grid">', $both, 'side by side');
}

function test_order_list_strip_is_one_card_with_summary_closed()
{
	$source = order_list_summary_source('view_orders.php');

	pg_assert_contains('<section class="pg-card pg-figure-strip"', $source, 'the figures are one card');
	pg_assert_same(4, substr_count($source, '. $strip_cell('), 'four cells');
	pg_assert_false(strpos($source, '$stat_card') !== false, 'no figure card is left');
	pg_assert_contains('aria-expanded="false" aria-controls="pg_orders_summary"', $source, 'Summary starts closed');
	pg_assert_contains('id="pg_orders_summary" data-src="view_orders.php?summary=1"', $source, 'Summary is read when it opens');
	pg_assert_contains('" hidden></div>', $source, 'the Summary row is hidden until opened');
	pg_assert_contains("if ((\$_GET['summary'] ?? '') === '1') {", $source, 'the screen answers Summary');
	pg_assert_contains('print pg_order_list_summary_html($top_products, $payment_split);', $source, 'with the shared markup');
	pg_assert_contains('var key = "pg.orders." + (button.getAttribute("data-pg-user") || "0") + ".summary";', $source, 'open or closed is the person\'s');
	pg_assert_contains('window.jQuery(box).html(html)', $source, 'the markup filled with jQuery');
	pg_assert_false(strpos($source, 'pg_orders_top') !== false || strpos($source, 'pg_orders_payments') !== false, 'no canvas chart is left');
}

function test_order_list_one_bar_holds_the_period()
{
	$source = order_list_summary_source('view_orders.php');
	$start = strpos($source, '<nav id="button_bar"');
	$bar = substr($source, (int) $start, (int) strpos($source, '</nav>', (int) $start) - (int) $start);

	$import = strpos($bar, "lang('Import Tracking Numbers')");
	$rule = strpos($bar, '<span class="pg-orders-bar-rule" aria-hidden="true"></span>');
	$period = strpos($bar, '$output_period_navigator');
	$grow = strpos($bar, '<div class="pg-orders-bar-end">');
	$sort = strpos($bar, 'pg_list_sort_menu(');

	pg_assert_true($import !== false && $rule > $import, 'the rule after Import Tracking Numbers');
	pg_assert_true($period > $rule && $grow > $period, 'the period navigator after the rule, before the right-hand controls');
	pg_assert_true($sort > $grow, 'Sort and the selects at the right');
	pg_assert_same(1, substr_count($source, '$output_period_navigator . \''), 'the navigator printed once');

	$css = order_list_summary_source('assets/css/backend.src.css');
	pg_assert_contains("@media (max-width: 1279.98px) {\n  .pg-orders-bar-rule {\n    flex-basis: 100%;", $css, 'under 1280 px the navigator starts the second row');
	pg_assert_contains(".pg-figure-strip {\n  display: grid;\n  grid-template-columns: repeat(4, minmax(0, 1fr)) auto;", $css, 'four cells and the button');
}
