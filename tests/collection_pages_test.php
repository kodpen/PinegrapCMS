<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests "New collection with pages" (includes/fn/collection_pages.php and
 * the designer part 'collection'): a make request checked before anything
 * is written (pg_coll_pages_check()), the page placed around the widget
 * (pg_coll_pages_page_tree()), the fields the design save takes
 * (pg_coll_pages_page_fields()) and the endpoint's sub action.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/collection_pages.php');

// A make request for $what that passes every check.
function collection_pages_request($what)
{
	$kinds = pg_coll_pages_widget_kinds();
	$config = array('regionType' => $kinds[$what]);

	if ($what !== 'collection') {
		$config['custom_form_page_id'] = 12;
	}

	return array(
		'what' => $what, 'title' => 'Teachers', 'name' => 'teachers', 'collection' => 'Teachers', 'collection_page_id' => 12,
		'widget_tree' => json_encode(array('_id' => 'root', 'type' => 'root', 'props' => array(), 'children' => array())),
		'widget_config' => json_encode($config),
	);
}

function test_collection_pages_check_passes()
{
	foreach (array('collection', 'list', 'detail') as $what) {
		$res = pg_coll_pages_check(collection_pages_request($what));
		pg_assert_true($res['ok'], $what . ' passes');
		pg_assert_same('root', $res['tree']['type'], $what . ' tree decoded');
	}
}

function test_collection_pages_check_refusals()
{
	$cases = array(
		'what'          => array('collection', array('what' => 'page')),
		'title'         => array('list', array('title' => '  ')),
		'name'          => array('list', array('name' => 'a b')),
		'collection'    => array('collection', array('collection' => '')),
		'widget_tree'   => array('list', array('widget_tree' => '{"type":"row"}')),
		'widget_config' => array('detail', array('widget_config' => json_encode(array('regionType' => 'form_list_view', 'custom_form_page_id' => 12)))),
	);

	foreach ($cases as $field => $case) {
		$res = pg_coll_pages_check(array_merge(collection_pages_request($case[0]), $case[1]));
		pg_assert_true(!$res['ok'], $field . ' refused');
		pg_assert_same($field, $res['field'], $field . ' names its field');
	}

	// A list bound to another form than the collection asked for.
	$res = pg_coll_pages_check(array_merge(collection_pages_request('list'), array('collection_page_id' => 13)));
	pg_assert_same('widget_config', $res['field'], 'a list of another form is refused');
	$res = pg_coll_pages_check(array_merge(collection_pages_request('detail'), array('collection_page_id' => 0)));
	pg_assert_same('collection', $res['field'], 'a detail without a collection is refused');
	foreach (array('a/b', 'a?b', 'a#b', 'a%b', str_repeat('a', 101)) as $bad) {
		$res = pg_coll_pages_check(array_merge(collection_pages_request('list'), array('name' => $bad)));
		pg_assert_same('name', $res['field'], 'the address ' . $bad . ' is refused');
	}
}

function test_collection_pages_page_tree()
{
	$list = pg_coll_pages_page_tree('list', 41, 'teachers-form-list-view-widget', 'Teachers');
	pg_assert_same('root', $list['type']);
	pg_assert_same(2, count($list['children']), 'a list page: heading and widget');
	pg_assert_same('Teachers', $list['children'][0]['children'][0]['props']['text']);
	pg_assert_same('h1', $list['children'][0]['children'][0]['props']['tag']);
	pg_assert_same(41, $list['children'][1]['props']['sharedId']);
	pg_assert_same(array(), $list['children'][1]['children'], 'a shared_ref keeps no children');

	foreach (array('collection', 'detail') as $what) {
		$t = pg_coll_pages_page_tree($what, 7, 'w', 'X');
		pg_assert_same(1, count($t['children']), $what . ': the widget alone');
		pg_assert_same('shared_ref', $t['children'][0]['type']);
	}
}

function test_collection_pages_page_fields()
{
	$tree = pg_coll_pages_page_tree('detail', 7, 'w', '');
	$f = pg_coll_pages_page_fields('teacher', 'Teacher', 0, $tree, true);
	pg_assert_same(0, $f['page_id'], 'a new page');
	pg_assert_same('teacher', $f['page_name'], 'the address is the page name');
	pg_assert_same('Teacher', $f['page_title'], 'the name is the title');
	pg_assert_same(1, $f['page_draft']);
	pg_assert_same(0, $f['page_home'], 'never the home page');
	pg_assert_same($tree, json_decode($f['tree_json'], true));
	pg_assert_same(0, pg_coll_pages_page_fields('a', 'A', 0, $tree, false)['page_draft']);
}

function test_collection_pages_endpoint()
{
	$panel = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/designer.php');
	pg_assert_contains("case 'collection_pages':", $panel, 'the designer endpoint has the sub action');

	// Design-level: not in the list a content-level operator may call.
	preg_match('/\$content_ok = array\(([^;]*)\);/s', $panel, $m);
	pg_assert_true(isset($m[1]) && strpos($m[1], 'collection_pages') === false, 'closed to the content roles');
	$res = pg_coll_pages_panel(array('op' => 'nothing'), array('id' => 1, 'role' => 1));
	pg_assert_same('error', $res['status'], 'an unknown op is refused');
}
