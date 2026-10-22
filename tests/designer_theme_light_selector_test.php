<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the selector the visual designer's Themes panel writes its
 * light-mode variables under. A plain `:root` also matches an <html> a theme
 * switcher set to dark, so the light block is written as
 * `:root:not([data-bs-theme="dark"]), [data-bs-theme="light"]`; the old
 * `:root, [data-bs-theme="light"]` block is still read and is rewritten when
 * the design loads and on every panel write. The panel's rule-block
 * helpers are cut out of assets/js/style_designer.js and run under Node; on
 * a machine without Node the test is skipped.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs $steps after the Themes panel's rule-block helpers; null without Node.
function designer_theme_light_selector_test_run($steps)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	$from = strpos($source, 'function _findRuleBlock(');
	$to = ($from !== false) ? strpos($source, '// Read the current value of a single theme var', $from) : false;
	pg_assert_true(($from !== false) && ($to !== false), 'the designer has the theme rule-block helpers');

	if (($from === false) || ($to === false)) {
		return null;
	}

	$script = substr($source, $from, $to - $from)
		. 'function light(css) { var b = _findRuleBlock(css, _themeSelectorRe("light")); return b ? _parseThemeDecls(b.body) : null; }'
		. 'function dark(css) { var b = _findRuleBlock(css, _themeSelectorRe("dark")); return b ? _parseThemeDecls(b.body) : null; }'
		. $steps;

	$file = tempnam(sys_get_temp_dir(), 'pgthemesel');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);

	pg_assert_same(0, $code, implode("\n", $out));

	return json_decode(implode("\n", $out), true);
}

// The light block is written so that it never matches a dark <html>; the
// dark block keeps Bootstrap's own selector.
function test_designer_theme_light_selector_written()
{
	$answer = designer_theme_light_selector_test_run(
		'console.log(JSON.stringify([_themeCanonicalSelector("light"), _themeCanonicalSelector("dark")]));'
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(":root:not([data-bs-theme=\"dark\"]),\n[data-bs-theme=\"light\"]", $answer[0]);
	pg_assert_same('[data-bs-theme="dark"]', $answer[1]);
}

// Every light form is read: the new one, the old combined one, a bare
// `:root` and a bare light block, in either quote style. The `:not()` of
// the new form is never taken for the dark block.
function test_designer_theme_light_selector_read()
{
	$answer = designer_theme_light_selector_test_run(
		'var both = "[data-bs-theme=\"dark\"] {\n  --bs-body-bg: #111;\n}\n\n:root:not([data-bs-theme=\"dark\"]),\n[data-bs-theme=\"light\"] {\n  --bs-body-bg: #fdf6e3;\n}\n";'
		. 'console.log(JSON.stringify(['
		. 'light(both), dark(both),'
		. 'light(":root:not([data-bs-theme=\'dark\']), [data-bs-theme=\'light\'] { --bs-body-bg: #a; }"),'
		. 'light(":root,\n[data-bs-theme=\"light\"] {\n  --bs-body-bg: #b;\n}"),'
		. 'light(":root { --bs-body-bg: #c; }"),'
		. 'light("[data-bs-theme=\"light\"] { --bs-body-bg: #d; }"),'
		. 'dark(":root:not([data-bs-theme=\"dark\"]),\n[data-bs-theme=\"light\"] { --bs-body-bg: #e; }"),'
		. 'dark("[data-bs-theme=\"dark\"] .bg-primary { color: red; }")'
		. ']));'
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array('--bs-body-bg' => '#fdf6e3'), $answer[0], 'the new light block next to a dark one');
	pg_assert_same(array('--bs-body-bg' => '#111'), $answer[1], 'the dark block next to the new light one');
	pg_assert_same(array('--bs-body-bg' => '#a'), $answer[2], 'single quotes');
	pg_assert_same(array('--bs-body-bg' => '#b'), $answer[3], 'the old combined form');
	pg_assert_same(array('--bs-body-bg' => '#c'), $answer[4], 'a bare :root');
	pg_assert_same(array('--bs-body-bg' => '#d'), $answer[5], 'a bare light block');
	pg_assert_same(null, $answer[6], 'the :not() is not a dark block');
	pg_assert_same(null, $answer[7], 'a dark cascade rule is not the dark block');
}

// The old combined light selector is rewritten to the new one, once; the
// new form, hand-written bare blocks, the dark block and cascade rules
// stay as they are.
function test_designer_theme_light_selector_upgrade()
{
	$answer = designer_theme_light_selector_test_run(
		'var old = ":root,\n[data-bs-theme=\"light\"] {\n  --bs-body-bg: #fdf6e3;\n}\n\n[data-bs-theme=\"dark\"] {\n  --bs-body-bg: #111;\n}\n";'
		. 'var once = _themeUpgradeLightSelector(old);'
		. 'var hand = ":root { --brand: red; }\n[data-bs-theme=\"light\"] { --x: 1; }\n[data-bs-theme=\"dark\"] .bg-primary { color: red; }";'
		. 'console.log(JSON.stringify(['
		. 'once, _themeUpgradeLightSelector(once), light(once), dark(once),'
		. '_themeUpgradeLightSelector(":root , [data-bs-theme=\'light\']{--a:1}"),'
		. '_themeUpgradeLightSelector(hand) === hand,'
		. '_themeUpgradeLightSelector(""), _themeUpgradeLightSelector(".a { color: red; }")'
		. ']));'
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(":root:not([data-bs-theme=\"dark\"]),\n[data-bs-theme=\"light\"] {\n  --bs-body-bg: #fdf6e3;\n}\n\n[data-bs-theme=\"dark\"] {\n  --bs-body-bg: #111;\n}\n", $answer[0], 'the old light block rewritten');
	pg_assert_same($answer[0], $answer[1], 'a second pass changes nothing');
	pg_assert_same(array('--bs-body-bg' => '#fdf6e3'), $answer[2], 'the rewritten block is still the light one');
	pg_assert_same(array('--bs-body-bg' => '#111'), $answer[3], 'the dark block is untouched');
	pg_assert_same(":root:not([data-bs-theme=\"dark\"]),\n[data-bs-theme=\"light\"] {--a:1}", $answer[4], 'spacing and single quotes');
	pg_assert_true($answer[5], 'hand-written bare blocks and cascade rules stay');
	pg_assert_same('', $answer[6]);
	pg_assert_same('.a { color: red; }', $answer[7]);
}

// The panel rewrites the old block when the design loads and before each
// write, and every new light block uses the canonical selector.
function test_designer_theme_light_selector_call_sites()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');

	$sentinels = strpos($source, 'function _ensureBootstrapSentinels(');
	$sentinels_end = ($sentinels !== false) ? strpos($source, '_ensureBootstrapSentinels();', $sentinels) : false;
	pg_assert_true(($sentinels !== false) && ($sentinels_end !== false), 'the designer has the load-time asset check');
	pg_assert_contains('_themeUpgradeLightSelector(cssFiles[ui].content', substr($source, $sentinels, $sentinels_end - $sentinels), 'the old light block is rewritten on load');

	$write = strpos($source, 'function _writeThemeVar(');
	$write_end = ($write !== false) ? strpos($source, 'function _findThemeVarMeta(', $write) : false;
	pg_assert_true(($write !== false) && ($write_end !== false), 'the designer has the theme variable writer');
	$body = substr($source, $write, $write_end - $write);
	pg_assert_contains("_themeUpgradeLightSelector(entry.content || '')", $body, 'the old light block is rewritten on write');
	pg_assert_contains('_themeCanonicalSelector(mode)', $body, 'a new block uses the canonical selector');
	pg_assert_false(strpos($body, ':root,') !== false, 'no light selector spelt out in the writer');
}
