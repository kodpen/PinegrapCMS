<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the volunteer shifts' store: the five tables of the migration
 * step with their columns and keys, their place in get_tables(), the
 * personal data erasure, export, inventory and retention rule, and who
 * opens a volunteer document's file in get_file.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/source_reader.php');

function volunteer_store_test_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

// The step's function, read from the migration file (it is loaded only by
// the upgrade).
function volunteer_store_test_step()
{
	$source = volunteer_store_test_source('includes/migrations/2026.11.0.php');
	$start = strpos($source, 'function upgrade_2026_11_0_volunteer_shifts() {');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\n}\n", $start);

	return ($end === false) ? substr($source, $start) : substr($source, $start, $end - $start + 3);
}

// The CREATE TABLE statement of one table in the step, column and key lines
// as written, whitespace collapsed.
function volunteer_store_test_table($table)
{
	if (!preg_match("/install_create_table\\('" . $table . "', \"CREATE TABLE " . $table . " \\((.*?)\\n\\t\\) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci\"\\);/s", volunteer_store_test_step(), $match)) {
		return array();
	}

	$lines = array();

	foreach (explode("\n", $match[1]) as $line) {
		$line = rtrim(trim(preg_replace('/\s+/', ' ', $line)), ',');

		if ($line !== '') {
			$lines[] = $line;
		}
	}

	return $lines;
}

// The columns are the architect's (decisions-made-schema-46, approved with
// changes in schema-47): a change here is a schema change and is asked first.
function test_volunteer_store_tables_are_pinned()
{
	pg_assert_contains('upgrade_2026_11_0_volunteer_shifts();', volunteer_store_test_source('includes/migrations/2026.11.0.php'), 'the step is called');

	pg_assert_same(array(
		'id INT UNSIGNED NOT NULL AUTO_INCREMENT',
		"name VARCHAR(150) NOT NULL DEFAULT ''",
		"state VARCHAR(16) NOT NULL DEFAULT 'open'",
		'page_id INT UNSIGNED NOT NULL DEFAULT 0',
		'form_fields TEXT NULL',
		'roles TEXT NULL',
		'coordinators TEXT NULL',
		'cutoff_hours SMALLINT UNSIGNED NOT NULL DEFAULT 24',
		'min_age TINYINT UNSIGNED NOT NULL DEFAULT 0',
		'swap_approval TINYINT UNSIGNED NOT NULL DEFAULT 1',
		'call_days TINYINT UNSIGNED NOT NULL DEFAULT 3',
		"call_channels VARCHAR(30) NOT NULL DEFAULT 'email'",
		'texts TEXT NULL',
		'created_by INT UNSIGNED NOT NULL DEFAULT 0',
		'created_at INT UNSIGNED NOT NULL DEFAULT 0',
		'updated_at INT UNSIGNED NOT NULL DEFAULT 0',
		'PRIMARY KEY (id)',
		'KEY idx_page (page_id)',
	), volunteer_store_test_table('volunteer_programmes'), 'volunteer_programmes');

	pg_assert_same(array(
		'id INT UNSIGNED NOT NULL AUTO_INCREMENT',
		'programme_id INT UNSIGNED NOT NULL DEFAULT 0',
		'contact_id INT UNSIGNED NOT NULL DEFAULT 0',
		'form_id INT UNSIGNED NOT NULL DEFAULT 0',
		"state VARCHAR(16) NOT NULL DEFAULT 'applied'",
		'skills TEXT NULL',
		'hours TEXT NULL',
		'calls TINYINT UNSIGNED NOT NULL DEFAULT 1',
		"call_channel VARCHAR(10) NOT NULL DEFAULT 'email'",
		'link_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL',
		'link_issued_at INT UNSIGNED NOT NULL DEFAULT 0',
		'thanked_hours SMALLINT UNSIGNED NOT NULL DEFAULT 0',
		'applied_at INT UNSIGNED NOT NULL DEFAULT 0',
		'approved_at INT UNSIGNED NOT NULL DEFAULT 0',
		'approved_by INT UNSIGNED NOT NULL DEFAULT 0',
		'left_at INT UNSIGNED NOT NULL DEFAULT 0',
		'updated_at INT UNSIGNED NOT NULL DEFAULT 0',
		'PRIMARY KEY (id)',
		'UNIQUE KEY uniq_link_hash (link_hash)',
		'KEY idx_programme_state (programme_id, state)',
		'KEY idx_contact (contact_id)',
		'KEY idx_form (form_id)',
	), volunteer_store_test_table('volunteers'), 'volunteers');

	pg_assert_same(array(
		'id INT UNSIGNED NOT NULL AUTO_INCREMENT',
		'volunteer_id INT UNSIGNED NOT NULL DEFAULT 0',
		"kind VARCHAR(60) NOT NULL DEFAULT ''",
		"number VARCHAR(100) NOT NULL DEFAULT ''",
		'valid_until DATE NULL DEFAULT NULL',
		'file_id INT UNSIGNED NOT NULL DEFAULT 0',
		'seen_by INT UNSIGNED NOT NULL DEFAULT 0',
		'seen_at INT UNSIGNED NOT NULL DEFAULT 0',
		'notice_sent TINYINT UNSIGNED NOT NULL DEFAULT 0',
		'added_by INT UNSIGNED NOT NULL DEFAULT 0',
		'created_at INT UNSIGNED NOT NULL DEFAULT 0',
		'updated_at INT UNSIGNED NOT NULL DEFAULT 0',
		'PRIMARY KEY (id)',
		'KEY idx_volunteer (volunteer_id)',
		'KEY idx_valid_until (valid_until)',
		'KEY idx_file (file_id)',
	), volunteer_store_test_table('volunteer_documents'), 'volunteer_documents');

	pg_assert_same(array(
		'id INT UNSIGNED NOT NULL AUTO_INCREMENT',
		'programme_id INT UNSIGNED NOT NULL DEFAULT 0',
		"place VARCHAR(100) NOT NULL DEFAULT ''",
		'start_time DATETIME NULL DEFAULT NULL',
		'end_time DATETIME NULL DEFAULT NULL',
		'roles TEXT NULL',
		"note VARCHAR(500) NOT NULL DEFAULT ''",
		'series_id INT UNSIGNED NOT NULL DEFAULT 0',
		'called TEXT NULL',
		'reminded_at INT UNSIGNED NOT NULL DEFAULT 0',
		'created_by INT UNSIGNED NOT NULL DEFAULT 0',
		'created_at INT UNSIGNED NOT NULL DEFAULT 0',
		'updated_at INT UNSIGNED NOT NULL DEFAULT 0',
		'PRIMARY KEY (id)',
		'KEY idx_programme_start (programme_id, start_time)',
		'KEY idx_start (start_time)',
		'KEY idx_series (series_id)',
	), volunteer_store_test_table('volunteer_shifts'), 'volunteer_shifts');

	pg_assert_same(array(
		'id INT UNSIGNED NOT NULL AUTO_INCREMENT',
		'programme_id INT UNSIGNED NOT NULL DEFAULT 0',
		'shift_id INT UNSIGNED NULL DEFAULT NULL',
		'volunteer_id INT UNSIGNED NOT NULL DEFAULT 0',
		"role VARCHAR(40) NOT NULL DEFAULT ''",
		"state VARCHAR(16) NOT NULL DEFAULT 'signed'",
		"outcome VARCHAR(16) NOT NULL DEFAULT ''",
		'offered_at INT UNSIGNED NOT NULL DEFAULT 0',
		'swap_from INT UNSIGNED NOT NULL DEFAULT 0',
		'check_in DATETIME NULL DEFAULT NULL',
		'check_out DATETIME NULL DEFAULT NULL',
		'checked_by INT UNSIGNED NOT NULL DEFAULT 0',
		'worked_on DATE NULL DEFAULT NULL',
		'minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0',
		"note VARCHAR(255) NOT NULL DEFAULT ''",
		'approved_at INT UNSIGNED NOT NULL DEFAULT 0',
		'approved_by INT UNSIGNED NOT NULL DEFAULT 0',
		'cancelled_at INT UNSIGNED NOT NULL DEFAULT 0',
		'created_at INT UNSIGNED NOT NULL DEFAULT 0',
		'updated_at INT UNSIGNED NOT NULL DEFAULT 0',
		'PRIMARY KEY (id)',
		'UNIQUE KEY uniq_shift_volunteer (shift_id, volunteer_id)',
		'KEY idx_volunteer (volunteer_id)',
		'KEY idx_programme_approved (programme_id, approved_at)',
	), volunteer_store_test_table('volunteer_signups'), 'volunteer_signups');

	pg_assert_contains("install_note('", volunteer_store_test_step(), 'the step ends with a note');
	pg_assert_true(strpos(volunteer_store_test_step(), 'db("') === false, 'no hand-written statement');
}

// No count, stamp or id in the five tables can go below zero: every integer
// column is UNSIGNED (schema-47 named minutes and thanked_hours).
function test_volunteer_store_integers_are_unsigned()
{
	foreach (array('volunteer_programmes', 'volunteers', 'volunteer_documents', 'volunteer_shifts', 'volunteer_signups') as $table) {
		$lines = volunteer_store_test_table($table);
		pg_assert_true(!empty($lines), $table . ' is read');

		foreach ($lines as $line) {
			if (preg_match('/^\w+ (TINYINT|SMALLINT|MEDIUMINT|INT|BIGINT)\b/', $line)) {
				pg_assert_contains(' UNSIGNED ', $line, $table . ': ' . $line);
			}
		}
	}
}

// A reinstall drops and makes the tables only when get_tables() lists them.
function test_volunteer_store_tables_are_in_get_tables()
{
	$install = volunteer_store_test_source('install/index.php');

	foreach (array('volunteer_programmes', 'volunteers', 'volunteer_documents', 'volunteer_shifts', 'volunteer_signups') as $table) {
		pg_assert_contains("\t\t'" . $table . "',\n", $install, $table . ' in get_tables()');
	}
}

// A volunteer is erased whole: profile, documents (with their files) and
// every shift and hours row; the profile follows its contact in a merge.
function test_volunteer_store_erasure()
{
	$tables = pg_privacy_erase_tables();

	foreach (array('volunteer_signups', 'volunteer_documents', 'volunteers') as $table) {
		pg_assert_same('delete', $tables[$table]['action'], $table . ' deleted');
	}

	pg_assert_same(array('id' => 'volunteer_ids', 'contact_id' => 'contact_ids'), $tables['volunteers']['find'], 'a profile by id or contact');
	pg_assert_same(array('volunteer_id' => 'volunteer_ids'), $tables['volunteer_documents']['find'], 'documents by the profile');
	pg_assert_same(array('volunteer_id' => 'volunteer_ids'), $tables['volunteer_signups']['find'], 'sign-ups by the profile');

	$scope = pg_test_function_source('pg_privacy_erase_scope');
	pg_assert_contains("SELECT id FROM volunteers WHERE", $scope, 'the profiles of the contacts');
	pg_assert_contains("SELECT file_id FROM volunteer_documents WHERE (file_id > 0)", $scope, 'the documents\' files go with the files entry');
	pg_assert_true(strpos($scope, 'volunteer_documents') > strpos($scope, "\$scope['volunteer_ids'] ="), 'the profiles are found before their files');

	$holders = pg_contact_holders();
	pg_assert_same(array('column' => 'contact_id', 'merge' => 'move', 'erase' => 'find'), $holders['volunteers'], 'contact holder');
}

// The export gives a person's volunteering in three sections, never a
// document's file, the link's hash or who saw a document.
function test_volunteer_store_export()
{
	$sections = pg_privacy_export_sections();

	foreach (array('volunteers', 'volunteer_documents', 'volunteer_hours') as $key) {
		pg_assert_true(isset($sections[$key]), $key . ' section');
	}

	$collect = pg_test_function_source('pg_privacy_export_collect');
	$start = strpos($collect, "\$sections['volunteers']");
	$block = ($start === false) ? '' : substr($collect, $start, 2000);

	pg_assert_true($block !== '', 'the volunteering block');

	foreach (array('link_hash', 'file_id', 'seen_by', 'coordinators') as $column) {
		pg_assert_true(strpos($block, $column) === false, $column . ' left out');
	}
}

// Each table has its inventory line, and the rule for volunteers who left
// reaches only their own rows.
function test_volunteer_store_inventory_and_rule()
{
	$inventory = pg_privacy_inventory();

	foreach (array('volunteers', 'volunteer_documents', 'volunteer_signups') as $table) {
		pg_assert_same(array(), pg_privacy_inventory_line_problems($inventory[$table]), $table . ' line');
		pg_assert_same('left_volunteers', $inventory[$table]['retention'], $table . ' retention');
	}

	$catalogue = pg_privacy_rules_catalogue();
	pg_assert_same('volunteers', $catalogue['volunteers']['table'], 'rule table');
	pg_assert_same(3, $catalogue['volunteers']['default'], '3 years by default');
	pg_assert_same(array('files', 'volunteer_signups', 'volunteer_documents', 'volunteers'), pg_privacy_rule_entries('volunteers'), 'the rule\'s entries');
	pg_assert_same(false, pg_privacy_rules_normalise(array())['volunteers']['on'], 'off by default');

	$now = mktime(12, 0, 0, 11, 20, 2026);
	$rule = pg_privacy_rules_normalise(array())['volunteers'];
	pg_assert_same(
		"(state = 'left') AND (left_at > 0) AND (left_at < '" . mktime(12, 0, 0, 11, 20, 2023) . "')",
		pg_privacy_rule_candidate_where('volunteers', $rule, $now, array()),
		'left more than 3 years ago'
	);

	$active = pg_test_function_source('_pg_privacy_rule_active');
	pg_assert_contains("(state <> 'left') OR (left_at >= ", $active, 'a passive contact still volunteering is kept');
}

// Who opens a volunteer document's file.
function test_volunteer_document_access_verdict()
{
	$holders = array(array('volunteer_id' => '5', 'contact_id' => '12', 'coordinators' => '[4, "9"]'));

	pg_assert_same(true, pg_volunteer_document_access_verdict($holders, array('user_id' => 2, 'role' => 2)), 'a manager');
	pg_assert_same(true, pg_volunteer_document_access_verdict($holders, array('user_id' => 1, 'role' => 0)), 'an administrator');
	pg_assert_same(true, pg_volunteer_document_access_verdict($holders, array('user_id' => 9, 'role' => 3)), 'a coordinator of the programme');
	pg_assert_same(false, pg_volunteer_document_access_verdict($holders, array('user_id' => 8, 'role' => 3)), 'a user who coordinates elsewhere');
	pg_assert_same(true, pg_volunteer_document_access_verdict($holders, array('user_id' => 30, 'role' => 3, 'contact_id' => 12)), 'the volunteer signed in');
	pg_assert_same(false, pg_volunteer_document_access_verdict($holders, array('user_id' => 31, 'role' => 3, 'contact_id' => 13)), 'another member');
	pg_assert_same(false, pg_volunteer_document_access_verdict($holders, array('user_id' => 0, 'role' => 0, 'contact_id' => 12)), 'nobody signed in has no role or contact');
	pg_assert_same(true, pg_volunteer_document_access_verdict($holders, array('link_volunteer_ids' => array('5'))), 'the volunteer\'s signed link');
	pg_assert_same(false, pg_volunteer_document_access_verdict($holders, array('link_volunteer_ids' => array(6))), 'another volunteer\'s link');
	pg_assert_same(false, pg_volunteer_document_access_verdict(array(array('volunteer_id' => 5, 'contact_id' => 0, 'coordinators' => null)), array('user_id' => 4, 'role' => 3, 'contact_id' => 0)), 'no coordinators, no contact');
	pg_assert_same(false, pg_volunteer_document_access_verdict(array(), array('user_id' => 4, 'role' => 3)), 'no holder');
}

// get_file.php decides a document's file before the folder's own check and
// the submitted-form attachment check, and never lets a cache keep it.
function test_volunteer_document_get_file_door()
{
	$source = volunteer_store_test_source('get_file.php');
	$door = strpos($source, 'FROM volunteer_documents d');
	$folder = strpos($source, '$access_control_type = (');

	pg_assert_true(($door !== false) && ($folder !== false) && ($door < $folder), 'the door before the folder check');
	pg_assert_contains('pg_volunteer_document_access_verdict($volunteer_holders', $source, 'the verdict');
	pg_assert_contains('|| $volunteer_document) ? \'public\' : get_access_control_type(', $source, 'the folder check skipped once let through');
	pg_assert_contains("(\$file['attachment'] == 1) && (\$product_form_upload === false) && (\$volunteer_document === false)", $source, 'the attachment check skipped');
	pg_assert_contains('($erp_document || $chat_attachment || $volunteer_document) ? \'private, no-store\'', $source, 'no-store');
}
