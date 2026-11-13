<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that panel screens fit a 375 px phone: a toolbar filter form and its
 * input-group never grow past the bar, Edit Theme File draws its facts in
 * the toolbar's grow slot and Edit Page and Edit Comment in their status column
 * (no line above the bar and no second heading), the Subscriptions date
 * range wraps, a scrolling table holds its hidden text and the Verified
 * Shipping Addresses state filter shrinks to the bar.
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
	// Edit Page reads its facts beside the form, in the status column's
	// summary card (edit-screen-wide-layout), not in the bar.
	$page = panel_narrow_layout_test_source('edit_page.php');
	pg_assert_true(preg_match("/pg_screen_toolbar\(array\(\s*'secondary'[^;]*'grow'\s*=>/s", $page) === 0, 'Edit Page keeps its facts out of the bar');
	pg_assert_true(preg_match('/\$output_summary_card =[^;]*lang\(\'Access\'\)[^;]*\$output_subnav_page_type[^;]*\$output_subnav_short_link/s', $page) === 1, 'Edit Page lists its facts in the summary card');
	pg_assert_true(preg_match('/<div class="col-12 lg:col-4 2xl:col-3 order-2">\s*<!--[^>]*-->\s*<div class="position-sticky mt-7" style="top:3\.3rem;">\s*\' \. \$output_page_type_selector \. \'\s*\' \. \$output_summary_card \. \'/', $page) === 1, 'the summary card sits in the sticky status box');
	pg_assert_true(strpos($page, '<div class="text-center md:text-start">') === false, 'Edit Page draws no fact block above the bar');

	$comment = panel_narrow_layout_test_source('edit_comment.php');
	pg_assert_true(preg_match("/pg_screen_toolbar\(array\(\s*'secondary'[^;]*'grow'\s*=>/s", $comment) === 0, 'Edit Comment keeps its facts out of the bar');
	pg_assert_true(preg_match('/<div class="col-12 lg:col-4 2xl:col-3 order-2">\s*<!--[^>]*-->\s*<div class="position-sticky" style="top:3\.3rem;">.*\$output_summary_rows/s', $comment) === 1, 'Edit Comment lists its facts in the sticky status column');
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

function test_panel_narrow_layout_subscription_dates_fit()
{
	$source = panel_narrow_layout_test_source('view_subscriptions.php');

	// The range never breaks between its dates (a dash left after the first
	// one on a phone, #5746): it stays on one line and its boxes shrink.
	pg_assert_true(preg_match('/<span class="[^"]*\bflex-nowrap\b[^"]*\bmax-w-100\b[^"]*">\s*<input type="date" data-pg-date name="next_from"/', $source) === 1, 'the date range stays on one line inside the bar');
	pg_assert_same(2, substr_count($source, 'form-control-sm w-auto flex-fill min-w-0'), 'both date boxes shrink to fit a narrow screen');
}

function test_panel_narrow_layout_scrolling_table_holds_hidden_text()
{
	// A .visually-hidden heading or label is position: absolute. Its
	// containing block has to be the scroll box itself, or the box's overflow
	// does not clip it and the page scrolls sideways (#9079: Edit User's store
	// credit cards, Visitors' landing pages).
	foreach (array('assets/css/backend.src.css', 'assets/css/backend.min.css') as $file) {
		$css = panel_narrow_layout_test_source($file);

		pg_assert_true(preg_match('/(^|[{}\s])\.table-responsive\s*\{[^}]*position:\s*relative/', $css) === 1, $file . ': every scrolling table is positioned');
		pg_assert_true(strpos($css, '.table-responsive:has(') === false, $file . ': not only the tables holding a combobox');
	}
}

function test_panel_narrow_layout_verified_address_state_filter_fits()
{
	$source = panel_narrow_layout_test_source('view_verified_shipping_addresses.php');

	// Without the toolbar filter cap the select keeps a flex item's automatic
	// minimum, its longest state name, and ran 38 px off a 390 px screen
	// (#9080, #5723 back).
	pg_assert_true(preg_match('/<form id="state_filter"[^>]*class="[^"]*\bpg-toolbar-filter\b/', $source) === 1, 'the state filter is a toolbar filter form');
	pg_assert_true(preg_match('/<label for="state_id" class="[^"]*\btext-nowrap\b/', $source) === 1, 'the label stays on one line beside the select');
}
