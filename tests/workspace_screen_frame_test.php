<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source checks for the frame of the Workspace screens: every screen but the
 * channel one sits in .ws-shell-main, which pads it for all of them; sticky
 * status columns stay below the fixed panel header; the MCP servers card
 * wraps on a phone.
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

// Every screen in .ws-shell-main takes its padding from the wrapper itself,
// never from a list of screens: a screen left out of such a list ran its
// cards against the edge of the page (calendar subscription, tables).
function test_ws_frame_shell_main_pads_every_screen()
{
	$source = ws_frame_css_source();

	pg_assert_true(preg_match('/body\.workspace:(?:is|not)\(\.workspace_/', $source) !== 1, 'no frame rule names a list of workspace screens');

	$main = ws_frame_css_rule($source, "\nbody.workspace .ws-shell-main");
	pg_assert_true(strpos($main, 'padding: 0 .75rem .75rem;') !== false, '.ws-shell-main is padded on every workspace screen');

	pg_assert_true(strpos($source, '.ws-shell-main > .row {' . "\n" . '  --bs-gutter-x: 0;') === false, 'a row in .ws-shell-main keeps its gutter');
	pg_assert_true(preg_match('/(^|\n)[^\n{]*#ws-root\s*\{\s*padding/', $source) !== 1, '#ws-root adds no second padding');
}

// The toolbar runs over the padding, across the whole width.
function test_ws_frame_toolbar_spans_the_padding()
{
	$source = ws_frame_css_source();

	pg_assert_true(strpos($source, 'body.workspace .ws-shell-main #ws-root > .pg-toolbar,' . "\n" . 'body.workspace .ws-shell-main > #button_bar {' . "\n" . '  max-width: none;' . "\n" . '  margin-left: -.75rem;' . "\n" . '  margin-right: -.75rem;') !== false, 'both toolbars stretch over the padding');
	pg_assert_true(preg_match('/\nbody\.workspace \.ws-shell-main > #button_bar\s*\{\s*padding: \.5rem \.75rem;\s*border-bottom/', $source) === 1, 'the server-drawn toolbar is padded and underlined');
}

// Every Workspace screen but the channel one sits in the shared wrapper.
function test_ws_frame_screens_use_the_shell()
{
	$root = dirname(__DIR__) . '/pinegrap/';

	foreach (glob($root . 'workspace_*.php') as $file) {
		$source = (string) file_get_contents($file);

		if (strpos($source, '<main id="content"') === false) {
			continue;
		}

		$name = basename($file, '.php');
		pg_assert_true(preg_match("/'extra classes'\s*=>\s*'workspace " . preg_quote($name, '/') . "'/", $source) === 1, $name . ' carries the workspace body classes');
		pg_assert_true(preg_match('/<main id="content" class="container-fluid p-0">\s*<div class="ws-shell">\s*<div class="ws-shell-main">/', $source) === 1, $name . ' draws its columns in .ws-shell-main');
	}
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

// A small select keeps its longest option clear of its arrow (the panel's
// small fields are 0.5rem across; v6's three of them ended where the arrow
// began).
function test_ws_frame_small_select_keeps_its_arrow_clear()
{
	$rule = ws_frame_css_rule(ws_frame_css_source(), 'select.form-control.form-control-sm:not([multiple], [size]:not([size="1"]))');

	pg_assert_contains('--bs-control-select-bg-position: right .5rem center;', $rule);
	pg_assert_contains('padding-inline-end: 1.75rem;', $rule);
}
