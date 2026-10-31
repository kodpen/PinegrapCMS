<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests the Load More Products button of the Create and Edit Product Group
 * screens (pg_product_group_form_products_card()): it is enabled again or
 * hidden on every answer, a failure says so in a toast, and focus moves to
 * the first row the batch brought.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function product_group_load_more_script()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/product_group_form.php');
}

function test_product_group_load_more_success_never_locks()
{
	$js = product_group_load_more_script();

	pg_assert_contains('if (!(response && response.status === "success")) {', $js, 'only a non-success answer goes to the failure path; any success, empty rows included, is handled');
	pg_assert_contains("if (!response.has_more) {\n", $js, 'has_more decides between hiding the strip and enabling the button');
	pg_assert_true(strpos($js, 'response.status === "success" && response.rows') === false, 'an empty batch no longer skips the button restore');
}

function test_product_group_load_more_failure_says_so()
{
	$js = product_group_load_more_script();

	pg_assert_contains('window.pgToast({ variant: "danger", message: message || ', $js, 'a failure is shown as a danger toast');
	pg_assert_contains("message || ' . json_encode(lang('Query failed.')) . ' });", $js, 'the toast falls back to a generic line');
	pg_assert_contains('loadMoreFailed(response && response.message);', $js, 'a refused answer reaches the failure path');
	pg_assert_contains("error: function() {\n                                        loadMoreFailed();", $js, 'a network failure, a sign-in page or an HTML error reaches the failure path');
}

function test_product_group_load_more_focuses_first_new_row()
{
	$js = product_group_load_more_script();

	pg_assert_contains('var $first = $rows.find("input.check").first();', $js, 'the first new row\'s tick box is read');
	pg_assert_contains('$first.trigger("focus");', $js, 'focus moves to the first new row');
	pg_assert_contains('pgInitInjected($rows);', $js, 'the new rows get the same hints as the first batch');
}
