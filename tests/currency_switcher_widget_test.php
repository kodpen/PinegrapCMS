<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the currency picker of the Order Preview, Shipping Method and
 * Order Form system widgets (includes/fn/widgets_cart.php): the
 * has_currency_choice flag of each widget, the picker controls carrying the
 * form attribute, the separate update_currency.php form with its token, and
 * the editor's starter trees carrying the same names.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _currency_switcher_test_rows($n)
{
	$all = array(
		array('id' => '1', 'name' => 'Turkish Lira', 'code' => 'TRY'),
		array('id' => '2', 'name' => 'Euro', 'code' => 'EUR'),
		array('id' => '3', 'name' => 'Pound <Sterling>', 'code' => 'GBP'),
	);
	return array_slice($all, 0, $n);
}

function _currency_switcher_test_tree()
{
	return array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'semantic', 'props' => array('tag' => 'div', '_bindings' => array('eo_visible_if' => 'has_currency_choice')), 'children' => array(
			array('type' => 'semantic', 'props' => array('tag' => 'select', 'id' => 'f_cur',
				'_attrs' => array(array('name' => 'name', 'value' => 'currency_id'))), 'children' => array(
				array('type' => 'semantic', 'props' => array('tag' => 'option', 'text' => 'Filled by the server',
					'_attrs' => array(array('name' => 'value', 'value' => ''))), 'children' => array()),
			)),
			array('type' => 'semantic', 'props' => array('tag' => 'button', 'text' => 'Update',
				'_attrs' => array(array('name' => 'type', 'value' => 'submit'), array('name' => 'name', 'value' => 'currency_update'))), 'children' => array()),
		)),
	));
}

function _currency_switcher_test_attrs($node)
{
	$out = array();
	foreach ((array)($node['props']['_attrs'] ?? array()) as $a) $out[(string)$a['name']] = (string)$a['value'];
	return $out;
}

function test_currency_switcher_flag_per_widget()
{
	foreach (array(1 => false, 2 => true, 3 => true) as $n => $want) {
		$rows = _currency_switcher_test_rows($n);
		pg_assert_same($want, pg_op_flags(array(), $rows)['has_currency_choice'], "order preview, $n currencies");
		pg_assert_same($want, pg_sm_flags(true, false, 1, false, $rows)['has_currency_choice'], "shipping method, $n currencies");
		pg_assert_same($want, pg_of_flags('list', array(), false, '', $rows)['has_currency_choice'], "order form, $n currencies");
	}
	pg_assert_false(pg_op_flags(array())['has_currency_choice'], 'no currencies passed: no choice');
	pg_assert_false(pg_sm_flags(false, false, 0, false)['has_currency_choice'], 'shipping method without currencies');
	pg_assert_false(pg_of_flags('list', array(), false, '')['has_currency_choice'], 'order form without currencies');
}

function test_currency_switcher_picker_controls()
{
	$tree = _currency_switcher_test_tree();
	pg_sw_currency_picker($tree, 7, _currency_switcher_test_rows(3), 2);
	$select = $tree['children'][0]['children'][0];
	$button = $tree['children'][0]['children'][1];
	$attrs = _currency_switcher_test_attrs($select);
	pg_assert_same('pg-cur-w7', $attrs['form'], 'the select belongs to the separate form');
	pg_assert_same('this.form.submit()', $attrs['onchange'], 'a choice submits that form');
	pg_assert_same('pg-cur-w7', _currency_switcher_test_attrs($button)['form'], 'the no-script button submits it too');
	pg_assert_same(3, count($select['children']), 'one option per currency');
	pg_assert_same('Pound <Sterling> (GBP)', $select['children'][2]['props']['text'], 'name and code (escaped when drawn)');
	pg_assert_true(isset(_currency_switcher_test_attrs($select['children'][1])['selected']), 'the visitor\'s currency chosen');
	pg_assert_false(isset(_currency_switcher_test_attrs($select['children'][0])['selected']), 'only that one');

	$tree = _currency_switcher_test_tree();
	pg_sw_currency_picker($tree, 7, _currency_switcher_test_rows(1), 1);
	$json = json_encode($tree);
	pg_assert_false(strpos($json, 'currency_id') !== false || strpos($json, 'currency_update') !== false, 'a single currency leaves no control behind');
}

function test_currency_switcher_separate_form()
{
	$_SESSION['software']['token'] = 'tok123';
	$html = pg_sw_currency_form_html(7, _currency_switcher_test_rows(2), '/shop/preview?a=1&b="2"');
	pg_assert_contains('<form id="pg-cur-w7"', $html, 'the id the controls point at');
	pg_assert_contains('/update_currency.php" method="post"', $html, 'posts to the classic handler');
	pg_assert_contains(get_token_field(), $html, 'carries the token');
	pg_assert_contains('name="send_to" value="/shop/preview?a=1&amp;b=&quot;2&quot;"', $html, 'the way back, escaped');
	pg_assert_false(strpos($html, '<select') !== false, 'the controls stay in the widget\'s tree');
	pg_assert_same('', pg_sw_currency_form_html(7, _currency_switcher_test_rows(1), '/x'), 'no form without a choice');
}

function test_currency_switcher_after_widget_form()
{
	foreach (array('widgets_order_preview.php', 'widgets_shipping_method.php', 'widgets_order_form.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/' . $file);
		pg_assert_contains('pg_sw_currency_rows()', $source, $file . ': the shared query');
		pg_assert_contains('pg_sw_currency_picker(', $source, $file . ': fills the picker');
		pg_assert_contains('pg_sw_currency_form_html(', $source, $file . ': prints the separate form');
	}
	$cart = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_cart.php');
	pg_assert_same(1, substr_count($cart, 'FROM currencies'), 'one currency query for the cart and the checkout widgets');
}

function test_currency_switcher_starter_trees()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_true((bool)preg_match('/function _swCurrencyPicker\(\) \{(.*?)\n    \}/s', $js, $m), 'the starter part');
	pg_assert_contains("{ name: 'name', value: 'currency_id' }", $m[1], 'the select the server fills');
	pg_assert_contains("{ name: 'name', value: 'currency_update' }", $m[1], 'the button the server points at the form');
	pg_assert_contains("eo_visible_if: 'has_currency_choice'", $m[1], 'shown only with a choice');
	foreach (array('order_preview', 'shipping_method', 'order_form') as $kind) {
		pg_assert_true((bool)preg_match("/            case '" . $kind . "': \{(.*?)\n            \}/s", $js, $c), $kind . ' starter');
		pg_assert_contains('_swCurrencyPicker(', $c[1], $kind . ' starter has the picker');
		pg_assert_true((bool)preg_match('/\n        ' . $kind . ": \[(.*?)\n        \]/s", $js, $f), $kind . ' flag list');
		pg_assert_contains("['has_currency_choice',", $f[1], $kind . ' lists the flag');
	}
}
