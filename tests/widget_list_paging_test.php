<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the paging of the visual editor's form list widget: the list's
 * own page parameter (pg_sw_list_page_param()), the offset
 * (pg_sw_list_offset()), the page shown (pg_sw_list_current_page()), the
 * paging tokens and flags (pg_sw_list_paging()), the canonical of a later
 * page under the list's own parameter, the renderer's wiring and the
 * editor's twins.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _widget_list_paging_render()
{
	$widgets = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets.php');
	$start = strpos($widgets, 'function _render_system_widget_form_list(');
	pg_assert_true($start !== false, 'the form list renderer is found');
	$end = strpos($widgets, "\nfunction ", (int) $start + 10);
	return substr($widgets, (int) $start, (int) $end - (int) $start);
}

// page_number unless the list names a usable parameter of its own.
function test_list_page_param()
{
	pg_assert_same('page_number', pg_sw_list_page_param(array()));
	pg_assert_same('page_number', pg_sw_list_page_param(null));
	pg_assert_same('page_number', pg_sw_list_page_param(array('page_param' => '')));
	pg_assert_same('topics_page', pg_sw_list_page_param(array('page_param' => 'topics_page')));
	pg_assert_same('topics_page', pg_sw_list_page_param(array('page_param' => ' Topics_Page ')));
	// Names the software reads itself, or that cannot be a name, fall back.
	foreach (array('page', 'query', 'comments_page', 'r', 'utm_x', 'pg_page', '2nd', 'a-b', str_repeat('a', 33)) as $name) {
		pg_assert_same('page_number', pg_sw_list_page_param(array('page_param' => $name)), $name);
	}
	pg_assert_same('page_number', pg_sw_list_page_param(array('page_param' => array('x'))));
}

function test_list_offset()
{
	pg_assert_same(0, pg_sw_list_offset(array()));
	pg_assert_same(0, pg_sw_list_offset(array('offset' => -3)));
	pg_assert_same(1, pg_sw_list_offset(array('offset' => '1')));
	pg_assert_same(100000, pg_sw_list_offset(array('offset' => 999999)));
	pg_assert_same(0, pg_sw_list_offset(array('offset' => array(5))));
}

function test_list_current_page()
{
	pg_assert_same(1, pg_sw_list_current_page(null, 5));
	pg_assert_same(3, pg_sw_list_current_page('3', 5));
	pg_assert_same(5, pg_sw_list_current_page('9', 5));
	pg_assert_same(1, pg_sw_list_current_page('-2', 5));
	pg_assert_same(1, pg_sw_list_current_page(array('2'), 5));
	pg_assert_same(1, pg_sw_list_current_page('4', 0));
}

// Escaped links that keep the other parameters, '' at the ends.
function test_list_paging_tokens_and_flags()
{
	$saved_get = $_GET;
	$_GET = array('author' => 'Jane & Co', 'topics_page' => '2', 'page_number' => '4');

	$p = pg_sw_list_paging('topics_page', 2, 3);
	pg_assert_same('2', $p['tokens']['__page_number']);
	pg_assert_same('3', $p['tokens']['__page_count']);
	pg_assert_same('?author=Jane+%26+Co&amp;topics_page=1&amp;page_number=4', $p['tokens']['__prev_page_url']);
	pg_assert_same('?author=Jane+%26+Co&amp;topics_page=3&amp;page_number=4', $p['tokens']['__next_page_url']);
	pg_assert_same(array('has_prev_page' => true, 'has_next_page' => true, 'has_more_pages' => true), $p['flags']);

	$p = pg_sw_list_paging('page_number', 1, 1);
	pg_assert_same('', $p['tokens']['__prev_page_url']);
	pg_assert_same('', $p['tokens']['__next_page_url']);
	pg_assert_same(array('has_prev_page' => false, 'has_next_page' => false, 'has_more_pages' => false), $p['flags']);

	$p = pg_sw_list_paging('page_number', 3, 3);
	pg_assert_true($p['flags']['has_prev_page']);
	pg_assert_false($p['flags']['has_next_page']);
	pg_assert_same('', $p['tokens']['__next_page_url']);

	// Every token is a plain value, escaped here once.
	foreach (array_keys($p['tokens']) as $key) {
		pg_assert_false(in_array($key, pg_sw_raw_token_keys(), true), $key . ' is not a raw key');
	}
	$_GET = $saved_get;
}

// A later page of an address-filtered list keeps the list's own name in
// the canonical; page_number lists keep theirs.
function test_list_page_param_canonical()
{
	$applied = array('params' => array('author' => 'Jane'));
	pg_assert_same(2, pg_sw_url_filter_page($applied, array('topics_page' => '2'), 2, 5, false, 'topics_page'));
	pg_assert_same(0, pg_sw_url_filter_page($applied, array('page_number' => '2'), 2, 5, false, 'topics_page'));

	pg_sw_url_filter_state(false);
	pg_sw_url_filter_state(array('params' => array('author' => 'Jane')));
	$state = pg_sw_url_filter_state(array('page_number' => 2, 'page_param' => 'topics_page'));
	pg_assert_same('author=Jane&topics_page=2', pg_sw_url_filter_query($state));
	pg_assert_same('author=Jane', pg_sw_url_filter_query($state, false));

	pg_sw_url_filter_state(false);
	pg_sw_url_filter_state(array('params' => array('author' => 'Jane')));
	$state = pg_sw_url_filter_state(array('page_number' => 2, 'page_param' => 'page_number'));
	pg_assert_false(isset($state['page_param']), 'page_number is not stored as a name of its own');
	pg_assert_same('author=Jane&page_number=2', pg_sw_url_filter_query($state));

	// A stored name that cannot be used falls back.
	pg_assert_same('author=Jane&page_number=2', pg_sw_url_filter_query(array('params' => array('author' => 'Jane'), 'page_number' => 2, 'page_param' => 'page')));
	pg_sw_url_filter_state(false);
}

// The renderer reads the page from its own parameter, starts after the
// offset and hands the paging tokens to the surround and the rows.
function test_list_paging_renderer_wiring()
{
	$body = _widget_list_paging_render();
	pg_assert_contains('$page_param     = pg_sw_list_page_param($cfg);', $body);
	pg_assert_contains('$requested_page = isset($_GET[$page_param]) ? $_GET[$page_param] : null;', $body);
	pg_assert_contains('$offset_records = pg_sw_list_offset($cfg);', $body);
	pg_assert_contains('$offset     = $offset_records + $page_start;', $body);
	pg_assert_contains('LIMIT $page_limit OFFSET $offset', $body);
	pg_assert_contains('&$paging_tokens', $body);
	pg_assert_contains(') + $paging_tokens + $page_link_bare)),', $body);
	pg_assert_contains('$extra += $paging_tokens;', $body);
	pg_assert_contains(') + $paging[\'flags\'];', $body);
	pg_assert_contains('pg_sw_url_filter_page($filter_sql, $request, $current_page, $total_pages, $search_active, $page_param);', $body);
	pg_assert_contains("'page_param' => \$page_param", $body);
	pg_assert_contains('pg_sw_pagination_html($page_param, $current_page, $total_pages);', $body);
	pg_assert_false(strpos($body, "\$page_param   = 'page_number';") !== false, 'the page parameter is not fixed');
}

// The panel writes the two settings; the editor lists the tokens and flags.
function test_list_paging_editor()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	pg_assert_contains('data-sw-cfg="offset" data-sw-cfg-kind="int" min="0" max="100000"', $js);
	pg_assert_contains('data-sw-cfg="page_param" data-sw-cfg-kind="param"', $js);
	pg_assert_contains("} else if (kind === 'param') {", $js);
	pg_assert_contains('function _swListOffset(cfg) {', $js);

	$start = strpos($js, 'var SW_FORM_LIST_VIEW_TOKENS = [');
	$list = substr($js, (int) $start, (int) strpos($js, '];', (int) $start) - (int) $start);
	foreach (array('__page_number', '__page_count', '__prev_page_url', '__next_page_url') as $token) {
		pg_assert_contains("['" . $token . "',", $list);
	}
	pg_assert_true((bool) preg_match("/\['no_records',[^\]]*\],\s*\['has_prev_page',[^\]]*\],\s*\['has_next_page',[^\]]*\],\s*\['has_more_pages',[^\]]*\]\s*\]/", $js), 'form_list_view lists the paging flags after no_records');
	pg_assert_contains("'__page_number': '1'", $js);
}
