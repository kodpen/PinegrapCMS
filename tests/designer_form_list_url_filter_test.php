<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the list filters of the visual editor's form list widget that
 * take their value from the page address (dynamic value 'url parameter',
 * archive pages such as /author?author=Jane): the parameter names a filter
 * may read (pg_sw_url_param_name(), the reserved list and its twin in
 * style_designer.js), the values it accepts (pg_sw_url_param_value()), who
 * passes an address (pg_sw_filter_request()), the SQL it adds and when it is
 * left out (pg_sw_form_list_filter_sql()), the head of such a page
 * (pg_sw_filter_title(), pg_sw_url_filter_query(), pg_sw_url_filter_noindex(),
 * pg_sw_url_filter_state()) and the editor offering it on lists only.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A filter on the reference code (a standard field: no form field lookup)
// whose value comes from ?$param=.
function _url_filter_row($param, $operator = 'is equal to', $value = '')
{
	return array('field' => 'reference_code', 'operator' => $operator, 'value' => $value, 'dynamic_value' => 'url parameter', 'dynamic_value_attribute' => $param);
}

// e() asks the connection class; without a database it falls back to
// addslashes() once the class exists. The class has the shape db_connect()
// gives it.
function _url_filter_db_stub()
{
	if (!class_exists('db')) {
		class db
		{
			public static $con;
		}
	}
}

function _url_filter_js()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
}

// -- parameter names ---------------------------------------------------------

function test_url_filter_name_accepts_plain_names()
{
	pg_assert_same('author', pg_sw_url_param_name('author'));
	pg_assert_same('tag_2', pg_sw_url_param_name('tag_2'));
	pg_assert_same('author', pg_sw_url_param_name(' Author '));
	pg_assert_same(str_repeat('a', 32), pg_sw_url_param_name(str_repeat('a', 32)));
}

function test_url_filter_name_refuses_malformed()
{
	foreach (array('', '1x', 'a-b', 'a b', 'ş', str_repeat('a', 33)) as $name) {
		pg_assert_same('', pg_sw_url_param_name($name), var_export($name, true));
	}
	pg_assert_same('', pg_sw_url_param_name(array('author')));
	pg_assert_same('', pg_sw_url_param_name(null));
}

// The software's own parameters: t, a and o write a tracking, affiliate or
// offer code; the rest switch the output or belong to the widgets.
function test_url_filter_name_refuses_reserved()
{
	foreach (array('page', 'page_number', 'query', 'r', 'rss', 't', 'a', 'o', 'pg_lang', 'highlight', 'send_to', 'utm_source', 'pg_x', 'Q') as $name) {
		pg_assert_same('', pg_sw_url_param_name($name), $name);
	}
	foreach (pg_sw_url_param_reserved() as $name) {
		pg_assert_same('', pg_sw_url_param_name($name), $name);
	}
}

// -- values ------------------------------------------------------------------

function test_url_filter_value_absent_or_unusable()
{
	pg_assert_same('', pg_sw_url_param_value(array(), 'author'));
	pg_assert_same('', pg_sw_url_param_value(array('author' => ''), 'author'));
	pg_assert_same('', pg_sw_url_param_value(array('author' => '  '), 'author'));
	pg_assert_same('', pg_sw_url_param_value(array('author' => array('x')), 'author'));
	pg_assert_same('', pg_sw_url_param_value(array('author' => "\xff"), 'author'));
	pg_assert_same('', pg_sw_url_param_value(array('author' => "a\tb"), 'author'));
	pg_assert_same('', pg_sw_url_param_value(array('author' => 'x'), ''));
	pg_assert_same('', pg_sw_url_param_value(null, 'author'));
}

// Control and format characters (C1, zero-width space, byte order mark,
// word joiner, bidi overrides) and line separators are no value: the
// collation ignores most of them, so they would print text no record holds.
function test_url_filter_value_refuses_invisible_characters()
{
	foreach (array("\u{202E}", "\u{200B}", "\u{FEFF}", "\u{2060}", "\u{0085}", "\u{2028}", "\u{2066}", "\u{00AD}") as $char) {
		pg_assert_same('', pg_sw_url_param_value(array('author' => 'Pub' . $char . 'lished'), 'author'), bin2hex($char));
	}
	// Letters with combining marks and other scripts stay values.
	pg_assert_same("Jose\u{0301}", pg_sw_url_param_value(array('author' => "Jose\u{0301}"), 'author'));
	pg_assert_same('Gülşen İnce', pg_sw_url_param_value(array('author' => 'Gülşen İnce'), 'author'));
}

function test_url_filter_value_trimmed_and_capped()
{
	pg_assert_same('Jane', pg_sw_url_param_value(array('author' => ' Jane '), 'author'));
	pg_assert_same('5', pg_sw_url_param_value(array('n' => "\n5"), 'n'));
	$long = pg_sw_url_param_value(array('tag' => str_repeat('ş', 150)), 'tag');
	pg_assert_same(100, mb_strlen($long, 'UTF-8'));
	pg_assert_same(str_repeat('ş', 100), $long);
}

// -- who passes an address -----------------------------------------------------

// An e-mail body and a render inside another request (SEO pass, preview)
// have no address of their own.
function test_url_filter_request_none_in_email_or_seo()
{
	$saved_get = $_GET;
	$_GET = array('author' => 'Jane');

	pg_assert_same(array('author' => 'Jane'), pg_sw_filter_request());

	$previous = pg_sw_render_context(array('email' => true));
	pg_assert_same(array(), pg_sw_filter_request());
	pg_sw_render_context($previous);

	$seo = pg_seo_rendering();
	pg_seo_rendering(true);
	pg_assert_same(array(), pg_sw_filter_request());
	pg_seo_rendering($seo);

	$_GET = $saved_get;
}

// The search indexer renders every page from its own request: the address
// that started the run must not narrow the lists it indexes. Defining
// UPDATE_SEARCH_INDEX here would change every later test, so the guard is
// read from the source.
function test_url_filter_request_none_in_search_index()
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets.php');
	pg_assert_true((bool) preg_match('/function pg_sw_filter_request\(\)\s*\{(.*?)\n\}/s', $src, $m), 'pg_sw_filter_request() is found');
	pg_assert_true(strpos($m[1], "if (defined('UPDATE_SEARCH_INDEX') && UPDATE_SEARCH_INDEX === true) return array();") !== false, 'the indexer gets no address');
}

// -- the SQL -------------------------------------------------------------------

function test_url_filter_sql_value_applied()
{
	_url_filter_db_stub();
	$sql = pg_sw_form_list_filter_sql(12, array(_url_filter_row('author')), array('author' => 'Jane'));
	pg_assert_same(" AND (IFNULL(forms.reference_code, '') = 'Jane')", $sql['where']);
	pg_assert_same(array('author' => 'Jane'), $sql['params']);
	pg_assert_false($sql['inexact']);
	pg_assert_same(0, $sql['address']);
	pg_assert_same(array('reference_code' => true), $sql['tokens']);

	$quoted = pg_sw_form_list_filter_sql(12, array(_url_filter_row('author')), array('author' => "O'Brien"));
	pg_assert_same(" AND (IFNULL(forms.reference_code, '') = 'O\\'Brien')", $quoted['where']);

	$like = pg_sw_form_list_filter_sql(12, array(_url_filter_row('tag', 'contains')), array('tag' => '50%'));
	pg_assert_same(" AND (IFNULL(forms.reference_code, '') LIKE '%50\\\\%%')", $like['where']);
	pg_assert_true($like['inexact']);
}

// "is equal to" compares under the column's collation (case, accents and
// combining marks ignored), so the builder also returns the comparisons
// byte for byte; the list prints a value only when a record passes them.
function test_url_filter_sql_exact_twin()
{
	_url_filter_db_stub();
	$sql = pg_sw_form_list_filter_sql(12, array(_url_filter_row('author')), array('author' => "O'Brien"));
	pg_assert_same(" AND (CAST(IFNULL(forms.reference_code, '') AS BINARY) = CAST('O\\'Brien' AS BINARY))", $sql['exact']);

	// A case variant reaches the SQL as typed; the byte comparison decides.
	$sql = pg_sw_form_list_filter_sql(12, array(_url_filter_row('author')), array('author' => 'jane'));
	pg_assert_same(array('author' => 'jane'), $sql['params']);
	pg_assert_contains("CAST('jane' AS BINARY)", $sql['exact']);
	pg_assert_false($sql['inexact']);
	$sql['inexact'] = true;
	pg_assert_same('', pg_sw_url_filter_value($sql, 4));

	// Other operators have no twin: they are inexact already.
	pg_assert_same('', pg_sw_form_list_filter_sql(12, array(_url_filter_row('tag', 'contains')), array('tag' => 'x'))['exact']);
	pg_assert_same('', pg_sw_form_list_filter_sql(12, array(_url_filter_row('tag', 'is equal to', 'Fixed')), array('tag' => 'x'))['exact']);

	// The feed's clause carries it through.
	$feed = pg_sw_list_record_filter_clause(12, array('filters' => array(_url_filter_row('author'))), array(), 0, array('author' => 'Jane'));
	pg_assert_same(" AND (CAST(IFNULL(forms.reference_code, '') AS BINARY) = CAST('Jane' AS BINARY))", $feed['exact']);
}

// A date-and-time value compared with "is equal to" names the page in the
// stored form (2026-01-01T10:00 is 2026-01-01 10:00:00), which is also
// what the byte comparison and the title use.
function test_url_filter_sql_date_value_stored_form()
{
	_url_filter_db_stub();
	$row = array('field' => 'submitted_date_and_time', 'operator' => 'is equal to', 'value' => '', 'dynamic_value' => 'url parameter', 'dynamic_value_attribute' => 'at');
	$sql = pg_sw_form_list_filter_sql(12, array($row), array('at' => '2026-01-01T10:00'));
	pg_assert_same(array('at' => '2026-01-01 10:00:00'), $sql['params']);
	pg_assert_contains("= '2026-01-01 10:00:00')", $sql['where']);
	pg_assert_contains("CAST('2026-01-01 10:00:00' AS BINARY)", $sql['exact']);
	pg_assert_same('at=2026-01-01%2010%3A00%3A00', pg_sw_url_filter_query($sql));
}

// No usable value: the filter is left out and every record is listed.
function test_url_filter_sql_absent_value_left_out()
{
	_url_filter_db_stub();
	foreach (array(array(), array('author' => ''), array('author' => array('x')), array('author' => "\x01")) as $request) {
		$sql = pg_sw_form_list_filter_sql(12, array(_url_filter_row('author')), $request);
		pg_assert_same('', $sql['where'], var_export($request, true));
		pg_assert_same(array(), $sql['params']);
		pg_assert_same(array(), $sql['tokens']);
		pg_assert_same(0, $sql['missing']);
	}
	// A reserved name never reads the address.
	$sql = pg_sw_form_list_filter_sql(12, array(_url_filter_row('t')), array('t' => 'climate'));
	pg_assert_same('', $sql['where']);
	pg_assert_same(array(), $sql['params']);
}

// No request (sitemap, record view, search index, preview): left out and
// counted, whatever $_GET holds.
function test_url_filter_sql_null_request_reads_no_address()
{
	$saved_get = $_GET;
	$_GET = array('author' => 'Jane');
	$sql = pg_sw_form_list_filter_sql(12, array(_url_filter_row('author')));
	pg_assert_same('', $sql['where']);
	pg_assert_same(1, $sql['address']);
	pg_assert_same(array(), $sql['params']);
	$_GET = $saved_get;
}

// A fixed value wins over the address, as over every dynamic value.
function test_url_filter_sql_fixed_value_wins()
{
	_url_filter_db_stub();
	$sql = pg_sw_form_list_filter_sql(12, array(_url_filter_row('author', 'is equal to', 'Fixed')), array('author' => 'Jane'));
	pg_assert_same(" AND (IFNULL(forms.reference_code, '') = 'Fixed')", $sql['where']);
	pg_assert_same(array(), $sql['params']);
	$sql = pg_sw_form_list_filter_sql(12, array(_url_filter_row('author', 'is equal to', 'Fixed')));
	pg_assert_same(0, $sql['address']);
}

// Compared by size, only a plain decimal goes into the SQL unquoted.
function test_url_filter_sql_size_operator_guard()
{
	_url_filter_db_stub();
	$row = _url_filter_row('n', 'is less than');
	pg_assert_same(' AND (TRUE = FALSE)', pg_sw_form_list_filter_sql(12, array($row), array('n' => '1e400'))['where']);
	pg_assert_same(' AND (TRUE = FALSE)', pg_sw_form_list_filter_sql(12, array($row), array('n' => '.5'))['where']);
	pg_assert_same(" AND (IFNULL(forms.reference_code, '') < 5)", pg_sw_form_list_filter_sql(12, array($row), array('n' => '5'))['where']);
	pg_assert_same(" AND (IFNULL(forms.reference_code, '') < -2.25)", pg_sw_form_list_filter_sql(12, array($row), array('n' => '-2.25'))['where']);
	pg_assert_same(" AND (IFNULL(forms.reference_code, '') < 'abc')", pg_sw_form_list_filter_sql(12, array($row), array('n' => 'abc'))['where']);
	pg_assert_same(" AND (IFNULL(forms.reference_code, '') < 5)", pg_sw_form_list_filter_sql(12, array($row), array('n' => "\n5"))['where']);
	$guarded = pg_sw_form_list_filter_sql(12, array($row), array('n' => '1e400'));
	pg_assert_same(array('n' => '1e400'), $guarded['params']);
	pg_assert_true($guarded['inexact']);
}

// A date-and-time field turns T into a space before the guard looks, so
// '1e400T' ('1e400 ', a number on PHP 8) and 'T1e400' (' 1e400', a number
// on every version) never reach the SQL unquoted: the filter matches
// nothing, or (PHP 7, where a trailing space is not numeric) compares the
// quoted text.
function test_url_filter_sql_size_guard_after_date_rewrite()
{
	_url_filter_db_stub();
	$row = array('field' => 'submitted_date_and_time', 'operator' => 'is greater than', 'value' => '', 'dynamic_value' => 'url parameter', 'dynamic_value_attribute' => 'since');
	foreach (array('1e400T', 'T1e400', '9e999T', '5T') as $value) {
		$sql = pg_sw_form_list_filter_sql(12, array($row), array('since' => $value));
		pg_assert_true($sql['where'] === ' AND (TRUE = FALSE)' || strpos($sql['where'], "> '") !== false, $value . ': ' . $sql['where']);
		pg_assert_same(array('since' => $value), $sql['params']);
	}
	pg_assert_same(' AND (TRUE = FALSE)', pg_sw_form_list_filter_sql(12, array($row), array('since' => 'T1e400'))['where']);
	$date = pg_sw_form_list_filter_sql(12, array($row), array('since' => '2026-01-01T10:00'))['where'];
	pg_assert_contains("> '2026-01-01 10:00:00')", $date);
}

// A dynamic value the core does not know compares with '' as before.
function test_url_filter_sql_unknown_dynamic_unchanged()
{
	_url_filter_db_stub();
	$row = array('field' => 'reference_code', 'operator' => 'is equal to', 'value' => '', 'dynamic_value' => 'next week', 'dynamic_value_attribute' => '');
	pg_assert_same(" AND (IFNULL(forms.reference_code, '') = '')", pg_sw_form_list_filter_sql(12, array($row), array())['where']);
}

// A record view never reads the address: such a filter closes it, and a
// list's records in the sitemap (no request) keep the record view's part.
function test_url_filter_record_view_closes()
{
	$row = _url_filter_row('author');
	$strict = pg_sw_record_filter_clause(12, array($row), 0, true);
	pg_assert_true($strict['closed']);
	pg_assert_false(pg_sw_record_passes_filters(12, array($row), 34));
	pg_assert_true(pg_sw_list_record_filter_clause(12, array(), array('filters' => array($row)))['closed']);

	// The list's own URL filter: left out without a request, applied with one.
	$list = pg_sw_list_record_filter_clause(12, array('filters' => array($row)), array());
	pg_assert_false($list['closed']);
	pg_assert_same('', $list['where']);

	_url_filter_db_stub();
	$feed = pg_sw_list_record_filter_clause(12, array('filters' => array($row)), array(), 0, array('author' => 'Jane'));
	pg_assert_same(" AND (IFNULL(forms.reference_code, '') = 'Jane')", $feed['where']);
	pg_assert_same(array('author' => 'Jane'), $feed['params']);
}

// -- the page head -------------------------------------------------------------

function test_url_filter_title()
{
	pg_assert_same('', pg_sw_filter_title('Articles by {value}', ''));
	pg_assert_same('Jane', pg_sw_filter_title('', 'Jane'));
	pg_assert_same('Articles by Jane', pg_sw_filter_title('Articles by {value}', 'Jane'));
	pg_assert_same('Jane | Archive', pg_sw_filter_title('| Archive', 'Jane'));
	pg_assert_same('', pg_sw_list_filter_title(array()));
	pg_assert_same('', pg_sw_list_filter_title(array('filter_title' => array('x'))));
	pg_assert_same(120, mb_strlen(pg_sw_list_filter_title(array('filter_title' => str_repeat('ğ', 130))), 'UTF-8'));
}

// Only a value compared with "is equal to" that found records is printed:
// any text matches the other operators, so a crafted link could otherwise
// put its own words in the title, the heading and the social card.
function test_url_filter_value_printed_only_for_exact_matches()
{
	pg_assert_same('Jane', pg_sw_url_filter_value(array('params' => array('author' => 'Jane'), 'inexact' => false), 3));
	pg_assert_same('Jane, x', pg_sw_url_filter_value(array('params' => array('author' => 'Jane', 'tag' => 'x'), 'inexact' => false), 1));
	pg_assert_same('', pg_sw_url_filter_value(array('params' => array('author' => 'Jane'), 'inexact' => false), 0));
	pg_assert_same('', pg_sw_url_filter_value(array('params' => array('since' => '!</script>'), 'inexact' => true), 9));
	pg_assert_same('', pg_sw_url_filter_value(array('params' => array(), 'inexact' => false), 9));
	pg_assert_same('', pg_sw_url_filter_value(null, 9));

	// From the SQL builder: "is not equal to" with a javascript: value.
	_url_filter_db_stub();
	$sql = pg_sw_form_list_filter_sql(12, array(_url_filter_row('x', 'is not equal to')), array('x' => 'javascript:alert(1)'));
	pg_assert_same('', pg_sw_url_filter_value($sql, 5));
	$sql = pg_sw_form_list_filter_sql(12, array(_url_filter_row('author')), array('author' => 'Jane'));
	pg_assert_same('Jane', pg_sw_url_filter_value($sql, 5));
}

// The page number goes into the canonical as the page the address asked
// for (not a clamped one), with or without an address value, and not
// while searching.
function test_url_filter_page()
{
	$applied = array('params' => array('author' => 'Jane'));
	pg_assert_same(2, pg_sw_url_filter_page($applied, array('page_number' => '2'), 2, 5, false));
	pg_assert_same(2, pg_sw_url_filter_page(array('params' => array()), array('page_number' => '2'), 2, 5, false), 'a plain list keeps its later page');
	pg_assert_same(3, pg_sw_url_filter_page(null, array('page_number' => '3'), 3, 5, false), 'no filter at all');
	pg_assert_same(0, pg_sw_url_filter_page(array('params' => array()), array('page_number' => '2'), 2, 5, true), 'a search is not a page of the list');
	pg_assert_same(0, pg_sw_url_filter_page($applied, array('page_number' => '2'), 2, 5, true));
	pg_assert_same(0, pg_sw_url_filter_page($applied, array('page_number' => '1'), 1, 5, false));
	pg_assert_same(0, pg_sw_url_filter_page($applied, array('page_number' => '7'), 3, 3, false));
	pg_assert_same(0, pg_sw_url_filter_page($applied, array('page_number' => array('2')), 2, 5, false));
	pg_assert_same(0, pg_sw_url_filter_page($applied, array(), 2, 5, false));
	pg_assert_same(0, pg_sw_url_filter_page($applied, array('page_number' => '2'), 2, 1, false));
}

// The search-sections JSON-LD carries the page title, which a list can take
// from its address: it can never close its <script> element.
function test_url_filter_sections_jsonld_escaped()
{
	$json = pg_search_sections_jsonld_json(array('name' => '!</script><script>alert(1)</script>', 'url' => 'https://example.com/a?b=c&d=e', 'title' => 'Şule'));
	pg_assert_false(strpos($json, '</script') !== false || strpos($json, '<') !== false, $json);
	pg_assert_contains('\u003C/script\u003E', $json);
	pg_assert_contains('https://example.com/a?b=c\u0026d=e', $json);
	pg_assert_contains('Şule', $json);
	pg_assert_same(array('name' => '!</script><script>alert(1)</script>', 'url' => 'https://example.com/a?b=c&d=e', 'title' => 'Şule'), json_decode($json, true));
}

// Carets cannot form a token, and the value is escaped once.
function test_url_filter_value_html()
{
	pg_assert_same('&lt;b&gt; &amp;#94;', pg_sw_filter_value_html('<b> &#94;'));
	pg_assert_same('&#94;&#94;reference_code&#94;&#94;', pg_sw_filter_value_html('^^reference_code^^'));
}

function test_url_filter_query()
{
	pg_assert_same('', pg_sw_url_filter_query(array('params' => array(), 'page_number' => 0)));
	pg_assert_same('', pg_sw_url_filter_query(array('params' => array(), 'page_number' => 1)));
	// A later page is its own address without an address value too.
	pg_assert_same('page_number=2', pg_sw_url_filter_query(array('params' => array(), 'page_number' => 2)));
	pg_assert_same('topics_page=4', pg_sw_url_filter_query(array('params' => array(), 'page_number' => 4, 'page_param' => 'topics_page')));
	pg_assert_same('', pg_sw_url_filter_query(array('params' => array(), 'page_number' => 2), false), 'the feed has no page');
	pg_assert_same('author=Jane&page_number=2', pg_sw_url_filter_query(array('params' => array('author' => 'Jane'), 'page_number' => 2)));
	pg_assert_same(
		'author=Jane%20Cooper&tag=a%26b&page_number=3',
		pg_sw_url_filter_query(array('params' => array('tag' => 'a&b', 'author' => 'Jane Cooper'), 'page_number' => 3))
	);
	pg_assert_same('author=%C5%9Fule', pg_sw_url_filter_query(array('params' => array('author' => 'şule'), 'page_number' => 3), false));
	pg_assert_same('', pg_sw_url_filter_query(null));
}

function test_url_filter_noindex()
{
	pg_assert_false(pg_sw_url_filter_noindex(array('params' => array(), 'empty' => true, 'inexact' => true)));
	pg_assert_true(pg_sw_url_filter_noindex(array('params' => array('a1' => 'x'), 'empty' => true, 'inexact' => false)));
	pg_assert_true(pg_sw_url_filter_noindex(array('params' => array('a1' => 'x'), 'empty' => false, 'inexact' => true)));
	pg_assert_false(pg_sw_url_filter_noindex(array('params' => array('a1' => 'x'), 'empty' => false, 'inexact' => false)));
	pg_assert_false(pg_sw_url_filter_noindex(null));
}

function test_url_filter_state_registry()
{
	$state = pg_sw_url_filter_state(false);
	pg_assert_same(array('params' => array(), 'inexact' => false, 'empty' => false, 'title' => '', 'title_configured' => false, 'page_number' => 0), $state);

	// No params: an empty list says nothing about the address.
	$state = pg_sw_url_filter_state(array('params' => array(), 'empty' => true));
	pg_assert_false($state['empty']);

	pg_sw_url_filter_state(array('params' => array('author' => 'Jane'), 'title' => 'Articles by Jane', 'title_configured' => true));
	$state = pg_sw_url_filter_state(array('params' => array('author' => 'Other', 'tag' => 'x'), 'inexact' => true, 'title' => 'Second', 'title_configured' => true));
	pg_assert_same(array('author' => 'Jane', 'tag' => 'x'), $state['params']);
	pg_assert_same('Articles by Jane', $state['title']);
	pg_assert_true($state['inexact']);
	pg_assert_false($state['empty']);

	pg_sw_url_filter_state(array('page_number' => 3));
	$state = pg_sw_url_filter_state(array('page_number' => 5));
	pg_assert_same(3, $state['page_number']);
	pg_assert_same('author=Jane&tag=x&page_number=3', pg_sw_url_filter_query($state));

	// A render inside another request adds nothing.
	pg_sw_url_filter_state(false);
	$seo = pg_seo_rendering();
	pg_seo_rendering(true);
	$state = pg_sw_url_filter_state(array('params' => array('author' => 'Jane')));
	pg_seo_rendering($seo);
	pg_assert_same(array(), $state['params']);

	$previous = pg_sw_render_context(array('email' => true));
	$state = pg_sw_url_filter_state(array('params' => array('author' => 'Jane')));
	pg_sw_render_context($previous);
	pg_assert_same(array(), $state['params']);

	pg_sw_url_filter_state(false);
}

// Two lists read the same address: an archive with its own title and a
// "most read" box without one. The box decides neither the robots nor the
// title, whichever comes first on the page.
function test_url_filter_state_two_lists()
{
	// The box first, empty; the archive found the records.
	pg_sw_url_filter_state(false);
	pg_sw_url_filter_state(array('params' => array('headline' => 'Story'), 'empty' => true, 'inexact' => true));
	$state = pg_sw_url_filter_state(array('params' => array('headline' => 'Story'), 'title' => 'Stories by Story', 'title_configured' => true));
	pg_assert_false($state['empty']);
	pg_assert_false($state['inexact']);
	pg_assert_false(pg_sw_url_filter_noindex($state));
	pg_assert_same('Stories by Story', $state['title']);

	// The archive first, the box empty after it.
	pg_sw_url_filter_state(false);
	pg_sw_url_filter_state(array('params' => array('headline' => 'Story'), 'title' => 'Stories by Story', 'title_configured' => true));
	$state = pg_sw_url_filter_state(array('params' => array('headline' => 'Story'), 'empty' => true));
	pg_assert_false(pg_sw_url_filter_noindex($state));
	pg_assert_same('Stories by Story', $state['title']);

	// The box first with records and the bare value: the archive's own
	// title wins.
	pg_sw_url_filter_state(false);
	pg_sw_url_filter_state(array('params' => array('headline' => 'Story'), 'title' => 'Story'));
	$state = pg_sw_url_filter_state(array('params' => array('headline' => 'Story'), 'title' => 'Stories by Story', 'title_configured' => true));
	pg_assert_same('Stories by Story', $state['title']);
	pg_assert_true($state['title_configured']);
	// A later configured title does not replace the first one.
	$state = pg_sw_url_filter_state(array('params' => array('headline' => 'Story'), 'title' => 'Third', 'title_configured' => true));
	pg_assert_same('Stories by Story', $state['title']);

	// A list that found records with an inexact comparison keeps the page
	// out of the index; every list empty does too.
	pg_sw_url_filter_state(false);
	pg_sw_url_filter_state(array('params' => array('headline' => 'Story'), 'title' => 'Stories by Story', 'title_configured' => true));
	$state = pg_sw_url_filter_state(array('params' => array('word' => 'x'), 'inexact' => true));
	pg_assert_true(pg_sw_url_filter_noindex($state));
	pg_sw_url_filter_state(false);
	pg_sw_url_filter_state(array('params' => array('headline' => 'Nobody'), 'empty' => true));
	$state = pg_sw_url_filter_state(array('params' => array('headline' => 'Nobody'), 'empty' => true));
	pg_assert_true($state['empty']);
	pg_assert_true(pg_sw_url_filter_noindex($state));

	pg_sw_url_filter_state(false);
}

// A list marks only the flags it evaluates: an element pasted from a record
// page with record_found stays drawn, as before lists had flags.
function test_url_filter_list_marks_only_its_flags()
{
	$node = function ($flag) {
		return array('type' => 'content', 'props' => array('contentType' => 'paragraph', '_bindings' => array('eo_visible_if' => $flag)), 'children' => array());
	};
	$tree = array('type' => 'section', 'props' => array(), 'children' => array($node('record_found'), $node('has_filter_value')));
	_pg_sw_mark_visibility($tree, array('has_filter_value', 'no_filter_value'));
	pg_assert_same(4, count($tree['children']));
	pg_assert_same('record_found', $tree['children'][0]['props']['_bindings']['eo_visible_if']);
	pg_assert_true(strpos($tree['children'][1]['props']['html'], '<!--pg-vis:has_filter_value:') === 0);

	// Without a list of flags every flag is marked (record and cart views).
	$tree = array('type' => 'section', 'props' => array(), 'children' => array($node('record_found')));
	_pg_sw_mark_visibility($tree);
	pg_assert_same(3, count($tree['children']));
}

// -- the editor ----------------------------------------------------------------

// The editor refuses the same names the server does.
function test_url_filter_editor_reserved_twin()
{
	$js = _url_filter_js();
	pg_assert_true((bool) preg_match('/var _SW_URL_PARAM_RESERVED = \[(.*?)\];/s', $js, $m), 'the reserved list is found in style_designer.js');
	preg_match_all("/'([a-z0-9_]+)'/", isset($m[1]) ? $m[1] : '', $names);
	pg_assert_same(pg_sw_url_param_reserved(), $names[1]);
	pg_assert_contains("if (n.indexOf('utm_') === 0 || n.indexOf('pg_') === 0) return '';", $js);
}

// Lists offer the address as a dynamic value; record views do not.
function test_url_filter_editor_lists_only()
{
	$js = _url_filter_js();
	pg_assert_contains("if (withUrl) o.push(['url parameter', _sdT('URL parameter')]);", $js);
	pg_assert_contains('_swFormListFiltersHtml(sid, _flvFilters, _ff, _fieldsLoading, null, true)', $js);

	$start = strpos($js, 'if (_isFormItemView && _curFormId > 0) {');
	pg_assert_true($start !== false, 'the record-view panel is found');
	$end = strpos($js, '// ── Records in the translation', $start);
	$panel = substr($js, $start, $end - $start);
	$call = strpos($panel, '_swFormListFiltersHtml(');
	pg_assert_true($call !== false, 'the record-view panel draws the filter rows');
	$statement = substr($panel, $call, strpos($panel, ";\n", $call) - $call);
	pg_assert_false(strpos($statement, ', true)') !== false, 'the record-view panel does not pass withUrl');
}

// The value token and the two flags are offered on list elements.
function test_url_filter_editor_token_and_flags()
{
	$js = _url_filter_js();
	pg_assert_contains("['__filter_value', _sdT('Value from the page address (when is equal to finds records)')]", $js);
	pg_assert_contains('var SW_BUILTIN_FIELD_OPTIONS = SW_STANDARD_FIELD_OPTIONS.concat(SW_FORM_LIST_VIEW_TOKENS);', $js);
	pg_assert_true((bool) preg_match("/form_list_view: \[\s*\['has_filter_value',[^\]]*\],\s*\['no_filter_value',/", $js), 'form_list_view lists both flags');

	// A first-time visitor on the bare archive sees the no-value parts, and
	// so does the template installer's preview.
	if (function_exists('_pg_tpl_preview_flag_on')) {
		pg_assert_true(_pg_tpl_preview_flag_on('no_filter_value'));
		pg_assert_false(_pg_tpl_preview_flag_on('has_filter_value'));
	}
}
