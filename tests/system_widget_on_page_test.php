<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for _pg_find_system_widget_in_list() (includes/fn/widgets_catalog.php),
 * the pure part of _pg_find_system_widget_on_page(): which of the widgets a
 * page places (pg_sw_page_widgets()) is the page's catalog listing or catalog
 * detail widget, as its feed link, structured data, sitemap and product feeds
 * see it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_system_widget_in_list_lowest_id_of_the_type_wins()
{
	$widgets = array(
		array('id' => 12, 'cfg' => array('regionType' => 'catalog_listing', 'product_group_id' => 5)),
		array('id' => 2, 'cfg' => array('regionType' => 'catalog_item_view')),
		array('id' => 9, 'cfg' => array('regionType' => 'catalog_listing', 'product_group_id' => '8')),
	);

	$found = _pg_find_system_widget_in_list($widgets, 'catalog_listing');

	pg_assert_same(9, $found['widget_id'], 'the lowest id of the type, not the first in the tree');
	pg_assert_same(8, $found['product_group_id'], 'the group comes from that widget, as an int');
	pg_assert_same('catalog_listing', $found['cfg']['regionType'], 'the whole config is returned');

	$detail = _pg_find_system_widget_in_list($widgets, 'catalog_item_view');

	pg_assert_same(2, $detail['widget_id'], 'the other type finds its own widget');
	pg_assert_same(0, $detail['product_group_id'], 'a widget without a group names none');
}

function test_system_widget_in_list_nothing_to_find()
{
	pg_assert_same(null, _pg_find_system_widget_in_list(array(), 'catalog_listing'), 'a page without widgets');
	pg_assert_same(null, _pg_find_system_widget_in_list(null, 'catalog_listing'), 'no list at all');
	pg_assert_same(null, _pg_find_system_widget_in_list(array(
		array('id' => 3, 'cfg' => array('regionType' => 'catalog_listing_extra')),
		array('id' => 4, 'cfg' => array('regionType' => 'Catalog_Listing')),
		array('id' => 0, 'cfg' => array('regionType' => 'catalog_listing')),
		array('id' => 5, 'cfg' => 'catalog_listing'),
		array('id' => 6),
	), 'catalog_listing'), 'only an exact type on a widget with an id and a config counts');
}
