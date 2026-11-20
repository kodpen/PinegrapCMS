<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A cart line the cart itself lowers (the stock check, the fixed or maximum
 * quantity, a reservation's remaining spots, a bundle's stock pool) loses
 * the File Upload answers and files of the units above its new quantity,
 * as a line the shopper lowers does (includes/fn/product_form_uploads.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/product_form_uploads.php');
require_once(__DIR__ . '/helpers/source_reader.php');

function test_product_form_uploads_lowered_quantity_not_down_calls_nothing()
{
	// No database in the runner: a call that reached the drop would fail.
	pg_assert_false(pg_pfu_quantity_lowered(5, 3, 3), 'an unchanged quantity dropped files');
	pg_assert_false(pg_pfu_quantity_lowered(5, 3, 4), 'a raised quantity dropped files');
	pg_assert_false(pg_pfu_quantity_lowered(5, '2', '10'), 'quantities compared as text');
	pg_assert_false(pg_pfu_quantity_lowered(0, 3, 1), 'a line with no id reached the drop');
}

function test_product_form_uploads_lowered_quantity_every_cart_path_drops()
{
	$paths = array(
		'check_quantity' => 2,
		'check_inventory' => 1,
		'check_reservations' => 1,
		'pg_bundle_check_inventory' => 1,
	);

	foreach ($paths as $function => $calls) {
		$src = pg_test_function_source($function);
		pg_assert_same($calls, substr_count($src, 'pg_pfu_quantity_lowered('), $function . '() lowers a quantity and keeps the files above it');
		pg_assert_contains("require_once(PG_FUNCTIONS_DIR . '/includes/fn/product_form_uploads.php');", $src, $function . '() calls the drop without loading it');
	}
}
