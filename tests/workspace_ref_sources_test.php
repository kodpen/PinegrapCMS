<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for how the readers of ws_refs take a tag row by the text it was
 * written in (ws_ref_sources(), includes/workspace/refs.php): a message as a
 * message, a task as a task, and a row of any other source left out rather
 * than read as a task, both in "where was this record talked about"
 * (ws_record_refs()) and in a customer's history
 * (ws_customer_history_ref_rows()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');
require_once(__DIR__ . '/helpers/source_reader.php');

// The texts whose tags ws_refs keeps today.
function test_ws_ref_source_types_are_messages_and_tasks()
{
	pg_assert_same(array('message', 'task'), ws_ref_source_types());
}

// Each row goes to its own source, each id once, in the order read. A row
// of a source the readers do not know (a note, a table row, or the empty
// value an ENUM column keeps for a value it lacks) and a row without an id
// are left out.
function test_ws_ref_sources_groups_rows_by_their_source()
{
	$sources = ws_ref_sources(array(
		array('source_type' => 'message', 'source_id' => '31'),
		array('source_type' => 'task', 'source_id' => '7'),
		array('source_type' => 'note', 'source_id' => '8'),
		array('source_type' => 'wtable_row', 'source_id' => '12'),
		array('source_type' => '', 'source_id' => '9'),
		array('source_type' => 'message', 'source_id' => '30'),
		array('source_type' => 'message', 'source_id' => '31'),
		array('source_type' => 'task', 'source_id' => '0'),
		array('source_id' => '5'),
	));

	pg_assert_same(array('message' => array(31 => 31, 30 => 30), 'task' => array(7 => 7)), $sources);
	pg_assert_same(array('message' => array(), 'task' => array()), ws_ref_sources(array()), 'no rows');
}

// Both readers group their rows through ws_ref_sources(): before, every row
// that was not a message was read as a task, so a tag kept for another
// source would have listed the task of the same id.
function test_ws_ref_readers_take_each_source_by_its_type()
{
	foreach (array('ws_record_refs', 'ws_customer_history_ref_rows') as $function) {
		$source = pg_test_function_source($function);

		pg_assert_contains('ws_ref_sources(', $source, $function . ' groups its rows by source');
		pg_assert_contains("\$sources['task']", $source, $function . ' reads tasks from the task rows');
		pg_assert_false(strpos($source, "\$row['source_type'] === 'message'") !== false, $function . ' no longer reads every row but messages as a task');
	}
}
