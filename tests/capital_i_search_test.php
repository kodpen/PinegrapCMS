<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the older panel searches: a term typed with a capital İ is
 * lowercased without the U+0307 mb_strtolower() leaves, and Find & Replace
 * counts and replaces Ç/Ş/Ö/Ü/Ğ in any letter case with one matcher.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_capital_i_search_lower_drops_the_combining_dot()
{
	pg_assert_same('istanbul', pg_search_lower('İstanbul'), 'a capital İ lowercases to a plain i');
	pg_assert_same('iade', pg_search_lower('İADE'), 'a whole capital word');
	pg_assert_same('çerez şubat öğün ünlü', pg_search_lower('ÇEREZ ŞUBAT ÖĞÜN ÜNLÜ'), 'the other Turkish capitals lowercase as before');
	pg_assert_same('isparta', pg_search_lower('ISPARTA'), 'a dotless capital I stays an i');
	pg_assert_same('', pg_search_lower(null), 'no term is empty');
}

function test_capital_i_search_ci_count_folds_turkish_letters()
{
	pg_assert_same(2, pg_search_ci_count('Çerez Politikası ve çerez', 'çerez'), 'Ç and ç both count');
	pg_assert_same(2, pg_search_ci_count('şubat ŞUBAT', 'Şubat'), 'Ş and ş both count');
	pg_assert_same(2, pg_search_ci_count('Öğün ÖĞÜN', 'öğün'), 'Ö, Ğ and Ü fold');
	pg_assert_same(1, pg_search_ci_count('İade talebi', 'İade'), 'a capital İ term finds itself');
	pg_assert_same(1, pg_search_ci_count('a.b and axb', 'a.b'), 'the term is not a regular expression');
	pg_assert_same(0, pg_search_ci_count('anything', ''), 'an empty term counts nothing');
}

function test_capital_i_search_ci_replace_matches_what_count_lists()
{
	$count = -1;
	pg_assert_same('Cookie Politikası ve Cookie', pg_search_ci_replace('çerez', 'Cookie', 'Çerez Politikası ve çerez', $count), 'every counted match is replaced');
	pg_assert_same(2, $count, 'the replace count equals the find count');

	pg_assert_same('Ay ay', pg_search_ci_replace('ŞUBAT', 'ay', 'Ay şubat', $count), 'a lowercase ş is replaced for a capital Ş term');
	pg_assert_same(1, $count, 'one replacement');

	pg_assert_same('$40 /yıl \\1', pg_search_ci_replace('ÜCRET', '$40', 'ücret /yıl \\1', $count), 'the replacement stays literal');

	pg_assert_same('a/b', pg_search_ci_replace('x', 'y', 'a/b', $count), 'nothing matched leaves the text');
	pg_assert_same(0, $count, 'no replacement counted');

	pg_assert_same("\xff bad", pg_search_ci_replace('bad', 'good', "\xff bad", $count), 'text that is not UTF-8 is left as it was');
	pg_assert_same(0, $count, 'and counts nothing');
}

function test_capital_i_search_screens_use_the_helpers()
{
	$dir = PG_FUNCTIONS_DIR . '/';

	foreach (array('view_themes.php', 'view_comments.php', 'editor_select_image.php', 'editor_select_page_or_file.php', 'find_and_replace.php') as $file) {
		$source = (string) file_get_contents($dir . $file);
		pg_assert_contains('pg_search_lower(', $source, $file . ' lowercases the term with pg_search_lower()');
		pg_assert_false((bool) preg_match('/LIKE.*mb_strtolower\(|mb_strtolower\(\(\$_SESSION/', $source), $file . ' has no search term lowercased with mb_strtolower() alone');
	}

	$find = (string) file_get_contents($dir . 'find_and_replace.php');
	pg_assert_false(strpos($find, 'str_ireplace(') !== false, 'Find & Replace does not replace with str_ireplace()');
	pg_assert_contains('pg_search_ci_count(', $find, 'Find & Replace counts with the shared matcher');
	pg_assert_contains('pg_search_ci_replace(', $find, 'Find & Replace replaces with the shared matcher');
}
