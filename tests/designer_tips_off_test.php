<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Visual Page Editor's "Tips" switch reaching the older
 * first-use lines: the core's pure reading of the stored tour state
 * (_sdTipsOffIn() in style_designer.src.js, run in Node), the check handed
 * to the parts (K._sdTipsOff()), and each older line reading it before it
 * shows. Without Node the reading cases are skipped.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function designer_tips_off_test_source($file)
{
	$path = PG_FUNCTIONS_DIR . '/assets/js/' . $file;
	pg_assert_true(is_file($path), $file . ' exists');

	return is_file($path) ? (string) file_get_contents($path) : '';
}

function test_designer_tips_off_reads_the_stored_state()
{
	$src = designer_tips_off_test_source('style_designer.src.js');
	$m = array();
	pg_assert_true((bool) preg_match('/    function _sdTipsOffIn\(raw\) \{\n.*?\n    \}\n/s', $src, $m), 'the core has _sdTipsOffIn()');

	if (!$m || !function_exists('exec')) {
		return;
	}

	$out = array();
	$status = 1;
	@exec('node --version 2>&1', $out, $status);

	if ($status !== 0) {
		return;
	}

	$script = $m[0] . "\n"
		. "var cases = [null, '', 'garbage{', 'null', '[]', '\"x\"', '{}', '{\"tipsOff\":false}', '{\"tipsOff\":\"1\"}', '{\"tipsOff\":true}', '{\"v\":1,\"tips\":{\"history\":true},\"tipsOff\":true}'];\n"
		. "console.log(JSON.stringify(cases.map(_sdTipsOffIn)));";

	$file = @tempnam(sys_get_temp_dir(), 'pgtips');

	if ($file === false) {
		return;
	}

	file_put_contents($file, $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $status);
	@unlink($file);

	pg_assert_same(0, $status, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));
	pg_assert_same(
		array(false, false, false, false, false, false, false, false, false, true, true),
		json_decode(implode("\n", $out), true),
		'only a stored tipsOff of true switches the tips off; nothing stored or unreadable keeps them on'
	);
}

function test_designer_tips_off_is_handed_to_the_parts()
{
	$src = designer_tips_off_test_source('style_designer.src.js');
	pg_assert_contains("'pg.designer.' + uid + '.tour'", $src, 'the check reads the tour state key');
	pg_assert_contains('_sdTipsOff: _sdTipsOff,', $src, 'the parts get the check in their kit');
}

function test_designer_tips_off_guards_every_older_line()
{
	$lines = array(
		'style_designer_similar.js' => "function tipSeen() {\n        if (K._sdTipsOff()) return true;",
		'style_designer_overrides.js' => "function firstSeen() {\n        if (K._sdTipsOff()) return true;",
		'style_designer_sketchbuild.js' => "function seen() {\n        if (K._sdTipsOff()) return true;",
		'style_designer_sketchread.js' => "function _srSeen() {\n        if (K._sdTipsOff()) return true;",
		'style_designer_states.js' => "function hintSeen() {\n        if (K._sdTipsOff()) return true;",
		'style_designer_variables.js' => "function tipShown() {\n        if (K._sdTipsOff()) return false;",
		'style_designer_previewas.js' => "if (seen || K._sdTipsOff()) return '';",
	);

	foreach ($lines as $file => $guard) {
		pg_assert_contains($guard, designer_tips_off_test_source($file), $file . ' reads "Tips" before its first-use line');
	}

	// Bands and side by side keep the tour's own state (tipDue reads tipsOff).
	foreach (array('style_designer_bands.js', 'style_designer_mirror.js') as $file) {
		pg_assert_contains('T.tipDue(state, TIP)', designer_tips_off_test_source($file), $file . ' asks the tour model, which reads "Tips"');
	}
}
