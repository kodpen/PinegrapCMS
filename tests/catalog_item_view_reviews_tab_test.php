<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Reviews frame of a catalog_item_view widget: the classic
 * comments block on a designed product page reads the product's thread, and
 * the frame around it (a tab and its pane, a "Reviews" section, the region)
 * is left out when the block will draw nothing
 * (includes/fn/widgets_catalog.php, get_page_content.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function civ_reviews_test_el($tag, $class, $children = array(), $extra = array())
{
	return array('type' => 'semantic', 'props' => array_merge(array('tag' => $tag, 'cssClass' => $class), $extra), 'children' => $children);
}

function civ_reviews_test_region()
{
	return array('type' => 'region', 'props' => array('regionType' => 'comments_block', 'regionName' => ''), 'children' => array());
}

// The catalog_item_view starter's tab section: Details, Checkout Options and
// Reviews, the comments block in the third pane. $active is the open tab.
function civ_reviews_test_tabs($active = 0)
{
	$li = function ($n, $label) use ($active) {
		$attrs = array(
			array('name' => 'type', 'value' => 'button'),
			array('name' => 'data-bs-toggle', 'value' => 'tab'),
			array('name' => 'data-bs-target', 'value' => '#civtab_x_' . $n),
			array('name' => 'role', 'value' => 'tab'),
		);
		if ($n === $active) {
			$attrs[] = array('name' => 'aria-selected', 'value' => 'true');
		}
		return civ_reviews_test_el('li', 'nav-item', array(
			array('type' => 'content', 'props' => array('contentType' => 'heading', 'tag' => 'button', 'text' => $label,
				'cssClass' => 'nav-link' . ($n === $active ? ' active' : ''), '_attrs' => $attrs), 'children' => array()),
		));
	};
	$pane = function ($n, $children) use ($active) {
		return civ_reviews_test_el('div', 'tab-pane fade' . ($n === $active ? ' show active' : ''), $children, array('id' => 'civtab_x_' . $n));
	};

	return civ_reviews_test_el('div', 'mt-5', array(
		civ_reviews_test_el('ul', 'nav nav-tabs', array($li(0, 'Details'), $li(1, 'Checkout Options'), $li(2, 'Reviews'))),
		civ_reviews_test_el('div', 'tab-content p-3 border border-top-0', array(
			$pane(0, array(civ_reviews_test_el('p', 'mb-0', array(), array('text' => 'Details')))),
			$pane(1, array(civ_reviews_test_el('div', 'table-responsive'))),
			$pane(2, array(civ_reviews_test_region())),
		)),
	));
}

function civ_reviews_test_attr($node, $name)
{
	foreach ((array) ($node['props']['_attrs'] ?? array()) as $a) {
		if (($a['name'] ?? '') === $name) {
			return $a['value'];
		}
	}
	return null;
}

// The block draws only while the page's comments are on and no comments
// widget on the page shows the thread.
function test_civ_comments_block_draws()
{
	pg_assert_same(true, pg_civ_comments_block_draws(true, false), 'comments on, no widget');
	pg_assert_same(false, pg_civ_comments_block_draws(false, false), 'comments off');
	pg_assert_same(false, pg_civ_comments_block_draws(true, true), 'a comments widget shows the reviews');
	pg_assert_same(false, pg_civ_comments_block_draws(false, true), 'both');
}

// Tab section: the Reviews tab and its pane go, the other two stay.
function test_civ_drop_comments_frame_tabs()
{
	$tree = array('type' => 'root', 'props' => array(), 'children' => array(civ_reviews_test_tabs(0)));
	_pg_civ_drop_comments_frame($tree);
	$tabs = $tree['children'][0];
	$nav = $tabs['children'][0]['children'];
	$panes = $tabs['children'][1]['children'];

	pg_assert_same(2, count($nav), 'two tabs left');
	pg_assert_same('Details', $nav[0]['children'][0]['props']['text'], 'Details tab kept');
	pg_assert_same('Checkout Options', $nav[1]['children'][0]['props']['text'], 'Checkout Options tab kept');
	pg_assert_same(2, count($panes), 'two panes left');
	pg_assert_same('civtab_x_0', $panes[0]['props']['id'], 'Details pane kept');
	pg_assert_same('civtab_x_1', $panes[1]['props']['id'], 'Payment pane kept');
	pg_assert_same('tab-pane fade show active', $panes[0]['props']['cssClass'], 'the open pane stays open');
	pg_assert_same('tab-pane fade', $panes[1]['props']['cssClass'], 'the closed pane stays closed');
}

// The Reviews tab was the open one: the first tab left opens.
function test_civ_drop_comments_frame_active_moves()
{
	$tree = civ_reviews_test_tabs(2);
	_pg_civ_drop_comments_frame($tree);
	$nav = $tree['children'][0]['children'];
	$panes = $tree['children'][1]['children'];
	$button = $nav[0]['children'][0];

	pg_assert_same(2, count($nav), 'two tabs left');
	pg_assert_same('nav-link active', $button['props']['cssClass'], 'the first tab is active');
	pg_assert_same('true', civ_reviews_test_attr($button, 'aria-selected'), 'and selected');
	pg_assert_same('tab-pane fade show active', $panes[0]['props']['cssClass'], 'the first pane opens');
	pg_assert_same('nav-link', $nav[1]['children'][0]['props']['cssClass'], 'the second stays as it was');
}

// The gallery starters' "Reviews" section: heading and block go together.
function test_civ_drop_comments_frame_section()
{
	$tree = civ_reviews_test_el('div', 'container', array(
		civ_reviews_test_el('section', 'mt-5', array(civ_reviews_test_el('h2', 'h5 mb-3', array(), array('text' => 'Details')), civ_reviews_test_el('p', '', array(), array('text' => 'x')))),
		civ_reviews_test_el('section', 'mt-5', array(civ_reviews_test_el('h2', 'h5 mb-3', array(), array('text' => 'Reviews')), civ_reviews_test_region())),
	));
	_pg_civ_drop_comments_frame($tree);

	pg_assert_same(1, count($tree['children']), 'the Reviews section is gone');
	pg_assert_same('Details', $tree['children'][0]['children'][0]['props']['text'], 'the Details section stays');
}

// A block among other content: only the region goes.
function test_civ_drop_comments_frame_bare_region()
{
	$tree = civ_reviews_test_el('div', 'col', array(
		civ_reviews_test_el('p', '', array(), array('text' => 'Text')),
		civ_reviews_test_region(),
	));
	_pg_civ_drop_comments_frame($tree);

	pg_assert_same(1, count($tree['children']), 'the region is gone');
	pg_assert_same('Text', $tree['children'][0]['props']['text'], 'the text stays');

	$plain = civ_reviews_test_tabs(0);
	$plain['children'][1]['children'][2]['children'] = array();
	$before = $plain;
	_pg_civ_drop_comments_frame($plain);
	pg_assert_same($before, $plain, 'no block: the tree is unchanged');
}

// The block reads the product's thread on a designed product page; the
// renderer leaves out its frame when it will draw nothing.
function test_civ_reviews_wiring()
{
	$page = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_page_content.php');
	pg_assert_true(strpos($page, '$comments_page_type = pg_comment_thread_page_type($system_region_properties[\'page_id\'], $comments_page_type);') !== false, 'the block asks for the thread page type');
	pg_assert_true(strpos($page, "(\$comments_page_type == 'catalog detail')") !== false, 'the item pick tests the thread page type');
	pg_assert_true(strpos($page, "pg_comment_address_item('catalog detail', pg_comment_address_item_facts(") !== false, 'the product is picked as the widget picks it');
	pg_assert_true(strpos($page, "lang('No reviews yet.')") !== false, 'the empty state');

	$catalog = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_catalog.php');
	$start = strpos($catalog, 'function _render_system_widget_catalog_item_view(');
	$body = substr($catalog, $start, 30000);
	$drop = strpos($body, '_pg_civ_drop_comments_frame($tree_decoded)');
	pg_assert_true($drop !== false, 'the renderer drops the frame');
	pg_assert_true($drop < strpos($body, '_pg_sw_mark_visibility($tree_decoded'), 'before the visibility marks and the split');
}

// Runs functions of style_designer.src.js and a script under Node; the
// decoded JSON it prints, or null when Node is missing (skipped).
function civ_reviews_test_node($names, $script)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) {
		return null;
	}

	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$code_js = '';
	foreach ($names as $name) {
		$start = strpos($js, "\n    function " . $name . '(');
		$end = ($start === false) ? false : strpos($js, "\n    }\n", $start);
		if (!pg_assert_true(($start !== false) && ($end !== false), 'style_designer.js has ' . $name . '()')) {
			return null;
		}
		$code_js .= substr($js, $start, $end - $start + 6) . "\n";
	}

	$file = tempnam(sys_get_temp_dir(), 'pgciv');
	file_put_contents($file, $code_js . $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));
	$res = json_decode(implode("\n", $out), true);

	return is_array($res) ? $res : null;
}

// The canvas fades the frame the site leaves out, with the reason.
function test_civ_reviews_canvas_twin()
{
	$tree = json_encode(array('type' => 'root', 'props' => array(), 'children' => array(
		civ_reviews_test_tabs(0),
		civ_reviews_test_el('section', 'mt-5', array(civ_reviews_test_el('h2', 'h5', array(), array('text' => 'Reviews')), civ_reviews_test_region())),
	)));
	$script = <<<JS
var _parentIndex = null, _sysWidgetRenderTreeRoot = null, _sdCivFrame = { root: null, nodes: [] };
function _parentIndexFor() { return null; }
function _sdT(s) { return s; }
var _cmt = { pg_comments: '0' }, _hasWidget = false;
function _getCmtVal(k) { return _cmt[k] || ''; }
function _pgActivePage() { return {}; }
function _swKindOf(c) { return c.kind || 'comments'; }
function _swTabHasWidget() { return _hasWidget; }
var tree = $tree;
_sysWidgetRenderTreeRoot = tree;
var tabs = tree.children[0], section = tree.children[1];
var nodes = [tree, tabs.children[0].children[0], tabs.children[0].children[1], tabs.children[0].children[2], tabs.children[1].children[0], tabs.children[1].children[2], section, section.children[1]];
var cfg = { regionType: 'catalog_item_view' };
var run = function (c) { return nodes.map(function (x) { return _sdCivCommentsFrameOff(x, c || cfg); }); };
var out = { off: run() };
_cmt.pg_comments = '1';
out.on = run();
_hasWidget = true;
out.widget = run();
out.other = run({ regionType: 'catalog_listing' });
process.stdout.write(JSON.stringify(out));
JS;

	$res = civ_reviews_test_node(array('findParent', '_sdCivHasClass', '_sdCivAttr', '_sdCivCommentsFrameNodes', '_sdCivCommentsFrameOff'), $script);
	if ($res === null) {
		return;
	}

	$off = 'Off — comments are off in this page\'s comment settings';
	$widget = 'Off — the Comments widget on this page shows the reviews';
	pg_assert_same(array('', '', '', $off, '', $off, $off, ''), $res['off'], 'comments off: the Reviews tab, its pane and the section fade');
	pg_assert_same(array('', '', '', '', '', '', '', ''), $res['on'], 'comments on: nothing fades');
	pg_assert_same(array('', '', '', $widget, '', $widget, $widget, ''), $res['widget'], 'a Comments widget on the page');
	pg_assert_same(array('', '', '', '', '', '', '', ''), $res['other'], 'another widget kind');
}
