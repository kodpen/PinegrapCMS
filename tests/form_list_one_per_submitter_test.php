<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the form list's one_per_submitter option
 * (pg_sw_form_list_one_per_submitter_sql() in includes/fn/widgets.php): off
 * by default; on, a signed-in submitter's older records leave the list and
 * records without a submitter stay; the renderer applies it with the
 * filters, so the count, the pages and the search see only what is left.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Off unless the option is set: running lists change nothing.
function test_form_list_one_per_submitter_off_by_default()
{
	pg_assert_same('', pg_sw_form_list_one_per_submitter_sql(array()));
	pg_assert_same('', pg_sw_form_list_one_per_submitter_sql(array('one_per_submitter' => false)));
	pg_assert_same('', pg_sw_form_list_one_per_submitter_sql(array('one_per_submitter' => 0)));
	pg_assert_same('', pg_sw_form_list_one_per_submitter_sql(null));
}

// On: anonymous rows pass; a member's row passes only without a newer
// complete record of the same form and submitter.
function test_form_list_one_per_submitter_condition()
{
	$sql = pg_sw_form_list_one_per_submitter_sql(array('one_per_submitter' => true));
	pg_assert_same(0, strpos(ltrim($sql), 'AND ('), 'an AND fragment');
	pg_assert_contains('forms.user_id = 0 OR NOT EXISTS', $sql);
	$flat = preg_replace('/\s+/', ' ', $sql);
	foreach (array(
		'newer_form.page_id = forms.page_id',
		'newer_form.complete = 1',
		'newer_form.user_id = forms.user_id',
		'newer_form.id > forms.id',
	) as $part) {
		pg_assert_contains($part, $flat);
	}
	pg_assert_same($sql, pg_sw_form_list_one_per_submitter_sql(array('one_per_submitter' => '1')));
}

// The renderer adds it to the filters' WHERE, which the count, the
// address check and the rows query all read.
function test_form_list_one_per_submitter_renderer_wiring()
{
	$source = file_get_contents(dirname(__DIR__) . '/pinegrap/includes/fn/widgets.php');
	$base = strpos($source, "\$filter_where = \$filter_sql['where'];");
	$add  = strpos($source, '$filter_where .= pg_sw_form_list_one_per_submitter_sql($cfg);');
	$count = strpos($source, 'SELECT COUNT(*) FROM forms', (int)$add);
	pg_assert_true($base !== false && $add !== false && $base < $add, 'added to the filters');
	pg_assert_true($count !== false, 'before the count');
}
