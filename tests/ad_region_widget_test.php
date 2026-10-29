<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Ad Region system widget (includes/fn/widgets_ad_region.php):
 * which ads a region shows and in which order, the carousel's rotation
 * values, the first ad marked active, and the places the kind is
 * registered in.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _aw_ads($orders)
{
	$out = array();
	foreach ($orders as $i => $order) {
		$out[] = array('id' => $i + 1, 'name' => 'Ad ' . ($i + 1), 'content' => '<p>x</p>', 'caption' => '', 'label' => '', 'sort_order' => $order);
	}
	return $out;
}

function test_ad_region_widget_pick()
{
	$reverse = function ($list) { return array_reverse($list); };
	pg_assert_same(array(3), array_column(pg_aw_pick(_aw_ads(array(1, 2, 3)), 'static', $reverse), 'id'), 'a static region shows one ad, picked at random');
	pg_assert_same(array(1, 2, 3), array_column(pg_aw_pick(_aw_ads(array(1, 2, 3)), 'dynamic', $reverse), 'id'), 'a dynamic region keeps the sort order');
	pg_assert_same(array(3, 2, 1), array_column(pg_aw_pick(_aw_ads(array(0, 0, 0)), 'dynamic', $reverse), 'id'), 'and shuffles when no ad has one');
	pg_assert_same(array(), pg_aw_pick(array(), 'static'), 'no ads');
	pg_assert_same(1, count(pg_aw_pick(_aw_ads(array(0, 0)), 'static')), 'the real shuffle picks one');
}

function test_ad_region_widget_rotation()
{
	pg_assert_same(array('__ad_ride' => 'carousel', '__ad_interval' => '4000', '__ad_wrap' => 'true'),
		pg_aw_rotation(array('slideshow' => 1, 'slideshow_interval' => 4, 'slideshow_continuous' => 1)), 'a continuous slideshow');
	pg_assert_same('false', pg_aw_rotation(array('slideshow' => 1, 'slideshow_interval' => 0, 'slideshow_continuous' => 0))['__ad_wrap'], 'stops after the last ad');
	pg_assert_same('1000', pg_aw_rotation(array('slideshow' => 1, 'slideshow_interval' => 0, 'slideshow_continuous' => 1))['__ad_interval'], 'at least a second');
	pg_assert_same(array('__ad_ride' => 'false', '__ad_interval' => 'false', '__ad_wrap' => 'true'), pg_aw_rotation(null), 'no slideshow: the visitor turns it');
}

function test_ad_region_widget_first_active()
{
	pg_assert_same('<div class="carousel-item active" id="a"><p>x</p></div>', pg_aw_mark_first_active('<div class="carousel-item" id="a"><p>x</p></div>'), 'the first carousel item is active');
	pg_assert_same('<!--c--><div class="carousel-item active">', pg_aw_mark_first_active('<!--c--><div class="carousel-item">'), 'after a leading comment');
	pg_assert_same('<div class="card"><div class="carousel-item">', pg_aw_mark_first_active('<div class="card"><div class="carousel-item">'), 'only the row\'s first element');
	pg_assert_same('<div class="carousel-item active">', pg_aw_mark_first_active('<div class="carousel-item active">'), 'once');
}

// The carousel's data-bs-* attributes take the rotation through the
// widget's attribute values, the ad's markup stays raw.
function test_ad_region_widget_attributes_and_markup()
{
	$tree = array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'semantic', 'props' => array('tag' => 'div', 'cssClass' => 'carousel slide',
			'_attrs' => array(array('name' => 'data-bs-interval', 'value' => '{{__ad_interval}}'))), 'children' => array(
			array('type' => 'semantic', 'props' => array('tag' => 'div', 'text' => 'x', '_bindings' => array('text' => '__ad_content_html')), 'children' => array()),
		)),
	));
	$scope = pg_sw_finish_open(array('sid' => 93, 'kind' => 'ad_region', 'email' => false));
	try {
		$html = trim(_render_tree_node($tree, 0, 0));
		$html = pg_sw_sweep_tokens(_pg_sw_fill_tokens($html, pg_sw_escape_token_values(pg_aw_rotation(array('slideshow' => 1, 'slideshow_interval' => 5, 'slideshow_continuous' => 1))
			+ array('__ad_content_html' => '<a href="/x">Sale</a>'))));
		$html = pg_sw_finish_widget_html($html, $scope);
	} finally {
		pg_sw_finish_close();
	}
	pg_assert_contains('data-bs-interval="5000"', $html, 'the interval');
	pg_assert_contains('<a href="/x">Sale</a>', $html, 'the ad\'s markup');
}

function test_ad_region_widget_registered()
{
	$labels = pg_sw_type_labels();
	pg_assert_true(isset($labels['ad_region']), 'panel label');
	pg_assert_true(isset(pg_sw_raw_token_keys()['__ad_content_html']), 'the ad\'s markup is raw');
	$designer = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/designer.php');
	pg_assert_contains("_render_system_widget_ad_region(", $designer, 'dispatched by _expand_system_widgets()');
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_contains("{ type: 'ad_region'", $js, 'in SW_TYPES');
	pg_assert_contains("case 'ad_region': {", $js, 'has a starter tree');
	pg_assert_contains("ad_region:         SW_AD_REGION_TOKEN_GROUPS", $js, 'has a token palette');
	pg_assert_contains("menu_sequence_region: 1, ad_region: 1,", $js, 'draws no Messages block');
}
