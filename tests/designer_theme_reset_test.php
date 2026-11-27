<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Themes panel's "Reset every change in this group" (the part 'theme',
 * assets/js/style_designer_theme.js), run under Node on a fake host: the
 * reset is one undo step that takes back every changed variable of the
 * group together, and a group with nothing changed adds no step at all. On
 * a machine without Node the case is skipped.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Presses the group's reset with $decls as the light values in User
// Styles: [undo steps taken, canvas redraws, the light values left].
function designer_theme_reset_test_press($decls)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) {
		return null;
	}
	$js = 'var factory = null; var StyleDesigner = { definePart: function (n, f) { if (n === "theme") factory = f; } };'
		. 'var window = {}; var localStorage = { getItem: function () { return null; }, setItem: function () {} };'
		. 'var reset = null;'
		. 'function el() { return { innerHTML: "", style: {}, dataset: {}, addEventListener: function () {}, querySelector: function () { return { style: {}, addEventListener: function () {} }; },'
		. ' querySelectorAll: function (s) { if (s !== ".sd-theme-grp-act") return []; var b = { dataset: { act: "reset-all", grp: "theme-colors" },'
		. ' addEventListener: function (t, f) { reset = f.bind(b); } }; return [b]; } }; }'
		. 'var head = el(), vars = el();'
		. 'var document = { addEventListener: function () {}, querySelector: function () { return null; }, getElementById: function (id) { return id === "sd-theme-head" ? head : (id === "sd-theme-vars" ? vars : null); } };'
		. 'var K = {}; K._sdT = function (s) { return s; }; K.esc = function (s) { return String(s); }; K._sdUsesBootstrap = function () { return true; };'
		. 'K._sdThemes = function () { return null; }; K._sdThemeFind = function () { return null; }; K._sdThemeValue = function () { return ""; }; K._sdLookDrawingOf = function () { return ""; };'
		. 'var steps = 0, redraws = 0; K.saveState = function () { steps++; }; K.applyAssetsToIframe = function () { redraws++; };'
		. 'var decls = ' . json_encode((object) $decls) . ';'
		. 'var host = { wrap: el(), mode: function () { return "light"; }, userStyles: function () { return ""; }, setUserStyles: function () {},'
		. ' activeTheme: function () { return null; }, openDesignSettings: function () {}, themeCss: function () { return ""; },'
		. ' blockFor: function () { return "light"; }, upgrade: function (c) { return c; }, hasDarkDefault: function () { return true; },'
		. ' setDecl: function (c, kind, n, v) { if (kind === "light") { if (v) decls[n] = v; else delete decls[n]; } return c; },'
		. ' modeDecls: function (c, m) { return m === "light" ? JSON.parse(JSON.stringify(decls)) : {}; } };'
		. "\n" . (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_theme.js') . "\n"
		. 'factory(K).render(host); steps = 0; redraws = 0;'
		. 'reset({ stopPropagation: function () {} });'
		. 'console.log(JSON.stringify([steps, redraws, decls]));';
	$file = tempnam(sys_get_temp_dir(), 'sdthreset') . '.js';
	file_put_contents($file, $js);
	$lines = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $lines, $code);
	@unlink($file);
	pg_assert_same(0, $code, 'the theme part runs: ' . implode("\n", $lines));
	return json_decode((string) end($lines), true);
}

function test_designer_theme_group_reset_is_one_undo_step()
{
	$r = designer_theme_reset_test_press(array('--bs-primary' => '#ff0000', '--bs-danger' => '#880000', '--bs-body-color' => '#111111'));
	if ($r === null) {
		return;
	}
	pg_assert_same(1, $r[0], 'one undo step takes the whole reset back');
	pg_assert_same(1, $r[1], 'the canvas is redrawn once');
	pg_assert_same(array('--bs-body-color' => '#111111'), $r[2], 'the group\'s values gone, another group\'s kept');
}

function test_designer_theme_group_reset_with_nothing_changed_adds_no_step()
{
	$r = designer_theme_reset_test_press(array('--bs-body-color' => '#111111'));
	if ($r === null) {
		return;
	}
	pg_assert_same(0, $r[0], 'no undo step for nothing');
	pg_assert_same(0, $r[1], 'no canvas redraw');
}
