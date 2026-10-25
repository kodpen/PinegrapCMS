<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a note's revision (ws_notes.revision): when a change sent
 * against what was read is refused, that every write of a note's title, text
 * or pin moves the revision on, and that the schema step and the API carry
 * it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/notes.php');

// The text of a product file, for the checks that read the source.
function workspace_notes_revision_test_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

function test_workspace_notes_revision_stale()
{
	$note = array('revision' => 7, 'updated_at' => 1000, 'updated_by' => 3);

	pg_assert_false(ws_note_stale($note, array(), 3), 'nothing sent: nothing to compare');
	pg_assert_false(ws_note_stale($note, array('revision' => 7), 3), 'the revision that was read');
	pg_assert_true(ws_note_stale($note, array('revision' => 6), 3), 'an older revision, though the same person changed it (another device)');
	pg_assert_true(ws_note_stale($note, array('revision' => 6, 'base' => 1000), 9), 'the revision decides over base');
	pg_assert_false(ws_note_stale($note, array('revision' => 0, 'base' => 1000), 9), 'revision 0 is none: base decides');

	pg_assert_true(ws_note_stale($note, array('base' => 999), 9), 'base older than the note, changed by another person');
	pg_assert_false(ws_note_stale($note, array('base' => 999), 3), 'base: the person\'s own change is not a conflict');
	pg_assert_false(ws_note_stale($note, array('base' => 1000), 9), 'base: read at that very second');

	$before_upgrade = array('updated_at' => 1000, 'updated_by' => 3);

	pg_assert_false(ws_note_stale($before_upgrade, array('revision' => 6), 9), 'no revision column yet: a revision sent is not compared');
	pg_assert_true(ws_note_stale($before_upgrade, array('revision' => 6, 'base' => 999), 9), 'and base still works');
}

function test_workspace_notes_revision_moves_on_every_write()
{
	$files = array(
		'includes/workspace/notes.php',
		'includes/workspace/notes_import.php',
	);

	foreach ($files as $file) {
		$source = workspace_notes_revision_test_source($file);

		preg_match_all('/db\("UPDATE ws_notes SET (.*?)WHERE/s', $source, $matches);

		foreach ($matches[1] as $set) {
			// ws_note_save() joins its fields with implode(): checked below.
			if (!preg_match('/\b(body|title|pinned)\b/', $set) || (strpos($set, 'implode') !== false)) {
				continue;
			}

			pg_assert_true(strpos($set, 'ws_note_revision_sql()') !== false, $file . ': an UPDATE of a note\'s text, title or pin moves the revision on: ' . trim(substr($set, 0, 80)));
		}
	}

	pg_assert_contains('$parts[] = ws_note_revision_sql(false);', workspace_notes_revision_test_source('includes/workspace/notes.php'), 'ws_note_save() moves it with the fields it writes');
}

function test_workspace_notes_revision_schema_and_api()
{
	$migration = workspace_notes_revision_test_source('includes/migrations/2026.11.0.php');

	pg_assert_contains('upgrade_2026_11_0_workspace_note_revision();', $migration, 'the step is called');
	pg_assert_contains("install_add_column('ws_notes', 'revision', \"INT UNSIGNED NOT NULL DEFAULT 1\");", $migration, 'the column, every note starting at 1');

	$api = workspace_notes_revision_test_source('includes/workspace/api.php');

	pg_assert_true((bool) preg_match("/'workspace\\.notes\\.update'.*?array\\('name' => 'revision', 'in' => 'body', 'type' => 'int'/s", $api), 'the update takes the revision that was read');

	$resources = workspace_notes_revision_test_source('includes/workspace/api_resources.php');

	pg_assert_contains("'revision'    => 'integer?',", $resources, 'the note object declares it');
	pg_assert_contains("\$data['revision'] = (int) \$params['revision'];", $resources, 'the update hands it to the check');
}
