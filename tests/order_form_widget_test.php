<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Order Form system widget
 * (includes/fn/widgets_order_form.php): when a product can be ordered, the
 * layout, the widget's and a row's flags, the page row the settings write
 * and the places the kind is registered in.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_order_form_widget_product_available()
{
	pg_assert_true(pg_of_product_available(array('inventory' => 0, 'inventory_quantity' => 0, 'backorder' => 0)), 'stock not tracked');
	pg_assert_true(pg_of_product_available(array('inventory' => 1, 'inventory_quantity' => 3, 'backorder' => 0)), 'some left');
	pg_assert_true(pg_of_product_available(array('inventory' => 1, 'inventory_quantity' => 0, 'backorder' => 1)), 'may be backordered');
	pg_assert_false(pg_of_product_available(array('inventory' => 1, 'inventory_quantity' => 0, 'backorder' => 0)), 'out of stock');
}

function test_order_form_widget_layout()
{
	pg_assert_same('drop-down selection', pg_of_layout('drop-down selection'), 'the pick list');
	pg_assert_same('list', pg_of_layout(''), 'the classic default');
	pg_assert_same('list', pg_of_layout('grid'), 'anything else is the list');
}

function test_order_form_widget_flags()
{
	$products = array(
		array('available' => true, 'selection_type' => 'donation'),
		array('available' => false, 'selection_type' => 'quantity'),
	);
	$f = pg_of_flags('drop-down selection', $products, true, '');
	pg_assert_true($f['has_products'] && $f['is_dropdown_layout'] && $f['has_recipient_choice'], 'one donation can be ordered');
	pg_assert_false($f['has_quantity'], 'only donations: no quantity');
	pg_assert_false($f['has_skip_button'], 'no skip label');
	$products[1]['available'] = true;
	$f = pg_of_flags('drop-down selection', $products, false, 'No, thanks');
	pg_assert_true($f['has_quantity'] && $f['has_skip_button'], 'a quantity product and a skip label');
	pg_assert_false($f['has_recipient_choice'], 'single recipient');
	$f = pg_of_flags('list', array(array('available' => false, 'selection_type' => 'checkbox')), true, ' ');
	pg_assert_true($f['no_products'] && $f['is_list_layout'], 'nothing can be ordered');
	pg_assert_false($f['has_recipient_choice'] || $f['has_quantity'] || $f['has_skip_button'], 'no recipient, quantity or blank skip label');
}

function test_order_form_widget_row_flags()
{
	$f = pg_of_row_flags(array('available' => true, 'selection_type' => 'quantity', 'recurring' => 1, 'recurring_schedule_editable_by_customer' => 1));
	pg_assert_true($f['order_form_quantity'] && $f['has_recurring_schedule'], 'quantity with a schedule');
	pg_assert_false($f['order_form_checkbox'] || $f['order_form_donation'] || $f['order_form_unavailable'], 'one control');
	$f = pg_of_row_flags(array('available' => false, 'selection_type' => 'checkbox', 'recurring' => 1, 'recurring_schedule_editable_by_customer' => 1));
	pg_assert_true($f['order_form_unavailable'], 'out of stock');
	pg_assert_false($f['order_form_checkbox'] || $f['has_recurring_schedule'], 'no control and no schedule');
	$f = pg_of_row_flags(array('available' => true, 'selection_type' => 'autoselect'));
	pg_assert_false($f['order_form_checkbox'] || $f['order_form_quantity'] || $f['order_form_donation'], 'autoselect has no control');
}

function test_order_form_widget_page_row_changes()
{
	$row = array('product_group_id' => '4', 'product_layout' => 'list', 'add_button_label' => 'Continue',
		'add_button_next_page_id' => '9', 'skip_button_label' => '', 'skip_button_next_page_id' => '0');
	pg_assert_same(array(), pg_of_page_row_changes($row, array()), 'a widget never set keeps the row');
	pg_assert_same(array(), pg_of_page_row_changes($row, array('product_group_id' => 0, 'add_button_next_page_id' => '')), 'zero means the page\'s own setting');
	pg_assert_same(array('product_group_id' => 7, 'product_layout' => 'drop-down selection'),
		pg_of_page_row_changes($row, array('product_group_id' => '7', 'product_layout' => 'drop-down selection', 'add_button_label' => ' Continue ')), 'only what changes');
	pg_assert_same(array('product_layout' => 'list', 'skip_button_label' => str_repeat('x', 50)),
		pg_of_page_row_changes(null, array('product_layout' => 'grid', 'skip_button_label' => str_repeat('x', 60))), 'a new row: layout kept to the two, labels to 50 characters');
}

function test_order_form_widget_registered()
{
	$labels = pg_sw_type_labels();
	pg_assert_true(isset($labels['order_form']), 'panel label');
	$map = pg_sw_page_type_widgets();
	pg_assert_same('order_form', $map['order form'], 'stands in for the classic type');
	$designer = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/designer.php');
	pg_assert_contains("_render_system_widget_order_form(", $designer, 'dispatched by _expand_system_widgets()');
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	pg_assert_contains("{ type: 'order_form'", $js, 'in SW_TYPES');
	pg_assert_contains("case 'order_form': {", $js, 'has a starter tree');
	pg_assert_contains("order_form:        SW_ORDER_FORM_TOKEN_GROUPS", $js, 'has a token palette');
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_order_form.php');
	pg_assert_contains("_pg_inject_messages_node(\$tree, 'order_form')", $source, 'Messages named after the classic liveform');
}

// The starter's row, as createNode() writes it: the price, then a check box
// block and a quantity block, each shown by its row flag.
function _order_form_test_row_tree()
{
	$node = function ($tag, $props, $children = array()) {
		return array('type' => 'semantic', 'props' => array('tag' => $tag) + $props, 'children' => $children);
	};
	return array('type' => 'root', 'props' => array(), 'children' => array(
		$node('div', array('cssClass' => 'list-group-item'), array(
			$node('span', array('text' => 'x', '_bindings' => array('text' => '__price_formatted'))),
			$node('div', array('cssClass' => 'form-check', '_bindings' => array('eo_visible_if' => 'order_form_checkbox')), array(
				$node('input', array('id' => 'f_check', '_attrs' => array(array('name' => 'type', 'value' => 'checkbox'), array('name' => 'name', 'value' => 'product')))),
			)),
			$node('div', array('cssClass' => 'qty', '_bindings' => array('eo_visible_if' => 'order_form_quantity')), array(
				$node('input', array('id' => 'f_qty', '_attrs' => array(array('name' => 'type', 'value' => 'number'), array('name' => 'name', 'value' => 'product')))),
			)),
			$node('div', array('cssClass' => 'oos', 'text' => 'x', '_bindings' => array('text' => '__out_of_stock_message', 'eo_visible_if' => 'order_form_unavailable'))),
		)),
	));
}

function test_order_form_widget_row_html()
{
	$p = array('id' => 5, 'name' => 'A&B', 'short_description' => '', 'full_description' => '', 'image_name' => '',
		'selection_type' => 'quantity', 'default_quantity' => 2, 'available' => true, 'inventory' => 0,
		'inventory_quantity' => 0, 'out_of_stock_message' => '', 'price_html' => '<b>$1.00</b>');
	$html = pg_of_row_html(_order_form_test_row_tree(), $p, array('product' => '', 'donation' => ''), true, '', 1);
	pg_assert_contains('name="product_5"', $html, 'the quantity box named for the product');
	pg_assert_contains('value="2"', $html, 'a new form opens with the default quantity');
	pg_assert_contains('<b>$1.00</b>', $html, 'the classic price HTML');
	pg_assert_false(strpos($html, 'type="checkbox"') !== false || strpos($html, 'oos') !== false, 'the other blocks are dropped');

	$html = pg_of_row_html(_order_form_test_row_tree(), $p, array('product' => '7'), false, '', 1);
	pg_assert_contains('value="7"', $html, 'a refused post keeps the visitor\'s quantity');

	$p['selection_type'] = 'checkbox';
	$html = pg_of_row_html(_order_form_test_row_tree(), $p, array('product' => '1'), false, '', 1);
	pg_assert_contains('type="checkbox"', $html, 'the check box');
	pg_assert_contains('checked', $html, 'ticked again after a refused post');
	pg_assert_false(strpos($html, 'type="number"') !== false, 'no quantity box');

	$p['available'] = false;
	$p['inventory'] = 1;
	$p['out_of_stock_message'] = '<p>Soon</p>';
	$html = pg_of_row_html(_order_form_test_row_tree(), $p, array(), false, '', 1);
	pg_assert_false(strpos($html, '<input') !== false, 'an unavailable product has no control');
	pg_assert_contains('<p>Soon</p>', $html, 'its out-of-stock words');
}
