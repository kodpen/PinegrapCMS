<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the lists inside a record (includes/fn/widgets_lists.php): a
 * loop_area with props.source is a list, drawn as a span of carriers while
 * the widget renders and expanded by the finish pass from the rows the
 * renderer published (a field's values, its comma-separated parts, the
 * record's attachments). The unsourced loop_area stays the main loop.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// $fn run inside a widget scope of $kind, closed whatever happens.
function _widget_lists_in_scope($kind, $fn, $sid = 31)
{
	$scope = pg_sw_finish_open(array('sid' => $sid, 'kind' => $kind, 'email' => false));
	try {
		return $fn($scope);
	} finally {
		pg_sw_finish_close();
	}
}

// A list of $source over $arg (null: none) whose row template is $children.
function _widget_lists_list($source, $arg, $children, $props = array())
{
	$props = array('source' => $source) + $props;
	if ($arg !== null) $props['sourceArg'] = $arg;
	return array('type' => 'loop_area', 'props' => $props, 'children' => $children);
}

// An element of $tag with $class, its text bound to $token.
function _widget_lists_text($token, $class = 'v', $tag = 'span', $extra = array())
{
	$props = array('tag' => $tag, 'cssClass' => $class, 'text' => 'Sample', '_bindings' => array('text' => $token)) + $extra;
	return array('type' => 'semantic', 'props' => $props, 'children' => array());
}

function _widget_lists_box($tag, $class, $children, $extra = array())
{
	return array('type' => 'semantic', 'props' => array('tag' => $tag, 'cssClass' => $class) + $extra, 'children' => $children);
}

function _widget_lists_root($children)
{
	return array('type' => 'root', 'props' => array(), 'children' => $children);
}

// One form_data row as the form renderers read it.
function _widget_lists_data($form_id, $id, $name, $data, $type = 'check box', $extra = array())
{
	return $extra + array(
		'form_id'          => (string)$form_id,
		'data'             => $data,
		'data_name'        => $name,
		'data_file_id'     => '0',
		'field_name'       => $name,
		'field_label'      => ucfirst($name),
		'field_type'       => $type,
		'field_wysiwyg'    => '0',
		'file_name'        => null,
		'data_id'          => (string)$id,
		'field_office_use' => '0',
	);
}

// What a form renderer does for $tree inside a widget scope of $kind: the
// tree split, the record template rendered once and filled per record with
// pg_sw_apply_tokens() (the key through $extra, the title through
// $custom), swept and judged by pg-hide-if-empty, $row_fn applied (the edit
// pencil), the surround around it, and the data rows published. $records:
// form id => title. Returns array(markup, scope), not yet finished.
function _widget_lists_render($tree, $records, $data_rows, $kind = 'form_list_view', $row_fn = null, $options = array())
{
	return _widget_lists_in_scope($kind, function ($scope) use ($tree, $records, $data_rows, $row_fn, $options) {
		$split = _split_widget_tree($tree);
		if ($split['loop_children'] === null) {
			$tpl    = trim(_render_tree_node($tree, 0, 0));
			$static = '';
		} else {
			$tpl = '';
			foreach ($split['loop_children'] as $child) $tpl .= _render_tree_node($child, 0, 0);
			$tpl    = trim($tpl);
			$static = trim(_render_tree_node($split['static_tree'], 0, 0));
		}
		if ($data_rows !== null) pg_sw_list_publish('form_record', $data_rows, $options);
		$rows = '';
		foreach ($records as $id => $title) {
			$row = pg_sw_apply_tokens($tpl, array(), array('title' => array('data' => $title, 'type' => 'text box', 'wysiwyg' => 0)),
				array('submitted_form_id' => (string)$id));
			$row = pg_sw_hide_empty_fields(pg_sw_sweep_tokens($row));
			$rows .= ($row_fn !== null) ? $row_fn($row, $id) : $row;
		}
		$html = ($static === '') ? $rows : str_replace('<!--pg-loop-slot-->', $rows, pg_sw_sweep_tokens($static));
		return array($html, $scope);
	});
}

function _widget_lists_finished($tree, $records, $data_rows, $kind = 'form_list_view', $row_fn = null, $options = array())
{
	$r = _widget_lists_render($tree, $records, $data_rows, $kind, $row_fn, $options);
	return pg_sw_finish_widget_html($r[0], $r[1]);
}

function _widget_lists_no_markers($html, $label = '')
{
	pg_assert_same(false, strpos($html, 'pg-list'), $label . ' no pg-list');
	pg_assert_same(false, strpos($html, 'pg-lv'), $label . ' no pg-lv');
	pg_assert_same(false, strpos($html, pg_sw_marker_nonce()), $label . ' no nonce');
	pg_assert_same(false, strpos($html, '^^'), $label . ' no token');
}

// The texts of the elements with $class, in order.
function _widget_lists_texts($html, $class)
{
	preg_match_all('#<[a-z][a-z0-9]* class="' . preg_quote($class, '#') . '"[^>]*>(.*?)</#s', $html, $m);
	return $m[1];
}

// The chip list of a card: a pg-hide-if-empty box around a list of
// $source over $arg, each chip printing the value, the row number and
// count, and the record's title.
function _widget_lists_card_tree($source = 'form_record.values', $arg = 'section', $list_props = array(), $chip = null)
{
	$chip = ($chip !== null) ? $chip : array(
		_widget_lists_text('__value_text', 'val'),
		_widget_lists_text('__loop_index', 'no'),
		_widget_lists_text('__loop_count', 'cnt'),
		_widget_lists_text('title', 'ttl'),
	);
	return _widget_lists_root(array(
		_widget_lists_box('div', 'cards', array(
			array('type' => 'loop_area', 'props' => array(), 'children' => array(
				_widget_lists_box('div', 'card', array(
					_widget_lists_text('title', 'title', 'h3'),
					_widget_lists_box('div', 'box pg-hide-if-empty', array(
						_widget_lists_box('ul', 'chips', array(
							_widget_lists_list($source, $arg, array(_widget_lists_box('li', 'chip', $chip)), $list_props),
						)),
					)),
				)),
			)),
		)),
	));
}

// -- the spec --------------------------------------------------------------------

function test_widget_lists_spec()
{
	pg_assert_same(null, pg_sw_list_spec(array()), 'no source');
	pg_assert_same(null, pg_sw_list_spec(array('source' => '')), 'an empty source');
	pg_assert_same(null, pg_sw_list_spec('x'), 'no props');
	pg_assert_same(false, pg_sw_list_spec(array('source' => 'form_record.nope', 'sourceArg' => 'a')), 'an unknown source');
	pg_assert_same(false, pg_sw_list_spec(array('source' => 'Form_Record.values', 'sourceArg' => 'a')), 'a source not written as one');
	pg_assert_same(false, pg_sw_list_spec(array('source' => 'form_record.values')), 'a required arg missing');
	pg_assert_same(false, pg_sw_list_spec(array('source' => 'form_record.split', 'sourceArg' => ' ;"')), 'an arg the sanitiser empties');
	foreach (array(-1, 101, 'x', '2.5', 2.5, true) as $limit) {
		pg_assert_same(false, pg_sw_list_spec(array('source' => 'form_record.values', 'sourceArg' => 'a', 'limit' => $limit)), 'limit ' . var_export($limit, true));
	}
	pg_assert_same(false, pg_sw_list_spec(array('source' => 'form_record.values', 'sourceArg' => 'a', 'order' => 'up')), 'order up');

	pg_assert_same(array('source' => 'form_record.values', 'arg' => 'sec-tion', 'limit' => 0, 'order' => ''),
		pg_sw_list_spec(array('source' => 'form_record.values', 'sourceArg' => 'sec-tion <"\'>')), 'defaults, the arg sanitised');
	pg_assert_same(array('source' => 'form_record.files', 'arg' => '', 'limit' => 5, 'order' => 'za'),
		pg_sw_list_spec(array('source' => 'form_record.files', 'limit' => '5', 'order' => 'za')), 'files take no arg');
	pg_assert_same(array('source' => 'form_record.split', 'arg' => 'tags', 'limit' => 100, 'order' => 'reverse'),
		pg_sw_list_spec(array('source' => 'form_record.split', 'sourceArg' => 'tags', 'limit' => 100.0, 'order' => 'reverse')), 'a whole float');
	pg_assert_same(0, pg_sw_list_spec(array('source' => 'form_record.split', 'sourceArg' => 'tags', 'limit' => ''))['limit'], 'an empty limit is all rows');

	pg_assert_true(pg_sw_list_is_sourced(_widget_lists_list('form_record.values', 'a', array())));
	pg_assert_true(pg_sw_list_is_sourced(_widget_lists_list('nope', null, array())), 'an unknown source is still a list');
	pg_assert_false(pg_sw_list_is_sourced(array('type' => 'loop_area', 'props' => array())), 'the main loop');
	pg_assert_false(pg_sw_list_is_sourced(array('type' => 'loop_area', 'props' => array('source' => ''))));
	pg_assert_false(pg_sw_list_is_sourced(array('type' => 'semantic', 'props' => array('source' => 'form_record.values'))));
	pg_assert_false(pg_sw_list_is_sourced('loop_area'));
}

// -- the descriptor ----------------------------------------------------------------

// Every source's tokens are the keys of its provider's rows, and the kinds
// offer the form record sources in the two form views only.
function test_widget_lists_descriptor_parity()
{
	$data = array(
		_widget_lists_data(5, 1, 'section', 'Yemek'),
		_widget_lists_data(5, 2, 'tags', 'a, b'),
		_widget_lists_data(5, 3, 'cover', '', 'file upload', array('file_name' => 'c.png')),
	);
	$published = array('form_record' => array('records' => pg_sw_list_form_records($data), 'options' => array()));
	$args = array('form_record.values' => 'section', 'form_record.split' => 'tags', 'form_record.files' => '');
	foreach (pg_sw_list_sources() as $source => $d) {
		pg_assert_same(array('family', 'key', 'label', 'arg', 'arg_required', 'tokens'), array_keys($d), $source . ' shape');
		// The calendar sources: widget_lists_calendar_test.php.
		if ($d['family'] !== 'form_record') continue;
		pg_assert_true(isset($args[$source]), $source . ' has a fixture');
		$tokens = array();
		foreach ($d['tokens'] as $pair) $tokens[] = $pair[0];
		$rows = pg_sw_list_source_rows($source, array(5 => array($args[$source] => true)), $published);
		pg_assert_true(!empty($rows[5][$args[$source]]), $source . ' gives rows');
		foreach ($rows[5][$args[$source]] as $row) pg_assert_same($tokens, array_keys($row), $source . ' row keys');
	}
	pg_assert_same(pg_sw_kind_lists('form_list_view'), pg_sw_kind_lists('blog_list'));
	pg_assert_same(array_keys($args), array_keys(pg_sw_kind_lists('form_item_view')));
	pg_assert_same(array(), pg_sw_kind_lists('catalog_listing'));
	pg_assert_same(array(), pg_sw_kind_lists('comments'));
}

// -- the split ---------------------------------------------------------------------

function test_widget_lists_split_finds_the_main_loop()
{
	$main_kids = array(_widget_lists_text('title', 'main'));
	$list      = _widget_lists_list('form_record.values', 'section', array(_widget_lists_text('__value_text')));

	$before = _widget_lists_root(array($list, _widget_lists_box('div', 'w', array(array('type' => 'loop_area', 'props' => array(), 'children' => $main_kids)))));
	pg_assert_same($main_kids, _split_widget_tree($before)['loop_children'], 'a list before the main loop');

	$inside_kids = array(_widget_lists_text('title', 'main'), $list);
	$inside = _widget_lists_root(array(array('type' => 'loop_area', 'props' => array(), 'children' => $inside_kids)));
	pg_assert_same($inside_kids, _split_widget_tree($inside)['loop_children'], 'a list inside the main loop');

	$holder = _widget_lists_list('form_record.values', 'section', array(array('type' => 'loop_area', 'props' => array(), 'children' => array(_widget_lists_text('x')))));
	$nested = _widget_lists_root(array($holder, array('type' => 'loop_area', 'props' => array(), 'children' => $main_kids)));
	pg_assert_same($main_kids, _split_widget_tree($nested)['loop_children'], 'a loop_area inside a list is not the main loop');

	$only = _widget_lists_root(array(_widget_lists_box('div', 'w', array($holder))));
	$split = _split_widget_tree($only, true);
	pg_assert_same(null, $split['loop_children'], 'a list is no main loop');
	pg_assert_same($only, $split['static_tree'], 'the tree as it is');
	pg_assert_same('', $split['loop_parent']);
}

// A tree without props.source splits and renders as it did before lists.
function test_widget_lists_unsourced_tree_is_unchanged()
{
	$fixture = json_decode((string)file_get_contents(dirname(__DIR__) . '/tests/fixtures/widget_lists_unsourced.json'), true);
	pg_assert_true(is_array($fixture), 'the fixture reads');
	pg_assert_same($fixture['split'], _split_widget_tree($fixture['tree'], true), 'the split');
	$loop = $fixture['tree']['children'][1]['children'][0];
	pg_assert_same($fixture['markup'], _render_tree_node($loop, 1, 0), 'outside a scope');
	pg_assert_same($fixture['markup'], _widget_lists_in_scope('form_list_view', function () use ($loop) {
		return _render_tree_node($loop, 1, 0);
	}), 'inside a scope');

	pg_assert_same(array(), _widget_lists_in_scope('form_list_view', function ($scope) use ($fixture) {
		_render_tree_node($fixture['tree'], 0, 0);
		pg_sw_list_publish('form_record', array(_widget_lists_data(1, 1, 'section', 'x')));
		return pg_sw_list_take($scope);
	}, 9001), 'nothing published for a tree without a list');

	$scope = array('sid' => 9001, 'kind' => 'form_list_view');
	pg_assert_same('<p>x</p>', pg_sw_finish_widget_html('<p>x</p>', $scope), 'markup without a span');
	$html = '<p>x ' . pg_sw_marker_nonce() . '</p><!--pg-list:0000000000000000:1-->';
	pg_assert_same($html, pg_sw_finish_widget_html($html, $scope), 'the nonce as text and a forged span');
}

// -- the render half -------------------------------------------------------------

function test_widget_lists_span_render()
{
	$list = _widget_lists_list('form_record.values', 'section', array(
		_widget_lists_text('__value_text', 'val'),
		_widget_lists_text('title', 'ttl'),
		_widget_lists_text('__loop_index', 'no'),
		_widget_lists_text('published', 'date', 'span', array('_bindFormats' => array('text' => 'd.m.Y'))),
		_widget_lists_text('title', 'again'),
	));
	pg_assert_same('', _render_tree_node($list, 0, 0), 'outside a scope');
	pg_assert_same('', _widget_lists_in_scope('catalog_listing', function () use ($list) {
		return _render_tree_node($list, 0, 0);
	}), 'a kind without lists');
	$bad = $list;
	$bad['props']['source'] = 'form_record.nope';
	pg_assert_same('', _widget_lists_in_scope('form_item_view', function () use ($bad) {
		return _render_tree_node($bad, 0, 0);
	}), 'an invalid spec');

	$html  = _widget_lists_in_scope('form_item_view', function () use ($list) {
		return _render_tree_node($list, 0, 0);
	});
	$nonce = preg_quote(pg_sw_marker_nonce(), '/');
	pg_assert_true((bool)preg_match('/^<!--pg-list:' . $nonce . ':(\d+)-->(.*)<!--\/pg-list:' . $nonce . ':\1-->$/s', $html, $m), 'one span');
	preg_match_all('/<!--pg-lv:' . $nonce . ':' . $m[1] . ':(\d+)-->(.*?)<!--\/pg-lv:' . $nonce . ':' . $m[1] . ':\1-->/s', $m[2], $c);
	pg_assert_same(array('0', '1', '2'), $c[1], 'carriers in order');
	pg_assert_same(array('^^submitted_form_id^^', '^^title^^', '^^published^^%%d.m.Y%%'), $c[2], 'the key, then each parent token once');
	pg_assert_same($m[2], implode('', $c[0]), 'only carriers in the span');
	pg_assert_same(false, strpos($html, '<span'), 'no element');
}

// -- the pass ----------------------------------------------------------------------

// A field's values, one chip each, in stored order, each row with its own
// record's title and its position among the chips; a record with no value
// loses its pg-hide-if-empty box.
function test_widget_lists_values_end_to_end()
{
	$data = array(
		_widget_lists_data(1, 12, 'section', 'Yemek'),
		_widget_lists_data(1, 11, 'section', 'Fikirler'),
		_widget_lists_data(1, 13, 'title', 'First', 'text box'),
		_widget_lists_data(2, 14, 'title', 'Second', 'text box'),
	);
	$out = _widget_lists_finished(_widget_lists_card_tree(), array(1 => 'First', 2 => 'Second'), $data);
	_widget_lists_no_markers($out);
	pg_assert_same(array('Fikirler', 'Yemek'), _widget_lists_texts($out, 'val'), 'values in stored order');
	pg_assert_same(array('1', '2'), _widget_lists_texts($out, 'no'), 'row numbers');
	pg_assert_same(array('2', '2'), _widget_lists_texts($out, 'cnt'), 'row counts');
	pg_assert_same(array('First', 'First'), _widget_lists_texts($out, 'ttl'), 'the record title in each row');
	pg_assert_same(array('First', 'Second'), _widget_lists_texts($out, 'title'), 'both records');
	pg_assert_same(1, substr_count($out, 'class="box pg-hide-if-empty"'), 'the empty list\'s box goes');
	pg_assert_same(2, substr_count($out, 'class="card"'));

	// A page of pages: the second page numbers its chips from 1 too.
	$data2 = array(_widget_lists_data(3, 20, 'section', 'A'), _widget_lists_data(3, 21, 'section', 'B'), _widget_lists_data(3, 22, 'section', 'C'));
	$out2  = _widget_lists_finished(_widget_lists_card_tree(), array(3 => 'Third', 1 => 'First'), array_merge($data, $data2));
	pg_assert_same(array('A', 'B', 'C', 'Fikirler', 'Yemek'), _widget_lists_texts($out2, 'val'));
	pg_assert_same(array('1', '2', '3', '1', '2'), _widget_lists_texts($out2, 'no'));
	pg_assert_same(array('3', '3', '3', '2', '2'), _widget_lists_texts($out2, 'cnt'));
}

// Values are escaped once: the list's own through the token map, the
// record's as the renderer printed them.
function test_widget_lists_escapes_once()
{
	$data = array(
		_widget_lists_data(1, 1, 'section', 'A & <b>'),
		_widget_lists_data(1, 2, 'section', 'küçük yerler'),
	);
	$chip = array(
		_widget_lists_text('__value_text', 'val'),
		_widget_lists_text('__value_param', 'prm'),
		_widget_lists_text('title', 'ttl'),
		array('type' => 'semantic', 'props' => array('tag' => 'a', 'cssClass' => 'lnk', 'text' => 'Go', '_bindings' => array('href' => '__value_param')), 'children' => array()),
	);
	$out = _widget_lists_finished(_widget_lists_card_tree('form_record.values', 'section', array(), $chip), array(1 => 'Tom & Jerry'), $data);
	pg_assert_same(array('A &amp; &lt;b&gt;', 'küçük yerler'), _widget_lists_texts($out, 'val'));
	pg_assert_same(array('A%20%26%20%3Cb%3E', 'k%C3%BC%C3%A7%C3%BCk%20yerler'), _widget_lists_texts($out, 'prm'));
	pg_assert_same(array('Tom &amp; Jerry', 'Tom &amp; Jerry'), _widget_lists_texts($out, 'ttl'));
	pg_assert_contains('href="k%C3%BC%C3%A7%C3%BCk%20yerler"', $out);
	pg_assert_same(false, strpos($out, '&amp;amp;'), 'never twice');
}

// A field's text cut at commas, each part trimmed, empty parts dropped.
function test_widget_lists_split()
{
	$rows = pg_sw_list_form_records(array(
		_widget_lists_data(1, 1, 'tags', "küçük yerler, editörün mektubu,\xC2\xA0 , son", 'text box'),
		_widget_lists_data(2, 2, 'tags', 'tek', 'text box'),
		_widget_lists_data(3, 3, 'tags', ' , ', 'text box'),
	));
	$parts = pg_sw_list_value_rows($rows[1], 'tags', array(), true);
	pg_assert_same(array('küçük yerler', 'editörün mektubu', 'son'), array_column($parts, '__value_text'));
	pg_assert_same(array('k%C3%BC%C3%A7%C3%BCk%20yerler', 'edit%C3%B6r%C3%BCn%20mektubu', 'son'), array_column($parts, '__value_param'));
	pg_assert_same(array('tek'), array_column(pg_sw_list_value_rows($rows[2], 'tags', array(), true), '__value_text'), 'one value without commas');
	pg_assert_same(array(), pg_sw_list_value_rows($rows[3], 'tags', array(), true), 'only commas');
	pg_assert_same(array("küçük yerler, editörün mektubu,\xC2\xA0 , son"), array_column(pg_sw_list_value_rows($rows[1], 'tags', array(), false), '__value_text'), 'values: one row');

	$tree = _widget_lists_card_tree('form_record.split', 'TAGS');
	$out  = _widget_lists_finished($tree, array(1 => 'One', 2 => 'Two'), array(
		_widget_lists_data(1, 1, 'tags', 'küçük yerler, editörün mektubu', 'text box'),
		_widget_lists_data(2, 2, 'tags', 'tek', 'text box'),
	));
	pg_assert_same(array('küçük yerler', 'editörün mektubu', 'tek'), _widget_lists_texts($out, 'val'), 'the arg is matched in any case');
	pg_assert_same(array('1', '2', '1'), _widget_lists_texts($out, 'no'));
}

// A field's values read as the field's token shows them: a date in the
// field's display shape; a rich text area and a file upload give none.
function test_widget_lists_value_display()
{
	$rows = pg_sw_list_form_records(array(
		_widget_lists_data(1, 1, 'day', '2026-10-24', 'date'),
		_widget_lists_data(1, 2, 'body', '<p>x</p>', 'text area', array('field_wysiwyg' => '1')),
		_widget_lists_data(1, 3, 'cover', '', 'file upload', array('file_name' => 'c.png')),
		_widget_lists_data(1, 4, 'note', 'a < b', 'text area'),
		_widget_lists_data(1, 5, 'empty', '', 'text box'),
	));
	$shown = pg_sw_apply_tokens('^^day^^', array(), array('day' => array('data' => '2026-10-24', 'type' => 'date', 'wysiwyg' => 0)));
	pg_assert_same(array(html_entity_decode($shown, ENT_QUOTES, 'UTF-8')), array_column(pg_sw_list_value_rows($rows[1], 'day', array(), false), '__value_text'), 'a date as its token shows it');
	pg_assert_same(array('2026-10-24'), array_column(pg_sw_list_value_rows($rows[1], 'day', array(), false), '__value_param'), 'the stored value for an address');
	pg_assert_same(array(), pg_sw_list_value_rows($rows[1], 'body', array(), false), 'rich text');
	pg_assert_same(array(), pg_sw_list_value_rows($rows[1], 'cover', array(), false), 'a file upload');
	pg_assert_same(array('a < b'), array_column(pg_sw_list_value_rows($rows[1], 'note', array(), false), '__value_text'), 'plain text, escaped later');
	pg_assert_same(array(), pg_sw_list_value_rows($rows[1], 'empty', array(), false), 'no value, no row');
	pg_assert_same(array(), pg_sw_list_value_rows($rows[1], 'nope', array(), false), 'another field');
}

// The record's attachments: every upload field holding a file, office use
// fields only when named.
function test_widget_lists_files()
{
	$rows = pg_sw_list_form_records(array(
		_widget_lists_data(1, 3, 'photo', '', 'file upload', array('file_name' => 'b.jpg', 'field_label' => 'Photo')),
		_widget_lists_data(1, 2, 'cover', '', 'file upload', array('file_name' => 'A.PNG', 'field_label' => 'Cover image')),
		_widget_lists_data(1, 4, 'scan', '', 'file upload', array('file_name' => 'scan.pdf', 'field_office_use' => '1')),
		_widget_lists_data(1, 5, 'empty_upload', '', 'file upload'),
		_widget_lists_data(1, 6, 'section', 'x'),
	));
	$base = defined('OUTPUT_PATH') ? OUTPUT_PATH : '/';
	pg_assert_same(array(
		array('__file_name' => 'A.PNG', '__file_url' => $base . 'A.PNG', '__file_field' => 'Cover image', '__file_ext' => 'png'),
		array('__file_name' => 'b.jpg', '__file_url' => $base . 'b.jpg', '__file_field' => 'Photo', '__file_ext' => 'jpg'),
	), pg_sw_list_file_rows($rows[1], ''), 'no arg: every upload but the office one, in stored order');
	pg_assert_same(array('scan.pdf'), array_column(pg_sw_list_file_rows($rows[1], 'scan'), '__file_name'), 'the office field named');
	pg_assert_same(array('b.jpg'), array_column(pg_sw_list_file_rows($rows[1], 'Photo'), '__file_name'), 'one field');
	pg_assert_same(array(), pg_sw_list_file_rows($rows[1], 'section'), 'not an upload');
	pg_assert_same(array(), pg_sw_list_file_rows(array(), ''), 'a record without files');

	$chip = array(array('type' => 'semantic', 'props' => array('tag' => 'a', 'cssClass' => 'file', 'text' => 'x',
		'_bindings' => array('href' => '__file_url', 'text' => '__file_name')), 'children' => array()));
	$out = _widget_lists_finished(_widget_lists_card_tree('form_record.files', null, array(), $chip), array(1 => 'One', 2 => 'Two'),
		array(
			_widget_lists_data(1, 2, 'cover', '', 'file upload', array('file_name' => 'A.PNG')),
			_widget_lists_data(2, 9, 'section', 'x'),
		));
	pg_assert_contains('<a class="file" href="' . $base . 'A.PNG" data-pg-bind="href:file_url;text:file_name">A.PNG</a>', $out);
	pg_assert_same(1, substr_count($out, 'pg-hide-if-empty'), 'the record without files has no box');
	_widget_lists_no_markers($out);
}

function test_widget_lists_order_and_limit()
{
	$rows = array(
		array('__value_text' => 'İzmir', '__value_param' => '1'),
		array('__value_text' => 'ankara', '__value_param' => '2'),
		array('__value_text' => 'Bursa', '__value_param' => '3'),
		array('__value_text' => 'ankara', '__value_param' => '4'),
	);
	$params = function ($rows) { return array_column($rows, '__value_param'); };
	pg_assert_same(array('1', '2', '3', '4'), $params(pg_sw_list_order($rows, '', 0)), 'stored order');
	pg_assert_same(array('4', '3', '2', '1'), $params(pg_sw_list_order($rows, 'reverse', 0)), 'reverse');
	pg_assert_same(array('2', '4', '3', '1'), $params(pg_sw_list_order($rows, 'az', 0)), 'a to z, folded, equal values in stored order');
	pg_assert_same(array('1', '3', '2', '4'), $params(pg_sw_list_order($rows, 'za', 0)), 'z to a');
	pg_assert_same(array('2'), $params(pg_sw_list_order($rows, 'az', 1)), 'limit 1 after the order');
	pg_assert_same(array('item2', 'item10'), array_column(pg_sw_list_order(array(array('__file_name' => 'item10'), array('__file_name' => 'item2')), 'az', 0), '__file_name'), 'natural order');

	$tree = _widget_lists_card_tree('form_record.values', 'section', array('order' => 'za', 'limit' => 2));
	$data = array(_widget_lists_data(1, 1, 'section', 'B'), _widget_lists_data(1, 2, 'section', 'C'), _widget_lists_data(1, 3, 'section', 'A'));
	$out  = _widget_lists_finished($tree, array(1 => 'One'), $data);
	pg_assert_same(array('C', 'B'), _widget_lists_texts($out, 'val'));
	pg_assert_same(array('2', '2'), _widget_lists_texts($out, 'cnt'), 'the count after the limit');
}

// A list in a list's row numbers its rows among themselves, and every id
// stays its own.
function test_widget_lists_nested()
{
	$inner = _widget_lists_list('form_record.values', 'b', array(
		_widget_lists_box('i', 'in', array(), array('id' => 'x', 'text' => 'Sample', '_bindings' => array('text' => '__value_text'))),
		_widget_lists_text('__loop_index', 'ino'),
	));
	$outer = _widget_lists_list('form_record.values', 'a', array(
		_widget_lists_box('div', 'out', array(_widget_lists_text('__value_text', 'oval', 'b'), _widget_lists_text('__loop_index', 'ono'), $inner), array('id' => 'o')),
	));
	$tree = _widget_lists_root(array(array('type' => 'loop_area', 'props' => array(), 'children' => array(_widget_lists_box('section', 'rec', array($outer))))));
	$data = array(
		_widget_lists_data(1, 1, 'a', 'A1'), _widget_lists_data(1, 2, 'a', 'A2'),
		_widget_lists_data(1, 3, 'b', 'B1'), _widget_lists_data(1, 4, 'b', 'B2'), _widget_lists_data(1, 5, 'b', 'B3'),
		_widget_lists_data(2, 6, 'a', 'C1'), _widget_lists_data(2, 7, 'b', 'D1'),
	);
	$out = _widget_lists_finished($tree, array(1 => 'One', 2 => 'Two'), $data);
	_widget_lists_no_markers($out);
	pg_assert_same(array('A1', 'A2', 'C1'), _widget_lists_texts($out, 'oval'));
	pg_assert_same(array('1', '2', '1'), _widget_lists_texts($out, 'ono'));
	pg_assert_same(array('1', '2', '3', '1', '2', '3', '1'), _widget_lists_texts($out, 'ino'));
	preg_match_all('/\bid="([^"]+)"/', $out, $ids);
	pg_assert_same(10, count($ids[1]), 'ids printed');
	pg_assert_same(count($ids[1]), count(array_unique($ids[1])), 'every id distinct');
}

// A condition inside a list's row reads that row's values.
function test_widget_lists_conditions_in_rows()
{
	$first = _widget_lists_text('__value_text', 'val', 'span', array('_bindParams' => array('show_if' => array('op' => 'not_empty'))));
	$first['props']['_bindings']['show_if'] = '__loop_is_first';
	$out = _widget_lists_finished(_widget_lists_card_tree('form_record.values', 'section', array(), array($first)), array(1 => 'One'), array(
		_widget_lists_data(1, 1, 'section', 'Fikirler'), _widget_lists_data(1, 2, 'section', 'Yemek'),
	));
	pg_assert_same(array('Fikirler'), _widget_lists_texts($out, 'val'), 'the first row only');
	_widget_lists_no_markers($out);

	$eq = _widget_lists_text('__value_text', 'val', 'span', array('_bindParams' => array('show_if' => array('op' => 'eq', 'value' => 'yemek'))));
	$eq['props']['_bindings']['show_if'] = '__value_text';
	$out = _widget_lists_finished(_widget_lists_card_tree('form_record.values', 'section', array(), array($eq)), array(1 => 'One'), array(
		_widget_lists_data(1, 1, 'section', 'Fikirler'), _widget_lists_data(1, 2, 'section', 'Yemek'),
	));
	pg_assert_same(array('Yemek'), _widget_lists_texts($out, 'val'), 'the matching row only');
	pg_assert_same(2, substr_count($out, 'class="chip"'), 'the rows stay, their cut node goes');
}

// Spans of another request stay text; spans and carriers of this request
// that no template answers go.
function test_widget_lists_forged_and_stray()
{
	$nonce = pg_sw_marker_nonce();
	$scope = array('sid' => 31, 'kind' => 'form_list_view');
	$forged = '<!--pg-list:0000000000000000:1--><!--pg-lv:0000000000000000:1:0-->5<!--/pg-lv:0000000000000000:1:0--><!--/pg-list:0000000000000000:1-->';
	pg_assert_same('<p>' . $forged . '</p>', pg_sw_finish_widget_html('<p>' . $forged . '</p><!--pg-cut:' . $nonce . '-->', $scope), 'another nonce stays');

	$unregistered = '<!--pg-list:' . $nonce . ':987654--><!--pg-lv:' . $nonce . ':987654:0-->5<!--/pg-lv:' . $nonce . ':987654:0--><!--/pg-list:' . $nonce . ':987654-->';
	pg_assert_same('<p>a</p><p>b</p>', pg_sw_finish_widget_html('<p>a</p>' . $unregistered . '<p>b</p>', $scope), 'an unregistered span');
	pg_assert_same('<p>a</p><p>b</p>', pg_sw_finish_widget_html('<p>a</p><!--pg-lv:' . $nonce . ':4:0-->x<!--/pg-lv:' . $nonce . ':4:0--><p>b</p>', $scope), 'a stray carrier');
	pg_assert_same('<p>a</p><p>b</p>', pg_sw_finish_widget_html('<p>a</p><!--/pg-list:' . $nonce . ':4--><p>b</p><!--pg-list:' . $nonce . ':4-->', $scope), 'stray markers');

	// The renderer drew no record, so nothing was published.
	$out = _widget_lists_finished(_widget_lists_card_tree(), array(1 => 'One'), null);
	pg_assert_same(array(), _widget_lists_texts($out, 'val'), 'no rows');
	pg_assert_same(false, strpos($out, 'pg-hide-if-empty'), 'its box goes');
	_widget_lists_no_markers($out);

	// A list outside the record (in a list widget's surround) has no key.
	$tree = _widget_lists_card_tree();
	array_unshift($tree['children'], _widget_lists_box('ul', 'top pg-hide-if-empty', array(_widget_lists_list('form_record.values', 'section', array(_widget_lists_text('__value_text', 'val'))))));
	$out = _widget_lists_finished($tree, array(1 => 'One'), array(_widget_lists_data(1, 1, 'section', 'Yemek')));
	pg_assert_same(array('Yemek'), _widget_lists_texts($out, 'val'), 'only the record\'s list');
	pg_assert_same(false, strpos($out, 'class="top'), 'the surround\'s empty list goes');
}

function test_widget_lists_publish_and_take()
{
	$list = _widget_lists_list('form_record.values', 'section', array(_widget_lists_text('__value_text')));
	pg_sw_list_publish('form_record', array(_widget_lists_data(1, 1, 'section', 'x')));
	pg_assert_same(array(), pg_sw_list_take(array('sid' => 0)), 'outside a scope nothing is stored');

	pg_assert_same(array(), _widget_lists_in_scope('form_item_view', function ($scope) {
		pg_sw_list_publish('form_record', array(_widget_lists_data(1, 1, 'section', 'x')));
		return pg_sw_list_take($scope);
	}, 9002), 'no list registered for the widget');

	$taken = _widget_lists_in_scope('form_item_view', function ($scope) use ($list) {
		_render_tree_node($list, 0, 0);
		pg_sw_list_publish('form_record', array(_widget_lists_data(1, 1, 'section', 'old')));
		pg_sw_list_publish('form_record', array(_widget_lists_data(4, 7, 'section', 'x', 'check box', array('field_translate' => '0'))), array('translate' => true));
		return array(pg_sw_list_take($scope), pg_sw_list_take($scope));
	}, 9003);
	pg_assert_same(array('form_record' => array(
		'records' => array(4 => array(array('id' => 7, 'name' => 'section', 'bare' => 'section', 'label' => 'Section', 'type' => 'check box',
			'data' => 'x', 'file' => '', 'office' => 0, 'wysiwyg' => 0, 'translate' => 0))),
		'options' => array('translate' => true),
	)), $taken[0], 'the last publish replaces the first');
	pg_assert_same(array(), $taken[1], 'take forgets');
}

function test_widget_lists_form_records()
{
	$out = pg_sw_list_form_records(array(
		_widget_lists_data(2, 9, 'Pub-Date', '2026-10-24', 'date', array('field_label' => 'Published')),
		_widget_lists_data(1, 5, 'body', '<p>x</p>', 'text area', array('field_wysiwyg' => '1', 'field_translate' => '1')),
		_widget_lists_data(1, 3, '', 'no name'),
		_widget_lists_data(1, 4, 'cover', '', 'file upload', array('file_name' => 'c.png', 'field_office_use' => '1', 'field_wysiwyg' => '1')),
		'not a row',
	));
	pg_assert_same(array(1, 2), array_keys($out));
	pg_assert_same(array(4, 5), array_column($out[1], 'id'), 'sorted by data id, a row without a field name dropped');
	pg_assert_same(array('id' => 4, 'name' => 'cover', 'bare' => 'cover', 'label' => 'Cover', 'type' => 'file upload', 'data' => '',
		'file' => 'c.png', 'office' => 1, 'wysiwyg' => 0, 'translate' => 1), $out[1][0], 'wysiwyg only on a text area');
	pg_assert_same(1, $out[1][1]['wysiwyg']);
	pg_assert_same('pub-date', $out[2][0]['name']);
	pg_assert_same('pubdate', $out[2][0]['bare']);
	pg_assert_same(array(), pg_sw_list_form_records('x'));
}

// A record's pencil is put in by the renderer: a record that starts with a
// list gets it in the first element after the span, and the pass puts the
// rows in front of it. A record holding a condition still waits for the pass.
function test_widget_lists_edit_pencil()
{
	$list = _widget_lists_list('form_record.values', 'section', array(_widget_lists_text('__value_text', 'val')));
	$tree = _widget_lists_root(array(array('type' => 'loop_area', 'props' => array(), 'children' => array(
		$list,
		_widget_lists_box('div', 'card', array(_widget_lists_text('title', 'title', 'h3'))),
	))));
	$data = array(_widget_lists_data(1, 1, 'section', 'Yemek'));
	$pencil = function ($row) { return pg_sw_add_edit_chip($row, '/edit?id=1', 'Edit', 'forms'); };
	$r = _widget_lists_render($tree, array(1 => 'One'), $data, 'form_list_view', $pencil);
	pg_assert_same(false, strpos($r[0], '<!--pg-chip:'), 'put in at once');
	pg_assert_contains('<div class="card pg-edit-host"><a class="pg-edit-chip', $r[0], 'in the first element after the span');
	$out = pg_sw_finish_widget_html($r[0], $r[1]);
	pg_assert_true((bool)preg_match('#^<span class="val"[^>]*>Yemek</span>\s*<div class="card pg-edit-host"><a class="pg-edit-chip#', $out), 'the rows in front of it');

	$cond = _widget_lists_text('title', 'sub', 'p', array('_bindParams' => array('show_if' => array('op' => 'empty'))));
	$cond['props']['_bindings']['show_if'] = 'title';
	$tree['children'][0]['children'][] = $cond;
	$r = _widget_lists_render($tree, array(1 => 'One'), $data, 'form_list_view', $pencil);
	pg_assert_contains('<!--pg-chip:' . pg_sw_marker_nonce() . ':', $r[0], 'a record with a condition defers');
	$out = pg_sw_finish_widget_html($r[0], $r[1]);
	pg_assert_same(1, substr_count($out, 'class="pg-edit-chip'), 'one pencil');
	pg_assert_same(false, strpos($out, 'pg-chip:'));
	_widget_lists_no_markers($out);
}

// -- the hooks -----------------------------------------------------------------------

function test_widget_lists_hooks()
{
	$nonce = pg_sw_marker_nonce();
	pg_assert_true(pg_sw_has_content('<!--pg-list:' . $nonce . ':3--><!--/pg-list:' . $nonce . ':3-->'), 'a list span counts as content');
	pg_assert_false(pg_sw_has_content('<!--pg-list:0000000000000000:3-->'), 'a forged one does not');

	$list = _widget_lists_list('form_record.values', 'a', array(_widget_lists_text('__loop_index')));
	$main = array(_widget_lists_text('title'), $list);
	pg_assert_false(_widget_lists_in_scope('form_list_view', function () use ($main) {
		return pg_sw_finish_uses_positions($main);
	}), 'a list\'s position token does not mark the main rows');
	$main[] = _widget_lists_text('__loop_count');
	pg_assert_true(_widget_lists_in_scope('form_list_view', function () use ($main) {
		return pg_sw_finish_uses_positions($main);
	}), 'the main rows\' own token does');
	pg_assert_same(array(_widget_lists_text('title'), _widget_lists_list('form_record.values', 'a', array())),
		pg_sw_list_unsourced(array(_widget_lists_text('title'), $list)), 'a list\'s children emptied');

	// The no-record layout prunes the record template, not a list before it.
	$not_found = array('type' => 'content', 'props' => array('contentType' => 'paragraph', 'text' => 'x', '_bindings' => array('text' => '__not_found_message')), 'children' => array());
	$tree = _widget_lists_root(array(
		_widget_lists_list('form_record.values', 'a', array(_widget_lists_text('__value_text'))),
		array('type' => 'loop_area', 'props' => array(), 'children' => array(_widget_lists_text('title'), $not_found)),
	));
	pg_assert_true(pg_sw_prune_record_layout($tree));
	pg_assert_same(array($not_found), $tree['children'][1]['children'], 'the main loop pruned');
	pg_assert_same(array(_widget_lists_text('__value_text')), $tree['children'][0]['children'], 'the list as designed');

	// The canvas ghosts mark the main loop's children, never a list's.
	$ghosts = new ReflectionFunction('pg_designer_widget_ghosts');
	$lines  = file($ghosts->getFileName());
	$body   = implode('', array_slice($lines, $ghosts->getStartLine() - 1, $ghosts->getEndLine() - $ghosts->getStartLine() + 1));
	pg_assert_contains('pg_sw_list_is_sourced($child)', $body, 'the ghost marks skip lists');
}

// functions.php loads the module after the cart widgets, and the pass runs
// the lists between the positions and the conditions, having taken what
// was published before anything can return.
function test_widget_lists_wiring()
{
	$functions = (string)file_get_contents(PG_FUNCTIONS_DIR . '/functions.php');
	$cart  = strpos($functions, "/includes/fn/widgets_cart.php');");
	$lists = strpos($functions, "require_once(PG_FUNCTIONS_DIR . '/includes/fn/widgets_lists.php');");
	pg_assert_true($cart !== false && $lists !== false && $lists > $cart, 'required after widgets_cart.php');

	$fn    = new ReflectionFunction('pg_sw_finish_widget_html');
	$lines = file($fn->getFileName());
	$body  = implode('', array_slice($lines, $fn->getStartLine() - 1, $fn->getEndLine() - $fn->getStartLine() + 1));
	$take      = strpos($body, 'pg_sw_list_take($scope)');
	$return    = strpos($body, 'return');
	$positions = strpos($body, 'pg_sw_finish_positions(');
	$expand    = strpos($body, 'pg_sw_lists_expand(');
	$resolve   = strpos($body, 'pg_sw_finish_resolve(');
	pg_assert_true($take !== false && $take < $return, 'taken before the first return');
	pg_assert_true($positions !== false && $expand !== false && $resolve !== false && $positions < $expand && $expand < $resolve, 'positions, lists, conditions');
}

// A row token's format is read when the row is filled; a position token's
// is dropped; a record token keeps its format through its carrier; a row
// without a format fills as strtr() did.
function test_widget_lists_row_formats()
{
	$data = array(
		_widget_lists_data(1, 12, 'section', 'yemek & içecek'),
		_widget_lists_data(1, 11, 'section', 'istanbul'),
	);
	$chip = array(
		_widget_lists_text('__value_text', 'val', 'span', array('_bindFormats' => array('text' => 'upper'))),
		_widget_lists_text('__value_text', 'cut', 'span', array('_bindFormats' => array('text' => 'truncate:3'))),
		_widget_lists_text('__loop_index', 'no', 'span', array('_bindFormats' => array('text' => 'number:2'))),
		_widget_lists_text('title', 'ttl', 'span', array('_bindFormats' => array('text' => 'lower'))),
		_widget_lists_text('__value_text', 'raw'),
	);
	$out = _widget_lists_finished(_widget_lists_card_tree('form_record.values', 'section', array(), $chip), array(1 => 'First'), $data);
	_widget_lists_no_markers($out);
	pg_assert_same(array('ISTANBUL', 'YEMEK &amp; IÇECEK'), _widget_lists_texts($out, 'val'), 'a filter on a row token (not a Turkish page)');
	pg_assert_same(array('ist…', 'yem…'), _widget_lists_texts($out, 'cut'), 'a filter with a number');
	pg_assert_same(array('1', '2'), _widget_lists_texts($out, 'no'), 'a position token\'s format is dropped');
	pg_assert_same(array('first', 'first'), _widget_lists_texts($out, 'ttl'), 'a record token, formatted by the renderer');
	pg_assert_same(array('istanbul', 'yemek &amp; içecek'), _widget_lists_texts($out, 'raw'), 'no format');

	$map = array('^^a^^' => '^^b^^', '^^b^^' => 'B', '^^c^^%%upper%%' => 'C!');
	foreach (array('<i>^^a^^ ^^b^^ ^^x^^</i>', '^^c^^%%upper%% ^^b^^', '') as $html) {
		if (strpos($html, '^^%%') === false) {
			pg_assert_same(strtr($html, $map), pg_sw_lists_fill_row($html, $map), 'strtr: ' . $html);
		}
	}
	pg_assert_same('C! ^^b^^ b ^^x^^%%upper%%', pg_sw_lists_fill_row('^^c^^%%upper%% ^^a^^ ^^b^^%%lower%% ^^x^^%%upper%%', $map), 'one pass, carriers whole');
}
