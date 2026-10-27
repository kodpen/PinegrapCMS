<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a common region made a shared component: its content converted
 * into one block marked editable (includes/designer_region_import.php), and
 * the per-request read of which regions a component draws
 * (includes/fn/content.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/designer_region_import.php');

function test_common_region_tree_one_editable_block()
{
	$r = pg_di_common_region_tree('<h2>Hello</h2><p>World</p>', null, array('php' => true));
	pg_assert_same('root', $r['tree']['type']);
	pg_assert_same(1, count($r['tree']['children']), 'one block');

	$block = $r['tree']['children'][0];
	pg_assert_same('semantic', $block['type']);
	pg_assert_same('div', $block['props']['tag']);
	pg_assert_same(1, $block['props']['_editable'], 'the block is an editable area');
	pg_assert_same(2, count($block['children']), 'the content inside it');
	pg_assert_same('Hello', $block['children'][0]['props']['text']);
}

function test_common_region_tree_empty_and_code()
{
	$empty = pg_di_common_region_tree('', null);
	pg_assert_same(1, count($empty['tree']['children']), 'an empty region is still one block to write into');
	pg_assert_same(array(), $empty['tree']['children'][0]['children']);

	$r = pg_di_common_region_tree('<p>x</p><?php echo 1; ?>', null, array('php' => true));
	pg_assert_same('blocks', $r['php']);
	$inner = $r['tree']['children'][0]['children'];
	pg_assert_same('custom_php', $inner[1]['props']['contentType'], 'PHP is a code node inside the block');

	$t = pg_di_common_region_tree('<p><?php echo 1; ?></p>', null, array('php' => false));
	pg_assert_same('text', $t['php'], 'PHP that does not run stays text');
}

function test_common_region_tree_placeholders_stay_regions()
{
	$lookup = function ($name) { return array('regionType' => ($name === 'D') ? 'designer' : 'common'); };
	$r = pg_di_common_region_tree('<div><cregion>D</cregion><cregion>C</cregion></div>', $lookup, array('php' => true));
	$types = array();
	$walk = function ($nodes) use (&$walk, &$types) {
		foreach ($nodes as $n) {
			if ($n['type'] === 'region') $types[$n['props']['regionName']] = $n['props']['regionType'];
			if ($n['type'] === 'shared_ref') $types['ref'] = true;
			if (!empty($n['children'])) $walk($n['children']);
		}
	};
	$walk($r['tree']['children']);
	pg_assert_same(array('D' => 'designer', 'C' => 'common'), $types, 'no nested component is made');
}

function test_common_region_component_category()
{
	pg_assert_same('common_region:12', pg_common_region_component_category('12'));
}

function test_common_region_components_read()
{
	$queries = array();
	$fetch = function ($sql) use (&$queries) {
		$queries[] = $sql;
		return array(
			array('id' => '7', 'category' => 'common_region:3'),
			array('id' => '9', 'category' => 'common_region:3'),
			array('id' => '8', 'category' => 'common_region:5'),
		);
	};

	$read = array();
	$out = pg_common_region_components_read($read, array(3, 5, 4), $fetch);
	pg_assert_same(array(3 => 7, 5 => 8, 4 => 0), $out, 'lowest id wins, 0 when not converted');
	pg_assert_same(1, count($queries));
	pg_assert_contains("'common_region:3', 'common_region:5', 'common_region:4'", $queries[0]);

	$out = pg_common_region_components_read($read, array(4, 3), $fetch);
	pg_assert_same(array(4 => 0, 3 => 7), $out);
	pg_assert_same(1, count($queries), 'ids read before are not asked again');

	pg_assert_same(array(0 => 0), pg_common_region_components_read($read, array(0), $fetch), 'no query for an empty id');
	pg_assert_same(1, count($queries));
}
