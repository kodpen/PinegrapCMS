<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the order of a classic form list view's page
 * (pg_form_list_view_order_sql(), includes/fn/forms.php): the records are
 * paged with LIMIT start, count, so the order ends on the record id and two
 * records sharing a sort value keep one place across the pages.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_form_list_view_order_ends_on_the_record_id()
{
	pg_assert_same(
		'ORDER BY forms.submitted_timestamp DESC, forms.id ASC',
		pg_form_list_view_order_sql('ORDER BY forms.submitted_timestamp DESC'));

	pg_assert_same(
		'ORDER BY (SELECT form_data.data FROM form_data LIMIT 1) + 0 ASC, forms.reference_code DESC, forms.id ASC',
		pg_form_list_view_order_sql("ORDER BY (SELECT form_data.data FROM form_data LIMIT 1) + 0 ASC, forms.reference_code DESC\n"));
}

function test_form_list_view_order_without_a_sort_field_is_the_record_id()
{
	pg_assert_same('ORDER BY forms.id ASC', pg_form_list_view_order_sql(''));
	pg_assert_same('ORDER BY forms.id ASC', pg_form_list_view_order_sql('  '));
}

function test_form_list_view_pages_with_the_tie_breaker()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_form_list_view.php');

	pg_assert_contains('$sql_order_by = pg_form_list_view_order_sql($sql_order_by);', $source);
	pg_assert_true(
		strpos($source, '$sql_order_by = pg_form_list_view_order_sql($sql_order_by);') < strpos($source, '$sql_limit = "LIMIT "'),
		'the tie-breaker is added before the page is read');
}
