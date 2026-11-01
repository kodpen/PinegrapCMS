<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The canvas placeholders of the Messages element and of the comments
 * block region are editor chrome (the site prints the messages or the
 * comments instead), so the designer's canvas audit leaves them out: each
 * carries the sd-editor-ph class and _SD_CANVAS_AUDIT_SKIP lists it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function designer_audit_editor_placeholder_test_source()
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/assets/js/style_designer.src.js');
}

function test_designer_audit_editor_placeholder_skip_list_has_class()
{
	$source = designer_audit_editor_placeholder_test_source();
	$from = strpos($source, 'var _SD_CANVAS_AUDIT_SKIP =');
	pg_assert_true($from !== false, 'the audit skip list is in the designer');
	$list = substr($source, $from, strpos($source, ';', $from) - $from);
	pg_assert_true(strpos($list, '.sd-editor-ph') !== false, 'the skip list leaves out .sd-editor-ph');
}

function test_designer_audit_editor_placeholder_messages_marked()
{
	$source = designer_audit_editor_placeholder_test_source();
	$at = strpos($source, "esc(_sdT('Errors and notices appear here'))");
	pg_assert_true($at !== false, 'the Messages placeholder is in the designer');
	$render = substr($source, strrpos(substr($source, 0, $at), 'render: function'), 600);
	pg_assert_true(strpos($render, "d.className = 'alert alert-info py-2 mb-0 sd-editor-ph'") !== false, 'the Messages placeholder carries sd-editor-ph');
}

function test_designer_audit_editor_placeholder_comments_marked()
{
	$source = designer_audit_editor_placeholder_test_source();
	$at = strpos($source, "esc(_sdT('Comments Area'))");
	pg_assert_true($at !== false, 'the comments block placeholder is in the designer');
	$before = substr($source, $at - 600, 600);
	pg_assert_true(strpos($before, '<div class="sd-editor-ph"') !== false, 'the comments block placeholder carries sd-editor-ph');
}

// The class names a render() gives the placeholder it draws before $text.
function designer_audit_editor_placeholder_test_render_class($text)
{
	$source = designer_audit_editor_placeholder_test_source();
	$at = strpos($source, $text);
	pg_assert_true($at !== false, 'the placeholder is in the designer: ' . $text);
	$render = substr($source, strrpos(substr($source, 0, $at), 'render: function'), $at);
	$render = substr($render, 0, strpos($render, $text));

	return preg_match("/d\\.className = ([^;]+);/", $render, $m) ? $m[1] : '';
}

// Placeholders the site prints only as a marker (<!--pg-carousel:…-->,
// <!--pg-variant-attr:…-->, the breadcrumb and applied offers markers):
// the product detail page opened with low-contrast badges on them.
function test_designer_audit_editor_placeholder_marker_components_marked()
{
	$texts = array(
		"esc(_sdT('The slides come from the product images at render time'))",
		"esc(_sdT('Variant Attribute Template'))",
		"esc(_sdT('Catalog → Group → Subgroup → Product (filled by the backend)'))",
		"esc(_sdT('The active offer/discount descriptions are listed here.'))",
	);

	foreach ($texts as $text) {
		pg_assert_true(strpos(designer_audit_editor_placeholder_test_render_class($text), 'sd-editor-ph') !== false, 'carries sd-editor-ph: ' . $text);
	}
}
