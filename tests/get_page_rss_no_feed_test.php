<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The RSS answer of a page that has no feed (get_page.php ?rss=true): the
 * page name is escaped in the channel description as it is in the title, so
 * a name with "<" still gives well-formed XML.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_get_page_rss_no_feed_description_escaped()
{
	$source = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/get_page.php');

	pg_assert_contains("'vars'=>h(\$page_name)));\n\t\tset_response_code(404);", $source, 'the description escapes the page name');
	pg_assert_same(false, strpos($source, "Calendar View, Catalog, Catalog Detail, or Order Form before a feed can be created.','vars'=>\$page_name)") !== false, 'no raw page name left');
}
