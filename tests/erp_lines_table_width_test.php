<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the minimum width of the ERP document line tables: on a phone the
 * table scrolls sideways instead of squeezing the quantity and unit boxes.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The invoice editor (#4434) and the waybill editor (#4696) give their line
// table a minimum width, so the Quantity and Unit cells keep their widths.
function test_erp_lines_table_width_has_a_minimum()
{
	foreach (array(
		'includes/erp/invoice_form.php',
		'includes/erp/waybill_form.php',
	) as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		pg_assert_true(
			preg_match('/<table[^>]*data-erp-lines-table[^>]*style="min-width:\d+rem"/', $source) === 1,
			$file . ' gives the line table a minimum width');
	}
}
