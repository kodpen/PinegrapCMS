<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the CSS minifier (tools/css_minify.php) behind
 * tools/build_css.php, and the list of minified stylesheets
 * (pg_css_bundles()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(dirname(__DIR__) . '/tools/css_minify.php');

function test_css_minify_drops_comments_and_spare_whitespace()
{
	$css = "@charset \"UTF-8\";\n/* tokens */\n.a {\n    color: red;\n\n    margin: 0 auto;\n}\n\n.b\t>\t.c { top: 0 }\n";

	pg_assert_same('@charset "UTF-8";.a{color: red;margin: 0 auto;}.b > .c{top: 0}', pg_css_minify($css));
}

function test_css_minify_keeps_the_whitespace_that_separates_tokens()
{
	pg_assert_same('.a .b{}', pg_css_minify(".a   .b {}"), 'descendant combinator');
	pg_assert_same('.a:hover,.b :is(.c){}', pg_css_minify(".a:hover,.b :is(.c) {}"), 'a space before a pseudo-class is a combinator');
	pg_assert_same('.a{margin: 0 1px 2px 3px;}', pg_css_minify(".a { margin: 0\n 1px 2px\t3px; }"), 'value list');
	pg_assert_same('.a{box-shadow: 0 0 0 var(--w) var(--c);}', pg_css_minify(".a { box-shadow: 0 0 0 var(--w)\n    var(--c); }"), 'a line break inside a value is one space');
	pg_assert_same('.a .b{}', pg_css_minify('.a/* x */.b {}'), 'a comment between tokens separates them');
}

function test_css_minify_keeps_commas_as_written()
{
	// A custom property holds its value as written; scripts read that text.
	pg_assert_same('.a{--x: var(--y, 1px);--z: a, b;}', pg_css_minify(".a { --x: var(--y, 1px); --z: a, b; }"));
	pg_assert_same('.a, .b{}', pg_css_minify(".a,\n.b {}"), 'a selector list keeps one space');
}

function test_css_minify_leaves_strings_alone()
{
	pg_assert_same('.a::before{content: "/* not a comment */  two  spaces";}', pg_css_minify('.a::before { content: "/* not a comment */  two  spaces"; }'));
	pg_assert_same(".a{content: 'it\\'s  {here}';}", pg_css_minify(".a { content: 'it\\'s  {here}'; }"), 'escaped quote and braces inside a string');
	pg_assert_same('.a{background: url("x y.png");}', pg_css_minify('.a { background: url("x y.png"); }'));
}

function test_css_minify_keeps_an_escapes_closing_space()
{
	// A hex escape ends at its space: without it a following hex digit or
	// letter a-f would be read as part of the escape's code point.
	pg_assert_same('.\\32 xl\\:drawer, .a{}', pg_css_minify(".\\32 xl\\:drawer,\n.a {}"), 'the space inside the selector stays');
	pg_assert_same('.\\31 {}', pg_css_minify(".\\31 {}"), 'an escape before a brace keeps its space');
	pg_assert_same('.a\\ b{}', pg_css_minify(".a\\ b {}"), 'an escaped space is a character, not whitespace');
}

function test_css_minify_reads_nested_and_layered_rules()
{
	$css = "@layer custom {\n  @media (min-width: 992px) {\n    .a { & .b { color: red; } }\n  }\n}\n";

	pg_assert_same('@layer custom{@media (min-width: 992px){.a{& .b{color: red;}}}}', pg_css_minify($css));
}

function test_css_bundles_are_built_from_sources_in_the_tree()
{
	foreach (pg_css_bundles() as $bundle => $source) {
		pg_assert_true((bool) preg_match('#^assets/css/[a-z_]+\.min\.css$#', $bundle), $bundle . ' is a .min.css under assets/css/');
		pg_assert_true(is_file(PG_FUNCTIONS_DIR . '/' . $bundle), $bundle . ' is built');
		pg_assert_true(is_file(PG_FUNCTIONS_DIR . '/' . $source), $bundle . ': ' . $source . ' exists');
		pg_assert_false((bool) preg_match('#\.min\.css$#', $source), $bundle . ': ' . $source . ' is a source');
	}
}
