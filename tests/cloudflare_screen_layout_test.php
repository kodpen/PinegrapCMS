<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Cloudflare Tools screen layout (cloudflare.php): period,
 * analytics, DNS records and logs in a form column, the zone switches and
 * Clear Cache in a sticky status column written after it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function cloudflare_screen_layout_source()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/cloudflare.php');

	return substr($source, (int) strpos($source, 'print pg_page_shell('));
}

function test_cloudflare_screen_has_form_and_status_columns()
{
	$source = cloudflare_screen_layout_source();

	$form = strpos($source, '<div class="col-12 lg:col-8 2xl:col-9 order-1">');
	$status = strpos($source, '<div class="col-12 lg:col-4 2xl:col-3 order-2">');

	pg_assert_true($form !== false, 'the form column is there');
	pg_assert_true($status !== false, 'the status column is there');
	pg_assert_true($form < $status, 'form column before status column');
	pg_assert_contains('<div class="position-sticky my-7" style="top:3.3rem;">', $source, 'the status column sticks below the top bar');

	foreach (array('nav nav-tabs', "pg_cloudflare_analytics_card(\$analytics, 'requests')", '$image_vary_card', "pg_list_table_search_box('cloudflare_dns')", '$audit_rows', '$fw_rows') as $part) {
		$at = strpos($source, $part);
		pg_assert_true($at !== false && $at > $form && $at < $status, $part . ' sits in the form column');
	}

	foreach (array('id="cf_dev"', 'id="cf_uam"', 'id="cf_https"', 'id="cf_brotli"', 'id="cf_rocket"', 'value="clear_cache"') as $part) {
		$at = strpos($source, $part);
		pg_assert_true($at !== false && $at > $status, $part . ' sits in the status column');
	}
}

function test_cloudflare_screen_analytics_fill_the_row()
{
	$source = cloudflare_screen_layout_source();

	pg_assert_true(strpos($source, '<div class="md:col-2"><div class="card text-center">') === false, 'no analytics card is a fixed sixth of the row');
	pg_assert_true(substr_count($source, '<div class="col-6 md:col-4 xl:col"><div class="card h-100">') === 5, 'the five analytics cards share the row');
	pg_assert_contains('<span class="pg-btn-label">\' . lang(\'Clear Cache\') . \'</span>', $source, 'the Clear Cache label is in its span');
}
