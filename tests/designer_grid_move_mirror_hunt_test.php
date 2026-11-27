<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for faults found in the designer's grid, move and mirror parts:
 * area names CSS reads as keywords, the areas tinted over a right-to-left
 * grid, the tint kept out of the Side by side mirrors, a changed track
 * count on a grid whose other axis has no count, the line under the
 * areas after an undo, and Enter in the "Move…" window after a new
 * search. The code runs under Node; without Node those cases are skipped.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function designer_gmm_hunt_node()
{
	if (!function_exists('exec')) {
		return false;
	}

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	return $code === 0;
}

function designer_gmm_hunt_js($file)
{
	return str_replace("\r\n", "\n", (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/' . $file));
}

// One four-space function of a part, from its declaration to its closing
// brace; '' when it is not there.
function designer_gmm_hunt_fn($js, $name)
{
	$start = strpos($js, "\n    function " . $name . '(');
	$end = ($start === false) ? false : strpos($js, "\n    }\n", $start);

	return (($start === false) || ($end === false)) ? '' : substr($js, $start, $end - $start + 6);
}

// Runs $script after the grid models (S size writer, G tracks, A areas)
// and the named functions of `$file`; returns what `out` holds.
function designer_gmm_hunt_run($file, $names, $script)
{
	if (!designer_gmm_hunt_node()) {
		return null;
	}

	$dir = PG_FUNCTIONS_DIR . '/assets/js';
	$src = "global.window = {};\n"
		. 'require(' . json_encode($dir . '/pg_user_styles.js') . ");\n"
		. 'require(' . json_encode($dir . '/pg_custom_style.js') . ");\n"
		. 'var Z = require(' . json_encode($dir . '/pg_size_style.js') . ");\n"
		. 'var G = require(' . json_encode($dir . '/pg_grid_tracks.js') . ");\n"
		. 'var A = require(' . json_encode($dir . '/pg_grid_areas.js') . ");\n"
		. "var _sdT = function (s, v) { if (Array.isArray(v)) { v.forEach(function (x, i) { s = s.replace('{var:' + (i + 1) + '}', x); }); return s; } return String(s).replace('{var}', v); };\n"
		. "var esc = function (s) { return String(s); };\n";

	if ($file !== '') {
		$js = designer_gmm_hunt_js($file);

		foreach ($names as $name) {
			$fn = designer_gmm_hunt_fn($js, $name);

			if (!pg_assert_true($fn !== '', $file . ' has ' . $name . '()')) {
				return null;
			}

			$src .= $fn . "\n";
		}
	}

	$tmp = @tempnam(sys_get_temp_dir(), 'pggmm');

	if ($tmp === false) {
		return null;
	}

	file_put_contents($tmp, $src . "var out = null;\n" . $script . "\nconsole.log(JSON.stringify(out));\n");
	$out = array();
	$code = 1;
	@exec('node ' . escapeshellarg($tmp) . ' 2>&1', $out, $code);
	@unlink($tmp);
	pg_assert_same(0, $code, implode("\n", array_slice($out, 0, 12)));

	return $code === 0 ? json_decode(implode("\n", $out), true) : null;
}

// An area named as a CSS keyword ("inherit", "AUTO") would turn a child's
// `grid-area` into that keyword: the name is refused and never read back.
function test_designer_gmm_hunt_area_keyword_names()
{
	$r = designer_gmm_hunt_run('', array(), <<<'JS'
var words = ['inherit', 'initial', 'unset', 'revert', 'revert-layer', 'default', 'AUTO', 'Span', 'None', 'Inherit'];
out = {
	names: words.map(function (w) { return A.nameError(w, [], -1); }),
	items: words.map(function (w) { return A.item(w); }),
	kept: [A.nameError('header', [], -1), A.item('header'), A.nameError('autoplay', [], -1), A.item('spanner')]
};
JS
	);

	if ($r === null) {
		return;
	}

	pg_assert_same(array_fill(0, 10, 'bad'), $r['names'], 'a CSS keyword in any case is no area name');
	pg_assert_same(array_fill(0, 10, ''), $r['items'], 'a child holding a keyword is in no area');
	pg_assert_same(array('', 'header', '', 'spanner'), $r['kept'], 'a name that only starts like a keyword is kept');
}

// The areas tinted over a grid box on the canvas are the editor's chrome:
// a Side by side mirror neither copies them nor draws again when they move.
function test_designer_gmm_hunt_tint_not_in_mirror()
{
	$part = designer_gmm_hunt_js('style_designer_grid.js');
	pg_assert_true(strpos($part, "old.className = 'sd-ga-overlay';") !== false, 'the tint carries its chrome class');

	$r = designer_gmm_hunt_run('', array(), 'require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_mirror_schedule.js') . ");\n" . <<<'JS'
var S = window.PGMirrorSchedule;
out = {
	strip: S.CHROME_SELECTOR.split(', ').indexOf('.sd-ga-overlay') !== -1,
	added: S.matters({ type: 'childList', cls: '', nodes: ['sd-ga-overlay'] }),
	inside: S.matters({ type: 'childList', cls: 'sd-ga-overlay', nodes: [''] }),
	page: S.matters({ type: 'childList', cls: '', nodes: ['row'] })
};
JS
	);

	if ($r === null) {
		return;
	}

	pg_assert_true($r['strip'], 'the mirror takes the tint out of its copy');
	pg_assert_same(array(false, false, true), array($r['added'], $r['inside'], $r['page']), 'only the page\'s own changes draw the mirror again');
}

// On a right-to-left page a grid's first column is its right one: the
// tint of an area stands over the cells the area holds there.
function test_designer_gmm_hunt_tint_rtl()
{
	$r = designer_gmm_hunt_run('', array(), <<<'JS'
var a = { name: 'side', c: 1, r: 2, w: 1, h: 1 };
var cols = [100, 200, 300], rows = [50, 60];
out = {
	ltr: A.box(a, cols, rows, 10, 5, false, 620),
	rtl: A.box(a, cols, rows, 10, 5, true, 620),
	wide: A.box({ name: 'main', c: 2, r: 1, w: 2, h: 2 }, cols, rows, 10, 5, true, 620)
};
JS
	);

	if ($r === null) {
		return;
	}

	pg_assert_same(array('x' => 0, 'y' => 55, 'w' => 100, 'h' => 60), $r['ltr'], 'left to right: column 1 at the left edge');
	pg_assert_same(array('x' => 520, 'y' => 55, 'w' => 100, 'h' => 60), $r['rtl'], 'right to left: column 1 at the right edge');
	pg_assert_same(array('x' => 0, 'y' => 0, 'w' => 510, 'h' => 115), $r['wide'], 'right to left: columns 2 and 3 to the left of it');

	$part = designer_gmm_hunt_js('style_designer_grid.js');
	pg_assert_true(strpos($part, "cs.direction === 'rtl'") !== false, 'the canvas tint reads the grid\'s direction');
}
