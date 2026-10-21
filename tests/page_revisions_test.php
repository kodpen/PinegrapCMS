<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure parts of includes/fn/page_revisions.php: the retention
 * rule (which revisions expire), the settings snapshot read from a page row,
 * the source labels and the identity check that spares a duplicate row.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Rows newest first, one a day back from $now.
function page_revisions_test_rows($count, $now)
{
	$rows = array();

	for ($i = 0; $i < $count; $i++) {
		$rows[] = array('revision_id' => 100 + $i, 'created_at' => $now - $i * 86400);
	}

	return $rows;
}

// The newest `keep` rows never expire, however old they are.
function test_page_revisions_count_rule_protects_newest()
{
	$now  = 1800000000;
	$rows = page_revisions_test_rows(10, $now - 400 * 86400);

	pg_assert_same(array(103, 104, 105, 106, 107, 108, 109), pg_page_revisions_expired($rows, 3, 90, $now));
	pg_assert_same(array(), pg_page_revisions_expired($rows, 10, 90, $now));
	pg_assert_same(array(), pg_page_revisions_expired($rows, 50, 1, $now));
}

// A row younger than the period never expires, however many there are.
function test_page_revisions_age_rule_protects_recent()
{
	$now  = 1800000000;
	$rows = page_revisions_test_rows(10, $now);

	pg_assert_same(array(), pg_page_revisions_expired($rows, 1, 90, $now));
	// Rows 0..5 are within 5 days (row 5 sits on the cutoff and stays), rows
	// 6..9 are older: the age rule alone lets those go, and a count rule of
	// 8 keeps the first two of them as well.
	pg_assert_same(array(106, 107, 108, 109), pg_page_revisions_expired($rows, 0, 5, $now));
	pg_assert_same(array(108, 109), pg_page_revisions_expired($rows, 8, 5, $now));
}

// A row at the cutoff is kept; one second older goes.
function test_page_revisions_cutoff_is_inclusive()
{
	$now  = 1800000000;
	$rows = array(
		array('revision_id' => 1, 'created_at' => $now),
		array('revision_id' => 2, 'created_at' => $now - 2 * 86400),
		array('revision_id' => 3, 'created_at' => $now - 2 * 86400 - 1),
	);

	pg_assert_same(array(3), pg_page_revisions_expired($rows, 1, 2, $now));
}

// The retention settings are clamped into their bounds.
function test_page_revisions_retention_clamps()
{
	pg_assert_same(array('keep' => 1, 'days' => 1), pg_page_revisions_retention(0, -5));
	pg_assert_same(array('keep' => 500, 'days' => 3650), pg_page_revisions_retention(9999, 99999));
	pg_assert_same(array('keep' => 30, 'days' => 90), pg_page_revisions_retention(30, 90));
}

// Only known labels pass; anything else is a publish.
function test_page_revisions_source_labels()
{
	pg_assert_same('import', pg_page_revision_source('import'));
	pg_assert_same('restore', pg_page_revision_source(' Restore '));
	pg_assert_same('ai', pg_page_revision_source('ai'));
	pg_assert_same('publish', pg_page_revision_source('drop table'));
	pg_assert_same('publish', pg_page_revision_source(''));
}

// The snapshot takes the settings columns the row has and nothing else; a
// NULL reads as ''.
function test_page_revisions_settings_from_row()
{
	$row = array(
		'page_id'               => 7,
		'page_name'             => 'about',
		'page_folder'           => 3,
		'page_title'            => 'About us',
		'page_meta_description' => null,
		'page_tree_json'        => '{"type":"root"}',
		'page_home'             => 'yes',
		'custom_jsonld'         => '{"@type":"Organization"}',
	);

	$settings = pg_page_revision_settings_from_row($row);

	pg_assert_same(array(
		'page_name'             => 'about',
		'page_folder'           => '3',
		'page_title'            => 'About us',
		'page_meta_description' => '',
		'page_home'             => 'yes',
		'custom_jsonld'         => '{"@type":"Organization"}',
	), $settings);
	pg_assert_same(array(), pg_page_revision_settings_from_row(null));
}

// The newest revision spares a save that changed nothing it records.
function test_page_revisions_identical()
{
	$latest = array('tree_hash' => 'a', 'tree_code_hash' => 'b', 'settings_json' => '{"page_title":"x"}');

	pg_assert_same(true, pg_page_revision_identical($latest, 'a', 'b', '{"page_title":"x"}'));
	pg_assert_same(false, pg_page_revision_identical($latest, 'a', 'b', '{"page_title":"y"}'));
	pg_assert_same(false, pg_page_revision_identical($latest, 'c', 'b', '{"page_title":"x"}'));
	pg_assert_same(false, pg_page_revision_identical(null, 'a', 'b', '{"page_title":"x"}'));
}

// History › Compare draws the revision with the shared components and
// system widgets its tree places, also those the page has dropped since and
// the editor's cache does not hold: they are fetched before both sides are
// drawn, as loading a tree does.
function test_page_revisions_compare_fetches_the_revision_shared_components()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	$from = strpos($source, 'function _sdRevisionOpenCompare(');
	$section = ($from === false) ? '' : substr($source, $from, strpos($source, 'function _sdRevisionShowCompare(', $from) - $from);

	$collect = strpos($section, 'collectSharedIds(rev.tree, sids);');
	$fetch = strpos($section, 'prefetchShared(missing, function () {');
	$draw = strpos($section, 'generateHTML(styleName, addBc, rev.tree)');

	pg_assert_true(($collect !== false) && ($fetch !== false) && ($draw !== false), 'collect, fetch and draw are there');
	pg_assert_true(($collect < $fetch) && ($fetch < $draw), 'the revision is drawn after its components are fetched');
	pg_assert_contains('!_sharedCache[id]', $section, 'only what the cache does not hold');
}
