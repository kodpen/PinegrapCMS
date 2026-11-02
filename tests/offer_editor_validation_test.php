<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the offer editor's save checks: a percentage with a fraction is
 * refused rather than rounded up, a ladder rung is never dropped (two rungs
 * at one amount and a rung out of range are refused), amounts and counts
 * above what the columns hold are refused, and a message the server sent
 * for a row neither blocks the next save nor outlives the row's edit.
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

function offer_editor_validation_js()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/offer_editor.js');
}

function test_offer_percent_takes_whole_numbers_only()
{
	pg_assert_same(12, _pg_offer_percent('12'));
	pg_assert_same(12, _pg_offer_percent(12));
	pg_assert_same(12, _pg_offer_percent(12.0), 'a whole float from JSON');
	pg_assert_same(12, _pg_offer_percent('12,0'));
	pg_assert_same(0, _pg_offer_percent(''));
	pg_assert_same(-5, _pg_offer_percent('-5'));
	pg_assert_same(null, _pg_offer_percent('12,5'), 'a fraction is refused, not rounded to 13');
	pg_assert_same(null, _pg_offer_percent(12.5));
	pg_assert_same(null, _pg_offer_percent('abc'));
	pg_assert_same(1000, _pg_offer_percent('99999999999999999999'), 'far-off values are held, then refused as over 100');
}

function test_offer_tiers_keep_every_rung_in_order()
{
	list($tiers, $error) = _pg_offer_clean_tiers(array(
		array('min' => 25000, 'percent' => '10'),
		array('min' => 10000, 'percent' => 5)));
	pg_assert_same('', $error);
	pg_assert_same(array(array('min' => 10000, 'percent' => 5), array('min' => 25000, 'percent' => 10)), $tiers);
}

function test_offer_tiers_refuse_two_rungs_at_one_amount()
{
	list($tiers, $error) = _pg_offer_clean_tiers(array(
		array('min' => '100', 'percent' => '5'),
		array('min' => '100', 'percent' => '13')));
	pg_assert_same(lang('Two tiers cannot start at the same amount.'), $error);
	pg_assert_same(2, count($tiers), 'neither rung is dropped');
}

function test_offer_tiers_refuse_a_bad_percentage()
{
	list(, $error) = _pg_offer_clean_tiers(array(array('min' => '100', 'percent' => '0')));
	pg_assert_same(lang('Please enter a percentage between 1 and 100 for every tier.'), $error);
	list(, $error) = _pg_offer_clean_tiers(array(array('min' => '100', 'percent' => '101')));
	pg_assert_same(lang('Please enter a percentage between 1 and 100 for every tier.'), $error);
	list(, $error) = _pg_offer_clean_tiers(array(array('min' => '100', 'percent' => '12,5')));
	pg_assert_same(lang('Please enter a whole-number percentage.'), $error);
}

function test_offer_tiers_refuse_an_amount_over_the_bound()
{
	list(, $error) = _pg_offer_clean_tiers(array(array('min' => PG_OFFER_MAX_NUMBER + 1, 'percent' => 5)));
	pg_assert_same(_pg_offer_amount_max_error(), $error);
	list(, $error) = _pg_offer_clean_tiers(array(array('min' => PG_OFFER_MAX_NUMBER, 'percent' => 5)));
	pg_assert_same('', $error, 'the bound itself is allowed');
}

function test_offer_validate_checks_upper_bounds()
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_offer_f.php');
	pg_assert_same(2147483647, PG_OFFER_MAX_NUMBER);
	pg_assert_contains("\$errors[\$key . '.amount'] = _pg_offer_amount_max_error();", $src, 'subtotal');
	pg_assert_contains("\$errors[\$key . '.total_limit'] = _pg_offer_number_max_error();", $src, 'total usage limit');
	pg_assert_contains("\$errors[\$key . '.customer_limit'] = _pg_offer_number_max_error();", $src, 'per-customer limit');
	pg_assert_contains("\$errors[\$key . '.value'] = _pg_offer_amount_max_error();", $src, 'an amount result');
	pg_assert_contains("\$errors[\$key . '.value'] = lang('Please enter a whole-number percentage.');", $src, 'a fractional percentage result');
	pg_assert_contains("\$errors[\$key . '.quantity'] = _pg_offer_number_max_error();", $src, 'gift quantity');
	pg_assert_same(false, strpos($src, "(int) round((float) str_replace(',', '.'"), 'no percentage is rounded on save');
}

function test_offer_editor_server_errors_do_not_block_save()
{
	$js = offer_editor_validation_js();
	pg_assert_contains("S.conditions.forEach(function (c) { if (conditionClientError(c)) { n++; } });", $js, 'the count reads only client checks');
	pg_assert_contains("S.actions.forEach(function (a) { if (actionClientError(a)) { n++; } });", $js);
	pg_assert_contains("return conditionClientError(c) || serverRowError('conditions', index);", $js, 'the server message is still shown');
	pg_assert_contains("return actionClientError(a) || serverRowError('actions', index);", $js);
}

function test_offer_editor_row_edit_clears_its_server_error()
{
	$js = offer_editor_validation_js();
	pg_assert_contains("if (/^cond-/.test(on)) { dropRowErrors('conditions', index, on === 'cond-remove'); }", $js);
	pg_assert_contains("if (/^(act|tier)-/.test(on)) { dropRowErrors('actions', index, on === 'act-remove'); }", $js);
	pg_assert_contains("if (removed && n > index) { n--; }", $js, 'later rows keep their own messages after a removal');
	pg_assert_same(3, substr_count($js, 'rowEdited(on, i);'), 'click, change and input handlers');
}

function test_offer_editor_checks_whole_percent_duplicates_and_bounds()
{
	$js = offer_editor_validation_js();
	pg_assert_contains("return isNaN(n) ? 0 : n;", $js, 'a typed fraction is kept, not rounded');
	pg_assert_contains("if (seen[min]) { return L.tierDuplicate; }", $js);
	pg_assert_contains("if (a.unit === 'percent' && !isWhole(a.value)) { return L.percentWhole; }", $js);
	pg_assert_contains("if (a.value < 0) { return L.enterValue; }", $js, 'a negative gift discount is caught before save');
	pg_assert_contains("var MAX_NUMBER = 2147483647;", $js);
	pg_assert_contains("inputmode=\"' + (a.unit === 'amount' ? 'decimal' : 'numeric') + '\"", $js);
}
