<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for includes/record_files.php: whether a page built in the visual
 * editor shows a submitted form's uploaded file to a visitor, which
 * get_file.php asks after its classic checks refuse the file. The decision
 * (pg_record_file_view_grants()), the binding and page tree walks, the
 * filter SQL and its fail-closed cases, the copies held to their originals
 * in includes/fn/, the file's call contract and the get_file.php call site.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/record_files.php');

// escape() asks the connection class; without a database it falls back to
// addslashes() once the class exists.
function _record_files_db_stub()
{
	if (!class_exists('db')) {
		class db
		{
			public static $con;
		}
	}
}

function _record_files_fields()
{
	_record_files_db_stub();
	return array(
		'status'  => array('id' => '41', 'name' => 'status', 'type' => 'pick list', 'multiple' => '0'),
		'tags'    => array('id' => '42', 'name' => 'tags', 'type' => 'pick list', 'multiple' => '1'),
		'day'     => array('id' => '43', 'name' => 'day', 'type' => 'date', 'multiple' => '0'),
		'when'    => array('id' => '44', 'name' => 'when', 'type' => 'date and time', 'multiple' => '0'),
		'cover'   => array('id' => '45', 'name' => 'cover', 'type' => 'file upload', 'multiple' => '0'),
		'agree'   => array('id' => '46', 'name' => 'agree', 'type' => 'check box', 'multiple' => '0'),
	);
}

function _record_files_published()
{
	return array('field' => 'status', 'operator' => 'is equal to', 'value' => 'Published', 'dynamic_value' => '', 'dynamic_value_attribute' => '');
}

// A record view printing the cover through an image binding.
function _record_files_tree()
{
	return array('type' => 'div', 'props' => array(), 'children' => array(
		array('type' => 'loop_area', 'props' => array(), 'children' => array(
			array('type' => 'image', 'props' => array('_bindings' => array('src' => 'cover', 'alt' => 'title'))),
			array('type' => 'heading', 'props' => array('_bindings' => array('text' => 'title'))),
		)),
	));
}

function _record_files_facts($override = array())
{
	$facts = array(
		'cfg'            => array('regionType' => 'form_item_view', 'custom_form_page_id' => 12, 'access_control' => 'public'),
		'tree'           => _record_files_tree(),
		'form_page_id'   => 12,
		'form_page_name' => 'articles',
		'identifiers'    => pg_record_file_field_identifiers('cover', 'Cover photo'),
		'pages'          => array(array('binned' => false, 'draft' => false, 'viewable' => true)),
		'filters'        => 'pass',
	);
	foreach ($override as $key => $value) {
		$facts[$key] = $value;
	}
	return $facts;
}

// -- the decision ----------------------------------------------------------------

// A public record view of the form, printing the field, on a page the
// visitor may view, for a record that passes its filters: the file opens.
function test_record_files_grants_public_bound_view()
{
	pg_assert_true(pg_record_file_view_grants(_record_files_facts()));

	$list = array('regionType' => 'form_list_view', 'custom_form_page_id' => 12, 'detail_page_id' => 7);
	pg_assert_true(pg_record_file_view_grants(_record_files_facts(array('cfg' => $list))), 'a public list');

	foreach (array('submitted_forms_list', 'form_list', 'blog_list') as $older) {
		pg_assert_true(pg_record_file_view_grants(_record_files_facts(array('cfg' => array('regionType' => $older, 'custom_form_page_id' => 12)))), $older);
	}

	// A record view keeps a list's viewer filter inert.
	$view = array('regionType' => 'form_item_view', 'form_page_id' => 12, 'viewer_filter' => true);
	pg_assert_true(pg_record_file_view_grants(_record_files_facts(array('cfg' => $view))));

	$named = array('regionType' => 'form_item_view', 'source_page' => 'Articles');
	pg_assert_true(pg_record_file_view_grants(_record_files_facts(array('cfg' => $named))), 'the form named by its page name');

	// An attachments list naming no field prints an upload field not kept
	// for office use: the decision reads that from the facts.
	$attachments = array('type' => 'div', 'children' => array(
		array('type' => 'loop_area', 'props' => array('source' => 'form_record.files'), 'children' => array(
			array('type' => 'a', 'props' => array('_bindings' => array('href' => '__file_url'))),
		)),
	));
	pg_assert_true(pg_record_file_view_grants(_record_files_facts(array('tree' => $attachments, 'listed' => true))), 'a listed upload');
	pg_assert_false(pg_record_file_view_grants(_record_files_facts(array('tree' => $attachments, 'listed' => false))), 'an office use upload');
	pg_assert_false(pg_record_file_view_grants(_record_files_facts(array('tree' => $attachments))), 'not said to be listed');
}

// Access other than public, a viewer filtered list, another form or another
// widget type: closed.
function test_record_files_refuses_private_or_other_widgets()
{
	foreach (array('submitter_only', 'logged_in', 'members') as $access) {
		$cfg = array('regionType' => 'form_item_view', 'custom_form_page_id' => 12, 'access_control' => $access);
		pg_assert_false(pg_record_file_view_grants(_record_files_facts(array('cfg' => $cfg))), $access);
	}

	$viewer = array('regionType' => 'form_list_view', 'custom_form_page_id' => 12, 'viewer_filter' => 1);
	pg_assert_false(pg_record_file_view_grants(_record_files_facts(array('cfg' => $viewer))), 'a viewer filtered list');

	$other = array('regionType' => 'form_item_view', 'custom_form_page_id' => 13);
	pg_assert_false(pg_record_file_view_grants(_record_files_facts(array('cfg' => $other))), 'another form');

	$catalog = array('regionType' => 'catalog_item_view', 'custom_form_page_id' => 12);
	pg_assert_false(pg_record_file_view_grants(_record_files_facts(array('cfg' => $catalog))), 'not a form widget');

	pg_assert_false(pg_record_file_view_grants(_record_files_facts(array('cfg' => null))));
	pg_assert_false(pg_record_file_view_grants(null));
}

// A tree that does not print the field keeps the file closed.
function test_record_files_refuses_without_binding()
{
	$tree = array('type' => 'div', 'children' => array(
		array('type' => 'heading', 'props' => array('_bindings' => array('text' => 'title'))),
	));
	pg_assert_false(pg_record_file_view_grants(_record_files_facts(array('tree' => $tree))));
	pg_assert_false(pg_record_file_view_grants(_record_files_facts(array('tree' => ''))));
	pg_assert_false(pg_record_file_view_grants(_record_files_facts(array('identifiers' => array()))));
}

// A record the filters keep out, or filters that cannot be applied: closed.
function test_record_files_refuses_filtered_out_records()
{
	pg_assert_false(pg_record_file_view_grants(_record_files_facts(array('filters' => 'fail'))));
	pg_assert_false(pg_record_file_view_grants(_record_files_facts(array('filters' => 'closed'))));
	pg_assert_false(pg_record_file_view_grants(_record_files_facts(array('filters' => true))));
	$facts = _record_files_facts();
	unset($facts['filters']);
	pg_assert_false(pg_record_file_view_grants($facts));
}

// A page in the recycle bin or among the drafts, or one the visitor may not
// view, does not count; another page carrying the widget does.
function test_record_files_pages()
{
	$binned  = array('binned' => true, 'draft' => false, 'viewable' => true);
	$draft   = array('binned' => false, 'draft' => true, 'viewable' => true);
	$private = array('binned' => false, 'draft' => false, 'viewable' => false);
	$open    = array('binned' => false, 'draft' => false, 'viewable' => true);

	pg_assert_false(pg_record_file_view_grants(_record_files_facts(array('pages' => array($binned)))), 'binned');
	pg_assert_false(pg_record_file_view_grants(_record_files_facts(array('pages' => array($draft)))), 'draft');
	pg_assert_false(pg_record_file_view_grants(_record_files_facts(array('pages' => array($private)))), 'no folder access');
	pg_assert_false(pg_record_file_view_grants(_record_files_facts(array('pages' => array()))), 'on no page');
	pg_assert_true(pg_record_file_view_grants(_record_files_facts(array('pages' => array($binned, $private, $open)))), 'one open page');
}

// -- what a tree prints ----------------------------------------------------------

function test_record_files_field_identifiers()
{
	pg_assert_same(array('cover' => true, 'cover photo' => true), pg_record_file_field_identifiers('cover', 'Cover Photo'));
	pg_assert_same(array('cover-image' => true, 'coverimage' => true), pg_record_file_field_identifiers('Cover-Image'));
	pg_assert_same(array(), pg_record_file_field_identifiers('', ''));
}

function test_record_files_tree_binds()
{
	$ids = pg_record_file_field_identifiers('cover', 'Cover photo');

	pg_assert_true(pg_record_file_tree_binds(_record_files_tree(), $ids), 'an image source binding');
	pg_assert_true(pg_record_file_tree_binds(json_encode(_record_files_tree()), $ids), 'the stored JSON');

	foreach (array('text', 'html', 'src', 'alt', 'href', 'value') as $prop) {
		$tree = array('type' => 'a', 'props' => array('_bindings' => array($prop => 'Cover')));
		pg_assert_true(pg_record_file_tree_binds($tree, $ids), $prop);
	}
	foreach (array('section', 'action', 'eo_visible_if', 'visible_if') as $prop) {
		$tree = array('type' => 'div', 'props' => array('_bindings' => array($prop => 'cover')));
		pg_assert_false(pg_record_file_tree_binds($tree, $ids), $prop . ' prints no value');
	}

	$html = array('type' => 'text', 'props' => array('html' => '<p><a href="^^cover^^">Download</a></p>'));
	pg_assert_true(pg_record_file_tree_binds($html, $ids), 'a token in rich text');
	$label = array('type' => 'text', 'props' => array('text' => 'File: ^^ Cover Photo ^^%%d.m.Y%%'));
	pg_assert_true(pg_record_file_tree_binds($label, $ids), 'a token by label');
	$attr = array('type' => 'div', 'props' => array('_attrs' => array(array('name' => 'style', 'value' => 'background:url(^^cover^^)'))));
	pg_assert_true(pg_record_file_tree_binds($attr, $ids), 'a token in an attribute');

	$other = array('type' => 'text', 'props' => array('text' => '^^cover_caption^^', '_bindings' => array('src' => 'coverx')));
	pg_assert_false(pg_record_file_tree_binds($other, $ids), 'another field');
	pg_assert_false(pg_record_file_tree_binds(array('type' => 'div'), $ids));
	pg_assert_false(pg_record_file_tree_binds('not json', $ids));
	pg_assert_false(pg_record_file_tree_binds(_record_files_tree(), array()));

	// A binding keeps letters, digits, underscores and hyphens.
	$hyphen = array('type' => 'img', 'props' => array('_bindings' => array('src' => 'cover-image')));
	pg_assert_true(pg_record_file_tree_binds($hyphen, pg_record_file_field_identifiers('cover-image')));

	// An attachments list prints the field it names, or, naming none, every
	// upload field not kept for office use ($listed). A list of a field's
	// values prints no file.
	$files = function ($arg) {
		$props = array('source' => 'form_record.files');
		if ($arg !== null) $props['sourceArg'] = $arg;
		return array('type' => 'div', 'children' => array(array('type' => 'loop_area', 'props' => $props, 'children' => array(
			array('type' => 'a', 'props' => array('_bindings' => array('href' => '__file_url', 'text' => '__file_name'))),
		))));
	};
	pg_assert_true(pg_record_file_tree_binds($files('cover'), $ids), 'a files list naming the field');
	pg_assert_true(pg_record_file_tree_binds($files('Co\"ver'), $ids), 'a files list naming it, sanitised');
	pg_assert_true(pg_record_file_tree_binds(json_encode($files('COVER')), $ids), 'in any case, stored');
	pg_assert_false(pg_record_file_tree_binds($files('photo'), $ids), 'a files list naming another field');
	pg_assert_false(pg_record_file_tree_binds($files('photo'), $ids, true), 'another field, listed or not');
	pg_assert_false(pg_record_file_tree_binds($files(null), $ids), 'no arg: not a listed field');
	pg_assert_true(pg_record_file_tree_binds($files(null), $ids, true), 'no arg: a listed field');
	pg_assert_true(pg_record_file_tree_binds($files(''), $ids, true), 'an empty arg is none');
	$values = array('type' => 'loop_area', 'props' => array('source' => 'form_record.values', 'sourceArg' => 'cover'), 'children' => array());
	pg_assert_false(pg_record_file_tree_binds($values, $ids, true), 'a values list naming the field');
	$list = array('regionType' => 'form_list_view', 'custom_form_page_id' => 12, 'access_control' => 'public');
	$surround = array('type' => 'root', 'children' => array($files(null), array('type' => 'loop_area', 'children' => array(array('type' => 'p')))));
	pg_assert_false(pg_record_file_widget_binds($list, $surround, $ids, true), 'a list\'s surround still prints no record');
	$in_loop = array('type' => 'root', 'children' => array(array('type' => 'loop_area', 'children' => array($files(null)))));
	pg_assert_true(pg_record_file_widget_binds($list, $in_loop, $ids, true), 'in the record template, listed');
	pg_assert_false(pg_record_file_widget_binds($list, $in_loop, $ids), 'in the record template, not listed');
	pg_assert_true(pg_record_file_widget_binds($list, $files(null), $ids, true), 'a list with no main loop draws its whole tree per record');
}

// The page tree walk finds the shared components a page places, in tree
// order, and does not look inside them.
function test_record_files_tree_shared_ids()
{
	$tree = array('type' => 'body', 'children' => array(
		array('type' => 'section', 'children' => array(
			array('type' => 'shared_ref', 'props' => array('sharedId' => 9), 'children' => array(
				array('type' => 'shared_ref', 'props' => array('sharedId' => 99)),
			)),
			array('type' => 'shared_ref', 'props' => array('sharedId' => '4')),
		)),
		array('type' => 'shared_ref', 'props' => array('sharedId' => 9)),
		array('type' => 'shared_ref', 'props' => array('sharedId' => 0)),
		array('type' => 'shared_ref', 'props' => array('sharedId' => 5)),
	));
	pg_assert_same(array(9, 4, 5), pg_record_file_tree_shared_ids($tree));
	pg_assert_same(array(9, 4, 5), pg_record_file_tree_shared_ids(json_encode($tree)));
	pg_assert_same(array(), pg_record_file_tree_shared_ids('{'));
}

// The loop_area search finds what _split_widget_tree() cuts out.
function test_record_files_loop_children_matches_split()
{
	$image = array('type' => 'image', 'props' => array('_bindings' => array('src' => 'cover')));
	$trees = array(
		_record_files_tree(),
		array('type' => 'div', 'children' => array($image)),
		// The first in depth-first order wins over a shallower later one.
		array('type' => 'root', 'children' => array(
			array('type' => 'div', 'children' => array(
				array('type' => 'loop_area', 'children' => array(array('type' => 'heading'))),
			)),
			array('type' => 'loop_area', 'children' => array($image)),
		)),
		array('type' => 'root', 'children' => array(array('type' => 'loop_area'))),
		array('type' => 'loop_area', 'children' => array($image)),
		array('type' => 'root', 'children' => array('text', array('type' => 'loop_area', 'children' => array($image)))),
		// A list (a loop_area with props.source) is never the main loop, and
		// a loop_area inside one is not either.
		array('type' => 'root', 'children' => array(
			array('type' => 'loop_area', 'props' => array('source' => 'form_record.files'), 'children' => array(
				array('type' => 'loop_area', 'children' => array(array('type' => 'heading'))),
			)),
			array('type' => 'div', 'children' => array(array('type' => 'loop_area', 'props' => array(), 'children' => array($image)))),
		)),
		array('type' => 'root', 'children' => array(
			array('type' => 'loop_area', 'props' => array('source' => 'form_record.values', 'sourceArg' => 'a'), 'children' => array($image)),
		)),
		array('type' => 'root', 'children' => array(
			array('type' => 'loop_area', 'children' => array(
				$image,
				array('type' => 'loop_area', 'props' => array('source' => 'form_record.split', 'sourceArg' => 'tags'), 'children' => array($image)),
			)),
		)),
		array('type' => 'root', 'children' => array(
			array('type' => 'loop_area', 'props' => array('source' => ''), 'children' => array($image)),
		)),
	);
	foreach ($trees as $i => $tree) {
		$split = _split_widget_tree($tree);
		pg_assert_same($split['loop_children'], pg_record_file_loop_children($tree), 'tree ' . $i);
		pg_assert_same($split['loop_children'], pg_record_file_loop_children(json_encode($tree)), 'json ' . $i);
	}
	pg_assert_same(null, pg_record_file_loop_children('{'));
}

// A list prints a record's values only in its loop_area; its surround is
// drawn once with no record. A record view fills its surround too.
function test_record_files_list_surround_does_not_print()
{
	$ids  = pg_record_file_field_identifiers('idscan');
	$list = array('regionType' => 'form_list_view', 'custom_form_page_id' => 12, 'access_control' => 'public');
	$view = array('regionType' => 'form_item_view', 'custom_form_page_id' => 12, 'access_control' => 'public');
	$surround = array('type' => 'div', 'children' => array(
		array('type' => 'image', 'props' => array('_bindings' => array('src' => 'idscan'))),
		array('type' => 'paragraph', 'props' => array('text' => 'Scan: ^^idscan^^')),
		array('type' => 'loop_area', 'props' => array('_bindings' => array('src' => 'idscan')), 'children' => array(
			array('type' => 'image', 'props' => array('_bindings' => array('src' => 'cover'))),
		)),
	));
	pg_assert_false(pg_record_file_widget_binds($list, $surround, $ids), 'a list surround');
	pg_assert_false(pg_record_file_widget_binds($list, json_encode($surround), $ids), 'a list surround, stored');
	foreach (array('submitted_forms_list', 'form_list', 'blog_list') as $older) {
		pg_assert_false(pg_record_file_widget_binds(array('regionType' => $older) + $list, $surround, $ids), $older);
	}
	pg_assert_true(pg_record_file_widget_binds($list, $surround, pg_record_file_field_identifiers('cover')), 'in the loop');
	pg_assert_true(pg_record_file_widget_binds($view, $surround, $ids), 'a record view surround');

	$facts = _record_files_facts(array('cfg' => $list, 'tree' => $surround, 'identifiers' => $ids));
	pg_assert_false(pg_record_file_view_grants($facts), 'the decision');
	$facts['cfg'] = $view;
	pg_assert_true(pg_record_file_view_grants($facts));

	// A list without a loop_area renders its whole tree per record.
	$flat = array('type' => 'div', 'children' => array(
		array('type' => 'image', 'props' => array('_bindings' => array('src' => 'idscan'))),
	));
	pg_assert_true(pg_record_file_widget_binds($list, $flat, $ids), 'no loop_area');
	// An empty loop_area prints nothing.
	$empty = array('type' => 'div', 'children' => array(
		array('type' => 'image', 'props' => array('_bindings' => array('src' => 'idscan'))),
		array('type' => 'loop_area', 'children' => array()),
	));
	pg_assert_false(pg_record_file_widget_binds($list, $empty, $ids), 'an empty loop');
}

// -- the filters -----------------------------------------------------------------

function test_record_files_filter_sql_applies_form_fields()
{
	$fields = _record_files_fields();

	$none = pg_record_file_filter_sql(array(), $fields, true);
	pg_assert_same(array('where' => '', 'closed' => false), $none);
	pg_assert_same(array('where' => '', 'closed' => false), pg_record_file_filter_sql(null, $fields));

	$sql = pg_record_file_filter_sql(array(_record_files_published()), $fields, true);
	pg_assert_false($sql['closed']);
	pg_assert_same(" AND (IFNULL((SELECT form_data.data FROM form_data WHERE (form_data.form_id = forms.id) AND (form_data.form_field_id = '41') LIMIT 1), '') = 'Published')", $sql['where']);

	// The field name compares without case; a blank row and a row that is
	// not an array are skipped.
	$rows = array(array('field' => ''), 'status', array('field' => 'STATUS', 'operator' => 'is not equal to', 'value' => 'Draft'));
	$sql  = pg_record_file_filter_sql($rows, $fields);
	pg_assert_contains("!= 'Draft'", $sql['where']);
	pg_assert_contains("form_field_id = '41'", $sql['where']);

	$multi = pg_record_file_filter_sql(array(array('field' => 'tags', 'operator' => 'is equal to', 'value' => 'News')), $fields);
	pg_assert_same(" AND (IFNULL((SELECT COUNT(*) FROM form_data WHERE (form_data.form_id = forms.id) AND (form_data.form_field_id = '42') AND (form_data.data = 'News')), '') > 0)", $multi['where']);

	$box = pg_record_file_filter_sql(array(array('field' => 'agree', 'operator' => 'contains', 'value' => 'Yes')), $fields);
	pg_assert_contains("GROUP_CONCAT(form_data.data ORDER BY form_data.id ASC SEPARATOR ', ')", $box['where']);

	$file = pg_record_file_filter_sql(array(array('field' => 'cover', 'operator' => 'is not equal to', 'value' => '')), $fields);
	pg_assert_contains('(SELECT files.name FROM form_data LEFT JOIN files ON form_data.file_id = files.id', $file['where']);

	$when = pg_record_file_filter_sql(array(array('field' => 'when', 'operator' => 'is less than', 'value' => '2026-01-02T10:30')), $fields);
	pg_assert_contains("< '2026-01-02 10:30:00'", $when['where']);

	$today = pg_record_file_filter_sql(array(array('field' => 'day', 'operator' => 'is greater than or equal to', 'value' => '', 'dynamic_value' => 'current date')), $fields);
	pg_assert_contains(">= '" . date('Y-m-d') . "'", $today['where']);

	$quote = pg_record_file_filter_sql(array(array('field' => 'status', 'operator' => 'is equal to', 'value' => "O'Brien")), $fields);
	pg_assert_contains("= 'O\\'Brien'", $quote['where'], 'the value is escaped');
}

// What the page closes on, and what this file cannot build, keeps the file
// closed: never an open answer.
function test_record_files_filter_sql_fails_closed()
{
	$fields = _record_files_fields();
	$closed = array('where' => ' AND (0 = 1)', 'closed' => true);

	pg_assert_same($closed, pg_record_file_filter_sql(array(array('field' => 'status', 'operator' => 'eşittir', 'value' => 'x')), $fields), 'an unknown operator');
	pg_assert_same($closed, pg_record_file_filter_sql(array(array('field' => 'state', 'operator' => 'is equal to', 'value' => 'x')), $fields), 'a missing field');
	pg_assert_same($closed, pg_record_file_filter_sql(array(array('field' => array('status'), 'operator' => 'is equal to', 'value' => 'x')), $fields), 'a field that is not text');
	pg_assert_same($closed, pg_record_file_filter_sql(array(array('field' => 'status', 'operator' => 'is equal to', 'value' => array('x'))), $fields), 'a value that is not text');
	pg_assert_same($closed, pg_record_file_filter_sql(array(array('field' => 'submitted_date_and_time', 'operator' => 'is greater than', 'value' => '', 'dynamic_value' => 'days ago', 'dynamic_value_attribute' => 30)), $fields), 'a standard field');
	pg_assert_same($closed, pg_record_file_filter_sql(array(array('field' => 'status', 'operator' => 'is equal to', 'value' => '', 'dynamic_value' => 'viewer')), $fields), 'the viewer');
	pg_assert_same($closed, pg_record_file_filter_sql(array(array('field' => 'status', 'operator' => 'is equal to', 'value' => '', 'dynamic_value' => 'viewers email address')), $fields), 'the viewer\'s address');
	pg_assert_same($closed, pg_record_file_filter_sql(array(array('field' => 'day', 'operator' => 'is equal to', 'value' => '31.12.2025')), $fields), 'a date in the site format');
	pg_assert_same($closed, pg_record_file_filter_sql(array(_record_files_published()), array()), 'no fields read');

	// Fifty filters apply; the fifty-first closes.
	$fifty = array_fill(0, 50, _record_files_published());
	pg_assert_false(pg_record_file_filter_sql($fifty, $fields)['closed']);
	$fifty[] = _record_files_published();
	pg_assert_true(pg_record_file_filter_sql($fifty, $fields)['closed']);

	// A value from the address: a list leaves it out, a record view closes;
	// a fixed value wins over it.
	$address = array('field' => 'status', 'operator' => 'is equal to', 'value' => '', 'dynamic_value' => 'url parameter', 'dynamic_value_attribute' => 'state');
	pg_assert_same(array('where' => '', 'closed' => false), pg_record_file_filter_sql(array($address), $fields, false));
	pg_assert_same($closed, pg_record_file_filter_sql(array($address), $fields, true));
	$address['value'] = 'Published';
	pg_assert_contains("= 'Published'", pg_record_file_filter_sql(array($address), $fields, true)['where']);
}

// A list shows only complete records, so its question always carries
// forms.complete = 1, with filters or without; a record view shows an
// incomplete record by its address, and with no filter asks nothing.
function test_record_files_filter_count_sql_lists_complete_records()
{
	$none   = array('where' => '', 'closed' => false);
	$status = array('where' => " AND (IFNULL((x), '') = 'Published')", 'closed' => false);

	pg_assert_same('', pg_record_file_filter_count_sql(false, $none, 12, 7), 'a record view with no filter');

	foreach (array($none, $status) as $clause) {
		$list = pg_record_file_filter_count_sql(true, $clause, 12, 7);
		pg_assert_contains('AND forms.complete = 1', $list, 'a list: ' . $clause['where']);
		pg_assert_contains("forms.id = '7'", $list);
		pg_assert_contains("forms.page_id = '12'", $list);
		pg_assert_contains($clause['where'], $list);
	}

	$view = pg_record_file_filter_count_sql(false, $status, 12, 7);
	pg_assert_contains("= 'Published'", $view);
	pg_assert_same(false, strpos($view, 'complete'), 'a record view shows incomplete records');

	// The same rule as the list's own query.
	$widgets = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets.php');
	pg_assert_contains('AND forms.complete = 1', $widgets);
}

// The pages come before the filter verdict, which is asked only for a widget
// on a page the visitor may view: the pages lists name and the home page
// first, then one scan of every page tree for the widgets left.
function test_record_files_access_asks_pages_before_filters()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/record_files.php');
	$start  = strpos($source, 'function pg_record_file_designed_access(');
	$body   = substr($source, $start, strpos($source, "\nfunction ", $start + 10) - $start);
	$pages  = strpos($body, 'pg_record_file_widget_pages(');
	$open   = strpos($body, 'pg_record_file_open_page(');
	$filter = strpos($body, 'pg_record_file_filter_verdict(');
	pg_assert_true($pages !== false && $open !== false && $filter !== false && $pages < $open && $open < $filter);
	pg_assert_same(1, substr_count($body, 'pg_record_file_filter_verdict('), 'one verdict call');
	pg_assert_same(1, substr_count($body, 'pg_record_file_widget_pages('), 'one page lookup call');
	pg_assert_contains('foreach (array(pg_record_file_unique_ids($known), null) as $page_ids) {', $body);
	pg_assert_contains('pg_record_file_widget_binds($facts[\'cfg\'], $facts[\'tree\'], $identifiers, $listed)', $body);
	pg_assert_contains('\'listed\'         => $listed,', $body, 'the facts carry it to the decision');
	// An attachments list naming no field prints an upload field not kept
	// for office use, read with the field's label.
	pg_assert_contains('SELECT page.page_name, form_fields.label, form_fields.type, form_fields.office_use_only', $body);
	pg_assert_contains("(string)\$form[0]['type'] === 'file upload' && (string)\$form[0]['office_use_only'] === '0'", $body);
	pg_assert_same(false, strpos($body, 'pg_record_file_tree_binds('), 'bindings go through the widget rule');
}

// The page query: by id with the home page, or one LIKE per component over
// every tree; the exact pair only as the columns.
function test_record_files_pages_sql()
{
	pg_assert_same('', pg_record_file_pages_sql(array(), 'T'));
	pg_assert_same('', pg_record_file_pages_sql(array(0, -3), 'T', array(5)));

	$scan = pg_record_file_pages_sql(array(12, 7), 'T');
	$where = substr($scan, strpos($scan, 'WHERE'));
	pg_assert_same("WHERE T LIKE '%\"sharedId\":12%' OR T LIKE '%\"sharedId\":7%'", $where);
	pg_assert_contains("(T LIKE '%\"sharedId\":12,%' OR T LIKE '%\"sharedId\":12}%') AS s12", $scan);
	pg_assert_contains("(T LIKE '%\"sharedId\":7,%' OR T LIKE '%\"sharedId\":7}%') AS s7", $scan);

	$known = pg_record_file_pages_sql(array(12), 'T', array(40, '41', 40, 0));
	pg_assert_contains("WHERE page.page_id IN (40,41) OR page.page_home = 'yes'", $known);
	pg_assert_same(false, strpos(substr($known, strpos($known, 'WHERE')), 'LIKE'), 'no scan by id');
	$home = pg_record_file_pages_sql(array(12), 'T', array());
	pg_assert_contains("WHERE page.page_home = 'yes'", $home);
	pg_assert_same(array(4, 9), pg_record_file_unique_ids(array(4, '9', 4, 0, -1)));
}

// A page that is open: not binned, not a draft, viewable.
function test_record_files_open_page()
{
	pg_assert_true(pg_record_file_open_page(array(array('binned' => false, 'draft' => false, 'viewable' => true))));
	pg_assert_false(pg_record_file_open_page(array(array('binned' => true, 'draft' => false, 'viewable' => true))));
	pg_assert_false(pg_record_file_open_page(array(array('binned' => false, 'draft' => true, 'viewable' => true))));
	pg_assert_false(pg_record_file_open_page(array(array('binned' => false, 'draft' => false, 'viewable' => false), 'junk')));
	pg_assert_false(pg_record_file_open_page(null));
}

// The list's records: its detail view's filters first, then its own.
function test_record_files_merge_filters_matches_widgets()
{
	$view = array('filters' => array(_record_files_published(), 'junk'));
	$list = array('filters' => array(array('field' => 'tags', 'operator' => 'contains', 'value' => 'a')));
	$cases = array(
		array($list, $view), array($list, array()), array(array(), $view), array(null, null),
		array(array('filters' => 'x'), array('filters' => 'y')),
	);
	foreach ($cases as $i => $case) {
		pg_assert_same(pg_sw_merge_record_filters($case[0], $case[1]), pg_record_file_merge_filters($case[0], $case[1]), 'case ' . $i);
	}
}

// -- the copies stay with their originals ------------------------------------

function test_record_files_widget_public_matches_widgets()
{
	$configs = array(
		array(), null, 'x', array('access_control' => 'public'), array('access_control' => ''),
		array('access_control' => 'logged_in'), array('access_control' => 'submitter_only'),
		array('viewer_filter' => true), array('viewer_filter' => 0), array('viewer_filter' => '1'),
		array('regionType' => 'form_item_view', 'viewer_filter' => true),
		array('regionType' => 'form_item_view', 'access_control' => 'logged_in'),
		array('regionType' => 'form_list_view', 'access_control' => 'public', 'viewer_filter' => false),
		array('regionType' => 'blog_list', 'viewer_filter' => 1),
	);
	foreach ($configs as $i => $cfg) {
		pg_assert_same(pg_sw_form_widget_public($cfg), pg_record_file_widget_public($cfg), 'config ' . $i);
	}
}

function test_record_files_sql_operation_matches_forms()
{
	_record_files_db_stub();
	$operators = array(
		'contains', 'does not contain', 'is equal to', 'is not equal to', 'is less than',
		'is less than or equal to', 'is greater than', 'is greater than or equal to', 'unknown',
	);
	$operands = array('Published', '5', '0', '2026-01-02', "O'Brien 50%_off", '', '1.5');
	foreach ($operators as $operator) {
		foreach ($operands as $operand) {
			pg_assert_same(
				prepare_sql_operation($operator, '(x)', $operand),
				pg_record_file_sql_operation($operator, '(x)', $operand),
				$operator . ' ' . $operand
			);
		}
	}
}

// Every standard field closes the filter here, and a form widget type the
// renderer knows is a type this file looks for.
function test_record_files_standard_names_cover_widgets()
{
	$names = pg_record_file_standard_names();
	foreach (array_keys(pg_sw_standard_fields()) as $name) {
		pg_assert_true(in_array($name, $names, true), $name);
	}
	pg_assert_same(array('form_item_view', 'form_list_view', 'submitted_forms_list', 'form_list', 'blog_list'), array_keys(pg_record_file_widget_types()));
}

// The operator and dynamic value lists, and the SQL each field type is read
// with, are the ones pg_sw_form_list_filter_sql() uses.
function test_record_files_filter_sql_matches_widgets_source()
{
	$widgets = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets.php');
	$start   = strpos($widgets, 'function pg_sw_form_list_filter_sql(');
	$end     = strpos($widgets, "\nfunction ", $start + 10);
	$builder = substr($widgets, $start, $end - $start);
	$copy    = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/record_files.php');
	$start   = strpos($copy, 'function pg_record_file_filter_sql(');
	$end     = strpos($copy, "\nfunction ", $start + 10);
	$copy    = substr($copy, $start, $end - $start);

	foreach (array('$operators = array(', '$dynamic_values = array(') as $list) {
		pg_assert_same(_record_files_list($builder, $list), _record_files_list($copy, $list), $list);
	}

	preg_match_all('/\$operand_1 = "(\(SELECT [^"]*)"/', $builder, $original);
	preg_match_all('/\$operand_1 = "(\(SELECT [^"]*)"/', $copy, $ours);
	pg_assert_same(4, count($ours[1]));
	foreach ($ours[1] as $sql) {
		pg_assert_true(in_array($sql, $original[1], true), $sql);
	}
	// The pick list / check box count, whose value is escaped with e() there.
	pg_assert_contains("AND (form_data.data = '\" . e(\$value) . \"'))\";", $builder);
	pg_assert_contains("AND (form_data.data = '\" . escape(\$value) . \"'))\";", $copy);
}

function _record_files_list($source, $marker)
{
	$at = strpos($source, $marker);
	if ($at === false) {
		return array();
	}
	$body = substr($source, $at, strpos($source, ');', $at) - $at);
	preg_match_all("/'([^']*)'/", $body, $matches);
	return $matches[1];
}

// -- the contract and the call site ----------------------------------------------

// get_file.php has no functions.php: the file calls only itself, escape(),
// the folder checks of includes/authentication.php, get_file.php's
// initialize_timezone() behind function_exists(), and PHP.
function test_record_files_calls_only_its_contract()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/record_files.php');
	$tokens = token_get_all($source);
	$own = array();
	$calls = array();
	$count = count($tokens);
	for ($i = 0; $i < $count; $i++) {
		if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_STRING) {
			continue;
		}
		$prev = $i - 1;
		while ($prev >= 0 && is_array($tokens[$prev]) && $tokens[$prev][0] === T_WHITESPACE) {
			$prev--;
		}
		$next = $i + 1;
		while ($next < $count && is_array($tokens[$next]) && $tokens[$next][0] === T_WHITESPACE) {
			$next++;
		}
		$before = ($prev >= 0 && is_array($tokens[$prev])) ? $tokens[$prev][0] : ($prev >= 0 ? $tokens[$prev] : '');
		if ($before === T_FUNCTION) {
			$own[strtolower($tokens[$i][1])] = true;
			continue;
		}
		if ($next < $count && $tokens[$next] === '(' && !in_array($before, array(T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_NEW), true)) {
			$calls[strtolower($tokens[$i][1])] = true;
		}
	}
	$allowed = array('escape', 'check_view_access', 'get_access_control_type', 'pg_auth_column_exists', 'initialize_timezone');
	$foreign = array();
	foreach (array_keys($calls) as $name) {
		if (isset($own[$name]) || in_array($name, $allowed, true)) {
			continue;
		}
		if (function_exists($name)) {
			$reflection = new ReflectionFunction($name);
			if ($reflection->isInternal()) {
				continue;
			}
		}
		$foreign[] = $name;
	}
	pg_assert_same(array(), $foreign, 'functions outside the contract');
	pg_assert_contains("if (function_exists('initialize_timezone')) initialize_timezone();", $source);
	pg_assert_contains("if (!defined('PG_FUNCTIONS_DIR') && !defined('PG_GET_FILE')) {", $source);
}

// get_file.php asks after the classic list and item view checks, in the
// submitted form branch, before the refusal; it loads the file there, and
// still never loads functions.php.
function test_record_files_get_file_call_site()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_file.php');

	$classic = strpos($source, "AND (form_item_view_pages.collection = 'a')");
	$gate    = strpos($source, "define('PG_GET_FILE', true);", (int) $classic);
	$load    = strpos($source, "require_once(dirname(__FILE__) . '/includes/record_files.php');");
	$call    = strpos($source, '$attachment_access = pg_record_file_designed_access(');
	$deny    = strpos($source, '// If the visitor does not have access to the attachment, then deal with that.');
	$comment = strpos($source, 'FROM comments');

	pg_assert_true($classic !== false && $gate !== false && $load !== false && $call !== false && $deny !== false);
	pg_assert_true($classic < $gate && $gate < $load && $load < $call && $call < $deny, 'after the classic checks, before the refusal');
	pg_assert_true($comment === false || $comment < $classic, 'the comment branch comes first and is left alone');

	$block = substr($source, (int) $call - 400, 700);
	pg_assert_contains('if ($attachment_access == false) {', $block);
	pg_assert_contains("\$submitted_form['custom_form_page_id'],", $block);
	pg_assert_contains("\$submitted_form['id'],", $block);
	pg_assert_contains("\$submitted_form['attachment_field_name']", $block);

	// A file opened here is never kept by a shared cache.
	$set = strpos($source, '$designed_attachment = $attachment_access;');
	pg_assert_true($set !== false && $call < $set && $set < $deny, 'set right after the call');
	$flag    = strpos($source, '$designed_attachment = false;');
	$private = strpos($source, "if (\$designed_attachment) {\n    \$cache_control_type = 'private';\n}");
	$header  = strpos($source, "header('Cache-Control: ' . (\$erp_document");
	pg_assert_true($flag !== false && $flag < $call, 'the flag starts false');
	pg_assert_true($private !== false && $header !== false && $call < $private && $private < $header, 'private before the header');

	pg_assert_same(1, substr_count($source, 'pg_record_file_designed_access('));
	pg_assert_same(0, preg_match('/^\s*(require|include)(_once)?\b[^;\n]*functions\.php/m', $source), 'get_file.php does not load functions.php');
}

// -- the media field, and a visitor who is not signed in -------------------------

// The form's RSS media field counts as printed by every public view of the
// record: the decision reads that from the facts, and the rest of the rule
// (a public widget, an open page, the filters) still holds.
function test_record_files_media_field_counts_as_shown()
{
	$tree = array('type' => 'div', 'children' => array(
		array('type' => 'heading', 'props' => array('_bindings' => array('text' => 'title'))),
	));
	pg_assert_false(pg_record_file_view_grants(_record_files_facts(array('tree' => $tree))), 'not printed');
	pg_assert_false(pg_record_file_view_grants(_record_files_facts(array('tree' => $tree, 'media' => false))), 'not the media field');
	pg_assert_true(pg_record_file_view_grants(_record_files_facts(array('tree' => $tree, 'media' => true))), 'the media field');
	pg_assert_true(pg_record_file_view_grants(_record_files_facts(array('tree' => '', 'media' => true))), 'no tree needed');

	$list = array('regionType' => 'form_list_view', 'custom_form_page_id' => 12, 'detail_page_id' => 7);
	pg_assert_true(pg_record_file_view_grants(_record_files_facts(array('tree' => $tree, 'media' => true, 'cfg' => $list))), 'a public list');

	$private = array('regionType' => 'form_item_view', 'custom_form_page_id' => 12, 'access_control' => 'logged_in');
	pg_assert_false(pg_record_file_view_grants(_record_files_facts(array('tree' => $tree, 'media' => true, 'cfg' => $private))), 'a private view');
	$viewer = array('regionType' => 'form_list_view', 'custom_form_page_id' => 12, 'viewer_filter' => 1);
	pg_assert_false(pg_record_file_view_grants(_record_files_facts(array('tree' => $tree, 'media' => true, 'cfg' => $viewer))), 'a viewer filtered list');
	pg_assert_false(pg_record_file_view_grants(_record_files_facts(array('tree' => $tree, 'media' => true, 'filters' => 'fail'))), 'a filtered-out record');
	pg_assert_false(pg_record_file_view_grants(_record_files_facts(array('tree' => $tree, 'media' => true, 'pages' => array()))), 'on no page');
	$closed = array('binned' => false, 'draft' => false, 'viewable' => false);
	pg_assert_false(pg_record_file_view_grants(_record_files_facts(array('tree' => $tree, 'media' => true, 'pages' => array($closed)))), 'on a closed page');
}

// The classic layout rule: the field's ^^variable^^ in the layout, without
// case, the name quoted; or the field being the media field.
function test_record_files_layout_prints()
{
	pg_assert_true(pg_record_file_layout_prints('<img src="{path}^^cover^^">', 'cover'));
	pg_assert_true(pg_record_file_layout_prints('^^Cover^^', 'cover'), 'without case');
	pg_assert_false(pg_record_file_layout_prints('^^cover_photo^^ ^^title^^', 'cover'), 'another field');
	pg_assert_true(pg_record_file_layout_prints('^^a.b^^', 'a.b'), 'the name quoted');
	pg_assert_false(pg_record_file_layout_prints('^^axb^^', 'a.b'), 'the name quoted');
	pg_assert_false(pg_record_file_layout_prints('^^^^', ''), 'no name');
	pg_assert_true(pg_record_file_layout_prints('^^title^^', 'cover', true), 'the media field');
	pg_assert_true(pg_record_file_layout_prints('', 'cover', true), 'the media field in an empty layout');
	pg_assert_false(pg_record_file_layout_prints('', 'cover', false));
}

// Whether a classic list view, or the item view it names, shows the file to
// a visitor who is not signed in: the rule of get_file.php for such a
// visitor over the view's facts.
function test_record_files_classic_view_shows()
{
	$list = array('layout' => '<h1>^^title^^</h1><img src="^^cover^^">', 'folder_public' => true, 'viewer_filter' => 0, 'item' => null);
	pg_assert_true(pg_record_file_classic_view_shows($list, 'cover'), 'a public list printing the field');
	pg_assert_false(pg_record_file_classic_view_shows($list, 'photo'), 'not printed, no item view');
	pg_assert_true(pg_record_file_classic_view_shows($list, 'photo', true), 'the media field');
	pg_assert_false(pg_record_file_classic_view_shows(array_merge($list, array('folder_public' => false)), 'cover'), 'a folder that is not public');
	pg_assert_false(pg_record_file_classic_view_shows(array_merge($list, array('viewer_filter' => 1)), 'cover'), 'a viewer filter needs a signed-in visitor');
	pg_assert_false(pg_record_file_classic_view_shows(array_merge($list, array('viewer_filter' => '1')), 'cover', true), 'the media field too');
	pg_assert_false(pg_record_file_classic_view_shows(null, 'cover'));
	pg_assert_false(pg_record_file_classic_view_shows(array(), 'cover'));

	$item = array('layout' => '<img src="^^cover^^">', 'folder_public' => true, 'submitter_security' => 0);
	$named = array_merge($list, array('layout' => '^^title^^', 'item' => $item));
	pg_assert_true(pg_record_file_classic_view_shows($named, 'cover'), 'the item view printing the field');
	pg_assert_true(pg_record_file_classic_view_shows(array_merge($named, array('item' => array_merge($item, array('layout' => '')))), 'cover', true), 'the media field on the item view');
	pg_assert_false(pg_record_file_classic_view_shows(array_merge($named, array('item' => array_merge($item, array('layout' => '^^title^^')))), 'cover'), 'the item view not printing it');
	pg_assert_false(pg_record_file_classic_view_shows(array_merge($named, array('item' => array_merge($item, array('folder_public' => false)))), 'cover'), 'the item view in a folder that is not public');
	pg_assert_false(pg_record_file_classic_view_shows(array_merge($named, array('item' => array_merge($item, array('submitter_security' => 1)))), 'cover'), 'submitter security needs a signed-in visitor');
	pg_assert_false(pg_record_file_classic_view_shows(array_merge($named, array('folder_public' => false, 'item' => array_merge($item, array('submitter_security' => '1')))), 'cover', true), 'the media field too');

	// As in get_file.php: a list that prints the field in a public folder
	// decides by its viewer filter and never falls back to its item view; a
	// list that does not print it, or sits in a closed folder, does.
	pg_assert_false(pg_record_file_classic_view_shows(array_merge($list, array('viewer_filter' => 1, 'item' => $item)), 'cover'), 'no fallback past the viewer filter');
	pg_assert_true(pg_record_file_classic_view_shows(array_merge($list, array('folder_public' => false, 'item' => $item)), 'cover'), 'a closed list, an open item view');
	pg_assert_true(pg_record_file_classic_view_shows(array_merge($list, array('layout' => '', 'item' => $item)), 'cover'), 'a list not printing it, an open item view');
}

// The media rule sits in both layout checks of get_file.php, with the same
// variable pattern the copy uses, and the field is read with its RSS role
// on both sides; the anonymous rule reads the rows get_file.php reads.
function test_record_files_classic_rule_matches_get_file()
{
	$get_file = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_file.php');
	$copy     = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/record_files.php');

	pg_assert_contains("form_fields.rss_field AS attachment_field_rss_field", $get_file);
	pg_assert_contains("\$attachment_field_media = ((string) \$submitted_form['attachment_field_rss_field'] === 'media');", $get_file);
	$pattern = "(\$attachment_field_media || (preg_match('/\^\^' . preg_quote(\$submitted_form['attachment_field_name'], '/') . '\^\^/i', ";
	pg_assert_same(2, substr_count($get_file, $pattern), 'the list view and the item view layout checks');
	pg_assert_same(2, substr_count($get_file, "preg_match('/\^\^' . preg_quote(\$submitted_form['attachment_field_name'], '/') . '\^\^/i', "), 'no other layout check');
	$media = strpos($get_file, '$attachment_field_media = ');
	$loop  = strpos($get_file, 'foreach ($form_list_views as $form_list_view) {');
	pg_assert_true($media !== false && $loop !== false && $media < $loop, 'read before the loop');

	pg_assert_contains("preg_match('/\^\^' . preg_quote(\$field_name, '/') . '\^\^/i', (string)\$layout) === 1", $copy);
	pg_assert_contains("WHERE (page.page_type = 'form list view')", $copy);
	pg_assert_contains("AND (form_list_view_pages.custom_form_page_id = '", $copy);
	pg_assert_contains("AND (form_item_view_pages.collection = 'a')", $copy);
	pg_assert_contains("form_fields.rss_field", $copy);
}

// pg_record_file_designed_access() reads the field's RSS role with the
// label, carries it to the decision, and skips the tree walk for the media
// field; for a visitor who is not signed in the pages are judged by their
// folder's own setting.
function test_record_files_designed_access_carries_media()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/record_files.php');
	$start  = strpos($source, 'function pg_record_file_designed_access(');
	$body   = substr($source, $start, strpos($source, "\nfunction ", $start + 10) - $start);
	pg_assert_contains('function pg_record_file_designed_access($custom_form_page_id, $form_id, $field_name, $anonymous = false)', $body);
	pg_assert_contains('SELECT page.page_name, form_fields.label, form_fields.type, form_fields.office_use_only, form_fields.rss_field', $body);
	pg_assert_contains("\$media = isset(\$form[0]['rss_field']) && (string)\$form[0]['rss_field'] === 'media';", $body);
	pg_assert_contains("'media'          => \$media,", $body, 'the facts carry it to the decision');
	pg_assert_contains('if (!$media) {', $body, 'no tree walk for the media field');
	pg_assert_contains('pg_record_file_widget_pages(array_keys($rest), $page_ids, $anonymous)', $body);

	$start = strpos($source, 'function pg_record_file_widget_pages(');
	$body  = substr($source, $start, strpos($source, "\nfunction ", $start + 10) - $start);
	pg_assert_contains('$anonymous ? pg_record_file_folder_public($folder) : (check_view_access($folder) == true)', $body);
}

// The answer for a visitor who is not signed in: the folder first, then
// the attachment flag, a comment attachment refused, the record's classic
// views, then the designed widgets. Without a database nothing can be read,
// and a file that cannot be read stays closed, so the share image and the
// feed fall back.
function test_record_files_anonymous_access_fails_closed()
{
	_record_files_db_stub();
	pg_assert_false(pg_record_file_anonymous_access(0));
	pg_assert_false(pg_record_file_anonymous_access(301));
	pg_assert_false(pg_record_file_classic_anonymous_access(12, 'cover', true));
	pg_assert_false(pg_record_file_designed_access(12, 7, 'cover', true));

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/record_files.php');
	$start  = strpos($source, 'function pg_record_file_anonymous_access(');
	$end    = strpos($source, "\nfunction ", $start + 10);
	$body   = substr($source, $start, (($end === false) ? strlen($source) : $end) - $start);
	$folder   = strpos($body, 'pg_record_file_folder_public(');
	$flag     = strpos($body, "(int)\$file[0]['attachment'] !== 1");
	$comment  = strpos($body, 'FROM comments');
	$record   = strpos($body, 'FROM form_data');
	$classic  = strpos($body, 'pg_record_file_classic_anonymous_access(');
	$designed = strpos($body, 'pg_record_file_designed_access(');
	pg_assert_true($folder !== false && $flag !== false && $comment !== false && $record !== false && $classic !== false && $designed !== false);
	pg_assert_true($folder < $flag && $flag < $comment && $comment < $record && $record < $classic && $classic < $designed, 'in that order');
	pg_assert_contains('pg_record_file_designed_access($form_page_id, $form_id, $field_name, true)', $body, 'for a visitor who is not signed in');
}
