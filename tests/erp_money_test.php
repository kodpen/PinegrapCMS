<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the ERP money helpers (includes/erp/money.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A Turkish-formatted amount: point groups thousands, comma marks decimals.
function test_erp_kurus_reads_turkish_grouping()
{
	pg_assert_same(123456, erp_kurus('1.234,56'));
}

// Three digits behind the only separator make it a thousands separator.
function test_erp_kurus_reads_three_digits_as_thousands()
{
	pg_assert_same(123400, erp_kurus('1.234'));
}

// Taking 18% tax out of a gross amount leaves net and tax that add back up.
function test_erp_split_gross_takes_tax_out()
{
	pg_assert_same(array('net' => 10000, 'tax' => 1800), erp_split_gross(11800, 18));
}

// An even split that does not divide gives the left-over kurus to the first line.
function test_erp_allocate_hands_out_the_remainder()
{
	pg_assert_same(array(34, 33, 33), erp_allocate(100, array(1, 1, 1)));
}
