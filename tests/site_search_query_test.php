<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the advanced site search over search_items
 * (includes/fn/site_search.php): a visitor's text becomes a valid fulltext
 * string (a lone "*" or "+*" ended the page with "Query failed."), a page
 * binned or removed after the index run is no longer found, a record result
 * is not labelled a page, and installing a design template makes the index
 * job due. The queries need a database, so their call sites are read from
 * the source.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The source of one function of a product file, up to the next one.
function _site_search_query_fn($file, $name)
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
	$start = strpos($src, 'function ' . $name . '(');
	pg_assert_true($start !== false, $name . ' is found');
	$end = strpos($src, "\nfunction ", (int) $start + 10);
	return ($end === false) ? substr($src, (int) $start) : substr($src, (int) $start, (int) $end - (int) $start);
}

// Operators without a word leave nothing to search.
function test_site_search_query_lone_operators_are_dropped()
{
	foreach (array('', '   ', '*', '+*', '**', '"', '""', '-', '+', '~', '()', '@', '<>', '+-*', '" * "') as $q) {
		pg_assert_same('', pg_search_boolean_terms($q), var_export($q, true));
	}
}

// A word keeps a leading + or - and a trailing wildcard.
function test_site_search_query_word_operators_are_kept()
{
	pg_assert_same('foo*', pg_search_boolean_terms('foo*'));
	pg_assert_same('foo*', pg_search_boolean_terms('foo***'));
	pg_assert_same('+foo -bar', pg_search_boolean_terms('+foo -bar'));
	pg_assert_same('+foo', pg_search_boolean_terms('+-foo'));
	pg_assert_same('foo bar', pg_search_boolean_terms('foo bar'));
	pg_assert_same('foo bar', pg_search_boolean_terms('(foo) ~bar'));
}

// A single word is an exact phrase, as before; so is a word with a joining
// character or with an inner operator.
function test_site_search_query_phrases()
{
	pg_assert_same('"foo"', pg_search_boolean_terms('foo'));
	pg_assert_same('"multi-recipient"', pg_search_boolean_terms('multi-recipient'));
	pg_assert_same('"a b"', pg_search_boolean_terms('a*b'));
	pg_assert_same('"a"', pg_search_boolean_terms('*a'));
	pg_assert_same('"foo bar" baz', pg_search_boolean_terms('"foo* bar" baz'));
	pg_assert_same('+"foo bar"', pg_search_boolean_terms('+"foo bar"'));
	pg_assert_same('"foo"', pg_search_boolean_terms('"foo'));
	pg_assert_same('"user example.com"', pg_search_boolean_terms('user@example.com'));
}

function test_site_search_query_turkish_letters()
{
	pg_assert_same('"eşitleme"', pg_search_boolean_terms('eşitleme'));
	pg_assert_same('Bug avı', pg_search_boolean_terms('Bug avı'));
	pg_assert_same('+çığ ÖZEL*', pg_search_boolean_terms('+çığ ÖZEL*'));
	pg_assert_same('"Pinegrap\'in"', pg_search_boolean_terms("Pinegrap'in"));
}

function test_site_search_query_mode_and_score()
{
	pg_assert_true(pg_search_needs_boolean_mode('"foo"'));
	pg_assert_true(pg_search_needs_boolean_mode('foo*'));
	pg_assert_true(pg_search_needs_boolean_mode('+foo bar'));
	pg_assert_false(pg_search_needs_boolean_mode('foo bar'));
	pg_assert_same('foo bar', pg_search_score_terms('+foo -bar'));
	pg_assert_same('multi-recipient', pg_search_score_terms('"multi-recipient"'));
	pg_assert_same('foo bar baz', pg_search_score_terms('+"foo bar" baz*'));
}

function test_site_search_query_live_items_sql()
{
	$none = pg_search_items_live_sql(array());
	pg_assert_contains("search_items.page_id = 0 OR EXISTS", $none);
	pg_assert_contains("folder.folder_archived = '0'", $none);
	pg_assert_false(strpos($none, 'NOT IN') !== false, 'no bin: no NOT IN');

	$sql = pg_search_items_live_sql(array(7, '9', 0, 7));
	pg_assert_contains("page.page_folder NOT IN (7,9)", $sql);
	pg_assert_contains("search_items.folder_id NOT IN (7,9)", $sql);
	pg_assert_same(0, strpos($sql, ' AND '));
}

function test_site_search_query_item_type()
{
	pg_assert_same('file', pg_search_item_type(array('url' => 'a.pdf', 'page_id' => '0', 'page_name' => null)));
	pg_assert_same('page', pg_search_item_type(array('url' => 'hakk%C4%B1m%C4%B1zda', 'page_id' => '3', 'page_name' => 'hakkımızda')));
	pg_assert_same('record', pg_search_item_type(array('url' => 'konu-3?r=ABC', 'page_id' => '3', 'page_name' => 'konu-3')));
	pg_assert_same('record', pg_search_item_type(array('url' => 'konu-3/bug-avi', 'page_id' => '3', 'page_name' => 'konu-3')));
	pg_assert_same('page', pg_search_item_type(array('url' => 'x', 'page_id' => '3', 'page_name' => null)));
}

// Both search paths clean the text, filter live items and survive a failed
// MATCH; no visitor text is concatenated into AGAINST().
function test_site_search_query_call_sites()
{
	$widget = _site_search_query_fn('includes/fn/widgets.php', '_pg_sw_search_collect');
	$legacy = _site_search_query_fn('get_search_results.php', 'get_search_results');
	foreach (array('widget' => $widget, 'legacy' => $legacy) as $where => $body) {
		pg_assert_contains('pg_search_boolean_terms($query)', $body, $where);
		pg_assert_contains('pg_search_items_live_sql(pg_recycle_bin_folder_ids())', $body, $where);
		pg_assert_contains('pg_search_fulltext_items(', $body, $where);
		pg_assert_false((bool) preg_match('/AGAINST\s*\(\s*\'"\s*\.\s*e\(/', $body), $where . ': no e() inside AGAINST');
		pg_assert_false((bool) preg_match('/=\s*db_items\(\s*"SELECT[^"]*FROM search_items/', $body), $where . ': no db_items over search_items');
	}
	pg_assert_contains("'__result_type_label'   => (isset(\$r['type_label'])", _site_search_query_fn('includes/fn/widgets.php', '_render_system_widget_search_results'));
}

function test_site_search_query_template_install_refreshes_index()
{
	$body = _site_search_query_fn('includes/fn/designer.php', 'pg_design_template_install');
	pg_assert_contains('pg_search_index_refresh_soon()', $body);
	$fn = _site_search_query_fn('includes/fn/site_search.php', 'pg_search_index_refresh_soon');
	pg_assert_contains("job_name = 'update_search_index'", $fn);
	pg_assert_contains('LEAST(last_run_at', $fn);
}
