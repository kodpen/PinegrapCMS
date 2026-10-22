<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for system widget HTML that leaves the renderer outside a page's own
 * output: the editor's Preview and the canvas's ghost records resolve the
 * address placeholders the page resolves last
 * (pg_designer_resolve_output_placeholders()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// -- address placeholders in preview and ghost HTML ----------------------------

function test_designer_widget_output_resolves_path()
{
	pg_assert_same(
		'<p><img src="/cover26.jpg" class="w-100"> <a href="/blog">x</a></p>',
		pg_designer_resolve_output_placeholders('<p><img src="{path}cover26.jpg" class="w-100"> <a href="{PATH}blog">x</a></p>', '/', 'pinegrap'));
	pg_assert_same(
		'<img src="/site/pinegrap/images/x.png">',
		pg_designer_resolve_output_placeholders('<img src="{path}{software_directory}/images/x.png">', '/site/', 'pinegrap'));
}

// A replacement with $ or \ is copied as written, not read as a back reference.
function test_designer_widget_output_path_is_literal()
{
	pg_assert_same('<img src="/a$1\\b/x.jpg">', pg_designer_resolve_output_placeholders('<img src="{path}x.jpg">', '/a$1\\b/', ''));
}

function test_designer_widget_output_without_placeholders()
{
	pg_assert_same('', pg_designer_resolve_output_placeholders('', '/', ''));
	pg_assert_same('<p>No braces</p>', pg_designer_resolve_output_placeholders('<p>No braces</p>', '/', ''));
}

// The preview function resolves what it returns, so the ghost records cut
// from its answer are resolved as well.
function test_designer_widget_output_preview_resolves()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/designer.php');
	pg_assert_contains('$out[(int)$sid] = pg_designer_resolve_output_placeholders((string)$html);', $source);
}
