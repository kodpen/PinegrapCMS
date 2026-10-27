<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the editor's twin of the value condition test (_sdCondEval()
 * and its helpers in style_designer.js, run under Node) against the
 * server's pg_sw_cond_eval(): the shared vectors, a grid of values and
 * operands both sides answer, and the single separator with three digits
 * after it, which the site's decimal point decides.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Whether Node can be run here; the tests pass quietly without it.
function _cond_eval_node_ok()
{
	if (!function_exists('exec')) return false;
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	return $code === 0;
}

// The twin's source, cut out of style_designer.js.
function _cond_eval_js_source()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	$cut = function ($from, $to) use ($js) {
		$a = strpos($js, $from);
		pg_assert_true($a !== false, 'found: ' . trim($from));
		$b = strpos($js, $to, $a);
		return substr($js, $a, $b + strlen($to) - $a);
	};
	$src = $cut("\n    var SD_COND_WS = ", ";\n");
	foreach (array('_sdCondPlain', '_sdCondText', '_sdCondFold', '_sdCondFigureOk', '_sdCondFigureNumber', '_sdCondAmount',
		'_sdCondNumber', '_sdCondComparable', '_sdCondIsWhole', '_sdCondEval') as $fn) {
		$src .= $cut("\n    function " . $fn . '(', "\n    }\n");
	}
	return $src;
}

// The twin's answers to $cases (rows of op, value, operand, decimal), in order.
function _cond_eval_js(array $cases)
{
	$script = _cond_eval_js_source()
		. 'var cases = ' . json_encode($cases) . ";\n"
		. "console.log(JSON.stringify(cases.map(function (c) { return _sdCondEval(c[0], c[1], c[2], c[3]); })));\n";
	$file = @tempnam(sys_get_temp_dir(), 'pgcond');
	if ($file === false) return null;
	file_put_contents($file, $script);
	$out = array();
	$code = 1;
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);
	$printed = implode("\n", $out);
	pg_assert_same(0, $code, $printed);
	$answer = json_decode($printed, true);
	pg_assert_true(is_array($answer) && count($answer) === count($cases), 'Node printed the answers: ' . substr($printed, 0, 300));
	return $answer;
}

// The site's decimal point, as pg_parse_amount() reads it here.
function _cond_eval_decimal()
{
	$s = pg_number_separators();
	return $s['decimal'];
}

// Every shared vector gives the same answer in the editor.
function test_widget_condition_eval_vectors()
{
	if (!_cond_eval_node_ok()) return;
	$rows = json_decode((string) file_get_contents(dirname(__FILE__) . '/fixtures/widget_condition_vectors.json'), true);
	pg_assert_true(is_array($rows) && count($rows) >= 60, 'the vectors file holds its rows');
	$cases = array();
	foreach ($rows as $row) {
		foreach (array('.', ',') as $decimal) {
			$cases[] = array($row['op'], $row['value'], $row['operand'], $decimal);
		}
	}
	$answer = _cond_eval_js($cases);
	foreach ($cases as $i => $c) {
		$row = $rows[intdiv($i, 2)];
		pg_assert_same($row['expect'], $answer[$i],
			'#' . intdiv($i, 2) . ' (' . $c[3] . ') ' . $c[0] . ' ' . json_encode($c[1], JSON_UNESCAPED_UNICODE) . ' ' . json_encode($c[2], JSON_UNESCAPED_UNICODE));
	}
}

// A grid of values and operands under every operator: the editor answers
// as the server does, with the server's decimal point.
function test_widget_condition_eval_grid()
{
	if (!_cond_eval_node_ok()) return;
	$values = array('', ' ', "\xC2\xA0", '0', '1', '-1', '12', '12,50', '12.50', '₺12,50', '-₺12,50', '€12,50 EUR', '12,50 TL',
		'10%', '1.234,56', '1,234.56', '1 234,56', '1.500', '1,500', '24.10.2026', '1.2,5', 'Kat 2', 'KAT 2', 'ODA 2',
		'US$12', 'R$ 3', 'İstanbul', 'ISTANBUL', 'ırmak', 'IRMAK', 'complete', 'Complete', 'pending', 'a  b', "a\u{2028}b",
		'9007199254740993', '1e3', '0x10', '12 tl', 'Ünye', 'şeker', '½');
	$operands = array('0', '1', '12.5', '12,5', '1500', '1.5', '3', 'istanbul', 'irmak', 'complete', 'kat', 'a b', 'EUR', '%', 'ün', '-1');
	$cases = array();
	$decimal = _cond_eval_decimal();
	foreach (array_keys(pg_sw_cond_ops()) as $op) {
		foreach ($values as $value) {
			foreach ($operands as $operand) {
				$cases[] = array($op, $value, $operand, $decimal);
			}
		}
	}
	$cases[] = array('bogus', 'x', 'x', $decimal);
	$answer = _cond_eval_js($cases);
	foreach ($cases as $i => $c) {
		pg_assert_same(pg_sw_cond_eval($c[0], $c[1], $c[2]), $answer[$i],
			$c[0] . ' ' . json_encode($c[1], JSON_UNESCAPED_UNICODE) . ' ' . json_encode($c[2], JSON_UNESCAPED_UNICODE));
	}
}

// One separator with three digits after it: a decimal point only where
// the site writes one so.
function test_widget_condition_eval_three_digits_follow_the_site()
{
	if (!_cond_eval_node_ok()) return;
	$answer = _cond_eval_js(array(
		array('eq', '1.500', '1500', ','),
		array('eq', '1.500', '1.5', '.'),
		array('eq', '1,500', '1.5', ','),
		array('eq', '1,500', '1500', '.'),
		array('gt', '₺1.500', '100', ','),
		array('gt', '₺1.500', '100', '.'),
	));
	pg_assert_same(array(true, true, true, true, true, false), $answer);
}

// What a visitor reads in a value: tags and comments go, spaces collapse.
function test_widget_condition_eval_text()
{
	if (!_cond_eval_node_ok()) return;
	$script = _cond_eval_js_source()
		. "console.log(JSON.stringify([_sdCondText('<b>12</b> <!-- x -->\\u00a0TL '), _sdCondText(null), _sdCondPlain(' a \\t b ')]));\n";
	$file = @tempnam(sys_get_temp_dir(), 'pgcond');
	if ($file === false) return;
	file_put_contents($file, $script);
	$out = array();
	$code = 1;
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);
	pg_assert_same(0, $code, implode("\n", $out));
	pg_assert_same(array(pg_sw_cond_text("<b>12</b> <!-- x -->\xC2\xA0TL "), '', 'a b'), json_decode(implode("\n", $out), true));
}

// The canvas states: an order view's status badges draw one stage at a
// time, an empty sibling only with the values empty, every element in the
// last state; a node picked from the layer tree is drawn in a state that
// draws it.
// The canvas state functions, cut out of style_designer.js: the value
// conditions and "Hide when empty".
function _cond_eval_canvas_source()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	$cut = function ($from, $to) use ($js) {
		$a = strpos($js, $from);
		pg_assert_true($a !== false, 'found: ' . trim($from));
		$b = strpos($js, $to, $a);
		return substr($js, $a, $b + strlen($to) - $a);
	};
	$src = _cond_eval_js_source()
		. $cut("\n    var SW_COND_OPS = [", "\n    ];\n")
		. $cut("\n    var SW_COND_DENY = [", "\n    ];\n")
		. $cut("\n    var SD_COND_NAMES = ", ";\n")
		. $cut("\n    var SD_OV_STATUS_STAGES = ", ";\n")
		. $cut("\n    var _SD_FLAG_TOKENS = {", "\n    };\n")
		. $cut("\n    var _sdCondCanvasState = ", ";\n")
		. $cut("\n    var SD_HIDE_EMPTY = ", ";\n")
		. $cut("\n    var SD_FIELD_VALUE = ", ";\n");
	foreach (array('_sdCondTokenAllowed', '_sdCondOp', '_sdCondHoldsRequired', '_sdCondCodeStates', '_sdCondTreeTokens',
		'_sdCondCanvasStates', '_sdCondStateOf', '_sdCondCanvasValue', '_sdCondCanvasShows', '_sdCondPresent', '_sdCondSpecs', '_sdCondEvalAll', '_sdCondStateFor',
		'_sdNodeClassHas', '_sdTreeHasHideEmpty', '_sdHideEmptyValueNode', '_sdHideEmptyContentShows', '_sdHideEmptyShows',
		'_sdHideEmptyCanvasOff') as $fn) {
		$src .= $cut("\n    function " . $fn . '(', "\n    }\n");
	}
	return $src;
}

function test_widget_condition_eval_canvas_states()
{
	if (!_cond_eval_node_ok()) return;
	$src = _cond_eval_canvas_source();
	$when = function ($token, $op, $value = null) {
		$param = array('op' => $op);
		if ($value !== null) $param['value'] = $value;
		return array('_bindings' => array('show_if' => $token), '_bindParams' => array('show_if' => $param));
	};
	$badges = array();
	foreach (array('complete', 'pending', 'shipped', 'cancelled', 'refunded', 'default') as $stage) {
		$badges[] = array('type' => 'semantic', 'props' => $when('__order_status_stage', 'eq', $stage), 'children' => array());
	}
	$order = array('type' => 'root', 'props' => array(), 'children' => array_merge($badges, array(
		array('type' => 'semantic', 'props' => $when('__order_note', 'not_empty'), 'children' => array()),
		array('type' => 'semantic', 'props' => $when('__order_note', 'empty'), 'children' => array()),
		array('type' => 'semantic', 'props' => $when('__has_discount', 'not_empty'), 'children' => array()),
		array('type' => 'semantic', 'props' => $when('__total', 'gt', '0'), 'children' => array()),
		array('type' => 'semantic', 'props' => $when('__edit_url', 'not_empty'), 'children' => array()),
		array('type' => 'semantic', 'props' => $when('__order_note', 'eq'), 'children' => array()),
	)));
	$plain = array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'semantic', 'props' => array(), 'children' => array()),
	));
	$stubs = "function _sdT(s) { return s; }\n"
		. "function _displayValueForBinding(prop, token) { return { __order_note: '<b>Ring twice</b>', __total: '\$1,234.56' }[token] || ''; }\n";
	$script = 'var order = ' . json_encode($order) . ', plain = ' . json_encode($plain) . ";\n"
		. "var shows = function (state) { return order.children.map(function (n) { return _sdCondCanvasShows(n, state) ? 1 : 0; }).join(''); };\n"
		. "var out = { states: _sdCondCanvasStates(order).map(function (s) { return s[0]; }), none: _sdCondCanvasStates(plain),\n"
		. "  first: _sdCondStateOf(order), plainOf: _sdCondStateOf(plain),\n"
		. "  pending: shows('code:__order_status_stage:pending'), empty: shows('empty'), all: shows('all'),\n"
		. "  forShipped: _sdCondStateFor(order, order.children[2]), forEmpty: _sdCondStateFor(order, order.children[7]),\n"
		. "  forDenied: _sdCondStateFor(order, order.children[10]), forOutside: _sdCondStateFor(order, plain.children[0]) };\n"
		. "_sdCondCanvasState = 'empty'; out.kept = _sdCondStateFor(order, order.children[7]);\n"
		. "_sdCondCanvasState = 'sample'; out.sampleOnOrder = _sdCondStateOf(order);\n"
		. "console.log(JSON.stringify(out));\n";
	$file = @tempnam(sys_get_temp_dir(), 'pgcond');
	if ($file === false) return;
	file_put_contents($file, $stubs . $src . $script);
	$out = array();
	$code = 1;
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);
	$printed = implode("\n", $out);
	pg_assert_same(0, $code, $printed);
	$a = json_decode($printed, true);
	pg_assert_true(is_array($a), 'Node printed JSON: ' . $printed);
	pg_assert_same(array('code:__order_status_stage:complete', 'code:__order_status_stage:pending', 'code:__order_status_stage:shipped',
		'code:__order_status_stage:cancelled', 'code:__order_status_stage:refunded', 'code:__order_status_stage:default', 'empty', 'all'), $a['states']);
	pg_assert_same(array(), $a['none'], 'no condition, no states');
	pg_assert_same('code:__order_status_stage:complete', $a['first']);
	pg_assert_same('all', $a['plainOf']);
	// Six badges, note, its empty sibling, a flag, an amount, a refused
	// token, an operator without its value.
	pg_assert_same('010000101100', $a['pending']);
	pg_assert_same('000000010000', $a['empty']);
	pg_assert_same('111111111111', $a['all']);
	pg_assert_same('code:__order_status_stage:shipped', $a['forShipped']);
	pg_assert_same('empty', $a['forEmpty']);
	pg_assert_same('all', $a['forDenied']);
	pg_assert_same('code:__order_status_stage:complete', $a['forOutside']);
	pg_assert_same('empty', $a['kept'], 'a state that draws the node is kept');
	pg_assert_same('code:__order_status_stage:complete', $a['sampleOnOrder'], 'a state the tree does not offer draws its first');
	// The canvas opens on each widget's first state, and a page opened in a
	// tab starts there again.
	$js = (string) file_get_contents(__DIR__ . '/../pinegrap/assets/js/style_designer.js');
	pg_assert_true(strpos($js, "\n    var _sdCondCanvasState = '';\n") !== false, 'the canvas opens on the first state');
	$a = strpos($js, "\n    function _pgTabsSwitch(");
	$b = strpos($js, "\n    }\n", $a);
	pg_assert_true($a !== false && strpos(substr($js, $a, $b - $a), "_sdCondCanvasState = '';") !== false,
		'a tab switch draws the first state again');
}

// The sample rows a list draws after its card name sources the server
// knows and only the row values of their source.
function test_widget_condition_eval_list_sample_rows_name_known_tokens()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	$from = strpos($js, "\n    var SD_LIST_SAMPLE_ROWS = {");
	pg_assert_true($from !== false, 'SD_LIST_SAMPLE_ROWS is found');
	$block = substr($js, $from, strpos($js, "\n    };\n", $from) - $from);
	preg_match_all("/^        '([a-z_]+\.[a-z_]+)': \[(.*?)^        \]/ms", $block, $m, PREG_SET_ORDER);
	pg_assert_true(count($m) >= 3, 'the sources are read');
	$sources = pg_sw_list_sources();
	foreach ($m as $row) {
		pg_assert_true(isset($sources[$row[1]]), $row[1] . ' is a list source');
		$tokens = array();
		foreach ($sources[$row[1]]['tokens'] as $t) {
			$tokens[] = $t[0];
		}
		preg_match_all('/(__[a-z0-9_]+):/', $row[2], $used);
		foreach ($used[1] as $token) {
			pg_assert_true(in_array($token, $tokens, true), $row[1] . ' has ' . $token);
		}
	}
}

// The timeline's sample rows draw one icon each: the kind of their row,
// the delivered one greyed as an estimate.
function test_widget_condition_eval_timeline_sample_rows()
{
	if (!_cond_eval_node_ok()) return;
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	$cut = function ($from, $to) use ($js) {
		$a = strpos($js, $from);
		pg_assert_true($a !== false, 'found: ' . trim($from));
		$b = strpos($js, $to, $a);
		return substr($js, $a, $b + strlen($to) - $a);
	};
	$src = _cond_eval_js_source()
		. $cut("\n    var SW_COND_OPS = [", "\n    ];\n")
		. $cut("\n    var SW_COND_DENY = [", "\n    ];\n")
		. $cut("\n    var SD_COND_NAMES = ", ";\n")
		. $cut("\n    var SD_OV_STATUS_STAGES = ", ";\n")
		. $cut("\n    var _SD_FLAG_TOKENS = {", "\n    };\n")
		. $cut("\n    var SD_LIST_SAMPLE_ROWS = {", "\n    };\n");
	foreach (array('_sdCondTokenAllowed', '_sdCondOp', '_sdCondHoldsRequired', '_sdCondCodeStates', '_sdCondCanvasValue', '_sdCondCanvasShows', '_sdCondPresent', '_sdCondSpecs', '_sdCondEvalAll') as $fn) {
		$src .= $cut("\n    function " . $fn . '(', "\n    }\n");
	}
	$timeline = json_decode(json_encode(pg_sw_part_order_timeline()), true);
	$stubs = "function _sdT(s) { return s; }\nvar _sdListSample = null;\n"
		. "function _displayValueForBinding(prop, token) { if (_sdListSample && token in _sdListSample) return _sdListSample[token]; return { __step_kind: 'created' }[token] || ''; }\n";
	$script = 'var timeline = ' . json_encode($timeline) . ";\n"
		. "var drawn = function () { var out = []; (function walk(n, on) {\n"
		. "  if (!n || typeof n !== 'object') return; if (Array.isArray(n)) { n.forEach(function (c) { walk(c, on); }); return; }\n"
		. "  var here = on && _sdCondCanvasShows(n, 'sample');\n"
		. "  if (here && /pg-ov-timeline-icon/.test((n.props && n.props.cssClass) || '')) out.push(n.props.cssClass.replace(/^pg-ov-timeline-icon /, ''));\n"
		. "  walk(n.children || [], here); })(timeline, true); return out.join('|'); };\n"
		. "var out = [drawn()];\n"
		. "SD_LIST_SAMPLE_ROWS['order.steps'].forEach(function (row) { _sdListSample = row; out.push(drawn()); });\n"
		. "console.log(JSON.stringify(out));\n";
	$file = @tempnam(sys_get_temp_dir(), 'pgcond');
	if ($file === false) return;
	file_put_contents($file, $stubs . $src . $script);
	$out = array();
	$code = 1;
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);
	$printed = implode("\n", $out);
	pg_assert_same(0, $code, $printed);
	pg_assert_same(array('bg-primary', 'bg-success', 'bg-info', 'bg-secondary'), json_decode($printed, true), $printed);
}

// The main loop's sample rows: two in a kind that repeats it without real
// records or sample comments; none in a single-record kind, a kind with
// server ghosts, the comments widget, inside a sample, or on a second loop.
function test_widget_condition_eval_main_loop_sample_rows()
{
	if (!_cond_eval_node_ok()) return;
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	$cut = function ($from, $to) use ($js) {
		$a = strpos($js, $from);
		pg_assert_true($a !== false, 'found: ' . trim($from));
		$b = strpos($js, $to, $a);
		return substr($js, $a, $b + strlen($to) - $a);
	};
	$src = $cut("\n    var _SD_GHOST_TYPES = ", ";\n")
		. $cut("\n    var _SW_ACCOUNT_SINGLE = ", ";\n")
		. $cut("\n    var _SW_ONE_SCREEN = ", ";\n");
	foreach (array('_sdLoopIsSingle', '_sdListSampleRows', '_sdFirstLoopArea', '_sdListIsSourced', '_swKindOf') as $fn) {
		$src .= $cut("\n    function " . $fn . '(', "\n    }\n");
	}
	$stubs = "var _sdGhostsOn = true, _sdListSample = null, _sdCommentSample = null, _sysWidgetRenderCfg = null, _sysWidgetRenderTreeRoot = null;\n"
		. "var SW_LIST_SOURCES = {}, SD_LIST_SAMPLE_ROWS = {};\n";
	$script = "var loop = { type: 'loop_area', props: {}, children: [{ type: 'semantic', props: {} }] };\n"
		. "var second = { type: 'loop_area', props: {}, children: [] };\n"
		. "var root = { type: 'root', props: {}, children: [loop, second] };\n"
		. "var rows = function (cfg, node) { _sysWidgetRenderCfg = cfg; _sysWidgetRenderTreeRoot = root; return _sdListSampleRows(node || loop).length; };\n"
		. "var out = { cart: rows({ regionType: 'shopping_cart' }), order: rows({ regionType: 'order_view' }), second: rows({ regionType: 'shopping_cart' }, second),\n"
		. "  item: rows({ regionType: 'form_item_view' }), listing: rows({ regionType: 'catalog_listing' }), forms: rows({ regionType: 'blog_list' }),\n"
		. "  comments: rows({ regionType: 'comments' }), recent: rows({ regionType: 'comments', mode: 'recent' }), profile: rows({ regionType: 'account_profile' }),\n"
		. "  account: rows({ regionType: 'my_account' }), accountList: rows({ regionType: 'my_account', show_submissions: true }) };\n"
		. "_sdListSample = {}; out.inSample = rows({ regionType: 'shopping_cart' }); _sdListSample = null;\n"
		. "_sdGhostsOn = false; out.off = rows({ regionType: 'shopping_cart' });\n"
		. "console.log(JSON.stringify(out));\n";
	$file = @tempnam(sys_get_temp_dir(), 'pgcond');
	if ($file === false) return;
	file_put_contents($file, $stubs . $src . $script);
	$out = array();
	$code = 1;
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);
	$printed = implode("\n", $out);
	pg_assert_same(0, $code, $printed);
	pg_assert_same(array('cart' => 2, 'order' => 2, 'second' => 0, 'item' => 0, 'listing' => 0, 'forms' => 0, 'comments' => 0, 'recent' => 0,
		'profile' => 0, 'account' => 0, 'accountList' => 2, 'inSample' => 0, 'off' => 0), json_decode($printed, true), $printed);
}

// "Hide when empty" on the canvas: a tree with a marked element offers the
// states; in the empty state a box judged by its value element goes with
// its heading, a box of designed words alone stays, a list empties; with
// samples a bound value with a sample shows and one without goes; every
// element in "All elements"; a picked hidden node finds a state that
// draws it.
function test_widget_condition_eval_hide_empty_canvas()
{
	if (!_cond_eval_node_ok()) return;
	$src = _cond_eval_canvas_source();
	$node = function ($props, $children = array(), $type = 'semantic') {
		return array('type' => $type, 'props' => $props, 'children' => $children);
	};
	$heading = $node(array('tag' => 'h3', 'text' => 'Related'));
	$tree = $node(array(), array(
		$node(array('cssClass' => 'border pg-hide-if-empty'), array($heading,
			$node(array('cssClass' => 'row pg-field-value'), array($node(array('source' => 'product.related'), array(), 'loop_area'))))),
		$node(array('cssClass' => 'pg-hide-if-empty'), array($node(array('text' => 'Designed words')))),
		$node(array('cssClass' => 'pg-hide-if-empty'), array($node(array('_bindings' => array('text' => '__note'))))),
		$node(array('cssClass' => 'pg-hide-if-empty'), array($node(array('_bindings' => array('text' => '__none'))))),
		$node(array('cssClass' => 'pg-hide-if-empty'), array($node(array('cssClass' => 'pg-hide-if-empty'), array($node(array('_bindings' => array('text' => '__none'))))))),
		$node(array('cssClass' => 'pg-hide-if-empty'), array($node(array('contentType' => 'image', 'src' => ''), array(), 'content'))),
	), 'root');
	$plain = $node(array(), array($node(array('cssClass' => 'not-pg-hide-if-empty'))), 'root');
	$stubs = "function _sdT(s) { return s; }\n"
		. "function _displayValueForBinding(prop, token) { return { __note: 'Ring twice' }[token] || ''; }\n";
	$script = 'var tree = ' . json_encode($tree) . ', plain = ' . json_encode($plain) . ";\n"
		. "var off = function (state) { return tree.children.map(function (n) { return _sdHideEmptyCanvasOff(n, state) ? 1 : 0; }).join(''); };\n"
		. "var out = { states: _sdCondCanvasStates(tree).map(function (s) { return s[0]; }), none: _sdCondCanvasStates(plain),\n"
		. "  empty: off('empty'), sample: off('sample'), all: off('all'), value: tree.children[0].children[1].props.cssClass,\n"
		. "  judged: _sdHideEmptyValueNode(tree.children[0]) === tree.children[0].children[1],\n"
		. "  forEmptied: _sdCondStateFor(tree, tree.children[3]) };\n"
		. "console.log(JSON.stringify(out));\n";
	$file = @tempnam(sys_get_temp_dir(), 'pgcond');
	if ($file === false) return;
	file_put_contents($file, $stubs . $src . $script);
	$out = array();
	$code = 1;
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);
	$printed = implode("\n", $out);
	pg_assert_same(0, $code, $printed);
	$a = json_decode($printed, true);
	pg_assert_true(is_array($a), 'Node printed JSON: ' . $printed);
	pg_assert_same(array('sample', 'empty', 'all'), $a['states']);
	pg_assert_same(array(), $a['none'], 'a class that only looks like it offers none');
	// Heading over a list, designed words, a value with a sample, one
	// without, a box whose only marked row went, a picture without an address.
	pg_assert_same('101111', $a['empty']);
	pg_assert_same('000111', $a['sample']);
	pg_assert_same('000000', $a['all']);
	pg_assert_true($a['judged'], 'the value element judges the box');
	pg_assert_same('all', $a['forEmptied'], 'a node no value fills is drawn in All elements');
}
