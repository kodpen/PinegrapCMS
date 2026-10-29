<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests the panel lists (table.chart DataTables in backend.src.js): a list
 * comes back on the page, length and search it was left on after Back, its
 * pager is named from the DataTables language, and Load More in the Ctrl+K
 * search keeps the keyboard's place and survives a failed page.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function list_page_state_script()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/backend.src.js');
}

function test_list_page_state_is_read_back_before_init()
{
	$js = list_page_state_script();

	pg_assert_contains('var pgListKept = pgListState(pgListKey);', $js, 'the kept state of the list is read from history.state');
	pg_assert_contains('options["displayStart"] = pgListKept.start;', $js, 'the kept page is the first draw\'s start');
	pg_assert_contains('options["pageLength"] = pgListKept.length;', $js, 'the kept length is the page length');
	pg_assert_contains('options["search"] = { search: pgListKept.search };', $js, 'the kept search text is the first search');
	pg_assert_true(strpos($js, 'pgListKept') < strpos($js, 'var datatable = $("table.chart").DataTable(options);'), 'the state is read before the init');
	pg_assert_contains("if (\$('table.chart').length === 1 && options[\"paging\"]) {", $js, 'only a single paged list is restored, all lists share one options object');
	pg_assert_contains('options["stateSave"] = false;', $js, 'DataTables stateSave stays off: it would also keep column order and visibility');
}

function test_list_page_state_is_kept_on_each_draw()
{
	$js = list_page_state_script();

	pg_assert_contains("datatable.on('draw.dt', function () {", $js, 'every draw (page, length, search) records the state');
	pg_assert_contains('pgListKeep(pgListKey, { start: info.start, length: info.length, search: datatable.search() });', $js, 'start, length and search text are recorded');
	pg_assert_contains('history.replaceState(state, \'\', location.href);', $js, 'the state goes into the current history entry');
	pg_assert_contains('var state = $.extend({}, $.isPlainObject(history.state) ? history.state : null);', $js, 'the existing history state is merged, not replaced');
}

function test_list_page_state_survives_init_and_query()
{
	$js = list_page_state_script();

	pg_assert_contains("if (!options.serverSide) {\n            datatable.draw(false);", $js, 'the init draw keeps the restored page');
	pg_assert_true(strpos($js, "if (!options.serverSide) {\n            datatable.draw();") === false, 'a bare draw() would put the list back on page 1');
	pg_assert_contains("history.replaceState(history.state, '', '?' + params + location.hash);", $js, 'the ?query= handler keeps the history state');
	pg_assert_true(strpos($js, "history.replaceState(null, '', '?' + params + location.hash);") === false, 'the ?query= handler no longer wipes the history state');
}

function test_list_page_state_validates_kept_values()
{
	$js = list_page_state_script();

	pg_assert_contains('function pgListState(key) {', $js, 'the reader is a named function');
	pg_assert_contains('kept.start >= 0 && kept.start % 1 === 0', $js, 'only a whole, non-negative start is used');
	pg_assert_contains("typeof kept.search === 'string'", $js, 'only a text search is used');
}

function test_list_pager_named_in_panel_language()
{
	$js = list_page_state_script();
	$tr = json_decode((string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/lib/DataTables/i18n/tr.json'), true);

	pg_assert_contains("\$(datatable.table().container()).find('div.dt-paging nav').attr('aria-label', datatable.i18n('aria.paging', 'Pagination'));", $js, 'the pager landmark is named from the DataTables language');
	pg_assert_same('Sayfalama', isset($tr['aria']['paging']) ? $tr['aria']['paging'] : null, 'the Turkish file names the pager');
}

function test_command_palette_load_more_keeps_focus()
{
	$js = list_page_state_script();

	pg_assert_contains('<span class="spinner-border spinner-border-sm" aria-hidden="true"></span><span class="visually-hidden">\' + t(\'Loading...\') + \'</span>', $js, 'the next page spinner is heard');
	pg_assert_contains('<div class="srch-load-more text-center py-3 fg-secondary" role="status">', $js, 'the next page spinner is a status');
	pg_assert_contains("if (\$oldMore.is(':focus')) {\n                \$oldMore.prevAll('button').first().trigger('focus');", $js, 'the result above Load More holds the focus while the page loads');
	pg_assert_contains('var $first = appendResults(resp.results);', $js, 'appendResults() returns the first new result');
	pg_assert_contains("if (offset > 0) {\n                            \$first.trigger('focus');", $js, 'focus moves to the first new result after Load More');
	pg_assert_contains("\$results.append(\$more);\n                        if (offset > 0 && !(resp.results && resp.results.length)) {\n                            \$more.trigger('focus');", $js, 'a page with no results keeps focus on Load More');
}

function test_command_palette_load_more_failure_recovers()
{
	$js = list_page_state_script();

	pg_assert_contains('function loadMoreFailed() {', $js, 'a later page that fails has its own path');
	pg_assert_contains('currentOffset -= 20;', $js, 'the offset goes back so the same page can be asked again');
	pg_assert_contains("if (offset > 0 && !(resp && resp.status === 'success')) {\n                        loadMoreFailed();\n                        return;", $js, 'a non-success answer at offset > 0 reaches the failure path');
	pg_assert_contains("if (offset > 0) { loadMoreFailed(); } else {", $js, 'a network failure at offset > 0 reaches the failure path');
}
