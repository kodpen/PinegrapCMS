<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the checkout's state pick list: the text box of the shipping and
 * billing state gets the pick list the other address widgets have
 * (_eo_fit_state_list()), each address of the form switches its own pair
 * (data-pg-sa-for, pg_sa_state_script()), the renderers' wiring, the order
 * screen's billing labels and the unlisted-district note's wording.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/language_map.php');

function express_order_state_list_test_attr($node, $name)
{
	foreach ((array) ($node['props']['_attrs'] ?? array()) as $a) {
		if (is_array($a) && ($a['name'] ?? '') === $name) return (string) $a['value'];
	}
	return null;
}

// The state column of the checkout's default layout once the field bindings
// named it for recipient 7: the label, then the text box.
function express_order_state_list_test_tree($name = 'shipping_7_state')
{
	return array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'semantic', 'props' => array('tag' => 'div', 'cssClass' => 'col-12 col-md-6 mb-3'), 'children' => array(
			array('type' => 'semantic', 'props' => array('tag' => 'label', 'text' => 'İl',
				'_attrs' => array(array('name' => 'for', 'value' => $name))), 'children' => array()),
			array('type' => 'semantic', 'props' => array('tag' => 'input', 'cssClass' => 'form-control', 'id' => $name,
				'_attrs' => array(array('name' => 'type', 'value' => 'text'), array('name' => 'required', 'value' => ''),
					array('name' => 'name', 'value' => $name), array('name' => 'value', 'value' => 'İstanbul'))), 'children' => array()),
		)),
	));
}

function express_order_state_list_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

// A Turkish address: the pick list is added after the text box, chosen by
// the stored name, and posts; the text box goes quiet.
function test_express_order_state_list_fit_country_with_states()
{
	$tree = express_order_state_list_test_tree();
	$tr = array(array('Ankara', 'Ankara'), array('İstanbul', 'İstanbul'), array('İzmir', 'İzmir'));
	pg_assert_true(_eo_fit_state_list($tree, 'shipping_7_state', 'İstanbul', $tr), 'the tree holds the state control');

	$kids = $tree['children'][0]['children'];
	pg_assert_same(3, count($kids), 'label, text box, pick list');
	$text = $kids[1];
	$list = $kids[2];
	pg_assert_same('select', $list['props']['tag']);
	pg_assert_same('shipping_7_state_list', $list['props']['id'], 'its own id beside the text box');
	pg_assert_same('shipping_7_state', express_order_state_list_test_attr($list, 'name'), 'the pick list posts');
	pg_assert_same(null, express_order_state_list_test_attr($list, 'hidden'), 'and is shown');
	pg_assert_same('', express_order_state_list_test_attr($text, 'name'), 'the text box posts nothing');
	pg_assert_same('', express_order_state_list_test_attr($text, 'hidden'), 'and is hidden');
	pg_assert_same(null, express_order_state_list_test_attr($text, 'required'), 'and no longer required');
	pg_assert_same('list', express_order_state_list_test_attr($list, 'data-pg-sa-state'));
	pg_assert_same('text', express_order_state_list_test_attr($text, 'data-pg-sa-state'));
	pg_assert_same('shipping_7_state', express_order_state_list_test_attr($list, 'data-pg-sa-for'), 'the script finds its own pair');
	pg_assert_same('shipping_7_state', express_order_state_list_test_attr($text, 'data-pg-sa-for'));

	pg_assert_same(4, count($list['children']), 'an empty choice and the states');
	pg_assert_same('İstanbul', $list['children'][2]['props']['text']);
	pg_assert_same('', express_order_state_list_test_attr($list['children'][2], 'selected'), 'the stored state is chosen');
}

// A country without states keeps the text box with what was typed.
function test_express_order_state_list_fit_country_without_states()
{
	$tree = express_order_state_list_test_tree('billing_state');
	pg_assert_true(_eo_fit_state_list($tree, 'billing_state', 'Bavaria', array()));
	$kids = $tree['children'][0]['children'];
	pg_assert_same('billing_state', express_order_state_list_test_attr($kids[1], 'name'), 'the text box posts');
	pg_assert_same('Bavaria', express_order_state_list_test_attr($kids[1], 'value'));
	pg_assert_same('', express_order_state_list_test_attr($kids[2], 'name'), 'the pick list posts nothing');
	pg_assert_same('', express_order_state_list_test_attr($kids[2], 'hidden'));
}

// A layout without that state control is left as it is; a layout whose
// designer already drew a pick list gets no second one.
function test_express_order_state_list_fit_other_layouts()
{
	$tree = express_order_state_list_test_tree();
	$before = $tree;
	pg_assert_false(_eo_fit_state_list($tree, 'billing_state', '', array(array('06', 'Ankara'))), 'no billing state in it');
	pg_assert_same($before, $tree, 'untouched');

	$own = array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'semantic', 'props' => array('tag' => 'select', 'id' => 'billing_state',
			'_attrs' => array(array('name' => 'name', 'value' => 'billing_state'))), 'children' => array()),
	));
	pg_assert_true(_eo_fit_state_list($own, 'billing_state', '06', array(array('06', 'Ankara'))));
	pg_assert_same(1, count($own['children']), 'no second list');
	pg_assert_same(2, count($own['children'][0]['children']), 'the designer\'s list is filled');
}

function test_express_order_state_list_prefixes()
{
	_eo_state_list_prefixes(null, true);
	_eo_state_list_prefixes('shipping_7_');
	_eo_state_list_prefixes('billing_');
	_eo_state_list_prefixes('shipping_7_');
	pg_assert_same(array('shipping_7_', 'billing_'), _eo_state_list_prefixes(null, true), 'each address once');
	pg_assert_same(array(), _eo_state_list_prefixes(), 'taken');
	pg_assert_same('', _eo_state_list_scripts('pg-eo-form-w1'), 'no address, no script');
}

// Several addresses in one form: each script works on its own pair.
function test_express_order_state_list_script_scoped()
{
	$ship = pg_sa_state_script('pg-eo-form-w1', array('TR' => array(array('İstanbul', 'İstanbul'))), array(), 'shipping_7_');
	$bill = pg_sa_state_script('pg-eo-form-w1', array('TR' => array(array('İstanbul', 'İstanbul'))), array(), 'billing_');
	pg_assert_contains('[data-pg-sa-state=text][data-pg-sa-for=\""+d.name+"\"]', $ship, 'the text box by its name');
	pg_assert_contains('[data-pg-sa-state=list][data-pg-sa-for=\""+d.name+"\"]', $ship, 'the pick list by its name');
	pg_assert_contains('"name":"shipping_7_state"', $ship);
	pg_assert_contains('select[name=shipping_7_country]', $ship);
	pg_assert_contains('"name":"billing_state"', $bill);
	pg_assert_contains('select[name=billing_country]', $bill);
}

function test_express_order_state_list_wiring()
{
	$cart = express_order_state_list_test_source('includes/fn/widgets_cart.php');
	pg_assert_contains('if ($_eo_lf) _eo_fit_state_lists($tree_decoded_for_eo, $_eo_lf, $_eo_ship_rid);', $cart, 'the static layout');
	pg_assert_contains('. _eo_state_list_scripts($form_id)', $cart, 'the scripts after the form');
	pg_assert_true(strpos($cart, '_eo_state_list_prefixes(null, true);') < strpos($cart, '_eo_fit_state_lists($tree_decoded_for_eo'), 'a fresh list per widget');
	$eo = express_order_state_list_test_source('includes/fn/widgets_express_order.php');
	pg_assert_contains('_eo_fit_state_lists($wrap, $lf, $rid);', $eo, 'each recipient of a recipient list');
}

// The order screen names a Turkish billing address's city and state as the
// checkout asked for them.
function test_express_order_state_list_order_screen_labels()
{
	$php = express_order_state_list_test_source('view_order.php');
	pg_assert_contains('$billing_place_labels = pg_address_labels($billing_country);', $php);
	pg_assert_contains("lang(\$billing_place_labels['city'])", $php);
	pg_assert_contains("lang(\$billing_place_labels['state'])", $php);
	pg_assert_false(strpos($php, "lang('City') . ':</span>") !== false, 'no fixed City label left on the billing card');
	pg_assert_same(array('city' => 'District', 'state' => 'Province'), pg_address_labels('TR'));
}

function test_express_order_state_list_note_wording()
{
	$tr = pg_test_language_map('tr');
	pg_assert_same('Bu ilçe listede yok. Göndermeden önce adresi kontrol edin.',
		$tr['This district is not in the district list. Check the address before shipping.'] ?? null);
	pg_assert_same('Bu ilçe {var:1} ilçeleri arasında yok. Göndermeden önce adresi kontrol edin.',
		$tr['This district is not in the district list of {var:1}. Check the address before shipping.'] ?? null);
}
