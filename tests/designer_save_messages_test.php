<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the messages of a designer save that is followed by a redirect
 * (the first save of a new design, assets/js/style_designer.src.js): the
 * warnings and notes are kept in the tab's sessionStorage and shown by the
 * editor the redirect opens, the warnings until closed, once. The functions
 * are cut out of the script and run under Node (skipped without Node).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Cuts the function that starts with $start out of the designer's source,
// up to the next top-level function.
function _test_dsm_cut($source, $start)
{
	$from = strpos($source, $start);
	pg_assert_true($from !== false, 'the designer has ' . trim($start));
	if ($from === false) return '';
	return substr($source, $from, strpos($source, "\n    function ", $from + 10) - $from);
}

// Runs $script after the cut functions, with a fake sessionStorage (null:
// one that throws) and sdToast() recording its calls; returns the decoded
// JSON the script prints, or null without Node.
function _test_dsm_run($script, $storage = true)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) return null;

	$source = (string)file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$js = ($storage
		? "var store = {}; var window = { sessionStorage: { getItem: function (k) { return Object.prototype.hasOwnProperty.call(store, k) ? store[k] : null; }, setItem: function (k, v) { store[k] = String(v); }, removeItem: function (k) { delete store[k]; } } };\n"
		: "var store = {}; var window = { sessionStorage: { getItem: function () { throw new Error('denied'); }, setItem: function () { throw new Error('denied'); }, removeItem: function () { throw new Error('denied'); } } };\n")
		. "var toasts = []; function sdToast(m, t, d) { toasts.push([m, t, d]); }\n"
		. "function esc(s) { return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;'); }\n"
		. "var SD_CARRIED_SAVE_KEY = 'pg_sd_save_messages';\n"
		. _test_dsm_cut($source, '    function _sdShowSaveMessages(') . "\n"
		. _test_dsm_cut($source, '    function _sdCarrySaveMessages(') . "\n"
		. _test_dsm_cut($source, '    function _sdShowCarriedSaveMessages(') . "\n"
		. $script;

	$file = tempnam(sys_get_temp_dir(), 'pgdsm');
	file_put_contents($file, $js);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);
	pg_assert_same(0, $code, 'the script runs: ' . implode("\n", $out));
	return json_decode(implode("\n", $out), true);
}

function test_designer_save_messages_carried_over_redirect()
{
	$r = _test_dsm_run(
		"_sdCarrySaveMessages(['Saved as \"home-7\" <b>'], ['note']);\n"
		. "var before = toasts.length;\n"
		. "_sdShowCarriedSaveMessages();\n"
		. "var first = toasts.slice();\n"
		. "_sdShowCarriedSaveMessages();\n"
		. "console.log(JSON.stringify({ before: before, first: first, again: toasts.length, left: Object.keys(store) }));\n");
	if ($r === null) return;
	pg_assert_same(0, $r['before'], 'nothing is shown on the page the redirect leaves');
	pg_assert_same(array(array('Saved as "home-7" &lt;b>', 'warning', 0), array('note', 'info', 7000)), $r['first'], 'the next editor shows them, the warnings until closed, escaped');
	pg_assert_same(2, $r['again'], 'shown once');
	pg_assert_same(array(), $r['left'], 'the storage is cleared');
}

function test_designer_save_messages_without_storage()
{
	$r = _test_dsm_run(
		"_sdCarrySaveMessages(['w'], []);\n"
		. "_sdShowCarriedSaveMessages();\n"
		. "_sdCarrySaveMessages([], []);\n"
		. "console.log(JSON.stringify(toasts));\n", false);
	if ($r === null) return;
	pg_assert_same(array(array('w', 'warning', 0)), $r, 'shown at once when the tab cannot keep them; nothing for an empty save');
}

function test_designer_save_messages_first_save_draft_text()
{
	$source = (string)file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_contains("var _liveToo  = _asDraft && !!(_design && _design.styleId) &&", $source, 'a design never saved says nothing of changes gone live');
	pg_assert_contains('setTimeout(_sdShowCarriedSaveMessages, 600);', $source, 'the editor shows carried messages once it is drawn');
}
