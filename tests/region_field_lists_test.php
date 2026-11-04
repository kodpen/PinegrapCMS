<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The region, ad and field lists on the list table driver
 * (includes/fn/region_list.php, includes/fn/field_list.php): columns, the
 * cells that change in place and the answer to a quick edit.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once PG_FUNCTIONS_DIR . '/includes/fn/list_table.php';
require_once PG_FUNCTIONS_DIR . '/includes/fn/region_list.php';
require_once PG_FUNCTIONS_DIR . '/includes/fn/field_list.php';

function region_field_lists_ad_region(array $over = array())
{
	return $over + array(
		'id' => 7, 'name' => 'Top', 'display_type' => 'static', 'transition_type' => 'slide',
		'transition_duration' => 0, 'slideshow' => 1, 'slideshow_interval' => 5, 'slideshow_continuous' => 1,
		'created_timestamp' => 1700000000, 'last_modified_timestamp' => 1700000000,
		'created_username' => '', 'last_modified_username' => '',
	);
}

function test_region_lists_every_list_is_printed()
{
	foreach (pg_region_list_names() as $list) {
		pg_assert_true(in_array($list, pg_list_table_printed_lists(), true), $list);
	}

	pg_assert_true(in_array('view_ads', pg_list_table_printed_lists(), true));
	pg_assert_true(in_array('view_fields', pg_list_table_printed_lists(), true));
}

function test_region_lists_edit_inputs()
{
	$specs = pg_ad_region_list_columns();
	pg_assert_same('text', $specs['name']['editable']);
	pg_assert_same('select', $specs['display_type']['editable']);
	pg_assert_same('number', $specs['transition_duration']['editable'], 'a whole number gets the number input');
	pg_assert_false(isset($specs['created']['editable']), 'a date is computed');

	$printed = pg_list_table_printed(array('list' => 'view_regions_all_ad_regions', 'columns' => $specs, 'edit' => array('url' => 'view_regions.php?filter=all_ad_regions', 'columns' => pg_ad_region_edit_columns())));
	pg_assert_true(is_array($printed['source']['edit']), 'the list gets quick edit');

	foreach ($specs as $key => $spec) {
		pg_assert_true(trim((string) $spec['heading']) !== '', $key . ' has a label for the details pane');
	}
}

function test_region_lists_static_ad_region_hides_dynamic_values_but_edits_them()
{
	$cells = pg_ad_region_list_row_cells(region_field_lists_ad_region(), pg_ad_region_edit_columns());

	pg_assert_same('', $cells['transition_type']['html'], 'a static region shows no transition');
	pg_assert_same('', $cells['slideshow_interval']['html']);
	pg_assert_same('slide', $cells['transition_type']['attributes']['data-pg-edit-value'], 'the stored value is still edited');
	pg_assert_same('1', $cells['slideshow']['attributes']['data-pg-edit-value'], 'autoplay opens with the stored value');
	pg_assert_same('', $cells['transition_duration']['attributes']['data-pg-edit-value'], '0 is the default, shown empty');
	pg_assert_contains('data-pg-open', $cells['name']['html']);

	$dynamic = pg_ad_region_list_row_cells(region_field_lists_ad_region(array('display_type' => 'dynamic')), pg_ad_region_edit_columns());
	pg_assert_contains('task_alt', $dynamic['slideshow']['html']);
	pg_assert_same('5', $dynamic['slideshow_interval']['html']);

	$plain = pg_ad_region_list_row_cells(region_field_lists_ad_region(), array());
	pg_assert_false(isset($plain['name']['attributes']['data-pg-edit']), 'no quick edit, no field');
}

function test_region_lists_saved_answer()
{
	$cells = pg_ad_region_list_row_cells(region_field_lists_ad_region(array('display_type' => 'dynamic')), pg_ad_region_edit_columns());
	$answer = pg_region_list_saved($cells, 'display_type');

	pg_assert_true($answer['ok']);
	pg_assert_same('dynamic', $answer['value']);
	pg_assert_true(isset($answer['cells']['transition_type']), 'the other cells are redrawn');
	pg_assert_false(isset($answer['cells']['display_type']), 'the saved cell is not repeated');
}

function test_region_lists_shared_component_keeps_its_type()
{
	$edit = pg_content_region_edit_columns('common');
	$row = array('id' => 3, 'name' => 'Footer', 'type' => 'common', 'preview' => '<p>x</p>', 'timestamp' => 1700000000, 'username' => '', 'component' => 0);

	$cells = pg_content_region_list_cells('common', $row, $edit);
	pg_assert_same('common', $cells['type']['attributes']['data-pg-edit-value']);
	pg_assert_same('x', $cells['content']['html'], 'a common region previews its text');

	$cells = pg_content_region_list_cells('designer', array('type' => 'designer') + $row, $edit);
	pg_assert_same('&lt;p&gt;x&lt;/p&gt;', $cells['content']['html'], 'a designer region previews its code, escaped');

	$cells = pg_content_region_list_cells('common', array('component' => 9) + $row, $edit);
	pg_assert_false(isset($cells['type']['attributes']['data-pg-edit']), 'a shared component region keeps its type');

	pg_assert_false(isset(pg_content_region_edit_columns('dynamic')['type']), 'a dynamic region has no type');
	pg_assert_true(isset(pg_content_region_list_columns('dynamic')['code']));
}

function test_region_lists_text_preview()
{
	pg_assert_same('Web Site Yönetme', pg_region_list_text_preview("<h1 class=\"large\">Web Site Y&ouml;netme</h1>"), 'tags dropped, entities decoded then escaped');
	pg_assert_same('© Telif hakkı', pg_region_list_text_preview("<p style=\"text-align: center;\">&copy; Telif

  hakkı</p>"), 'whitespace collapsed');
	pg_assert_same('a b', pg_region_list_text_preview('<p>a</p><p>b</p>'), 'blocks keep a space between them');
	pg_assert_same('&lt;b&gt;', pg_region_list_text_preview('&lt;b&gt;'), 'decoded markup stays text');
	pg_assert_same(str_repeat('a', 50) . '...', pg_region_list_text_preview('<p>' . str_repeat('a', 60) . '</p>'));
	pg_assert_same('', pg_region_list_text_preview(null));
}

function test_region_lists_ad_columns()
{
	$edit = pg_ad_edit_columns(array(4 => 'Top', 5 => 'Side'));
	pg_assert_same(array('4' => 'Top', '5' => 'Side'), $edit['ad_region_id']['options']);

	$specs = pg_ad_list_columns($edit);
	pg_assert_false(isset($specs['display_type']['editable']), 'display type is the region\'s');
	pg_assert_same('number', $specs['sort_order']['editable']);
}

function test_field_lists_columns_by_form()
{
	$custom = pg_field_edit_columns('custom');
	pg_assert_true(isset($custom['rss_field']) && isset($custom['office_use_only']));

	$product = pg_field_edit_columns('product');
	pg_assert_false(isset($product['rss_field']) || isset($product['office_use_only']), 'custom form settings only on custom forms');

	$specs = pg_field_list_columns('product', $product);
	pg_assert_false(isset($specs['rss_field']));
	pg_assert_same('number', $specs['position']['kind']);
	pg_assert_false(isset($specs['type']['editable']), 'the type changes on the field screen');
	pg_assert_false(isset(pg_field_list_columns('custom', array())['name']['editable']));
}

function test_field_lists_information_field_cells()
{
	$row = array('id' => 2, 'name' => 'intro', 'rss_field' => '', 'label' => '', 'information' => '<p>Hello</p>', 'type' => 'information', 'required' => 0, 'office_use_only' => 0, 'timestamp' => 1700000000, 'username' => '');
	$cells = pg_field_list_cells($row, 1, 'edit_field.php?id=2', false, pg_field_edit_columns('custom'));

	pg_assert_false(isset($cells['label']['attributes']['data-pg-edit']), 'information text changes on the field screen');
	pg_assert_false(isset($cells['required']['attributes']['data-pg-edit']), 'an information field is never required');
	pg_assert_same('intro', $cells['name']['attributes']['data-pg-edit-value']);
	pg_assert_same('1', $cells['position']['attributes']['data-pg-sort-value']);

	$text = array('type' => 'text box', 'label' => 'Your <b>name</b>', 'required' => 1) + $row;
	$cells = pg_field_list_cells($text, 2, 'edit_field.php?id=2', true, array());
	pg_assert_contains('Your &lt;b&gt;name&lt;/b&gt;', $cells['label']['html'], 'a label is escaped');
	pg_assert_contains('^^intro^^', $cells['name']['html'], 'a designer form shows the variable');
	pg_assert_contains('task_alt', $cells['required']['html']);
}
