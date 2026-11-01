<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for writing a visitor's site search to the visit
 * (pg_search_visit_terms_append(), pg_search_record_visit_term()): the search
 * widget of designed pages and the legacy Search Results page both add the
 * term to visitors.site_search_terms, and a later results page of the widget
 * does not add it again. The write needs a database, so its call sites are
 * read from the source.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_search_visit_terms_append_joins_with_a_bar()
{
	pg_assert_same('kahve', pg_search_visit_terms_append('', 'kahve'));
	pg_assert_same('kahve|menü', pg_search_visit_terms_append('kahve', 'menü'));
	pg_assert_same('kahve', pg_search_visit_terms_append('kahve', '   '), 'an empty search adds nothing');
	pg_assert_same('kahve|çay', pg_search_visit_terms_append('kahve', ' çay '), 'the term is trimmed');
}

function test_search_visit_terms_append_stays_inside_the_column()
{
	// visitors.site_search_terms is varchar(255) and the session's sql_mode is
	// not strict: a longer value was cut by MySQL, half a term was stored and
	// every later search of the visit was lost.
	$full = rtrim(str_repeat('kahvaltı|', 28), '|');
	pg_assert_same(251, mb_strlen($full, 'UTF-8'));
	pg_assert_same($full, pg_search_visit_terms_append($full, 'menü'), 'a term that does not fit is not added');
	pg_assert_same($full . '|çay', pg_search_visit_terms_append($full, 'çay'), 'a term that fits exactly is added');

	$long = str_repeat('ş', 300);
	pg_assert_same(str_repeat('ş', 255), pg_search_visit_terms_append('', $long), 'a single long term is cut to the column');
	pg_assert_same('kahve', pg_search_visit_terms_append('kahve', $long), 'a long term after another one does not fit');
	pg_assert_same(str_repeat('a', 10), pg_search_visit_terms_append('', str_repeat('a', 20), 10), 'the width can be given');
}

function test_search_visit_terms_append_keeps_the_separator_apart()
{
	pg_assert_same('kahve|çay menü', pg_search_visit_terms_append('kahve', 'çay|menü'), 'a bar in the term does not make two terms');
	pg_assert_same('kahve|çay menü', pg_search_visit_terms_append('kahve', "çay \t | menü"), 'spaces are folded');
	pg_assert_same('kahve', pg_search_visit_terms_append('kahve', ' | '), 'a lone bar adds nothing');
}

function test_search_visit_term_written_by_both_search_screens()
{
	$widgets = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets.php');
	$start = strpos($widgets, "\nfunction _render_system_widget_search_results(");
	$end = strpos($widgets, "\nfunction ", $start + 10);
	$body = (string) substr($widgets, $start, $end - $start);

	pg_assert_contains('pg_search_record_visit_term($query)', $body, 'the search widget writes the term to the visit');
	pg_assert_contains('$current_page === 1', $body, 'only on the first results page');

	$legacy = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_search_results.php');
	pg_assert_contains('pg_search_record_visit_term($query)', $legacy, 'the legacy Search Results page uses the same write');
	pg_assert_false(strpos($legacy, 'UPDATE visitors') !== false, 'no second copy of the write');
}
