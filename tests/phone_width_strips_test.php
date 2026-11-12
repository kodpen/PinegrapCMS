<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that three panel strips wrap at phone width instead of pushing the
 * page sideways: the e-invoice inbox fetch form, the header buttons of the
 * marketplace "Matched products" card and the image picker toolbar. Also
 * pins the Unsplash result tile as a keyboard-reachable button built
 * without innerHTML.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function phone_width_strips_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function test_phone_width_inbox_fetch_form_wraps()
{
	$source = phone_width_strips_test_source('erp_inbox.php');

	pg_assert_true(preg_match('/<form action="erp_inbox\.php" method="post" class="[^"]*\bflex-wrap\b[^"]*\brow-gap-2\b/', $source) === 1, 'fetch form wraps');

	// The date range stays together on one row; the button may drop below it.
	pg_assert_true(preg_match('/<span class="d-inline-flex align-items-center gap-1">\s*<input type="date" data-pg-date name="from"[^>]*>\s*<span class="fg-secondary">&ndash;<\/span>\s*<input type="date" data-pg-date name="to"[^>]*>\s*<\/span>/', $source) === 1, 'date range is one group');
}

function test_phone_width_marketplace_card_header_buttons_wrap()
{
	$source = phone_width_strips_test_source('marketplace_settings.php');

	preg_match_all('/<div class="ms-auto d-flex[^"]*">/', $source, $clusters);

	pg_assert_true(count($clusters[0]) >= 2, 'card header clusters found');

	foreach ($clusters[0] as $cluster) {
		pg_assert_true(strpos($cluster, ' flex-wrap ') !== false && strpos($cluster, 'justify-content-end') !== false, $cluster . ' wraps');
	}
}

