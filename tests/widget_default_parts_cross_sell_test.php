<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the catalog detail view's cross-sell default part
 * (includes/fn/widgets_parts.php): the products bought together show the
 * visitor the same words, links and classes as the cards the renderer built
 * before, where it added them; the variant script's redraw draws the
 * design's part; the editor builds the same part.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Three products bought together, normalised as the lookup gives them: a
// picture and a discount, no picture, no short description.
function _parts_xs_items()
{
	return array(
		_civ_normalize_xsell_item(array('id' => 4, 'name' => 'CUP-1', 'short_description' => 'Tea <cup>', 'image_name' => 'cup.jpg', 'price_cents' => 2000, 'address_name' => 'cup'), '/shop/', array(4 => 1500)),
		_civ_normalize_xsell_item(array('id' => 5, 'name' => 'POT-1', 'short_description' => 'Pot', 'image_name' => '', 'price_cents' => 3000, 'address_name' => 'pot'), '/shop/', array()),
		_civ_normalize_xsell_item(array('id' => 6, 'name' => 'LID-1', 'short_description' => '', 'image_name' => 'lid.jpg', 'price_cents' => 500, 'address_name' => ''), '/shop/', array()),
	);
}

// The cards as _civ_render_cross_sell() printed them with the detail view's
// settings (four columns, a heading, prices, square pictures).
function _parts_xs_old_html($items)
{
	$html = '<div class="pg-civ-cross-sell mt-4" data-pg-civ-cross-sell="1">';
	if (!$items) return $html . '</div>';
	$html .= '<h3 class="h5 mb-3">' . h(lang('Frequently bought together')) . '</h3><div class="row g-3">';
	foreach ($items as $it) {
		$html .= '<div class="col-6 col-md-3"><a href="' . h($it['url']) . '" class="card h-100 text-decoration-none text-body">'
			. ($it['image_url'] !== '' ? '<img src="' . h($it['image_url']) . '" class="card-img-top" alt="" loading="lazy">' : '<div class="card-img-top bg-body-tertiary"></div>')
			. '<div class="card-body p-2"><div class="small fw-semibold text-truncate">' . h($it['short_description'] !== '' ? $it['short_description'] : $it['name']) . '</div>'
			. '<div class="small mt-1">' . $it['price_block_html'] . '</div></div></a></div>';
	}
	return $html . '</div></div>';
}

function _parts_xs_card()
{
	return array('type' => 'semantic', 'props' => array('tag' => 'div', 'cssClass' => 'record', 'text' => 'Record'), 'children' => array());
}

// $tree given its parts at the widget door, drawn and finished as a detail
// view of product 3 whose products bought together are $items; $has is
// __has_related as the renderer sets it.
function _parts_xs_render($tree, $items, $has)
{
	$tree = json_decode(pg_sw_default_parts_apply(json_encode($tree), 'catalog_item_view', array('regionType' => 'catalog_item_view')), true);
	$scope = pg_sw_finish_open(array('sid' => 47, 'kind' => 'catalog_item_view', 'email' => false));
	try {
		$html = trim(_render_tree_node($tree, 0, 0));
		if (pg_sw_list_wanted('product')) pg_sw_list_publish('product', array('product.related' => array(3 => pg_sw_list_product_related_rows($items))));
		$html = pg_sw_sweep_tokens(pg_sw_fill_longest($html, pg_sw_escape_token_values(array('__id' => '3', '__has_related' => $has))));
	} finally {
		pg_sw_finish_close();
	}
	return pg_sw_finish_widget_html($html, $scope);
}

function _parts_xs_visible($html)
{
	$html = str_replace('<', ' <', $html);
	return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8')));
}

function _parts_xs_classes($html)
{
	// The picture's own way of loading (srcset, a <picture>) is not the
	// card's look: its classes are read from the <img> alone.
	preg_match_all('/<(?!source)[a-z0-9]+\s[^>]*?\bclass="([^"]*)"/i', $html, $m);
	return array_values(array_filter(array_map('trim', $m[1]), 'strlen'));
}

function _parts_xs_links($html)
{
	preg_match_all('/<a\s[^>]*href="([^"]*)"/', $html, $m);
	preg_match_all('/<img\s[^>]*src="([^"]*)"/', $html, $i);
	return array($m[1], $i[1]);
}

function _parts_xs_same($old, $new, $what)
{
	pg_assert_same(_parts_xs_visible($old), _parts_xs_visible($new), $what . ': same words');
	pg_assert_same(_parts_xs_classes($old), _parts_xs_classes($new), $what . ': same classes in the same order');
	pg_assert_same(_parts_xs_links($old), _parts_xs_links($new), $what . ': same links and pictures');
	pg_assert_true(strpos($new, '^^') === false && strpos($new, '<!--pg-') === false && strpos($new, '{{') === false, $what . ': no token or marker left');
}

// Added after the record when nothing places it, as the renderer did.
function test_widget_parts_cross_sell_matches_the_old_cards()
{
	$items = _parts_xs_items();
	$tree = array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'loop_area', 'props' => array(), 'children' => array(_parts_xs_card())),
	));
	$new = _parts_xs_render($tree, $items, '1');
	_parts_xs_same('<div class="record">Record</div>' . _parts_xs_old_html($items), $new, 'three cards');
	pg_assert_contains('data-pg-civ-cross-sell="1"', $new, 'the variant script finds it');
	pg_assert_contains('Tea &lt;cup&gt;', $new, 'escaped once');

	// None to suggest: the empty box the variant script fills.
	_parts_xs_same('<div class="record">Record</div>' . _parts_xs_old_html(array()), _parts_xs_render($tree, array(), '0'), 'none');
	// Switched off, or no product: nothing.
	$off = _parts_xs_render($tree, $items, '');
	pg_assert_same(array('record'), _parts_xs_classes($off), 'off: nothing');
	pg_assert_same('Record', _parts_xs_visible($off), 'off: no words');

	// The old token, bound in place.
	$placed = array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'semantic', 'props' => array('tag' => 'section', 'cssClass' => 'mine', '_bindings' => array('text' => '__cross_sell_html')), 'children' => array()),
		array('type' => 'loop_area', 'props' => array(), 'children' => array(_parts_xs_card())),
	));
	_parts_xs_same('<section class="mine">' . _parts_xs_old_html($items) . '</section><div class="record">Record</div>', _parts_xs_render($placed, $items, '1'), 'the token');
}

// Where the part goes: the end of the main loop once, else the end of the
// tree; nowhere when the tree places the token or its own list.
function test_widget_parts_cross_sell_placement()
{
	$apply = function ($tree) {
		return json_decode(pg_sw_default_parts_apply(json_encode($tree), 'catalog_item_view'), true);
	};
	$loop = array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'semantic', 'props' => array('tag' => 'div'), 'children' => array(array('type' => 'loop_area', 'props' => array(), 'children' => array()))),
		array('type' => 'semantic', 'props' => array('tag' => 'footer'), 'children' => array()),
	));
	$given = $apply($loop);
	pg_assert_same(pg_sw_part_catalog_cross_sell(), $given['children'][0]['children'][0]['children'], 'in the empty main loop, before what follows it');
	pg_assert_same(json_encode($given), json_encode($apply($given)), 'once');

	$flat = array('type' => 'root', 'props' => array(), 'children' => array(_parts_xs_card()));
	$given = $apply($flat);
	pg_assert_same('pg-civ-cross-sell mt-4', $given['children'][1]['props']['cssClass'], 'no main loop: the end of the tree');

	$outside = array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'loop_area', 'props' => array(), 'children' => array(_parts_xs_card())),
		array('type' => 'semantic', 'props' => array('tag' => 'div', 'text' => '^^__cross_sell_html^^'), 'children' => array()),
	));
	pg_assert_same(1, substr_count(json_encode($apply($outside)), 'pg-civ-cross-sell mt-4'), 'placed outside the loop: that one alone');
	$own = array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'loop_area', 'props' => array(), 'children' => array(array('type' => 'loop_area', 'props' => array('source' => 'product.related'), 'children' => array()))),
	));
	pg_assert_same(json_encode($own), json_encode($apply($own)), 'a list of its own takes its place');
	pg_assert_true(strpos(json_encode($apply($loop)), 'pg-civ-cross-sell') !== false, 'detail view');
	pg_assert_true(strpos(pg_sw_default_parts_apply(json_encode($loop), 'catalog_listing'), 'pg-civ-cross-sell') === false, 'never in a listing');
}

// The redraw for a variant draws the widget's own part, its changes kept.
function test_widget_parts_cross_sell_variant_redraw()
{
	$tree = json_decode(pg_sw_default_parts_apply(json_encode(array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'loop_area', 'props' => array(), 'children' => array(_parts_xs_card())),
	))), 'catalog_item_view'), true);
	$node = _civ_cross_sell_part_node($tree);
	pg_assert_same('pg-civ-cross-sell mt-4', $node['props']['cssClass'], 'found');
	$node['children'][0]['props']['text'] = 'You may also like';
	$node['children'][0]['props']['cssClass'] = 'h4';
	$items = _parts_xs_items();
	$html = _civ_cross_sell_part_draw($node, 47, 3, pg_sw_list_product_related_rows($items));
	pg_assert_contains('<h3 class="h4">You may also like</h3>', preg_replace('/>\s+/', '>', $html), 'the design\'s heading');
	pg_assert_same(_parts_xs_links(_parts_xs_old_html($items)), _parts_xs_links($html), 'the variant\'s cards');
	pg_assert_true(strpos($html, '^^') === false && strpos($html, '<!--pg-') === false, 'no token or marker left');
	pg_assert_same(null, _civ_cross_sell_part_node(array('type' => 'root', 'children' => array(_parts_xs_card()))), 'none');

	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_civ_variants.js');
	pg_assert_contains("widget_id: parseInt(data.widget_id, 10)", $js, 'the script names the widget');
	pg_assert_true(strpos($js, 'cross_sell_heading') === false && strpos($js, 'class="card h-100') === false, 'no cards of its own');
	$api = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/commerce.php');
	pg_assert_contains("_civ_cross_sell_part_html((int)\$request['widget_id']", $api, 'the action draws it');
	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/pg_carousel_layout.css');
	pg_assert_contains('.pg-civ-cross-sell .card-img-top{aspect-ratio:1/1;object-fit:cover}', $css, 'square pictures');
}

function test_widget_parts_cross_sell_token_retired()
{
	$raw = pg_sw_raw_token_keys();
	pg_assert_false(isset($raw['__cross_sell_html']), 'not raw');
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_catalog.php');
	pg_assert_true(strpos($src, "'__cross_sell_html'") === false && strpos($src, 'function _civ_render_cross_sell(') === false, 'no renderer fills it');
}

// The editor's part, its loop tail and the starter (style_designer.js).
function test_widget_parts_cross_sell_editor_twin()
{
	if (!function_exists('exec')) return;
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) return;

	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	$cut = function ($from, $to) use ($js) {
		$a = strpos($js, $from);
		pg_assert_true($a !== false, 'found: ' . trim($from));
		$b = strpos($js, $to, $a);
		return substr($js, $a, $b + strlen($to) - $a);
	};
	$src = $cut("\n    var SW_DEFAULT_PARTS = {", "\n    };\n") . $cut("\n    var SW_DEFAULT_LOOP_TAILS = {", "\n    };\n");
	foreach (array('_sdDefaultParts', '_sdPartTokenOf', '_sdPartHost', '_sdPartEl', '_sdPartWhen', '_sdPartCatalogCrumbs', '_sdPartCatalogBreadcrumb',
		'_sdPartCatalogCrossSell', '_sdDefaultLoopTails', '_sdMainLoop', '_sdPartPlaced', '_sdListIsSourced', '_sdPartOrderItemForm', '_sdPartOrderItemGiftCard') as $fn) {
		$src .= $cut("\n    function " . $fn . '(', "\n    }\n");
	}
	$trees = array(
		'token' => array('type' => 'root', 'props' => array(), 'children' => array(
			array('type' => 'content', 'props' => array('contentType' => 'paragraph', 'text' => '^^__cross_sell_html^^'), 'children' => array()),
			array('type' => 'loop_area', 'props' => array(), 'children' => array()),
		)),
		'loop' => array('type' => 'root', 'props' => array(), 'children' => array(array('type' => 'loop_area', 'props' => array(), 'children' => array(_parts_xs_card())))),
		'flat' => array('type' => 'root', 'props' => array(), 'children' => array(_parts_xs_card())),
	);
	$stubs = "var _n = 0; function gid() { return 'n' + (++_n); }\nfunction _sdT(s) { return s; }\n"
		. "function createNode(type, props, children) { return { _id: gid(), type: type, props: props || {}, children: children || [] }; }\n";
	$script = 'var trees = ' . json_encode($trees) . ";\n"
		. "Object.keys(trees).forEach(function (k) { _sdDefaultParts(trees[k], 'catalog_item_view', null); _sdDefaultLoopTails(trees[k], 'catalog_item_view', null); });\n"
		. "var again = _sdDefaultLoopTails(trees.loop, 'catalog_item_view', null);\n"
		. "console.log(JSON.stringify({ trees: trees, again: again, part: _sdPartCatalogCrossSell() }));\n";
	$file = @tempnam(sys_get_temp_dir(), 'pgpartsxs');
	if ($file === false) return;
	file_put_contents($file, $stubs . $src . $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);
	$printed = implode("\n", $out);
	pg_assert_same(0, $code, $printed);
	$answer = json_decode($printed, true);
	pg_assert_true(is_array($answer), 'Node printed JSON: ' . $printed);
	foreach ($trees as $k => $tree) {
		$server = json_decode(pg_sw_default_parts_apply(json_encode($tree), 'catalog_item_view'), true);
		pg_assert_same(_parts_xs_norm($server), _parts_xs_norm($answer['trees'][$k]), $k . ': the same tree');
	}
	pg_assert_false($answer['again'], 'once');
	pg_assert_same(_parts_xs_norm(pg_sw_part_catalog_cross_sell()), _parts_xs_norm($answer['part']), 'the part');
	pg_assert_contains("var loopArea = createNode('loop_area', {}, _sdPartCatalogCrossSell());", $js, 'the starter');
}

// A tree without what only one side writes (the editor's ids, names and
// sample text under a text binding), keys in one order.
function _parts_xs_norm($node)
{
	if (!is_array($node)) return $node;
	unset($node['_id'], $node['customName']);
	if (isset($node['_bindings']['text']) && isset($node['text'])) unset($node['text']);
	foreach ($node as $k => $v) $node[$k] = _parts_xs_norm($v);
	if (array_keys($node) !== range(0, count($node) - 1)) ksort($node);
	return $node;
}

// A currency sign stored as an entity (the lira's &#8378;, &euro;): the
// rows hold the sign itself, so a card shows it, escaped once.
function test_widget_parts_cross_sell_prices_keep_their_sign()
{
	$items = array(
		array('id' => 4, 'name' => 'CUP-1', 'short_description' => 'Tea cup', 'image_url' => '', 'url' => '/shop/cup',
			'price_formatted' => '&#8378;15,00', 'original_price_formatted' => '&#8378;20,00', 'has_discount' => '1'),
		array('id' => 5, 'name' => 'POT-1', 'short_description' => '', 'image_url' => '', 'url' => '/shop/pot',
			'price_formatted' => '&euro;30,00 EUR', 'original_price_formatted' => '&euro;30,00 EUR', 'has_discount' => '0'),
	);
	$rows = pg_sw_list_product_related_rows($items);
	pg_assert_same('₺15,00', $rows[0]['__related_price'], 'the lira');
	pg_assert_same('₺20,00', $rows[0]['__related_original_price'], 'the lira before the discount');
	pg_assert_same('€30,00 EUR', $rows[1]['__related_price'], 'the euro');
	pg_assert_same('', $rows[1]['__related_original_price'], 'no discount, no price before it');

	$tree = array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'loop_area', 'props' => array(), 'children' => array(_parts_xs_card())),
	));
	$html = _parts_xs_render($tree, $items, '1');
	pg_assert_contains('>₺15,00</span>', $html, 'the sale price');
	pg_assert_contains('>₺20,00</span>', $html, 'the price before the discount');
	pg_assert_contains('>€30,00 EUR</span>', $html, 'the euro');
	pg_assert_true(strpos($html, '&amp;') === false && strpos($html, '&#8378;') === false && strpos($html, '&euro;') === false,
		'escaped once: no entity shows as text');
}

// Switched off, the part draws nothing (__has_related empty), so the detail
// view reads no products bought together for it; a list of them the design
// placed where it still shows is read as before.
function test_widget_parts_cross_sell_off_reads_nothing()
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_catalog.php');
	pg_assert_contains("if (\$_civ_xsell_off && isset(\$_civ_listed['product.related']) && !_civ_related_list_shown_when_off(\$tree_decoded)) {\n"
		. "            unset(\$_civ_listed['product.related']);", $src, 'the detail view leaves them out of its read');

	$with_part = json_decode(pg_sw_default_parts_apply(json_encode(array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'loop_area', 'props' => array(), 'children' => array(_parts_xs_card())),
	))), 'catalog_item_view'), true);
	pg_assert_false(_civ_related_list_shown_when_off($with_part), 'the part alone: nothing to read');

	$own = array('type' => 'loop_area', 'props' => array('source' => 'product.related'), 'children' => array(_parts_xs_card()));
	$box = function ($token, $op, $value, $children) {
		return array('type' => 'semantic', 'props' => array_merge(array('tag' => 'div'), pg_sw_part_when($token, $op, $value)), 'children' => $children);
	};
	$beside = $with_part;
	$beside['children'][] = $own;
	pg_assert_true(_civ_related_list_shown_when_off($beside), 'a list of the design\'s own beside the part');
	pg_assert_true(_civ_related_list_shown_when_off(array('type' => 'root', 'props' => array(), 'children' => array($own))), 'the design\'s own list');
	pg_assert_true(_civ_related_list_shown_when_off(array('type' => 'root', 'props' => array(), 'children' => array($box('__has_discount', 'not_empty', '', array($own))))),
		'under a condition on another value');
	pg_assert_false(_civ_related_list_shown_when_off(array('type' => 'root', 'props' => array(), 'children' => array($box('__has_related', 'eq', '1', array($own))))),
		'under a condition the empty value cuts');
	pg_assert_true(_civ_related_list_shown_when_off(array('type' => 'root', 'props' => array(), 'children' => array($box('__has_related', 'empty', '', array($own))))),
		'under one it keeps');
	pg_assert_false(_civ_related_list_shown_when_off(array('type' => 'root', 'props' => array(), 'children' => array(_parts_xs_card()))), 'no list');
}

// On the canvas the part draws its heading and cards in the sample state
// (__has_related reads as a flag that is set) and nothing with every value
// empty (style_designer.js).
function test_widget_parts_cross_sell_canvas_sample()
{
	if (!function_exists('exec')) return;
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) return;

	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	$cut = function ($from, $to) use ($js) {
		$a = strpos($js, $from);
		pg_assert_true($a !== false, 'found: ' . trim($from));
		$b = strpos($js, $to, $a);
		return substr($js, $a, $b + strlen($to) - $a);
	};
	$src = $cut("\n    var SD_COND_WS = ", ";\n")
		. $cut("\n    var SW_COND_OPS = [", "\n    ];\n")
		. $cut("\n    var SW_COND_DENY = [", "\n    ];\n")
		. $cut("\n    var SD_OV_STATUS_STAGES = ", ";\n")
		. $cut("\n    var _SD_FLAG_TOKENS = {", "\n    };\n");
	foreach (array('_sdCondPlain', '_sdCondText', '_sdCondFold', '_sdCondFigureOk', '_sdCondFigureNumber', '_sdCondAmount', '_sdCondNumber',
		'_sdCondComparable', '_sdCondIsWhole', '_sdCondEval', '_sdCondTokenAllowed', '_sdCondOp', '_sdCondHoldsRequired', '_sdCondCodeStates',
		'_sdCondCanvasValue', '_sdCondCanvasShows', '_sdTokenPlaceholder') as $fn) {
		$src .= $cut("\n    function " . $fn . '(', "\n    }\n");
	}
	// A token no preview map names reads as its placeholder, as in
	// _displayValueForBinding().
	$stubs = "function _sdT(s) { return s; }\nfunction _sdFormFieldOf() { return null; }\n"
		. "function _displayValueForBinding(prop, token) { return _sdTokenPlaceholder(token, null); }\n";
	$script = 'var part = ' . json_encode(pg_sw_part_catalog_cross_sell()) . ";\n"
		. "var drawn = function (state) { var out = []; (function walk(n, on) {\n"
		. "  if (!n || typeof n !== 'object') return; if (Array.isArray(n)) { n.forEach(function (c) { walk(c, on); }); return; }\n"
		. "  var here = on && _sdCondCanvasShows(n, state);\n"
		. "  if (here && n.props && n.props.cssClass) out.push(n.props.cssClass);\n"
		. "  walk(n.children || [], here); })(part, true); return out; };\n"
		. "console.log(JSON.stringify({ sample: drawn('sample'), empty: drawn('empty') }));\n";
	$file = @tempnam(sys_get_temp_dir(), 'pgpartsxs');
	if ($file === false) return;
	file_put_contents($file, $stubs . $src . $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);
	$printed = implode("\n", $out);
	pg_assert_same(0, $code, $printed);
	$a = json_decode($printed, true);
	pg_assert_true(is_array($a), 'Node printed JSON: ' . $printed);
	pg_assert_true(in_array('h5 mb-3', $a['sample'], true) && in_array('row g-3', $a['sample'], true), 'the heading and the cards in the sample: ' . $printed);
	pg_assert_same(array(), $a['empty'], 'nothing with every value empty');
}
