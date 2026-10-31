<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that the installer wizard works from the keyboard and a screen
 * reader: Continue, Back and the step rail move focus to the new step and
 * announce it, the rail says which steps are done and which is current, a
 * refused step and the install run are announced, and focus is never left
 * on a control that the run hides.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function installer_wizard_a11y_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/install/index.php');
}

function test_installer_wizard_step_change_moves_focus()
{
	$php = installer_wizard_a11y_source();

	pg_assert_contains('function pg_install_set_step(step, move_focus) {', $php, 'the step change knows whether a person asked for it');
	pg_assert_contains('pg_install_set_step(parseInt($(this).attr("data-step"), 10), true);', $php, 'a rail button moves focus');
	pg_assert_contains('pg_install_set_step(pg_install_step + 1, true);', $php, 'Continue moves focus');
	pg_assert_contains('pg_install_set_step(pg_install_step - 1, true);', $php, 'Back moves focus');
	pg_assert_contains('pg_install_set_step(pg_install_step);', $php, 'switching between install and upgrade leaves focus alone');
	pg_assert_contains('var header = $(".pg-step-panel[data-panel=" + step + "] .card-header").first();', $php, 'the new step\'s header is the focus target');
	pg_assert_contains('header.attr("tabindex", "-1");', $php, 'the header can take focus');
}

function test_installer_wizard_step_is_announced()
{
	$php = installer_wizard_a11y_source();

	pg_assert_contains('<span class="visually-hidden" id="pg_step_status" role="status">', $php, 'the step line has a polite status');
	pg_assert_contains("lang('{var:1} of {var:2}')", $php, 'the status reads the existing step count key');
	pg_assert_contains('$("#pg_step_status").text(', $php, 'every step change fills the status');
}

function test_installer_wizard_rail_says_current_and_done()
{
	$php = installer_wizard_a11y_source();

	pg_assert_contains('<button type="button" class="pg-install-step" data-step="1" aria-current="step">', $php, 'the first rail button starts as the current step');
	pg_assert_contains('if (number === step) { $(this).attr("aria-current", "step"); } else { $(this).removeAttr("aria-current"); }', $php, 'aria-current sits on the focusable rail button');
	pg_assert_false(strpos($php, 'item.attr("aria-current"') !== false, 'the list item no longer carries aria-current');
	pg_assert_contains('.pg-install-step[aria-current="step"] {', $php, 'the current step look follows the button');
	pg_assert_contains("<span class=\"visually-hidden pg-step-done-text\" hidden>' . lang('Completed') . '</span>", $php, 'a finished step says Completed');
	pg_assert_contains('$(this).find(".pg-step-done-text").prop("hidden", number >= step);', $php, 'Completed shows only on finished steps');
}

function test_installer_wizard_problems_are_announced()
{
	$php = installer_wizard_a11y_source();

	pg_assert_contains('<div class=\"alert theme-danger small d-flex gap-3 pg-step-problem\" role=\"alert\">', $php, 'step problems are an alert');
	pg_assert_contains('$("#install_fields [aria-invalid]").removeAttr("aria-invalid");', $php, 'aria-invalid is cleared with .is-invalid');
	pg_assert_contains('.addClass("is-invalid").attr("aria-invalid", "true");', $php, 'aria-invalid is set with .is-invalid');
	pg_assert_false(strpos($php, '.addClass("is-invalid");') !== false, 'no field is marked invalid only visually');
	pg_assert_contains('var invalid = $(".pg-step-panel[data-panel=" + step + "] [aria-invalid]").first();', $php, 'the first invalid field of the refused step is found');
}

function test_installer_wizard_run_is_announced()
{
	$php = installer_wizard_a11y_source();

	pg_assert_contains('<div class="d-none" id="pg_run_area" tabindex="-1"', $php, 'the run area can take focus');
	pg_assert_contains('id="pg_run_now" role="status"', $php, 'what the run is doing is a polite status');
	pg_assert_contains('<div class="progress flex-grow-1" style="height:7px;" aria-hidden="true"><div class="progress-bar" id="pg_run_bar"', $php, 'the bar is hidden beside its percent');
	pg_assert_contains('function pg_install_focus_run() {', $php, 'one helper moves focus into the run');
	pg_assert_contains("\$(\"#pg_upgrade_actions\").addClass(\"d-none\");\n\t\t\tpg_install_focus_run();", $php, 'the upgrade start focuses the run area');
	pg_assert_contains("\$(\".pg-install-step\").prop(\"disabled\", true);\n\t\t\t\t}\n\t\t\t\tpg_install_focus_run();", $php, 'the install start focuses the run area');
	pg_assert_contains('<div id="pg_run_result" tabindex="-1"></div>', $php, 'the result can take focus');
	pg_assert_contains('function pg_install_focus_result() {', $php, 'one helper focuses the result');
	pg_assert_true(substr_count($php, 'pg_install_focus_result();') >= 5, 'every result put into the run area is focused');
	pg_assert_contains('id="pg_reinstall_message" role="alert"', $php, 'the reinstall confirmation is announced');
	pg_assert_contains('$("#reinstall_software").trigger("focus");', $php, 'focus moves to the reinstall box');
}

function test_installer_wizard_console_glyphs_have_words()
{
	$php = installer_wizard_a11y_source();

	$mark = 'var mark = (state === "warning") ? "<span class=\"pg-warning\"><span aria-hidden=\"true\">&#9650;</span><span class=\"visually-hidden\">\' . escape_javascript(lang(\'Warning\')) . \'</span></span>"';
	pg_assert_true(substr_count($php, $mark) === 2, 'both console copies say Warning');
	pg_assert_true(substr_count($php, '<span class=\"pg-error\"><span aria-hidden=\"true\">&#10007;</span><span class=\"visually-hidden\">\' . escape_javascript(lang(\'Error\')) . \'</span></span>') === 2, 'both console copies say Error');
	pg_assert_true(substr_count($php, '<span class=\"pg-ok\" aria-hidden=\"true\">&#10003;</span>') === 2, 'the tick is decoration');
	pg_assert_contains('id="pg_stream_now" role="status"', $php, 'the stand-alone run page says what it is doing');
}
