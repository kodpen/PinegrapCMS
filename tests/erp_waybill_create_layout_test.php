<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the new delivery note screen layout (add_erp_waybill.php): the
 * form cards in a form column, what the note is for and its Notes in a
 * sticky status column written after it, both inside the one form.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function erp_waybill_create_layout_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function test_erp_waybill_create_has_form_and_status_columns()
{
	$source = erp_waybill_create_layout_source('add_erp_waybill.php');

	$form_tag = strpos($source, '<form name="form" action="add_erp_waybill.php"');
	$form = strpos($source, '<div class="col-12 lg:col-8 2xl:col-9 order-1">');
	$status = strpos($source, '<div class="col-12 lg:col-4 2xl:col-3 order-2">');
	$form_end = strpos($source, '</form>', (int) $status);

	pg_assert_true($form_tag !== false && $form !== false && $status !== false, 'the form and both columns are there');
	pg_assert_true($form_tag < $form && $form < $status && $status < $form_end, 'the form wraps both columns, the status column after the form column');
	pg_assert_contains('<div class="position-sticky" style="top:3.3rem;">', $source, 'the status box sticks below the top bar');
	pg_assert_contains("'aside' => false,", $source, 'the form column leaves the aside cards out');
	pg_assert_contains('erp_waybill_form_aside_cards($liveform', substr($source, (int) $status), 'the status column draws them');
	pg_assert_true(strpos($source, 'position-sticky mb-7" style="bottom:.5rem;"') < $status, 'the save bar closes the form column');
}

function test_erp_waybill_form_cards_aside_option()
{
	$source = erp_waybill_create_layout_source('includes/erp/waybill_form.php');

	pg_assert_contains("\$with_aside = !isset(\$options['aside']) || \$options['aside'];", $source, 'the aside cards stay by default');
	pg_assert_contains("\$output_order = \$with_aside ? erp_waybill_form_source(\$options, 'alert') : '';", $source, 'the order or invoice note follows the option');
	pg_assert_contains("(\$with_aside ? erp_waybill_form_notes_card(\$liveform) : '')", $source, 'the Notes card follows the option');
	pg_assert_contains("return erp_waybill_form_source(\$options, 'card') . erp_waybill_form_notes_card(\$liveform);", $source, 'the aside draws the source card, then Notes');
	pg_assert_false(strpos($source, '"col-6 lg:col-2 my-3"') !== false, 'no sixth-width date boxes in a two-thirds column');
}
