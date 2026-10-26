<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pages of a design in SQL: pg_page_visual_design_sql_for()
 * builds the same test as pg_page_is_visual_design() (the page's own style
 * is a visual design, or the page carries its own tree), never layout_type,
 * so a converted classic page with a custom layout is listed, counted and
 * cleaned with the rest of its design.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_page_of_design_sql_styles_and_tree()
{
	pg_assert_same(
		"(page.page_style IN (3,7) OR (page.page_tree_json IS NOT NULL AND page.page_tree_json <> ''))",
		pg_page_visual_design_sql_for(array(3, 7), true));
}

function test_page_of_design_sql_before_tree_column()
{
	// page_tree_json does not exist before its upgrade step: never name it.
	pg_assert_same('(page.page_style IN (3))', pg_page_visual_design_sql_for(array(3), false));
}

function test_page_of_design_sql_nothing_matches()
{
	pg_assert_same('0', pg_page_visual_design_sql_for(array(), false));
	pg_assert_same('0', pg_page_visual_design_sql_for(array(0, '', 'x'), false));
}

function test_page_of_design_sql_ids_cleaned()
{
	pg_assert_same('(page.page_style IN (5,2))', pg_page_visual_design_sql_for(array('5', 5, "2' OR 1", 0), false));
}

function test_page_of_design_sql_prefix()
{
	pg_assert_same('(p2.page_style IN (4))', pg_page_visual_design_sql_for(array(4), false, 'p2.'));
	pg_assert_same('(page_style IN (4))', pg_page_visual_design_sql_for(array(4), false, ''));
	pg_assert_same('(page.page_style IN (4))', pg_page_visual_design_sql_for(array(4), false, "page.' --"));
}

function test_page_of_design_sql_never_layout_type()
{
	pg_assert_false(strpos(pg_page_visual_design_sql_for(array(1), true), 'layout_type') !== false);
}

// The queries that read "the pages of a design" use the helper: a custom
// layout page on a design is one of its pages.
function test_page_of_design_queries_skip_layout_type()
{
	$files = array(
		'includes/designer_screen.php',
		'includes/panel/designer.php',
		'includes/panel/shared_component.php',
		'includes/designer_collab.php',
		'includes/fn/prototype.php',
		'includes/api/resources/design.php',
		'includes/fn/language_redirect.php',
		'duplicate_style.php',
	);
	foreach ($files as $file) {
		$src = file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		pg_assert_false(strpos($src, "layout_type = 'system'") !== false, $file);
	}

	// designer.php keeps one: the legacy bridge that finds a page with no
	// style by the design's name.
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/designer.php');
	pg_assert_same(1, preg_match_all("/AND layout_type = 'system'/", $src));
	pg_assert_contains("AND (page_style = 0 OR page_style IS NULL)", $src);
}
