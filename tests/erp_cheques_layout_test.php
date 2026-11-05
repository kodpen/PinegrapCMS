<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the ERP cheques and notes screen layout (erp_cheques.php): the
 * list and the New cheque or note form in a form column, the totals in a
 * Summary card of a sticky status column written after it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function erp_cheques_layout_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/erp_cheques.php');
}

function test_erp_cheques_has_form_and_status_columns()
{
	$source = erp_cheques_layout_source();

	$form = strpos($source, '<div class="col-12 lg:col-8 2xl:col-9 order-1">');
	$status = strpos($source, '<div class="col-12 lg:col-4 2xl:col-3 order-2">');

	pg_assert_true($form !== false, 'the form column is there');
	pg_assert_true($status !== false, 'the status column is there');
	pg_assert_true($form < $status, 'form column before status column');
	pg_assert_contains('<div class="position-sticky my-7" style="top:3.3rem;">', $source, 'the status column sticks below the top bar');

	$list = strpos($source, 'id="erp_cheques"');
	$new = strpos($source, "' . \$output_new . '");
	pg_assert_true($list !== false && $list > $form && $list < $status, 'the list sits in the form column');
	pg_assert_true($new !== false && $new > $list && $new < $status, 'the new cheque form sits in the form column, below the list');
}

function test_erp_cheques_status_column_reads_the_totals()
{
	$source = erp_cheques_layout_source();
	$status = substr($source, (int) strpos($source, '<div class="col-12 lg:col-4 2xl:col-3 order-2">'));

	pg_assert_contains("lang('Summary')", $status, 'the summary card has its title');
	foreach (array("\$totals['received']", "\$totals['given']", "\$totals['due_week']", "\$totals['overdue']") as $total) {
		pg_assert_contains($total, $status, $total . ' is shown in the status column');
	}
	pg_assert_true(strpos($source, '<div class="row g-5 my-1">') === false, 'the full-width strip of total cards is gone');
}

function test_erp_cheques_save_label_in_its_span()
{
	pg_assert_contains('<span class="pg-btn-label">\' . lang(array(\'string\' => \'Save\')) . \'</span>', erp_cheques_layout_source(), 'the Save label is in its span');
}
