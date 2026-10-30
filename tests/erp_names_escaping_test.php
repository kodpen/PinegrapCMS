<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for names with apostrophes, quotes and & on the ERP screens:
 * the invoice and waybill line editors hand liveform raw values (it escapes
 * a field's value itself, so a pre-escaped one is shown and saved back as
 * entities), and the till and country option labels are escaped (liveform
 * prints select labels as they are).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function erp_names_escaping_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

// The body of one function, from its declaration to the next one.
function erp_names_escaping_function($source, $name)
{
	$start = strpos($source, 'function ' . $name . '(');
	pg_assert_true($start !== false, $name . ' exists');
	$end = strpos($source, "\nfunction ", $start + 1);

	return ($end === false) ? substr($source, $start) : substr($source, $start, $end - $start);
}

function test_erp_names_escaping_line_values_raw()
{
	$files = array(
		'includes/erp/invoice_form.php' => 'erp_invoice_form_line_row',
		'includes/erp/waybill_form.php' => 'erp_waybill_form_line_row',
	);

	foreach ($files as $path => $function) {
		$body = erp_names_escaping_function(erp_names_escaping_source($path), $function);
		pg_assert_true(strpos($body, "'value' => h(") === false, $function . ' hands liveform raw values');
		pg_assert_contains("'value' => \$line['description'],", $body, $function . ' description raw');
		pg_assert_contains("'value' => \$line['product_name'],", $body, $function . ' product name raw');
		pg_assert_contains("'value' => \$unit_code,", $body, $function . ' unit code raw');
	}
}

function test_erp_names_escaping_option_labels()
{
	pg_assert_contains("\$till_options[h(\$till['name'])] = ", erp_names_escaping_source('add_erp_transfer.php'), 'till names escaped');
	pg_assert_contains("\$country_options[h((string) \$country_row['name'])] = ", erp_names_escaping_source('includes/erp/account_form.php'), 'country names escaped');
}
