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
	pg_assert_same(null, pg_layout_piece_map('catalog'), 'a type of a later family has none yet');
	pg_assert_same(null, pg_layout_piece_map('custom form'), 'the custom form keeps its own converter');
	pg_assert_same(array('form item view', 'form list view', 'order form', 'photo gallery'), pg_layout_convert_types());
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
	$r = pg_layout_convert('catalog', '<p>x</p>', array());
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
	pg_assert_same(false, pg_layout_type_file_convert(array('type' => 'catalog'), $code, array())['ok'], 'a type with no map');
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
