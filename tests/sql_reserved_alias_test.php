<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A guard over the product tree: no query names a column or table alias
 * with a bare word MySQL 8 reserves. MariaDB takes most of these words as
 * names, so a query tried there runs, while MySQL 8 answers a syntax error
 * and the query returns false.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Words MySQL 5.7 and 8.0 reserved that read like a good alias. The SQL of
// the product writes AS in capitals, so a capital AS before a bare word of
// this list is an alias; a backtick-quoted one is allowed.
function sql_reserved_alias_test_words()
{
	return array(
		'array', 'cube', 'cume_dist', 'dense_rank', 'empty', 'except',
		'first_value', 'function', 'generated', 'grouping', 'groups',
		'intersect', 'json_table', 'lag', 'last_value', 'lateral', 'lead',
		'member', 'nth_value', 'ntile', 'of', 'over', 'percent_rank', 'rank',
		'recursive', 'row', 'row_number', 'rows', 'stored', 'system',
		'virtual', 'window',
	);
}

function sql_reserved_alias_test_pattern()
{
	return '/\sAS\s+(' . implode('|', sql_reserved_alias_test_words()) . ')(?![a-z0-9_`])/';
}

function test_sql_reserved_alias_pattern()
{
	$pattern = sql_reserved_alias_test_pattern();

	pg_assert_true((bool) preg_match($pattern, "SELECT f.name AS stored FROM files f"), 'a reserved alias');
	pg_assert_true((bool) preg_match($pattern, "SELECT COUNT(*) AS rank\nFROM page"), 'at the end of a line');
	pg_assert_false((bool) preg_match($pattern, "SELECT f.name AS stored_name FROM files f"), 'a longer name');
	pg_assert_false((bool) preg_match($pattern, "SELECT f.name AS `stored` FROM files f"), 'quoted');
	pg_assert_false((bool) preg_match($pattern, "SELECT f.name AS name FROM files f"), 'a free word');
	pg_assert_false((bool) preg_match($pattern, "// The stock as stored, read once"), 'a comment writes as in lower case');
}

function test_sql_aliases_avoid_words_mysql8_reserves()
{
	$root = str_replace('\\', '/', PG_FUNCTIONS_DIR);
	$skip = '#/(includes/(dompdf|phpmailer|vendor|htmlpurifier)|vendor)/#i';
	$pattern = sql_reserved_alias_test_pattern();
	$found = array();
	$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(PG_FUNCTIONS_DIR, RecursiveDirectoryIterator::SKIP_DOTS));

	foreach ($files as $file) {
		$path = str_replace('\\', '/', $file->getPathname());

		if ((substr($path, -4) !== '.php') || preg_match($skip, $path)) {
			continue;
		}

		foreach (explode("\n", (string) file_get_contents($path)) as $number => $line) {
			if ((strpos($line, 'AS ') !== false) && preg_match($pattern, $line)) {
				$found[] = ltrim(substr($path, strlen($root)), '/') . ':' . ($number + 1);
			}
		}
	}

	pg_assert_same(array(), $found);
}
