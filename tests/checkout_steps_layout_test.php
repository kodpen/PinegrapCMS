<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a classic checkout step's layout drawn by its widget: the flags
 * the billing information, shipping address and shipping method widgets
 * give a converted layout, and the converter's handling of a branch that
 * opens or closes wrappers and of a table's heading row beside the
 * widget's list.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/layout_convert.php');

function test_csl_billing_custom_fields_flag()
{
	pg_assert_true(pg_bi_flags(array('custom_field_2_label' => 'Note'), true, false, false, false)['has_custom_fields'], 'the second field alone');
	pg_assert_false(pg_bi_flags(array('custom_field_1_label' => ''), true, false, false, false)['has_custom_fields'], 'no label, no field');
}

function test_csl_shipping_address_flags()
{
	$f = pg_sa_flags(true, false, true, false, false, true, true);
	pg_assert_true($f['has_address_type']);
	pg_assert_true($f['offers_same_as_billing']);
	$f = pg_sa_flags(false, false, true, false, false, true, true);
	pg_assert_false($f['has_address_type'], 'no recipient, no form');
	pg_assert_false($f['offers_same_as_billing']);
	$f = pg_sa_flags(true, false, true, false);
	pg_assert_false($f['has_address_type'], 'off by default');
	pg_assert_false($f['offers_same_as_billing']);
}

function test_csl_shipping_method_flags()
{
	$f = pg_sm_flags(true, false, 2, false, array(), array('company' => 'Kodpen', 'address_2' => ' '));
	pg_assert_true($f['has_several_shipping_methods']);
	pg_assert_true($f['recipient_has_company']);
	pg_assert_false($f['recipient_has_address_2'], 'a blank second line');
	$f = pg_sm_flags(true, false, 1, false);
	pg_assert_false($f['has_several_shipping_methods'], 'one method');
	pg_assert_false($f['recipient_has_company']);
	pg_assert_false(pg_sm_flags(false, false, 3, false, array(), array('company' => 'Kodpen'))['recipient_has_company'], 'no recipient');
}

function test_csl_flag_wrap()
{
	$o = '<pg-vis data-flag="f">';
	$c = '</pg-vis>';
	pg_assert_same($o . '<p>x</p>' . $c, _pg_lc_flag_wrap('<p>x</p>', $o, $c), 'a balanced branch');
	pg_assert_same('<div class="row"><div>', _pg_lc_flag_wrap('<div class="row"><div>', $o, $c), 'wrappers it only opens are drawn always');
	pg_assert_same(' </div>' . $o . '<div>b</div>' . $c . '</div>', _pg_lc_flag_wrap(' </div><div>b</div></div>', $o, $c), 'the wrappers it closes stand outside');
	pg_assert_same($o . '<div class="row"><div>' . $c, _pg_lc_flag_wrap('<div class="row"><div>', $o, $c, false), 'one of an if and else: as before');
	pg_assert_same($o . '<p>x<br><img src="a"></p>' . $c, _pg_lc_flag_wrap('<p>x<br><img src="a"></p>', $o, $c), 'void elements count none');
}

function test_csl_listed_table()
{
	$th = pg_di_mk('semantic', array('tag' => 'tr'), array(pg_di_mk('semantic', array('tag' => 'th', 'text' => 'Item'))));
	$list = pg_di_mk('semantic', array('tag' => 'div', '_list' => true));
	$td = pg_di_mk('semantic', array('tag' => 'tr'), array(pg_di_mk('semantic', array('tag' => 'td', 'text' => 'x'))));
	pg_assert_same($list, _pg_lc_listed(array($th, $list)), 'a heading row beside the list');
	pg_assert_same($list, _pg_lc_listed(array($list)));
	pg_assert_same(null, _pg_lc_listed(array($td, $list)), 'a row of data cells stays');
	pg_assert_same(null, _pg_lc_listed(array($th)), 'no list');
}
