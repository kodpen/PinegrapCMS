<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the result count of the visual editor's form list widget:
 * ^^__result_count^^ (the records the list matched, capped at max_results
 * like the classic list's "Found N results"), the "Show only when" flags
 * has_records / no_records, the list whose loop area is empty (a count strip
 * that runs only the count), the editor offering the token and the flags,
 * and the template installer's preview of the flags.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The text of a function, from its declaration to the next one.
function _form_list_result_count_function_body($source, $declaration)
{
	$start = strpos($source, $declaration);
	pg_assert_true($start !== false, $declaration . ' is found');
	if ($start === false) {
		return '';
	}
	$end = strpos($source, "\nfunction ", $start + strlen($declaration));
	return substr($source, $start, ($end === false ? strlen($source) : $end) - $start);
}

function _form_list_result_count_render()
{
	$widgets = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets.php');
	return _form_list_result_count_function_body($widgets, 'function _render_system_widget_form_list(');
}

function _form_list_result_count_js()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
}

// $before is found in $body, and so is $after, later.
function _form_list_result_count_order($body, $before, $after, $message)
{
	$a = strpos($body, $before);
	$b = ($a === false) ? false : strpos($body, $after, $a + strlen($before));
	pg_assert_true($a !== false, $message . ': ' . $before . ' is found');
	pg_assert_true($b !== false, $message . ': ' . $after . ' follows it');
}

// The empty loop area is recorded before the template is resolved, the count
// is capped where pagination is still uncapped, and a count strip returns
// only after the page head has been told what the list found.
function test_form_list_result_count_render_order()
{
	$body = _form_list_result_count_render();

	_form_list_result_count_order($body, '$loop_empty = ($loop_template === \'\');', '$loop_template = _pg_sw_resolve_visibility($loop_template, $flags);', 'the empty loop area is taken before visibility is resolved');
	_form_list_result_count_order($body, 'if ($loop_empty && $static_html === \'\') return \'\';', 'SELECT COUNT(*) FROM forms', 'a list with nothing to draw runs no query');
	_form_list_result_count_order($body, 'SELECT COUNT(*) FROM forms', '$listed_records = max(0, $total_records - $offset_records);', 'the offset is taken off the COUNT');
	_form_list_result_count_order($body, '$listed_records = max(0, $total_records - $offset_records);', '$result_count = ($max_results > 0) ? min($listed_records, $max_results) : $listed_records;', 'max_results caps what the offset left');
	_form_list_result_count_order($body, '$result_count = ($max_results > 0) ? min($listed_records, $max_results) : $listed_records;', '$total_pages  = $loop_empty ? min(1, $result_count) : (int)ceil($result_count / $items_per_page);', 'the pages follow the capped count');
	_form_list_result_count_order($body, 'pg_sw_url_filter_state(array(', 'if ($loop_empty) return $stitch(\'\', \'\');', 'a count strip registers the head state first');
	_form_list_result_count_order($body, 'if ($result_count === 0) return $stitch($empty_html, \'\');', 'if ($loop_empty) return $stitch(\'\', \'\');', 'a count strip shows the empty message only when nothing matched');
	_form_list_result_count_order($body, 'if ($loop_empty) return $stitch(\'\', \'\');', 'pg_sw_pagination_html(', 'a count strip has no pagination');

	// The old shortcut printed the empty message without a query.
	pg_assert_false(strpos($body, "if (\$loop_template === '') {") !== false, 'the empty loop area no longer skips the count');
}

// The number is escaped once and grouped like the classic list's, in the
// surround and in every row; the flags follow the capped count.
function test_form_list_result_count_tokens_and_flags()
{
	$body = _form_list_result_count_render();

	pg_assert_contains('$result_count_html = h(pg_format_number($result_count, 0));', $body);

	$stitch_start = strpos($body, '$stitch = function ($rows, $pagination) use (');
	pg_assert_true($stitch_start !== false, 'the stitch closure is found');
	$stitch = substr($body, (int) $stitch_start, (int) strpos($body, '};', (int) $stitch_start) - (int) $stitch_start);
	pg_assert_contains('&$result_count_html', $stitch);
	pg_assert_contains("'__result_count' => \$result_count_html", $stitch);

	pg_assert_contains("\$extra['__result_count'] = \$result_count_html;", $body);
	pg_assert_true((bool) preg_match("/'has_records'\s*=> \\\$result_count > 0,/", $body), 'has_records is on when the list counted records');
	pg_assert_true((bool) preg_match("/'no_records'\s*=> \\\$result_count === 0,/", $body), 'no_records is on when it counted none');
	pg_assert_contains("_pg_sw_mark_visibility(\$tree_decoded, array('has_filter_value', 'no_filter_value', 'has_records', 'no_records', 'has_prev_page', 'has_next_page', 'has_more_pages'));", $body);

	// A finished value, not a raw HTML key.
	pg_assert_false(in_array('__result_count', pg_sw_raw_token_keys(), true), '__result_count is not a raw key');
}

// The editor offers the token under the label search results use for the
// same name, and lists the two flags after the address flags.
function test_form_list_result_count_editor()
{
	$js = _form_list_result_count_js();

	$start = strpos($js, 'var SW_FORM_LIST_VIEW_TOKENS = [');
	pg_assert_true($start !== false, 'the form list token list is found');
	$list = substr($js, (int) $start, (int) strpos($js, '];', (int) $start) - (int) $start);
	pg_assert_contains("['__result_count', _sdT('Number of results')]", $list);

	pg_assert_true((bool) preg_match("/form_list_view: \[\s*\['has_filter_value',[^\]]*\],\s*\['no_filter_value',[^\]]*\],\s*\['has_records',[^\]]*\],\s*\['no_records',[^\]]*\],/", $js), 'form_list_view lists the address flags, then has_records and no_records');
	pg_assert_true((bool) preg_match("/\['has_records',\s*_sdT\('The list found records'\)\]/", $js), 'has_records carries its label');
	pg_assert_true((bool) preg_match("/\['no_records',\s*_sdT\('The list found nothing'\)\]/", $js), 'no_records carries its label');

	// The canvas draws a small number for it.
	pg_assert_contains("'__result_count': '12'", $js);
}

// The template installer's preview draws a listing with records.
function test_form_list_result_count_preview_flags()
{
	pg_assert_true(function_exists('_pg_tpl_preview_flag_on'), '_pg_tpl_preview_flag_on is loaded');
	if (!function_exists('_pg_tpl_preview_flag_on')) {
		return;
	}
	pg_assert_true(_pg_tpl_preview_flag_on('has_records'));
	pg_assert_false(_pg_tpl_preview_flag_on('no_records'));
	pg_assert_false(_pg_tpl_preview_flag_on('has_filter_value'));
	pg_assert_true(_pg_tpl_preview_flag_on('no_filter_value'));
}
