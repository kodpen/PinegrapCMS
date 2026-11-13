<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * prepare_rich_text_editor_content_for_input() (includes/fn/editor.php):
 * addresses on the site get the {path} placeholder; a protocol-relative
 * address on another host (the Media Embed button's src="//www.youtube.com/…")
 * keeps its two slashes on a site at the web root (#9131).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_rich_text_path_placeholder_keeps_protocol_relative_addresses()
{
	pg_assert_same(
		'<iframe src="//www.youtube.com/embed/x"></iframe><a href="//example.com/a">a</a>',
		prepare_rich_text_editor_content_for_input('<iframe src="//www.youtube.com/embed/x"></iframe><a href="//example.com/a">a</a>', '/'),
		'the call of the issue: nothing to fold'
	);

	pg_assert_same(
		'<img src="{path}pic.png"><a href="{path}">home</a><a href="{path}shop/?a=1">s</a><input value="{path}v"><img data-src="{path}x.png">',
		prepare_rich_text_editor_content_for_input('<img src="/pic.png"><a href="/">home</a><a href="/shop/?a=1">s</a><input value="/v"><img data-src="/x.png">', '/'),
		'paths on a site at the web root, as before'
	);

	pg_assert_same(
		'<a href="{path}a">x</a><a href="/shop//b">y</a><a href="/other">z</a><a href="https://example.com/shop/">e</a>',
		prepare_rich_text_editor_content_for_input('<a href="/shop/a">x</a><a href="/shop//b">y</a><a href="/other">z</a><a href="https://example.com/shop/">e</a>', '/shop/'),
		'a site in a folder: only its own paths'
	);

	pg_assert_same('<a href="/a">a</a>', prepare_rich_text_editor_content_for_input('<a href="/a">a</a>', ''), 'no path: nothing to fold');
	pg_assert_same('<a href="{path}$1\\a">a</a>', prepare_rich_text_editor_content_for_input('<a href="/$1\\a">a</a>', '/'), 'a dollar and a backslash stay text');
}

// The values a caller may hand over, read as str_replace() read them.
function test_rich_text_path_placeholder_other_values()
{
	pg_assert_same('', prepare_rich_text_editor_content_for_input(null, '/'), 'null');
	pg_assert_same('', prepare_rich_text_editor_content_for_input('', '/'), 'empty');
	pg_assert_same('42', prepare_rich_text_editor_content_for_input(42, '/'), 'a number');
	pg_assert_same(array('a' => '<img src="{path}x">', 'b' => '<img src="//h/x">'), prepare_rich_text_editor_content_for_input(array('a' => '<img src="/x">', 'b' => '<img src="//h/x">'), '/'), 'a list, item by item');
}

// What the site and the API fill back: a protocol-relative address stays one.
function test_rich_text_path_placeholder_round_trip()
{
	$html = '<iframe src="//www.youtube.com/embed/dQw4w9WgXcQ" title="x"></iframe><img src="/files/a.png">';
	$stored = prepare_rich_text_editor_content_for_input($html, '/');

	pg_assert_same('<iframe src="//www.youtube.com/embed/dQw4w9WgXcQ" title="x"></iframe><img src="{path}files/a.png">', $stored);
	pg_assert_same('<iframe src="//www.youtube.com/embed/dQw4w9WgXcQ" title="x"></iframe><img src="https://example.com/files/a.png">', str_replace('{path}', 'https://example.com/', $stored), 'as the external API fills it');
}
