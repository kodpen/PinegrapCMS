<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the optional district box of a shipping address
 * (ship_tos.district): when it is drawn (pg_ship_to_district_shown()), what
 * is stored (pg_ship_to_district_clean(), pg_ship_to_district_set_sql()) and
 * which screens draw and save it, read from their source since no test here
 * has a database.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function tr_district_box_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

// escape() reads db::$con; no test here has a connection.
function tr_district_box_test_db_stub()
{
	if (!class_exists('db')) {
		class db
		{
			public static $con;
		}
	}
}

// A Turkish address gets the box, by code or by name, once the column exists.
function test_tr_district_box_shown_for_turkey()
{
	pg_assert_true(pg_ship_to_district_shown('TR', false, true), 'code');
	pg_assert_true(pg_ship_to_district_shown('tr', false, true), 'lower-case code');
	pg_assert_true(pg_ship_to_district_shown('Turkey', false, true), 'English name');
	pg_assert_true(pg_ship_to_district_shown('Türkiye', false, true), 'Turkish name');
	pg_assert_true(pg_ship_to_district_country_is_tr(' TR '), 'spaces');
}

// Another country gets it only when the carrier's driver asks for it.
function test_tr_district_box_hidden_elsewhere()
{
	pg_assert_false(pg_ship_to_district_shown('DE', false, true), 'Germany');
	pg_assert_false(pg_ship_to_district_shown('US', false, true), 'United States');
	pg_assert_false(pg_ship_to_district_shown('', false, true), 'no country');
	pg_assert_true(pg_ship_to_district_shown('DE', true, true), 'driver asks');
	pg_assert_false(pg_ship_to_district_carrier_asks(''), 'no carrier');
}

// Before step 11.0.134 nothing draws the box, even for Turkey.
function test_tr_district_box_hidden_without_column()
{
	pg_assert_false(pg_ship_to_district_shown('TR', false, false), 'Turkey');
	pg_assert_false(pg_ship_to_district_shown('DE', true, false), 'driver asks');
}

// Stored as one trimmed line of at most 50 characters; empty is allowed.
function test_tr_district_box_clean()
{
	pg_assert_same('Kadıköy', pg_ship_to_district_clean('  Kadıköy  '));
	pg_assert_same('', pg_ship_to_district_clean(''));
	pg_assert_same('', pg_ship_to_district_clean('   '));
	pg_assert_same('', pg_ship_to_district_clean(null));
	pg_assert_same('', pg_ship_to_district_clean(array('x')));
	pg_assert_same('Çankaya Merkez', pg_ship_to_district_clean("Çankaya\r\n Merkez"));
	pg_assert_same(str_repeat('ş', 50), pg_ship_to_district_clean(str_repeat('ş', 60)));
	pg_assert_same(50, mb_strlen(pg_ship_to_district_clean(str_repeat('a', 51)), 'UTF-8'));
	pg_assert_same('', pg_ship_to_district_clean("\xC3\x28"));
}

// The SET part carries the cleaned value, and nothing while the column is missing.
function test_tr_district_box_set_sql()
{
	tr_district_box_test_db_stub();

	pg_assert_same('', pg_ship_to_district_set_sql('Kadıköy', false));
	pg_assert_same('', pg_ship_to_district_set_sql('', false));
	pg_assert_same(", district = 'Kadıköy'", pg_ship_to_district_set_sql(' Kadıköy ', true));
	pg_assert_same(", district = ''", pg_ship_to_district_set_sql('', true));
	pg_assert_contains("O\\'Hara", pg_ship_to_district_set_sql("O'Hara", true));
}

// A field column of the designer tree: a label and one control.
function tr_district_box_test_column($name, $label_children = array())
{
	return array('type' => 'semantic', 'props' => array('tag' => 'div', 'cssClass' => 'col-sm-6 mb-3'), 'children' => array(
		array('type' => 'semantic', 'props' => array('tag' => 'label', 'text' => $name), 'children' => $label_children),
		array('type' => 'semantic', 'props' => array('tag' => 'input', '_attrs' => array(array('name' => 'name', 'value' => $name))), 'children' => array()),
	));
}

function tr_district_box_test_named($name)
{
	return function ($node) use ($name) {
		foreach ((array) ($node['props']['_attrs'] ?? array()) as $a) {
			if (($a['name'] ?? '') === 'name' && ($a['value'] ?? '') === $name) {
				return true;
			}
		}
		return false;
	};
}

function tr_district_box_test_names($tree)
{
	$names = array();
	foreach ($tree['children'] as $column) {
		$names[] = $column['children'][1]['props']['_attrs'][0]['value'] ?? '?';
	}
	return $names;
}

// A layout designed before the box gets it right after City's column (a
// required City label with its star counts as a column); one that has it
// keeps it where it is; while the column is missing the box and its column go.
function test_tr_district_box_tree_fit()
{
	$star = array(array('type' => 'semantic', 'props' => array('tag' => 'span', 'text' => '*'), 'children' => array()));
	$row = array('type' => 'semantic', 'props' => array('tag' => 'div'), 'children' => array(
		tr_district_box_test_column('city', $star),
		tr_district_box_test_column('country'),
	));
	$city = tr_district_box_test_named('city');
	$district = tr_district_box_test_named('district');

	$tree = $row;
	pg_ship_to_district_tree_fit($tree, $city, $district, tr_district_box_test_column('district'), true);
	pg_assert_same(array('city', 'district', 'country'), tr_district_box_test_names($tree), 'added after City');

	$again = $tree;
	pg_ship_to_district_tree_fit($again, $city, $district, tr_district_box_test_column('district'), true);
	pg_assert_same(array('city', 'district', 'country'), tr_district_box_test_names($again), 'added once');

	$moved = array('type' => 'semantic', 'props' => array('tag' => 'div'), 'children' => array(
		tr_district_box_test_column('district'), tr_district_box_test_column('city'),
	));
	pg_ship_to_district_tree_fit($moved, $city, $district, tr_district_box_test_column('district'), true);
	pg_assert_same(array('district', 'city'), tr_district_box_test_names($moved), 'kept where the designer put it');

	pg_ship_to_district_tree_fit($tree, $city, $district, tr_district_box_test_column('district'), false);
	pg_assert_same(array('city', 'country'), tr_district_box_test_names($tree), 'dropped with its column');

	$plain = $row;
	pg_ship_to_district_tree_fit($plain, $city, $district, tr_district_box_test_column('district'), false);
	pg_assert_same(array('city', 'country'), tr_district_box_test_names($plain), 'nothing added without the column');
}

// The control carries what the script reads; its column is what is hidden.
function test_tr_district_box_tree_mark()
{
	$tree = array('type' => 'semantic', 'props' => array('tag' => 'div'), 'children' => array(tr_district_box_test_column('district')));
	pg_ship_to_district_tree_mark($tree, tr_district_box_test_named('district'), 'shipping_7_country', array('TR'), false);

	$wrap = array();
	foreach ($tree['children'][0]['props']['_attrs'] as $a) $wrap[$a['name']] = $a['value'];
	$attrs = array();
	foreach ($tree['children'][0]['children'][1]['props']['_attrs'] as $a) $attrs[$a['name']] = $a['value'];

	pg_assert_true(isset($wrap['data-pg-district-wrap']), 'column marked');
	pg_assert_same('shipping_7_country', $attrs['data-pg-district']);
	pg_assert_same('TR', $attrs['data-pg-district-codes']);
	pg_assert_same('50', $attrs['maxlength']);
	pg_assert_false(isset($attrs['data-pg-district-asks']), 'the carrier does not ask');

	pg_ship_to_district_tree_mark($tree, tr_district_box_test_named('district'), 'country', array('TR'), true);
	$attrs = array();
	foreach ($tree['children'][0]['children'][1]['props']['_attrs'] as $a) $attrs[$a['name']] = $a['value'];
	pg_assert_same('1', $attrs['data-pg-district-asks']);
	pg_assert_same('country', $attrs['data-pg-district']);
}

// The script follows the country control and hides the column.
function test_tr_district_box_script()
{
	// Read from the source: the script is printed once per request, and an
	// earlier test may have drawn it already.
	$source = tr_district_box_test_source('includes/fn/shipping.php');
	$start = strpos($source, "\nfunction pg_ship_to_district_script(");
	$js = (string) substr($source, (int) $start, 1500);
	pg_assert_contains('data-pg-district-wrap', $js);
	pg_assert_contains('data-pg-district-asks', $js);
	pg_assert_contains('w.hidden=!on', $js);
}

// Both twins of each widget draw the box: the express order's PHP default
// tree and JS starter, the shipping address starter (its PHP side adds it to
// a saved layout), the server's per-recipient form and the classic page.
function test_tr_district_box_twins()
{
	$eo = tr_district_box_test_source('includes/fn/widgets_express_order.php');
	$js = tr_district_box_test_source('assets/js/style_designer.src.js');

	pg_assert_contains("lang('District'),    'shipping_district'", $eo, 'PHP default tree');
	pg_assert_contains("_sdT('District'),     'shipping_district'", $js, 'JS starter tree');
	pg_assert_contains("_sdT('District'), 'shipping_district'", $js, 'JS recipient loop starter');
	pg_assert_contains("'district', { autocomplete: 'address-level3'", $js, 'shipping address starter');
	pg_assert_contains("_eo_fit_district_field(\$wrap, \$rid);", $eo, 'per-recipient loop');
	pg_assert_contains("_eo_fit_district_field(\$tree_decoded_for_eo", tr_district_box_test_source('includes/fn/widgets_cart.php'), 'single recipient');
	pg_assert_contains("'shipping_' . \$rid . '_district'", $eo, 'server per-recipient form');
	pg_assert_contains('pg_ship_to_district_tree_fit(', tr_district_box_test_source('includes/fn/widgets_shipping_address.php'));
	pg_assert_contains('name="district"', tr_district_box_test_source('get_shipping_address_and_arrival.php'), 'classic page');
	pg_assert_true(in_array('district', pg_sa_address_fields(), true), 'a shipping address control');
	pg_assert_true(_eo_is_known_input_name('shipping_12_district'), 'a known express order field');
}

// Both checkouts save it through the helpers; the order screen shows it.
function test_tr_district_box_saved_and_shown()
{
	$eo = tr_district_box_test_source('express_order.php');
	pg_assert_contains("pg_ship_to_district_address_sql(\$liveform->get(\$prefix . 'district')", $eo);
	pg_assert_contains("\$pg_prefix . 'district'", $eo, 'one value per post');

	$sa = tr_district_box_test_source('shipping_address_and_arrival.php');
	pg_assert_contains("pg_ship_to_district_save((int) (\$_POST['ship_to_id'] ?? 0), \$liveform->get_field_value('district')", $sa);
	pg_assert_contains("'city', 'district', 'state'", $sa, 'one value per post');

	$view = tr_district_box_test_source('view_order.php');
	pg_assert_contains('(pg_ship_to_district_ready() ? "ship_tos.district," : "")', $view);
	pg_assert_contains("\$ship_tos[\$key]['district'] . ', '", $view);
}
