<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source test for Bootstrap 6 panel fixes no checker scans: the Workspace
 * context menus stay visible (.popover is transparent without .show) and
 * land in an open dialog, v5 sibling toggle buttons show their checked
 * state, the MCP badges have a fill and full-screen dialogs lose their
 * corners.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function bs6_overlays_test_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/' . $path);
}

// The body of the first rule whose selector list is exactly $selector.
function bs6_overlays_test_rule($css, $selector)
{
	$pattern = '/(?:^|\n)' . preg_quote($selector, '/') . ' \{\n(.*?)\n\}/s';

	return preg_match($pattern, $css, $match) ? $match[1] : '';
}

function test_bs6_ws_ctx_menu_is_opaque()
{
	$css = bs6_overlays_test_source('pinegrap/assets/css/backend.src.css');

	pg_assert_contains('opacity: 1;', bs6_overlays_test_rule($css, '.ws-ctx.contextmenu.popover'), 'the menu overrides .popover opacity 0');
}

function test_bs6_ws_ctx_menu_on_overlay_host()
{
	$js = bs6_overlays_test_source('pinegrap/assets/js/workspace.js');

	pg_assert_same(0, preg_match_all('/document\.body\.appendChild\((menu|box)\);/', $js), 'ctxMenu and floatBox no longer append to the body');
	pg_assert_same(2, preg_match_all('/ctxHost\(\)\.appendChild\((menu|box)\);/', $js), 'both append to the overlay host');
	pg_assert_contains("window.pgUI ? pgUI.overlayHost() : document.body", $js);
	pg_assert_contains("event.preventDefault();\n            event.stopPropagation();\n            closeCtx();", $js, 'Escape closes only the menu');
}

function test_bs6_sibling_toggle_rules()
{
	$css = bs6_overlays_test_source('pinegrap/assets/css/backend.src.css');
	$tool = bs6_overlays_test_source('tools/bs6_switch.php');
	$checked = bs6_overlays_test_rule($css, 'input.btn-check:checked + .btn');

	pg_assert_contains('background-color: var(--bs-btn-active-bg', $checked);
	pg_assert_contains('border-color: var(--bs-btn-active-border-color)', $checked);
	pg_assert_contains('color: var(--bs-btn-active-color)', $checked);
	pg_assert_contains('var(--pg-focus-ring-color)', bs6_overlays_test_rule($css, 'input.btn-check:focus-visible + .btn'));
	pg_assert_contains('opacity: var(--bs-btn-disabled-opacity', bs6_overlays_test_rule($css, 'input.btn-check:disabled + .btn'));
	pg_assert_contains('border-start-start-radius: var(--bs-btn-border-radius)', bs6_overlays_test_rule($css, '.btn-group > input.btn-check:first-child + .btn'));

	foreach (array('input.btn-check:checked + .btn {', 'input.btn-check:focus-visible + .btn {', 'input.btn-check:disabled + .btn {', '.btn-group > input.btn-check:first-child + .btn {') as $selector) {
		pg_assert_contains($selector, $tool, 'bs6_switch.php mirrors ' . $selector);
	}
}

function test_bs6_mcp_badges_have_a_fill()
{
	foreach (array('pinegrap/includes/workspace/mcp.php', 'pinegrap/includes/workspace/mcp_catalogue.php') as $path) {
		$php = bs6_overlays_test_source($path);

		pg_assert_same(0, preg_match_all('/text-bg-\w+-subtle/', $php), $path . ' carries no text-bg-*-subtle');
		pg_assert_true(preg_match_all('/bg-subtle-\w+ fg-emphasis-\w+/', $php) >= 3, $path . ' badges use bg-subtle-*');
	}
}

function test_bs6_dialog_fullscreen_has_no_corners()
{
	$css = bs6_overlays_test_source('pinegrap/assets/css/backend.src.css');

	pg_assert_contains('border-radius: 0;', bs6_overlays_test_rule($css, '.dialog.dialog-fullscreen,' . "\n" . '.dialog-fullscreen :is(.dialog-header, .dialog-footer)'));

	foreach (array('575.98' => 'sm', '767.98' => 'md', '1023.98' => 'lg') as $width => $bp) {
		$pattern = '/@media \(max-width: ' . preg_quote($width, '/') . 'px\) \{\n  \.dialog\.' . $bp . '-down\\\\:dialog-fullscreen \{\n    border-width: 0;\n    border-radius: 0;\n  \}\n  \.' . $bp . '-down\\\\:dialog-fullscreen :is\(\.dialog-header, \.dialog-footer\) \{\n    border-radius: 0;\n  \}\n\}/';
		pg_assert_same(1, preg_match($pattern, $css), $bp . '-down full-screen reset');
	}
}
