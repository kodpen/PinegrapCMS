<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the external API's end bounds: a date-only `to` / `*_to`
 * parameter counts to the end of that day in the site's time zone, a value
 * with a time stays that moment.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/time.php');

function test_api_end_bound_names()
{
	pg_assert_true(api_param_is_end_bound('to'), 'to');
	pg_assert_true(api_param_is_end_bound('date_to'), 'date_to');
	pg_assert_true(api_param_is_end_bound('issued_to'), 'issued_to');
	pg_assert_true(!api_param_is_end_bound('from'), 'from');
	pg_assert_true(!api_param_is_end_bound('updated_since'), 'updated_since');
	pg_assert_true(!api_param_is_end_bound('created_before'), 'created_before is exclusive, not a day');
	pg_assert_true(!api_param_is_end_bound('starts_before'), 'starts_before');
	pg_assert_true(!api_param_is_end_bound('total'), 'a name merely ending in to');
}

function test_api_end_bound_date_only_counts_the_whole_day_in_the_site_zone()
{
	$zone = date_default_timezone_get();

	date_default_timezone_set('Europe/Istanbul');

	$end = api_time_parse_end_bound('2026-10-20');
	$exact = api_time_parse_end_bound('2026-10-20T18:21:00+03:00');
	$midnight = api_time_parse_end_bound('2026-10-20T00:00:00+03:00');
	$stamp = api_time_parse_end_bound('1760000000');
	$bad = api_time_parse_end_bound('not a day');
	$empty = api_time_parse_end_bound('');

	date_default_timezone_set('America/New_York');

	// A day with a clock change still ends at its own 23:59:59.
	$dst = api_time_parse_end_bound('2026-11-01');

	date_default_timezone_set($zone);

	pg_assert_same(gmmktime(20, 59, 59, 10, 20, 2026), $end, '23:59:59 Istanbul');
	pg_assert_same(gmmktime(15, 21, 0, 10, 20, 2026), $exact, 'a time stays that moment');
	pg_assert_same(gmmktime(21, 0, 0, 10, 19, 2026), $midnight, 'an explicit midnight stays midnight');
	pg_assert_same(1760000000, $stamp, 'a unix stamp stays');
	pg_assert_same(null, $bad, 'unreadable');
	pg_assert_same(null, $empty, 'empty');
	pg_assert_same(gmmktime(4, 59, 59, 11, 2, 2026), $dst, '23:59:59 EST after the change');
}

// The parameter reader applies it to every end-bound datetime, in one place.
function test_api_router_reads_end_bounds_through_the_helper()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/api/router.php');

	pg_assert_true((bool)preg_match("/case 'datetime':\s*(?:\/\/[^\n]*\s*)*\\\$parsed = api_param_is_end_bound\(\\\$name\) \? api_time_parse_end_bound\(\\\$raw\) : api_time_parse\(\\\$raw\);/", $source), 'datetime case');

	$bootstrap = file_get_contents(PG_FUNCTIONS_DIR . '/includes/api/bootstrap.php');

	pg_assert_true(strpos($bootstrap, "require_once(\$pg_api_directory . '/time.php');") !== false, 'loaded by the API entry');
}
