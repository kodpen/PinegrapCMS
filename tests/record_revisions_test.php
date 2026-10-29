<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for collection item revisions (includes/fn/record_revisions.php,
 * 2026.11.0 step 11.0.123): the values read from form_data rows, the hash
 * that leaves the complete flag out, when a save writes a revision and when
 * an item is seeded first, the size guard, the source labels, the writers
 * that take the state before their save, the erasure's record list and the
 * migration step.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Values by field id in field order, a file by its id, empty values dropped.
function test_record_revisions_values_from_rows()
{
	$rows = array(
		array('form_field_id' => '12', 'data' => 'b', 'file_id' => '0'),
		array('form_field_id' => '3', 'data' => 'Title', 'file_id' => '0'),
		array('form_field_id' => '12', 'data' => 'a', 'file_id' => '0'),
		array('form_field_id' => '7', 'data' => '', 'file_id' => '55'),
		array('form_field_id' => '9', 'data' => '', 'file_id' => '0'),
		array('form_field_id' => '0', 'data' => 'stray', 'file_id' => '0'),
	);

	pg_assert_same(array(
		'3'  => array('Title'),
		'7'  => array(array('file_id' => 55)),
		'12' => array('b', 'a'),
	), pg_record_revision_values($rows));

	pg_assert_same('{"3":["Title"],"7":[{"file_id":55}],"12":["b","a"]}', pg_record_revision_json(pg_record_revision_values($rows)));
	pg_assert_same('{}', pg_record_revision_json(pg_record_revision_values(array())), 'no values');
	pg_assert_same('{"3":["Çay"]}', pg_record_revision_json(array('3' => array('Çay'))), 'unicode kept as is');
}

// A blank row where there was none reads as the same state.
function test_record_revisions_blank_rows_do_not_count()
{
	$a = pg_record_revision_values(array(array('form_field_id' => 3, 'data' => 'x', 'file_id' => 0)));
	$b = pg_record_revision_values(array(
		array('form_field_id' => 3, 'data' => 'x', 'file_id' => 0),
		array('form_field_id' => 8, 'data' => '', 'file_id' => 0),
	));

	pg_assert_same(pg_record_revision_json($a), pg_record_revision_json($b));
}

// The hash covers the values and the address name, not the complete flag.
function test_record_revisions_hash()
{
	$values = '{"3":["Title"]}';
	$record = pg_record_revision_record_fields(array('address_name' => 'title', 'complete' => '1'));

	pg_assert_same(array('address_name' => 'title', 'complete' => 1), $record);
	pg_assert_same(array('address_name' => '', 'complete' => 0), pg_record_revision_record_fields(null));
	pg_assert_same(40, strlen(pg_record_revision_hash($values, 'title')));
	pg_assert_true(pg_record_revision_hash($values, 'title') !== pg_record_revision_hash($values, 'title-2'), 'address name counts');
	pg_assert_true(pg_record_revision_hash($values, 'title') !== pg_record_revision_hash('{"3":["Other"]}', 'title'), 'values count');
	pg_assert_true(pg_record_revision_hash('{"3":["a"]}', '') !== pg_record_revision_hash('{"3":["a', '"]}'), 'values and name kept apart');
}

// When a save writes a revision, and when the item is seeded first.
function test_record_revisions_plan()
{
	$a = sha1('a');
	$b = sha1('b');

	// An item with history: one row when the new state differs from the newest.
	pg_assert_same(array('seed' => false, 'write' => true), pg_record_revision_plan($a, $a, $b));
	pg_assert_same(array('seed' => false, 'write' => false), pg_record_revision_plan($b, $a, $b), 'equal to the newest');
	pg_assert_same(array('seed' => false, 'write' => false), pg_record_revision_plan($a, $a, $a), 'complete-only save');

	// No history: the state before is recorded first, then the new one.
	pg_assert_same(array('seed' => true, 'write' => true), pg_record_revision_plan(null, $a, $b));
	pg_assert_same(array('seed' => false, 'write' => false), pg_record_revision_plan(null, $a, $a), 'unchanged save seeds nothing');
	pg_assert_same(array('seed' => false, 'write' => false), pg_record_revision_plan(null, null, $b), 'state before unknown');
}

// A revision above its columns is skipped, never written cut short.
function test_record_revisions_fits()
{
	pg_assert_true(pg_record_revision_fits('{}', '{}'));
	pg_assert_true(pg_record_revision_fits(str_repeat('a', PG_RECORD_REVISION_VALUES_MAX), '{}'), 'MEDIUMTEXT edge');
	pg_assert_false(pg_record_revision_fits(str_repeat('a', PG_RECORD_REVISION_VALUES_MAX + 1), '{}'));
	pg_assert_false(pg_record_revision_fits('{}', str_repeat('a', PG_RECORD_REVISION_RECORD_MAX + 1)));
}

function test_record_revisions_source_labels()
{
	pg_assert_same(array('edit', 'restore', 'api', 'workspace'), pg_record_revision_sources());
	pg_assert_same('api', pg_record_revision_source(' API '));
	pg_assert_same('edit', pg_record_revision_source('publish'), 'an unknown label is edit');
	pg_assert_true(strlen(PG_RECORD_REVISION_SOURCES) > 0 && max(array_map('strlen', pg_record_revision_sources())) <= 16, 'fits VARCHAR(16)');
}

// Each writer takes the state before its write and records after the
// address name is renamed, with its own source.
function test_record_revisions_writers()
{
	$writers = array(
		'edit_submitted_form.php'          => array('UPDATE forms', "'edit'"),
		'includes/api/resources/forms.php' => array('UPDATE forms', "'api'"),
		'includes/workspace/changes.php'   => array('UPDATE forms', "'workspace'"),
	);

	foreach ($writers as $file => $expect) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		$before = strpos($source, 'pg_record_revision_before(');
		$after  = strpos($source, 'pg_record_revision_after(');

		pg_assert_true($before !== false && $after !== false, $file . ' calls both');
		pg_assert_same(1, substr_count($source, 'pg_record_revision_after('), $file . ' one write');
		pg_assert_true($before < strpos($source, $expect[0], $before - 2000 > 0 ? $before - 2000 : 0), $file . ' before the update');
		pg_assert_true($after > strrpos(substr($source, 0, $after), 'update_submitted_form_address_name('), $file . ' after the rename');
		pg_assert_contains($expect[1], substr($source, $after, 200), $file . ' source');
	}
}

// An erasure removes the revisions of records it deletes and of those it
// blanks a field of.
function test_record_revisions_erasure()
{
	pg_assert_same(array(3, 5, 9), pg_privacy_erase_revision_form_ids(array(9, 3), array('5', 3, 0, -1)));
	pg_assert_same(array(), pg_privacy_erase_revision_form_ids(array(), array()));

	$tables = pg_privacy_erase_tables();
	pg_assert_same(array('find' => array('form_id' => 'revision_form_ids'), 'action' => 'delete', 'fields' => array()), $tables['record_revisions']);
}

function _record_revisions_migration_step()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/2026.11.0.php');
	return preg_match('/function upgrade_2026_11_0_record_revisions\(\) \{(.*?)\n\}\n/s', $source, $m) ? $m[1] : '';
}

// The table is created once, by the guarded helper, under one label, and a
// reinstall knows it.
function test_record_revisions_migration_step()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/2026.11.0.php');
	pg_assert_same(1, substr_count($source, "\tupgrade_2026_11_0_record_revisions();       // 11.0.123\n"), 'called once from upgrade_to_2026_11_0()');
	preg_match('/function upgrade_to_2026_11_0\(\) \{(.*?)\n\}\n/s', $source, $entry);
	preg_match_all('#// (11\.0\.\d+)\n#', isset($entry[1]) ? $entry[1] : '', $labels);
	pg_assert_same(1, count(array_keys($labels[1], '11.0.123', true)), 'no other step carries 11.0.123');

	$step = _record_revisions_migration_step();
	pg_assert_contains("install_create_table('record_revisions', \"CREATE TABLE record_revisions (", $step);
	foreach (array('values_json  MEDIUMTEXT NOT NULL', 'record_json  TEXT NOT NULL', "values_hash  CHAR(40) NOT NULL DEFAULT ''",
		"source       VARCHAR(16) NOT NULL DEFAULT 'edit'", 'UNIQUE KEY idx_record_revision (form_id, revision_no)',
		'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4') as $part) {
		pg_assert_contains($part, $step);
	}
	pg_assert_true(stripos($step, 'FOREIGN KEY') === false, 'no foreign keys');
	pg_assert_true(stripos($step, 'ALTER TABLE') === false && strpos($step, 'db(') === false, 'no raw statement');
	pg_assert_true(preg_match("/install_note\\('(?:[^'\\\\]|\\\\.)+'\\);\\s*\$/", $step) === 1, 'ends with a note for the operator');

	$install = (string) file_get_contents(PG_FUNCTIONS_DIR . '/install/index.php');
	pg_assert_contains("\t\t'record_revisions',\n", $install, 'listed in get_tables()');
}
