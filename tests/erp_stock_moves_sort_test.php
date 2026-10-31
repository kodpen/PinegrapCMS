<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source scan of the ERP stock screen (erp_stock.php): the movements table
 * sorts in the browser, and its Stock cell shows the quantity inside words
 * ("+1.250, stock now 3.400"), so the cell must carry the signed raw
 * quantity in data-sort or the column would sort as text.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_erp_stock_moves_stock_cell_sorts_by_signed_quantity()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/erp_stock.php');

	pg_assert_true(preg_match('/<td[^>]*data-sort="[^"]*\$move\[\'quantity\'\][^>]*>\'\s*\.\s*h\(erp_stock_effect_text\(\$move\)\)/', $source) === 1, 'the Stock cell carries data-sort with the move quantity');
	pg_assert_true(strpos($source, "(((string) \$move['direction'] === 'in') ? 1 : -1)") !== false, 'an outgoing move sorts as a negative quantity');
}
