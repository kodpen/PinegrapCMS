<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that the Workspace honours the ERP's read-only right for an
 * accountant (user.manage_erp_readonly): the flag a reader carries
 * (ws_viewer(), and the viewer built for another person from their user
 * row, ws_change_viewer_from_row()), the refusal every kind of change that
 * writes ERP data meets (ws_change_erp_readonly_refusal(), asked by
 * ws_change_allowed() and by the writer itself), the reader a route that
 * only reads asks with (ws_change_reader()), and, read from source, every
 * Workspace path that writes ERP data: the record drawer, a proposal and
 * its Apply, a bulk change, a scheduled change, the time turned into an
 * invoice draft and the sales quote's copy link.
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
require_once(__DIR__ . '/helpers/source_reader.php');

// The code of a loaded function without its comments, so a check of what it
// calls is not misled by a comment that names a call.
function workspace_erp_readonly_test_code($function)
{
	$code = '';

	foreach (pg_test_source_tokens(pg_test_function_source($function)) as $token) {
		$code .= pg_test_source_text($token);
	}

	return $code;
}

// A reader built by ws_viewer(). Each one takes an id of its own: ws_rights()
// keeps its answer per id for the request.
function workspace_erp_readonly_test_viewer($role, array $rights)
{
	static $id = 970000;

	$id++;

	return ws_viewer(array('id' => $id, 'role' => $role) + $rights);
}

// A Workspace member who holds every right a kind of change asks for.
function workspace_erp_readonly_test_member($readonly)
{
	return array(
		'id' => 5, 'role' => 3, 'member' => true,
		'ecommerce' => true, 'contacts' => true, 'erp' => true, 'erp_cash' => true,
		'forms' => true, 'calendars' => true, 'users' => false,
		'erp_readonly' => $readonly,
	);
}

// The sentence the ERP screens refuse the read-only right with
// (validate_erp_access()).
function workspace_erp_readonly_test_sentence()
{
	return lang('Your account can read the ERP but not change anything in it.');
}

// The user role with the ERP right and the read-only right on it is read
// only, whichever shape the account came in (validate_user() booleans, a
// user row's 1 or '1', the API owner's raw column).
function test_workspace_erp_readonly_viewer_flag_for_the_user_role()
{
	foreach (array(true, 1, '1', 'yes') as $on) {
		$viewer = workspace_erp_readonly_test_viewer(3, array('manage_erp' => $on, 'manage_erp_readonly' => $on));
		pg_assert_true($viewer['erp_readonly'], 'the user role with both rights (' . var_export($on, true) . ')');
	}

	foreach (array(false, 0, '0', '', null) as $off) {
		$viewer = workspace_erp_readonly_test_viewer(3, array('manage_erp' => true, 'manage_erp_readonly' => $off));
		pg_assert_false($viewer['erp_readonly'], 'the ERP right alone writes (' . var_export($off, true) . ')');
	}

	pg_assert_false(workspace_erp_readonly_test_viewer(3, array('manage_erp' => true))['erp_readonly'], 'an account read before the column existed');
	pg_assert_false(workspace_erp_readonly_test_viewer(3, array('manage_erp' => false, 'manage_erp_readonly' => true))['erp_readonly'], 'the read-only right sits on the ERP right');
}

// Roles 0 to 2 hold the ERP by their role and are never read only, as
// USER_ERP_READONLY and validate_erp_access() decide.
function test_workspace_erp_readonly_viewer_flag_never_for_staff()
{
	foreach (array(0, 1, 2) as $role) {
		$viewer = workspace_erp_readonly_test_viewer($role, array('manage_erp' => true, 'manage_erp_readonly' => true));
		pg_assert_false($viewer['erp_readonly'], 'role ' . $role);
	}
}

// The viewer built for another person (a scheduled action's creator, the
// person a proposal waits for, whoever started a bulk change) reads the
// read-only right from their user row like the panel does.
function test_workspace_erp_readonly_viewer_for_another_person()
{
	$row = array(
		'user_id' => '41', 'user_role' => '3',
		'user_manage_ecommerce' => 'no', 'user_manage_contacts' => 'no',
		'manage_erp' => '1', 'manage_erp_cash' => '0', 'manage_erp_readonly' => '1',
		'user_manage_calendars' => 'no', 'user_manage_forms' => 'no',
	);

	$viewer = ws_change_viewer_from_row($row);
	pg_assert_same(41, $viewer['id'], 'the id');
	pg_assert_same(3, $viewer['role'], 'the role');
	pg_assert_true($viewer['erp_readonly'], 'read only');

	$writer = $row;
	$writer['user_id'] = '42';
	$writer['manage_erp_readonly'] = '0';
	pg_assert_false(ws_change_viewer_from_row($writer)['erp_readonly'], 'the ERP right alone writes');

	$before = $row;
	$before['user_id'] = '43';
	unset($before['manage_erp_readonly']);
	pg_assert_false(ws_change_viewer_from_row($before)['erp_readonly'], 'a table without the column');

	$staff = $row;
	$staff['user_id'] = '44';
	$staff['user_role'] = '2';
	pg_assert_false(ws_change_viewer_from_row($staff)['erp_readonly'], 'a manager is never read only');

	pg_assert_contains('ws_change_viewer_from_row($row)', workspace_erp_readonly_test_code('ws_change_viewer_for_id'), 'ws_change_viewer_for_id() builds the viewer from the row');
}

// Every kind of change whose right is an ERP right is a kind that writes ERP
// data, and the other way round: the writer refuses by the list, the rights
// check by the catalogue's right.
function test_workspace_erp_readonly_kinds_are_the_erp_right_kinds()
{
	$erp = array();

	foreach (pg_test_ws_change_types() as $kind => $type) {
		if (in_array((string) $type['right'], array('erp', 'erp_cash'), true)) {
			$erp[] = $kind;
		}
	}

	sort($erp);
	$listed = ws_change_erp_types();
	sort($listed);

	pg_assert_same($erp, $listed, 'ws_change_erp_types() lists the kinds of ws_change_types() asked with an ERP right');
	pg_assert_same('erp', pg_test_ws_change_types()['erp_account']['right'] ?? null, 'an ERP account is changed with the ERP right');
}

// The read-only right is refused every kind that writes ERP data, with the
// ERP screens' sentence; every other kind, and every person without it, is
// left to the other checks.
function test_workspace_erp_readonly_refusal()
{
	$readonly = workspace_erp_readonly_test_member(true);
	$writer = workspace_erp_readonly_test_member(false);

	foreach (ws_change_erp_types() as $kind) {
		pg_assert_same(workspace_erp_readonly_test_sentence(), ws_change_erp_readonly_refusal($readonly, $kind), $kind . ': refused to the read-only right');
		pg_assert_same('', ws_change_erp_readonly_refusal($writer, $kind), $kind . ': left to the other checks for a writer');
	}

	foreach (array_keys(pg_test_ws_change_types()) as $kind) {
		if (!in_array($kind, ws_change_erp_types(), true)) {
			pg_assert_same('', ws_change_erp_readonly_refusal($readonly, $kind), $kind . ': not an ERP kind');
		}
	}

	pg_assert_same('', ws_change_erp_readonly_refusal(array('id' => 1, 'role' => 3), 'erp_account'), 'a viewer built without the flag');
	pg_assert_same('', ws_change_erp_readonly_refusal(null, 'erp_account'), 'no viewer is refused elsewhere');
}

// ws_change_allowed() refuses the read-only right before it decides
// anything else, so no path that asks it (the drawer, a proposal, Apply,
// bulk, scheduled, the kinds the assistants are told about) offers or makes
// an ERP change.
function test_workspace_erp_readonly_change_allowed_refuses()
{
	$code = workspace_erp_readonly_test_code('ws_change_allowed');
	$asks = strpos($code, 'if (ws_change_erp_readonly_refusal($viewer, $type) !== \'\') {');
	$right = strpos($code, 'empty($viewer[$right])');
	$first_true = strpos($code, 'return true;');
	$channel = strpos($code, "if (\$type === 'channel')");

	pg_assert_true($asks !== false, 'ws_change_allowed() asks ws_change_erp_readonly_refusal()');
	pg_assert_true(($asks !== false) && ($right !== false) && ($right < $asks), 'after the kind\'s right');
	pg_assert_true(($asks !== false) && ($channel !== false) && ($asks < $channel), 'before the per-kind checks');
	pg_assert_true(($asks !== false) && ($first_true !== false) && ($asks < $first_true), 'before anything allows it');
	pg_assert_contains("return false;", substr($code, (int) $asks, 120), 'and refuses');
}

// The writer refuses as well, whoever called it: nothing reaches
// erp_account_save() for the read-only right.
function test_workspace_erp_readonly_writer_refuses()
{
	$done = ws_change_write(workspace_erp_readonly_test_member(true), 'erp_account', 'update', array('id' => 1, 'title' => 'Acme'), array('title' => 'Acme Ltd'));

	pg_assert_false($done['ok'], 'not written');
	pg_assert_same(workspace_erp_readonly_test_sentence(), $done['error'], 'the ERP screens\' sentence');

	$code = workspace_erp_readonly_test_code('ws_change_write');
	$guard = strpos($code, 'ws_change_erp_readonly_refusal($viewer, $type)');
	$write = strpos($code, "call_user_func('ws_change_write_' . \$type");

	pg_assert_true(($guard !== false) && ($write !== false) && ($guard < $write), 'the guard comes before the kind\'s writer');
}

// A person sees the ERP screens' sentence where the drawer or Apply refuses
// them for the read-only right.
function test_workspace_erp_readonly_person_refusals_say_why()
{
	$quick = workspace_erp_readonly_test_code('ws_quick_edit_check');
	pg_assert_contains('ws_change_allowed($viewer, $type, (int) $id)', $quick, 'the drawer asks ws_change_allowed()');
	pg_assert_contains('ws_change_erp_readonly_refusal($viewer, $type)', $quick, 'the drawer says why');

	$apply = workspace_erp_readonly_test_code('ws_change_apply_check');
	pg_assert_contains("ws_change_allowed(\$viewer, \$type, (int) \$change['record_id'])", $apply, 'Apply asks ws_change_allowed()');
	pg_assert_contains('ws_change_erp_readonly_refusal($viewer, $type)', $apply, 'Apply says why');
}

// Every other path that writes a record asks ws_change_allowed() with a
// viewer that carries the flag: a proposal (Claude's, Pinegrap AI's, an
// application's) for the person who would apply it, a bulk change for
// whoever started it, a scheduled change for its creator.
function test_workspace_erp_readonly_every_write_path_asks()
{
	$input = workspace_erp_readonly_test_code('ws_changes_input');
	pg_assert_contains('$asker = ws_change_viewer_for_id($asker_id);', $input, 'a proposal: the person it waits for');
	pg_assert_contains('ws_change_allowed($asker, $type, $record_id)', $input, 'a proposal asks ws_change_allowed()');

	pg_assert_contains('ws_change_allowed($asker, $type, 0)', workspace_erp_readonly_test_code('ws_bulk_input'), 'a bulk proposal asks ws_change_allowed()');

	$step = workspace_erp_readonly_test_code('ws_bulk_step');
	pg_assert_contains('ws_change_viewer_for_id(', $step, 'a bulk slice: whoever started it');
	pg_assert_contains('ws_change_allowed($viewer, $type, (int) $id)', $step, 'each record of a bulk slice asks ws_change_allowed()');

	$scheduled = workspace_erp_readonly_test_code('ws_scheduled_change');
	$typed = strpos($scheduled, 'ws_changes_input(');
	$written = strpos($scheduled, 'ws_change_write(');
	pg_assert_true(($typed !== false) && ($written !== false) && ($typed < $written), 'a scheduled change goes through ws_changes_input() before it is written');
	pg_assert_contains("\$viewer = ws_change_viewer_for_id(\$row['created_by']);", workspace_erp_readonly_test_code('ws_scheduled_execute'), 'a scheduled action runs as its creator');

	pg_assert_contains("ws_change_allowed(\$viewer, \$type, (int) \$change['record_id'])", workspace_erp_readonly_test_code('ws_change_apply_check'), 'Apply, from the panel and the API');
}

// The time of a channel turned into an ERP invoice draft is refused to the
// read-only right; the sales quote's copy link is not offered to it.
function test_workspace_erp_readonly_invoice_draft_and_quote_copy()
{
	$invoice = workspace_erp_readonly_test_code('ws_task_time_can_invoice');
	pg_assert_contains("!empty(\$user['manage_erp_readonly'])", $invoice, 'the invoice draft asks the read-only right');
	pg_assert_contains('ws_task_time_can_invoice(', workspace_erp_readonly_test_code('ws_task_time_invoice'), 'and the draft is made only past it');

	pg_assert_contains("(!empty(\$viewer['erp']) && empty(\$viewer['erp_readonly']))", workspace_erp_readonly_test_code('ws_screen_config'), 'the quote copy link');
}

// A route that only reads keeps reading: the accountant reads an ERP account
// with Claude as they do in the panel, while its change kinds leave it out.
function test_workspace_erp_readonly_reads_stay()
{
	$reader = ws_change_reader(workspace_erp_readonly_test_member(true));
	pg_assert_false($reader['erp_readonly'], 'the reader is not refused for the read-only right');
	pg_assert_true($reader['erp'], 'and keeps the ERP right it reads with');
	pg_assert_same(null, ws_change_reader(null), 'nobody stays nobody');

	pg_assert_contains('ws_change_allowed(ws_change_reader($asker), $type, $record_id)', workspace_erp_readonly_test_code('ws_api_claude_record'), 'Claude reads a record as a reader');
	pg_assert_contains("ws_change_allowed(\$asker, \$key, (\$key === 'channel') ? (int) \$row['channel_id'] : 0)", workspace_erp_readonly_test_code('ws_api_claude_change_types'), 'its change kinds ask as a writer');

	// Tags and summaries are drawn with the ERP right alone.
	$tags = ws_ref_types(workspace_erp_readonly_test_member(true));
	pg_assert_true(isset($tags['erp_account']), 'the read-only right sees the ERP account tag');
	pg_assert_true(isset($tags['erp_quote']), 'and the sales quote tag');
}

// The API answers the read-only refusal with 403 like the other rights'
// refusals, not 422: ws_api_change_refused() lists the sentence the writer
// refuses with among its forbidden ones.
function test_workspace_erp_readonly_api_refusal_is_forbidden()
{
	$sentence = lang('Your account can read the ERP but not change anything in it.');
	pg_assert_same($sentence, ws_change_erp_readonly_refusal(array('erp' => true, 'erp_readonly' => true), 'erp_account'), 'the sentence the writer refuses with');

	$code = workspace_erp_readonly_test_code('ws_api_change_refused');
	$listed = strpos($code, "lang('Your account can read the ERP but not change anything in it.')");
	$forbidden = strpos($code, "api_fail(403, 'forbidden', \$error)");
	pg_assert_true(($listed !== false) && ($forbidden !== false) && ($listed < $forbidden), 'the sentence is among the refusals answered with 403');
}
