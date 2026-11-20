<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the satisfaction surveys' store: the two tables of the migration
 * step with their columns and keys, their place in get_tables(), and the
 * personal data erasure, export and inventory of the answers.
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

function feedback_store_test_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

// The step's function, read from the migration file (it is loaded only by
// the upgrade).
function feedback_store_test_step()
{
	$source = feedback_store_test_source('includes/migrations/2026.11.0.php');
	$start = strpos($source, 'function upgrade_2026_11_0_feedback_surveys() {');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\n}\n", $start);

	return ($end === false) ? substr($source, $start) : substr($source, $start, $end - $start + 3);
}

// The CREATE TABLE statement of one table in the step, column lines and key
// lines as written, whitespace collapsed.
function feedback_store_test_table($table)
{
	if (!preg_match("/install_create_table\\('" . $table . "', \"CREATE TABLE " . $table . " \\((.*?)\\n\\t\\) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci\"\\);/s", feedback_store_test_step(), $match)) {
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

// The columns are the ones the architect set (decisions-made-schema-45): a
// change here is a schema change and is asked first.
function test_feedback_store_tables_are_pinned()
{
	pg_assert_contains('upgrade_2026_11_0_feedback_surveys();', feedback_store_test_source('includes/migrations/2026.11.0.php'), 'the step is called');

	pg_assert_same(array(
		'id INT UNSIGNED NOT NULL AUTO_INCREMENT',
		"name VARCHAR(150) NOT NULL DEFAULT ''",
		'kind TINYINT UNSIGNED NOT NULL DEFAULT 1',
		"touchpoint VARCHAR(30) NOT NULL DEFAULT ''",
		'filter TEXT NULL',
		'delay_minutes INT UNSIGNED NOT NULL DEFAULT 0',
		"channel VARCHAR(10) NOT NULL DEFAULT ''",
		'purpose TINYINT UNSIGNED NOT NULL DEFAULT 0',
		'fallback_user_id INT UNSIGNED NOT NULL DEFAULT 0',
		'escalation_user_id INT UNSIGNED NOT NULL DEFAULT 0',
		'ws_channel_id INT UNSIGNED NOT NULL DEFAULT 0',
		'review_invite TINYINT UNSIGNED NOT NULL DEFAULT 0',
		'texts TEXT NULL',
		'enabled TINYINT UNSIGNED NOT NULL DEFAULT 0',
		'archived_at INT UNSIGNED NOT NULL DEFAULT 0',
		'last_periodic_at INT UNSIGNED NOT NULL DEFAULT 0',
		'created_by INT UNSIGNED NOT NULL DEFAULT 0',
		'created_at INT UNSIGNED NOT NULL DEFAULT 0',
		'updated_at INT UNSIGNED NOT NULL DEFAULT 0',
		'PRIMARY KEY (id)',
		'KEY idx_enabled_touchpoint (enabled, touchpoint)',
	), feedback_store_test_table('feedback_surveys'), 'feedback_surveys');

	pg_assert_same(array(
		'id INT UNSIGNED NOT NULL AUTO_INCREMENT',
		'survey_id INT UNSIGNED NOT NULL DEFAULT 0',
		'contact_id INT UNSIGNED NOT NULL DEFAULT 0',
		"person_hash CHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT ''",
		"recipient VARCHAR(190) NOT NULL DEFAULT ''",
		"record_type VARCHAR(20) NOT NULL DEFAULT ''",
		'record_id INT UNSIGNED NOT NULL DEFAULT 0',
		'subject_id INT UNSIGNED NOT NULL DEFAULT 0',
		'staff_user_id INT UNSIGNED NOT NULL DEFAULT 0',
		"channel VARCHAR(10) NOT NULL DEFAULT ''",
		'status TINYINT UNSIGNED NOT NULL DEFAULT 0',
		'due_at INT UNSIGNED NOT NULL DEFAULT 0',
		'sent_at INT UNSIGNED NOT NULL DEFAULT 0',
		'reminded_at INT UNSIGNED NOT NULL DEFAULT 0',
		'answered_at INT UNSIGNED NOT NULL DEFAULT 0',
		'token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL',
		'score TINYINT UNSIGNED NOT NULL DEFAULT 0',
		'band TINYINT UNSIGNED NOT NULL DEFAULT 0',
		'comment TEXT NULL',
		'answers TEXT NULL',
		'stopped TINYINT UNSIGNED NOT NULL DEFAULT 0',
		'owner_user_id INT UNSIGNED NOT NULL DEFAULT 0',
		'task_id INT UNSIGNED NOT NULL DEFAULT 0',
		'escalated_at INT UNSIGNED NOT NULL DEFAULT 0',
		'outcome TINYINT UNSIGNED NOT NULL DEFAULT 0',
		'created_at INT UNSIGNED NOT NULL DEFAULT 0',
		'PRIMARY KEY (id)',
		'UNIQUE KEY uniq_token_hash (token_hash)',
		'KEY idx_person_created (person_hash, created_at)',
		'KEY idx_status_due (status, due_at)',
		'KEY idx_survey_answered (survey_id, answered_at)',
		'KEY idx_record (record_type, record_id)',
		'KEY idx_contact (contact_id)',
		'KEY idx_staff_answered (staff_user_id, answered_at)',
	), feedback_store_test_table('feedback_responses'), 'feedback_responses');

	$index = feedback_store_test_source('install/index.php');

	foreach (array('feedback_responses', 'feedback_surveys') as $table) {
		pg_assert_contains("\t\t'" . $table . "',\n", $index, $table . ' in get_tables()');
	}

	pg_assert_false(strpos(feedback_store_test_step(), 'install_add_column(') !== false, 'no column on an existing table');
	pg_assert_contains('install_note(', feedback_store_test_step(), 'a note for the operator');
}

// An erasure keeps the score, band and dates and takes the person and what
// they wrote; a send still queued is cancelled first.
function test_feedback_store_erase()
{
	$tables = pg_privacy_erase_tables();

	pg_assert_true(isset($tables['feedback_responses']), 'an entry');
	pg_assert_same(array('contact_id' => 'contact_ids', 'person_hash' => 'feedback_hashes'), $tables['feedback_responses']['find'], 'by contact or by the hash of an address or number');
	pg_assert_same('anonymise', $tables['feedback_responses']['action'], 'the rows stay');
	pg_assert_same(
		array('contact_id' => 0, 'person_hash' => '', 'recipient' => '', 'comment' => null, 'answers' => null),
		$tables['feedback_responses']['fields'],
		'the person and what they wrote go'
	);
	pg_assert_false(isset($tables['feedback_surveys']), 'a survey names nobody');

	$order = array_keys($tables);
	pg_assert_true(array_search('feedback_responses', $order, true) < array_search('contacts', $order, true), 'before the contacts that find them');

	$labels = pg_privacy_erase_labels();
	pg_assert_true(isset($labels['feedback_responses']) && ($labels['feedback_responses'] !== ''), 'a name on the screen');

	$hook = pg_test_function_source('_pg_privacy_erase_apply_feedback_responses');
	pg_assert_contains('SET status = 3', $hook, 'a queued send is cancelled');
	pg_assert_contains('(status = 0)', $hook, 'only a queued one');
	pg_assert_true(strpos($hook, 'SET status = 3') < strpos($hook, "_pg_privacy_erase_write('feedback_responses'"), 'cancelled while the hash still finds the row');

	$scope = pg_test_function_source('pg_privacy_erase_scope');
	pg_assert_contains("\$scope['feedback_hashes'] = pg_feedback_person_hashes(array_merge(\$scope['emails'], \$scope['phones']));", $scope, 'hashes of the addresses and numbers');
	pg_assert_true(strpos($scope, "\$scope['phones'] = ") < strpos($scope, "\$scope['feedback_hashes'] = "), 'after the numbers are known');

	pg_assert_true(isset(pg_privacy_inventory()['feedback_responses']), 'in the inventory');
}

// NULL is written as NULL, so a TEXT column the erasure clears holds no
// empty string.
function test_feedback_store_erase_set_null()
{
	if (!class_exists('db')) {
		eval('class db { public static $con; }');
	}

	pg_assert_same("`comment` = NULL, `recipient` = '', `contact_id` = 0", pg_privacy_erase_set(array('comment' => null, 'recipient' => '', 'contact_id' => 0), 'x', ''), 'null, text and number');
}

// The export lists the person's answers with the survey's name; never the
// link's hash, the person hash or the staff behind the follow-up.
function test_feedback_store_export()
{
	$sections = pg_privacy_export_sections();
	pg_assert_true(isset($sections['feedback']), 'a section');

	$keys = array_keys($sections);
	pg_assert_same(array_search('bookings', $keys, true) + 1, array_search('feedback', $keys, true), 'after the bookings');

	$source = pg_test_function_source('pg_privacy_export_collect');
	pg_assert_same(1, preg_match_all('/SELECT ([^"]*?) FROM feedback_responses AS r/', $source, $read), 'one read of the answers');
	pg_assert_same(
		'r.id, s.name AS survey, s.kind, r.recipient, r.record_type, r.record_id, r.channel, r.status, r.sent_at, r.answered_at, r.score, r.band, r.comment, r.answers, r.stopped, r.outcome, r.created_at',
		isset($read[1][0]) ? $read[1][0] : '',
		'columns'
	);
	pg_assert_contains("pg_feedback_person_hashes(array_merge(\$emails, \$feedback_phones))", $source, 'found by the hashes too');
	pg_assert_contains('pg_feedback_export_row(', $source, 'each row made readable');
}

// One hash per address or number, folded first; nothing for empty input.
function test_feedback_person_hashes()
{
	pg_assert_same(
		array(sha1('ali@example.com'), sha1('905321234567')),
		pg_feedback_person_hashes(array(' Ali@Example.com', 'ali@example.com', '+90 532 123 45 67', '', null, '-')),
		'folded, once each, empty left out'
	);
	pg_assert_same(array(), pg_feedback_person_hashes(array()), 'none');
}

// A score is read only with answered_at set; the extra answers are JSON.
function test_feedback_export_row()
{
	pg_assert_same(
		array('id' => 4, 'score' => 0, 'answered_at' => 1700000000, 'answers' => array('q1' => 'Yes')),
		pg_feedback_export_row(array('id' => '4', 'score' => '0', 'answered_at' => '1700000000', 'answers' => '{"q1":"Yes"}')),
		'an NPS of 0 answered'
	);
	pg_assert_same(
		array('id' => 5, 'score' => null, 'answered_at' => 0, 'answers' => null),
		pg_feedback_export_row(array('id' => 5, 'score' => 0, 'answered_at' => 0, 'answers' => null)),
		'not answered'
	);
	pg_assert_same(
		array('score' => 9, 'answered_at' => 10, 'answers' => null),
		pg_feedback_export_row(array('score' => 9, 'answered_at' => 10, 'answers' => 'not json')),
		'unreadable answers'
	);
}
