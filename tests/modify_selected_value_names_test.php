<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for the Modify Selected (Products) popup
 * (includes/templates/edit_products.php): the price change amount, the new
 * tax rate and the inventory quantity boxes carry a visible label of their
 * own, and the price box no longer offers a fixed comma-decimal example.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function modify_selected_value_names_source()
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/includes/templates/edit_products.php');
}

function test_modify_selected_value_names_price_amount()
{
	$php = modify_selected_value_names_source();

	pg_assert_contains('<label for="price_value" id="price_value_label" class="form-label"><?=h(lang(\'Amount\'))?></label>', $php);
	pg_assert_true(strpos($php, "12,30") === false, 'the price box still offers a fixed comma-decimal example');
	// The label follows the method: an amount in the base currency or a percent.
	pg_assert_contains("price_value_label.textContent = <?=json_encode(lang('Amount'))?>;", $php);
	pg_assert_contains("price_value_label.textContent = <?=json_encode(lang('Percent'))?>;", $php);
}

function test_modify_selected_value_names_tax_rate()
{
	$php = modify_selected_value_names_source();

	pg_assert_contains('<label for="tax_rate_value" class="form-label"><?=h(lang(\'New Tax Rate\'))?></label>', $php);
	pg_assert_contains("'aria-describedby' => 'tax_rate_value_hint',", $php);
	pg_assert_contains('<div class="form-text" id="tax_rate_value_hint">', $php);
}

function test_modify_selected_value_names_inventory_quantity()
{
	$php = modify_selected_value_names_source();

	pg_assert_contains('<label for="inventory_quantity" class="form-label"><?=h(lang(\'Quantity\'))?></label>', $php);
}
