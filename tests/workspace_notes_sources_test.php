<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the place a note came from (ws_note_sources): the id each kind
 * of file is known by, what the import plans for a file it saw before, and
 * the schema step and the code that keep the rows.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/refs.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/notes_io.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/notes_import.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/notes_enex.php');

function test_workspace_notes_sources_id_per_provider()
{
	pg_assert_same('Takeout/Keep/Alışveriş.json', ws_note_source_id('keep', array('entry' => 'Takeout/Keep/Alışveriş.json')), 'Keep: the JSON entry name');

	pg_assert_same('a1b2-c3', ws_note_source_id('enex', array('guid' => ' a1b2-c3 ', 'title' => 'Plan', 'created' => 1700000000)), 'Evernote: its guid when exported');
	pg_assert_same('Plan 2023-11-14T22:13:20Z', ws_note_source_id('enex', array('guid' => '', 'title' => 'Plan', 'created' => 1700000000)), 'Evernote: else title and created');
	pg_assert_same('Plan', ws_note_source_id('enex', array('title' => 'Plan', 'created' => 0)), 'Evernote: a note without a date by its title');
	pg_assert_same('', ws_note_source_id('enex', array('title' => '', 'created' => 0)), 'Evernote: nothing to know it by');

	pg_assert_same('20231001-x', ws_note_source_id('markdown', array('id' => '20231001-x', 'path' => 'Daily/a.md')), 'Markdown: the front matter id');
	pg_assert_same('Daily/a.md', ws_note_source_id('markdown', array('id' => '', 'path' => 'Daily/a.md')), 'Markdown: else the path in the upload');

	pg_assert_same('https://example.com/post', ws_note_source_id('html', array('address' => 'https://example.com/post', 'name' => 'post.html')), 'HTML: its canonical address');
	pg_assert_same('post.html', ws_note_source_id('html', array('address' => '', 'name' => 'C:\\Users\\me\\post.html')), 'HTML: else the file name');

	pg_assert_same('', ws_note_source_id('notion', array('id' => 'x')), 'an unknown provider has no id');
	pg_assert_same('a b', ws_note_source_id('markdown', array('id' => "a\x01\tb", 'path' => '')), 'control characters are spaces');
}

function test_workspace_notes_sources_long_ids_fit_the_column()
{
	$long = str_repeat('ş', 150) . str_repeat('x', 100);
	$other = str_repeat('ş', 150) . str_repeat('y', 100);
	$id = ws_note_source_id('markdown', array('id' => $long, 'path' => ''));

	pg_assert_same(WS_NOTE_SOURCE_ID_MAX, mb_strlen($id), 'cut to the column');
	pg_assert_same($id, ws_note_source_id('markdown', array('id' => $long, 'path' => '')), 'the same every time');
	pg_assert_true($id !== ws_note_source_id('markdown', array('id' => $other, 'path' => '')), 'two ids that start alike stay apart');
	pg_assert_same(str_repeat('a', WS_NOTE_SOURCE_ID_MAX), ws_note_source_id('markdown', array('id' => str_repeat('a', WS_NOTE_SOURCE_ID_MAX), 'path' => '')), 'an id that fits is kept as it is');
}

function test_workspace_notes_sources_hash()
{
	pg_assert_same(40, strlen(ws_note_source_hash('T', 'body')));
	pg_assert_same(ws_note_source_hash('T', "a\r\nb"), ws_note_source_hash('T', "a\nb"), 'line ends do not count');
	pg_assert_true(ws_note_source_hash('T', 'body') !== ws_note_source_hash('U', 'body'), 'the title counts');
}

function test_workspace_notes_sources_plan_decision()
{
	$hash = ws_note_source_hash('T', 'body');

	pg_assert_same('same', ws_note_import_source_kind($hash, $hash, 'owner'), 'the file as it was last brought in');
	pg_assert_same('same', ws_note_import_source_kind($hash, $hash, 'view'), 'unchanged, whatever the access');
	pg_assert_same('update', ws_note_import_source_kind($hash, ws_note_source_hash('T', 'changed'), 'owner'), 'changed since: its note brought up to date');
	pg_assert_same('update', ws_note_import_source_kind($hash, ws_note_source_hash('T', 'changed'), 'edit'));
	pg_assert_same('new', ws_note_import_source_kind($hash, ws_note_source_hash('T', 'changed'), 'view'), 'a note they may only read: a new note');
	pg_assert_same('new', ws_note_import_source_kind($hash, $hash, ''), 'its note gone: a new note');
}

function test_workspace_notes_sources_staged_file_carries_its_id()
{
	$text = ws_note_enex_file(array('title' => 'Plan', 'created' => 1700000000, 'updated' => 0, 'tags' => array(), 'id' => 'Takeout/Keep/Plan: "x".json'), 'Body');

	pg_assert_contains('id: ', $text, 'the id in the front matter');

	$parsed = ws_note_import_parse(ws_note_import_text($text), 'Plan.md', 0);

	pg_assert_same('Takeout/Keep/Plan: "x".json', $parsed['source'], 'read back whole');
	pg_assert_same('Body', $parsed['body']);

	$plain = ws_note_import_parse("---\ntitle: A\n---\n\nText", 'a.md', 0);

	pg_assert_same('', $plain['source'], 'a file without an id');
	pg_assert_false(strpos(ws_note_enex_file(array('title' => 'A', 'tags' => array()), 'x'), 'id:') !== false, 'no id, no line');
}

// The text of a product file, for the checks that read the source.
function workspace_notes_sources_test_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

// The body of a function in a product file, up to the next function.
function workspace_notes_sources_test_body($path, $name)
{
	$source = workspace_notes_sources_test_source($path);
	$start = strpos($source, 'function ' . $name . '(');

	pg_assert_true($start !== false, $name . ' is there');

	$end = strpos($source, "\nfunction ", $start + 10);

	return (string) substr($source, $start, ($end === false) ? null : $end - $start);
}

function test_workspace_notes_sources_schema_step_and_table_list()
{
	$migration = workspace_notes_sources_test_source('includes/migrations/2026.11.0.php');

	pg_assert_contains('upgrade_2026_11_0_workspace_note_sources(); // 11.0.86', $migration, 'the step is called');
	pg_assert_contains("install_create_table('ws_note_sources'", $migration, 'it creates the table');
	pg_assert_contains('UNIQUE KEY idx_source (user_id, provider, external_id)', $migration, 'one row a file a person brought in');
	pg_assert_contains("'ws_note_sources',", workspace_notes_sources_test_source('install/index.php'), 'a reinstall drops it with the other tables (get_tables())');
	pg_assert_contains("pg_schema_has('ws_note_sources')", workspace_notes_sources_test_body('includes/workspace/notes.php', 'ws_note_sources_ready'), 'the code runs without the table');
}

function test_workspace_notes_sources_written_read_and_dropped()
{
	pg_assert_contains('ws_note_source_save(', workspace_notes_sources_test_body('includes/workspace/notes_import.php', 'ws_note_import_write'), 'the import writes the row');
	pg_assert_contains('ws_note_source_find(', workspace_notes_sources_test_body('includes/workspace/notes_import.php', 'ws_note_import_entry'), 'the plan reads it');
	pg_assert_contains('DELETE FROM ws_note_sources', workspace_notes_sources_test_body('includes/workspace/notes.php', 'ws_note_delete'), 'a deleted note drops its rows');
	pg_assert_contains("'provider'", workspace_notes_sources_test_body('includes/workspace/notes_import.php', 'ws_note_import_stage'), 'the stage keeps the kind of upload');
}
