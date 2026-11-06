<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The cookie notice's script (assets/js/pg_consent.js) run in Node against a
 * small stand-in for the page: a refused category's cookies are deleted
 * whatever the answer before it was, and only a withdrawal of a category
 * that was allowed reloads the page. Skipped where Node cannot run.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs the script with the given cookies, presses one of the notice's
// buttons and returns the cookies left and whether the page reloaded; null
// when Node cannot run.
function consent_js_revoke_test_run($cookies, $button)
{
	if (!function_exists('exec')) {
		return null;
	}

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$revoke = pg_consent_revoke_names();

	$script = 'var jar = ' . json_encode($cookies) . ";\n"
		. 'var reloaded = false;' . "\n"
		. 'function el(attrs) {' . "\n"
		. '    return { attrs: attrs || {}, hidden: true, checked: false,' . "\n"
		. '        getAttribute: function (n) { return this.attrs.hasOwnProperty(n) ? this.attrs[n] : null; },' . "\n"
		. '        setAttribute: function (n, v) { this.attrs[n] = String(v); },' . "\n"
		. '        hasAttribute: function (n) { return this.attrs.hasOwnProperty(n); },' . "\n"
		. '        querySelector: function () { return null; }, querySelectorAll: function () { return []; },' . "\n"
		. '        addEventListener: function () {}, contains: function () { return false; }, focus: function () {},' . "\n"
		. '        closest: function () { return this; } };' . "\n"
		. '}' . "\n"
		. 'var switches = [' . "\n"
		. '    el({ "data-pg-cc-category": "analytics", "data-pg-cc-revoke": ' . json_encode(implode(' ', $revoke['analytics'])) . ' }),' . "\n"
		. '    el({ "data-pg-cc-category": "marketing", "data-pg-cc-revoke": ' . json_encode(implode(' ', $revoke['marketing'])) . ' })' . "\n"
		. '];' . "\n"
		. 'var fab = el(), card = el(), intro = el(), prefs = el(), onclick = null;' . "\n"
		. 'var root = el({ "data-path": "/", "data-categories": "analytics,marketing" });' . "\n"
		. 'root.querySelector = function (s) { return s === ".pg-cc-fab" ? fab : s === ".pg-cc-card" ? card : s.indexOf("intro") !== -1 ? intro : s.indexOf("prefs") !== -1 ? prefs : null; };' . "\n"
		. 'root.querySelectorAll = function (s) { return s === ".pg-cc-switch" ? switches : []; };' . "\n"
		. 'root.addEventListener = function (type, fn) { if (type === "click") { onclick = fn; } };' . "\n"
		. 'var document = { activeElement: null,' . "\n"
		. '    getElementById: function () { return root; },' . "\n"
		. '    querySelector: function () { return null; }, querySelectorAll: function () { return []; },' . "\n"
		. '    addEventListener: function () {}, dispatchEvent: function () {}, contains: function () { return false; } };' . "\n"
		. 'Object.defineProperty(document, "cookie", {' . "\n"
		. '    get: function () { return Object.keys(jar).map(function (n) { return n + "=" + jar[n]; }).join("; "); },' . "\n"
		. '    set: function (v) { var first = v.split(";")[0], at = first.indexOf("="), name = first.slice(0, at);' . "\n"
		. '        if (/Max-Age=0(;|$)/.test(v)) { delete jar[name]; } else { jar[name] = first.slice(at + 1); } }' . "\n"
		. '});' . "\n"
		. 'var window = { addEventListener: function () {} };' . "\n"
		. 'var location = { hostname: "www.example.com", protocol: "https:", hash: "", reload: function () { reloaded = true; } };' . "\n"
		. 'var navigator = {};' . "\n"
		. 'function CustomEvent() {}' . "\n"
		. file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_consent.js') . "\n"
		. 'onclick({ target: el({ "data-pg-cc": ' . json_encode($button) . ' }) });' . "\n"
		. 'console.log(JSON.stringify({ cookies: jar, reloaded: reloaded }));';

	$file = @tempnam(sys_get_temp_dir(), 'pgcc');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	pg_assert_same(0, $code, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));

	return json_decode(implode("\n", $out), true);
}

// A visitor who never answered still carries the tracking and affiliate
// cookies set while the site did not ask (before the notice was switched on,
// or on an older version). Refusing must delete them, or the server keeps
// reading them (get_tracking_code(), get_affiliate_code()). Nothing ran
// under a consent, so the page does not reload.
function test_consent_js_refusal_deletes_cookies_set_before_any_answer()
{
	$leftovers = array('software[tracking_code]' => 'spring', 'software[affiliate_code]' => 'partner1', '_ga' => 'GA1.1.1', 'PHPSESSID' => 'abc');

	foreach (array('reject', 'close') as $button) {
		$result = consent_js_revoke_test_run($leftovers, $button);

		if ($result === null) {
			return;
		}

		pg_assert_same(array('PHPSESSID' => 'abc', 'pg_consent' => '1.a0.m0'), $result['cookies'], $button . ': only the necessary cookies stay');
		pg_assert_same(false, $result['reloaded'], $button . ': no reload');
	}
}

// Withdrawing what was allowed still deletes its cookies and reloads, so a
// script that already ran stops; the category that stays allowed keeps its
// cookies.
function test_consent_js_withdrawal_deletes_and_reloads()
{
	$result = consent_js_revoke_test_run(array('pg_consent' => '1.a1.m1', 'software[affiliate_code]' => 'partner1', '_ga' => 'GA1.1.1'), 'reject');

	if ($result === null) {
		return;
	}

	pg_assert_same(array('pg_consent' => '1.a0.m0'), $result['cookies'], 'both categories withdrawn');
	pg_assert_same(true, $result['reloaded'], 'reloaded');

	$result = consent_js_revoke_test_run(array('pg_consent' => '1.a1.m1', '_ga' => 'GA1.1.1'), 'accept');

	pg_assert_same(array('pg_consent' => '1.a1.m1', '_ga' => 'GA1.1.1'), $result['cookies'], 'accepting keeps them');
	pg_assert_same(false, $result['reloaded'], 'no reload on accept');
}
