<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_svg_sanitize() (includes/fn/svg_sanitize.php): hostile SVG
 * samples lose their scripts, event handlers, foreign content, external
 * references and entity tricks; plain drawing markup comes through.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Wraps $body in an svg root.
function svg_sanitize_doc($body, $attributes = '')
{
	return '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 100 100"' . $attributes . '>' . $body . '</svg>';
}

// Every hostile string that must not survive a sanitize.
function svg_sanitize_assert_clean($out, $label)
{
	$lower = strtolower($out);

	foreach (array('<script', 'onload', 'onclick', 'onerror', 'onbegin', 'javascript:', 'foreignobject', 'evil.example', 'x.htc', '@import', 'expression(', '<iframe', '<!entity', '<!doctype', '<a ', '<set', '<animate', 'data:text', 'data:image/svg') as $bad) {
		pg_assert_false(strpos($lower, $bad) !== false, $label . ': no ' . $bad);
	}
}

// Plain drawing markup keeps its elements, attributes and texts.
function test_svg_sanitize_keeps_drawing()
{
	$in = svg_sanitize_doc(
		'<defs><linearGradient id="g1" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#ff0000"/><stop offset="1" stop-color="#0000ff" stop-opacity="0.5"/></linearGradient></defs>'
		. '<g transform="translate(10 10)" opacity="0.8"><rect x="0" y="0" width="20" height="10" rx="2" fill="url(#g1)" stroke="#000" stroke-width="2"/>'
		. '<circle cx="5" cy="5" r="4" style="fill: #00ff00; stroke-dasharray: 2 2"/><path d="M0 0L10 10Z" fill-rule="evenodd"/>'
		. '<text x="1" y="20" font-family="Inter, sans-serif" font-size="12">Merhaba &amp; <tspan font-weight="700">dünya</tspan></text>'
		. '<image x="0" y="0" width="1" height="1" href="data:image/png;base64,iVBORw0KGgo="/><use xlink:href="#g1"/></g>');
	$out = pg_svg_sanitize($in);

	pg_assert_contains('<linearGradient id="g1"', $out, 'a gradient stays');
	pg_assert_contains('fill="url(#g1)"', $out, 'a fill by id stays');
	pg_assert_contains('style="fill: #00ff00; stroke-dasharray: 2 2"', $out, 'a plain style stays');
	pg_assert_contains('Merhaba &amp; <tspan font-weight="700">dünya</tspan>', $out, 'texts stay, escaped');
	pg_assert_contains('font-family="Inter, sans-serif"', $out, 'a font by name');
	pg_assert_contains('href="data:image/png;base64,iVBORw0KGgo="', $out, 'an embedded picture stays');
	pg_assert_contains('xlink:href="#g1"', $out, 'a reference by id stays');
	pg_assert_contains('viewBox="0 0 100 100"', $out, 'the root keeps its view box');
	pg_assert_same(0, strpos($out, '<svg'), 'the result starts at the root');
}

// Scripts, handlers, animations that set attributes, foreign content and
// links go.
function test_svg_sanitize_drops_active_content()
{
	$in = svg_sanitize_doc(
		'<script>alert(1)</script><script xlink:href="https://evil.example/x.js"/>'
		. '<rect width="10" height="10" onload="alert(1)" onclick="alert(2)" ONERROR="alert(3)"/>'
		. '<foreignObject width="10" height="10"><iframe xmlns="http://www.w3.org/1999/xhtml" src="javascript:alert(1)"></iframe></foreignObject>'
		. '<a href="javascript:alert(1)"><circle r="5"/></a>'
		. '<set attributeName="href" to="javascript:alert(1)"/><animate onbegin="alert(1)" attributeName="x" to="5"/>'
		. '<g><handler xmlns="http://www.w3.org/2001/xml-events" event="load">alert(1)</handler><rect width="1" height="1"/></g>',
		' onload="alert(1)"');
	$out = pg_svg_sanitize($in);

	svg_sanitize_assert_clean($out, 'active content');
	pg_assert_contains('<rect width="10" height="10"/>', $out, 'the rect stays without its handlers');
	pg_assert_contains('<g><rect width="1" height="1"/></g>', $out, 'a group stays, its foreign child goes');
}

// References only to an id in the file or an embedded raster picture.
function test_svg_sanitize_references()
{
	$in = svg_sanitize_doc(
		'<image href="https://evil.example/track.png" width="1" height="1"/>'
		. '<image xlink:href="//evil.example/a.png" width="1" height="1"/>'
		. '<image href=" jav&#x09;ascript:alert(1)" width="1" height="1"/>'
		. '<image href="data:image/svg+xml;base64,PHN2Zz48c2NyaXB0PmFsZXJ0KDEpPC9zY3JpcHQ+PC9zdmc+" width="1" height="1"/>'
		. '<image href="data:text/html,&lt;script&gt;alert(1)&lt;/script&gt;" width="1" height="1"/>'
		. '<use href="https://evil.example/sprite.svg#a"/>'
		. '<rect width="1" height="1" fill="url(https://evil.example/p.svg#g)" filter="url(http://evil.example/f)"/>'
		. '<rect width="2" height="2" style="fill: url(//evil.example/x); stroke: red; background: url(javascript:alert(1))"/>'
		. '<image href="data:image/jpeg;base64,/9j/4AAQ" width="3" height="3"/>');
	$out = pg_svg_sanitize($in);

	svg_sanitize_assert_clean($out, 'references');
	pg_assert_contains('<rect width="1" height="1"/>', $out, 'an outside paint goes, the rect stays');
	pg_assert_contains('style="stroke: red"', $out, 'only the safe declarations stay');
	pg_assert_contains('href="data:image/jpeg;base64,/9j/4AAQ"', $out, 'an embedded JPEG stays');
	pg_assert_false(strpos($out, '<use') !== false, 'a use with an outside address goes');
}

// Style sheets: @import, outside url() and escapes take the whole sheet out.
function test_svg_sanitize_style_sheets()
{
	$ok = pg_svg_sanitize(svg_sanitize_doc('<style>/* note */ .a { fill: #f00; } g > .b, #c rect { fill: url(#g1); stroke-width: 2 }</style><rect class="a" width="1" height="1"/>', ' id="art"'));
	pg_assert_contains('<style>.pgsvg-art-a{fill: #f00}' . "\n" . '#art g &gt; .pgsvg-art-b, #art #pgsvg-art-c rect{fill: url(#pgsvg-art-g1); stroke-width: 2}</style>', $ok, 'a plain sheet stays, a selector with an element name under the root');
	pg_assert_contains('class="pgsvg-art-a"', $ok, 'classes stay, prefixed');
	pg_assert_true((bool) preg_match('#<svg [^>]*id="art"#', $ok), 'the root keeps its id');

	foreach (array(
		'@import url(https://evil.example/x.css);',
		'.a { background: url(https://evil.example/x.png); }',
		'.a { fill: u\\72 l(https://evil.example/x); }',
		'.a { width: expression(alert(1)); }',
		'.a { behavior: url(x.htc); }',
		'</style><script>alert(1)</script>',
	) as $sheet) {
		$out = pg_svg_sanitize(svg_sanitize_doc('<style><![CDATA[' . $sheet . ']]></style><rect width="1" height="1"/>'));
		pg_assert_false(strpos($out, '<style') !== false, 'sheet dropped: ' . $sheet);
		svg_sanitize_assert_clean($out, 'sheet ' . $sheet);
	}
}

// A sheet cannot reach past the drawing: rules are scoped to the root's id
// (a generated one when it has none) or name only the file's prefixed ids
// and classes, selectors other than names, classes,
// ids and descendant or child combinators go, and so do at-rules, rules
// on the root's own element name and declarations of unlisted properties.
function test_svg_sanitize_sheet_scoped()
{
	$out = pg_svg_sanitize(svg_sanitize_doc('<style>svg path{display:none} .pgie-top{background:#ff00ff !important} rect.box{fill:#2563eb}</style><rect class="box" width="1" height="1"/>'));

	pg_assert_true((bool) preg_match('#<svg [^>]*id="(pgsvg-[0-9a-f]{10})"#', $out, $m), 'a root without an id gets one');
	pg_assert_contains('<style>rect.' . (isset($m[1]) ? $m[1] : '?') . '-box{fill: #2563eb}</style>', $out, 'only the drawing\'s own rule stays, on its prefixed class');
	pg_assert_false(strpos($out, 'pgie-top') !== false, 'a rule of no listed property goes');
	pg_assert_false(strpos($out, 'display') !== false, 'a rule on the root\'s element name goes');

	$out = pg_svg_sanitize(svg_sanitize_doc('<style>input[value^="a"]{fill:red} rect:hover{fill:red} rect::before{fill:red} * {fill:red} a ~ b {fill:red} a + b {fill:red}'
		. ' @media print { rect { fill: red } } @keyframes k { from { opacity: 0 } } @font-face { font-family: x; src: local(x) } rect, circle:hover {stroke: blue}</style><rect width="1" height="1"/>', ' id="r"'));
	pg_assert_contains('<style>#r rect{stroke: blue}</style>', $out, 'only plain selectors and no at-rule stay');

	$once = pg_svg_sanitize(svg_sanitize_doc('<style>rect.box{fill:#2563eb} g > .t{fill:red}</style><rect class="box" width="1" height="1"/>'));
	pg_assert_same($once, pg_svg_sanitize($once), 'a sanitized file comes through unchanged: rules are scoped once');
	pg_assert_true((bool) preg_match('#<style>rect\.pgsvg-[0-9a-f]{10}-box\{fill: \#2563eb\}\n\#pgsvg-[0-9a-f]{10} g &gt; \.pgsvg-[0-9a-f]{10}-t\{#', $once), 'prefixed, and scoped where a compound is an element name');

	$plain = pg_svg_sanitize(svg_sanitize_doc('<rect width="1" height="1"/>'));
	pg_assert_false(strpos($plain, ' id=') !== false, 'no sheet, no generated id');
}

// CSS fetches pictures from plain strings too: the image functions are
// refused in sheets, style attributes and presentation attributes.
function test_svg_sanitize_image_functions()
{
	foreach (array('image-set("https://evil.example/t.png" 1x)', '-webkit-image-set("//evil.example/t.png" 1x)', 'IMAGE-SET(\'https://evil.example/t.png\' 1x)',
		'cross-fade(url(#a), "https://evil.example/t.png", 50%)', 'element(#x)', 'image("https://evil.example/t.png")', 'src("https://evil.example/t.png")') as $value) {
		$sheet = pg_svg_sanitize(svg_sanitize_doc('<style>rect{mask:' . $value . '}</style><rect width="1" height="1"/>'));
		pg_assert_false(strpos($sheet, '<style') !== false, 'sheet: ' . $value);

		$attribute = pg_svg_sanitize(svg_sanitize_doc('<rect width="1" height="1" mask="' . htmlspecialchars($value) . '"/>'));
		pg_assert_contains('<rect width="1" height="1"/>', $attribute, 'mask attribute: ' . $value);

		$style = pg_svg_sanitize(svg_sanitize_doc('<rect width="1" height="1" style="mask: ' . htmlspecialchars($value) . '; fill: red"/>'));
		pg_assert_contains('style="fill: red"', $style, 'style attribute: ' . $value);
		svg_sanitize_assert_clean($sheet . $attribute . $style, $value);
	}
}

// A document type is refused after parsing too: a UTF-16 file hides it
// from a text search.
function test_svg_sanitize_doctype_utf16()
{
	$xml = '<?xml version="1.0" encoding="UTF-16"?><!DOCTYPE svg [<!ENTITY x "boom">]><svg xmlns="http://www.w3.org/2000/svg"><text>&x;</text></svg>';
	$utf16 = "\xFF\xFE" . mb_convert_encoding($xml, 'UTF-16LE', 'UTF-8');

	pg_assert_same('', pg_svg_sanitize($utf16), 'a UTF-16 document type is refused');
}

// Document types, entities, processing instructions and comments: a file
// with a document type is refused whole (entity expansion, outside reads).
function test_svg_sanitize_refuses_entities()
{
	$xxe = '<?xml version="1.0"?><!DOCTYPE svg [<!ENTITY x SYSTEM "file:///etc/passwd">]><svg xmlns="http://www.w3.org/2000/svg"><text>&x;</text></svg>';
	pg_assert_same('', pg_svg_sanitize($xxe), 'an outside entity is refused');

	$laughs = '<?xml version="1.0"?><!DOCTYPE svg [<!ENTITY a "aaaaaaaaaa"><!ENTITY b "&a;&a;&a;&a;&a;&a;&a;&a;&a;&a;">]><svg xmlns="http://www.w3.org/2000/svg"><text>&b;</text></svg>';
	pg_assert_same('', pg_svg_sanitize($laughs), 'an entity bomb is refused');

	$out = pg_svg_sanitize('<?xml version="1.0"?><?xml-stylesheet href="https://evil.example/x.css"?><!-- <script>alert(1)</script> --><svg xmlns="http://www.w3.org/2000/svg"><rect width="1" height="1"/></svg>');
	svg_sanitize_assert_clean($out, 'instructions and comments');
	pg_assert_false(strpos($out, '<?') !== false, 'no processing instruction');
	pg_assert_false(strpos($out, '<!--') !== false, 'no comment');
	pg_assert_contains('<rect width="1" height="1"/>', $out, 'the drawing stays');
}

// Not an SVG, broken XML, an empty or oversized input: refused.
function test_svg_sanitize_refuses_non_svg()
{
	pg_assert_same('', pg_svg_sanitize(''), 'empty');
	pg_assert_same('', pg_svg_sanitize('<html><body>x</body></html>'), 'not an svg root');
	pg_assert_same('', pg_svg_sanitize('<svg xmlns="http://www.w3.org/2000/svg"><rect'), 'broken XML');
	pg_assert_same('', pg_svg_sanitize('<svg><rect width="1"/></svg>'), 'no SVG namespace');
	pg_assert_same('', pg_svg_sanitize(array('<svg/>')), 'not a string');
	pg_assert_same('', pg_svg_sanitize(svg_sanitize_doc(str_repeat('<g/>', 3000000))), 'over the size limit');
}

// Namespaced editor attributes (Inkscape, Sodipodi) and unknown elements go;
// a gradient's or text's own attributes stay.
function test_svg_sanitize_foreign_namespaces()
{
	$in = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:inkscape="http://www.inkscape.org/namespaces/inkscape" xmlns:sodipodi="http://sodipodi.sourceforge.net/DTD/sodipodi-0.dtd" width="50" height="40">'
		. '<sodipodi:namedview pagecolor="#fff"/><g inkscape:label="Layer 1" inkscape:groupmode="layer"><radialGradient id="r" cx="0.5" cy="0.5" r="0.5" gradientUnits="objectBoundingBox"><stop offset="0" stop-color="#fff"/></radialGradient>'
		. '<ellipse cx="5" cy="5" rx="4" ry="3" fill="url(#r)"/><polygon points="0,0 5,5 0,5"/><polyline points="0 0 1 1"/><line x1="0" y1="0" x2="1" y2="1"/></g><blink>x</blink></svg>';
	$out = pg_svg_sanitize($in);

	pg_assert_false(strpos($out, 'inkscape') !== false, 'no inkscape attribute or namespace');
	pg_assert_false(strpos($out, 'sodipodi') !== false, 'no sodipodi element or namespace');
	pg_assert_false(strpos($out, 'blink') !== false, 'an unknown element goes');
	pg_assert_contains('<radialGradient id="r" cx="0.5" cy="0.5" r="0.5" gradientUnits="objectBoundingBox">', $out, 'a radial gradient stays');
	pg_assert_contains('<polygon points="0,0 5,5 0,5"/>', $out, 'a polygon stays');
	pg_assert_contains('width="50" height="40"', $out, 'the root keeps its size');
}

// A file whose sheet styles symbol content drawn by <use> (as Illustrator
// exports it): what a <use> draws matches selectors inside the use's own
// tree, so a rule scoped under the root's id stops there. Ids and classes
// get the file's prefix (in the elements, href, url(#…) and the rules) and
// a rule whose every compound names one of them is left unscoped.
function test_svg_sanitize_sheet_reaches_use_copies()
{
	$in = svg_sanitize_doc('<defs><style>.cls-1{fill:#e30000}#star{stroke:#000;stroke-width:2}.cls-1 path{fill:#00f}.cls-1 > .cls-2{opacity:.5}#Layer_1 .cls-2{fill:url(#g1)}#Layer_1{color:red}</style>'
		. '<linearGradient id="g1"><stop offset="0" stop-color="#fff"/></linearGradient>'
		. '<symbol id="star" viewBox="0 0 10 10"><path class="cls-1 cls-2" d="M0 0h10v10z" fill="url(#g1)" style="stroke: url(&quot;#g1&quot;)"/></symbol></defs>'
		. '<use href="#star" width="10" height="10"/><use xlink:href="#star" x="20"/>', ' id="Layer_1"');
	$out = pg_svg_sanitize($in);

	pg_assert_true((bool) preg_match('#<svg [^>]*id="Layer_1"#', $out), 'the root keeps its id');
	pg_assert_contains("<style>.pgsvg-Layer_1-cls-1{fill: #e30000}\n#pgsvg-Layer_1-star{stroke: #000; stroke-width: 2}\n#Layer_1 .pgsvg-Layer_1-cls-1 path{fill: #00f}\n"
		. ".pgsvg-Layer_1-cls-1 &gt; .pgsvg-Layer_1-cls-2{opacity: .5}\n.pgsvg-Layer_1-cls-2{fill: url(#pgsvg-Layer_1-g1)}\n#Layer_1 #Layer_1{color: red}</style>", $out,
		'a rule of prefixed ids and classes only is unscoped; one with an element name or the root\'s id stays under the root');
	pg_assert_contains('<symbol id="pgsvg-Layer_1-star"', $out, 'ids get the prefix');
	pg_assert_contains('<linearGradient id="pgsvg-Layer_1-g1">', $out, 'a definition\'s id gets the prefix');
	pg_assert_contains('class="pgsvg-Layer_1-cls-1 pgsvg-Layer_1-cls-2"', $out, 'every class gets the prefix');
	pg_assert_contains('fill="url(#pgsvg-Layer_1-g1)"', $out, 'url(#…) in an attribute follows');
	pg_assert_contains('style="stroke: url(&quot;#pgsvg-Layer_1-g1&quot;)"', $out, 'url(#…) in a style attribute follows');
	pg_assert_contains('<use href="#pgsvg-Layer_1-star"', $out, 'href follows');
	pg_assert_contains('xlink:href="#pgsvg-Layer_1-star"', $out, 'xlink:href follows');
	pg_assert_same($out, pg_svg_sanitize($out), 'a sanitized file comes through unchanged: names are prefixed once');

	// A chosen root id cannot steer an unscoped rule onto the panel's names.
	$out = pg_svg_sanitize(svg_sanitize_doc('<style>.top{display:none}#pgie-top{display:none}</style><rect class="top" width="1" height="1"/>', ' id="pgie-top"'));
	pg_assert_contains("<style>.pgsvg-pgie-top-top{display: none}\n#pgie-top #pgie-top{display: none}</style>", $out, 'unscoped names all start with pgsvg-');

	// A generated root id is the prefix itself.
	$out = pg_svg_sanitize(svg_sanitize_doc('<style>.a{fill:red}</style><rect class="a" width="1" height="1"/>'));
	pg_assert_true((bool) preg_match('#<svg [^>]*id="(pgsvg-[0-9a-f]{10})"#', $out, $m), 'a root without an id gets one');
	pg_assert_contains('<style>.' . (isset($m[1]) ? $m[1] : '?') . '-a{fill: red}</style>', $out, 'prefixed with the generated id');

	// A file sanitized before names were prefixed: its scoped rules and
	// plain classes are brought to the prefixed form together.
	$out = pg_svg_sanitize(svg_sanitize_doc('<style>#art .a{fill:red}</style><rect class="a" width="1" height="1"/>', ' id="art"'));
	pg_assert_contains('<style>.pgsvg-art-a{fill: red}</style>', $out, 'an old scoped rule');
	pg_assert_contains('class="pgsvg-art-a"', $out, 'and its class');

	// A motion group's class is the editor's, styled after the sanitizer.
	$out = pg_svg_sanitize(svg_sanitize_doc('<style>.a{fill:red}</style><g class="pgm-0 a"><rect width="1" height="1"/></g>', ' id="m"'));
	pg_assert_contains('<g class="pgm-0 pgsvg-m-a">', $out, 'pgm-n keeps its name');

	// Without a sheet nothing is renamed.
	$out = pg_svg_sanitize(svg_sanitize_doc('<symbol id="s"><rect class="a" width="1" height="1"/></symbol><use href="#s"/>'));
	pg_assert_contains('<symbol id="s"><rect class="a"', $out, 'no sheet, names unchanged');
	pg_assert_contains('href="#s"', $out, 'no sheet, references unchanged');
}
