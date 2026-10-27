<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the product lists (includes/fn/widgets_lists_product.php): the
 * catalog detail widget lists a product's images, its attribute values and
 * the products bought with it, drawn by the finish pass from the rows the
 * renderer published, keyed by the product's id.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _wlp_node($tag, $class, $children = array(), $extra = array())
{
	return array('type' => 'semantic', 'props' => array('tag' => $tag, 'cssClass' => $class) + $extra, 'children' => $children);
}

function _wlp_text($token, $class, $tag = 'span')
{
	return _wlp_node($tag, $class, array(), array('text' => 'Sample', '_bindings' => array('text' => $token)));
}

function _wlp_list($source, $children, $props = array())
{
	return array('type' => 'loop_area', 'props' => array('source' => $source) + $props, 'children' => $children);
}

// What the catalog detail renderer does: the tree split, the product's
// template filled once with pg_sw_fill_longest(), the rows of the lists it
// drew published for product $pid ($read: source => rows), then the
// finish pass. Returns array(finished markup, the sources it asked for).
function _wlp_detail($tree, $pid, $name, $read)
{
	$scope = pg_sw_finish_open(array('sid' => 77, 'kind' => 'catalog_item_view', 'email' => false));
	try {
		$split = _split_widget_tree($tree);
		$tpl = '';
		foreach ($split['loop_children'] as $child) $tpl .= _render_tree_node($child, 0, 0);
		$wanted = pg_sw_list_wanted('product');
		$rows = array();
		foreach ($read as $source => $list) {
			if (isset($wanted[$source])) $rows[$source][$pid] = $list;
		}
		pg_sw_list_publish('product', $rows);
		$html = pg_sw_sweep_tokens(pg_sw_fill_longest(trim($tpl), pg_sw_escape_token_values(array('__id' => (string)$pid, '__name' => $name))));
		return array(pg_sw_finish_widget_html($html, $scope), $wanted);
	} finally {
		pg_sw_finish_close();
	}
}

function _wlp_base()
{
	return defined('OUTPUT_PATH') ? OUTPUT_PATH : '/';
}

function _wlp_texts($html, $class)
{
	preg_match_all('#<[a-z][a-z0-9]* class="' . preg_quote($class, '#') . '"[^>]*>(.*?)</#s', $html, $m);
	return $m[1];
}

// Every product source's row keys are its tokens; the catalog detail view
// offers the product sources and the crumbs, the form views none.
function test_widget_lists_product_descriptors()
{
	$rows = array(
		'product.images'     => pg_sw_list_product_image_rows('a.jpg', array()),
		'product.attributes' => pg_sw_list_product_attr_rows(array(array('name' => 'color', 'label' => 'Colour', 'option_label' => 'Red', 'no_value' => '0'))),
		'product.related'    => pg_sw_list_product_related_rows(array(array('id' => 4, 'name' => 'Cup'))),
	);
	$sources = pg_sw_list_sources();
	pg_assert_same(array_merge(array_keys($rows), array('catalog.crumbs')), array_keys(pg_sw_kind_lists('catalog_item_view')), 'the detail view offers the product sources and the crumbs');
	foreach ($rows as $source => $list) {
		$d = $sources[$source];
		pg_assert_same('product', $d['family'], $source);
		pg_assert_same('__id', $d['key'], $source);
		pg_assert_false($d['arg_required'], $source);
		$tokens = array();
		foreach ($d['tokens'] as $pair) $tokens[] = $pair[0];
		pg_assert_same(1, count($list), $source . ' one row');
		pg_assert_same($tokens, array_keys($list[0]), $source . ' row keys');
		pg_assert_true(pg_sw_list_spec(array('source' => $source)) !== false, $source . ' needs no arg');
	}
	foreach (array('form_list_view', 'form_item_view') as $kind) {
		foreach (pg_sw_kind_lists($kind) as $d) pg_assert_true(in_array($d['family'], array('form_record', 'paging'), true), $kind . ': no product list');
	}
	pg_assert_same(array('product.images', 'product.attributes', 'catalog.crumbs', 'catalog.active_filters', 'paging.pages'), array_keys(pg_sw_kind_lists('catalog_listing')), 'the listing: no products bought together');
}

function test_widget_lists_product_rows()
{
	$images = pg_sw_list_product_image_rows('main.jpg', array(array('file_name' => 'b one.jpg'), array('file_name' => 'main.jpg'), array('file_name' => ''), 'x'));
	pg_assert_same(array(_wlp_base() . 'main.jpg', _wlp_base() . 'b%20one.jpg'), array_column($images, '__gallery_url'), 'main first, each once, encoded');
	pg_assert_same(array(_wlp_base() . 'b.jpg'), array_column(pg_sw_list_product_image_rows('', array(array('file_name' => 'b.jpg'))), '__gallery_url'), 'no main image');

	$attrs = pg_sw_list_product_attr_rows(array(
		array('name' => 'color', 'label' => 'Colour', 'option_label' => 'Red', 'no_value' => '0'),
		array('name' => 'size', 'label' => '', 'option_label' => ' 42 ', 'no_value' => '0'),
		array('name' => 'gift', 'label' => 'Gift wrap', 'option_label' => 'None', 'no_value' => '1'),
		array('name' => 'x', 'label' => 'X', 'option_label' => '', 'no_value' => '0'),
	));
	pg_assert_same(array(array('__attr_name' => 'Colour', '__attr_value' => 'Red'), array('__attr_name' => 'size', '__attr_value' => '42')), $attrs, 'label or name; no-value and empty options give none');

	$related = pg_sw_list_product_related_rows(array(
		array('id' => 3, 'name' => 'Mug', 'short_description' => 'A mug', 'url' => '/p/mug', 'image_url' => '/mug.jpg',
			'price_formatted' => '₺90.00', 'has_discount' => '1', 'original_price_formatted' => '₺100.00'),
		array('id' => 5, 'name' => 'Pen', 'price_formatted' => '₺5.00', 'has_discount' => '0', 'original_price_formatted' => '₺5.00'),
		array('id' => 0, 'name' => 'none'),
	));
	pg_assert_same(2, count($related), 'an item without an id gives none');
	pg_assert_same('₺100.00', $related[0]['__related_original_price'], 'the sticker price with a discount');
	pg_assert_same('', $related[1]['__related_original_price'], 'none without');
	pg_assert_same('#', $related[1]['__related_url'], 'no address');
}

// The detail view draws each list from the product's published rows, the
// product's own values read in every row; a source not drawn is not read.
function test_widget_lists_product_detail_end_to_end()
{
	$tree = array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'loop_area', 'props' => array(), 'children' => array(
			_wlp_text('__name', 'title', 'h1'),
			_wlp_node('div', 'gallery', array(_wlp_list('product.images', array(
				_wlp_node('img', 'pic', array(), array('src' => 'x.jpg', '_bindings' => array('src' => '__gallery_url', 'alt' => '__name'))),
			)))),
			_wlp_node('dl', 'specs', array(_wlp_list('product.attributes', array(
				_wlp_text('__attr_name', 'k', 'dt'),
				_wlp_text('__attr_value', 'v', 'dd'),
			), array('order' => 'za')))),
		)),
	));
	$read = array(
		'product.images'     => pg_sw_list_product_image_rows('a.jpg', array(array('file_name' => 'b.jpg'))),
		'product.attributes' => array(array('__attr_name' => 'Colour', '__attr_value' => 'Red & Blue'), array('__attr_name' => 'Size', '__attr_value' => '42')),
		'product.related'    => array(array('__related_name' => 'never asked')),
	);
	list($out, $wanted) = _wlp_detail($tree, 12, 'Mug <b>', $read);
	pg_assert_same(array('product.images' => true, 'product.attributes' => true), $wanted, 'the sources drawn');
	pg_assert_same(false, strpos($out, 'pg-list'), 'no span left');
	pg_assert_same(false, strpos($out, '^^'), 'no token left');
	pg_assert_same(2, substr_count($out, 'class="pic"'), 'one image each');
	pg_assert_contains('src="' . _wlp_base() . 'b.jpg"', $out);
	pg_assert_same(2, substr_count($out, 'alt="Mug &lt;b&gt;"'), 'the product name in each row, escaped once');
	pg_assert_same(array('Size', 'Colour'), _wlp_texts($out, 'k'), 'Z to A');
	pg_assert_same(array('42', 'Red &amp; Blue'), _wlp_texts($out, 'v'), 'escaped once');
	pg_assert_same(false, strpos($out, 'never asked'));

	// Another product's rows, or none: the list draws nothing.
	list($none) = _wlp_detail($tree, 13, 'Pen', array());
	pg_assert_same(0, substr_count($none, 'class="pic"'), 'no rows');
	pg_assert_same(false, strpos($none, 'pg-list'));

	// The same tree in a form view: no product list is drawn there.
	$scope = pg_sw_finish_open(array('sid' => 78, 'kind' => 'form_item_view', 'email' => false));
	try {
		$html = _render_tree_node(_wlp_list('product.images', array(_wlp_text('__gallery_url', 'u'))), 0, 0);
		pg_assert_same('', $html, 'a form view offers no product list');
		pg_assert_same(array(), pg_sw_list_wanted('product'));
	} finally {
		pg_sw_finish_close();
	}
}

// A catalog listing draws product and group cards from one template: each
// product card lists its own rows; a group card, whose id may be a
// product's too, lists none.
function test_widget_lists_product_listing_cards()
{
	$card = array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'loop_area', 'props' => array(), 'children' => array(
			_wlp_node('div', 'card', array(
				_wlp_text('__name', 'name', 'h3'),
				_wlp_node('ul', 'specs', array(_wlp_list('product.attributes', array(_wlp_text('__attr_value', 'v', 'li'))))),
			)),
		)),
	));
	$scope = pg_sw_finish_open(array('sid' => 79, 'kind' => 'catalog_listing', 'email' => false));
	try {
		$split = _split_widget_tree($card);
		$tpl = '';
		foreach ($split['loop_children'] as $child) $tpl .= _render_tree_node($child, 0, 0);
		pg_assert_same(array('product.attributes' => true), pg_sw_list_wanted('product'));
		pg_sw_list_publish('product', array('product.attributes' => array(
			5 => array(array('__attr_name' => 'Size', '__attr_value' => 'S'), array('__attr_name' => 'Size', '__attr_value' => 'M')),
			6 => array(array('__attr_name' => 'Size', '__attr_value' => 'L')),
		)));
		$cards = array(
			array('__row_type' => 'group', '__id' => '5', '__name' => 'Shirts'),
			array('__id' => '5', '__name' => 'Tee'),
			array('__id' => '6', '__name' => 'Polo'),
		);
		$html = '';
		foreach ($cards as $values) $html .= pg_sw_fill_longest(trim($tpl), pg_sw_escape_token_values($values));
		$out = pg_sw_finish_widget_html(pg_sw_sweep_tokens($html), $scope);
	} finally {
		pg_sw_finish_close();
	}
	pg_assert_same(array('Shirts', 'Tee', 'Polo'), _wlp_texts($out, 'name'));
	pg_assert_same(array('S', 'M', 'L'), _wlp_texts($out, 'v'), 'the group card lists nothing, each product its own');
	pg_assert_same(false, strpos($out, 'pg-l'), 'no span left');
	pg_assert_same(false, strpos($out, '^^'), 'no token left');
}
