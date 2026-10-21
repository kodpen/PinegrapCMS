<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the n11 marketplace connector
 * (includes/api/outbound/connectors/n11.php): how the order feed is paged and
 * how far a pull lets the cursor move.
 *
 * n11 is never called. The connector's functions take an optional transport
 * with the signature of api_http_request(); the ones here answer from a feed
 * built in memory, in the shape of their shipmentPackages answer.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The connector files sit behind init.php's gate and only declare functions
// behind it, so the gate is answered here.
if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/connectors/base.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/connectors/n11.php');

function n11_test_account()
{
	return array(
		'id'          => 3,
		'provider'    => 'n11',
		'credentials' => array('app_key' => 'key-abc', 'app_secret' => 'secret-xyz'),
	);
}

// $count packages, the first modified at $first (unix seconds) and each next
// one $step seconds later, in the shape their feed answers with.
function n11_test_packages($count, $first, $step, $prefix = 'pkg')
{
	$packages = array();

	for ($i = 1; $i <= $count; $i++) {
		$packages[] = array(
			'id'                    => $prefix . $i,
			'orderNumber'           => 'ORD' . $i,
			'shipmentPackageStatus' => 'Created',
			'lastModifiedDate'      => ($first + ($i - 1) * $step) * 1000,
			'lines'                 => array(),
		);
	}

	return $packages;
}

// A transport that answers the shipmentPackages feed out of $feed the way n11
// does: only the packages modified inside startDate..endDate, in ascending
// order of modification, a page of the size asked for at a time. Every URL it
// is asked for is written into $calls. Page $fail_page, when given, answers 500.
function n11_test_feed_transport($feed, &$calls, $fail_page = -1)
{
	return function ($method, $url, $options) use ($feed, &$calls, $fail_page) {

		$calls[] = $url;

		$query = array();
		parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

		if ((int) $query['page'] === $fail_page) {
			return array('ok' => false, 'status' => 500, 'body' => '{"errors":[{"errorMessage":"down"}]}',
				'error' => '', 'duration_ms' => 1);
		}

		$inside = array();

		foreach ($feed as $package) {
			if ($package['lastModifiedDate'] >= (float) $query['startDate']
				&& $package['lastModifiedDate'] <= (float) $query['endDate']) {
				$inside[] = $package;
			}
		}

		usort($inside, function ($a, $b) {
			return ($a['lastModifiedDate'] < $b['lastModifiedDate']) ? -1
				: (($a['lastModifiedDate'] > $b['lastModifiedDate']) ? 1 : 0);
		});

		$size = (int) $query['size'];

		$content = array_slice($inside, (int) $query['page'] * $size, $size);

		return array('ok' => true, 'status' => 200,
			'body' => json_encode(array('content' => $content, 'totalElements' => count($inside))),
			'error' => '', 'duration_ms' => 1);
	};
}

function n11_test_ids($packages)
{
	$ids = array();

	foreach ($packages as $package) {
		$ids[] = $package['remote_package_id'];
	}

	return $ids;
}

/* ------------------------------------------------------------ order feed */

function test_marketplace_n11_pull_reads_a_short_feed_to_the_end()
{
	$since = time() - 3600;
	$calls = array();

	$result = mp_n11_pull_orders(n11_test_account(), $since,
		n11_test_feed_transport(n11_test_packages(150, $since + 10, 1), $calls));

	pg_assert_true($result['ok']);
	pg_assert_same(150, count($result['data']['packages']));
	pg_assert_true($result['data']['complete']);
	pg_assert_true($result['data']['read_to'] >= time() - 5, 'a feed read to its end moves the cursor to now');
	pg_assert_false(!empty($result['data']['more']));
	pg_assert_same(2, count($calls), 'one full page and one short one');
	pg_assert_contains('&page=1&size=100&', $calls[1]);
	pg_assert_contains('startDate=' . (($since - 300) * 1000) . '&', $calls[0], 'the window starts five minutes early');
}

function test_marketplace_n11_pull_at_the_page_cap_moves_the_cursor_only_as_far_as_it_read()
{
	$since = time() - 86400;
	$feed = n11_test_packages(2500, $since + 60, 10);
	$calls = array();

	$result = mp_n11_pull_orders(n11_test_account(), $since, n11_test_feed_transport($feed, $calls));

	pg_assert_true($result['ok']);
	pg_assert_same(20, count($calls), 'twenty pages, then it stops');
	pg_assert_same(2000, count($result['data']['packages']));
	pg_assert_same('pkg2000', $result['data']['packages'][1999]['remote_package_id']);
	pg_assert_true($result['data']['complete'], 'the cursor moves');
	pg_assert_true(!empty($result['data']['more']));
	pg_assert_same($since + 60 + 1999 * 10, $result['data']['read_to'],
		'to the modification time of the last package read, not to now');

	// The next pass starts there and reads the rest.
	$calls = array();

	$next = mp_n11_pull_orders(n11_test_account(), $result['data']['read_to'], n11_test_feed_transport($feed, $calls));

	pg_assert_true($next['ok']);
	pg_assert_true($next['data']['complete']);
	pg_assert_false(!empty($next['data']['more']), 'the second pass reaches the end of the feed');

	$seen = array_unique(array_merge(n11_test_ids($result['data']['packages']), n11_test_ids($next['data']['packages'])));

	pg_assert_same(2500, count($seen), 'every package of the window is read by one pass or the other');
	pg_assert_contains('pkg2500', $seen);
}

function test_marketplace_n11_pull_reads_past_the_cap_when_the_cap_would_not_move_the_cursor()
{
	$since = time() - 86400;

	// 2,050 packages modified inside the five-minute overlap before $since, then
	// newer ones. Twenty pages would end inside the overlap and hand the next
	// pass the same start.
	$feed = array_merge(
		n11_test_packages(2050, $since - 100, 0, 'old'),
		n11_test_packages(500, $since + 1000, 1, 'new'));
	$calls = array();

	$result = mp_n11_pull_orders(n11_test_account(), $since, n11_test_feed_transport($feed, $calls));

	pg_assert_true($result['ok']);
	pg_assert_same(21, count($calls), 'one page past the cap reaches a newer package');
	pg_assert_same(2100, count($result['data']['packages']));
	pg_assert_true($result['data']['complete']);
	pg_assert_same($since + 1000 + 49, $result['data']['read_to']);
}

function test_marketplace_n11_pull_keeps_the_cursor_when_even_the_ceiling_does_not_move_it()
{
	$since = time() - 86400;
	$calls = array();

	$result = mp_n11_pull_orders(n11_test_account(), $since,
		n11_test_feed_transport(n11_test_packages(10100, $since - 100, 0), $calls));

	pg_assert_true($result['ok'], 'what was read is still handed over for import');
	pg_assert_same(100, count($calls), 'a hundred pages at most');
	pg_assert_same(10000, count($result['data']['packages']));
	pg_assert_false($result['data']['complete'], 'the cursor stays rather than jumping over the rest');
	pg_assert_false(isset($result['data']['read_to']));
}

function test_marketplace_n11_pull_failure_hands_over_what_was_read_and_keeps_the_cursor()
{
	$since = time() - 86400;
	$calls = array();

	$result = mp_n11_pull_orders(n11_test_account(), $since,
		n11_test_feed_transport(n11_test_packages(500, $since + 60, 1), $calls, 2));

	pg_assert_false($result['ok']);
	pg_assert_true(!empty($result['retry']), 'a 500 is worth trying again');
	pg_assert_same(200, count($result['data']['packages']));
	pg_assert_false($result['data']['complete']);
}
