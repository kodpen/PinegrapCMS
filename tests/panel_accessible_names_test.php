<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for accessible names in the panel: dialog and drawer close
 * buttons and icon-only controls carry a translated aria-label, the settings
 * autofill decoy stays out of the tab order, and the marketplace products
 * screen's inline style sits in a cascade layer so the d-none utility wins.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function panel_accessible_names_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

function test_panel_accessible_names_close_buttons()
{
	$paths = array(
		'cloudflare.php',
		'marketplace_settings.php',
		'view_parasut_inbox.php',
		'includes/designer_screen.php',
		'assets/js/image_editor_modal.js',
		'view_folders.php',
	);

	foreach ($paths as $path) {
		$source = panel_accessible_names_source($path);
		pg_assert_true((bool) preg_match_all('/<button[^>]*class="btn-close[^"]*"[^>]*>/', $source, $matches), $path . ': no close button found');

		foreach ($matches[0] as $tag) {
			pg_assert_contains('aria-label=', $tag, $path);
		}
	}

	pg_assert_contains("'Close' => lang('Close')", panel_accessible_names_source('includes/fn/editor.php'));
	pg_assert_contains("aria-label=\"' + t('Close') + '\"", panel_accessible_names_source('assets/js/image_editor_modal.js'));
	pg_assert_contains("aria-label=\"' + L.close + '\"", panel_accessible_names_source('view_folders.php'));
}

function test_panel_accessible_names_icon_buttons()
{
	$expect = array(
		'cloudflare.php' => array("aria-label=\"' . h(lang('Edit')) . '\"", "aria-label=\"' . h(lang('Delete')) . '\""),
		'view_parasut_inbox.php' => array("aria-label=\"' . h(lang('Invoice Detail')) . '\""),
		'image_editor_edit.php' => array("id=\"bd-theme\" type=\"button\" aria-label=\"' . h(lang('Software Theme')) . '\""),
		'editor_select_image.php' => array("onclick=\"esiUnsplashSearch()\" aria-label=\"' . h(lang('Search')) . '\""),
		'erp_account_prices.php' => array("<button type=\"submit\" class=\"btn btn-outline theme-secondary\" aria-label=\"' . h(lang('Search')) . '\">"),
		'view_performance_log.php' => array("<button type=\"submit\" class=\"btn btn-sm btn-solid theme-primary\" aria-label=\"' . h(lang('Search')) . '\">"),
		'includes/settings/screen.php' => array('disabled="disabled" aria-label="\' . h($pg_settings_step[2]) . \'"'),
	);

	foreach ($expect as $path => $needles) {
		$source = panel_accessible_names_source($path);

		foreach ($needles as $needle) {
			pg_assert_contains($needle, $source, $path);
		}
	}

	$source = panel_accessible_names_source('view_parasut_inbox.php');
	pg_assert_same(2, substr_count($source, "aria-label=\"' . h(lang('Invoice Detail')) . '\""), 'view_parasut_inbox.php: both detail buttons');

	$source = panel_accessible_names_source('marketplace_products.php');
	pg_assert_same(3, substr_count($source, "aria-label=\"' . h(lang('Previous page')) . '\""), 'marketplace_products.php: previous page links');
	pg_assert_same(3, substr_count($source, "aria-label=\"' . h(lang('Next page')) . '\""), 'marketplace_products.php: next page links');
}

function test_panel_accessible_names_autofill_decoy()
{
	$source = panel_accessible_names_source('includes/settings/screen.php');

	pg_assert_true((bool) preg_match('/<input id="fake_user_name"[^>]*>/', $source, $match), 'decoy input found');
	pg_assert_contains('tabindex="-1"', $match[0]);
	pg_assert_contains('aria-hidden="true"', $match[0]);
}

function test_panel_accessible_names_marketplace_bulk_layer()
{
	$source = panel_accessible_names_source('marketplace_products.php');

	pg_assert_contains("<style>@layer custom {\n", $source);
	pg_assert_contains("}\n}</style>", $source);
	pg_assert_true(strpos($source, "<style>\n") === false, 'unlayered inline style');
}
