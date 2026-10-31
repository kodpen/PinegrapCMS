<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pin for list quick edit (backend.src.js): Tab on the last row and
 * Shift+Tab on the first leave the focus on the edited cell, not on <body>.
 * A unit test cannot press keys in a list, so this pins the source.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function list_edit_focus_function($name)
{
	$source = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/assets/js/backend.src.js');
	$found = preg_match('/^( *)function ' . preg_quote($name, '/') . '\(state, direction, refocus\).*?\n\1\}\n|^( *)function ' . preg_quote($name, '/') . '\(cell, direction\).*?\n\2\}\n/ms', $source, $match);
	pg_assert_true((bool) $found, $name . '() not found');

	return $found ? $match[0] : '';
}

function test_list_edit_focus_step_says_whether_it_moved()
{
	$step = list_edit_focus_function('step');
	pg_assert_contains('return true;', $step);
	pg_assert_contains('return false;', $step);
}

function test_list_edit_focus_stays_on_the_cell_past_the_last_row()
{
	$commit = list_edit_focus_function('commit');
	pg_assert_contains("if (!step(state.cell, direction)) {\n                state.cell.focus();", $commit);
}
