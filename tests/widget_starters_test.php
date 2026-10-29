<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the layout gallery's "Starters" (includes/fn/widgets_starters.php
 * and pg_sw_starter_layouts() in includes/fn/widgets_gallery.php): the
 * listing per kind, the binding contract (a starter places only values,
 * conditions and actions its kind knows: the designer's token palettes and
 * "Show only when" flags php tools/check_bindings.php compares with the
 * renderers, and the actions of the kind's default layout), style-neutral
 * markup, and the wiring of the gallery source.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function widget_starters_test_designer()
{
	static $source = null;

	if ($source === null) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	}

	return $source;
}

// The text from the bracket or brace at $start to its match.
function widget_starters_test_block($source, $start)
{
	$open  = $source[$start];
	$close = ($open === '[') ? ']' : '}';
	$depth = 0;
	$limit = strlen($source);

	for ($at = $start; $at < $limit; $at++) {
		if ($source[$at] === $open) $depth++;
		if ($source[$at] === $close && --$depth === 0) break;
	}

	return substr($source, $start, $at - $start + 1);
}

// The first names of the ['name', label] pairs of designer palette $var.
function widget_starters_test_palette($var)
{
	$source = widget_starters_test_designer();

	if (!preg_match('/var ' . preg_quote($var, '/') . '\s*=\s*\[/', $source, $m, PREG_OFFSET_CAPTURE)) {
		return array();
	}

	$body = widget_starters_test_block($source, $m[0][1] + strlen($m[0][0]) - 1);
	preg_match_all("/\['([a-z0-9_]+)'/", $body, $names);

	return $names[1];
}

// The "Show only when" flags SW_VISIBILITY_FLAGS lists for $type.
function widget_starters_test_flags($type)
{
	$source = widget_starters_test_designer();

	if (!preg_match('/var SW_VISIBILITY_FLAGS\s*=\s*\{/', $source, $m, PREG_OFFSET_CAPTURE)) {
		return array();
	}

	$body = widget_starters_test_block($source, $m[0][1] + strlen($m[0][0]) - 1);

	if (!preg_match('/\n\s*' . preg_quote($type, '/') . '\s*:\s*\[/', $body, $t, PREG_OFFSET_CAPTURE)) {
		return array();
	}

	$list = widget_starters_test_block($body, $t[0][1] + strlen($t[0][0]) - 1);
	preg_match_all("/\['([a-z][a-z0-9_]*)'\s*,/", $list, $flags);

	return $flags[1];
}

// The source of the kind's case in _buildStarterTree(): its default layout,
// with the bodies of the part helpers it calls (_sw…/_sdPart…), so a part a
// helper draws counts as the layout's.
function widget_starters_test_default_case($kind)
{
	$source = widget_starters_test_designer();
	$from   = strpos($source, 'function _buildStarterTree(');
	$at     = ($from === false) ? false : strpos($source, "case '" . $kind . "': {", $from);

	if ($at === false) {
		return '';
	}

	$case = widget_starters_test_block($source, $at + strlen("case '" . $kind . "': "));
	preg_match_all('/\b(_sw[A-Za-z]+|_sdPart[A-Za-z]+|_sdWishlistHeart)\(/', $case, $calls);

	foreach (array_unique($calls[1]) as $fn) {
		if (preg_match('/function ' . preg_quote($fn, '/') . '\([^)]*\)\s*\{/', $source, $f, PREG_OFFSET_CAPTURE)) {
			$case .= "\n" . widget_starters_test_block($source, $f[0][1] + strlen($f[0][0]) - 1);
		}
	}

	return $case;
}

// What each kind may place: values, conditions, and the actions/sections
// its default layout uses.
function widget_starters_test_contract($kind)
{
	$palettes = array(
		'catalog_listing'   => array('SW_CATALOG_TOKEN_GROUPS'),
		'comments_recent'   => array('SW_COMMENTS_RECENT_TOKEN_GROUPS'),
		'form_list_view'    => array('SW_STANDARD_FIELD_OPTIONS', 'SW_FORM_LIST_VIEW_TOKENS'),
		'catalog_item_view' => array('SW_CATALOG_ITEM_TOKEN_GROUPS', 'SW_PRODUCT_ENTITY_TOKENS'),
		'shopping_cart'     => array('SW_SHOPPING_CART_TOKEN_GROUPS'),
		'order_preview'     => array('SW_ORDER_PREVIEW_TOKEN_GROUPS'),
		'form_item_view'    => array('SW_STANDARD_FIELD_OPTIONS'),
	);
	$flag_types = array('comments_recent' => 'comments');

	$values = array();
	foreach (isset($palettes[$kind]) ? $palettes[$kind] : array() as $var) {
		$values = array_merge($values, widget_starters_test_palette($var));
	}

	return array(
		'values'  => $values,
		'flags'   => widget_starters_test_flags(isset($flag_types[$kind]) ? $flag_types[$kind] : $kind),
		'default' => widget_starters_test_default_case($kind),
	);
}

// Every node of a list of nodes, depth first.
function widget_starters_test_nodes($nodes)
{
	$out = array();

	foreach ((array) $nodes as $node) {
		if (!is_array($node)) continue;
		$out[] = $node;
		$out   = array_merge($out, widget_starters_test_nodes(isset($node['children']) ? $node['children'] : array()));
	}

	return $out;
}

// The nodes a starter places: its repeated part, or its whole layout.
function widget_starters_test_placed($item)
{
	return widget_starters_test_nodes($item['body'] ? $item['body'] : $item['loop']);
}

// The quoted name a marker for a part of the default layout must find in
// that layout's source: 'loop', 'content:<type>', 'region:<type>' or
// '<binding>:<value>'.
function widget_starters_test_keep_needle($key)
{
	if ($key === 'loop') return "'loop_area'";
	$at = strpos($key, ':');

	return ($at === false || $at === 0 || $at === strlen($key) - 1) ? '' : "'" . substr($key, $at + 1) . "'";
}

function test_widget_starters_gallery_listing()
{
	$kinds = pg_sw_starter_kinds();
	pg_assert_same(array('catalog_listing', 'form_list_view', 'comments_recent', 'catalog_item_view', 'shopping_cart', 'order_preview', 'form_item_view'), $kinds, 'seven kinds have starters');

	foreach ($kinds as $kind) {
		$items = pg_sw_starter_layouts($kind);
		pg_assert_true(count($items) >= 2 && count($items) <= 3, $kind . ': two or three starters');

		$keys = array();
		foreach ($items as $item) {
			pg_assert_same(array('key', 'label', 'loop', 'wrap', 'body'), array_keys($item), $kind . ': the shape the editor reads');
			pg_assert_true($item['label'] !== '' && $item['label'] !== $item['key'], $kind . '/' . $item['key'] . ': a name of its own');
			pg_assert_true((count($item['loop']) > 0) !== (count($item['body']) > 0), $kind . '/' . $item['key'] . ': a repeated part or a whole layout, not both');
			$keys[] = $item['key'];
		}
		pg_assert_same(count($keys), count(array_unique($keys)), $kind . ': keys are unique');
	}

	// The detail widgets hold forms and controls outside a repeated part:
	// their starters are whole layouts.
	foreach (array('catalog_item_view', 'shopping_cart', 'order_preview', 'form_item_view') as $kind) {
		foreach (pg_sw_starter_layouts($kind) as $item) {
			pg_assert_true(count($item['body']) > 0, $kind . '/' . $item['key'] . ': a whole layout');
		}
	}

	pg_assert_same(array(), pg_sw_starter_layouts('order_view'), 'a kind without starters lists none');
	pg_assert_same(array(), pg_sw_starter_layouts(''), 'no kind lists none');
	pg_assert_same(array(), pg_sw_starter_layouts(array('catalog_listing')), 'a kind that is not a string lists none');
}

function test_widget_starters_place_only_known_values()
{
	foreach (pg_sw_starter_kinds() as $kind) {
		$contract = widget_starters_test_contract($kind);
		pg_assert_true(count($contract['values']) > 0, $kind . ': its palette is read');
		pg_assert_true($contract['default'] !== '', $kind . ': its default layout is read');

		foreach (pg_sw_starter_layouts($kind) as $item) {
			$where = $kind . '/' . $item['key'];

			foreach (widget_starters_test_placed($item) as $node) {
				$bindings = isset($node['props']['_bindings']) ? $node['props']['_bindings'] : array();

				if (isset($node['props']['starterKeep'])) {
					$needle = widget_starters_test_keep_needle((string) $node['props']['starterKeep']);
					pg_assert_true($needle !== '', $where . ': marker ' . $node['props']['starterKeep'] . ' is spelled as the editor reads it');
					pg_assert_contains($needle, $contract['default'], $where . ': marker ' . $node['props']['starterKeep'] . ' names a part of the default layout');
					pg_assert_same(array('starterKeep'), array_keys($node['props']), $where . ': a marker carries nothing else');
					pg_assert_same(array(), $node['children'], $where . ': a marker holds nothing');
				}

				foreach ($bindings as $prop => $value) {
					if ($prop === 'eo_visible_if') {
						pg_assert_true(in_array($value, $contract['flags'], true), $where . ': flag ' . $value . ' is listed for the kind');
					} elseif ($prop === 'action' || $prop === 'section' || $prop === 'value') {
						pg_assert_contains("'" . $value . "'", $contract['default'], $where . ': ' . $prop . ' ' . $value . ' is one the default layout uses');
					} else {
						pg_assert_true(in_array($value, $contract['values'], true), $where . ': value ' . $value . ' (' . $prop . ') is in the kind\'s palette');
					}
				}
			}
		}
	}
}

function test_widget_starters_are_style_neutral()
{
	$types = array('semantic', 'content', 'component', 'col', 'row', 'container');

	foreach (pg_sw_starter_kinds() as $kind) {
		foreach (pg_sw_starter_layouts($kind) as $item) {
			$where = $kind . '/' . $item['key'];
			$json  = json_encode($item);

			$placed = widget_starters_test_placed($item);

			if ($item['body']) {
				// A whole layout has the widget's one repeated part: its own,
				// or the default layout's kept by the 'loop' marker.
				$loops = 0;
				foreach ($placed as $node) {
					if ($node['type'] === 'loop_area' || (isset($node['props']['starterKeep']) && $node['props']['starterKeep'] === 'loop')) $loops++;
				}
				pg_assert_same(1, $loops, $where . ': one repeated part');
				pg_assert_same(array(), $item['wrap'], $where . ': a whole layout wraps nothing');
				pg_assert_true(strpos($json, 'starterFields') === false, $where . ': the record comes with the kept repeated part');
			} else {
				pg_assert_false(strpos($json, 'loop_area') !== false, $where . ': no loop area of its own');
			}
			pg_assert_false((bool) preg_match('/#[0-9a-fA-F]{3,6}\b/', (string) json_encode(array_column($placed, 'props'))), $where . ': no fixed colour');

			foreach ($placed as $node) {
				pg_assert_true(in_array($node['type'], $types, true) || ($item['body'] && $node['type'] === 'loop_area'), $where . ': node type ' . $node['type']);
				pg_assert_false(isset($node['props']['style']), $where . ': no inline style prop');

				foreach (isset($node['props']['_attrs']) ? $node['props']['_attrs'] : array() as $attr) {
					pg_assert_false(strtolower($attr['name']) === 'style', $where . ': no style attribute');
				}
			}

			foreach (array_keys($item['wrap']) as $prop) {
				pg_assert_true(in_array($prop, array('cssClass', 'gutter'), true), $where . ': the holder gets only classes and a gutter');
			}
		}
	}

	// A form list's record goes where the starter marks it, once.
	foreach (pg_sw_starter_layouts('form_list_view') as $item) {
		$marked = 0;
		foreach (widget_starters_test_nodes($item['loop']) as $node) {
			if (!empty($node['props']['starterFields'])) $marked++;
		}
		pg_assert_same(1, $marked, 'form_list_view/' . $item['key'] . ': one place for the record');
	}
}

function test_widget_starters_wiring()
{
	$designer = widget_starters_test_designer();

	pg_assert_contains("sub_action: 'starter_layouts'", $designer, 'the gallery reads the starters');
	pg_assert_contains("head(_sdT('Starters'))", $designer, 'the gallery shows a Starters source');
	pg_assert_contains("String(key).indexOf('st:') === 0", $designer, 'Try takes a starter');
	pg_assert_contains('_sdLgShow(sid, key, si.label, st)', $designer, 'a starter is tried like every other layout (Keep, Cancel, undo)');
	pg_assert_contains('delete n.props.starterFields', $designer, 'the record marker is not saved');
	pg_assert_contains('_sdLgStarterBody(kind, it, base)', $designer, 'a whole-layout starter is built from the default layout');
	pg_assert_contains('n.props.starterKeep', $designer, 'a marker takes the default layout\'s part');

	$panel = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/shared_component.php');
	pg_assert_contains("case 'starter_layouts':", $panel, 'the panel answers the gallery');
	pg_assert_contains('pg_sw_starter_layouts($sc_st_kind)', $panel, 'through the gallery listing');

	$cache = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/page_cache.php');
	pg_assert_contains("'template_layouts', 'starter_layouts')", $cache, 'reading the starters leaves the page cache alone');
}
