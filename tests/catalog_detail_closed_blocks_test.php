<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the catalog detail widget's closed blocks on the designer canvas:
 * the breadcrumb content node drawn as the crumbs part its widget prints and
 * opened into it, the product carousel drawn as the site builds it with
 * sample photos, the Checkout Options rows drawn as the store's payment
 * settings give them, the price block and the Variant Attribute node drawn
 * as the site prints them for a product with variants. The drawing functions are cut out of
 * assets/js/style_designer.src.js and run under Node (skipped on a machine
 * without Node); their output is compared with the site's own markup.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function catalog_closed_blocks_test_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
}

// The body of the named function, up to the next function at the same indentation.
function catalog_closed_blocks_test_function($source, $name)
{
	$from = strpos($source, '    function ' . $name . '(');
	pg_assert_true($from !== false, 'the designer has ' . $name . '()');
	$to = strpos($source, "\n    function ", $from + 10);
	pg_assert_true($to !== false, $name . '() has an end');

	return substr($source, $from, $to - $from);
}

// Runs a JavaScript expression over the cut functions; null without Node.
function catalog_closed_blocks_test_run($expr)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$source = catalog_closed_blocks_test_source();
	$rows = strpos($source, "        'catalog.crumbs': [");
	pg_assert_true($rows !== false, 'the crumbs list has sample rows');
	$rows_end = strpos($source, '],', $rows);
	$script = "function esc(s) { return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\"/g, '&quot;'); }\n"
		. "function _sdT(s) { return s; }\n"
		. "function _sdSamplePictureUri() { return 'data:image/svg+xml;utf8,x'; }\n"
		. "function _sdMoney(n) { return '\$' + Number(n).toFixed(2); }\n"
		. 'var SD_LIST_SAMPLE_ROWS = {' . substr($source, $rows, $rows_end + 1 - $rows) . "};\n"
		. "var doc = { createElement: function (tag) { return { tagName: tag, className: '', attrs: {}, innerHTML: '', setAttribute: function (n, v) { this.attrs[n] = v; } }; } };\n"
		. catalog_closed_blocks_test_function($source, '_sdBreadcrumbSample') . "\n"
		. catalog_closed_blocks_test_function($source, '_sdCarouselSampleHtml') . "\n"
		. catalog_closed_blocks_test_function($source, '_sdPriceBlockHtml') . "\n"
		. catalog_closed_blocks_test_function($source, '_sdVariantAttrCfg') . "\n"
		. catalog_closed_blocks_test_function($source, '_sdVariantAttrHtml') . "\n"
		. 'console.log(JSON.stringify(' . $expr . '));';
	$file = tempnam(sys_get_temp_dir(), 'pgcatblocks');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, implode("\n", $out));

	return json_decode(implode("\n", $out), true);
}

// The carousel's classes in document order, only those naming a carousel
// part and the active mark, one class attribute a line.
function catalog_closed_blocks_test_carousel_classes($html)
{
	preg_match_all('/class="([^"]*)"/', $html, $m);
	$out = array();
	foreach ($m[1] as $attr) {
		$keep = array();
		foreach (preg_split('/\s+/', trim($attr)) as $cls) {
			if (strpos($cls, 'carousel') !== false || $cls === 'active') {
				$keep[] = $cls;
			}
		}
		if ($keep) {
			$out[] = implode(' ', $keep);
		}
	}

	return $out;
}

// The site's carousel for three pictures with these settings.
function catalog_closed_blocks_test_site_carousel($props)
{
	$props['contentType'] = 'carousel';
	$props['slides'] = array('/files/a.jpg', '/files/b.jpg', '/files/c.jpg');

	return _render_content_html($props, '');
}

// The canvas draws the carousel's parts in the site's order, for each place
// of the thumbnail strip and with the arrows, dots and lightbox on or off.
function test_catalog_closed_blocks_carousel_matches_the_site()
{
	$cases = array(
		array('thumbsPosition' => 'bottom'),
		array('thumbsPosition' => 'top', 'showIndicators' => true),
		array('thumbsPosition' => 'left', 'lightbox' => false),
		array('thumbsPosition' => 'right', 'showControls' => false),
		array('thumbsPosition' => 'none', 'showIndicators' => true, 'cssClass' => 'shadow'),
	);
	$drawn = catalog_closed_blocks_test_run('[' . implode(', ', array_map(function ($p) {
		return '_sdCarouselSampleHtml(' . json_encode($p) . ')';
	}, $cases)) . ']');

	if ($drawn === null) {
		return;
	}

	foreach ($cases as $i => $props) {
		pg_assert_same(
			catalog_closed_blocks_test_carousel_classes(catalog_closed_blocks_test_site_carousel($props)),
			catalog_closed_blocks_test_carousel_classes($drawn[$i]),
			'the canvas carousel is the site\'s: ' . json_encode($props)
		);
	}
}

// The thumbnails take the size and the active border the settings give,
// kept within the site's bounds; the sample is never live.
function test_catalog_closed_blocks_carousel_settings()
{
	$drawn = catalog_closed_blocks_test_run('[_sdCarouselSampleHtml({ thumbSize: 90, activeBorder: "#ff0000" }), '
		. '_sdCarouselSampleHtml({ thumbSize: 5, activeBorder: "red;x" })]');

	if ($drawn === null) {
		return;
	}

	pg_assert_contains('width:90px;height:90px', $drawn[0]);
	pg_assert_contains('border:2px solid #ff0000', $drawn[0]);
	pg_assert_contains('width:20px;height:20px', $drawn[1], 'a size under the site\'s least is raised to it');
	pg_assert_contains('border:2px solid #0d6efd', $drawn[1], 'a colour the site refuses is its default');
	pg_assert_true(strpos($drawn[0], 'data-bs-ride') === false && strpos($drawn[0], 'data-fancybox') === false,
		'no slide runs and no lightbox opens on the canvas');
}

// A breadcrumb node in a catalog widget is drawn as the crumbs part the
// widget prints for it, the nav's class by the widget's kind.
function test_catalog_closed_blocks_breadcrumb_sample()
{
	$drawn = catalog_closed_blocks_test_run('[_sdBreadcrumbSample(doc, "catalog_item_view"), _sdBreadcrumbSample(doc, "catalog_listing")]');

	if ($drawn === null) {
		return;
	}

	$item = pg_sw_part_catalog_breadcrumb_item();
	$listing = pg_sw_part_catalog_breadcrumb_listing();
	pg_assert_same('nav', $item[0]['props']['tag']);
	pg_assert_same($item[0]['props']['cssClass'], $drawn[0]['className']);
	pg_assert_same($listing[0]['props']['cssClass'], $drawn[1]['className']);
	pg_assert_same('breadcrumb', $drawn[0]['attrs']['aria-label']);

	$ol = $item[0]['children'][0];
	$li = $ol['children'][0]['children'][0];
	pg_assert_contains('<ol class="' . $ol['props']['cssClass'] . '">', $drawn[0]['innerHTML']);
	pg_assert_contains('<li class="' . $li['props']['cssClass'] . '"><a href="#" tabindex="-1">Example group</a></li>', $drawn[0]['innerHTML']);
	pg_assert_contains('<li class="' . $li['props']['cssClass'] . '"><span class="active">Example product</span></li>', $drawn[0]['innerHTML']);
}

// The breadcrumb opens into the part the widget prints, kept in place;
// anywhere else it says that it prints nothing.
function test_catalog_closed_blocks_breadcrumb_editor()
{
	$js = catalog_closed_blocks_test_source();
	$open = catalog_closed_blocks_test_function($js, '_sdBreadcrumbToPart');
	pg_assert_contains('_sdDefaultParts(node, kind, null)', $open);
	pg_assert_contains('_sharedDirty[hit.sid] = true', $open);
	pg_assert_contains("document.querySelectorAll('#sd-properties [data-sd-crumbs-open]')", $js);
	pg_assert_contains("if (kind) return _sdBreadcrumbSample(doc, kind);", $js);
	pg_assert_contains("Prints nothing here: only a catalog or product page widget draws the breadcrumb.", $js);
}

// The canvas gets the Checkout Options rows the site prints and draws them
// in the payment_methods body of a product page.
function test_catalog_closed_blocks_payment_rows()
{
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/designer_screen.php');
	pg_assert_contains("'payment_method_rows' => function_exists('pg_civ_payment_method_rows')", $screen);
	pg_assert_contains('pg_civ_payment_method_rows(_pg_civ_payment_settings(false))', $screen);

	$js = catalog_closed_blocks_test_source();
	$table = catalog_closed_blocks_test_function($js, 'buildTableNodeEl');
	pg_assert_contains("node.props._bindings.section === 'payment_methods'", $table);
	pg_assert_contains("_sdCatalogKindDrawn() === 'catalog_item_view') _sdPaymentMethodsDraw(node, el);", $table);
	$draw = catalog_closed_blocks_test_function($js, '_sdPaymentMethodsDraw');
	pg_assert_contains("copy.classList.add('sd-ghost', 'sd-list-sample');", $draw);
	pg_assert_contains("copy.setAttribute('inert', '');", $draw);
}

// The canvas draws a Variant Attribute node exactly as the site expands its
// marker, for each style, with the default classes and with the designer's.
function test_catalog_closed_blocks_variant_attr_matches_the_site()
{
	$vattrs = array(
		1 => array('label' => 'Colour', 'options' => array(11 => 'Red', 12 => 'Blue', 13 => 'Green')),
		2 => array('label' => 'Size', 'options' => array(21 => 'S', 22 => 'M', 23 => 'L')),
	);
	$picked = array(1 => 12, 2 => 21);
	$js_attrs = '[{ id: 1, label: "Colour", options: [[11, "Red"], [12, "Blue"], [13, "Green"]] }, '
		. '{ id: 2, label: "Size", options: [[21, "S"], [22, "M"], [23, "L"]] }]';
	$props = array(
		array('renderStyle' => 'select'),
		array('renderStyle' => 'radio', 'itemClass' => 'form-check-inline'),
		array('renderStyle' => 'button_group', 'controlClass' => 'mb-2', 'labelClass' => 'small'),
		array('renderStyle' => 'button_list', 'itemClass' => 'text-start', 'cssClass' => ''),
		array('renderStyle' => 'checkbox'),
	);
	$drawn = catalog_closed_blocks_test_run('[' . implode(', ', array_map(function ($p) use ($js_attrs) {
		return '_sdVariantAttrHtml(_sdVariantAttrCfg(' . json_encode($p) . '), ' . $js_attrs . ', { 1: 12, 2: 21 })';
	}, $props)) . ']');

	if ($drawn === null) {
		return;
	}

	foreach ($props as $i => $p) {
		// The marker carries what toHTML() puts in it (_sdVariantAttrCfg()).
		$cfg = array(
			'renderStyle'  => $p['renderStyle'],
			'labelClass'   => !empty($p['labelClass']) ? $p['labelClass'] : 'form-label fw-semibold',
			'controlClass' => !empty($p['controlClass']) ? $p['controlClass'] : '',
			'itemClass'    => !empty($p['itemClass']) ? $p['itemClass'] : '',
			'cssClass'     => !empty($p['cssClass']) ? $p['cssClass'] : 'mb-3',
		);
		$site = _civ_expand_variant_attr_markers('<!--pg-variant-attr:' . base64_encode(json_encode($cfg)) . '-->', $vattrs, $picked);
		pg_assert_same($site, $drawn[$i], 'the canvas variant attribute is the site\'s: ' . json_encode($p));
	}
}

// The marker a Variant Attribute node saves is the one it always saved.
function test_catalog_closed_blocks_variant_attr_marker_kept()
{
	$js = catalog_closed_blocks_test_source();
	pg_assert_contains("var cfg = _sdVariantAttrCfg(p);\n                var b64 = btoa(unescape(encodeURIComponent(JSON.stringify(cfg))));", $js);
	$drawn = catalog_closed_blocks_test_run('JSON.stringify(_sdVariantAttrCfg({ renderStyle: "radio", labelClass: "" }))');

	if ($drawn === null) {
		return;
	}

	pg_assert_same('{"renderStyle":"radio","labelClass":"form-label fw-semibold","controlClass":"","itemClass":"","cssClass":"mb-3"}', $drawn);
}

// The price block is drawn as the site prints it, with and without an
// offer: the same elements and classes, the look inline.
function test_catalog_closed_blocks_price_block()
{
	$drawn = catalog_closed_blocks_test_run('[_sdPriceBlockHtml(500, 450), _sdPriceBlockHtml(450, 450)]');

	if ($drawn === null) {
		return;
	}

	$shape = function ($html) {
		$html = preg_replace('/ style="[^"]*"/', '', $html);
		return preg_replace('/>[^<]+</', '>X<', $html);
	};
	pg_assert_same($shape(_pg_format_price_with_discount(50000, 45000, '$')), $shape($drawn[0]));
	pg_assert_same($shape(_pg_format_price_with_discount(45000, 45000, '$')), $shape($drawn[1]));
	pg_assert_contains('text-decoration:line-through', $drawn[0]);
	pg_assert_contains('$500.00', $drawn[0]);
	pg_assert_contains('$450.00', $drawn[0]);

	$js = catalog_closed_blocks_test_source();
	pg_assert_contains("'__price_block_html': function () { return _sdPriceBlockHtml(500, 450); },", $js);
}
