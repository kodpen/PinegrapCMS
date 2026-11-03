<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a system widget drawn twice on one page (a header and an
 * offcanvas menu): the ids of its main loop's rows take the placement's
 * suffix on the second drawing, as its static part does, so no id or toggle
 * target is shared between the copies (pg_sw_widget_drawn_before(),
 * pg_sw_uniquify_loop_row_ids() in includes/fn/widgets.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The id values of $html.
function _repeat_placement_ids($html)
{
	preg_match_all('/\bid="([^"]+)"/', (string) $html, $m);
	return $m[1];
}

function test_repeat_placement_drawn_before()
{
	pg_assert_false(pg_sw_widget_drawn_before(990101), 'first drawing');
	pg_assert_true(pg_sw_widget_drawn_before(990101), 'second drawing');
	pg_assert_false(pg_sw_widget_drawn_before(990102), 'another widget is counted on its own');
}

function test_repeat_placement_loop_row_ids()
{
	$row = '<button data-bs-target="#more" aria-controls="more"></button><div id="more"></div>';
	pg_assert_same(pg_sw_uniquify_row_ids($row, 5, 3), pg_sw_uniquify_loop_row_ids($row, 5, 3, 0), 'first drawing: the ids it always had');
	$again = pg_sw_uniquify_loop_row_ids($row, 5, 3, 2);
	pg_assert_contains('id="more_pgw5r3_pgw5r1002"', $again, 'drawn again: the placement suffix follows the row suffix');
	pg_assert_contains('data-bs-target="#more_pgw5r3_pgw5r1002"', $again, 'the toggle target moves with the id');
	pg_assert_contains('aria-controls="more_pgw5r3_pgw5r1002"', $again, 'aria-controls too');
}

// A Google reviews widget whose review rows carry a collapse, drawn twice:
// the two copies share no id.
function test_repeat_placement_google_reviews_render()
{
	$tree = json_encode(array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'semantic', 'props' => array('tag' => 'h3', 'id' => 'head', 'text' => 'x', '_bindings' => array('text' => '__place_name')), 'children' => array()),
		array('type' => 'loop_area', 'props' => array(), 'children' => array(
			array('type' => 'semantic', 'props' => array('tag' => 'button', 'text' => 'More',
				'_attrs' => array(array('name' => 'data-bs-toggle', 'value' => 'collapse'), array('name' => 'data-bs-target', 'value' => '#more'))), 'children' => array()),
			array('type' => 'semantic', 'props' => array('tag' => 'p', 'id' => 'more', 'cssClass' => 'collapse', 'text' => 'x', '_bindings' => array('text' => '__review_text')), 'children' => array()),
		)),
	)));
	$place = array('name' => 'Place', 'rating' => 4.5, 'count' => 2, 'maps_url' => '', 'reviews' => array(
		array('author' => 'A', 'author_url' => '', 'photo' => '', 'rating' => 5, 'text' => 'One', 'relative' => '', 'url' => ''),
		array('author' => 'B', 'author_url' => '', 'photo' => '', 'rating' => 4, 'text' => 'Two', 'relative' => '', 'url' => ''),
	));

	$first  = _render_system_widget_google_reviews($tree, 990103, array(), 'preview', $place);
	$second = _render_system_widget_google_reviews($tree, 990103, array(), 'preview', $place);
	$a = _repeat_placement_ids($first);
	$b = _repeat_placement_ids($second);
	pg_assert_same(3, count($a), 'the heading and a collapse per review');
	pg_assert_same(3, count(array_unique($a)), 'unique within a copy');
	pg_assert_same(array(), array_values(array_intersect($a, $b)), 'no id shared between the copies');
	pg_assert_contains('id="more_pgw990103r1"', $first, 'the first copy keeps its row ids');
	foreach ($b as $id) {
		if (strpos($id, 'more_') !== 0) continue;
		pg_assert_contains('data-bs-target="#' . $id . '"', $second, 'the second copy\'s toggle opens its own row: ' . $id);
	}
}

// A folder view drawn twice (no page in the test, so only its static part
// draws): the copies share no id.
function test_repeat_placement_folder_view_render()
{
	$tree = json_encode(array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'semantic', 'props' => array('tag' => 'button', 'text' => 'Open',
			'_attrs' => array(array('name' => 'data-bs-toggle', 'value' => 'collapse'), array('name' => 'data-bs-target', 'value' => '#fvbody'))), 'children' => array()),
		array('type' => 'semantic', 'props' => array('tag' => 'div', 'id' => 'fvbody', 'cssClass' => 'collapse', 'text' => 'x'), 'children' => array()),
	)));

	$first  = _render_system_widget_folder_view($tree, 990104, array(), 'preview');
	$second = _render_system_widget_folder_view($tree, 990104, array(), 'preview');
	$a = _repeat_placement_ids($first);
	$b = _repeat_placement_ids($second);
	pg_assert_true(count($a) > 0, 'the copy has ids');
	pg_assert_same(array(), array_values(array_intersect($a, $b)), 'no id shared between the copies');
	foreach ($b as $id) {
		if (strpos($id, 'fvbody') !== 0) continue;
		pg_assert_contains('data-bs-target="#' . $id . '"', $second, 'the second copy\'s toggle opens its own body: ' . $id);
	}
}
