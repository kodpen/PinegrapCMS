<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the address labels of the panel and ERP forms: the city field of
 * a Turkish account card or delivery note says İl (erp_address_city_label_key()),
 * the verified shipping address follows its state pick list, and the Turkish
 * default layout of the new-conversation form says İlçe and İl.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/accounts.php');

function address_labels_panel_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

// The card keeps its columns (city = il, district = ilçe); only the word moves.
function test_address_labels_panel_erp_city_key()
{
	pg_assert_same('Province', erp_address_city_label_key('TR'));
	pg_assert_same('Province', erp_address_city_label_key(' tr '));
	pg_assert_same('City', erp_address_city_label_key('DE'));
	pg_assert_same('City', erp_address_city_label_key('US'));
}

// Both ERP forms draw the country's word and switch it with the country.
function test_address_labels_panel_erp_forms()
{
	$account = address_labels_panel_test_source('includes/erp/account_form.php');
	pg_assert_contains('<label for="city" class="form-label" data-label-tr="\' . h(lang(\'Province\')) . \'" data-label-other="\' . h(lang(\'City\')) . \'">\' . h(lang(erp_address_city_label_key($country)))', $account);
	pg_assert_contains('document.querySelectorAll("label[data-label-tr]")', $account, 'every switched label follows');

	$waybill = address_labels_panel_test_source('includes/erp/waybill_form.php');
	pg_assert_contains('h(lang(erp_address_city_label_key((string) $liveform->get_field_value(\'ship_to_country\'))))', $waybill);
	pg_assert_contains('city.getAttribute((select.value === "TR") ? "data-label-tr" : "data-label-other")', $waybill);
}

// A label following another control than the country: the value and the
// codes given decide, not a country lookup.
function test_address_labels_panel_follows_given_codes()
{
	$label = pg_address_labels_html('City', 'city', 'state_id', '34', array(34, 35));
	pg_assert_contains('data-pg-addr-label="state_id"', $label['attrs']);
	pg_assert_contains('data-pg-addr-codes="34,35"', $label['attrs']);
	pg_assert_same(h(lang('District')), $label['text'], 'a Turkish state');

	$label = pg_address_labels_html('City', 'city', 'state_id', '7', array('34'));
	pg_assert_same(h(lang('City')), $label['text'], 'another state');

	$own = pg_address_labels_html('Town', 'city', 'state_id', '34', array('34'));
	pg_assert_same('', $own['attrs'], 'own words are not stamped');
}

// The verified shipping address card follows its state pick list.
function test_address_labels_panel_verified_address()
{
	$form = address_labels_panel_test_source('includes/forms/verified_shipping_address_form.php');
	pg_assert_contains("pg_address_labels_html(lang('City'), 'city', 'state_id', \$state_id, \$tr_state_ids)", $form);
	pg_assert_contains("pg_address_labels_html(lang('State'), 'state', 'state_id', \$state_id, \$tr_state_ids)", $form);
	pg_assert_contains('pg_fields_script() . pg_ship_to_district_script()', $form);
}

// The Turkish default install's new-conversation form says İlçe and İl, as
// its other address layouts do.
function test_address_labels_panel_turkish_layout()
{
	$layout = address_labels_panel_test_source('data/backups/turkish_default/layouts/1005.php');
	pg_assert_contains('<label for="business_city">&#304;l&ccedil;e</label>', $layout);
	pg_assert_contains('<label for="business_state_text_box">&#304;l</label>', $layout);
	pg_assert_contains('<label for="business_state_pick_list" style="display: none">&#304;l</label>', $layout);
	pg_assert_same(false, strpos($layout, 'State / Province'), 'no English left');
}
