<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the incomplete records of a classic form list view
 * (pg_form_list_view_complete_sql(), includes/fn/forms.php): a record saved
 * for later is left out of the list, its RSS feed and the sitemap, as the
 * designed list widget and the search index leave it out; only a list
 * filtered to the viewer's own records still shows it, so its submitter
 * can open it and finish it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_form_list_view_complete_sql_leaves_out_incomplete_records()
{
	pg_assert_same(' AND (forms.complete = 1)', pg_form_list_view_complete_sql(false));
	pg_assert_same(' AND (forms.complete = 1)', pg_form_list_view_complete_sql(0));
	pg_assert_same(' AND (forms.complete = 1)', pg_form_list_view_complete_sql('0'));
}

function test_form_list_view_complete_sql_keeps_them_for_a_viewer_filtered_list()
{
	pg_assert_same('', pg_form_list_view_complete_sql(true));
	pg_assert_same('', pg_form_list_view_complete_sql('1'));
}

function test_form_list_view_page_starts_from_the_complete_filter()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_form_list_view.php');

	pg_assert_contains('$sql_viewer_filter = pg_form_list_view_complete_sql($viewer_filter == 1);', $source);
}

function test_form_list_view_feed_and_sitemap_read_complete_records_only()
{
	$page = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_page.php');
	pg_assert_same(2, substr_count($page, 'pg_form_list_view_complete_sql(false)'), 'both queries of the classic feed');

	$sitemap = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_sitemap_info.php');
	pg_assert_contains('pg_form_list_view_complete_sql(false)', $sitemap);
}
