<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * pg_system_region_fill() (includes/fn/content.php) and its use in
 * get_page_content(): each render of a <system> tag fills its own tag, so a
 * widget placed twice on a page (a side column and a footer region) keeps
 * the inline editing ids of both renders in Edit mode (#9129).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Two tags of one name: the first render fills the first, the second render
// the second, any case of the tag.
function test_system_region_fill_fills_one_tag_at_a_time()
{
	$layout = '<aside><system>blog-widget</system></aside><footer><SYSTEM>blog-widget</SYSTEM></footer>';

	$first = pg_system_region_fill($layout, 'blog-widget', '<div id="software_inline_editing_region_1"></div>');
	pg_assert_same('<aside><div id="software_inline_editing_region_1"></div></aside><footer><SYSTEM>blog-widget</SYSTEM></footer>', $first, 'the first render, the first tag');

	$second = pg_system_region_fill($first, 'blog-widget', '<div id="software_inline_editing_region_7"></div>');
	pg_assert_same('<aside><div id="software_inline_editing_region_1"></div></aside><footer><div id="software_inline_editing_region_7"></div></footer>', $second, 'the second render, the second tag');

	pg_assert_same($second, pg_system_region_fill($second, 'blog-widget', 'x'), 'no tag left: nothing changes');
}

// The page's own region has no name; a name is text, not a pattern; the
// output is text, not a replacement pattern.
function test_system_region_fill_reads_names_and_output_as_text()
{
	pg_assert_same('<p>A</p><system>x</system>', pg_system_region_fill('<system></system><system>x</system>', '', '<p>A</p>'), 'the page\'s own region');
	pg_assert_same('<system>axb</system>[a.b]', pg_system_region_fill('<system>axb</system><system>a.b</system>', 'a.b', '[a.b]'), 'a dot in a name is a dot');
	pg_assert_same('[a/b]', pg_system_region_fill('<system>a/b</system>', 'a/b', '[a/b]'), 'a slash in a name');
	pg_assert_same('price $1 \\0 \\\\', pg_system_region_fill('<system>p</system>', 'p', 'price $1 \\0 \\\\'), 'dollars and backslashes in the output stay as written');
	pg_assert_same('', pg_system_region_fill('<system>gone</system>', 'gone', ''), 'a missing page empties its tag');
}

// get_page_content() fills through the helper, never with a replace of every
// tag of a name.
function test_get_page_content_fills_each_system_tag_once()
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_page_content.php');

	pg_assert_contains('$content = pg_system_region_fill($content, $system_page_name, $system_output);', $src, 'the render fills its own tag');
	pg_assert_contains("\$content = pg_system_region_fill(\$content, \$system_page_name, '');", $src, 'a missing page empties its tag');
	pg_assert_false(strpos($src, "preg_replace('/<system>' . \$system_page_name") !== false, 'no replace of every tag of the name');
}
