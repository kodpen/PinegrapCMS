<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that Create Page and Edit Page lay out as a form column, a sticky
 * status column holding the page type card, and a sticky save bar on its own
 * column, and that the page type card brings no column or stickiness of its
 * own.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function page_edit_layout_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function test_page_edit_layout_columns()
{
	$selectors = array(
		'add_page.php' => "\$page_type_fields['selector']",
		'edit_page.php' => '$output_page_type_selector',
	);

	foreach ($selectors as $file => $selector) {
		$source = page_edit_layout_test_source($file);
		$form = strpos($source, '<div class="col-12 lg:col-8 2xl:col-9 order-1">');
		$status = strpos($source, '<div class="col-12 lg:col-4 2xl:col-3 order-2">');
		$box = strpos($source, '<div class="position-sticky mt-7" style="top:3.3rem;">');
		$type = strpos($source, "' . " . $selector . " . '");
		$bar = strpos($source, '<div class="col-12 order-3 position-sticky" style="bottom:0; z-index:3;">');
		$nav = strpos($source, '<nav class="buttons navigation text-center mb-7"');

		pg_assert_true($form !== false, $file . ': form column');
		pg_assert_true($status !== false && $status > $form, $file . ': status column after the form column');
		pg_assert_true($box !== false && $box > $status && $type > $box, $file . ': page type card in the sticky status box');
		pg_assert_true($bar !== false && $bar > $type && $nav > $bar, $file . ': save bar on its own sticky column, last');
		pg_assert_true(strpos($source, 'style="top:56px;"') === false, $file . ': no card sticks on its own');
	}
}

function test_page_edit_layout_type_card_has_no_column()
{
	$source = page_edit_layout_test_source('includes/forms/page_form.php');

	pg_assert_true(preg_match("/\\\$output_page_type_selector = \\s*'<div class=\"card mb-7\">/", $source) === 1, 'the page type card starts with the card itself');
	pg_assert_true(strpos($source, 'md:col-4 lg:col-3 xl:col-2') === false, 'no column of its own');
}
