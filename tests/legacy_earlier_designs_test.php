<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the "Designs moved earlier" card: a component holding the page
 * content, the card's rows, a design's dry run entry and its groups, the
 * cursor of a design's run and its two steps, and the wiring of the move
 * screen.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/layouts.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/legacy_to_designer.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/legacy_earlier_designs.php');

function _lte_region($type, $name = '')
{
	return array('type' => 'region', 'props' => array('regionType' => $type, 'regionName' => $name), 'children' => array());
}

function _lte_root($children)
{
	return array('type' => 'root', 'props' => array(), 'children' => $children);
}

function _lte_census($total, $components = array())
{
	return array('total' => $total, 'kinds' => $total ? array('system' => $total) : array(), 'components' => $components, 'editable' => $total === 0);
}

function test_lte_holds_page_region()
{
	pg_assert_true(pg_lte_holds_page_region(_lte_root(array(array('type' => 'section', 'props' => array(), 'children' => array(_lte_region('page')))))));
	pg_assert_false(pg_lte_holds_page_region(_lte_root(array(_lte_region('designer', 'top')))));
	pg_assert_false(pg_lte_holds_page_region(_lte_root(array(array('type' => 'shared_ref', 'props' => array('sharedId' => 4), 'children' => array(_lte_region('page')))))), 'a placed component is not entered');
	pg_assert_true(pg_lte_holds_page_region(array(_lte_region('page'))), 'a list of nodes');
	pg_assert_false(pg_lte_holds_page_region(null));
}

function test_lte_rows()
{
	$measure = array(
		'censuses' => array(10 => _lte_census(0, array(5)), 11 => _lte_census(2, array(5)), 12 => _lte_census(0, array(7)), 13 => _lte_census(0)),
		'pages' => array(10 => 1, 11 => 1, 12 => 2, 13 => 3),
		'components' => array(5 => _lte_root(array(_lte_region('designer', 'nav'))), 7 => _lte_root(array(_lte_region('page'))), 8 => null),
	);
	$rows = pg_lte_rows(array(2 => 'b set', 1 => 'a set', 3 => 'alone', 4 => 'empty'), $measure, array(1, '2'));
	pg_assert_same(array(
		array('id' => 2, 'name' => 'b set', 'pages' => 1, 'editable' => 1, 'left' => 0, 'before_split' => true, 'back' => true),
		array('id' => 1, 'name' => 'a set', 'pages' => 2, 'editable' => 1, 'left' => 1, 'before_split' => false, 'back' => true),
		array('id' => 3, 'name' => 'alone', 'pages' => 1, 'editable' => 1, 'left' => 0, 'before_split' => false, 'back' => false),
	), $rows, 'by the order given, a design with no page left out');
	pg_assert_same('a set', pg_lte_row($rows, 1)['name']);
	pg_assert_same(null, pg_lte_row($rows, 4));
	pg_assert_same(array(), pg_lte_rows(array(), array(), array()));
}

function test_lte_plan_entry_and_groups()
{
	$row = array('id' => 9, 'name' => 'home set', 'pages' => 3, 'editable' => 0, 'left' => 3, 'before_split' => false, 'back' => true);
	$pages = array(
		array('page_id' => 1, 'page_name' => 'Home', 'holder' => null, 'tree' => _lte_root(array(_lte_region('designer', 'slider'), _lte_region('menu', 'main'), _lte_region('system')))),
		array('page_id' => 2, 'page_name' => 'About', 'holder' => null, 'tree' => _lte_root(array(_lte_region('common', 'footer'), _lte_region('dynamic', 'news'), _lte_region('menu', 'main')))),
		array('page_id' => 3, 'page_name' => 'Held', 'holder' => 'ayse', 'tree' => _lte_root(array(_lte_region('designer', 'held-only')))),
	);
	$types = array(
		array('page_name' => 'Home', 'kind' => 'catalog_listing', 'state' => 'convert', 'held' => array()),
		array('page_name' => 'Shop', 'kind' => 'catalog_item_view', 'state' => 'file', 'held' => array()),
	);
	$entry = pg_lte_plan_entry($row, $pages, $types, array('slider'));
	pg_assert_same('', $entry['skip']);
	pg_assert_same(array('slider'), $entry['parts']['designer'], 'a held page\'s regions are not counted');
	pg_assert_same(array('footer'), $entry['parts']['common']);
	pg_assert_same(1, count($entry['parts']['widgets']), 'one menu across pages');
	pg_assert_same(1, $entry['custom_layouts']);
	pg_assert_same(array('catalog_listing'), pg_ltd_plan_type_kinds(array($entry)));

	$groups = pg_ltd_plan_groups($entry);
	$editable = array();
	foreach ($groups['editable'] as $g) $editable[$g['key']] = $g['names'];
	pg_assert_same(array('common' => array('footer'), 'designer' => array('slider'), 'widgets' => array(pg_ltd_widget_target_label($entry['parts']['widgets'][0])), 'types' => array('Home')), $editable, 'no page content row: it is in the trees');
	pg_assert_true($groups['common']);
	pg_assert_same(1, count($groups['skipped']));
	pg_assert_same('Held', $groups['skipped'][0]['label']);
	$code = array_column($groups['code'], 'count', 'label');
	pg_assert_same(1, $code[lang('Dynamic regions')]);
	pg_assert_same(1, $code[lang('Designer regions with PHP')]);
	pg_assert_same(1, $code[lang('Pages with a custom layout')]);

	$before = pg_lte_plan_entry(array('before_split' => true) + $row, $pages, $types, array());
	pg_assert_same('before_split', $before['skip']);
	pg_assert_same(array(), pg_ltd_plan_type_kinds(array($before)));
	pg_assert_same(array(), pg_ltd_plan_groups($before)['editable']);
}

function test_lte_state_walks_two_steps()
{
	pg_assert_same(array('page_parts', 'types'), pg_ltd_steps(false, true));
	$state = pg_lte_state_new(array('id' => 9, 'name' => 'home set'), array('id' => 2, 'username' => 'erdal'), 1764000000);
	pg_assert_same(array(9), $state['styles']);
	pg_assert_same(array(9), $state['earlier']);
	pg_assert_same('page_parts', $state['step']);
	pg_assert_same('home set', $state['names']['9']);
	$state = pg_ltd_state_next($state);
	pg_assert_same('types', $state['step']);
	pg_assert_false(pg_ltd_state_done($state));
	$state = pg_ltd_state_next($state);
	pg_assert_true(pg_ltd_state_done($state));
	pg_assert_same(array(9), pg_ltd_run_designs($state), 'the design opens when the run ends');

	$plain = pg_ltd_state_new(array(4), array('id' => 2, 'username' => 'erdal'), 1764000000);
	pg_assert_same('regions', $plain['step'], 'a style moving alone keeps its steps');
}

function test_lte_wiring()
{
	pg_assert_true(strpos(pg_ltd_convert_script('ltd_earlier_convert'), '"form":"ltd_earlier_convert"') !== false);
	pg_assert_true(strpos(pg_ltd_convert_script(), '"form":"ltd_convert"') !== false, 'the dry run card\'s form by default');
	$screen = (string)file_get_contents(PG_FUNCTIONS_DIR . '/legacy_to_designer.php');
	pg_assert_true(strpos($screen, 'validate_token_field()') < strpos($screen, 'pg_lte_run_start('), 'the run starts after the form token');
	pg_assert_true(strpos($screen, "id=\"ltd-back-' . (int)\$d['id']") !== false, 'Back to classic rows are reached from the card');
	$module = (string)file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/legacy_earlier_designs.php');
	pg_assert_true(strpos($module, "if ((int)\$user['role'] > 1)") < strpos($module, 'pg_ltd_state_write($state)'), 'a role above 1 is refused first');
	pg_assert_true(strpos($module, "if (\$row['before_split'])") < strpos($module, 'pg_ltd_state_write($state)'), 'a design moved before the frame split is refused');
	$run = (string)file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/legacy_to_designer_run.php');
	pg_assert_true(strpos($run, "lang('The design is not a Legacy design with pages any more.')") !== false, 'a design gone meanwhile is skipped');
}
