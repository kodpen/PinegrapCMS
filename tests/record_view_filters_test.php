<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the record visibility filters of the visual editor's form
 * widgets: the filters a record view holds its record to
 * (pg_sw_record_view_filters()), the filters a list's records go through in
 * the sitemap (pg_sw_merge_record_filters()), which form widgets get a public
 * feed and sitemap records (pg_sw_form_widget_public()), when an e-mail
 * skips the filters (pg_sw_record_filter_bypass()), the cases of the record
 * check that need no query, and the record-view panel offering the filter
 * rows in style_designer.js.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _record_view_filters_published()
{
	return array('field' => 'status', 'operator' => 'is equal to', 'value' => 'Published', 'dynamic_value' => '', 'dynamic_value_attribute' => '');
}

// -- the record view's filters -------------------------------------------------

// No filters key, an empty list or a value that is not a list: no filter,
// the record view behaves as before the setting existed.
function test_record_view_filters_absent_means_none()
{
	pg_assert_same(array(), pg_sw_record_view_filters(array()));
	pg_assert_same(array(), pg_sw_record_view_filters(array('filters' => array())));
	pg_assert_same(array(), pg_sw_record_view_filters(array('filters' => 'status')));
	pg_assert_same(array(), pg_sw_record_view_filters(null));
}

// Well-formed rows are kept in order; a row that is not an array is dropped.
function test_record_view_filters_keeps_rows()
{
	$published = _record_view_filters_published();
	$cfg = array('filters' => array('x' => $published, 'junk', 5));
	pg_assert_same(array($published), pg_sw_record_view_filters($cfg));
}

// -- the sitemap's merged filters ---------------------------------------------

// The record view's filters come first, then the list's, so a list with
// many filters cannot push the page's own out of the fifty that are read.
function test_record_view_filters_merge_order()
{
	$published = _record_view_filters_published();
	$featured  = array('field' => 'featured', 'operator' => 'is equal to', 'value' => 'Yes');
	pg_assert_same(
		array($published, $featured),
		pg_sw_merge_record_filters(array('filters' => array($featured)), array('filters' => array($published)))
	);
}

// Either side may have none; neither gives an empty list.
function test_record_view_filters_merge_one_side()
{
	$published = _record_view_filters_published();
	pg_assert_same(array($published), pg_sw_merge_record_filters(array(), array('filters' => array($published))));
	pg_assert_same(array($published), pg_sw_merge_record_filters(array('filters' => array($published)), array()));
	pg_assert_same(array(), pg_sw_merge_record_filters(array(), array()));
	pg_assert_same(array(), pg_sw_merge_record_filters(null, null));
}

// -- which widgets feed and list their records publicly ------------------------

// Public access, or none set, with no viewer filter.
function test_record_view_filters_public_widget()
{
	pg_assert_true(pg_sw_form_widget_public(array()));
	pg_assert_true(pg_sw_form_widget_public(array('access_control' => 'public')));
	pg_assert_true(pg_sw_form_widget_public(array('access_control' => '')));
	pg_assert_true(pg_sw_form_widget_public(array('viewer_filter' => false)));
	pg_assert_true(pg_sw_form_widget_public(array('viewer_filter' => 0)));
}

// A record view for the submitter or signed-in visitors only is not public.
function test_record_view_filters_restricted_access_is_not_public()
{
	pg_assert_false(pg_sw_form_widget_public(array('access_control' => 'submitter_only')));
	pg_assert_false(pg_sw_form_widget_public(array('access_control' => 'logged_in')));
	pg_assert_false(pg_sw_form_widget_public(null));
}

// A viewer filtered list shows an anonymous visitor nothing, so it has no
// public feed and puts nothing in the sitemap (the legacy rule).
function test_record_view_filters_viewer_filter_is_not_public()
{
	pg_assert_false(pg_sw_form_widget_public(array('viewer_filter' => true)));
	pg_assert_false(pg_sw_form_widget_public(array('access_control' => 'public', 'viewer_filter' => 1)));
}

// A list switched to a record view in the editor keeps its viewer_filter
// key; the record view never reads it, so its page keeps its feed.
function test_record_view_filters_item_view_ignores_viewer_filter()
{
	pg_assert_true(pg_sw_form_widget_public(array('regionType' => 'form_item_view', 'access_control' => 'public', 'viewer_filter' => true)));
	pg_assert_true(pg_sw_form_widget_public(array('regionType' => 'form_item_view', 'viewer_filter' => 1)));
	pg_assert_false(pg_sw_form_widget_public(array('regionType' => 'form_item_view', 'access_control' => 'logged_in')));
	pg_assert_false(pg_sw_form_widget_public(array('regionType' => 'form_list_view', 'viewer_filter' => true)));
}

// -- the e-mail bypass ---------------------------------------------------------

// Only an e-mail drawn for a record the server named skips the filters;
// ?email=true on an address (no record id) does not.
function test_record_view_filters_email_bypass_needs_record_id()
{
	pg_assert_true(pg_sw_record_filter_bypass(true, 42));
	pg_assert_true(pg_sw_record_filter_bypass(1, '42'));
	pg_assert_false(pg_sw_record_filter_bypass(true, 0));
	pg_assert_false(pg_sw_record_filter_bypass(true, null));
	pg_assert_false(pg_sw_record_filter_bypass(false, 42));
}

// -- the record check without a query ----------------------------------------

// No filters: nothing to add to a query, and every record passes.
function test_record_view_filters_clause_empty()
{
	$none = array('join' => '', 'where' => '', 'closed' => false, 'params' => array(), 'inexact' => false, 'exact' => '');
	pg_assert_same($none, pg_sw_record_filter_clause(12, array()));
	pg_assert_same($none, pg_sw_record_filter_clause(12, 'status'));
	pg_assert_same($none, pg_sw_record_filter_clause(12, array(), 0, true));
	pg_assert_same($none, pg_sw_list_record_filter_clause(12, array(), array()));
	pg_assert_same($none, pg_sw_list_record_filter_clause(12, null, null, 5));
	pg_assert_true(pg_sw_record_passes_filters(12, array(), 34));
}

// No record or no form: nothing passes, whatever the filters.
function test_record_view_filters_no_record_fails()
{
	pg_assert_false(pg_sw_record_passes_filters(12, array(), 0));
	pg_assert_false(pg_sw_record_passes_filters(0, array(), 34));
}

// An operator outside the list (here the Turkish "eşittir" a hand-written
// config might carry) is counted, not silently dropped: a record view and
// a list both close on it. A blank row counts in neither.
function test_record_view_filters_invalid_operator_closes()
{
	$bad = array('field' => 'status', 'operator' => 'eşittir', 'value' => 'Published');
	$sql = pg_sw_form_list_filter_sql(12, array($bad));
	pg_assert_same(1, $sql['invalid']);
	pg_assert_same('', $sql['where']);

	$closed = pg_sw_record_filter_clause(12, array($bad), 0, true);
	pg_assert_true($closed['closed']);
	pg_assert_same(' AND (0 = 1)', $closed['where']);
	pg_assert_false(pg_sw_record_passes_filters(12, array($bad), 34));
	pg_assert_true(pg_sw_list_record_filter_clause(12, array(), array('filters' => array($bad)))['closed']);

	$list = pg_sw_record_filter_clause(12, array($bad));
	pg_assert_same(array('join' => '', 'where' => ' AND (0 = 1)', 'closed' => true, 'params' => array(), 'inexact' => false, 'exact' => ''), $list);
	pg_assert_true(pg_sw_list_record_filter_clause(12, array('filters' => array($bad)), array())['closed']);

	$no_operator = array('field' => 'reference_code', 'value' => 'X');
	pg_assert_true(pg_sw_record_filter_clause(12, array($no_operator), 0, true)['closed']);

	$blank = array('field' => '', 'operator' => 'is equal to', 'value' => '');
	$sql = pg_sw_form_list_filter_sql(12, array($blank));
	pg_assert_same(0, $sql['invalid']);
	pg_assert_same(0, $sql['missing']);
	pg_assert_false(pg_sw_record_filter_clause(12, array($blank), 0, true)['closed']);
}

// -- the editor ----------------------------------------------------------------

// The record-view panel offers the list's filter rows, saved under the
// widget's `filters`.
function test_record_view_filters_editor_panel()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	$start = strpos($js, 'if (_isFormItemView && _curFormId > 0) {');
	pg_assert_true($start !== false, 'the record-view panel is found in style_designer.js');
	$end = strpos($js, '// ── Records in the translation', $start);
	pg_assert_true($end !== false, 'the end of the record-view panel is found');
	$panel = substr($js, $start, $end - $start);
	pg_assert_contains('_swFormListFiltersHtml(sid, Array.isArray(_cfg.filters)', $panel);
	pg_assert_contains("_sdT('Filters')", $panel);
}

// A row whose operator is outside the list shows it as incompatible instead
// of reading as "is equal to".
function test_record_view_filters_editor_flags_unknown_operator()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	$start = strpos($js, 'function _swFormListFiltersHtml(');
	pg_assert_true($start !== false, 'the filter rows are found in style_designer.js');
	$rows = substr($js, $start, 6000);
	pg_assert_contains("if (opBad) opOpts += '<option value=\"' + esc(rawOp) + '\" selected>'", $rows);
}

// -- the list page's pretty address -------------------------------------------

// A list page's /<list>/<name> answers 301 only into a record view that every
// visitor may open: a redirect into a submitter_only or logged_in view would
// tell which names exist, drafts included. The list branch asks
// pg_sw_form_widget_public() of the detail view before it looks the name up,
// so the answer is the same as for an unknown name.
function test_record_view_filters_list_redirect_needs_public_detail_view()
{
	$function = new ReflectionFunction('pg_sw_route_record_address');
	$lines = file($function->getFileName());
	$source = implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));

	$list_branch = (string) substr($source, (int) strpos($source, 'foreach ($lists as $w)'));
	pg_assert_contains('foreach ($lists as $w)', $list_branch, 'the list branch is found');
	pg_assert_contains("if (!pg_sw_form_widget_public(\$detail_view['cfg'])) continue;", $list_branch, 'the detail view must be public');

	$guard  = strpos($list_branch, "pg_sw_form_widget_public(\$detail_view['cfg'])");
	$lookup = strpos($list_branch, 'pg_sw_record_by_address(');
	pg_assert_true($guard !== false && $lookup !== false && $guard < $lookup, 'the guard comes before the record is looked up');
}

// The list branch runs before get_page.php's folder check, so a detail page in
// a private or membership folder the visitor may not open gets no redirect
// either, even with a public record view: the 301 would name what that page's
// own sign-in answer keeps hidden. Registration and guest folders pass (the
// second argument), since anyone may sign up or choose guest.
function test_record_view_filters_list_redirect_needs_detail_folder_access()
{
	$function = new ReflectionFunction('pg_sw_route_record_address');
	$lines = file($function->getFileName());
	$source = implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));

	$list_branch = (string) substr($source, (int) strpos($source, 'foreach ($lists as $w)'));
	pg_assert_contains("if (!check_view_access(\$detail_page['page_folder'], true)) continue;", $list_branch, 'the detail page folder must be open to the visitor');

	$guard  = strpos($list_branch, "check_view_access(\$detail_page['page_folder'], true)");
	$lookup = strpos($list_branch, 'pg_sw_record_by_address(');
	pg_assert_true($guard !== false && $lookup !== false && $guard < $lookup, 'the folder check comes before the record is looked up');
}
