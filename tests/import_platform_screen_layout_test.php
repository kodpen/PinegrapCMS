<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Import from another platform screen layout (import.php): the
 * cards to fill or act on in a form column, the Summary and the counts in a
 * sticky status column written after it, the step's buttons in a sticky bar
 * inside the form; Discard is its own form named by its button.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function import_platform_screen_layout_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/import.php');
}

function test_import_platform_screen_has_form_and_status_columns()
{
	$source = import_platform_screen_layout_source();
	$main = strpos($source, '<main id="content" class="container-fluid">');
	$markup = substr($source, (int) $main);

	$form = strpos($markup, '<div class="col-12 lg:col-8 2xl:col-9 order-1">');
	$status = strpos($markup, '<div class="col-12 lg:col-4 2xl:col-3 order-2">');
	$bar = strpos($markup, '<div class="col-12 order-3">');

	pg_assert_true($main !== false, 'the screen markup is there');
	pg_assert_true($form !== false && $status !== false && $bar !== false, 'form column, status column and save bar are there');
	pg_assert_true($form < $status && $status < $bar, 'form column, status column, save bar in source order');
	pg_assert_contains('<div class="position-sticky" style="top:3.3rem;">', $markup, 'the status box sticks below the top bar');
	pg_assert_true(strpos($markup, "' . \$form_open . '") < $form && strpos($markup, "' . \$form_close . '") > $bar, 'the form wraps the row, so the bar submits it');
	pg_assert_false(strpos($source, 'lg:col-10 2xl:col-8') !== false, 'the narrow single column is gone');
}

function test_import_platform_screen_status_column_holds_what_is_read()
{
	$source = import_platform_screen_layout_source();

	pg_assert_contains("\$side .= \$card(lang('What Is Imported')", $source, 'the upload step says what is imported beside the file');
	pg_assert_contains("\$summary = \$card(lang('Summary')", $source, 'a checked or run import has a Summary');
	pg_assert_contains("\$side .= \$summary . \$card(lang('What the file holds')", $source, 'the dry run counts sit in the status column');
	pg_assert_contains("\$main .= \$card(lang('Where it goes')", $source, 'the mapping sits in the form column');
	pg_assert_contains('<div class="col-12 md:col-6 my-3">', $source, 'mapping rows pair in two columns instead of label/field rows');
	pg_assert_false(strpos($source, 'md:col-4 form-label') !== false, 'no label column left');
}

function test_import_platform_screen_discard_is_its_own_form()
{
	$source = import_platform_screen_layout_source();

	pg_assert_contains('<form id="pg_import_discard" action="import.php" method="post">', $source, 'Discard keeps its own form');
	pg_assert_contains('form="pg_import_discard"', $source, 'the bar button submits the discard form');
	pg_assert_true(strpos($source, "' . \$before . '") < strpos($source, "' . \$form_open . '"), 'the discard form is printed before the start form, not inside it');
}
