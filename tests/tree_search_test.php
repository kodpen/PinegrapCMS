<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for search over designer trees (includes/fn/tree_search.php): which
 * texts are read, the replace that leaves wiring alone, the components a
 * tree places and the plain text site search counts in.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function tree_search_test_tree()
{
	return array(
		'type' => 'root',
		'props' => array(),
		'children' => array(
			array('id' => 'h', 'type' => 'content', 'props' => array('contentType' => 'heading', 'text' => 'Kodpen <b>news</b> &amp; more', 'cssClass' => 'kodpen-title'), 'children' => array()),
			array('id' => 's', 'type' => 'semantic', 'props' => array('tag' => 'div', 'text' => 'About Kodpen', '_attrs' => array(array('name' => 'title', 'value' => 'Kodpen team'), array('name' => 'data-x', 'value' => 'kodpen'))), 'children' => array(
				array('id' => 'c', 'type' => 'semantic', 'props' => array('tag' => 'code', 'text' => 'kodpen()'), 'children' => array()),
				array('id' => 'b', 'type' => 'content', 'props' => array('contentType' => 'paragraph', 'text' => 'sample', '_bindings' => array('text' => 'name')), 'children' => array()),
			)),
			array('id' => 'r', 'type' => 'shared_ref', 'props' => array('sharedId' => 7, 'sharedName' => 'Kodpen footer'), 'children' => array()),
		),
	);
}

function test_tree_search_texts_are_the_words()
{
	$texts = pg_tree_search_texts(tree_search_test_tree());
	$values = array();
	foreach ($texts as $text) {
		$values[] = $text['field'] . '=' . $text['value'];
	}

	pg_assert_same(array('text=Kodpen <b>news</b> &amp; more', 'text=About Kodpen', '_attrs:title=Kodpen team'), $values,
		'texts and worded attributes, not classes, other attributes, code, bound samples or component names');
}

function test_tree_search_replace_leaves_wiring()
{
	$tree = tree_search_test_tree();
	list($new, $count) = pg_tree_search_replace($tree, function ($text) {
		$n = 0;
		$out = str_replace('Kodpen', 'Pinegrap', $text, $n);
		return array($out, $n);
	});

	pg_assert_same(3, $count, 'three texts changed');
	pg_assert_same('Pinegrap <b>news</b> &amp; more', $new['children'][0]['props']['text'], 'heading text replaced');
	pg_assert_same('kodpen-title', $new['children'][0]['props']['cssClass'], 'class kept');
	pg_assert_same('Pinegrap team', $new['children'][1]['props']['_attrs'][0]['value'], 'title attribute replaced');
	pg_assert_same('kodpen', $new['children'][1]['props']['_attrs'][1]['value'], 'other attribute kept');
	pg_assert_same('kodpen()', $new['children'][1]['children'][0]['props']['text'], 'code kept');
	pg_assert_same('Kodpen footer', $new['children'][2]['props']['sharedName'], 'component snapshot name kept');
	pg_assert_same('Kodpen <b>news</b> &amp; more', $tree['children'][0]['props']['text'], 'the given tree is not changed');

	list($same, $none) = pg_tree_search_replace($tree, function ($text) {
		return array('x', 0);
	});
	pg_assert_same(0, $none, 'no count, no change');
	pg_assert_same($tree, $same, 'tree as it was');
}

function test_tree_search_shared_ids()
{
	$tree = tree_search_test_tree();
	$tree['children'][] = array('type' => 'semantic', 'props' => array('tag' => 'div'), 'children' => array(
		array('type' => 'shared_ref', 'props' => array('sharedId' => '3'), 'children' => array()),
		array('type' => 'shared_ref', 'props' => array('sharedId' => 7), 'children' => array()),
	));

	pg_assert_same(array(3, 7), pg_tree_search_shared_ids($tree), 'each placed component once, by id');
	pg_assert_same(array(), pg_tree_search_shared_ids(null), 'no tree, no ids');
}

function test_tree_search_plain_text_reads_components()
{
	$footer = array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'content', 'props' => array('contentType' => 'paragraph', 'text' => 'Call   us'), 'children' => array()),
		array('type' => 'shared_ref', 'props' => array('sharedId' => 7), 'children' => array()),
	));

	$text = pg_tree_search_plain_text(tree_search_test_tree(), array(7 => $footer));

	pg_assert_same("Kodpen news & more\nAbout Kodpen\nKodpen team\nCall us", $text,
		'markup removed, entities decoded, component read once though it places itself');
	pg_assert_same(3, mb_substr_count(mb_strtolower(pg_tree_search_plain_text(tree_search_test_tree())), 'kodpen'), 'a missing component adds nothing');
}

function test_tree_search_list_of_nodes()
{
	$list = array(
		array('type' => 'content', 'props' => array('contentType' => 'paragraph', 'text' => 'one'), 'children' => array()),
		array('type' => 'content', 'props' => array('contentType' => 'paragraph', 'text' => 'two'), 'children' => array()),
	);

	pg_assert_same(2, count(pg_tree_search_texts($list)), 'a list of nodes is walked');
}

function test_tree_search_like_safe()
{
	pg_assert_true(pg_tree_search_like_safe('Kodpen news', false), 'a plain term pre-selects with LIKE');
	pg_assert_true(pg_tree_search_like_safe('çay & ğ/ı', false), 'letters, slashes and entities are stored as typed');
	pg_assert_true(!pg_tree_search_like_safe('say "hi"', false), 'a double quote is escaped in JSON');
	pg_assert_true(!pg_tree_search_like_safe('a\\b', false), 'a backslash is escaped in JSON');
	pg_assert_true(!pg_tree_search_like_safe("two\nlines", false), 'a line break is escaped in JSON');
	pg_assert_true(!pg_tree_search_like_safe('Kodpen', true), 'a regular expression reads every tree');
}

function test_tree_search_screens_read_trees()
{
	$root = dirname(__DIR__) . '/pinegrap/';
	$far = file_get_contents($root . 'find_and_replace.php');
	$search = file_get_contents($root . 'get_search_results.php');

	pg_assert_true(strpos($far, "\$all_table_defs['page_tree']") !== false, 'Find & Replace lists designer pages');
	pg_assert_true(strpos($far, "\$all_table_defs['shared_component']") !== false, 'Find & Replace lists shared components');
	pg_assert_true(strpos($far, "'base_where'   => 'system_region_config IS NULL'") !== false, 'system widgets are left out');
	pg_assert_true(strpos($far, 'pg_tree_search_like_safe($find, $is_regex)') !== false, 'a term JSON escapes reads every tree');
	pg_assert_true(strpos($far, "pg_collab_may_edit_page('', \$tree_id)") !== false, 'a page open in the designer is not written');
	pg_assert_true(strpos($far, 'pg_tree_search_save_page(') !== false && strpos($far, 'pg_tree_search_save_component(') !== false, 'trees are written whole');
	pg_assert_true(strpos($search, 'pg_tree_search_plain_text(json_decode($page_tree, true), pg_tree_search_components())') !== false, 'simple site search counts in the page tree and its components');
}

function tree_search_test_components()
{
	$text = function ($words) {
		return array('type' => 'content', 'props' => array('contentType' => 'paragraph', 'text' => $words), 'children' => array());
	};

	return array(
		7 => array('type' => 'root', 'props' => array(), 'children' => array($text('Call <b>us</b>'))),
		8 => array('type' => 'root', 'props' => array(), 'children' => array(
			$text('Office'),
			array('type' => 'shared_ref', 'props' => array('sharedId' => 7), 'children' => array()),
		)),
		9 => array('type' => 'root', 'props' => array(), 'children' => array($text('Şehir ofisi'))),
	);
}

function test_tree_search_matching_components()
{
	$components = tree_search_test_components();

	pg_assert_same(array(7, 8), pg_tree_search_matching_components($components, 'CALL US'),
		'a component holds the words, and so does one that places it');
	pg_assert_same(array(9), pg_tree_search_matching_components($components, 'şehir'), 'letters beyond ASCII are folded to lower case');
	pg_assert_same(array(), pg_tree_search_matching_components($components, 'nowhere'), 'no component holds the words');
	pg_assert_same(array(), pg_tree_search_matching_components($components, ''), 'an empty term matches no component');
}

function test_tree_search_shared_ref_sql()
{
	pg_assert_same('', pg_tree_search_shared_ref_sql('tree', array()), 'no component, no condition');
	pg_assert_same(
		"(tree LIKE '%\"sharedId\":7,%' OR tree LIKE '%\"sharedId\":7}%' OR tree LIKE '%\"sharedId\":\"7\"%')",
		pg_tree_search_shared_ref_sql('tree', array('7', 7, 0, -2)),
		'a tree that places the component, its id as a number or a string, each id once'
	);
}

function test_tree_search_widget_reads_trees_and_components()
{
	$widgets = file_get_contents(dirname(__DIR__) . '/pinegrap/includes/fn/widgets.php');
	$start = strpos($widgets, 'function _pg_sw_search_collect_one(');
	$body = substr($widgets, $start, strpos($widgets, 'function _pg_sw_search_merge_pages(') - $start);

	pg_assert_true(strpos($body, 'pg_page_tree_sql_expr()') !== false, 'the Search Results widget reads the tree a page is drawn from');
	pg_assert_true(strpos($body, 'pg_tree_search_matching_components(') !== false, 'pages whose words sit in a placed component are candidates');
	pg_assert_true(strpos($body, 'pg_tree_search_plain_text($tree, $components)') !== false, 'the widget counts in the page tree and its components');
}
