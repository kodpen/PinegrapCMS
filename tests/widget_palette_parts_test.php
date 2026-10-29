<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the visual designer's widget palette group: which parts
 * (SW_DEFAULT_PARTS) and values (Bind Data options) a widget offers, which
 * of them are drawn per row of a loop and so fit only inside one, which
 * element each value binds (text, href or src), and the node an item
 * creates, a part of the page's detail widget outside it included. The
 * helpers run under Node.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _wpal_js()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
}

// The source between $from and the "    }" line closing $last, or ''.
function _wpal_cut($from, $last, $close = "\n    }\n")
{
	$js = _wpal_js();
	$start = strpos($js, $from);
	$fn = ($start === false) ? false : strpos($js, $last, $start);
	$end = ($fn === false) ? false : strpos($js, $close, $fn);
	return ($start === false || $end === false) ? '' : substr($js, $start, $end - $start + strlen($close));
}

// The pure helpers, from SD_PALETTE_ROW_PARTS to the end of _sdPaletteNode().
function _wpal_block()
{
	return _wpal_cut("\n    var SD_PALETTE_ROW_PARTS = ", "\n    function _sdPaletteNode(");
}

// The kinds' part tables (SW_DEFAULT_PARTS), their builders never called.
function _wpal_parts()
{
	return _wpal_cut("\n    var SW_DEFAULT_PARTS = {", "\n    var SW_DEFAULT_PARTS = {", "\n    };\n");
}

// $script run under Node after the helpers: its JSON answer, or null when
// Node is missing.
function _wpal_node($script)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) {
		return null;
	}
	$stubs = 'function _sdT(s) { return s; }'
		. 'var _ids = 0; function createNode(type, props, children) { return { _id: "n" + (++_ids), type: type, props: props || {}, children: children || [] }; }';
	$file = tempnam(sys_get_temp_dir(), 'pgwpal');
	file_put_contents($file, $stubs . "\n" . _wpal_parts() . "\n" . _wpal_block() . "\n" . $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));
	return json_decode(implode("\n", $out), true);
}

// The parts a kind offers: one per part, its section, content and inner
// spellings left out; every offered part has a palette name; the parts
// drawn per row fit only in the main loop, a list's in that list.
function test_widget_palette_parts_of_a_kind()
{
	pg_assert_true(_wpal_block() !== '', 'the helpers are found');
	pg_assert_true(_wpal_parts() !== '', 'the part tables are found');
	$res = _wpal_node('var rowOf = function (t) { return t === "__return_actions_html" ? "list" : ""; };'
		. 'var out = {}; Object.keys(SW_DEFAULT_PARTS).forEach(function (k) { out[k] = _sdPalettePartItems(Object.keys(SW_DEFAULT_PARTS[k]), rowOf); });'
		. 'var named = _sdPalettePartLabels(), missing = [];'
		. 'Object.keys(SW_DEFAULT_PARTS).forEach(function (k) { Object.keys(SW_DEFAULT_PARTS[k]).forEach(function (t) {'
		. ' if (!/^(section|content):/.test(t) && !/_inner_html$/.test(t) && !named[t]) missing.push(k + ":" + t); }); });'
		. 'console.log(JSON.stringify({ kinds: out, missing: missing }));');
	if ($res === null) {
		return;
	}
	if (!pg_assert_true(is_array($res) && isset($res['kinds']), 'the script answers')) {
		return;
	}
	pg_assert_same(array(), $res['missing'], 'every part has a palette name');
	$tokens = function ($kind) use ($res) {
		return array_map(function ($p) { return $p['token']; }, $res['kinds'][$kind]);
	};
	pg_assert_same(array('__breadcrumb_html', '__cross_sell_html'), $tokens('catalog_item_view'));
	pg_assert_same(array('__breadcrumb_html', '__filter_chips', '__active_filters_html'), $tokens('catalog_listing'));
	$loops = array();
	foreach ($res['kinds']['order_view'] as $p) {
		$loops[$p['token']] = $p['loop'];
		pg_assert_true($p['label'] !== '' && strpos($p['label'], '__') === false, 'a name, never a token: ' . $p['token']);
	}
	pg_assert_same('main', $loops['__item_form_data_html']);
	pg_assert_same('main', $loops['__item_gift_card_html']);
	pg_assert_same('list', $loops['__return_actions_html']);
	pg_assert_same('', $loops['__timeline']);
	pg_assert_same('main', $res['kinds']['comments'][0]['loop']);
}

// The values: a yes/no one, a part's token, one that needs settings and a
// repeat are left out; a widget value fits anywhere, a list row's and a row
// position only in a loop; the element bound is the token's kind.
function test_widget_palette_values()
{
	$base = array(
		array('token' => '__name', 'label' => 'Product name'),
		array('token' => '__out_of_stock', 'label' => 'Out of stock', 'cond' => true),
		array('token' => '__image_url', 'label' => 'Main image <b>URL</b>'),
		array('token' => '__detail_url', 'label' => 'Page URL'),
		array('token' => '__gallery_image_2', 'label' => 'Gallery image 2'),
		array('token' => '__breadcrumb_html', 'label' => 'Breadcrumb'),
		array('token' => '__details', 'label' => 'Details'),
		array('token' => '__price_block_html', 'label' => 'Price block'),
		array('token' => '__page_link', 'label' => 'Another page'),
		array('token' => '__name', 'label' => 'Again'),
		array('token' => '__value_text', 'label' => 'Also a widget value'),
	);
	$rows = array(
		array('token' => '__value_text', 'label' => 'Value'),
		array('token' => '__related_url', 'label' => 'Product address'),
		array('token' => '__related_in_stock', 'label' => 'In stock', 'cond' => true),
	);
	$pos = array(
		array('token' => '__loop_index', 'label' => 'Row number'),
		array('token' => '__loop_is_first', 'label' => 'First row', 'cond' => true),
	);
	$res = _wpal_node('console.log(JSON.stringify(_sdPaletteValueItems(' . json_encode($base) . ', ' . json_encode($rows) . ', '
		. json_encode($pos) . ', ["__breadcrumb_html"])));');
	if ($res === null) {
		return;
	}
	$by = array();
	foreach ((array) $res as $v) {
		$by[$v['token']] = $v;
	}
	pg_assert_same(array('__name', '__image_url', '__detail_url', '__gallery_image_2', '__details', '__price_block_html', '__value_text', '__related_url', '__loop_index'), array_keys($by));
	pg_assert_same('Product name', $by['__name']['label']);
	pg_assert_same('Main image URL', $by['__image_url']['label']);
	pg_assert_same(array('text', 'src', 'href', 'src', 'text', 'text', 'text', 'href', 'text'), array_values(array_map(function ($v) { return $v['prop']; }, $by)));
	pg_assert_same('', $by['__value_text']['loop'], 'a widget value fits anywhere though a list names it too');
	pg_assert_same('list', $by['__related_url']['loop']);
	pg_assert_same('position', $by['__loop_index']['loop']);
	pg_assert_same('', $by['__name']['loop']);
}

// The loops a place stands in, and what fits there.
function test_widget_palette_loops_and_fit()
{
	$main = array('type' => 'loop_area', 'props' => array());
	$list = array('type' => 'loop_area', 'props' => array('source' => 'product.related'));
	$rcp = array('type' => 'recipient_loop_area', 'props' => array());
	$div = array('type' => 'semantic', 'props' => array('tag' => 'div'));
	$chains = array(
		array($div),
		array($div, $main, $div),
		array($list, $div, $main),
		array($div, $rcp, $main),
		array($list),
	);
	$items = array(
		array('token' => '__name', 'loop' => ''),
		array('token' => '__loop_index', 'loop' => 'position'),
		array('token' => '__item_form_data_html', 'loop' => 'main'),
		array('token' => '__related_url', 'loop' => 'list'),
		array('token' => '__value_text', 'loop' => 'list'),
	);
	$res = _wpal_node('var rows = function (s) { return s === "product.related" ? ["__related_url", "__related_name"] : []; };'
		. 'var items = ' . json_encode($items) . ';'
		. 'console.log(JSON.stringify(' . json_encode($chains) . '.map(function (c) { var l = _sdPaletteLoops(c, rows);'
		. ' return items.map(function (it) { return _sdPaletteFits(it, l); }); })));');
	if ($res === null) {
		return;
	}
	pg_assert_same(array(
		array(true, false, false, false, false),
		array(true, true, true, false, false),
		array(true, true, true, true, false),
		array(true, false, false, false, false),
		array(true, true, false, true, false),
	), $res);
}

// The node an item creates: a value bound as its kind asks, a part as its
// builder draws it (several elements in one box), and outside the widget a
// part of it.
function test_widget_palette_node()
{
	$res = _wpal_node('var mk = function (tag) { return createNode("semantic", { tag: tag }, []); };'
		. 'console.log(JSON.stringify({'
		. ' text: _sdPaletteNode({ token: "__name", label: "Product name", prop: "text" }, null),'
		. ' html: _sdPaletteNode({ token: "__price_block_html", label: "Price block", prop: "text" }, null),'
		. ' link: _sdPaletteNode({ token: "__detail_url", label: "Page URL", prop: "href" }, null),'
		. ' img: _sdPaletteNode({ token: "__image_url", label: "Main image", prop: "src" }, null),'
		. ' one: _sdPaletteNode({ token: "__breadcrumb_html", label: "Breadcrumb", part: true }, [mk("nav")]),'
		. ' two: _sdPaletteNode({ token: "__timeline", label: "Order timeline", part: true }, [mk("ol"), mk("p")]),'
		. ' away: _sdPaletteNode({ token: "__name", label: "Product name", prop: "text", away: true, sid: "31" }, null),'
		. ' awayPart: _sdPaletteNode({ token: "__breadcrumb_html", label: "Breadcrumb", part: true, away: true, sid: 31 }, [mk("nav")])'
		. '}));');
	if ($res === null) {
		return;
	}
	pg_assert_same('span', $res['text']['props']['tag']);
	pg_assert_same(array('text' => '__name'), $res['text']['props']['_bindings']);
	pg_assert_same('Product name', $res['text']['props']['customName']);
	pg_assert_false(isset($res['text']['props']['partOf']), 'inside the widget no partOf');
	pg_assert_same('div', $res['html']['props']['tag']);
	pg_assert_same('a', $res['link']['props']['tag']);
	pg_assert_same(array('href' => '__detail_url'), $res['link']['props']['_bindings']);
	pg_assert_same('Page URL', $res['link']['props']['text']);
	pg_assert_same('content', $res['img']['type']);
	pg_assert_same('image', $res['img']['props']['contentType']);
	pg_assert_same(array('src' => '__image_url'), $res['img']['props']['_bindings']);
	pg_assert_same('', $res['img']['props']['alt']);
	pg_assert_same('nav', $res['one']['props']['tag']);
	pg_assert_same('div', $res['two']['props']['tag']);
	pg_assert_same('Order timeline', $res['two']['props']['customName']);
	pg_assert_same(array('ol', 'p'), array_map(function ($c) { return $c['props']['tag']; }, $res['two']['children']));
	pg_assert_same(31, $res['away']['props']['partOf']);
	pg_assert_same(31, pg_sw_away_of($res['away']));
	pg_assert_same(31, $res['awayPart']['props']['partOf']);
}
