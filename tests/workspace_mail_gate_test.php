<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for incoming e-mail on a site whose Workspace is off: the one gate
 * (ws_mail_on()) and every mail entry asking it instead of the Workspace's
 * switch: the tab, the panel actions the screen sends, the jobs' passes,
 * the frame, the external API's mail routes, the bell's mail lines and the
 * Mailboxes card's box kept in the mailbox itself.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api_mail_alone.php');
require_once(PG_FUNCTIONS_DIR . '/includes/notifications.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/modules.php');
require_once(dirname(__FILE__) . '/helpers/source_reader.php');

// A file of the software, with Unix line ends.
function workspace_mail_gate_test_file($file)
{
	return str_replace("\r\n", "\n", (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file));
}

// Without a database the mail tables are not there: no gate, nothing in use,
// and the Workspace is off in the test runner.
function test_workspace_mail_gate_without_the_tables()
{
	pg_assert_false(ws_mail_on(), 'no tables, no mail');
	pg_assert_false(ws_mail_in_use(), 'nothing in use');
	pg_assert_false(ws_mail_ready(), 'the module\'s own question is the gate');
	pg_assert_false(defined('WORKSPACE_ENABLED') && WORKSPACE_ENABLED, 'the Workspace is off in the test runner');
	pg_assert_contains('$ready = ws_mail_on();', pg_test_function_source('ws_mail_ready'), 'one definition of the mail tables');
	pg_assert_false(strpos(pg_test_function_source('ws_mail_on'), 'WORKSPACE_ENABLED') !== false, 'the gate never asks the switch');
}

// Every ws_mail_* action ws_handle_action() answers, and every action the
// screen's script sends, passes the switch with the Workspace off once mail
// is on, and only then.
function test_workspace_mail_gate_every_action()
{
	preg_match_all("/case '((ws_mail_|ws_whatsapp_reply)[a-z_]*)':/", workspace_mail_gate_test_file('includes/workspace/actions.php'), $cases);
	preg_match_all("/api\\('(ws_[a-z_]+)'/", workspace_mail_gate_test_file('assets/js/workspace_mail.js'), $sent);

	$actions = array_unique(array_merge($cases[1], $sent[1]));
	$listed = ws_mail_actions_without_workspace();

	pg_assert_true(count($cases[1]) >= 12, 'the mail actions are found');

	foreach ($actions as $action) {
		pg_assert_true(in_array($action, $listed, true), $action . ' is in ws_mail_actions_without_workspace()');
		pg_assert_true(ws_action_switch_allows($action, array(), false, false, true), $action . ' answers with the Workspace off');
		pg_assert_false(ws_action_switch_allows($action, array(), false, false, false), $action . ' waits for the mail tables');
	}

	pg_assert_true(ws_action_switch_allows('ws_bootstrap', array(), false, false, true), 'the team for the holder picker');
	pg_assert_false(ws_action_switch_allows('ws_channel_open', array(), false, false, true), 'a channel stays the Workspace\'s');
	pg_assert_false(ws_action_switch_allows('ws_flow_list', array(), false, false, true), 'the flows stay with their own gate');
	pg_assert_true(ws_action_switch_allows('ws_mail_pane', array(), true, false, false), 'Workspace on: any action');
	pg_assert_contains('ws_ready() && ws_mail_on()', pg_test_function_source('ws_handle_action'), 'the entry asks the gate');
}

// The screens, the door's target, the frame, the menu and the jobs ask the
// gate, never the Workspace's switch.
function test_workspace_mail_gate_entries()
{
	pg_assert_contains('ws_screen_gate($user, false, true)', workspace_mail_gate_test_file('messages_mail.php'), 'the tab opens with the Workspace off');
	pg_assert_contains("!(\$mail && ws_mail_on())", pg_test_function_source('ws_screen_gate'), 'the screen gate asks the mail gate');
	pg_assert_contains('ws_mail_on()', workspace_mail_gate_test_file('workspace_mail_frame.php'), 'the frame');
	pg_assert_false(strpos(workspace_mail_gate_test_file('workspace_mail_frame.php'), 'ws_enabled()') !== false, 'the frame never asks the switch');
	pg_assert_contains('ws_mail_on()', workspace_mail_gate_test_file('workspace_mail_job.php'), 'the IMAP job');
	pg_assert_false(strpos(workspace_mail_gate_test_file('workspace_mail_job.php'), 'ws_enabled()') !== false, 'the job never asks the switch');

	$menu = pg_test_function_source('pg_messages_mail_visible');

	pg_assert_contains('ws_mail_on()', $menu, 'the tab in the menu');
	pg_assert_false(strpos($menu, 'WORKSPACE_ENABLED') !== false, 'the menu never asks the switch');
	pg_assert_false(strpos($menu, 'ws_enabled()') !== false, 'nor the module\'s');

	$job = workspace_mail_gate_test_file('job.php');

	pg_assert_contains("    ws_mail_on()\n    && is_file(", $job, 'the general job\'s IMAP pass');
	pg_assert_contains('|| ws_mail_in_use()) {', $job, 'the orphans pass');

	$recurring = workspace_mail_gate_test_file('workspace_recurring_job.php');

	pg_assert_contains("ws_ready() && ws_mail_on() && function_exists('ws_mail_html_fill')", $recurring, 'the HTML fill');
	pg_assert_contains("ws_ready() && ws_mail_on() && function_exists('ws_mail_eml_sweep')", $recurring, 'the .eml sweep');

	// Each root file of mail asks the gate; the old address is a door to the tab.
	foreach (glob(PG_FUNCTIONS_DIR . '/{workspace_mail,messages_mail}*.php', GLOB_BRACE) as $path) {
		$name = basename($path);
		$source = workspace_mail_gate_test_file($name);

		if ($name === 'workspace_mail.php') {
			pg_assert_contains('pg_messages_mail_door(', $source, 'the old address leads to the tab');
			continue;
		}

		pg_assert_true((strpos($source, 'ws_mail_on()') !== false) || (strpos($source, 'ws_screen_gate($user, false, true)') !== false), $name . ' asks the mail gate');
	}
}

// The mail routes of the external API, merged alone with the Workspace off:
// every /workspace/mail… route, its objects and its event.
function test_workspace_mail_gate_api_routes()
{
	pg_assert_true(api_modules_mail_alone(false, true), 'Workspace off, mail on');
	pg_assert_false(api_modules_mail_alone(true, true), 'Workspace on: its own file carries mail');
	pg_assert_false(api_modules_mail_alone(false, false), 'no mail tables');

	$modules = api_modules();

	pg_assert_true(isset($modules['mail']), 'the mail module is listed');
	pg_assert_same('ws_mail_alone_', $modules['mail']['prefix'], 'its prefix');
	pg_assert_true(file_exists($modules['mail']['file']), 'its file is there');

	$picked = ws_mail_alone_api_routes();
	$ids = array();
	$named = array();

	foreach ($picked as $route) {
		$ids[] = $route['id'];
		pg_assert_true(strpos($route['path'], '/workspace/mail') === 0, $route['id'] . ' is under /workspace/mail…');
		pg_assert_true(function_exists($route['handler']), $route['id'] . ' has its handler');
		pg_assert_true(in_array($route['scope'], array('workspace:read', 'workspace:write', 'workspace:mail_send'), true), $route['id'] . ' asks a Workspace scope');

		$returns = is_array($route['returns']) ? $route['returns']['list'] : $route['returns'];
		$named[$returns] = true;
	}

	pg_assert_true(count($picked) >= 6, 'the mail routes');

	foreach (ws_api_routes() as $route) {
		if (strpos($route['path'], '/workspace/mail') === 0) {
			pg_assert_true(in_array($route['id'], $ids, true), $route['id'] . ' is picked');
		}
	}

	$objects = ws_mail_alone_openapi_objects();

	foreach (array_keys($named) as $name) {
		pg_assert_true(isset($objects[$name]), $name . ' is merged');
	}

	pg_assert_same(array('workspace.mail.received'), array_keys(ws_mail_alone_webhook_events()), 'its event');
	pg_assert_same(array('workspace', 'workspace_mail'), array_keys(ws_mail_alone_scope_groups()), 'the mail row and the row that sends in the person\'s name');
}

// The bell shows mail's lines with the Workspace off: every kind mail
// writes into the inbox is one of them.
function test_workspace_mail_gate_bell()
{
	pg_assert_same(array('mail_assigned', 'mail_customer', 'mail_unsent', 'mail_remind', 'mail_no_answer'), pg_notification_mail_kinds(), 'given to you, the customer wrote, not sent, back from a reminder, no answer yet');
	pg_assert_same(array(), pg_notification_off_kinds(false, false), 'neither flows nor mail');
	pg_assert_same(pg_notification_mail_kinds(), pg_notification_off_kinds(false, true), 'mail alone');
	pg_assert_same(array_merge(pg_notification_flow_kinds(), pg_notification_mail_kinds()), pg_notification_off_kinds(true, true), 'both');
	pg_assert_false(pg_notification_off_line(5, 9), 'no tables, no line');

	foreach (glob(PG_FUNCTIONS_DIR . '/includes/workspace/mail*.php') as $path) {
		preg_match_all("/ws_notify\\([^,;]*, *'([a-z_]+)'/", (string) file_get_contents($path), $kinds);

		foreach ($kinds[1] as $kind) {
			pg_assert_true(in_array($kind, pg_notification_mail_kinds(), true), basename($path) . ': ' . $kind . ' reaches the bell with the Workspace off');
		}
	}

	pg_assert_contains('$channel, ws_enabled())', pg_test_function_source('ws_mail_inbox_describe'), 'a notice leads to the tab with the Workspace off');
	pg_assert_same('/panel/workspace.php?channel=6&message=40', ws_mail_notice_url('/panel/', 6, 40, array('id' => 6, 'kind' => 'private'), true), 'Workspace on: the card');
}

// With the Workspace off a new box, or one kept in no channel, is kept in
// the mailbox itself: no Kept in choice, no channel, no routing and no
// suggestion under a card. A box kept in a channel keeps its choice.
function test_workspace_mail_gate_box_kept_in_the_mailbox()
{
	$channel_box = array('id' => 3, 'settings' => '{}');
	$room_box = array('id' => 4, 'settings' => '{"kept_in":"mailbox"}');

	pg_assert_true(ws_mail_box_rooms_only(null, false), 'a new box with the Workspace off');
	pg_assert_true(ws_mail_box_rooms_only($room_box, false), 'a box kept in no channel');
	pg_assert_false(ws_mail_box_rooms_only($channel_box, false), 'a box kept in a channel goes on');
	pg_assert_false(ws_mail_box_rooms_only(null, true), 'Workspace on: the choice');

	$html = ws_mail_room_fields_html('p_', null, '<option value="">x</option>', true, array(7 => 'Ada'), array(), true);

	pg_assert_contains('<input type="hidden" name="kept_in" value="mailbox">', $html, 'kept in the mailbox itself');
	pg_assert_false(strpos($html, 'name="channel_id"') !== false, 'no channel to pick');
	pg_assert_false(strpos($html, '<option value="channel"') !== false, 'no Kept in choice');
	pg_assert_contains('name="room_people[]" value="7"', $html, 'its people are chosen');

	$check = pg_test_function_source('ws_mail_box_save_check');

	pg_assert_contains("\$post['kept_in'] = 'mailbox';", $check, 'the save keeps it there whatever was posted');
	pg_assert_contains("'routes' => ws_mail_routes(\$box)", $check, 'and keeps the routes the form did not draw');
	pg_assert_contains('ws_enabled() && ws_mail_triage_due(', workspace_mail_gate_test_file('includes/workspace/mail.php'), 'no suggestion with the Workspace off');
}

// With the Workspace off the tab's heading line names the mailboxes, not
// channels, and the toolbar draws no My calendar, whose screen is the
// Workspace's.
function test_workspace_mail_gate_tab_without_channels()
{
	pg_assert_same('', ws_mail_list_my_calendar_html(false), 'no My calendar with the Workspace off');
	pg_assert_contains('href="workspace_my_calendar.php"', ws_mail_list_my_calendar_html(true), 'My calendar with it on');
	pg_assert_false(strpos(ws_mail_list_filters_html(ws_mail_list_filters(array()), array(), false, true, array(), false, false), 'workspace_my_calendar.php') !== false, 'the toolbar leaves it out');

	$page = workspace_mail_gate_test_file('messages_mail.php');

	pg_assert_contains("'heading_description' => ws_enabled()", $page, 'the heading line follows the switch');
	pg_assert_contains("lang('The e-mail conversations of the mailboxes you read.", $page, 'the mailboxes you read');
	pg_assert_contains("(bool) ws_mail_check_boxes(\$viewer, \$filters['box']), ws_enabled()", $page, 'the toolbar is told the switch');
}
