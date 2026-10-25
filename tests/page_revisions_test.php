<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure parts of includes/fn/page_revisions.php: the retention
 * rule (which revisions expire), the settings snapshot read from a page row,
 * the source labels, the identity check that spares a duplicate row, and the
 * first revision of a page that has none (its values, the style's tree as
 * fallback, and where the save calls it), and which failed INSERT is tried
 * again.
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

// Every page column the designer's save writes, besides the tree, is in the
// snapshot: a publish that changes only one of them (a comment setting the
// panel sets) records a revision. The style, the tree and the schedule are
// recorded elsewhere or not at all.
function test_page_revisions_settings_cover_the_designer_save()
{
	$fn = new ReflectionFunction('pg_designer_save_page');
	$lines = file($fn->getFileName());
	$body = implode('', array_slice($lines, $fn->getStartLine() - 1, $fn->getEndLine() - $fn->getStartLine() + 1));

	$columns = array();
	preg_match_all("/\\\$(?:c_extra|own)\\['([a-z_]+)'\\]\\s*=/", $body, $m);
	$columns = array_merge($columns, $m[1]);
	pg_assert_true(preg_match('/\$own = array\((.*?)\);/s', $body, $own) === 1, 'the save lists its own columns');
	preg_match_all("/'([a-z_]+)'\s*=>/", isset($own[1]) ? $own[1] : '', $m);
	$columns = array_merge($columns, $m[1]);
	foreach (pg_comment_editor_keys() as $key => $def) {
		$columns = array_merge($columns, $def['columns']);
	}
	$columns = array_values(array_diff(array_unique($columns), array('page_style', 'page_tree_json', 'publish_at', 'unpublish_at')));

	pg_assert_true(in_array('comments_allow_file_attachments', $columns, true), 'the comment settings are among them');
	pg_assert_true(in_array('canonical_url', $columns, true), 'and the robots and meta columns');

	$snapshot = pg_page_revision_settings_from_row(array_fill_keys($columns, 'x'));
	foreach ($columns as $column) {
		pg_assert_true(array_key_exists($column, $snapshot), $column . ' is in the revision\'s settings');
	}
}

// The newest revision spares a save that changed nothing it records: the
// tree and the settings decide, the drawn HTML does not (the renderer gives
// a navbar or carousel a fresh id on every draw, so its hash differs between
// two draws of one tree).
function test_page_revisions_identical()
{
	$latest = array('tree_hash' => 'a', 'tree_code_hash' => 'b', 'settings_json' => '{"page_title":"x"}');

	pg_assert_same(true, pg_page_revision_identical($latest, 'a', '{"page_title":"x"}'));
	pg_assert_same(false, pg_page_revision_identical($latest, 'a', '{"page_title":"y"}'));
	pg_assert_same(false, pg_page_revision_identical($latest, 'c', '{"page_title":"x"}'));
	pg_assert_same(false, pg_page_revision_identical(null, 'a', '{"page_title":"x"}'));

	// The newest row's drawn HTML differs from this draw of the same tree.
	$redrawn = array('tree_hash' => 'a', 'tree_code_hash' => 'other', 'settings_json' => '{"page_title":"x"}');
	pg_assert_same(true, pg_page_revision_identical($redrawn, 'a', '{"page_title":"x"}'));
}

// A page that has no revision yet is recorded as it is stored before its
// first changing save: the stored tree and body, the settings, the author
// and time of the last save. A row without a tree gives nothing to record.
function test_page_revisions_seed_values()
{
	$row = array(
		'page_id'        => 7,
		'page_style'     => 3,
		'page_name'      => 'about',
		'page_folder'    => 1,
		'page_title'     => 'About us',
		'page_tree_json' => '{"type":"root"}',
		'page_tree_code' => '<div>About</div>',
		'page_user'      => 5,
		'page_timestamp' => 1700000000,
	);

	$values = pg_page_revision_seed_values($row, 1800000000);

	pg_assert_same(3, $values['style_id']);
	pg_assert_same('{"type":"root"}', $values['tree_json']);
	pg_assert_same(sha1('{"type":"root"}'), $values['tree_hash']);
	pg_assert_same(sha1('<div>About</div>'), $values['code_hash']);
	pg_assert_same('{"page_name":"about","page_folder":"1","page_title":"About us"}', $values['settings_json']);
	pg_assert_same(5, $values['user_id']);
	pg_assert_same(1700000000, $values['created_at']);

	// A row saved before the time was stamped is dated now.
	$row['page_timestamp'] = 0;
	pg_assert_same(1800000000, pg_page_revision_seed_values($row, 1800000000)['created_at']);

	// No tree (a classic page, or one never saved by the designer): nothing.
	$row['page_tree_json'] = '';
	pg_assert_same(null, pg_page_revision_seed_values($row, 1800000000));
	unset($row['page_tree_json']);
	pg_assert_same(null, pg_page_revision_seed_values($row, 1800000000));
	pg_assert_same(null, pg_page_revision_seed_values(null, 1800000000));
}

// A page saved before the per-page tree (no page_tree_json; the editor opens
// it with its style's tree) is recorded with the style's tree and body, so
// its first changing save still leaves a way back. A page with a tree of its
// own keeps it; a page with no tree anywhere still gives nothing to record.
function test_page_revisions_seed_row_takes_the_style_tree()
{
	$row = array(
		'page_id'        => 9,
		'page_style'     => 4,
		'page_name'      => 'contact',
		'page_tree_json' => null,
		'page_tree_code' => null,
		'page_user'      => 2,
		'page_timestamp' => 1700000000,
	);

	$values = pg_page_revision_seed_values(
		pg_page_revision_seed_row($row, '{"type":"root","from":"style"}', '<div>Style</div>'), 1800000000);

	pg_assert_true(is_array($values), 'an empty page tree is seeded from the style');
	pg_assert_same('{"type":"root","from":"style"}', $values['tree_json']);
	pg_assert_same(sha1('{"type":"root","from":"style"}'), $values['tree_hash']);
	pg_assert_same(sha1('<div>Style</div>'), $values['code_hash']);
	pg_assert_same(4, $values['style_id']);
	pg_assert_same(1700000000, $values['created_at']);

	// An empty string is as empty as NULL.
	$row['page_tree_json'] = " \n";
	pg_assert_same('{"type":"root","from":"style"}',
		pg_page_revision_seed_row($row, '{"type":"root","from":"style"}', '')['page_tree_json']);

	// The page's own tree wins over the style's, and so does its own body.
	$own = $row;
	$own['page_tree_json'] = '{"type":"root","from":"page"}';
	$own['page_tree_code'] = '<div>Page</div>';
	pg_assert_same($own, pg_page_revision_seed_row($own, '{"type":"root","from":"style"}', '<div>Style</div>'));

	// A page body already there is kept when only the tree is missing.
	$body = $row;
	$body['page_tree_code'] = '<div>Page</div>';
	pg_assert_same('<div>Page</div>', pg_page_revision_seed_row($body, '{"type":"root"}', '<div>Style</div>')['page_tree_code']);

	// No tree on the page or on the style: still nothing to record.
	pg_assert_same(null, pg_page_revision_seed_values(pg_page_revision_seed_row($row, '', '<div>Style</div>'), 1800000000));
	pg_assert_same(null, pg_page_revision_seed_row(null, '{"type":"root"}', ''));
}

// The first revision is written from the row as stored, so the seed must run
// before the save's UPDATE and the publish's revision after it; the other way
// round revision 1 would be the new state and History would have no way back.
function test_page_revisions_seed_written_before_the_update()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/designer.php');
	$from = strpos($source, 'function pg_designer_save_page(');
	$end = ($from === false) ? false : strpos($source, "\nfunction ", $from + 1);
	$body = ($from === false) ? '' : (($end === false) ? substr($source, $from) : substr($source, $from, $end - $from));

	$seed = strpos($body, 'pg_page_revision_seed($page_id)');
	$update = strpos($body, 'db("UPDATE page SET');
	$record = strpos($body, '$revision_no = pg_page_revision_record(');

	pg_assert_true(($seed !== false) && ($update !== false) && ($record !== false), 'seed, update and record are there');
	pg_assert_true($seed < $update, 'the seed reads the row before the save writes over it');
	pg_assert_true($update < $record, 'the publish is recorded after the save');
}

// The seed and the publish that follows encode the settings the same way: a
// save that changes neither the tree nor the settings finds the seed equal and
// writes no second row; a changed tree does.
function test_page_revisions_seed_then_identical_save()
{
	$row = array(
		'page_id'        => 7,
		'page_style'     => 3,
		'page_name'      => 'about',
		'page_folder'    => 1,
		'page_title'     => 'Über uns / About',
		'page_tree_json' => '{"type":"root"}',
		'page_tree_code' => '<div>About</div>',
		'page_user'      => 5,
		'page_timestamp' => 1700000000,
	);

	$values = pg_page_revision_seed_values($row, 1800000000);
	$latest = array('tree_hash' => $values['tree_hash'], 'settings_json' => $values['settings_json']);
	$settings_json = (string) json_encode(pg_page_revision_settings_from_row($row), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

	pg_assert_same(true, pg_page_revision_identical($latest, sha1('{"type":"root"}'), $settings_json));
	pg_assert_same(false, pg_page_revision_identical($latest, sha1('{"type":"root","x":1}'), $settings_json));
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

// Two writes of one page at the same moment fail on each other's locks more
// often than on the unique key. A deadlock or an auto-increment value the
// engine could not read is tried again after a growing wait, a duplicate
// number at once; four statements at most.
function test_page_revisions_retry_on_lock_errors()
{
	foreach (array(1213, 1467) as $errno) {
		pg_assert_same(20000, pg_page_revision_retry_delay($errno, 1), $errno . ' after the first try');
		pg_assert_same(40000, pg_page_revision_retry_delay($errno, 2), $errno . ' after the second try');
		pg_assert_same(60000, pg_page_revision_retry_delay($errno, 3), $errno . ' after the third try');
		pg_assert_same(-1, pg_page_revision_retry_delay($errno, 4), $errno . ' not after the fourth');
	}

	// A lock wait timeout has already waited innodb_lock_wait_timeout.
	pg_assert_same(20000, pg_page_revision_retry_delay(1205, 1));
	pg_assert_same(-1, pg_page_revision_retry_delay(1205, 2));

	pg_assert_same(0, pg_page_revision_retry_delay(1062, 1));
	pg_assert_same(0, pg_page_revision_retry_delay(1062, 3));
	pg_assert_same(-1, pg_page_revision_retry_delay(1062, 4));
	pg_assert_same(-1, pg_page_revision_retry_delay('1062', 0), 'no statement run yet');
}

// The first revision a duplicate ends: the other write recorded the page.
// Its lock errors are tried again like any other write's.
function test_page_revisions_seed_retry_ends_on_duplicate()
{
	pg_assert_same(-1, pg_page_revision_retry_delay(1062, 1, true));
	pg_assert_same(20000, pg_page_revision_retry_delay(1213, 1, true));
	pg_assert_same(40000, pg_page_revision_retry_delay(1467, 2, true));
}

// Anything but a duplicate or a lock error is a real failure: no second try.
function test_page_revisions_no_retry_on_other_errors()
{
	foreach (array(0, 1054, 1146, 1406, 2006) as $errno) {
		pg_assert_same(-1, pg_page_revision_retry_delay($errno, 1), (string) $errno);
	}
}

// Both writers ask the rule after a failed statement and stop when it says
// so; neither stops on the first error that is not a duplicate any more.
function test_page_revisions_writers_ask_the_retry_rule()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/page_revisions.php');

	foreach (array('pg_page_revision_seed' => ', true)', 'pg_page_revision_record' => ', $attempt)') as $name => $call) {
		$from = strpos($source, 'function ' . $name . '(');
		$end = ($from === false) ? false : strpos($source, "\nfunction ", $from + 1);
		$body = ($from === false) ? '' : (($end === false) ? substr($source, $from) : substr($source, $from, $end - $from));

		pg_assert_true(strpos($body, 'pg_page_revision_retry_delay(') !== false, $name . ' asks the rule');
		pg_assert_true(strpos($body, $call . ';') !== false, $name . ' passes its own case');
		pg_assert_true(preg_match('/if \(\$delay < 0\) \{\s*break;\s*\}/', $body) === 1, $name . ' stops when told');
		pg_assert_true(strpos($body, '!== 1062) {' . "\n" . '            break;') === false, $name . ' does not stop on a lock error');
	}
}
