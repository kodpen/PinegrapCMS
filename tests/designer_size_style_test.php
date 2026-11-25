<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the designer's value per screen size (assets/js/pg_size_style.js):
 * a length at a size written as an @media (min-width: …) rule on the
 * element's pgs- class in User Styles, in size order, read back per size,
 * the value in force at a size, the sizes that hold a value, clearing a
 * size (its @media goes when empty), the base rule for every size, refused
 * values and the font size classes as values. Run under Node with
 * pg_user_styles.js and pg_custom_style.js; skipped without Node.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs $script with the model as S; returns what it prints as JSON, null
// without Node.
function designer_size_style_run($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgsize');

	if ($file === false) {
		return null;
	}

	$dir = PG_FUNCTIONS_DIR . '/assets/js';
	file_put_contents($file,
		"global.window = {};\n"
		. 'require(' . json_encode($dir . '/pg_user_styles.js') . ");\n"
		. 'require(' . json_encode($dir . '/pg_custom_style.js') . ");\n"
		. 'var S = require(' . json_encode($dir . '/pg_size_style.js') . ");\n"
		. $script
	);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	if ($code !== 0) {
		pg_assert_false(true, implode("\n", $out));

		return null;
	}

	return json_decode(implode("\n", $out), true);
}

function test_designer_size_style_write_in_size_order()
{
	$r = designer_size_style_run(
		'var o = {}, c = "pgs-abc123", t = ".a { color: red }\n";'
		. 'o.md = S.write(t, c, "width", "md", "50%");'
		. 'o.xs = S.write(o.md, c, "width", "xs", "100%");'
		. 'o.sm = S.write(o.xs, c, "width", "sm", "80%");'
		. 'o.xxl = S.write(o.sm, c, "width", "xxl", "30%");'
		. 'o.two = S.write(o.xxl, c, "height", "md", "10rem");'
		. 'o.change = S.write(o.two, c, "width", "md", "60%");'
		. 'console.log(JSON.stringify(o));'
	);

	if ($r === null) {
		return;
	}

	$md = "@media (min-width: 768px) {\n  .pgs-abc123 {\n    width: 50%;\n  }\n}\n";
	pg_assert_same(".a { color: red }\n\n" . $md, $r['md'], 'a size is an @media rule on the class');
	pg_assert_same(".a { color: red }\n\n.pgs-abc123 {\n  width: 100%;\n}\n\n" . $md, $r['xs'], 'every size is the base rule, before the @media rules');
	$sm = "@media (min-width: 576px) {\n  .pgs-abc123 {\n    width: 80%;\n  }\n}\n";
	pg_assert_same(".a { color: red }\n\n.pgs-abc123 {\n  width: 100%;\n}\n\n" . $sm . "\n" . $md, $r['sm'], 'a smaller size goes before a larger one');
	pg_assert_true(substr($r['xxl'], -strlen("@media (min-width: 1400px) {\n  .pgs-abc123 {\n    width: 30%;\n  }\n}\n")) === "@media (min-width: 1400px) {\n  .pgs-abc123 {\n    width: 30%;\n  }\n}\n", 'the largest size goes last');
	pg_assert_true(strpos($r['two'], "    width: 50%;\n    height: 10rem;\n") !== false, 'a second property joins the size\'s rule');
	pg_assert_true(strpos($r['change'], "    width: 60%;\n    height: 10rem;\n") !== false, 'a change writes the value in place');
	pg_assert_same(1, substr_count($r['change'], '(min-width: 768px)'), 'one rule per size');
}

function test_designer_size_style_read_and_held()
{
	$r = designer_size_style_run(
		'var o = {}, c = "pgs-abc123";'
		. 'var t = ".pgs-abc123 { width: 100%; }\n@media (min-width: 768px) { .pgs-abc123 { width: 50%; height: 2rem } }\n'
		. '@media (min-width:1200px){.pgs-abc123{width:40%}}\n@media (min-width: 800px) { .pgs-abc123 { width: 1px } }\n'
		. '@media (min-width: 992px) { .pgs-abc123 .x { width: 2px } .pgs-other1 { width: 3px } }\n";'
		. 'o.read = S.read(t, c, "width");'
		. 'o.lg = S.at(o.read, "lg"); o.md = S.at(o.read, "md"); o.none = S.at(S.read(t, c, "top"), "xl");'
		. 'o.held = S.held(t, c, ["width", "height"]); o.heldH = S.held(t, c, ["height"]);'
		. 'o.per = S.perSize(t, c, "width"); o.perTop = S.perSize(t, c, "top");'
		. 'o.badCls = S.read(t, "abc", "width").md;'
		. 'o.sizeOf = [S.sizeOf(""), S.sizeOf("@media (min-width:576px)"), S.sizeOf("@media (max-width: 576px)"), S.sizeOf("@media print")];'
		. 'console.log(JSON.stringify(o));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_same(array('xs' => '100%', 'sm' => '', 'md' => '50%', 'lg' => '', 'xl' => '40%', 'xxl' => ''), $r['read'], 'only the breakpoints\' own @media rules of the class alone');
	pg_assert_same(array('value' => '50%', 'here' => false, 'size' => 'md'), $r['lg'], 'a size without a value takes the smaller size\'s');
	pg_assert_same(array('value' => '50%', 'here' => true, 'size' => 'md'), $r['md']);
	pg_assert_same(array('value' => '', 'here' => false, 'size' => ''), $r['none']);
	pg_assert_same(array('md', 'xl'), $r['held'], 'the sizes above xs holding a value');
	pg_assert_same(array('md'), $r['heldH']);
	pg_assert_true($r['per']);
	pg_assert_false($r['perTop']);
	pg_assert_same('', $r['badCls'], 'only a pgs- class');
	pg_assert_same(array('xs', 'sm', '', ''), $r['sizeOf']);
}

function test_designer_size_style_clear()
{
	$r = designer_size_style_run(
		'var o = {}, c = "pgs-abc123";'
		. 'var t = ".a {}\n\n.pgs-abc123 {\n  width: 100%;\n}\n\n@media (min-width: 768px) {\n  .pgs-abc123 {\n    width: 50%;\n    height: 2rem;\n  }\n}\n\n'
		. '@media (min-width: 992px) {\n  .pgs-abc123 {\n    width: 40%;\n  }\n  .b { color: red; }\n}\n\n@media (min-width: 1200px) {\n  .pgs-abc123 {\n    /* keep */\n    width: 30%;\n  }\n}\n";'
		. 'o.oneOfTwo = S.write(t, c, "width", "md", "");'
		. 'o.media = S.write(o.oneOfTwo, c, "height", "md", "");'
		. 'o.shared = S.write(t, c, "width", "lg", "");'
		. 'o.comment = S.write(t, c, "width", "xl", "");'
		. 'o.base = S.write(t, c, "width", "xs", "");'
		. 'o.nothing = S.write(t, c, "top", "md", "") === t;'
		. 'console.log(JSON.stringify(o));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_true(strpos($r['oneOfTwo'], ".pgs-abc123 {\n    height: 2rem;\n  }") !== false, 'the other property stays');
	pg_assert_same(0, substr_count($r['media'], '768px'), 'an emptied size takes its @media with it');
	pg_assert_true(strpos($r['media'], "width: 100%;\n}\n\n@media (min-width: 992px)") !== false, 'one blank line between the neighbours');
	pg_assert_true(strpos($r['shared'], "@media (min-width: 992px) {\n  .b { color: red; }\n}") !== false, 'an @media holding another rule stays');
	pg_assert_true(strpos($r['comment'], "/* keep */") !== false && strpos($r['comment'], '30%') === false, 'a rule with a comment keeps the comment');
	pg_assert_true(strpos($r['base'], ".pgs-abc123 {\n  width: 100%") === false && strpos($r['base'], '50%') !== false, 'clearing every size leaves the sizes');
	pg_assert_true($r['nothing']);
}

function test_designer_size_style_refused()
{
	$r = designer_size_style_run(
		'var o = {}, c = "pgs-abc123", t = ".a {}\n";'
		. 'o.brace = S.write(t, c, "width", "md", "1px } .b { color: red") === t;'
		. 'o.semi = S.write(t, c, "width", "md", "1px; color: red") === t;'
		. 'o.cls = S.write(t, "foo", "width", "md", "1px") === t;'
		. 'o.size = S.write(t, c, "width", "huge", "1px") === t;'
		. 'o.str = S.write("", c, "font-family", "md", "\"a;b\"");'
		. 'console.log(JSON.stringify(o));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_true($r['brace'], 'a brace would end the rule');
	pg_assert_true($r['semi'], 'a semicolon would start another declaration');
	pg_assert_true($r['cls'], 'only a pgs- class');
	pg_assert_true($r['size'], 'only the six sizes');
	pg_assert_true(strpos($r['str'], 'font-family: "a;b";') !== false, 'inside a string it is text');
}

function test_designer_size_style_font_and_length()
{
	$r = designer_size_style_run(
		'var o = {};'
		. 'o.fs = S.fontValue("x fs-3 y"); o.none = S.fontValue("lead");'
		. 'o.back = S.fontClass("min(1.3rem + 0.6vw, 1.75rem)", ["fs-1", "fs-3"]);'
		. 'o.same = S.fontClass(S.FONT["fs-1"], ["display-6", "fs-1"]);'
		. 'o.other = S.fontClass("13px", ["fs-1"]);'
		. 'o.len = [S.len("48"), S.len("-8"), S.len("50%"), S.len(" auto "), S.len("")];'
		. 'o.media = [S.media("xs"), S.media("lg")];'
		. 'console.log(JSON.stringify(o));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_same(array('cls' => 'fs-3', 'value' => 'min(1.3rem + 0.6vw, 1.75rem)'), $r['fs'], 'Bootstrap\'s fs-3 as one value');
	pg_assert_same(null, $r['none']);
	pg_assert_same('fs-3', $r['back']);
	pg_assert_same('fs-1', $r['same'], 'fs-1 before display-6, which draws the same size');
	pg_assert_same('', $r['other']);
	pg_assert_same(array('48px', '-8px', '50%', 'auto', ''), $r['len'], 'a bare number is pixels');
	pg_assert_same(array('', '@media (min-width: 992px)'), $r['media']);
}

// The chooser is the spacing part's, on Size and display and Text: the
// module rides in that part's bundle, the core hands the part a host and
// puts the canvas at the chosen size.
function test_designer_size_style_is_in_the_spacing_part()
{
	$bundles = pg_js_bundles();
	pg_assert_true(in_array('assets/js/pg_size_style.js', $bundles['assets/js/style_designer_spacing.min.js'], true), 'the module is in the spacing part');
	pg_assert_false(in_array('assets/js/pg_size_style.js', $bundles['assets/js/style_designer.min.js'], true), 'not in the core');

	$core = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_contains("_sdPartCall('spacing', 'sizes', [_sdSzHost(sz, nodes)])", $core, 'the core draws the chooser through the part');
	pg_assert_same(2, substr_count($core, "'<div data-sd-szbox hidden></div>"), 'Size and display and Text hold its place');
	pg_assert_contains('_sdCanvasToSize(s);', $core, 'choosing a size puts the canvas at it');
	pg_assert_contains('setSize: _sdSpSizeSet,', $core, 'one chosen size for the spacing box and the lengths');

	$part = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_spacing.js');
	pg_assert_contains('sizes: sizes', $part, 'the part draws the chooser');
	pg_assert_contains("(has[s] ? ' is-has' : '')", $part, 'a size holding a value carries the dot');
	pg_assert_contains('.sd-spb-size.is-has::after', (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/style_designer.css'), 'the dot');
}
