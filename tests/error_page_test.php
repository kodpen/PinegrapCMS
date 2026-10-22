<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the error page system widget (includes/fn/widgets_error.php):
 * which of its blocks a page keeps for an error, and the title of the screen
 * it draws for maintenance mode. Maintenance is the only 503 the software
 * sends (pg_maintenance_gate()); the starter sites title their error page
 * "Page Not Found", which is not what a visitor should read while the site is
 * being worked on.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_error_page_flags_for_a_missing_page()
{
	pg_assert_same(
		array('is_not_found' => true, 'is_maintenance' => false, 'is_other_error' => false, 'has_error_code' => true),
		pg_sw_error_flags(404, true));
}

function test_error_page_flags_for_another_error()
{
	pg_assert_same(
		array('is_not_found' => false, 'is_maintenance' => false, 'is_other_error' => true, 'has_error_code' => true),
		pg_sw_error_flags(500, true), 'a code');

	pg_assert_same(
		array('is_not_found' => false, 'is_maintenance' => false, 'is_other_error' => true, 'has_error_code' => false),
		pg_sw_error_flags(0, false), 'an error without a code');
}

// A page with a maintenance block shows maintenance there and only there. A
// page designed before that block existed has nowhere else to show it, so its
// "other error" block keeps showing maintenance as it always did.
function test_error_page_flags_for_maintenance()
{
	$with = pg_sw_error_flags(503, true);

	pg_assert_true($with['is_maintenance'], 'the maintenance block');
	pg_assert_false($with['is_other_error'], 'not the other error block as well');
	pg_assert_false($with['is_not_found'], 'not the missing page block');

	$without = pg_sw_error_flags(503, false);

	pg_assert_true($without['is_other_error'], 'a page without a maintenance block');
}

// Whether a tree holds an element shown only for a flag, however deep.
function test_error_page_tree_shows_a_flag()
{
	$block = array('type' => 'semantic', 'props' => array('_bindings' => array('eo_visible_if' => 'is_maintenance')), 'children' => array());
	$tree = array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'container', 'props' => array(), 'children' => array(
			array('type' => 'semantic', 'props' => array('_bindings' => array('eo_visible_if' => 'is_not_found')), 'children' => array()),
			array('type' => 'semantic', 'props' => array(), 'children' => array($block)),
		)),
	));

	pg_assert_true(pg_sw_error_tree_shows($tree, 'is_maintenance'), 'nested');
	pg_assert_true(pg_sw_error_tree_shows($tree, 'is_not_found'), 'beside it');
	pg_assert_false(pg_sw_error_tree_shows($tree, 'is_other_error'), 'a flag no element carries');
	pg_assert_false(pg_sw_error_tree_shows(array(), 'is_maintenance'), 'an empty tree');
	pg_assert_false(pg_sw_error_tree_shows(null, 'is_maintenance'), 'no tree');
}

// The screen of maintenance mode is titled for maintenance; every other error
// keeps the title of the page that shows it.
function test_error_page_title()
{
	pg_assert_same(lang('Down for Maintenance'), pg_sw_error_title(503, 'Page Not Found'), 'maintenance');
	pg_assert_same('Page Not Found', pg_sw_error_title(404, 'Page Not Found'), 'a missing page');
	pg_assert_same('Oops', pg_sw_error_title(500, 'Oops'), 'another error');
	pg_assert_same('', pg_sw_error_title(403, ''), 'a page without a title keeps looking elsewhere');
}

// The page renderer asks for the error screen's title before it writes the
// <title> tag, and only while an error is being shown.
function test_error_page_title_is_asked_by_the_page_renderer()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_page_content.php');

	$asked = strpos($source, 'pg_sw_error_title(');
	$written = strpos($source, "if (\$page_title != '') {\n        \$content = preg_replace('/<title>");

	pg_assert_true(($asked !== false) && ($written !== false) && ($asked < $written), 'before the title tag is written');
	pg_assert_true(preg_match('/\(\(\$pg_error_screen = pg_sw_error_context\(\)\) !== null\)\)\s*\{\s*\$page_title = pg_sw_error_title\(\$pg_error_screen\[\'code\'\], \$page_title\);/', $source) === 1, 'only on an error screen');
}

// The requested address: the visitor's own on the site, the page's address in
// a background render (the editor's Preview runs inside an api.php call).
function test_error_page_requested_url()
{
	pg_assert_same('/missing?x=1', pg_sw_error_requested_url(false, 'sw-w-28', '/missing?x=1', '/'), 'a visitor request');
	pg_assert_same('/sw-w-28', pg_sw_error_requested_url(true, 'sw-w-28', '/pinegrap/api.php', '/'), 'the preview');
	pg_assert_same('/site/hata%20sayfas%C4%B1', pg_sw_error_requested_url(true, 'hata sayfası', '/pinegrap/api.php', '/site/'), 'encoded under a sub-folder');
	pg_assert_same('/', pg_sw_error_requested_url(true, '', '/pinegrap/api.php', '/'), 'a page not saved yet');
}
