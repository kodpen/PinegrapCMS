<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Advanced Filters panel of the contacts list (view_contacts.php)
 * and the Referring URL filter of the submitted forms list
 * (view_submitted_forms.php): the person, company, address, phone and e-mail
 * boxes stay out of the browser's address autofill, the phone, fax and web
 * address boxes ask for the matching keyboard, and every box stays
 * type="text" so a partial value still filters.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

/**
 * Each text input tag of a list screen, keyed by its name.
 */
function contact_filter_keyboards_test_inputs($file)
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
	preg_match_all('/<input\b[^>]*\bname="([a-z_0-9]+)"[^>]*>/', $source, $matches, PREG_SET_ORDER);

	$inputs = array();

	foreach ($matches as $match) {
		$inputs[$match[1]] = $match[0];
	}

	return $inputs;
}

function test_contact_filter_keyboards_no_address_autofill()
{
	$inputs = contact_filter_keyboards_test_inputs('view_contacts.php');

	$names = array('first_name', 'last_name', 'company', 'business_phone', 'home_phone', 'mobile_phone', 'business_fax', 'home_fax', 'email_address');

	foreach (array('business', 'home') as $kind) {
		foreach (array('address_1', 'address_2', 'city', 'state', 'zip_code', 'country') as $part) {
			$names[] = $kind . '_' . $part;
		}
	}

	foreach ($names as $name) {
		pg_assert_true(isset($inputs[$name]), 'filter box found: ' . $name);
		pg_assert_true(strpos($inputs[$name], 'autocomplete="pg-no-autofill"') !== false, 'no address autofill: ' . $name);
		pg_assert_true(strpos($inputs[$name], 'type="text"') !== false, 'stays a text box: ' . $name);
	}
}

function test_contact_filter_keyboards_phone_boxes_ask_for_tel()
{
	$inputs = contact_filter_keyboards_test_inputs('view_contacts.php');

	foreach (array('business_phone', 'home_phone', 'mobile_phone', 'business_fax', 'home_fax') as $name) {
		pg_assert_true(strpos($inputs[$name], 'inputmode="tel"') !== false, 'phone keyboard: ' . $name);
	}

	pg_assert_true(strpos($inputs['email_address'], 'inputmode="email"') !== false, 'e-mail keyboard kept');
	pg_assert_true(strpos($inputs['business_zip_code'], 'inputmode') === false, 'zip code keeps the text keyboard');
}

function test_contact_filter_keyboards_url_boxes()
{
	$boxes = array(
		array('view_contacts.php', 'website'),
		array('view_submitted_forms.php', 'http_referer'),
	);

	foreach ($boxes as $box) {
		$inputs = contact_filter_keyboards_test_inputs($box[0]);
		$tag = $inputs[$box[1]];

		pg_assert_true(strpos($tag, 'type="text"') !== false, 'stays a text box: ' . $box[1]);
		pg_assert_true(strpos($tag, 'inputmode="url"') !== false, 'web address keyboard: ' . $box[1]);
		pg_assert_true(strpos($tag, 'spellcheck="false"') !== false, 'no spellcheck: ' . $box[1]);
		pg_assert_true(strpos($tag, 'autocapitalize="off"') !== false, 'no capital first letter: ' . $box[1]);
	}
}
