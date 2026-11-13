<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the main menu's Automation flows row: its slot and place, its
 * gate (the Flows screen's), the screen it lights, the flows running now
 * beside it (the quiet count), the name on the row and the screen, and the
 * icon with its motion.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/source_reader.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

// A product file with Windows line ends read as Unix ones.
function automation_flows_menu_test_file($file)
{
	return str_replace("\r\n", "\n", (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file));
}

// The source of a PHP function of a product file, up to its closing brace.
function automation_flows_menu_test_php($file, $name)
{
	$source = automation_flows_menu_test_file($file);
	$start = strpos($source, "\nfunction " . $name . '(');
	$end = ($start !== false) ? strpos($source, "\n}\n", $start) : false;

	return (($start !== false) && ($end !== false)) ? substr($source, $start, $end - $start) : '';
}

// Is the text at $offset of a stylesheet (comments taken out) inside a
// prefers-reduced-motion: no-preference block?
function automation_flows_menu_test_in_motion_block($css, $offset)
{
	$stack = array();
	$head = 0;

	for ($i = 0; $i < $offset; $i++) {
		$char = $css[$i];

		if ($char === '{') {
			$stack[] = substr($css, $head, $i - $head);
			$head = $i + 1;
		} elseif ($char === '}') {
			array_pop($stack);
			$head = $i + 1;
		} elseif ($char === ';') {
			$head = $i + 1;
		}
	}

	foreach ($stack as $header) {
		if (strpos($header, 'prefers-reduced-motion: no-preference') !== false) {
			return true;
		}
	}

	return false;
}

// Whether a lang() source has its Turkish in a shard.
function automation_flows_menu_test_translated($key)
{
	foreach (glob(PG_FUNCTIONS_DIR . '/includes/local/tr/*.json') as $file) {
		$shard = json_decode((string) file_get_contents($file), true);

		if (is_array($shard) && isset($shard[$key]) && ($shard[$key] !== '')) {
			return true;
		}
	}

	return false;
}

function test_automation_flows_menu_row_has_its_own_slot_at_the_top()
{
	$categories = pg_menu_categories();

	pg_assert_same(array(0, 26, 23, 24), $categories['']['slots'], 'between the Dashboard and the Workspace, under no heading');

	$layout = pg_menu_layout(array(25, 23, 0, 26, 2), $categories);

	pg_assert_same('', $layout[0]['key'], 'the untitled block first');
	pg_assert_same(array(0, 26, 23), $layout[0]['slots'], 'in the table\'s order whatever the build order');

	pg_assert_same(array(array('key' => '', 'label' => '', 'slots' => array(0, 26))), array_slice(pg_menu_layout(array(0, 26), $categories), 0, 1), 'a site whose Workspace is off: the Dashboard and the flows');

	foreach (array(3, 13, 16, 17, 18, 19, 20, 22, 23, 24, 25) as $taken) {
		pg_assert_false($taken === 26, 'slot ' . $taken . ' is not the row\'s');
	}
}

function test_automation_flows_menu_row_is_built_under_the_screen_gate()
{
	$menu = pg_test_function_source('output_menu');

	$asked = strpos($menu, '$menu_flows = pg_flows_menu_row($user);');
	$workspace = strpos($menu, '// WORKSPACE. Slot 23');

	pg_assert_true(($asked !== false) && ($workspace !== false) && ($asked < $workspace), 'asked once, before the Workspace row');
	pg_assert_contains("if (\$menu_flows !== null) {\n        \$menu_items[26]['id'] = 26;", $menu, 'built only for the people the gate lets in');
	pg_assert_contains("\$menu_items[26]['href'] = 'workspace_flows.php';", $menu, 'the Flows screen');
	pg_assert_contains("\$menu_items[26]['menu_icon'] = 'flows';", $menu, 'icon A');
	pg_assert_contains("\$menu_items[26]['color_class'] = 'workspace-color';", $menu, 'the module colour flows were drawn in');
	pg_assert_contains("\$menu_items[26]['title'] = 'Automation flows';", $menu, 'the name');
	pg_assert_contains("\$menu_items[26]['live'] = \$menu_flows['running'];", $menu, 'the flows running now');
	pg_assert_contains("\$menu_items[26]['row_attributes'] = ' data-pg-flows-running';", $menu, 'the mark the slow movement reads');
	pg_assert_false(strpos($menu, "\$menu_items[26]['panel']") !== false, 'one screen, no panel');
	pg_assert_false(strpos($menu, "\$menu_items[26]['badge']") !== false, 'no red pill');

	// The gate is the screen's: the Workspace's tables, flows on the site,
	// a member of the team; the switch is not asked.
	$row = automation_flows_menu_test_php('includes/fn/automation_flows.php', 'pg_flows_menu_row');
	$gate = automation_flows_menu_test_php('includes/fn/automation_flows.php', 'pg_flows_user_may');
	$screen = automation_flows_menu_test_php('includes/workspace/screen.php', 'ws_screen_gate');

	pg_assert_contains('pg_flows_enabled()', $gate, 'flows on the site');
	pg_assert_contains("ws_ready() && !empty(ws_rights(\$user)['member'])", $gate, 'the Workspace\'s tables and a member');
	pg_assert_contains('if (!pg_flows_user_may($user)) {', $row, 'the row asks the gate');
	pg_assert_contains("USER_MANAGE_WORKSPACE", $gate, 'role 3 with the Workspace right only');
	pg_assert_false(strpos($gate, 'WORKSPACE_ENABLED') !== false, 'the switch is not asked');
	pg_assert_false((bool) preg_match('/(?<![a-z_])ws_enabled\(/', $gate), 'nor the module\'s gate');
	pg_assert_contains('ws_ready()', $screen, 'the screen asks the tables');
	pg_assert_contains('pg_flows_enabled()', $screen, 'and flows on the site');
	pg_assert_contains("\$viewer['member']", $screen, 'and a member');

	// The whole module only while a run is open; the gate needs one file.
	$open = strpos($row, 'if (pg_flows_runs_open()) {');
	$module = strpos($row, "require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');");

	pg_assert_contains("require_once(PG_FUNCTIONS_DIR . '/includes/workspace/access.php');", $gate, 'the access file for the gate');
	pg_assert_false(strpos($gate, 'bootstrap.php') !== false, 'not the whole module');
	pg_assert_true(($open !== false) && ($module !== false) && ($open < $module), 'the module behind an open run');
	pg_assert_contains('ws_flows_running_count(ws_viewer($user))', $row, 'counted as the screen\'s viewer');
	pg_assert_same(null, pg_flows_menu_row('nobody'), 'no user, no row');
}

function test_automation_flows_menu_row_lights_its_screen()
{
	$menu = pg_test_function_source('output_menu');

	pg_assert_contains("case 'workspace_flows.php':\n            // Its own row; the Workspace's on a site whose database has no\n            // flows yet, where the screen opens with the Workspace alone.\n            \$active_menu = isset(\$menu_items[26]) ? 26 : 23;", $menu, 'its own row, the Workspace\'s without it');
	pg_assert_same(1, substr_count($menu, "case 'workspace_flows.php':"), 'one case');

	// The Workspace list leaves the screen to the row: not opened by itself
	// there, and the menu is no navigator on it.
	pg_assert_contains("if ((\$ws_side_screen === 'flows') && (\$menu_flows !== null)) {\n                \$ws_side_screen = '';", $menu, 'the list stays shut on the Flows screen');
	pg_assert_same('flows', ws_side_screen('/pinegrap/workspace_flows.php'), 'the list still knows the screen');

	$nav = automation_flows_menu_test_file('includes/workspace/nav.php');

	pg_assert_contains("array('key' => 'flows', 'label' => lang('Automation flows'), 'short' => lang('Flows'), 'icon' => 'bi-diagram-3', 'url' => \$base . 'workspace_flows.php')", $nav, 'the list keeps its link to the same screen, by its name');
}

function test_automation_flows_menu_names_the_screen()
{
	$page = automation_flows_menu_test_file('workspace_flows.php');

	pg_assert_contains("'title' => lang('Automation flows'),", $page, 'the page title');
	pg_assert_contains("'heading' => lang('Automation flows'),", $page, 'the heading');
	pg_assert_false(strpos($page, "lang('Flows')") !== false, 'no old name on the screen');
	pg_assert_contains("'fl_nav'              => lang('Automation flows'),", automation_flows_menu_test_php('includes/workspace/flows.php', 'ws_flow_js_strings'), 'the list\'s own label');

	foreach (array('Automation flows', '{var:1} flow(s) running now', 'Flows') as $key) {
		pg_assert_true(automation_flows_menu_test_translated($key), $key . ' is translated');
	}
}

function test_menu_live_count_draws_nothing_without_a_count()
{
	foreach (array(0, -2, '0', null, 'abc', array(3)) as $count) {
		pg_assert_same('', pg_menu_live_count($count, 'Running'), 'nothing for ' . json_encode($count));
	}
}

function test_menu_live_count_draws_the_count_quietly()
{
	pg_assert_same('<span class="pg-menu-live"><span class="pg-menu-live-n">2</span></span>', pg_menu_live_count(2), 'a count without words');
	pg_assert_same('<span class="pg-menu-live" title="3 flows &quot;now&quot; &amp; more"><span class="pg-menu-live-n" aria-hidden="true">3</span><span class="visually-hidden">3 flows &quot;now&quot; &amp; more</span></span>', pg_menu_live_count('3', '3 flows "now" & more'), 'the words escaped, for the hover and a screen reader');
	pg_assert_contains('>99+<', pg_menu_live_count(100, 'x'), 'past 99');
	pg_assert_false(strpos(pg_menu_live_count(5, 'x'), 'theme-danger') !== false, 'never the red pill');
}

function test_automation_flows_running_count_reads_open_runs_only()
{
	$count = automation_flows_menu_test_php('includes/workspace/flow_runs.php', 'ws_flows_running_count');

	pg_assert_contains("SELECT id, flow_id, started_by FROM ws_flow_runs\n        WHERE status IN ('running', 'waiting')", $count, 'open runs, without their context');
	pg_assert_contains('LIMIT " . WS_FLOW_LIST_SCAN', $count, 'a bounded read');
	pg_assert_contains("AND status <> 'binned'", $count, 'no deleted flow');
	pg_assert_contains('ws_flow_share_rows_prime(', $count, 'the shares read once');
	pg_assert_contains("if (\$access === 'edit') {\n            \$count++;", $count, 'an editor reads every run of the flow');
	pg_assert_contains("ws_flow_run_access(\$viewer, \$open[\$flow_id], \$run) !== ''", $count, 'any other flow by a run they read');

	// The statuses the menu counts are the open ones.
	foreach (array_keys(ws_flow_run_statuses()) as $status) {
		pg_assert_same(ws_flow_run_open($status), strpos($count, "'" . $status . "'") !== false, $status);
	}

	pg_assert_contains("WHERE status IN ('running', 'waiting') LIMIT 1", automation_flows_menu_test_php('includes/fn/automation_flows.php', 'pg_flows_runs_open'), 'the menu\'s first read');
	pg_assert_same(0, ws_flows_running_count(array('id' => 1, 'role' => 0)), 'nobody outside the team');
}

function test_automation_flows_menu_icon_is_drawing_a()
{
	$svg = pg_menu_icon('flows', 'workspace-color');

	pg_assert_contains('class="pg-mi pg-mi-flows me-3 workspace-color"', $svg, 'its classes');
	pg_assert_contains('<circle class="pg-mi-2" fill="currentColor" fill-opacity=".3" stroke="none" cx="5.2" cy="6" r="2.7"/>', $svg, 'the start');
	pg_assert_contains('<g class="pg-mi-step"><rect class="pg-mi-2" fill="currentColor" fill-opacity=".3" stroke="none" x="9.2" y="9.2" width="5.6" height="5.6" rx="1.7"/>', $svg, 'the step');
	pg_assert_contains('<circle cx="18.8" cy="18" r="2.7"/>', $svg, 'the end');
	pg_assert_contains('<g class="pg-mi-a"><path d="M7.9 6H10a2 2 0 0 1 2 2v1.2M12 14.8V16a2 2 0 0 0 2 2h2.1"/>', $svg, 'the path in the second colour');
	pg_assert_contains('<circle class="pg-mi-dot" fill="currentColor" stroke="none" r="1.5" transform="translate(18.8 18)"/>', $svg, 'the dot, resting in the end');
	pg_assert_false(strpos($svg, '<animate') !== false, 'moved by the stylesheet, which a device asking for less motion stops');
}

function test_automation_flows_menu_icon_moves_along_its_path()
{
	$css = preg_replace('#/\*.*?\*/#s', '', automation_flows_menu_test_file('assets/css/backend.src.css'));

	pg_assert_contains('#menu .pg-mi-flows { --pg-mi-a: var(--pg-mi-sky); }', $css, 'the second colour');

	foreach (array(
		'#menu .list-group-item[data-pg-flows-running] .pg-mi-flows .pg-mi-dot {' => 'pg-mi-flow-round 4.8s linear infinite',
		'#menu .list-group-item:is(:hover, :focus-visible) .pg-mi-flows .pg-mi-dot {' => 'pg-mi-flow 0.9s linear;',
		'#menu .list-group-item:is(:hover, :focus-visible) .pg-mi-flows .pg-mi-step {' => 'pg-mi-bounce',
		'#menu .pg-menu-live::before {' . "\n    animation:" => 'pg-menu-live 1.8s',
	) as $rule => $animation) {
		$at = strpos($css, $rule);

		pg_assert_true($at !== false, $rule);
		pg_assert_true(automation_flows_menu_test_in_motion_block($css, (int) $at), $rule . ' moves only where more motion is welcome');
		pg_assert_contains($animation, substr($css, (int) $at, 160), $rule . ' plays ' . $animation);
	}

	pg_assert_false(automation_flows_menu_test_in_motion_block($css, strpos($css, '#menu .pg-mi-flows { --pg-mi-a')), 'the colour is not motion');

	// The trip's points lie on the dot's path at their share of its length:
	// 4.8 along, a quarter turn of radius 2, 8 down, a quarter turn, 4.8.
	$turn = M_PI;
	$length = 4.8 + $turn + 8 + $turn + 4.8;
	$points = array(
		array(0, 5.2, 6),
		array(4.8, 10, 6),
		array(4.8 + $turn / 2, 10 + M_SQRT2, 8 - M_SQRT2),
		array(4.8 + $turn, 12, 8),
		array(12.8 + $turn, 12, 16),
		array(12.8 + 1.5 * $turn, 14 - M_SQRT2, 16 + M_SQRT2),
		array(12.8 + 2 * $turn, 14, 18),
		array($length, 18.8, 18),
	);

	foreach (array('pg-mi-flow' => 1, 'pg-mi-flow-round' => 0.75) as $name => $share) {
		pg_assert_true((bool) preg_match('/@keyframes ' . preg_quote($name, '/') . ' \{(.*?)\n\}/s', $css, $block), $name);
		preg_match_all('/(from|to|[\d.]+%)(?:, to)? \{ transform: translate\(([\d.]+)px, ([\d.]+)px\); \}/', $block[1], $frames, PREG_SET_ORDER);
		pg_assert_same(count($points), count($frames), $name . ': one frame a point');

		foreach ($frames as $i => $frame) {
			$at = ($frame[1] === 'from') ? 0 : (($frame[1] === 'to') ? 100 : (float) $frame[1]);

			pg_assert_true(abs($at - 100 * $share * $points[$i][0] / $length) < 0.1, $name . ' frame ' . $i . ' at its share');
			pg_assert_true((abs((float) $frame[2] - $points[$i][1]) < 0.01) && (abs((float) $frame[3] - $points[$i][2]) < 0.01), $name . ' frame ' . $i . ' on the path');
		}
	}

	pg_assert_contains('75%, to { transform: translate(18.8px, 18px); }', $css, 'the slow trip rests in the end, where the dot sits at rest');
}
