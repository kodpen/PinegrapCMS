<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for ERP fields that open the right phone keyboard: the expense
 * supplier tax number (numeric for a Turkish store), the till IBAN (capital
 * letters, no spellcheck) and the send-by-e-mail recipient boxes (e-mail
 * keyboard, kept type=text because they take a list).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function erp_input_keyboards_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

function test_erp_input_keyboards_expense_tax_number()
{
	$source = erp_input_keyboards_source('add_erp_expense.php');

	pg_assert_contains("'name' => 'supplier_tax_number', 'class' => 'form-control', 'maxlength' => '32', 'autocomplete' => 'off', 'inputmode' => ((pg_erp_store_country() === 'TR') ? 'numeric' : '')", $source);
}

function test_erp_input_keyboards_till_iban()
{
	$source = erp_input_keyboards_source('includes/erp/till_form.php');

	pg_assert_contains("'class' => 'form-control', 'maxlength' => '34', 'autocomplete' => 'off',\n                        'spellcheck' => 'false', 'autocapitalize' => 'characters')", $source);
}

function test_erp_input_keyboards_mail_recipient()
{
	foreach (array('edit_erp_invoice.php', 'edit_erp_quote.php', 'edit_erp_purchase_order.php') as $path) {
		$source = erp_input_keyboards_source($path);
		pg_assert_contains('<input type="text" class="form-control" id="mail_to" name="mail_to" value="', $source, $path . ': still a list box');
		pg_assert_contains('maxlength="500" inputmode="email" spellcheck="false" autocapitalize="off" autocomplete="off" required />', $source, $path);
	}
}
