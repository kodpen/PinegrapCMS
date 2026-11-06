<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that the installer's choice cards work from the keyboard: the
 * starter site, server backup, upload and design cards each hold a visually
 * hidden radio in a named group, so they take focus, show the panel ring and
 * are chosen with Space, Enter or the arrow keys, and a focused colour
 * swatch keeps the ring that marks it as chosen.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function installer_choice_cards_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/install/index.php');
}

function test_installer_source_cards_are_a_radio_group()
{
	$php = installer_choice_cards_source();

	pg_assert_contains('<div role="radiogroup" aria-label="\' . h(lang(\'Installation Folder\')) . \'">\' . $output_starter_sites . $output_backup_source . $output_upload_source . \'</div>\' . $output_upload_area', $php, 'the source cards are one named group and the upload area sits after it');
	pg_assert_contains('<input type="radio" class="visually-hidden pg-choice-input" name="pg_source_choice" value="starter" aria-labelledby="pg_source_starter_\' . $starter_site_number . \'"\' . (($output_starter_site_selected != \'\') ? \' checked\' : \'\') . \'>', $php, 'a starter site card holds a radio checked with the card');
	pg_assert_contains('<input type="radio" class="visually-hidden pg-choice-input" name="pg_source_choice" value="backup" aria-labelledby="pg_source_backup"', $php, 'the server backup card holds a radio');
	pg_assert_contains('<input type="radio" class="visually-hidden pg-choice-input" name="pg_source_choice" value="upload" aria-labelledby="pg_source_upload">', $php, 'the upload card holds a radio');
	pg_assert_contains("\$output_upload_area = '\n\t\t<div class=\"pg-drop d-none\" id=\"pg_zip_area\">", $php, 'the upload area is built apart from its card');
}

function test_installer_design_cards_are_a_radio_group()
{
	$php = installer_choice_cards_source();

	pg_assert_contains('<div role="radiogroup" aria-label="\' . h(lang(\'Site Design\')) . \'">', $php, 'the design cards are one named group');
	pg_assert_contains('<div class="pg-design-tiles">\' . $output_design_choices . \'</div>', $php, 'the template tiles sit in the group');
	pg_assert_contains('<div class="mt-5">\' . $output_design_standard . \'</div>', $php, 'the standard installation sits in the same group');
	pg_assert_contains('<input type="radio" class="visually-hidden pg-choice-input" name="pg_design_choice" value="\' . h($design_template[\'id\']) . \'" aria-labelledby="pg_design_name_\' . $design_template_number . \'"', $php, 'a template card holds a radio');
	pg_assert_contains('<input type="radio" class="visually-hidden pg-choice-input" name="pg_design_choice" value="standard" aria-labelledby="pg_design_name_standard"', $php, 'the standard installation card holds a radio');
	pg_assert_contains('$(".pg-design-template .pg-choice-input").prop("disabled", !starter);', $php, 'a template that cannot be used is a disabled radio, skipped by the arrow keys');
}

function test_installer_choice_cards_follow_the_keyboard()
{
	$php = installer_choice_cards_source();

	pg_assert_contains('$(element).find(".pg-choice-input").prop("checked", true);', $php, 'choosing a card checks its radio');
	pg_assert_contains('$(".pg-source .pg-choice-input").on("change", function() {', $php, 'Space and the arrow keys choose through the radio');
	pg_assert_contains('$(".pg-source .pg-choice-input").on("keydown", function(event) {', $php, 'Enter chooses the focused card');
	pg_assert_contains('if (event.key !== "Enter") { return; }', $php, 'only Enter is taken over');
	pg_assert_contains('pg_install_select_source($(this).closest(".pg-source")[0]);', $php, 'changing the server backup chooses its card');
}

function test_installer_choice_cards_show_focus()
{
	$php = installer_choice_cards_source();

	pg_assert_contains('.pg-source:has(> .pg-choice-input:focus-visible) { outline: 2px solid var(--pg-primary-ink); outline-offset: 2px; }', $php, 'a focused card shows the panel ring');
	pg_assert_contains('.btn-check:focus-visible + .pg-design-swatch { outline: 2px solid var(--pg-primary-ink); outline-offset: 3px; }', $php, 'a focused swatch is ringed with an outline');
	pg_assert_false(strpos($php, '.btn-check:focus-visible + .pg-design-swatch { box-shadow') !== false, 'focus no longer replaces the chosen swatch\'s ring');
}

function test_installer_design_step_previews_and_tour()
{
	$php = installer_choice_cards_source();

	pg_assert_contains('$_GET[\'install_action\'] === \'design_tour\'', $php, 'the tour has its own action beside design_preview');
	pg_assert_contains('$design_tour_html = pg_design_tour_html($design_tour_options);', $php, 'the tour is printed by the shared helper');
	pg_assert_contains('pg_design_live_picture($design_template_picture, array(', $php, 'each tile is a live picture of its home page');
	pg_assert_contains('if ((apply_theme) && ($(element).hasClass("pg-design-template"))) { pg_install_apply_template_theme($(element)); }', $php, 'picking a template sets the pickers to its own look and palette');
	pg_assert_contains('$install_design_look = ($install_design_selected_theme[\'look\'] !== null) ? (string) $install_design_selected_theme[\'look\'] : pg_design_default_look();', $php, 'a template that names no look keeps the default look in the pickers');
	pg_assert_contains('$design_template_look = ($design_template_summary[\'look\'] !== null) ? (string) $design_template_summary[\'look\'] : pg_design_default_look();', $php, 'a tile of a template that names no look wears the default look');
	pg_assert_contains('var tour = window.open(this.href, "_blank");', $php, 'the tour opens from a script, so its button can close the tab');
	pg_assert_contains('main#content.pg-install { max-width: 1440px; }', $php, 'the installer is as wide as the widest container');
	pg_assert_contains('.pg-wizard-grid.pg-wide-step .pg-side { display: none; }', $php, 'the design step takes the side column\'s room');
}

function test_installer_designer_scripts_load_in_order()
{
	$php = installer_choice_cards_source();

	pg_assert_contains('$output_designer_scripts = json_encode(array_values(pg_js_bundle_urls(\'assets/js/style_designer.min.js\'))', $php, 'the designer\'s addresses travel as a list');
	pg_assert_contains('script.onload = function() { next(index + 1); };', $php, 'each source loads after the one before');
	pg_assert_false(strpos($php, 'implode(\'\', pg_js_bundle_urls(') !== false, 'the addresses are never glued into one');
}
