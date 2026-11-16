<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the link preview of a Workspace message
 * (includes/workspace/link_preview.php): the first web address of a text,
 * the address check and the cut of a page's fields.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/link_preview.php');

function test_workspace_link_preview_first_url()
{
	pg_assert_same('https://example.com/a', ws_link_preview_first_url('See https://example.com/a and https://example.org/b'), 'the first address');
	pg_assert_same('', ws_link_preview_first_url('no address here'), 'no address');
	pg_assert_same('', ws_link_preview_first_url('ftp://example.com/file'), 'only http and https');
	pg_assert_same('http://example.com/x?a=1', ws_link_preview_first_url('Look: http://example.com/x?a=1.'), 'a full stop is the sentence');
	pg_assert_same('https://example.com/b', ws_link_preview_first_url('(see https://example.com/b), done'), 'a bracket around the address');
	pg_assert_same('https://en.wikipedia.org/wiki/Ankara_(disambiguation)', ws_link_preview_first_url('https://en.wikipedia.org/wiki/Ankara_(disambiguation)'), 'a bracket of the address');
}

function test_workspace_link_preview_first_url_skips_code_and_tags()
{
	pg_assert_same('https://example.org/b', ws_link_preview_first_url('run `curl https://example.com/a` then https://example.org/b'), 'inline code');
	pg_assert_same('https://example.org/b', ws_link_preview_first_url("```\nhttps://example.com/a\n```\nhttps://example.org/b"), 'a code block');
	pg_assert_same('', ws_link_preview_first_url("```\nhttps://example.com/a"), 'an open code block');
	pg_assert_same('https://example.org/b', ws_link_preview_first_url('<#order:5> <@app:2> https://example.org/b'), 'tags are not addresses');
	pg_assert_same('', ws_link_preview_first_url('https://' . str_repeat('a', 2100) . '.com'), 'too long');
}

function test_workspace_link_preview_url_allowed()
{
	pg_assert_true(ws_link_preview_url_allowed('https://example.com/a?b=1'), 'a public name');
	pg_assert_true(ws_link_preview_url_allowed('http://example.com:80/'), 'the usual port');
	pg_assert_true(ws_link_preview_url_allowed('https://93.184.216.34/'), 'a public address');

	foreach (array(
		'ftp://example.com/',
		'https://user:pass@example.com/',
		'https://example.com:8080/',
		'http://localhost/',
		'http://intranet/',
		'http://printer.local/',
		'http://db.internal/',
		'http://127.0.0.1/',
		'http://10.1.2.3/',
		'http://192.168.0.1/',
		'http://169.254.169.254/latest/meta-data/',
		'http://[::1]/',
		'http://0x7f.1/',
		'http://2130706433.0/',
		'https://exa mple.com/',
		'',
	) as $url) {
		pg_assert_false(ws_link_preview_url_allowed($url), 'refused: ' . $url);
	}
}

function test_workspace_link_preview_redirect_target_and_type()
{
	pg_assert_same('https://example.com/b', ws_link_preview_redirect_target('/b', 'https://example.com/a'), 'a path');
	pg_assert_same('https://example.org/', ws_link_preview_redirect_target('https://example.org/', 'https://example.com/a'), 'a whole address');
	pg_assert_same('', ws_link_preview_redirect_target('', 'https://example.com/a'), 'no Location');

	pg_assert_true(ws_link_preview_is_html('text/html; charset=utf-8'), 'html');
	pg_assert_true(ws_link_preview_is_html('application/xhtml+xml'), 'xhtml');
	pg_assert_false(ws_link_preview_is_html('application/pdf'), 'pdf');
	pg_assert_false(ws_link_preview_is_html(''), 'none said');
}

function test_workspace_link_preview_cut()
{
	pg_assert_same('Bold & plain', ws_link_preview_cut("  <b>Bold</b> &amp;\n\t plain ", 100), 'tags, entities, spaces');
	pg_assert_same('abcd…', ws_link_preview_cut('abcdefghij', 5), 'cut with an ellipsis');
	pg_assert_same('çğış…', ws_link_preview_cut('çğışöü', 5), 'characters, not bytes');
	pg_assert_same('a b', ws_link_preview_cut("a\x00\u{202E}b", 10), 'control and direction marks out');
	pg_assert_same('<script>', ws_link_preview_cut('&lt;script&gt;', 20), 'kept plain; escaped where drawn');
}

function test_workspace_link_preview_fields()
{
	$html = '<html><head><title>Plain title</title>'
		. '<meta property="og:title" content="Open &amp; Graph">'
		. '<meta name="description" content="A page about things.">'
		. '<meta property="og:site_name" content="Example">'
		. '</head><body><h1>x</h1></body></html>';

	pg_assert_same(array('title' => 'Open & Graph', 'description' => 'A page about things.', 'site_name' => 'Example'), ws_link_preview_fields($html, 'https://www.example.com/a'), 'og first');

	pg_assert_same(array('title' => 'Başlık', 'description' => '', 'site_name' => 'example.com'), ws_link_preview_fields('<title>Başlık</title>', 'https://www.example.com/a'), 'the title tag, the host');

	$latin = mb_convert_encoding('<html><head><meta charset="iso-8859-9"><title>Ağaç</title></head></html>', 'ISO-8859-9', 'UTF-8');
	pg_assert_same('Ağaç', ws_link_preview_fields($latin, 'https://example.com/')['title'], 'a page in another charset');

	pg_assert_same('', ws_link_preview_fields('', 'https://example.com/')['title'], 'nothing to show');
	pg_assert_same(WS_LINK_PREVIEW_TITLE_MAX, mb_strlen(ws_link_preview_fields('<title>' . str_repeat('ş', 400) . '</title>', 'https://e.com/')['title'], 'UTF-8'), 'a long title cut');
}

function test_workspace_link_preview_own_page()
{
	pg_assert_same('about-us', ws_link_preview_own_page('https://www.example.com/about-us?x=1', 'example.com', '/'), 'a page of the site');
	pg_assert_same('', ws_link_preview_own_page('https://example.com/', 'www.example.com', '/'), 'the home page');
	pg_assert_same('hakkımızda', ws_link_preview_own_page('https://example.com/hakk%C4%B1m%C4%B1zda', 'example.com', '/'), 'a name with letters of its own');
	pg_assert_same('shop', ws_link_preview_own_page('https://example.com/site/shop', 'example.com', '/site/'), 'under the site path');
	pg_assert_same('', ws_link_preview_own_page('https://example.com/site', 'example.com', '/site/'), 'the site path itself');
	pg_assert_same(null, ws_link_preview_own_page('https://example.com/other', 'example.com', '/site/'), 'outside the site path');
	pg_assert_same(null, ws_link_preview_own_page('https://example.org/about', 'example.com', '/'), 'another site');
	pg_assert_same(null, ws_link_preview_own_page('https://example.com/files/a.pdf', 'example.com', '/'), 'a file');
	pg_assert_same(null, ws_link_preview_own_page('https://example.com/a/b', 'example.com', '/'), 'deeper than a page');
}
