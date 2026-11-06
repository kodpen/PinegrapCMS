<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a form field filled into a link's address by
 * pg_sw_apply_tokens() (includes/fn/widgets.php): a submitted value whose
 * scheme can run script (javascript:, vbscript:, data:) never becomes the
 * address; a web address, a mail address and a relative path still do.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _href_scheme_fill($template, $value)
{
	if (!defined('DATE_FORMAT')) define('DATE_FORMAT', 'day_month');
	if (!defined('TIME_FORMAT')) define('TIME_FORMAT', 'twenty_four_hours');
	$custom = array('website' => array('data' => $value, 'type' => 'text box', 'wysiwyg' => 0));
	return pg_sw_apply_tokens($template, array(), $custom);
}

function test_widget_href_scheme_script_is_not_linked()
{
	$tpl = '<a href="^^website^^" target="_blank">^^website^^</a>';
	pg_assert_same('<a href="#" target="_blank">javascript:alert(document.cookie)</a>',
		_href_scheme_fill($tpl, 'javascript:alert(document.cookie)'), 'javascript: is not the address, the text stays');
	pg_assert_same('<a href="#" target="_blank">JavaScript:x</a>', _href_scheme_fill($tpl, 'JavaScript:x'), 'any case');
	pg_assert_same('<a href="#">', _href_scheme_fill('<a href="^^website^^">', " java\tscript:x"), 'a tab inside the scheme');
	pg_assert_same('<a href="#">', _href_scheme_fill('<a href="^^website^^">', 'vbscript:x'), 'vbscript:');
	pg_assert_same('<a href="#">', _href_scheme_fill('<a href="^^website^^">', 'data:text/html,<b>x</b>'), 'data:');
	pg_assert_same("<a href='#'>", _href_scheme_fill("<a href='^^website^^'>", 'javascript:x'), 'single quotes');
	pg_assert_same('<form action="#">', _href_scheme_fill('<form action="^^website^^">', 'javascript:x'), 'a form action');
}

function test_widget_href_scheme_web_address_is_linked()
{
	pg_assert_same('<a href="https://example.com/a?b=1&amp;c=2">', _href_scheme_fill('<a href="^^website^^">', 'https://example.com/a?b=1&c=2'), 'https');
	pg_assert_same('<a href="http://example.com">', _href_scheme_fill('<a href="^^website^^">', 'http://example.com'), 'http');
	pg_assert_same('<a href="mailto:a@example.com">', _href_scheme_fill('<a href="^^website^^">', 'mailto:a@example.com'), 'mailto');
	pg_assert_same('<a href="tel:+905551112233">', _href_scheme_fill('<a href="^^website^^">', 'tel:+905551112233'), 'tel');
	pg_assert_same('<a href="/files/a.pdf">', _href_scheme_fill('<a href="^^website^^">', '/files/a.pdf'), 'a path');
	pg_assert_same('<a href="example.com">', _href_scheme_fill('<a href="^^website^^">', 'example.com'), 'no scheme');
	pg_assert_same('<a href="/x/javascript:y">', _href_scheme_fill('<a href="/x/^^website^^">', 'javascript:y'), 'inside an address the page wrote');
	pg_assert_same('<p>javascript:x</p>', _href_scheme_fill('<p>^^website^^</p>', 'javascript:x'), 'text is text');
}
