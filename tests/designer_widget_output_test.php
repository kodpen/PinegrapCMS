<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for system widget HTML that leaves the renderer outside a page's own
 * output: the editor's Preview and the canvas's ghost records resolve the
 * address placeholders the page resolves last
 * (pg_designer_resolve_output_placeholders()), and rich text written for the
 * classic theme keeps its pictures in their column on a Bootstrap 5 page
 * (pg_img_fluid_alias()).
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

// -- img-responsive on a Bootstrap 5 page -------------------------------------

function test_designer_widget_output_fluid_alias()
{
	pg_assert_same(
		'<p><img src="{path}cover26.jpg" class="img-responsive img-fluid" alt=""></p>',
		pg_img_fluid_alias('<p><img src="{path}cover26.jpg" class="img-responsive" alt=""></p>'), 'the only class');
	pg_assert_same(
		"<img class='rounded img-responsive img-fluid shadow' src='/a.jpg'>",
		pg_img_fluid_alias("<img class='rounded img-responsive shadow' src='/a.jpg'>"), 'between others, single quotes');
	pg_assert_same(
		'<IMG SRC="/a.jpg" CLASS="img-responsive img-fluid" />',
		pg_img_fluid_alias('<IMG SRC="/a.jpg" CLASS="img-responsive" />'), 'upper-case markup');
}

function test_designer_widget_output_fluid_alias_leaves_the_rest()
{
	$kept = array(
		'<img class="img-responsive img-fluid" src="/a.jpg">'  => 'already fluid',
		'<img class="img-fluid img-responsive" src="/a.jpg">'  => 'fluid first',
		'<img class="img-responsive-lg" src="/a.jpg">'         => 'a longer class name',
		'<div class="img-responsive"><img src="/a.jpg"></div>' => 'not an image',
		'<p>img-responsive</p>'                                => 'a word in the text',
		'<img src="/a.jpg">'                                   => 'no class',
	);
	foreach ($kept as $html => $why) {
		pg_assert_same($html, pg_img_fluid_alias($html), $why);
	}
	pg_assert_same(
		'<img class="img-responsive img-fluid" src="/a.jpg"><img src="/b.jpg" class="x img-responsive img-fluid">',
		pg_img_fluid_alias('<img class="img-responsive" src="/a.jpg"><img src="/b.jpg" class="x img-responsive">'), 'every image');
}

// -- starter layouts -----------------------------------------------------------

// A product's long description is rich text with its own paragraphs: the
// catalog detail starter binds it to a div, never to a paragraph.
function test_designer_widget_output_description_in_a_div()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	pg_assert_true(preg_match("/createNode\('semantic', \{ tag: 'div'[^}]*\n\s*customName: _sdT\('Description'\),\n\s*_bindings: \{ text: '__description' \} \}\)/", $source) === 1, 'a div');
	pg_assert_true(preg_match("/contentType: 'paragraph',[^}]*\n[^}]*_bindings: \{ text: '__description' \}/", $source) === 0, 'no paragraph');
}

// Every widget's finished output goes through the alias before its srcset
// pass, outside an e-mail.
function test_designer_widget_output_fluid_alias_on_widgets()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/designer.php');
	pg_assert_contains('return $email ? $out : pg_img_responsive_html(pg_img_fluid_alias($out));', $source);
}
