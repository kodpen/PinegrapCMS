<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for escape_regex() (includes/fn/core.php): text placed in a
 * /-delimited pattern matches itself, whatever it contains.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Every metacharacter, the dollar sign and the backslash included.
function test_escape_regex_matches_itself()
{
	$texts = array(
		'<img src="{path}deal.jpg" alt="$50 off, now $5">',
		'<img src="a.png" title="C:\\pics\\new">',
		'a.b*c+d?e^f|g(h)[i]{j}/k',
		'Price $1 \\1 ${2}',
	);

	foreach ($texts as $text) {
		$pattern = '/' . escape_regex($text) . '/i';

		pg_assert_same(1, @preg_match($pattern, 'x' . $text . 'y'), 'matches: ' . $text);
	}
}

// The escaped text is literal: a dot does not match any character.
function test_escape_regex_literal()
{
	pg_assert_same(0, preg_match('/^' . escape_regex('a.b') . '$/', 'axb'), 'dot is literal');
}

// The image edit button: a tag with a backslash or a dollar keeps the content
// and gets its button.
function test_escape_regex_edit_button_tag()
{
	$content = '<p>x</p><img src="{path}a.png" alt="$50 off" title="C:\\pics"><p>y</p>';
	$out = add_edit_button_for_images('page', 1, $content);

	pg_assert_contains('<p>x</p><img src="{path}a.png" alt="$50 off" title="C:\\pics"', $out, 'tag kept');
	pg_assert_contains('software_edit_button_for_', $out, 'button added');
	pg_assert_contains('<p>y</p>', $out, 'rest kept');
}
