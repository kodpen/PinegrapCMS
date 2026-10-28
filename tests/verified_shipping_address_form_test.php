<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the verified shipping address form shared by the create and
 * the edit screens (includes/forms/verified_shipping_address_form.php):
 * the state options, the saved values, the logged name, and that both
 * screens use the shared form.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/verified_shipping_address_form.php');

/**
 * The liveform reads the values helper makes, over a plain array of values.
 */
class pg_test_verified_shipping_address_liveform
{
	public $values = array();

	public function __construct($values)
	{
		$this->values = $values;
	}

	public function get_field_value($name)
	{
		return isset($this->values[$name]) ? $this->values[$name] : '';
	}
}

function test_verified_shipping_address_form_state_options()
{
	$states = array('Arizona' => '3', 'Texas' => '7');

	pg_assert_same(
		'<option value="3">Arizona</option><option value="7" selected>Texas</option>',
		pg_verified_shipping_address_form_state_options($states, '7'),
		'the current state is selected'
	);
	pg_assert_same(
		'<option value="3">Arizona</option><option value="7">Texas</option>',
		pg_verified_shipping_address_form_state_options($states, ''),
		'no state selected when none is set'
	);
	pg_assert_same('', pg_verified_shipping_address_form_state_options(array(), '7'), 'no states, no options');
}

function test_verified_shipping_address_form_values()
{
	$liveform = new pg_test_verified_shipping_address_liveform(array(
		'company' => 'Acme',
		'address_1' => '1 Main St',
		'city' => 'Phoenix',
		'state_id' => 3,
		'zip_code' => '85001',
		'submit_save' => 'Save',
	));

	pg_assert_same(
		array('company' => 'Acme', 'address_1' => '1 Main St', 'address_2' => '', 'city' => 'Phoenix', 'state_id' => '3', 'zip_code' => '85001'),
		pg_verified_shipping_address_form_values($liveform),
		'the six columns in save order, as strings, nothing else'
	);
}

function test_verified_shipping_address_form_name()
{
	pg_assert_same('Acme', pg_verified_shipping_address_form_name('Acme', '1 Main St'), 'the company names the address');
	pg_assert_same('1 Main St', pg_verified_shipping_address_form_name('', '1 Main St'), 'address 1 without a company');
}

function test_verified_shipping_address_form_screens_use_shared_form()
{
	foreach (array('add_verified_shipping_address.php', 'edit_verified_shipping_address.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_contains('includes/forms/verified_shipping_address_form.php', $source, $file . ' loads the form');
		pg_assert_contains('pg_verified_shipping_address_form_card($liveform)', $source, $file . ' prints the shared card');
		pg_assert_contains('pg_verified_shipping_address_form_validate($liveform)', $source, $file . ' checks the required fields the shared way');
		pg_assert_contains('pg_verified_shipping_address_form_values($liveform)', $source, $file . ' saves the shared values');
		pg_assert_contains('validate_token_field()', $source, $file . ' checks the token');
		pg_assert_contains('get_token_field()', $source, $file . ' prints the token');
		pg_assert_contains('validate_ecommerce_access($user)', $source, $file . ' checks commerce access');
		pg_assert_false(strpos($source, 'get_state_options()') !== false, $file . ' keeps no copy of the card');
	}
}
