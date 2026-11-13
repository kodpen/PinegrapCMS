<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the values the Themes panel of the Visual Page Editor writes
 * from its text boxes (assets/js/style_designer_theme.js): one CSS value
 * only, so a stray ";" or "}" can no longer close the variable's rule and
 * the twenty cascade rules a theme colour writes and hide the page; a colour
 * row asks the browser whether the value is a colour. The check is cut out
 * of the source and run under Node; on a machine without Node those cases
 * are skipped.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function designer_theme_value_test_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_theme.js');
}

function test_designer_theme_value_check()
{
	$source = designer_theme_value_test_source();
	$from = strpos($source, 'function _sdThemeValueOk(');
	$to = ($from !== false) ? strpos($source, "// The browser's own answer", $from) : false;
	pg_assert_true(($from !== false) && ($to !== false), 'the Themes panel checks a typed value');
	if (($from === false) || ($to === false)) {
		return;
	}

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) {
		return;
	}

	$js = substr($source, $from, $to - $from) . <<<'JS'

var colour = function (v) { return /^(#[0-9a-f]{3,8}|red|rgba?\([^)]*\)|var\(--[a-z-]+\)|color-mix\(.*\))$/i.test(v); };
var good = ['', '  ', '#ff0000', 'red', 'rgba(33, 37, 41, 0.75)', 'var(--brand)', 'color-mix(in srgb, red, blue 20%)',
	'1rem', 'calc(1rem + 2px)', '"Press Start 2P", sans-serif', "'Inter'", 'var(--bs-font-sans-serif)'];
var bad = ['red; } body { display:none } .x {', 'red;', '}', '{', 'red /* x', 'a */ b', '</style><script>', 'rgba(1,2,3',
	'var(--x', 'a)', '"Inter', "'x", 'a]', '[a', 'url(x\\)', "red\nblue", "a\u0000b"];
console.log(JSON.stringify({
	good: good.filter(function (v) { return !_sdThemeValueOk(v, null); }),
	bad: bad.filter(function (v) { return _sdThemeValueOk(v, null); }),
	colourRow: [_sdThemeValueOk('#00ff00', colour), _sdThemeValueOk('1rem', colour), _sdThemeValueOk('', colour)]
}));
JS;

	$file = tempnam(sys_get_temp_dir(), 'pgthemeval');
	file_put_contents($file, $js);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, implode("\n", $out));
	$answer = json_decode(implode("\n", $out), true);

	pg_assert_same(array(), $answer['good'], 'one CSS value is written');
	pg_assert_same(array(), $answer['bad'], 'a value that ends its rule, opens a comment or a tag, or leaves a bracket or quote open is not');
	pg_assert_same(array(true, false, true), $answer['colourRow'], 'a colour row takes a colour or nothing');
}

// The text boxes check before they write, and the part's own write refuses
// what the check refuses.
function test_designer_theme_value_check_wiring()
{
	$source = designer_theme_value_test_source();
	$check = strpos($source, "if (!_sdThemeValueOk(v, colour ? _sdThemeIsColour : null)) {");
	$write = ($check !== false) ? strpos($source, '_writeThemeVar(H.mode(), this.dataset.var, v);', $check) : false;
	pg_assert_true(($check !== false) && ($write !== false), 'a typed value is checked before it is written');
	pg_assert_contains("if (!_sdThemeValueOk(value, null)) return false;", $source, 'the part\'s write refuses it too');

	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/style_designer.css');
	pg_assert_contains('.sd-theme-row-text.is-invalid', $css, 'a refused box is marked');
}
