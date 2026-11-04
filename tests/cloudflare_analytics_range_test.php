<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * pg_cloudflare_analytics_range(): the day-group reads behind the Cloudflare
 * Tools analytics cards ask for every date of their range, and the page
 * passes only text request values to the date handling.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/cloudflare_tools.php');

function test_cloudflare_analytics_range_presets_cover_every_date()
{
	$now = mktime(12, 0, 0, 11, 2, 2026);

	// "-7 days" … today is eight dates: a limit of 7 dropped one of them.
	pg_assert_same(array('start' => '2026-10-26', 'end' => '2026-11-02', 'limit' => 8), pg_cloudflare_analytics_range(7, null, null, $now), '7 days');
	pg_assert_same(array('start' => '2026-11-01', 'end' => '2026-11-02', 'limit' => 2), pg_cloudflare_analytics_range(1, null, null, $now), '24 hours');
	pg_assert_same(31, pg_cloudflare_analytics_range(30, null, null, $now)['limit'], '30 days');
}

function test_cloudflare_analytics_range_custom_dates()
{
	$now = mktime(12, 0, 0, 11, 2, 2026);

	pg_assert_same(array('start' => '2026-10-01', 'end' => '2026-10-31', 'limit' => 31), pg_cloudflare_analytics_range(31, '2026-10-01', '2026-10-31', $now), 'October');
	// Across a daylight saving change the count stays whole.
	pg_assert_same(31, pg_cloudflare_analytics_range(31, '2026-03-15', '2026-04-14', $now)['limit'], 'over a clock change');
	// An end before the start still asks for one group.
	pg_assert_same(1, pg_cloudflare_analytics_range(1, '2026-10-31', '2026-10-01', $now)['limit'], 'reversed range');
}

function test_cloudflare_page_reads_dates_as_text_only()
{
	$source = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/cloudflare.php');

	pg_assert_same(2, substr_count($source, "is_string(\$_REQUEST['log_start']) && is_string(\$_REQUEST['log_end'])) {"), 'log_start checked before both trims');
	pg_assert_true(strpos($source, "htmlspecialchars(\$_REQUEST['log_start']") === false, 'log_start not printed unchecked');
	pg_assert_true(strpos($source, "(string)isset(\$_REQUEST['start_date'])") === false, 'start_date not printed unchecked');
	pg_assert_contains('pg_cloudflare_analytics_range(', $source, 'the page uses the range helper');
	pg_assert_true(strpos($source, "limit: '.\$days") === false, 'no read limited to the day count');
}
