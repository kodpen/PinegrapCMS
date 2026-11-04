<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_imported_tag_pattern() and pg_imported_tag_set_attribute()
 * (update_imported_items.php): an imported page's src, href or data
 * attribute is read and rewritten on its own, never through an attribute
 * whose name only ends in it (data-src, data-href). The old patterns took
 * the last "src=" of the tag and rewrote every one, so
 * <img data-src="photo.jpg" src="placeholder.gif"> lost the photo.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/update_imported_items.php');

function test_imported_tag_pattern_reads_the_attribute_itself()
{
	$html = '<p><img class="lazy" data-src="photo.jpg" src="placeholder.gif" alt=""></p><img src="b.png"><abbr href="x">';

	preg_match_all(pg_imported_tag_pattern('img', 'src'), $html, $matches, PREG_SET_ORDER);
	pg_assert_same(2, count($matches), 'two img tags');
	pg_assert_same('placeholder.gif', $matches[0][1], 'src, not data-src');
	pg_assert_same('<img class="lazy" data-src="photo.jpg" src="placeholder.gif" alt="">', $matches[0][0], 'the whole tag');
	pg_assert_same('b.png', $matches[1][1], 'plain img');

	preg_match_all(pg_imported_tag_pattern('a', 'href'), '<abbr href="x"><a data-href="no" href="yes.html">', $matches, PREG_SET_ORDER);
	pg_assert_same(1, count($matches), 'abbr is not an a tag');
	pg_assert_same('yes.html', $matches[0][1], 'href, not data-href');

	preg_match_all(pg_imported_tag_pattern('img', 'src'), "<IMG\n  SRC='c.gif'>", $matches, PREG_SET_ORDER);
	pg_assert_same('c.gif', $matches[0][1], 'any case, attribute on the next line');
}

function test_imported_tag_set_attribute_rewrites_one_attribute()
{
	pg_assert_same(
		'<img data-src="photo.jpg" src="{path}new.gif" alt="">',
		pg_imported_tag_set_attribute('<img data-src="photo.jpg" src="placeholder.gif" alt="">', 'src', '{path}new.gif'),
		'data-src kept'
	);
	pg_assert_same(
		'<a href="{path}p.html#x" data-href="keep">',
		pg_imported_tag_set_attribute("<a href='old.html#x' data-href=\"keep\">", 'href', '{path}p.html#x'),
		'single quotes, data-href kept'
	);
	pg_assert_same(
		'<img src="{path}a$1b.png">',
		pg_imported_tag_set_attribute('<img src="x.png">', 'src', '{path}a$1b.png'),
		'a dollar sign in the value is literal'
	);
	pg_assert_same('<img alt="x">', pg_imported_tag_set_attribute('<img alt="x">', 'src', 'y'), 'no attribute, no change');
}

// Every tag the crawl rewrites goes through the helpers.
function test_imported_tag_crawl_uses_helpers()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/update_imported_items.php');

	pg_assert_same(0, preg_match_all("/preg_replace\\('\\/(src|href|data)\\\\\\\\s\\*=/", $source), 'no global attribute replace');
	pg_assert_same(0, preg_match_all("/preg_match_all\\('\\/<\\\\\\\\s\\*[a-z]+\\\\\\\\s\\+\\[\\^>\\]\\*/", $source), 'no greedy tag pattern');
	pg_assert_same(7, preg_match_all('/pg_imported_tag_set_attribute\(\$old_content,/', $source), 'seven rewrites through the helper');
}
