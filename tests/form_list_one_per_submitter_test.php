<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_sw_form_list_one_per_submitter_sql() in includes/fn/widgets.php:
 * the form list option "newest record per submitter" keeps, for each signed-in
 * submitter, only their newest complete record; anonymous records are never
 * collapsed, and the count, paging and search run on what is left.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Off (default, missing, falsy) adds nothing to the list's query.
function test_form_list_one_per_submitter_off()
{
	pg_assert_same('', pg_sw_form_list_one_per_submitter_sql(12, array()));
	pg_assert_same('', pg_sw_form_list_one_per_submitter_sql(12, array('one_per_submitter' => false)));
	pg_assert_same('', pg_sw_form_list_one_per_submitter_sql(12, array('one_per_submitter' => 0)));
	pg_assert_same('', pg_sw_form_list_one_per_submitter_sql(12, 'not an array'));
	pg_assert_same('', pg_sw_form_list_one_per_submitter_sql(0, array('one_per_submitter' => true)));
}

// On: anonymous rows pass, a member's row passes only as their newest
// complete record of this form.
function test_form_list_one_per_submitter_on()
{
	$sql = pg_sw_form_list_one_per_submitter_sql('12', array('one_per_submitter' => true));
	pg_assert_contains('forms.user_id = 0', $sql);
	pg_assert_contains('MAX(', $sql);
	pg_assert_contains('GROUP BY', $sql);
	pg_assert_contains("page_id = '12'", $sql);
	pg_assert_contains('complete = 1', $sql);
	pg_assert_contains('user_id > 0', $sql);
	pg_assert_same(0, strpos(ltrim($sql), 'AND ('));
	pg_assert_same(pg_sw_form_list_one_per_submitter_sql(12, array('one_per_submitter' => '1')), $sql);
}

// The collapse joins every query of the list: the count, the exact-value
// check and the rows, beside the search, so they all run after it.
function test_form_list_one_per_submitter_renderer_wiring()
{
	$source = file_get_contents(dirname(__DIR__) . '/pinegrap/includes/fn/widgets.php');
	pg_assert_contains('$one_per_submitter = pg_sw_form_list_one_per_submitter_sql($custom_form_page_id, $cfg);', $source);
	pg_assert_same(3, substr_count($source, "\$search_where\n           \$one_per_submitter") + substr_count($source, "\$search_where\n               \$one_per_submitter"));
}
