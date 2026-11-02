<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the orders list's bulk actions and exports
 * (includes/fn/order_list.php, edit_orders.php, view_orders.php): the Paraşüt
 * export takes only completed orders and a VAT rate from 0 to 100, sends the
 * zip and stops, removing only its own folder; Cancel Selected skips open
 * carts; the CSV exports leave an empty date and a missing card number empty;
 * the Country filter matches the name the panel shows.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function orders_list_bulk_case($action, $next)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_orders.php');
	$start = strpos($source, "case '" . $action . "':");
	pg_assert_true($start !== false, $action . ' case exists');
	$end = strpos($source, "case '" . $next . "':", $start);
	pg_assert_true($end !== false, 'next case found');
	return substr($source, $start, $end - $start);
}

function test_orders_list_parasut_exportable_status()
{
	pg_assert_true(pg_order_parasut_exportable('complete'), 'complete');
	pg_assert_true(pg_order_parasut_exportable('exported'), 'exported');
	pg_assert_true(!pg_order_parasut_exportable('cancelled'), 'cancelled refused');
	pg_assert_true(!pg_order_parasut_exportable('incomplete'), 'incomplete refused');
	pg_assert_true(!pg_order_parasut_exportable(null), 'no row refused');
}

function test_orders_list_parasut_tax_rate_bounds()
{
	pg_assert_same(0.0, pg_order_parasut_tax_rate(''), 'empty is 0');
	pg_assert_same(20.0, pg_order_parasut_tax_rate('20'), '20');
	pg_assert_same(0.0, pg_order_parasut_tax_rate('0'), '0');
	pg_assert_same(100.0, pg_order_parasut_tax_rate('100'), '100');
	pg_assert_same(8.5, pg_order_parasut_tax_rate('8,5'), 'decimal comma');
	pg_assert_same(null, pg_order_parasut_tax_rate('-20'), 'negative refused');
	pg_assert_same(null, pg_order_parasut_tax_rate('150'), 'over 100 refused');
	pg_assert_same(null, pg_order_parasut_tax_rate('abc'), 'text refused');
}

// A row the status filter did not read is skipped, the rate is checked
// before any file is written, and only written orders are marked.
function test_orders_list_parasut_export_skips_and_checks()
{
	$block = orders_list_bulk_case('export_orders_for_parasut', 'remove_card_data');
	pg_assert_contains('pg_order_parasut_tax_rate(', $block, 'the rate is checked');
	pg_assert_contains("pg_order_parasut_exportable(\$row['status'] ?? null)", $block, 'an unread order is skipped');
	pg_assert_contains('$parasut_skipped++', $block, 'skipped orders are counted');
	pg_assert_true(
		strpos($block, 'pg_order_parasut_tax_rate(') < strpos($block, 'PHPExcel_IOFactory::load('),
		'the rate is checked before the workbooks are built'
	);
}

// The zip is the whole answer: notice and log first, only this export's
// folder removed, then exit before the redirect.
function test_orders_list_parasut_export_ends_with_the_zip()
{
	$block = orders_list_bulk_case('export_orders_for_parasut', 'remove_card_data');
	pg_assert_true(strpos($block, '$dir = $temps_location;') === false, 'the whole temps folder is not removed');
	pg_assert_true(strpos($block, 'rmdir($temps_location . $temp_folder_name)') !== false, 'its own folder is removed');
	$stream = strpos($block, 'fread($handle');
	pg_assert_true(strpos($block, 'add_notice(') < $stream, 'the notice is written before the zip is sent');
	pg_assert_true(strpos($block, 'log_activity(') < $stream, 'the log is written before the zip is sent');
	pg_assert_true(strpos($block, 'exit;', $stream) !== false, 'the script stops after the zip');
}

function test_orders_list_bulk_cancel_skips_incomplete()
{
	$block = orders_list_bulk_case('cancel', 'mark_shipped');
	pg_assert_contains("=== 'incomplete'", $block, 'an open cart is not cancelled');
	pg_assert_contains('were never completed and skipped', $block, 'the skip is said');
	pg_assert_true(
		strpos($block, "=== 'incomplete'") < strpos($block, 'process_order_cancellation('),
		'checked before the cancellation'
	);
}

function test_orders_list_export_cells()
{
	pg_assert_same('', pg_order_export_card_cell(''), 'no card number, empty cell');
	pg_assert_same('#************1111', pg_order_export_card_cell('************1111'), 'a number keeps the #');
	pg_assert_same('', pg_order_export_date_cell('0000-00-00'), 'empty date');
	pg_assert_same('', pg_order_export_date_cell(''), 'blank date');
	pg_assert_same(prepare_form_data_for_output('2026-11-02', 'date'), pg_order_export_date_cell('2026-11-02'), 'a date is formatted');

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_orders.php');
	pg_assert_true(strpos($source, '"#\' . $card_number') === false, 'no bare # before the card number');
	pg_assert_true(preg_match("/prepare_form_data_for_output\\(\\$\\w+\\['(arrival|ship|delivery)_date'\\], 'date'\\)/", $source) === 0, 'recipient dates go through the empty-date cell');
}

function test_orders_list_country_codes_by_label()
{
	$labels = array('TR' => 'Türkiye', 'DE' => 'Almanya', 'TM' => 'Türkmenistan', 'IT' => 'İtalya');
	pg_assert_same(array('TR'), pg_order_country_codes_by_label('Türkiye', $labels), 'shown name');
	pg_assert_same(array('TR'), pg_order_country_codes_by_label('TÜRKİYE', $labels), 'upper case with dotted I');
	pg_assert_same(array('TR', 'TM'), pg_order_country_codes_by_label('türk', $labels), 'part of a name');
	pg_assert_same(array('IT'), pg_order_country_codes_by_label('ital', $labels), 'dotted capital I folds');
	pg_assert_same(array(), pg_order_country_codes_by_label('  ', $labels), 'empty term');

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_orders.php');
	pg_assert_contains("pg_order_country_filter_sql('orders.billing_country'", $source, 'billing filter');
	pg_assert_contains("pg_order_country_filter_sql('ship_tos.country'", $source, 'shipping filter');
}
