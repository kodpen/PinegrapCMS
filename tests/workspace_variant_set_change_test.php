<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the variant set a Workspace change adds (kind variant_set in
 * includes/workspace/changes.php): the variant rows and price rules as a
 * change carries them (ws_change_variant_rows(), ws_change_price_rules(),
 * pure), the matrix the card draws (the attribute axes and each row's price
 * from pg_variant_price_compute(), in integer minor units), the input the
 * catalogue's set writer takes, and, from source, that the set is checked
 * and written by pg_catalog_variant_set_create() and its shared check.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/changes.php');
require_once(__DIR__ . '/helpers/source_reader.php');

// The limit the catalogue gives the field: at most 100 rows, a SKU or a
// short description of at most 100 characters.
function workspace_variant_set_change_test_limit()
{
	return array(100, 100);
}

// The source of one function of a Workspace file.
function workspace_variant_set_change_test_source($name, $file = 'changes.php')
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/' . $file);
	$start = strpos($source, 'function ' . $name . '(');

	pg_assert_true($start !== false, $name . ' is in ' . $file);

	$end = strpos($source, "\nfunction ", $start + 1);

	return substr($source, $start, (($end === false) ? strlen($source) : $end) - $start);
}

// Two colours by two sizes, the first row priced by hand.
function workspace_variant_set_change_test_rows()
{
	return array(
		array('name' => 'TS-RED-S', 'short_description' => 'T-shirt red S', 'attributes' => array(array('attribute_id' => 1, 'option_id' => 11), array('attribute_id' => 2, 'option_id' => 21)), 'price' => 9900),
		array('name' => 'TS-RED-M', 'attributes' => array(array('attribute_id' => 1, 'option_id' => 11), array('attribute_id' => 2, 'option_id' => 22)), 'quantity' => 5),
		array('name' => 'TS-BLUE-M', 'attributes' => array(array('attribute_id' => 1, 'option_id' => '12'), array('attribute_id' => 2, 'option_id' => 22))),
	);
}

// The catalogue: a create-only kind of the shop right, tagged and filed as
// the product group it makes, its rows and rules fields of their own kinds.
function test_workspace_variant_set_kind_in_the_catalogue()
{
	$types = pg_test_ws_change_types();

	pg_assert_true(isset($types['variant_set']), 'variant_set is a kind of change');
	pg_assert_same(array('create'), $types['variant_set']['actions'], 'only added');
	pg_assert_same('ecommerce', $types['variant_set']['right'], 'with the shop right');
	pg_assert_same('product_group', $types['variant_set']['tag'], 'tagged as the group it makes');
	pg_assert_same('product_groups', $types['variant_set']['table'], 'in the groups table');
	pg_assert_same(array('name'), $types['variant_set']['required'], 'a name is needed');
	pg_assert_same(array('variants', array(100, 100)), array_slice($types['variant_set']['create']['variants'], 0, 2), 'the rows field');
	pg_assert_same('price_rules', $types['variant_set']['create']['price_rules'][0], 'the rules field');
	pg_assert_same(array('money', 2147483647), array_slice($types['variant_set']['create']['price'], 0, 2), 'the base price, bounded as products.price');
	pg_assert_same(array('ids', 'product_group'), array_slice($types['variant_set']['create']['parent_id'], 0, 2), 'a parent group');
	pg_assert_contains('variant_set', workspace_variant_set_change_test_skipped(), 'no scheduled action adds one');
}

// The kinds a scheduled action leaves alone, read from scheduled.php.
function workspace_variant_set_change_test_skipped()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/scheduled.php');

	pg_assert_true((bool) preg_match('/function ws_scheduled_change_skipped\(\)\s*\{\s*return array\(([^)]*)\);/', $source, $match), 'ws_scheduled_change_skipped() is one list');

	preg_match_all("/'([a-z_]+)'/", $match[1] ?? '', $kinds);

	return $kinds[1];
}

// Rows in one shape: text trimmed, ids and the price whole numbers, a
// price left out priced by the rules, a quantity tracking the stock.
function test_workspace_variant_set_rows_are_read_in_one_shape()
{
	$rows = ws_change_variant_rows(workspace_variant_set_change_test_rows(), workspace_variant_set_change_test_limit());

	pg_assert_true(is_array($rows), 'the rows are taken');
	pg_assert_same(3, count($rows), 'three rows');
	pg_assert_same(array('name', 'short_description', 'gtin', 'attributes', 'price', 'quantity'), array_keys($rows[0]), 'every field, in order');
	pg_assert_same(9900, $rows[0]['price'], 'a price given');
	pg_assert_same(null, $rows[1]['price'], 'a price left to the rules');
	pg_assert_same(5, $rows[1]['quantity'], 'a stock count');
	pg_assert_same(null, $rows[0]['quantity'], 'no stock tracked');
	pg_assert_same(array('attribute_id' => 1, 'option_id' => 12), $rows[2]['attributes'][0], 'ids sent as text are numbers');
	pg_assert_same('', $rows[2]['short_description'], 'no short description');
}

function test_workspace_variant_set_rows_refusals()
{
	$limit = workspace_variant_set_change_test_limit();
	$base = workspace_variant_set_change_test_rows();

	$cases = array(
		'not a list'           => 'TS-RED-S',
		'a row not an object'  => array('TS-RED-S', 'TS-RED-M'),
		'no SKU'               => array(array('name' => ' ') + $base[0], $base[1]),
		'a SKU twice'          => array($base[0], array('name' => 'ts-red-s') + $base[1]),
		'a SKU too long'       => array(array('name' => str_repeat('a', 101)) + $base[0], $base[1]),
		'no option pairs'      => array(array('attributes' => array()) + $base[0], $base[1]),
		'an attribute twice'   => array(array('attributes' => array(array('attribute_id' => 1, 'option_id' => 11), array('attribute_id' => 1, 'option_id' => 12))) + $base[0], $base[1]),
		'a pair without ids'   => array(array('attributes' => array(array('attribute_id' => 1))) + $base[0], $base[1]),
		'a price in lira'      => array(array('price' => 99.5) + $base[0], $base[1]),
		'a price as text'      => array(array('price' => '9900') + $base[0], $base[1]),
		'a negative price'     => array(array('price' => -1) + $base[0], $base[1]),
		'a price over the INT' => array(array('price' => 2147483648) + $base[0], $base[1]),
		'stock below zero'     => array(array('quantity' => -1) + $base[0], $base[1]),
		'stock not whole'      => array(array('quantity' => 1.5) + $base[0], $base[1]),
		'too many rows'        => array_fill(0, 101, $base[0]),
	);

	foreach ($cases as $what => $value) {
		$out = ws_change_variant_rows($value, $limit);

		pg_assert_true(is_string($out) && ($out !== ''), $what . ' is refused');
	}

	pg_assert_contains('variants[1].name', ws_change_variant_rows(array($base[0], array('name' => 'TS-RED-S') + $base[1]), $limit), 'the refusal names the row and the field');
}

// Rules as POST /product-groups/variant-sets takes them, laid out as the
// catalogue keeps them; an amount not in whole minor units is refused, not
// rounded.
function test_workspace_variant_set_price_rules()
{
	$rules = ws_change_price_rules(array(
		array('attribute_id' => 2, 'mode' => 'adjust', 'options' => array(array('option_id' => 22, 'amount' => 1500))),
		array('attribute_id' => 1, 'mode' => 'adjust', 'options' => array(array('option_id' => 12, 'percent' => 10))),
	));

	pg_assert_true(is_array($rules), 'the rules are taken');
	pg_assert_same(2, count($rules), 'two attributes');
	pg_assert_same(array('attribute_id' => 2, 'mode' => 'adjust', 'options' => array(array('option_id' => 22, 'amount' => 1500))), $rules[0], 'in the canonical shape');

	foreach (array(
		'an amount in lira'  => array(array('attribute_id' => 2, 'mode' => 'adjust', 'options' => array(array('option_id' => 22, 'amount' => 15.5)))),
		'an unknown mode'    => array(array('attribute_id' => 2, 'mode' => 'double', 'options' => array(array('option_id' => 22, 'amount' => 15)))),
		'two bases'          => array(array('attribute_id' => 2, 'mode' => 'set', 'options' => array(array('option_id' => 22, 'amount' => 15))), array('attribute_id' => 1, 'mode' => 'set', 'options' => array(array('option_id' => 11, 'amount' => 15)))),
		'not a list'         => 'adjust',
	) as $what => $value) {
		$out = ws_change_price_rules($value);

		pg_assert_true(is_string($out) && ($out !== ''), $what . ' is refused');
	}

	pg_assert_same(array(), ws_change_price_rules(array()), 'no rules');
}

// The card's matrix: one column per attribute in the order the rows name
// them, an option's label in each cell, each price in minor units from the
// rules on the base unless the row has its own.
function test_workspace_variant_set_matrix()
{
	$values = array(
		'price'       => 10000,
		'variants'    => ws_change_variant_rows(workspace_variant_set_change_test_rows(), workspace_variant_set_change_test_limit()),
		'price_rules' => ws_change_price_rules(array(
			array('attribute_id' => 2, 'mode' => 'adjust', 'options' => array(array('option_id' => 22, 'amount' => 1500))),
			array('attribute_id' => 1, 'mode' => 'adjust', 'options' => array(array('option_id' => 12, 'percent' => 10))),
		)),
	);
	$labels = array(
		'attributes' => array(1 => 'Colour', 2 => 'Size'),
		'options'    => array(11 => 'Red', 12 => 'Blue', 21 => 'S', 22 => 'M'),
	);

	$matrix = ws_change_variant_set_matrix($values, $labels);

	pg_assert_same(array('Colour', 'Size'), $matrix['axes'], 'the axes, in the rows\' order');
	pg_assert_same(array('Red', 'S'), $matrix['rows'][0]['options'], 'an option per axis');
	pg_assert_same('TS-RED-S', $matrix['rows'][0]['sku'], 'the SKU');
	pg_assert_same(9900, $matrix['rows'][0]['price'], 'a price given stays');
	pg_assert_true($matrix['rows'][0]['manual'], 'and is marked as typed');
	pg_assert_same(11500, $matrix['rows'][1]['price'], 'M adds 15.00');
	pg_assert_false($matrix['rows'][1]['manual'], 'priced by the rules');
	pg_assert_same(12650, $matrix['rows'][2]['price'], 'blue M: 115.00 and 10 percent');
	pg_assert_true(is_int($matrix['rows'][2]['price']), 'in integer minor units');

	$unknown = ws_change_variant_set_matrix($values, array('attributes' => array(), 'options' => array()));

	pg_assert_same(array('#1', '#2'), $unknown['axes'], 'an attribute without a label by its number');
	pg_assert_same(array('#11', '#21'), $unknown['rows'][0]['options'], 'an option the same');

	$no_rules = ws_change_variant_set_matrix(array('price_rules' => array()) + $values, $labels);

	pg_assert_same(10000, $no_rules['rows'][1]['price'], 'without rules a row without a price takes the base');
}

// The writer's input: the group, the rows priced as the set writer prices
// them, the axes, defaults that keep a new set off sale.
function test_workspace_variant_set_writer_input()
{
	$values = array(
		'name'        => 'TS',
		'short_description' => 'T-shirt',
		'price'       => 10000,
		'parent_id'   => array(4),
		'variants'    => ws_change_variant_rows(workspace_variant_set_change_test_rows(), workspace_variant_set_change_test_limit()),
		'price_rules' => array(),
	);

	$data = ws_change_variant_set_data($values, 7);

	pg_assert_same('TS', $data['name'], 'the name');
	pg_assert_same('T-shirt', $data['short_description'], 'the short description');
	pg_assert_same(4, $data['parent_id'], 'the parent');
	pg_assert_same(false, $data['enabled'], 'off sale unless asked');
	pg_assert_same('0', $data['common']['enabled'], 'its products too');
	pg_assert_same(7, $data['common']['user'], 'in the name of the person who applies it');
	pg_assert_same(10000, $data['price_base'], 'the base');
	pg_assert_same(null, $data['price_rules'], 'no rules');
	pg_assert_same(9900, $data['variants'][0]['price'], 'a row priced by hand');
	pg_assert_true($data['variants'][0]['price_manual'], 'kept over the rules');
	pg_assert_same(10000, $data['variants'][1]['price'], 'a row without a price starts from the base');
	pg_assert_false($data['variants'][1]['price_manual'], 'and is priced by the rules');
	pg_assert_same('1', $data['variants'][1]['inventory'], 'a stock count tracks the stock');
	pg_assert_same(5, $data['variants'][1]['inventory_quantity'], 'the count');
	pg_assert_same('0', $data['variants'][0]['inventory'], 'none, none tracked');
	pg_assert_same(array(array('id' => 1, 'default_option_id' => 0), array('id' => 2, 'default_option_id' => 0)), $data['attribute_meta'], 'the axes');
	pg_assert_same(array(), pg_catalog_variant_set_check($data), 'the set writer\'s check takes it');

	$on = ws_change_variant_set_data(array('enabled' => true) + $values, 7);

	pg_assert_same(true, $on['enabled'], 'on sale when asked');
	pg_assert_same('1', $on['common']['enabled'], 'its products too');
	pg_assert_same(0, ws_change_variant_set_data(array('parent_id' => array()) + $values, 7)['parent_id'], 'no parent named: 0, which the writer files under the top group');
}

// A set proposed without a parent is filed under the catalogue's top group,
// as pg_catalog_group_create_input() files a new group: the writer turns the
// 0 of the data built (which reads no table) into pg_catalog_group_top_id()
// before the set writer sees it. A group with parent 0 is a second root,
// which no catalogue page, search or product feed reads.
function test_workspace_variant_set_without_a_parent_goes_under_the_top_group()
{
	$writer = workspace_variant_set_change_test_source('ws_change_write_variant_set');
	$when = strpos($writer, "if (\$data['parent_id'] <= 0) {");
	$resolved = strpos($writer, "\$data['parent_id'] = pg_catalog_group_top_id();");
	$written = strpos($writer, 'pg_catalog_variant_set_create($data, ');

	pg_assert_true($when !== false, 'only when no parent was named');
	pg_assert_true($resolved !== false, 'the parent becomes the catalogue\'s top group');
	pg_assert_true($written !== false, 'the set writer is given that data');
	pg_assert_true(($when !== false) && ($resolved !== false) && ($written !== false) && ($when < $resolved) && ($resolved < $written), 'before the set is written');
}

// Checked and written by the catalogue's set writer: the refusal asks its
// check and whether the rules and options are this shop's, the writer is
// pg_catalog_variant_set_create() and writes no row itself, and both the
// proposal and the apply ask the refusal (ws_change_check()).
function test_workspace_variant_set_is_checked_and_written_by_the_set_writer()
{
	$refusal = workspace_variant_set_change_test_source('ws_change_variant_set_refusal');

	pg_assert_contains('pg_catalog_variant_set_check(', $refusal, 'the writer\'s own check');
	pg_assert_contains('pg_variant_price_rules_owned(', $refusal, 'rules of this shop\'s options');
	pg_assert_contains('product_attribute_options', $refusal, 'option pairs of this shop');
	pg_assert_contains('FROM products WHERE name', $refusal, 'no SKU taken');
	pg_assert_contains('FROM product_groups WHERE name', $refusal, 'no group name taken');

	$writer = workspace_variant_set_change_test_source('ws_change_write_variant_set');

	pg_assert_contains('pg_catalog_variant_set_create(', $writer, 'the set writer');
	pg_assert_contains('pg_audit_record(', $writer, 'a line per product in the audit trail');
	pg_assert_false(stripos($writer, 'INSERT') !== false, 'no row written by hand');

	pg_assert_contains("case 'variant_set':\n            return ws_change_variant_set_refusal(\$values);",
		workspace_variant_set_change_test_source('ws_change_check'), 'asked at proposal and at apply');
}

// The assistants and the applications are told about it: Pinegrap AI's
// guide and tool, the proposal route's changes parameter.
function test_workspace_variant_set_is_offered_to_the_proposers()
{
	pg_assert_contains("case 'variants':", workspace_variant_set_change_test_source('ws_ai_changes_guide', 'ai.php'), 'Pinegrap AI reads how rows are given');
	pg_assert_contains("case 'price_rules':", workspace_variant_set_change_test_source('ws_ai_changes_guide', 'ai.php'), 'and rules');
	pg_assert_contains('variant set', workspace_variant_set_change_test_source('ws_ai_tools', 'ai.php'), 'its propose_change tool names the rows');
	pg_assert_contains('variant_set', workspace_variant_set_change_test_source('ws_api_changes_param', 'api_proposals.php'), 'the proposal routes name the kind');
}

// The card: the change's rows drawn as the matrix, priced as the writer
// prices them, the rules left to the prices; workspace.js draws the table.
function test_workspace_variant_set_card_draws_the_matrix()
{
	$map = workspace_variant_set_change_test_source('ws_changes_map');

	pg_assert_contains('ws_change_variant_set_matrix($values, ws_change_variant_set_labels(', $map, 'the matrix of the proposed values');
	pg_assert_contains("'price' => ws_money_out(\$variant['price'])", $map, 'prices shown from minor units');
	pg_assert_contains("if (\$field[0] === 'price_rules') {\n                continue;", $map, 'the rules shown in the prices');

	$script = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');

	pg_assert_contains('if (field.matrix) {', $script, 'workspace.js draws the matrix');
	pg_assert_contains('field.matrix.rows.forEach(', $script, 'a row per variant');
}
