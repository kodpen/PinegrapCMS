<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that panel screens fit a 375 px phone: a toolbar filter form and its
 * input-group never grow past the bar, Edit Page, Edit Comment and Edit Theme
 * File draw their facts in the toolbar's grow slot (no line above the bar and
 * no second heading), and the Subscriptions date range wraps.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function panel_narrow_layout_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function test_panel_narrow_layout_toolbar_filter_is_capped_by_the_bar()
{
	foreach (array('assets/css/backend.src.css', 'assets/css/backend.min.css') as $file) {
		$css = panel_narrow_layout_test_source($file);

		pg_assert_true(preg_match('/\.pg-toolbar-filter\s*,\s*\.pg-toolbar-filter\s*>\s*\.input-group\s*,\s*\.pg-toolbar-filter\s+select\s*\{[^}]*min-width:\s*0[^}]*max-width:\s*100%/', $css) === 1, $file . ': filter form, group and select shrink to the bar');
	}
}

function test_panel_narrow_layout_facts_sit_in_the_grow_slot()
{
	$page = panel_narrow_layout_test_source('edit_page.php');
	pg_assert_true(preg_match("/pg_screen_toolbar\(array\(\s*'secondary'[^;]*'grow'\s*=>/s", $page) === 1, 'Edit Page passes its facts as the grow slot');
	pg_assert_true(strpos($page, '<div class="text-center md:text-start">') === false, 'Edit Page draws no fact block above the bar');

	$comment = panel_narrow_layout_test_source('edit_comment.php');
	pg_assert_true(preg_match("/pg_screen_toolbar\(array\(\s*'secondary'[^;]*'grow'\s*=>/s", $comment) === 1, 'Edit Comment passes its facts as the grow slot');
	pg_assert_true(strpos($comment, '<div class="mb-3 text-center md:text-start">') === false, 'Edit Comment draws no fact block above the bar');

	$theme = panel_narrow_layout_test_source('edit_theme_file.php');
	pg_assert_true(preg_match("/pg_screen_toolbar\(array\(\s*'secondary'[^;]*'grow'\s*=>/s", $theme) === 1, 'Edit Theme File passes its facts as the grow slot');
	pg_assert_true(strpos($theme, '<h2') === false, 'Edit Theme File has no second heading');
}

function test_panel_narrow_layout_grow_only_bar_is_drawn()
{
	$bar = pg_screen_toolbar(array('grow' => '<p class="mb-0">Fact</p>'));

	pg_assert_true(strpos($bar, '<nav id="button_bar"') === 0, 'a bar with only a fact is drawn');
	pg_assert_true(strpos($bar, '<div class="pg-toolbar-grow"><p class="mb-0">Fact</p></div>') !== false, 'the fact sits in the grow slot');
}

function test_panel_narrow_layout_subscription_dates_wrap()
{
	$source = panel_narrow_layout_test_source('view_subscriptions.php');

	pg_assert_true(preg_match('/<span class="[^"]*\bflex-wrap\b[^"]*\bmax-w-100\b[^"]*">\s*<input type="date" data-pg-date name="next_from"/', $source) === 1, 'the date range wraps inside the bar');
}
