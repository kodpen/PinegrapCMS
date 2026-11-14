<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the outgoing mode of includes/fn/mail_html.php, which cleans
 * HTML pasted as a campaign's body: a whole document again, @media rules,
 * https pictures and https url() kept, merge fields kept in links, and
 * everything the incoming mode refuses still refused. The incoming mode is
 * checked to be unchanged by the same input.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function mail_html_outgoing_test_absent($needle, $haystack, $label)
{
	pg_assert_true(stripos((string) $haystack, (string) $needle) === false, $label . ' (' . $needle . ')');
}

function mail_html_outgoing_test_newsletter()
{
	return '<!DOCTYPE html><html><head><meta charset="windows-1254"><title>News</title>'
		. '<link rel="stylesheet" href="https://cdn.example.com/x.css">'
		. '<style>.wrap { width: 600px } @media (max-width: 620px) { .wrap { width: 100% !important } }'
		. ' .hero { background-image: url("https://cdn.example.com/hero.jpg") } .bad { background: url(http://x.test/a.png) }'
		. ' @import url(https://x.test/b.css); @font-face { font-family: X; src: url(https://x.test/f.woff) }</style>'
		. '<script>alert(1)</script></head>'
		. '<body bgcolor="#f4f4f4" style="margin: 0">'
		. '<table role="presentation" class="wrap" width="600" cellpadding="0"><tr><td class="hero" style="background: url(\'https://cdn.example.com/bg.png\') #fff; padding: 24px">'
		. '<h1 style="color: #222">Hello ^^first_name^^</h1>'
		. '<img src="https://cdn.example.com/p.png" alt="Product" width="280">'
		. '<img src="http://cdn.example.com/plain.png" alt="Plain">'
		. '<img src="cid:abc@x" alt="Inline">'
		. '<p><a href="https://shop.example.com/sale" style="color: #0a58ca">Shop</a> '
		. '<a href="^^unsubscribe_url^^">Leave</a> <a href="javascript:alert(1)">x</a> <a href="^^bad name^^">y</a></p>'
		. '<form action="https://x.test"><input name="q"></form><iframe src="https://x.test"></iframe>'
		. '<div onclick="steal()" style="width: expression(alert(1)); color: red">Text</div>'
		. '</td></tr></table></body></html>';
}

function test_mail_html_outgoing_is_a_whole_document_with_its_look()
{
	$out = pg_mail_html_clean_outgoing(mail_html_outgoing_test_newsletter());

	pg_assert_true(strpos($out, '<!DOCTYPE html><html><head><meta charset="utf-8">') === 0, 'a document in UTF-8');
	pg_assert_contains('<meta name="viewport" content="width=device-width, initial-scale=1">', $out, 'phones get the viewport');
	pg_assert_contains('<body style="background-color: #f4f4f4; margin: 0">', $out, 'the body keeps its look on <body>');
	pg_assert_contains('<table role="presentation" class="wrap" width="600" cellpadding="0">', $out, 'tables keep their attributes');
	pg_assert_contains('Hello ^^first_name^^', $out, 'a merge field in the text stays');
	pg_assert_true(substr($out, -14) === '</body></html>', 'the document is closed');
}

function test_mail_html_outgoing_keeps_media_rules_and_https_pictures()
{
	$out = pg_mail_html_clean_outgoing(mail_html_outgoing_test_newsletter());

	pg_assert_contains('@media (max-width: 620px) {.wrap {width: 100% !important}}', $out, 'the phone rule stays');
	pg_assert_contains('.hero {background-image: url("https://cdn.example.com/hero.jpg")}', $out, 'an https url() in the sheet stays');
	pg_assert_contains('background: url(&#039;https://cdn.example.com/bg.png&#039;) #fff; padding: 24px', $out, 'an https url() in a style attribute stays');
	pg_assert_contains('<img src="https://cdn.example.com/p.png" alt="Product" width="280">', $out, 'an https picture keeps its src');
	pg_assert_contains('href="https://shop.example.com/sale"', $out, 'a web link stays');
	pg_assert_contains('href="^^unsubscribe_url^^"', $out, 'a merge field link stays');
}

function test_mail_html_outgoing_still_refuses_what_runs_or_loads_elsewhere()
{
	$out = pg_mail_html_clean_outgoing(mail_html_outgoing_test_newsletter());

	foreach (array('<script', 'alert(1)', '<link', 'cdn.example.com/x.css', '<form', '<input', '<iframe', 'onclick', 'expression', 'javascript:', '@import', '@font-face', 'f.woff', 'http://x.test/a.png', 'http://cdn.example.com/plain.png', 'cid:', 'data-pg-src', 'data-pg-cid', '^^bad name^^', '<title') as $needle) {
		mail_html_outgoing_test_absent($needle, $out, 'left out');
	}

	pg_assert_contains('<div style="color: red">Text</div>', $out, 'the safe declaration of a mixed style stays');
	mail_html_outgoing_test_absent('alt="Plain"', $out, 'a picture with only a plain http address is left out');
}

function test_mail_html_outgoing_css_checks()
{
	pg_assert_true(pg_mail_html_css_ok('url(https://a.example/x.png) no-repeat', true), 'an https url() passes outgoing');
	pg_assert_true(pg_mail_html_css_ok("url( 'https://a.example/x.png' )", true), 'quoted and spaced');
	pg_assert_false(pg_mail_html_css_ok('url(https://a.example/x.png)', false), 'never incoming');
	pg_assert_false(pg_mail_html_css_ok('url(http://a.example/x.png)', true), 'plain http is refused');
	pg_assert_false(pg_mail_html_css_ok('url(data:image/png;base64,AAAA)', true), 'a data: url is refused');
	pg_assert_false(pg_mail_html_css_ok('url(https://a.example/x.png) url(javascript:x)', true), 'a second, bad url() is refused');
	pg_assert_false(pg_mail_html_css_ok('url("https://a.example/x.png\'), expression(1)', true), 'mismatched quotes do not open a gap');
	pg_assert_false(pg_mail_html_css_ok('image-set(url(https://a.example/x.png) 1x)', true), 'image-set stays refused');
	pg_assert_same('', pg_mail_html_href('^^unsubscribe_url^^'), 'a merge field is no incoming link');
	pg_assert_same('^^unsubscribe_url^^', pg_mail_html_href('^^unsubscribe_url^^', true), 'but an outgoing one');
}

function test_mail_html_outgoing_leaves_the_incoming_mode_as_it_was()
{
	$in = pg_mail_html_clean(mail_html_outgoing_test_newsletter());

	pg_assert_contains('data-pg-src="https://cdn.example.com/p.png"', $in, 'incoming still sets an outside picture aside');
	pg_assert_contains('data-pg-cid="abc@x"', $in, 'incoming still names a picture of the mail');
	mail_html_outgoing_test_absent('hero.jpg', $in, 'incoming still refuses url()');
	mail_html_outgoing_test_absent('^^unsubscribe_url^^', $in, 'incoming still refuses a merge field link');
	pg_assert_true(strpos($in, '<style>') === 0, 'incoming is still a fragment');
}

function test_mail_html_outgoing_empty_and_too_large()
{
	pg_assert_same('', pg_mail_html_clean_outgoing(''), 'nothing in, nothing out');
	pg_assert_same('', pg_mail_html_clean_outgoing('<html><body><script>x</script></body></html>'), 'nothing to read is left');
	pg_assert_same('', pg_mail_html_clean_outgoing(str_repeat('a', pg_mail_html_max_bytes() + 1)), 'over the limit');
}
