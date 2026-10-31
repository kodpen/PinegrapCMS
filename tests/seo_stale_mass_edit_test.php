<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that Mass Edit (its table and its Find and Replace card) and the
 * Find & Replace screen mark the SEO score of what they change stale: the
 * scheduled SEO job recalculates only rows with seo_analysis_current = 0, so
 * the SEO column and the weak-SEO filters would keep the old score.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function seo_stale_mass_edit_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

// Every "UPDATE page SET ..." statement of a file, up to its WHERE.
function seo_stale_mass_edit_test_page_updates($source)
{
	preg_match_all('/UPDATE page SET (.*?)WHERE page_id/s', $source, $matches);

	return $matches[1];
}

function test_mass_edit_page_updates_mark_the_seo_score_stale()
{
	$updates = seo_stale_mass_edit_test_page_updates(seo_stale_mass_edit_test_source('mass_edit.php'));

	// Find and Replace card, then page name, title, description, search and
	// keywords of the table.
	pg_assert_true(count($updates) >= 6, 'the six page updates are found');

	foreach ($updates as $update) {
		pg_assert_contains("seo_analysis_current = '0'", $update, 'a Mass Edit page update marks the score stale');
	}
}

function test_find_and_replace_marks_products_and_groups_stale()
{
	$source = seo_stale_mass_edit_test_source('find_and_replace.php');

	foreach (array('products', 'product_groups') as $table) {
		pg_assert_true(
			(bool) preg_match("/'table'\s*=>\s*'" . $table . "',(?:(?!\];).)*'seo_stale'\s*=>\s*'row'/s", $source),
			$table . ' rows are marked stale in the same UPDATE'
		);
	}

	pg_assert_contains("\$sql_set .= \", seo_analysis_current = '0'\";", $source, 'the row mark is part of the UPDATE');
}

function test_find_and_replace_marks_the_region_page_stale()
{
	$source = seo_stale_mass_edit_test_source('find_and_replace.php');

	pg_assert_true(
		(bool) preg_match("/'table'\s*=>\s*'pregion',(?:(?!\];).)*'seo_stale_page'\s*=>\s*'pregion_page'/s", $source),
		'a page region names the page it belongs to'
	);
	pg_assert_contains("UPDATE page SET seo_analysis_current = '0' WHERE page_id = ?", $source, 'the region page is marked stale');
}
