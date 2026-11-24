<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source test for what the Workspace's move to the panel look must keep:
 * a disabled icon button or segment shows faded, the command palette's
 * "Only in #channel" segment fits a phone, and a table board's Group by
 * row wraps.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function workspace_look_regressions_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

// The rule whose selector list ends with $selector, from its first selector
// line to its closing brace.
function workspace_look_regressions_rule($css, $selector)
{
	$at = strpos($css, $selector . ' {');

	if ($at === false) {
		return '';
	}

	$start = strrpos(substr($css, 0, (int) $at), '}');

	return substr($css, (int) $start, (int) strpos($css, '}', (int) $at) - (int) $start);
}

function test_workspace_look_disabled_icon_button_and_segment()
{
	$rule = workspace_look_regressions_rule(workspace_look_regressions_source('assets/css/backend.src.css'), '.pg-seg-item:has(> input:disabled)');

	pg_assert_true($rule !== '', 'the disabled rule is found');

	foreach (array('.pg-icon-button:disabled,', '.pg-icon-button.disabled,', '.pg-seg-item:disabled,', 'opacity: 0.5;', 'pointer-events: none;') as $part) {
		pg_assert_contains($part, $rule);
	}
}

function test_workspace_look_palette_scope_fits()
{
	$css = workspace_look_regressions_source('assets/css/backend.src.css');

	pg_assert_contains('min-width: 0;', workspace_look_regressions_rule($css, '.ws-palette-scope > .pg-seg-item'));
	pg_assert_contains('max-width: 100%;', workspace_look_regressions_rule($css, '.ws-palette-scope'));

	$script = workspace_look_regressions_source('assets/js/workspace_palette.js');

	pg_assert_contains("item.appendChild(el('span', 'text-truncate', option[1]));", $script);
	pg_assert_contains('item.title = option[1];', $script);
}

function test_workspace_look_board_group_by_wraps()
{
	pg_assert_contains('<div class="pg-seg pg-seg-wrap" role="group" aria-label="\' . h(lang(\'Group by\'))', workspace_look_regressions_source('workspace_wtables.php'));
}
