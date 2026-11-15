<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the look's list table inside an edit screen (.pg-embedded-table):
 * its stylesheet block, and the products table of Add and Edit Product Group
 * drawn with it through one row helper.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/product_group_form.php');

function look_embedded_table_css_block()
{
	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/backend.src.css');
	$start = strpos($css, 'Look A: a list table inside an edit screen.');
	pg_assert_true($start !== false, 'the block is in backend.src.css');
	$end = strpos($css, '/* ====', $start + 10);

	return substr($css, $start, ($end === false) ? null : $end - $start);
}

function test_embedded_table_block_reads_the_list_tokens()
{
	$block = look_embedded_table_css_block();

	pg_assert_contains('@layer custom {', $block);
	pg_assert_contains('height: 38px;', $block, 'the heading row of the list table');
	pg_assert_contains('font-size: var(--pg-text-meta);', $block);
	pg_assert_contains('font-weight: var(--pg-weight-strong);', $block);
	pg_assert_contains('color: var(--pg-fg-3);', $block);
	pg_assert_contains('height: 52px;', $block, 'rows of the list table');
	pg_assert_contains("td.pg-embedded-table-text {\n    min-width: 10rem;", $block, 'a text cell holds three words on two lines on a phone');
	pg_assert_same(0, preg_match('/var\(--bs-/', $block), 'no --bs-* variable read');
}

function test_product_row_draws_the_look_parts()
{
	$row = pg_product_group_form_row(array(
		'id' => 7, 'type' => 'product', 'name' => 'Tea <b>', 'enabled' => 1,
		'short_description' => 'Black', 'price' => 12.5, 'sort_order' => '3',
	), true, '');

	pg_assert_contains('<input class="check pg-checkbox" type="checkbox" name="products[]" checked="checked" value="7"', $row, 'a ticked look tick box');
	pg_assert_contains('<a class="pg-icon-button pg-icon-button-sm" href="edit_product.php?id=7"', $row, 'Edit is an icon link');
	pg_assert_contains('aria-label="' . h(lang('Edit') . ': Tea <b>') . '"', $row);
	pg_assert_contains('class="pg-input pg-input-sm pg-embedded-table-number" type="text" name="sort_order_product_7"', $row);
	pg_assert_contains('value="3"', $row);
	pg_assert_contains('data-sort="12.5"', $row, 'price sorts by its number');
	pg_assert_same(2, substr_count($row, 'pg-embedded-table-text'), 'name and short description are text cells');
	pg_assert_false(strpos($row, 'style=') !== false, 'no inline style');
	pg_assert_false(strpos($row, 'btn-data-control') !== false, 'no old row button');
	pg_assert_false(strpos($row, 'Tea <b>') !== false, 'the name is escaped');
}

function test_child_group_row_keeps_its_fixed_mark()
{
	$row = pg_product_group_form_row(array(
		'id' => 4, 'type' => 'product_group', 'name' => 'Cups', 'enabled' => 0,
		'short_description' => '', 'price' => 0, 'sort_order' => '1',
	), true, '');

	pg_assert_contains('<tr id="4" class="unselectable selected">', $row);
	pg_assert_contains('disabled-checkbox', $row);
	pg_assert_false(strpos($row, 'name="products[]"') !== false, 'a child group is not posted as a product');
	pg_assert_contains('href="edit_product_group.php?id=4"', $row);
	pg_assert_contains('name="sort_order_product_group_4"', $row);
	pg_assert_contains(pg_list_status_word(lang('Disabled')), $row);
}

function test_products_card_takes_the_embedded_table()
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/product_group_form.php');

	pg_assert_contains('<input class="pg-checkbox no-popover"', $src, 'Select All is a look tick box');
	pg_assert_contains('class="pg-embedded-table-foot"', $src);
	pg_assert_contains('class="pg-button pg-button-quiet pg-button-sm"', $src, 'Load More is a quiet button');
	pg_assert_contains('$btn.closest(".pg-embedded-table-foot").hide();', $src, 'the strip hides with its last batch');
}
