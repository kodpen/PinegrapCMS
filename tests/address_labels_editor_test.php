<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the address labels in the visual editor: the editor gets the
 * store's default country's words (pg_address_labels_editor_config()), the
 * address widgets' starters write them, and the canvas shows a default-word
 * city or state label in them (style_designer.src.js).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function address_labels_editor_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

// Turkey as the default country by code or name; any other, or none, is not.
function test_address_labels_editor_config()
{
	$words = array('city' => array('tr' => 'İlçe', 'other' => 'Şehir', 'defaults' => array('City')),
		'state' => array('tr' => 'İl', 'other' => 'Eyalet / İl', 'defaults' => array('State / Province')));
	pg_assert_same(array('tr' => true, 'words' => $words), pg_address_labels_editor_config('TR', $words));
	pg_assert_same(true, pg_address_labels_editor_config('Türkiye', $words)['tr']);
	pg_assert_same(false, pg_address_labels_editor_config('US', $words)['tr']);
	pg_assert_same(false, pg_address_labels_editor_config('', $words)['tr']);
}

// The editor's region data carries it; the express order reads the same
// default country.
function test_address_labels_editor_region_data()
{
	pg_assert_contains("\$data['address_labels'] = pg_address_labels_editor_config(pg_address_default_country());",
		address_labels_editor_test_source('includes/fn/designer.php'));
	pg_assert_contains('return pg_address_default_country();', address_labels_editor_test_source('includes/fn/widgets_express_order.php'));
}

// Every starter's city and state label takes the default country's words:
// express order (shipping and billing), the recipient list block, account
// profile, address book, billing information, affiliate sign-up and
// shipping address and arrival.
function test_address_labels_editor_starters()
{
	$js = address_labels_editor_test_source('assets/js/style_designer.src.js');
	pg_assert_same(8, substr_count($js, "_sdAddrLabel('city')"), 'city labels');
	pg_assert_same(8, substr_count($js, "_sdAddrLabel('state')"), 'state labels');
	foreach (array("'shipping_city'", "'shipping_state'", "'billing_city'", "'billing_state'") as $field) {
		pg_assert_true(!preg_match("~_sdT\\('(City|State|State / Province)'\\),\\s*" . preg_quote($field, '~') . '~', $js), $field . ' starter label');
	}
	pg_assert_true(!preg_match("~_swAcctField\\(_sdT\\('(City|State / Province)'\\)~", $js), 'member starters');
	pg_assert_true(!preg_match("~text: _sdT\\('State / Province'\\), customName: _sdT\\('Label'\\)~", $js), 'state labels beside the pick list');

	// The canvas: a plain label's text goes through the swap; the control
	// names match the server's stamp (pg_sa_address_labels(), _eo_fit_district_field()).
	pg_assert_contains('_semText = _sdAddrCanvasText(node, _semText);', $js);
	pg_assert_contains("city:  ['city', 'billing_city', 'business_city', 'shipping_city']", $js);
	pg_assert_contains("state: ['state', 'billing_state', 'business_state', 'shipping_state']", $js);

	$min = address_labels_editor_test_source('assets/js/style_designer.min.js');
	pg_assert_true(strpos($min, 'address_labels') !== false, 'the served editor has it');
}
