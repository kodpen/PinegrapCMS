<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for panel markup built at runtime (JavaScript strings, class
 * names swapped by scripts) that tools/check_bs6.php does not scan: toasts,
 * the Paraşüt status box, image tiles, the DataTables column menu, text
 * colours and the visual editor's Settings rail.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function bs6_js_leftovers_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

function test_bs6_js_leftovers_toasts()
{
	foreach (array('assets/js/backend.src.js', 'assets/js/workspace.js', 'view_folders.php') as $path) {
		$source = bs6_js_leftovers_source($path);
		pg_assert_true(!preg_match("/'[^'\\n]*toast[^'\\n]*text-bg-'/", $source), $path . ': toast built with text-bg-*');
		pg_assert_true(strpos($source, 'btn-close fg-white') === false, $path . ': white close button on a toast');
		pg_assert_contains("' fg-contrast-' + ", $source, $path);
	}

	pg_assert_contains("(!kind || kind === 'dark') ? 'inverse' : kind", bs6_js_leftovers_source('assets/js/workspace.js'));
}

function test_bs6_js_leftovers_parasut_status()
{
	$source = bs6_js_leftovers_source('assets/js/backend.src.js');

	pg_assert_true(strpos($source, 'addClass("alert-" + tone)') === false, 'status box swaps alert-*');
	pg_assert_contains('addClass("theme-" + tone)', $source);
}

function test_bs6_js_leftovers_image_tiles()
{
	foreach (array('assets/js/backend.src.js', 'product_builder.php', 'edit_contact.php', 'editor_select_image.php') as $path) {
		$source = bs6_js_leftovers_source($path);
		pg_assert_true(strpos($source, 'ratio-2x1') === false, $path . ': ratio-2x1');
		pg_assert_true(strpos($source, '--bs-aspect-ratio') === false, $path . ': --bs-aspect-ratio');
		pg_assert_true(!preg_match('/class="[^"]*(?<![\w-])ratio(?![\w-])/', $source), $path . ': bare ratio class');
		pg_assert_contains('aspect-ratio: 5 / 4', $source, $path);
	}
}

function test_bs6_js_leftovers_datatables_collection()
{
	$source = bs6_js_leftovers_source('assets/js/backend.src.js');

	pg_assert_contains('$.fn.dataTable.Buttons.defaults.dom.collection', $source);
	pg_assert_contains("content: { className: 'dropdown-menu menu show' }", $source);
	pg_assert_contains("liner: { className: 'dropdown-item menu-item' }", $source);
}

function test_bs6_js_leftovers_text_colours()
{
	pg_assert_contains('text-uppercase h5 fg-primary fw-bold', bs6_js_leftovers_source('erp_dashboard.php'));
	pg_assert_contains('bi bi-house fg-success', bs6_js_leftovers_source('toolbar.php'));
}

function test_bs6_js_leftovers_settings_rail()
{
	$source = bs6_js_leftovers_source('includes/designer_screen.php');

	pg_assert_true(strpos($source, 'data-bs-toggle="pill"') === false, 'Settings rail uses pill');
	pg_assert_contains('data-bs-toggle="tab" data-bs-target="#tab-', $source);
}

function test_bs6_js_leftovers_loader_metrics()
{
	// Bootstrap 6 raised .placeholder's min-height to 1.25em; the dashboard
	// skeleton was measured with v5's 1em.
	pg_assert_contains('@layer custom { .card-body-placeholder .placeholder { min-height: 1em; } }', bs6_js_leftovers_source('welcome.php'));

	// v6 .spinner-border is 1em; 2rem gives v5's 32px with a 4px ring.
	foreach (array('assets/js/add_order.js' => 2, 'assets/js/erp_lists.js' => 1) as $path => $count) {
		$source = bs6_js_leftovers_source($path);
		pg_assert_same($count, substr_count($source, '<span class="spinner-border fg-secondary" style="font-size:2rem" role="status" aria-hidden="true"></span>'), $path);
		pg_assert_same($count, substr_count($source, 'spinner-border'), $path . ': spinner without the size');
	}

	pg_assert_contains('<div class=\\"spinner-border fg-primary\\" style=\\"font-size:2rem\\" role=\\"status\\"></div>', bs6_js_leftovers_source('seo.php'));
}
