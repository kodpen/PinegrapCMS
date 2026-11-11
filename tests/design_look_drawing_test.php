<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the small drawing of a look (pg_design_look_drawing() in
 * includes/fn/design_themes.php and its twin _sdLookDrawing() in
 * style_designer.src.js): every look has every key the drawing reads, each
 * key changes the drawing, and both twins give the same SVG for every look.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The previews drawn: Plain Bootstrap, every look, and a few edge rows.
function design_look_drawing_test_previews()
{
	$rows = array('plain' => pg_design_plain_look_preview());
	foreach (pg_design_looks() as $key => $look) {
		$rows[$key] = $look['preview'];
	}
	$rows['edge-big'] = array('radius' => 40, 'btn_radius' => 30, 'shadow' => 'glow', 'border' => 5, 'heading' => 'mono', 'weight' => 950, 'caps' => true);
	$rows['edge-unknown'] = array('radius' => 3, 'btn_radius' => 1, 'shadow' => 'neon', 'border' => 0, 'heading' => 'script', 'weight' => 50, 'caps' => false);
	$rows['edge-empty'] = array();
	return $rows;
}

function test_design_look_drawing_every_look_has_every_key()
{
	$keys = array_keys(pg_design_plain_look_preview());
	foreach (pg_design_looks() as $key => $look) {
		foreach ($keys as $field) {
			pg_assert_true(isset($look['preview']) && array_key_exists($field, $look['preview']), 'preview of ' . $key . ' has ' . $field);
		}
	}
}

function test_design_look_drawing_is_one_small_svg()
{
	foreach (design_look_drawing_test_previews() as $key => $pv) {
		$svg = pg_design_look_drawing($pv);
		pg_assert_same(0, strpos($svg, '<svg class="pg-look-drawing" viewBox="0 0 48 32"'), $key . ' starts as the 48×32 drawing');
		pg_assert_same(1, substr_count($svg, '</svg>'), $key . ' is one SVG');
		pg_assert_true(substr($svg, -6) === '</svg>', $key . ' ends with the SVG');
		pg_assert_true(!preg_match('/NaN|INF|undefined|null|-0[^.]/', $svg), $key . ' has no broken number');
		pg_assert_true(strpos($svg, '#') === false, $key . ' is drawn in currentColor only');
	}
	pg_assert_same(pg_design_look_drawing(pg_design_plain_look_preview()), pg_design_look_drawing(null), 'no preview draws Plain Bootstrap');
}

function test_design_look_drawing_follows_each_key()
{
	$base = pg_design_plain_look_preview();
	$plain = pg_design_look_drawing($base);
	$changes = array(
		'radius'     => 20,
		'btn_radius' => 99,
		'shadow'     => 'hard',
		'border'     => 0,
		'heading'    => 'serif',
		'weight'     => 800,
		'caps'       => true,
	);
	foreach ($changes as $field => $value) {
		$pv = $base;
		$pv[$field] = $value;
		pg_assert_true(pg_design_look_drawing($pv) !== $plain, $field . ' changes the drawing');
	}
	$pv = $base;
	$pv['btn_radius'] = 99;
	pg_assert_contains('a2.5 2.5 0 0 1 2.5 2.5', pg_design_look_drawing($pv), 'a pill button has round ends');
	$pv = $base;
	$pv['radius'] = 0;
	$pv['btn_radius'] = 0;
	pg_assert_contains('rx="0"', pg_design_look_drawing($pv), 'a square look draws a square card');
	pg_assert_contains('M9 19h15v5h-15z', pg_design_look_drawing($pv), 'a square look draws a square button');
}

function test_design_look_drawing_twins_give_the_same_svg()
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) {
		pg_assert_true(true, 'Node is not installed: the twins are compared in CI');
		return;
	}
	$js = (string)file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$cut = function ($from, $to) use ($js) {
		$start = strpos($js, $from);
		$end = $start === false ? false : strpos($js, $to, $start);
		pg_assert_true($start !== false && $end !== false, 'the core holds ' . trim($from));
		return ($start === false || $end === false) ? '' : substr($js, $start, $end - $start + strlen($to));
	};
	$rows = design_look_drawing_test_previews();
	$script = "'use strict';\n"
		. $cut('    var _SD_PLAIN_LOOK_PREVIEW = {', "};\n")
		. $cut('    function _sdLookDrawing(pv) {', "\n    }\n")
		. 'var rows = ' . json_encode($rows) . ";\n"
		. "var out = {};\n"
		. "Object.keys(rows).forEach(function (k) { out[k] = _sdLookDrawing(Array.isArray(rows[k]) ? {} : rows[k]); });\n"
		. "out['@null'] = _sdLookDrawing(null);\n"
		. "process.stdout.write(JSON.stringify(out));\n";
	$file = tempnam(sys_get_temp_dir(), 'pglook');
	file_put_contents($file, $script);
	$lines = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $lines, $code);
	@unlink($file);
	$got = json_decode(implode("\n", $lines), true);
	pg_assert_true($code === 0 && is_array($got), 'the JavaScript twin runs: ' . substr(implode("\n", $lines), 0, 300));
	if (!is_array($got)) return;
	foreach ($rows as $key => $pv) {
		pg_assert_same(pg_design_look_drawing($pv), isset($got[$key]) ? $got[$key] : null, 'both twins draw ' . $key . ' alike');
	}
	pg_assert_same(pg_design_look_drawing(null), isset($got['@null']) ? $got['@null'] : null, 'both twins draw no preview alike');
}

function test_design_look_drawing_is_shown_in_both_places()
{
	$picker = (string)file_get_contents(PG_FUNCTIONS_DIR . '/includes/designer_screen.php');
	pg_assert_contains("pg_design_look_drawing(\$key === '' ? null : \$l['preview'])", $picker, 'the template chooser draws each look');
	$part = (string)file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_theme.js');
	pg_assert_contains("_sdLookDrawingOf(_sdThemeValue('look'))", $part, 'the Themes tab head row draws the design\'s look');
	pg_assert_true(strpos($part, 'bi-brush sd-theme-head-icon') === false, 'the brush icon gave way to the drawing');
}
