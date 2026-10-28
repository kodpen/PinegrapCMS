<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the widget offered beside a classic page's custom layout
 * (pg_layout_widget_kinds(), pg_layout_widget_seed()): which page types get
 * a widget and of which kind, and which type settings the widget's config
 * carries over.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/layouts.php');

function test_layout_widget_kinds()
{
	$kinds = pg_layout_widget_kinds();
	$labels = pg_sw_type_labels();

	foreach ($kinds as $type => $kind) {
		pg_assert_true(check_if_page_type_supports_layout($type), $type . ': draws a layout');
		pg_assert_true(isset($labels[$kind]), $type . ': ' . $kind . ' is a registered kind');
	}

	pg_assert_true(!isset($kinds['custom form']), 'a custom form page converts its layout instead');
	pg_assert_true(!isset($kinds['order receipt']), 'no widget for the order receipt');
	pg_assert_same('catalog_listing', $kinds['catalog']);
	pg_assert_same('form_list_view', $kinds['form list view']);

	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/designer_screen.php');
	pg_assert_contains("'layoutWidgets' => pg_layout_widget_offers(\$layout_pages)", $screen, 'the editor gets the offers of its layout pages');

	// Every widget the account module names for a layout type is offered.
	foreach (pg_sw_page_type_widgets() as $type => $kind) {
		if (check_if_page_type_supports_layout($type)) {
			pg_assert_same($kind, isset($kinds[$type]) ? $kinds[$type] : '', $type . ': the same kind as pg_sw_page_type_widgets()');
		}
	}
}

function test_layout_widget_seed_catalog()
{
	$cfg = pg_layout_widget_seed('catalog', array('product_group_id' => '12', 'catalog_detail_page_id' => '40', 'search' => '1', 'number_of_columns' => '3'));
	pg_assert_same(array('product_group_id' => 12, 'detail_page_id' => 40, 'search_enabled' => true), $cfg);

	pg_assert_same(array('next_page_id' => 7), pg_layout_widget_seed('catalog detail', array('next_page_id' => '7')));
	pg_assert_same(array(), pg_layout_widget_seed('catalog detail', array('next_page_id' => '0')), 'no next page: the widget\'s own');
	pg_assert_same(array('product_group_id' => 0, 'detail_page_id' => 0, 'search_enabled' => false), pg_layout_widget_seed('catalog', array()), 'a page without its type row');
}

function test_layout_widget_seed_form_views()
{
	$row = array(
		'custom_form_page_id'                => '5',
		'form_item_view_page_id'             => '6',
		'maximum_number_of_results'          => '0',
		'maximum_number_of_results_per_page' => '25',
		'search'                             => '1',
		'search_label'                       => 'Find',
		'viewer_filter'                      => '1',
		'viewer_filter_submitter'            => '1',
		'viewer_filter_watcher'              => '0',
		'viewer_filter_editor'               => '0',
		'order_by_1_standard_field'          => '',
		'order_by_1_form_field_id'           => '31',
		'order_by_1_type'                    => 'descending_alphabetical',
		'order_by_2_standard_field'          => 'submitted_date_and_time',
		'order_by_2_type'                    => 'ascending_alphabetical',
	);
	$cfg = pg_layout_widget_seed('form list view', $row, array(31 => 'title'));
	pg_assert_same(5, $cfg['form_page_id']);
	pg_assert_same(6, $cfg['detail_page_id']);
	pg_assert_same(0, $cfg['max_results']);
	pg_assert_same(25, $cfg['items_per_page']);
	pg_assert_same(true, $cfg['search_enabled']);
	pg_assert_same('Find', $cfg['search_label']);
	pg_assert_same(true, $cfg['viewer_filter']);
	pg_assert_same(true, $cfg['viewer_filter_submitter']);
	pg_assert_same(false, $cfg['viewer_filter_watcher']);
	pg_assert_same('title', $cfg['order_by_field'], 'a form field by its name');
	pg_assert_same('DESC', $cfg['order_by_direction']);
	pg_assert_same('submitted_date_and_time', $cfg['order_by_2_field']);
	pg_assert_same('ASC', $cfg['order_by_2_direction']);

	$row['order_by_1_standard_field'] = 'random';
	$row['order_by_2_form_field_id'] = '99';
	$row['order_by_2_standard_field'] = '';
	$row['maximum_number_of_results_per_page'] = '0';
	$row['search_label'] = '';
	$cfg = pg_layout_widget_seed('form list view', $row, array(31 => 'title'));
	pg_assert_true(!isset($cfg['order_by_field']), 'random: the widget\'s own order');
	pg_assert_true(!isset($cfg['order_by_2_field']), 'a field the form no longer has');
	pg_assert_true(!isset($cfg['items_per_page']), 'no page size: the widget\'s own');
	pg_assert_true(!isset($cfg['search_label']), 'no label: the widget\'s own');

	pg_assert_same(array('form_page_id' => 5), pg_layout_widget_seed('form item view', array('custom_form_page_id' => '5', 'layout' => 'x')));
}

function test_layout_widget_seed_types_reading_their_row()
{
	foreach (array('shopping cart', 'express order', 'billing information', 'order form', 'photo gallery', 'login') as $type) {
		pg_assert_same(array(), pg_layout_widget_seed($type, array('next_page_id' => '3')), $type . ': the widget reads its page itself');
	}
}
