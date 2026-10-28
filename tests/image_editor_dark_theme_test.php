<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for the Pintura (Doka) image editor in the dark panel: an
 * unlayered rule recolours the editor's RGB-triple palette on the editor
 * page and in the openImageEditor() dialog, and the editor page's theme
 * menu carries the ids backend.src.js marks the active choice by.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function image_editor_dark_theme_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

function test_image_editor_dark_theme_page_rule()
{
	$source = image_editor_dark_theme_source('image_editor_edit.php');
	$rule = '[data-bs-theme="dark"] .doka-image-editor { --color-background: var(--pg-body-bg-rgb, 16, 17, 20); --color-foreground: 222, 226, 230; }';

	pg_assert_contains($rule, $source);

	// Unlayered and after doka.css: a @layer custom rule loses to the
	// unlayered vendor sheet.
	$link = strpos($source, 'doka/doka.css');
	$at = strpos($source, $rule);
	pg_assert_true($link !== false && $at > $link, 'rule after doka.css');
	$style_open = strrpos(substr($source, 0, $at), '<style');
	pg_assert_true(strpos(substr($source, $style_open, $at - $style_open), '@layer') === false, 'rule inside a layer');
}

function test_image_editor_dark_theme_modal_rule()
{
	pg_assert_contains(
		"'[data-bs-theme=\"dark\"] .doka-image-editor { --color-background: var(--pg-body-bg-rgb, 16, 17, 20); --color-foreground: 222, 226, 230; }'",
		image_editor_dark_theme_source('assets/js/image_editor_modal.js')
	);
}

function test_image_editor_dark_theme_menu_ids()
{
	$source = image_editor_dark_theme_source('image_editor_edit.php');

	foreach (array('light', 'dark', 'auto') as $theme) {
		pg_assert_true((bool) preg_match('/<button id="theme-' . $theme . '" [^>]*data-bs-theme-value="' . $theme . '"/', $source), 'id theme-' . $theme);
	}

	pg_assert_true(!preg_match('/class="[^"]*\bactive\b[^"]*" data-bs-theme-value=/', $source), 'hard-coded active theme item');
	pg_assert_contains("id=\"bd-theme\" type=\"button\" aria-label=\"' . h(lang('Software Theme')) . '\"", $source);
}
