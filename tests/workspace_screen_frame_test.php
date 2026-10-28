<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source checks for the frame of the Workspace screens in backend.src.css:
 * the MCP connections and channel template screens put their columns
 * straight into .ws-shell-main, so they take the settings screen's padding
 * and gutter; sticky status columns stay below the fixed panel header; the
 * MCP servers card wraps on a phone.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function ws_frame_css_source()
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/assets/css/backend.src.css');
}

// The body of the first rule whose selector list ends with $selector.
function ws_frame_css_rule($source, $selector)
{
	if (preg_match('/' . preg_quote($selector, '/') . '\s*\{([^}]*)\}/', $source, $match) !== 1) {
		return '';
	}

	return $match[1];
}

// The rows of the MCP and template screens keep their gutter.
function test_ws_frame_gutter_rule_leaves_out_framed_screens()
{
	$source = ws_frame_css_source();
	$framed = 'body.workspace:not(.workspace_settings, .workspace_mcp, .workspace_template)';

	pg_assert_true(strpos($source, $framed . ' #content.p-0 > .row,') !== false, 'the gutter-0 rule on #content leaves out the framed screens');
	$rule = ws_frame_css_rule($source, $framed . ' .ws-shell-main > .row');
	pg_assert_true(strpos($rule, '--bs-gutter-x: 0;') !== false, 'the gutter-0 rule on .ws-shell-main leaves out the framed screens');
	pg_assert_true(strpos($source, 'body.workspace:not(.workspace_settings) .ws-shell-main > .row') === false, 'the old gutter-0 selector is gone');
}

// The MCP and template screens get the settings screen's padding and toolbar.
function test_ws_frame_mcp_and_template_take_settings_frame()
{
	$source = ws_frame_css_source();
	$screens = 'body.workspace:is(.workspace_settings, .workspace_mcp, .workspace_template)';

	$main = ws_frame_css_rule($source, $screens . ' .ws-shell-main');
	pg_assert_true(strpos($main, 'padding: 0 .75rem .75rem;') !== false, 'the framed screens pad .ws-shell-main');

	pg_assert_true(strpos($source, $screens . ' .ws-shell-main > #button_bar {' . "\n" . '  max-width: none;') !== false, 'the framed screens stretch the toolbar over the padding');
	pg_assert_true(preg_match('/' . preg_quote($screens . ' .ws-shell-main > #button_bar', '/') . '\s*\{\s*padding: \.5rem \.75rem;\s*border-bottom/', $source) === 1, 'the framed screens pad and underline the toolbar');
}

// Sticky columns stop below the 50px panel header, not under it.
function test_ws_frame_sticky_columns_below_header()
{
	$source = ws_frame_css_source();

	foreach (array('.ws-claude-status', '.ws-tl-side') as $selector) {
		$rule = ws_frame_css_rule($source, "\n" . $selector);
		pg_assert_true(strpos($rule, 'position: sticky;') !== false, $selector . ' is sticky');
		pg_assert_true(strpos($rule, 'top: calc(var(--pg-navbar-h) + 1rem);') !== false, $selector . ' sticks below the panel header');
	}
}

// The MCP servers heading and rows wrap; other .ws-dept-row lists do not.
function test_ws_frame_mcp_rows_wrap()
{
	$source = ws_frame_css_source();

	pg_assert_true(strpos(ws_frame_css_rule($source, '#ws-mcp > .card-header'), 'flex-wrap: wrap;') !== false, 'the MCP servers card header wraps');
	pg_assert_true(strpos(ws_frame_css_rule($source, '#ws-mcp > .card-body > .ws-dept-row'), 'flex-wrap: wrap;') !== false, 'an MCP server row wraps');
	pg_assert_true(strpos(ws_frame_css_rule($source, '#ws-mcp > .card-body > .ws-dept-row > .ws-grow'), 'flex-basis: 12rem;') !== false, 'an MCP server name column keeps 12rem before the buttons wrap');
	pg_assert_true(strpos(ws_frame_css_rule($source, "\n.ws-dept-row"), 'flex-wrap') === false, '.ws-dept-row itself still does not wrap');
}
