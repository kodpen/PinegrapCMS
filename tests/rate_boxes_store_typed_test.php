<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The tax zone rate, the contact commission rate and the user reward points
 * boxes accept only what their columns store: the tax zone rate is read
 * through parse_tax_rate() and a blank or unreadable one is refused, the
 * commission rate box holds decimal(5,2), and reward points cannot go below
 * zero.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/tax_zone_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/contact_form.php');

function rate_boxes_store_typed_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function rate_boxes_store_typed_test_lacks($needle, $haystack, $label)
{
	pg_assert_false(strpos($haystack, $needle) !== false, $label);
}

function test_rate_boxes_tax_zone_box_is_a_bounded_number()
{
	$create = pg_tax_zone_form_cards(null, '', '');
	pg_assert_contains('type="number" min="0" max="100" step="0.001" name="tax_rate" id="tax_rate" required class="form-control"', $create, 'the rate box takes 0 to 100 and cannot be left blank');
	rate_boxes_store_typed_test_lacks('maxlength="10"', $create, 'no free text box');
}

function test_rate_boxes_tax_zone_edit_fills_the_formatted_rate()
{
	$edit = pg_tax_zone_form_cards(array('name' => 'EU', 'tax_rate' => '8.250'), '', '');
	pg_assert_contains('required value="8.25" class="form-control"', $edit, 'the stored 8.250 reads as 8.25');

	$zero = pg_tax_zone_form_cards(array('name' => 'EU', 'tax_rate' => '0.000'), '', '');
	pg_assert_contains('required value="0" class="form-control"', $zero, 'a zero rate stays 0, not blank');
}

function test_rate_boxes_tax_zone_rate_parse_path()
{
	pg_assert_same('8.250', parse_tax_rate('8,25'), 'a comma is the decimal separator, not the end of the number');
	pg_assert_same(null, parse_tax_rate(''), 'blank is refused, the column is NOT NULL');
	pg_assert_same(null, parse_tax_rate('abc'), 'unreadable is refused');
}

function test_rate_boxes_tax_zone_saves_read_the_rate()
{
	foreach (array('add_tax_zone.php', 'edit_tax_zone.php') as $file) {
		$source = rate_boxes_store_typed_test_source($file);
		pg_assert_contains("\$tax_rate = parse_tax_rate(\$_POST['tax_rate'] ?? '');", $source, $file . ' reads the rate through parse_tax_rate()');
		pg_assert_contains("output_error(lang('Enter a tax rate between 0 and 100.')", $source, $file . ' refuses a blank or unreadable rate');
		rate_boxes_store_typed_test_lacks("(string) (\$_POST['tax_rate'] ?? '')", $source, $file . ' no longer writes the raw text');
	}

	$edit = rate_boxes_store_typed_test_source('edit_tax_zone.php');
	pg_assert_true(
		strpos($edit, 'parse_tax_rate(') < strpos($edit, 'DELETE FROM tax_zones_countries_xref'),
		'the edit save refuses the rate before it drops the zone\'s countries');
}

function test_rate_boxes_commission_rate_holds_decimal_5_2()
{
	$source = rate_boxes_store_typed_test_source('includes/forms/contact_form.php');
	pg_assert_contains('name="affiliate_commission_rate" id="affiliate_commission_rate" class="form-ghost" size="6" maxlength="6" inputmode="decimal"', $source, 'the box holds 999.99');
	rate_boxes_store_typed_test_lacks('maxlength="3"', $source, 'the old three characters are gone');
	pg_assert_same('12.50', pg_contact_form_commission_rate_value('12.50'), 'a stored 12.50 is shown whole');
}

function test_rate_boxes_reward_points_cannot_go_negative()
{
	foreach (array('add_user.php', 'edit_user.php', 'import_users.php') as $file) {
		$source = rate_boxes_store_typed_test_source($file);
		pg_assert_contains('type="number" min="0" max="4294967295" step="1" class="form-control" id="reward_points" name="reward_points"', $source, $file . ' bounds the reward points to int(10) unsigned');
		rate_boxes_store_typed_test_lacks('name="reward_points" maxlength="9"', $source, $file . ' drops the maxlength a number box ignores');
	}
}
