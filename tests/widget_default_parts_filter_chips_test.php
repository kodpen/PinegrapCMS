<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the catalog listing's filter chip default parts
 * (includes/fn/widgets_parts.php) and the catalog.active_filters list they
 * draw (widgets_lists_product.php): the two old tokens, their inner twins
 * and the two section bindings show the visitor the same words, links and
 * classes as the markup the listing built before; the editor builds the
 * same parts.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The attributes the listing read, as the renderer keeps them.
function _parts_fc_attrs()
{
	return array(
		4 => array('attr_label' => 'Colour', 'options' => array(
			array('option_id' => 11, 'option_label' => 'Red & Blue'),
			array('option_id' => 12, 'option_label' => 'Green'),
		)),
		6 => array('attr_label' => 'Size', 'options' => array(
			array('option_id' => 21, 'option_label' => 'M'),
		)),
	);
}

function _parts_fc_rows()
{
	return pg_sw_list_active_filter_rows(array(4 => 11, 6 => 99, 8 => 3), _parts_fc_attrs(), function ($aid) {
		return '?pg_attr_' . $aid . '=&x=<1>';
	});
}

// The rows: one per active attribute filter, the option's label (else its
// number), the attribute's (else "Attribute"), the link that drops it.
function test_widget_parts_filter_chips_rows()
{
	pg_assert_same(array(
		pg_sw_list_active_filter_row('Colour: Red & Blue', '?pg_attr_4=&x=<1>'),
		pg_sw_list_active_filter_row('Size: #99', '?pg_attr_6=&x=<1>'),
		pg_sw_list_active_filter_row(lang('Attribute') . ': #3', '?pg_attr_8=&x=<1>'),
	), _parts_fc_rows(), 'one row per active filter');
	pg_assert_same(array(), pg_sw_list_active_filter_rows(array(), _parts_fc_attrs(), 'strval'), 'none');

	$d = pg_sw_list_sources();
	$tokens = array();
	foreach ($d['catalog.active_filters']['tokens'] as $pair) $tokens[] = $pair[0];
	pg_assert_same($tokens, array_keys(pg_sw_list_active_filter_row('a', 'b')), 'row keys are the tokens');
	$kind = pg_sw_kind_lists('catalog_listing');
	pg_assert_true(isset($kind['catalog.active_filters']), 'offered in the listing');
	$item = pg_sw_kind_lists('catalog_item_view');
	pg_assert_false(isset($item['catalog.active_filters']), 'not in the detail view');
}

// The markup the listing built before the parts.
function _parts_fc_old_attr($rows)
{
	if (!$rows) return '';
	$html = '';
	foreach ($rows as $r) {
		$html .= '<a href="' . h($r['__filter_chip_url']) . '" class="badge text-bg-secondary text-decoration-none me-2 mb-1 d-inline-flex align-items-center" style="font-weight:normal">'
			. h($r['__filter_chip_label']) . ' <span class="bi bi-x-lg ms-2" style="font-size:.7em"></span></a>';
	}
	return '<div class="pg-sw-attr-active-filters mb-3">' . $html . '</div>';
}

function _parts_fc_old_inner($rows, $active, $clear)
{
	if (!$active) return '';
	return _parts_fc_old_attr($rows) . '<span class="ms-auto"><a href="' . h($clear) . '" class="btn btn-sm btn-outline-danger"><i class="bi bi-x-circle me-1"></i>' . h(lang('Clear Filters')) . '</a></span>';
}

function _parts_fc_old_full($rows, $active, $clear)
{
	if (!$active) return '';
	return '<div class="pg-sw-filter-chips d-flex align-items-center gap-2 flex-wrap py-2">' . _parts_fc_old_inner($rows, $active, $clear) . '</div>';
}

// $tree given its parts, drawn and finished in a listing whose page has
// $rows active attribute filters, any filter active when $active.
function _parts_fc_render($tree, $rows, $active, $clear)
{
	$tree = json_decode(pg_sw_default_parts_apply(json_encode($tree), 'catalog_listing', array('regionType' => 'catalog_listing')), true);
	$scope = pg_sw_finish_open(array('sid' => 47, 'kind' => 'catalog_listing', 'email' => false));
	try {
		$html = trim(_render_tree_node($tree, 0, 0));
		if (pg_sw_list_wanted('filter_chips')) pg_sw_list_publish('filter_chips', array('catalog.active_filters' => array(1 => $rows)));
		$html = pg_sw_sweep_tokens(pg_sw_fill_longest($html, pg_sw_escape_token_values(array(
			'__has_active_filters' => $active ? '1' : '0',
			'__has_active_attr_filters' => $rows ? '1' : '0',
			'__clear_filters_url' => $clear,
		))));
	} finally {
		pg_sw_finish_close();
	}
	return pg_sw_finish_widget_html($html, $scope);
}

function _parts_fc_visible($html)
{
	$html = str_replace('<', ' <', $html);
	return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8')));
}

function _parts_fc_classes($html)
{
	preg_match_all('/\sclass="([^"]*)"/', $html, $m);
	return array_values(array_filter(array_map('trim', $m[1]), 'strlen'));
}

function _parts_fc_hrefs($html)
{
	preg_match_all('/\shref="([^"]*)"/', $html, $m);
	return array_map(function ($v) { return html_entity_decode($v, ENT_QUOTES, 'UTF-8'); }, $m[1]);
}

function _parts_fc_same($old, $new, $what)
{
	pg_assert_same(_parts_fc_visible($old), _parts_fc_visible($new), $what . ': same words');
	pg_assert_same(_parts_fc_classes($old), _parts_fc_classes($new), $what . ': same classes in the same order');
	pg_assert_same(_parts_fc_hrefs($old), _parts_fc_hrefs($new), $what . ': same links');
	pg_assert_true(strpos($new, '^^') === false && strpos($new, '<!--pg-') === false && strpos($new, '{{') === false, $what . ': no token or marker left');
}

function _parts_fc_root($children)
{
	return array('type' => 'root', 'props' => array(), 'children' => array_merge($children, array(
		array('type' => 'loop_area', 'props' => array(), 'children' => array(
			array('type' => 'semantic', 'props' => array('tag' => 'div', 'cssClass' => 'card', 'text' => 'Card'), 'children' => array()),
		)),
	)));
}

// Every placement draws what the listing drew: with attribute filters,
// with only a price filter, with none.
function test_widget_parts_filter_chips_match_the_old_markup()
{
	$clear = '?q=a"b&cat=2';
	$states = array(
		'attribute filters' => array(_parts_fc_rows(), true),
		'a price filter'    => array(array(), true),
		'no filter'         => array(array(), false),
	);
	foreach ($states as $state => $st) {
		list($rows, $active) = $st;
		$placements = array(
			'token' => array(
				array('type' => 'semantic', 'props' => array('tag' => 'div', 'cssClass' => 'own', '_bindings' => array('text' => '__filter_chips')), 'children' => array()),
				'<div class="own">' . _parts_fc_old_full($rows, $active, $clear) . '</div>',
			),
			'custom HTML token' => array(
				array('type' => 'content', 'props' => array('contentType' => 'custom_html', 'html' => '^^__filter_chips^^'), 'children' => array()),
				'<div>' . _parts_fc_old_full($rows, $active, $clear) . '</div>',
			),
			'inner token' => array(
				array('type' => 'semantic', 'props' => array('tag' => 'div', 'cssClass' => 'own', 'text' => '^^__filter_chips_inner_html^^'), 'children' => array()),
				'<div class="own">' . _parts_fc_old_inner($rows, $active, $clear) . '</div>',
			),
			'section' => array(
				array('type' => 'semantic', 'props' => array('tag' => 'div', 'cssClass' => 'pg-sw-filter-chips mb-3', '_bindings' => array('section' => 'filter_chips')), 'children' => array(
					array('type' => 'semantic', 'props' => array('tag' => 'span', 'text' => 'replaced'), 'children' => array()),
				)),
				'<div class="pg-sw-filter-chips mb-3">' . _parts_fc_old_inner($rows, $active, $clear) . '</div>',
			),
			'attribute token' => array(
				array('type' => 'semantic', 'props' => array('tag' => 'div', '_bindings' => array('text' => '__active_filters_html')), 'children' => array()),
				'<div>' . _parts_fc_old_attr($rows) . '</div>',
			),
			'attribute inner token' => array(
				array('type' => 'semantic', 'props' => array('tag' => 'div', 'text' => '^^__active_filters_inner_html^^'), 'children' => array()),
				'<div>' . _parts_fc_old_attr($rows) . '</div>',
			),
			'attribute section' => array(
				array('type' => 'semantic', 'props' => array('tag' => 'aside', 'cssClass' => 'x', '_bindings' => array('section' => 'active_filters')), 'children' => array()),
				'<aside class="x">' . _parts_fc_old_attr($rows) . '</aside>',
			),
		);
		foreach ($placements as $what => $case) {
			$new = _parts_fc_render(_parts_fc_root(array($case[0])), $rows, $active, $clear);
			_parts_fc_same($case[1] . '<div class="card">Card</div>', $new, $state . ', ' . $what);
		}
	}
	$new = _parts_fc_render(_parts_fc_root(array(
		array('type' => 'semantic', 'props' => array('tag' => 'div', '_bindings' => array('section' => 'active_filters')), 'children' => array()),
	)), _parts_fc_rows(), true, $clear);
	pg_assert_contains('Colour: Red &amp; Blue', $new, 'a label escaped once');
	pg_assert_true(strpos($new, 'style=') === false, 'no inline style: the look is in the stylesheet');
	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/pg_carousel_layout.css');
	pg_assert_contains('.pg-sw-attr-active-filters > .badge{font-weight:normal}', $css, 'the weight');
	pg_assert_contains('.pg-sw-attr-active-filters > .badge > .bi-x-lg{font-size:.7em}', $css, 'the cross');
}

// The pushed chip row is left out once the design places the chips, as a
// section binding left it out before; the old tokens are no longer filled.
function test_widget_parts_filter_chips_placed_and_retired()
{
	$section = json_decode(pg_sw_default_parts_apply(json_encode(_parts_fc_root(array(
		array('type' => 'semantic', 'props' => array('tag' => 'div', '_bindings' => array('section' => 'filter_chips')), 'children' => array()),
	))), 'catalog_listing'), true);
	pg_assert_true(pg_sw_placed($section, array('catalog.active_filters')), 'the part places the chips');
	pg_assert_false(strpos(json_encode($section), '"section"') !== false, 'the section binding goes');
	pg_assert_false(pg_sw_placed(_parts_fc_root(array()), array('catalog.active_filters')), 'a tree without chips');

	$raw = pg_sw_raw_token_keys();
	foreach (array('__filter_chips', '__filter_chips_inner_html', '__active_filters_html', '__active_filters_inner_html') as $t) {
		pg_assert_false(isset($raw[$t]), $t . ' not raw');
	}
	pg_assert_false(in_array('__filter_chips', pg_sw_cond_denied_tokens(), true), 'a condition may name it: nothing looks for it');
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_catalog.php');
	foreach (array('__filter_chips', '__filter_chips_inner_html', '__active_filters_html', '__active_filters_inner_html', 'filter_chips', 'active_filters') as $t) {
		pg_assert_false((bool)preg_match("/'" . $t . "'\\s*=>/", $src), $t . ' not filled, no section map entry');
	}
	pg_assert_contains("pg_sw_placed(\$tree_decoded, array('catalog.active_filters'", $src, 'the pushed row asks pg_sw_placed()');
}

// The editor's parts and the listing starter (style_designer.js) are the
// server's.
function test_widget_parts_filter_chips_editor_twin()
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
	$src = $cut("\n    var SW_DEFAULT_PARTS = {", "\n    };\n");
	foreach (array('_sdDefaultParts', '_sdPartTokenOf', '_sdPartHost', '_sdPartEl', '_sdPartWhen', '_sdPartCatalogCrumbs', '_sdPartCatalogBreadcrumb',
		'_sdPartCatalogActiveFilters', '_sdPartCatalogFilterChipsInner', '_sdPartCatalogFilterChips') as $fn) {
		$src .= $cut("\n    function " . $fn . '(', "\n    }\n");
	}
	$tree = array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'semantic', 'props' => array('tag' => 'div', '_bindings' => array('text' => '__filter_chips')), 'children' => array()),
		array('type' => 'semantic', 'props' => array('tag' => 'div', 'cssClass' => 'x', '_bindings' => array('section' => 'filter_chips', 'eo_visible_if' => 'f')), 'children' => array(
			array('type' => 'semantic', 'props' => array('tag' => 'span'), 'children' => array()),
		)),
		array('type' => 'content', 'props' => array('contentType' => 'paragraph', 'text' => '^^__filter_chips_inner_html^^'), 'children' => array()),
		array('type' => 'semantic', 'props' => array('tag' => 'div', '_bindings' => array('text' => '__active_filters_html')), 'children' => array()),
		array('type' => 'content', 'props' => array('contentType' => 'custom_html', 'html' => ' ^^__active_filters_inner_html^^ '), 'children' => array()),
		array('type' => 'semantic', 'props' => array('tag' => 'nav', '_bindings' => array('section' => 'active_filters')), 'children' => array()),
	));
	$stubs = "var _n = 0; function gid() { return 'n' + (++_n); }\nfunction _sdT(s) { return s; }\n"
		. "function createNode(type, props, children) { return { _id: gid(), type: type, props: props || {}, children: children || [] }; }\n";
	$script = 'var tree = ' . json_encode($tree) . ";\n"
		. "var changed = _sdDefaultParts(tree, 'catalog_listing', {});\n"
		. "console.log(JSON.stringify({ changed: changed, again: _sdDefaultParts(tree, 'catalog_listing', null), tree: tree }));\n";
	$file = @tempnam(sys_get_temp_dir(), 'pgpartsfc');
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
	$server = json_decode(pg_sw_default_parts_apply(json_encode($tree), 'catalog_listing'), true);
	pg_assert_same(_parts_fc_norm($server), _parts_fc_norm($answer['tree']), 'the same tree');

	pg_assert_contains("customName: _sdT('Filter Chips') }, _sdPartCatalogFilterChipsInner()),", $js, 'the listing starter');
}

// A tree without what only one side writes (the editor's ids and names),
// keys in one order.
function _parts_fc_norm($node)
{
	if (!is_array($node)) return $node;
	unset($node['_id'], $node['customName']);
	foreach ($node as $k => $v) $node[$k] = _parts_fc_norm($v);
	if (array_keys($node) !== range(0, count($node) - 1)) ksort($node);
	return $node;
}
