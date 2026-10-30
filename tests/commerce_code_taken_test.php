<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the codes another record already holds: an offer code a key code
 * uses (edit_offer_f.php), a country code another country has
 * (includes/forms/country_form.php) and a state code another state of the
 * same country has (includes/forms/state_form.php). Each check runs on a new
 * or changed, non-empty code only, so an unrelated edit of an old clash
 * still saves.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/edit_offer_f.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/country_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/state_form.php');

// A lookup stub that records its calls and answers from a fixed list.
function commerce_code_taken_test_stub(array $taken, array &$calls)
{
	return function () use ($taken, &$calls) {
		$args = func_get_args();
		$calls[] = $args;
		return in_array($args[0], $taken, true);
	};
}

function test_offer_code_a_key_code_holds_is_refused()
{
	$calls = array();
	$taken = commerce_code_taken_test_stub(array('SPRING'), $calls);

	pg_assert_same(
		lang('A key code already uses this code. Checkout would apply the offer and the key code would stop working.'),
		_pg_offer_code_key_code_error('SPRING', null, $taken),
		'a new offer with a key code\'s code is refused');
	pg_assert_same(
		lang('A key code already uses this code. Checkout would apply the offer and the key code would stop working.'),
		_pg_offer_code_key_code_error('SPRING', 'WINTER', $taken),
		'an offer changed to a key code\'s code is refused');
	pg_assert_same('', _pg_offer_code_key_code_error('SUMMER', null, $taken), 'a free code saves');

	$calls = array();
	pg_assert_same('', _pg_offer_code_key_code_error('SPRING', 'SPRING', $taken), 'an unchanged code saves');
	pg_assert_same('', _pg_offer_code_key_code_error('', null, $taken), 'an empty code is left to the required check');
	pg_assert_same(array(), $calls, 'no lookup for an unchanged or empty code');
}

function test_offer_save_checks_key_codes_before_writing()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_offer_f.php');
	$check = strpos($source, "_pg_offer_code_key_code_error(\$checked['clean']['offer']['code']");
	$save = strpos($source, '$offer_id = _pg_offer_save($checked[\'clean\'], $user);');

	pg_assert_true($check !== false, 'offer_save asks for the key code clash');
	pg_assert_true($save !== false && $check < $save, 'the clash is checked before the offer is written');
	pg_assert_contains("SELECT id FROM key_codes WHERE code = ? LIMIT 1", $source, 'the key code lookup binds the code');
}

function test_country_code_another_country_has_is_refused()
{
	$calls = array();
	$taken = commerce_code_taken_test_stub(array('DE'), $calls);
	$message = lang('The code that you entered is already in use by another country, please enter a different code.');

	pg_assert_same($message, pg_country_form_code_error('DE', null, null, $taken), 'create with a taken code');
	pg_assert_same($message, pg_country_form_code_error('DE', 'AT', 4, $taken), 'edit changed to a taken code');
	pg_assert_same(array('DE', 4), $calls[1], 'edit leaves its own country out');
	pg_assert_same('', pg_country_form_code_error('FR', null, null, $taken), 'a free code saves');

	$calls = array();
	pg_assert_same('', pg_country_form_code_error('DE', 'DE', 4, $taken), 'an unchanged code saves');
	pg_assert_same('', pg_country_form_code_error('  ', null, null, $taken), 'an empty code saves (the field is optional)');
	pg_assert_same(array(), $calls, 'no lookup for an unchanged or empty code');
}

function test_country_card_prints_the_code_error_next_to_the_field()
{
	$card = pg_country_form_card(pg_country_form_values(array('code' => 'DE')), array('code' => 'Taken <b>'));

	pg_assert_contains('class="form-control is-invalid" maxlength="50" value="DE" aria-invalid="true" aria-describedby="code-error"', $card, 'the code field is marked');
	pg_assert_contains('<div id="code-error" class="invalid-feedback d-block">Taken &lt;b&gt;</div>', $card, 'the message sits under the field, escaped');
	pg_assert_false(strpos(pg_country_form_card(null), 'is-invalid') !== false, 'no mark without an error');
}

function test_state_code_another_state_of_the_country_has_is_refused()
{
	$calls = array();
	$taken = commerce_code_taken_test_stub(array('CA'), $calls);
	$message = lang('The code that you entered is already in use by another state in this country, please enter a different code.');

	pg_assert_same($message, pg_state_form_code_error(array('code' => 'CA', 'country_id' => '1'), null, null, $taken), 'create with a taken code');
	pg_assert_same(array('CA', 1, null), $calls[0], 'the lookup is scoped to the country');
	pg_assert_same($message, pg_state_form_code_error(array('code' => 'CA', 'country_id' => '1'), array('code' => 'NV', 'country_id' => '1'), 9, $taken), 'edit changed to a taken code');
	pg_assert_same($message, pg_state_form_code_error(array('code' => 'CA', 'country_id' => '1'), array('code' => 'CA', 'country_id' => '2'), 9, $taken), 'edit moved to a country where the code is taken');
	pg_assert_same(array('CA', 1, 9), $calls[2], 'edit leaves its own state out');

	$calls = array();
	pg_assert_same('', pg_state_form_code_error(array('code' => 'CA', 'country_id' => '1'), array('code' => 'CA', 'country_id' => '1'), 9, $taken), 'an unchanged code and country save');
	pg_assert_same('', pg_state_form_code_error(array('code' => '', 'country_id' => '1'), null, null, $taken), 'an empty code saves');
	pg_assert_same(array(), $calls, 'no lookup for an unchanged or empty code');
}

// The state card fills its country list from the database (select_country()),
// so its code field markup is read from the source.
function test_state_card_prints_the_code_error_next_to_the_field()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/state_form.php');

	pg_assert_contains('class="form-control\' . $code_class . \'" maxlength="50"\' . $code_aria . \' />\' . $code_feedback', $source, 'the code field is marked and followed by its message');
	pg_assert_contains('<div id="code-error" class="invalid-feedback d-block">\' . h($code_error) . \'</div>', $source, 'the message is escaped');
}

function test_country_and_state_screens_check_the_code_before_writing()
{
	$pins = array(
		'add_country.php' => array('pg_country_form_code_error($values[\'code\'], null, null)', 'INSERT INTO countries'),
		'edit_country.php' => array('pg_country_form_code_error($values[\'code\'], (string) $row[\'code\'], $id)', 'UPDATE countries SET'),
		'add_state.php' => array('pg_state_form_code_error($values, null, null)', 'INSERT INTO states'),
		'edit_state.php' => array('pg_state_form_code_error($values, $row, $id)', 'UPDATE states SET'),
	);

	foreach ($pins as $file => $pin) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		$check = strpos($source, $pin[0]);
		$write = strpos($source, $pin[1]);
		pg_assert_true($check !== false, $file . ' checks the code');
		pg_assert_true($write !== false && $check < $write, $file . ' checks before it writes');
	}
}
