<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the widget flags inside a list's row (pg_sw_list_flag_alias(),
 * includes/fn/widgets_lists.php): an eo_visible_if naming a value of the
 * row is a value condition on it, so each row decides; the widget's own
 * flags are still judged on the whole tree.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A menu: Home, Shop (Shoes, Bags).
function _lfa_items()
{
	$item = function ($id, $parent, $name, $page) {
		return array('id' => $id, 'parent_id' => $parent, 'name' => $name, 'link_page_id' => $id * 10,
			'link_page_name' => $page, 'link_page_folder_id' => 1, 'link_url' => '',
			'link_target' => 'Same Window', 'security' => 0, 'class' => '');
	};
	return array($item(1, 0, 'Home', 'home'), $item(2, 0, 'Shop', 'shop'), $item(3, 2, 'Shoes', 'shoes'), $item(4, 2, 'Bags', 'bags'));
}

function _lfa_node($tag, $class, $props = array(), $children = array())
{
	return array('type' => 'semantic', 'props' => array_merge(array('tag' => $tag, 'cssClass' => $class), $props), 'children' => $children);
}

// A list of the top level, each row a name, a caret when it has sub-items,
// a box shown by a widget flag; $flags are the widget's.
function _lfa_render($flags)
{
	$tree = array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'loop_area', 'props' => array('source' => 'menu.children'), 'children' => array(
			_lfa_node('li', 'row', array(), array(
				_lfa_node('span', 'name', array('text' => 'x', '_bindings' => array('text' => '__menu_item_name'))),
				_lfa_node('i', 'caret', array('text' => 'v', '_bindings' => array('eo_visible_if' => 'menu_item_has_children'))),
				_lfa_node('b', 'staff', array('text' => 'S', '_bindings' => array('eo_visible_if' => 'is_staff'))),
			)),
		)),
		_lfa_node('p', 'outside', array('text' => 'O', '_bindings' => array('eo_visible_if' => 'menu_item_has_children'))),
	));
	_eo_apply_visibility_bindings($tree, $flags);
	$scope = pg_sw_finish_open(array('sid' => 92, 'kind' => 'menu_region', 'email' => false));
	try {
		$html = trim(_render_tree_node($tree, 0, 0));
		pg_sw_list_publish('menu', array('menu.children' => pg_sw_list_menu_rows(_lfa_items(), 0)));
		$html = pg_sw_sweep_tokens(_pg_sw_fill_tokens($html, array('__menu_item_id' => '0')));
	} finally {
		pg_sw_finish_close();
	}
	return pg_sw_finish_widget_html($html, $scope);
}

function test_widget_list_flag_alias_rows_decide()
{
	$html = _lfa_render(array('is_staff' => false));
	preg_match_all('#class="(name|caret|staff|outside)"#', $html, $m);
	pg_assert_same(array('name', 'name', 'caret'), $m[1], 'only the row with sub-items keeps its caret; the widget flag is judged on the tree');
	pg_assert_false(strpos($html, '<!--pg-') !== false, 'no marker left');
	pg_assert_false(strpos($html, '^^') !== false, 'no token left');

	$html = _lfa_render(array('is_staff' => true));
	pg_assert_same(2, substr_count($html, 'class="staff"'), 'a widget flag that is on keeps its node in every row');
}

function test_widget_list_flag_alias_spec()
{
	pg_assert_same(null, pg_sw_list_flag_alias(array('_bindings' => array('eo_visible_if' => 'menu_item_has_children'))), 'outside a list: none');
	pg_assert_true(isset(pg_sw_list_row_flags('menu.children')['menu_item_is_current']));
	pg_assert_same(array(), pg_sw_list_row_flags('no.such'));

	// The other walker leaves a row flag for the row too.
	$tree = array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'loop_area', 'props' => array('source' => 'paging.pages'), 'children' => array(
			_lfa_node('span', 'cur', array('_bindings' => array('eo_visible_if' => 'pager_is_current'))),
		)),
		_lfa_node('span', 'top', array('_bindings' => array('eo_visible_if' => 'has_records'))),
	));
	_pg_sw_mark_visibility($tree);
	$json = json_encode($tree);
	pg_assert_same(1, substr_count($json, '<!--pg-vis:'), 'only the widget flag is marked');
	pg_assert_contains('pg-vis:has_records', $json);
}

// The editor offers a list's row flags inside its rows, the nearest list's
// first, each once.
function test_widget_list_flag_alias_editor_offers_row_flags()
{
	if (!function_exists('exec')) return;
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) return;
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$cut = function ($from, $to) use ($js) {
		$a = strpos($js, $from);
		pg_assert_true($a !== false, 'found: ' . trim($from));
		$b = strpos($js, $to, $a);
		return substr($js, $a, $b + strlen($to) - $a);
	};
	$src = '';
	foreach (array('_sdListRowFlags', '_sdListsAbove', '_sdListIsSourced') as $fn) $src .= $cut("\n    function " . $fn . '(', "\n    }\n");
	$stubs = "function _sdT(s) { return s; }\n"
		. "var SW_LIST_SOURCES = { 'menu.children': { label: 'Sub-items', rows: [['__menu_item_name', 'Name'], ['__menu_item_has_children', 'Has'],"
		. " ['__menu_item_is_current', 'Current'], ['__menu_item_holds_current', 'Holds']] } };\n";
	$script = "var leaf = { type: 'semantic', props: {}, children: [] };\n"
		. "var inner = { type: 'loop_area', props: { source: 'menu.children' }, children: [leaf] };\n"
		. "var outer = { type: 'loop_area', props: { source: 'menu.children' }, children: [inner] };\n"
		. "var root = { type: 'root', props: {}, children: [outer] };\n"
		. "var parents = new Map([[leaf, inner], [inner, outer], [outer, root]]);\n"
		. "function _sdAnyParent(n) { return parents.has(n) ? { parent: parents.get(n) } : null; }\n"
		. "console.log(JSON.stringify({ inList: _sdListRowFlags(leaf).map(function (f) { return f[0]; }), outside: _sdListRowFlags(outer) }));\n";
	$file = @tempnam(sys_get_temp_dir(), 'pglfa');
	if ($file === false) return;
	file_put_contents($file, $stubs . $src . $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);
	$printed = implode("\n", $out);
	pg_assert_same(0, $code, $printed);
	$a = json_decode($printed, true);
	pg_assert_true(is_array($a), 'Node printed JSON: ' . $printed);
	pg_assert_same(array('menu_item_has_children', 'menu_item_is_current', 'menu_item_holds_current'), $a['inList']);
	pg_assert_same(array(), $a['outside'], 'outside a list: none');
}
