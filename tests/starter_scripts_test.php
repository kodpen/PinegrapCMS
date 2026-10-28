<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the starter sites' theme script
 * (data/backups/english_default/files/scripts.js and its turkish_default
 * twin): its document-ready handler runs to the end whether or not the
 * FlexSlider plugin loaded.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs a starter scripts.js under Node against a chainable jQuery stub whose
// selections are all empty, then calls every document-ready handler. With
// $flexslider false the stub has no $.fn.flexslider, as on a page where the
// plugin's script was refused or could not be fetched. Returns the script's
// JSON ({ran, error, flexslider_calls}); null when there is no Node.
function starter_scripts_test_run($language, $flexslider)
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

	$path = PG_FUNCTIONS_DIR . '/data/backups/' . $language . '_default/files/scripts.js';
	$file = @tempnam(sys_get_temp_dir(), 'pgstarterjs');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, '"use strict";' . "\n"
		. 'var ready = [], fn = {}, calls = { flexslider: 0 };' . "\n"
		// The ready handler's last block looks for a Google Maps element.
		. 'var result = { ran: false, error: null };' . "\n"
		. ($flexslider ? 'fn.flexslider = function () { calls.flexslider++; return sel; };' . "\n" : '')
		// Every method returns the empty selection again, so any chain works;
		// plugins are looked up on fn the way jQuery.fn is.
		. 'var sel = new Proxy(function () {}, {' . "\n"
		. '    get: function (t, name) {' . "\n"
		. '        if (name === "length") { return 0; }' . "\n"
		. '        if (name === "valueOf") { return function () { return 0; }; }' . "\n"
		. '        if (name === "toString") { return function () { return ""; }; }' . "\n"
		. '        if (name === "ready") { return function (f) { ready.push(f); return sel; }; }' . "\n"
		. '        if (typeof name === "string" && Object.prototype.hasOwnProperty.call(fn, name)) { return fn[name]; }' . "\n"
		. '        if (name === "flexslider") { return undefined; }' . "\n"
		. '        if (typeof name !== "string") { return undefined; }' . "\n"
		. '        return function () { return sel; };' . "\n"
		. '    }' . "\n"
		. '});' . "\n"
		. 'var $ = function (a) { if (typeof a === "function") { ready.push(a); } return sel; };' . "\n"
		. '$.fn = fn;' . "\n"
		. 'global.$ = global.jQuery = global.software_$ = $;' . "\n"
		. 'global.window = global;' . "\n"
		. 'global.document = { addEventListener: function () {}, querySelector: function (q) { if (q === "[data-maps-api-key]") { result.ran = true; } return null; }, getElementById: function () { return null; } };' . "\n"
		. 'global.addEventListener = function () {};' . "\n"
		. 'global.location = { search: "" };' . "\n"
		. 'global.setTimeout = global.setInterval = function () { return 0; };' . "\n"
		. 'try {' . "\n"
		. '    (0, eval)(require("fs").readFileSync(' . json_encode($path) . ', "utf8"));' . "\n"
		. '    ready.forEach(function (f) { f(); });' . "\n"
		. '} catch (e) { result.error = String(e && e.message || e); }' . "\n"
		. 'result.flexslider_calls = calls.flexslider;' . "\n"
		. 'process.stdout.write(JSON.stringify(result));' . "\n");

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return json_decode(implode("\n", $out), true);
}

// Without the plugin the ready handler used to stop at the first
// .flexslider() call with "$(...).flexslider is not a function", so the
// lightbox titles, Masonry, the Twitter feed and everything after it never
// ran, on every page of the starter site.
function test_starter_scripts_ready_handler_runs_without_flexslider()
{
	foreach (array('english', 'turkish') as $language) {
		$result = starter_scripts_test_run($language, false);

		if ($result === null) {
			return;
		}

		pg_assert_same(null, $result['error'], $language . ': no error');
		pg_assert_true($result['ran'], $language . ': the handler ran to the end');
	}
}

// With the plugin the sliders are still set up.
function test_starter_scripts_sliders_set_up_with_flexslider()
{
	foreach (array('english', 'turkish') as $language) {
		$result = starter_scripts_test_run($language, true);

		if ($result === null) {
			return;
		}

		pg_assert_same(null, $result['error'], $language . ': no error');
		pg_assert_true($result['ran'], $language . ': the handler ran to the end');
		pg_assert_true($result['flexslider_calls'] >= 5, $language . ': the sliders were set up');
	}
}
