<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the @herkes tag (includes/workspace/messages.php and
 * agent_queue.php) that need no database: the tag as a word, who gets a
 * mention line from it, which agents it wakes, and the reserved handles.
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

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');

function test_workspace_everyone_tag()
{
	pg_assert_true(ws_message_everyone('@herkes look at this'));
	pg_assert_true(ws_message_everyone('Please, @HERKES.'), 'any case, punctuation after');
	pg_assert_true(ws_message_everyone("line one\n@everyone"), 'the English word too');
	pg_assert_true(ws_message_everyone('@Herkes: ready?'));
	pg_assert_false(ws_message_everyone('@herkesle konuştum'), 'inside a word is no match');
	pg_assert_false(ws_message_everyone('ali@herkes.com'), 'an address is no match');
	pg_assert_false(ws_message_everyone('@herkes-ekibi'), 'a longer handle is no match');
	pg_assert_false(ws_message_everyone('herkes buraya'), 'no @, no tag');
	pg_assert_false(ws_message_everyone('@hepsi'), 'hepsi is reserved, not a tag');
	pg_assert_false(ws_message_everyone(''));
}

function test_workspace_everyone_line_due()
{
	pg_assert_true(ws_mention_line_due(true, true, 'all', false), 'a named member');
	pg_assert_false(ws_mention_line_due(true, true, 'none', false), 'a named member who silenced the channel');
	pg_assert_true(ws_mention_line_due(true, true, 'none', true), '@herkes rings whatever the notify level');
	pg_assert_false(ws_mention_line_due(false, true, 'all', true), 'a guest of a room gets no line from @herkes');
	pg_assert_false(ws_mention_line_due(false, true, 'all', false), 'nor from a name');
	pg_assert_false(ws_mention_line_due(true, false, 'all', true), 'a member who may not read the channel');
}

function test_workspace_everyone_wakes()
{
	$app = array('kind' => 'app', 'handle' => 'ci', 'app_id' => 7, 'wake_rule' => 'mention');
	$model = array('kind' => 'model', 'handle' => 'writer', 'app_id' => 0, 'wake_rule' => 'mention');
	$reach = $app + array('reach_from' => 3);

	pg_assert_true(ws_agent_wake_matches($app, '@herkes the build is red', false), 'a person\'s @herkes wakes an application agent');
	pg_assert_true(ws_agent_wake_matches($app, '@everyone', false));
	pg_assert_false(ws_agent_wake_matches($app, '@herkes the build is red', false, true), 'an application\'s @herkes wakes no agent');
	pg_assert_false(ws_agent_wake_matches($model, '@herkes the build is red', false), 'a model agent is not woken');
	pg_assert_false(ws_agent_wake_matches($reach, '@herkes', false), 'an agent a discussion reaches through its channel is asked by name only');
	pg_assert_false(ws_agent_wake_matches($app, '@herkesle konuştum', false), 'inside a word wakes nobody');
	pg_assert_true(ws_agent_wake_matches($app, 'added @herkes', false, true, true), 'a person\'s edit asks by name, @herkes included');
	pg_assert_true(ws_agent_wake_matches($app, '@ci and @herkes', false, true), 'a mention still wakes under an application\'s words');
}

function test_workspace_everyone_reserved()
{
	foreach (array('herkes', 'everyone', 'hepsi') as $word) {
		pg_assert_true(in_array($word, ws_mcp_reserved_aliases(), true), $word . ' is reserved');
		pg_assert_true(ws_mcp_alias_shape_problem($word) !== '', $word . ' is no handle');
	}
}

function test_workspace_everyone_menu_wiring()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');

	pg_assert_contains("insert: '@herkes '", $js, 'the @ menu offers Everyone as @herkes');
	pg_assert_contains("if (self.channel && (!needle || ['herkes', 'everyone'", $js, 'only in a channel or discussion composer, found by both words');
	pg_assert_contains("items.unshift({ label: t('everyone')", $js, 'on top of the list');
}
