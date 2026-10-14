<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for request values that arrive as arrays in the order flow (express
 * order, shipping address and arrival, shipping method, billing information,
 * order preview, shopping cart, and the add to cart forms of the catalog detail
 * and order form pages): pg_cf_posted_value() and pg_cf_keep_posted_value()
 * (includes/fn/custom_form.php), which submit_custom_form() and the product
 * forms of the cart and the express order apply to each field, the parts of
 * pg_liveform_single_values() (includes/fn/forms.php) the order scripts rely
 * on: a card number read as stored, and a total emptied to null, and the
 * scripts' own lists of single-value fields.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Stands in for a liveform whose card number is stored encrypted: reading it
// through get_field_value() decrypts it, which an array cannot go through.
function _test_order_form_array_liveform($values)
{
	return new class($values) {
		public $values;

		public function __construct($values)
		{
			$this->values = $values;
		}

		public function get_field($field)
		{
			return array_key_exists($field, $this->values) ? array('value' => $this->values[$field]) : null;
		}

		public function get_field_value($field)
		{
			if (($field === 'card_number') && is_array($this->values[$field] ?? null)) {
				throw new TypeError('decrypt_credit_card_number(): array given');
			}

			return isset($this->values[$field]) ? $this->values[$field] : null;
		}

		public function assign_field_value($field, $value)
		{
			$this->values[$field] = $value;
		}
	};
}

// A field that holds one value keeps a string and loses an array.
function test_order_form_array_posted_value_single_fields()
{
	foreach (array('text box', 'text area', 'date', 'date and time', 'time', 'email address', 'radio button') as $type) {
		$field = array('type' => $type, 'multiple' => '0');

		pg_assert_same('', pg_cf_posted_value(array('2026-10-14'), $field), $type . ': array');
		pg_assert_same('2026-10-14', pg_cf_posted_value('2026-10-14', $field), $type . ': string');
		pg_assert_same(null, pg_cf_posted_value(null, $field), $type . ': not posted');
	}

	pg_assert_same('', pg_cf_posted_value(array('A'), array('type' => 'pick list', 'multiple' => '0')), 'single pick list');
}

// A check box group and a multi-select keep their list as it came, so a normal
// post is stored exactly as before.
function test_order_form_array_posted_value_lists_kept()
{
	$list = array('A', 'C');

	pg_assert_same($list, pg_cf_posted_value($list, array('type' => 'check box', 'multiple' => '0')), 'check box group');
	pg_assert_same($list, pg_cf_posted_value($list, array('type' => 'pick list', 'multiple' => '1')), 'multi-select');
	pg_assert_same('A', pg_cf_posted_value('A', array('type' => 'check box', 'multiple' => '0')), 'one check box');
}

// An entry of a list that is itself a list (name[0][]=x) is dropped; when none
// is left the field counts as not given, which a required field reports.
function test_order_form_array_posted_value_nested_entries_dropped()
{
	$field = array('type' => 'check box', 'multiple' => '0');

	pg_assert_same(array(1 => 'B'), pg_cf_posted_value(array(array('A'), 'B'), $field), 'mixed');
	pg_assert_same('', pg_cf_posted_value(array(array('A')), array('type' => 'pick list', 'multiple' => '1')), 'only nested');
}

// A card number posted as an array is emptied without being read through
// get_field_value(), and a string card number is left alone.
function test_order_form_array_single_values_card_number()
{
	$liveform = _test_order_form_array_liveform(array('card_number' => array('4111111111111111')));

	pg_liveform_single_values($liveform, array('card_number'));

	pg_assert_same('', $liveform->values['card_number'], 'array emptied');

	$liveform = _test_order_form_array_liveform(array('card_number' => 'pgc1:cipher'));

	pg_liveform_single_values($liveform, array('card_number'));

	pg_assert_same('pgc1:cipher', $liveform->values['card_number'], 'string kept');
}

// A total, which the order compares as a number, is emptied to null: '' would
// stop that arithmetic as the array did.
function test_order_form_array_single_values_total_to_null()
{
	$liveform = _test_order_form_array_liveform(array('total' => array('299.95'), 'total_with_surcharge' => '305.00'));

	pg_liveform_single_values($liveform, array('total', 'total_with_surcharge'), null);

	pg_assert_same(null, $liveform->values['total'], 'array becomes null');
	pg_assert_same('305.00', $liveform->values['total_with_surcharge'], 'string kept');
}

// A product form field keeps what pg_cf_posted_value() keeps of the posted value:
// a list where one value belongs is replaced by '', a list that belongs is left
// as posted, and a field not posted stays absent.
function test_order_form_array_keep_posted_value()
{
	$liveform = _test_order_form_array_liveform(array(
		'name' => array('Ali'),
		'email' => 'guest@example.test',
		'days' => array('Mon', 'Wed'),
		'colors' => array('Red', 'Blue'),
		'size' => array('M'),
	));

	pg_cf_keep_posted_value($liveform, 'name', array('type' => 'text box', 'multiple' => '0'));
	pg_cf_keep_posted_value($liveform, 'email', array('type' => 'email address', 'multiple' => '0'));
	pg_cf_keep_posted_value($liveform, 'days', array('type' => 'check box', 'multiple' => '0'));
	pg_cf_keep_posted_value($liveform, 'colors', array('type' => 'pick list', 'multiple' => '1'));
	pg_cf_keep_posted_value($liveform, 'size', array('type' => 'pick list', 'multiple' => '0'));
	pg_cf_keep_posted_value($liveform, 'needs', array('type' => 'text area', 'multiple' => '0'));

	pg_assert_same('', $liveform->values['name'], 'text box array');
	pg_assert_same('guest@example.test', $liveform->values['email'], 'string kept');
	pg_assert_same(array('Mon', 'Wed'), $liveform->values['days'], 'check box group');
	pg_assert_same(array('Red', 'Blue'), $liveform->values['colors'], 'multi-select');
	pg_assert_same('', $liveform->values['size'], 'single pick list array');
	pg_assert_false(array_key_exists('needs', $liveform->values), 'not posted');
}

// Each order script empties a single-value field posted as an array before it
// reads it: the shipping method step, the add to cart forms, and the cart and
// express order fields of each item (quantity, recurring schedule, gift card,
// product form).
function test_order_form_array_scripts_single_values()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/shipping_method.php');
	$normalised = strpos($source, "foreach (array('ship_to_id', 'page_id', 'next_page_id') as \$pg_post_field)");
	pg_assert_true(($normalised !== false) && ($normalised < strpos($source, 'FROM ship_tos')), 'shipping method: ids before the recipient query');
	$method = strpos($source, "pg_liveform_single_values(\$liveform, array('shipping_method'));");
	pg_assert_true(($method !== false) && ($method < strpos($source, "validate_required_field('shipping_method', lang('Please select a shipping method.'));")), 'shipping method: method before it is required');

	$source = file_get_contents(PG_FUNCTIONS_DIR . '/catalog_detail.php');
	$fields = strpos($source, "pg_liveform_single_values(\$liveform, array('product_id', 'quantity', 'ship_to', 'add_name', 'page_id', 'current_url', 'next_url', 'next_page_id'));");
	pg_assert_true(($fields !== false) && ($fields < strpos($source, 'FROM products')), 'catalog detail: fields before the product query');

	$source = file_get_contents(PG_FUNCTIONS_DIR . '/order_form.php');
	$fields = strpos($source, "pg_liveform_single_values(\$liveform, array('page_id', 'product_id', 'quantity', 'ship_to', 'add_name'));");
	pg_assert_true(($fields !== false) && ($fields < strpos($source, 'FROM order_form_pages')), 'order form: fields before the page query');
	pg_assert_contains("pg_liveform_single_values(\$liveform, array('product_' . \$product['id'], 'donation_' . \$product['id'], 'recurring_payment_period_' . \$product['id'], 'recurring_number_of_payments_' . \$product['id'], 'recurring_start_date_' . \$product['id']));", $source, 'order form: list layout fields');

	foreach (array('shopping_cart', 'express_order') as $script) {
		$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $script . '.php');

		pg_assert_contains("pg_liveform_single_values(\$liveform, array('recurring_payment_period_' . \$order_item['id'], 'recurring_number_of_payments_' . \$order_item['id'], 'recurring_start_date_' . \$order_item['id']));", $source, $script . ': recurring schedule');
		pg_assert_contains("pg_liveform_single_values(\$liveform, array(\$pg_prefix . 'recipient_email_address', \$pg_prefix . 'from_name', \$pg_prefix . 'message', \$pg_prefix . 'delivery_date'));", $source, $script . ': gift card');
		pg_assert_contains('pg_cf_keep_posted_value($liveform, $html_field_name, $form_field);', $source, $script . ': product form');
		pg_assert_true(preg_match("/wysiwyg,\\s+multiple\\s+FROM form_fields\\s+WHERE \\(product_id = /", $source) === 1, $script . ': product form fields carry multiple');
	}

	$source = file_get_contents(PG_FUNCTIONS_DIR . '/shopping_cart.php');
	pg_assert_contains("if (!\$pg_item_posted_as_list && (isset(\$_POST['donations'][\$order_item['id']]) == true)) {", $source, 'shopping cart: donation as a list kept');
	pg_assert_contains('} elseif (!$pg_item_posted_as_list) {', $source, 'shopping cart: quantity as a list kept');
}
