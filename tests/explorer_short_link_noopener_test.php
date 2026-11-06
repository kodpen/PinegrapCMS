<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the File Manager (view_folders.php) opening a short link: the
 * link's address redirects to a destination outside the site, so the new
 * tab is opened without a reference back to the panel window.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function pg_test_short_link_noopener_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_folders.php');
}

// Opening a short link (double click or Enter) and its Visit menu entry
// both pass noopener.
function test_short_link_opens_without_opener()
{
	$source = pg_test_short_link_noopener_source();
	pg_assert_contains("if (item.short_link) { window.open(item.url, '_blank', 'noopener'); return; }", $source);
	pg_assert_contains("case 'short_link_visit': if (item && item.url) { window.open(item.url, '_blank', 'noopener'); }", $source);
}

// No short link path opens a tab that keeps window.opener.
function test_short_link_has_no_opener_call_left()
{
	$source = pg_test_short_link_noopener_source();
	pg_assert_true(strpos($source, "if (item.short_link) { window.open(item.url, '_blank'); return; }") === false, 'open keeps no opener');
	pg_assert_true(strpos($source, "case 'short_link_visit': if (item && item.url) { window.open(item.url, '_blank'); }") === false, 'visit keeps no opener');
}
