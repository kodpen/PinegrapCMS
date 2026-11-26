<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings > Ecommerce, Shipping, Address autocomplete and Stock
 * locations opened: the revealed box behind a button, and the three cards
 * and the functions that draw into them without popovers, plain radios or
 * old switches, every field under its old name.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/commerce_settings_fold.php');

// The part of commerce.php from one card's id to the next card or the next
// part's heading comment.
function settings_commerce_ship_test_card($id)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/commerce.php');
	$open = '<div id="' . $id . '" class="pg-set-card">';
	$start = strpos($source, $open);

	if ($start === false) {
		return '';
	}

	$ends = array(strlen($source));

	foreach (array('class="pg-set-card">', "\n// ── ") as $mark) {
		$end = strpos($source, $mark, $start + strlen($open));

		if ($end !== false) {
			$ends[] = $end;
		}
	}

	return substr($source, $start, min($ends) - $start);
}

// One function's body in a file under pinegrap/.
function settings_commerce_ship_test_function($file, $name)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
	$start = strpos($source, "\nfunction " . $name . '(');

	if ($start === false) {
		return '';
	}

	return substr($source, $start, strpos($source, "\n}\n", $start) - $start);
}

// No popover, no plain radio, no old switch, nothing opened by a switch.
function settings_commerce_ship_test_plain($html, $what)
{
	pg_assert_true($html !== '', $what . ' is drawn');
	pg_assert_false(strpos($html, 'popover') !== false, $what . ' has no popover');
	pg_assert_false(strpos($html, 'class="radio"') !== false, $what . ' has no plain radio');
	pg_assert_false(strpos($html, 'class="switch') !== false, $what . ' has no old switch');
	pg_assert_false(strpos($html, 'collapse-switcher') !== false, $what . ' opens nothing by a switch');
}

function test_settings_commerce_ship_reveal()
{
	$html = pg_commerce_settings_reveal('stock_location_new', 'Add a location', 'plus-lg', '<b>fields</b>');

	pg_assert_contains('<button type="button" class="pg-button pg-button-quiet pg-button-sm" aria-expanded="false" aria-controls="stock_location_new_box" data-pg-commerce-reveal="stock_location_new_box">', $html, 'a quiet button names the box it opens');
	pg_assert_contains('<i class="bi bi-plus-lg" aria-hidden="true"></i>Add a location</button>', $html, 'its icon and label');
	pg_assert_contains('<div class="col-12" id="stock_location_new_box" hidden>', $html, 'the box is closed at first');
	pg_assert_contains('<b>fields</b>', $html, 'the box holds the fields as given');

	$escaped = pg_commerce_settings_reveal('x', 'A & B', 'plus', '');
	pg_assert_contains('>A &amp; B</button>', $escaped, 'the label escaped');

	$script = pg_commerce_settings_reveal_script();
	pg_assert_contains('data-pg-commerce-reveal', $script, 'the script finds the buttons');
	pg_assert_contains('aria-expanded', $script, 'and says what it opened');
}

function test_settings_commerce_ship_cards_drawn()
{
	foreach (array('pgset-shipping', 'pgset-address-autocomplete', 'pgset-stock-locations') as $id) {
		settings_commerce_ship_test_plain(settings_commerce_ship_test_card($id), $id);
	}

	foreach (array(
		array('includes/fn/carrier_labels.php', 'pg_label_settings_rows'),
		array('includes/fn/carrier_api.php', 'pg_carrier_api_settings_fields'),
		array('includes/fn/carrier_api.php', 'pg_carrier_api_test_button'),
		array('includes/fn/address_autocomplete.php', 'pg_addr_settings_fields'),
		array('includes/fn/address_autocomplete.php', 'pg_addr_settings_connection_html'),
	) as $function) {
		settings_commerce_ship_test_plain(settings_commerce_ship_test_function($function[0], $function[1]), $function[1]);
	}

	pg_assert_contains('class="pg-switch"', settings_commerce_ship_test_function('includes/fn/carrier_labels.php', 'pg_label_settings_rows'), 'a label service heads its group with a pg-switch');
	pg_assert_contains('class="pg-switch"', settings_commerce_ship_test_function('includes/fn/address_autocomplete.php', 'pg_addr_settings_fields'), 'address autocomplete is a pg-switch');
}

function test_settings_commerce_ship_shipping()
{
	$card = settings_commerce_ship_test_card('pgset-shipping');

	pg_assert_contains("pg_commerce_settings_switch('ecommerce_shipping'", $card, 'the shipping switch heads the row');
	pg_assert_contains("pg_commerce_settings_seg(lang('Recipient mode'), 'ecommerce_recipient_mode'", $card, 'recipient mode as a pg-seg');
	pg_assert_contains("pg_commerce_settings_seg(lang('Enforcement'), 'ecommerce_address_verification_enforcement_type'", $card, 'the enforcement as a pg-seg');
	pg_assert_contains("pg_commerce_settings_switch('ecommerce_address_verification'", $card, 'US address checking under USPS');
	pg_assert_contains("pg_commerce_settings_group('USPS')", $card, 'USPS is a group');

	foreach (array('ups', 'fedex') as $carrier) {
		pg_assert_contains("pg_commerce_settings_group_switch('" . $carrier . "'", $card, $carrier . ' heads its group');
		pg_assert_contains("pg_carrier_api_test_button('" . $carrier . "')", $card, $carrier . ' keeps its test');
	}

	// $commerce_seg_options() joins the radios' name and these ends into
	// the ids they always had.
	foreach (array("'single recipient', 'single_recipient'", "'multi-recipient', 'multirecipient'", "'warning', 'warning'", "'error', 'error'") as $choice) {
		pg_assert_contains('array(' . $choice . ',', $card, $choice . ' keeps its value and id');
	}

	foreach (array('ups_account', 'fedex_account', 'ecommerce_product_restriction_message', 'ecommerce_no_shipping_methods_message', 'ecommerce_end_of_day_time') as $name) {
		pg_assert_contains('name="' . $name . '"', $card, $name . ' kept');
	}

	pg_assert_contains("pg_commerce_settings_group(lang('Messages'))", $card, 'the two messages in their group');
	pg_assert_contains('pg_label_settings_rows()', $card, 'the label services drawn in');
	pg_assert_contains("lang('Site time now')", $card, 'the end of day beside the site\'s time now');
}

function test_settings_commerce_ship_stock_locations()
{
	$card = settings_commerce_ship_test_card('pgset-stock-locations');

	pg_assert_contains("pg_commerce_settings_reveal('stock_location_new', lang('Add a location')", $card, 'the new location behind Add a location');
	pg_assert_contains('name="stock_location_new_name"', $card, 'the new name kept');
	pg_assert_contains('name="stock_location_new_address"', $card, 'the new address kept');
	pg_assert_contains('name="stock_location_default"', $card, 'the default kept');
	pg_assert_contains('<select', $card, 'the default chosen in a list');
	pg_assert_contains('name="stock_location_card"', $card, 'the card still says it was posted');
	pg_assert_contains('href="view_stock_moves.php"', $card, 'the stock history lead kept');

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/commerce.php');
	pg_assert_contains('class="pg-switch" value="1" name="stock_location_enabled[', $source, 'a location\'s on switch is a pg-switch');
	pg_assert_contains('pg_commerce_settings_reveal_script()', $source, 'the pane prints the reveal script');
}
