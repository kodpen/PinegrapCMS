<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the visual editor's form lists failing closed: a filter that
 * names a field the form no longer has, or an operator outside the list,
 * shows no record (pg_sw_filter_sql_closed()) on the list, its feeds, the
 * sitemap and the search index, as a record view does; a filter valued from
 * the page address keeps its meaning on a list; and the editor's filter rows
 * say why the list is empty.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// e() asks the connection class; without a database it falls back to
// addslashes() once the class exists.
function _list_closed_db_stub()
{
	if (!class_exists('db')) {
		class db
		{
			public static $con;
		}
	}
}

// pg_sw_form_list_filter_sql()'s answer with the given counts.
function _list_closed_sql($missing, $invalid, $address)
{
	return array('where' => '', 'tokens' => array(), 'missing' => $missing, 'invalid' => $invalid,
		'params' => array(), 'inexact' => false, 'address' => $address, 'exact' => '');
}

// A filter on the reference code (a standard field: no form field lookup).
function _list_closed_reference_row($operator = 'is equal to')
{
	return array('field' => 'reference_code', 'operator' => $operator, 'value' => 'R1', 'dynamic_value' => '', 'dynamic_value_attribute' => '');
}

// The same field valued from ?author=.
function _list_closed_address_row($operator = 'is equal to')
{
	return array('field' => 'reference_code', 'operator' => $operator, 'value' => '', 'dynamic_value' => 'url parameter', 'dynamic_value_attribute' => 'author');
}

// The text of a function, from its declaration to the next one.
function _list_closed_function_body($source, $declaration)
{
	$start = strpos($source, $declaration);
	pg_assert_true($start !== false, $declaration . ' is found');
	$end = strpos($source, "\nfunction ", $start + strlen($declaration));
	return substr($source, $start, ($end === false ? strlen($source) : $end) - $start);
}

// -- the decision ----------------------------------------------------------------

// A field the form lacks or an unknown operator closes a list and a record
// view alike; a filter valued from the address closes only the record view.
function test_list_closed_decision()
{
	pg_assert_false(pg_sw_filter_sql_closed(_list_closed_sql(0, 0, 0)));
	pg_assert_false(pg_sw_filter_sql_closed(_list_closed_sql(0, 0, 0), true));

	pg_assert_true(pg_sw_filter_sql_closed(_list_closed_sql(1, 0, 0)), 'a missing field closes a list');
	pg_assert_true(pg_sw_filter_sql_closed(_list_closed_sql(1, 0, 0), true));
	pg_assert_true(pg_sw_filter_sql_closed(_list_closed_sql(0, 2, 0)), 'an unknown operator closes a list');
	pg_assert_true(pg_sw_filter_sql_closed(_list_closed_sql(0, 2, 0), true));

	pg_assert_false(pg_sw_filter_sql_closed(_list_closed_sql(0, 0, 1)), 'an address filter is left out of a list');
	pg_assert_true(pg_sw_filter_sql_closed(_list_closed_sql(0, 0, 1), true), 'an address filter closes a record view');

	pg_assert_false(pg_sw_filter_sql_closed(null));
	pg_assert_false(pg_sw_filter_sql_closed(pg_sw_form_list_filter_sql(12, array())));
}

// -- the list's records through its detail page (feed, sitemap, search index) ---

// One unknown operator among good filters lists nothing; the good filters
// alone list as before.
function test_list_closed_invalid_operator()
{
	_list_closed_db_stub();
	$good = _list_closed_reference_row();
	$bad  = array('field' => 'status', 'operator' => 'eşittir', 'value' => 'Published');

	$sql = pg_sw_form_list_filter_sql(12, array($good, $bad));
	pg_assert_same(1, $sql['invalid']);
	pg_assert_true(pg_sw_filter_sql_closed($sql));

	$closed = pg_sw_list_record_filter_clause(12, array('filters' => array($good, $bad)), array());
	pg_assert_true($closed['closed']);
	pg_assert_same(' AND (0 = 1)', $closed['where']);
	pg_assert_same('', $closed['join']);
	pg_assert_true(pg_sw_list_record_filter_clause(12, array('filters' => array($bad)), array(), 0, array('author' => 'Jane'))['closed'], 'the feed closes too');

	$open = pg_sw_list_record_filter_clause(12, array('filters' => array($good)), array());
	pg_assert_false($open['closed']);
	pg_assert_same(" AND (IFNULL(forms.reference_code, '') = 'R1')", $open['where']);
}

// Only 50 filters are applied; one past them that names a field cannot be
// applied, so the set closes rather than listing what it would hold back.
// A row with no field past them is ignored, as it is anywhere.
function test_list_closed_filter_past_the_cap()
{
	_list_closed_db_stub();
	$fifty = array_fill(0, 50, _list_closed_reference_row());

	$sql = pg_sw_form_list_filter_sql(12, $fifty);
	pg_assert_same(0, $sql['invalid']);
	pg_assert_false(pg_sw_filter_sql_closed($sql), 'fifty filters are applied');

	$blank = $fifty;
	$blank[] = array('field' => '', 'operator' => 'is equal to', 'value' => '');
	pg_assert_false(pg_sw_filter_sql_closed(pg_sw_form_list_filter_sql(12, $blank)), 'a row with no field is ignored');

	$over = $fifty;
	$over[] = _list_closed_reference_row();
	$sql = pg_sw_form_list_filter_sql(12, $over);
	pg_assert_same(1, $sql['invalid']);
	pg_assert_true(pg_sw_filter_sql_closed($sql), 'a 51st filter closes the list');

	// The record view's filters come first: a list filter pushed past the
	// cap by them closes the feed, sitemap and search index.
	$view = array('filters' => array_fill(0, 49, _list_closed_reference_row()));
	$list = array('filters' => array(_list_closed_reference_row(), _list_closed_reference_row()));
	pg_assert_true(pg_sw_list_record_filter_clause(12, $list, $view)['closed']);
}

// A filter valued from the address keeps its meaning on a list: left out
// with no address (sitemap, search index) or no value in it (the page), so
// the list is not closed; a record view still closes on it. Its operator is
// checked first, so an unknown one closes the list whatever the address.
function test_list_closed_address_filter_unaffected()
{
	_list_closed_db_stub();
	$row = _list_closed_address_row();

	$none = pg_sw_form_list_filter_sql(12, array($row));
	pg_assert_same(1, $none['address']);
	pg_assert_false(pg_sw_filter_sql_closed($none));
	pg_assert_false(pg_sw_list_record_filter_clause(12, array('filters' => array($row)), array())['closed']);

	$empty = pg_sw_form_list_filter_sql(12, array($row), array());
	pg_assert_same(0, $empty['address']);
	pg_assert_false(pg_sw_filter_sql_closed($empty));
	pg_assert_same('', $empty['where']);

	pg_assert_true(pg_sw_record_filter_clause(12, array($row), 0, true)['closed']);

	$bad = _list_closed_address_row('eşittir');
	pg_assert_true(pg_sw_filter_sql_closed(pg_sw_form_list_filter_sql(12, array($bad), array('author' => 'Jane'))));
	pg_assert_true(pg_sw_list_record_filter_clause(12, array('filters' => array($bad)), array())['closed']);
}

// -- every path takes the same decision -----------------------------------------

// The list page and the record clause (feed, sitemap, search index) ask
// pg_sw_filter_sql_closed(); no other code reads a list's filters itself.
function test_list_closed_every_path_uses_the_decision()
{
	$widgets = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets.php');

	$render = _list_closed_function_body($widgets, 'function _render_system_widget_form_list(');
	$call   = strpos($render, '$filter_sql   = pg_sw_form_list_filter_sql(');
	$check  = strpos($render, 'if (pg_sw_filter_sql_closed($filter_sql)) {');
	$where  = strpos($render, '$filter_where = $filter_sql[\'where\'];');
	pg_assert_true($call !== false && $check !== false && $where !== false, 'the list page checks its filters');
	pg_assert_true($call < $check && $check < $where, 'the check runs before the WHERE is used');

	$clause = _list_closed_function_body($widgets, 'function pg_sw_record_filter_clause(');
	pg_assert_contains('if (pg_sw_filter_sql_closed($filter_sql, $strict)) {', $clause);

	$feed = _list_closed_function_body($widgets, 'function pg_sw_form_feed_parts(');
	pg_assert_contains('pg_sw_list_record_filter_clause($form_page_id, $cfg,', $feed);

	pg_assert_contains('pg_sw_list_record_filter_clause($form_page_id, $cfg, $record_view[\'cfg\'], $detail_page_id)', (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_sitemap_info.php'));
	pg_assert_contains('pg_sw_list_record_filter_clause($form_page_id, $list_cfg, $record_view[\'cfg\'], $detail_page_id)', (string) file_get_contents(PG_FUNCTIONS_DIR . '/update_search_index.php'));

	// Only those two functions turn filters into SQL.
	$skip = array('includes/phpmailer', 'includes/stripe', 'includes/iyzipay-php', 'includes/phpexcel', 'includes/boxpacker', 'includes/dompdf', 'assets/lib', 'data');
	$root = rtrim(str_replace('\\', '/', PG_FUNCTIONS_DIR), '/') . '/';
	$calls = array();
	$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(PG_FUNCTIONS_DIR, FilesystemIterator::SKIP_DOTS));
	foreach ($files as $file) {
		$path = str_replace('\\', '/', (string) $file->getPathname());
		$relative = substr($path, strlen($root));
		if ($relative === false || substr($relative, -4) !== '.php') {
			continue;
		}
		foreach ($skip as $folder) {
			if (strpos($relative, $folder . '/') === 0) {
				continue 2;
			}
		}
		$count = preg_match_all('/(?<!function )pg_sw_form_list_filter_sql\((?!\))/', (string) file_get_contents($path));
		if ($count) {
			$calls[$relative] = $count;
		}
	}
	pg_assert_same(array('includes/fn/widgets.php' => 2), $calls);
}

// -- the editor --------------------------------------------------------------------

// A row naming a field the form does not have (once the fields are loaded)
// or an unknown operator says why no record is shown.
function test_list_closed_editor_row_warning()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$start = strpos($js, 'function _swFormListFiltersHtml(');
	pg_assert_true($start !== false, 'the filter rows are found in style_designer.js');
	$end = strpos($js, "\n    function ", $start + 10);
	$rows = substr($js, $start, $end - $start);
	pg_assert_contains("var rowWarn = (!known && !loading)", $rows);
	pg_assert_contains("_sdT('This field is not in the form, so no record is shown. Choose the field again.')", $rows);
	pg_assert_contains(": opBad\n                ? _sdT('This operator is not known, so no record is shown. Choose the operator again.')", $rows);
	pg_assert_contains("(rowWarn\n                        ? '<div class=\"sd-sw-filter-warn\"", $rows);
}
