<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the frame split of a classic style moved to the visual designer
 * (pg_classic_style_frame_split() in includes/fn/classic_style_design.php):
 * the page regions go into each page's tree, what comes before and after
 * them becomes shared components, and the page still prints what the whole
 * frame printed.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/classic_style_design.php');
// The starting sites' classic styles (_test_dil_starting_styles()) and the
// node walk (_test_csd_nodes()); already loaded when the runner reads the
// files in name order.
require_once(__DIR__ . '/classic_style_design_test.php');
require_once(__DIR__ . '/designer_import_legacy_test.php');

function _test_lfs_frame($body)
{
	$doc = '<!DOCTYPE html><html><head><title>Site</title></head><body>' . $body . '</body></html>';
	return pg_classic_style_frame($doc, function ($name) {
		return array('regionType' => 'common');
	});
}

// A tree's nodes as short labels, depth first: a node's class (else its
// type), a region's kind, a placed component's id, a part's place.
function _test_lfs_shape($nodes)
{
	$out = array();
	foreach ((array)$nodes as $n) {
		if (array_key_exists('_part', $n)) {
			$out[] = 'part' . $n['_part'];
			continue;
		}
		if ($n['type'] === 'region') {
			$label = $n['props']['regionType'];
		} elseif ($n['type'] === 'shared_ref') {
			$label = 'ref' . $n['props']['sharedId'];
		} else {
			$label = (string)($n['props']['cssClass'] ?? $n['type']);
		}
		if (!empty($n['children'])) $label .= '[' . implode(',', _test_lfs_shape($n['children'])) . ']';
		$out[] = $label;
	}
	return $out;
}

// The split frame's page drawn as the site draws it: the page tree, each
// part's marker replaced by the part drawn on its own (_expand_shared_refs()).
function _test_lfs_page_html($frame)
{
	$split = pg_classic_style_frame_split($frame['frame']);
	if ($split === null) return null;
	$made = array();
	$parts = array();
	foreach ($split['parts'] as $i => $part) {
		$made[$i] = array('id' => 100 + $i, 'name' => 'Part ' . $i);
		$parts[100 + $i] = pg_di_root($part['nodes']);
	}
	$tree = pg_classic_style_split_page_tree($frame['root_props'], $split, $made);
	$html = _render_tree_node($tree, 0);
	return preg_replace_callback('/<!--pg-shared-ref:(\d+)-->/', function ($m) use ($parts) {
		return _render_tree_node($parts[(int)$m[1]], 0);
	}, $html);
}

function _test_lfs_norm($html)
{
	return trim(preg_replace('/\s+/', ' ', (string)$html));
}

function test_lfs_no_page_region_no_split()
{
	$f = _test_lfs_frame('<header class="top"><cregion>header</cregion></header><main class="main"><system></system></main>');
	pg_assert_same(null, pg_classic_style_frame_split($f['frame']));
}

function test_lfs_regions_at_the_root_give_one_top_and_one_bottom()
{
	$f = _test_lfs_frame('<header class="top">a</header><pregion></pregion><aside class="mid">b</aside><pregion></pregion><footer class="end">c</footer>');
	$s = pg_classic_style_frame_split($f['frame']);
	pg_assert_same(array('part0', 'page', 'mid', 'page', 'part1'), _test_lfs_shape($s['children']), 'the content runs from the first page region to the last');
	pg_assert_same(array('top', 'bottom'), array_column($s['parts'], 'side'));
	pg_assert_same(array('top:1', 'bottom:1'), array_column($s['parts'], 'key'));
	pg_assert_same(array(1, 1), array_column($s['parts'], 'of'));
	pg_assert_same(array('top'), _test_lfs_shape($s['parts'][0]['nodes']));
	pg_assert_same(array('end'), _test_lfs_shape($s['parts'][1]['nodes']));
}

function test_lfs_a_wrapper_around_chrome_and_content_gives_a_part_per_level()
{
	$f = _test_lfs_frame('<nav class="nav">n</nav><div class="main"><span class="up">u</span>'
		. '<section class="s1"><pregion></pregion></section><section class="s2"><pregion></pregion></section>'
		. '<footer class="foot">f</footer><span class="down">d</span></div>');
	$s = pg_classic_style_frame_split($f['frame']);
	pg_assert_same(array('part0', 'main[part1,s1[page],s2[page],part2]'), _test_lfs_shape($s['children']), 'the wrapper is copied, its chrome shared');
	pg_assert_same(array('top:1', 'top:2', 'bottom:1'), array_column($s['parts'], 'key'), 'parts in document order');
	pg_assert_same(array(2, 2, 1), array_column($s['parts'], 'of'));
	pg_assert_same(array('foot', 'down'), _test_lfs_shape($s['parts'][2]['nodes']), 'a run of siblings is one part');
}

function test_lfs_one_page_region_is_the_content()
{
	$f = _test_lfs_frame('<div class="wrap"><h1 class="title">t</h1><div class="body"><pregion></pregion></div></div><footer class="foot">f</footer>');
	$s = pg_classic_style_frame_split($f['frame']);
	pg_assert_same(array('wrap[part0,body[page]]', 'part1'), _test_lfs_shape($s['children']));
	pg_assert_same(array('top', 'bottom'), array_column($s['parts'], 'side'));
}

function test_lfs_nothing_around_the_content_makes_no_part()
{
	$f = _test_lfs_frame('<pregion></pregion>');
	$s = pg_classic_style_frame_split($f['frame']);
	pg_assert_same(array(), $s['parts']);
	pg_assert_same(array('page'), _test_lfs_shape($s['children']));
}

function test_lfs_a_page_region_inside_a_placed_component_is_not_the_frame_s()
{
	$frame = pg_di_root(array(
		pg_di_mk('shared_ref', array('sharedId' => 4, 'sharedName' => 'Head')),
		pg_di_mk('region', array('regionType' => 'page', 'regionName' => '')),
	));
	$s = pg_classic_style_frame_split($frame);
	pg_assert_same(array('part0', 'page'), _test_lfs_shape($s['children']));
	pg_assert_same(array('ref4'), _test_lfs_shape($s['parts'][0]['nodes']));
}

function test_lfs_part_names()
{
	pg_assert_same('Main top', pg_classic_style_part_name('Main', array('side' => 'top', 'number' => 1, 'of' => 1)));
	pg_assert_same('Main bottom', pg_classic_style_part_name('Main', array('side' => 'bottom', 'number' => 1, 'of' => 1)));
	pg_assert_same('Main top 2', pg_classic_style_part_name('Main', array('side' => 'top', 'number' => 2, 'of' => 2)));
	pg_assert_same('Main bottom 1', pg_classic_style_part_name('Main', array('side' => 'bottom', 'number' => 1, 'of' => 3)));
}

function test_lfs_page_tree_places_each_part()
{
	$f = _test_lfs_frame('<nav class="nav">n</nav><div class="main"><span class="up">u</span><pregion></pregion><footer class="foot">f</footer></div>');
	$s = pg_classic_style_frame_split($f['frame']);
	$t = pg_classic_style_split_page_tree(array('cssClass' => 'home', 'title' => 'Site'), $s,
		array(0 => array('id' => 7, 'name' => 'Main top 1'), 1 => array('id' => 8, 'name' => 'Main top 2'), 2 => array('id' => 9, 'name' => 'Main bottom')));
	pg_assert_same('root', $t['type']);
	pg_assert_same('home', $t['props']['cssClass']);
	pg_assert_same(array('ref7', 'main[ref8,page,ref9]'), _test_lfs_shape($t['children']));
	pg_assert_same('sd_frame', $t['children'][0]['_id'], 'the first ref keeps the frame\'s id');
	pg_assert_same('sd_frame_2', $t['children'][1]['children'][0]['_id']);
	pg_assert_same('sd_frame_3', $t['children'][1]['children'][2]['_id']);
	pg_assert_same('Main bottom', $t['children'][1]['children'][2]['props']['sharedName']);

	$ids = array();
	foreach (_test_csd_nodes($t['children']) as $n) $ids[] = $n['_id'] ?? '';
	pg_assert_same(count($ids), count(array_unique($ids)), 'every node id once');
	pg_assert_false(in_array('', $ids, true), 'every node has an id');
}

// Every classic style of both starting sites: the page drawn from its tree
// and its parts prints what the whole frame printed, page regions in the
// same order.
function test_lfs_starting_site_styles_print_the_same()
{
	$split = 0;
	foreach (array('english_default', 'turkish_default') as $site) {
		foreach (_test_dil_starting_styles(PG_FUNCTIONS_DIR . '/data/backups/' . $site . '/sql.sql') as $id => $code) {
			$label = $site . ' style ' . $id;
			$f = pg_classic_style_frame($code, function ($name) {
				return array('regionType' => 'common');
			});
			$page = _test_lfs_page_html($f);
			if ($page === null) {
				pg_assert_false(strpos(json_encode($f['frame']), '"regionType":"page"') !== false, $label . ': whole only without a page region');
				continue;
			}
			pg_assert_same(_test_lfs_norm(_render_tree_node($f['frame'], 0)), _test_lfs_norm($page), $label . ': the same page');
			$split++;
		}
	}
	pg_assert_true($split > 40, 'most styles are split');
}
