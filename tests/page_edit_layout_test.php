<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that Create Page and Edit Page lay out as a form column, a sticky
 * status column holding the page type card, and a sticky save bar on its own
 * column, and that the page type card brings no column or stickiness of its
 * own, and that their rows hide with d-none, toggled by pg_page_row(), with
 * no inline handlers or fake popover boxes.
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

function test_page_edit_layout_rows_hide_with_a_class()
{
	foreach (array('includes/forms/page_form.php', 'edit_page.php') as $file) {
		$source = page_edit_layout_test_source($file);

		pg_assert_true(preg_match('/display:\s*none/', $source) === 0, $file . ': no row is hidden with an inline display');
		pg_assert_true(strpos($source, 'fake_popover') === false, $file . ': no always-open popover boxes');
		pg_assert_true(preg_match('/\son(click|change)="(change_page_type|show_or_hide_comments|show_or_hide_custom_form_(confirmation|return)_type)/', $source) === 0, $file . ': no inline handler for the type picker or the comments switch');
	}

	$form = page_edit_layout_test_source('includes/forms/page_form.php');
	pg_assert_contains('<div class="col-12 my-3\' . $layout_type_row_style . \'" id="layout_type_row">', $form, 'the hidden state is a class on the row');
	pg_assert_contains('<div class="collapse border rounded p-5 mt-3 mb-3" id="update_address_book_address_type_page_id_row">', $form, 'the address type page box is a flat bordered block');

	$edit = page_edit_layout_test_source('edit_page.php');
	foreach (array('show_or_hide_search', 'show_or_hide_comments') as $id) {
		pg_assert_contains('<div class="collapse border rounded p-5 mt-3 mb-3" id="' . $id . '">', $edit, $id . ' is a flat bordered block');
	}

	foreach (array('add_page.php', 'edit_page.php') as $file) {
		pg_assert_contains('bindPageTypePicker();', page_edit_layout_test_source($file), $file . ' binds the type picker');
	}
}

function test_page_edit_layout_script_toggles_the_class()
{
	$js = page_edit_layout_test_source('assets/js/backend.src.js');
	$start = strpos($js, 'function pg_page_row(');
	$end = strpos($js, 'function change_order_by(');
	$block = substr($js, $start, $end - $start);
	$arrival = strpos($block, 'function show_or_hide_custom() {');
	$arrival_end = strpos($block, 'function show_or_hide_comments() {');
	// show_or_hide_custom() belongs to the arrival date form, not the page form.
	$block = substr($block, 0, $arrival) . substr($block, $arrival_end);

	pg_assert_true($start !== false && $end > $start, 'the page form block exists');
	pg_assert_contains("row.classList.toggle('d-none', !shown);", $block, 'pg_page_row() toggles d-none');
	pg_assert_true(strpos($block, '.style.display') === false, 'no inline display in the page form scripts');
	pg_assert_true(preg_match('/fadeIn|fadeOut|tinyMCE/', $block) === 0, 'no jQuery fades and no tinyMCE branches');
	pg_assert_true(strpos($block, "pg_page_row('update_address_book_address_type_page_id_row'") === false, 'the switcher owns its box');
}
