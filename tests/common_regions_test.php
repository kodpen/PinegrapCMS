<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the reader of the common and designer regions behind <cregion>
 * tags (includes/fn/content.php): pg_common_regions_read(), which asks only
 * for names it has not read yet, all of them in one query;
 * pg_common_regions_query(); pg_common_regions_from_rows(), which matches the
 * rows to the names the way a query per tag did; and the two region passes
 * of get_page_content(), which read through it instead of a query per tag.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A stand-in for the database: answers pg_common_regions_query() from a
// table of regions, comparing names without case as the column's collation
// does, and records every query it is asked.
function common_regions_test_fetch(array $table, array &$queries)
{
	return function ($sql) use ($table, &$queries) {
		$queries[] = $sql;

		pg_assert_same(1, preg_match('/WHERE cregion_name IN \((.*)\)\s+ORDER BY cregion_id$/s', $sql, $in), 'the query lists the names and orders by id');
		preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $in[1], $literals);
		$names = array_map('stripslashes', $literals[1]);

		$rows = array();

		foreach ($table as $region) {
			$positions = array();

			foreach ($names as $position => $name) {
				if (strtolower(rtrim($region[1])) === strtolower(rtrim($name))) {
					$positions[] = $position;
				}
			}

			if ($positions) {
				$rows[] = array(
					'cregion_id' => (string) $region[0],
					'cregion_content' => $region[3],
					'cregion_designer_type' => $region[2],
					'cregion_name' => $region[1],
					'pg_matches' => implode(',', $positions),
				);
			}
		}

		usort($rows, function ($a, $b) {
			return (int) $a['cregion_id'] - (int) $b['cregion_id'];
		});

		return $rows;
	};
}

// Of several regions a name finds, the one with the lowest id answers every
// spelling that finds them, whatever order the rows come in; a name nobody
// has gets null; the match column is not handed on.
function test_common_regions_from_rows_lowest_id()
{
	$names = array('pgt-dup', 'PGT-DUP', 'missing', 'Pgt-Dup');
	$rows = array(
		array('cregion_id' => '9', 'cregion_content' => 'high', 'cregion_designer_type' => 'no', 'cregion_name' => 'PGT-DUP', 'pg_matches' => '0,1,3'),
		array('cregion_id' => '4', 'cregion_content' => 'low', 'cregion_designer_type' => 'yes', 'cregion_name' => 'pgt-dup', 'pg_matches' => '0,1,3'),
	);

	$regions = pg_common_regions_from_rows($names, $rows);

	pg_assert_same(array('pgt-dup', 'PGT-DUP', 'missing', 'Pgt-Dup'), array_keys($regions), 'one entry per name, in the order asked');
	pg_assert_same('4', $regions['pgt-dup']['cregion_id'], 'the lowest id wins');
	pg_assert_same('4', $regions['PGT-DUP']['cregion_id'], 'also for the spelling of the higher row');
	pg_assert_same('pgt-dup', $regions['Pgt-Dup']['cregion_name'], 'the row keeps its own name');
	pg_assert_same(null, $regions['missing'], 'no region by that name');
	pg_assert_false(isset($regions['pgt-dup']['pg_matches']), 'the match column is dropped');
	pg_assert_same(array('cregion_id', 'cregion_content', 'cregion_designer_type', 'cregion_name'), array_keys($regions['pgt-dup']), 'the columns a query per tag returned');

	pg_assert_same(array('x' => null), pg_common_regions_from_rows(array('x'), array()), 'nothing found');
	pg_assert_same(null, pg_common_regions_from_rows(array('x'), array(array('cregion_id' => '1', 'pg_matches' => '7')))['x'], 'a position outside the list is ignored');
}

// Names are written as escaped literals, each compared once in the WHERE
// clause and once in the match column at its position.
function test_common_regions_query_lists_every_name()
{
	$sql = pg_common_regions_query(array('header', "it's", ''), 'addslashes');

	pg_assert_contains("WHERE cregion_name IN ('header', 'it\\'s', '')", $sql, 'every name in the IN list, escaped');
	pg_assert_contains("CONCAT_WS(',', IF(cregion_name = 'header', 0, NULL), IF(cregion_name = 'it\\'s', 1, NULL), IF(cregion_name = '', 2, NULL)) AS pg_matches", $sql, 'each name compared at its position');
	pg_assert_contains('FROM cregion', $sql, 'the regions table');
	pg_assert_same(1, preg_match('/ORDER BY cregion_id$/', $sql), 'by id');
}

// A name is read once: a repeated name in one call, or a name an earlier
// call read (found or not), costs no query; new names of a call share one.
function test_common_regions_read_once()
{
	$table = array(
		array(3, 'header', 'yes', '<nav></nav>'),
		array(5, 'Footer', 'no', '<footer></footer>'),
		array(8, '123', 'no', 'numeric name'),
	);
	$queries = array();
	$fetch = common_regions_test_fetch($table, $queries);
	$read = array();

	$regions = pg_common_regions_read($read, array('header', 'footer', 'header', 'nope'), $fetch, 'addslashes');

	pg_assert_same(1, count($queries), 'one query for a page');
	pg_assert_same(array('header', 'footer', 'nope'), array_keys($regions), 'each name once');
	pg_assert_same('3', $regions['header']['cregion_id'], 'found');
	pg_assert_same('Footer', $regions['footer']['cregion_name'], 'found under another spelling, with the row name');
	pg_assert_same(null, $regions['nope'], 'not found');
	pg_assert_contains("IN ('header', 'footer', 'nope')", $queries[0], 'a repeated name asked once');

	$regions = pg_common_regions_read($read, array('footer', 'nope', '123'), $fetch, 'addslashes');

	pg_assert_same(2, count($queries), 'only the new name is asked');
	pg_assert_false(strpos($queries[1], "'footer'") !== false, 'a name read before is not asked again');
	pg_assert_false(strpos($queries[1], "'nope'") !== false, 'nor a name found missing before');
	pg_assert_same('numeric name', $regions['123']['cregion_content'], 'a name made of digits');

	pg_common_regions_read($read, array('header', '123'), $fetch, 'addslashes');
	pg_assert_same(2, count($queries), 'all read: no query');

	pg_assert_same(array(), pg_common_regions_read($read, array(), $fetch, 'addslashes'), 'no names');
	pg_assert_same(2, count($queries), 'no names: no query');

	$many = array();

	for ($i = 0; $i < 250; $i++) {
		$many[] = 'region-' . $i;
	}

	pg_common_regions_read($read, $many, $fetch, 'addslashes');
	pg_assert_same(5, count($queries), 'at most 100 names per query');
}

// The source of get_page_content.php's two region passes, from the designer
// region pass to the page region pass.
function common_regions_test_passes_source()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_page_content.php');
	$from = strpos($source, "preg_match_all('/<cregion>.*?<\\/cregion>/i', \$content, \$regions);");
	$to = strpos($source, "preg_match_all('/<pregion><\\/pregion>/i', \$content, \$regions);");

	return (($from !== false) && ($to !== false) && ($to > $from)) ? substr($source, $from, $to - $from) : '';
}

// Neither pass asks for a region by itself: both read through
// pg_common_regions(), the designer region pass for the content's names and
// for the names inside its designer regions, the common region pass for
// what is left. Edit mode asks for the common regions a user may edit once.
function test_common_regions_page_passes_read_through_the_reader()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_page_content.php');
	$passes = common_regions_test_passes_source();

	pg_assert_true($passes !== '', 'the region passes are there');
	pg_assert_false(stripos($source, 'FROM cregion') !== false, 'get_page_content.php queries no region by itself');
	pg_assert_same(3, substr_count($passes, '= pg_common_regions('), 'the content, its designer regions, then the common region pass');
	pg_assert_same(1, substr_count($passes, "get_items_user_can_edit('common_regions'"), 'editable regions asked once');
	pg_assert_false(strpos($passes, "in_array(\$cregion_id, get_items_user_can_edit(") !== false, 'not once per region');
}
