<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the application that changed a Workspace note (notes.php,
 * 2026.11.0 step 11.0.257): the save is written as the key's application,
 * the card and the versions name it ("Changed by @handle"), the API's
 * changed_by, and the migration step's two columns.
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

require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/notes.php');

function ws_note_app_test_source($file, $function)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
	$start = strpos($source, 'function ' . $function . '(');

	pg_assert_true($start !== false, $function . ' exists');

	$end = strpos($source, "\nfunction ", $start + 1);

	return ($end === false) ? substr($source, $start) : substr($source, $start, $end - $start);
}

function ws_note_app_test_person()
{
	return array('id' => 7, 'name' => 'Ada Lovelace', 'username' => 'ada');
}

function ws_note_app_test_app()
{
	return array('id' => 0, 'app_id' => 12, 'name' => 'Builder (application)', 'username' => '', 'app' => true, 'agent' => true);
}

// Only a key's application is written; a person, or nothing sent, is 0.
function test_ws_note_saver_app()
{
	pg_assert_same(12, ws_note_saver_app(array('app_id' => '12')), 'the key\'s application');
	pg_assert_same(0, ws_note_saver_app(array('title' => 'x')), 'a person saved');
	pg_assert_same(0, ws_note_saver_app(array('app_id' => -4)), 'never below zero');
}

// The application is named when its key saved, the person otherwise.
function test_ws_note_changer()
{
	pg_assert_same(12, ws_note_changer(ws_note_app_test_person(), ws_note_app_test_app())['app_id'], 'the application');
	pg_assert_same(7, ws_note_changer(ws_note_app_test_person(), null)['id'], 'the person');
	pg_assert_same(null, ws_note_changer(null, null), 'nobody known');
}

// "Changed by @handle"; an application, which has no handle, by its name.
function test_ws_note_changed_by_words()
{
	pg_assert_contains('@ada', ws_note_changed_by_words(ws_note_app_test_person()), 'the person\'s handle');
	pg_assert_contains('Builder (application)', ws_note_changed_by_words(ws_note_app_test_app()), 'the application\'s name');
	pg_assert_contains('Ada Lovelace', ws_note_changed_by_words(array('id' => 7, 'name' => 'Ada Lovelace', 'username' => '')), 'a name without a handle');
	pg_assert_same('', ws_note_changed_by_words(null), 'nobody known');
}

// The API's changed_by: the application with user_id null, else the person.
function test_ws_api_note_changer()
{
	$app = ws_api_note_changer(ws_note_app_test_person(), ws_note_app_test_app());

	pg_assert_same(null, $app['user_id'], 'an application has no user id');
	pg_assert_same(12, $app['app_id'], 'its application');
	pg_assert_same(true, $app['agent'], 'an agent');

	$person = ws_api_note_changer(ws_note_app_test_person(), null);

	pg_assert_same(7, $person['user_id'], 'the person');
	pg_assert_same(null, $person['app_id'], 'no application');
	pg_assert_same(null, ws_api_note_changer(null, null), 'nobody known');
	pg_assert_same(null, ws_api_note_changer('Ada', null), 'not a person row');
}

// note_card carries changed_by, and its schema declares it.
function test_ws_api_note_card_changed_by()
{
	$map = array(5531 => array(
		'id' => 88, 'name' => 'Plan', 'excerpt' => '', 'access' => 'view', 'revision' => 2, 'updated_at' => 1767225600,
		'updated_by' => ws_note_app_test_person(), 'updated_app' => ws_note_app_test_app(),
	));
	$card = ws_api_message_note_card(array('id' => 5531, 'deleted_at' => 0), $map);

	pg_assert_same(12, $card['changed_by']['app_id'], 'the application named on the card');
	pg_assert_same('WorkspaceMessageSender?', ws_api_message_note_card_schema()['changed_by'], 'the card schema');
	pg_assert_same('WorkspaceMessageSender?', ws_api_note_schema()['changed_by'], 'the note schema');
}

// The save writes the application beside the person, on a new note and on
// a change, and the version it keeps carries the one that saved that state.
function test_ws_note_save_writes_the_application()
{
	$save = ws_note_app_test_source('includes/workspace/notes.php', 'ws_note_save');

	pg_assert_contains('$app_id = ws_note_saver_app($data);', $save, 'read from the save');
	pg_assert_contains("'updated_app_id, '", $save, 'on a new note');
	pg_assert_contains('"updated_app_id = \'" . $app_id . "\'"', $save, 'on a change');
	pg_assert_contains('ws_note_version_keep($viewer, $note, $sets, $app_id);', $save, 'the version keeps it');

	$keep = ws_note_app_test_source('includes/workspace/notes.php', 'ws_note_version_keep');

	pg_assert_contains("'app_id, '", $keep, 'the version\'s column');
	pg_assert_contains("\$note['updated_app_id']", $keep, 'the state\'s saver');

	pg_assert_contains('updated_app_id = 0', ws_note_app_test_source('includes/workspace/notes.php', 'ws_note_claude_deliver'), 'an answer the screen writes names no application');
	pg_assert_contains("'app_id' => (int) (\$data['app_id'] ?? 0)", ws_note_app_test_source('includes/workspace/notes.php', 'ws_note_channel_write'), 'a note written in a channel');
}

// The API's note writes pass the key's application; the panel's never do.
function test_ws_note_app_only_from_the_api()
{
	foreach (array('ws_api_notes_create', 'ws_api_notes_update') as $route) {
		pg_assert_contains("\$data['app_id'] = (int) ws_api_message_writer()['app_id'];", ws_note_app_test_source('includes/workspace/api_resources.php', $route), $route);
	}

	$actions = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php');
	$start = strpos($actions, "case 'ws_note_save':");

	pg_assert_true($start !== false, 'the panel save');
	pg_assert_false(strpos(substr($actions, $start, strpos($actions, 'ws_note_save($viewer, $data);', $start) - $start), 'app_id') !== false, 'the panel sends no application');
}

// The step adds both columns with the idempotent helper, labelled and called.
function test_ws_note_app_migration_step()
{
	$file = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/2026.11.0.php');

	pg_assert_contains('upgrade_2026_11_0_ws_note_app();             // 11.0.257', $file, 'called with its label');

	$step = ws_note_app_test_source('includes/migrations/2026.11.0.php', 'upgrade_2026_11_0_ws_note_app');

	pg_assert_contains("install_add_column('ws_notes', 'updated_app_id', \"INT UNSIGNED NOT NULL DEFAULT 0\");", $step, 'the note\'s column');
	pg_assert_contains("install_add_column('ws_note_versions', 'app_id', \"INT UNSIGNED NOT NULL DEFAULT 0\");", $step, 'the version\'s column');
	pg_assert_contains('install_note(', $step, 'a line for the operator');
	pg_assert_false(stripos($step, 'ALTER TABLE') !== false, 'no hand-written ALTER');
}
