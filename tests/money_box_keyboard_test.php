<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the panel's money boxes in includes/forms/: every box drawn with
 * the currency mask carries inputmode="decimal", so a phone keyboard offers
 * the decimal separator. Inputmask only sets that mode itself when the
 * element has no inputmode, so an explicit "numeric" opens a digits-only pad.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_money_boxes_open_the_decimal_keyboard()
{
	$files = glob(dirname(__DIR__) . '/pinegrap/includes/forms/*.php');
	$boxes = 0;

	foreach ($files as $file) {
		foreach (file($file) as $number => $line) {
			if (!preg_match('/pg_money_mask_(html|attributes)\(/', $line)) {
				continue;
			}

			$boxes++;
			$where = basename($file) . ':' . ($number + 1);

			pg_assert_true(
				strpos($line, 'inputmode="decimal"') !== false || preg_match("/'inputmode'\s*=>\s*'decimal'/", $line) === 1,
				$where . ' money box carries inputmode decimal');
		}
	}

	pg_assert_true($boxes >= 10, 'the money boxes of the zone, shipping method, gift card and currency forms are found');
}

function test_integer_boxes_keep_the_numeric_keyboard()
{
	$root = dirname(__DIR__) . '/pinegrap/includes/forms/';
	$shipping = (string) file_get_contents($root . 'shipping_method_form.php');
	$gift = (string) file_get_contents($root . 'gift_card_form.php');

	pg_assert_contains('name="handle_days" id="handle_days" class="form-control" size="7" maxlength="7" inputmode="numeric"', $shipping);
	pg_assert_contains('name="base_transit_days" id="base_transit_days" class="form-control" size="7" maxlength="7" inputmode="numeric"', $shipping);
	pg_assert_contains("'inputmode'=>'numeric',", $gift);
}
