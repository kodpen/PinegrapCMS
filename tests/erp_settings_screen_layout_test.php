<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the ERP Settings / Document Templates screen layout
 * (erp_settings.php): the setting cards and the template editor in a form
 * column, the templates' state and where the seller is set in a sticky
 * status column written after it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function erp_settings_screen_layout_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/erp_settings.php');
}

function test_erp_settings_screen_has_form_and_status_columns()
{
	$source = erp_settings_screen_layout_source();

	$form = strpos($source, '<div class="col-12 lg:col-8 2xl:col-9 order-1">');
	$status = strpos($source, '<div class="col-12 lg:col-4 2xl:col-3 order-2">');

	pg_assert_true($form !== false, 'the form column is there');
	pg_assert_true($status !== false, 'the status column is there');
	pg_assert_true($form < $status, 'form column before the status column in source order');
	pg_assert_contains('<div class="position-sticky" style="top:3.3rem;">', $source, 'the status box sticks below the top bar');

	$cards = strpos($source, "' . \$output_cards . '");
	$template = strpos($source, '<form name="form" id="erp_template"');
	pg_assert_true($cards !== false && $cards > $form && $cards < $status, 'the setting cards sit in the form column');
	pg_assert_true($template !== false && $template > $form && $template < $status, 'the template editor sits in the form column');
}

function test_erp_settings_screen_status_column_is_read_only()
{
	$source = erp_settings_screen_layout_source();
	$start = (int) strpos($source, '<div class="col-12 lg:col-4 2xl:col-3 order-2">');
	$status = substr($source, $start, (int) strpos($source, '</main>', $start) - $start);

	pg_assert_contains('$output_document_states', $status, 'each template\'s state is read there');
	pg_assert_contains('$output_seller', $status, 'where the seller is set is read there');
	pg_assert_false(strpos($status, '<form') !== false || strpos($status, '<textarea') !== false, 'nothing to fill in the status column');
	pg_assert_true(strpos($source, "<div class=\"card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold\">\n                    ' . lang('Seller Information')") === false, 'no Seller Information card left in the form column');
}
