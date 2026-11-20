<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the application review desk's store: the four tables of the
 * migration step with their columns and keys, their place in get_tables(),
 * the personal data erasure and export of the tables, and the audit trail's
 * record types.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function application_review_store_test_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

// The step's function, read from the migration file (it is loaded only by
// the upgrade).
function application_review_store_test_step()
{
	$source = application_review_store_test_source('includes/migrations/2026.11.0.php');
	$start = strpos($source, 'function upgrade_2026_11_0_application_review() {');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\n}\n", $start);

	return ($end === false) ? substr($source, $start) : substr($source, $start, $end - $start + 3);
}

// The CREATE TABLE statement of one table in the step, column lines and key
// lines as written, whitespace collapsed.
function application_review_store_test_table($table)
{
	$step = application_review_store_test_step();

	if (!preg_match("/install_create_table\\('" . $table . "', \"CREATE TABLE " . $table . " \\((.*?)\\n\\t\\) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci\"\\);/s", $step, $match)) {
		return array();
	}

	$lines = array();

	foreach (explode("\n", $match[1]) as $line) {
		$line = trim(preg_replace('/\s+/', ' ', $line));
		$line = rtrim($line, ',');

		if ($line !== '') {
			$lines[] = $line;
		}
	}

	return $lines;
}

// The columns are the ones the architect set (decisions-made-schema-44):
// a change here is a schema change and is asked first.
function test_application_review_store_tables_are_pinned()
{
	$migration = application_review_store_test_source('includes/migrations/2026.11.0.php');
	pg_assert_contains('upgrade_2026_11_0_application_review();', $migration, 'the step is called');

	pg_assert_same(array(
		'id INT UNSIGNED NOT NULL AUTO_INCREMENT',
		'page_id INT UNSIGNED NOT NULL DEFAULT 0',
		"name VARCHAR(150) NOT NULL DEFAULT ''",
		'status TINYINT UNSIGNED NOT NULL DEFAULT 0',
		'opens_at INT UNSIGNED NOT NULL DEFAULT 0',
		'closes_at INT UNSIGNED NOT NULL DEFAULT 0',
		'category_field_id INT UNSIGNED NOT NULL DEFAULT 0',
		'reviewers_per_entry TINYINT UNSIGNED NOT NULL DEFAULT 2',
		'round TINYINT UNSIGNED NOT NULL DEFAULT 1',
		'blind TINYINT UNSIGNED NOT NULL DEFAULT 0',
		'waitlist_auto TINYINT UNSIGNED NOT NULL DEFAULT 0',
		'rounds TEXT NULL',
		'eligibility TEXT NULL',
		'blind_fields TEXT NULL',
		'texts TEXT NULL',
		'created_by INT UNSIGNED NOT NULL DEFAULT 0',
		'created_at INT UNSIGNED NOT NULL DEFAULT 0',
		'updated_at INT UNSIGNED NOT NULL DEFAULT 0',
		'PRIMARY KEY (id)',
		'KEY idx_page_status (page_id, status)',
	), application_review_store_test_table('application_programs'), 'application_programs');

	pg_assert_same(array(
		'id INT UNSIGNED NOT NULL AUTO_INCREMENT',
		'program_id INT UNSIGNED NOT NULL DEFAULT 0',
		'user_id INT UNSIGNED NOT NULL DEFAULT 0',
		"name VARCHAR(150) NOT NULL DEFAULT ''",
		"email VARCHAR(190) NOT NULL DEFAULT ''",
		'token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL',
		'categories TEXT NULL',
		'revoked_at INT UNSIGNED NOT NULL DEFAULT 0',
		'last_seen_at INT UNSIGNED NOT NULL DEFAULT 0',
		'created_at INT UNSIGNED NOT NULL DEFAULT 0',
		'PRIMARY KEY (id)',
		'UNIQUE KEY uniq_program_person (program_id, user_id, email)',
		'UNIQUE KEY uniq_token_hash (token_hash)',
		'KEY idx_user (user_id)',
	), application_review_store_test_table('application_reviewers'), 'application_reviewers');

	pg_assert_same(array(
		'id INT UNSIGNED NOT NULL AUTO_INCREMENT',
		'program_id INT UNSIGNED NOT NULL DEFAULT 0',
		'form_id INT UNSIGNED NOT NULL DEFAULT 0',
		"category VARCHAR(100) NOT NULL DEFAULT ''",
		'round TINYINT UNSIGNED NOT NULL DEFAULT 1',
		'eligible TINYINT UNSIGNED NOT NULL DEFAULT 0',
		'eligibility_note TEXT NULL',
		'score_raw SMALLINT UNSIGNED NOT NULL DEFAULT 0',
		'score_adjusted SMALLINT UNSIGNED NOT NULL DEFAULT 0',
		'spread SMALLINT UNSIGNED NOT NULL DEFAULT 0',
		'consensus TINYINT UNSIGNED NOT NULL DEFAULT 0',
		'shortlisted TINYINT UNSIGNED NOT NULL DEFAULT 0',
		'decision TINYINT UNSIGNED NOT NULL DEFAULT 0',
		'decided_at INT UNSIGNED NOT NULL DEFAULT 0',
		'decided_by INT UNSIGNED NOT NULL DEFAULT 0',
		'mailed_at INT UNSIGNED NOT NULL DEFAULT 0',
		'fix_token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL',
		'fix_fields TEXT NULL',
		'created_at INT UNSIGNED NOT NULL DEFAULT 0',
		'updated_at INT UNSIGNED NOT NULL DEFAULT 0',
		'PRIMARY KEY (id)',
		'UNIQUE KEY uniq_form (form_id)',
		'UNIQUE KEY uniq_fix_token_hash (fix_token_hash)',
		'KEY idx_program_round_category (program_id, round, category)',
		'KEY idx_program_decision (program_id, decision)',
	), application_review_store_test_table('application_entries'), 'application_entries');

	pg_assert_same(array(
		'id INT UNSIGNED NOT NULL AUTO_INCREMENT',
		'program_id INT UNSIGNED NOT NULL DEFAULT 0',
		'entry_id INT UNSIGNED NOT NULL DEFAULT 0',
		'reviewer_id INT UNSIGNED NOT NULL DEFAULT 0',
		'round TINYINT UNSIGNED NOT NULL DEFAULT 1',
		'status TINYINT UNSIGNED NOT NULL DEFAULT 0',
		'scores TEXT NULL',
		'total SMALLINT UNSIGNED NOT NULL DEFAULT 0',
		"conflict_reason VARCHAR(500) NOT NULL DEFAULT ''",
		'assigned_at INT UNSIGNED NOT NULL DEFAULT 0',
		'submitted_at INT UNSIGNED NOT NULL DEFAULT 0',
		'reminded_at INT UNSIGNED NOT NULL DEFAULT 0',
		'PRIMARY KEY (id)',
		'UNIQUE KEY uniq_entry_reviewer_round (entry_id, reviewer_id, round)',
		'KEY idx_reviewer_status (reviewer_id, status)',
		'KEY idx_program_round_status (program_id, round, status)',
	), application_review_store_test_table('application_reviews'), 'application_reviews');

	$index = application_review_store_test_source('install/index.php');

	foreach (array('application_programs', 'application_reviewers', 'application_entries', 'application_reviews') as $table) {
		pg_assert_contains("'" . $table . "',", $index, $table . ' in get_tables()');
	}

	pg_assert_false(strpos(application_review_store_test_step(), "install_add_column('forms'") !== false, 'no column on forms');
}

// The erasure takes the person's entries with their reviews; a juror who is
// erased keeps the reviews, without name, e-mail or a working link.
function test_application_review_store_erase()
{
	$tables = pg_privacy_erase_tables();

	pg_assert_same(array('entry_id' => 'application_entry_ids'), $tables['application_reviews']['find'], 'reviews by the person\'s entries');
	pg_assert_same('delete', $tables['application_reviews']['action'], 'reviews deleted');
	pg_assert_same(array('form_id' => 'form_ids'), $tables['application_entries']['find'], 'entries by the person\'s submissions');
	pg_assert_same('delete', $tables['application_entries']['action'], 'entries deleted');
	pg_assert_same(array('user_id' => 'user_ids', 'email' => 'emails'), $tables['application_reviewers']['find'], 'reviewers by account or address');
	pg_assert_same('anonymise', $tables['application_reviewers']['action'], 'reviewers kept');
	pg_assert_same(array('name' => '', 'email' => '{token}'), $tables['application_reviewers']['fields'], 'name and address go');

	$order = array_keys($tables);
	pg_assert_true(array_search('application_reviews', $order, true) < array_search('application_entries', $order, true), 'reviews before their entries');
	pg_assert_true(array_search('application_entries', $order, true) < array_search('forms', $order, true), 'entries before the submissions');

	$hook = pg_test_function_source('_pg_privacy_erase_apply_application_reviewers');
	pg_assert_contains('token_hash = NULL', $hook, 'the juror link stops working');
	pg_assert_true(strpos($hook, 'token_hash = NULL') < strpos($hook, "_pg_privacy_erase_write('application_reviewers'"), 'revoked before the address that finds the row is replaced');

	$scope = pg_test_function_source('pg_privacy_erase_scope');
	pg_assert_contains("SELECT id FROM application_entries WHERE \" . _pg_privacy_export_in('form_id', \$scope['form_ids'])", $scope, 'entries of the person\'s submissions');
	pg_assert_contains("'application_entry' => 'application_entry_ids'", $scope, 'the trail\'s lines about the entries');

	pg_assert_true(array_key_exists('application_entry', pg_privacy_erase_audit_fields()), 'an entry line is cleaned');
	pg_assert_same(array('label' => ''), pg_privacy_erase_audit_row(array('entity_type' => 'application_entry', 'label' => 'Ali Veli', 'before_json' => '', 'after_json' => '{"decision":1}'), 'Deleted customer'), 'an entry line loses its label');
}

// The export gives the entry's state, decision, combined and criterion
// scores; never a reviewer's note or who the reviewer was.
function test_application_review_store_export_reads_no_note_or_reviewer()
{
	pg_assert_true(isset(pg_privacy_export_sections()['applications']), 'a section');

	$source = application_review_store_test_source('includes/fn/privacy_export.php');

	pg_assert_same(1, preg_match_all('/SELECT ([^"]*?) FROM application_entries AS e/', $source, $entries), 'one read of the entries');
	pg_assert_same(
		'e.id, e.form_id, p.name AS programme, e.category, e.round, e.eligible, e.shortlisted, e.decision, e.decided_at, e.score_raw, e.score_adjusted',
		isset($entries[1][0]) ? $entries[1][0] : '',
		'entry columns'
	);

	pg_assert_same(1, preg_match_all('/SELECT ([^"]*?) FROM application_reviews /', $source, $reviews), 'one read of the reviews');
	pg_assert_same('entry_id, round, scores, total', isset($reviews[1][0]) ? $reviews[1][0] : '', 'review columns');
	pg_assert_false(strpos($source, 'application_reviewers') !== false, 'the reviewers are never read');
}

// A review's scores leave its notes behind; a score is a whole number.
function test_application_review_export_review()
{
	pg_assert_same(
		array('round' => 1, 'total' => 7.25, 'scores' => array('impact' => 8, 'budget' => 6)),
		pg_application_export_review(array('entry_id' => 3, 'round' => '1', 'total' => '725', 'scores' => '{"impact":{"score":8,"note":"Strong"},"budget":{"score":"6","note":"Thin"}}')),
		'notes left out'
	);

	pg_assert_same(
		array('round' => 2, 'total' => 0.0, 'scores' => array()),
		pg_application_export_review(array('round' => 2, 'total' => 0, 'scores' => 'not json')),
		'unreadable scores'
	);

	pg_assert_same(
		array('d' => 5),
		pg_application_export_review(array('round' => 1, 'total' => 500, 'scores' => '{"a":{"note":"only a note"},"b":"x","c":[],"d":{"score":5}}'))['scores'],
		'a criterion without a score is left out'
	);

	pg_assert_same(7.25, pg_application_export_score('725'), 'times 100');
}

// The trail names the desk's records.
function test_application_review_store_audit_types()
{
	$types = pg_audit_view_labels('type');

	pg_assert_true(isset($types['application_program']), 'a programme');
	pg_assert_true(isset($types['application_entry']), 'an entry');
}
