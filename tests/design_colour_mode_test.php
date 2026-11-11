<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a design's colour mode (style.style_color_mode, 2026.11.0,
 * 11.0.186; includes/fn/design_themes.php): the keys, what the published
 * page's <html> carries, the early script's order (the visitor's stored
 * choice before the design's mode) and the paths that carry the column.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _test_colour_mode_src($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

// Runs $script under Node and returns its JSON output decoded; null without
// Node.
function _test_colour_mode_node($script)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) {
		return null;
	}
	$file = tempnam(sys_get_temp_dir(), 'pgcm');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 5)));
	return json_decode(implode("\n", $out), true);
}

function test_colour_mode_keys()
{
	pg_assert_same('', pg_design_color_mode_key(''), 'empty stays empty');
	pg_assert_same('light', pg_design_color_mode_key('light'), 'light');
	pg_assert_same('dark', pg_design_color_mode_key(' Dark '), 'dark, trimmed and lower-cased');
	pg_assert_same('auto', pg_design_color_mode_key('auto'), 'follow the system');
	foreach (array('system', 'night', 'dark;', '1', null, array('dark'), true) as $v) {
		pg_assert_same('', pg_design_color_mode_key($v), 'an unknown value is empty: ' . json_encode($v));
	}
}

function test_colour_mode_empty_leaves_the_page_as_it_is()
{
	$page = "<!DOCTYPE html>\n<html lang=\"en\">\n<head><title>x</title></head>\n<body>x</body>\n</html>";
	pg_assert_same($page, pg_design_color_mode_apply($page, ''), 'no mode: the output does not change');
	pg_assert_same($page, pg_design_color_mode_apply($page, 'bogus'), 'an unknown mode: the output does not change');
	pg_assert_same('<div>no document</div>', pg_design_color_mode_apply('<div>no document</div>', 'dark'), 'markup without <html> does not change');
}

function test_colour_mode_written_on_html()
{
	$page = "<!DOCTYPE html>\n<html lang=\"en\">\n<head><title>x</title></head>\n<body><p>&lt;html&gt;</p></body>\n</html>";

	$dark = pg_design_color_mode_apply($page, 'dark');
	pg_assert_contains('<html data-pg-color-mode="dark" data-bs-theme="dark" lang="en">', $dark, 'dark: the mode and data-bs-theme on <html>');
	pg_assert_true(strpos($dark, '<script>') < strpos($dark, '</head>'), 'the early script is in <head>');
	pg_assert_same(1, substr_count($dark, '<script>'), 'one script');

	$light = pg_design_color_mode_apply($page, 'light');
	pg_assert_contains('<html data-pg-color-mode="light" data-bs-theme="light" lang="en">', $light, 'light');

	$auto = pg_design_color_mode_apply($page, 'auto');
	pg_assert_contains('<html data-pg-color-mode="auto" lang="en">', $auto, 'auto: no fixed data-bs-theme, the script resolves it');

	$own = pg_design_color_mode_apply('<html data-bs-theme="light" lang="tr"><head></head><body></body></html>', 'dark');
	pg_assert_contains('<html data-pg-color-mode="dark" data-bs-theme="dark" lang="tr">', $own, 'the design\'s mode replaces a data-bs-theme on <html>');
	pg_assert_same(1, substr_count($own, 'data-bs-theme='), 'one data-bs-theme');
}

// The early script under Node on a stand-in document: [stored pg-theme,
// the design's mode, the system is dark] => what <html> wears.
function test_colour_mode_early_script_order()
{
	$cases = array(
		array(null,    'dark',  false, 'dark'),   // nothing stored: the design's mode
		array(null,    'light', true,  'light'),
		array(null,    'auto',  true,  'dark'),   // the design follows the system
		array(null,    'auto',  false, 'light'),
		array('light', 'dark',  false, 'light'),  // the visitor's choice wins
		array('dark',  'light', false, 'dark'),
		array('auto',  'dark',  false, 'light'),  // the visitor chose "System"
		array('weird', 'dark',  false, 'dark'),   // an unknown stored value is no choice
	);
	$script = pg_design_color_mode_script();
	$js = preg_replace('~^<script>|</script>$~', '', $script);
	$runner = 'var cases = ' . json_encode($cases) . ', out = [];'
		. 'cases.forEach(function (c) {'
		. '  var attrs = { "data-pg-color-mode": c[1] };'
		. '  var document = { documentElement: { getAttribute: function (n) { return attrs.hasOwnProperty(n) ? attrs[n] : null; }, setAttribute: function (n, v) { attrs[n] = v; } } };'
		. '  var localStorage = { getItem: function () { return c[0]; } };'
		. '  var window = { matchMedia: function () { return { matches: c[2], addEventListener: function () {} }; } };'
		. '  (function (document, localStorage, window) { ' . $js . ' })(document, localStorage, window);'
		. '  out.push(attrs["data-bs-theme"] || null);'
		. '});'
		. 'console.log(JSON.stringify(out));';
	$r = _test_colour_mode_node($runner);
	if ($r === null) {
		pg_assert_true(true, 'Node is not installed: the script order is checked by source only');
		pg_assert_true(strpos($js, 'localStorage.getItem("pg-theme")') < strpos($js, 'data-pg-color-mode'), 'the stored choice is read before the design\'s mode');
		return;
	}
	foreach ($cases as $i => $c) {
		pg_assert_same($c[3], $r[$i], 'stored ' . json_encode($c[0]) . ', design ' . $c[1] . ', system ' . ($c[2] ? 'dark' : 'light'));
	}
}

function test_colour_mode_migration_step()
{
	$m = _test_colour_mode_src('includes/migrations/2026.11.0.php');
	pg_assert_contains('upgrade_2026_11_0_design_color_mode();       // 11.0.186', $m, 'the step is in the call list');
	pg_assert_contains("install_add_column('style', 'style_color_mode', \"VARCHAR(8) NOT NULL DEFAULT ''\");", $m, 'one short column, empty by default');
	$body = substr($m, strpos($m, 'function upgrade_2026_11_0_design_color_mode()'));
	pg_assert_false((bool) preg_match('~UPDATE\s+style~i', $body), 'no backfill');
}

// A theme switcher on the page: its inline resolve and its engine fall back
// to the design's mode only when nothing is stored; without a mode both
// follow the system, as before.
function test_colour_mode_switcher_falls_back_to_the_design()
{
	$inject = pg_theme_switcher_inject('<html><head></head><body><div data-pg-theme-switcher="toggle"></div></body></html>');
	$early = substr($inject, strpos($inject, '<script>') + 8);
	$early = substr($early, 0, strpos($early, '</script>'));
	pg_assert_true(strpos($early, 'localStorage.getItem("pg-theme")') < strpos($early, 'data-pg-color-mode'), 'the inline resolve reads the stored choice first');
	pg_assert_contains('if(!t){t=document.documentElement.getAttribute("data-pg-color-mode");}', $early, 'nothing stored: the design\'s mode');

	$engine = _test_colour_mode_src('assets/js/pg_theme_switcher.js');
	pg_assert_contains("return window.localStorage.getItem(KEY) || designMode();", $engine, 'the engine: the stored choice, else the design\'s mode');
	pg_assert_contains("return (m === 'light' || m === 'dark' || m === 'auto') ? m : 'auto';", $engine, 'no mode on <html>: the system, as before');

	$cases = array(
		// [stored, design mode, system dark] => <html>
		array(null,    null,    true,  'dark'),   // no mode: the system, as before
		array(null,    'dark',  false, 'dark'),
		array(null,    'light', true,  'light'),
		array('light', 'dark',  true,  'light'),
		array('auto',  'light', true,  'dark'),
	);
	$runner = 'var cases = ' . json_encode($cases) . ', out = [];'
		. 'cases.forEach(function (c) {'
		. '  var attrs = {}; if (c[1]) attrs["data-pg-color-mode"] = c[1];'
		. '  var document = { documentElement: { getAttribute: function (n) { return attrs.hasOwnProperty(n) ? attrs[n] : null; }, setAttribute: function (n, v) { attrs[n] = v; } } };'
		. '  var localStorage = { getItem: function () { return c[0]; } };'
		. '  var window = { matchMedia: function () { return { matches: c[2] }; } };'
		. '  (function (document, localStorage, window) { ' . $early . ' })(document, localStorage, window);'
		. '  out.push(attrs["data-bs-theme"] || null);'
		. '});'
		. 'console.log(JSON.stringify(out));';
	$r = _test_colour_mode_node($runner);
	if ($r === null) {
		return;
	}
	foreach ($cases as $i => $c) {
		pg_assert_same($c[3], $r[$i], 'switcher page: stored ' . json_encode($c[0]) . ', design ' . json_encode($c[1]));
	}
}

function test_colour_mode_page_output_and_save_path()
{
	$gpc = _test_colour_mode_src('get_page_content.php');
	pg_assert_contains("pg_design_color_mode_key(\$row['style_color_mode'] ?? '')", $gpc, 'the published page reads the mode from the style row');
	pg_assert_contains("if (!\$email && \$pg_color_mode !== '') {", $gpc, 'not in an e-mail, nothing without a mode');

	$fn = _test_colour_mode_src('includes/fn/designer.php');
	pg_assert_contains("pg_design_color_mode_ready()\n        && array_key_exists('color_mode', \$data)", $fn, 'saved only with the column and a posted mode');
	pg_assert_contains("style_color_mode = '\$_mode',", $fn, 'the update writes the mode');
	pg_assert_contains("\$sql_theme_field .= ' style_color_mode,';", $fn, 'a new design writes the mode');

	$screen = _test_colour_mode_src('includes/designer_screen.php');
	pg_assert_contains('name="style_color_mode" id="sd-style-color-mode"', $screen, 'the hidden input');
	pg_assert_contains("\$style_data['color_mode'] = (string)\$_POST['style_color_mode'];", $screen, 'the save posts it');

	$js = _test_colour_mode_src('assets/js/style_designer.src.js');
	pg_assert_contains("'style_look', 'style_palette', 'style_color_mode', 'style_meta_tags'", $js, 'a changed mode makes the design unsaved');
	pg_assert_contains("document.getElementById('sd-style-color-mode')", $js, 'the Theme pane writes the hidden input');
}

function test_colour_mode_copies_carry_the_column()
{
	pg_assert_contains("'color_mode'                        => pg_design_color_mode_key(isset(\$row['style_color_mode'])", _test_colour_mode_src('edit_system_style.php'), 'the editor opens with the stored mode');
	pg_assert_contains('SET dst.style_color_mode = src.style_color_mode', _test_colour_mode_src('duplicate_style.php'), 'a duplicated design keeps the mode');
	pg_assert_contains("'color_mode'                        => isset(\$src['style_color_mode'])", _test_colour_mode_src('includes/panel/designer.php'), 'a page detached into its own design keeps the mode');
}
