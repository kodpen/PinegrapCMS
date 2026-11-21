<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the walk-in queue's store (step 11.0.266,
 * includes/fn/walk_in_queue_store.php): the three tables' shape, their place
 * in get_tables(), the keep period and its clear in the general job, and the
 * registrations with the personal data erasure, export, inventory and
 * contact merge.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function walk_in_queue_store_test_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

// The body of upgrade_2026_11_0_walk_in_queue(), CREATE TABLE by table.
function walk_in_queue_store_test_tables()
{
	$source = walk_in_queue_store_test_source('includes/migrations/2026.11.0.php');
	$start = strpos($source, 'function upgrade_2026_11_0_walk_in_queue()');
	pg_assert_true($start !== false, 'the step is there');
	$body = substr($source, (int) $start);
	$body = substr($body, 0, (int) strpos($body, "\n}\n"));

	preg_match_all('/install_create_table\(\'([a-z_]+)\', "CREATE TABLE \1 \((.*?)\n\t\) (ENGINE=[^"]+)"\);/s', $body, $matches, PREG_SET_ORDER);
	$tables = array();

	foreach ($matches as $match) {
		$tables[$match[1]] = array('body' => $match[2], 'tail' => $match[3]);
	}

	return $tables;
}

function walk_in_queue_store_test_columns($body)
{
	$columns = array();

	foreach (preg_split('/,\n/', trim($body)) as $line) {
		$line = trim($line);

		if (preg_match('/^(PRIMARY|UNIQUE|KEY)\b/', $line)) {
			continue;
		}

		$name = strtok($line, " \t");
		$columns[$name] = trim(substr($line, strlen($name)));
	}

	return $columns;
}

function test_walk_in_queue_store_migration_step_shape()
{
	$tables = walk_in_queue_store_test_tables();
	pg_assert_same(array('waiting_places', 'waiting_desks', 'waiting_tickets'), array_keys($tables), 'the three tables');

	$expected = array(
		'waiting_places' => array('id', 'name', 'address', 'status', 'services', 'hours', 'exceptions', 'settings', 'created_by', 'created_at', 'updated_at'),
		'waiting_desks' => array('id', 'place_id', 'name', 'services', 'state', 'user_id', 'opened_at', 'created_at', 'updated_at'),
		'waiting_tickets' => array('id', 'place_id', 'service', 'letter', 'day', 'number', 'state', 'source', 'priority', 'party_size', 'booking_id', 'booking_at', 'desk_id', 'staff_user_id', 'moved_back', 'notices', 'joined_at', 'on_way_at', 'called_at', 'done_at', 'name', 'phone', 'email', 'contact_id', 'token_hash', 'cleared_at'),
	);

	$keys = array(
		'waiting_places' => array('PRIMARY KEY (id)', 'KEY idx_status (status)'),
		'waiting_desks' => array('PRIMARY KEY (id)', 'KEY idx_place (place_id)', 'KEY idx_user (user_id)'),
		'waiting_tickets' => array('PRIMARY KEY (id)', 'UNIQUE KEY uniq_number (place_id, day, service, number)', 'KEY idx_place_state (place_id, state, joined_at)', 'KEY idx_place_joined (place_id, joined_at)', 'KEY idx_desk_called (desk_id, called_at)', 'KEY idx_booking (booking_id)', 'KEY idx_contact (contact_id)', 'KEY idx_phone (phone)', 'KEY idx_email (email)'),
	);

	foreach ($expected as $table => $names) {
		if (!isset($tables[$table])) {
			continue;
		}

		$columns = walk_in_queue_store_test_columns($tables[$table]['body']);
		pg_assert_same($names, array_keys($columns), $table . ' columns');
		pg_assert_same('ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci', $tables[$table]['tail'], $table . ' engine and collation');

		foreach ($columns as $name => $definition) {
			if (strpos($definition, 'TEXT') === 0) {
				pg_assert_same('TEXT NULL', $definition, $table . '.' . $name . ' TEXT is NULL');
				continue;
			}

			pg_assert_contains('NOT NULL', $definition, $table . '.' . $name . ' NOT NULL');

			if (strpos($definition, 'AUTO_INCREMENT') === false) {
				pg_assert_contains('DEFAULT', $definition, $table . '.' . $name . ' has a default');
			}
		}

		foreach ($keys[$table] as $key) {
			pg_assert_contains($key, $tables[$table]['body'], $table . ' ' . $key);
		}
	}

	// The states the pure rules compare (pg_waiting_open_desks(), Call next's
	// WHERE state = 'waiting') are the columns' defaults.
	pg_assert_contains("state          VARCHAR(10) CHARACTER SET ascii NOT NULL DEFAULT 'waiting'", $tables['waiting_tickets']['body'] ?? '', 'a new number waits');
	pg_assert_contains("state       VARCHAR(10) CHARACTER SET ascii NOT NULL DEFAULT 'closed'", $tables['waiting_desks']['body'] ?? '', 'a new desk is closed');
	pg_assert_contains("status      VARCHAR(10) CHARACTER SET ascii NOT NULL DEFAULT 'closed'", $tables['waiting_places']['body'] ?? '', 'a new place is closed');
	pg_assert_contains('CHAR(64) CHARACTER SET ascii COLLATE ascii_bin', $tables['waiting_tickets']['body'] ?? '', 'the link hash is ascii');

	$source = walk_in_queue_store_test_source('includes/migrations/2026.11.0.php');
	pg_assert_contains("upgrade_2026_11_0_walk_in_queue();           // 11.0.266", $source, 'called with its label');
	pg_assert_contains('install_note(', substr($source, (int) strpos($source, 'function upgrade_2026_11_0_walk_in_queue()')), 'the step ends with its note');

	$install = walk_in_queue_store_test_source('install/index.php');

	foreach (array_keys($expected) as $table) {
		pg_assert_contains("\t\t'" . $table . "',\n", $install, $table . ' in get_tables()');
	}
}

function test_walk_in_queue_store_keep_days()
{
	pg_assert_same(30, pg_waiting_keep_days(null), '30 days when not set');
	pg_assert_same(30, pg_waiting_keep_days('{"daily_cap":50}'), '30 days when the settings name none');
	pg_assert_same(7, pg_waiting_keep_days('{"keep_days":7}'), 'the place\'s own days, from JSON');
	pg_assert_same(90, pg_waiting_keep_days(array('keep_days' => '90')), 'the place\'s own days, from an array');
	pg_assert_same(1, pg_waiting_keep_days(array('keep_days' => 0)), 'never below a day');
	pg_assert_same(365, pg_waiting_keep_days(array('keep_days' => 5000)), 'never above a year');
	pg_assert_same(30, pg_waiting_keep_days(array('keep_days' => 'soon')), 'a word is not a period');
	pg_assert_same(30, pg_waiting_keep_days('not json'), 'unreadable settings');

	pg_assert_same(1000000 - 30 * 86400, pg_waiting_keep_cutoff(30, 1000000), 'the cutoff is the days before now');
	pg_assert_same(1000000 - 86400, pg_waiting_keep_cutoff(0, 1000000), 'a period of 0 counts as a day, so today\'s numbers are never cleared');
}

function test_walk_in_queue_store_clear_query()
{
	list($sql, $params) = pg_waiting_keep_clear_query(4, 5000, 9000, 1000);

	pg_assert_same(
		'UPDATE waiting_tickets SET name = ?, phone = ?, email = ?, contact_id = ?, priority = ?, token_hash = ?, cleared_at = ? WHERE place_id = ? AND (cleared_at = 0) AND (joined_at < ?) LIMIT 1000',
		$sql,
		'one place, numbers not cleared yet, joined before the cutoff'
	);
	pg_assert_same(array('', '', '', 0, '', '', 9000, 4, 5000), $params, 'the values bound');

	list($sql, $params) = pg_waiting_keep_clear_query(0, 5000, 9000, 1000, array(4, 6));
	pg_assert_contains('WHERE place_id NOT IN (?, ?) AND (cleared_at = 0)', $sql, 'the numbers of a deleted place');
	pg_assert_same(array('', '', '', 0, '', '', 9000, 4, 6, 5000), $params, 'the kept places bound');

	list($sql) = pg_waiting_keep_clear_query(0, 5000, 9000, 1000);
	pg_assert_contains('WHERE 1 AND (cleared_at = 0)', $sql, 'no place left: every number');
}

function test_walk_in_queue_store_clear_keeps_the_figures()
{
	$cleared = array_keys(pg_waiting_clear_fields());

	foreach (array('name', 'phone', 'email', 'contact_id', 'priority', 'token_hash') as $column) {
		pg_assert_true(in_array($column, $cleared, true), $column . ' is cleared');
	}

	foreach (array('service', 'state', 'desk_id', 'staff_user_id', 'joined_at', 'called_at', 'done_at', 'booking_id', 'party_size') as $column) {
		pg_assert_false(in_array($column, $cleared, true), $column . ' stays for the report');
	}

	// An erasure takes from a number what its keep period takes.
	$erase = pg_privacy_erase_tables();
	pg_assert_same(pg_waiting_clear_fields(), $erase['waiting_tickets']['fields'], 'the erasure and the clear empty the same fields');
	pg_assert_same('anonymise', $erase['waiting_tickets']['action'], 'the number stays, without the person');
}

function test_walk_in_queue_store_privacy_registrations()
{
	$erase = pg_privacy_erase_tables();
	pg_assert_same(array('contact_id' => 'contact_ids', 'email' => 'emails', 'phone' => 'phones'), $erase['waiting_tickets']['find'], 'found by contact, address and number');
	pg_assert_true(function_exists('_pg_privacy_erase_apply_waiting_tickets'), 'a number still waiting leaves the queue first');

	$labels = pg_privacy_erase_labels();
	pg_assert_true(isset($labels['waiting_tickets']) && ($labels['waiting_tickets'] !== ''), 'the erasure names it');

	$sections = pg_privacy_export_sections();
	pg_assert_true(isset($sections['queue']), 'the export has a section');

	$export = walk_in_queue_store_test_source('includes/fn/privacy_export.php');
	$start = (int) strpos($export, "\$sections['queue'] = ");
	$select = substr($export, $start, (int) strpos($export, ');', $start) - $start);
	pg_assert_false(strpos($select, 'token_hash') !== false, 'the export leaves the link hash out');
	pg_assert_false(strpos($select, 'staff_user_id') !== false, 'the export leaves the staff member out');

	$inventory = pg_privacy_inventory();
	pg_assert_same('queue_keep', $inventory['waiting_tickets']['retention'], 'kept for the place\'s keep period');
	pg_assert_same(array('contact_id', 'int'), $inventory['waiting_tickets']['person'], 'people counted by contact');

	$holders = pg_contact_holders();
	pg_assert_same(array('column' => 'contact_id', 'merge' => 'move', 'erase' => 'find'), $holders['waiting_tickets'], 'a merge moves the numbers, the erasure finds them');

	// No ticket table holds the places' or desks' rows of a person.
	pg_assert_false(isset($erase['waiting_places']) || isset($erase['waiting_desks']), 'places and desks name nobody');
}

function test_walk_in_queue_store_clear_runs_in_the_general_job()
{
	$job = walk_in_queue_store_test_source('job.php');
	pg_assert_contains('$waiting_cleared = pg_waiting_keep_clear_daily();', $job, 'the general job calls the clear');

	$store = walk_in_queue_store_test_source('includes/fn/walk_in_queue_store.php');
	pg_assert_contains('if (!pg_waiting_ready())', $store, 'nothing runs before the step');
}
