<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings > Ecommerce, Store and Payment methods opened: the pane's
 * switch, segmented choice, ticked list and group heads, and the two cards
 * drawn without popovers or plain radios, every field under its old name.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_SETTINGS_ENTRY')) {
	define('PG_SETTINGS_ENTRY', true);
}

if (!defined('PG_SETTINGS_MENU')) {
	define('PG_SETTINGS_MENU', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/settings/registry.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/commerce_settings_fold.php');

// The part of commerce.php from one card's id to the next card or the next
// part's heading comment.
function settings_commerce_sp_test_card($id)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/commerce.php');
	$start = strpos($source, '<div id="' . $id . '" class="pg-set-card">');

	if ($start === false) {
		return '';
	}

	$ends = array(strlen($source));

	foreach (array('class="pg-set-card">', "\n// ── ") as $mark) {
		$end = strpos($source, $mark, $start + 30);

		if ($end !== false) {
			$ends[] = $end;
		}
	}

	return substr($source, $start, min($ends) - $start);
}

function test_settings_commerce_sp_switch()
{
	$html = pg_commerce_settings_switch('ecommerce_tax', true, 'Tax & duty', 'One line <b>');

	pg_assert_contains('class="pg-switch"', $html, 'a pg-switch');
	pg_assert_contains('id="ecommerce_tax" name="ecommerce_tax"', $html, 'id and name kept');
	pg_assert_contains(' checked="checked"', $html, 'on when on');
	pg_assert_contains('aria-describedby="ecommerce_tax_help"', $html, 'the help line describes it');
	pg_assert_contains('>Tax &amp; duty</label>', $html, 'the label escaped');
	pg_assert_contains('id="ecommerce_tax_help">One line &lt;b&gt;</p>', $html, 'one help line, escaped');

	$off = pg_commerce_settings_switch('ups', false, 'UPS', '', ' onclick="x()"');
	pg_assert_false(strpos($off, 'checked') !== false, 'off when off');
	pg_assert_false(strpos($off, 'aria-describedby') !== false, 'no help, no describedby');
	pg_assert_contains(' onclick="x()"', $off, 'extra attributes kept');
}

function test_settings_commerce_sp_group_switch()
{
	$html = pg_commerce_settings_group_switch('ecommerce_offline_payment', true, 'Offline payments');

	pg_assert_contains('class="pg-switch"', $html, 'a pg-switch heads the group');
	pg_assert_contains('class="pg-group-label mb-0" for="ecommerce_offline_payment"', $html, 'its label is the group\'s');
	pg_assert_contains('name="ecommerce_offline_payment"', $html, 'name kept');
}

function test_settings_commerce_sp_seg()
{
	$html = pg_commerce_settings_seg('Transaction type', 'ecommerce_payment_gateway_transaction_type', array(
		array('value' => 'Authorize', 'id' => 'tt_a', 'label' => 'Authorize', 'on' => false),
		array('value' => 'Authorize & Capture', 'id' => 'tt_ac', 'label' => 'Authorize & capture', 'on' => true),
	));

	pg_assert_contains('<legend class="form-label pg-field-label">Transaction type</legend>', $html, 'a legend');
	pg_assert_contains('class="pg-seg flex-wrap"', $html, 'a pg-seg');
	pg_assert_contains('<label class="pg-seg-item" for="tt_ac"><input value="Authorize &amp; Capture" class="visually-hidden" type="radio" id="tt_ac" name="ecommerce_payment_gateway_transaction_type" checked="checked"/>Authorize &amp; capture</label>', $html, 'the value posted as before, the one stored chosen');
	pg_assert_false(strpos($html, 'class="radio"') !== false, 'no plain radio');
	pg_assert_same(1, substr_count($html, 'checked="checked"'), 'one chosen');
}

function test_settings_commerce_sp_ticks_and_group()
{
	$html = pg_commerce_settings_ticks('Accepted cards', array(
		array('name' => 'ecommerce_visa', 'label' => 'Visa', 'on' => true),
		array('name' => 'ecommerce_troy', 'label' => 'Troy', 'on' => false),
	));

	pg_assert_contains('<legend class="form-label pg-field-label">Accepted cards</legend>', $html, 'a legend');
	pg_assert_same(2, substr_count($html, 'class="pg-checkbox"'), 'a tick a card');
	pg_assert_contains('value="1" id="ecommerce_visa" name="ecommerce_visa" checked="checked"', $html, 'name and value kept');
	pg_assert_contains('id="ecommerce_troy" name="ecommerce_troy"/>', $html, 'off unticked');

	pg_assert_same('
                    <div class="col-12"><h3 class="pg-group-label mb-0">Prices &amp; tax</h3></div>', pg_commerce_settings_group('Prices & tax'), 'a group label');
}

function test_settings_commerce_sp_cards_drawn()
{
	foreach (array('pgset-store', 'pgset-payments') as $id) {
		$card = settings_commerce_sp_test_card($id);

		pg_assert_true($card !== '', $id . ' is drawn');
		pg_assert_false(strpos($card, 'popover') !== false, $id . ' has no popover');
		pg_assert_false(strpos($card, 'class="radio"') !== false, $id . ' has no plain radio');
		pg_assert_false(strpos($card, '<div class="switch">') !== false, $id . ' has no old switch');
		pg_assert_false(strpos($card, 'collapse-switcher') !== false, $id . ' opens nothing by a switch');
	}

	$store = settings_commerce_sp_test_card('pgset-store');

	foreach (array('Prices and tax', 'Products', 'Labels') as $group) {
		pg_assert_contains("pg_commerce_settings_group(lang('" . $group . "'))", $store, 'Store has the group ' . $group);
	}

	pg_assert_contains('$pack_before_label_row', $store, 'the label rule is drawn in Store');
	pg_assert_contains('$store_custom_fields', $store, 'the custom product fields are in Store');

	$payments = settings_commerce_sp_test_card('pgset-payments');
	pg_assert_contains("pg_commerce_settings_group(lang('After the order'))", $payments, 'Payment methods ends with After the order');

	foreach (array('ecommerce_credit_debit_card', 'ecommerce_pay_with_iyzico', 'ecommerce_paypal_express_checkout', 'ecommerce_offline_payment') as $name) {
		pg_assert_contains("pg_commerce_settings_group_switch('" . $name . "'", $payments, $name . ' heads its group');
	}

	foreach (array('ecommerce_payment_gateway_transaction_type_row', 'ecommerce_payment_gateway_mode_row', 'ecommerce_paypal_payments_pro_gateway_mode_row', 'ecommerce_iyzipay_installment_row', 'ecommerce_iyzipay_threeds_row', 'ecommerce_iyzipay_protected_currency_row') as $row) {
		pg_assert_contains('id="' . $row . '"', $payments, $row . ' kept for the gateway script');
	}

	$redirect = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/payments.php');
	$start = strpos($redirect, 'function pg_payment_settings_redirect_rows()');
	$body = substr($redirect, $start, strpos($redirect, "\n}\n", $start) - $start);
	pg_assert_false(strpos($body, 'popover') !== false, 'a redirect driver opens no popover');
	pg_assert_contains('class="pg-switch"', $body, 'a redirect driver heads its group with a pg-switch');
}

function test_settings_commerce_sp_anchors()
{
	$anchors = pg_settings_legacy_anchors();

	pg_assert_same(array('commerce', 'pgset-store'), $anchors['pack_before_label'], 'the label rule opens Store');
	pg_assert_same(array('commerce', 'pgset-store'), $anchors['ecommerce_custom_product_field_1_label'], 'the custom product fields open Store');
}
