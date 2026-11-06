<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_sitemap_page_types() and pg_sitemap_unlisted_widget_types()
 * in includes/fn/seo.php: which pages sitemap.xml lists, and which designed
 * pages it leaves out because their system widget stands in for a page type
 * it never lists (get_sitemap_info.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The listed types are the ones the query has always read.
function test_sitemap_pages_listed_types()
{
	$types = pg_sitemap_page_types();

	foreach (array('standard', 'folder view', 'photo gallery', 'custom form', 'form list view', 'form view directory', 'calendar view', 'catalog', 'express order', 'order form', 'shopping cart', 'search results') as $type) {
		pg_assert_true(in_array($type, $types, true), $type);
	}

	foreach (array('error', 'login', 'logout', 'registration entrance', 'form item view', 'catalog detail', 'calendar event view', 'my account') as $type) {
		pg_assert_false(in_array($type, $types, true), $type);
	}
}

// A widget standing in for a type the sitemap leaves out makes its page
// unlisted; one standing in for a listed type does not.
function test_sitemap_pages_unlisted_widgets()
{
	$types = pg_sitemap_unlisted_widget_types(array(
		'logout'                => 'logout',
		'login'                 => 'login_form',
		'registration entrance' => 'registration',
		'search results'        => 'search_results',
		'shopping cart'         => 'shopping_cart',
		'calendar view'         => 'calendar_view',
		'calendar event view'   => 'calendar_event_view',
	));

	foreach (array('error_page', 'form_item_view', 'catalog_item_view', 'logout', 'login_form', 'registration', 'calendar_event_view') as $type) {
		pg_assert_true(in_array($type, $types, true), $type);
	}

	foreach (array('search_results', 'shopping_cart', 'calendar_view', 'form_list_view', 'catalog_listing') as $type) {
		pg_assert_false(in_array($type, $types, true), $type);
	}

	pg_assert_same(count($types), count(array_unique($types)), 'each once');
	pg_assert_same(array('error_page', 'form_item_view', 'catalog_item_view'), pg_sitemap_unlisted_widget_types(array()));
}

// The site's own map: every legacy type it names that the sitemap leaves out
// brings its widget along.
function test_sitemap_pages_site_widget_map()
{
	$types = pg_sitemap_unlisted_widget_types(pg_sw_page_type_widgets());

	foreach (pg_sw_page_type_widgets() as $page_type => $widget_type) {
		pg_assert_same(!in_array($page_type, pg_sitemap_page_types(), true), in_array($widget_type, $types, true), $page_type);
	}
}

// get_sitemap_info() reads its types from the helper and skips the pages
// those widgets mark, the home page excepted.
function test_sitemap_pages_builder_uses_helpers()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_sitemap_info.php');

	pg_assert_contains('pg_sitemap_page_types()', $source);
	pg_assert_contains('pg_sitemap_unlisted_widget_types(pg_sw_page_type_widgets())', $source);
	pg_assert_false(strpos($source, "(page_type = 'shopping cart')") !== false);

	$home = strpos($source, "if (\$page['home'] == 'yes') {");
	$skip = strpos($source, "!isset(\$unlisted_page_ids[(int) \$page['id']])");
	pg_assert_true(($home !== false) && ($skip !== false) && ($home < $skip));
}

// A legacy form list view filter compared with the viewer (no fixed value,
// dynamic value viewer or viewers email address) marks the list as one
// whose records differ per visitor; any other filter does not.
function test_sitemap_pages_legacy_filters_compare_viewer()
{
	pg_assert_true(pg_sitemap_legacy_filters_compare_viewer(array(
		array('form_field_id' => '0', 'standard_field' => 'submitter', 'operator' => 'is equal to', 'value' => '', 'dynamic_value' => 'viewer'),
	)), 'submitter is the viewer');
	pg_assert_true(pg_sitemap_legacy_filters_compare_viewer(array(
		array('form_field_id' => '0', 'standard_field' => 'status', 'operator' => 'is equal to', 'value' => 'Live', 'dynamic_value' => ''),
		array('form_field_id' => '12', 'standard_field' => '', 'operator' => 'is equal to', 'value' => '', 'dynamic_value' => 'viewers email address'),
	)), 'a custom field holds the viewer\'s e-mail');
	pg_assert_false(pg_sitemap_legacy_filters_compare_viewer(array(
		array('form_field_id' => '12', 'standard_field' => '', 'operator' => 'is equal to', 'value' => 'jane', 'dynamic_value' => 'viewer'),
	)), 'a fixed value wins over the dynamic one');
	pg_assert_false(pg_sitemap_legacy_filters_compare_viewer(array(
		array('form_field_id' => '0', 'standard_field' => 'submitted_date_and_time', 'operator' => 'is greater than', 'value' => '', 'dynamic_value' => 'days ago'),
	)), 'a date filter');
	pg_assert_false(pg_sitemap_legacy_filters_compare_viewer(array()), 'no filters');
	pg_assert_false(pg_sitemap_legacy_filters_compare_viewer(null), 'not a list');
}

// The kept sitemap is shared by every visitor and drawn by whoever asks
// first (a crawler, a panel save through update_sitemap_and_ping()), so a
// list whose filters compare a field with the viewer lists none of its
// records: both branches skip it, as the search index does.
function test_sitemap_pages_builder_skips_viewer_lists()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_sitemap_info.php');

	pg_assert_contains('pg_sitemap_legacy_filters_compare_viewer($filters)', $source);
	pg_assert_contains('pg_sw_filters_compare_viewer(pg_sw_merge_record_filters($cfg, $record_view[\'cfg\']))', $source);
}
