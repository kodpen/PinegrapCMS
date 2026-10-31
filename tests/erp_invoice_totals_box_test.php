<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the totals box of the built-in invoice template
 * (includes/erp/templates/invoice_default.html): its rows add up to the grand
 * total. Shipping and surcharge are invoice lines and part of the subtotal, so
 * the box never adds them again. Also pins the sample the template preview
 * takes (erp_invoice_template_sample_query() in includes/erp/document.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_ERP_ENTRY')) {
	define('PG_ERP_ENTRY', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/document.php');

// Renders the totals table of the built-in template and returns its rows as
// label => signed minor units, the grand total row apart.
function erp_invoice_totals_box_rows_for_test(array $totals)
{
	$labels = array();

	foreach (array('subtotal', 'discount', 'shipping', 'surcharge', 'vat_total', 'tax2', 'tax_inclusive', 'withholding', 'amount_payable', 'grand_total', 'grand_total_base', 'gift_card') as $key) {
		$labels[$key] = 'L_' . $key;
	}

	$html = erp_template_render(erp_invoice_default_template(), array(
		'invoice' => array('is_foreign' => false),
		'lines' => array(),
		'totals' => $totals,
		'label' => $labels,
	));

	pg_assert_true((bool) preg_match('/<table class="totals">(.*?)<\/table>/s', $html, $match), 'the totals table is there');
	preg_match_all('/<tr( class="([a-z]+)")?><td>(L_[a-z0-9_]+)<\/td><td class="v">(&minus;)?(-?\d+)<\/td><\/tr>/', $match[1], $rows, PREG_SET_ORDER);

	$out = array('rows' => array(), 'grand' => null);

	foreach ($rows as $row) {
		$value = (int) $row[5] * (($row[4] !== '') ? -1 : 1);

		if ($row[2] === 'grand') {
			$out['grand'] = $value;
		} elseif ($row[2] === '') {
			$out['rows'][$row[3]] = $value;
		}
	}

	return $out;
}

// The order of issue #3998: discount 13.00, shipping 9.98 (8.32 + 1.66 VAT) as
// a line inside the subtotal, grand total 150.32.
function test_erp_invoice_totals_box_adds_up_with_shipping_and_surcharge()
{
	$box = erp_invoice_totals_box_rows_for_test(array(
		'subtotal' => '13827',
		'discount_total' => '1300',
		'shipping_total' => '832',
		'surcharge_total' => '250',
		'tax_total' => '2505',
		'grand_total' => '15032',
		'has_discount' => true,
		'has_shipping' => true,
		'has_surcharge' => true,
	));

	pg_assert_same(15032, $box['grand'], 'the grand total row');
	pg_assert_same(15032, array_sum($box['rows']), 'the rows above it add up to it');
	pg_assert_true(!isset($box['rows']['L_shipping']), 'shipping is not added a second time');
	pg_assert_true(!isset($box['rows']['L_surcharge']), 'the surcharge is not added a second time');
}

// With withholding the rows down to the tax-inclusive total add up to it, and
// that less the withholding is the amount payable.
function test_erp_invoice_totals_box_adds_up_with_withholding()
{
	$box = erp_invoice_totals_box_rows_for_test(array(
		'subtotal' => '10000',
		'discount_total' => '0',
		'shipping_total' => '1000',
		'tax_total' => '2000',
		'tax_inclusive' => '12000',
		'withholding_total' => '1000',
		'grand_total' => '11000',
		'has_shipping' => true,
		'has_withholding' => true,
	));

	pg_assert_same(11000, $box['grand'], 'the amount payable row');
	pg_assert_same(12000, $box['rows']['L_tax_inclusive'], 'the tax-inclusive total');
	pg_assert_same(12000, $box['rows']['L_subtotal'] + $box['rows']['L_vat_total'], 'subtotal and VAT make it');
	pg_assert_same(11000, $box['rows']['L_tax_inclusive'] + $box['rows']['L_withholding'], 'less the withholding');
}

// The preview samples an issued sales invoice: no purchase, return, proforma,
// draft or cancelled one.
function test_erp_invoice_template_sample_query_takes_an_issued_sales_invoice()
{
	$sql = preg_replace('/\s+/', ' ', erp_invoice_template_sample_query());

	pg_assert_contains("direction = 'sales'", $sql, 'a sales invoice');
	pg_assert_contains("doc_type = 'invoice'", $sql, 'an invoice, not a return or proforma');
	pg_assert_contains("status NOT IN ('draft', 'cancelled')", $sql, 'issued, not a draft or cancelled');
	pg_assert_contains('ORDER BY id DESC LIMIT 1', $sql, 'the newest of them');
}

// The preview endpoint takes its sample from that query.
function test_erp_invoice_pdf_preview_uses_the_sample_query()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_erp_invoice_pdf.php');

	pg_assert_contains('db_value(erp_invoice_template_sample_query())', $source, 'the preview sample');
	pg_assert_true(strpos($source, 'SELECT id FROM erp_invoices ORDER BY id DESC LIMIT 1') === false, 'not simply the newest row');
}
