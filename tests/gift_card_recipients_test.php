<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the gift card recipient check at the order preview: every card a
 * gift card line issues needs its recipient row with a valid e-mail address
 * before the order is placed, or the card is issued with no recipient and,
 * with validity days set, an end date in 1971.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (is_file(PG_FUNCTIONS_DIR . '/includes/fn/gift_card_recipients.php')) {
	require_once(PG_FUNCTIONS_DIR . '/includes/fn/gift_card_recipients.php');
}

function test_gift_card_recipients_missing_counts_units_without_a_recipient()
{
	pg_assert_true(function_exists('pg_gift_card_recipients_missing'), 'the check has a pure helper');

	if (!function_exists('pg_gift_card_recipients_missing')) {
		return;
	}

	$row = function ($item, $number, $email) {
		return array('order_item_id' => $item, 'quantity_number' => $number, 'recipient_email_address' => $email);
	};

	pg_assert_same(0, pg_gift_card_recipients_missing(array(), array()), 'no gift card line: nothing missing');

	pg_assert_same(0, pg_gift_card_recipients_missing(
		array(7 => 2),
		array($row(7, 1, 'a@example.com'), $row(7, 2, 'b@example.com'))), 'every unit has its recipient');

	// The designer cart saves only the units drawn before the quantity was
	// raised in the same post: units 2 and 3 have no row.
	pg_assert_same(2, pg_gift_card_recipients_missing(
		array(7 => 3),
		array($row(7, 1, 'a@example.com'))), 'units without a row are missing');

	pg_assert_same(1, pg_gift_card_recipients_missing(
		array(7 => 1),
		array($row(7, 1, ''))), 'an empty recipient is missing');

	pg_assert_same(1, pg_gift_card_recipients_missing(
		array(7 => 1),
		array($row(7, 1, 'not an address'))), 'an invalid recipient is missing');

	pg_assert_same(1, pg_gift_card_recipients_missing(
		array(7 => 1, 8 => 1),
		array($row(7, 1, 'a@example.com'), $row(9, 1, 'c@example.com'))), 'a row of another line does not count');

	pg_assert_same(0, pg_gift_card_recipients_missing(
		array(7 => 1),
		array($row(7, 1, 'a@example.com'), $row(7, 2, ''))), 'a row past the line quantity is not asked for');

	pg_assert_same(100, pg_gift_card_recipients_missing(array(7 => 150), array()), 'only the cards the line issues are counted');
}

function test_gift_card_recipients_checked_before_the_order_is_submitted()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/order_preview.php');
	$check = strpos($source, 'pg_order_gift_card_recipients_missing(');
	$submit = strpos($source, "require(dirname(__FILE__) . '/submit_order.php');");

	pg_assert_true($check !== false, 'order_preview.php checks the recipients');
	pg_assert_true(($check !== false) && ($submit !== false) && ($check < $submit), 'the check runs before submit_order()');
}
