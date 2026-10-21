<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure parts of the İşbaşı e-document driver
 * (includes/erp/edoc/isbasi.php): how a line's discount is sent, so that
 * İşbaşı works the line out to the ERP's figure.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/edoc/registry.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/edoc/isbasi.php');

// A line with a campaign discount goes with the amount of both discounts
// (the ERP takes the campaign first and the typed rate from what is left);
// a line whose only discount is the typed rate goes with the rate, as
// before; a line without one with neither.
function test_erp_edoc_isbasi_line_discount()
{
	pg_assert_same(array('rate' => 0.0, 'value' => 145.0), erp_edoc_isbasi_line_discount(array('discount_rate' => 5, 'offer_discount_rate' => 10, 'discount_amount' => 14500)), 'campaign and typed discount: the amount');
	pg_assert_same(array('rate' => 0.0, 'value' => 100.0), erp_edoc_isbasi_line_discount(array('discount_rate' => 0, 'offer_discount_rate' => 10, 'discount_amount' => 10000)), 'campaign alone: the amount');
	pg_assert_same(array('rate' => 5.0, 'value' => 0.0), erp_edoc_isbasi_line_discount(array('discount_rate' => 5, 'offer_discount_rate' => 0, 'discount_amount' => 5000)), 'typed rate alone: the rate');
	pg_assert_same(array('rate' => 5.0, 'value' => 0.0), erp_edoc_isbasi_line_discount(array('discount_rate' => 5, 'discount_amount' => 5000)), 'a line without the campaign column');
	pg_assert_same(array('rate' => 0.0, 'value' => 0.0), erp_edoc_isbasi_line_discount(array('discount_rate' => 0, 'discount_amount' => 0)), 'no discount');
	pg_assert_same(array('rate' => 0.0, 'value' => 12.34), erp_edoc_isbasi_line_discount(array('discount_rate' => 0, 'discount_amount' => 1234)), 'an amount typed as such');
}
