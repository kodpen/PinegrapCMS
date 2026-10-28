<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for who reaches a workflow table (includes/workspace/wtables.php):
 * the access from owning it and from its shares (ws_wtable_access_level(),
 * pure), what each share gives, what each access may do, and the reader
 * that gathers the facts: no role gives access, and a table whose owner
 * left the team is reached by nobody.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/wtables.php');

// What is known about a person and a table: a team member, a table of
// somebody else who is in the team, nothing shared.
function ws_wtables_access_test_facts($facts = array())
{
	return $facts + array('member' => true, 'owner' => false, 'present' => true, 'person' => '', 'channel' => false);
}

function test_workspace_wtables_access_owner()
{
	pg_assert_same('owner', ws_wtable_access_level(ws_wtables_access_test_facts(array('owner' => true))));
	pg_assert_same('', ws_wtable_access_level(ws_wtables_access_test_facts(array('owner' => true, 'member' => false))), 'an owner taken out of the team');
	pg_assert_same('', ws_wtable_access_level(ws_wtables_access_test_facts(array('owner' => true, 'present' => false))));
}

// Nothing reaches a table of another but its shares.
function test_workspace_wtables_access_shares()
{
	pg_assert_same('', ws_wtable_access_level(ws_wtables_access_test_facts()), 'somebody else\'s table, unshared');
	pg_assert_same('view', ws_wtable_access_level(ws_wtables_access_test_facts(array('person' => 'view'))));
	pg_assert_same('edit', ws_wtable_access_level(ws_wtables_access_test_facts(array('person' => 'edit'))));
	pg_assert_same('view', ws_wtable_access_level(ws_wtables_access_test_facts(array('channel' => true))));
	pg_assert_same('edit', ws_wtable_access_level(ws_wtables_access_test_facts(array('person' => 'edit', 'channel' => true))), 'the highest wins');
	pg_assert_same('view', ws_wtable_access_level(ws_wtables_access_test_facts(array('person' => 'owner', 'channel' => true))), 'a stored level that is no share level gives nothing');
	pg_assert_same('', ws_wtable_access_level(ws_wtables_access_test_facts(array('person' => 'run'))));
	pg_assert_same('', ws_wtable_access_level(ws_wtables_access_test_facts(array('person' => 'edit', 'member' => false))), 'a site member, shared or not');
}

// The owner deleted or excluded: the table is invisible to its shares too.
function test_workspace_wtables_access_owner_gone()
{
	pg_assert_same('', ws_wtable_access_level(ws_wtables_access_test_facts(array('present' => false, 'person' => 'edit'))));
	pg_assert_same('', ws_wtable_access_level(ws_wtables_access_test_facts(array('present' => false, 'channel' => true))));
}

function test_workspace_wtables_access_share_gives()
{
	pg_assert_same('view', ws_wtable_share_gives('user', 'view'));
	pg_assert_same('edit', ws_wtable_share_gives('user', 'edit'));
	pg_assert_same('view', ws_wtable_share_gives('channel', 'edit'), 'a channel views whatever is stored');
	pg_assert_same('', ws_wtable_share_gives('channel', 'run'));
	pg_assert_same('', ws_wtable_share_gives('group', 'view'));
}

function test_workspace_wtables_access_may()
{
	pg_assert_true(ws_wtable_may('view', 'read'));
	pg_assert_false(ws_wtable_may('view', 'write'));
	pg_assert_true(ws_wtable_may('edit', 'write'), 'a person shared at edit changes columns and rows');
	pg_assert_false(ws_wtable_may('edit', 'manage'), 'but neither deletes the table nor shares it');
	pg_assert_true(ws_wtable_may('owner', 'manage'));
	pg_assert_false(ws_wtable_may('', 'read'));
	pg_assert_false(ws_wtable_may('owner', 'other'));
	pg_assert_same(0, ws_wtable_access_rank('admin'));
}

// The reader: no role in it, the owner's presence asked through the
// workspace rights (deleted, excluded or without the right), and the
// shares not read for a table whose owner is gone.
function test_workspace_wtables_access_reader()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/wtables.php');

	preg_match('/^function ws_wtable_access\(\$viewer, \$table\)\n\{\n(.*?)\n\}\n/ms', $source, $match);
	$body = isset($match[1]) ? $match[1] : '';

	pg_assert_true($body !== '', 'the reader is found');
	pg_assert_true(strpos($body, "['role']") === false, 'no role gives access');
	pg_assert_contains("empty(\$viewer['member'])", $body);
	pg_assert_contains('ws_wtable_owner_present(', $body);
	pg_assert_contains('(!$owner && $present) ? ws_wtable_share_reach(', $body);

	preg_match('/^function ws_wtable_owner_present\(\$user_id\)\n\{\n(.*?)\n\}\n/ms', $source, $match);
	pg_assert_contains("ws_rights_for_id(\$user_id)['member']", isset($match[1]) ? $match[1] : '', 'excluded and deleted owners are no members');

	preg_match('/^function ws_wtable_delete_check\(\$viewer, \$table\)\n\{\n(.*?)\n\}\n/ms', $source, $match);
	pg_assert_contains("ws_wtable_may(\$access, 'manage')", isset($match[1]) ? $match[1] : '', 'only the owner deletes');
}
