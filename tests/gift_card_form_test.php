<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the gift card form shared by add_gift_card.php and
 * edit_gift_card.php (includes/forms/gift_card_form.php): the expiration
 * date a new card opens with, the card in both modes and the edit screen's
 * stale-save guard staying in the screen.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!class_exists('liveform', false)) {
	require_once(PG_FUNCTIONS_DIR . '/liveform.class.php');
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/gift_card_form.php');

function test_gift_card_form_default_expiration()
{
	$now = mktime(12, 0, 0, 3, 5, 2026);

	pg_assert_same('', pg_gift_card_form_default_expiration('', 'month_day', $now), 'no validity days, no date');
	pg_assert_same('', pg_gift_card_form_default_expiration('0', 'day_month', $now), 'zero validity days, no date');
	pg_assert_same('4/4/2026', pg_gift_card_form_default_expiration('30', 'month_day', $now), 'month first');
	pg_assert_same('15/3/2026', pg_gift_card_form_default_expiration(10, 'day_month', $now), 'day first');
}

/**
 * The card in a mode, drawn with an empty session.
 */
function pg_test_gift_card_form_card($mode, $options)
{
	$saved = isset($_SESSION) ? $_SESSION : null;
	$_SESSION = array();

	$card = pg_gift_card_form_card(new liveform('gift_card_form_test'), $mode, $options);

	if ($saved === null) {
		unset($_SESSION);
	} else {
		$_SESSION = $saved;
	}

	return $card;
}

function test_gift_card_form_card_add_mode()
{
	$card = pg_test_gift_card_form_card('add', array('symbol' => '$', 'scripts' => '<script>/*s*/</script>', 'quantity_max' => '1000'));

	pg_assert_contains('<label for="amount" class="form-label">', $card, 'the amount label');
	pg_assert_contains('id="amount"', $card, 'the amount input');
	pg_assert_contains('class="form-ghost text-end', $card, 'the amount is a ghost input inside the adorn');
	pg_assert_contains('data-inputmask-alias="currency"', $card, 'the amount carries the money mask');
	pg_assert_contains('aria-hidden="true">$</span>', $card, 'the currency symbol');
	pg_assert_contains('name="quantity"', $card, 'the create card asks for a quantity');
	pg_assert_contains('max="1000"', $card, 'the quantity limit');
	pg_assert_contains('<script>/*s*/</script>', $card, 'the date scripts');
	pg_assert_contains('name="expiration_date"', $card, 'the expiration date');
	pg_assert_contains('name="notes"', $card, 'the notes');
	pg_assert_false(strpos($card, 'name="balance"') !== false, 'no balance on create');
}

function test_gift_card_form_card_edit_mode()
{
	$card = pg_test_gift_card_form_card('edit', array('symbol' => '$', 'scripts' => '', 'original_amount' => '$50.00'));

	pg_assert_contains('<label for="balance" class="form-label">', $card, 'the balance label');
	pg_assert_contains('id="balance"', $card, 'the balance input');
	pg_assert_contains('data-inputmask-alias="currency"', $card, 'the balance carries the money mask');
	pg_assert_contains('$50.00', $card, 'the original amount under the balance');
	pg_assert_contains('name="expiration_date"', $card, 'the expiration date');
	pg_assert_contains('name="notes"', $card, 'the notes');
	pg_assert_false(strpos($card, 'name="quantity"') !== false, 'no quantity on edit');
	pg_assert_false(strpos($card, 'name="amount"') !== false, 'no amount on edit');
}

function test_gift_card_form_screens_use_shared_form()
{
	foreach (array('add_gift_card.php' => "'add'", 'edit_gift_card.php' => "'edit'") as $file => $mode) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_contains('includes/forms/gift_card_form.php', $source, $file . ' loads the form');
		pg_assert_contains('pg_gift_card_form_card($liveform, ' . $mode, $source, $file . ' prints the shared card');
		pg_assert_contains('pg_gift_card_form_check($liveform, ' . $mode . ')', $source, $file . ' checks the posted values the shared way');
		pg_assert_contains('validate_token_field()', $source, $file . ' checks the token');
		pg_assert_contains('validate_ecommerce_access($user)', $source, $file . ' keeps the commerce gate');
		pg_assert_false(strpos($source, "'class' => 'form-ghost text-end'") !== false, $file . ' keeps no copy of the card');
	}

	$add = (string) file_get_contents(PG_FUNCTIONS_DIR . '/add_gift_card.php');
	pg_assert_contains("(\$quantity > 1000)", $add, 'the create screen keeps the 1,000 limit');
	pg_assert_contains("get('limit') != 'false'", $add, 'and its limit=false bypass');
}

function test_gift_card_form_edit_keeps_the_balance_guard()
{
	$edit = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_gift_card.php');

	pg_assert_contains("'type' => 'hidden', 'name' => 'balance_was'", $edit, 'the edit screen draws balance_was');
	pg_assert_contains("AND balance = '\" . (int) \$balance_was . \"'\")", $edit, 'a changed balance is written only over the one the screen opened with');
	pg_assert_contains("\$liveform->assign_field_value('balance_was', (string) \$now);", $edit, 'a stale save takes the current balance');
	pg_assert_contains("output_error(lang('Record not found.')", $edit, 'the not-found guard');
}
