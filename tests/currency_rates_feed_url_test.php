<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Frankfurter batch request goes to the feed's current address: the old
 * api.frankfurter.app host answers 301, and the outbound client follows no
 * redirect, so every rate then fell through to one HexaRate call per code.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_currency_rates_frankfurter_url_uses_current_host()
{
	pg_assert_same(
		'https://api.frankfurter.dev/v1/latest?from=EUR&to=USD,TRY',
		pg_currency_rates_frankfurter_url('EUR', array('USD', 'TRY'))
	);
}

function test_currency_rates_fetch_calls_the_url_builder()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/currency_rates.php');

	pg_assert_true(strpos($source, "'https://api.frankfurter.app") === false);
	pg_assert_true(strpos($source, 'pg_currency_rates_http_get(pg_currency_rates_frankfurter_url($base_code, $batch))') !== false);
}

// The ECB stopped its BGN reference rate when Bulgaria joined the euro; the
// code is left to HexaRate rather than asked of a feed that drops it.
function test_currency_rates_frankfurter_codes_drop_bgn()
{
	pg_assert_true(!in_array('BGN', pg_currency_rates_frankfurter_codes(), true));
	pg_assert_true(in_array('EUR', pg_currency_rates_frankfurter_codes(), true));
}
