<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source checks for the store's front end: the checkout's card fields carry
 * no hint that they appear when the card is picked (the PHP renderer and the
 * editor's twin), the product page's tabs stay on one line on a phone, and
 * the product page's edit chip goes in the widget's first element, not in
 * the first element of its loop.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_store_front_end_no_card_hint_in_either_twin()
{
	$hint = 'The card fields appear when Credit/Debit Card is selected.';
	foreach (array('/includes/fn/widgets_express_order.php', '/assets/js/style_designer.src.js', '/assets/js/style_designer.min.js') as $file) {
		$src = file_get_contents(PG_FUNCTIONS_DIR . $file);
		pg_assert_true(strpos($src, $hint) === false, $file . ': no card hint');
		pg_assert_true(strpos($src, 'pg-eo-cc-fields') !== false, $file . ': the card fields are still drawn');
	}
}

function test_store_front_end_product_tabs_on_one_line()
{
	$js    = file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$start = strpos($js, "customName: _sdT('Tab Menu')");
	pg_assert_true($start !== false, 'the product page tab menu');
	$menu  = substr($js, strrpos(substr($js, 0, $start), 'createNode('));
	$menu  = substr($menu, 0, strpos($menu, "_sdT('Tab Contents')"));

	pg_assert_contains("cssClass: 'nav nav-tabs nav-justified flex-nowrap'", $menu);
	pg_assert_same(1, substr_count($menu, "cssClass: 'nav-link active h-100'"));
	pg_assert_same(2, substr_count($menu, "cssClass: 'nav-link h-100'"));
	pg_assert_same(3, substr_count($menu, "cssClass: 'nav-link"));
}

function test_store_front_end_item_view_chip_on_the_widget()
{
	$src  = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_catalog.php');
	$join = strpos($src, "\$_civ_html = (\$static === '') ? \$rendered : str_replace('<!--pg-loop-slot-->', \$rendered, \$static);");
	pg_assert_true($join !== false, 'the loop put into the widget');
	$after = substr($src, $join, 1200);

	pg_assert_contains("pg_sw_add_edit_chip(\$_civ_html, pg_sw_backend_edit_url('edit_product.php'", $after);
	pg_assert_contains("pg_sw_add_edit_chip(\$_civ_html, pg_sw_backend_edit_url('edit_product_group.php'", $after);
	pg_assert_true(strpos($src, "pg_sw_add_edit_chip(\$rendered, pg_sw_backend_edit_url('edit_product.php', (int)\$p['id'])") === false, 'no chip on the loop');
	pg_assert_true(strpos($src, "pg_sw_add_edit_chip(\$rendered, pg_sw_backend_edit_url('edit_product_group.php', \$select_group_id)") === false, 'no group chip on the loop');
}
