<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for several value conditions on one widget node (show_if,
 * show_if_2, show_if_3, joined by _bindParams.show_if.join): how they are
 * read (pg_sw_cond_specs()), joined (pg_sw_cond_eval_all() and its twin
 * _sdCondEvalAll() on tests/fixtures/widget_condition_join_vectors.json),
 * carried through the finish pass, translated and dropped from the tree
 * the placement checks read. A node with one condition renders as before.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Props with the conditions $rows (list of array(token, op, value)) and
// the join $join (null: none written).
function _widget_cond_rows_props(array $rows, $join = null)
{
	$props = array('_bindings' => array(), '_bindParams' => array());
	foreach ($rows as $i => $row) {
		$name = $i ? 'show_if_' . ($i + 1) : 'show_if';
		$props['_bindings'][$name] = $row[0];
		$param = array('op' => $row[1]);
		if (isset($row[2])) $param['value'] = $row[2];
		$props['_bindParams'][$name] = $param;
	}
	if ($join !== null) $props['_bindParams']['show_if']['join'] = $join;
	return $props;
}

function test_widget_condition_rows_specs()
{
	pg_assert_same(null, pg_sw_cond_specs(array('text' => 'x')), 'none');
	pg_assert_same(null, pg_sw_cond_specs(array('_bindings' => array('show_if' => '', 'show_if_2' => ''))), 'empty rows are none');
	pg_assert_same(array('join' => 'all', 'conds' => array(array('token' => '__a', 'op' => 'not_empty', 'operand' => ''))),
		pg_sw_cond_specs(array('_bindings' => array('show_if' => '__a'))), 'one row, as saved before');
	pg_assert_same(array('join' => 'any', 'conds' => array(
		array('token' => '__city', 'op' => 'eq', 'operand' => 'İzmir'),
		array('token' => '__postcode', 'op' => 'starts', 'operand' => '35'),
	)), pg_sw_cond_specs(_widget_cond_rows_props(array(array('__city', 'eq', ' İzmir '), array('__postcode', 'starts', '35')), 'any')), 'two rows, any');
	pg_assert_same('all', pg_sw_cond_specs(_widget_cond_rows_props(array(array('__a', 'not_empty'), array('__b', 'empty')), ''))['join'], 'an empty join is all');
	$three = pg_sw_cond_specs(_widget_cond_rows_props(array(array('__a', 'not_empty'), array('__b', 'empty'), array('__c', 'gt', '0'))));
	pg_assert_same(3, count($three['conds']), 'three rows');

	$gap = _widget_cond_rows_props(array(array('__a', 'not_empty'), array('', 'empty'), array('__c', 'gt', '0')));
	pg_assert_same(array('__a', '__c'), array_column(pg_sw_cond_specs($gap)['conds'], 'token'), 'an empty middle row is skipped');

	pg_assert_false(pg_sw_cond_specs(array('_bindings' => array('show_if_2' => '__b'))), 'a later row without the first');
	pg_assert_false(pg_sw_cond_specs(_widget_cond_rows_props(array(array('__a', 'not_empty'), array('__b', 'gt')))), 'a later row without its value');
	pg_assert_false(pg_sw_cond_specs(_widget_cond_rows_props(array(array('__a', 'not_empty'), array('__b', 'empty'), array('__edit_url', 'not_empty')))), 'a refused token in the third row');
	pg_assert_false(pg_sw_cond_specs(_widget_cond_rows_props(array(array('__a', 'not_empty'), array('__b', 'empty')), 'either')), 'an unknown join');
	pg_assert_true(pg_sw_cond_present(array('_bindings' => array('show_if_3' => ''))), 'present, set or empty');
	pg_assert_false(pg_sw_cond_present(array('_bindings' => array('text' => '__a'))), 'no condition name');
}

function test_widget_condition_rows_join_vectors()
{
	$rows = json_decode((string) file_get_contents(dirname(__FILE__) . '/fixtures/widget_condition_join_vectors.json'), true);
	pg_assert_true(is_array($rows) && count($rows) >= 15, 'the join vectors file holds its rows');
	foreach ($rows as $i => $row) {
		pg_assert_same($row['expect'], pg_sw_cond_eval_all($row['join'], $row['tests']), '#' . $i);
	}
	if (!_widget_cond_rows_node_ok()) return;

	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	$a = strpos($js, "\n    function _sdCondEvalAll(");
	pg_assert_true($a !== false, 'the twin is found');
	$src = _widget_cond_rows_twin_source() . substr($js, $a, strpos($js, "\n    }\n", $a) + 7 - $a);
	$answer = _widget_cond_rows_node($src . 'var rows = ' . json_encode($rows) . ";\n"
		. "console.log(JSON.stringify(rows.map(function (r) { return _sdCondEvalAll(r.join, r.tests, '.'); })));\n");
	foreach ($rows as $i => $row) {
		pg_assert_same($row['expect'], $answer[$i], 'twin #' . $i);
	}
}

// The twin reads the rows as the server does.
function test_widget_condition_rows_specs_twin()
{
	if (!_widget_cond_rows_node_ok()) return;
	$cases = array(
		array('text' => 'x'),
		array('_bindings' => array('show_if' => '__a')),
		array('_bindings' => array('show_if' => 'publish-date"><'), '_bindParams' => array('show_if' => array('op' => 'empty', 'value' => 'x'))),
		_widget_cond_rows_props(array(array('__city', 'eq', ' İzmir '), array('__postcode', 'starts', '35')), 'any'),
		_widget_cond_rows_props(array(array('__a', 'not_empty'), array('', 'empty'), array('__c', 'gt', 0))),
		_widget_cond_rows_props(array(array('__a', 'not_empty'), array('__b', 'gt'))),
		_widget_cond_rows_props(array(array('__a', 'not_empty'), array('__x_html', 'empty'))),
		_widget_cond_rows_props(array(array('__a', 'not_empty'), array('__b', 'empty')), 'either'),
		_widget_cond_rows_props(array(array('__a', 'between', '1'))),
		array('_bindings' => array('show_if_2' => '__b')),
		array('_bindings' => array('show_if' => array('x'))),
	);
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	$cut = function ($from, $to) use ($js) {
		$a = strpos($js, $from);
		pg_assert_true($a !== false, 'found: ' . trim($from));
		return substr($js, $a, strpos($js, $to, $a) + strlen($to) - $a);
	};
	$src = "function _sdT(s) { return s; }\n"
		. $cut("\n    var SW_COND_OPS = [", "\n    ];\n")
		. $cut("\n    var SW_COND_DENY = [", "\n    ];\n")
		. $cut("\n    var SD_COND_NAMES = ", ";\n");
	foreach (array('_sdCondTokenAllowed', '_sdCondOp', '_sdCondSpecs') as $fn) {
		$src .= $cut("\n    function " . $fn . '(', "\n    }\n");
	}
	$answer = _widget_cond_rows_node($src . 'var cases = ' . json_encode($cases) . ";\n"
		. "console.log(JSON.stringify(cases.map(function (c) { return _sdCondSpecs(c); })));\n");
	foreach ($cases as $i => $props) {
		pg_assert_same(pg_sw_cond_specs($props), $answer[$i], 'case ' . $i);
	}
}

// Whether Node can be run here; the tests pass quietly without it.
function _widget_cond_rows_node_ok()
{
	if (!function_exists('exec')) return false;
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	return $code === 0;
}

// The editor's test of one condition, cut out of style_designer.js.
function _widget_cond_rows_twin_source()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	$a = strpos($js, "\n    var SD_COND_WS = ");
	$src = substr($js, $a, strpos($js, ";\n", $a) + 2 - $a);
	foreach (array('_sdCondPlain', '_sdCondText', '_sdCondFold', '_sdCondFigureOk', '_sdCondFigureNumber', '_sdCondAmount',
		'_sdCondNumber', '_sdCondComparable', '_sdCondIsWhole', '_sdCondEval') as $fn) {
		$a = strpos($js, "\n    function " . $fn . '(');
		pg_assert_true($a !== false, 'found: ' . $fn);
		$src .= substr($js, $a, strpos($js, "\n    }\n", $a) + 7 - $a);
	}
	return $src;
}

// What Node printed for $script, decoded.
function _widget_cond_rows_node($script)
{
	$file = @tempnam(sys_get_temp_dir(), 'pgrows');
	if ($file === false) return null;
	file_put_contents($file, $script);
	$out = array();
	$code = 1;
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);
	$printed = implode("\n", $out);
	pg_assert_same(0, $code, $printed);
	$answer = json_decode($printed, true);
	pg_assert_true(is_array($answer), 'Node printed JSON: ' . substr($printed, 0, 300));
	return $answer;
}

// One condition writes the markers it always did; several carry their
// tests in the open marker and a carrier each.
function test_widget_condition_rows_markers()
{
	$scope = array('kind' => 'order_preview');
	$one   = pg_sw_cond_specs(array('_bindings' => array('show_if' => '__a'), '_bindParams' => array('show_if' => array('op' => 'gt', 'value' => '5'))));
	$old   = preg_replace('/:\d+(?=[:\-])/', ':N', pg_sw_finish_wrap('<b>x</b>', $one['conds'][0]));
	$new   = preg_replace('/:\d+(?=[:\-])/', ':N', pg_sw_finish_wrap('<b>x</b>', $one));
	pg_assert_same($old, $new, 'one condition: the same markers');

	$html = pg_sw_finish_wrap('<p>Ships to you</p>', pg_sw_cond_specs(
		_widget_cond_rows_props(array(array('__city', 'eq', 'İzmir'), array('__postcode', 'starts', '35')), 'any')));
	pg_assert_contains(':any-->', $html);
	$fill = function ($city, $code) use ($html, $scope) {
		return pg_sw_finish_widget_html(strtr($html, array('^^__city^^' => $city, '^^__postcode^^' => $code)), $scope);
	};
	pg_assert_same('<p>Ships to you</p>', $fill('izmir', ''), 'any: the city');
	pg_assert_same('<p>Ships to you</p>', $fill('Ankara', '35100'), 'any: the postcode');
	pg_assert_same('', $fill('Ankara', '06100'), 'any: neither');
	$out = $fill('İZMİR', '35100');
	pg_assert_same('<p>Ships to you</p>', $out, 'any: both');
	pg_assert_true(strpos($out, 'pg-if:') === false && strpos($out, 'pg-cv:') === false, 'no markers left');

	$all = pg_sw_finish_wrap('<p>Both</p>', pg_sw_cond_specs(
		_widget_cond_rows_props(array(array('__a', 'not_empty'), array('__b', 'gt', '0'), array('__c', 'empty')))));
	$fill = function ($a, $b, $c) use ($all, $scope) {
		return pg_sw_finish_widget_html(strtr($all, array('^^__a^^' => $a, '^^__b^^' => $b, '^^__c^^' => $c)), $scope);
	};
	pg_assert_same('<p>Both</p>', $fill('x', '2', ''), 'all hold');
	pg_assert_same('', $fill('x', '2', 'y'), 'the third fails');
	pg_assert_same('', $fill('', '2', ''), 'the first fails');

	// Inside a kept span of the same tokens, and with a carrier stripped.
	$inner = pg_sw_finish_wrap('<i>^^__b^^</i>', pg_sw_cond_specs(_widget_cond_rows_props(array(array('__a', 'empty'), array('__b', 'eq', '1')), 'any')));
	$outer = pg_sw_finish_wrap('<div>' . $inner . '</div>', pg_sw_cond_specs(_widget_cond_rows_props(array(array('__a', 'not_empty'), array('__b', 'not_empty')), 'any')));
	pg_assert_same('<div><i>1</i></div>', pg_sw_finish_widget_html(strtr($outer, array('^^__a^^' => 'A', '^^__b^^' => '1')), $scope), 'nested, both kept');
	pg_assert_same('<div></div>', pg_sw_finish_widget_html(strtr($outer, array('^^__a^^' => 'A', '^^__b^^' => '2')), $scope), 'nested, inner cut');
	pg_assert_same('<div><i>^^__b^^</i></div>', pg_sw_finish_strip_carriers(preg_replace('/<!--\/?pg-if:[^>]*-->/', '', $outer)), 'every carrier stripped');
}

// The renderer reads every row and leaves none on the node.
function test_widget_condition_rows_render()
{
	$node = array('type' => 'semantic', 'props' => array_merge(array('tag' => 'p', 'text' => 'Gift'),
		_widget_cond_rows_props(array(array('__has_offer', 'not_empty'), array('__offer_discount', 'gt', '0')), 'any')), 'children' => array());
	$scope = pg_sw_finish_open(array('kind' => 'shopping_cart'));
	try {
		$html = _render_tree_node($node, 0, 0);
	} finally {
		pg_sw_finish_close();
	}
	pg_assert_true(strpos($html, 'show_if') === false, 'no condition prop written');
	pg_assert_same('', trim(pg_sw_finish_widget_html(strtr($html, array('^^__has_offer^^' => '', '^^__offer_discount^^' => '₺0,00')), $scope)), 'neither');
	pg_assert_contains('Gift', pg_sw_finish_widget_html(strtr($html, array('^^__has_offer^^' => '', '^^__offer_discount^^' => '₺5,00')), $scope), 'the second');

	$bad = $node;
	$bad['props']['_bindParams']['show_if_2']['op'] = 'between';
	$scope = pg_sw_finish_open(array('kind' => 'shopping_cart'));
	try {
		pg_assert_same('', _render_tree_node($bad, 0, 0), 'an invalid row draws nothing');
	} finally {
		pg_sw_finish_close();
	}
	$plain = trim(_render_tree_node($bad, 0, 0));
	pg_assert_contains('Gift', $plain, 'outside a widget it draws as it is');
	pg_assert_true(strpos($plain, 'pg-if') === false && strpos($plain, 'show_if') === false, 'unmarked');
}

// Each compared text with words is a translation field of its own.
function test_widget_condition_rows_translation_fields()
{
	$props = _widget_cond_rows_props(array(array('__city', 'eq', 'Kültür'), array('__total', 'gt', '5'), array('__note', 'contains', 'Hediye')), 'any');
	$fields = array();
	foreach (pg_tr_node_fields('semantic', $props) as $spec) {
		if (strpos($spec[0], '_cond:') === 0) $fields[] = $spec[0];
	}
	pg_assert_same(array('_cond:show_if', '_cond:show_if_3'), $fields);
	pg_assert_same('Hediye', pg_tr_field_get($props, '_cond:show_if_3'));
	pg_tr_field_set($props, '_cond:show_if_3', 'Gift');
	pg_assert_same('Gift', $props['_bindParams']['show_if_3']['value']);
	pg_assert_same('any', $props['_bindParams']['show_if']['join'], 'the join stays');
	pg_assert_false(pg_sw_cond_operand_is_words(_widget_cond_rows_props(array(array('__a', 'not_empty'), array('__crumb_kind', 'eq', 'link'))), 'show_if_2'), 'a code token');
}

// The placement checks read the tree without any row.
function test_widget_condition_rows_tree_json()
{
	$tree = array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'semantic', 'props' => _widget_cond_rows_props(array(array('__a', 'not_empty'), array('__not_found_message', 'empty'), array('has_items', 'empty'))), 'children' => array()),
	));
	$json = pg_sw_cond_tree_json(json_encode($tree));
	pg_assert_true(strpos($json, '__not_found_message') === false && strpos($json, 'has_items') === false && strpos($json, 'show_if') === false, $json);
}

// The cross-sell probe: an 'any' row it cannot read keeps the list.
function test_widget_condition_rows_cross_sell_probe()
{
	$list = array('type' => 'loop_area', 'props' => array('source' => 'product.related'), 'children' => array());
	$wrap = function ($rows, $join) use ($list) {
		return array('type' => 'root', 'props' => array(), 'children' => array(
			array('type' => 'semantic', 'props' => _widget_cond_rows_props($rows, $join), 'children' => array($list)),
		));
	};
	$rows = array(array('__has_related', 'not_empty'), array('__name', 'not_empty'));
	pg_assert_false(_civ_related_list_shown_when_off($wrap($rows, 'all')), 'all: cut by __has_related');
	pg_assert_true(_civ_related_list_shown_when_off($wrap($rows, 'any')), 'any: another row may hold');
	pg_assert_false(_civ_related_list_shown_when_off($wrap(array(array('__has_related', 'not_empty'), array('__has_related', 'eq', '1')), 'any')), 'any: every row fails');
}
