<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for how an Image element's width and height props are written: a
 * whole number as the width / height attribute, a value with a unit as a
 * style with that unit, never a number without a unit in a style
 * (pg_img_dimension() and the image case of _render_content_html()). The
 * editor's side (_sdImgDim(), _sdImgSizeCss(), the image's toHTML() and
 * addGlobalAttrs()) and the canvas's (the image's render() and
 * _sdApplyNodeAttrs(), on a small DOM stand-in) are cut out of
 * assets/js/style_designer.js and run under Node, and have to write the same
 * markup as the page; on a machine without Node that part is skipped.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The image nodes the tests draw: [props, what the page gets].
function designer_image_size_test_cases()
{
	$src = 'https://example.com/a.jpg';

	return array(
		'whole numbers' => array(
			array('width' => '48', 'height' => '48', 'cssClass' => 'rounded-circle'),
			'<img src="' . $src . '" alt="A" width="48" height="48" class="rounded-circle">',
		),
		'pixels with the unit' => array(
			array('width' => '120px', 'height' => '80px'),
			'<img src="' . $src . '" alt="A" style="width:120px;height:80px">',
		),
		'percent' => array(
			array('width' => '50%'),
			'<img src="' . $src . '" alt="A" style="width:50%">',
		),
		'rem and a keyword' => array(
			array('width' => '12rem', 'height' => 'auto'),
			'<img src="' . $src . '" alt="A" style="width:12rem;height:auto">',
		),
		'a bare decimal is pixels' => array(
			array('width' => '48.5'),
			'<img src="' . $src . '" alt="A" style="width:48.5px">',
		),
		'fluid with object-fit' => array(
			array('width' => '112', 'height' => '112', 'fluid' => true, 'cssClass' => 'object-fit-cover'),
			'<img src="' . $src . '" alt="A" width="112" height="112" class="img-fluid object-fit-cover">',
		),
		'one style attribute with object-position and the user style' => array(
			array('width' => '50%', 'objectPosition' => 'top', '_attrs' => array(array('name' => 'style', 'value' => 'opacity:.9'))),
			'<img src="' . $src . '" alt="A" style="object-position:top;width:50%;opacity:.9">',
		),
		'a ratio box takes whole numbers in pixels' => array(
			array('width' => '200', 'height' => '120', 'aspectRatio' => '16x9', 'overflow' => 'hidden'),
			'<div class="ratio ratio-16x9" style="width:200px;height:120px;overflow:hidden"><img src="' . $src . '" alt="A" width="200" height="120"></div>',
		),
		'a ratio box takes a unit, the image fills it' => array(
			array('width' => '50%', 'aspectRatio' => '1x1', 'objectPosition' => 'top'),
			'<div class="ratio ratio-1x1" style="width:50%"><img src="' . $src . '" alt="A" style="object-position:top"></div>',
		),
		'the other Sizing lengths, z-index and overflow' => array(
			array('width' => '48', 'height' => '48', 'minWidth' => '24', 'maxWidth' => '100%', 'top' => '4', 'zIndex' => '2', 'overflow' => 'hidden'),
			'<img src="' . $src . '" alt="A" width="48" height="48" style="z-index:2;min-width:24px;max-width:100%;overflow:hidden;top:4px">',
		),
		'the Sizing panel classes' => array(
			array('cssClass' => 'rounded-circle', 'srOnly' => true, 'focusRing' => true),
			'<img src="' . $src . '" alt="A" class="rounded-circle visually-hidden focus-ring">',
		),
		'in a ratio box the image keeps its id, attributes and style' => array(
			array('width' => '200', 'aspectRatio' => '16x9', 'id' => 'hero', 'objectPosition' => 'top', 'minHeight' => '100', 'overflow' => 'hidden',
				'_attrs' => array(array('name' => 'data-x', 'value' => '1'), array('name' => 'style', 'value' => 'opacity:.9'))),
			'<div class="ratio ratio-16x9" style="width:200px;min-height:100px;overflow:hidden"><img src="' . $src . '" alt="A" width="200" style="object-position:top;opacity:.9" id="hero" data-x="1"></div>',
		),
		'in a ratio box the image keeps the Sizing panel classes' => array(
			array('aspectRatio' => '1x1', 'srOnly' => true),
			'<div class="ratio ratio-1x1"><img src="' . $src . '" alt="A" class="visually-hidden"></div>',
		),
		'the Sizing panel\'s custom ratio is a box' => array(
			array('width' => '120', 'aspectRatio' => 'custom', 'aspectRatioEnabled' => true, 'aspectRatioCustom' => '75%'),
			'<div class="ratio" style="width:120px;--bs-aspect-ratio:75%"><img src="' . $src . '" alt="A" width="120" class="ratio"></div>',
		),
		'a custom ratio switched off is no box' => array(
			array('width' => '120', 'aspectRatio' => 'custom'),
			'<img src="' . $src . '" alt="A" width="120">',
		),
		// The editor's test of a prop: the string '0' is a size, the number
		// 0 is none (pg_designer_prop_text()).
		'the string 0 is a size' => array(
			array('width' => '0', 'height' => '0'),
			'<img src="' . $src . '" alt="A" width="0" height="0">',
		),
		'the number 0 is no size' => array(
			array('width' => 0, 'height' => 0, 'minWidth' => 0),
			'<img src="' . $src . '" alt="A">',
		),
		'the string 0 in a ratio box' => array(
			array('width' => '0', 'aspectRatio' => '1x1'),
			'<div class="ratio ratio-1x1" style="width:0px"><img src="' . $src . '" alt="A" width="0"></div>',
		),
		'a custom ratio of the string 0 is a box' => array(
			array('aspectRatio' => 'custom', 'aspectRatioEnabled' => true, 'aspectRatioCustom' => '0', 'objectPosition' => '0'),
			'<div class="ratio" style="--bs-aspect-ratio:0"><img src="' . $src . '" alt="A" class="ratio" style="object-position:0"></div>',
		),
		'an exponent is pixels' => array(
			array('width' => '1e2', 'height' => '48'),
			'<img src="' . $src . '" alt="A" height="48" style="width:1e2px">',
		),
	);
}

// The markup with each tag's attributes in name order: the page and the
// editor write them in different orders, which means nothing to a browser.
function designer_image_size_test_canon($html)
{
	return preg_replace_callback('/<([a-z][a-z0-9]*)((?:\s+[^\s=>]+(?:="[^"]*")?)*)\s*>/i', function ($m) {
		preg_match_all('/\s+([^\s=>]+)(?:="([^"]*)")?/', $m[2], $attrs, PREG_SET_ORDER);
		$list = array();
		foreach ($attrs as $a) {
			$list[] = ' ' . $a[1] . (isset($a[2]) ? '="' . $a[2] . '"' : '');
		}
		sort($list);
		return '<' . $m[1] . implode('', $list) . '>';
	}, (string) $html);
}

// One line, without the no-image fallback handler and the indentation.
function designer_image_size_test_flat($html)
{
	$html = preg_replace('/\s*onerror="[^"]*"/', '', (string) $html);

	return trim(preg_replace('/>\s+</', '><', $html));
}

function designer_image_size_test_page($props)
{
	$node = array('type' => 'content', 'props' => array_merge(array(
		'contentType' => 'image', 'src' => 'https://example.com/a.jpg', 'alt' => 'A',
	), $props));

	return designer_image_size_test_flat(_render_tree_node($node, 0));
}

function test_img_dimension_splits_attribute_and_css()
{
	pg_assert_same(array('attr' => '48', 'css' => ''), pg_img_dimension('48'), 'a whole number is the attribute');
	pg_assert_same(array('attr' => '48', 'css' => ''), pg_img_dimension(' 48 '), 'trimmed');
	pg_assert_same(array('attr' => '', 'css' => '120px'), pg_img_dimension('120px'), 'pixels with the unit');
	pg_assert_same(array('attr' => '', 'css' => '50%'), pg_img_dimension('50%'), 'percent');
	pg_assert_same(array('attr' => '', 'css' => '12rem'), pg_img_dimension('12rem'), 'rem');
	pg_assert_same(array('attr' => '', 'css' => 'auto'), pg_img_dimension('auto'), 'a keyword');
	pg_assert_same(array('attr' => '', 'css' => '48.5px'), pg_img_dimension('48.5'), 'a bare decimal is pixels');
	pg_assert_same(array('attr' => '', 'css' => '.5px'), pg_img_dimension('.5'), 'no leading zero');
	pg_assert_same(array('attr' => '', 'css' => ''), pg_img_dimension(''), 'empty');
	pg_assert_same(array('attr' => '0', 'css' => ''), pg_img_dimension('0'), 'the string 0 is a size, as in the editor');
	pg_assert_same(array('attr' => '', 'css' => ''), pg_img_dimension(0), 'the number 0 is none, as in the editor');
	pg_assert_same(array('attr' => '', 'css' => ''), pg_img_dimension(null), 'no value');
	pg_assert_same(array('attr' => '', 'css' => ''), pg_img_dimension(false), 'false');
	pg_assert_same(array('attr' => '48', 'css' => ''), pg_img_dimension(48), 'a whole number stored as a number');
	pg_assert_same(array('attr' => '', 'css' => '1e2px'), pg_img_dimension('1e2'), 'an exponent is pixels');
}

// A prop as the editor's `if (p.x)` reads it: JavaScript's false values are
// no value, the string '0' is one.
function test_designer_prop_text_reads_as_the_editor()
{
	pg_assert_same('0', pg_designer_prop_text('0'), 'the string 0');
	pg_assert_same('', pg_designer_prop_text(0), 'the number 0');
	pg_assert_same('', pg_designer_prop_text(0.0), 'the number 0.0');
	pg_assert_same('', pg_designer_prop_text(null), 'null');
	pg_assert_same('', pg_designer_prop_text(false), 'false');
	pg_assert_same('', pg_designer_prop_text(''), 'empty');
	pg_assert_same('', pg_designer_prop_text(array('48')), 'a list');
	pg_assert_same('48', pg_designer_prop_text(' 48 '), 'trimmed');
	pg_assert_same('48', pg_designer_prop_text(48), 'a number');
	pg_assert_same('1', pg_designer_prop_text(true), 'true');
}

function test_designer_image_size_page_markup()
{
	foreach (designer_image_size_test_cases() as $label => $case) {
		$html = designer_image_size_test_page($case[0]);
		pg_assert_same($case[1], $html, $label);
		// The e-mail pass takes only the responsive markup out.
		pg_assert_same($html, pg_img_strip_responsive($html), $label . ' in an e-mail');
	}
}

// Nothing the page writes for an image has a length without a unit
// in a style: the browser drops such a declaration.
function test_designer_image_size_no_unitless_style()
{
	foreach (array('48', '0.5', '48.5', '120px', '50%', '12rem') as $value) {
		foreach (array('', '1x1') as $ratio) {
			$html = designer_image_size_test_page(array('width' => $value, 'height' => $value, 'minWidth' => $value, 'maxHeight' => $value, 'top' => $value, 'aspectRatio' => $ratio));
			pg_assert_false((bool) preg_match('/style="[^"]*(?:^|[";])\s*(?:width|height|min-width|max-height|top)\s*:\s*[0-9.]+\s*(?:;|")/', $html), $value . ' ' . ($ratio !== '' ? 'in a ratio box' : 'alone') . ': ' . $html);
			pg_assert_same(1, preg_match_all('/<img\b/', $html), 'one image');
		}
	}
}

// The source of one declaration of the designer, braces matched (the code cut
// out holds no brace inside a string); '' when it is not found.
function designer_image_size_test_cut($source, $start)
{
	$from = strpos($source, $start);

	if ($from === false) {
		return '';
	}

	$open = strpos($source, '{', $from);
	$depth = 0;

	for ($i = $open; $i < strlen($source); $i++) {
		if ($source[$i] === '{') {
			$depth++;
		} elseif ($source[$i] === '}') {
			$depth--;

			if ($depth === 0) {
				return substr($source, $from, $i - $from + 1);
			}
		}
	}

	return '';
}

// Runs $body under Node after the designer's sizing helpers, the declarations
// $more names and its Image element, cut out of assets/js/style_designer.js;
// $body fills `out`, which comes back decoded. null without Node.
function designer_image_size_test_js($body, $more = array())
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

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	$code_js = '';

	foreach (array_merge(array('function _sdCssLen(', 'function _sdImgDim(', 'function _sdImgSizeCss(', 'function _sdImgInRatio(', 'function _sdImgRatioClass(', 'function _sdSizingValue(', 'function getNodeExtraStyle(', 'function getNodeExtraClasses(', 'var _SD_CONTENT_GLOBAL_ATTRS', 'function _sdContentSizingNode(', 'function _sdContentProps(', 'function _sdUniqueClasses(', 'function _sdDecodeAttr(', 'function _sdUserAttrValue(', 'function _sdFontFamilyCss(', 'function _sdNodeStyle(', 'function _sdNodeTitleAttrs(', 'function _sdNodeExtraAttrs(', 'function addGlobalAttrs('), $more) as $start) {
		$found = designer_image_size_test_cut($source, $start);
		pg_assert_true($found !== '', $start . ' is in the designer');
		$code_js .= $found . "\n";
	}

	$image = designer_image_size_test_cut($source, "        image: {\n            label: _sdT('Image')");
	pg_assert_true($image !== '', 'the Image element is in the designer');

	$script = 'function _sdT(s) { return s; }'
		. 'function esc(s) { return s ? String(s).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;") : ""; }'
		. $code_js
		. 'var CONTENT = {' . $image . '};'
		. 'var out = {};'
		. $body
		. 'console.log(JSON.stringify(out));';

	$file = @tempnam(sys_get_temp_dir(), 'pgsdimgsize');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	if ($code !== 0) {
		return array('error' => implode("\n", $out));
	}

	return json_decode(implode("\n", $out), true);
}

// The editor's markup for the same nodes ($props by label), or null without Node.
function designer_image_size_test_editor($cases)
{
	return designer_image_size_test_js(
		'var cases = ' . json_encode($cases) . ';'
		. 'Object.keys(cases).forEach(function (k) {'
		. '  var p = Object.assign({ contentType: "image", src: "https://example.com/a.jpg", alt: "A" }, cases[k]);'
		. '  var node = { type: "content", props: p };'
		. '  var P = _sdContentProps(node, p);'
		. '  out[k] = addGlobalAttrs(CONTENT.image.toHTML(P, ""), node, P);'
		. '  out[k + " / dim"] = [_sdImgDim(p.width), _sdImgDim(p.height)];'
		. '});'
	);
}

// The editor (code view, preview, HTML export) writes what the page writes,
// and splits a value the same way as pg_img_dimension().
function test_designer_image_size_editor_matches_page()
{
	$cases = array();

	foreach (designer_image_size_test_cases() as $label => $case) {
		$cases[$label] = $case[0];
	}

	$cases['decimal without a leading zero'] = array('width' => '.5', 'height' => ' 48 ');
	$result = designer_image_size_test_editor($cases);

	if ($result === null) {
		return;
	}

	pg_assert_false(isset($result['error']), isset($result['error']) ? $result['error'] : 'ran');

	foreach ($cases as $label => $props) {
		pg_assert_same(designer_image_size_test_canon(designer_image_size_test_page($props)), designer_image_size_test_canon(designer_image_size_test_flat($result[$label])), $label);

		foreach (array(0 => 'width', 1 => 'height') as $i => $prop) {
			$value = isset($props[$prop]) ? $props[$prop] : '';
			pg_assert_same(pg_img_dimension($value), $result[$label . ' / dim'][$i], $label . ' ' . $prop . ' split');
		}
	}
}

// The Sizing panel's lengths (width, height, their minimum and maximum, the
// position offsets) of any element: a number without a unit is pixels.
function designer_image_size_test_sizing_cases()
{
	return array(
		'whole numbers' => array('width' => '48', 'height' => '120', 'minHeight' => '200', 'maxWidth' => '960'),
		'decimal and negative offsets' => array('top' => '-8', 'left' => '1.5', 'right' => '0', 'bottom' => '.5'),
		'units kept' => array('width' => '50%', 'minWidth' => '12rem', 'maxHeight' => '80vh', 'height' => 'auto'),
		'with z-index and overflow' => array('zIndex' => '10', 'width' => ' 320 ', 'overflow' => 'hidden', 'overflowY' => 'auto'),
		'an expression' => array('width' => 'calc(100% - 2rem)', 'maxWidth' => 'min(100%, 40rem)'),
		'zero as a string and as a number' => array('width' => '0', 'height' => 0, 'top' => '0', 'left' => 0, 'zIndex' => 0),
		'exponents' => array('width' => '1e2', 'minHeight' => '2E1', 'maxWidth' => '1em'),
	);
}

function test_designer_sizing_length_bare_number_is_pixels()
{
	pg_assert_same('48px', pg_designer_css_length('48'), 'a whole number');
	pg_assert_same('48px', pg_designer_css_length(' 48 '), 'trimmed');
	pg_assert_same('48.5px', pg_designer_css_length('48.5'), 'a decimal');
	pg_assert_same('.5px', pg_designer_css_length('.5'), 'no leading zero');
	pg_assert_same('-8px', pg_designer_css_length('-8'), 'negative');
	pg_assert_same('0px', pg_designer_css_length('0'), 'zero');
	pg_assert_same('1e2px', pg_designer_css_length('1e2'), 'an exponent');
	pg_assert_same('-1.5E-1px', pg_designer_css_length('-1.5E-1'), 'a negative exponent');
	pg_assert_same('1em', pg_designer_css_length('1em'), 'em is a unit, not an exponent');
	pg_assert_same('50%', pg_designer_css_length('50%'), 'percent');
	pg_assert_same('12rem', pg_designer_css_length('12rem'), 'rem');
	pg_assert_same('auto', pg_designer_css_length('auto'), 'a keyword');
	pg_assert_same('calc(100% - 2rem)', pg_designer_css_length('calc(100% - 2rem)'), 'an expression');
	pg_assert_same('', pg_designer_css_length(''), 'empty');
}

// Every element the page draws with the Sizing panel's style writes its
// lengths with a unit.
function test_designer_sizing_length_every_node_type()
{
	$props = array('width' => '48', 'height' => '120', 'minHeight' => '200', 'top' => '-8');
	$want = 'style="width:48px;height:120px;min-height:200px;top:-8px"';

	foreach (array('container', 'row', 'col', 'area') as $type) {
		pg_assert_contains($want, _render_tree_node(array('type' => $type, 'props' => $props, 'children' => array()), 0), $type);
	}

	foreach (array('section', 'div', 'img') as $tag) {
		pg_assert_contains($want, _render_tree_node(array('type' => 'semantic', 'props' => array_merge($props, array('tag' => $tag)), 'children' => array()), 0), 'semantic ' . $tag);
	}

	foreach (designer_image_size_test_sizing_cases() as $label => $case) {
		$html = _render_tree_node(array('type' => 'container', 'props' => $case, 'children' => array()), 0);
		pg_assert_false((bool) preg_match('/style="[^"]*(?:^|[";])\s*(?:width|height|min-width|max-width|min-height|max-height|top|right|bottom|left)\s*:\s*[-+0-9.]+\s*(?:;|")/', $html), $label . ': ' . $html);
	}
}

// The editor's sizing style (getNodeExtraStyle(), on the canvas and in the
// editor's HTML of every element) is the page's (_build_node_sizing_attrs()).
function test_designer_sizing_length_editor_matches_page()
{
	$cases = designer_image_size_test_sizing_cases();
	$result = designer_image_size_test_js(
		'var cases = ' . json_encode($cases) . ';'
		. 'Object.keys(cases).forEach(function (k) {'
		. '  out[k] = getNodeExtraStyle({ type: "container", props: cases[k] });'
		. '});'
	);

	if ($result === null) {
		return;
	}

	pg_assert_false(isset($result['error']), isset($result['error']) ? $result['error'] : 'ran');

	foreach ($cases as $label => $props) {
		$page = _build_node_sizing_attrs($props);
		pg_assert_same($page['style'], $result[$label], $label);
	}
}

// The Attributes panel of an image (getNodeBuiltinAttrs(), the merged style
// row _currentInlineStyleValue() and its write-back _sdApplyInlineStyle()):
// width / height are attribute rows while they are attributes, a value with
// a unit is in the style row, in a ratio box neither (the box takes it).
function test_designer_image_size_attributes_panel()
{
	$cases = array(
		'whole numbers' => array('width' => '48', 'height' => '48'),
		'a unit and a whole number' => array('width' => '50%', 'height' => '48'),
		'units in a ratio box' => array('width' => '50%', 'height' => '12rem', 'aspectRatio' => '16x9'),
		'a whole number in a ratio box' => array('width' => '200', 'aspectRatio' => '16x9'),
		'with object-position and the user style' => array('width' => '12rem', 'objectPosition' => 'top', '_attrs' => array(array('name' => 'style', 'value' => 'opacity: .9'))),
		'a whole number beside the user style' => array('width' => '72', '_attrs' => array(array('name' => 'style', 'value' => 'width: 72px'))),
	);
	$writes = array(
		'a unit edited' => array(array('width' => '50%'), 'width: 30%; opacity: .5'),
		'the unit taken out' => array(array('width' => '50%', 'height' => '2rem'), 'height: 2rem'),
		'a whole number typed' => array(array('width' => '50%'), 'width: 48'),
		'a whole number keeps the user style' => array(array('width' => '72', '_attrs' => array(array('name' => 'style', 'value' => 'width: 72px'))), 'width: 80px'),
	);
	$result = designer_image_size_test_js(
		'var cases = ' . json_encode($cases) . ', writes = ' . json_encode($writes) . ';'
		. 'var mk = function (p) { return { type: "content", props: Object.assign({ contentType: "image", src: "a.jpg", alt: "A" }, JSON.parse(JSON.stringify(p))) }; };'
		. 'Object.keys(cases).forEach(function (k) {'
		. '  var n = mk(cases[k]);'
		. '  out[k] = { rows: getNodeBuiltinAttrs(n).filter(function (a) { return a.name === "width" || a.name === "height"; }).map(function (a) { return a.name + "=" + a.value; }), style: _currentInlineStyleValue(n) };'
		. '});'
		. 'Object.keys(writes).forEach(function (k) {'
		. '  var n = mk(writes[k][0]);'
		. '  _sdApplyInlineStyle(n, writes[k][1]);'
		. '  var st = (n.props._attrs || []).filter(function (a) { return a.name === "style"; });'
		. '  out["write " + k] = { width: n.props.width || "", height: n.props.height || "", attrStyle: st.length ? st[0].value : "" };'
		. '});',
		array('function getNodeBuiltinAttrs(', 'function _sdInlineStyleProps(', 'function _propDerivedInlineDecls(', 'function _currentInlineStyleValue(', 'function _sdApplyInlineStyle(', 'function _parseInlineStyle(', 'function _serializeInlineDecls(')
	);

	if ($result === null) {
		return;
	}

	pg_assert_false(isset($result['error']), isset($result['error']) ? $result['error'] : 'ran');

	$want = array(
		'whole numbers' => array(array('width=48', 'height=48'), ''),
		'a unit and a whole number' => array(array('height=48'), 'width: 50%'),
		'units in a ratio box' => array(array(), ''),
		'a whole number in a ratio box' => array(array('width=200', 'height='), ''),
		'with object-position and the user style' => array(array('height='), 'object-position: top; width: 12rem; opacity: .9'),
		'a whole number beside the user style' => array(array('width=72', 'height='), 'width: 72px'),
	);

	foreach ($want as $label => $w) {
		pg_assert_same($w[0], $result[$label]['rows'], $label . ': attribute rows');
		pg_assert_same($w[1], $result[$label]['style'], $label . ': style row');
	}

	pg_assert_same(array('width' => '30%', 'height' => '', 'attrStyle' => 'opacity: .5'), $result['write a unit edited'], 'a unit edited in the style row stays the prop');
	pg_assert_same(array('width' => '', 'height' => '2rem', 'attrStyle' => ''), $result['write the unit taken out'], 'a width taken out of the style row clears the prop');
	pg_assert_same(array('width' => '48', 'height' => '', 'attrStyle' => ''), $result['write a whole number typed'], 'a whole number goes back to the attribute');
	pg_assert_same(array('width' => '72', 'height' => '', 'attrStyle' => 'width: 80px'), $result['write a whole number keeps the user style'], 'beside an attribute the user style stays the user\'s');
}

// A small stand-in for the canvas document: elements keep their attributes,
// classes and style declarations in order, and every style property set is
// logged, so the test sees what the canvas would draw. setAttribute('style')
// replaces the declarations, as in a browser.
function designer_image_size_test_dom_stub()
{
	return 'function _sdResolveAttrTokens(v) { return v; }'
		. 'var styleLog = [];'
		. 'var kebab = function (k) { return k.replace(/[A-Z]/g, function (c) { return "-" + c.toLowerCase(); }); };'
		. 'function StubEl(tag) {'
		. '  var self = this; this.tagName = tag.toUpperCase(); this.attrs = []; this.children = []; this.decls = []; this.className = "";'
		. '  var put = function (name, value) { styleLog.push([name, String(value)]); self.decls = self.decls.filter(function (d) { return d[0] !== name; }); if (String(value) !== "") self.decls.push([name, String(value)]); };'
		. '  this.style = new Proxy({}, { set: function (t, k, v) { put(kebab(String(k)), v); return true; },'
		. '    get: function (t, k) { if (k === "setProperty") return function (n, v) { put(n, v); }; var d = self.decls.filter(function (x) { return x[0] === kebab(String(k)); }); return d.length ? d[0][1] : ""; } });'
		. '  this.classList = { add: function (c) { var l = self.className.split(/\s+/).filter(Boolean); if (l.indexOf(c) === -1) l.push(c); self.className = l.join(" "); },'
		. '    contains: function (c) { return self.className.split(/\s+/).indexOf(c) !== -1; } };'
		. '}'
		. 'StubEl.prototype.setAttribute = function (n, v) {'
		. '  if (n === "style") { var self = this; this.decls = []; String(v).split(";").forEach(function (d) { var i = d.indexOf(":"); if (i > 0) { var k = d.slice(0, i).trim(), val = d.slice(i + 1).trim(); styleLog.push([k, val]); self.decls.push([k, val]); } }); return; }'
		. '  if (n === "class") { this.className = String(v); return; }'
		. '  this.attrs = this.attrs.filter(function (a) { return a[0] !== n; }); this.attrs.push([n, String(v)]);'
		. '};'
		. 'StubEl.prototype.appendChild = function (c) { this.children.push(c); return c; };'
		. 'Object.defineProperty(StubEl.prototype, "firstElementChild", { get: function () { return this.children[0] || null; } });'
		. 'var stubDoc = { createElement: function (t) { return new StubEl(t); } };'
		. 'var serialize = function (el) {'
		. '  var a = [];'
		. '  ["src", "alt", "id", "title"].forEach(function (k) { if (el[k] !== undefined && el[k] !== "") a.push(" " + k + "=\"" + el[k] + "\""); });'
		. '  el.attrs.forEach(function (x) { a.push(" " + x[0] + "=\"" + x[1] + "\""); });'
		. '  var cls = el.className.split(/\s+/).filter(Boolean).join(" "); if (cls) a.push(" class=\"" + cls + "\"");'
		. '  if (el.decls.length) a.push(" style=\"" + el.decls.map(function (d) { return d[0] + ":" + d[1]; }).join(";") + "\"");'
		. '  var tag = el.tagName.toLowerCase(), html = "<" + tag + a.join("") + ">";'
		. '  if (tag === "img") return html;'
		. '  return html + el.children.map(serialize).join("") + "</" + tag + ">";'
		. '};';
}

// The canvas (CONTENT.image.render() and _sdApplyNodeAttrs(), which
// buildNodeEl() calls) draws what the page writes: the id, attributes and
// style on the <img>, the sizing style on a ratio box, no length without a
// unit; and a percentage width makes the image's wrapper a block.
function test_designer_image_size_canvas_matches_page()
{
	$cases = array();

	foreach (designer_image_size_test_cases() as $label => $case) {
		$cases[$label] = $case[0];
	}

	$cases['decimal without a leading zero'] = array('width' => '.5', 'height' => ' 48 ');
	$wrappers = array(
		'a percentage width' => array(array('width' => '50%'), true),
		'a percentage maximum width' => array(array('width' => '200', 'maxWidth' => '100%'), true),
		'a percentage minimum width' => array(array('minWidth' => '25%'), true),
		'whole numbers' => array(array('width' => '200', 'height' => '100'), false),
		'a unit that is not a percentage' => array(array('width' => '12rem'), false),
		'no size' => array(array(), false),
	);
	$result = designer_image_size_test_js(
		designer_image_size_test_dom_stub()
		. 'var cases = ' . json_encode($cases) . ', wrappers = ' . json_encode($wrappers) . ';'
		. 'Object.keys(cases).forEach(function (k) {'
		. '  var p = Object.assign({ contentType: "image", src: "https://example.com/a.jpg", alt: "A" }, JSON.parse(JSON.stringify(cases[k])));'
		. '  var node = { type: "content", props: p };'
		. '  styleLog = [];'
		. '  var el = CONTENT.image.render(stubDoc, p);'
		. '  _sdApplyNodeAttrs(el, node);'
		. '  out[k] = { html: serialize(el), log: styleLog };'
		. '});'
		. 'Object.keys(wrappers).forEach(function (k) {'
		. '  out["wrapper " + k] = _sdImgWrapperIsBlock({ type: "content", props: Object.assign({ contentType: "image", src: "a.jpg" }, wrappers[k][0]) });'
		. '});'
		. 'out["wrapper of a heading"] = _sdImgWrapperIsBlock({ type: "content", props: { contentType: "heading", width: "50%" } });',
		array('function _sdApplyNodeAttrs(', 'function _sdImgWrapperIsBlock(', 'function getAttrStyleProp(', 'function splitStylePairs(')
	);

	if ($result === null) {
		return;
	}

	pg_assert_false(isset($result['error']), isset($result['error']) ? $result['error'] : 'ran');

	foreach ($cases as $label => $props) {
		pg_assert_same(designer_image_size_test_canon(designer_image_size_test_page($props)), designer_image_size_test_canon($result[$label]['html']), $label . ' on the canvas');

		foreach ($result[$label]['log'] as $set) {
			$is_length = in_array($set[0], array('width', 'height', 'min-width', 'max-width', 'min-height', 'max-height', 'top', 'right', 'bottom', 'left'), true);
			pg_assert_false($is_length && (bool) preg_match('/^[-+]?[0-9.]+$/', $set[1]), $label . ': the canvas set ' . $set[0] . ':' . $set[1]);
		}
	}

	foreach ($wrappers as $label => $w) {
		pg_assert_same($w[1], $result['wrapper ' . $label], 'wrapper: ' . $label);
	}

	pg_assert_false($result['wrapper of a heading'], 'only an image\'s wrapper');
}

// A ';' or ':' inside url(), var(), calc() or a quoted string belongs to the
// value: the style row keeps such a declaration whole and writes it back
// unchanged.
function test_designer_inline_style_parser_brackets_and_quotes()
{
	$cases = array(
		'data url' => 'background: url(data:image/png;base64,AAA=); color: red',
		'quoted url' => 'background-image: url("a;b:c.png"); opacity: .5',
		'double quotes' => 'content: "a;b:c"; color: red',
		'single quotes' => "content: 'x;y'; color: red",
		'escaped quote' => 'content: "a\\";b"; color: red',
		'nested brackets' => 'width: calc(100% - var(--gap, 1px)); height: 2px',
		'custom property' => '--grid: repeat(2, minmax(0;1fr)); gap: 1rem',
		'plain' => ' color : red ;; ;opacity:.5; ',
		'no colon' => 'color; width: 1px',
		'unclosed bracket' => 'background: url(a;b; color: red',
		'unclosed quote' => 'content: "a;b',
	);
	$result = designer_image_size_test_js(
		'var cases = ' . json_encode($cases) . ';'
		. 'Object.keys(cases).forEach(function (k) {'
		. '  out[k] = _parseInlineStyle(cases[k]).map(function (d) { return [d.name, d.value]; });'
		. '});'
		. 'var n = { type: "content", props: { contentType: "image", src: "a.jpg", alt: "A" } };'
		. '_sdApplyInlineStyle(n, cases["data url"]);'
		. 'out.written = (n.props._attrs || []).map(function (a) { return a.name + "=" + a.value; });',
		array('function getNodeBuiltinAttrs(', 'function _sdInlineStyleProps(', 'function _propDerivedInlineDecls(', 'function _currentInlineStyleValue(', 'function _sdApplyInlineStyle(', 'function _parseInlineStyle(', 'function _serializeInlineDecls(')
	);

	if ($result === null) {
		return;
	}

	pg_assert_false(isset($result['error']), isset($result['error']) ? $result['error'] : 'ran');

	$want = array(
		'data url' => array(array('background', 'url(data:image/png;base64,AAA=)'), array('color', 'red')),
		'quoted url' => array(array('background-image', 'url("a;b:c.png")'), array('opacity', '.5')),
		'double quotes' => array(array('content', '"a;b:c"'), array('color', 'red')),
		'single quotes' => array(array('content', "'x;y'"), array('color', 'red')),
		'escaped quote' => array(array('content', '"a\\";b"'), array('color', 'red')),
		'nested brackets' => array(array('width', 'calc(100% - var(--gap, 1px))'), array('height', '2px')),
		'custom property' => array(array('--grid', 'repeat(2, minmax(0;1fr))'), array('gap', '1rem')),
		'plain' => array(array('color', 'red'), array('opacity', '.5')),
		'no colon' => array(array('width', '1px')),
		'unclosed bracket' => array(array('background', 'url(a;b; color: red')),
		'unclosed quote' => array(array('content', '"a;b')),
	);

	foreach ($want as $label => $w) {
		pg_assert_same($w, $result[$label], $label);
	}

	pg_assert_same(array('style=background: url(data:image/png;base64,AAA=); color: red'), $result['written'], 'a data url is written back whole');
}
