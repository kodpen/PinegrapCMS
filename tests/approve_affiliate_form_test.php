<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Approve Affiliate screen's layout helpers
 * (includes/forms/approve_affiliate_form.php): the request's facts, the
 * website link and the columns for a pending and an approved contact.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/approve_affiliate_form.php');

function test_approve_affiliate_form_facts()
{
	pg_assert_same(
		array('name' => 'Deniz Kaya', 'email_address' => 'deniz@example.test', 'website' => 'example.test', 'phone' => '+90 532', 'country' => 'TR'),
		pg_approve_affiliate_form_facts(array(
			'first_name' => ' Deniz', 'last_name' => 'Kaya ', 'email_address' => 'deniz@example.test', 'company' => '  ',
			'website' => 'example.test', 'business_phone' => '', 'mobile_phone' => '+90 532', 'business_country' => 'tr',
			'affiliate_name' => null, 'business_city' => array('x'),
		)),
		'empty, null and nested values left out; the mobile phone stands in; the country code in capitals');

	pg_assert_same('+90 232', pg_approve_affiliate_form_facts(array('business_phone' => '+90 232', 'mobile_phone' => '+90 532'))['phone'], 'the business phone comes first');
	pg_assert_same('Kaya', pg_approve_affiliate_form_facts(array('last_name' => 'Kaya'))['name'], 'a last name alone has no leading space');
}

function test_approve_affiliate_form_website_url()
{
	pg_assert_same('https://example.test', pg_approve_affiliate_form_website_url('example.test'), 'no scheme: https is added');
	pg_assert_same('http://example.test/a', pg_approve_affiliate_form_website_url(' http://example.test/a '), 'an http address is kept');
	pg_assert_same('', pg_approve_affiliate_form_website_url('javascript:alert(1)'), 'another scheme is not linked');
	pg_assert_same('', pg_approve_affiliate_form_website_url('//evil.test'), 'a //host start is not linked');
	pg_assert_same('', pg_approve_affiliate_form_website_url('my site'), 'text with spaces is not linked');
	pg_assert_same('', pg_approve_affiliate_form_website_url('https://'), 'a scheme with no host is not linked');
}

function test_approve_affiliate_form_columns()
{
	$contact = array('email_address' => 'a<b@example.test', 'affiliate_code' => 'Q4EOZ', 'website' => 'javascript:x');

	$pending = pg_approve_affiliate_form_card($contact, false) . pg_approve_affiliate_form_status($contact, false, '&mdash;', '/c?id=1');
	pg_assert_contains('lg:col-8 2xl:col-9 order-1', $pending, 'the form column');
	pg_assert_contains('lg:col-4 2xl:col-3 order-2', $pending, 'the status column');
	pg_assert_contains('a&lt;b@example.test', $pending, 'the e-mail address is escaped');
	pg_assert_true(strpos($pending, 'href="javascript:') === false, 'an unsafe website is printed as text');
	pg_assert_contains('theme-warning', $pending, 'a pending request has the warning badge');
	pg_assert_contains('Q4EOZ', $pending, 'the code in the Summary');

	$approved = pg_approve_affiliate_form_status(array('affiliate_code' => ''), true, '&mdash;', '/c?id=1');
	pg_assert_contains('theme-success', $approved, 'an approved affiliate has the success badge');
	pg_assert_contains('&mdash;', $approved, 'no code yet reads a dash');

	$layout = pg_approve_affiliate_form_layout('<div id="cols"></div>', '<button>x</button>');
	pg_assert_contains('order-3 position-sticky', $layout, 'the sticky save bar');
}
