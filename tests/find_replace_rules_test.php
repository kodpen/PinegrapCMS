<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Find & Replace rules: the posted scope, the regular
 * expression built from the typed pattern (a "/" inside it, no empty match),
 * the LIKE term that lets a capital I find a dotless ı, the column limit a
 * replaced value must fit in and the screen's source pins.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_find_replace_rules_scope_none_is_none()
{
	$allowed = array('style' => array(), 'pregion' => array(), 'comments' => array());

	pg_assert_same(array(), pg_find_replace_scope(null, $allowed), 'no scope[] posted selects nothing, not everything');
	pg_assert_same(array(), pg_find_replace_scope('style', $allowed), 'a string is not a scope list');
	pg_assert_same(array('style', 'comments'), pg_find_replace_scope(array('style', 'comments', 'dregion', array('x')), $allowed), 'only known keys are kept');
	pg_assert_same(array('pregion'), pg_find_replace_scope(array('pregion', 'pregion'), $allowed), 'a key is kept once');
}

function test_find_replace_rules_regex_takes_a_slash()
{
	pg_assert_same('/a\/b/iu', pg_find_replace_regex('a/b', false), 'a bare slash is escaped for the delimiter');
	pg_assert_same('/a\/b/u', pg_find_replace_regex('a\/b', true), 'an escaped slash is kept as typed');
	pg_assert_same('/<\/p>/iu', pg_find_replace_regex('</p>', false), 'a closing tag works');
	pg_assert_same('/a\\\\\/b/iu', pg_find_replace_regex('a\\\\/b', false), 'an escaped backslash before a slash still needs the slash escaped');
	pg_assert_same('/[\/]x/iu', pg_find_replace_regex('[/]x', false), 'a slash in a class');
	pg_assert_same(1, preg_match(pg_find_replace_regex('a/b', false), 'x A/B y'), 'the pattern runs');
}

function test_find_replace_rules_regex_refusals()
{
	pg_assert_same('', pg_find_replace_regex_error('pg-[0-9]+', false), 'a valid pattern');
	pg_assert_same('', pg_find_replace_regex_error('a/b', false), 'a slash is not an error');
	pg_assert_same('invalid', pg_find_replace_regex_error('([', false), 'a broken pattern');
	foreach (array('x*', '^', 'a?', '$', '(?:)', 'b|') as $pattern) {
		pg_assert_same('empty', pg_find_replace_regex_error($pattern, false), $pattern . ' matches empty text');
	}
}

function test_find_replace_rules_empty_match_in_text()
{
	pg_assert_true(pg_find_replace_has_empty_match(pg_find_replace_regex('\b', false), 'one two'), 'a word boundary matches empty inside a text');
	pg_assert_true(pg_find_replace_has_empty_match(pg_find_replace_regex('(?=a)', false), 'bab'), 'a lookahead matches empty');
	pg_assert_false(pg_find_replace_has_empty_match(pg_find_replace_regex('a+', false), 'baab'), 'a non-empty match');
	pg_assert_false(pg_find_replace_has_empty_match(pg_find_replace_regex('\b', false), ''), 'nothing to match');
}

function test_find_replace_rules_like_term_capital_i()
{
	pg_assert_same('_rmak', pg_search_like_term('IRMAK'), 'a capital I stands for i or ı');
	pg_assert_same('_rmak', pg_search_like_term('ırmak'), 'a dotless ı stands for ı or I');
	pg_assert_same('istanbul', pg_search_like_term('İSTANBUL'), 'a capital İ lowercases as before');
	pg_assert_same('_stanbul', pg_search_like_term('ISTANBUL'), 'ISTANBUL fetches the İstanbul row too');
	pg_assert_same('a\%b\__', pg_search_like_term('a%b_I'), 'LIKE wildcards typed are escaped, the I is one character');
	pg_assert_same('', pg_search_like_term(''), 'empty term');
}

function test_find_replace_rules_capital_i_counts_dotted_capital()
{
	pg_assert_same(1, pg_search_ci_count('pgtD05c İstanbul', 'PGTD05C ISTANBUL'), 'ISTANBUL counts İstanbul');
	pg_assert_same(1, pg_search_ci_count('pgtD05c ırmak', 'PGTD05C IRMAK'), 'IRMAK counts ırmak');
	pg_assert_same(0, pg_search_ci_count('kir', 'kır'), 'a dotless ı still does not find a dotted i');
}

function test_find_replace_rules_too_long()
{
	$limits = array('short_description' => 100);

	pg_assert_false(pg_find_replace_too_long('short_description', str_repeat('ç', 100), $limits), '100 characters fit');
	pg_assert_true(pg_find_replace_too_long('short_description', str_repeat('ç', 101), $limits), '101 characters do not');
	pg_assert_false(pg_find_replace_too_long('full_description', str_repeat('a', 5000), $limits), 'a column with no limit');
}

function test_find_replace_rules_screen_pins()
{
	$source = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/find_and_replace.php');

	pg_assert_false(strpos($source, 'REGEXP BINARY') !== false, 'no REGEXP BINARY (MySQL 8 refuses it on utf8mb4)');
	pg_assert_false(strpos($source, "array_keys(\$all_table_defs)") !== false, 'no posted scope never means every table');
	pg_assert_contains('pg_find_replace_scope(', $source, 'the scope is read with the helper');
	pg_assert_contains('pg_search_like_term(', $source, 'the row filter uses the capital-I LIKE term');
	pg_assert_contains('data-confirm-content=', $source, 'Find & Replace asks before writing');
	pg_assert_contains("'name'  => 'scope[]'", $source, 'scope boxes are liveform fields and come back as ticked');
	pg_assert_contains("'touch'", $source, 'changed rows get their last-modified columns');
}

function test_find_replace_rules_snippet_ellipsis_only_when_cut()
{
	pg_assert_false(pg_find_replace_snippet_cut(10, 5, 80, 20), 'the text ends inside the context: no ellipsis');
	pg_assert_false(pg_find_replace_snippet_cut(10, 5, 80, 95), 'the context reaches the last character');
	pg_assert_true(pg_find_replace_snippet_cut(10, 5, 80, 96), 'one character left out');
}

function test_find_replace_rules_records_merge_by_key()
{
	$merged = pg_find_replace_merge_records(array(
		array('key' => 7, 'name' => 'Contact #A1', 'matches' => array(array('col' => 'title', 'count' => 1))),
		array('key' => 9, 'name' => 'Contact #B2', 'matches' => array(array('col' => 'body', 'count' => 2))),
		array('key' => 7, 'name' => 'Contact #A1', 'matches' => array(array('col' => 'body', 'count' => 3))),
	));

	pg_assert_same(2, count($merged), 'one record per submission');
	pg_assert_same(7, $merged[0]['key'], 'first seen order');
	pg_assert_same(array('title', 'body'), array_column($merged[0]['matches'], 'col'), 'the field rows of one submission stay together');
}

function test_find_replace_rules_screen_pins_results()
{
	$source = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/find_and_replace.php');

	pg_assert_contains('INNER JOIN page ON pregion.pregion_page = page.page_id', $source, 'a region whose page is gone is not searched');
	pg_assert_contains('pg_pages_bin_sql(pg_recycle_bin_folder_ids(), false)', $source, 'regions of binned pages are left out');
	pg_assert_contains("pg_catalog_bin_live_sql('products')", $source, 'binned products are left out');
	pg_assert_contains("pg_catalog_bin_live_sql('product_groups')", $source, 'binned product groups are left out');
	pg_assert_contains("'column_labels'", $source, 'the Field column shows a translated label');
	pg_assert_false(strpos($source, "h(\$m['col'])") !== false, 'no raw column name in the Field column');
	pg_assert_contains('pg_find_replace_merge_records(', $source, 'submitted form rows are counted per submission');
	pg_assert_contains('$find_results_limit', $source, 'the results are capped per type');
}
