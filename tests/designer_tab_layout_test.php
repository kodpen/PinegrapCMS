<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the page tab layout of the visual editor
 * (pg_designer_tab_layout_normalize(), includes/fn/designer.php): the order
 * of a design's tabs and the groups they sit in, as stored in
 * style.style_tab_layout.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The ids of the tabs, in order, with their group after a colon.
function _test_tab_layout_ids($layout)
{
	$out = array();
	foreach ($layout['tabs'] as $t) {
		$out[] = $t['id'] . (isset($t['g']) ? ':' . $t['g'] : '');
	}
	return implode(',', $out);
}

function test_designer_tab_layout_keeps_the_order()
{
	$l = pg_designer_tab_layout_normalize(array('v' => 1, 'groups' => array(),
		'tabs' => array(array('id' => 14), array('id' => 12), array('id' => 13))), array(12, 13, 14));
	pg_assert_same('14,12,13', _test_tab_layout_ids($l), 'listed order stays');
	pg_assert_same(1, $l['v'], 'version 1');
}

function test_designer_tab_layout_drops_foreign_and_repeated_ids()
{
	$l = pg_designer_tab_layout_normalize(array(
		'tabs' => array(array('id' => 99), array('id' => 13), array('id' => 12), array('id' => 13), array('id' => 'x'), 'junk')),
		array(12, 13));
	pg_assert_same('13,12', _test_tab_layout_ids($l), 'page of another design and the repeat are gone');
}

function test_designer_tab_layout_appends_missing_pages_in_id_order()
{
	$l = pg_designer_tab_layout_normalize(array('tabs' => array(array('id' => 13))), array(15, 12, 13, 14));
	pg_assert_same('13,12,14,15', _test_tab_layout_ids($l), 'unlisted pages follow by page_id');
}

function test_designer_tab_layout_makes_groups_consecutive()
{
	$l = pg_designer_tab_layout_normalize(array(
		'groups' => array('g1' => array('name' => 'Docs', 'color' => 'orange')),
		'tabs'   => array(array('id' => 12), array('id' => 13, 'g' => 'g1'), array('id' => 14),
		                  array('id' => 15, 'g' => 'g1'), array('id' => 16, 'g' => 'g1'))),
		array(12, 13, 14, 15, 16));
	pg_assert_same('12,13:g1,15:g1,16:g1,14', _test_tab_layout_ids($l), 'members pulled to the first one');
	pg_assert_same(array('name' => 'Docs', 'color' => 'orange'), $l['groups']['g1'], 'group kept');
}

function test_designer_tab_layout_drops_empty_and_unknown_groups()
{
	$l = pg_designer_tab_layout_normalize(array(
		'groups' => array(
			'g1'      => array('name' => 'Used', 'color' => 'blue'),
			'g2'      => array('name' => 'Empty', 'color' => 'red'),
			'Bad Id!' => array('name' => 'Invalid', 'color' => 'red'),
		),
		'tabs' => array(array('id' => 12, 'g' => 'g1'), array('id' => 13, 'g' => 'g9'), array('id' => 14, 'g' => 'Bad Id!'))),
		array(12, 13, 14));
	pg_assert_same(array('g1'), array_keys($l['groups']), 'only the group with tabs survives');
	pg_assert_same('12:g1,13,14', _test_tab_layout_ids($l), 'a tab of a missing group loses the group');
}

function test_designer_tab_layout_cleans_names_and_colours()
{
	$long = str_repeat('ş', 80);
	$l = pg_designer_tab_layout_normalize(array(
		'groups' => array('g1' => array('name' => $long, 'color' => 'magenta', 'extra' => 1)),
		'tabs'   => array(array('id' => 12, 'g' => 'g1', 'extra' => 1)),
		'extra'  => 'x'),
		array(12));
	pg_assert_same(60, mb_strlen($l['groups']['g1']['name']), 'name cut to 60 characters');
	pg_assert_same('grey', $l['groups']['g1']['color'], 'unknown colour becomes grey');
	pg_assert_same(array('name', 'color'), array_keys($l['groups']['g1']), 'unknown group fields dropped');
	pg_assert_same(array('id', 'g'), array_keys($l['tabs'][0]), 'unknown tab fields dropped');
	pg_assert_same(array('v', 'groups', 'tabs'), array_keys($l), 'unknown top-level fields dropped');
}

function test_designer_tab_layout_keeps_at_most_fifty_groups()
{
	$groups = array();
	$tabs = array();
	$ids = array();
	for ($i = 1; $i <= 60; $i++) {
		$groups['g' . $i] = array('name' => 'G' . $i, 'color' => 'blue');
		$tabs[] = array('id' => 100 + $i, 'g' => 'g' . $i);
		$ids[] = 100 + $i;
	}
	$l = pg_designer_tab_layout_normalize(array('groups' => $groups, 'tabs' => $tabs), $ids);
	pg_assert_same(50, count($l['groups']), 'fifty groups');
	pg_assert_false(isset($l['tabs'][59]['g']), 'the tab of a dropped group is ungrouped');
}

function test_designer_tab_layout_broken_input_gives_the_default()
{
	$want = '12,13';
	foreach (array('{not json', '', null, '[1,2]', 42) as $bad) {
		$l = pg_designer_tab_layout_normalize($bad, array(13, 12));
		pg_assert_same($want, _test_tab_layout_ids($l), 'pages by id for ' . var_export($bad, true));
		pg_assert_same(array(), $l['groups'], 'no groups for ' . var_export($bad, true));
	}
	$l = pg_designer_tab_layout_normalize('{"v":1,"groups":{"g1":{"name":"A","color":"cyan"}},"tabs":[{"id":13,"g":"g1"}]}', array(12, 13));
	pg_assert_same('13:g1,12', _test_tab_layout_ids($l), 'JSON string is read');
}

function test_designer_tab_layout_export_keeps_groups_an_object()
{
	$l = pg_designer_tab_layout_normalize(null, array(12));
	pg_assert_same('{"v":1,"groups":{},"tabs":[{"id":12}]}', json_encode(pg_designer_tab_layout_export($l)), 'empty groups encode as {}');
	$l = pg_designer_tab_layout_normalize(array('groups' => array('7' => array('name' => 'N', 'color' => 'red')),
		'tabs' => array(array('id' => 12, 'g' => '7'))), array(12));
	pg_assert_same('{"v":1,"groups":{"7":{"name":"N","color":"red"}},"tabs":[{"id":12,"g":"7"}]}',
		json_encode(pg_designer_tab_layout_export($l)), 'numeric group id stays an object key');
}
