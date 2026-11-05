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
	pg_assert_contains('data-inputmask-alias="integer"', $card, 'the quantity takes whole numbers only');
	pg_assert_contains('data-inputmask-allowminus="false"', $card, 'and no minus');
	pg_assert_false(strpos($card, 'data-inputmask-alias="decimal"') !== false, 'no decimal mask on the quantity');
	pg_assert_contains('rows="5"', $card, 'the notes open five lines high');
	pg_assert_false(strpos($card, 'cols=""') !== false, 'no empty cols');
	pg_assert_false(strpos($card, 'id="gift_card_code"') !== false, 'a card being created has no code yet');
}

/**
 * The edit screen names the card it changes (the code was printed only in
 * the log after the screen moved to the page shell).
 */
function test_gift_card_form_edit_shows_the_code()
{
	$card = pg_test_gift_card_form_card('edit', array('symbol' => '$', 'scripts' => '', 'original_amount' => '$5.00', 'code' => '4059-4366-4285-0475', 'status_class' => 'status_disabled fg-danger', 'status_word' => '<div class="small fg-secondary">Expired</div>'));

	pg_assert_contains('fs-xl fw-bold status_disabled fg-danger" id="gift_card_code">4059-4366-4285-0475</span>', $card, 'the code in the state colour');
	pg_assert_contains('>Expired</div>', $card, 'the state word');

	$edit = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_gift_card.php');
	pg_assert_contains("'code' => \$output_code", $edit, 'the screen passes the code');
	pg_assert_contains("array('label' => \$output_code)", $edit, 'the breadcrumb names the card');
	pg_assert_false(strpos($edit, 'chart-buttons justify-content-end d-flex flex-wrap"></div>') !== false, 'no empty header strip over the redemption history');
}

/**
 * The whole value must be a date: '5/5/20271' used to pass validate_date()
 * and be stored as 0000-00-00 (never expires), while '15.12.2027' failed.
 */
function test_gift_card_form_expiration()
{
	if (!defined('DATE_FORMAT')) {
		define('DATE_FORMAT', 'day_month');
	}

	pg_assert_same('0000-00-00', pg_gift_card_form_expiration(''), 'blank is no expiration');
	pg_assert_same('0000-00-00', pg_gift_card_form_expiration('  '), 'spaces are blank');
	pg_assert_same('2027-12-15', pg_gift_card_form_expiration('2027-12-15'), 'ISO');
	pg_assert_same('', pg_gift_card_form_expiration('5/5/20271'), 'a five-digit year');
	pg_assert_same('', pg_gift_card_form_expiration('1/1/2027xx'), 'letters after the year');
	pg_assert_same('', pg_gift_card_form_expiration('31/31/2027'), 'no such day');
	pg_assert_same('', pg_gift_card_form_expiration(array('1')), 'an array');

	if (DATE_FORMAT == 'day_month') {
		pg_assert_same('2027-12-15', pg_gift_card_form_expiration('15.12.2027'), 'the Turkish dotted date');
		pg_assert_same('2027-12-15', pg_gift_card_form_expiration('15/12/2027'), 'day first with slashes');
	} else {
		pg_assert_same('2027-12-15', pg_gift_card_form_expiration('12.15.2027'), 'month first with dots');
		pg_assert_same('2027-12-15', pg_gift_card_form_expiration('12/15/2027'), 'month first with slashes');
	}

	foreach (array('add_gift_card.php' => 'e(pg_gift_card_form_expiration($expiration_date))', 'edit_gift_card.php' => "'expiration_date' => pg_gift_card_form_expiration(\$expiration_date)") as $file => $stores) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		pg_assert_contains($stores, $source, $file . ' stores the checked date');
		pg_assert_false(strpos($source, "prepare_form_data_for_input(\$expiration_date, 'date')") !== false, $file . ' no longer pads an unchecked date');
	}
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
	$form = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/gift_card_form.php');
	pg_assert_contains("'balance_was' => is_numeric(\$balance_was) ? (int) \$balance_was : null", $edit, 'the screen saves over the balance it opened with');
	pg_assert_contains('" AND balance = ?"', $form, 'a changed balance is written only over that one (pg_gift_card_form_write())');
	pg_assert_contains("\$liveform->assign_field_value('balance_was', (string) \$written['balance']);", $edit, 'a stale save takes the current balance');
	pg_assert_contains("output_error(lang('Record not found.')", $edit, 'the not-found guard');
}

/**
 * The save bar's icons are hidden from screen readers: the Material Icons
 * ligatures were read out, naming the buttons "saveKaydet" and "deleteSil".
 */
function test_gift_card_form_edit_buttons_name_only_their_label()
{
	$edit = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_gift_card.php');

	pg_assert_contains('<i class="bi bi-floppy me-3" aria-hidden="true"></i><span class="pg-btn-label">', $edit, 'the save icon');
	pg_assert_contains('<i class="bi bi-trash me-3" aria-hidden="true"></i><span class="pg-btn-label">', $edit, 'the delete icon');
	pg_assert_false(strpos($edit, 'material-icons me-3') !== false, 'no ligature icon in the save bar');
}

/**
 * The quantity of a create is a whole number of cards: a fraction used to
 * pass the "above 0" check and create the whole part only (0.5 none at all)
 * while the screen said the cards were created.
 */
function test_gift_card_form_quantity_is_a_whole_number()
{
	pg_assert_same(1, pg_gift_card_form_quantity('1'), 'one card');
	pg_assert_same(25, pg_gift_card_form_quantity(' 25 '), 'spaces around');
	pg_assert_same(1000, pg_gift_card_form_quantity('1000'), 'the limit');
	pg_assert_same(false, pg_gift_card_form_quantity('1,5'), 'a Turkish decimal comma is no fifteen');
	pg_assert_same(false, pg_gift_card_form_quantity('1,000'), 'no separator is dropped');
	pg_assert_same(false, pg_gift_card_form_quantity('0'), 'zero');
	pg_assert_same(false, pg_gift_card_form_quantity('0.5'), 'a half creates no card');
	pg_assert_same(false, pg_gift_card_form_quantity('2.5'), 'a fraction');
	pg_assert_same(false, pg_gift_card_form_quantity('-3'), 'negative');
	pg_assert_same(false, pg_gift_card_form_quantity('1e3'), 'exponent');
	pg_assert_same(false, pg_gift_card_form_quantity(''), 'empty');
	pg_assert_same(false, pg_gift_card_form_quantity(array('2')), 'an array');
}

/**
 * gift_cards.amount and balance are INT(10) UNSIGNED minor units: an amount
 * above 42,949,672.95 was clamped to that ceiling by MySQL (or the write
 * failed in strict mode) while the screen reported it saved.
 */
function test_gift_card_form_amount_fits_the_column()
{
	pg_assert_true(pg_gift_card_form_amount_fits('0'), 'zero');
	pg_assert_true(pg_gift_card_form_amount_fits('12.50'), 'an ordinary amount');
	pg_assert_true(pg_gift_card_form_amount_fits('42949672.95'), 'the ceiling itself');
	pg_assert_false(pg_gift_card_form_amount_fits('42949672.96'), 'one minor unit above');
	pg_assert_false(pg_gift_card_form_amount_fits('999999999.99'), 'what the 12-character field takes');
	pg_assert_same(4294967295, PG_GIFT_CARD_AMOUNT_MAX, 'the column ceiling in minor units');
}

function test_gift_card_form_screens_use_the_edit_screen_layout()
{
	$card = pg_test_gift_card_form_card('add', array('symbol' => '$', 'scripts' => '', 'quantity_max' => '1000'));
	pg_assert_true(strpos(ltrim($card), '<div class="card mb-7">') === 0, 'the helper returns the card alone, no row');

	foreach (array('add_gift_card.php', 'edit_gift_card.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		pg_assert_contains('<div class="col-12 lg:col-8 2xl:col-9 order-1">', $source, $file . ' puts the card in the form column');
		pg_assert_contains('<div class="col-12 order-3 position-sticky" style="bottom:0; z-index:3;">', $source, $file . ' puts its save bar in its own sticky column');
		pg_assert_false(strpos($source, 'card my-7') !== false, $file . ' stacks no full-width card');
	}

	$edit = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_gift_card.php');
	pg_assert_contains('<div class="col-12 lg:col-4 2xl:col-3 order-2">', $edit, 'the order info sits in the status column');
	pg_assert_contains('<div class="position-sticky" style="top:3.3rem;">', $edit, 'the status column box sticks below the top bar');
}

/**
 * The maxlength of the input a card draws with the given name.
 */
function pg_test_gift_card_form_maxlength($card, $name)
{
	if (preg_match('/<input[^>]*\bname="' . preg_quote($name, '/') . '"[^>]*>/', $card, $tag) !== 1) {
		return null;
	}

	return (preg_match('/\bmaxlength="([0-9]*)"/', $tag[0], $length) === 1) ? $length[1] : '';
}

/**
 * The money mask counts its grouping and decimals against maxlength: at 12
 * the amount and balance boxes stopped at 9.999.999,99, so 40000000 kept
 * 4.000.000,00 and the card was made ten times smaller without a word.
 * 13 holds 42.949.672,95, the column top. The quantity box kept 4 digits
 * in the limit=false mode too, cutting 12000 cards to 1200.
 */
function test_gift_card_form_boxes_hold_their_top()
{
	$add = pg_test_gift_card_form_card('add', array('symbol' => '$', 'scripts' => '', 'quantity_max' => '1000'));
	$edit = pg_test_gift_card_form_card('edit', array('symbol' => '$', 'scripts' => '', 'original_amount' => '$5.00'));
	$unlimited = pg_test_gift_card_form_card('add', array('symbol' => '$', 'scripts' => '', 'quantity_max' => ''));

	pg_assert_same('13', pg_test_gift_card_form_maxlength($add, 'amount'), 'the amount box holds 42.949.672,95');
	pg_assert_same('13', pg_test_gift_card_form_maxlength($edit, 'balance'), 'the balance box holds 42.949.672,95');
	pg_assert_same(13, strlen('42.949.672,95'), 'the column top with grouping and decimals');
	pg_assert_same('4', pg_test_gift_card_form_maxlength($add, 'quantity'), 'the 1,000 limit takes four digits');
	pg_assert_same('9', pg_test_gift_card_form_maxlength($unlimited, 'quantity'), 'no limit: as many digits as the server reads');
	pg_assert_same(123456789, pg_gift_card_form_quantity('123456789'), 'the server reads nine digits');
}

// Store credit (a card bound to a customer) is voided on the customer's
// screen, never deleted from the gift card screen; an ordinary card is.
function test_gift_card_form_delete_refusal()
{
	pg_assert_same('', pg_gift_card_form_delete_refusal(array('credit_user_id' => 0)), 'an ordinary gift card is deleted');
	pg_assert_same('', pg_gift_card_form_delete_refusal(array()), 'before the store credit columns exist');
	pg_assert_same(lang('Store credit is not deleted: void it on the customer\'s Store credit card.'),
		pg_gift_card_form_delete_refusal(array('credit_user_id' => 12)), 'store credit is refused');
	pg_assert_same(lang('Store credit is not deleted: void it on the customer\'s Store credit card.'),
		pg_gift_card_form_delete_refusal(array('credit_user_id' => '12')), 'a numeric string from the row');
}
