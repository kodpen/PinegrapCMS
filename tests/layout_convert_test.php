<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the page type layout converter (pg_layout_convert(),
 * pg_layout_piece_map()): a page's own layout file becomes the design
 * elements of its page type's widget, the theme's markup kept, each piece
 * of the type's data contract turned into what the widget has for it, and
 * the pieces with no element reported with their line. Every starting-site
 * text of the form list view runs through it, and the move run converts a
 * file page only when nothing is dropped.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/layout_convert.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/layouts.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/legacy_to_designer_plan.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/legacy_to_designer_run.php');

// Every node of a tree, depth first, the root left out.
function _test_lc_nodes($nodes)
{
	$out = array();
	foreach ((array)$nodes as $n) {
		$out[] = $n;
		if (!empty($n['children'])) {
			$out = array_merge($out, _test_lc_nodes($n['children']));
		}
	}
	return $out;
}

function _test_lc_of_type($tree, $type)
{
	return array_values(array_filter(_test_lc_nodes($tree['children']), function ($n) use ($type) {
		return $n['type'] === $type;
	}));
}

// The flags of the nodes shown only under one, in tree order.
function _test_lc_flags($tree)
{
	$out = array();
	foreach (_test_lc_nodes($tree['children']) as $n) {
		if (isset($n['props']['_bindings']['eo_visible_if'])) $out[] = $n['props']['_bindings']['eo_visible_if'];
	}
	return $out;
}

// The codes of a dropped list, in order.
function _test_lc_codes($dropped)
{
	return array_map(function ($d) { return $d['code']; }, $dropped);
}

// A form list starter's loop area: a column holding a card.
function _test_lc_starter()
{
	return array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'container', 'props' => array(), 'children' => array(
			array('type' => 'row', 'props' => array(), 'children' => array(
				array('type' => 'loop_area', 'props' => array(), 'children' => array(
					array('type' => 'col', 'props' => array('xs' => '12', 'md' => '6'), 'children' => array(
						array('type' => 'semantic', 'props' => array('tag' => 'div', 'cssClass' => 'card'), 'children' => array()),
					)),
				)),
			)),
		)),
	));
}

function _test_lc_template($type)
{
	return pg_layout_strip_template_header((string)file_get_contents(PG_FUNCTIONS_DIR . '/includes/templates/' . str_replace(' ', '_', $type) . '.php'));
}

function _test_lc_site($site, $id)
{
	return (string)file_get_contents(PG_FUNCTIONS_DIR . '/data/backups/' . $site . '/layouts/' . $id . '.php');
}

function test_lc_piece_map_names_the_first_family()
{
	foreach (array('form list view', 'form item view') as $type) {
		$map = pg_layout_piece_map($type);
		pg_assert_true(is_array($map) && isset($map['echo'], $map['if'], $map['loop']), $type . ' has a piece map');
	}
	pg_assert_same(null, pg_layout_piece_map('order form'), 'a type of a later family has none yet');
	pg_assert_same(null, pg_layout_piece_map('custom form'), 'the custom form keeps its own converter');
	pg_assert_same(array('form item view', 'form list view'), pg_layout_convert_types());
}

function test_lc_row_template_from_the_starter()
{
	$row = pg_layout_convert_row(_test_lc_starter());
	pg_assert_same(array(array('type' => 'semantic', 'props' => array('tag' => 'div', 'cssClass' => 'card'), 'children' => array())), $row, 'a single column gives its content');
	pg_assert_same(array(), pg_layout_convert_row(array('type' => 'root', 'props' => array(), 'children' => array())));
}

function test_lc_form_list_template_with_nothing_on()
{
	$r = pg_layout_convert('form list view', _test_lc_template('form list view'), array('row' => pg_layout_convert_row(_test_lc_starter())));
	pg_assert_same(true, $r['ok']);
	pg_assert_same(array(), $r['dropped'], 'browse and search off: the conditions on them are never drawn');
	pg_assert_same(array('empty_message' => 'There are no results.'), $r['cfg'], 'the empty branch becomes the widget\'s empty message');
	$loops = _test_lc_of_type($r['tree'], 'loop_area');
	pg_assert_same(1, count($loops), 'the forms loop is the widget\'s list');
	$cards = array_values(array_filter(_test_lc_nodes($loops[0]['children']), function ($n) { return ($n['props']['cssClass'] ?? '') === 'card'; }));
	pg_assert_same(1, count($cards), 'the row template sits in the loop body');
	$json = json_encode($r['tree']);
	pg_assert_same(false, strpos($json, '<?'), 'no PHP');
	pg_assert_same(false, (bool)preg_match('/pg-(vis|loop|row|captcha)/', $json), 'no marker left');
	pg_assert_same(false, strpos($json, 'pager'), 'the pagination is the widget\'s own');
	pg_assert_same(false, strpos($json, 'browse_and_search_form'), 'the search form is the widget\'s own');
}

function test_lc_form_list_browse_is_dropped_with_its_line()
{
	$code = _test_lc_template('form list view');
	$r = pg_layout_convert('form list view', $code, array('state' => array('browse' => true, 'search' => true, 'show_results' => true)));
	pg_assert_same(array('browse', 'condition'), _test_lc_codes($r['dropped']));
	$lines = explode("\n", $code);
	pg_assert_same(true, strpos($lines[$r['dropped'][0]['line'] - 1], '$browse or $search') !== false, 'the line of the piece');
	pg_assert_same('$browse or $search', $r['dropped'][0]['piece']);
	pg_assert_same('$browse_active or $search_active', $r['dropped'][1]['piece'], 'no widget state says a search is in force');
}

function test_lc_form_list_required_branch()
{
	$code = _test_lc_template('form list view');
	$r = pg_layout_convert('form list view', $code, array('state' => array('search' => true, 'show_results' => false)));
	pg_assert_same(array('$browse_or_search_required', '$browse_active or $search_active'), array_map(function ($d) { return $d['piece']; }, $r['dropped']));
}

function test_lc_form_list_header_footer_and_flags()
{
	$code = '<?=$header?><?php foreach ($forms as $form): ?><div class="item"><?=$form[\'content\']?></div><?php endforeach ?><?=$footer?>'
		. '<?php if ($number_of_forms == 0): ?><p>None.</p><?php else: ?><p class="tail">More soon.</p><?php endif ?>';
	$r = pg_layout_convert('form list view', $code, array('header' => '<h2>Ads</h2>', 'footer' => '', 'row' => array()));
	pg_assert_same(array(), $r['dropped']);
	pg_assert_same(array('empty_message' => 'None.'), $r['cfg']);
	pg_assert_same(array('has_records'), _test_lc_flags($r['tree']), 'the else branch shows with records');
	pg_assert_same('heading', $r['tree']['children'][0]['props']['contentType'] ?? '', 'the header\'s markup is kept');
	pg_assert_same('item', _test_lc_of_type($r['tree'], 'loop_area')[0]['children'][0]['props']['cssClass'], 'the loop body\'s wrapper is kept');
}

function test_lc_unknown_code_and_values_are_dropped()
{
	$code = "<div>\n<?php \$x = load(); ?>\n<?=\$secret?>\n<?php foreach (\$rows as \$r): ?><i>x</i><?php endforeach ?>\n<?php if (\$other): ?><b>y</b><?php endif ?></div>";
	$r = pg_layout_convert('form list view', $code, array());
	pg_assert_same(array('code', 'value', 'loop', 'condition'), _test_lc_codes($r['dropped']));
	pg_assert_same(array(2, 3, 4, 5), array_map(function ($d) { return $d['line']; }, $r['dropped']));
	pg_assert_same(1, count(_test_lc_of_type($r['tree'], 'loop_area')), 'a widget always gets its list');
	pg_assert_same(false, strpos(json_encode($r['tree']), '<i>'), 'what a dropped loop holds goes with it');
}

function test_lc_unknown_type()
{
	$r = pg_layout_convert('order form', '<p>x</p>', array());
	pg_assert_same(false, $r['ok']);
}

function test_lc_form_item_view_template()
{
	$r = pg_layout_convert('form item view', _test_lc_template('form item view'), array('row' => array(array('type' => 'content', 'props' => array('contentType' => 'paragraph', 'text' => 'r'), 'children' => array()))));
	pg_assert_same(true, $r['ok']);
	// No widget state tells the submitter from another reader, nor gives a
	// way back: both branches of the first and the Back button are reported.
	pg_assert_same(array('$auto_registration_for_submitter', 'else', '$back_button_url'), array_map(function ($d) { return $d['piece']; }, $r['dropped']));
	pg_assert_same(array('has_new_account', 'can_edit'), _test_lc_flags($r['tree']));
	$links = array_values(array_filter(_test_lc_nodes($r['tree']['children']), function ($n) { return ($n['props']['contentType'] ?? '') === 'link'; }));
	pg_assert_same(array('__edit_url'), array_map(function ($n) { return $n['props']['_bindings']['href'] ?? ''; }, $links), 'addresses are bound, never typed');
	$loops = _test_lc_of_type($r['tree'], 'loop_area');
	pg_assert_same('r', $loops[0]['children'][0]['props']['text'], 'the record is the loop area');
}

// Every starting-site text of the form list view, with each page's own
// settings: the pages with browse or search on keep pieces no element has.
function test_lc_form_list_starting_site_texts()
{
	$texts = array(
		array('english_default', 1015), // 26 pages of both sites
		array('turkish_default', 1015), // 17 pages
		array('turkish_default', 598),  // 3 pages
	);
	foreach ($texts as $t) {
		$code = _test_lc_site($t[0], $t[1]);
		$label = $t[0] . '/' . $t[1];
		$off = pg_layout_convert('form list view', $code, array('row' => pg_layout_convert_row(_test_lc_starter())));
		pg_assert_same(true, $off['ok'], $label);
		pg_assert_same(array(), $off['dropped'], $label . ': browse and search off');
		pg_assert_same(1, count(_test_lc_of_type($off['tree'], 'loop_area')), $label);
		pg_assert_same(false, strpos(json_encode($off['tree']), '<?'), $label . ': no PHP');
		pg_assert_same(true, ($off['cfg']['empty_message'] ?? '') !== '', $label . ': its own empty words');

		$search = pg_layout_convert('form list view', $code, array('state' => array('search' => true, 'show_results' => true)));
		pg_assert_same(array('$browse_active or $search_active'), array_map(function ($d) { return $d['piece']; }, $search['dropped']), $label . ': search on');

		$browse = pg_layout_convert('form list view', $code, array('state' => array('browse' => true, 'search' => true, 'show_results' => true)));
		pg_assert_same(array('browse', 'condition'), _test_lc_codes($browse['dropped']), $label . ': browse on');
	}
}

function test_lc_convert_state_from_the_type_row()
{
	pg_assert_same(array('browse' => true, 'search' => false, 'show_results' => false), pg_layout_convert_state('form list view', array('browse' => '1', 'search' => '0', 'show_results_by_default' => '0')));
	pg_assert_same(array(), pg_layout_convert_state('form item view', array('browse' => '1')));
}

function test_lc_type_target_of_a_file_page()
{
	$facts = array('kind' => 'form_list_view', 'modified' => true, 'held' => array());
	pg_assert_same('file', pg_ltd_type_target('Ads', $facts, false)['state'], 'not read: stays');
	pg_assert_same('file', pg_ltd_type_target('Ads', $facts + array('dropped' => array(array('piece' => 'x', 'line' => 1, 'code' => 'value'))), false)['state']);
	$t = pg_ltd_type_target('Ads', $facts + array('dropped' => array()), false);
	pg_assert_same(array('convert', true), array($t['state'], $t['file']), 'nothing dropped: the file converts');
	pg_assert_same('held', pg_ltd_type_target('Ads', array('held' => array('header'), 'dropped' => array()) + $facts, false)['state'], 'a held setting still keeps it');
	pg_assert_same(array('form_list_view'), pg_ltd_plan_type_kinds(array(array('kind' => 'style', 'skip' => '', 'types' => array($t)))), 'its starter comes with Convert');
}

function test_lc_type_file_widget()
{
	$target = array('page_name' => 'Ads', 'kind' => 'form_list_view', 'state' => 'convert', 'held' => array(), 'file' => true);
	$facts = array('type' => 'form list view', 'cfg' => array('form_page_id' => 9), 'tree' => array(), 'state' => array());
	$code = '<section class="theme"><?php foreach ($forms as $form): ?><?=$form[\'content\']?><?php endforeach ?></section>';
	$w = pg_ltd_type_file_widget($target, $facts, $code, array('form_list_view' => _test_lc_starter()));
	pg_assert_same(array('regionType' => 'form_list_view', 'form_page_id' => 9), $w['cfg']);
	pg_assert_same('theme', $w['tree']['children'][0]['props']['cssClass'], 'the theme\'s wrapper stays');
	pg_assert_same(null, pg_ltd_type_file_widget($target, $facts, $code, array()), 'no starter: left as it is');
	pg_assert_same(null, pg_ltd_type_file_widget($target, $facts, $code . '<?=$secret?>', array('form_list_view' => _test_lc_starter())), 'a file that drops since the dry run: left as it is');
}

function test_lc_dry_run_does_not_count_a_converting_file_twice()
{
	$s = array('kind' => 'style', 'id' => 3, 'name' => 'home', 'skip' => '', 'components' => array(), 'pages' => array(), 'widgets' => array(),
	           'stays' => array(), 'designer_php' => array(), 'custom_layouts' => 2,
	           'types' => array(array('page_name' => 'Ads', 'kind' => 'form_list_view', 'state' => 'convert', 'held' => array(), 'file' => true)));
	$g = pg_ltd_plan_groups($s);
	$layouts = array_values(array_filter($g['code'], function ($r) { return $r['label'] === lang('Pages with a custom layout'); }));
	pg_assert_same(1, $layouts[0]['count'] ?? null);
}
