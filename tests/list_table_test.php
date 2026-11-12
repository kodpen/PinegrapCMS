<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the list table driver's server core (includes/fn/list_table.php):
 * the column model, the source, keyset paging and its cursor, the rows
 * answer and the printed first rows, and the selection resolver's condition.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');

function pg_test_list_table_source($levels = array(), $extra = array())
{
	return pg_list_table_source(array_merge(array(
		'list' => 'test_list',
		'columns' => array(
			'name' => array('heading' => 'Name', 'sort' => 'item.name', 'width' => '20rem'),
			'price' => array('heading' => 'Price', 'kind' => 'money', 'sort' => 'item.price', 'editable' => 'money'),
			'notes' => array('heading' => 'Notes', 'hidden' => true),
		),
		'from' => 'item',
		'where' => 'item.site = ?',
		'params' => array(3),
		'id' => 'item.id',
		'id_order' => 'desc',
		'search' => array('columns' => array('item.name'), 'labels' => array()),
		'levels' => $levels,
		'created' => 'item.created',
		'params_out' => array('filter' => 'all'),
	), $extra));
}

function test_list_table_column_model()
{
	$column = pg_list_table_column('price', array('heading' => 'Price', 'kind' => 'money', 'sort' => 'item.price', 'width' => '8rem', 'editable' => 'money'));

	pg_assert_same('end', $column['align'], 'money leans to the end');
	pg_assert_true($column['sortable']);
	pg_assert_same('item.price', $column['sort']);
	pg_assert_same('8rem', $column['width']);
	pg_assert_same('money', $column['editable']);
	pg_assert_true($column['hideable']);

	$plain = pg_list_table_column('notes', array('heading' => 'Notes', 'kind' => 'nonsense', 'width' => '1rem; color: red', 'editable' => 'script', 'sortable' => true));

	pg_assert_same('text', $plain['kind'], 'unknown kind');
	pg_assert_same('', $plain['width'], 'width that is not a length');
	pg_assert_same('', $plain['editable'], 'unknown edit kind');
	pg_assert_false($plain['sortable'], 'no sort expression, not sortable');

	pg_assert_false(pg_list_table_column('price', array('sort' => 'x', 'sortable' => false))['sortable'], 'sorting turned off');
	pg_assert_same(null, pg_list_table_column('Bad-Key', array()), 'invalid key');
	pg_assert_same(null, pg_list_table_column('1st', array()), 'key starting with a digit');
}

function test_list_table_columns_first_is_never_hidden()
{
	$columns = pg_list_table_columns(array(
		'name' => array('heading' => 'Name', 'hidden' => true, 'hideable' => true),
		'name ' => array('heading' => 'Bad'),
		'notes' => array('heading' => 'Notes', 'hidden' => true),
	));

	pg_assert_same(array('name', 'notes'), array_keys($columns));
	pg_assert_false($columns['name']['hideable']);
	pg_assert_false($columns['name']['hidden']);
	pg_assert_true($columns['notes']['hidden']);
}

function test_list_table_client_columns_leave_out_sql()
{
	$source = pg_test_list_table_source();
	$client = pg_list_table_client_columns($source['columns']);

	pg_assert_same(3, count($client));

	foreach ($client as $column) {
		pg_assert_false(array_key_exists('sort', $column), $column['key']);
	}

	pg_assert_same(array('name' => 'Name', 'price' => 'Price'), pg_list_table_sort_columns($source['columns']));
}

function test_list_table_levels_keep_sortable_columns()
{
	$source = pg_test_list_table_source(array(
		array('sort' => 'price', 'order' => 'DESC'),
		array('sort' => 'notes', 'order' => 'asc'),
		array('sort' => 'price', 'order' => 'asc'),
		array('sort' => 'item.name; DROP', 'order' => 'asc'),
		array('sort' => 'name', 'order' => 'desc'),
	));

	pg_assert_same(array(array('sort' => 'price', 'order' => 'asc'), array('sort' => 'name', 'order' => 'desc')), $source['levels']);
	pg_assert_same('item.price asc, item.name desc, item.id desc', pg_list_table_order_sql($source));
	pg_assert_same('item.id desc', pg_list_table_order_sql(pg_test_list_table_source()), 'id alone');
}

function test_list_table_apply_sort()
{
	$source = pg_test_list_table_source(array(array('sort' => 'name', 'order' => 'asc')));

	pg_assert_same(array(array('sort' => 'price', 'order' => 'desc')), pg_list_table_apply_sort($source, 'price:desc')['levels']);
	pg_assert_same($source['levels'], pg_list_table_apply_sort($source, 'notes:asc')['levels'], 'not sortable: kept');
	pg_assert_same($source['levels'], pg_list_table_apply_sort($source, '')['levels'], 'no value: kept');
	pg_assert_same($source['levels'], pg_list_table_apply_sort($source, array('price'))['levels'], 'not a string');
}

function test_list_table_keyset_ascending_and_descending()
{
	$source = pg_test_list_table_source(array(array('sort' => 'price', 'order' => 'asc'), array('sort' => 'name', 'order' => 'desc')));
	$keyset = pg_list_table_keyset_where($source, array('values' => array('150', 'Bag'), 'id' => '42'));

	pg_assert_same(
		'((item.price > ?) OR (item.price = ? AND (item.name < ? OR item.name IS NULL)) OR (item.price = ? AND item.name = ? AND item.id < ?))',
		$keyset['sql']);
	pg_assert_same(array('150', '150', 'Bag', '150', 'Bag', '42'), $keyset['params']);
}

function test_list_table_keyset_null_values()
{
	// Ascending: NULL is the lowest value, so every non-NULL value is after it.
	$source = pg_test_list_table_source(array(array('sort' => 'price', 'order' => 'asc')));
	$keyset = pg_list_table_keyset_where($source, array('values' => array(null), 'id' => '7'));

	pg_assert_same('((item.price IS NOT NULL) OR (item.price IS NULL AND item.id < ?))', $keyset['sql']);
	pg_assert_same(array('7'), $keyset['params']);

	// Descending: NULL is the highest value, nothing is after it but the id.
	$source = pg_test_list_table_source(array(array('sort' => 'price', 'order' => 'desc')));
	$keyset = pg_list_table_keyset_where($source, array('values' => array(null), 'id' => '7'));

	pg_assert_same('((item.price IS NULL AND item.id < ?))', $keyset['sql']);
	pg_assert_same(array('7'), $keyset['params']);
}

function test_list_table_keyset_id_only_ascending()
{
	$source = pg_test_list_table_source(array(), array('id_order' => 'asc'));
	$keyset = pg_list_table_keyset_where($source, array('values' => array(), 'id' => '10'));

	pg_assert_same('((item.id > ?))', $keyset['sql']);
	pg_assert_same(array('10'), $keyset['params']);
}

function test_list_table_cursor_round_trip()
{
	$source = pg_test_list_table_source(array(array('sort' => 'price', 'order' => 'asc'), array('sort' => 'name', 'order' => 'desc')));
	$cursor = pg_list_table_cursor_encode($source, array(150, null), 42);

	pg_assert_true((bool) preg_match('/^[A-Za-z0-9_-]+$/', $cursor), 'url-safe');
	pg_assert_same(array('values' => array('150', null), 'id' => '42'), pg_list_table_cursor_decode($source, $cursor));
}

function test_list_table_cursor_refuses_other_sorts_and_junk()
{
	$source = pg_test_list_table_source(array(array('sort' => 'price', 'order' => 'asc')));
	$cursor = pg_list_table_cursor_encode($source, array('150'), 42);

	$resorted = pg_test_list_table_source(array(array('sort' => 'price', 'order' => 'desc')));
	pg_assert_same(null, pg_list_table_cursor_decode($resorted, $cursor), 'another direction');

	$encode = function ($data) {
		return rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');
	};

	pg_assert_same(null, pg_list_table_cursor_decode($source, $encode(array('s' => 'price:asc,#desc', 'v' => array(), 'i' => '1'))), 'too few values');
	pg_assert_same(null, pg_list_table_cursor_decode($source, $encode(array('s' => 'price:asc,#desc', 'v' => array(array('x')), 'i' => '1'))), 'value not text');
	pg_assert_same(null, pg_list_table_cursor_decode($source, $encode(array('s' => 'price:asc,#desc', 'v' => array('1'), 'i' => ''))), 'no id');
	pg_assert_same(null, pg_list_table_cursor_decode($source, 'not+base64/'), 'characters outside base64url');
	pg_assert_same(null, pg_list_table_cursor_decode($source, ''), 'empty');
	pg_assert_same(null, pg_list_table_cursor_decode($source, array()), 'not a string');
	pg_assert_same(null, pg_list_table_cursor_decode($source, 'abc'), 'not JSON');
}

function test_list_table_row_data_by_column_key()
{
	$source = pg_test_list_table_source();
	$row = array(
		'id' => 5,
		'class' => 'tone-muted',
		'cells' => array(
			'price' => pg_list_cell('1,50', 'align-middle', array('data-pg-edit' => 'price')),
			'name' => pg_list_cell('<a href="x">Bag</a>'),
			'stray' => pg_list_cell('dropped'),
		),
	);
	$data = pg_list_table_row_data($row, $source['columns']);

	pg_assert_same('5', $data['id']);
	pg_assert_same(array('name', 'price', 'notes'), array_keys($data['cells']), 'columns order, missing cell filled, stray dropped');
	pg_assert_same('', $data['cells']['notes']['html']);
	pg_assert_same(array('data-pg-edit' => 'price'), $data['cells']['price']['attrs']);
	pg_assert_false(isset($data['cells']['name']['attrs']));
}

function test_list_table_answer_counts_only_first_batch()
{
	$source = pg_test_list_table_source();
	$first = pg_list_table_answer(array('rows' => array(), 'next' => 'abc', 'total' => 250, 'restarted' => false), $source['columns']);

	pg_assert_same(array('status' => 'success', 'rows' => array(), 'next' => 'abc', 'total' => 250), $first);

	$later = pg_list_table_answer(array('rows' => array(), 'next' => null, 'total' => null, 'restarted' => false), $source['columns']);
	pg_assert_false(array_key_exists('total', $later));
	pg_assert_same(null, $later['next']);

	$restarted = pg_list_table_answer(array('rows' => array(), 'next' => null, 'total' => 3, 'restarted' => true), $source['columns']);
	pg_assert_true($restarted['restarted']);
}

function test_list_table_row_html()
{
	$source = pg_test_list_table_source();
	$html = pg_list_table_row_html(array(
		'id' => '9"',
		'cells' => array(
			'name' => pg_list_cell('Bag'),
			'price' => pg_list_cell('5', 'align-middle', array('data-pg-edit' => 'a"b', 'on click' => 'x')),
		),
	), $source['columns']);

	pg_assert_contains('<tr role="row" data-pg-id="9&quot;">', $html);
	pg_assert_contains('<td role="cell" data-pg-col="name" class="align-middle">Bag</td>', $html);
	pg_assert_contains('class="align-middle text-end" data-pg-edit="a&quot;b">5</td>', $html);
	pg_assert_false(strpos($html, 'on click') !== false, 'attribute name that is not one');
}

function test_list_table_html_first_batch()
{
	$source = pg_test_list_table_source(array(array('sort' => 'price', 'order' => 'desc')));
	$html = pg_list_table_html($source, array('rows' => array(array('id' => 1, 'cells' => array('name' => pg_list_cell('Bag')))), 'next' => 'CUR', 'total' => 120), 'Items');

	pg_assert_contains('data-pg-list-table="test_list"', $html);
	pg_assert_contains('data-pg-list-mode="server"', $html);
	pg_assert_contains('data-pg-list-next="CUR"', $html);
	pg_assert_contains('data-pg-list-total="120"', $html);
	pg_assert_contains('data-pg-list-sort="price:desc"', $html);
	pg_assert_contains('data-pg-list-params="{&quot;filter&quot;:&quot;all&quot;}"', $html);
	pg_assert_contains('role="table" aria-label="Items"', $html);
	pg_assert_contains('data-pg-col="price" class="pg-list-col-money text-end" data-pg-sortable="1" aria-sort="descending"', $html);
	pg_assert_contains('data-pg-col="name" class="pg-list-col-text" data-pg-sortable="1" style="width: 20rem"', $html);
	pg_assert_contains('data-pg-col="notes" class="pg-list-col-text" hidden>', $html);
	pg_assert_contains('<p class="pg-list-table-empty fg-secondary" hidden>', $html);
	pg_assert_contains('aria-live="polite"', $html);
	pg_assert_false(strpos($html, 'item.price') !== false, 'no SQL in the markup');
	pg_assert_false(strpos($html, 'item.site') !== false, 'no SQL condition in the markup');

	$browser = pg_list_table_html(pg_test_list_table_source(array(), array('all' => true)), array('rows' => array(), 'next' => null, 'total' => 0), 'Items');

	pg_assert_contains('data-pg-list-mode="browser"', $browser);
	pg_assert_contains('<p class="pg-list-table-empty fg-secondary">', $browser, 'empty line shown');
}

function test_list_table_selection_parse_ids()
{
	pg_assert_same(array('mode' => 'ids', 'ids' => array(3, 5)), pg_list_table_selection_parse(array('ids' => array(3, '5', 3, '0', -2, '1.5', '07', 'x', array(1))), 1000));
	pg_assert_same(array('mode' => 'ids', 'ids' => array()), pg_list_table_selection_parse(array('ids' => 'all'), 1000));
	pg_assert_same(null, pg_list_table_selection_parse(array('ids' => array(1, 2, 3)), 1000, 2), 'more ids than allowed');
	pg_assert_same(null, pg_list_table_selection_parse('ids', 1000));
	pg_assert_same(null, pg_list_table_selection_parse(array(), 1000));
}

function test_list_table_selection_parse_condition()
{
	$selection = pg_list_table_selection_parse(array(
		'condition' => array('search' => 'bag', 'params' => array('filter' => 'all')),
		'except' => array('4', 9),
		'as_of' => '1700000000',
	), 1800000000);

	pg_assert_same(array('mode' => 'condition', 'search' => 'bag', 'params' => array('filter' => 'all'), 'except' => array(4, 9), 'as_of' => 1700000000), $selection);

	$future = pg_list_table_selection_parse(array('condition' => array(), 'as_of' => 1900000000), 1800000000);
	pg_assert_same(1800000000, $future['as_of'], 'a moment in the future is now');
	pg_assert_same('', $future['search']);
	pg_assert_same(array(), $future['except']);

	pg_assert_same(null, pg_list_table_selection_parse(array('condition' => array()), 1800000000), 'no moment');
	pg_assert_same(null, pg_list_table_selection_parse(array('condition' => array(), 'as_of' => 0), 1800000000), 'zero moment');
	pg_assert_same(null, pg_list_table_selection_parse(array('condition' => 'all', 'as_of' => 5), 1800000000), 'condition not an object');
	pg_assert_same(null, pg_list_table_selection_parse(array('condition' => array(), 'except' => array(1, 2, 3), 'as_of' => 5), 1800000000, 2), 'too many exceptions');
}

function test_list_table_selection_where()
{
	$source = pg_test_list_table_source();

	$ids = pg_list_table_selection_where($source, array('mode' => 'ids', 'ids' => array(3, 5)));
	pg_assert_same(array('sql' => 'item.id IN (?, ?)', 'params' => array(3, 5)), $ids);

	pg_assert_same(array('sql' => '(1 = 0)', 'params' => array()), pg_list_table_selection_where($source, array('mode' => 'ids', 'ids' => array())), 'nothing picked');

	$all = pg_list_table_selection_where($source, array('mode' => 'condition', 'search' => '', 'params' => array(), 'except' => array(4), 'as_of' => 1700000000));
	pg_assert_same('item.id NOT IN (?) AND (item.created <= ?)', $all['sql']);
	pg_assert_same(array(4, 1700000000), $all['params']);

	$no_created = pg_test_list_table_source(array(), array('created' => ''));
	pg_assert_same(array('sql' => '(1 = 1)', 'params' => array()), pg_list_table_selection_where($no_created, array('mode' => 'condition', 'search' => '', 'params' => array(), 'except' => array(), 'as_of' => 1)));
}

function test_list_table_selection_where_search_in_english_panel()
{
	// The Turkish matching (pg_list_search_where()) needs a connection; an
	// explicit search column keeps the plain LIKE.
	$source = pg_test_list_table_source(array(), array('search' => array('columns' => array(array('expr' => 'item.name', 'term' => 'strval')), 'labels' => array())));
	$where = pg_list_table_selection_where($source, array('mode' => 'condition', 'search' => 'bag', 'params' => array(), 'except' => array(), 'as_of' => 9));

	pg_assert_same('(item.name LIKE ?) AND (item.created <= ?)', $where['sql']);
	pg_assert_same(array('%bag%', 9), $where['params']);
}

function test_list_table_definitions_lookup()
{
	pg_assert_same(null, pg_list_table_definition('no_such_list'));
	pg_assert_same(null, pg_list_table_definition(array('x')));

	foreach (pg_list_table_definitions() as $name => $definition) {
		pg_assert_true((bool) preg_match('/^[a-z][a-z0-9_]*$/', $name), $name);
		pg_assert_true(is_file(PG_FUNCTIONS_DIR . '/' . $definition['file']), $name . ' file');
		pg_assert_true(is_string($definition['source']) && ($definition['source'] !== ''), $name . ' source');
	}
}

function test_list_table_html_hidden_cells_texts_and_scroll_box()
{
	$source = pg_test_list_table_source();
	$html = pg_list_table_html($source, array('rows' => array(array('id' => 1, 'cells' => array('name' => pg_list_cell('Bag'), 'notes' => pg_list_cell('x')))), 'next' => null, 'total' => 1), 'Items');

	pg_assert_contains('<td role="cell" data-pg-col="notes" class="align-middle" hidden>x</td>', $html, 'a hidden column hides its cells');
	pg_assert_contains('<div class="pg-list-table-bar is-empty">', $html, 'the bar always shows its tools');
	pg_assert_contains('class="pg-icon-button pg-icon-button-sm pg-list-table-columns" data-bs-toggle="menu"', $html);
	pg_assert_contains('<div class="menu pg-menu-end pg-list-table-columns-menu"></div>', $html);
	pg_assert_contains('pg-list-table-details-switch" aria-pressed="false">', $html);
	pg_assert_contains('<aside class="pg-list-table-details" aria-label="', $html);
	pg_assert_contains('<div class="pg-list-table-body"><div class="pg-list-table-scroll"', $html, 'the rows and the details panel side by side');
	pg_assert_contains(' data-pg-list-user="', $html);
	pg_assert_contains('<div class="pg-list-table-scroll" tabindex="-1"><table', $html);
	pg_assert_contains('<div class="pg-list-table-foot"></div></div>', $html);
	pg_assert_contains('data-pg-list-texts="', $html);

	$texts = pg_list_table_texts();

	foreach (array('more', 'loading', 'failed', 'retry', 'loaded', 'selected', 'all', 'clear', 'undo', 'sort') as $key) {
		pg_assert_true(isset($texts[$key]) && ($texts[$key] !== ''), $key);
	}

	pg_assert_contains('%n', $texts['selected']);
	pg_assert_contains('%n', $texts['loaded']);
}

function test_list_table_html_puts_the_screens_actions_in_the_bar()
{
	$html = pg_list_table_html(pg_test_list_table_source(), array('rows' => array(), 'next' => null, 'total' => 0), 'Items', '<button type="button">Delete</button>');

	pg_assert_contains('<span class="pg-list-table-actions"><button type="button">Delete</button></span>', $html);
	pg_assert_contains('<span class="pg-list-table-actions"></span>', pg_list_table_html(pg_test_list_table_source(), array('rows' => array(), 'next' => null, 'total' => 0), 'Items'));
}

function test_list_table_edit_spec_needs_an_editable_column()
{
	$edit = array('url' => 'view_items.php', 'columns' => array('price' => array('type' => 'money', 'label' => 'Price')));

	pg_assert_same($edit, pg_test_list_table_source(array(), array('edit' => $edit))['edit']);
	pg_assert_same(null, pg_test_list_table_source()['edit'], 'no quick edit named');
	pg_assert_same(null, pg_test_list_table_source(array(), array('edit' => array('url' => '', 'columns' => $edit['columns'])))['edit'], 'no address');
	pg_assert_same(null, pg_test_list_table_source(array(), array('edit' => array('url' => 'view_items.php', 'columns' => array())))['edit'], 'no field');
	pg_assert_same(null, pg_test_list_table_source(array(), array(
		'columns' => array('name' => array('heading' => 'Name')),
		'edit' => $edit,
	))['edit'], 'no column of the list is editable');
}

function test_list_table_html_quick_edit_button_and_fields()
{
	$edit = array('url' => 'view_items.php', 'columns' => array('price' => array('type' => 'money', 'label' => 'Price')));
	$page = array('rows' => array(), 'next' => null, 'total' => 0);
	$html = pg_list_table_html(pg_test_list_table_source(array(), array('edit' => $edit)), $page, 'Items');

	pg_assert_contains(' data-pg-edit-url="view_items.php"', $html);
	pg_assert_contains(' data-pg-edit-columns="{&quot;price&quot;:{&quot;type&quot;:&quot;money&quot;,&quot;label&quot;:&quot;Price&quot;}}"', $html);
	pg_assert_contains('class="pg-icon-button pg-icon-button-sm pg-list-table-quick" aria-pressed="false" aria-keyshortcuts="F2"', $html);

	$plain = pg_list_table_html(pg_test_list_table_source(), $page, 'Items');

	pg_assert_same(false, strpos($plain, 'data-pg-edit-url') !== false, 'no fields without quick edit');
	pg_assert_same(false, strpos($plain, 'pg-list-table-quick') !== false, 'no button without quick edit');
}
