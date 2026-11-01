<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Checkout Options table of a catalog_item_view widget: a
 * container bound to the payment_methods section gets one row per payment
 * method the store has switched on, with its real fee, and is left out with
 * its tab when none is on (includes/fn/widgets_catalog.php); the starter
 * binds it and names no fee (assets/js/style_designer.src.js).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function civ_pay_test_el($tag, $class, $children = array(), $extra = array())
{
	return array('type' => 'semantic', 'props' => array_merge(array('tag' => $tag, 'cssClass' => $class), $extra), 'children' => $children);
}

function civ_pay_test_td($text, $class = '')
{
	return civ_pay_test_el($class === 'th' ? 'th' : 'td', $class === 'th' ? '' : $class, array(
		array('type' => 'content', 'props' => array('contentType' => 'paragraph', 'text' => $text, 'cssClass' => 'mb-0'), 'children' => array()),
	));
}

// The starter's table, the body bound to payment_methods.
function civ_pay_test_table()
{
	return civ_pay_test_el('div', 'table-responsive', array(
		civ_pay_test_el('table', 'table table-striped', array(
			civ_pay_test_el('thead', '', array(civ_pay_test_el('tr', '', array(civ_pay_test_td('Method', 'th'), civ_pay_test_td('Description', 'th'), civ_pay_test_td('Fee', 'th'))))),
			civ_pay_test_el('tbody', '', array(
				civ_pay_test_el('tr', 'sample-row', array(civ_pay_test_td('Card'), civ_pay_test_td('Sample'), civ_pay_test_td('', 'text-end'))),
				civ_pay_test_el('tr', '', array(civ_pay_test_td('Bank'), civ_pay_test_td('Sample'), civ_pay_test_td('', 'text-end'))),
			), array('_bindings' => array('section' => 'payment_methods'))),
		)),
	));
}

// The starter's tab section with the table in the second pane; $active is
// the open tab.
function civ_pay_test_tabs($active = 0)
{
	$li = function ($n, $label) use ($active) {
		$attrs = array(
			array('name' => 'data-bs-toggle', 'value' => 'tab'),
			array('name' => 'data-bs-target', 'value' => '#civtab_p_' . $n),
		);
		return civ_pay_test_el('li', 'nav-item', array(
			array('type' => 'content', 'props' => array('contentType' => 'heading', 'tag' => 'button', 'text' => $label,
				'cssClass' => 'nav-link' . ($n === $active ? ' active' : ''), '_attrs' => $attrs), 'children' => array()),
		));
	};
	$pane = function ($n, $children) use ($active) {
		return civ_pay_test_el('div', 'tab-pane fade' . ($n === $active ? ' show active' : ''), $children, array('id' => 'civtab_p_' . $n));
	};

	return civ_pay_test_el('div', 'mt-5', array(
		civ_pay_test_el('ul', 'nav nav-tabs', array($li(0, 'Details'), $li(1, 'Checkout Options'), $li(2, 'Reviews'))),
		civ_pay_test_el('div', 'tab-content', array(
			$pane(0, array(civ_pay_test_el('p', 'mb-0', array(), array('text' => 'Details')))),
			$pane(1, array(civ_pay_test_table())),
			$pane(2, array(civ_pay_test_el('p', '', array(), array('text' => 'Reviews')))),
		)),
	));
}

function civ_pay_test_texts($tr)
{
	$out = array();
	foreach ($tr['children'] as $td) {
		$out[] = $td['children'][0]['props']['text'];
	}
	return $out;
}

// Each switched-on method is one row; only the card names a fee.
function test_civ_payment_method_rows()
{
	pg_assert_same(array(), pg_civ_payment_method_rows(array('ecommerce' => false, 'card' => true)), 'e-commerce off: no row');
	pg_assert_same(array(), pg_civ_payment_method_rows(array('ecommerce' => true)), 'nothing on: no row');

	$rows = pg_civ_payment_method_rows(array('ecommerce' => true, 'card' => true, 'surcharge' => 0));
	pg_assert_same(1, count($rows), 'the card alone');
	pg_assert_same('0%', $rows[0][2], 'no surcharge: 0%');

	$rows = pg_civ_payment_method_rows(array('ecommerce' => true, 'card' => true, 'surcharge' => 2.50));
	pg_assert_same('2.5%', $rows[0][2], 'the surcharge as set');

	$all = pg_civ_payment_method_rows(array(
		'ecommerce' => true, 'card' => true, 'surcharge' => 0, 'installments' => 6, 'offline' => true, 'cod' => true,
		'paypal' => true, 'iyzico' => true, 'on_account' => true,
	));
	pg_assert_same(7, count($all), 'every method on: seven rows');
	pg_assert_true(strpos($all[1][1], '6') !== false, 'the instalment count');
	for ($i = 1; $i < count($all); $i++) {
		pg_assert_same('', $all[$i][2], 'no invented fee on row ' . $i);
	}

	$rows = pg_civ_payment_method_rows(array('ecommerce' => true, 'card' => false, 'installments' => 6, 'offline' => true));
	pg_assert_same(1, count($rows), 'instalments only with the card');

	$rows = pg_civ_payment_method_rows(array('ecommerce' => true, 'card' => true, 'installments' => 1));
	pg_assert_same(1, count($rows), 'one instalment is no instalment row');
}

// Rows fill the bound body in the first row's look; the head stays.
function test_civ_fill_payment_methods_rows()
{
	$tree = civ_pay_test_tabs(0);
	_pg_civ_fill_payment_methods($tree, array(array('Card', 'By card', '0%'), array('Bank <b>', 'Transfer', '')));
	$table = $tree['children'][1]['children'][1]['children'][0]['children'][0];
	$body = $table['children'][1];

	pg_assert_same(2, count($body['children']), 'two rows');
	pg_assert_same(array('Card', 'By card', '0%'), civ_pay_test_texts($body['children'][0]), 'the first row');
	pg_assert_same(array('Bank &lt;b&gt;', 'Transfer', ''), civ_pay_test_texts($body['children'][1]), 'escaped');
	pg_assert_same('sample-row', $body['children'][1]['props']['cssClass'], 'every row copies the first');
	pg_assert_same('text-end', $body['children'][0]['children'][2]['props']['cssClass'], 'the fee cell keeps its class');
	pg_assert_same(array('Method', 'Description', 'Fee'), civ_pay_test_texts($table['children'][0]['children'][0]), 'the head stays');
	pg_assert_same(3, count($tree['children'][0]['children']), 'the three tabs stay');

	$bare = civ_pay_test_el('tbody', '', array(), array('_bindings' => array('section' => 'payment_methods')));
	_pg_civ_fill_payment_methods($bare, array(array('Card', 'By card', '0%')));
	pg_assert_same(array('Card', 'By card', '0%'), civ_pay_test_texts($bare['children'][0]), 'no row to copy: a plain row');
	pg_assert_same('text-end', $bare['children'][0]['children'][2]['props']['cssClass'], 'its fee cell on the right');
}

// Nothing on: the pane, its tab and the tab's li go; an open tab moves on.
function test_civ_fill_payment_methods_empty()
{
	$tree = civ_pay_test_tabs(1);
	_pg_civ_fill_payment_methods($tree, array());
	$nav = $tree['children'][0]['children'];
	$panes = $tree['children'][1]['children'];

	pg_assert_same(2, count($nav), 'two tabs left');
	pg_assert_same('Details', $nav[0]['children'][0]['props']['text'], 'Details stays');
	pg_assert_same('Reviews', $nav[1]['children'][0]['props']['text'], 'Reviews stays');
	pg_assert_same(array('civtab_p_0', 'civtab_p_2'), array($panes[0]['props']['id'], $panes[1]['props']['id']), 'the payment pane is gone');
	pg_assert_same('tab-pane fade show active', $panes[0]['props']['cssClass'], 'the first pane opens');
	pg_assert_same('nav-link active', $nav[0]['children'][0]['props']['cssClass'], 'the first tab is active');

	$plain = civ_pay_test_tabs(0);
	$plain['children'][1]['children'][1]['children'][0]['children'][0]['children'][1]['props']['_bindings'] = array();
	$before = $plain;
	_pg_civ_fill_payment_methods($plain, array());
	pg_assert_same($before, $plain, 'a static table (a saved tree) is untouched');

	// The comments frame drop is unchanged by the generalisation.
	$tree = array('type' => 'root', 'props' => array(), 'children' => array(
		civ_pay_test_el('div', 'col', array(
			civ_pay_test_el('p', '', array(), array('text' => 'Text')),
			array('type' => 'region', 'props' => array('regionType' => 'comments_block'), 'children' => array()),
		)),
	));
	_pg_civ_drop_comments_frame($tree);
	pg_assert_same(1, count($tree['children'][0]['children']), 'the comments block still goes');
}

// The renderer fills the table before the frames are dropped; the canvas
// fades the bound body when no method is on; the starter binds the body and
// names no fee.
function test_civ_payment_methods_wiring()
{
	$catalog = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_catalog.php');
	$start = strpos($catalog, 'function _render_system_widget_catalog_item_view(');
	$body = substr($catalog, $start, 30000);
	$fill = strpos($body, '_pg_civ_fill_payment_methods($tree_decoded, pg_civ_payment_method_rows(_pg_civ_payment_settings()));');
	pg_assert_true($fill !== false, 'the renderer fills the table');
	pg_assert_true($fill < strpos($body, '_pg_sw_mark_visibility($tree_decoded'), 'before the visibility marks and the split');

	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/designer_screen.php');
	pg_assert_true(strpos($screen, "'payment_methods'      => function_exists('pg_civ_payment_method_rows')") !== false, 'the editor gets the flag');

	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_true(strpos($js, "return b.section === 'payment_methods' && !!flags && flags.payment_methods === false;") !== false, 'the canvas twin');
	pg_assert_true(strpos($js, "out.push(['payment_methods',") !== false, 'the Section list names it');
	$a = strpos($js, "// Pane 2 — Checkout Options");
	$b = strpos($js, "// Pane 3 — Reviews", $a);
	$pane = substr($js, $a, $b - $a);
	pg_assert_true(strpos($pane, "_bindings: { section: 'payment_methods' }") !== false, 'the starter binds the body');
	pg_assert_true(!preg_match('/TL\'|\'%0\'|\d%\'/', $pane), 'the starter names no fee');
}
