<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for the Flows canvas toolbar and the channel template screen:
 * the status pill is filled with a Bootstrap 6 theme class, the open flow's
 * bar in its three parts (New flow on the list only, Canvas or List in the
 * middle, Publish the one filled button, Pause and Resume in the ⋯ menu), a
 * paused flow offers Resume (the panel action resumes the version it was
 * paused at), an
 * untouched blank canvas is not kept when it is left, the checklist and
 * table windows of a template text say where they add, a task title is
 * required, and an untitled task box's problem quotes its name.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function workspace_flows_bar_source($path)
{
	return str_replace("\r\n", "\n", (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path));
}

function test_workspace_flows_bar_status_pill_bootstrap_6()
{
	$source = workspace_flows_bar_source('assets/js/workspace_flow.js');

	pg_assert_true(strpos($source, 'text-bg-') === false, 'Bootstrap 5 text-bg-* left in the canvas');
	pg_assert_true(strpos($source, "'badge rounded-pill wsf-status theme-' + tone") !== false, 'the status pill has no theme fill');
}

function test_workspace_flows_bar_resume()
{
	$script = workspace_flows_bar_source('assets/js/workspace_flow.js');
	$actions = workspace_flows_bar_source('includes/workspace/actions.php');

	pg_assert_true((bool) preg_match("/\\(flow\\.status === 'paused'\\) && flow\\.manage\\) \\{.*?act\\('ws_flow_resume', resume, t\\('fl_resumed'\\)\\)/s", $script), 'a paused flow has no Resume');
	pg_assert_true(strpos($actions, "case 'ws_flow_resume':") !== false, 'no ws_flow_resume panel action');
	pg_assert_true(strpos($actions, '$result = ws_flow_resume($viewer, $flow);') !== false, 'the panel action does not resume');
	pg_assert_true(strpos(workspace_flows_bar_source('includes/panel/actions.php'), "'ws_flow_pause', 'ws_flow_resume',") !== false, 'ws_flow_resume not registered');
}

// The body of one function of the flow script, from its line to the first
// line that closes it at its own indent.
function workspace_flows_bar_function($source, $name)
{
	return preg_match('/\n    function ' . preg_quote($name, '/') . '\(.*?\n    \}\n/s', $source, $found) ? $found[0] : '';
}

function test_workspace_flows_bar_window_layout()
{
	$source = workspace_flows_bar_source('assets/js/workspace_flow.js');
	$bar = workspace_flows_bar_function($source, 'drawBar');
	$more = workspace_flows_bar_function($source, 'moreMenu');
	$view = workspace_flows_bar_function($source, 'viewSwitch');

	pg_assert_true($bar !== '' && $more !== '' && $view !== '', 'drawBar(), moreMenu() and viewSwitch() are found');

	// New flow is the list's: the open flow's bar never draws it.
	pg_assert_true(strpos($bar, 'newFlowButton()') === false, 'New flow is drawn in an open flow\'s bar');
	pg_assert_contains('newFlowButton();', workspace_flows_bar_function($source, 'clearScreen'), 'the flow list keeps New flow');

	// The flow at the left, the view in the middle, Publish the one filled
	// button, Run filled only without it.
	pg_assert_true(strpos($bar, 'statusPill(flow)') < strpos($bar, 'viewSwitch()') && strpos($bar, 'viewSwitch()') < strpos($bar, 'runsButton()'), 'the bar\'s three parts are out of order');
	pg_assert_contains('runButton(!publishing);', $bar, 'Run is filled beside Publish');
	pg_assert_contains("ui.publish = barButton(BAR_FILL, t('fl_publish'), 'bi-send');", $bar);
	pg_assert_contains("var BAR_FILL = 'pg-button pg-button-fill pg-button-sm';", $source, 'the look A recipe of the filled button');

	// Canvas or List says which is chosen.
	pg_assert_contains("var group = el('div', 'pg-seg md:mx-auto wsf-view');", $view);
	pg_assert_contains("choice.setAttribute('aria-pressed', (state.outline === item[0]) ? 'true' : 'false');", $view, 'the view switch says which is chosen');

	// Pause and Resume live in the ⋯ menu.
	pg_assert_true(strpos($bar, 'ws_flow_pause') === false && strpos($bar, 'ws_flow_resume') === false, 'Pause or Resume on the bar itself');
	pg_assert_contains("act('ws_flow_pause', pause, t('fl_paused'))", $more, 'Pause in the ⋯ menu');
	pg_assert_contains("act('ws_flow_resume', resume, t('fl_resumed'))", $more, 'Resume in the ⋯ menu');
}

function test_workspace_flows_bar_untouched_blank_dropped()
{
	$source = workspace_flows_bar_source('assets/js/workspace_flow.js');

	pg_assert_true(strpos($source, 'function dropUntouched(next) {') !== false, 'an untouched blank canvas is kept');
	pg_assert_true(strpos($source, "(flow.status !== 'draft') || (flow.version > 0) || (flow.name !== t('fl_untitled'))") !== false, 'only a never published, untitled draft is dropped');
	pg_assert_true(strpos($source, 'return leaveFlow(id).then(') !== false, 'reopening the same flow drops it');
	pg_assert_true(strpos($source, 'leaveFlow(into).then(') !== false, 'placing a template into the empty flow drops it');
}

function test_workspace_template_texts()
{
	$templates = workspace_flows_bar_source('assets/js/workspace_templates.js');
	$editor = workspace_flows_bar_source('assets/js/workspace_editor.js');

	pg_assert_same(2, substr_count($templates, "}, { insertLabel: t('insert_to_text') });"), 'the checklist and table windows of a template say Add to the message');
	pg_assert_true(strpos($editor, 'function openChecklist(markup, done, options) {') !== false, 'the checklist window takes no options');
	pg_assert_true(strpos($editor, "openChecklist('', done, { insertLabel: options.insertLabel });") !== false, 'a slash checklist loses its insert label');
	pg_assert_true(strpos($templates, 'title.required = true;') !== false, 'a task title is not required');
	pg_assert_true(strpos(workspace_flows_bar_source('includes/workspace/flows.php'), "'The task box “{var:1}” needs a title.'") !== false, 'the task box name is not quoted');
}

function test_workspace_template_save_bar_sticky()
{
	$source = workspace_flows_bar_source('workspace_template.php');

	pg_assert_true(strpos($source, '<div class="col-12 order-3 position-sticky" style="bottom:0; z-index:3;">') !== false, 'the save bar column is not sticky');
	pg_assert_true(strpos($source, 'text-center position-sticky mb-7') === false, 'the sticky box is the nav, which has no room to travel');
}
