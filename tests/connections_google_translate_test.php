<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for Google Cloud Translation on Connections: its key is typed on its
 * page under the gate of Site Settings › Languages and Translation it came
 * from; the Translation Engines card shows the connection and leads there;
 * the registry row and the old anchor follow the move.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_SETTINGS_MENU')) {
	define('PG_SETTINGS_MENU', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/settings/registry.php');
require_once(PG_FUNCTIONS_DIR . '/includes/translate/store.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/connections_registry.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/connections_screen.php');

function connections_google_translate_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function test_connections_google_translate_key_format()
{
	pg_assert_true(pg_tr_google_key_valid('AIzaSyD-abc_123' . str_repeat('x', 24)), 'a Google key has the shape');
	pg_assert_true(pg_tr_google_key_valid(str_repeat('a', 200)), '200 characters are allowed');
	pg_assert_false(pg_tr_google_key_valid(str_repeat('a', 201)), 'too long');
	pg_assert_false(pg_tr_google_key_valid(''), 'empty');
	pg_assert_false(pg_tr_google_key_valid('abc 123'), 'no space');
	pg_assert_false(pg_tr_google_key_valid("abc\n123"), 'no line break');
	pg_assert_false(pg_tr_google_key_valid('abc"123'), 'no quote');
	pg_assert_false(pg_tr_google_key_valid(null), 'not a string');
}

function test_connections_google_translate_page_card_keeps_the_languages_gate()
{
	pg_assert_same(array('status', 'details', 'uses'), pg_connections_screen_page_cards('google_translate', array('manager' => true, 'languages' => true)), 'who may open Languages and Translation types the key');
	pg_assert_same(array('status', 'uses'), pg_connections_screen_page_cards('google_translate', array('manager' => true, 'languages' => false)), 'a manager without the category sees no key');
	pg_assert_same(array('status', 'uses'), pg_connections_screen_page_cards('indexnow', array('languages' => true)), 'only Google Cloud Translation takes the languages gate');

	$screen = connections_google_translate_test_source('connections.php');
	pg_assert_contains("return pg_settings_may_open('languages', \$user);", $screen, 'the gate is the Languages and Translation category');
	pg_assert_contains("in_array(\$action, array('translate_save', 'translate_remove'), true) && connections_languages_may_manage(\$user)", $screen, 'the write checks the gate');
	pg_assert_contains('name="translation_google_key" value=""', $screen, 'the stored key is never drawn back');
	pg_assert_contains("'languages'    => (\$page_id === 'google_translate') && connections_languages_may_manage(\$user)", $screen, 'the details card takes the same right as the write');
}

function test_connections_google_translate_page_works_without_the_workspace()
{
	pg_assert_false(isset(pg_ai_connection_services()['google_translate']), 'not an AI provider connection, so the Workspace gate of the assistants does not hide it');
	pg_assert_same(array('status', 'details', 'uses'), pg_connections_screen_page_cards('google_translate', array('languages' => true, 'ai' => false, 'manager' => false)), 'the key is typed without the assistants\' right or the Workspace');

	$screen = connections_google_translate_test_source('connections.php');
	$start = strpos($screen, 'function connections_translate_page(');
	$end = strpos($screen, "\n}\n", $start);
	$page = substr($screen, $start, $end - $start);

	pg_assert_true(($start !== false) && ($end !== false), 'the page function is found');
	pg_assert_false((strpos($page, 'ws_') !== false) || (strpos($page, 'WORKSPACE') !== false), 'the page reads nothing of the Workspace');
}

function test_connections_google_translate_registry_and_anchor_follow_the_move()
{
	$registry = pg_connections_registry();

	pg_assert_same('connections.php', $registry['google_translate']['place'], 'its place is Connections');
	pg_assert_same('#settings/languages/pgset-translation-engines', $registry['google_translate']['found_at'], 'the Translation Engines card still finds it');
	pg_assert_same(78, pg_connections_count(), 'the figure stays');

	$anchors = pg_settings_legacy_anchors();
	pg_assert_same(array('languages', 'pgset-translation-engines'), $anchors['translation_google_key'], 'an old link to the field lands on the card');
}

function test_connections_google_translate_card_shows_the_connection()
{
	$card = connections_google_translate_test_source('includes/settings/languages.php');
	$save = connections_google_translate_test_source('includes/settings/languages.save.php');

	pg_assert_false(strpos($card, 'name="translation_google_key') !== false, 'the card no longer types or clears the key');
	pg_assert_contains('id="pgsub-google-translate"', $card, 'the card shows the connection');
	pg_assert_contains('connections.php?service=google_translate', $card, 'and leads to its page');
	pg_assert_contains("lang('Manage in Connections')", $card, 'with Manage in Connections');
	pg_assert_contains('id="translation_attribution"', $card, 'the attribution switch stays on the card');
	pg_assert_contains('id="translation_style_note"', $card, 'the style note stays on the card');
	pg_assert_false(strpos($save, 'pg_tr_google_key_store') !== false, 'the card\'s save cannot change the key');
}
