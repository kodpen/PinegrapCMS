<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the measure of the move to the visual designer
 * (pg_classic_closed_census() and pg_ltd_closed_figures() in
 * includes/fn/legacy_to_designer.php): the region nodes left in a page's
 * tree and in the components it places, by kind, and the move screen's
 * figures built from them.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/legacy_to_designer.php');

function _lec_region($type, $name = '')
{
	return array('type' => 'region', 'props' => array('regionType' => $type, 'regionName' => $name), 'children' => array());
}

function _lec_ref($id)
{
	return array('type' => 'shared_ref', 'props' => array('sharedId' => $id), 'children' => array());
}

function _lec_root($children)
{
	return array('type' => 'root', 'props' => array(), 'children' => $children);
}

function _lec_box($children)
{
	return array('type' => 'container', 'props' => array(), 'children' => $children);
}

function test_lec_census_fully_editable_tree()
{
	$tree = _lec_root(array(
		_lec_box(array(array('type' => 'content', 'props' => array('contentType' => 'text'), 'children' => array()))),
		_lec_region('page'),
	));
	$c = pg_classic_closed_census($tree);
	pg_assert_same(0, $c['total']);
	pg_assert_same(array(), $c['kinds']);
	pg_assert_same(true, $c['editable']);
}

function test_lec_census_each_kind_left()
{
	$kinds = array('system', 'common', 'designer', 'dynamic', 'menu', 'menu_sequence', 'login', 'ad',
		'cart', 'tag_cloud', 'mobile_switch', 'pdf', 'comments_block');
	foreach ($kinds as $kind) {
		$c = pg_classic_closed_census(_lec_root(array(_lec_box(array(_lec_region($kind, 'x'))))));
		pg_assert_same(1, $c['total'], $kind);
		pg_assert_same(array($kind => 1), $c['kinds'], $kind);
		pg_assert_same(false, $c['editable'], $kind);
	}
}

function test_lec_census_counts_by_kind_in_order()
{
	$tree = _lec_root(array(
		_lec_region('menu', 'Main'),
		_lec_box(array(_lec_region('dynamic', 'Clock'), _lec_region('menu', 'Foot'))),
		_lec_region('system'),
	));
	$c = pg_classic_closed_census($tree);
	pg_assert_same(4, $c['total']);
	pg_assert_same(array('dynamic' => 1, 'menu' => 2, 'system' => 1), $c['kinds']);
}

function test_lec_census_walks_placed_components()
{
	$components = array(
		7 => _lec_root(array(_lec_region('login', 'Sign in'), _lec_ref(8))),
		8 => _lec_root(array(_lec_region('cart'))),
		9 => _lec_root(array(_lec_box(array()))),
	);
	$c = pg_classic_closed_census(_lec_root(array(_lec_ref(7), _lec_ref(9), _lec_region('pdf'))), $components);
	pg_assert_same(array('cart' => 1, 'login' => 1, 'pdf' => 1), $c['kinds']);
	pg_assert_same(3, $c['total']);
	pg_assert_same(array(7, 8, 9), $c['components']);
}

function test_lec_census_component_once_and_no_loop()
{
	$components = array(
		3 => _lec_root(array(_lec_region('ad', 'Top'), _lec_ref(4))),
		4 => _lec_root(array(_lec_ref(3), _lec_region('tag_cloud', 'Tags'))),
	);
	$c = pg_classic_closed_census(_lec_root(array(_lec_ref(3), _lec_ref(3), _lec_ref(4))), $components);
	pg_assert_same(array('ad' => 1, 'tag_cloud' => 1), $c['kinds']);
	pg_assert_same(array(3, 4), $c['components']);
}

function test_lec_census_unknown_component_and_bad_input()
{
	$c = pg_classic_closed_census(_lec_root(array(_lec_ref(99), _lec_ref(0))), array());
	pg_assert_same(0, $c['total']);
	pg_assert_same(array(), $c['components']);
	pg_assert_same(0, pg_classic_closed_census(null)['total']);
	pg_assert_same(0, pg_classic_closed_census('x')['total']);
	// A list of nodes (no root) is walked as the root's children.
	$c = pg_classic_closed_census(array(_lec_region('common', 'Head'), _lec_region('designer', 'Side')));
	pg_assert_same(array('common' => 1, 'designer' => 1), $c['kinds']);
	// A region node with no type is counted under "unknown", never dropped.
	$c = pg_classic_closed_census(_lec_root(array(array('type' => 'region', 'props' => array(), 'children' => array()))));
	pg_assert_same(array('unknown' => 1), $c['kinds']);
}

function test_lec_figures()
{
	$f = pg_ltd_closed_figures(array(
		array('total' => 0, 'kinds' => array()),
		array('total' => 2, 'kinds' => array('dynamic' => 1, 'system' => 1)),
		array('total' => 3, 'kinds' => array('system' => 3)),
	), 4, 2);
	pg_assert_same(3, $f['pages']);
	pg_assert_same(1, $f['editable']);
	pg_assert_same(2, $f['classic']);
	pg_assert_same(4, $f['styles']);
	pg_assert_same(2, $f['designs']);
	// Pages a kind is left on, most pages first, then by kind.
	pg_assert_same(array('system' => 2, 'dynamic' => 1), $f['kinds']);

	$f = pg_ltd_closed_figures(array(), 0, 0);
	pg_assert_same(array(0, 0, 0, array()), array($f['pages'], $f['editable'], $f['classic'], $f['kinds']));
}

function test_lec_placed_ids()
{
	$tree = _lec_root(array(_lec_ref(5), _lec_box(array(_lec_ref(2), _lec_ref(5))), _lec_ref(0)));
	pg_assert_same(array(5, 2), pg_ltd_placed_ids($tree));
	pg_assert_same(array(4), pg_ltd_placed_ids(array(_lec_ref(4))));
	pg_assert_same(array(), pg_ltd_placed_ids(null));
}
