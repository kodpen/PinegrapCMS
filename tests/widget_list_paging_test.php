<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the paging of the visual editor's form list widget: the list's
 * own page parameter (pg_sw_list_page_param()), the offset
 * (pg_sw_list_offset()), the page shown (pg_sw_list_current_page()), the
 * paging tokens and flags (pg_sw_list_paging()), the canonical of a later
 * page under the list's own parameter, the wiring of the form list, catalog
 * listing and search results renderers and the editor's twins.
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

// A page past the last one is missing only when no list reading that
// parameter showed it: a short box beside the main list has no veto.
function test_list_page_past_the_last_one()
{
	pg_sw_list_page_requests(false);
	pg_sw_list_page_requests(array('page_number', '9', 5));
	pg_assert_true(pg_sw_list_page_past(pg_sw_list_page_requests()), 'page 9 of 5');

	pg_sw_list_page_requests(false);
	pg_sw_list_page_requests(array('page_number', '2', 0));
	pg_assert_true(pg_sw_list_page_past(pg_sw_list_page_requests()), 'page 2 of an empty list');

	pg_sw_list_page_requests(false);
	pg_sw_list_page_requests(array('page_number', '3', 1));
	pg_sw_list_page_requests(array('page_number', '3', 5));
	pg_assert_false(pg_sw_list_page_past(pg_sw_list_page_requests()), 'the main list shows page 3');

	pg_sw_list_page_requests(false);
	pg_sw_list_page_requests(array('topics_page', '3', 5));
	pg_sw_list_page_requests(array('page_number', '3', 1));
	pg_assert_true(pg_sw_list_page_past(pg_sw_list_page_requests()), 'another parameter shows nothing for page_number');

	pg_sw_list_page_requests(false);
	foreach (array('1', '0', '-4', 'abc', array('9'), null) as $asked) {
		pg_sw_list_page_requests(array('page_number', $asked, 1));
	}
	pg_assert_same(array(), pg_sw_list_page_requests(), 'page 1, nothing or no number asks for nothing');
	pg_assert_false(pg_sw_list_page_past(null));
	pg_sw_list_page_requests(false);
}

// The renderer registers the asked page, and the page answers 404 for a
// page past the last one after its widget pass.
function test_list_page_past_wiring()
{
	pg_assert_contains('pg_sw_list_page_requests(array($page_param, $request[$page_param], $total_pages));', _widget_list_paging_render());

	$page = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_page_content.php');
	pg_assert_contains("if (function_exists('pg_sw_list_page_requests') && !\$email) pg_sw_list_page_requests(false);", $page);
	pg_assert_contains('&& pg_sw_list_page_past(pg_sw_list_page_requests())', $page);
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
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
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

// The source of one renderer in $file.
function _widget_list_paging_fn($file, $name)
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/' . $file);
	$start = strpos($src, 'function ' . $name . '(');
	pg_assert_true($start !== false, $name . ' is found');
	$end = strpos($src, "\nfunction ", (int) $start + 10);
	return substr($src, (int) $start, (int) $end - (int) $start);
}

// The catalog listing pages under its own parameter, starts after the
// offset, fills the paging tokens everywhere and keeps or cuts the flagged
// parts of its surround once the pages are counted.
function test_catalog_listing_paging_wiring()
{
	$body = _widget_list_paging_fn('widgets_catalog.php', '_render_system_widget_catalog_listing');
	pg_assert_contains('$page_param     = pg_sw_list_page_param($cfg);', $body);
	pg_assert_contains('$offset_records = pg_sw_list_offset($cfg);', $body);
	pg_assert_contains('$total_records = max(0, $total_records - $offset_records);', $body);
	pg_assert_contains('LIMIT $page_limit OFFSET " . ($offset_records + $offset)', $body);
	pg_assert_contains('$paging = pg_sw_list_paging($page_param, $current_page, $total_pages);', $body);
	pg_assert_contains("_pg_sw_mark_visibility(\$split['static_tree'], array('has_prev_page', 'has_next_page', 'has_more_pages'));", $body);
	pg_assert_contains("\$static_html = _pg_sw_resolve_visibility(\$static_html, \$paging['flags']);", $body);
	pg_assert_contains("\$page_tokens = pg_sw_escape_token_values(\$page_tokens) + \$paging['tokens'];", $body);
	pg_assert_false(strpos($body, "= 'page_number';") !== false, 'the page parameter is not fixed');
	// The offset is applied to the count before max_results caps it.
	pg_assert_true(strpos($body, '$total_records - $offset_records') < strpos($body, 'Apply max_results cap'), 'offset before the cap');
	// The flags are resolved before the tokens go in.
	pg_assert_true(strpos($body, "_pg_sw_resolve_visibility(\$static_html, \$paging['flags'])") < strpos($body, '$static_html = _pg_catalog_fill_page_tokens('), 'flags before tokens');
}

// The search results do the same; their flags are known before the render.
function test_search_results_paging_wiring()
{
	$body = _widget_list_paging_fn('widgets.php', '_render_system_widget_search_results');
	pg_assert_contains('$page_param = pg_sw_list_page_param($cfg);', $body);
	pg_assert_contains('$rows  = array_slice($rows, pg_sw_list_offset($cfg));', $body);
	pg_assert_contains('$paging       = pg_sw_list_paging($page_param, $current_page, $total_pages);', $body);
	pg_assert_contains(") + \$paging['flags']);", $body);
	pg_assert_contains("), array('__result_price')) + \$paging['tokens'];", $body);
	pg_assert_contains(")) + \$paging['tokens'];", $body);
	pg_assert_contains('_pg_sw_hidden_query_inputs(array($param, $page_param))', $body);
	pg_assert_false(strpos($body, "= 'page_number';") !== false, 'the page parameter is not fixed');
	pg_assert_true(strpos($body, 'array_slice($rows, pg_sw_list_offset($cfg))') < strpos($body, '$total = count($rows);'), 'offset before the count');
}

// The two widgets' panels and palettes.
function test_catalog_and_search_paging_editor()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_contains('((_isFormListView || _isCatalogListing) ? row(_sdT(\'Skip the first records\'),', $js);
	pg_assert_contains("row(_sdT('Skip the first results'),", $js);
	pg_assert_same(2, substr_count($js, 'data-sw-cfg="page_param" data-sw-cfg-kind="param"'), 'two panels write page_param');

	foreach (array('SW_CATALOG_TOKEN_GROUPS', 'SW_SEARCH_RESULTS_TOKEN_GROUPS') as $var) {
		$start = strpos($js, 'var ' . $var . ' = [');
		pg_assert_true($start !== false, $var);
		$list = substr($js, (int) $start, (int) strpos($js, "\n    ];", (int) $start) - (int) $start);
		foreach (array('__page_number', '__page_count', '__prev_page_url', '__next_page_url') as $token) {
			pg_assert_contains("['" . $token . "',", $list, $var . ' ' . $token);
		}
	}
	foreach (array('search_results', 'catalog_listing') as $kind) {
		pg_assert_true((bool) preg_match('/\n        ' . $kind . ": \\[[^\\n]*\\n(?:[^\\n]*\\n){0,8}?\\s*\\['has_prev_page',[^\\]]*\\],\\s*\\['has_next_page',[^\\]]*\\],\\s*\\['has_more_pages',[^\\]]*\\]\\s*\\]/", $js), $kind . ' lists the paging flags');
	}
}
