<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Workspace list of the main menu (includes/workspace/nav.php,
 * assets/js/workspace_side.js): which screen a file is, what each place is
 * gated by, the box the menu prints, the data printed with the page, the
 * words the script draws with, and where the list's data may be read.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The module files hold functions and constants only; loading them runs nothing.
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php');
require_once(PG_FUNCTIONS_DIR . '/includes/panel/actions.php');
require_once(__DIR__ . '/helpers/source_reader.php');

// Every flag of ws_nav_groups() on, for the tests that switch one off.
function workspace_side_test_flags($changes = array())
{
	return array_merge(array(
		'base'          => '/pg/',
		'notes'         => true,
		'calendar_feed' => true,
		'actions'       => true,
		'messages'      => true,
		'flows'         => true,
		'mcp'           => true,
	), $changes);
}

// The keys of one group of ws_nav_groups().
function workspace_side_test_keys($groups, $group)
{
	return array_map(function ($item) {
		return $item['key'];
	}, $groups[$group]);
}

function test_workspace_side_screen_names_the_workspace_screens()
{
	pg_assert_same('channels', ws_side_screen('/pinegrap/workspace.php'), 'the channel screen');
	pg_assert_same('tasks', ws_side_screen('/pinegrap/workspace_tasks.php'));
	pg_assert_same('notes', ws_side_screen('workspace_notes.php'));
	pg_assert_same('board', ws_side_screen('/a/b/workspace_board.php'));
	pg_assert_same('calendar', ws_side_screen('/pinegrap/workspace_calendar.php'));
	pg_assert_same('calendar_feed', ws_side_screen('/pinegrap/workspace_calendar_feed.php'));
	pg_assert_same('timeline', ws_side_screen('/pinegrap/workspace_timeline.php'));
	pg_assert_same('flows', ws_side_screen('/pinegrap/workspace_flows.php'));
	pg_assert_same('mcp', ws_side_screen('/pinegrap/workspace_mcp.php'));
	pg_assert_same('settings', ws_side_screen('/pinegrap/workspace_settings.php'));
	pg_assert_same('settings', ws_side_screen('/pinegrap/workspace_template.php'), 'a template is written from the settings');
	pg_assert_same('tasks', ws_side_screen('C:\\inetpub\\pinegrap\\workspace_tasks.php'), 'a Windows path');
}

function test_workspace_side_screen_is_empty_elsewhere()
{
	foreach (array('', '/pinegrap/toolbar.php', '/pinegrap/view_orders.php', '/pinegrap/workspace_notes_export.php',
		'/pinegrap/workspace_guest.php', '/pinegrap/workspace_approval.php', '/pinegrap/workspace_ical.php', '/pinegrap/api.php') as $file) {
		pg_assert_same('', ws_side_screen($file), $file);
	}
}

function test_workspace_side_nav_keeps_every_place_under_its_gate()
{
	$staff = ws_nav_groups(array('role' => 1, 'settings' => true), workspace_side_test_flags());

	pg_assert_same(array('home', 'inbox'), workspace_side_test_keys($staff, 'top'));
	pg_assert_same(array('tasks', 'notes', 'board', 'calendar', 'calendar_feed', 'flows', 'scheduled', 'mcp'), workspace_side_test_keys($staff, 'work'));
	pg_assert_same(array('timeline', 'archived', 'audit'), workspace_side_test_keys($staff, 'records'));
	pg_assert_same(array('settings'), workspace_side_test_keys($staff, 'foot'));
	pg_assert_same('/pg/workspace.php?view=home&inbox=1', $staff['top'][1]['url']);
	pg_assert_true(!empty($staff['top'][1]['inbox']), 'the inbox entry carries the count');

	// A member of the team without staff's role or the settings right.
	$member = ws_nav_groups(array('role' => 3, 'settings' => false), workspace_side_test_flags());

	pg_assert_same(array('timeline', 'archived'), workspace_side_test_keys($member, 'records'), 'the private channels audit is staff\'s');
	pg_assert_same(array(), $member['foot'], 'no settings without the right');

	$none = ws_nav_groups(array('role' => 3, 'settings' => false), workspace_side_test_flags(array(
		'notes' => false, 'calendar_feed' => false, 'actions' => false, 'messages' => false, 'flows' => false, 'mcp' => false,
	)));

	pg_assert_same(array('tasks', 'board', 'calendar'), workspace_side_test_keys($none, 'work'), 'only the screens every member has');
}

function test_workspace_side_nav_scheduled_is_one_entry()
{
	$actions = ws_nav_groups(array('role' => 1, 'settings' => true), workspace_side_test_flags());
	$labels = array();

	foreach ($actions['work'] as $item) {
		if ($item['key'] === 'scheduled') {
			$labels[] = $item['label'];
		}
	}

	pg_assert_same(array(lang('Scheduled actions')), $labels, 'actions first');

	// Staff whose actions Flows lists keep their scheduled messages here.
	$messages = ws_nav_groups(array('role' => 1, 'settings' => true), workspace_side_test_flags(array('actions' => false)));
	$labels = array();

	foreach ($messages['work'] as $item) {
		if ($item['key'] === 'scheduled') {
			$labels[] = $item['label'];
		}
	}

	pg_assert_same(array(lang('Scheduled messages')), $labels);
}

function test_workspace_side_nav_keeps_key_label_icon_url()
{
	$nav = ws_side_nav(array(
		'top'  => array(array('key' => 'home', 'label' => 'Overview', 'icon' => 'bi-house', 'url' => '/w.php', 'rail' => false, 'extra' => 1)),
		'work' => array(array('key' => 'inbox', 'label' => 'Inbox', 'icon' => 'bi-inbox', 'url' => '/i.php', 'inbox' => true)),
	));

	pg_assert_same(array('top', 'work', 'records', 'foot'), array_keys($nav), 'every group, in the order drawn');
	pg_assert_same(array('key' => 'home', 'label' => 'Overview', 'icon' => 'bi-house', 'url' => '/w.php', 'inbox' => false), $nav['top'][0]);
	pg_assert_true($nav['work'][0]['inbox'], 'the inbox flag');
	pg_assert_same(array(), $nav['foot'], 'an empty group stays a list');
}

function test_workspace_side_markup_is_an_empty_box_with_its_words()
{
	$html = ws_side_markup(array(
		'api'     => '/pinegrap/api.php',
		'loading' => 'Yükleniyor…',
		'error'   => 'Say "no" & <stop>',
		'retry'   => 'Try again',
	));

	pg_assert_contains('id="ws-menu-side"', $html, 'the id that brings the --ws-* tokens');
	pg_assert_contains('data-ws-side-api="/pinegrap/api.php"', $html);
	pg_assert_contains('data-ws-side-error="Say &quot;no&quot; &amp; &lt;stop&gt;"', $html, 'words escaped');
	pg_assert_contains('role="status"', $html, 'the grey rows say they wait');
	pg_assert_false(strpos($html, '<a') !== false, 'no link before the list is drawn');
	pg_assert_false(strpos($html, '<form') !== false, 'no form for Ctrl+S to find');
	pg_assert_false(strpos($html, 'data-ws-side-target') !== false, 'no target outside the toolbar');
	pg_assert_false(strpos($html, 'data-pg-menu-auto') !== false, 'opens by itself only when asked');

	$toolbar = ws_side_markup(array('api' => '/a.php', 'target' => '_parent', 'auto' => true));

	pg_assert_contains('data-ws-side-target="_parent"', $toolbar, 'the toolbar frame leaves for the whole window');
	pg_assert_contains('data-pg-menu-auto', $toolbar);
}

function test_workspace_side_markup_opens_a_panel_of_the_menu()
{
	$html = ws_side_markup(array('api' => '/a.php'));

	pg_assert_false(pg_menu_row_opens_panel($html, 'workspace.php'), 'no link in it to count');
	pg_assert_true(pg_menu_row_has_panel(array('panel' => true, 'href' => 'workspace.php'), $html), 'the row asks for its panel');
}

function test_workspace_side_inline_escapes_markup()
{
	$html = ws_side_inline(array('v' => 1, 'channels' => array(array('id' => 1, 'name' => '</script><img src=x onerror=alert(1)>'))));

	pg_assert_contains('<script type="application/json" id="ws-side-data">', $html);
	pg_assert_same(1, substr_count($html, '</script'), 'the only </script is the closing tag');
	pg_assert_false(strpos($html, '<img') !== false, 'no markup inside the data');

	$decoded = json_decode(substr($html, strlen('<script type="application/json" id="ws-side-data">'), -strlen('</script>')), true);

	pg_assert_same('</script><img src=x onerror=alert(1)>', $decoded['channels'][0]['name'], 'the name reads back as it was');
	pg_assert_same('', ws_side_inline(array()), 'nothing for an empty payload');
}

function test_workspace_side_payload_is_empty_for_anybody_outside_the_team()
{
	pg_assert_same(array(), ws_side_payload(array('id' => 5, 'role' => 3, 'member' => false), 'tasks'), 'a site member');
	pg_assert_same(array(), ws_side_payload(array('id' => 0), ''), 'nobody');
}

function test_workspace_side_strings_cover_the_renderer()
{
	$script = file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_side.js');

	preg_match_all("/\\bt\\('([a-z_]+)'/", $script, $used);

	$used = array_values(array_unique($used[1]));
	$keys = ws_side_js_keys();

	sort($used);

	foreach ($used as $key) {
		pg_assert_true(in_array($key, $keys, true), 'workspace_side.js draws ' . $key . ' with a word ws_side_js_keys() names');
	}

	$strings = ws_side_js_strings();

	foreach ($keys as $key) {
		pg_assert_true(isset($strings[$key]) && ($strings[$key] !== ''), $key . ' has its word in the screen\'s own set');
	}
}

// The list's data is read only where it is drawn at once: a page that
// never opens the panel pays for none of it, and the endpoint never marks
// the person as looking at the workspace.
function test_workspace_side_data_is_read_only_where_it_is_drawn()
{
	$menu = pg_test_function_source('output_menu');
	$slot = substr($menu, strpos($menu, '// WORKSPACE. Slot 23'));
	$slot = substr($slot, 0, strpos($slot, '// SITE SETTINGS.'));

	foreach (array('ws_channels_for(', 'ws_groups_for(', 'ws_threads_for(') as $call) {
		pg_assert_false(strpos($slot, $call) !== false, 'slot 23 does not call ' . $call . ' itself');
	}

	pg_assert_same(1, substr_count($slot, 'ws_side_payload('), 'one read of the list');
	pg_assert_contains("if (\$ws_side_screen !== '') {\n                \$menu_tail .= ws_side_inline(ws_side_payload(", $slot, 'and only on a workspace screen');

	$actions = pg_test_function_source('ws_handle_action');
	$case = substr($actions, strpos($actions, "case 'ws_side':"));
	$case = substr($case, 0, strpos($case, "case 'ws_channels':"));

	pg_assert_contains('Cache-Control: private, no-store', $case, 'the answer is not kept');
	pg_assert_false(strpos($case, 'ws_sync_seen') !== false, 'opening the list is not looking at the workspace');
	pg_assert_contains('ws_side_payload($viewer', $case);

	$row = pg_panel_action('ws_side');

	pg_assert_same('pg_panel_ws', $row['handler'], 'the workspace door, with its session and member checks');
	pg_assert_true($row['token'], 'the form token is checked');
}

// The channel screen draws the list again only when what ws_sync answers
// differs from the last answer; discussions with the same last message
// second came back in either order, and the list was drawn again on every
// poll (the open tour step lost its ring with it).
function test_workspace_side_discussions_come_in_one_order()
{
	pg_assert_contains('ORDER BY c.last_message_at DESC, c.id DESC', pg_test_function_source('ws_threads_for'), 'a tie keeps its order');
}
