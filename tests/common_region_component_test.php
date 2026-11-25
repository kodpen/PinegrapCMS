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

function test_designer_component_style_choice()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/designer_access.php');
	$designs = array(9, 4, 7);
	$placed = array(
		array('style_id' => 7, 'binned' => true),
		array('style_id' => 3, 'binned' => false),
		array('style_id' => 9, 'binned' => false),
	);
	pg_assert_same(4, pg_designer_component_style(4, $placed, $designs), 'the design asked for');
	pg_assert_same(9, pg_designer_component_style(0, $placed, $designs), 'a design placing it, outside the bin, that is a visual design');
	pg_assert_same(9, pg_designer_component_style(3, $placed, $designs), 'a classic style asked for is passed over');
	pg_assert_same(4, pg_designer_component_style(0, array(), $designs), 'else the first visual design');
	pg_assert_same(0, pg_designer_component_style(5, $placed, array()), 'none without a visual design');
}

function test_common_region_ids_from_categories()
{
	pg_assert_same(array(3, 12), pg_common_region_ids_from_categories(array(
		'common_region:12', 'cregion:5', 'common_region:3', 'common_region:3', 'common_region:0',
		'common_region:x', 'template:a', '', 'common_region:7 ',
	)), 'only well-formed common region categories, each once, in order');
	pg_assert_same(array(), pg_common_region_ids_from_categories(array()));
}

// A converted common region (and a designed pop-up) is drawn after the page's
// own passes for page conditions and page links have run: the component's
// drawing decides its own conditions and points its links itself.
function test_common_region_component_finish_decides_page_conditions()
{
	$signed_in = array('join' => 'all', 'conds' => array(array('token' => '__visit_signed_in', 'op' => '', 'operand' => '')));
	$signed_out = array('join' => 'all', 'conds' => array(array('token' => '__visit_signed_in', 'op' => 'empty', 'operand' => '')));
	$html = '<div>' . pg_page_fact_wrap('<p>Members only</p>', $signed_in)
		. pg_page_fact_wrap('<p>Sign in</p>', $signed_out) . '</div>';

	// An e-mail belongs to no visit: signed out.
	$out = pg_common_region_component_finish($html, true);
	pg_assert_false(strpos($out, 'Members only') !== false, 'a signed-in band is not shown to a signed-out visit: ' . $out);
	pg_assert_contains('<p>Sign in</p>', $out);
	pg_assert_false(strpos($out, '<!--pg-pf:') !== false, 'no condition marker is left');
}

function test_common_region_component_finish_page_link_marker_goes()
{
	$out = pg_common_region_component_finish('<p><span data-pg-page-link="0">x</span></p>', true);
	pg_assert_same('<p><span>x</span></p>', $out, 'the page-link marker never reaches the visitor');
}

function test_common_region_component_usage_read()
{
	$queries = array();
	$fetch = function ($sql) use (&$queries) {
		$queries[] = $sql;
		if (strpos($sql, 'FROM shared_components') !== false && strpos($sql, 'id IN') !== false) {
			// 7 draws region 3; 9 is a second row made from it and draws nothing.
			return array(
				array('id' => '7', 'category' => 'common_region:3'),
				array('id' => '9', 'category' => 'common_region:3'),
			);
		}
		if (strpos($sql, 'FROM shared_components') !== false) {
			return array(array('id' => '7', 'category' => 'common_region:3'), array('id' => '9', 'category' => 'common_region:3'));
		}
		if (strpos($sql, 'cregion_id IN') !== false) {
			return array(array('cregion_id' => '3', 'cregion_name' => 'Footer'));
		}
		if (strpos($sql, 'FROM cregion') !== false) {
			// A designer region printing the footer's tag: its own tag counts too.
			return (strpos($sql, 'Outer') === false)
				? array(array('cregion_name' => 'Outer', 'cregion_content' => '<p><cregion>Footer</cregion></p>'), array('cregion_name' => 'Plain', 'cregion_content' => '<p>Footer</p>'))
				: array();
		}
		if (strpos($sql, 'FROM style') !== false) {
			return array(
				array('style_id' => '2', 'style_code' => '<body><cregion>Footer</cregion></body>'),
				array('style_id' => '4', 'style_code' => '<body><cregion>Footer2</cregion></body>'),
			);
		}
		if (strpos($sql, 'FROM pregion') !== false) {
			return array(array('pregion_page' => '30', 'pregion_content' => '<div><cregion>Outer</cregion></div>'));
		}
		if (strpos($sql, 'FROM page') !== false) {
			return array(
				array('page_id' => '10', 'page_name' => 'home', 'page_folder' => '1', 'style_id' => '2', 'style_name' => 'Classic'),
				array('page_id' => '30', 'page_name' => 'about', 'page_folder' => '1', 'style_id' => '5', 'style_name' => 'Other'),
				array('page_id' => '40', 'page_name' => 'shop', 'page_folder' => '1', 'style_id' => '6', 'style_name' => 'Visual'),
			);
		}
		return array();
	};
	$trees = array(
		array('page_id' => '40', 'tree_json' => '{"type":"root","children":[{"type":"region","props":{"regionType":"common","regionName":"Footer"}}]}'),
		array('page_id' => '41', 'tree_json' => '{"type":"root","children":[{"type":"region","props":{"regionType":"page","regionName":"Footer"}}]}'),
	);

	$out = pg_shared_component_region_usage_read(array(7, 9, 8), $trees, $fetch);
	pg_assert_same(array(7), array_keys($out), 'only the row that draws the region');
	$pages = array();
	foreach ($out[7] as $u) {
		$pages[] = $u['page_id'];
		pg_assert_same('Footer', $u['common_region']);
	}
	sort($pages);
	pg_assert_same(array(10, 30, 40), $pages, 'style tag (not Footer2), page region through Outer, designer tree node');

	$queries = array();
	$none = function ($sql) use (&$queries) {
		$queries[] = $sql;
		return array();
	};
	pg_assert_same(array(), pg_shared_component_region_usage_read(array(5), $trees, $none));
	pg_assert_same(1, count($queries), 'one query when no row is a converted region');
	pg_assert_same(array(), pg_shared_component_region_usage_read(array(), $trees, $none));
	pg_assert_same(1, count($queries), 'no query for no ids');
}
