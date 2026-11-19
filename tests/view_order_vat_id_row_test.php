<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The order screen's EU VAT ID row. Its value was read from $row where the
 * card is drawn, and by then $row held the last line's form count (or the end
 * of a result set): the row showed no VAT id under its reverse charge note
 * and the screen wrote a PHP warning. The id is now taken with the order's
 * other columns.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_view_order_vat_id_read_with_the_order()
{
	$source = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/view_order.php');

	$capture = strpos($source, "\$order_vat_id = (string) (\$row['vat_id'] ?? '');");
	$note = strpos($source, '$order_vat_note = pg_vat_order_note($row);');
	$reused = strpos($source, 'while ($row = mysqli_fetch_assoc($result))');

	pg_assert_true($capture !== false, 'the VAT id is taken with the order columns');
	pg_assert_true(($note !== false) && ($reused !== false), 'markers');
	pg_assert_true(($capture < $reused) && ($note < $reused), 'before $row is reused');
	pg_assert_same(1, substr_count($source, "\$row['vat_id']"), 'no other read of the column from $row');
	pg_assert_contains("pg_order_view_row(lang('EU VAT ID'), ((\$order_vat_note !== '') ? h(\$order_vat_id)", $source, 'the row prints the captured id');
}
