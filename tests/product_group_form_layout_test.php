<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the product group screens' wide edit layout
 * (includes/forms/product_group_form.php): the form column comes first,
 * the status column holds a sticky box below the top bar with the Enabled
 * switch and the parent group, the products table takes the full width
 * under both, the save bar is sticky and last, and both screens draw
 * through it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/product_group_form.php');

function test_product_group_form_layout_columns_in_order()
{
	$html = pg_product_group_form_layout(array(
		'form' => '<i>FORM</i>',
		'status' => '<i>STATUS</i>',
		'wide' => '<i>PRODUCTS</i>',
		'buttons' => '<b>BUTTONS</b>',
		'bar_extra' => '<u>EXTRA</u>',
		'row_class' => 'edit_product_group_container'));

	$row = strpos($html, '<div class="row edit_product_group_container">');
	$form = strpos($html, '<div class="col-12 lg:col-8 2xl:col-9 order-1"><i>FORM</i>');
	$status = strpos($html, '<div class="col-12 lg:col-4 2xl:col-3 order-2">');
	$sticky = strpos($html, '<div class="position-sticky" style="top:3.3rem;"><i>STATUS</i>');
	$wide = strpos($html, '<div class="col-12 order-3"><div class="row"><i>PRODUCTS</i>');
	$bar = strpos($html, '<div class="col-12 order-3 position-sticky" style="bottom:0; z-index:3;">');
	$buttons = strpos($html, '<b>BUTTONS</b>');
	$extra = strpos($html, '<u>EXTRA</u>');

	pg_assert_true($row !== false, 'row keeps the class the screen script reads');
	pg_assert_true($form !== false && $form > $row, 'form column with its cards straight in it');
	pg_assert_true($status !== false && $status > $form, 'status column after the form column');
	pg_assert_true($sticky !== false && $sticky > $status, 'sticky box inside the status column');
	pg_assert_true($wide !== false && $wide > $sticky, 'products table under both columns');
	pg_assert_true($bar !== false && $bar > $wide, 'save bar last, in an order-3 column after the table\'s');
	pg_assert_true($buttons !== false && $buttons > $bar, 'buttons in the save bar');
	pg_assert_true($extra !== false && $extra > $buttons, 'extra bar content after the buttons');
	pg_assert_contains('<nav class="buttons navigation text-center mb-7"', $html, 'shared save bar component');
}

function test_product_group_form_status_card()
{
	$html = pg_product_group_form_status_card(array('enabled' => '1'), '<i>PARENT</i>');

	pg_assert_contains('id="enabled" name="enabled" value="1" checked="checked"', $html, 'enabled switch, ticked');
	pg_assert_contains('<i>PARENT</i>', $html, 'parent group picker');
	pg_assert_true(strpos(pg_product_group_form_status_card(array('enabled' => '0'), ''), 'checked') === false, 'disabled group unticked');

	// The form column cards no longer draw the switch.
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/product_group_form.php');
	$cards = substr($source, strpos($source, 'function pg_product_group_form_cards('), strpos($source, 'function pg_product_group_form_status_card(') - strpos($source, 'function pg_product_group_form_cards('));
	pg_assert_true(strpos($cards, 'name="enabled"') === false, 'the switch is no longer in the form column');
}

function test_product_group_form_screens_use_the_layout()
{
	foreach (array('add_product_group.php', 'edit_product_group.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_contains('pg_product_group_form_layout(array(', $source, $file . ': wide layout');
		pg_assert_contains('pg_product_group_form_status_card(', $source, $file . ': status card');
		pg_assert_true(strpos($source, 'md:col-4 my-3') === false, $file . ': parent picker sized for the status column');
	}
}
