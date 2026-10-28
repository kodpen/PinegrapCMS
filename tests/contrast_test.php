<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for colour contrast: the ratio the theme palette builder computes
 * (_pg_theme_contrast() in includes/fn/design_themes.php) against WCAG's own
 * figures, and its browser twin assets/js/pg_contrast.js, which the visual
 * designer's audit uses, run under Node on the same colours to give the same
 * ratios. On a machine without Node the browser half is skipped.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The colour pairs both halves are measured on.
function contrast_test_pairs()
{
	return array(
		array('#000000', '#ffffff'),
		array('#767676', '#ffffff'),
		array('#777777', '#ffffff'),
		array('#6c757d', '#ffffff'),
		array('#0d6efd', '#ffffff'),
		array('#ffc107', '#212529'),
		array('#198754', '#f8f9fa'),
		array('#ff0000', '#00ff00'),
		array('#123456', '#abcdef'),
		array('#ffffff', '#ffffff'),
	);
}

// Runs a script under Node with assets/js/pg_contrast.js loaded as C and
// returns what it prints as JSON; null when there is no Node to run it.
function contrast_test_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgcontrast');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, 'var C = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_contrast.js') . ");\n" . $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	if ($code !== 0) {
		return array('error' => implode("\n", $out));
	}

	return json_decode(implode("\n", $out), true);
}

// WCAG's own figures: black on white is 21:1 and a colour on itself 1:1, in
// either order; #767676 is the lightest grey that still reads at 4.5:1 on
// white, #777777 the first one that does not.
function test_contrast_ratio_matches_wcag()
{
	$white = array(255, 255, 255);

	pg_assert_same('21.00', sprintf('%.2f', _pg_theme_contrast(array(0, 0, 0), $white)), 'black on white');
	pg_assert_same('21.00', sprintf('%.2f', _pg_theme_contrast($white, array(0, 0, 0))), 'white on black');
	pg_assert_same('1.00', sprintf('%.2f', _pg_theme_contrast(array(80, 90, 100), array(80, 90, 100))), 'a colour on itself');
	pg_assert_true(_pg_theme_contrast(_pg_theme_rgb('#767676'), $white) >= 4.5, '#767676 passes');
	pg_assert_true(_pg_theme_contrast(_pg_theme_rgb('#777777'), $white) < 4.5, '#777777 fails');
	pg_assert_same('4.69', sprintf('%.2f', _pg_theme_contrast(_pg_theme_rgb('#6c757d'), $white)), "Bootstrap's secondary grey");
}

// The browser's ratio is the server's, colour for colour.
function test_contrast_script_ratio_matches_php()
{
	$result = contrast_test_node(
		'var pairs = ' . json_encode(contrast_test_pairs()) . ";\n"
		. 'console.log(JSON.stringify(pairs.map(function (p) { return C.ratio(C.parse(p[0]), C.parse(p[1])); })));'
	);

	if ($result === null) {
		return;
	}

	pg_assert_false(isset($result['error']), isset($result['error']) ? $result['error'] : 'ran');

	foreach (contrast_test_pairs() as $i => $pair) {
		$php = _pg_theme_contrast(_pg_theme_rgb($pair[0]), _pg_theme_rgb($pair[1]));
		pg_assert_same(sprintf('%.9f', $php), isset($result[$i]) ? sprintf('%.9f', $result[$i]) : null, $pair[0] . ' on ' . $pair[1]);
	}
}

// Colours as getComputedStyle() gives them, and what cannot be read.
function test_contrast_script_reads_css_colours()
{
	$values = array(
		'rgb(31, 35, 40)', 'rgba(0, 0, 0, 0)', 'rgba(0, 0, 0, 0.5)', 'rgb(255 0 0 / 50%)',
		'#fff', '#ffffff80', 'transparent', 'color(srgb 1 0 0.5)', 'oklch(0.5 0.1 200)', 'red', '',
	);

	$result = contrast_test_node(
		'console.log(JSON.stringify(' . json_encode($values) . '.map(function (v) { return C.parse(v); })));'
	);

	if ($result === null) {
		return;
	}

	pg_assert_same(array(
		array(31, 35, 40, 1),
		array(0, 0, 0, 0),
		array(0, 0, 0, 0.5),
		array(255, 0, 0, 0.5),
		array(255, 255, 255, 1),
		array(255, 255, 255, 0.502),
		array(0, 0, 0, 0),
		array(255, 0, 127.5, 1),
		null,
		null,
		null,
	), $result);
}

// A translucent colour laid over an opaque one, and the minimum ratio by
// text size: 3:1 from 24px, or from 18.66px (14pt) when bold; 4.5:1 below.
function test_contrast_script_blends_and_sizes()
{
	$result = contrast_test_node(
		'console.log(JSON.stringify({'
		. 'half: C.over([0, 0, 0, 0.5], [255, 255, 255, 1]),'
		. 'none: C.over([10, 20, 30, 0], [200, 100, 50, 1]),'
		. 'sizes: [["16px", "400"], ["24px", "400"], ["18.6667px", "700"], ["18.6667px", "600"], ["20px", "bold"], ["23.9px", "normal"]].map(function (s) { return C.minimum(s[0], s[1]); })'
		. '}));'
	);

	if ($result === null) {
		return;
	}

	pg_assert_same(array(127.5, 127.5, 127.5, 1), $result['half'], 'half black over white');
	pg_assert_same(array(200, 100, 50, 1), $result['none'], 'a transparent colour changes nothing');
	pg_assert_same(array(4.5, 3, 3, 4.5, 3, 4.5), $result['sizes'], 'minimum by size');
}

// Opacity on the way to the text: below the text's backdrop element it fades
// the text alone; at the backdrop element or above it, text and backdrop
// fade together over what lies under them. Bootstrap's opacity-75 white text
// on the secondary grey is drawn 3.4:1, not the 4.7:1 of its colours.
function test_contrast_script_through_opacity()
{
	$result = contrast_test_node(
		'var cta = C.throughOpacity([255, 255, 255, 1], [108, 117, 125, 1], 0.75, []);'
		. 'var body = C.throughOpacity([33, 37, 41, 1], [255, 255, 255, 1], 0.5, []);'
		. 'var group = C.throughOpacity([255, 255, 255, 1], [13, 110, 253, 1], 1, [{ opacity: 0.5, under: [255, 255, 255, 1] }]);'
		. 'var none = C.throughOpacity([33, 37, 41, 0.5], [255, 255, 255, 1], 1, []);'
		. 'console.log(JSON.stringify({'
		. 'cta: cta.color, ctaRatio: C.ratio(cta.color, cta.background),'
		. 'bodyRatio: C.ratio(body.color, body.background),'
		. 'group: group.background, groupRatio: C.ratio(group.color, group.background),'
		. 'none: none.color'
		. '}));'
	);

	if ($result === null) {
		return;
	}

	pg_assert_false(isset($result['error']), isset($result['error']) ? $result['error'] : 'ran');
	pg_assert_same(array(218.25, 220.5, 222.5, 1), $result['cta'], 'the glyph colour the browser draws');
	pg_assert_same('3.4', sprintf('%.1f', $result['ctaRatio']), 'opacity-75 on the secondary grey (drawn 3.41 with whole channels)');
	pg_assert_true($result['bodyRatio'] < 4.5, 'body text in a half-transparent box: ' . $result['bodyRatio']);
	pg_assert_same(array(134, 182.5, 254, 1), $result['group'], 'a faded backdrop over white');
	pg_assert_true($result['groupRatio'] < 3, 'white text on a faded blue: ' . $result['groupRatio']);
	pg_assert_same(array(144, 146, 148, 1), $result['none'], 'a translucent text colour as before');
}

// check() walks the element and its ancestors for opacity: a test of the
// walk under Node needs a page, so the source is read.
function test_contrast_check_reads_opacity_on_the_way()
{
	$script = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_contrast.js');
	$from = strpos($script, 'function check(');
	$section = substr($script, $from, strpos($script, 'var api = {') - $from);

	pg_assert_contains('n !== bg.element', $section, 'below the backdrop element the text fades alone');
	pg_assert_contains('backdrop(m.parentElement, opts, cache)', $section, 'at it or above, over what lies under it');
	pg_assert_contains('throughOpacity(fg, bg.color, text, groups)', $section, 'both in one place');
}

// A label on a user-chosen colour keeps white where it reads at 4.5:1 and
// turns near-black where it does not (orange, light purple).
function test_contrast_readable_ink_on()
{
	pg_assert_same('#fff', pg_readable_ink_on('#1d4ed8'), 'dark blue');
	pg_assert_same('#18181b', pg_readable_ink_on('#f59e0b'), 'orange');
	pg_assert_same('#18181b', pg_readable_ink_on('#c4b5fd'), 'light purple');
	pg_assert_same('#18181b', pg_readable_ink_on('#fff'), 'three-digit white');
	pg_assert_same('#fff', pg_readable_ink_on('var(--x)'), 'not a colour: as before');
}
