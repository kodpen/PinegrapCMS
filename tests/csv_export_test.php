<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The panel CSV exports start with a UTF-8 byte order mark and a charset so
 * Excel keeps Turkish letters, and stamp their times on the site's clock.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_csv_export_time_follows_the_site_clock()
{
	$timestamp = mktime(17, 5, 9, 3, 4, 2026);
	$zone = date('T', $timestamp);

	pg_assert_same('3/4/2026 17:05:09 ' . $zone, pg_csv_export_time($timestamp, 'month_day', 'twenty_four_hours'), 'month first, 24-hour');
	pg_assert_same('4/3/2026 17:05:09 ' . $zone, pg_csv_export_time($timestamp, 'day_month', 'twenty_four_hours'), 'day first, 24-hour');
	pg_assert_same('3/4/2026 5:05:09 PM ' . $zone, pg_csv_export_time($timestamp, 'month_day', 'twelve_hours'), 'month first, 12-hour');
	pg_assert_same('4/3/2026 5:05:09 PM ' . $zone, pg_csv_export_time($timestamp, 'day_month', 'twelve_hours'), 'day first, 12-hour');
}

function test_csv_export_headers_name_utf8_and_the_file()
{
	pg_assert_same(
		array('Content-Type: text/csv; charset=utf-8', 'Content-Disposition: attachment; filename=contacts.csv'),
		pg_csv_download_headers('contacts.csv'));
}

function test_csv_export_download_start_writes_the_byte_order_mark()
{
	ob_start();
	@pg_csv_download_start('forms.csv');
	$output = ob_get_clean();

	pg_assert_same("\xEF\xBB\xBF", $output);
}

function test_csv_export_screens_use_the_helpers()
{
	$files = array('view_contacts.php', 'view_gift_cards.php', 'view_key_codes.php', 'view_submitted_forms.php', 'view_email_campaign_profiles.php', 'view_orders.php');

	foreach ($files as $file) {
		$source = file_get_contents(dirname(__DIR__) . '/pinegrap/' . $file);

		pg_assert_true(strpos($source, 'pg_csv_download_start(') !== false, $file . ' starts its download with pg_csv_download_start()');
		pg_assert_true(stripos($source, 'Content-type: text/csv') === false, $file . ' sends no bare text/csv header');
		pg_assert_true(strpos($source, 'g:i:s A T') === false, $file . ' stamps no fixed 12-hour time');
	}
}

function test_csv_export_words_in_the_panel_language()
{
	$gift_cards = file_get_contents(dirname(__DIR__) . '/pinegrap/view_gift_cards.php');
	pg_assert_true(strpos($gift_cards, "\$gift_card['from_name'] = 'Anonymous'") === false, 'gift card sender translated');
	pg_assert_true(strpos($gift_cards, "\$delivery_date = 'Immediate'") === false, 'gift card delivery translated');

	$forms = file_get_contents(dirname(__DIR__) . '/pinegrap/view_submitted_forms.php');
	pg_assert_true(strpos($forms, "\$status = 'Complete'") === false, 'form status translated');
	pg_assert_true(strpos($forms, "\$status = 'Incomplete'") === false, 'form status translated');
	pg_assert_true(strpos($forms, '\'", "\' . $address_name') === false, 'no space before the address name cell');
}

function test_csv_export_orders_zip_files_start_with_the_byte_order_mark()
{
	$source = file_get_contents(dirname(__DIR__) . '/pinegrap/view_orders.php');

	foreach (array('orders.csv', 'ship_tos.csv', 'order_items.csv', 'shipping_tracking_numbers.csv') as $file) {
		pg_assert_true(preg_match('/add_file\\("\\\\xEF\\\\xBB\\\\xBF" \\. \\$\\w+, \'' . preg_quote($file, '/') . '\'\\)/', $source) === 1, $file . ' in the zip starts with the byte order mark');
	}

	pg_assert_true(strpos($source, "lang('One file per order')") === false, 'the zip choice is named for what it holds');
}

function test_csv_export_products_csv_starts_with_the_byte_order_mark()
{
	require_once(PG_FUNCTIONS_DIR . '/export_products_f.php');

	ob_start();
	@pg_products_export_csv(array('headings' => array('name'), 'rows' => array(array('Çay'))));
	$output = ob_get_clean();

	pg_assert_same("\xEF\xBB\xBF\"name\"\n\"Çay\"\n", $output);
}

function test_csv_export_erp_audit_trail_uses_the_store_separator()
{
	$source = file_get_contents(dirname(__DIR__) . '/pinegrap/includes/erp/audit.php');
	$start = strpos($source, 'function erp_audit_csv(');
	$body = substr($source, $start, strpos($source, 'fclose($out);', $start) - $start);

	pg_assert_true(strpos($body, 'erp_csv_delimiter()') !== false, 'audit CSV reads the store separator');
	pg_assert_true(strpos($body, "';', '\"'") === false, 'audit CSV has no fixed semicolon');
}

function test_csv_export_formula_guard_keeps_a_formula_as_text()
{
	pg_assert_same("'=HYPERLINK(\"x\")", pg_csv_formula_guard('=HYPERLINK("x")'), 'equals sign');
	pg_assert_same("'+1", pg_csv_formula_guard('+1'), 'plus sign');
	pg_assert_same("'-1+1", pg_csv_formula_guard('-1+1'), 'minus sign');
	pg_assert_same("'@SUM(A1)", pg_csv_formula_guard('@SUM(A1)'), 'at sign');
	pg_assert_same("'\tx", pg_csv_formula_guard("\tx"), 'tab');
	pg_assert_same("'\rx", pg_csv_formula_guard("\rx"), 'carriage return');
	pg_assert_same('Acme Ltd = 1', pg_csv_formula_guard('Acme Ltd = 1'), 'a trigger later in the text stays');
	pg_assert_same('', pg_csv_formula_guard(''), 'empty');
	pg_assert_same('12', pg_csv_formula_guard(12), 'number as text');
	pg_assert_same("'=1+1", csv_cell('=1+1'), 'csv_cell() keeps its guard');
	pg_assert_same("'=\"\"a\"\"", csv_cell('="a"'), 'csv_cell() still doubles quotes');
}

// The body of function $name in $file, up to the next top-level function.
function csv_export_test_function_body($file, $name)
{
	$source = file_get_contents(dirname(__DIR__) . '/pinegrap/' . $file);
	$start = strpos($source, 'function ' . $name . '(');
	$end = strpos($source, "\nfunction ", $start + 1);

	return substr($source, $start, (($end === false) ? strlen($source) : $end) - $start);
}

function test_csv_export_erp_audit_trail_guards_its_text_cells()
{
	$body = csv_export_test_function_body('includes/erp/audit.php', 'erp_audit_csv');

	foreach (array("(string) \$row['username']", "(string) \$row['description']", 'erp_audit_event_text($row)', "(string) \$row['label']", "(string) \$row['detail']") as $cell) {
		pg_assert_true(strpos($body, 'pg_csv_formula_guard(' . $cell . ')') !== false, 'audit CSV guards ' . $cell);
	}
	pg_assert_true(strpos($body, "pg_csv_formula_guard(number_format(") === false, 'audit CSV leaves the amount a number');
}

function test_csv_export_erp_aging_guards_the_account_title()
{
	$body = csv_export_test_function_body('includes/erp/aging.php', 'erp_aging_csv');

	pg_assert_true(strpos($body, "pg_csv_formula_guard(\$account['title'])") !== false, 'aging CSV guards the account title');
	pg_assert_true(strpos($body, 'pg_csv_formula_guard(erp_export_amount(') === false, 'aging CSV leaves amounts numbers');

	$export = csv_export_test_function_body('includes/erp/export.php', 'erp_export_write_csv');
	pg_assert_true(strpos($export, 'pg_csv_formula_guard(') === false, 'the export profile writer stays unguarded');
}
