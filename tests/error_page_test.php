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

function test_error_panel_link_only_for_panel_users_on_panel_scripts()
{
	$link = pg_error_panel_link('/pinegrap/erp_reconciliation.php', 'pinegrap', 2);

	pg_assert_true(substr($link, -strlen('pinegrap/welcome.php')) === 'pinegrap/welcome.php', 'a manager on a panel screen gets the dashboard');
	pg_assert_same($link, pg_error_panel_link('/shop/pinegrap/edit_page.php', 'pinegrap', '0'), 'an administrator, role as stored text');
	pg_assert_same('', pg_error_panel_link('/pinegrap/edit_page.php', 'pinegrap', 3), 'role 3 includes site members');
	pg_assert_same('', pg_error_panel_link('/pinegrap/edit_page.php', 'pinegrap', null), 'nobody signed in');
	pg_assert_same('', pg_error_panel_link('/pinegrap/get_page.php', 'pinegrap', 0), 'the page renderer: a site page error');
	pg_assert_same('', pg_error_panel_link('/index.php', 'pinegrap', 0), 'outside the software directory');
	pg_assert_same('', pg_error_panel_link('/pinegrap/edit_page.php', '', 0), 'no software directory known');
}

// The body of function $name in $source, up to its closing brace at the
// start of a line.
function pg_test_error_page_function_body($source, $name)
{
	$start = strpos($source, 'function ' . $name . '(');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\n}", $start);

	return substr($source, $start, ($end === false ? strlen($source) : $end) - $start);
}

// A panel screen's error ("Record not found." on Edit Page, Edit Short Link,
// Edit Calendar Event ...) keeps the screen's own message and a way back to
// the panel. On a site with a designed error page it was drawn as that
// page's 404 block in the shop's design, with neither (#9024).
function test_error_panel_card_keeps_the_message_and_the_way_back()
{
	$message = lang('Record not found.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.';
	$card = pg_error_panel_card($message, '/pinegrap/welcome.php');

	pg_assert_contains($message, $card, 'the screen\'s message, with its own link');
	pg_assert_contains('href="/pinegrap/welcome.php"', $card, 'the way back to the panel');
	pg_assert_contains(h(lang('Back to the control panel')), $card, 'named as before');
	pg_assert_contains('<h1 class="h4 mb-5"><i class="bi bi-exclamation-triangle me-3" aria-hidden="true"></i>' . h(lang('Error')) . '</h1>', $card, 'headed as an error');
	pg_assert_contains('<div class="card mx-auto', $card, 'on the card of the sign-in screens without a site page');
	pg_assert_same(1, substr_count($card, '<a class="btn'), 'one button');
	pg_assert_contains('href="/a&quot;b/welcome.php"', pg_error_panel_card('x', '/a"b/welcome.php'), 'the address is escaped');
}

// output_error() draws a panel screen's error in the panel's own frame
// before the designed error page hears of it, and only past the database
// check: a failed query still prints the bare message, which carries the
// way back too.
function test_output_error_draws_a_panel_error_in_the_panel()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/core.php');
	$body = pg_test_error_page_function_body($source, 'output_error');
	$panel = strpos($body, "if (\$panel_link !== '') {\n            echo pg_error_panel_screen(\$error_message, \$panel_link);\n            exit();");

	pg_assert_true($panel !== false, 'a panel link draws the panel screen and stops');
	pg_assert_true($panel > strpos($body, "if (!db::\$con or \$mysql_error != '') {"), 'after the database check');
	pg_assert_true($panel < strpos($body, 'pg_sw_error_context('), 'before the designed error page is told of the error');
	pg_assert_true($panel < strpos($body, 'get_error_screen('), 'so the site\'s error page is never drawn for it');
	pg_assert_contains("\$content .= '<p><a href=\"' . h(\$panel_link) . '\">'", $body, 'the bare message keeps the way back');

	$screen = pg_test_error_page_function_body($source, 'pg_error_panel_screen');

	pg_assert_contains("output_header_secure(array('title' => lang('Error'), 'icon' => 'welcome'))", $screen, 'the panel\'s own frame');
	pg_assert_contains('pg_error_panel_card($error_message, $panel_link)', $screen, 'around the card');
	pg_assert_contains('output_footer_secure()', $screen, 'closed');
}
