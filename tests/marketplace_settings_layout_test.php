<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Marketplaces screen layout (marketplace_settings.php): the
 * account's work cards in a form column, the selected account's counts,
 * state and actions in a sticky status column written after it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function marketplace_settings_layout_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/marketplace_settings.php');
}

function test_marketplace_settings_has_form_and_status_columns()
{
	$source = marketplace_settings_layout_source();

	$form = strpos($source, '<div class="col-12 lg:col-8 2xl:col-9 order-1">');
	$status = strpos($source, '<div class="col-12 lg:col-4 2xl:col-3 order-2">');

	pg_assert_true($form !== false, 'the form column is there');
	pg_assert_true($status !== false, 'the status column is there');
	pg_assert_true($form < $status, 'form column before status column');
	pg_assert_contains('<div class="position-sticky" style="top:3.3rem;">\' . $mp_status . \'', $source, 'the status column sticks below the top bar and holds the account card');

	foreach (array('id="mp-map"', 'id="mp-orders"', 'id="mp-claims"', 'id="mp-questions"', 'id="mp-queue"') as $card) {
		$at = strpos($source, $card);
		pg_assert_true($at !== false && $at > $form && $at < $status, $card . ' sits in the form column');
	}
}

function test_marketplace_settings_account_card_is_read_in_the_status_column()
{
	$source = marketplace_settings_layout_source();
	$card = strpos($source, 'id="mp-account"');

	pg_assert_true($card !== false, 'the account card is there');
	pg_assert_true(strpos($source, '$mp_status = \'') < $card, 'the account card is buffered for the status column');

	$account = substr($source, $card, (int) strpos($source, '$mp_status .= \'', $card + 1) - $card);
	foreach (array("lang('Matched products')", "lang('Waiting')", "lang('With the marketplace')", "lang('Failed')") as $label) {
		pg_assert_contains($label, $account, $label . ' is shown');
	}
	pg_assert_true(strpos($source, 'class="mp-count"') === false, 'the old count row is gone');
	pg_assert_contains('data-bs-target="#mp_drawer">', $source, 'the Settings button opens the drawer');
	pg_assert_contains('<span class="pg-btn-label">\' . h(lang(\'Test connection\'))', $source, 'the Test connection label is in its span');
}

function test_marketplace_settings_dialog_and_drawer_fields_are_form_fields()
{
	$source = marketplace_settings_layout_source();

	foreach (array('new_provider', 'new_name', 'edit_name', 'edit_shipment_template', 'edit_preparing_day') as $id) {
		pg_assert_true((bool) preg_match('/<div class="form-field[^"]*">\s*<label class="form-label" for="' . $id . '">/', $source), $id . ' sits in a form-field');
	}

	pg_assert_contains('<div class="form-field mb-5">
				<label class="form-label" for="\' . h($id) . \'">', $source, 'a credential field is a form-field');
	pg_assert_true(strpos($source, '<div class="mb-5">') === false, 'no bare label and control block is left');
}

function test_marketplace_settings_drawer_sections_in_order()
{
	$source = marketplace_settings_layout_source();
	$drawer = strpos($source, 'id="mp_drawer"');
	pg_assert_true($drawer !== false, 'the drawer is there');

	$last = $drawer;
	foreach (array('Connection', 'Stock and prices', 'Orders', 'Shipping') as $section) {
		$at = strpos($source, '<legend class="fs-md fw-semibold mb-4">\' . lang(\'' . $section . '\') . \'</legend>', $drawer);
		pg_assert_true($at !== false && $at > $last, $section . ' is a drawer section after the previous one');
		$last = $at;
	}

	pg_assert_true(strpos($source, 'id="edit_status"', $drawer) > strpos($source, "lang('Stock and prices')", $drawer), 'the sending switches sit under Stock and prices');
	pg_assert_true(strpos($source, 'value="delete"', $drawer) > $last, 'Remove marketplace stays last');
}
