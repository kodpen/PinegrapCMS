<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source tests for the Flows list table's layout (drawFlows() and flowRow()
 * in workspace_flow.js, the .wsf-flow-* rules in backend.src.css): the
 * table scrolls inside its own box instead of widening the page, and the
 * "How it starts" cell stays a table cell, its icon and sentence laid out
 * by a flex wrapper inside it, so row borders and the hover band span every
 * column at the row's height.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function workspace_flows_list_layout_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

// The table goes into a horizontally scrolling box, not straight into the list.
function test_workspace_flows_list_table_in_scroll_box()
{
	$js = workspace_flows_list_layout_source('assets/js/workspace_flow.js');
	pg_assert_same(1, preg_match("/el\('div', 'table-responsive wsf-flow-scroll'\)/", $js), 'the flow table has a scrolling wrapper');
	pg_assert_same(0, preg_match('/ui\.flows\.appendChild\(table\)/', $js), 'the flow table is not appended to the list directly');
}

// The start cell is never a flex box itself; its wrapper is.
function test_workspace_flows_list_start_cell_stays_table_cell()
{
	$css = workspace_flows_list_layout_source('assets/css/backend.src.css');
	pg_assert_same(0, preg_match('/(^|\n)\.wsf-flow-start\s*\{[^}]*display:\s*flex/', $css), 'td.wsf-flow-start is not display: flex');
	pg_assert_same(1, preg_match('/\.wsf-flow-start-in\s*\{[^}]*display:\s*flex/', $css), 'the wrapper inside the start cell is the flex box');

	$js = workspace_flows_list_layout_source('assets/js/workspace_flow.js');
	pg_assert_same(1, preg_match("/el\('div', 'wsf-flow-start-in'\)/", $js), 'flowRow() puts the icon and sentence in a wrapper');
}
