<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings > Assistants and agents, the long lists (MCP servers,
 * Workspace agents, Canvas templates): at most five rows in the pane, the
 * ones that need something first, then "Show all N" opening the part's
 * drawer with every row; one cut shared by the three lists.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_SETTINGS_ENTRY')) {
	define('PG_SETTINGS_ENTRY', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/settings/drawer.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/assistants_fold.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/canvas_templates_card.php');

function settings_assistants_list_cut_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function test_settings_assistants_list_cut_five_and_fewer()
{
	pg_assert_same(array(), ws_assistants_list_cut(array()), 'an empty list');
	pg_assert_same(array(0, 1, 2, 3, 4), ws_assistants_list_cut(array(false, false, false, true, false)), 'five rows stand in their order, an asking one too');
	pg_assert_same('', ws_assistants_show_all_html('ws-mcp', 5), 'a list of five shows no button');
}

function test_settings_assistants_list_cut_six()
{
	pg_assert_same(array(0, 1, 2, 3, 4), ws_assistants_list_cut(array_fill(0, 6, false)), 'a list of six shows five rows');

	$button = ws_assistants_show_all_html('ws-mcp', 6);

	pg_assert_contains(h(lang(array('string' => 'Show all {var:1}', 'vars' => array(6)))), $button, '"Show all 6"');
	pg_assert_contains('data-bs-toggle="drawer" data-bs-target="#pgset-ws-mcp-all"', $button, 'it opens the part\'s drawer');
	pg_assert_contains('pg-button pg-button-quiet pg-button-sm', $button, 'a quiet small button');
}

function test_settings_assistants_list_cut_asking_rows_first()
{
	$asks = array_fill(0, 9, false);
	$asks[7] = true;

	pg_assert_same(array(7, 0, 1, 2, 3), ws_assistants_list_cut($asks), 'a row with a rose pill at place eight is shown, first');

	$keyed = array(11 => false, 12 => true, 13 => false, 14 => true, 15 => false, 16 => false, 17 => true);
	pg_assert_same(array(12, 14, 17, 11, 13), ws_assistants_list_cut($keyed), 'keys kept, asking rows in their order, then the rest');

	pg_assert_true(ws_assistants_list_asks('pg-tone-rose'), 'rose asks');
	pg_assert_true(ws_assistants_list_asks('pg-tone-amber'), 'amber asks');
	pg_assert_false(ws_assistants_list_asks('pg-tone-gray'), 'gray does not');
	pg_assert_false(ws_assistants_list_asks('pg-tone-teal'), 'teal does not');
}

function test_settings_assistants_list_cut_drawer()
{
	$row = '<button type="button" data-bs-toggle="drawer" data-bs-target="#pgset-ws-mcp-3">x</button>';

	$dialog = ws_settings_assistants_all_drawer('ws-mcp', $row, true);
	pg_assert_contains('id="pgset-ws-mcp-all"', $dialog, 'the drawer\'s id');
	pg_assert_contains(h(lang('MCP servers')), $dialog, 'titled by the part');
	pg_assert_contains('data-pgdrawer="open"', $dialog, 'a row\'s drawer opens over it in the dialog');
	pg_assert_false(strpos($dialog, 'data-bs-toggle="drawer"') !== false, 'never Bootstrap\'s data-api there');

	pg_assert_contains('data-bs-toggle="drawer"', ws_settings_assistants_all_drawer('ws-agents', $row, false), 'the page keeps the data-api');
	pg_assert_contains('id="pgset-ws-canvas-templates-all"', ws_settings_assistants_all_drawer('ws-canvas-templates', '', true), 'the templates\' drawer');
}

function test_settings_assistants_list_cut_canvas_rows()
{
	$row = array('id' => 4, 'name' => 'Sale', 'summary' => array('width' => 1080, 'height' => 1080, 'layers' => array(array('name' => 'title', 'kind' => 'text'))));

	$pane = ws_canvas_templates_settings_row_html($row, true);
	$copy = ws_canvas_templates_settings_row_html($row, true, true, 'pg_settings_modal_form');

	foreach (array($pane, $copy) as $html) {
		pg_assert_contains('name="ws_canvas_template[]" value="4"', $html, 'the posted name and value do not change');
		pg_assert_contains('data-ws-canvas-template="4"', $html, 'the twins find each other');
		pg_assert_contains('class="pg-switch"', $html, 'look A\'s switch');
	}

	pg_assert_contains('id="ws_canvas_template_4"', $pane, 'the pane\'s id');
	pg_assert_false(strpos($pane, 'form="') !== false, 'the pane posts inside the form');
	pg_assert_contains('id="ws_canvas_template_all_4"', $copy, 'the copy has an id of its own');
	pg_assert_contains('form="pg_settings_modal_form"', $copy, 'the copy joins the settings form');
	pg_assert_contains('[data-ws-canvas-template', ws_canvas_templates_settings_script(), 'the script keeps the twins alike');
}

function test_settings_assistants_list_cut_used_by_the_three_lists()
{
	$mcp = settings_assistants_list_cut_test_source('includes/workspace/mcp.php');
	$agents = settings_assistants_list_cut_test_source('includes/workspace/agents_settings.php');
	$canvas = settings_assistants_list_cut_test_source('includes/workspace/canvas_templates_card.php');
	$pane = settings_assistants_list_cut_test_source('includes/settings/workspace_assistants.php');

	foreach (array('MCP servers' => array($mcp, 'ws-mcp'), 'agents' => array($agents, 'ws-agents'), 'canvas templates' => array($canvas, 'ws-canvas-templates')) as $name => $part) {
		pg_assert_contains('ws_assistants_list_cut(', $part[0], $name . ' are cut by the one function');
		pg_assert_contains("ws_assistants_show_all_html('" . $part[1] . "'", $part[0], $name . ' show "Show all N"');
		pg_assert_contains("ws_settings_assistants_all_drawer('" . $part[1] . "'", $pane, $name . ' have their drawer');
	}

	// The drawer's copies never repeat the pane rows' ids.
	pg_assert_contains('ws_agents_settings_row_html($agent, $requests, $now, $error, false)', $agents, 'an agent\'s copy has no id');
	pg_assert_contains('$copies[$id] = \'', $mcp, 'a server\'s copy is drawn apart');
	pg_assert_contains('$row . $inner;', $mcp, 'without the row\'s id');
}
