<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the card data an order keeps when no payment gateway takes the
 * card (includes/fn/ecommerce.php): the security code is never stored, the
 * number is masked once the order is processed, and it is never kept in clear.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A row needs clearing while it holds a number not yet masked or any security code.
function test_card_data_needs_clearing()
{
	pg_assert_same(false, pg_card_data_needs_clearing('', ''), 'nothing stored');
	pg_assert_same(false, pg_card_data_needs_clearing('************1111', ''), 'masked number');
	pg_assert_same(true, pg_card_data_needs_clearing('4111111111111111', ''), 'plain number');
	pg_assert_same(true, pg_card_data_needs_clearing('pgc1:abc', ''), 'encrypted number');
	pg_assert_same(true, pg_card_data_needs_clearing('', '123'), 'security code');
	pg_assert_same(true, pg_card_data_needs_clearing('************1111', '***'), 'masked security code is still a stored code');
	pg_assert_same(false, pg_card_data_needs_clearing(null, null), 'NULL columns');
}

// The number kept after clearing: the last four digits behind asterisks, '' when it cannot be read.
function test_card_number_cleared_masks_or_empties()
{
	pg_assert_same('', pg_card_number_cleared('', 'key'), 'empty');
	pg_assert_same('************1111', pg_card_number_cleared('************1111', 'key'), 'already masked');
	pg_assert_same('************1111', pg_card_number_cleared('4111111111111111', 'key'), 'plain 16 digits');
	pg_assert_same('***************1111', pg_card_number_cleared('4111111111111111111', ''), 'plain 19 digits, no key');
	pg_assert_same('', pg_card_number_cleared('pgc1:not-base64!', 'key'), 'unreadable cipher');
	pg_assert_same('', pg_card_number_cleared('pgc1:abc', ''), 'cipher without a key');
}

// An encrypted number is decrypted with the key and masked; a wrong key leaves ''.
function test_card_number_cleared_decrypts()
{
	if (!function_exists('openssl_encrypt')) {
		return;
	}

	$stored = encrypt_credit_card_number('5555555555554444', 'test-key');

	pg_assert_same('************4444', pg_card_number_cleared($stored, 'test-key'), 'right key');
	pg_assert_same('', pg_card_number_cleared($stored, 'other-key'), 'wrong key');
}

// Processed orders (out of complete) lose the number; every stored security code goes.
function test_card_data_sweep_where()
{
	$where = pg_order_card_data_sweep_where();

	pg_assert_true(strpos($where, "card_verification_number <> ''") !== false, 'any security code');
	pg_assert_true(strpos($where, "status NOT IN ('incomplete', 'complete')") !== false, 'processed orders only for the number');
	pg_assert_true(strpos($where, "card_number NOT LIKE '*%'") !== false, 'masked numbers left alone');
}

// No security code value reaches an INSERT or UPDATE: the checkout writes '' and
// no screen writes the posted or stored code back.
function test_no_security_code_is_written()
{
	$submit = (string) file_get_contents(PG_FUNCTIONS_DIR . '/submit_order.php');

	pg_assert_true(preg_match('/\$card_values = array\((.*?)\);/s', $submit, $match) === 1, 'card values block found');
	pg_assert_true(strpos($match[1], "'card_verification_number' => ''") !== false, 'checkout stores an empty code');
	pg_assert_true(strpos($submit, 'protect_card_verification_number(') === false, 'no masked code stored either');

	foreach (glob(PG_FUNCTIONS_DIR . '/{*.php,includes/*.php,includes/*/*.php,includes/*/*/*.php}', GLOB_BRACE) as $path) {
		$source = (string) file_get_contents($path);

		if (strpos($source, 'card_verification_number') === false) {
			continue;
		}

		$name = substr($path, strlen(PG_FUNCTIONS_DIR) + 1);

		// A SQL write naming the column may only set it to ''.
		pg_assert_same(0, preg_match("/(?<![\$\w])card_verification_number\s*=\s*(?!'')(?!\s*\?)['\"]?\s*[\.\$]/", $source), $name . ': code written from a value');
		pg_assert_same(0, preg_match('/(?<![\$\w])card_verification_number\s*=\s*\?/', $source), $name . ': code written through a placeholder');
	}

	$edit = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_orders.php');
	pg_assert_true(strpos($edit, 'pg_order_card_data_clear(') !== false, 'Remove Card Data goes through the helper');
}

// Without encryption the checkout refuses the card instead of storing the number in clear.
function test_checkout_refuses_card_without_encryption()
{
	$submit = (string) file_get_contents(PG_FUNCTIONS_DIR . '/submit_order.php');

	pg_assert_true(strpos($submit, '$processed_card_number = $card_number;') === false, 'no plain number stored');
	pg_assert_true(preg_match("/ECOMMERCE_PAYMENT_GATEWAY == ''\)\s*&&\s*!credit_card_encryption_is_available\(\)/", $submit) === 1, 'refusal at validation');
}
