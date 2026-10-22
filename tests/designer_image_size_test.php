<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for how an Image element's width and height props are written: a
 * whole number as the width / height attribute, a value with a unit as a
 * style with that unit, never a number without a unit in a style
 * (pg_img_dimension() and the image case of _render_content_html()). The
 * editor's side (_sdImgDim(), _sdImgSizeCss(), the image's toHTML() and
 * addGlobalAttrs()) is cut out of assets/js/style_designer.js and run under
 * Node, and has to write the same markup as the page; on a machine without
 * Node that part is skipped.
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
	);
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

// Nothing the page writes for an image has a width or height without a unit
// in a style: the browser drops such a declaration.
function test_designer_image_size_no_unitless_style()
{
	foreach (array('48', '0.5', '48.5', '120px', '50%', '12rem') as $value) {
		foreach (array('', '1x1') as $ratio) {
			$html = designer_image_size_test_page(array('width' => $value, 'height' => $value, 'aspectRatio' => $ratio));
			pg_assert_false((bool) preg_match('/style="[^"]*(?:^|[";])\s*(?:width|height)\s*:\s*[0-9.]+\s*(?:;|")/', $html), $value . ' ' . ($ratio !== '' ? 'in a ratio box' : 'alone') . ': ' . $html);
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

// The editor's markup for the same nodes ($props by label), or null without Node.
function designer_image_size_test_editor($cases)
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

	foreach (array('function _sdImgDim(', 'function _sdImgSizeCss(', 'function getNodeExtraStyle(', 'function getNodeExtraClasses(', 'function addGlobalAttrs(') as $start) {
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
		. 'var cases = ' . json_encode($cases) . ';'
		. 'var out = {};'
		. 'Object.keys(cases).forEach(function (k) {'
		. '  var p = Object.assign({ contentType: "image", src: "https://example.com/a.jpg", alt: "A" }, cases[k]);'
		. '  var node = { type: "content", props: p };'
		. '  out[k] = addGlobalAttrs(CONTENT.image.toHTML(p, ""), node);'
		. '  out[k + " / dim"] = [_sdImgDim(p.width), _sdImgDim(p.height)];'
		. '});'
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
		pg_assert_same(designer_image_size_test_page($props), designer_image_size_test_flat($result[$label]), $label);

		foreach (array(0 => 'width', 1 => 'height') as $i => $prop) {
			$value = isset($props[$prop]) ? $props[$prop] : '';
			pg_assert_same(pg_img_dimension($value), $result[$label . ' / dim'][$i], $label . ' ' . $prop . ' split');
		}
	}
}
