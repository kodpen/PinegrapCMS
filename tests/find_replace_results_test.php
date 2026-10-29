<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for the Find & Replace screen: results tables that scroll
 * inside their card, icon ligatures kept out of accessible names, a Scope
 * header toggled from the keyboard and counts written as whole sentences.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function find_replace_results_source()
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/find_and_replace.php');
}

function test_find_replace_results_table_scrolls_in_card()
{
	$source = find_replace_results_source();

	pg_assert_contains('<div class="card-body p-0 table-responsive">', $source, 'results table wrapper');
	pg_assert_contains('<td class="align-top small font-monospace lh-lg text-break">', $source, 'context cell breaks');
	pg_assert_contains('<td class="align-top text-break">\' . $name_cell . \'</td>', $source, 'name cell breaks');
}

function test_find_replace_results_ligatures_hidden()
{
	$source = find_replace_results_source();

	foreach (array('search', 'find_replace', 'open_in_new', 'arrow_back', 'expand_more') as $ligature) {
		pg_assert_true(
			(bool) preg_match('/<span class="material-icons[^"]*"[^>]*aria-hidden="true"[^>]*>' . $ligature . '<\/span>/', $source),
			$ligature . ' ligature aria-hidden'
		);
	}
}

function test_find_replace_scope_header_is_button()
{
	$source = find_replace_results_source();

	pg_assert_contains('<button type="button" class="card-header', $source, 'scope header button');
	pg_assert_contains('aria-controls="scope_panel"', $source);
	pg_assert_false(strpos($source, 'cursor:pointer') !== false, 'no inline cursor style');
}

function test_find_replace_results_counts_are_sentences()
{
	$source = find_replace_results_source();

	pg_assert_contains("'{var:1} record'", $source);
	pg_assert_contains("'{var:1} records'", $source);
	pg_assert_contains("'{var:1} matches found in {var:2} records'", $source);
	pg_assert_false(strpos($source, "lang('matches found in')") !== false, 'summary not concatenated');
	pg_assert_false(strpos($source, "' ' . lang('record')") !== false, 'badge not concatenated');
}
