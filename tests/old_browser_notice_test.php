<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The "update your browser" notice (pg_old_browser_notice_script() in
 * includes/fn/output.php): a classic <head> script that old browsers can
 * run, three CSS.supports tests, a bar only when one fails, closed for the
 * browser session; printed by the panel head and the four standalone
 * screens that load Bootstrap 6 without it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/prototype.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/prototype_links.php');

function old_browser_notice_test_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

function old_browser_notice_test_function_body($source, $name)
{
	$start = strpos($source, 'function ' . $name . '(');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\nfunction ", $start + 10);

	return substr($source, $start, ($end === false) ? null : $end - $start);
}

function test_old_browser_notice_tests_the_three_features()
{
	$script = pg_old_browser_notice_script('Update', 'Close');

	pg_assert_contains("CSS.supports('color', 'light-dark(#000, #fff)')", $script, 'light-dark()');
	pg_assert_contains("CSS.supports('selector(:has(*))')", $script, ':has()');
	pg_assert_contains("CSS.supports('color', 'oklch(from red l c h)')", $script, 'relative oklch()');
	pg_assert_contains('window.CSS && CSS.supports &&', $script, 'a browser without CSS.supports counts as old');
}

function test_old_browser_notice_is_a_classic_es5_script()
{
	$script = pg_old_browser_notice_script('Update', 'Close');

	pg_assert_same(0, strpos($script, '<script>'), 'a classic script, not a module');
	pg_assert_same(1, substr_count($script, '</script>'), 'one script element');

	foreach (array('=>', 'const ', 'let ', '`', 'class ', 'import', '...') as $modern) {
		pg_assert_false(strpos($script, $modern) !== false, 'no ' . $modern);
	}

	foreach (array('fetch(', 'XMLHttpRequest', 'http:', 'https:', 'sendBeacon') as $network) {
		pg_assert_false(strpos($script, $network) !== false, 'no network call: ' . $network);
	}
}

function test_old_browser_notice_closes_for_the_session()
{
	$script = pg_old_browser_notice_script('Update', 'Close');

	pg_assert_contains("sessionStorage.getItem('pg-old-browser-notice')", $script, 'read the closed mark');
	pg_assert_contains("sessionStorage.setItem('pg-old-browser-notice', '1')", $script, 'write the closed mark');
	pg_assert_false(strpos($script, 'localStorage') !== false, 'not kept past the session');
	pg_assert_true(substr_count($script, 'try {') >= 3, 'every storage and CSS.supports call is guarded');
}

function test_old_browser_notice_puts_the_text_in_as_text()
{
	$script = pg_old_browser_notice_script('Update', 'Close');

	pg_assert_contains('createTextNode(texts.text)', $script, 'the notice is a text node');
	pg_assert_contains("setAttribute('aria-label', texts.close)", $script, 'the close button is named');

	foreach (array('innerHTML', 'insertAdjacentHTML', 'document.write') as $parser) {
		pg_assert_false(strpos($script, $parser) !== false, 'no HTML parsing: ' . $parser);
	}
}

function test_old_browser_notice_escapes_the_texts_for_the_script()
{
	$script = pg_old_browser_notice_script('</script><img src=x onerror=alert(1)> Ali\'nin "Ş" & co', 'Kapat');

	pg_assert_same(1, substr_count($script, '</script>'), 'the text cannot close the element');
	pg_assert_false(strpos($script, '<img') !== false, 'no tag from the text');
	$u = chr(92) . 'u';
	pg_assert_contains($u . '003C' . chr(92) . '/script' . $u . '003E' . $u . '003Cimg', $script, 'tags as JSON escapes');
	pg_assert_contains('Ali' . $u . '0027nin ' . $u . '0022Ş' . $u . '0022 ' . $u . '0026 co', $script, 'quotes and ampersand escaped, letters kept');
	pg_assert_contains('"close":"Kapat"', $script, 'the close name');
}

function test_old_browser_notice_prints_nothing_for_broken_text()
{
	pg_assert_same('', pg_old_browser_notice_script("Bad \xC3\x28 text", 'Close'), 'invalid UTF-8');
}

function test_old_browser_notice_stays_out_of_frames()
{
	pg_assert_contains('window.self !== window.top', pg_old_browser_notice_script('Update', 'Close'), 'one bar per tab');
}

function test_old_browser_notice_translated_wrapper()
{
	$script = pg_old_browser_notice();

	pg_assert_same(0, strpos($script, '<script>'), 'the wrapper prints the script');
	pg_assert_contains('Please update your browser.', $script, 'the notice text');
}

function test_old_browser_notice_in_the_panel_head()
{
	$body = old_browser_notice_test_function_body(old_browser_notice_test_source('includes/fn/output.php'), 'output_control_panel_header_includes');

	pg_assert_true($body !== '', 'output_control_panel_header_includes() found');
	pg_assert_contains('pg_old_browser_notice()', $body, 'every panel head and output_header_secure() screen');
}

function test_old_browser_notice_on_the_standalone_screens()
{
	foreach (array('workspace_guest.php', 'workspace_approval.php') as $path) {
		$source = old_browser_notice_test_source($path);
		$head = substr($source, (int) strpos($source, '<head>'), (int) strpos($source, '</head>') - (int) strpos($source, '<head>'));

		pg_assert_contains('pg_old_browser_notice()', $head, $path);
	}

	$shell = pg_proto_shell_html(array('pages' => array(), 'frame_url' => ''));
	pg_assert_true(strpos($shell, "'pg-old-browser-notice'") !== false && strpos($shell, "'pg-old-browser-notice'") < strpos($shell, '</head>'), 'the prototype viewer');

	$password = pg_proto_link_password_html(array());
	pg_assert_true(strpos($password, "'pg-old-browser-notice'") !== false && strpos($password, "'pg-old-browser-notice'") < strpos($password, '</head>'), 'the prototype link password page');
}
