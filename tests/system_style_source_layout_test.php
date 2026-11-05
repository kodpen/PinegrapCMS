<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the system style View Source screen layout
 * (view_system_style_source.php): the head content and the read-only source
 * in a form column, a Summary in a sticky status column written after it,
 * the save buttons in the sticky save bar, the source kept out of the form.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function system_style_source_layout_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_system_style_source.php');
}

function test_system_style_source_has_form_status_and_save_bar_columns()
{
	$source = system_style_source_layout_source();

	$form = strpos($source, '<div class="col-12 lg:col-8 2xl:col-9 order-1">');
	$status = strpos($source, '<div class="col-12 lg:col-4 2xl:col-3 order-2">');
	$bar = strpos($source, '<div class="col-12 order-3 position-sticky" style="bottom:0; z-index:3;">');

	pg_assert_true($form !== false && $status !== false && $bar !== false, 'form column, status column and save bar are there');
	pg_assert_true($form < $status && $status < $bar, 'form column, status column, save bar in source order');
	pg_assert_contains('<div class="position-sticky" style="top:3.3rem;">', $source, 'the status box sticks below the top bar');

	foreach (array("'name' => 'head'", "'name' => 'code'") as $field) {
		$at = strpos($source, $field);
		pg_assert_true($at !== false && $at > $form && $at < $status, $field . ' sits in the form column');
	}

	$status_markup = substr($source, $status, $bar - $status);
	pg_assert_contains("lang('Summary')", $status_markup, 'the status column holds the Summary');
	pg_assert_contains("lang('Last Modified')", $status_markup, 'with the last change');
	pg_assert_false(strpos($status_markup, 'output_field') !== false, 'nothing to fill in the status column');
}

function test_system_style_source_keeps_the_page_code_out_of_the_post()
{
	$source = system_style_source_layout_source();

	$form_open = strpos($source, '<form action="view_system_style_source.php" method="post" id="system_style_source_form">');
	$form_close = strpos($source, '</form>', (int) $form_open);
	$code = strpos($source, "'name' => 'code'");

	pg_assert_true($form_open !== false && $form_close !== false, 'the form is there');
	pg_assert_true($code > $form_close, 'the read-only source is outside the form');
	pg_assert_true(substr_count($source, 'form="system_style_source_form"') === 2, 'both save buttons submit the form from the save bar');
	pg_assert_contains("'cancel' => array('enable' => 'true', 'url' => \$back_url)", $source, 'the back arrow goes where Cancel went');
	pg_assert_false(strpos($source, "lang('Cancel')") !== false, 'no Cancel copy in the save bar');
}
