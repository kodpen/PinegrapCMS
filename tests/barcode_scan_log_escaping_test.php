<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source checks for the Last Scans table on the stock increase and decrease
 * screens: the scanned product's name, short description and quantities go
 * into the new row as text, so markup stored in a product name shows as
 * typed; only the server-built scanned_at <time> element goes in as HTML.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_barcode_scan_log_row_as_text()
{
	foreach (array('barcode_increase_inventory.php', 'barcode_decrease_inventory.php') as $file) {
		$source = str_replace("\\'", "'", (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file));
		foreach (array('name', 'short_description', 'inventory_quantity', 'quantity', 'new_inventory_quantity') as $field) {
			pg_assert_true(
				!preg_match('/"\s*\+\s*response\.data\.' . $field . '\s*\+\s*"/', $source),
				$file . ' does not concatenate response.data.' . $field . ' into markup'
			);
			pg_assert_true(
				strpos($source, '.text(response.data.' . $field . ')') !== false,
				$file . ' writes response.data.' . $field . ' with .text()'
			);
		}
		pg_assert_true(
			strpos($source, '.html(response.data.scanned_at)') !== false,
			$file . ' keeps the scanned_at <time> element as HTML'
		);
		pg_assert_true(
			strpos($source, '$("#action_logs tbody").prepend(row)') !== false,
			$file . ' prepends the row it built'
		);
	}
}
