<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for the ERP account card's phone keyboards: the e-mail box
 * (e-mail keyboard, kept type=text because imported addresses are not
 * checked), the phone box (tel) and the postcode (numeric for a Turkish
 * account, following the country select like the tax number).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function erp_account_form_keyboards_source()
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/includes/erp/account_form.php');
}

function test_erp_account_form_keyboards_email()
{
	pg_assert_contains("'type' => 'text', 'id' => 'email', 'name' => 'email',\n                        'class' => 'form-control', 'maxlength' => '255', 'autocomplete' => 'off',\n                        'inputmode' => 'email', 'spellcheck' => 'false', 'autocapitalize' => 'off')", erp_account_form_keyboards_source());
}

function test_erp_account_form_keyboards_phone()
{
	pg_assert_contains("'type' => 'text', 'id' => 'phone', 'name' => 'phone',\n                        'class' => 'form-control', 'maxlength' => '50', 'autocomplete' => 'off', 'inputmode' => 'tel')", erp_account_form_keyboards_source());
}

function test_erp_account_form_keyboards_postcode()
{
	pg_assert_contains("'type' => 'text', 'id' => 'postcode', 'name' => 'postcode',\n                        'class' => 'form-control', 'maxlength' => '20',\n                        'autocomplete' => 'off'), \$is_tr ? array('inputmode' => 'numeric') : array()))", erp_account_form_keyboards_source());
}

function test_erp_account_form_keyboards_country_toggle()
{
	pg_assert_contains('["tax_number", "postcode"].forEach(function (id) {', erp_account_form_keyboards_source());
	pg_assert_contains('input.setAttribute("inputmode", "numeric");', erp_account_form_keyboards_source());
}
