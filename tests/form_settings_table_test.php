<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_form_settings_table() in includes/fn/forms.php: the table the
 * legacy field screens read a page form's name from. A visual-designer page
 * keeps page_type 'standard', and building the name from the type sent those
 * screens to a standard_pages table that does not exist.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The legacy page types keep the table they always used.
function test_form_settings_table_legacy_page_types()
{
	pg_assert_same('custom_form_pages', pg_form_settings_table('custom form', 'custom'), 'legacy custom form page');
	pg_assert_same('express_order_pages', pg_form_settings_table('express order', 'billing'), 'express order billing form');
	pg_assert_same('express_order_pages', pg_form_settings_table('express order', 'shipping'), 'express order shipping form');
	pg_assert_same('shipping_address_and_arrival_pages', pg_form_settings_table('shipping address and arrival', 'shipping'), 'shipping page');
	pg_assert_same('billing_information_pages', pg_form_settings_table('billing information', 'billing'), 'billing page');
}

// A form drawn in the visual designer sits on a standard page; its settings
// row is in custom_form_pages.
function test_form_settings_table_designer_form()
{
	pg_assert_same('custom_form_pages', pg_form_settings_table('standard', 'custom'));
}

// A page type without a form settings table has no table to read.
function test_form_settings_table_none()
{
	pg_assert_same('', pg_form_settings_table('standard', ''), 'standard page without a form');
	pg_assert_same('', pg_form_settings_table('catalog', ''), 'other page type');
	pg_assert_same('', pg_form_settings_table('', ''), 'empty');
}
