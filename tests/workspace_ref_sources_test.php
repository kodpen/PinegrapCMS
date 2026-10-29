<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the texts whose tags ws_refs keeps (includes/workspace/refs.php):
 * messages and tasks, and since 11.0.116 notes and workflow table rows. How
 * the readers take a row by its source (ws_ref_sources()); which records a
 * note and a row tag (ws_note_ref_tokens(), ws_wtable_row_ref_tokens());
 * that their writers wait for the column and write no channel; that a note
 * or a row is listed only where the reader reaches it, Claude's key narrowed
 * as on its own routes, in "where was this record talked about"
 * (ws_record_refs()), GET /workspace/refs and a customer's history; the
 * general job's backfill and its cursor; and the schema step, run twice
 * against an in-memory schema.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');
require_once(__DIR__ . '/helpers/source_reader.php');

// A function's source without its comment lines.
function workspace_ref_sources_test_source($function)
{
	return (string) preg_replace('~^\s*(//|\*|/\*\*).*$~m', '', pg_test_function_source($function));
}

// The texts whose tags ws_refs keeps.
function test_ws_ref_source_types_are_messages_tasks_notes_and_rows()
{
	pg_assert_same(array('message', 'task', 'note', 'wtable_row'), ws_ref_source_types());
}

// Each row goes to its own source, each id once, in the order read. A row
// of a source the readers do not know (the empty value an ENUM column keeps
// for a value it lacks, or a source of a later version) and a row without
// an id are left out.
function test_ws_ref_sources_groups_rows_by_their_source()
{
	$sources = ws_ref_sources(array(
		array('source_type' => 'message', 'source_id' => '31'),
		array('source_type' => 'task', 'source_id' => '7'),
		array('source_type' => 'note', 'source_id' => '8'),
		array('source_type' => 'wtable_row', 'source_id' => '12'),
		array('source_type' => '', 'source_id' => '9'),
		array('source_type' => 'mail', 'source_id' => '4'),
		array('source_type' => 'message', 'source_id' => '30'),
		array('source_type' => 'message', 'source_id' => '31'),
		array('source_type' => 'task', 'source_id' => '0'),
		array('source_id' => '5'),
	));

	pg_assert_same(array('message' => array(31 => 31, 30 => 30), 'task' => array(7 => 7), 'note' => array(8 => 8), 'wtable_row' => array(12 => 12)), $sources);
	pg_assert_same(array('message' => array(), 'task' => array(), 'note' => array(), 'wtable_row' => array()), ws_ref_sources(array()), 'no rows');
}

// A note tags the records written in its text, each once: people,
// departments, applications, tasks and channels are no record, a token
// whose sign does not belong to its kind is no tag, and an id 0 none.
function test_ws_note_ref_tokens_keeps_record_tags_once()
{
	$body = "Call <@user:3> about <#order:12> and <#contact:7>.\n<#order:12> again, <#task:9>, <@order:5>, <#channel:2>, <#product:0>, <@app:1>, <@dept:4>";

	pg_assert_same(array(array('type' => 'order', 'id' => 12), array('type' => 'contact', 'id' => 7)), ws_note_ref_tokens($body));
	pg_assert_same(array(), ws_note_ref_tokens(''), 'no text');
	pg_assert_same(array(), ws_note_ref_tokens('Order 12 and #order:12, written out'), 'no token');
}

// A row points at the records of its record-link cells, of the link columns
// the table has now, each once: a text cell holding a token, a person, a
// deleted column's cell and a kind that is no record's do not count.
function test_ws_wtable_row_ref_tokens_reads_the_record_links()
{
	$columns = array(
		'1' => array('type' => 'text'),
		'2' => array('type' => 'link'),
		'3' => array('type' => 'link'),
		'4' => array('type' => 'person'),
		'6' => array('type' => 'link'),
	);
	$cells = array(
		'1' => '<#order:99>',
		'2' => array('kind' => 'contact', 'id' => 7),
		'3' => array('kind' => 'contact', 'id' => 7),
		'4' => 12,
		'5' => array('kind' => 'order', 'id' => 4),
		'6' => array('kind' => 'supplier', 'id' => 3),
	);

	pg_assert_same(array(array('type' => 'contact', 'id' => 7)), ws_wtable_row_ref_tokens($columns, $cells));

	$cells['6'] = array('kind' => 'erp_account', 'id' => '15');

	pg_assert_same(array(array('type' => 'contact', 'id' => 7), array('type' => 'erp_account', 'id' => 15)), ws_wtable_row_ref_tokens($columns, $cells), 'another record');
	pg_assert_same(array(), ws_wtable_row_ref_tokens($columns, array('2' => array('kind' => 'order', 'id' => 0), '3' => 'contact:7')), 'no id, no link');
	pg_assert_same(array(), ws_wtable_row_ref_tokens(array(), $cells), 'a table without columns');
}

// The writers wait for the column to take the sources: on the ENUM of
// 2026.4.4 a note's row would be kept as ''. They replace what each source
// had and write channel 0, so a note or a table never makes a channel watch
// a record (ws_watch_channels() reads channel_id > 0).
function test_ws_ref_source_write_waits_for_the_column_and_writes_no_channel()
{
	$write = workspace_ref_sources_test_source('ws_ref_source_write');

	pg_assert_contains('ws_ref_sources_ready()', $write, 'nothing written on the old enum');
	pg_assert_contains("array('note', 'wtable_row')", $write, 'only the two new sources');
	pg_assert_contains("DELETE FROM ws_refs WHERE source_type = '", $write, 'what each source had is replaced');
	pg_assert_contains("\"', 0, '\"", $write, 'channel_id 0');

	$probe = workspace_ref_sources_test_source('ws_ref_sources_ready');

	pg_assert_contains("TABLE_NAME = 'ws_refs' AND COLUMN_NAME = 'source_type'", $probe, 'the column asked by its exact name');
	pg_assert_contains("=== 'varchar'", $probe, 'ready once it is a VARCHAR');
	pg_assert_contains('static $ready', $probe, 'asked once a request');
}

// Every write of a note's text keeps its tags: a save (the panel, the API,
// a forward, a meeting, a template), the assistant's answer written in, a
// message kept as a note, a new note of the import; a deleted note's go.
function test_ws_ref_note_writers()
{
	foreach (array('ws_note_save', 'ws_note_claude_deliver', 'ws_note_from_message', 'ws_note_import_write') as $function) {
		$source = workspace_ref_sources_test_source($function);

		pg_assert_contains("ws_ref_source_write('note'", $source, $function . ' keeps the note\'s tags');
		pg_assert_contains('ws_note_ref_tokens(', $source, $function . ' from its text');
	}

	pg_assert_contains("ws_ref_source_write('note', array(\$id => array()))", workspace_ref_sources_test_source('ws_note_delete'), 'a deleted note\'s tags go');
	pg_assert_contains('ws_note_save($viewer', workspace_ref_sources_test_source('ws_note_import_write'), 'an import that updates a note saves it');
}

// Every write of a row keeps its links; a deleted row's and a deleted
// table's rows' go, read before the rows are; an import adds its rows
// through the row create.
function test_ws_ref_table_row_writers()
{
	foreach (array('ws_wtable_row_create', 'ws_wtable_row_update') as $function) {
		$source = workspace_ref_sources_test_source($function);

		pg_assert_contains("ws_ref_source_write('wtable_row'", $source, $function . ' keeps the row\'s links');
		pg_assert_contains('ws_wtable_row_ref_tokens(', $source, $function . ' from its cells');
	}

	pg_assert_contains("ws_ref_source_write('wtable_row', array((int) \$row['id'] => array()))", workspace_ref_sources_test_source('ws_wtable_row_delete'), 'a deleted row\'s links go');

	$delete = workspace_ref_sources_test_source('ws_wtable_delete');

	pg_assert_contains("ws_ref_source_write('wtable_row'", $delete, 'a deleted table\'s rows lose their links');
	pg_assert_true(strpos($delete, "ws_ref_source_write('wtable_row'") < strpos($delete, "'ws_wtable_rows'"), 'read before the rows go');
	pg_assert_contains('ws_wtable_row_create(', workspace_ref_sources_test_source('ws_wtable_import_rows'), 'an import writes through the row create');
}

// The readers take each source by its type: a message where the reader may
// read its channel, a task where they may see it, a note only where
// ws_note_access() gives access, a row only where the reader reaches its
// table and while it still points at the record. Before, every row that was
// not a message was read as a task.
function test_ws_ref_readers_take_each_source_by_its_type()
{
	foreach (array('ws_record_refs', 'ws_customer_history_ref_rows') as $function) {
		$source = workspace_ref_sources_test_source($function);

		pg_assert_contains('ws_ref_sources(', $source, $function . ' groups its rows by source');
		pg_assert_contains("\$sources['task']", $source, $function . ' reads tasks from the task rows');
		pg_assert_contains("ws_ref_notes_open(\$viewer, \$sources['note']", $source, $function . ' reads notes from the note rows');
		pg_assert_contains("ws_ref_rows_open(\$viewer, \$sources['wtable_row']", $source, $function . ' reads table rows from theirs');
		pg_assert_false(strpos($source, "\$row['source_type'] === 'message'") !== false, $function . ' no longer reads every row but messages as a task');
	}

	$notes = workspace_ref_sources_test_source('ws_ref_notes_open');

	pg_assert_contains('ws_note_access($viewer, $row', $notes, 'a note only where its access rule gives access');
	pg_assert_contains('ws_note_shares_map(', $notes, 'the shares read once for all the notes');

	$rows = workspace_ref_sources_test_source('ws_ref_rows_open');

	pg_assert_contains('ws_wtable_access($viewer, $table)', $rows, 'a row only where the reader reaches its table');
	pg_assert_contains('ws_wtable_row_ref_tokens(', $rows, 'and only while it still points at the record');
}

// Claude's key reads a note or a table as it does on their own routes
// (ws_api_note_reachable(), ws_api_wtable_reachable()), in GET
// /workspace/refs before the channel gate keeps only its three lists, and
// in a customer's history.
function test_ws_ref_claude_key_reaches_notes_and_tables_as_on_their_routes()
{
	$get = workspace_ref_sources_test_source('ws_api_refs_get');

	pg_assert_contains('ws_api_note_reachable($viewer, $note)', $get, 'a note');
	pg_assert_contains('ws_api_wtable_reachable($viewer, ', $get, 'a table');
	pg_assert_true(strpos($get, 'ws_api_wtable_reachable(') < strpos($get, 'ws_api_refs_within('), 'before the channel gate');

	$history = workspace_ref_sources_test_source('ws_customer_history_ref_rows');

	pg_assert_contains('ws_api_note_reachable($viewer, $note)', $history, 'a note in the history');
	pg_assert_contains('ws_api_wtable_reachable($viewer, ', $history, 'a table in the history');

	$schema = ws_api_refs_schema();

	pg_assert_same(array('messages', 'tasks', 'channels', 'notes', 'rows'), array_keys($schema));
	pg_assert_same(array('id', 'name', 'excerpt', 'updated'), array_keys($schema['notes'][0]));
	pg_assert_same(array('id', 'table_id', 'table', 'title'), array_keys($schema['rows'][0]));
}

// A customer's history lists the notes and table rows that tag the customer
// as entries of their own, linking to the note and to the row's table; none
// without the Workspace.
function test_ws_ref_customer_history_lists_notes_and_rows()
{
	$data = array(
		'notes' => array(array('id' => 8, 'name' => 'Call notes', 'excerpt' => 'Asked for a discount', 'updated_at' => 1300)),
		'rows'  => array(array('id' => 12, 'table_id' => 3, 'table' => 'Pipeline', 'title' => 'Demir Ltd', 'updated_at' => 1200)),
	);
	$money = function ($minor, $currency) {
		return (string) $minor;
	};
	$history = ws_customer_history_build(array('workspace' => true), $data, $money, '/panel/', 20);

	pg_assert_same(array('note', 'wtable_row'), array_column($history['entries'], 'kind'), 'newest first');
	pg_assert_same(array(8, 1300, 'Call notes', 'Asked for a discount', '/panel/workspace_notes.php?note=8'),
		array($history['entries'][0]['id'], $history['entries'][0]['at'], $history['entries'][0]['title'], $history['entries'][0]['detail'], $history['entries'][0]['url']));
	pg_assert_same(array(12, 1200, 'Demir Ltd', 'Pipeline', '/panel/workspace_wtables.php?id=3&row=12#ws-wt-row-form'),
		array($history['entries'][1]['id'], $history['entries'][1]['at'], $history['entries'][1]['title'], $history['entries'][1]['detail'], $history['entries'][1]['url']));
	pg_assert_same(null, $history['entries'][0]['amount'], 'no amount');
	pg_assert_same(array(), ws_customer_history_build(array('workspace' => false), $data, $money, '/panel/', 20)['entries'], 'none without the Workspace');
}

// The cursor of the backfill as kept in config_text: anything that is not
// one reads as the start, and done stays done.
function test_ws_ref_backfill_cursor_reads_what_was_kept()
{
	$start = array('source' => 'note', 'after' => 0, 'done' => false);

	pg_assert_same($start, ws_ref_backfill_cursor(''), 'nothing kept');
	pg_assert_same($start, ws_ref_backfill_cursor('not json'));
	pg_assert_same($start, ws_ref_backfill_cursor('{"source":"message","after":-3}'), 'an unknown source and a negative id');
	pg_assert_same(array('source' => 'wtable_row', 'after' => 40, 'done' => false), ws_ref_backfill_cursor('{"source":"wtable_row","after":40}'));
	pg_assert_true(ws_ref_backfill_cursor('{"done":1}')['done'], 'done stays done');
}

// After a batch: a full one goes on past its last id; a short one ends its
// source, the notes going on to the rows and the rows ending the work.
function test_ws_ref_backfill_next_moves_on()
{
	$notes = array('source' => 'note', 'after' => 0, 'done' => false);
	$rows = array('source' => 'wtable_row', 'after' => 50, 'done' => false);

	pg_assert_same(array('source' => 'note', 'after' => 250, 'done' => false), ws_ref_backfill_next($notes, 250, 100, 100), 'a full batch of notes');
	pg_assert_same(array('source' => 'wtable_row', 'after' => 0, 'done' => false), ws_ref_backfill_next($notes, 260, 10, 100), 'a short batch ends the notes');
	pg_assert_same(array('source' => 'wtable_row', 'after' => 0, 'done' => false), ws_ref_backfill_next($notes, 0, 0, 100), 'no notes at all');
	pg_assert_same(array('source' => 'wtable_row', 'after' => 900, 'done' => false), ws_ref_backfill_next($rows, 900, 100, 100), 'a full batch of rows');
	pg_assert_same(array('source' => 'wtable_row', 'after' => 0, 'done' => true), ws_ref_backfill_next($rows, 910, 3, 100), 'a short batch of rows ends the work');
}

// The backfill runs from the general job under a time budget, once the
// column takes the sources, and keeps its place after every batch; a site
// that is through reads its marker and nothing more.
function test_ws_ref_backfill_runs_from_the_general_job()
{
	$run = workspace_ref_sources_test_source('ws_ref_sources_backfill_run');

	pg_assert_contains("pg_config_text('ws_refs_backfill')", $run, 'the cursor is kept in config_text');
	pg_assert_contains("pg_config_text_save('ws_refs_backfill'", $run, 'saved after every batch');
	pg_assert_contains('ws_ref_sources_ready()', $run, 'only once the column takes the sources');
	pg_assert_true(strpos($run, "\$cursor['done']") < strpos($run, 'db_items('), 'nothing is read once through');
	pg_assert_contains('ws_ref_source_write($cursor[\'source\'], $tags)', $run, 'each batch replaced as a save replaces it');
	pg_assert_contains('microtime(true)', $run, 'under a time budget');
	pg_assert_contains('ws_ref_sources_backfill_run(', (string) file_get_contents(PG_FUNCTIONS_DIR . '/job.php'), 'the general job runs it');
}

// The step is called once, under its label, and needs no heavy-table note.
function test_ws_ref_sources_schema_step_is_called_under_its_label()
{
	$migration = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/2026.11.0.php');

	pg_assert_same(1, substr_count($migration, "\tupgrade_2026_11_0_workspace_ref_sources();"), 'called once from upgrade_to_2026_11_0()');
	pg_assert_true((bool) preg_match('~\tupgrade_2026_11_0_workspace_ref_sources\(\); +// 11\.0\.116\n~', $migration), 'under 11.0.116');
	pg_assert_contains("install_modify_column('ws_refs', 'source_type', \"VARCHAR(20) NOT NULL DEFAULT 'message'\")", $migration);
	pg_assert_false(strpos((string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/runner.php'), "'ws_refs'") !== false, 'no install_heavy_tables() entry');
}

// Runs the step against an in-memory schema in a PHP process of its own
// (the real runner helpers, a fake db layer): twice on the ENUM of 2026.4.4,
// once on a site without ws_refs. Null when PHP cannot be started again.
function workspace_ref_sources_test_run_step()
{
	if (!function_exists('exec') || !defined('PHP_BINARY') || (PHP_BINARY === '')) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgrefschema');

	if ($file === false) {
		return null;
	}

	$script = <<<'PHP'
define('INSTALL_OR_UPDATE', true);

$GLOBALS['fake_schema'] = array();
$GLOBALS['fake_writes'] = array();

function e($value) { return addslashes((string) $value); }

function lang($text)
{
	if (is_array($text)) {
		return str_replace('{var:1}', (string) $text['vars'], $text['string']);
	}
	return $text;
}

function db_value($sql)
{
	if (preg_match("/TABLE_NAME = '([^']+)'/", $sql, $m)) {
		return isset($GLOBALS['fake_schema'][$m[1]]) ? 1 : 0;
	}
	throw new Exception('unexpected db_value: ' . $sql);
}

function db_item($sql)
{
	if (preg_match("/^SHOW COLUMNS FROM `([^`]+)` WHERE Field = '([^']+)'$/", $sql, $m)) {
		if (isset($GLOBALS['fake_schema'][$m[1]][$m[2]])) {
			return array('Field' => $m[2], 'Type' => $GLOBALS['fake_schema'][$m[1]][$m[2]], 'Null' => 'NO', 'Default' => 'message');
		}
		return array();
	}
	throw new Exception('unexpected db_item: ' . $sql);
}

function db($sql)
{
	$GLOBALS['fake_writes'][] = $sql;
	if (preg_match('/^ALTER TABLE `([^`]+)` MODIFY `([^`]+)` (VARCHAR\(\d+\))/', $sql, $m)) {
		$GLOBALS['fake_schema'][$m[1]][$m[2]] = strtolower($m[3]);
		return true;
	}
	throw new Exception('unexpected db: ' . $sql);
}

require $pg_dir . '/includes/migrations/runner.php';
require $pg_dir . '/includes/migrations/2026.11.0.php';

$out = array('runs' => array());

$GLOBALS['fake_schema'] = array('ws_refs' => array('source_type' => "enum('message','task')", 'ref_type' => 'varchar(20)'));

foreach (array(1, 2) as $run) {
	$GLOBALS['fake_writes'] = array();
	upgrade_2026_11_0_workspace_ref_sources();
	$out['runs'][] = $GLOBALS['fake_writes'];
}

$out['schema'] = $GLOBALS['fake_schema'];

$GLOBALS['fake_schema'] = array();
$GLOBALS['fake_writes'] = array();
upgrade_2026_11_0_workspace_ref_sources();
$out['missing'] = $GLOBALS['fake_writes'];

echo json_encode($out);
PHP;

	file_put_contents($file, '<?php' . "\n"
		. '$pg_dir = ' . var_export(PG_FUNCTIONS_DIR, true) . ";\n"
		. $script . "\n");

	$lines = array();
	$code = 1;
	@exec(escapeshellarg(PHP_BINARY) . ' -d display_errors=stderr -d error_reporting=-1 ' . escapeshellarg($file) . ' 2>&1', $lines, $code);
	@unlink($file);

	$result = json_decode(implode("\n", $lines), true);

	if (($code !== 0) || !is_array($result) || !isset($result['runs'], $result['schema'], $result['missing'])) {
		return array('error' => implode("\n", $lines));
	}

	return $result;
}

// The first run turns the ENUM into VARCHAR(20); the second, and a run on a
// site without the table, write nothing.
function test_ws_ref_sources_schema_step_run_twice_changes_nothing_the_second_time()
{
	$result = workspace_ref_sources_test_run_step();

	if ($result === null) {
		return;
	}

	pg_assert_true(!isset($result['error']), 'the step ran: ' . (isset($result['error']) ? $result['error'] : ''));

	if (isset($result['error'])) {
		return;
	}

	list($first, $second) = $result['runs'];

	pg_assert_same(array("ALTER TABLE `ws_refs` MODIFY `source_type` VARCHAR(20) NOT NULL DEFAULT 'message'"), $first, 'the enum widened once');
	pg_assert_same(array(), $second, 'the second run writes nothing');
	pg_assert_same('varchar(20)', $result['schema']['ws_refs']['source_type']);
	pg_assert_same(array(), $result['missing'], 'nothing written where the table is missing');
}
