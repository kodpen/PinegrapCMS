<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that a File Manager page rename or move marks the page's SEO score
 * stale (includes/explorer/handle_items.php): the address and the folder are
 * part of the score, so the SEO column and the weak-SEO filters would keep
 * the old one.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function explorer_page_seo_stale_test_body($name)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/explorer/handle_items.php');
	$start = strpos($source, 'function ' . $name . '(');

	if ($start === false) {
		return '';
	}

	$next = strpos($source, "\nfunction ", $start + 1);

	return ($next === false) ? substr($source, $start) : substr($source, $start, $next - $start);
}

// The page UPDATE of each handler, from "UPDATE page SET" to its WHERE.
function explorer_page_seo_stale_test_update($body)
{
	$start = strpos($body, 'UPDATE page SET');

	if ($start === false) {
		return '';
	}

	$end = strpos($body, 'WHERE page_id', $start);

	return ($end === false) ? '' : substr($body, $start, $end - $start);
}

function test_explorer_page_rename_marks_the_seo_score_stale()
{
	$update = explorer_page_seo_stale_test_update(explorer_page_seo_stale_test_body('pg_explorer_handle_rename'));

	pg_assert_contains('page_name = ', $update, 'the rename writes the page name');
	pg_assert_contains("seo_analysis_current = '0'", $update, 'a renamed page is scored again');
}

function test_explorer_page_move_marks_the_seo_score_stale()
{
	$update = explorer_page_seo_stale_test_update(explorer_page_seo_stale_test_body('pg_explorer_handle_move'));

	pg_assert_contains('page_folder = ', $update, 'the move writes the page folder');
	pg_assert_contains("seo_analysis_current = '0'", $update, 'a moved page is scored again');
}
