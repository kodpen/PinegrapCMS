<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for the Workspace template screen's keyboard reorder (the
 * arrow keys on a task or note grip move the row, keep the focus on its grip
 * and say the new position) and for the selection format bar, whose tools
 * act on click so a press can be cancelled and a click-only activation works.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function workspace_template_reorder_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

function workspace_template_reorder_draggable()
{
	$js = workspace_template_reorder_source('assets/js/workspace_templates.js');

	if (!preg_match('/function draggable\(.*?\n        \}\n/s', $js, $match)) {
		return '';
	}

	return $match[0];
}

// Task and note rows moved only by an HTML5 drag; the grip button ignored
// every key.
function test_workspace_template_grip_moves_with_arrow_keys()
{
	$fn = workspace_template_reorder_draggable();

	pg_assert_true($fn !== '', 'draggable() found');
	pg_assert_contains("handle.addEventListener('keydown'", $fn);
	pg_assert_contains("'ArrowUp'", $fn);
	pg_assert_contains("'ArrowDown'", $fn);
	pg_assert_true(
		(bool) preg_match("/'keydown'.*?collect\(\).*?items\.splice\(.*?redraw\(\).*?\.focus\(\).*?pgAnnounce\(/s", $fn),
		'the key keeps typed values, moves the item, redraws, focuses the grip again and announces');
	pg_assert_contains("typeof window.pgAnnounce === 'function'", $fn);
	pg_assert_contains("t('tpl_position')", $fn);
	pg_assert_contains("'aria-describedby'", $fn);
}

// The position text uses named placeholders, which t() does not replace.
function test_workspace_template_reorder_strings()
{
	$php = workspace_template_reorder_source('includes/workspace/templates.php');

	pg_assert_contains("'tpl_position'        => lang('Position {position} of {total}')", $php);
	pg_assert_contains("'tpl_reorder_hint'    => lang('Use the arrow keys to change the order.')", $php);

	$fn = workspace_template_reorder_draggable();
	pg_assert_contains("split('{position}')", $fn);
	pg_assert_contains("split('{total}')", $fn);
}

// The bar over a selection applied its mark on mousedown and had no click
// listener, so a screen reader's activate command did nothing.
function test_workspace_format_bar_acts_on_click()
{
	$js = workspace_template_reorder_source('assets/js/workspace_editor.js');

	pg_assert_true(
		(bool) preg_match("/var tool = button\('ws-format-tool'.*?bar\.appendChild\(tool\);/s", $js, $match),
		'format bar tool loop found');
	$loop = $match[0];

	pg_assert_true(
		(bool) preg_match("/addEventListener\('mousedown', function \(event\) \{\s*event\.preventDefault\(\);\s*\}\);/", $loop),
		'mousedown only keeps the selection');
	pg_assert_true(
		(bool) preg_match("/addEventListener\('click'.*?openLink\(surface, null\).*?formatSurface\(surface, entry\[0\]\)/s", $loop),
		'the mark and the link tip run on click');
}
