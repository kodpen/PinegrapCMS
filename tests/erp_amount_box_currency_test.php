<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the currency named beside the amount on ERP Transfer and Record
 * a Receipt: with foreign currency on, the box names the currency the money
 * is entered in (the till's on a transfer, the chosen one on a receipt),
 * and a refused receipt form keeps its choice.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function erp_amount_box_currency_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

// A transfer is recorded in the From till's currency, so each till option
// names its currency and the amount box says so instead of the base symbol.
function test_erp_transfer_amount_box_names_the_till_currency()
{
	$src = erp_amount_box_currency_source('add_erp_transfer.php');

	pg_assert_contains('SELECT id, name, currency FROM erp_cash_accounts', $src, 'the till list reads each till\'s currency');
	pg_assert_contains("\$till_options[h(\$till['name'] . (\$fx_on ? (' (' . strtoupper(trim((string) \$till['currency'])) . ')') : ''))]", $src, 'each till option names its currency with FX on, escaped once');
	pg_assert_contains("<span class=\"form-adorn-text\" aria-hidden=\"true\">' . (\$fx_on ? lang('in the till currency') : BASE_CURRENCY_SYMBOL) . '</span>", $src, 'the amount box names the till currency with FX on');
}

// The receipt's adornment comes from the form's currency value, not the
// first-visit preset, and follows the Currency select.
function test_erp_receipt_amount_box_follows_the_currency()
{
	$src = erp_amount_box_currency_source('add_erp_receipt.php');

	pg_assert_true(strpos($src, "h(\$preset_currency) : BASE_CURRENCY_SYMBOL) . '</span>") === false, 'the adornment is not the static preset currency');
	pg_assert_contains('<span class="form-adorn-text" id="erp_receipt_currency_label" aria-hidden="true">', $src, 'the adornment has an id a script can fill');
	pg_assert_contains("\$liveform->get_field_value('currency')", substr($src, 0, (int) strpos($src, 'erp_receipt_currency_label')), 'the label reads the form\'s currency value');
	pg_assert_contains('document.getElementById("erp_receipt_currency_label").textContent = currency.value;', $src, 'the label follows the Currency select');
}
