<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Cloudflare Tools says when a Cloudflare read or action fails: the answer
 * helpers of includes/fn/cloudflare_tools.php (a GraphQL error arrives as HTTP
 * 200 with data null), the analytics cards that show a dash instead of zeros,
 * and source pins for the switches, the firewall table and the failure
 * messages of cloudflare.php.
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

function cloudflare_failed_reads_source()
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/cloudflare.php');
}

function test_cloudflare_rest_answer_failed()
{
	pg_assert_true(!pg_cloudflare_answer_failed(array('status' => 200, 'json' => array('success' => true, 'result' => array()))), 'a successful REST answer');
	pg_assert_true(pg_cloudflare_answer_failed(array('status' => 403, 'json' => array('success' => false))), 'a 403 fails');
	pg_assert_true(pg_cloudflare_answer_failed(array('status' => 200, 'json' => array('success' => false))), 'success false fails');
	pg_assert_true(pg_cloudflare_answer_failed(array('status' => 0, 'json' => null)), 'no answer fails');
}

function test_cloudflare_graphql_answer_failed()
{
	pg_assert_true(!pg_cloudflare_answer_failed(array('status' => 200, 'json' => array('data' => array('viewer' => array()), 'errors' => null)), true), 'a GraphQL answer with data');
	pg_assert_true(pg_cloudflare_answer_failed(array('status' => 200, 'json' => array('data' => null, 'errors' => array(array('message' => 'not authorized')))), true), 'HTTP 200 with errors fails');
	pg_assert_true(pg_cloudflare_answer_failed(array('status' => 200, 'json' => array('data' => null)), true), 'HTTP 200 with data null fails');
	pg_assert_true(pg_cloudflare_answer_failed(array('status' => 500, 'json' => null), true), 'HTTP 500 fails');
}

function test_cloudflare_answer_reason()
{
	pg_assert_same('Authentication error', pg_cloudflare_answer_reason(array('status' => 403, 'json' => array('errors' => array(array('code' => 10000, 'message' => 'Authentication error'))))), 'Cloudflare\'s own text');
	pg_assert_contains('403', pg_cloudflare_answer_reason(array('status' => 403, 'json' => null)), 'the status without a message');
	pg_assert_true(pg_cloudflare_answer_reason(array('status' => 0, 'json' => null)) !== '', 'a reason when nothing answered');
}

function test_cloudflare_analytics_totals_and_cards()
{
	$ok = array('status' => 200, 'json' => array('data' => array('viewer' => array('zones' => array(array('httpRequests1dGroups' => array(
		array('sum' => array('requests' => 10, 'bytes' => 2000000, 'cachedRequests' => 5, 'cachedBytes' => 1000000)),
	)))))));
	$uniques = array('status' => 200, 'json' => array('data' => array('viewer' => array('zones' => array(array('httpRequestsAdaptiveGroups' => array(
		array('sum' => array('visits' => 7)),
	)))))));
	$refused = array('status' => 200, 'json' => array('data' => null, 'errors' => array(array('message' => 'zone not authorized'))));

	$totals = pg_cloudflare_analytics_totals($ok, $uniques, null);
	pg_assert_same(10, $totals['requests'], 'requests summed');
	pg_assert_same(7, $totals['uniques'], 'visits summed');
	pg_assert_same('', $totals['error'], 'no error');
	pg_assert_same('', $totals['uniques_error'], 'no uniques error');
	pg_assert_contains('50', pg_cloudflare_analytics_card($totals, 'percent_cached'), 'half of the bytes cached');

	$failed = pg_cloudflare_analytics_totals($refused, $refused, $refused);
	pg_assert_same('zone not authorized', $failed['error'], 'the reason is kept');
	pg_assert_same('zone not authorized', $failed['uniques_error'], 'the uniques reason is kept');
	foreach (array('requests', 'uniques', 'bandwidth', 'percent_cached', 'cached_bytes') as $area) {
		pg_assert_same('—', pg_cloudflare_analytics_card($failed, $area), $area . ' shows a dash, not 0');
	}

	// The adaptive read refused but the daily fallback answered: visitors count.
	$fallback = array('status' => 200, 'json' => array('data' => array('viewer' => array('zones' => array(array('httpRequests1dGroups' => array(
		array('uniq' => array('uniques' => 4)),
	)))))));
	$partial = pg_cloudflare_analytics_totals($ok, $refused, $fallback);
	pg_assert_same(4, $partial['uniques'], 'fallback visitors');
	pg_assert_same('', $partial['uniques_error'], 'no uniques error when the fallback answered');
}

function test_cloudflare_page_reports_failures()
{
	$source = cloudflare_failed_reads_source();

	foreach (array('Delete record failed', 'Development Mode toggle failed', 'Under Attack Mode toggle failed', 'Always Use HTTPS toggle failed', 'Brotli toggle failed', 'Cache purge failed') as $text) {
		pg_assert_true((bool) preg_match("/lang\\('" . preg_quote($text, '/') . "'\\) \\. ': ' \\. h\\(pg_cloudflare_answer_reason\\(\\\$resp\\)\\)/", $source), $text . ' carries Cloudflare\'s reason');
	}

	// A setting that could not be read is drawn disabled, never as Off.
	foreach (array('cf_dev', 'cf_uam', 'cf_https', 'cf_brotli', 'cf_rocket') as $id) {
		pg_assert_true((bool) preg_match('/id="' . $id . '" onchange="this\.form\.submit\(\)"\'\.\$switch_focus\(\'' . $id . '\'\)\.\' \'\.\([^)]*\)\.\$switch_unread\(\'' . $id . '\'\)\.\'>/', $source), $id . ' is disabled when unread');
		pg_assert_contains("\$switch_unread_note('" . $id . "')", $source, $id . ' says it could not be read');
	}
	pg_assert_contains('pg_cloudflare_answer_failed($resp_fw, true)', $source, 'a GraphQL error is a failed firewall read');
	pg_assert_contains('$firewall_error', $source, 'the firewall row shows the reason');
	pg_assert_true(strpos($source, 'getCloudflareAnalytics(') === false, 'the analytics are read once, before the print');
	pg_assert_contains("\$audit_error .= ': ' . h(", $source, 'the audit log reason is escaped');
}
