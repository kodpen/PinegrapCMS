<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the script behind the previous / next buttons of a sideways row
 * (includes/fn/designer.php): pg_scroller_on_page() finds the row's
 * data-pg-scroller marker and nothing else, pg_scroller_inject() adds
 * assets/js/pg_scroller.js once, before the last </body>, and the editor's
 * Preview tests the markup with the same expression.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _test_scroller_src()
{
	return '/pinegrap/assets/js/pg_scroller.js?v=1';
}

function test_designer_scroller_marker()
{
	pg_assert_true(pg_scroller_on_page('<div id="r" class="row" data-pg-scroller="item">'), 'a marked row');
	pg_assert_true(pg_scroller_on_page('<div data-pg-scroller>'), 'a marker without a value');
	pg_assert_true(pg_scroller_on_page("<div\ndata-pg-scroller='item'>"), 'single quotes, after a line break');
	pg_assert_true(pg_scroller_on_page('<div DATA-PG-SCROLLER="item">'), 'upper case');
	pg_assert_false(pg_scroller_on_page('<button data-pg-scroll="next">'), 'a button alone brings no script');
	pg_assert_false(pg_scroller_on_page('<div data-pg-scroller-step="2">'), 'a longer attribute name');
	pg_assert_false(pg_scroller_on_page('<p>data-pg-scroller</p>'), 'the word as text');
	pg_assert_false(pg_scroller_on_page('<div class="row">'), 'no marker');
}

function test_designer_scroller_inject()
{
	$src = _test_scroller_src();
	$tag = '<script src="' . $src . '" defer></script>' . "\n";

	$plain = '<html><head></head><body><div class="row"></div></body></html>';
	pg_assert_same($plain, pg_scroller_inject($plain, $src), 'a page without the marker is left as it is');

	$page = '<html><head></head><body><div data-pg-scroller="item"></div></body></html>';
	$once = pg_scroller_inject($page, $src);
	pg_assert_same('<html><head></head><body><div data-pg-scroller="item"></div>' . $tag . '</body></html>', $once, 'the script goes before </body>');
	pg_assert_same($once, pg_scroller_inject($once, $src), 'a second call adds nothing');

	// An earlier </body> can be text in the page; the script goes before the last one.
	$text = '<html><body><pre>&lt;/body&gt; </body> as text</pre><div data-pg-scroller="item"></div></body></html>';
	$out = pg_scroller_inject($text, $src);
	pg_assert_same(strlen($out) - strlen('</body></html>'), strrpos($out, '</body>'), 'the last </body> closes the page');
	pg_assert_same(strrpos($out, $tag) + strlen($tag), strrpos($out, '</body>'), 'the script sits right before the last </body>');

	pg_assert_same('<div data-pg-scroller="item"></div>' . $tag, pg_scroller_inject('<div data-pg-scroller="item"></div>', $src), 'a fragment gets it at the end');
	pg_assert_contains('src="/x.js?a=1&amp;b=2"', pg_scroller_inject('<div data-pg-scroller="item"></div>', '/x.js?a=1&b=2'), 'the address is escaped');
}

// The Preview's row in _SD_PAGE_SCRIPTS tests the markup with the server's
// expression, so the preview and the page agree on which pages get the file.
function test_designer_scroller_preview_pattern()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$found = preg_match("~\\{ test: (/[^\\n]*?/[a-z]*), file: 'assets/js/pg_scroller\\.js' \\}~", $js, $m);
	pg_assert_true((bool) $found, 'the preview has a row for pg_scroller.js');
	pg_assert_same(pg_scroller_pattern(), $found ? $m[1] : '', 'the preview tests the same expression as the server');
	pg_assert_contains("pg_scroller_inject(\$content)", (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_page_content.php'), 'the published page asks for the script');
}
