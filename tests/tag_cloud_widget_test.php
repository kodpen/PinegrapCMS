<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Tag Cloud system widget (includes/fn/widgets_tag_cloud.php):
 * the order, sizes, weights and links of its keywords against the classic
 * <tcloud> region, and the places the kind is registered in.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_tag_cloud_widget_rows()
{
	// Most used first, as the query returns them.
	$rows = pg_tcw_rows(array(
		array('keyword' => 'shoes', 'count' => '9'),
		array('keyword' => 'bags', 'count' => '5'),
		array('keyword' => 'red hat', 'count' => '1'),
	), 'search');
	pg_assert_same(array('bags', 'red hat', 'shoes'), array_column($rows, '__tag_name'), 'alphabetical, as the classic cloud');
	pg_assert_same(array('150', '100', '200'), array_column($rows, '__tag_size'), 'sized from 100% to 200%');
	pg_assert_same(array('3', '1', '5'), array_column($rows, '__tag_weight'), 'weights 1 to 5');
	pg_assert_contains('search?query=red+hat', $rows[1]['__tag_url'], 'the search link');
	pg_assert_same('9', $rows[2]['__tag_count'], 'the count');
}

function test_tag_cloud_widget_rows_edges()
{
	$same = pg_tcw_rows(array(array('keyword' => 'a', 'count' => 3), array('keyword' => 'b', 'count' => 3)), 'p');
	pg_assert_same(array('100', '100'), array_column($same, '__tag_size'), 'one count: every keyword 100%, as the classic step');
	pg_assert_same(array('1', '1'), array_column($same, '__tag_weight'), 'and weight 1');
	pg_assert_same(array(), pg_tcw_rows(array(), 'p'), 'no keywords');
	pg_assert_same(array(), pg_tcw_rows(null, 'p'), 'nothing read');

	$tr = pg_tcw_rows(array(array('keyword' => 'shoes', 'count' => 1)), 'p', function ($w) { return 'ayakkabı'; });
	pg_assert_same('ayakkabı', $tr[0]['__tag_name'], 'the word shown is translated');
	pg_assert_same('shoes', $tr[0]['__tag_keyword'], 'the stored keyword stays');
	pg_assert_contains('?query=shoes', $tr[0]['__tag_url'], 'and is what the link searches');
}

function test_tag_cloud_widget_registered()
{
	$labels = pg_sw_type_labels();
	pg_assert_true(isset($labels['tag_cloud_region']), 'panel label');
	$designer = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/designer.php');
	pg_assert_contains("_render_system_widget_tag_cloud_region(", $designer, 'dispatched by _expand_system_widgets()');
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_contains("{ type: 'tag_cloud_region'", $js, 'in SW_TYPES');
	pg_assert_contains("case 'tag_cloud_region': {", $js, 'has a starter tree');
	pg_assert_contains("tag_cloud_region:  SW_TAG_CLOUD_TOKEN_GROUPS", $js, 'has a token palette');
	pg_assert_contains("ad_region: 1, tag_cloud_region: 1", $js, 'draws no Messages block');
}

function test_tag_cloud_widget_merge_keywords()
{
	$rows = pg_tcw_merge_keywords(
		array('coffee' => 3, 'tea' => 1),
		array('Tea, cake , cake', 'cake', ' , ', 'coffee')
	);
	pg_assert_same(
		array(array('keyword' => 'coffee', 'count' => 4), array('keyword' => 'cake', 'count' => 2), array('keyword' => 'tea', 'count' => 2)),
		$rows,
		'pages counted, each item once per keyword, case folded, most used first then by keyword'
	);
	$many = array();
	for ($i = 0; $i < 30; $i++) $many[] = 'k' . $i;
	pg_assert_same(25, count(pg_tcw_merge_keywords(array(), $many)), 'the 25 used most');
	pg_assert_same(array(), pg_tcw_merge_keywords(array(), array()), 'no keywords');
}

function test_tag_cloud_widget_search_widget_pages()
{
	$root = dirname(__DIR__) . '/pinegrap/';
	$designer = (string)file_get_contents($root . 'includes/fn/designer.php');
	pg_assert_contains("pg_sw_widget_pages('search_results')", $designer, 'the editor lists the pages built with the Search Results widget');
	$js = (string)file_get_contents($root . 'assets/js/style_designer.src.js');
	pg_assert_contains('regionData.tag_cloud_widget_pages', $js, 'the Tag Cloud picker offers them');
	$widget = (string)file_get_contents($root . 'includes/fn/widgets_tag_cloud.php');
	pg_assert_contains('pg_tcw_widget_search_page($name)', $widget, 'the widget reads a Search Results widget page');
}
