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
	$files = array('view_contacts.php', 'view_gift_cards.php', 'view_key_codes.php', 'view_submitted_forms.php', 'view_email_campaign_profiles.php');

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
