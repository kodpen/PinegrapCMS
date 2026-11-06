<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The first day of the SEO screens' recent-views window: calendar days in
 * the site's time zone, also when a clock change lies inside the window.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_seo_impact_since_steps_calendar_days()
{
	$zone = date_default_timezone_get();
	date_default_timezone_set('Europe/Berlin');

	try {
		// No clock change inside: thirty days before today.
		pg_assert_same('2026-07-02', pg_seo_impact_since(30, mktime(12, 0, 0, 8, 1, 2026)));

		// Autumn change (25 October) inside, read just before midnight: seconds
		// would land on 4 October, a day short.
		pg_assert_same('2026-10-03', pg_seo_impact_since(30, mktime(23, 30, 0, 11, 2, 2026)), 'autumn change');

		// Spring change (29 March) inside, read just after midnight: seconds
		// would land on 2 March, a day too many.
		pg_assert_same('2026-03-03', pg_seo_impact_since(30, mktime(0, 30, 0, 4, 2, 2026)), 'spring change');

		pg_assert_same('2026-04-01', pg_seo_impact_since(0, mktime(0, 30, 0, 4, 2, 2026)), 'at least one day back');
	} finally {
		date_default_timezone_set($zone);
	}
}
