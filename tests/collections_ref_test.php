<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a field of a record another record points at, printed in a form
 * list or form detail widget (props._bindings.<prop> = '__ref',
 * props._bindParams.<prop>, includes/fn/collections.php): the stored spec,
 * the renderer's pre-pass, the references a value holds, what is asked of
 * each form, the value an element prints, its escaping by
 * pg_sw_apply_tokens(), the sentinel a path that does not expand it prints,
 * and the editor's twin of the spec.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_collections_ref_spec()
{
	$empty = array('field' => '', 'page_id' => 0, 'show' => '', 'kind' => '');
	pg_assert_same($empty, pg_coll_ref_spec(null), 'not an array');
	pg_assert_same($empty, pg_coll_ref_spec('x'), 'a string');
	pg_assert_same(
		array('field' => 'category', 'page_id' => 12, 'show' => 'title-tr', 'kind' => ''),
		pg_coll_ref_spec(array('field' => ' Cate gory', 'page_id' => '12', 'show' => 'Title-TR')),
		'tokens cut to the characters a binding keeps and lowercased, digits read as an id'
	);

	foreach (array('tab:abc', '0', -3, 'x', '', 0, 1.5, array(7)) as $value) {
		pg_assert_same(0, pg_coll_ref_spec(array('page_id' => $value))['page_id'], var_export($value, true));
	}
}

function test_collections_ref_bindings()
{
	$tree = array('type' => 'semantic', 'children' => array(
		array('type' => 'content', 'props' => array(
			'_bindings' => array('text' => '__ref', 'href' => '__ref'),
			'_bindParams' => array('text' => array('field' => 'category', 'page_id' => 4, 'show' => 'title')),
		)),
		array('type' => 'content', 'props' => array('_bindings' => array('text' => 'title'))),
		array('type' => 'semantic', 'children' => array(
			array('type' => 'content', 'props' => array(
				'_bindings' => array('src' => '__ref'),
				'_bindParams' => array('src' => array('field' => 'author', 'page_id' => 9, 'show' => 'photo')),
			)),
		)),
	));
	$refs = pg_coll_ref_bindings($tree);

	pg_assert_same(array('__ref_0', '__ref_1', '__ref_2'), array_keys($refs), 'one token per binding, in walk order');
	pg_assert_same(array('field' => 'category', 'page_id' => 4, 'show' => 'title', 'kind' => ''), $refs['__ref_0']);
	pg_assert_same(array('field' => '', 'page_id' => 0, 'show' => '', 'kind' => ''), $refs['__ref_1'], 'a binding without its spec reads nothing');
	pg_assert_same('__ref_0', $tree['children'][0]['props']['_bindings']['text']);
	pg_assert_same('__ref_1', $tree['children'][0]['props']['_bindings']['href']);
	pg_assert_same('title', $tree['children'][1]['props']['_bindings']['text'], 'another binding is left alone');
	pg_assert_same('__ref_2', $tree['children'][2]['children'][0]['props']['_bindings']['src'], 'nested nodes are walked');
}

function test_collections_ref_codes()
{
	pg_assert_same(array('AB12CD34EF', 'news_and_events', 'post[2]'), pg_coll_ref_codes(' AB12CD34EF, news_and_events,,post[2] , AB12CD34EF'), 'trimmed, empty and repeated parts left out');
	pg_assert_same(array('ok'), pg_coll_ref_codes("ok, x' OR 1=1, <b>, a b"), 'a part with other characters is no reference');
	pg_assert_same(array(), pg_coll_ref_codes(array('x')), 'not a scalar');
	pg_assert_same(pg_coll_ref_max(), count(pg_coll_ref_codes(implode(',', range(1, 80)))), 'at most the maximum');
}

function test_collections_ref_wanted()
{
	$refs = array(
		'__ref_0' => array('field' => 'category', 'page_id' => 4, 'show' => 'title'),
		'__ref_1' => array('field' => 'tags', 'page_id' => 4, 'show' => 'title'),
		'__ref_2' => array('field' => 'author', 'page_id' => 0, 'show' => 'name'),
		'__ref_3' => array('field' => 'author', 'page_id' => 7, 'show' => ''),
	);
	$records = array(
		1 => array('category' => array('data' => 'news'), 'tags' => array('data' => 'a, b'), 'author' => array('data' => 'x')),
		2 => array('category' => array('data' => 'news, sport')),
	);

	pg_assert_same(array(4 => array('news', 'sport', 'a', 'b')), pg_coll_ref_wanted($refs, $records), 'one list per form, each reference once; a spec without a page or a field to show asks nothing');
}

function test_collections_ref_value()
{
	$spec = array('field' => 'category', 'page_id' => 4, 'show' => 'title');
	$loaded = array(
		'codes' => array('AAAA111111' => 31, 'news' => 31, 'sport' => 32, 'empty' => 33),
		'rows' => array(31 => array('reference_code' => 'AAAA111111', 'address_name' => 'news'), 32 => array('reference_code' => 'BBBB222222', 'address_name' => 'sport')),
		'records' => array(
			31 => array('title' => array('data' => 'News', 'type' => 'text box', 'wysiwyg' => 0, 'translate' => 1)),
			32 => array('title' => array('data' => 'Sport & Games', 'type' => 'text box', 'wysiwyg' => 0, 'translate' => 1)),
			33 => array('title' => array('data' => '', 'type' => 'text box', 'wysiwyg' => 0, 'translate' => 1)),
		),
	);

	$value = pg_coll_ref_value($spec, array('category' => array('data' => 'sport, gone, AAAA111111, empty')), $loaded);
	pg_assert_same('Sport & Games, News', $value['data'], 'in the stored order; a reference to nothing or to an empty value is left out');
	pg_assert_same('text box', $value['type']);

	$value = pg_coll_ref_value(array('show' => 'address_name') + $spec, array('category' => array('data' => 'AAAA111111')), $loaded);
	pg_assert_same('news', $value['data'], 'the address name of the record');

	pg_assert_same(null, pg_coll_ref_value($spec, array('category' => array('data' => 'gone')), $loaded), 'nothing answers');
	pg_assert_same(null, pg_coll_ref_value($spec, array(), $loaded), 'the record has no such field');
	pg_assert_same(null, pg_coll_ref_value(array('page_id' => 0) + $spec, array('category' => array('data' => 'news')), $loaded), 'no form chosen');
	pg_assert_same(null, pg_coll_ref_value(array('show' => 'missing') + $spec, array('category' => array('data' => 'news')), $loaded), 'a field the other form does not have');
}

// The value is escaped once, by pg_sw_apply_tokens(), like the record's own
// fields.
function test_collections_ref_value_escaped_once()
{
	$custom = array('__ref_0' => array('data' => 'Sport & <Games>', 'type' => 'text box', 'wysiwyg' => 0, 'translate' => 1));
	pg_assert_same('<p>Sport &amp; &lt;Games&gt;</p>', pg_sw_apply_tokens('<p>^^__ref_0^^</p>', array(), $custom, array()));
}

// Nothing is asked and nothing written when no element reads a reference.
function test_collections_ref_fill_without_refs()
{
	$by_form = array(1 => array('title' => array('data' => 'x')));
	pg_coll_ref_fill(array(), $by_form, $by_form);
	pg_assert_same(array(1 => array('title' => array('data' => 'x'))), $by_form);
}

// A path that does not expand the sentinel prints a link that goes nowhere,
// never href="" (the current address).
function test_collections_ref_unexpanded_href()
{
	$props = _apply_bindings(array('href' => '/x', '_bindings' => array('href' => '__ref')));
	pg_assert_same('#', $props['href']);
}

// The editor's twin keeps a tab of the design as the form until Publish.
function test_collections_ref_editor_spec()
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) {
		return;
	}

	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	$start = strpos($js, 'function _swRefSpec(');
	$end = ($start === false) ? false : strpos($js, "\n    }\n", $start);
	pg_assert_true(($start !== false) && ($end !== false), 'the designer has _swRefSpec()');
	$fn = substr($js, $start, $end - $start + 6);

	$script = $fn . "\nconsole.log(JSON.stringify(["
		. "_swRefSpec(null),"
		. "_swRefSpec({field: ' Cate gory', page_id: '12', show: 'Title'}),"
		. "_swRefSpec({field: 'a', page_id: 'tab:k1', show: 'b'}),"
		. "_swRefSpec({page_id: -3}),"
		. "_swRefSpec({field: 'item', page_id: 12, show: 'price', kind: 'product'}),"
		. "_swRefSpec({field: 'about', show: 'url', kind: 'page'}),"
		. "_swRefSpec({field: 'x', page_id: 3, kind: 'reference'})"
		. "]));";
	$file = tempnam(sys_get_temp_dir(), 'pgref');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);

	pg_assert_same(0, $code, implode("\n", $out));
	pg_assert_same(array(
		array('field' => '', 'page_id' => 0, 'show' => '', 'kind' => ''),
		array('field' => 'category', 'page_id' => 12, 'show' => 'title', 'kind' => ''),
		array('field' => 'a', 'page_id' => 'tab:k1', 'show' => 'b', 'kind' => ''),
		array('field' => '', 'page_id' => 0, 'show' => '', 'kind' => ''),
		array('field' => 'item', 'page_id' => 0, 'show' => 'price', 'kind' => 'product'),
		array('field' => 'about', 'page_id' => 0, 'show' => 'url', 'kind' => 'page'),
		array('field' => 'x', 'page_id' => 3, 'show' => '', 'kind' => ''),
	), json_decode(implode('', $out), true));
}

// The Bind Data panel offers the binding where the renderer expands it and
// keeps the spec beside it.
function test_collections_ref_panel_wiring()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	pg_assert_contains("var SW_REF_TOKEN = '__ref';", $js);
	pg_assert_contains('var withRef = _pageLinkOffered && _SW_REF_PROPS[prop] === 1;', $js, 'offered in the widget kinds that expand it');
	pg_assert_contains("bp._bindParams[propName] = { field: '', page_id: 0, show: '', kind: '' };", $js, 'picking it starts an empty spec');
	pg_assert_contains("p._bindParams[prop] = _swRefSpec(spec);", $js, 'an edit is stored normalised');
}

function test_collections_ref_spec_kind()
{
	pg_assert_same(
		array('field' => 'item', 'page_id' => 0, 'show' => 'price', 'kind' => 'product'),
		pg_coll_ref_spec(array('field' => 'item', 'page_id' => 12, 'show' => 'price', 'kind' => 'product')),
		'a product points into no form'
	);
	pg_assert_same('page', pg_coll_ref_spec(array('kind' => 'page'))['kind']);
	foreach (array('reference', 'number', 'Product', array('page'), 1) as $kind) {
		pg_assert_same('', pg_coll_ref_spec(array('kind' => $kind))['kind'], var_export($kind, true));
	}
	pg_assert_same(array('name', 'url', 'price', 'image'), pg_coll_ref_shows('product'));
	pg_assert_same(array('name', 'url'), pg_coll_ref_shows('page'));
	pg_assert_same(array(), pg_coll_ref_shows(''));
}

function test_collections_ref_wanted_by_kind()
{
	$refs = array(
		'__ref_0' => pg_coll_ref_spec(array('field' => 'item', 'show' => 'name', 'kind' => 'product')),
		'__ref_1' => pg_coll_ref_spec(array('field' => 'about', 'show' => 'url', 'kind' => 'page')),
		'__ref_2' => pg_coll_ref_spec(array('field' => 'item', 'show' => 'title', 'kind' => 'product')),
	);
	$records = array(
		1 => array('item' => array('data' => '12, x7, 9'), 'about' => array('data' => '4')),
		2 => array('item' => array('data' => '12')),
	);
	pg_assert_same(array('product' => array('12', '9'), 'page' => array('4')), pg_coll_ref_wanted($refs, $records),
		'ids only, each once; a show the kind does not have asks nothing');
}

function test_collections_ref_value_by_kind()
{
	$loaded = array('codes' => array('12' => 12, '9' => 9), 'rows' => array(
		12 => array('name' => 'Shoes', 'url' => '/shop/shoes', 'price' => '10.00', 'image' => '/shoes.jpg'),
		9  => array('name' => 'Bags', 'url' => '/shop/bags', 'price' => '', 'image' => ''),
	), 'records' => array());
	$spec = pg_coll_ref_spec(array('field' => 'item', 'show' => 'name', 'kind' => 'product'));
	$value = pg_coll_ref_value($spec, array('item' => array('data' => '12, 5, 9')), $loaded);
	pg_assert_same('Shoes, Bags', $value['data']);
	pg_assert_same('', $value['type']);
	$value = pg_coll_ref_value(array('show' => 'image') + $spec, array('item' => array('data' => '9, 12')), $loaded);
	pg_assert_same('/shoes.jpg', $value['data'], 'an empty value is left out');
	pg_assert_same(null, pg_coll_ref_value(array('show' => 'reference_code') + $spec, array('item' => array('data' => '12')), $loaded), 'not a product field');
}

function test_collections_ref_product_and_page_fields()
{
	$row = array('id' => 12, 'name' => 'SKU-1', 'short_description' => 'Red shoes', 'address_name' => 'red shoes', 'price' => 1250, 'image_name' => 'red shoes.jpg');
	$fields = pg_coll_ref_product_fields($row, '/shop/', '/');
	pg_assert_same('Red shoes', $fields['name']);
	pg_assert_same('/shop/red%20shoes', $fields['url']);
	pg_assert_same(pg_money_text(pg_visitor_amount(12.5), null, defined('VISITOR_CURRENCY_CODE_FOR_OUTPUT') ? VISITOR_CURRENCY_CODE_FOR_OUTPUT : ''), $fields['price']);
	pg_assert_same('/red%20shoes.jpg', $fields['image']);

	$fields = pg_coll_ref_product_fields(array('short_description' => '', 'address_name' => '', 'image_name' => '') + $row, '', '/');
	pg_assert_same('SKU-1', $fields['name'], 'the name when there is no short description');
	pg_assert_same('', $fields['url'], 'no detail page or no address name: no link');
	pg_assert_same('', $fields['image']);

	pg_assert_same(array('name' => 'about us', 'url' => '/site/about%20us'), pg_coll_ref_page_fields(array('page_name' => 'about us'), '/site/'));
}
