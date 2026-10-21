<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the tax number a checkout writes onto a contact card that is
 * not updated from the billing block (pg_checkout_contact_tax_fill(),
 * includes/fn/contacts.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A card without a number takes the one typed at checkout, with its office.
function test_checkout_contact_tax_fill_empty_card()
{
	$empty = array('tax_number' => '', 'tax_office' => '');

	pg_assert_same(
		array('tax_number' => '3130557669', 'tax_office' => 'Boğaziçi Kurumlar'),
		pg_checkout_contact_tax_fill($empty, ' 313 055.76-69 ', ' Boğaziçi Kurumlar '),
		'separators go, both fields written'
	);
	pg_assert_same(
		array('tax_number' => '10000000146'),
		pg_checkout_contact_tax_fill(array('tax_number' => '', 'tax_office' => 'Kadıköy'), '10000000146', ''),
		'no office typed: the card keeps its own'
	);
	pg_assert_same(20, mb_strlen(pg_checkout_contact_tax_fill($empty, str_repeat('7', 30), '')['tax_number']), 'cut to the column');
}

// A card that names a number keeps it, and nothing typed writes nothing.
function test_checkout_contact_tax_fill_leaves_a_numbered_card()
{
	pg_assert_same(array(), pg_checkout_contact_tax_fill(array('tax_number' => '1234567890', 'tax_office' => 'Ankara'), '3130557669', 'Boğaziçi Kurumlar'), 'card has a number');
	pg_assert_same(array(), pg_checkout_contact_tax_fill(array('tax_number' => '', 'tax_office' => ''), ' ', 'Boğaziçi Kurumlar'), 'nothing typed');
	pg_assert_same(array(), pg_checkout_contact_tax_fill(array(), '', ''), 'no card fields, nothing typed');
}
