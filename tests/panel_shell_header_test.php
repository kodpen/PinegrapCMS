<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Pins, by source, the panel header details: the search dialog and the bell
 * have an accessible name, the Ctrl+K hints follow the platform's modifier,
 * Enter in the search box does not submit it, the licence diagnostic is
 * translated and shown for technical failures only, and the legacy layers
 * (json2, the Ctrl+Y outline toggle, the feedback popup) are gone.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_panel_shell_search_dialog_and_bell_are_named()
{
	$header = pg_test_function_source('output_header');

	pg_assert_contains('id="SearchBox" aria-label="\' . h(lang(\'Search\')) . \'"', $header, 'the search dialog is named');
	pg_assert_false(strpos($header, 'SearchBoxLabel') !== false, 'no reference to a missing id');
	pg_assert_contains('<span class="visually-hidden">\' . h(lang(\'Notifications\')) . \'</span>', $header, 'the bell is named beside its count');
	pg_assert_false(strpos($header, '⌘+Q') !== false, 'Command+Q quits the browser: not offered');
	pg_assert_same(2, substr_count($header, '<span data-pg-mod>Ctrl</span>+K'), 'both Ctrl+K hints follow the platform');
}

function test_panel_shell_search_enter_and_stale_answers()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/backend.src.js');

	pg_assert_contains("\$modal.find('form.search_form').on('submit', function (e) {\n            e.preventDefault();", $js, 'Enter does not reload the screen');
	pg_assert_contains("(String(e.key).toLowerCase() === 'k')", $js, 'Ctrl+K with Caps Lock or Shift');
	pg_assert_contains('if (loading && offset > 0) return;', $js, 'only Load More waits for the running request');
	pg_assert_same(2, substr_count($js, 'if (seq !== searchSeq) return;'), 'an older answer is dropped on success and on error');
	pg_assert_contains("document.querySelectorAll('[data-pg-mod]')", $js, 'the modifier rewrite reaches the header hints');
	pg_assert_false(strpos($js, 'show_draw_lines') !== false, 'the Ctrl+Y outline toggle is gone');
}

function test_panel_shell_licence_diagnostic()
{
	$check = pg_test_function_source('license_check');

	pg_assert_contains("(pg_license_error_notice(\$error_code) === '')", $check, 'no diagnostic for a key the server answered for');
	pg_assert_contains("h(lang('Diagnostic'))", $check, 'the summary is translated');
	pg_assert_contains("h(lang('Response')) . ': '", $check, 'the response label is translated');
	pg_assert_false(strpos($check, 'style="font-size:.65rem"') !== false, 'its look is in the stylesheet');
}

function test_panel_shell_legacy_layers_gone()
{
	pg_assert_false(strpos(pg_test_function_source('output_control_panel_header_includes'), 'json2') !== false, 'no JSON polyfill');

	$menu = pg_test_function_source('pg_account_menu');

	pg_assert_false(strpos($menu, 'window.open(') !== false, 'the feedback row opens no popup window');
	pg_assert_contains("\$row(\$feedback_url, 'bi-chat-square-heart', lang('Feedback'), \$new_tab, true)", $menu, 'it opens a new tab like the other outside rows');
}
