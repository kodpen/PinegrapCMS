<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the row position tokens of system widget lists
 * (__loop_index, __loop_count, __loop_is_first, __loop_is_last,
 * __loop_is_odd, __loop_is_even): _split_widget_tree() puts row marks
 * around the row template only when it uses one, the token sweep keeps
 * them inside a widget scope, and the finish pass fills them per row
 * before the conditions run (includes/fn/widgets_finish.php). The editor's
 * side (style_designer.js) offers them inside the loop only.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// $fn run inside a widget scope of $kind, closed whatever happens.
function _widget_loop_in_scope($kind, $fn)
{
	$scope = pg_sw_finish_open(array('sid' => 9, 'kind' => $kind, 'email' => false));
	try {
		return $fn($scope);
	} finally {
		pg_sw_finish_close();
	}
}

function _widget_loop_open()
{
	return '<!--pg-row:' . pg_sw_marker_nonce() . '-->';
}

function _widget_loop_close()
{
	return '<!--/pg-row:' . pg_sw_marker_nonce() . '-->';
}

// $body between the row marks of this request.
function _widget_loop_row($body)
{
	return _widget_loop_open() . $body . _widget_loop_close();
}

// A paragraph bound to $text_token, shown only when $show_if passes.
function _widget_loop_node($text_token, $show_if = null, $params = null, $class = 'v')
{
	$props = array('contentType' => 'paragraph', 'text' => 'Sample', 'cssClass' => $class, '_bindings' => array('text' => $text_token));
	if ($show_if !== null) $props['_bindings']['show_if'] = $show_if;
	if ($params !== null) $props['_bindParams'] = array('show_if' => $params);
	return array('type' => 'content', 'props' => $props, 'children' => array());
}

// A widget tree: a heading outside the loop, then a list whose row
// template is $row_children.
function _widget_loop_tree($row_children)
{
	return array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'content', 'props' => array('contentType' => 'heading', 'tag' => 'h2', 'text' => 'List', 'cssClass' => 'head'), 'children' => array()),
		array('type' => 'semantic', 'props' => array('tag' => 'div', 'cssClass' => 'list'), 'children' => array(
			array('type' => 'loop_area', 'props' => array(), 'children' => $row_children),
		)),
	));
}

// What a list renderer prints for $tree inside a widget scope: the row
// template rendered once, filled for each record with the renderer's own
// fill and swept, put in the static part's loop slot. Returns
// array(markup, scope), the markup not yet finished.
function _widget_loop_render($tree, $records, $kind = 'form_list_view', $row_fn = null)
{
	return _widget_loop_in_scope($kind, function ($scope) use ($tree, $records, $row_fn) {
		$split = _split_widget_tree($tree);
		$tpl   = '';
		foreach ($split['loop_children'] as $child) $tpl .= _render_tree_node($child, 0, 0);
		$tpl  = trim($tpl);
		$rows = '';
		foreach ($records as $i => $values) {
			$row = pg_sw_sweep_tokens(_pg_sw_fill_tokens($tpl, $values));
			$rows .= ($row_fn !== null) ? $row_fn($row, $i) : $row;
		}
		$static = pg_sw_sweep_tokens(trim(_render_tree_node($split['static_tree'], 0, 0)));
		return array(str_replace('<!--pg-loop-slot-->', $rows, $static), $scope);
	});
}

// $html rendered and finished.
function _widget_loop_finished($tree, $records, $kind = 'form_list_view', $row_fn = null)
{
	$r = _widget_loop_render($tree, $records, $kind, $row_fn);
	return pg_sw_finish_widget_html($r[0], $r[1]);
}

function _widget_loop_no_marks($html, $label = '')
{
	pg_assert_same(false, strpos($html, 'pg-row:'), $label . ' no row mark');
	pg_assert_same(false, strpos($html, '__loop_'), $label . ' no position token');
	pg_assert_same(false, strpos($html, pg_sw_marker_nonce()), $label . ' no nonce');
}

// -- the values ----------------------------------------------------------------

function test_widget_loop_position_values()
{
	pg_assert_same(array(
		'__loop_index' => '1', '__loop_count' => '3', '__loop_is_first' => '1',
		'__loop_is_last' => '', '__loop_is_odd' => '1', '__loop_is_even' => '',
	), pg_sw_finish_position_values(0, 3), 'first of three');
	pg_assert_same(array(
		'__loop_index' => '2', '__loop_count' => '3', '__loop_is_first' => '',
		'__loop_is_last' => '', '__loop_is_odd' => '', '__loop_is_even' => '1',
	), pg_sw_finish_position_values(1, 3), 'second of three');
	pg_assert_same(array(
		'__loop_index' => '3', '__loop_count' => '3', '__loop_is_first' => '',
		'__loop_is_last' => '1', '__loop_is_odd' => '1', '__loop_is_even' => '',
	), pg_sw_finish_position_values(2, 3), 'third of three');
	pg_assert_same(array(
		'__loop_index' => '1', '__loop_count' => '1', '__loop_is_first' => '1',
		'__loop_is_last' => '1', '__loop_is_odd' => '1', '__loop_is_even' => '',
	), pg_sw_finish_position_values(0, 1), 'one row of one');
}

// -- the position stage ------------------------------------------------------

// Three row spans get their own numbers and flags; the marks go.
function test_widget_loop_position_fills_three_rows()
{
	$tpl  = '<li>^^__loop_index^^/^^__loop_count^^ f=^^__loop_is_first^^ l=^^__loop_is_last^^ o=^^__loop_is_odd^^ e=^^__loop_is_even^^</li>';
	$html = '<ul>' . _widget_loop_row($tpl) . _widget_loop_row($tpl) . _widget_loop_row($tpl) . '</ul>';
	$out  = pg_sw_finish_widget_html($html, array('kind' => 'catalog_listing'));
	pg_assert_same('<ul><li>1/3 f=1 l= o=1 e=</li><li>2/3 f= l= o= e=1</li><li>3/3 f= l=1 o=1 e=</li></ul>', $out);
}

// A token in an attribute value escaped with h() is filled there.
function test_widget_loop_position_fills_an_href()
{
	$tpl  = '<a href="' . h('/list?row=^^__loop_index^^&x=1') . '">Go</a>';
	$html = _widget_loop_row($tpl) . _widget_loop_row($tpl);
	pg_assert_same('<a href="/list?row=1&amp;x=1">Go</a><a href="/list?row=2&amp;x=1">Go</a>',
		pg_sw_finish_widget_html($html, array('kind' => 'form_list_view')));
}

// A format suffix after a position token is dropped with it.
function test_widget_loop_position_drops_a_format_suffix()
{
	$html = _widget_loop_row('<b>^^__loop_index^^%%d.m.Y%%</b>') . _widget_loop_row('<b>^^__loop_count^^%%x%%</b>');
	pg_assert_same('<b>1</b><b>2</b>', pg_sw_finish_widget_html($html, array('kind' => 'catalog_listing')));
}

// Rows inside a row (a reply list in a comment) number among themselves.
function test_widget_loop_position_nested_rows()
{
	$inner = _widget_loop_row('<i>^^__loop_index^^/^^__loop_count^^</i>');
	$outer = _widget_loop_row('<b>^^__loop_index^^/^^__loop_count^^</b>' . $inner . $inner . $inner);
	$out   = pg_sw_finish_widget_html($outer . $outer, array('kind' => 'comments'));
	pg_assert_same('<b>1/2</b><i>1/3</i><i>2/3</i><i>3/3</i><b>2/2</b><i>1/3</i><i>2/3</i><i>3/3</i>', $out);
}

// A position token outside any row (bound outside the loop) is taken out,
// also when the output holds no marker at all.
function test_widget_loop_position_leftovers()
{
	$scope = array('kind' => 'form_list_view');
	pg_assert_same('<h2>List </h2>', pg_sw_finish_widget_html('<h2>List ^^__loop_index^^</h2>', $scope), 'no nonce');
	pg_assert_same('<h2>List </h2>', pg_sw_finish_widget_html('<h2>List ^^__loop_count^^%%x%%</h2>', $scope), 'no nonce, a suffix');
	pg_assert_same('<h2>^^__other^^</h2>', pg_sw_finish_widget_html('<h2>^^__other^^</h2>', $scope), 'another token is not the pass\'s');
	pg_assert_same('<h2></h2><p>1</p>',
		pg_sw_finish_widget_html('<h2>^^__loop_is_first^^</h2>' . _widget_loop_row('<p>^^__loop_index^^</p>'), $scope), 'beside a row');
	pg_assert_same('<p>a</p><p>b</p>', pg_sw_finish_widget_html(_widget_loop_open() . '<p>a</p>' . _widget_loop_close() . _widget_loop_close() . '<p>b</p>', $scope), 'a close mark without its pair');
	pg_assert_same('<p>a</p>', pg_sw_finish_widget_html(_widget_loop_open() . '<p>a</p>', $scope), 'an open mark without its pair');
	$forged = '<!--pg-row:0000000000000000--><p>^^__loop_index^^</p><!--/pg-row:0000000000000000-->';
	pg_assert_same('<!--pg-row:0000000000000000--><p></p><!--/pg-row:0000000000000000-->', pg_sw_finish_widget_html($forged, $scope), 'another nonce is text');
	pg_assert_same('<p>x</p>', pg_sw_finish_widget_html('<p>x</p>', $scope), 'nothing to do');
}

// A page of many rows is filled in one pass, without a pattern over a body.
function test_widget_loop_position_many_rows()
{
	$html = str_repeat(_widget_loop_row('<p>' . str_repeat('x', 400) . '^^__loop_index^^</p>'), 3000);
	$out  = pg_sw_finish_widget_html($html, array('kind' => 'form_list_view'));
	pg_assert_same(3000, substr_count($out, '<p>'));
	pg_assert_contains('x3000</p>', $out);
	pg_assert_same(false, strpos($out, 'pg-row:'));
}

// -- the token sweep -----------------------------------------------------------

// Inside a widget scope the sweep keeps the six position tokens (with a
// format suffix) for the pass and takes every other unfilled token; outside
// one it takes them all, as before.
function test_widget_loop_position_sweep()
{
	$html = '<p>^^__loop_index^^ a ^^foo^^ b ^^__loop_count^^%%x%% c ^^bar^^%%d%% ^^__loop_is_odd^^^^__loop_is_even^^ ^^__loop_nope^^</p>';
	pg_assert_same('<p> a  b  c   </p>', pg_sw_sweep_tokens($html), 'outside a scope');
	$in = _widget_loop_in_scope('catalog_listing', function () use ($html) {
		return pg_sw_sweep_tokens($html);
	});
	pg_assert_same('<p>^^__loop_index^^ a  b ^^__loop_count^^%%x%% c  ^^__loop_is_odd^^^^__loop_is_even^^ </p>', $in, 'inside a scope');

	$plain = '<p>^^a^^ x ^^b^^%%y%%</p>';
	pg_assert_same(pg_sw_sweep_tokens($plain), _widget_loop_in_scope('catalog_listing', function () use ($plain) {
		return pg_sw_sweep_tokens($plain);
	}), 'no position token: the same sweep');
}

// -- the row marks -------------------------------------------------------------

// The marks come only inside a scope, only around a row template that uses
// a position token, and never around an empty loop.
function test_widget_loop_position_split_marks()
{
	$plain = _widget_loop_tree(array(_widget_loop_node('__name')));
	$outside = _split_widget_tree($plain);
	$inside  = _widget_loop_in_scope('form_list_view', function () use ($plain) {
		return _split_widget_tree($plain);
	});
	pg_assert_same($outside, $inside, 'no position token: identical');

	$custom = array('type' => 'content', 'props' => array('contentType' => 'custom_html', 'html' => '<b>No. ^^__loop_index^^</b>'), 'children' => array());
	$uses = array(
		'text binding' => array(_widget_loop_node('__loop_index')),
		'show_if'      => array(_widget_loop_node('__name', '__loop_is_first')),
		'custom html'  => array($custom),
		'href'         => array(array('type' => 'content', 'props' => array('contentType' => 'link', 'text' => 'Go', '_bindings' => array('href' => '__loop_count')), 'children' => array())),
	);
	foreach ($uses as $label => $children) {
		$tree  = _widget_loop_tree($children);
		$split = _widget_loop_in_scope('form_list_view', function () use ($tree) {
			return _split_widget_tree($tree);
		});
		$kids = $split['loop_children'];
		pg_assert_same(count($children) + 2, count($kids), $label . ': two marks');
		pg_assert_same(_widget_loop_open(), $kids[0]['props']['html'], $label . ': open first');
		pg_assert_same(_widget_loop_close(), $kids[count($kids) - 1]['props']['html'], $label . ': close last');
		pg_assert_same('custom_html', $kids[0]['props']['contentType'], $label);
		pg_assert_same($children, array_slice($kids, 1, -1), $label . ': the template between them');
		pg_assert_same(_split_widget_tree($tree), _split_widget_tree($tree), $label);
		pg_assert_same($children, _split_widget_tree($tree)['loop_children'], $label . ': outside a scope, never');
	}

	$similar = _widget_loop_tree(array(_widget_loop_node('__loop_indexes')));
	$split = _widget_loop_in_scope('form_list_view', function () use ($similar) {
		return _split_widget_tree($similar);
	});
	pg_assert_same(1, count($split['loop_children']), 'a longer name is not a position token');

	$empty = _widget_loop_tree(array());
	$split = _widget_loop_in_scope('form_list_view', function () use ($empty) {
		return _split_widget_tree($empty);
	});
	pg_assert_same(array(), $split['loop_children'], 'an empty loop gets no marks');

	$static_only = array('type' => 'root', 'props' => array(), 'children' => array(_widget_loop_node('__loop_index')));
	$split = _widget_loop_in_scope('form_list_view', function () use ($static_only) {
		return _split_widget_tree($static_only);
	});
	pg_assert_same(null, $split['loop_children'], 'no loop, no marks');
}

// A whole list: the row number bound as text, the static heading keeps no
// token, page markup holds no mark.
function test_widget_loop_position_whole_list()
{
	$tree = _widget_loop_tree(array(array('type' => 'semantic', 'props' => array('tag' => 'article', 'cssClass' => 'card'), 'children' => array(
		_widget_loop_node('__loop_index', null, null, 'no'),
		_widget_loop_node('__name', null, null, 'name'),
	))));
	$tree['children'][0]['props']['_bindings'] = array('text' => '__loop_count');
	$out = _widget_loop_finished($tree, array(array('__name' => 'A'), array('__name' => 'B'), array('__name' => 'C')));
	pg_assert_same(3, preg_match_all('/<p class="no"[^>]*>(\d)<\/p>/', $out, $m));
	pg_assert_same(array('1', '2', '3'), $m[1]);
	pg_assert_same(1, preg_match('#<h2 class="head"[^>]*></h2>#', $out), 'the count outside the loop is taken out: ' . $out);
	_widget_loop_no_marks($out);
}

// -- with the conditions -------------------------------------------------------

function _widget_loop_records($n)
{
	$records = array();
	for ($i = 1; $i <= $n; $i++) $records[] = array('__name' => 'R' . $i);
	return $records;
}

// The names printed by the rows of $out.
function _widget_loop_names($out, $class = 'name')
{
	preg_match_all('/<p class="' . $class . '"[^>]*>([^<]*)<\/p>/', $out, $m);
	return $m[1];
}

// show_if __loop_is_first (not_empty): only the first row keeps the badge.
function test_widget_loop_position_first_row_condition()
{
	$tree = _widget_loop_tree(array(
		_widget_loop_node('__name', '__loop_is_first', null, 'badge'),
		_widget_loop_node('__name', null, null, 'name'),
	));
	$out = _widget_loop_finished($tree, _widget_loop_records(4));
	pg_assert_same(array('R1'), _widget_loop_names($out, 'badge'));
	pg_assert_same(array('R1', 'R2', 'R3', 'R4'), _widget_loop_names($out));
	_widget_loop_no_marks($out);
	pg_assert_same(false, strpos($out, 'pg-if:'));
}

// multiple_of 3 on __loop_index: rows 3 and 6 of 7.
function test_widget_loop_position_every_third_row()
{
	$tree = _widget_loop_tree(array(
		_widget_loop_node('__name', null, null, 'name'),
		_widget_loop_node('__name', '__loop_index', array('op' => 'multiple_of', 'value' => '3'), 'insert'),
	));
	$out = _widget_loop_finished($tree, _widget_loop_records(7));
	pg_assert_same(array('R3', 'R6'), _widget_loop_names($out, 'insert'));
	pg_assert_same(7, count(_widget_loop_names($out)));
	_widget_loop_no_marks($out);
}

// empty on __loop_is_last: every row but the last keeps its divider; odd
// and even rows read their own flag.
function test_widget_loop_position_last_row_divider()
{
	$divider = array('type' => 'semantic', 'props' => array('tag' => 'hr', 'cssClass' => 'sep',
		'_bindings' => array('show_if' => '__loop_is_last'), '_bindParams' => array('show_if' => array('op' => 'empty'))), 'children' => array());
	$tree = _widget_loop_tree(array(
		_widget_loop_node('__name', '__loop_is_even', null, 'even'),
		_widget_loop_node('__name', null, null, 'name'),
		$divider,
	));
	$out = _widget_loop_finished($tree, _widget_loop_records(3));
	pg_assert_same(2, substr_count($out, 'class="sep"'), 'two dividers for three rows');
	pg_assert_same(1, preg_match('#R3</p>\s*</div>#', $out), 'nothing after the last row: ' . $out);
	pg_assert_same(array('R2'), _widget_loop_names($out, 'even'));
	_widget_loop_no_marks($out);
}

// -- the canvas ghosts and the edit pencil -------------------------------------

// The ghost marks pg_designer_widget_ghosts() writes and the row marks
// together: every ghost span survives with its own number.
function test_widget_loop_position_with_ghost_marks()
{
	$ghost = function ($html) {
		return array('type' => 'content', 'props' => array('contentType' => 'custom_html', 'html' => $html), 'children' => array());
	};
	$tree = _widget_loop_tree(array($ghost('<!--pg-ghost-start-->'), _widget_loop_node('__loop_index', null, null, 'no'), $ghost('<!--pg-ghost-end-->')));
	$out  = _widget_loop_finished($tree, _widget_loop_records(4));
	pg_assert_same(4, preg_match_all('/<!--pg-ghost-start-->(.*?)<!--pg-ghost-end-->/s', $out, $m));
	foreach ($m[1] as $i => $row) {
		pg_assert_same(1, preg_match('#<p class="no"[^>]*>' . ($i + 1) . '</p>#', $row), 'ghost ' . ($i + 1));
	}
	_widget_loop_no_marks($out);
}

// A row mark is the row's first child; the pencil still goes in the row's
// first element, put in at once (no condition) or by the pass (a row that
// holds a condition), and so does a comment's id.
function test_widget_loop_position_edit_pencil_after_a_row_mark()
{
	$card = function ($with_condition) {
		$kids = array(_widget_loop_node('__loop_index', null, null, 'no'));
		if ($with_condition) $kids[] = _widget_loop_node('__name', '__loop_is_first', null, 'badge');
		return _widget_loop_tree(array(array('type' => 'semantic', 'props' => array('tag' => 'article', 'cssClass' => 'card'), 'children' => $kids)));
	};
	$chip = function ($row, $i) {
		pg_assert_same(0, strpos($row, _widget_loop_open()), 'the row starts with its mark');
		return pg_sw_add_edit_chip($row, '/e?id=' . ($i + 1), 'Edit', 'forms');
	};
	foreach (array('at once' => false, 'deferred' => true) as $label => $with_condition) {
		$out = _widget_loop_finished($card($with_condition), _widget_loop_records(2), 'form_list_view', $chip);
		pg_assert_same(2, substr_count($out, 'class="pg-edit-chip '), $label . ': one pencil per row');
		for ($id = 1; $id <= 2; $id++) {
			pg_assert_same(1, preg_match('#<article class="card pg-edit-host"[^>]*><a class="pg-edit-chip pg-edit-chip-forms" href="/e\?id=' . $id . '"#', $out), $label . ': row ' . $id . ': ' . $out);
		}
		_widget_loop_no_marks($out, $label);
		pg_assert_same(false, strpos($out, 'pg-chip:'), $label);
	}

	$row = _widget_loop_row("\n<article class=\"card\">x</article>\n");
	pg_assert_same(1, preg_match('#^<!--pg-row:[0-9a-f]+-->\s*<article id="c-5" class="card">#', pg_comments_row_root_id($row, 5)), 'the comment id');
	pg_assert_same(1, preg_match('#^<!--pg-row:[0-9a-f]+-->\s*<article class="card pg-edit-host"><a class="pg-edit-chip #', pg_sw_insert_edit_chip($row, '/e', 'Edit', 'page')), 'the insert');
}

// -- the dispatcher and the editor ---------------------------------------------

// The pass returns early only when the output holds neither the nonce nor
// a position token, and the stage list names the position stage first.
function test_widget_loop_position_pass_in_source()
{
	$finish = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_finish.php');
	pg_assert_contains('$html = pg_sw_finish_positions($html, $nonce);', $finish);
	pg_assert_true(strpos($finish, 'pg_sw_finish_positions($html, $nonce);') < strpos($finish, 'pg_sw_finish_resolve($html, $nonce, $cut);'), 'positions before the conditions');

	$designer = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/designer.php');
	pg_assert_contains('pg_sw_finish_uses_positions(', $designer);
}

// The editor's palette names the same six tokens the pass fills.
function test_widget_loop_position_js_parity()
{
	$js   = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	$from = strpos($js, "\n    var SW_LOOP_POSITION_TOKEN_GROUPS = [");
	pg_assert_true($from !== false, 'the palette is found');
	if ($from === false) return;
	$to = strpos($js, "\n    ];\n", $from);
	preg_match_all("/\['(__[a-z0-9_]+)'/", substr($js, $from, $to - $from), $m);
	pg_assert_same(array_keys(pg_sw_finish_position_values(0, 1)), $m[1]);
}

// A function of style_designer.js, cut out at its four-space indent.
function _widget_loop_js_function($source, $name)
{
	$from = strpos($source, "\n    function " . $name . '(');
	if ($from === false) {
		return '';
	}
	$to = strpos($source, "\n    }\n", $from);
	return ($to === false) ? '' : substr($source, $from, $to + 7 - $from);
}

// Runs the editor's offer under Node; null when Node is not installed.
function _widget_loop_run_js($script)
{
	if (!function_exists('exec')) {
		return null;
	}
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) {
		return null;
	}

	$js   = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	$from = strpos($js, "\n    var SW_LOOP_POSITION_TOKEN_GROUPS = [");
	pg_assert_true($from !== false, 'the palette is found');
	if ($from === false) return null;
	$to = strpos($js, "\n    ];\n", $from);
	$cut = substr($js, $from, $to + 7 - $from);
	foreach (array('_swInMainLoop', '_swPositionLabel', '_swBindTokenOptions', '_swBindLabel', '_sdListIsSourced', '_sdListsAbove', '_sdListRowOptions', '_sdListRowLabel') as $name) {
		$fn = _widget_loop_js_function($js, $name);
		pg_assert_true($fn !== '', $name . ' is found');
		$cut .= $fn;
	}

	$stubs = "var _sdT = function (s) { return s; };\n"
		. "var SW_LIST_SOURCES = {};\n"
		. "var KIND = '';\n"
		. "var PARENTS = new Map();\n"
		. "function _sdAnyParent(n) { return PARENTS.has(n) ? { parent: PARENTS.get(n), sid: 0 } : null; }\n"
		. "function _getSystemWidgetRegionType(n) { return KIND; }\n"
		. "function _swBindTokenGroups(type) { return type === 'calendar_view' ? [{ label: 'Event', tokens: [['__event_title', 'Event title']] }] : []; }\n"
		. "function _getSystemWidgetCustomFormPageId(n) { return 5; }\n"
		. "var _formFieldsCache = { 5: [{ id: 1, name: 'title', label: 'Title' }] };\n"
		. "function _fetchFormFields() {}\n"
		. "function _swFormFieldToken(ff) { return ff.name; }\n"
		. "function _sdSystemWidgetCfgOf(n) { return {}; }\n"
		. "function _swStandardYields() { return false; }\n"
		. "var SW_BUILTIN_FIELD_OPTIONS = [['__detail_url', 'Detail page address']];\n"
		. "var SW_FORM_ITEM_VIEW_BUILTIN_OPTIONS = [['__not_found_message', 'Not found']];\n"
		. "var SW_PAGE_LINK_TOKEN = '__page_link';\n"
		. "function node(type, kids) { var n = { type: type, props: {}, children: kids || [] }; n.children.forEach(function (c) { PARENTS.set(c, n); }); return n; }\n";

	$file = @tempnam(sys_get_temp_dir(), 'pgloop');
	if ($file === false) return null;
	file_put_contents($file, $stubs . $cut . "\n" . $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	$printed = implode("\n", $out);
	pg_assert_same(0, $code, $printed);
	$answer = json_decode($printed, true);
	pg_assert_true(is_array($answer), 'Node printed JSON: ' . (($printed === '') ? '(nothing)' : $printed));
	return $answer;
}

// The position group is offered inside the loop only: not outside it, not
// on the loop_area itself, not in a recipient loop; for a fixed palette and
// for a form list, whose own options come from the form.
function test_widget_loop_position_editor_offer()
{
	$script = <<<'JS'
var inLoop = node('content'), card = node('semantic', [inLoop]), loop = node('loop_area', [card]);
var outside = node('content'), inRecipient = node('content'), recipient = node('recipient_loop_area', [inRecipient]);
var item = node('content'), itemLoop = node('loop_area', [item]), recipient2 = node('recipient_loop_area', [itemLoop]);
var root = node('root', [node('semantic', [loop]), outside, recipient, recipient2]);
var tokens = function (n) { return _swBindTokenOptions(n).map(function (o) { return o.token; }); };
var answer = {};
['calendar_view', 'form_list_view'].forEach(function (kind) {
    KIND = kind;
    answer[kind] = { inLoop: tokens(inLoop), card: tokens(card), loop: tokens(loop), outside: tokens(outside), recipient: tokens(inRecipient), item: tokens(item), root: tokens(root),
        label: _swBindLabel(kind, '__loop_index'), countLabel: _swBindLabel(kind, '__loop_count'), other: _swBindLabel(kind, '__nope') };
});
KIND = 'calendar_view';
answer.optionLabel = _swBindTokenOptions(inLoop).filter(function (o) { return o.token === '__loop_is_last'; })[0].label;
console.log(JSON.stringify(answer));
JS;
	$answer = _widget_loop_run_js($script);
	if ($answer === null) {
		return;
	}

	$six = array_keys(pg_sw_finish_position_values(0, 1));
	$own = array('calendar_view' => array('__event_title'), 'form_list_view' => array('title', '__detail_url'));
	foreach ($own as $kind => $tokens) {
		$a = $answer[$kind];
		pg_assert_same(array_merge($tokens, $six), $a['inLoop'], $kind . ': inside the loop');
		pg_assert_same(array_merge($tokens, $six), $a['card'], $kind . ': the row element');
		pg_assert_same(array_merge($tokens, $six), $a['item'], $kind . ': a loop inside a recipient loop');
		pg_assert_same($tokens, $a['loop'], $kind . ': the loop_area itself');
		pg_assert_same($tokens, $a['outside'], $kind . ': outside the loop');
		pg_assert_same($tokens, $a['recipient'], $kind . ': in a recipient loop');
		pg_assert_same($tokens, $a['root'], $kind . ': the root');
		pg_assert_same('Row number', $a['label'], $kind);
		pg_assert_same('Rows on this page', $a['countLabel'], $kind);
		pg_assert_same('__nope', $a['other'], $kind);
	}
	pg_assert_same('Last row (1 or empty)', $answer['optionLabel']);
}
