<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Generate Layout screen layout (generate_layout.php,
 * includes/templates/generate_layout.php): the read-only code in a form
 * column, a Summary in a sticky status column written after it, Copy and
 * Edit Layout in the sticky save bar.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function generate_layout_screen_template()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/templates/generate_layout.php');
}

function test_generate_layout_has_form_status_and_save_bar_columns()
{
	$source = generate_layout_screen_template();

	$form = strpos($source, '<div class="col-12 lg:col-8 2xl:col-9 order-1">');
	$status = strpos($source, '<div class="col-12 lg:col-4 2xl:col-3 order-2">');
	$bar = strpos($source, '<div class="col-12 order-3 position-sticky" style="bottom:0; z-index:3;">');

	pg_assert_true($form !== false && $status !== false && $bar !== false, 'form column, status column and save bar are there');
	pg_assert_true($form < $status && $status < $bar, 'form column, status column, save bar in source order');
	pg_assert_contains('<div class="position-sticky" style="top:3.3rem;">', $source, 'the status box sticks below the top bar');

	$code = strpos($source, "'name' => 'layout'");
	pg_assert_true($code !== false && $code > $form && $code < $status, 'the layout code sits in the form column');

	$status_markup = substr($source, $status, $bar - $status);
	pg_assert_contains("lang('Summary')", $status_markup, 'the status column holds the Summary');
	pg_assert_contains("lang('Page Type')", $status_markup, 'with the page type');
	pg_assert_contains("lang('Lines')", $status_markup, 'and the code length');
}

function test_generate_layout_save_bar_copies_and_links_edit_layout()
{
	$source = generate_layout_screen_template();
	$bar = (int) strpos($source, '<div class="col-12 order-3 position-sticky"');
	$bar_markup = substr($source, $bar);

	pg_assert_contains('id="generate_layout_copy"', $bar_markup, 'Copy the code is in the save bar');
	pg_assert_contains("<?php if (\$edit_layout_url !== ''): ?>", $bar_markup, 'Edit Layout only when the page has a custom layout');
	pg_assert_false(strpos($source, "lang('Cancel')") !== false, 'no Cancel copy: the back arrow goes back');

	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/generate_layout.php');
	pg_assert_contains("if (\$page['layout_type'] === 'custom')", $screen, 'the Edit Layout target is built for a custom layout only');
	pg_assert_contains('pg_layout_edit_url(', $screen, 'the same target as the Edit Page link');
}
