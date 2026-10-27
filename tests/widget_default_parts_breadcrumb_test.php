<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the catalog breadcrumb default part (includes/fn/widgets_parts.php)
 * and the catalog.crumbs list it draws (widgets_lists_product.php): every
 * placement the catalog widgets read (the two old tokens, a section binding,
 * the breadcrumb content type, and the listing's breadcrumb before the
 * toolbar) shows the visitor the same words, links and classes as the
 * markup the renderers built before; the editor builds the same part.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The translation extractor: which fields of a crumb enter the site
// translation.
require_once(PG_FUNCTIONS_DIR . '/includes/translate/store.php');
require_once(PG_FUNCTIONS_DIR . '/includes/translate/extract.php');
require_once(PG_FUNCTIONS_DIR . '/includes/translate/content.php');

// The crumbs as the renderers printed them before the part: each crumb an
// <li class="breadcrumb-item"> holding a link, words, or span.active.
function _parts_bc_old_inner($rows)
{
	$html = '<ol class="breadcrumb mb-0">';
	foreach ($rows as $r) {
		if ($r['__crumb_kind'] === 'link') $in = '<a href="' . h($r['__crumb_url']) . '">' . h($r['__crumb_label']) . '</a>';
		elseif ($r['__crumb_kind'] === 'active') $in = '<span class="active">' . h($r['__crumb_label']) . '</span>';
		else $in = '<span>' . h($r['__crumb_label']) . '</span>';
		$html .= '<li class="breadcrumb-item">' . $in . '</li>';
	}
	return $html . '</ol>';
}

function _parts_bc_old_full($rows, $class)
{
	return '<nav class="' . $class . '" aria-label="breadcrumb">' . _parts_bc_old_inner($rows) . '</nav>';
}

function _parts_bc_groups()
{
	return array(
		array('id' => 3, 'name' => 'Shoes & Bags', 'address_name' => 'shoes'),
		array('id' => 7, 'name' => 'Boots', 'address_name' => ''),
		array('id' => 9, 'name' => 'Winter', 'address_name' => 'winter boots'),
	);
}

// The listing's and the detail view's crumbs as the old walks composed them.
function test_widget_parts_breadcrumb_rows()
{
	$main = lang('Main Catalog');
	pg_assert_same(array(pg_sw_list_crumb_row($main, '', 'active')), pg_sw_list_crumb_rows_listing(array(), '/shop', '/shop/'), 'listing root: where the visitor is');
	pg_assert_same(array(
		pg_sw_list_crumb_row($main, '/shop', 'link'),
		pg_sw_list_crumb_row('Shoes & Bags', '/shop/shoes', 'link'),
		pg_sw_list_crumb_row('Boots', '/shop', 'link'),
		pg_sw_list_crumb_row('Winter', '', 'active'),
	), pg_sw_list_crumb_rows_listing(_parts_bc_groups(), '/shop', '/shop/'), 'listing: groups link, the open one is where the visitor is');

	pg_assert_same(array(
		pg_sw_list_crumb_row($main, '/shop', 'link'),
		pg_sw_list_crumb_row('Shoes & Bags', '/shop/shoes', 'link'),
		pg_sw_list_crumb_row('Boots', '', 'text'),
		pg_sw_list_crumb_row('Winter', '/shop/winter%20boots', 'link'),
		pg_sw_list_crumb_row('Red boot', '', 'active'),
	), pg_sw_list_crumb_rows_item(_parts_bc_groups(), '/shop', false, 'Red boot'), 'detail: a group without an address is words');
	pg_assert_same(array(
		pg_sw_list_crumb_row($main, '', 'text'),
		pg_sw_list_crumb_row('Shoes & Bags', '', 'text'),
		pg_sw_list_crumb_row('Boots', '', 'text'),
		pg_sw_list_crumb_row('Winter', '', 'active'),
	), pg_sw_list_crumb_rows_item(_parts_bc_groups(), '', true, 'ignored'), 'detail without a catalog page, on a group');
	pg_assert_same(array(pg_sw_list_crumb_row($main, '', 'text')), pg_sw_list_crumb_rows_item(array(), '', false, null), 'no product, no group');

	$d = pg_sw_list_sources();
	$tokens = array();
	foreach ($d['catalog.crumbs']['tokens'] as $pair) $tokens[] = $pair[0];
	pg_assert_same($tokens, array_keys(pg_sw_list_crumb_row('a', 'b', 'link')), 'row keys are the tokens');
	pg_assert_same('crumbs', $d['catalog.crumbs']['family'], 'its own family');
	pg_assert_same(null, pg_sw_list_key_guard('crumbs'), 'no guard');
}

// $tree given its parts at the widget door, drawn and finished in a $kind
// widget whose page shows $rows (null: no breadcrumb).
function _parts_bc_render($tree, $kind, $rows)
{
	$tree = json_decode(pg_sw_default_parts_apply(json_encode($tree), $kind, array('regionType' => $kind)), true);
	$scope = pg_sw_finish_open(array('sid' => 46, 'kind' => $kind, 'email' => false));
	try {
		$html = trim(_render_tree_node($tree, 0, 0));
		if ($rows !== null && pg_sw_list_wanted('crumbs')) pg_sw_list_publish('crumbs', array('catalog.crumbs' => array(1 => $rows)));
		$html = pg_sw_sweep_tokens(pg_sw_fill_longest($html, array('__has_breadcrumb' => ($rows !== null) ? '1' : '')));
	} finally {
		pg_sw_finish_close();
	}
	return pg_sw_finish_widget_html($html, $scope);
}

function _parts_bc_visible($html)
{
	$html = str_replace('<', ' <', $html);
	return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8')));
}

function _parts_bc_classes($html)
{
	preg_match_all('/\sclass="([^"]*)"/', $html, $m);
	return array_values(array_filter(array_map('trim', $m[1]), 'strlen'));
}

function _parts_bc_hrefs($html)
{
	preg_match_all('/\shref="([^"]*)"/', $html, $m);
	return $m[1];
}

function _parts_bc_same($old, $new, $what)
{
	pg_assert_same(_parts_bc_visible($old), _parts_bc_visible($new), $what . ': same words');
	pg_assert_same(_parts_bc_classes($old), _parts_bc_classes($new), $what . ': same classes in the same order');
	pg_assert_same(_parts_bc_hrefs($old), _parts_bc_hrefs($new), $what . ': same links');
	pg_assert_true(strpos($new, '^^') === false && strpos($new, '<!--pg-') === false && strpos($new, '{{') === false, $what . ': no token or marker left');
}

function _parts_bc_root($children)
{
	return array('type' => 'root', 'props' => array(), 'children' => $children);
}

// The main loop: a card, and an empty list of the products bought together
// that draws nothing and keeps the detail view's cross-sell part out.
function _parts_bc_loop()
{
	return array('type' => 'loop_area', 'props' => array(), 'children' => array(
		array('type' => 'semantic', 'props' => array('tag' => 'div', 'cssClass' => 'card', 'text' => 'Card'), 'children' => array()),
		array('type' => 'loop_area', 'props' => array('source' => 'product.related'), 'children' => array()),
	));
}

// Each placement draws what the renderer drew, in both widgets.
function test_widget_parts_breadcrumb_match_the_old_markup()
{
	$cases = array(
		'catalog_listing'   => array('pg-sw-breadcrumb mb-3', pg_sw_list_crumb_rows_listing(_parts_bc_groups(), '/shop', '/shop/')),
		'catalog_item_view' => array('pg-civ-breadcrumb mb-3', pg_sw_list_crumb_rows_item(_parts_bc_groups(), '/shop', false, 'Red <boot>')),
	);
	foreach ($cases as $kind => $case) {
		list($class, $rows) = $case;
		// The full token, bound and literal.
		$bound = _parts_bc_render(_parts_bc_root(array(
			array('type' => 'semantic', 'props' => array('tag' => 'div', 'cssClass' => 'mb-2', '_bindings' => array('text' => '__breadcrumb_html')), 'children' => array()),
			_parts_bc_loop(),
		)), $kind, $rows);
		_parts_bc_same('<div class="mb-2">' . _parts_bc_old_full($rows, $class) . '</div><div class="card">Card</div>', $bound, $kind . ' token');
		pg_assert_contains('aria-label="breadcrumb"', $bound, $kind . ': said as breadcrumb');
		$literal = _parts_bc_render(_parts_bc_root(array(
			array('type' => 'content', 'props' => array('contentType' => 'custom_html', 'html' => '^^__breadcrumb_html^^'), 'children' => array()),
			_parts_bc_loop(),
		)), $kind, $rows);
		_parts_bc_same('<div>' . _parts_bc_old_full($rows, $class) . '</div><div class="card">Card</div>', $literal, $kind . ' custom HTML token');

		// The inner token in the design's own nav.
		$inner = _parts_bc_render(_parts_bc_root(array(
			array('type' => 'semantic', 'props' => array('tag' => 'nav', 'cssClass' => 'own', 'text' => '^^__breadcrumb_inner_html^^'), 'children' => array()),
			_parts_bc_loop(),
		)), $kind, $rows);
		_parts_bc_same('<nav class="own">' . _parts_bc_old_inner($rows) . '</nav><div class="card">Card</div>', $inner, $kind . ' inner token');

		// The section binding: the nav kept, its children replaced.
		$section = _parts_bc_render(_parts_bc_root(array(
			array('type' => 'semantic', 'props' => array('tag' => 'nav', 'cssClass' => 'pg-sw-breadcrumb mb-3', '_bindings' => array('section' => 'breadcrumb')), 'children' => array(
				array('type' => 'semantic', 'props' => array('tag' => 'span', 'text' => 'replaced'), 'children' => array()),
			)),
			_parts_bc_loop(),
		)), $kind, $rows);
		_parts_bc_same('<nav class="pg-sw-breadcrumb mb-3">' . _parts_bc_old_inner($rows) . '</nav><div class="card">Card</div>', $section, $kind . ' section');

		// The content type: the whole breadcrumb in its place.
		$content = _parts_bc_render(_parts_bc_root(array(
			array('type' => 'content', 'props' => array('contentType' => 'breadcrumb', 'cssClass' => 'mb-3'), 'children' => array()),
			_parts_bc_loop(),
		)), $kind, $rows);
		_parts_bc_same(_parts_bc_old_full($rows, $class) . '<div class="card">Card</div>', $content, $kind . ' content type');

		// No breadcrumb (no product, group navigation off): nothing.
		$none = _parts_bc_render(_parts_bc_root(array(
			array('type' => 'content', 'props' => array('contentType' => 'breadcrumb'), 'children' => array()),
			_parts_bc_loop(),
		)), $kind, null);
		pg_assert_true(strpos($none, 'breadcrumb') === false, $kind . ': no path, no nav');
	}
	pg_assert_contains('&lt;boot&gt;', _parts_bc_render(_parts_bc_root(array(
		array('type' => 'content', 'props' => array('contentType' => 'breadcrumb'), 'children' => array()),
	)), 'catalog_item_view', $cases['catalog_item_view'][1]), 'a name escaped once');
}

// The listing drew its breadcrumb before the toolbar, at the main loop,
// unless the layout placed one: now a part put before the main loop once.
function test_widget_parts_breadcrumb_listing_lead()
{
	$rows = pg_sw_list_crumb_rows_listing(_parts_bc_groups(), '/shop', '/shop/');
	$tree = _parts_bc_root(array(
		array('type' => 'semantic', 'props' => array('tag' => 'h1', 'text' => 'Shop'), 'children' => array()),
		array('type' => 'semantic', 'props' => array('tag' => 'div', 'cssClass' => 'row'), 'children' => array(_parts_bc_loop())),
	));
	$given = json_decode(pg_sw_default_parts_apply(json_encode($tree), 'catalog_listing'), true);
	pg_assert_same('nav', $given['children'][1]['children'][0]['props']['tag'], 'just before the main loop');
	pg_assert_same('loop_area', $given['children'][1]['children'][1]['type'], 'the loop after it');
	pg_assert_same(json_encode($given), pg_sw_default_parts_apply(json_encode($given), 'catalog_listing'), 'placed once');
	_parts_bc_same('<h1>Shop</h1><div class="row">' . _parts_bc_old_full($rows, 'pg-sw-breadcrumb mb-3') . '<div class="card">Card</div></div>',
		_parts_bc_render($tree, 'catalog_listing', $rows), 'the lead');

	// A placed breadcrumb, a crumbs list of the design's own, a detail view
	// and a template's placeholder get none.
	$section = _parts_bc_root(array(
		array('type' => 'semantic', 'props' => array('tag' => 'nav', '_bindings' => array('section' => 'breadcrumb')), 'children' => array()),
		_parts_bc_loop(),
	));
	pg_assert_same(1, substr_count(pg_sw_default_parts_apply(json_encode($section), 'catalog_listing'), '"tag":"nav"'), 'a section binding places it');
	$own = _parts_bc_root(array(array('type' => 'loop_area', 'props' => array('source' => 'catalog.crumbs'), 'children' => array()), _parts_bc_loop()));
	pg_assert_same(json_encode($own), pg_sw_default_parts_apply(json_encode($own), 'catalog_listing'), 'a crumbs list places it');
	pg_assert_same(json_encode($tree), pg_sw_default_parts_apply(json_encode($tree), 'catalog_item_view'), 'the detail view never pushed one');
	$placeholder = _parts_bc_root(array(array('type' => 'loop_area', 'props' => array(), 'children' => array())));
	pg_assert_same(json_encode($placeholder), pg_sw_default_parts_apply(json_encode($placeholder), 'catalog_listing'), 'a placeholder');
}

// The crumbs list stands anywhere in a listing; the old tokens are no longer
// filled or raw.
function test_widget_parts_breadcrumb_tokens_retired()
{
	$raw = pg_sw_raw_token_keys();
	pg_assert_false(isset($raw['__breadcrumb_html']) || isset($raw['__breadcrumb_inner_html']), 'not raw');
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_catalog.php');
	pg_assert_true(strpos($src, "'__breadcrumb_html'") === false && strpos($src, "'__breadcrumb_inner_html'") === false, 'no renderer fills them');
	pg_assert_true(strpos($src, 'pg-breadcrumb-placeholder') === false, 'the marker is not read');
}

// A crumb's kind is a code ('link', 'text', 'active'), the same in every
// language: the text the three crumb elements compare it with is no words
// to translate, or a translated "active" would never match and every crumb
// would draw empty on a page in another language.
function test_widget_parts_breadcrumb_kind_is_not_translated()
{
	$found = 0;
	$walk = function ($node) use (&$walk, &$found) {
		if (!is_array($node)) return;
		$props = (isset($node['props']) && is_array($node['props'])) ? $node['props'] : array();
		if (isset($props['_bindings']['show_if']) && $props['_bindings']['show_if'] === '__crumb_kind') {
			$found++;
			$what = $props['tag'] . ' ' . $props['_bindParams']['show_if']['value'];
			pg_assert_false(pg_sw_cond_operand_is_words($props), $what . ': a code, not words');
			foreach (pg_tr_node_fields($node['type'], $props) as $spec) {
				pg_assert_true($spec[0] !== '_cond:show_if', $what . ': not taken into the translation');
			}
		}
		foreach ((isset($node['children']) && is_array($node['children'])) ? $node['children'] : array() as $child) $walk($child);
	};
	foreach (pg_sw_part_catalog_breadcrumb_item() as $node) $walk($node);
	pg_assert_same(3, $found, 'the three crumb elements');
}

// The editor's part, leads and starters (style_designer.js) are the server's.
function test_widget_parts_breadcrumb_editor_twin()
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
	$src = $cut("\n    var SD_COND_NAMES = ", ";\n") . $cut("\n    var SW_DEFAULT_PARTS = {", "\n    };\n") . $cut("\n    var SW_DEFAULT_LEADS = {", "\n    };\n");
	foreach (array('_sdDefaultParts', '_sdPartTokenOf', '_sdPartHost', '_sdPartEl', '_sdPartWhen', '_sdPartCatalogCrumbs', '_sdPartCatalogBreadcrumb',
		'_sdInsertBeforeMainLoop', '_sdDefaultLeads', '_sdPartPlaced', '_sdListIsSourced') as $fn) {
		$src .= $cut("\n    function " . $fn . '(', "\n    }\n");
	}
	$tree = _parts_bc_root(array(
		array('type' => 'semantic', 'props' => array('tag' => 'div', '_bindings' => array('text' => '__breadcrumb_html')), 'children' => array()),
		array('type' => 'semantic', 'props' => array('tag' => 'nav', 'cssClass' => 'x', '_bindings' => array('section' => 'breadcrumb', 'eo_visible_if' => 'f')), 'children' => array(
			array('type' => 'semantic', 'props' => array('tag' => 'span'), 'children' => array()),
		)),
		array('type' => 'content', 'props' => array('contentType' => 'paragraph', 'text' => '^^__breadcrumb_inner_html^^'), 'children' => array()),
		array('type' => 'content', 'props' => array('contentType' => 'breadcrumb', 'cssClass' => 'mb-3'), 'children' => array()),
		array('type' => 'content', 'props' => array('contentType' => 'custom_html', 'html' => ' ^^__breadcrumb_html^^ '), 'children' => array()),
		array('type' => 'loop_area', 'props' => array('source' => 'product.related'), 'children' => array()),
	));
	$lead = _parts_bc_root(array(array('type' => 'semantic', 'props' => array('tag' => 'div'), 'children' => array(_parts_bc_loop()))));
	$stubs = "var _n = 0; function gid() { return 'n' + (++_n); }\nfunction _sdT(s) { return s; }\n"
		. "function createNode(type, props, children) { return { _id: gid(), type: type, props: props || {}, children: children || [] }; }\n";
	$script = 'var trees = { catalog_listing: ' . json_encode($tree) . ', catalog_item_view: ' . json_encode($tree) . " };\n"
		. 'var lead = ' . json_encode($lead) . ";\n"
		. "var changed = _sdDefaultParts(trees.catalog_listing, 'catalog_listing', {}) && _sdDefaultParts(trees.catalog_item_view, 'catalog_item_view', {});\n"
		. "var led = _sdDefaultLeads(lead, 'catalog_listing', null);\n"
		. "console.log(JSON.stringify({ changed: changed, again: _sdDefaultParts(trees.catalog_listing, 'catalog_listing', null), trees: trees, led: led, lead: lead,"
		. " again_lead: _sdDefaultLeads(lead, 'catalog_listing', null), item_lead: _sdDefaultLeads(JSON.parse(JSON.stringify(lead)), 'catalog_item_view', null) }));\n";
	$file = @tempnam(sys_get_temp_dir(), 'pgpartsbc');
	if ($file === false) return;
	file_put_contents($file, $stubs . $src . $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);
	$printed = implode("\n", $out);
	pg_assert_same(0, $code, $printed);
	$answer = json_decode($printed, true);
	pg_assert_true(is_array($answer), 'Node printed JSON: ' . $printed);
	pg_assert_true($answer['changed'], 'the editor gave the parts');
	pg_assert_false($answer['again'], 'nothing left to give');
	foreach (array('catalog_listing', 'catalog_item_view') as $kind) {
		$server = json_decode(pg_sw_default_parts_apply(json_encode($tree), $kind), true);
		pg_assert_same(_parts_bc_norm($server), _parts_bc_norm($answer['trees'][$kind]), $kind . ': the same tree');
	}
	pg_assert_true($answer['led'] && !$answer['again_lead'] && !$answer['item_lead'], 'the lead once, in the listing alone');
	pg_assert_same(_parts_bc_norm(json_decode(pg_sw_default_parts_apply(json_encode($lead), 'catalog_listing'), true)), _parts_bc_norm($answer['lead']), 'the same lead');

	// The starters build the part.
	pg_assert_contains("_sdPartCatalogBreadcrumb('pg-sw-breadcrumb mb-3')[0],", $js, 'the listing starter');
	pg_assert_contains("var breadcrumbNode = _sdPartCatalogBreadcrumb('pg-civ-breadcrumb mb-3')[0];", $js, 'the detail view starter');
}

// A tree without what only one side writes (the editor's ids and names),
// keys in one order.
function _parts_bc_norm($node)
{
	if (!is_array($node)) return $node;
	unset($node['_id'], $node['customName']);
	foreach ($node as $k => $v) $node[$k] = _parts_bc_norm($v);
	if (array_keys($node) !== range(0, count($node) - 1)) ksort($node);
	return $node;
}
