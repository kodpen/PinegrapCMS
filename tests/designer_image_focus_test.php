<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a picture's focus point (includes/fn/image_focus.php): the pair
 * of percentages read and written, a pair of words read, the value per
 * screen size, the background's twin in a style attribute, and the Image
 * element's site markup. The designer's twins (_sdFocus*()) are cut out of
 * assets/js/style_designer.src.js and run under Node on the same cases; on
 * a machine without Node that half is skipped.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Values read: [typed value, what it reads as (formatted) or null].
function designer_image_focus_test_parse_cases()
{
	return array(
		'a pair of percentages' => array('30% 20%', '30% 20%'),
		'one percentage is the x' => array('25%', '25% 50%'),
		'decimals' => array('12.5% 99.4%', '13% 99%'),
		'clamped' => array('-10% 140%', '0% 100%'),
		'zero without a unit' => array('0 0', '0% 0%'),
		'center' => array('center', '50% 50%'),
		'top' => array('top', '50% 0%'),
		'right' => array('right', '100% 50%'),
		'center top' => array('center top', '50% 0%'),
		'top center' => array('top center', '50% 0%'),
		'left bottom' => array('left bottom', '0% 100%'),
		'bottom right' => array('bottom right', '100% 100%'),
		'upper case and spaces' => array('  Right   Top ', '100% 0%'),
		'a word and a percentage' => array('left 20%', '0% 20%'),
		'a percentage and a word' => array('20% bottom', '20% 100%'),
		'empty' => array('', null),
		'pixels are not read' => array('10px 20px', null),
		'two horizontals' => array('left right', null),
		'a vertical word before a percentage' => array('top 20%', null),
		'three parts' => array('left top 10%', null),
		'not a position' => array('red', null),
	);
}

// The values per screen and what each screen shows, the decls and the class.
function designer_image_focus_test_write_cases()
{
	return array(
		'nothing set' => array(array('desktop' => '', 'tablet' => '', 'phone' => ''), ''),
		'one value as today' => array(array('desktop' => 'center top', 'tablet' => '', 'phone' => ''), 'object-position:center top'),
		'a phone value' => array(array('desktop' => '30% 20%', 'tablet' => '', 'phone' => '70% 10%'),
			'object-position:var(--pg-focus,30% 20%);--pg-focus-d:30% 20%;--pg-focus-t:30% 20%;--pg-focus-p:70% 10%'),
		'a tablet value is the phone\'s too' => array(array('desktop' => '', 'tablet' => '40% 60%', 'phone' => ''),
			'object-position:var(--pg-focus,50% 50%);--pg-focus-d:50% 50%;--pg-focus-t:40% 60%;--pg-focus-p:40% 60%'),
		'an unreadable screen value is left out' => array(array('desktop' => 'top', 'tablet' => 'x;color:red', 'phone' => ''), 'object-position:top'),
	);
}

function test_image_focus_parse()
{
	foreach (designer_image_focus_test_parse_cases() as $label => $case) {
		$xy = pg_focus_parse($case[0]);
		pg_assert_same($case[1], $xy === null ? null : pg_focus_format($xy[0], $xy[1]), $label);
	}
	pg_assert_same(array(50.0, 0.0), pg_focus_parse('top'), 'numbers');
}

function test_image_focus_screen_by_width()
{
	foreach (array(375 => 'phone', 576 => 'phone', 767 => 'phone', 768 => 'tablet', 991 => 'tablet', 992 => 'desktop', 1400 => 'desktop', 0 => 'desktop') as $w => $screen) {
		pg_assert_same($screen, pg_focus_screen($w), (string) $w);
	}
	pg_assert_same('desktop', pg_focus_screen(''), 'the full width');
}

function test_image_focus_per_screen()
{
	$v = pg_focus_set(pg_focus_values(array()), 'desktop', '30% 20%');
	pg_assert_same('30% 20%', pg_focus_for_screen($v, 'phone'), 'the desktop value on every screen');
	$v = pg_focus_set($v, 'tablet', '40% 40%');
	pg_assert_same('40% 40%', pg_focus_for_screen($v, 'phone'), 'the phone follows the tablet');
	pg_assert_same('30% 20%', pg_focus_for_screen($v, 'desktop'), 'the desktop keeps its own');
	$v = pg_focus_set($v, 'phone', '90% 0%');
	pg_assert_same('90% 0%', pg_focus_for_screen($v, 'phone'), 'the phone\'s own');
	pg_assert_same('40% 40%', pg_focus_for_screen($v, 'tablet'), 'the tablet\'s own');
	pg_assert_true(pg_focus_has_screens($v), 'per screen');
	$same = pg_focus_same_everywhere($v);
	pg_assert_same(array('desktop' => '30% 20%', 'tablet' => '', 'phone' => ''), $same, 'Same on every screen');
	pg_assert_false(pg_focus_has_screens($same), 'one value');

	$props = pg_focus_image_props(array('src' => 'a.jpg', 'objectPositionPhone' => 'top'), $v);
	pg_assert_same(array('src' => 'a.jpg', 'objectPosition' => '30% 20%', 'objectPositionTablet' => '40% 40%', 'objectPositionPhone' => '90% 0%'), $props, 'written to the props');
	pg_assert_same($v, pg_focus_values($props), 'read from the props');
	pg_assert_same(array('src' => 'a.jpg', 'objectPosition' => '30% 20%'), pg_focus_image_props($props, $same), 'the others removed');
}

function test_image_focus_decls()
{
	foreach (designer_image_focus_test_write_cases() as $label => $case) {
		pg_assert_same($case[1], pg_focus_decls($case[0], 'object-position'), $label);
	}
}

function test_image_focus_background_style()
{
	$style = 'background-image:url("data:image/png;base64,AAA=");background-size:cover;background-position:center top;color:red';
	pg_assert_same(array('desktop' => 'center top', 'tablet' => '', 'phone' => ''), pg_focus_style_read($style), 'today\'s background');

	$v = array('desktop' => 'center top', 'tablet' => '', 'phone' => '20% 80%');
	$written = pg_focus_style_write($style, $v);
	pg_assert_same('background-image:url("data:image/png;base64,AAA=");background-size:cover;background-position:var(--pg-focus,center top);--pg-focus-d:center top;--pg-focus-t:center top;--pg-focus-p:20% 80%;color:red', $written, 'per screen, in place');
	pg_assert_same($v, pg_focus_style_read($written), 'read back');
	pg_assert_same($style, pg_focus_style_write($written, pg_focus_same_everywhere($v)), 'back to the one value');
	pg_assert_same('color:red;background-position:10% 10%', pg_focus_style_write('color:red', array('desktop' => '10% 10%', 'tablet' => '', 'phone' => '')), 'added at the end');
	pg_assert_same('color:red', pg_focus_style_write('color:red;background-position:top', array('desktop' => '', 'tablet' => '', 'phone' => '')), 'removed');
}

// The rule that picks a screen's value lives with the block rules, so the
// designer adds it to User Styles the way it adds a block's.
function test_image_focus_rule()
{
	$css = pg_designer_block_css(array('pg-focus'));
	pg_assert_contains('.pg-focus { --pg-focus: var(--pg-focus-d); }', $css, 'the desktop');
	pg_assert_contains('@media (max-width: 991.98px) { .pg-focus { --pg-focus: var(--pg-focus-t); } }', $css, 'the tablet');
	pg_assert_contains('@media (max-width: 767.98px) { .pg-focus { --pg-focus: var(--pg-focus-p); } }', $css, 'the phone');
}

function designer_image_focus_test_page($props)
{
	$node = array('type' => 'content', 'props' => array_merge(array('contentType' => 'image', 'src' => 'https://example.com/a.jpg', 'alt' => 'A'), $props), 'children' => array());
	return trim(preg_replace('/\s+data-pg-fallback="[^"]*"|\s+onerror="[^"]*"/', '', _render_tree_node($node, 0)));
}

function test_image_focus_site_render()
{
	pg_assert_same('<img src="https://example.com/a.jpg" alt="A">', designer_image_focus_test_page(array()), 'no focus as today');
	pg_assert_same('<img src="https://example.com/a.jpg" alt="A" style="object-position:center top">', designer_image_focus_test_page(array('objectPosition' => 'center top')), 'one value as today');
	pg_assert_same('<img src="https://example.com/a.jpg" alt="A" class="object-fit-cover pg-focus" style="object-position:var(--pg-focus,30% 20%);--pg-focus-d:30% 20%;--pg-focus-t:30% 20%;--pg-focus-p:70% 10%">',
		designer_image_focus_test_page(array('cssClass' => 'object-fit-cover', 'objectPosition' => '30% 20%', 'objectPositionPhone' => '70% 10%')), 'per screen');
}

// The designer's twins give the same answers on the same cases.
function test_image_focus_js_twin()
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) {
		return;
	}

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$from = strpos($source, '    function _sdFocusParse(');
	$to = strpos($source, '    function _sdNoImageUrl(', (int) $from);
	pg_assert_true(($from !== false) && ($to !== false), 'the designer has the twins');
	$img = strpos($source, "        image: {\n            label: _sdT('Image')");
	pg_assert_true($img !== false, 'the designer has the Image element');

	$parse = array();
	foreach (designer_image_focus_test_parse_cases() as $label => $case) {
		$parse[$label] = $case[0];
	}
	$write = array();
	foreach (designer_image_focus_test_write_cases() as $label => $case) {
		$write[$label] = $case[0];
	}
	$style = 'background-image:url("data:image/png;base64,AAA=");background-size:cover;background-position:center top;color:red';
	$v = array('desktop' => 'center top', 'tablet' => '', 'phone' => '20% 80%');
	$props = array('src' => 'a.jpg', 'alt' => 'A', 'cssClass' => 'object-fit-cover', 'objectPosition' => '30% 20%', 'objectPositionPhone' => '70% 10%');

	$script = 'function esc(s) { return s ? String(s).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;") : ""; }'
		. substr($source, $from, $to - $from)
		. 'var parse = ' . json_encode($parse) . ', write = ' . json_encode($write) . ', out = { parse: {}, write: {}, screen: {} };'
		. 'Object.keys(parse).forEach(function (k) { var xy = _sdFocusParse(parse[k]); out.parse[k] = xy ? _sdFocusFormat(xy[0], xy[1]) : null; });'
		. 'Object.keys(write).forEach(function (k) { out.write[k] = _sdFocusDecls(write[k], "object-position"); });'
		. '[375, 576, 767, 768, 991, 992, 1400, 0, ""].forEach(function (w) { out.screen[String(w)] = _sdFocusScreen(w); });'
		. 'var v = _sdFocusSet(_sdFocusValues({}), "desktop", "30% 20%"); v = _sdFocusSet(v, "tablet", "40% 40%");'
		. 'out.follows = _sdFocusForScreen(v, "phone"); v = _sdFocusSet(v, "phone", "90% 0%");'
		. 'out.same = _sdFocusSameEverywhere(v); out.has = [_sdFocusHasScreens(v), _sdFocusHasScreens(out.same)];'
		. 'out.props = _sdFocusImageProps({ src: "a.jpg", objectPositionPhone: "top" }, v);'
		. 'out.propsSame = _sdFocusImageProps(out.props, out.same);'
		. 'var st = ' . json_encode($style) . ', bv = ' . json_encode($v) . ';'
		. 'out.bgRead = _sdFocusStyleRead(st); out.bgWrite = _sdFocusStyleWrite(st, bv); out.bgBack = _sdFocusStyleRead(out.bgWrite);'
		. 'out.bgSame = _sdFocusStyleWrite(out.bgWrite, _sdFocusSameEverywhere(bv));'
		. 'out.imgStyle = _sdFocusImageStyle(' . json_encode($props) . '); out.imgClass = _sdFocusImageClass(' . json_encode($props) . ');'
		. 'out.imgNone = [_sdFocusImageStyle({ objectPosition: "top" }), _sdFocusImageClass({ objectPosition: "top" })];'
		. 'console.log(JSON.stringify(out));';

	$file = tempnam(sys_get_temp_dir(), 'pgfocus');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, implode("\n", $out));
	$js = json_decode(implode("\n", $out), true);

	foreach (designer_image_focus_test_parse_cases() as $label => $case) {
		pg_assert_same($case[1], array_key_exists($label, $js['parse']) ? $js['parse'][$label] : 'missing', 'twin parse: ' . $label);
	}
	foreach (designer_image_focus_test_write_cases() as $label => $case) {
		pg_assert_same($case[1], $js['write'][$label] ?? 'missing', 'twin decls: ' . $label);
	}
	foreach (array('375', '576', '767', '768', '991', '992', '1400', '0', '') as $w) {
		pg_assert_same(pg_focus_screen($w), $js['screen'][$w] ?? 'missing', 'twin screen: ' . $w);
	}
	pg_assert_same('40% 40%', $js['follows'] ?? null, 'twin: the phone follows the tablet');
	pg_assert_same(array('desktop' => '30% 20%', 'tablet' => '', 'phone' => ''), $js['same'] ?? null, 'twin: Same on every screen');
	pg_assert_same(array(true, false), $js['has'] ?? null, 'twin: per screen');
	pg_assert_same(array('src' => 'a.jpg', 'objectPosition' => '30% 20%', 'objectPositionTablet' => '40% 40%', 'objectPositionPhone' => '90% 0%'), $js['props'] ?? null, 'twin: written to the props');
	pg_assert_same(array('src' => 'a.jpg', 'objectPosition' => '30% 20%'), $js['propsSame'] ?? null, 'twin: the others removed');
	pg_assert_same(pg_focus_style_read($style), $js['bgRead'] ?? null, 'twin: background read');
	pg_assert_same(pg_focus_style_write($style, $v), $js['bgWrite'] ?? null, 'twin: background written');
	pg_assert_same($v, $js['bgBack'] ?? null, 'twin: background read back');
	pg_assert_same($style, $js['bgSame'] ?? null, 'twin: background back to one value');
	pg_assert_same(pg_focus_decls(pg_focus_values($props), 'object-position'), $js['imgStyle'] ?? null, 'twin: the image style');
	pg_assert_same('pg-focus', $js['imgClass'] ?? null, 'twin: the image class');
	pg_assert_same(array('object-position:top', ''), $js['imgNone'] ?? null, 'twin: one value, no class');
}

// The editor's HTML and canvas write the image focus through the twins.
function test_image_focus_editor_writers()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_contains("var style = _sdFocusImageStyle(p) ? ' style=\"' + esc(_sdFocusImageStyle(p)) + '\"' : '';", $source, 'toHTML');
	pg_assert_contains('_sdFocusImageClass(p)', $source, 'the class');
	pg_assert_contains("_inlineBase = (_inlineBase ? _inlineBase + ';' : '') + _sdFocusImageStyle(node.props);", $source, 'the canvas style');
}
