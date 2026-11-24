<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for leaving the Workspace during a call: a link that would take the
 * page elsewhere opens in a new tab (leavesPage() of
 * assets/js/workspace_calls.js, under node), and a reload or a close asks
 * first while the call lasts.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs the script after workspace_calls.js under node and returns what it
// printed as JSON; false when node is missing.
function workspace_calls_leave_page_test_run($script)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return false;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgwscall');

	if ($file === false) {
		return false;
	}

	$stub = "var window = { addEventListener: function () {} }; var navigator = {}; var document = {};\n";
	file_put_contents($file, $stub . file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_calls.js') . "\nvar C = window.PGWsCalls;\n" . $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return json_decode(implode("\n", $out), true);
}

function test_workspace_calls_leave_page_which_links_leave()
{
	$got = workspace_calls_leave_page_test_run(<<<'JS'
var here = { origin: 'https://example.com', pathname: '/pinegrap/workspace.php', search: '?channel=4' };
var base = 'https://example.com/pinegrap/';
var link = function (raw, target, download) {
	return { raw: raw, href: new URL(raw, base + 'workspace.php?channel=4').href, target: target || null, download: !!download };
};
console.log(JSON.stringify({
	order: C.leavesPage(link('order.php?id=7'), here),
	customer: C.leavesPage(link('/pinegrap/contact.php?id=3'), here),
	page: C.leavesPage(link('https://example.com/about'), here),
	outside: C.leavesPage(link('https://other.example/'), here),
	other_channel: C.leavesPage(link('workspace.php?channel=5'), here),
	self_target: C.leavesPage(link('order.php?id=7', '_self'), here),
	top_target: C.leavesPage(link('order.php?id=7', '_top'), here),
	hash: C.leavesPage(link('#notes'), here),
	same_address_hash: C.leavesPage(link('workspace.php?channel=4#notes'), here),
	empty: C.leavesPage(link(''), here),
	script: C.leavesPage(link('javascript:void(0)'), here),
	mail: C.leavesPage(link('mailto:a@example.com'), here),
	phone: C.leavesPage(link('tel:+905550000000'), here),
	new_tab: C.leavesPage(link('order.php?id=7', '_blank'), here),
	named_window: C.leavesPage(link('order.php?id=7', 'preview'), here),
	download: C.leavesPage(link('files/report.pdf', null, true), here),
	broken: C.leavesPage({ raw: 'x', href: 'not a url', target: null, download: false }, here),
	nothing: C.leavesPage(null, here)
}));
JS
	);

	if ($got === false) {
		pg_assert_true(true, 'node is not installed: the browser parts are checked in CI');
		return;
	}

	pg_assert_true(is_array($got), 'workspace_calls.js runs under node');
	pg_assert_true($got['order'], 'an order leaves the page');
	pg_assert_true($got['customer'], 'a customer leaves the page');
	pg_assert_true($got['page'], 'a page of the site leaves the page');
	pg_assert_true($got['outside'], 'an outside address leaves the page');
	pg_assert_true($got['other_channel'], 'another address of the Workspace itself reloads it');
	pg_assert_true($got['self_target'], 'a link aimed at this tab leaves');
	pg_assert_true($got['top_target'], 'a link aimed at the top window leaves');
	pg_assert_false($got['hash'], 'a hash stays on the page');
	pg_assert_false($got['same_address_hash'], 'the same address with a hash stays');
	pg_assert_false($got['empty'], 'an empty link stays');
	pg_assert_false($got['script'], 'a script link stays');
	pg_assert_false($got['mail'], 'a mail link stays');
	pg_assert_false($got['phone'], 'a telephone link stays');
	pg_assert_false($got['new_tab'], 'a link already opening a new tab is left to the browser');
	pg_assert_false($got['named_window'], 'a link aimed at a named window is left to the browser');
	pg_assert_false($got['download'], 'a download stays');
	pg_assert_false($got['broken'], 'an address that cannot be read stays');
	pg_assert_false($got['nothing'], 'no link stays');
}

function test_workspace_calls_leave_page_the_browser_wiring()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_calls.js');

	pg_assert_true(strpos($js, "window.addEventListener('beforeunload', askBeforeLeaving);") !== false, 'a reload or a close asks first once a call begins');
	pg_assert_true(strpos($js, "window.removeEventListener('beforeunload', askBeforeLeaving);") !== false, 'and no longer once it is over');
	pg_assert_true(strpos($js, "window.addEventListener('click'") !== false, 'link clicks are heard on the window, after the Workspace\'s own handlers');
	pg_assert_true(strpos($js, 'event.defaultPrevented') !== false, 'a link the Workspace handles in the page is left alone');
	pg_assert_true(strpos($js, "window.open(link.href, '_blank', 'noopener')") !== false, 'a link leaving the page opens in a new tab');

	$calls = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/calls.php');
	pg_assert_true(strpos($calls, "'call_new_tab'    => lang('Opened in a new tab, so the call goes on.')") !== false, 'the new tab is told once, translated');
}
