<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the one door into the visual designer and the dry run in three
 * groups: the regions a move leaves, the groups of a style and of a set,
 * their rows, what the door ticks, the designs a run made and the wiring of
 * the move screen, the style list and Edit Style.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/legacy_to_designer_plan.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/legacy_to_designer.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/legacy_to_designer_run.php');

function _led_region($type, $name)
{
	return array('type' => 'region', 'props' => array('regionType' => $type, 'regionName' => $name), 'children' => array());
}

function _led_page($name, $action, $holder = '')
{
	return array('page_id' => 1, 'page_name' => $name, 'source' => 'own', 'action' => $action, 'holder' => $holder);
}

function _led_style($over = array())
{
	return array_merge(array(
		'kind' => 'style', 'id' => 4, 'name' => 'home', 'skip' => '',
		'components' => array(array('kind' => 'common', 'name' => 'Footer', 'exists' => false), array('kind' => 'designer', 'name' => 'Header', 'exists' => true)),
		'pages' => array(_led_page('about', 'tree'), _led_page('blog', 'regions'), _led_page('old', 'keep'), _led_page('shop', 'held', 'Ayşe')),
		'widgets' => array(array('type' => 'menu', 'name' => 'Main', 'kind' => 'menu', 'key' => 'menu_name')),
		'parts' => array('common' => array('Footer', 'Side'), 'designer' => array('Promo'), 'widgets' => array(array('type' => 'cart', 'name' => '', 'kind' => 'cart_link', 'key' => '')), 'page_regions' => 0),
		'stays' => array(array('type' => 'dynamic', 'name' => 'latest-news'), array('type' => 'pdf', 'name' => '')),
		'designer_php' => array('Promo'), 'custom_layouts' => 3, 'warnings' => array(), 'writes' => 9,
	), $over);
}

function test_led_stay_targets()
{
	$tree = pg_di_root(array(
		_led_region('dynamic', 'news'), _led_region('menu', 'Main'), _led_region('dynamic', 'news'),
		array('type' => 'semantic', 'props' => array('tag' => 'div'), 'children' => array(_led_region('system', ''), _led_region('mobile_switch', ''))),
		array('type' => 'shared_ref', 'props' => array('sharedId' => 3), 'children' => array(_led_region('pdf', ''))),
		_led_region('page', ''),
	));
	pg_assert_same(array(array('type' => 'dynamic', 'name' => 'news'), array('type' => 'system', 'name' => ''), array('type' => 'mobile_switch', 'name' => '')),
		pg_ltd_stay_targets($tree), 'each kept region once, in order, no nested component, no page region');
	pg_assert_same(array(), pg_ltd_stay_targets(null), 'bad input');
}

function test_led_groups_of_a_style()
{
	$g = pg_ltd_plan_groups(_led_style());
	$by = array();
	foreach ($g['editable'] as $r) $by[$r['key']] = $r['names'];
	pg_assert_same(array('about', 'blog'), $by['content'], 'pages given a tree or their page regions; kept and held pages are not');
	pg_assert_same(array('Footer', 'Side'), $by['common'], 'a common region once, the frame and the pages together');
	pg_assert_same(array('Promo'), $by['designer'], 'a designer region made before is not counted');
	pg_assert_same(2, count($by['widgets']), 'the frame\'s widgets and the pages\'');
	pg_assert_true($g['common'], 'a common region converts');
	pg_assert_same(4, count($g['code']), 'a row a kind: dynamic, pdf, designer region with PHP, custom layouts');
	pg_assert_same(array('latest-news'), $g['code'][0]['names']);
	pg_assert_same(array(), $g['code'][1]['names'], 'a region with no name counts, unnamed');
	pg_assert_same(3, $g['code'][3]['count'], 'the custom layout pages counted');

	$two = pg_ltd_plan_groups(_led_style(array('stays' => array(array('type' => 'system', 'name' => 'a'), array('type' => 'system', 'name' => 'b')), 'designer_php' => array(), 'custom_layouts' => 0)));
	pg_assert_same(1, count($two['code']), 'regions of one kind share a row');
	pg_assert_same(2, $two['code'][0]['count']);
	pg_assert_same(1, count($g['skipped']));
	pg_assert_same('shop', $g['skipped'][0]['label'], 'a held page is skipped');
	pg_assert_contains('Ayşe', $g['skipped'][0]['reason']);

	$plain = pg_ltd_plan_groups(_led_style(array('components' => array(), 'parts' => array('common' => array(), 'designer' => array(), 'widgets' => array()))));
	pg_assert_false($plain['common'], 'no common region: no note');
}

function test_led_groups_of_a_refused_style_and_sets()
{
	$refused = pg_ltd_plan_groups(_led_style(array('skip' => 'refusal')));
	pg_assert_same(array(), $refused['editable']);
	pg_assert_same(array(), $refused['code']);
	pg_assert_same('home', $refused['skipped'][0]['label'], 'a refused style is skipped');

	$set = array('kind' => 'set', 'name' => 'home set', 'skip' => '', 'styles' => array(
		_led_style(array('name' => 'a')), _led_style(array('name' => 'b', 'skip' => 'refusal')),
	));
	$g = pg_ltd_plan_groups($set);
	pg_assert_same(2, count($g['skipped']), 'the held page and the refused style');
	pg_assert_same(4, count($g['code']), 'the set gathers its styles\' rows once');
	pg_assert_same(1, $g['code'][0]['count'], 'a region two styles share counts once');

	$moved = pg_ltd_plan_groups(array('kind' => 'set', 'name' => 'old set', 'skip' => 'no_pages', 'styles' => array(_led_style())));
	pg_assert_same(array(), $moved['editable'], 'a set moved before lists nothing');
	pg_assert_same(1, count($moved['skipped']));
}

function test_led_groups_html()
{
	$names = array();
	for ($i = 1; $i <= 7; $i++) $names[] = 'p<' . $i . '>';
	$html = pg_ltd_plan_groups_html(array(
		'editable' => array(array('key' => 'content', 'label' => lang('Page content'), 'names' => $names)),
		'code' => array(array('label' => 'Dynamic regions', 'count' => 1, 'names' => array('x'), 'reason' => 'why')),
		'skipped' => array(), 'common' => false,
	));
	pg_assert_contains(lang('Becomes editable'), $html);
	pg_assert_contains(lang('Stays code, drawn as on the site'), $html);
	pg_assert_false(strpos($html, lang('Skipped')) !== false, 'an empty group is not drawn');
	pg_assert_contains(lang(array('string' => 'Show all {var:1}', 'vars' => array('7'))), $html, 'past five names');
	pg_assert_contains('p&lt;7&gt;', $html, 'names escaped');
	pg_assert_false(strpos($html, 'p<7>') !== false);
	pg_assert_same(2, substr_count($html, 'class="pg-list-row"'), 'a row a kind or a thing');
	pg_assert_same('', pg_ltd_plan_groups_html(array('editable' => array(), 'code' => array(), 'skipped' => array(), 'common' => false)));
}

function test_led_door_choice()
{
	$sets = array('sets' => array(array('key' => 'abcdef0123456789', 'styles' => array(3, 4))), 'alone' => array(7 => 'single', 9 => 'blocked'));
	pg_assert_same(array('styles' => array(), 'sets' => array('abcdef0123456789')), pg_ltd_door_choice($sets, 4), 'a style of a set ticks its set');
	pg_assert_same(array('styles' => array(7), 'sets' => array()), pg_ltd_door_choice($sets, '7'), 'a style alone ticks itself');
	pg_assert_same(array('styles' => array(9), 'sets' => array()), pg_ltd_door_choice($sets, 9), 'a refused style shows why it is skipped');
	pg_assert_same(array('styles' => array(), 'sets' => array()), pg_ltd_door_choice($sets, 12), 'one the report does not list');
	pg_assert_same(array('styles' => array(), 'sets' => array()), pg_ltd_door_choice($sets, 0));
}

function test_led_run_designs()
{
	$run = array('styles' => array(3, 4, 7, 8), 'sets' => array(
		array('styles' => array(3, 4), 'design' => 21),
		array('styles' => array(8), 'design' => 0),
	));
	pg_assert_same(array(21, 7), pg_ltd_run_designs($run), 'each set\'s design, then each style moved alone');
	pg_assert_same(array(), pg_ltd_run_designs(array()));
}

function test_led_wiring()
{
	$screen = (string)file_get_contents(PG_FUNCTIONS_DIR . '/legacy_to_designer.php');
	pg_assert_contains("pg_ltd_door_choice(\$sets, (int)\$_GET['style'])", $screen, 'the door ticks the style or its set');
	pg_assert_contains("href=\"legacy_to_designer.php?style=' . (int)\$s['id'] . '#ltd-dry-run\"", $screen, 'the row\'s magic button is the door');
	pg_assert_false(strpos($screen, 'classic=1') !== false, 'no open that writes nothing before Save');
	pg_assert_contains("lang('Convert')", $screen);
	pg_assert_contains("lang('Cancel')", $screen);
	pg_assert_contains("lang('Open the design')", $screen);
	pg_assert_contains('?ended=1#ltd-ended', $screen, 'an ended run lands on its buttons');
	pg_assert_contains('pg_ltd_run_designs(', $screen);

	$list = (string)file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/style_list_edit.php');
	pg_assert_false(strpos($list, 'classic=1') !== false, 'the style list opens no classic style directly');

	$edit = (string)file_get_contents(PG_FUNCTIONS_DIR . '/edit_custom_style.php');
	pg_assert_contains('legacy_to_designer.php?style=', $edit, 'Edit Style points to the move screen');
}
