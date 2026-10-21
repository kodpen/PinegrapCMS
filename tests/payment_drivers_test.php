<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the payment driver layer (includes/payments/driver.php,
 * includes/fn/payments.php): driver keys and definitions, the answer shape,
 * the amount helpers against the legacy gateways' format, a registered
 * driver's operations and capabilities, the settings fields and what a post
 * of the Ecommerce card decides. No service and no database is reached: the
 * test drivers below live in this file and are registered with its path.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A card driver that defines prepare, authorize and capture.
function pg_payment_pgtestcard_capabilities()
{
	return array('wallets', 'installments', 'teleport', 'wallets');
}

function pg_payment_pgtestcard_fields()
{
	return array(
		array('name' => 'merchant_id', 'label' => 'Merchant ID', 'required' => true),
		array('name' => 'secret_key', 'type' => 'secret', 'label' => 'Secret Key', 'required' => true),
		array('name' => 'mode', 'type' => 'select', 'label' => 'Mode', 'options' => array('test' => 'Test', 'live' => 'Live'), 'default' => 'test'),
	);
}

function pg_payment_pgtestcard_prepare($order, $amount)
{
	return array('success' => true, 'client' => array('arguments' => func_num_args(), 'amount' => $amount));
}

function pg_payment_pgtestcard_authorize($order, $amount, $payment)
{
	throw new \RuntimeException('connection reset');
}

function pg_payment_pgtestcard_capture($order, $amount, $transaction_id)
{
	return array('success' => true, 'status' => 'paid', 'transaction_id' => $transaction_id, 'amount' => (string) $amount);
}

// A redirect driver with no fields and a capabilities function that throws.
function pg_payment_pgtestredirect_capabilities()
{
	throw new \RuntimeException('broken');
}

function payment_drivers_test_register()
{
	static $done = false;

	if (!$done) {
		pg_payment_driver_register('pgtestcard', array('label' => 'Test Card', 'kind' => 'card', 'gateway' => 'Pg Test Gateway', 'file' => __FILE__));
		pg_payment_driver_register('pgtestredirect', array('label' => 'Test Redirect', 'kind' => 'redirect', 'method' => 'Pay With Pg Test', 'file' => __FILE__));
		$done = true;
	}
}

function payment_drivers_test_stored($values = array(), $secrets = array(), $enabled = false, $exists = true)
{
	return array(
		'values'        => $values,
		'secrets'       => $secrets,
		'secret_stored' => ($secrets !== array()),
		'readable'      => true,
		'enabled'       => $enabled,
		'exists'        => $exists,
	);
}

function test_payment_drivers_key_rules()
{
	foreach (array('iyzico', 'paytr', 'stripe_v2', 'pay_with_x', str_repeat('a', 32)) as $key) {
		pg_assert_true(pg_payment_key_valid($key), $key);
	}

	foreach (array('a', 'Stripe', '1pay', 'pay__x', 'pay_', '_pay', 'pay-x', 'pay x', str_repeat('a', 33), 12, null) as $key) {
		pg_assert_false(pg_payment_key_valid($key), var_export($key, true));
	}
}

// The double underscore keeps a key and a field apart, so two drivers never
// post under one name.
function test_payment_drivers_input_names_do_not_collide()
{
	pg_assert_same('payment_a_b__c', pg_payment_input_name('a_b', 'c'));
	pg_assert_same('payment_a__b_c', pg_payment_input_name('a', 'b_c'));
	pg_assert_same('payment_iyzico', pg_payment_settings_key('iyzico'));
}

// The text the legacy gateways have always sent: sprintf("%01.2lf", $total / 100).
function test_payment_drivers_decimal_matches_legacy_format()
{
	foreach (array(0, 1, 5, 10, 99, 100, 101, 1999, 12345, 4714, 99999999) as $hundredths) {
		pg_assert_same(sprintf('%01.2lf', $hundredths / 100), pg_payment_decimal($hundredths), (string) $hundredths);
	}

	pg_assert_same('47.14', pg_payment_decimal('4714'));
}

// Read back the way the iyzico answers are read: rounded, never cut.
function test_payment_drivers_hundredths_rounds()
{
	pg_assert_same(1999, pg_payment_hundredths('19.99'));
	pg_assert_same(1999, pg_payment_hundredths(19.99));
	pg_assert_same(10, pg_payment_hundredths('0.1'));
	pg_assert_same(1250, pg_payment_hundredths('12.5'));
	pg_assert_same(1200, pg_payment_hundredths(12));
	pg_assert_same(340, pg_payment_hundredths(' 3.40 '));

	foreach (array('abc', '', '12,50', null, array('1'), true) as $value) {
		pg_assert_same(null, pg_payment_hundredths($value), var_export($value, true));
	}
}

function test_payment_drivers_capabilities_normalize()
{
	pg_assert_same(array('installments', 'wallets'), pg_payment_capabilities_normalize(array('wallets', 'teleport', 'installments', 'wallets', 7)));
	pg_assert_same(array(), pg_payment_capabilities_normalize('wallets'));
	pg_assert_same(array(), pg_payment_capabilities_normalize(null));
}

function test_payment_drivers_answer_without_array_is_failure()
{
	$answer = pg_payment_answer('authorize', null);

	pg_assert_false($answer['success']);
	pg_assert_true($answer['error'] !== '');
	pg_assert_same('', $answer['status']);
	pg_assert_same(null, $answer['amount']);
	pg_assert_same(array(), $answer['state']);
}

// A payment the caller cannot account for is not a success.
function test_payment_drivers_answer_success_needs_a_fitting_status()
{
	pg_assert_false(pg_payment_answer('authorize', array('success' => true))['success'], 'no status');
	pg_assert_false(pg_payment_answer('authorize', array('success' => true, 'status' => 'refunded'))['success'], 'refund status for a charge');
	pg_assert_false(pg_payment_answer('authorize', array('success' => true, 'status' => 'approved'))['success'], 'unknown status');
	pg_assert_false(pg_payment_answer('refund', array('success' => true, 'status' => 'paid'))['success'], 'charge status for a refund');
	pg_assert_false(pg_payment_answer('capture', array('success' => true, 'status' => 'authorized'))['success'], 'capture still held');

	pg_assert_same('paid', pg_payment_answer('authorize', array('success' => true, 'status' => 'paid'))['status']);
	pg_assert_same('pending', pg_payment_answer('complete', array('success' => true, 'status' => 'pending'))['status']);
	pg_assert_same('refunded', pg_payment_answer('refund', array('success' => true, 'status' => 'refunded'))['status']);
	pg_assert_same('cancelled', pg_payment_answer('cancel', array('success' => true, 'status' => 'cancelled'))['status']);
	pg_assert_true(pg_payment_answer('prepare', array('success' => true))['success'], 'prepare needs no status');
}

function test_payment_drivers_answer_success_must_be_true()
{
	foreach (array(1, '1', 'true', 'yes') as $value) {
		pg_assert_false(pg_payment_answer('capture', array('success' => $value, 'status' => 'paid'))['success'], var_export($value, true));
	}
}

function test_payment_drivers_answer_failure_has_a_sentence_and_no_status()
{
	$answer = pg_payment_answer('authorize', array('success' => false, 'status' => 'paid', 'error' => '  Card declined. '));

	pg_assert_false($answer['success']);
	pg_assert_same('', $answer['status']);
	pg_assert_same('Card declined.', $answer['error']);

	pg_assert_true(pg_payment_answer('authorize', array('success' => false))['error'] !== '', 'a failure without a sentence gets one');
	pg_assert_same('', pg_payment_answer('capture', array('success' => true, 'status' => 'paid', 'error' => 'stale'))['error'], 'a success carries no error');
}

function test_payment_drivers_answer_redirect_needs_https_or_a_page()
{
	pg_assert_false(pg_payment_answer('authorize', array('success' => true, 'status' => 'redirect'))['success'], 'nowhere to go');
	pg_assert_false(pg_payment_answer('authorize', array('success' => true, 'status' => 'redirect', 'redirect_url' => 'http://bank.example/3ds'))['success'], 'plain http');
	pg_assert_false(pg_payment_answer('authorize', array('success' => true, 'status' => 'redirect', 'redirect_url' => 'javascript:alert(1)'))['success'], 'script address');

	$answer = pg_payment_answer('authorize', array('success' => true, 'status' => 'redirect', 'redirect_url' => 'https://bank.example/3ds'));
	pg_assert_true($answer['success']);
	pg_assert_same('https://bank.example/3ds', $answer['redirect_url']);

	pg_assert_true(pg_payment_answer('authorize', array('success' => true, 'status' => 'redirect', 'html' => '<form></form>'))['success'], 'a page to output');

	$paid = pg_payment_answer('complete', array('success' => true, 'status' => 'paid', 'redirect_url' => 'https://bank.example/', 'html' => '<form></form>'));
	pg_assert_same('', $paid['redirect_url'], 'only a redirect carries an address');
	pg_assert_same('', $paid['html']);
}

function test_payment_drivers_answer_reads_amounts_and_lists()
{
	pg_assert_same(4714, pg_payment_answer('capture', array('success' => true, 'status' => 'paid', 'amount' => 4714))['amount']);
	pg_assert_same(4714, pg_payment_answer('capture', array('success' => true, 'status' => 'paid', 'amount' => '4714'))['amount']);
	pg_assert_same(null, pg_payment_answer('capture', array('success' => true, 'status' => 'paid', 'amount' => 47.14))['amount'], 'a float is not hundredths');
	pg_assert_same(null, pg_payment_answer('capture', array('success' => true, 'status' => 'paid', 'amount' => '47.14'))['amount']);

	$answer = pg_payment_answer('prepare', array('success' => true, 'client' => 'key', 'state' => array('token' => 't'), 'transaction_id' => 12));
	pg_assert_same(array(), $answer['client'], 'a list must be an array');
	pg_assert_same(array('token' => 't'), $answer['state']);
	pg_assert_same('12', $answer['transaction_id']);
}

function test_payment_drivers_answer_notify_names_the_order()
{
	pg_assert_false(pg_payment_answer('notify', array('success' => true, 'status' => 'paid'))['success']);
	pg_assert_same('1172', pg_payment_answer('notify', array('success' => true, 'status' => 'paid', 'order_reference' => '1172', 'reply' => 'OK'))['order_reference']);
	pg_assert_false(pg_payment_answer('notify', array('success' => true, 'status' => 'redirect', 'order_reference' => '1172', 'redirect_url' => 'https://x.example/'))['success'], 'a callback does not redirect');
}

function test_payment_drivers_definition_normalize()
{
	$card = pg_payment_driver_definition_normalize('examplepay', array('label' => ' Example Pay ', 'kind' => 'card', 'gateway' => 'Example Pay', 'file' => 'examplepay.php', 'portal' => 'https://example.com/keys'), '/srv/pinegrap/includes/payments/');

	pg_assert_same('Example Pay', $card['label']);
	pg_assert_same('/srv/pinegrap/includes/payments/examplepay.php', $card['file']);
	pg_assert_same('Credit/Debit Card', $card['method']);
	pg_assert_same('https://example.com/keys', $card['portal']);

	$redirect = pg_payment_driver_definition_normalize('examplego', array('label' => 'Example Go', 'kind' => 'redirect', 'method' => 'Pay With Example', 'gateway' => 'Ignored', 'file' => 'C:\\inetpub\\addons\\example\\go.php', 'portal' => 'http://example.com/'), '');

	pg_assert_same('', $redirect['gateway']);
	pg_assert_same('C:\\inetpub\\addons\\example\\go.php', $redirect['file'], 'a Windows path is absolute');
	pg_assert_same('', $redirect['portal'], 'only an https portal is linked');

	$refused = array(
		'bad key'           => array('Example', array('label' => 'x', 'kind' => 'card', 'gateway' => 'x', 'file' => 'x.php'), '/d'),
		'no label'          => array('example', array('label' => ' ', 'kind' => 'card', 'gateway' => 'x', 'file' => 'x.php'), '/d'),
		'unknown kind'      => array('example', array('label' => 'x', 'kind' => 'wallet', 'gateway' => 'x', 'file' => 'x.php'), '/d'),
		'card, no gateway'  => array('example', array('label' => 'x', 'kind' => 'card', 'file' => 'x.php'), '/d'),
		'gateway too long'  => array('example', array('label' => 'x', 'kind' => 'card', 'gateway' => str_repeat('g', 51), 'file' => 'x.php'), '/d'),
		'redirect, no method' => array('example', array('label' => 'x', 'kind' => 'redirect', 'file' => 'x.php'), '/d'),
		'a method of its own' => array('example', array('label' => 'x', 'kind' => 'redirect', 'method' => 'Offline Payment', 'file' => 'x.php'), '/d'),
		'not php'           => array('example', array('label' => 'x', 'kind' => 'card', 'gateway' => 'x', 'file' => 'x.inc'), '/d'),
		'parent folder'     => array('example', array('label' => 'x', 'kind' => 'card', 'gateway' => 'x', 'file' => '/srv/addons/../x.php'), ''),
		'relative, no base' => array('example', array('label' => 'x', 'kind' => 'card', 'gateway' => 'x', 'file' => 'x.php'), ''),
		'sub folder'        => array('example', array('label' => 'x', 'kind' => 'card', 'gateway' => 'x', 'file' => 'sub/x.php'), '/d'),
		'not an array'      => array('example', 'x.php', '/d'),
	);

	foreach ($refused as $label => $case) {
		pg_assert_same(null, pg_payment_driver_definition_normalize($case[0], $case[1], $case[2]), $label);
	}
}

// What the release ships loads: a file that exists and declares what it can do.
function test_payment_drivers_builtin_definitions_load()
{
	foreach (pg_payment_builtin_drivers() as $key => $definition) {
		$normalized = pg_payment_driver_definition_normalize($key, $definition, PG_FUNCTIONS_DIR . '/includes/payments');

		pg_assert_true($normalized !== null, $key . ' definition');
		pg_assert_true(($normalized !== null) && is_file($normalized['file']), $key . ' file');
		pg_assert_true(pg_payment_load($key), $key . ' loads');
	}
}

// The gateways left in submit_order.php are the ones the settings list offers
// and the column can hold.
function test_payment_drivers_legacy_gateways_match_the_settings_list()
{
	$card = file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/commerce.php');
	$start = strpos($card, 'name="ecommerce_payment_gateway"');
	$list = substr($card, $start, strpos($card, '</select>', $start) - $start);

	preg_match_all('/<option value="([^"]+)"/', $list, $options);

	pg_assert_same(pg_payment_legacy_gateways(), $options[1], 'settings list');

	$migration = file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/legacy.php');

	pg_assert_true(preg_match("/CHANGE ecommerce_payment_gateway ecommerce_payment_gateway ENUM\\(([^)]*)\\)/", $migration, $enum) === 1, 'column definition found');

	$values = array_map(function ($value) {
		return trim($value, " '");
	}, explode(',', isset($enum[1]) ? $enum[1] : ''));

	pg_assert_same(pg_payment_legacy_gateways(), array_values(array_diff($values, array(''))), 'column values');
}

function test_payment_drivers_register_and_find()
{
	payment_drivers_test_register();

	pg_assert_same('Test Card', pg_payment_driver('pgtestcard')['label']);
	pg_assert_same('pgtestcard', pg_payment_driver_for_gateway('Pg Test Gateway'));
	pg_assert_same('pgtestredirect', pg_payment_driver_for_method('Pay With Pg Test'));
	pg_assert_same('', pg_payment_driver_for_gateway('Sage'), 'a legacy gateway with no driver');
	pg_assert_same('', pg_payment_driver_for_method('Credit/Debit Card'));
	pg_assert_same(null, pg_payment_driver('nosuchdriver'));

	pg_assert_false(pg_payment_driver_register('pgtestcard', array('label' => 'Again', 'kind' => 'card', 'gateway' => 'Other', 'file' => __FILE__)), 'key taken');
	pg_assert_false(pg_payment_driver_register('pgtestother', array('label' => 'Other', 'kind' => 'card', 'gateway' => 'Pg Test Gateway', 'file' => __FILE__)), 'gateway taken');
	pg_assert_false(pg_payment_driver_register('pgtestother', array('label' => 'Other', 'kind' => 'redirect', 'method' => 'Pay With Pg Test', 'file' => __FILE__)), 'method taken');
	pg_assert_false(pg_payment_driver_register('pgtestother', array('label' => 'Other', 'kind' => 'card', 'gateway' => 'Other', 'file' => 'other.php')), 'an add-on names its file in full');
	pg_assert_same(null, pg_payment_driver('pgtestother'));
}

function test_payment_drivers_can_answers_operations_and_capabilities()
{
	payment_drivers_test_register();

	pg_assert_true(pg_payment_can('pgtestcard', 'prepare'));
	pg_assert_true(pg_payment_can('pgtestcard', 'capture'));
	pg_assert_false(pg_payment_can('pgtestcard', 'refund'), 'not defined');
	pg_assert_true(pg_payment_can('pgtestcard', 'wallets'));
	pg_assert_false(pg_payment_can('pgtestcard', 'teleport'), 'not a known capability');
	pg_assert_false(pg_payment_can('pgtestcard', 'recurring'));
	pg_assert_same(array('installments', 'wallets'), pg_payment_capabilities('pgtestcard'));

	pg_assert_same(array(), pg_payment_capabilities('pgtestredirect'), 'a throwing declaration declares nothing');
	pg_assert_false(pg_payment_can('nosuchdriver', 'prepare'));
}

function test_payment_drivers_call_never_throws()
{
	payment_drivers_test_register();

	$thrown = pg_payment_call('pgtestcard', 'authorize', array(array(), 4714, array()));
	pg_assert_false($thrown['success']);
	pg_assert_same('RuntimeException', $thrown['raw']['exception']);
	pg_assert_same('connection reset', $thrown['raw']['message']);

	pg_assert_false(pg_payment_call('pgtestcard', 'refund', array(array(), 100, array()))['success'], 'not defined');
	pg_assert_false(pg_payment_call('pgtestcard', 'teleport')['success'], 'not an operation');
	pg_assert_false(pg_payment_call('pgtestcard', 'capabilities')['success'], 'not an operation');
	pg_assert_false(pg_payment_call('nosuchdriver', 'prepare', array(array(), 1))['success'], 'no driver');
}

// Missing arguments arrive as null and extra ones are dropped, so a caller's
// slip is not a PHP error inside the driver.
function test_payment_drivers_call_passes_the_contract_arguments()
{
	payment_drivers_test_register();

	$short = pg_payment_call('pgtestcard', 'prepare', array(array('id' => 1)));
	pg_assert_true($short['success']);
	pg_assert_same(array('arguments' => 2, 'amount' => null), $short['client']);

	$long = pg_payment_call('pgtestcard', 'prepare', array(array(), 4714, 'extra', 'more'));
	pg_assert_same(array('arguments' => 2, 'amount' => 4714), $long['client']);

	$captured = pg_payment_call('pgtestcard', 'capture', array(array(), 4714, 'tx-1'));
	pg_assert_true($captured['success']);
	pg_assert_same('tx-1', $captured['transaction_id']);
	pg_assert_same(4714, $captured['amount']);
}

function test_payment_drivers_fields_normalize()
{
	$fields = pg_payment_fields_normalize(array(
		array('name' => 'api_key', 'label' => 'API Key', 'required' => true, 'max_length' => 5000),
		array('name' => 'api_key', 'label' => 'Twice'),
		array('name' => 'Bad', 'label' => 'Uppercase'),
		array('name' => 'two__parts', 'label' => 'Double underscore'),
		array('name' => 'enabled', 'label' => 'Reserved'),
		array('name' => 'present', 'label' => 'Reserved'),
		array('name' => 'row', 'label' => 'Reserved'),
		array('name' => 'nolabel'),
		array('name' => 'colour', 'type' => 'color', 'label' => 'Unknown type'),
		array('name' => 'empty_select', 'type' => 'select', 'label' => 'No options', 'options' => array()),
		array('name' => 'mode', 'type' => 'select', 'label' => 'Mode', 'options' => array('test' => 'Test', 'live' => 'Live', 'blank' => ''), 'default' => 'staging'),
		array('name' => 'secret_key', 'type' => 'secret', 'label' => 'Secret', 'default' => 'leaked'),
		array('name' => 'wallets', 'type' => 'switch', 'label' => 'Wallets', 'default' => 'yes', 'required' => true),
		'not a field',
	));

	pg_assert_same(array('api_key', 'mode', 'secret_key', 'wallets'), array_column($fields, 'name'));
	pg_assert_same(1000, $fields[0]['max_length'], 'clamped');
	pg_assert_same('text', $fields[0]['type'], 'text when not given');
	pg_assert_same(array('test' => 'Test', 'live' => 'Live'), $fields[1]['options'], 'an option without a label is left out');
	pg_assert_same('test', $fields[1]['default'], 'a default outside the options falls to the first');
	pg_assert_same('', $fields[2]['default'], 'a secret has no default');
	pg_assert_same('', $fields[3]['default']);
	pg_assert_false($fields[3]['required'], 'a switch is never required');
	pg_assert_same(array(), pg_payment_fields_normalize('api_key'));
}

function test_payment_drivers_fields_come_from_the_driver()
{
	payment_drivers_test_register();

	pg_assert_same(array('merchant_id', 'secret_key', 'mode'), array_column(pg_payment_fields('pgtestcard'), 'name'));
	pg_assert_same(array(), pg_payment_fields('pgtestredirect'), 'no fields function');
}

function test_payment_drivers_decide_only_what_was_posted()
{
	payment_drivers_test_register();

	$decision = pg_payment_settings_decide(pg_payment_driver('pgtestcard'), pg_payment_fields('pgtestcard'), array('ecommerce_payment_gateway' => 'Pg Test Gateway'), payment_drivers_test_stored(), true);

	pg_assert_same(array('posted' => false), $decision);
}

// Required is asked only of the driver about to take payments.
function test_payment_drivers_decide_requires_only_the_active_driver()
{
	payment_drivers_test_register();

	$definition = pg_payment_driver('pgtestcard');
	$fields = pg_payment_fields('pgtestcard');
	$post = array('payment_pgtestcard__present' => '1', 'payment_pgtestcard__merchant_id' => ' ', 'payment_pgtestcard__secret_key' => '');

	$idle = pg_payment_settings_decide($definition, $fields, $post, payment_drivers_test_stored(), false);
	pg_assert_same(array(), $idle['marks'], 'not selected');

	$active = pg_payment_settings_decide($definition, $fields, $post, payment_drivers_test_stored(), true);
	pg_assert_same(array('payment_pgtestcard__merchant_id', 'payment_pgtestcard__secret_key'), array_keys($active['marks']));
	pg_assert_contains('Merchant ID', $active['marks']['payment_pgtestcard__merchant_id']);

	$kept = pg_payment_settings_decide($definition, $fields, array('payment_pgtestcard__present' => '1', 'payment_pgtestcard__merchant_id' => 'M-1'), payment_drivers_test_stored(array('merchant_id' => 'M-1'), array('secret_key' => 'sk')), true);
	pg_assert_same(array(), $kept['marks'], 'a stored secret answers the requirement');
}

// An empty box keeps the stored secret; only a typed, different one is new.
function test_payment_drivers_decide_keeps_secrets()
{
	payment_drivers_test_register();

	$definition = pg_payment_driver('pgtestcard');
	$fields = pg_payment_fields('pgtestcard');
	$stored = payment_drivers_test_stored(array('merchant_id' => 'M-1', 'mode' => 'live'), array('secret_key' => 'sk_live'), true);

	$same = pg_payment_settings_decide($definition, $fields, array('payment_pgtestcard__present' => '1', 'payment_pgtestcard__merchant_id' => 'M-1', 'payment_pgtestcard__mode' => 'live', 'payment_pgtestcard__secret_key' => ''), $stored, true);
	pg_assert_same(array(), $same['secrets']);
	pg_assert_false($same['changed'], 'nothing to write');

	$retyped = pg_payment_settings_decide($definition, $fields, array('payment_pgtestcard__present' => '1', 'payment_pgtestcard__merchant_id' => 'M-1', 'payment_pgtestcard__mode' => 'live', 'payment_pgtestcard__secret_key' => ' sk_live '), $stored, true);
	pg_assert_same(array(), $retyped['secrets'], 'the stored one typed again');
	pg_assert_false($retyped['changed']);

	$new = pg_payment_settings_decide($definition, $fields, array('payment_pgtestcard__present' => '1', 'payment_pgtestcard__merchant_id' => 'M-1', 'payment_pgtestcard__mode' => 'live', 'payment_pgtestcard__secret_key' => 'sk_new'), $stored, true);
	pg_assert_same(array('secret_key' => 'sk_new'), $new['secrets']);
	pg_assert_true($new['changed']);
	pg_assert_false(array_key_exists('secret_key', $new['values']), 'a secret is never among the values');
}

function test_payment_drivers_decide_reads_each_type()
{
	payment_drivers_test_register();

	$definition = pg_payment_driver('pgtestcard');
	$fields = pg_payment_fields_normalize(array(
		array('name' => 'merchant_id', 'label' => 'Merchant ID', 'max_length' => 5),
		array('name' => 'mode', 'type' => 'select', 'label' => 'Mode', 'options' => array('test' => 'Test', 'live' => 'Live')),
		array('name' => 'wallets', 'type' => 'switch', 'label' => 'Wallets'),
	));

	$tampered = pg_payment_settings_decide($definition, $fields, array('payment_pgtestcard__present' => '1', 'payment_pgtestcard__merchant_id' => array('x'), 'payment_pgtestcard__mode' => 'prod', 'payment_pgtestcard__wallets' => 'on'), payment_drivers_test_stored(array('mode' => 'live')), false);
	pg_assert_same(array('merchant_id' => '', 'mode' => 'live', 'wallets' => ''), $tampered['values'], 'an array, an unknown option and a stray switch value');

	$long = pg_payment_settings_decide($definition, $fields, array('payment_pgtestcard__present' => '1', 'payment_pgtestcard__merchant_id' => 'ABCDEF', 'payment_pgtestcard__wallets' => '1'), payment_drivers_test_stored(), false);
	pg_assert_same(array('payment_pgtestcard__merchant_id'), array_keys($long['marks']), 'too long is refused, not cut');
	pg_assert_same('1', $long['values']['wallets']);
	pg_assert_same('test', $long['values']['mode'], 'nothing posted, nothing stored: the default');
}

// A driver never set up gets no row from an unrelated save of the card.
function test_payment_drivers_decide_writes_no_row_for_defaults()
{
	payment_drivers_test_register();

	$card = pg_payment_settings_decide(pg_payment_driver('pgtestcard'), pg_payment_fields('pgtestcard'), array('payment_pgtestcard__present' => '1', 'payment_pgtestcard__merchant_id' => '', 'payment_pgtestcard__mode' => 'test'), payment_drivers_test_stored(array(), array(), false, false), false);
	pg_assert_false($card['changed']);

	$typed = pg_payment_settings_decide(pg_payment_driver('pgtestcard'), pg_payment_fields('pgtestcard'), array('payment_pgtestcard__present' => '1', 'payment_pgtestcard__merchant_id' => 'M-1'), payment_drivers_test_stored(array(), array(), false, false), false);
	pg_assert_true($typed['changed']);

	$redirect = pg_payment_driver('pgtestredirect');
	$off = pg_payment_settings_decide($redirect, array(), array('payment_pgtestredirect__present' => '1'), payment_drivers_test_stored(array(), array(), false, false), false);
	pg_assert_false($off['changed']);
	pg_assert_false($off['enabled']);

	$on = pg_payment_settings_decide($redirect, array(), array('payment_pgtestredirect__present' => '1', 'payment_pgtestredirect__enabled' => '1'), payment_drivers_test_stored(array(), array(), false, false), false);
	pg_assert_true($on['changed']);
	pg_assert_true($on['enabled']);
	pg_assert_true($on['active'], 'a redirect driver switched on is the active one');

	$switched_off = pg_payment_settings_decide($redirect, array(), array('payment_pgtestredirect__present' => '1'), payment_drivers_test_stored(array(), array(), true), false);
	pg_assert_true($switched_off['changed']);
	pg_assert_false($switched_off['enabled']);
}

function test_payment_drivers_fields_html_escapes_and_never_draws_secrets()
{
	$definition = pg_payment_driver_definition_normalize('examplepay', array('label' => 'Example <Pay>', 'kind' => 'card', 'gateway' => 'Example Pay', 'file' => 'x.php', 'portal' => 'https://example.com/?a=1&b=2'), '/d');
	$fields = pg_payment_fields_normalize(array(
		array('name' => 'merchant_id', 'label' => 'Merchant <ID>', 'help' => 'From "Keys" & tokens.'),
		array('name' => 'secret_key', 'type' => 'secret', 'label' => 'Secret Key'),
		array('name' => 'mode', 'type' => 'select', 'label' => 'Mode', 'options' => array('test' => 'Test', 'live' => 'Live')),
		array('name' => 'wallets', 'type' => 'switch', 'label' => 'Wallets'),
	));

	$html = pg_payment_settings_fields_html($definition, $fields, payment_drivers_test_stored(array('merchant_id' => '"><script>', 'mode' => 'live', 'wallets' => '1'), array('secret_key' => 'sk_live_secret')));

	pg_assert_contains('name="payment_examplepay__present" value="1"', $html);
	pg_assert_contains('Merchant &lt;ID&gt;', $html);
	pg_assert_contains('From &quot;Keys&quot; &amp; tokens.', $html);
	pg_assert_contains('value="&quot;&gt;&lt;script&gt;"', $html);
	pg_assert_contains('href="https://example.com/?a=1&amp;b=2"', $html);
	pg_assert_contains('Example &lt;Pay&gt;', $html);
	pg_assert_contains('<option value="live" selected="selected">', $html);
	pg_assert_contains('checked="checked"', $html);
	pg_assert_false(strpos($html, 'sk_live_secret') !== false, 'the secret is not drawn');
	pg_assert_contains('name="payment_examplepay__secret_key" value=""', $html);
	pg_assert_contains('placeholder="', $html);
	pg_assert_false(strpos($html, '<script>') !== false);

	$unread = pg_payment_settings_fields_html($definition, $fields, array('values' => array(), 'secrets' => array(), 'secret_stored' => true, 'readable' => false, 'enabled' => false, 'exists' => true));
	pg_assert_contains('alert-warning', $unread, 'secrets the site\'s key cannot read');
	pg_assert_false(strpos($unread, 'placeholder="') !== false, 'nothing readable is stored');
}
