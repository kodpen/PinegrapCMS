<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the short link form shared by the create and the edit screens
 * (includes/forms/short_link_form.php): which option rows a destination
 * opens with, the column a destination is saved in, and that both screens
 * use the shared form and the token.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/short_link_form.php');

function short_link_form_test_shown($destination_type)
{
	return array_keys(array_filter(pg_short_link_form_row_styles($destination_type), function ($style) {
		return $style === '';
	}));
}

function test_short_link_form_row_styles()
{
	pg_assert_same(array('page_id', 'tracking_code'), short_link_form_test_shown('page'), 'page');
	pg_assert_same(array('catalog_page_id', 'or', 'catalog_detail_page_id', 'product_group_id', 'tracking_code'), short_link_form_test_shown('product_group'), 'product group');
	pg_assert_same(array('catalog_detail_page_id', 'product_id', 'tracking_code'), short_link_form_test_shown('product'), 'product');
	pg_assert_same(array('url'), short_link_form_test_shown('url'), 'url');
	pg_assert_same(array('file'), short_link_form_test_shown('file'), 'file');
	pg_assert_same(array(), short_link_form_test_shown(''), 'none chosen yet');
	pg_assert_same(array(), short_link_form_test_shown('workspace_guest'), 'a type the form does not offer');

	$styles = pg_short_link_form_row_styles('url');

	pg_assert_same(9, count($styles), 'every row has a style');
	pg_assert_same(' style="display: none"', $styles['page_id'], 'hidden rows');
}

function test_short_link_form_destination_column()
{
	pg_assert_same(array('product_group_id', 'product_group_id'), pg_short_link_form_destination_column('product_group'), 'product group');
	pg_assert_same(array('product_id', 'product_id'), pg_short_link_form_destination_column('product'), 'product');
	pg_assert_same(array('url', 'url'), pg_short_link_form_destination_column('url'), 'url');
	pg_assert_same(array('file_id', 'file'), pg_short_link_form_destination_column('file'), 'file: the field is named file');
	pg_assert_same(null, pg_short_link_form_destination_column('page'), 'a page keeps its target in page_id');
	pg_assert_same(null, pg_short_link_form_destination_column(''), 'none');
}

function test_short_link_form_screens_use_the_shared_form()
{
	foreach (array('add_short_link.php', 'edit_short_link.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_contains("require_once(PG_FUNCTIONS_DIR . '/includes/forms/short_link_form.php');", $source, $file . ': the form');
		pg_assert_contains('pg_short_link_form_cards($liveform, ', $source, $file . ': the cards');
		pg_assert_contains('pg_short_link_form_validate_destination($liveform, ', $source, $file . ': the destination check');
		pg_assert_contains('pg_short_link_form_answer($liveform, ', $source, $file . ': the answer check');
		pg_assert_contains('get_token_field()', $source, $file . ': the token field');
		pg_assert_contains('validate_token_field();', $source, $file . ': the token check');
	}

	// A one-time link has no name to check; an edited link may keep its own.
	pg_assert_contains("if (\$link_mode !== 'once') {\n        \$name = pg_short_link_form_validate_name(\$liveform, null);", (string) file_get_contents(PG_FUNCTIONS_DIR . '/add_short_link.php'), 'create');
	pg_assert_contains("pg_short_link_form_validate_name(\$liveform, \$liveform->get_field_value('id'))", (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_short_link.php'), 'edit');
}
