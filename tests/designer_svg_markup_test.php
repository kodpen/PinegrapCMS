<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the cleaning of an svg content node's drawing
 * (pg_designer_svg_markup() in includes/fn/designer.php). The node is not
 * locked for content-level operators, so the cleaner is what keeps their
 * markup from running script on the site: an event handler written the
 * ways a browser still reads as an attribute (after a slash, straight after
 * a quoted value, unquoted) and an unquoted script URL are dropped too.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _test_svg_clean_has_no_script($raw, $label)
{
	$out = pg_designer_svg_markup($raw);
	pg_assert_false((bool)preg_match('~[\s/"\']on[a-z]+\s*=~i', $out), $label . ': no event handler in ' . $out);
	pg_assert_false(stripos($out, 'javascript:') !== false, $label . ': no script URL in ' . $out);
	pg_assert_false((bool)preg_match('~<(script|foreignobject|iframe|object|embed)\b~i', $out), $label . ': no script element in ' . $out);
	return $out;
}

function test_designer_svg_markup_keeps_a_drawing()
{
	$raw = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" class="logo" fill="currentColor">'
		. '<path d="M8 0a8 8 0 1 0 0 16A8 8 0 0 0 8 0z"/><circle cx=\'8\' cy=\'8\' r=\'2\'></circle></svg>';
	pg_assert_same(
		'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" class="logo" fill="currentColor">'
		. '<path d="M8 0a8 8 0 1 0 0 16A8 8 0 0 0 8 0z"/><circle cx="8" cy="8" r="2"></circle></svg>',
		pg_designer_svg_markup($raw),
		'a plain drawing keeps its elements and attributes'
	);
	pg_assert_same('', pg_designer_svg_markup('<div>no drawing</div>'), 'no <svg>, no markup');
	pg_assert_same('<svg><text x="1">a &amp; b &lt; c</text></svg>', pg_designer_svg_markup('<svg><text x="1">a &amp; b &lt; c</text></svg>'), 'text and its entities stay');
	pg_assert_same('<svg><image href="data:image/png;base64,AAA="/></svg>', pg_designer_svg_markup('<svg><image href="data:image/png;base64,AAA="/></svg>'), 'an image data URL stays');
}

function test_designer_svg_markup_drops_handlers_the_browser_reads()
{
	_test_svg_clean_has_no_script('<svg/onload=alert(1)></svg>', 'after the slash of the tag');
	_test_svg_clean_has_no_script('<svg><g/onclick="alert(1)"></g></svg>', 'after a slash, quoted');
	_test_svg_clean_has_no_script('<svg x="1"onload="alert(1)"></svg>', 'straight after a quoted value');
	_test_svg_clean_has_no_script("<svg x='1'onload=alert(1)></svg>", 'straight after a single-quoted value');
	_test_svg_clean_has_no_script('<svg><rect x=a\'b onclick=alert(1) /></svg>', 'after an unquoted value holding a quote');
	_test_svg_clean_has_no_script("<svg\nonload\n=\nalert(1)></svg>", 'line breaks around the equals sign');
	_test_svg_clean_has_no_script('<svg><g ONMOUSEOVER=alert(1)></g></svg>', 'upper case');
	$out = _test_svg_clean_has_no_script('<svg viewBox="0 0 1 1" onload="alert(1)"><path d="M0 0"/></svg>', 'the plain form');
	pg_assert_contains('viewBox="0 0 1 1"', $out, 'the other attributes stay');
}

function test_designer_svg_markup_drops_script_urls()
{
	_test_svg_clean_has_no_script('<svg><a href=javascript:alert(1)><text>x</text></a></svg>', 'an unquoted href');
	_test_svg_clean_has_no_script('<svg><a xlink:href="&#106;avascript:alert(1)"><text>x</text></a></svg>', 'an entity-encoded href');
	_test_svg_clean_has_no_script('<svg><a href="java&Tab;script:alert(1)"><text>x</text></a></svg>', 'a tab inside the scheme');
	_test_svg_clean_has_no_script('<svg><animate attributeName="href" values=javascript:alert(1) /></svg>', 'an animated href');
	$out = pg_designer_svg_markup('<svg><use href=data:text/html,x></use></svg>');
	pg_assert_false(stripos($out, 'data:text') !== false, 'a data URL that is not an image is dropped: ' . $out);
}

function test_designer_svg_markup_drops_script_elements()
{
	_test_svg_clean_has_no_script('<svg><script>alert(1)</script></svg>', 'a script');
	_test_svg_clean_has_no_script('<svg><script a=">">alert(1)</script></svg>', 'a script whose attribute holds >');
	_test_svg_clean_has_no_script('<svg><SCRIPT>alert(1)</SCRIPT><rect/></svg>', 'an upper-case script');
	_test_svg_clean_has_no_script('<svg><foreignObject><img src=x onerror=alert(1)></foreignObject></svg>', 'a foreignObject');
	_test_svg_clean_has_no_script('<svg><script>alert(1)</svg>', 'a script never closed');
	$out = pg_designer_svg_markup('<svg><script>alert(1)</script><rect width="1"/></svg>');
	pg_assert_false(strpos($out, 'alert') !== false, 'the script text goes with it: ' . $out);
	pg_assert_contains('<rect width="1"/>', $out, 'what follows the script stays');
}

function test_designer_svg_markup_text_cannot_turn_into_markup()
{
	// Inside <title>/<desc> the browser parses HTML: a raw-text element or
	// a CDATA section there must not hide markup from the cleaner.
	// The handler may stay as escaped text; no <img> tag may come out.
	$cases = array(
		'markup hidden in an attribute value' => '<svg><desc><style><g x="</style><img src=x onerror=alert(1)>"></g></style></desc></svg>',
		'markup in a CDATA section'           => '<svg><title><![CDATA[><img src=x onerror=alert(1)>]]></title></svg>',
		'a comment the browser closes at once' => '<svg><!--><img src=x onerror=alert(1)>--></svg>',
		'a comment closed by --!>'            => '<svg><!-- a --!><img src=x onerror=alert(1)>--></svg>',
		'a processing instruction'            => '<svg><?x ><img src=x onerror=alert(1)>?></svg>',
	);
	foreach ($cases as $label => $raw) {
		$out = pg_designer_svg_markup($raw);
		pg_assert_false((bool)preg_match('~<img\b[^>]*\sonerror~i', $out), $label . ': no handler on a tag in ' . $out);
		pg_assert_false((bool)preg_match('~<(style|g)\b[^>]*<~', $out), $label . ': no markup inside a tag in ' . $out);
	}
	pg_assert_same('<svg><desc><style><g x="&lt;/style&gt;&lt;img src=x onerror=alert(1)&gt;"></g></style></desc></svg>', pg_designer_svg_markup($cases['markup hidden in an attribute value']), 'the value is escaped');
	pg_assert_same('<svg><title>&gt;&lt;img src=x onerror=alert(1)&gt;</title></svg>', pg_designer_svg_markup($cases['markup in a CDATA section']), 'the section is text');
	pg_assert_same('<svg><style>.a&gt;b{fill:red}</style></svg>', pg_designer_svg_markup('<svg><style><![CDATA[.a>b{fill:red}]]></style></svg>'), 'CDATA becomes escaped text');
}
