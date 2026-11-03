<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the contact screens' wide edit layout
 * (includes/forms/contact_form.php): the form column comes first, the
 * status column holds a sticky box below the top bar, the save bar is
 * sticky, and both screens draw through it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/contact_form.php');

function test_contact_form_layout_columns_in_order()
{
	$html = pg_contact_form_layout('<i>FORM</i>', '<i>STATUS</i>', '<b>BUTTONS</b>');

	$form = strpos($html, '<div class="col-12 lg:col-8 2xl:col-9 order-1"><i>FORM</i>');
	$status = strpos($html, '<div class="col-12 lg:col-4 2xl:col-3 order-2">');
	$sticky = strpos($html, '<div class="position-sticky" style="top:3.3rem;"><i>STATUS</i>');
	$bar = strpos($html, '<div class="col-12 order-3 position-sticky" style="bottom:0; z-index:3;">');
	$buttons = strpos($html, '<b>BUTTONS</b>');

	pg_assert_true($form !== false, 'form column with its cards');
	pg_assert_true($status !== false && $status > $form, 'status column after the form column');
	pg_assert_true($sticky !== false && $sticky > $status, 'sticky box inside the status column');
	pg_assert_true($bar !== false && $bar > $sticky, 'save bar last');
	pg_assert_true($buttons !== false && $buttons > $bar, 'buttons in the save bar');
	pg_assert_contains('<nav class="buttons navigation text-center mb-7"', $html, 'shared save bar component');
}

function test_contact_form_picture_card_picker_input()
{
	pg_assert_contains("file_input_name:'file_id'", pg_contact_form_picture_card(false, ''), 'edit posts the picture as file_id');
	pg_assert_contains("file_input_name:'file_id'", pg_contact_form_picture_card(true, ''), 'create posts the picture as file_id too (selected_images[] was never read)');
	pg_assert_contains('id="software_image_picker_container"', pg_contact_form_picture_card(true, '<x>'), 'picker container');
}

function test_contact_form_screens_use_the_layout()
{
	foreach (array('add_contact.php', 'edit_contact.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_contains('pg_contact_form_layout(', $source, $file . ': wide layout');
		pg_assert_contains('pg_contact_form_name_card(', $source, $file . ': name card');
		pg_assert_contains('pg_contact_form_picture_card(', $source, $file . ': picture card');
	}
}
