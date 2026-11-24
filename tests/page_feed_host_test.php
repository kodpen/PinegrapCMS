<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the host of the addresses in a page's ?rss=true feed
 * (get_page.php): every item link and guid is built on the site's own
 * host (HOSTNAME_SETTING), never on the request's Host header (HOSTNAME),
 * which the client sets and a cache in front of the site would keep.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_page_feed_builds_addresses_on_the_site_host()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_page.php');
	$start = strpos($source, "else if (isset(\$_GET['rss']) && \$_GET['rss'] == 'true')");
	$end = strpos($source, "elseif (isset(\$_GET['icalendar'])");
	pg_assert_true($start !== false && $end !== false && $start < $end, 'the feed branch is found');

	$branch = substr($source, $start, $end - $start);
	pg_assert_same(0, preg_match_all('/\bHOSTNAME\b(?!_)/', $branch), 'no feed address on the request host');
	pg_assert_true(substr_count($branch, 'URL_SCHEME . HOSTNAME_SETTING . OUTPUT_PATH') >= 9, 'item links and guids on the site host');
}
