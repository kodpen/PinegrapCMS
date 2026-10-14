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

// The add_to_cart API action reads a JSON body, where the quantity can arrive as
// a list or an object. It is answered as an invalid quantity before any cart is
// opened, without the "Array to string conversion" warning it used to write.
function test_order_form_array_add_to_cart_quantity_list()
{
	require_once(PG_FUNCTIONS_DIR . '/add_to_cart.php');

	foreach (array(array(2), array('a' => 2), array()) as $quantity) {
		$response = add_to_cart(array('product' => array('id' => 70), 'quantity' => $quantity));

		pg_assert_same('error', $response['status'], 'quantity ' . json_encode($quantity));
		pg_assert_same(lang('Please enter a valid quantity.'), $response['message'], 'quantity ' . json_encode($quantity) . ': message');
	}
}

// The rest of the body is read before the cart is opened: a product id that is
// not one value names no product (a string product or an id list stopped the
// request with a TypeError), and a form name or notice that is not text, or is
// not sent at all (the storefront button sends no form), is left out.
function test_order_form_array_add_to_cart_body_values()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/add_to_cart.php');

	$guard = strpos($source, "\$product_id = is_array(\$request['product'] ?? null) ? (\$request['product']['id'] ?? '') : '';");
	pg_assert_true(($guard !== false) && ($guard < strpos($source, 'initialize_order();')), 'product id read before the cart is opened');
	pg_assert_contains("\$product_id = (is_string(\$product_id) || is_int(\$product_id)) ? \$product_id : '';", $source, 'product id is one value');
	pg_assert_contains("add_order_item(\$product_id, \$quantity,", $source, 'checked id added');
	pg_assert_contains("\$form_name = is_string(\$request['form'] ?? null) ? \$request['form'] : '';", $source, 'form name');
	pg_assert_contains("\$notice = is_string(\$request['notice'] ?? null) ? \$request['notice'] : '';", $source, 'notice');
	pg_assert_false(strpos($source, "\$request['product']['id'], \$quantity") !== false, 'raw product id no longer added');
	pg_assert_false(strpos($source, "if (\$request['form'])") !== false, 'raw form no longer read');
}

// The designer's cart widget posts to cart_action.php. Its one-value fields
// posted as an array count as not given: the coupon, the quick add fields, the
// saved item and the checkout address are dropped before the order is opened; a
// row's quantity or donation amount posted as a list leaves the row as it is
// ((int) made the quantity 1, the amount parsed as 0 removed the row); a product
// form field keeps what pg_cf_posted_value() keeps; the recurring schedule
// fields read a list as blank.
function test_order_form_array_cart_action()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/cart_action.php');

	$single = strpos($source, "pg_request_single_values(array('special_offer_code', 'offline_payment_allowed', 'quick_add_product_id', 'quick_add_quantity', 'quick_add_amount', 'quick_add_ship_to', 'quick_add_add_name', 'save_item_id', 'checkout_to'));");
	pg_assert_true(($single !== false) && ($single < strpos($source, 'initialize_order();')), 'one-value fields before the order is opened');

	$skip = strpos($source, 'if (!is_string($q)) continue;');
	pg_assert_true(($skip !== false) && ($skip < strpos($source, '$q   = (int)$q;')), 'quantity list skipped before the cast');

	$skip = strpos($source, 'if (!is_string($raw)) continue;');
	pg_assert_true(($skip !== false) && ($skip < strpos($source, '$amount = pg_parse_amount($raw);')), 'donation list skipped before it is parsed');

	pg_assert_contains("SELECT type, multiple FROM form_fields", $source, 'product form field carries multiple');
	pg_assert_contains("\$raw = pg_cf_posted_value(\$raw, array('type' => \$f_type, 'multiple' => is_array(\$f_meta) ? \$f_meta['multiple'] : 0));", $source, 'product form value kept as pg_cf_posted_value() keeps it');

	foreach (array('_p' => '_n_period', '_c' => '_n_payments', '_d' => '_n_start') as $variable => $name) {
		pg_assert_contains('$' . $variable . ' = trim(is_string($_POST[$' . $name . '] ?? null) ? $_POST[$' . $name . '] : \'\');', $source, 'recurring ' . $name);
	}

	pg_assert_false(strpos($source, "trim((string)(\$_POST[") !== false, 'no (string) cast of a posted value left');
}

// A pending offer's recipient fields, read by every cart and the express order,
// are one value each.
function test_order_form_array_pending_offer_recipient()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/ecommerce.php');

	pg_assert_contains("\$ship_to = is_string(\$ship_to) ? \$ship_to : '';", $source, 'ship to');
	pg_assert_contains("\$add_name = is_string(\$add_name) ? \$add_name : '';", $source, 'add name');
}

// Warnings that every order wrote, unrelated to arrays: an address in no tax
// zone of its country left $valid_tax_zone_id undefined; an express order
// recipient without a salutation read a name that was never started; and an
// express order's receipt read the billing information and shipping address
// pages from a session that never named them. The values the page gets are the
// same empty ones as before.
function test_order_form_array_order_warnings()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/forms.php');
	$function = substr($source, strpos($source, 'function get_tax_rate_for_address('));
	$start = strpos($function, "\$valid_tax_zone_id = '';");
	pg_assert_true(($start !== false) && ($start < strpos($function, 'SELECT tax_zone_id')), 'tax zone id starts empty');

	$source = file_get_contents(PG_FUNCTIONS_DIR . '/get_express_order.php');
	$start = strpos($source, "                \$recipient['name'] = '';");
	pg_assert_true(($start !== false) && ($start < strpos($source, "\$recipient['name'] = \$recipient['salutation'];")), 'recipient name starts empty');

	$source = file_get_contents(PG_FUNCTIONS_DIR . '/get_order_receipt.php');
	pg_assert_same(2, substr_count($source, "\$_SESSION['ecommerce']['billing_information_page_id'] ?? ''"), 'billing information page');
	pg_assert_same(2, substr_count($source, "\$custom_field_1_label = \$row['custom_field_1_label'] ?? '';"), 'custom field 1 label');
	pg_assert_same(2, substr_count($source, "\$custom_field_2_label = \$row['custom_field_2_label'] ?? '';"), 'custom field 2 label');
	pg_assert_contains("\$_SESSION['ecommerce']['shipping_address_and_arrival_page_id'] ?? ''", $source, 'shipping address page');
}
