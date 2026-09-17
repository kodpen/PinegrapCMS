<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Per-application request ceiling, counted in one-minute windows.
//
// Counting is a single INSERT ... ON DUPLICATE KEY UPDATE against a primary key
// of (application, minute). A SELECT followed by an UPDATE would let two
// simultaneous requests both read the same count and both write count + 1, which
// is how a limit of 120 lets 200 through under exactly the load it exists for.
// The database does the arithmetic, so there is no window to lose.

if (!defined('PG_API_ENTRY')) {
	exit;
}

function api_rate_limit_check($app) {

	$limit = (int)$app['rate_limit_per_min'];

	if ($limit <= 0) {

		return;

	}

	$app_id = (int)$app['id'];

	$window = time() - (time() % 60);

	api_exec("INSERT INTO api_rate_bucket (app_id, window_start, hits)
		VALUES ('" . $app_id . "', '" . $window . "', 1)
		ON DUPLICATE KEY UPDATE hits = hits + 1");

	$hits = (int)api_value("SELECT hits FROM api_rate_bucket
		WHERE app_id = '" . $app_id . "' AND window_start = '" . $window . "'");

	$remaining = $limit - $hits;

	if ($remaining < 0) {

		$remaining = 0;

	}

	// Sent on every answer, not only on refusal: a client that watches the
	// remaining count can slow down before it is turned away, which is the
	// point of publishing it.
	api_extra_headers('X-RateLimit-Limit', (string)$limit);

	api_extra_headers('X-RateLimit-Remaining', (string)$remaining);

	api_extra_headers('X-RateLimit-Reset', (string)($window + 60));

	if ($hits > $limit) {

		$retry_after = ($window + 60) - time();

		if ($retry_after < 1) {

			$retry_after = 1;

		}

		api_extra_headers('Retry-After', (string)$retry_after);

		api_fail(429, 'rate_limited', lang(array(
			'string' => 'Rate limit reached ({var:1} requests per minute). Try again in {var:2} seconds.',
			'vars'   => array($limit, $retry_after)
		)));

	}

}
