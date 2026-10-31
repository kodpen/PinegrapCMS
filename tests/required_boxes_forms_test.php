<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins: on the shared create/edit forms, every always-visible box
 * the server refuses blank carries required, each edit screen's Delete
 * button skips that check, and no fixed 'Required Area' text sits beside
 * the message liveform prints for a refused field.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function required_boxes_forms_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

function required_boxes_forms_field($source, $name)
{
	// A liveform output_field() call naming the field, up to the end of its
	// options array.
	if (preg_match('/output_field\(array\((?:(?!output_field\().)*?\'name\'\s*=>\s*\'' . preg_quote($name, '/') . '\'.*?\)\)/s', $source, $match)) {
		return $match[0];
	}

	// A hand-written <input> or <select> tag with that name.
	if (preg_match('/<(?:input|select)\b[^>]*\bname="' . preg_quote($name, '/') . '"[^>]*>/', $source, $match)) {
		return $match[0];
	}

	return '';
}

function test_required_boxes_forms_marked_required()
{
	$fields = array(
		'includes/forms/ad_form.php' => array('name', 'ad_region_id'),
		'includes/forms/login_region_form.php' => array('name'),
		'includes/forms/verified_shipping_address_form.php' => array('address_1', 'city', 'zip_code'),
		'includes/forms/container_form.php' => array('name'),
		'includes/forms/ship_date_adjustment_form.php' => array('zip_code_prefix', 'shipping_method_id', 'adjustment_days'),
		'includes/forms/email_campaign_profile_form.php' => array('name', 'subject', 'from_name', 'from_email_address'),
		'includes/forms/calendar_event_location_form.php' => array('name'),
		'includes/forms/menu_item_form.php' => array('name'),
		'includes/forms/product_attribute_form.php' => array('name'),
		'includes/templates/edit_auto_dialog.php' => array('name', 'url'),
		'includes/templates/edit_key_code.php' => array('offer_code', 'quantity'),
		'edit_ad_region.php' => array('name'),
	);

	foreach ($fields as $path => $names) {
		$source = required_boxes_forms_source($path);

		foreach ($names as $name) {
			$field = required_boxes_forms_field($source, $name);
			pg_assert_true($field !== '', $path . ': field ' . $name . ' not found');
			pg_assert_true(
				(bool) preg_match('/\'required\'\s*=>\s*\'required\'|\srequired(?=[\s>\/])/', $field),
				$path . ': field ' . $name . ' is not marked required'
			);
		}
	}
}

function test_required_boxes_forms_swapping_boxes_stay_optional()
{
	// The dimension boxes sit in inch/cm groups swapped with d-none, and the
	// state box is a combobox: a required hidden box would block the save.
	$fields = array(
		'includes/forms/container_form.php' => array('length', 'width', 'height'),
		'includes/forms/verified_shipping_address_form.php' => array('state_id'),
	);

	foreach ($fields as $path => $names) {
		$source = required_boxes_forms_source($path);

		foreach ($names as $name) {
			$field = required_boxes_forms_field($source, $name);
			pg_assert_true(
				$field === '' || !preg_match('/\'required\'\s*=>\s*\'required\'|\srequired(?=[\s>\/])/', $field),
				$path . ': field ' . $name . ' must not be required'
			);
		}
	}
}

function test_required_boxes_forms_delete_skips_validation()
{
	$paths = array(
		'edit_ad.php',
		'edit_login_region.php',
		'edit_verified_shipping_address.php',
		'edit_container.php',
		'edit_ship_date_adjustment.php',
		'edit_email_campaign_profile.php',
		'edit_calendar_event_location.php',
		'edit_menu_item.php',
		'edit_product_attribute.php',
		'edit_ad_region.php',
		'includes/templates/edit_auto_dialog.php',
		'includes/templates/edit_key_code.php',
		'edit_calendar.php',
		'edit_currency.php',
		'edit_calendar_event.php',
		'edit_short_link.php',
	);

	foreach ($paths as $path) {
		$source = required_boxes_forms_source($path);
		pg_assert_true((bool) preg_match_all('/<button type="submit"[^>]*value="Delete"[^>]*>/s', $source, $matches), $path . ': no Delete button found');

		foreach ($matches[0] as $tag) {
			pg_assert_true(strpos($tag, 'formnovalidate') !== false, $path . ': Delete button without formnovalidate');
		}
	}
}

function test_required_boxes_forms_key_code_required_on_edit_only()
{
	// On create the Code may be left blank (or is collapsed for several
	// codes), so only the edit screen asks for it.
	$source = required_boxes_forms_source('includes/templates/edit_key_code.php');
	pg_assert_true(
		(bool) preg_match('/\'name\'\s*=>\s*\'code\'.*?\$screen\s*==\s*\'edit\'\s*\?\s*array\(\'required\'\s*=>\s*\'required\'\)/s', $source),
		'edit_key_code.php: Code is not required on the edit screen only'
	);
}

function test_required_boxes_forms_no_static_required_area()
{
	// liveform prints the server's own message after a refused field, and
	// Bootstrap's .is-invalid ~ .invalid-feedback would show a fixed text
	// beside it.
	$paths = array(
		'includes/forms/calendar_event_location_form.php',
		'includes/forms/calendar_form.php',
		'includes/forms/currency_form.php',
		'includes/forms/menu_item_form.php',
		'includes/forms/product_attribute_form.php',
		'includes/forms/product_group_form.php',
		'includes/forms/ad_form.php',
		'includes/forms/short_link_form.php',
		'add_short_link.php',
		'includes/forms/email_campaign_profile_form.php',
		'includes/forms/calendar_event_form.php',
		'includes/templates/edit_auto_dialog.php',
		'includes/templates/edit_key_code.php',
		'edit_ad_region.php',
	);

	foreach ($paths as $path) {
		pg_assert_true(
			strpos(required_boxes_forms_source($path), 'Required Area') === false,
			$path . ': static Required Area message left'
		);
	}
}
