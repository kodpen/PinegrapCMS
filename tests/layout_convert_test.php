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
	pg_assert_same(null, pg_layout_piece_map('view order'), 'a type of a later family has none yet');
	pg_assert_same(null, pg_layout_piece_map('custom form'), 'the custom form keeps its own converter');
	pg_assert_same(array('form item view', 'form list view', 'order form', 'photo gallery', 'catalog', 'catalog detail', 'shopping cart', 'express order', 'billing information', 'shipping address and arrival', 'shipping method', 'order preview'), pg_layout_convert_types());
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
	$r = pg_layout_convert('view order', '<p>x</p>', array());
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

// A catalog page whose search setting is off: its layout's own search form
// searches all the same, so the widget's search is on.
function test_lc_type_file_widget_layout_settings_win()
{
	$target = array('page_name' => 'Shop', 'kind' => 'catalog_listing', 'state' => 'convert', 'held' => array(), 'file' => true);
	$facts = array('type' => 'catalog', 'cfg' => array('product_group_id' => 3, 'search_enabled' => false), 'tree' => array(), 'state' => array());
	$w = pg_ltd_type_file_widget($target, $facts, _test_lc_site('english_default', 999), array('catalog_listing' => _test_lc_starter()));
	pg_assert_same(array('regionType' => 'catalog_listing', 'search_enabled' => true, 'product_group_id' => 3), $w['cfg']);
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

// -- order form ---------------------------------------------------------------

// The nodes bound to a value, by the bound prop.
function _test_lc_bound($tree, $prop)
{
	$out = array();
	foreach (_test_lc_nodes($tree['children']) as $n) {
		if (isset($n['props']['_bindings'][$prop])) $out[] = $n['props']['_bindings'][$prop];
	}
	return $out;
}

function _test_lc_names($tree)
{
	$out = array();
	foreach (_test_lc_nodes($tree['children']) as $n) {
		$name = _pg_lfc_attr($n, 'name');
		if ($name !== null && $name !== '') $out[] = $name;
	}
	return $out;
}

function test_lc_order_form_template()
{
	$r = pg_layout_convert('order form', _test_lc_template('order form'), array());
	pg_assert_same(true, $r['ok']);
	pg_assert_same(array(), $r['dropped'], 'every piece has its element');
	$json = json_encode($r['tree']);
	pg_assert_same(false, strpos($json, '<?'), 'no PHP');
	pg_assert_same(false, (bool)preg_match('/pg-(vis|loop|row|section)/', $json), 'no marker left');
	pg_assert_same(false, (bool)preg_match('/"tag":"form"/', $json), 'the widget draws its own form');
	pg_assert_same(1, count(_test_lc_of_type($r['tree'], 'loop_area')), 'the products loop is the list');
	pg_assert_same(array('recurring_schedule'), _test_lc_bound($r['tree'], 'section'), 'the schedule is the widget\'s section');
	pg_assert_same('is_list_layout', $r['tree']['children'][0]['props']['_bindings']['eo_visible_if'] ?? '', 'the table goes with the drop-down layout');
	$names = _test_lc_names($r['tree']);
	foreach (array('product', 'donation', 'product_id', 'ship_to', 'add_name', 'quantity', 'currency_id', 'submit_skip') as $want) {
		pg_assert_contains($want, $names, 'control ' . $want);
	}
	pg_assert_same(array(), array_values(array_filter($names, function ($n) { return strpos($n, '^^') !== false || strpos($n, 'recurring_') === 0; })), 'no row name left for the server to rename');
	pg_assert_same(array(), _test_lc_bound($r['tree'], 'href'), 'the skip address is the skip button');
	$flags = _test_lc_flags($r['tree']);
	foreach (array('only_donations', 'not_only_donations', 'selects_by_checkbox_and_quantity', 'selects_by_checkbox_only', 'selects_by_quantity_only',
	               'order_form_has_image', 'order_form_donation', 'order_form_checkbox', 'order_form_quantity', 'has_currency_code',
	               'is_dropdown_layout', 'has_recipient_choice', 'has_quantity', 'has_products', 'has_skip_button', 'no_products', 'has_currency_choice') as $want) {
		pg_assert_contains($want, $flags, 'flag ' . $want);
	}
	pg_assert_same(array('__image_url'), _test_lc_bound($r['tree'], 'src'), 'the image address is bound');
}

// The skip button alone when nothing can be ordered: under both flags; the
// image's column alone under the image flag, the description beside it
// drawn always.
function test_lc_order_form_skip_and_image()
{
	$code = '<?php if ($available_products): ?><button type="submit">Go</button><?php elseif ($skip_button_url): ?>'
		. '<a href="<?=h($skip_button_url)?>" class="btn btn-secondary"><?=h($skip_button_label)?></a><?php endif ?>'
		. '<?php foreach ($products as $product): ?><div class="row"><?php if ($product[\'image_url\']): ?><div class="col-6"><img src="<?=h($product[\'image_url\'])?>"></div><?php endif ?>'
		. '<div class="col-6"><?=h($product[\'short_description\'])?></div></div><?php endforeach ?>';
	$r = pg_layout_convert('order form', $code, array());
	pg_assert_same(array(), $r['dropped']);
	$top = $r['tree']['children'];
	pg_assert_same('has_products', $top[0]['props']['_bindings']['eo_visible_if'] ?? '');
	pg_assert_same('no_products', $top[1]['props']['_bindings']['eo_visible_if'] ?? '', 'the outer flag');
	$skip = $top[1]['children'][0];
	pg_assert_same(array('button', 'btn btn-secondary', 'has_skip_button', '__skip_button_label'),
		array($skip['props']['tag'], $skip['props']['cssClass'], $skip['props']['_bindings']['eo_visible_if'] ?? '', $skip['props']['_bindings']['text'] ?? ''));
	pg_assert_same('submit_skip', _pg_lfc_attr($skip, 'name'), 'the widget\'s skip control');
	$cols = $r['tree']['children'][2]['children'][0]['children'];
	pg_assert_same('order_form_has_image', $cols[0]['props']['_bindings']['eo_visible_if'] ?? '', 'the image column');
	pg_assert_same(false, isset($cols[1]['props']['_bindings']['eo_visible_if']), 'the description column stays');
}

// A heading's condition read only after an "else": alone it is dropped.
function test_lc_order_form_elseif_keys()
{
	$r = pg_layout_convert('order form', '<?php if ($checkbox_selections): ?>Select<?php endif ?>', array());
	pg_assert_same(array('condition'), _test_lc_codes($r['dropped']));
	$r = pg_layout_convert('order form', '<?php if ($skip_button_url): ?><b>x</b><?php endif ?>', array());
	pg_assert_same(array('has_skip_button'), _test_lc_flags($r['tree']), 'the bare key');
}

// Every starting-site text of the order form (11 over 20 pages): nothing
// dropped, so each page converts in the run.
function test_lc_order_form_starting_site_texts()
{
	$texts = array(
		array('english_default', 283), // 7 pages
		array('english_default', 266),
		array('english_default', 251),
		array('english_default', 496),
		array('turkish_default', 283), // 4 pages
		array('turkish_default', 266),
		array('turkish_default', 251),
		array('turkish_default', 394),
		array('turkish_default', 418),
		array('turkish_default', 639),
		array('turkish_default', 496),
	);
	foreach ($texts as $t) {
		$label = $t[0] . '/' . $t[1];
		$r = pg_layout_convert('order form', _test_lc_site($t[0], $t[1]), array());
		pg_assert_same(true, $r['ok'], $label);
		pg_assert_same(array(), $r['dropped'], $label . ': nothing dropped');
		$json = json_encode($r['tree']);
		pg_assert_same(false, strpos($json, '<?'), $label . ': no PHP');
		pg_assert_same(1, count(_test_lc_of_type($r['tree'], 'loop_area')), $label . ': one list');
		pg_assert_same(array('recurring_schedule'), _test_lc_bound($r['tree'], 'section'), $label . ': the schedule');
	}
}

// A converted row drawn by the widget: the control named for the product
// and filled with its default quantity, the theme's markup kept.
function test_lc_order_form_row_through_the_widget()
{
	$r = pg_layout_convert('order form', _test_lc_site('english_default', 283), array());
	$loop = _test_lc_of_type($r['tree'], 'loop_area')[0];
	$row = array('type' => 'root', 'props' => array(), 'children' => $loop['children']);
	$p = array('id' => 5, 'name' => 'SKU5', 'short_description' => 'Mug', 'full_description' => '<p>Big mug</p>', 'price_html' => '$5.00',
	           'image_name' => '', 'inventory' => 0, 'inventory_quantity' => 0, 'out_of_stock_message' => '', 'available' => true,
	           'selection_type' => 'quantity', 'default_quantity' => 2);
	$html = pg_of_row_html($row, $p, array(), true, '', 3);
	pg_assert_true(strpos($html, 'name="product_5" value="2"') !== false, 'named and filled');
	pg_assert_true(strpos($html, 'responsive_table_image_1') === false, 'no image: its column goes');
	pg_assert_true(strpos($html, '<p>Big mug</p>') !== false && strpos($html, 'mobile_stacked') === false, 'the description, the row alone');
	pg_assert_true(strpos($html, 'type="checkbox"') === false && strpos($html, 'donation_5') === false, 'one control');
}

function test_lc_type_file_convert_keeps_the_dropped_list()
{
	$facts = array('type' => 'form list view', 'state' => array('browse' => true), 'tree' => array());
	$code = "<div class=\"theme\">\n<?php if (\$browse or \$search): ?><form></form><?php endif ?>\n<?php foreach (\$forms as \$form): ?><?=\$form['content']?><?php endforeach ?></div>";
	$r = pg_layout_type_file_convert($facts, $code, _test_lc_starter());
	pg_assert_true($r['ok']);
	pg_assert_same(array('browse'), _test_lc_codes($r['dropped']), 'the Region panel gets what the run refuses');
	pg_assert_same('theme', $r['tree']['children'][0]['props']['cssClass'], 'the theme\'s wrapper stays');
	pg_assert_same(1, count(_test_lc_of_type($r['tree'], 'loop_area')));
	pg_assert_same(false, pg_layout_type_file_convert(array('type' => 'view order'), $code, array())['ok'], 'a type with no map');
}

function test_lc_dropped_rows()
{
	$rows = pg_layout_dropped_rows(array(
		array('piece' => '$watcher_option', 'line' => 42, 'code' => 'condition'),
		array('piece' => '$secret', 'line' => 3, 'code' => 'value'),
		array('piece' => '$a as $b', 'line' => 4, 'code' => 'loop'),
		array('piece' => 'echo   date()', 'line' => 5, 'code' => 'code'),
		array('piece' => '$browse or $search', 'line' => 6, 'code' => 'browse'),
		array('piece' => str_repeat('x', 70), 'line' => 7, 'code' => 'other'),
	));
	pg_assert_same(array(42, 3, 4, 5, 6, 7), array_column($rows, 'line'));
	if (lang(array('info' => true)) !== 'en') return;
	pg_assert_same('line 42 · a condition on $watcher_option · dropped', $rows[0]['text']);
	pg_assert_same('line 3 · the value $secret · dropped', $rows[1]['text']);
	pg_assert_same('line 4 · a loop over $a as $b · dropped', $rows[2]['text']);
	pg_assert_same('line 5 · PHP code echo date() · dropped', $rows[3]['text']);
	pg_assert_same('line 6 · the browse form · dropped', $rows[4]['text']);
	pg_assert_same('line 7 · ' . str_repeat('x', 59) . '… · dropped', $rows[5]['text'], 'a long piece is cut');
	pg_assert_same(array(), pg_layout_dropped_rows(array()));
}

// -- photo gallery ------------------------------------------------------------

// The albums loop and the photos loop side by side are the widget's one
// list, each body shown in its kind's rows; the wrappers saying "there are
// albums" or "photos" around a loop alone go with it.
function test_lc_photo_gallery_loops_are_one_list()
{
	$code = '<div class="grid"><?php if ($albums): ?><?php foreach ($albums as $album): ?><div class="album"><?=h($album[\'name\'])?></div><?php endforeach ?><?php endif ?>'
		. '<?php if ($photos): ?><?php foreach ($photos as $photo): ?><div class="photo"><img src="<?=h($photo[\'url\'])?>"></div><?php endforeach ?><?php endif ?></div>';
	$r = pg_layout_convert('photo gallery', $code, array());
	pg_assert_same(array(), $r['dropped']);
	$grid = $r['tree']['children'][0];
	pg_assert_same(1, count($grid['children']), 'the grid holds the list alone');
	$loop = $grid['children'][0];
	pg_assert_same('loop_area', $loop['type']);
	pg_assert_same(array('gallery_item_is_album', 'gallery_item_is_photo'), array_map(function ($n) { return $n['props']['_bindings']['eo_visible_if']; }, $loop['children']));
	pg_assert_same(false, isset($loop['props']['_kinds']), 'no working mark left');
	pg_assert_same(array('__gallery_item_url'), _test_lc_bound($r['tree'], 'src'));
}

// The stock template puts albums and photos under their own headings: two
// lists the widget has not, so the second loop is dropped.
function test_lc_photo_gallery_template()
{
	$r = pg_layout_convert('photo gallery', _test_lc_template('photo gallery'), array());
	pg_assert_same(true, $r['ok']);
	pg_assert_same(array(array('piece' => '$photos as $photo', 'line' => 26, 'code' => 'loop')), $r['dropped']);
	pg_assert_same(1, count(_test_lc_of_type($r['tree'], 'loop_area')));
	pg_assert_true(strpos(json_encode($r['tree']), '(^^__gallery_item_count_label^^)') !== false, 'the count in words');
}

// Every starting-site text of the photo gallery (11 over 12 pages): nothing
// dropped, one list.
function test_lc_photo_gallery_starting_site_texts()
{
	foreach (array('english_default', 'turkish_default') as $site) {
		foreach (array(343, 994, 995, 996, 1049, 1050) as $id) {
			$label = $site . '/' . $id;
			$r = pg_layout_convert('photo gallery', _test_lc_site($site, $id), array());
			pg_assert_same(true, $r['ok'], $label);
			pg_assert_same(array(), $r['dropped'], $label . ': nothing dropped');
			$json = json_encode($r['tree']);
			pg_assert_same(false, strpos($json, '<?'), $label . ': no PHP');
			pg_assert_same(false, (bool)preg_match('/pg-(vis|loop|row)|_kinds/', $json), $label . ': no marker left');
			pg_assert_same(1, count(_test_lc_of_type($r['tree'], 'loop_area')), $label . ': one list');
			pg_assert_contains('gallery_item_is_photo', _test_lc_flags($r['tree']), $label . ': the photo rows');
		}
	}
	$flags = _test_lc_flags(pg_layout_convert('photo gallery', _test_lc_site('english_default', 343), array())['tree']);
	foreach (array('has_albums', 'in_album', 'not_in_album', 'only_photos', 'no_gallery_items', 'has_gallery_items', 'gallery_item_is_album', 'gallery_item_many_photos') as $want) {
		pg_assert_contains($want, $flags, 'flag ' . $want);
	}
	$flags = _test_lc_flags(pg_layout_convert('photo gallery', _test_lc_site('english_default', 1049), array())['tree']);
	pg_assert_same(array('no_photos', 'has_photos', 'gallery_item_is_photo'), $flags, 'the photos-only widget layout');
}

// Converted rows drawn by the widget: an album shows its own body with
// its count, a photo its own.
function test_lc_photo_gallery_rows_through_the_widget()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/fn/widgets_photo_gallery.php');
	$r = pg_layout_convert('photo gallery', _test_lc_site('english_default', 343), array());
	$loop = _test_lc_of_type($r['tree'], 'loop_area')[0];
	$rows = pg_gal_rows(array(array('id' => 7, 'name' => 'Summer', 'photo' => 'a.jpg', 'photos' => 3)),
		array(array('id' => 20, 'name' => 'b.jpg', 'description' => 'Beach')), '/gallery', '/');
	$album = pg_gal_row_html($loop['children'], $rows[0]);
	pg_assert_true(strpos($album, 'data-filter="Summer"') !== false && strpos($album, 'href="/gallery?folder_id=7"') !== false, 'the album and its address');
	pg_assert_true(strpos($album, 'src="/a.jpg"') !== false && strpos($album, 'data-filter="Photos"') === false, 'its picture, no photo body');
	pg_assert_true((bool)preg_match('/\(3 Photo\s*<\/span>\s*<span[^>]*>s<\/span>/', $album), 'the count and its plural');
	$photo = pg_gal_row_html($loop['children'], $rows[1]);
	pg_assert_true(strpos($photo, 'data-filter="Photos"') !== false && strpos($photo, 'data-title="Beach"') !== false, 'the photo body');
	pg_assert_true(strpos($photo, 'src="/b.jpg"') !== false && strpos($photo, 'folder_id') === false, 'its picture alone');
}

// -- catalog ------------------------------------------------------------------

// The catalog's starting-site texts: 10 pages, 9 texts (1000 and 1001 the
// same on the Turkish site).
function _test_lc_catalog_texts()
{
	$out = array();
	foreach (array('english_default', 'turkish_default') as $site) {
		foreach (array(985, 997, 999, 1000, 1001) as $id) $out[$site . '/' . $id] = _test_lc_site($site, $id);
	}
	return $out;
}

// Every starting-site text of the catalog: nothing dropped, the items loop
// the one list, the categories the group tree, the search form bound.
function test_lc_catalog_starting_site_texts()
{
	pg_assert_same(9, count(array_unique(_test_lc_catalog_texts())), 'nine distinct texts');
	foreach (_test_lc_catalog_texts() as $label => $code) {
		$r = pg_layout_convert('catalog', $code, array());
		pg_assert_same(true, $r['ok'], $label);
		pg_assert_same(array(), $r['dropped'], $label . ': nothing dropped');
		$json = json_encode($r['tree']);
		pg_assert_same(false, strpos($json, '<?'), $label . ': no PHP');
		pg_assert_same(false, (bool)preg_match('/pg-(vis|loop|row|section|form)|"_list"/', $json), $label . ': no marker left');
		pg_assert_same(1, count(_test_lc_of_type($r['tree'], 'loop_area')), $label . ': one list');
		pg_assert_same(array('search_enabled' => true), $r['cfg'], $label . ': the search is on');
		pg_assert_same(array('search_query'), _test_lc_bound($r['tree'], 'value'), $label . ': the search box');
		pg_assert_same(array('__image_url'), _test_lc_bound($r['tree'], 'src'), $label . ': the picture');
		// One Turkish sidebar text adds the page's anchor to the address.
		$typed = $label === 'turkish_default/985';
		pg_assert_same($typed ? array() : array('__detail_url'), _test_lc_bound($r['tree'], 'href'), $label . ': the address');
		pg_assert_same($typed, strpos($json, '^^__detail_url^^#catalog-top') !== false, $label . ': the address and its anchor');
		$forms = array_values(array_filter(_test_lc_nodes($r['tree']['children']), function ($n) { return _pg_lfc_tag($n) === 'form'; }));
		pg_assert_same(1, count($forms), $label . ': the search form alone, the currency form gone');
		pg_assert_same('search_form', $forms[0]['props']['_bindings']['action'], $label . ': bound');
		$names = array_values(array_filter(array_map(function ($n) { return _pg_lfc_attr($n, 'name'); }, _test_lc_nodes($r['tree']['children']))));
		pg_assert_same(array('catalog-top', 'query', 'currency_id'), $names, $label . ': of the search controls the clear button alone keeps a name');
		$flags = _test_lc_flags($r['tree']);
		if (strpos($label, 'english') === 0) pg_assert_same(array('many_results', 'one_result'), array_values(array_intersect($flags, array('many_results', 'one_result'))), $label . ': the line with its plural, and without');
		foreach (array('has_items', 'no_items', 'has_query', 'no_query', 'has_currency_choice', 'catalog_item_has_image', 'catalog_item_has_short_description', 'catalog_item_has_price') as $want) {
			pg_assert_contains($want, $flags, $label . ': flag ' . $want);
		}
		pg_assert_true(strpos($json, '^^__result_count^^') !== false && strpos($json, '^^__search_query^^') !== false, $label . ': the count and the words searched');
		pg_assert_same(array('__active_cat_description', '__short_description', '__price_formatted', '__active_cat_code'), _test_lc_bound($r['tree'], 'text'), $label . ': the group\'s description and code, the row\'s words and price');
		$sections = _test_lc_bound($r['tree'], 'section');
		pg_assert_same(in_array(substr($label, -3), array('985', '997'), true) ? array('group_tree') : array(), $sections, $label . ': the categories');
	}
}

// The full-width texts choose the search column's width by the currency
// choice; the sidebar text draws the group tree in the theme's nav.
function test_lc_catalog_columns_and_tree()
{
	$r = pg_layout_convert('catalog', _test_lc_site('english_default', 999), array());
	pg_assert_same(array('no_currency_choice', 'has_currency_choice'), array_slice(_test_lc_flags($r['tree']), 0, 2));
	$r = pg_layout_convert('catalog', _test_lc_site('english_default', 985), array());
	$navs = array_values(array_filter(_test_lc_nodes($r['tree']['children']), function ($n) { return _pg_lfc_tag($n) === 'nav'; }));
	pg_assert_same(1, count($navs));
	pg_assert_same(array(array('type' => 'semantic', 'props' => array('tag' => 'div', 'cssClass' => '', '_bindings' => array('section' => 'group_tree')), 'children' => array())), $navs[0]['children'], 'the list the loop filled is the tree');
}

// The stock template: what the starting sites never use is dropped.
function test_lc_catalog_template()
{
	$r = pg_layout_convert('catalog', _test_lc_template('catalog'), array());
	pg_assert_same(true, $r['ok']);
	pg_assert_same(array('value', 'condition', 'value', 'condition'), _test_lc_codes($r['dropped']));
}

// -- catalog detail -----------------------------------------------------------

// The catalog detail's starting-site texts: 6 pages, 6 texts.
function _test_lc_catalog_detail_texts()
{
	$out = array();
	foreach (array('english_default', 'turkish_default') as $site) {
		foreach (array(986, 998, 1002) as $id) $out[$site . '/' . $id] = _test_lc_site($site, $id);
	}
	return $out;
}

// Every starting-site text of the catalog detail: nothing dropped, the
// tree the product (no list), the product row's controls bound, the
// sidebar's search form, categories and keywords the widget's, the classic
// cross-sell container its cross-sell part.
function test_lc_catalog_detail_starting_site_texts()
{
	pg_assert_same(6, count(array_unique(_test_lc_catalog_detail_texts())), 'six distinct texts');
	foreach (_test_lc_catalog_detail_texts() as $label => $code) {
		$r = pg_layout_convert('catalog detail', $code, array());
		$sidebar = substr($label, -4) !== '1002';
		pg_assert_same(true, $r['ok'], $label);
		pg_assert_same(array(), $r['dropped'], $label . ': nothing dropped');
		$json = json_encode($r['tree']);
		pg_assert_same(false, strpos($json, '<?'), $label . ': no PHP');
		pg_assert_same(false, (bool)preg_match('/pg-(vis|loop|row|section|form)|"_list"/', $json), $label . ': no marker left');
		pg_assert_same(0, count(_test_lc_of_type($r['tree'], 'loop_area')), $label . ': the tree is the product, no list');
		pg_assert_same(array('__image_url'), _test_lc_bound($r['tree'], 'src'), $label . ': the picture');
		pg_assert_same($sidebar ? array('product_lines', 'group_tree', 'keywords') : array('product_lines'), _test_lc_bound($r['tree'], 'section'), $label . ': the widget\'s lists');
		pg_assert_same($sidebar ? array('search_query', 'product_choice', 'recipient_select', 'quantity') : array('product_choice', 'recipient_select', 'quantity'), _test_lc_bound($r['tree'], 'value'), $label . ': the controls the widget fills');
		$text = _test_lc_bound($r['tree'], 'text');
		foreach (array('__variant_picker', '__add_button_label', '__cross_sell_html', '__details') as $want) pg_assert_contains($want, $text, $label . ': ' . $want);
		$item = (strpos($label, 'turkish') === 0) ? 'Ürün' : 'Item';
		pg_assert_true(strpos(json_encode($r['tree'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), '<strong>' . $item . ':</strong> ^^__product_line^^') !== false, $label . ': the product row');
		pg_assert_false(strpos($json, 'item-grid') !== false, $label . ': the classic cross-sell template is gone');
		$forms = array_values(array_filter(_test_lc_nodes($r['tree']['children']), function ($n) { return _pg_lfc_tag($n) === 'form'; }));
		pg_assert_same($sidebar ? 1 : 0, count($forms), $label . ': the search form alone, the add-to-cart and currency forms the widget\'s');
		if ($sidebar) pg_assert_same('search_form', $forms[0]['props']['_bindings']['action'], $label . ': bound');
		$names = array_values(array_filter(array_map(function ($n) { return _pg_lfc_attr($n, 'name'); }, _test_lc_nodes($r['tree']['children']))));
		// One Turkish sidebar text has the page's anchor at its top.
		$own = ($label === 'turkish_default/986') ? array('catalog-top', 'add_name', 'currency_id') : ($sidebar ? array('add_name', 'currency_id') : array('add_name'));
		pg_assert_same($own, $names, $label . ': the controls left with their own names');
		$flags = _test_lc_flags($r['tree']);
		$want_flags = array('has_products', 'no_products', 'no_variant_choice', 'has_product_choice', 'no_product_choice', 'one_product', 'many_products', 'has_recipient_choice', 'has_back_button');
		if ($sidebar) $want_flags = array_merge($want_flags, array('has_keywords', 'has_currency_choice'));
		foreach ($want_flags as $want_flag) pg_assert_contains($want_flag, $flags, $label . ': flag ' . $want_flag);
	}
	$r = pg_layout_convert('catalog detail', _test_lc_site('english_default', 1002), array());
	pg_assert_same(array('has_back_button'), array_slice(_test_lc_flags($r['tree']), 0, 1), 'the full-width text starts with its back link');
	pg_assert_same(array('__back_url', '__back_url'), _test_lc_bound($r['tree'], 'href'), 'the back links');
}

// The stock template converts whole too.
function test_lc_catalog_detail_template()
{
	$r = pg_layout_convert('catalog detail', _test_lc_template('catalog detail'), array());
	pg_assert_same(true, $r['ok']);
	pg_assert_same(array(), $r['dropped']);
	pg_assert_same(array('keywords', 'product_lines', 'group_tree'), _test_lc_bound($r['tree'], 'section'));
}

// -- shopping cart ------------------------------------------------------------

// The shopping cart's starting-site texts: 3 pages, 3 texts.
function _test_lc_cart_texts()
{
	return array(
		'english_default/248' => _test_lc_site('english_default', 248),
		'turkish_default/248' => _test_lc_site('turkish_default', 248),
		'turkish_default/497' => _test_lc_site('turkish_default', 497),
	);
}

// The conditions a type row's settings decide, as a page of the starting
// sites has them (full description, one recipient).
function _test_lc_cart_state()
{
	return pg_layout_convert_state('shopping cart', array('product_description_type' => 'full_description'));
}

// Every starting-site text of the shopping cart: nothing dropped, the rows
// listed in both tables (today's and the recurring charges) by row flags,
// the blocks with their own forms the widget's, the quantity and donation
// boxes and the two buttons wired by the widget, no form of the layout's
// left, a classic cross-sell script kept as it ran.
function test_lc_cart_starting_site_texts()
{
	pg_assert_same(3, count(array_unique(_test_lc_cart_texts())), 'three distinct texts');
	foreach (_test_lc_cart_texts() as $label => $code) {
		$r = pg_layout_convert('shopping cart', $code, array('state' => _test_lc_cart_state()));
		pg_assert_same(true, $r['ok'], $label);
		pg_assert_same(array(), $r['dropped'], $label . ': nothing dropped');
		$json = json_encode($r['tree']);
		pg_assert_same(false, strpos($json, '<?'), $label . ': no PHP');
		pg_assert_same(false, (bool)preg_match('/pg-(vis|loop|row|section|form|raw)|"_list"/', $json), $label . ': no marker left');
		$loops = _test_lc_of_type($r['tree'], 'loop_area');
		pg_assert_same(2, count($loops), $label . ': today\'s and the recurring table');
		pg_assert_same('cart_item_today', $loops[0]['children'][0]['props']['_bindings']['eo_visible_if'], $label . ': the first table\'s rows');
		pg_assert_same('cart_item_recurring', $loops[1]['children'][0]['props']['_bindings']['eo_visible_if'], $label . ': the second table\'s rows');
		pg_assert_same(array('pending_offers', 'quick_add', 'applied_offers', 'payment_periods'), _test_lc_bound($r['tree'], 'section'), $label . ': the widget\'s blocks');
		pg_assert_same(array('cart_qty', 'cart_qty', 'cart_qty', 'cart_qty'), _test_lc_bound($r['tree'], 'value'), $label . ': quantity and amount in both tables');
		pg_assert_same(array('cart_update', 'cart_checkout'), _test_lc_bound($r['tree'], 'action'), $label . ': the buttons');
		pg_assert_same(array(), _test_lc_names($r['tree']), $label . ': no control keeps a name of its own');
		$text = _test_lc_bound($r['tree'], 'text');
		foreach (array('__item_gift_card_html', '__item_form_data_html', '__item_recurring_schedule_html', '__tax_shipping_notice',
			'__special_offer_code_form', '__offline_payment_checkbox', '__currency_selector', '__cart_subtotal', '__cart_total') as $want) {
			pg_assert_contains($want, $text, $label . ': ' . $want);
		}
		pg_assert_same(array('__item_image', '__item_image'), _test_lc_bound($r['tree'], 'src'), $label . ': the picture in each table');
		pg_assert_same(array(), array_values(array_filter(_test_lc_nodes($r['tree']['children']), function ($n) { return _pg_lfc_tag($n) === 'form'; })), $label . ': the widget draws the forms');
		$raw = array_values(array_filter(_test_lc_nodes($r['tree']['children']), function ($n) { return ($n['props']['contentType'] ?? '') === 'custom_html'; }));
		// Only the English text loads the classic cross-sell.
		$english = strpos($label, 'english') === 0;
		pg_assert_same($english ? 1 : 0, count($raw), $label . ': the script as a raw block');
		if ($english) pg_assert_true(strpos($raw[0]['props']['html'], "module: 'cross_sell'") !== false, $label . ': the cross-sell script');
		$flags = _test_lc_flags($r['tree']);
		foreach (array('has_recurring_products', 'has_today_items', 'has_today_non_donations', 'has_recurring_non_donations', 'has_discount',
			'has_special_offer_code', 'has_currency_code', 'cart_item_has_image', 'cart_item_out_of_stock', 'cart_item_has_calendar_event',
			'cart_item_recurring_only', 'cart_item_not_donation', 'cart_item_donation', 'cart_item_added_by_offer', 'cart_item_quantity_editable',
			'cart_item_recurring_quantity_fixed', 'cart_item_recurring_quantity_editable', 'cart_item_recurring_donation', 'cart_item_recurring_amount') as $want_flag) {
			pg_assert_contains($want_flag, $flags, $label . ': flag ' . $want_flag);
		}
		pg_assert_false(in_array('has_items', $flags, true), $label . ': an empty cart is the widget\'s own state');
		pg_assert_true(isset($r['cfg']['empty_message']) && $r['cfg']['empty_message'] !== '', $label . ': the empty cart\'s words');
	}
}

// A site that ships to several recipients has a heading per recipient the
// widget's list does not group by: reported, once in each table.
function test_lc_cart_recipient_headings_drop()
{
	$state = pg_layout_convert_state('shopping cart', array('multi_recipient' => true));
	$r = pg_layout_convert('shopping cart', _test_lc_site('english_default', 248), array('state' => $state));
	pg_assert_same(array('recipients', 'recipients'), _test_lc_codes($r['dropped']));
	pg_assert_contains('ship-to heading', implode(' ', array_column(pg_layout_dropped_rows($r['dropped']), 'text')));
}

// The page's description setting picks one of the layout's two branches.
function test_lc_cart_description_setting()
{
	$code = _test_lc_site('english_default', 248);
	$full = pg_layout_convert('shopping cart', $code, array('state' => _test_lc_cart_state()));
	$short = pg_layout_convert('shopping cart', $code, array('state' => pg_layout_convert_state('shopping cart', array('product_description_type' => 'short_description'))));
	pg_assert_same(array(), $short['dropped']);
	pg_assert_contains('__item_description', _test_lc_bound($full['tree'], 'text'));
	pg_assert_false(in_array('__item_description', _test_lc_bound($short['tree'], 'text'), true), 'short: no full description');
	pg_assert_same(array(), _test_lc_bound($short['tree'], 'src'), 'short: no picture');
	pg_assert_contains('__item_short_description', _test_lc_bound($short['tree'], 'text'));
}

// The stock template: two sentences with the page's words inside are
// dropped; the rest converts.
function test_lc_cart_template()
{
	$r = pg_layout_convert('shopping cart', _test_lc_template('shopping cart'), array('state' => _test_lc_cart_state()));
	pg_assert_same(true, $r['ok']);
	pg_assert_same(array('value', 'value'), _test_lc_codes($r['dropped']));
	pg_assert_same(2, count(_test_lc_of_type($r['tree'], 'loop_area')));
}

// A switch skipped inside a block the widget prints whole: its later
// "case …:" lines open nothing, so what follows the block is read.
function test_lc_skipped_switch_cases()
{
	$code = "<p><?php if (\$item['form']): ?><?php switch(\$x): case 'a': ?>A<?php break ?><?php case 'b': ?>B<?php break ?><?php endswitch ?><?php endif ?></p><span><?=h(\$item['name'])?></span>";
	$r = pg_layout_convert('shopping cart', $code, array());
	pg_assert_same(array(), $r['dropped']);
	pg_assert_contains('__item_name', _test_lc_bound($r['tree'], 'text'), 'the value after the block');
}

function _test_lc_express_order_texts()
{
	return array(
		'english_default/256' => _test_lc_site('english_default', 256),
		'turkish_default/256' => _test_lc_site('turkish_default', 256),
		'english_default/476' => _test_lc_site('english_default', 476),
		'turkish_default/476' => _test_lc_site('turkish_default', 476),
		'english_default/497' => _test_lc_site('english_default', 497),
	);
}

// The express order pages of the starting sites: one recipient, a PO
// number box on the order page, no quick add and no billing form.
function _test_lc_express_order_state($row = array())
{
	return pg_layout_convert_state('express order', $row + array('product_description_type' => 'full_description', 'po_number' => 1));
}

// Every starting-site text of the express order: nothing dropped, the cart
// rows in both tables by the cart's row flags, the blocks the widget prints
// whole as its sections, the boxes and buttons named or bound as the
// widget needs, the applied gift cards as its list, no form left.
function test_lc_express_order_starting_site_texts()
{
	pg_assert_same(5, count(array_unique(_test_lc_express_order_texts())), 'five distinct texts');
	foreach (_test_lc_express_order_texts() as $label => $code) {
		$r = pg_layout_convert('express order', $code, array('state' => _test_lc_express_order_state()));
		pg_assert_same(true, $r['ok'], $label);
		pg_assert_same(array(), $r['dropped'], $label . ': nothing dropped');
		$json = json_encode($r['tree']);
		pg_assert_same(false, strpos($json, '<?'), $label . ': no PHP');
		pg_assert_same(false, (bool)preg_match('/pg-(vis|loop|row|section|form|raw|source)|"_list"/', $json), $label . ': no marker left');
		$loops = array_values(array_filter(_test_lc_of_type($r['tree'], 'loop_area'), function ($n) { return empty($n['props']['source']); }));
		pg_assert_same(2, count($loops), $label . ': today\'s and the recurring table');
		pg_assert_same('cart_item_today', $loops[0]['children'][0]['props']['_bindings']['eo_visible_if'], $label . ': the first table\'s rows');
		pg_assert_same('cart_item_recurring', $loops[1]['children'][0]['props']['_bindings']['eo_visible_if'], $label . ': the second table\'s rows');
		$sections = _test_lc_bound($r['tree'], 'section');
		foreach (array('eo_errors_notices', 'eo_payment_methods', 'eo_terms', 'eo_applied_offers', 'eo_payment_periods') as $want) {
			pg_assert_contains($want, $sections, $label . ': section ' . $want);
		}
		pg_assert_same(array('eo_submit_update', 'eo_submit_purchase'), array_slice(array_unique(_test_lc_bound($r['tree'], 'action')), 0, 2), $label . ': the buttons');
		pg_assert_contains('billing_country', _test_lc_bound($r['tree'], 'eo_field'), $label . ': the country list the widget fills');
		$names = _test_lc_names($r['tree']);
		pg_assert_contains('quantity[^^__item_id^^]', $names, $label . ': the express order\'s quantity box');
		pg_assert_false(in_array('donations[^^__item_id^^]', $names, true), $label . ': a donation\'s box is its quantity box');
		pg_assert_contains('billing_email_address', $names, $label . ': billing fields keep the classic names');
		$sourced = array_values(array_filter(_test_lc_of_type($r['tree'], 'loop_area'), function ($n) { return !empty($n['props']['source']); }));
		pg_assert_same(array('checkout.gift_cards'), array_map(function ($n) { return $n['props']['source']; }, $sourced), $label . ': the applied gift cards');
		pg_assert_same(array(), array_values(array_filter(_test_lc_nodes($r['tree']['children']), function ($n) { return _pg_lfc_tag($n) === 'form'; })), $label . ': the widget draws the form');
		$text = _test_lc_bound($r['tree'], 'text');
		foreach (array('__item_gift_card_html', '__item_form_data_html', '__item_recurring_schedule_html', '__cart_subtotal', '__cart_total',
			'__cart_discount', '__update_button_label', '__purchase_now_button_label') as $want) {
			pg_assert_contains($want, $text, $label . ': ' . $want);
		}
		$flags = _test_lc_flags($r['tree']);
		foreach (array('has_recurring_products', 'has_today_items', 'has_subtotal_line', 'has_discount', 'has_gift_card', 'has_payment',
			'has_special_offer_code', 'cart_item_not_donation', 'cart_item_donation') as $want_flag) {
			pg_assert_contains($want_flag, $flags, $label . ': flag ' . $want_flag);
		}
		pg_assert_same(false, strpos($json, '-^^__cart_discount'), $label . ': the value carries its own minus');
	}
}

// The parts the widget has not: a quick add box, the page's own billing
// form, the ship-to names of a multi-recipient site are reported.
function test_lc_express_order_settings_drop()
{
	$code = _test_lc_site('english_default', 256);
	$r = pg_layout_convert('express order', $code, array('state' => _test_lc_express_order_state(array('quick_add_product_group_id' => 4, 'form' => 1, 'multi_recipient' => true))));
	pg_assert_same(array('quick_add', 'recipients', 'billing_form'), array_values(array_unique(_test_lc_codes($r['dropped']))));
	$off = pg_layout_convert('express order', $code, array('state' => _test_lc_express_order_state(array('po_number' => 0))));
	pg_assert_false(in_array('po_number', _test_lc_names($off['tree']), true), 'no PO number box when the page asks none');
	pg_assert_contains('po_number', _test_lc_names($r['tree']), 'the PO number box');
}

// The custom billing fields take the page's labels.
function test_lc_express_order_custom_fields()
{
	$state = _test_lc_express_order_state(array('custom_field_1_label' => 'Tax <ID>', 'custom_field_1_required' => 1));
	$r = pg_layout_convert('express order', _test_lc_site('english_default', 256), array('state' => $state));
	pg_assert_contains('custom_field_1', _test_lc_names($r['tree']));
	pg_assert_false(in_array('custom_field_2', _test_lc_names($r['tree']), true), 'the second field is off');
	$labels = array_values(array_filter(_test_lc_nodes($r['tree']['children']), function ($n) { return _pg_lfc_attr($n, 'for') === 'custom_field_1'; }));
	pg_assert_same('Tax <ID>*', $labels[0]['props']['text'], 'the label as words, with its star');
}

// The stock template converts; the words the page builds in PHP and the
// parts only it carries are reported.
function test_lc_express_order_template()
{
	$r = pg_layout_convert('express order', _test_lc_template('express order'), array('state' => _test_lc_express_order_state()));
	pg_assert_same(true, $r['ok']);
	pg_assert_contains('condition', _test_lc_codes($r['dropped']), 'the store credit box');
	pg_assert_same(2, count(array_filter(_test_lc_of_type($r['tree'], 'loop_area'), function ($n) { return empty($n['props']['source']); })));
}

// -- checkout steps -----------------------------------------------------------

// The checkout steps' starting-site texts: billing information 4 pages (3
// texts), shipping address and arrival 2, shipping method 2.
function _test_lc_checkout_texts()
{
	$out = array();
	foreach (array('english_default', 'turkish_default') as $site) {
		foreach (array(179 => 'billing information', 1076 => 'billing information', 144 => 'shipping address and arrival', 145 => 'shipping method') as $id => $type) {
			$out[$site . '/' . $id] = array($type, _test_lc_site($site, $id));
		}
	}
	return $out;
}

function _test_lc_checkout_state($type, $row = array())
{
	return pg_layout_convert_state($type, $row + array('submit_button_label' => 'Continue Checkout'));
}

// Every starting-site text of the three steps: nothing dropped, no marker
// left, the controls by the classic names, the button with the page's words.
function test_lc_checkout_starting_site_texts()
{
	$texts = array();
	foreach (_test_lc_checkout_texts() as $t) $texts[] = $t[1];
	pg_assert_same(7, count(array_unique($texts)), 'seven distinct texts');
	$want = array(
		'billing information' => array('names' => array('custom_field_1', 'billing_first_name', 'billing_email_address', 'opt_in', 'billing_state', 'billing_country', 'update_contact', 'po_number', 'referral_source', 'tax_exempt'),
			'flags' => array('has_custom_fields', 'has_custom_field_1', 'shows_opt_in', 'can_update_contact', 'has_po_number', 'has_referral_sources', 'has_tax_exempt')),
		'shipping address and arrival' => array('names' => array('first_name', 'state', 'country', 'address_type', 'shipping_same_as_billing', 'arrival_date', 'custom_arrival_date'),
			'flags' => array('is_multi_recipient', 'has_address_type', 'offers_same_as_billing', 'has_custom_form', 'has_arrival_dates', 'arrival_is_standard', 'arrival_is_custom')),
		'shipping method' => array('names' => array('shipping_method', 'currency_id'),
			'flags' => array('has_arrival_date', 'recipient_has_company', 'recipient_has_address_2', 'no_shipping_methods', 'has_shipping_methods', 'has_currency_choice')),
	);
	foreach (_test_lc_checkout_texts() as $label => $t) {
		list($type, $code) = $t;
		$r = pg_layout_convert($type, $code, array('state' => _test_lc_checkout_state($type)));
		pg_assert_same(true, $r['ok'], $label);
		pg_assert_same(array(), $r['dropped'], $label . ': nothing dropped');
		$json = json_encode($r['tree']);
		pg_assert_same(false, strpos($json, '<?'), $label . ': no PHP');
		pg_assert_same(false, (bool)preg_match('/pg-(vis|loop|row|section|form)|"_list"|%5E/i', $json), $label . ': no marker left');
		pg_assert_contains('Continue Checkout', $json, $label . ': the button\'s words');
		$names = _test_lc_names($r['tree']);
		foreach ($want[$type]['names'] as $name) pg_assert_contains($name, $names, $label . ': control ' . $name);
		$flags = _test_lc_flags($r['tree']);
		foreach ($want[$type]['flags'] as $flag) pg_assert_contains($flag, $flags, $label . ': flag ' . $flag);
		pg_assert_same($type === 'billing information' ? 0 : 1, count(_test_lc_of_type($r['tree'], 'loop_area')), $label . ': the list');
	}
}

// The shipping method's address column is drawn without an arrival date;
// the items table is the widget's items list; the methods row the list.
function test_lc_shipping_method_parts()
{
	$r = pg_layout_convert('shipping method', _test_lc_site('english_default', 145), array('state' => _test_lc_checkout_state('shipping method')));
	$first = $r['tree']['children'][0];
	pg_assert_same(false, isset($first['props']['_bindings']['eo_visible_if']), 'the address row is always drawn');
	pg_assert_same('has_arrival_date', $first['children'][1]['props']['_bindings']['eo_visible_if'], 'the arrival column under its flag');
	pg_assert_contains('recipient_items', _test_lc_bound($r['tree'], 'section'));
	$tables = array_filter(_test_lc_nodes($r['tree']['children']), function ($n) { return _pg_lfc_tag($n) === 'table'; });
	pg_assert_same(1, count($tables), 'the items table went with its heading row');
	pg_assert_contains('^^__change_address_url^^', json_encode($r['tree'], JSON_UNESCAPED_SLASHES), 'the Update link\'s address');
	$loop = _test_lc_of_type($r['tree'], 'loop_area')[0];
	pg_assert_contains('__shipping_method_name', _test_lc_bound(array('children' => array($loop)), 'text'));
}

// The billing step's required star follows the page's setting; the custom
// arrival day's box is named as the widget renames it.
function test_lc_checkout_settings()
{
	$code = _test_lc_site('english_default', 179);
	$on = pg_layout_convert('billing information', $code, array('state' => _test_lc_checkout_state('billing information', array('custom_field_1_required' => 1))));
	$off = pg_layout_convert('billing information', $code, array('state' => _test_lc_checkout_state('billing information')));
	$label = function ($r) {
		$found = array_values(array_filter(_test_lc_nodes($r['tree']['children']), function ($n) { return _pg_lfc_attr($n, 'for') === 'custom_field_1'; }));
		return trim((string)($found[0]['props']['text'] ?? ''));
	};
	pg_assert_same('*', substr($label($on), -1), 'the star when required');
	pg_assert_false(strpos($label($off), '*'), 'no star otherwise');
	$r = pg_layout_convert('shipping address and arrival', _test_lc_site('english_default', 144), array('state' => _test_lc_checkout_state('shipping address and arrival')));
	pg_assert_false(in_array('custom_arrival_date_^^__arrival_date_id^^', _test_lc_names($r['tree']), true));
}

// The stock templates convert; the words the page builds in PHP are
// reported.
function test_lc_checkout_templates()
{
	foreach (array('billing information' => array('value'), 'shipping address and arrival' => array('value', 'value'), 'shipping method' => array('value', 'value', 'value')) as $type => $codes) {
		$r = pg_layout_convert($type, _test_lc_template($type), array('state' => _test_lc_checkout_state($type)));
		pg_assert_same(true, $r['ok'], $type);
		pg_assert_same($codes, _test_lc_codes($r['dropped']), $type);
	}
}

// -- order preview ------------------------------------------------------------

// The order preview's starting-site texts: 4 pages, each its own text.
function _test_lc_order_preview_texts()
{
	$out = array();
	foreach (array('english_default', 'turkish_default') as $site) {
		foreach (array(101, 1077) as $id) $out[$site . '/' . $id] = _test_lc_site($site, $id);
	}
	return $out;
}

// Every starting-site text: nothing dropped, no marker left; the recipients
// the widget's section once, the offers and gift cards its lists, the
// payment methods and billing lines under the widget's flags.
function test_lc_order_preview_starting_site_texts()
{
	$texts = _test_lc_order_preview_texts();
	pg_assert_same(4, count(array_unique($texts)), 'four distinct texts');
	$want = array('cart_empty', 'has_items', 'has_recurring', 'shows_subtotal', 'has_discount', 'has_shipping_cost', 'has_surcharge',
		'has_currency_disclaimer', 'has_applied_offers', 'can_purchase_in_totals', 'shows_custom_field_1', 'billing_has_name',
		'billing_has_city_line', 'billing_has_city', 'has_billing_form', 'has_billing_form_title', 'has_applied_gift_cards',
		'has_payment', 'has_gift_card_code', 'pays_by_card', 'one_payment_method', 'many_payment_methods', 'pays_by_paypal',
		'pays_offline', 'has_card_verification_help', 'surcharge_message', 'has_terms', 'can_purchase');
	foreach ($texts as $label => $code) {
		$r = pg_layout_convert('order preview', $code, array('state' => pg_layout_convert_state('order preview', array())));
		pg_assert_same(true, $r['ok'], $label);
		pg_assert_same(array(), $r['dropped'], $label . ': nothing dropped');
		$json = json_encode($r['tree'], JSON_UNESCAPED_SLASHES);
		pg_assert_same(false, strpos($json, '<?'), $label . ': no PHP');
		pg_assert_same(false, (bool)preg_match('/pg-(vis|loop|row|section|form|source)|"_list"|%5E/i', $json), $label . ': no marker left');
		$sections = _test_lc_bound($r['tree'], 'section');
		pg_assert_same(1, count(array_keys($sections, 'order_recipients')), $label . ': the recipients once');
		foreach (array('recurring_payments', 'billing_form') as $s) pg_assert_contains($s, $sections, $label . ': section ' . $s);
		$sources = array_map(function ($n) { return $n['props']['source'] ?? ''; }, _test_lc_of_type($r['tree'], 'loop_area'));
		pg_assert_same(array('checkout.offers', 'checkout.gift_cards'), $sources, $label . ': the lists');
		$flags = _test_lc_flags($r['tree']);
		foreach ($want as $flag) pg_assert_contains($flag, $flags, $label . ': flag ' . $flag);
		$names = _test_lc_names($r['tree']);
		foreach (array('gift_card_code', 'submit_apply_gift_card', 'payment_method', 'card_number', 'expiration', 'card_verification_number', 'terms', 'submit_purchase_now') as $name) {
			pg_assert_contains($name, $names, $label . ': control ' . $name);
		}
		pg_assert_contains('__purchase_now_label', _test_lc_bound($r['tree'], 'text'), $label . ': the button\'s words');
	}
}

// The Turkish texts' installments and 3-D Secure: under their flags, the
// installment table the widget's section.
function test_lc_order_preview_installments()
{
	$r = pg_layout_convert('order preview', _test_lc_site('turkish_default', 101), array());
	$flags = _test_lc_flags($r['tree']);
	foreach (array('has_installments', 'has_installment_table', 'has_threedsecure', 'no_surcharge') as $flag) pg_assert_contains($flag, $flags, $flag);
	pg_assert_contains('installment_table', _test_lc_bound($r['tree'], 'section'));
	pg_assert_contains('threedsecure', _test_lc_names($r['tree']));
}

// The stock template converts; the words it builds in PHP and the store
// credit box are reported.
function test_lc_order_preview_template()
{
	$r = pg_layout_convert('order preview', _test_lc_template('order preview'), array());
	pg_assert_same(true, $r['ok']);
	pg_assert_same(array('condition', 'value', 'value'), _test_lc_codes($r['dropped']));
	pg_assert_contains('pays_by_iyzico', _test_lc_flags($r['tree']));
	pg_assert_contains('Applied Gift Cards', json_encode($r['tree'], JSON_UNESCAPED_UNICODE), 'the plural heading');
}
