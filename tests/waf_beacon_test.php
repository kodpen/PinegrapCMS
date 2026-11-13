<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the site's own beacons at the firewall: the scripts a site page
 * posts to without a click (waf_beacon_scripts()) stay off the sign-in, forms
 * and checkout allowance and the cap on open requests
 * (waf_sensitive_request_counts()), and are held to a bucket of their own
 * whose excess is answered with an empty 204 (waf_beacon_bucket_answer()).
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

// The census: the page cache's view, the speed figures, the search and
// experiment clicks (page_view.php) and the cookie notice (consent_answer.php).
function test_waf_beacon_scripts_named()
{
	pg_assert_same(array('page_view.php', 'consent_answer.php'), waf_beacon_scripts());
}

// A shared address reading pages spent the 30 a minute allowance on beacons.
function test_waf_beacon_posts_leave_sensitive_allowance()
{
	foreach (waf_beacon_scripts() as $script) {
		pg_assert_false(waf_sensitive_request_counts('POST', $script, 'empty', '*/*'), $script . ' POST');
		pg_assert_false(waf_sensitive_request_counts('POST', $script, '', ''), $script . ' POST without headers');
	}
}

// Every other POST still counts, the sign-in and the cart first.
function test_waf_beacon_other_posts_still_count()
{
	foreach (array('index.php', 'registration_entrance.php', 'forgot_password.php', 'custom_form.php', 'cart_action.php', 'router.php', 'page_view.php.bak', 'Page_View.php', '') as $script) {
		pg_assert_true(waf_sensitive_request_counts('POST', $script, 'empty', '*/*'), $script . ' POST');
	}
}

// Within the bucket the beacon passes; the first request over it is logged
// once, the rest of the minute is answered without a row.
function test_waf_beacon_bucket_answer()
{
	pg_assert_same('pass', waf_beacon_bucket_answer(0, 240), 'counter unavailable');
	pg_assert_same('pass', waf_beacon_bucket_answer(1, 240), 'first');
	pg_assert_same('pass', waf_beacon_bucket_answer(240, 240), 'at the limit');
	pg_assert_same('log', waf_beacon_bucket_answer(241, 240), 'first over');
	pg_assert_same('drop', waf_beacon_bucket_answer(242, 240), 'second over');
	pg_assert_same('drop', waf_beacon_bucket_answer(5000, 240), 'far over');
	pg_assert_same('pass', waf_beacon_bucket_answer(5000, 0), 'no limit');
}
