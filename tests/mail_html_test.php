<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the allowlist cleaner of incoming mail's HTML
 * (includes/fn/mail_html.php): what it keeps (text, tables, inline styles,
 * the mail's own style sheet), what it drops (scripts, forms, frames, event
 * attributes, style sheets and pictures from outside, anything CSS could
 * fetch), how a picture is named (data-pg-src, data-pg-cid, data-pg-file),
 * the 1 MB limit and a mail sent in another character set.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function mail_html_test_absent($needle, $haystack, $label)
{
	pg_assert_true(stripos((string) $haystack, (string) $needle) === false, $label . ' (' . $needle . ')');
}

function test_mail_html_keeps_text_tables_and_inline_styles()
{
	$clean = pg_mail_html_clean('<html><body><h1>Order <b>#12</b></h1>'
		. '<table border="0" cellpadding="4" width="100%" style="border-collapse: collapse"><tr>'
		. '<td colspan="2" align="center" bgcolor="#eeeeee" style="color: #333; font-size: 14px">Total &amp; tax</td></tr></table>'
		. '<p class="note" dir="rtl">Line<br>two</p><ul><li>One</li></ul><hr></body></html>');

	pg_assert_contains('<h1>Order <b>#12</b></h1>', $clean, 'headings and bold');
	pg_assert_contains('<table border="0" cellpadding="4" width="100%" style="border-collapse: collapse">', $clean, 'a table with its attributes');
	pg_assert_contains('<td colspan="2" align="center" bgcolor="#eeeeee" style="color: #333; font-size: 14px">Total &amp; tax</td>', $clean, 'a cell, its look and escaped text');
	pg_assert_contains('<p class="note" dir="rtl">Line<br>two</p>', $clean, 'a void element without an end tag');
	pg_assert_contains('<ul><li>One</li></ul><hr>', $clean, 'lists and a rule');
}

function test_mail_html_drops_what_runs_asks_or_frames()
{
	$clean = pg_mail_html_clean('<p onclick="steal()" onmouseover="x()">Hi</p><p>A<o:p>Outlook</o:p></p>'
		. '<script>alert(1)</script><noscript>no</noscript>'
		. '<form action="https://evil.example/"><input name="password"><button>Send</button>Please sign in</form>'
		. '<iframe src="https://evil.example/"></iframe><object data="x.swf"></object><embed src="x">'
		. '<svg><script>alert(2)</script></svg><math><mi>x</mi></math>'
		. '<base href="https://evil.example/"><meta http-equiv="refresh" content="0;url=https://evil.example/">'
		. '<link rel="stylesheet" href="https://evil.example/a.css"><p>End</p>');

	pg_assert_contains('<p>Hi</p>', $clean, 'the text stays, its event attributes do not');

	foreach (array('onclick', 'onmouseover', '<script', 'alert(', '<form', '<input', '<button', 'password', '<iframe', '<object', '<embed',
		'<svg', '<math', '<base', '<meta', 'refresh', '<link', 'evil.example', '<noscript', '<o:p') as $gone) {
		mail_html_test_absent($gone, $clean, 'dropped');
	}

	pg_assert_contains('Please sign in', $clean, 'an element not on the list is unwrapped, its text kept');
	pg_assert_contains('<p>AOutlook</p>', $clean, 'an Outlook element unwrapped');
	pg_assert_same(false, strpos($clean, '<p>A<p>'), 'never a paragraph inside a paragraph');
	pg_assert_contains('<p>End</p>', $clean, 'what follows the dropped elements stays');
}

// libxml 2.9's HTML parser reads <o:p> as <p>: the prefixed tags are taken
// out before any parser sees them, so the result does not depend on its
// version.
function test_mail_html_prefixed_elements_are_unwrapped_before_parsing()
{
	pg_assert_same('<p>AOutlook</p>', pg_mail_html_unprefix('<p>A<o:p>Outlook</o:p></p>'), 'the tags go, the text stays');
	pg_assert_same('<p>AB</p>', pg_mail_html_unprefix('<p>A<O:P class="x">B</O:P><o:p/><o:p /></p>'), 'any case, attributes, self-closed');
	pg_assert_same('<div>x</div>', pg_mail_html_unprefix('<div><v:shape style="width:1px"><v:imagedata src="cid:a"/></v:shape><w:Sdt.Pr-1>x</w:Sdt.Pr-1></div>'), 'VML and Word parts');
	pg_assert_same('<p xmlns:o="urn:x" title="a:b"><a href="mailto:a@b">m</a></p>', pg_mail_html_unprefix('<p xmlns:o="urn:x" title="a:b"><a href="mailto:a@b">m</a></p>'), 'a colon in an attribute leaves the element alone');
	pg_assert_same('a < b: c', pg_mail_html_unprefix('a < b: c'), 'text that is no tag');

	$clean = pg_mail_html_clean('<p>A<o:p>Outlook</o:p></p><p>B<v:shape><v:imagedata src="cid:a"/></v:shape></p>');

	pg_assert_same('<p>AOutlook</p><p>B</p>', trim($clean), 'cleaned the same on every libxml');
}

function test_mail_html_text_after_an_html5_empty_element_stays()
{
	pg_assert_same('<p>a<wbr>b</p><p>c</p>', pg_mail_html_clean('<p>a<wbr>b</p><p>c</p>'), 'the parser hands the text after <wbr> to it');
	pg_assert_same('<p>x after</p>', pg_mail_html_clean('<p>x <embed src="https://evil.example/y">after</p>'), 'and after a dropped <embed>');
	pg_assert_same('<p>s after</p>', pg_mail_html_clean('<p>s <source src="a">after</p>'), 'and after <source>');
}

function test_mail_html_links_keep_only_web_mail_and_phone_addresses()
{
	$clean = pg_mail_html_clean('<a href="https://example.com/a?b=1&amp;c=2">web</a><a href="mailto:a@example.com">mail</a>'
		. '<a href="tel:+905551112233">phone</a><a href="#top">here</a><a href="javascript:alert(1)">js</a>'
		. '<a href=" JaVaScRiPt:alert(1)">js2</a><a href="data:text/html,x">data</a><a href="/relative">rel</a>'
		. '<a href="vbscript:x">vb</a>');

	pg_assert_contains('<a href="https://example.com/a?b=1&amp;c=2">web</a>', $clean, 'a web address');
	pg_assert_contains('<a href="mailto:a@example.com">mail</a>', $clean, 'a mail address');
	pg_assert_contains('<a href="tel:+905551112233">phone</a>', $clean, 'a telephone number');
	pg_assert_contains('<a href="#top">here</a>', $clean, 'a place in the mail');
	pg_assert_contains('<a>js</a><a>js2</a><a>data</a><a>rel</a><a>vb</a>', $clean, 'every other address is taken off');
}

function test_mail_html_pictures_from_outside_are_set_aside()
{
	$clean = pg_mail_html_clean('<p>x</p><img src="https://tracker.example/open.gif" width="1" height="1" alt="">'
		. '<table background="http://cdn.example/bg.png"><tr><td background="//cdn.example/c.png">c</td></tr></table>'
		. '<img src="cid:Logo.Part1%40Example.com" alt="Logo"><img src="cid:&lt;bad id&gt;">'
		. '<img src="data:image/png;base64,iVBORw0K Gg==" alt="dot"><img src="data:image/svg+xml;base64,PHN2Zz4=">'
		. '<img src="javascript:alert(1)"><img src="/local.png"><img>');

	pg_assert_contains('<img data-pg-src="https://tracker.example/open.gif" width="1" height="1">', $clean, 'an outside picture keeps its address aside, nothing loads');
	pg_assert_contains('<table data-pg-background="http://cdn.example/bg.png">', $clean, 'an outside background too');
	pg_assert_contains('<td>c</td>', $clean, 'a protocol-relative background is dropped');
	pg_assert_contains('<img data-pg-cid="logo.part1@example.com" alt="Logo">', $clean, 'a picture of the mail named by its Content-ID');
	pg_assert_contains('<img src="data:image/png;base64,iVBORw0KGg==" alt="dot">', $clean, 'an embedded raster picture keeps its src');
	pg_assert_same(3, substr_count($clean, '<img'), 'a picture with nothing left to show is left out (bad cid, SVG, script, relative, none)');
	mail_html_test_absent(' src="https', $clean, 'no outside src');
	mail_html_test_absent('svg', $clean, 'no SVG picture');
}

function test_mail_html_css_can_fetch_nothing()
{
	pg_assert_same('color: red; margin: 0 auto', pg_mail_html_style('color:red; background:url(https://t.example/x.gif); margin : 0  auto'), 'url() dropped, the rest kept');

	foreach (array(
		'background-image: url("x")', 'background: URL(x)', 'width: expression(alert(1))', 'behavior: url(x.htc)',
		'-moz-binding: url(x.xml#a)', 'background: image-set("a.png" 1x)', 'background: -webkit-cross-fade(a, b, 50%)',
		'background: element(#a)', 'background: image("a.png")', 'background: src("a.png")', 'content: attr(href url)',
		'background: \75 rl(x)', 'color: red /* x */', 'font-family: "a</style><script>"', 'x: @import',
		'list-style: javascript:x', 'color: {}',
	) as $declaration) {
		pg_assert_same('', pg_mail_html_style($declaration), $declaration);
	}

	pg_assert_same('', pg_mail_html_style('1x: y; :z; a b: c; -: d; x'), 'only plain property names');
	pg_assert_same('--brand: #123; -webkit-text-size-adjust: 100%', pg_mail_html_style('--brand: #123; -webkit-text-size-adjust: 100%'), 'custom and prefixed properties');
}

function test_mail_html_keeps_the_mails_own_style_sheet_cleaned()
{
	$clean = pg_mail_html_clean('<html><head><style type="text/css"><!--'
		. '@import url("https://evil.example/a.css"); @import "https://evil.example/b.css";'
		. '@charset "utf-8"; @font-face { font-family: X; src: url(x.woff) }'
		. ' .btn { color: #fff; background: url(x.png); padding: 4px } /* note */'
		. ' @media screen and (max-width: 600px) { .col { width: 100% !important } }'
		. ' @supports (display: grid) { @media print { p { color: black } } }'
		. ' a[title="<x>"] { color: red }'
		. ' @keyframes k { from { opacity: 0 } }'
		. ' td { } p, div > span { line-height: 1.4 }'
		. '--></style></head><body><p class="btn">Go</p><style>.late { font-weight: bold }</style></body></html>');

	pg_assert_contains('<style>.btn {color: #fff; padding: 4px}'
		. '@media screen and (max-width: 600px) {.col {width: 100% !important}}'
		. '@supports (display: grid) {@media print {p {color: black}}}'
		. 'p, div > span {line-height: 1.4}'
		. '.late {font-weight: bold}</style>', $clean, 'plain rules and @media / @supports kept, each declaration cleaned');

	foreach (array('@import', 'evil.example', '@font-face', 'woff', '@charset', '@keyframes', 'opacity', 'title=', 'note', '<!--') as $gone) {
		mail_html_test_absent($gone, $clean, 'left out of the sheet');
	}

	pg_assert_same(1, substr_count($clean, '</style>'), 'one style element, closed once');
	pg_assert_contains('</style><p class="btn">Go</p>', $clean, 'the content after the sheet');
}

function test_mail_html_nested_at_rules_stop_at_three()
{
	$sheet = pg_mail_html_sheet('@media a { @media b { @media c { p { color: red } } } }');
	$deep = pg_mail_html_sheet('@media a { @media b { @media c { @media d { p { color: red } } } } }');

	pg_assert_same('@media a {@media b {@media c {p {color: red}}}}', $sheet, 'three levels');
	pg_assert_same('', $deep, 'a fourth is left out');
	pg_assert_same('p {color: red}', pg_mail_html_sheet('p { color: red } q { color: blue'), 'an unclosed block ends the sheet');
}

function test_mail_html_body_look_and_character_set()
{
	$clean = pg_mail_html_clean('<html><head><meta charset="windows-1254"><meta http-equiv="Content-Type" content="text/html; charset=iso-8859-9"></head>'
		. '<body bgcolor="#f4f4f4" style="margin:0" onload="x()" class="mail" background="https://cdn.example/bg.jpg">'
		. '<p>Merhaba dünya: ğüşıöç İ</p></body></html>');

	pg_assert_contains('<p>Merhaba dünya: ğüşıöç İ</p>', $clean, 'the text stays UTF-8 whatever the mail named');
	pg_assert_contains('<div class="mail" data-pg-background="https://cdn.example/bg.jpg" style="background-color: #f4f4f4; margin: 0">', $clean, 'the body\'s look on a div');
	mail_html_test_absent('onload', $clean, 'no event on the body');
	pg_assert_same('<p>Plain</p>', pg_mail_html_clean('<!DOCTYPE html><html><body><p>Plain</p></body></html>'), 'a body without a look gets no div');
	pg_assert_same('<p>Bare</p>', pg_mail_html_clean('<p>Bare</p>'), 'a fragment without html or body');
}

function test_mail_html_attribute_values_stay_inside_their_quotes()
{
	$clean = pg_mail_html_clean("<p title='a\" onclick=\"x()' id=\"&lt;x&gt;\" style='font-family: \"A\", serif'>t</p>");

	pg_assert_contains('<p title="a&quot; onclick=&quot;x()" id="&lt;x&gt;" style="font-family: &quot;A&quot;, serif">t</p>', $clean, 'escaped');
	pg_assert_contains('<td nowrap="nowrap">x</td>', pg_mail_html_clean('<table><tr><td nowrap>x</td></tr></table>'), 'a boolean attribute kept');
	pg_assert_contains('<p>x</p>', pg_mail_html_clean('<p dir="sideways" lang="">x</p>'), 'a bad direction and an empty value dropped');
}

function test_mail_html_gives_nothing_when_nothing_is_left_or_it_is_too_large()
{
	pg_assert_same('', pg_mail_html_clean(''), 'no HTML part');
	pg_assert_same('', pg_mail_html_clean('<script>alert(1)</script><style>p{color:red}</style>'), 'nothing to read');
	pg_assert_same('', pg_mail_html_clean('<p>   </p><br>'), 'only white space');
	pg_assert_same('', pg_mail_html_clean('<p>' . str_repeat('a', pg_mail_html_max_bytes()) . '</p>'), 'over 1 MB');
	pg_assert_same(1048576, pg_mail_html_max_bytes(), '1 MB');
	pg_assert_true(pg_mail_html_clean('<img src="cid:a@b">') !== '', 'a picture alone is something to show');

	$deep = str_repeat('<div>', 300) . 'deep' . str_repeat('</div>', 300);
	pg_assert_true(strlen(pg_mail_html_clean($deep)) < strlen($deep), 'nesting past 200 is cut');
}

function test_mail_html_cids_and_their_files()
{
	pg_assert_same('logo@x.com', pg_mail_html_cid('<Logo@X.com>'), 'brackets off, small letters');
	pg_assert_same('a.b@c', pg_mail_html_cid('a.b%40c'), 'decoded from its cid: form');
	pg_assert_same('', pg_mail_html_cid('a"b@c'), 'a quote is not a plain character');
	pg_assert_same('', pg_mail_html_cid(''), 'none');

	$clean = pg_mail_html_clean('<p>Hi</p><img src="cid:logo@x"><img src="cid:photo@x"><img src="cid:LOGO@x">');

	pg_assert_same(array('logo@x', 'photo@x'), pg_mail_html_cids($clean), 'each Content-ID once');
	pg_assert_same(array(), pg_mail_html_cids(''), 'none in no HTML');

	$linked = pg_mail_html_link_files($clean, array('logo@x' => 41, 'photo@x' => 0, 'other@x' => 9));

	pg_assert_same(2, substr_count($linked, 'data-pg-cid="logo@x" data-pg-file="41"'), 'a kept picture names its file wherever it is shown');
	pg_assert_contains('<img data-pg-cid="photo@x">', $linked, 'a picture without a kept file stays named by its Content-ID');
	mail_html_test_absent('data-pg-file="9"', $linked, 'a file the HTML does not show');
}

function test_mail_html_module_is_loaded()
{
	pg_assert_contains("require_once(PG_FUNCTIONS_DIR . '/includes/fn/mail_html.php');", (string) file_get_contents(PG_FUNCTIONS_DIR . '/functions.php'), 'loaded with the other mail modules');
}

function test_mail_html_document_is_whole_and_keeps_the_frame_in_charge_of_its_height()
{
	$clean = pg_mail_html_clean('<html><head><style>p{color:red}</style></head><body bgcolor="#eee"><p>Hi <a href="https://example.com/x">there</a></p></body></html>');
	$document = pg_mail_html_document($clean);

	pg_assert_same(0, strpos($document, '<!DOCTYPE html>'), 'standards mode, never quirks');
	pg_assert_contains('<base target="_blank">', $document, 'a link opens a new tab before the frame dresses it');
	pg_assert_false(strpos($document, '<base href') !== false, 'base-uri stays closed: no address of its own');
	pg_assert_contains('html:root,html:root>body{height:auto!important', $document, 'a mail\'s height: 100% never follows the frame');
	pg_assert_contains('overflow-y:hidden', $document, 'the frame grows, it never scrolls inside');
	pg_assert_contains('background:#fff', $document, 'drawn on white in either theme');
	pg_assert_contains('<body>' . $clean . '</body></html>', $document, 'the cleaned HTML unchanged in the body');
	pg_assert_false(strpos($document, '<script') !== false);
}
