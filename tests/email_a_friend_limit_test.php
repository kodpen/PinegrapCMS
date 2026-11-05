<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for E-mail a Friend's daily limit (pg_eaf_limit_take() in
 * includes/fn/forms.php): the count is taken before the mail is sent, as one
 * atomic step, and an IPv6 visitor is counted by the /64.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/waf.php');

// A fake counter: an in-memory waf_rate_record().
function pg_test_eaf_counter()
{
	$counts = array();
	$calls = array();

	$record = function ($subject, $scope, $window) use (&$counts, &$calls) {
		$calls[] = array($subject, $scope, $window);
		$key = $subject . '|' . $scope;
		$counts[$key] = isset($counts[$key]) ? $counts[$key] + 1 : 1;

		return $counts[$key];
	};

	return array($record, &$calls);
}

// Ten e-mails a day pass; the eleventh is refused. Every attempt is counted
// before the mail goes, so parallel requests cannot all read room.
function test_eaf_limit_ten_a_day()
{
	list($record, $calls) = pg_test_eaf_counter();

	for ($i = 1; $i <= 10; $i++) {
		pg_assert_same(false, pg_eaf_limit_take('203.0.113.7', $record));
	}

	pg_assert_same(true, pg_eaf_limit_take('203.0.113.7', $record));
	pg_assert_same(false, pg_eaf_limit_take('203.0.113.8', $record));
}

// The counter is the daily 'eaf' scope the sweep keeps for 24 hours.
function test_eaf_limit_scope_and_window()
{
	$seen = array();
	$record = function ($subject, $scope, $window) use (&$seen) {
		$seen = array($subject, $scope, $window);

		return 1;
	};

	pg_eaf_limit_take('203.0.113.7', $record);
	pg_assert_same(array('203.0.113.7', 'eaf', 86400), $seen);
}

// Every address of one IPv6 /64 shares one allowance: a visitor holding the
// whole /64 cannot send ten from each address.
function test_eaf_limit_ipv6_counted_by_64()
{
	list($record) = pg_test_eaf_counter();

	for ($i = 1; $i <= 10; $i++) {
		pg_assert_same(false, pg_eaf_limit_take('2001:db8:1:2::' . dechex($i), $record));
	}

	pg_assert_same(true, pg_eaf_limit_take('2001:db8:1:2::ff', $record));
	pg_assert_same(false, pg_eaf_limit_take('2001:db8:1:3::1', $record));
}

// The page checks and sends in this order: the count is taken before
// email(), never read first and recorded after the mail went.
function test_eaf_page_counts_before_sending()
{
	$page = (string) file_get_contents(PG_FUNCTIONS_DIR . '/email_a_friend.php');

	pg_assert_same(false, strpos($page, 'waf_rate_hits('));
	pg_assert_same(false, strpos($page, 'waf_rate_record('));

	$take = strpos($page, 'pg_eaf_limit_take(');
	$send = strpos($page, 'email(array(');

	pg_assert_true($take !== false);
	pg_assert_true($send !== false);
	pg_assert_true($take < $send);
}
