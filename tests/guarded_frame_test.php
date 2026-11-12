<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the guarded frame (includes/fn/guarded_frame.php): the headers
 * of each mode, the shell's markup, and the refusal of anything but a stored
 * page's id. What a browser enforces is proven by
 * tests/js/guarded_frame_proofs.js.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/guarded_frame.php');

/**
 * The directives of a policy, name => value.
 */
function guarded_frame_test_directives($policy)
{
	$directives = array();

	foreach (explode(';', $policy) as $part) {
		$part = trim($part);

		if ($part === '') {
			continue;
		}

		$words = explode(' ', $part, 2);
		pg_assert_false(isset($directives[$words[0]]), 'directive ' . $words[0] . ' is given once');
		$directives[$words[0]] = isset($words[1]) ? $words[1] : '';
	}

	return $directives;
}

function test_guarded_frame_page_id_takes_only_plain_digits()
{
	pg_assert_same(42, pg_guarded_frame_page_id('42'));
	pg_assert_same(2147483647, pg_guarded_frame_page_id('2147483647'));

	foreach (array(null, '', '0', '042', '-1', '+1', ' 1', '1 ', '1e3', '0x1', '1.0', '2147483648', '99999999999', 'abc', '1;2', "1\n") as $raw) {
		pg_assert_same(0, pg_guarded_frame_page_id($raw), var_export($raw, true) . ' is refused');
	}

	pg_assert_same(0, pg_guarded_frame_page_id(5), 'an integer never comes from an address');
	pg_assert_same(0, pg_guarded_frame_page_id(array('5')), 'nor an array (message[]=5)');
}

function test_guarded_frame_page_name_is_html_only()
{
	foreach (array('page.html', 'Chart.HTM', 'a.b.html') as $name) {
		pg_assert_true(pg_guarded_frame_is_page_name($name), $name);
	}

	foreach (array('page.svg', 'page.xhtml', 'page.html.txt', 'page.php', '.html', 'a/b.html', 'a\\b.html', '', null) as $name) {
		pg_assert_false(pg_guarded_frame_is_page_name($name), var_export($name, true));
	}
}

function test_guarded_frame_mode_is_script_unless_static()
{
	pg_assert_same('static', pg_guarded_frame_mode('static'));

	foreach (array(null, '', 'script', 'STATIC', 'static ', array('static')) as $raw) {
		pg_assert_same('script', pg_guarded_frame_mode($raw), var_export($raw, true));
	}
}

function test_guarded_frame_script_policy()
{
	$policy = guarded_frame_test_directives(pg_guarded_frame_policy('script', 'n0nce'));

	pg_assert_same('allow-scripts', $policy['sandbox'], 'opaque origin: never allow-same-origin');
	pg_assert_same("'none'", $policy['default-src']);
	pg_assert_same("'unsafe-inline'", $policy['script-src'], 'inline only, no host, no eval');
	pg_assert_same("'unsafe-inline'", $policy['style-src']);
	pg_assert_same('data: blob:', $policy['img-src'], 'pictures only from inside the document');
	pg_assert_same('data:', $policy['font-src']);
	pg_assert_same('data: blob:', $policy['media-src']);

	foreach (array('connect-src', 'frame-src', 'child-src', 'worker-src', 'object-src', 'manifest-src', 'form-action', 'base-uri') as $closed) {
		pg_assert_same("'none'", $policy[$closed], $closed . ' is closed');
	}

	pg_assert_same("'self'", $policy['frame-ancestors']);
	pg_assert_false(strpos(pg_guarded_frame_policy('script', 'n0nce'), 'n0nce') !== false, 'no nonce: it would switch unsafe-inline off');
}

function test_guarded_frame_static_policy()
{
	$policy = guarded_frame_test_directives(pg_guarded_frame_policy('static', 'n0nce'));

	pg_assert_same('allow-scripts allow-same-origin', $policy['sandbox'], 'the shell measures the inert document');
	pg_assert_same("'nonce-n0nce'", $policy['script-src'], 'only the shell\'s own script');
	pg_assert_same('data:', $policy['img-src']);
	pg_assert_same("'none'", $policy['media-src']);
	pg_assert_same("'none'", $policy['connect-src']);
	pg_assert_same("'none'", $policy['frame-src']);
	pg_assert_same("'none'", $policy['form-action']);
}

function test_guarded_frame_headers()
{
	foreach (array('script', 'static') as $mode) {
		$headers = pg_guarded_frame_headers($mode, 'abc');

		pg_assert_true(in_array('Content-Type: text/html; charset=utf-8', $headers, true), $mode);
		pg_assert_true(in_array('Content-Security-Policy: ' . pg_guarded_frame_policy($mode, 'abc'), $headers, true), $mode);
		pg_assert_true(in_array('X-Content-Type-Options: nosniff', $headers, true), $mode);
		pg_assert_true(in_array('X-Frame-Options: SAMEORIGIN', $headers, true), $mode);
		pg_assert_true(in_array('Referrer-Policy: no-referrer', $headers, true), $mode);
		pg_assert_true(in_array('Cache-Control: private, no-store', $headers, true), $mode);

		$permissions = '';

		foreach ($headers as $header) {
			if (strpos($header, 'Permissions-Policy: ') === 0) {
				$permissions = $header;
			}
		}

		foreach (array('camera=()', 'microphone=()', 'geolocation=()', 'payment=()') as $feature) {
			pg_assert_true(strpos($permissions, $feature) !== false, $mode . ' ' . $feature);
		}
	}
}

function test_guarded_frame_sandbox_attributes()
{
	pg_assert_same('allow-scripts', pg_guarded_frame_panel_sandbox('script'));
	pg_assert_same('allow-scripts', pg_guarded_frame_inner_sandbox('script'));
	pg_assert_same('allow-scripts allow-same-origin', pg_guarded_frame_panel_sandbox('static'));
	pg_assert_same('allow-same-origin', pg_guarded_frame_inner_sandbox('static'), 'no script runs in a mail');

	foreach (array('script', 'static') as $mode) {
		foreach (array(pg_guarded_frame_panel_sandbox($mode), pg_guarded_frame_inner_sandbox($mode)) as $sandbox) {
			foreach (array('allow-top-navigation', 'allow-popups', 'allow-forms', 'allow-downloads', 'allow-modals') as $never) {
				pg_assert_false(strpos($sandbox, $never) !== false, $mode . ' never ' . $never);
			}
		}
	}

	pg_assert_false(strpos(pg_guarded_frame_panel_sandbox('script') . pg_guarded_frame_inner_sandbox('script'), 'allow-same-origin') !== false, 'a live page has an opaque origin');
}

function test_guarded_frame_shell_script_mode()
{
	$html = '<p title="a&amp;b">x</p><script>alert("\'")</script>';
	$shell = pg_guarded_frame_shell($html, 'script', 'n0nce', 'Live "page"');

	pg_assert_same(1, substr_count($shell, '<iframe '), 'one frame');
	pg_assert_true(strpos($shell, 'sandbox="allow-scripts"') !== false);
	pg_assert_true(strpos($shell, 'title="Live &quot;page&quot;"') !== false, 'the title is escaped');
	pg_assert_false(strpos($shell, 'nonce=') !== false, 'no nonce in the script mode');

	// The page sits in srcdoc only, escaped: decoding gives it back, the
	// height reporter after it.
	pg_assert_true((bool) preg_match('/ srcdoc="([^"]*)"/', $shell, $match), 'srcdoc is one attribute');
	$inner = html_entity_decode($match[1], ENT_QUOTES, 'UTF-8');
	pg_assert_same($html . pg_guarded_frame_reporter(), $inner);
	pg_assert_false(strpos($match[1], '<') !== false, 'no markup of the page is markup of the shell');
	pg_assert_same(1, substr_count($shell, '<script>'), 'one live script tag in the shell');
}

function test_guarded_frame_shell_static_mode()
{
	$shell = pg_guarded_frame_shell('<b onclick="x()">hi</b><script>x()</script>', 'static', 'n0nce', 'Mail');

	pg_assert_true(strpos($shell, 'sandbox="allow-same-origin"') !== false);
	pg_assert_true(strpos($shell, '<script nonce="n0nce">') !== false, 'the shell\'s script carries the nonce');
	pg_assert_true((bool) preg_match('/ srcdoc="([^"]*)"/', $shell, $match));
	pg_assert_same('<b onclick="x()">hi</b><script>x()</script>', html_entity_decode($match[1], ENT_QUOTES, 'UTF-8'), 'no reporter: no script in a mail');
	pg_assert_true(strpos($shell, 'mode="static"') !== false);
}

function test_guarded_frame_shell_survives_bad_bytes()
{
	$shell = pg_guarded_frame_shell("a\xff\"<b>", 'script', '', 'T');

	pg_assert_true((bool) preg_match('/ srcdoc="([^"]*)"/', $shell, $match), 'invalid UTF-8 cannot end the attribute');
	pg_assert_true(strpos($match[1], '&quot;&lt;b&gt;') !== false);
}

function test_guarded_frame_request_framed()
{
	pg_assert_true(pg_guarded_frame_request_framed(array('HTTP_SEC_FETCH_DEST' => 'iframe')));
	pg_assert_true(pg_guarded_frame_request_framed(array('HTTP_SEC_FETCH_DEST' => 'IFRAME')));
	pg_assert_false(pg_guarded_frame_request_framed(array('HTTP_SEC_FETCH_DEST' => 'document')), 'opened as a page of its own');
	pg_assert_false(pg_guarded_frame_request_framed(array()), 'a browser that does not say is refused');
}

function test_guarded_frame_limits()
{
	pg_assert_same(512000, PG_GUARDED_FRAME_MAX_BYTES, '500 KB a page');
	pg_assert_true(strpos(pg_guarded_frame_shell_script('script'), 'max=' . PG_GUARDED_FRAME_MAX_HEIGHT) !== false, 'the height is bounded');
}
