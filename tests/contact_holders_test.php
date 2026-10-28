<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_contact_holders() (includes/fn/contacts.php), the one list of
 * the places a contact's id is kept, shared by merge_contacts() and the
 * personal data erasure: every contact_id and user_contact column of the
 * install schema (the stock dump and every migration) is on it, every entry
 * names a column the schema has, its merge and erase answers are known ones,
 * and the entries the erasure finds by contact id are exactly the tables
 * pg_privacy_erase_tables() finds by contact_ids. The merge itself needs a
 * database and is run in a sandbox instead.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// table => column names, from the CREATE TABLE statements of the stock dumps
// and the migrations, and the columns the migrations add.
function contact_holders_test_schema()
{
	static $schema = null;

	if ($schema !== null) {
		return $schema;
	}

	$schema = array();
	$sources = array(
		PG_FUNCTIONS_DIR . '/data/backups/english_default/sql.sql',
		PG_FUNCTIONS_DIR . '/data/backups/turkish_default/sql.sql',
	);
	$sources = array_merge($sources, glob(PG_FUNCTIONS_DIR . '/includes/migrations/*.php'));

	foreach ($sources as $file) {
		$source = (string) file_get_contents($file);

		preg_match_all('/CREATE TABLE (?:IF NOT EXISTS )?`?([a-z0-9_]+)`? \((.*?)\n\s*\)\s*(?:ENGINE|;|")/s', $source, $tables, PREG_SET_ORDER);

		foreach ($tables as $table) {
			preg_match_all('/^\s*`?([a-z0-9_]+)`?\s+(?:int|tinyint|smallint|mediumint|bigint|varchar|char|text|mediumtext|longtext|enum|decimal|date|datetime|timestamp|float|double|blob|longblob|mediumblob|set|json|binary|varbinary)\b/mi', $table[2], $columns);

			foreach ($columns[1] as $column) {
				$schema[$table[1]][$column] = true;
			}
		}

		preg_match_all("/install_add_column\(\s*'([a-z0-9_]+)'\s*,\s*'([a-z0-9_]+)'/", $source, $added, PREG_SET_ORDER);

		foreach ($added as $column) {
			$schema[$column[1]][$column[2]] = true;
		}
	}

	return $schema;
}

function test_contact_holders_cover_every_contact_column_of_the_schema()
{
	$schema = contact_holders_test_schema();

	pg_assert_true(isset($schema['contacts']['email_address']), 'the schema reader finds the contacts table');
	pg_assert_true(isset($schema['consent_log']['contact_id']), 'the schema reader finds a table a migration creates');

	$in_schema = array();

	foreach ($schema as $table => $columns) {
		foreach (array('contact_id', 'user_contact') as $column) {
			if (isset($columns[$column])) {
				$in_schema[] = $table . '.' . $column;
			}
		}
	}

	$listed = array();

	foreach (pg_contact_holders() as $key => $holder) {
		if (!isset($holder['type'])) {
			$listed[] = (isset($holder['table']) ? $holder['table'] : $key) . '.' . $holder['column'];
		}
	}

	sort($in_schema);
	sort($listed);

	pg_assert_same($in_schema, $listed, 'every contact_id / user_contact column is a holder, and every untyped holder is such a column');
}

function test_contact_holders_name_columns_the_schema_has()
{
	$schema = contact_holders_test_schema();

	foreach (pg_contact_holders() as $key => $holder) {
		$table = isset($holder['table']) ? $holder['table'] : $key;

		pg_assert_true(isset($schema[$table][$holder['column']]), $key . ': ' . $table . '.' . $holder['column'] . ' exists');

		foreach (isset($holder['type']) ? $holder['type'] : array() as $column => $value) {
			pg_assert_true(isset($schema[$table][$column]), $key . ': type column ' . $column . ' exists');
			pg_assert_same('contact', $value, $key . ': the type names a contact');
		}

		pg_assert_true(in_array($holder['merge'], array('move', 'one', 'groups', 'keep', 'survivor'), true), $key . ': known merge answer');
		pg_assert_true(in_array($holder['erase'], array('find', 'scope', 'none'), true), $key . ': known erase answer');
		pg_assert_same(1, preg_match('/^[a-z0-9_]+$/', $table . $holder['column']), $key . ': names are safe to put in SQL');
	}
}

function test_contact_holders_merge_answers_as_decided()
{
	$holders = pg_contact_holders();

	pg_assert_same('one', $holders['erp_accounts']['merge'], 'a group with more than one ERP account is not merged');
	pg_assert_same('keep', $holders['consent_log']['merge'], 'the consent log stays as proof');
	pg_assert_same('survivor', $holders['user']['merge'], 'a contact a user holds is the one that stays');

	foreach (array('orders', 'forms', 'email_recipients', 'ws_channels', 'ws_channels_customer') as $key) {
		pg_assert_same('move', $holders[$key]['merge'], $key . ' is re-pointed');
	}
}

function test_contact_holders_match_what_the_erasure_finds_by_contact_id()
{
	$by_contact = array();

	foreach (pg_privacy_erase_tables() as $key => $spec) {
		$table = isset($spec['table']) ? $spec['table'] : $key;

		foreach ($spec['find'] as $column => $scope_key) {
			// the contacts themselves, not a holder
			if (($scope_key === 'contact_ids') && ($table !== 'contacts')) {
				$by_contact[] = $table . '.' . $column;
			}
		}
	}

	$find = array();

	foreach (pg_contact_holders() as $key => $holder) {
		if ($holder['erase'] === 'find') {
			$find[] = (isset($holder['table']) ? $holder['table'] : $key) . '.' . $holder['column'];
		}
	}

	sort($by_contact);
	sort($find);

	pg_assert_same($by_contact, $find, 'the holders marked find are the tables the erasure finds by contact_ids');
}

function test_contact_holders_scope_entries_are_reached_by_the_erasure()
{
	$tables = array_keys(pg_privacy_erase_tables());

	foreach (pg_contact_holders() as $key => $holder) {
		if ($holder['erase'] === 'scope') {
			pg_assert_true(in_array($key, $tables, true), $key . ' is in the erasure, found through its scope');
		}
	}
}

function test_contact_holders_one_row_per_contact_keeps_its_mirror()
{
	$schema = contact_holders_test_schema();

	foreach (pg_contact_holders() as $key => $holder) {
		if ($holder['merge'] === 'one') {
			pg_assert_true(isset($holder['mirror']), $key . ' names the contacts column that points back');
		}

		if (isset($holder['mirror'])) {
			pg_assert_true(isset($schema['contacts'][$holder['mirror']]), $key . ': contacts.' . $holder['mirror'] . ' exists');
		}
	}
}
