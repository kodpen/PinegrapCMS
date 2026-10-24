<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_mail_merge_address_hrefs() (includes/fn/mail.php): a link
 * whose address is a mail-merge field that holds a whole address loses the
 * scheme the editor's Link dialog put in front of it, and no other link
 * changes.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The Link dialog stores http:// (its default protocol) or https:// in front
// of what was typed; the field's own address is what is left.
function test_mail_merge_hrefs_scheme_removed()
{
	pg_assert_same(
		'<p><a href="^^review_url^^">Review</a></p>',
		pg_mail_merge_address_hrefs('<p><a href="http://^^review_url^^">Review</a></p>'),
		'http://, double quotes');
	pg_assert_same(
		"<a class='btn' href='^^review_url^^'>Review</a>",
		pg_mail_merge_address_hrefs("<a class='btn' href='HTTPS://^^review_url^^'>Review</a>"),
		'HTTPS://, single quotes');
	pg_assert_same(
		'<a href="^^review_url^^">Review</a>',
		pg_mail_merge_address_hrefs('<a href=" http://^^review_url^^">Review</a>'),
		'a space before the scheme');
	pg_assert_same(
		'<a href="^^review_url^^">A</a> <a href="^^review_url^^">B</a>',
		pg_mail_merge_address_hrefs('<a href="http://^^review_url^^">A</a> <a href="https://^^review_url^^">B</a>'),
		'every such link');
	pg_assert_same(
		'<a href="^^shop_url^^">Shop</a>',
		pg_mail_merge_address_hrefs('<a href="http://^^shop_url^^">Shop</a>', array('shop_url')),
		'the fields named by the caller');
}

// Any other address is left as it was written.
function test_mail_merge_hrefs_others_untouched()
{
	$untouched = array(
		'<a href="^^review_url^^">Review</a>'                     => 'the field alone already',
		'<a href="http://^^order_number^^">Order</a>'             => 'a field that is not an address',
		'<a href="http://example.com/^^review_url^^">Review</a>'  => 'a host before the field',
		'<a href="http://^^review_url^^?utm=mail">Review</a>'     => 'more after the field',
		'<a href="http://^^review_url^^\'>Review</a>'             => 'quotes that do not match',
		'<img src="http://^^review_url^^">'                       => 'not a link',
		'<a data-href="http://^^review_url^^">Review</a>'         => 'another attribute',
		'Visit http://^^review_url^^ today'                       => 'text',
	);

	foreach ($untouched as $body => $label) {
		pg_assert_same($body, pg_mail_merge_address_hrefs($body), $label);
	}

	pg_assert_same('<a href="http://^^review_url^^">x</a>', pg_mail_merge_address_hrefs('<a href="http://^^review_url^^">x</a>', array()), 'no fields named');
	pg_assert_same('', pg_mail_merge_address_hrefs(''), 'an empty body');
}

// The auto campaign runs it on the page body before relative links are made
// absolute and before the fields are filled in.
function test_mail_merge_hrefs_called_by_auto_campaigns()
{
	$mail = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/mail.php');
	$page = strpos($mail, '$body = get_page_content($email_campaign_profile[\'page_id\']');
	$hrefs = strpos($mail, '$body = pg_mail_merge_address_hrefs($body)');
	$absolute = strpos($mail, '$base_in_html = preg_match(');

	pg_assert_true(($page !== false) && ($hrefs !== false) && ($absolute !== false) && ($page < $hrefs) && ($hrefs < $absolute), 'after the page, before the absolute addresses');
}
