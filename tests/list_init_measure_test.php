<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests the panel lists (table.chart DataTables in backend.src.js): the init
 * measures the table once, after it is shown, and a window resize leaves the
 * column sizing to DataTables' own throttled ResizeObserver.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function list_init_measure_complete()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/backend.src.js');
	$start = strpos($js, 'options["fnInitComplete"] = function () {');
	pg_assert_true($start !== false, 'the list init callback exists');
	$end = strpos($js, 'options["dom"]', $start);
	return substr($js, $start, $end - $start);
}

function test_list_init_measures_once_after_show()
{
	$init = list_init_measure_complete();

	pg_assert_same(1, substr_count($init, 'datatable.columns.adjust()'), 'the columns are measured once');
	pg_assert_true(strpos($init, "\$('table.chart').show();") < strpos($init, 'datatable.columns.adjust()'), 'the table is shown before it is measured');
	pg_assert_true(strpos($init, 'datatable.draw(') === false, 'no second draw: the first draw already opened on the restored page');
}

function test_list_window_resize_is_left_to_datatables()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/backend.src.js');

	pg_assert_true(preg_match('/\$\(window\)\.on\("resize", function \(\) \{\s*datatable\.columns\.adjust\(\);/', $js) === 0, 'no unthrottled resize listener measures the list');
	$lib = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/lib/DataTables/datatables.js');
	pg_assert_contains('settings.resizeObserver = new ResizeObserver(', $lib, 'DataTables watches its own container width');
	pg_assert_contains('options["scrollX"] = true;', $js, 'scrollX turns that observer on');
}
