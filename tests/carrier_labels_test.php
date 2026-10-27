<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the carrier label driver layer (includes/fn/carrier_labels.php):
 * driver keys and definitions, what info() may declare, the answer shape and
 * what it refuses, masking of the account's secrets, the options a parcel row
 * gets, the account fields and what a post of the Shipping card decides. No
 * service and no database is reached: the test driver below lives in this
 * file and is registered with its path.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A driver with every operation, answering what its arguments ask for.
function pg_label_pgtestship_info()
{
	return array('carriers' => array('aras', 'nowhere', 'yurtici', 7), 'formats' => array('zpl', 'png', 'pdf'));
}

function pg_label_pgtestship_fields()
{
	return array(
		array('name' => 'api_key', 'label' => 'API Key', 'required' => true),
		array('name' => 'api_secret', 'type' => 'secret', 'label' => 'API Secret', 'required' => true),
		array('name' => 'mode', 'type' => 'select', 'label' => 'Environment', 'options' => array('test' => 'Test', 'live' => 'Live'), 'default' => 'test'),
		array('name' => 'labels_only', 'type' => 'switch', 'label' => 'Labels only'),
	);
}

function pg_label_pgtestship_test($account)
{
	if ($account['api_secret'] === 'wrong-secret') {
		return array('success' => false, 'error' => 'Invalid key wrong-secret for ' . $account['api_key'], 'raw' => array('request' => array('secret' => 'wrong-secret')));
	}

	return array('success' => true, 'account' => 'Test Shop', 'arguments' => func_num_args());
}

function pg_label_pgtestship_create($shipment, $account)
{
	return $shipment;
}

function pg_label_pgtestship_track($reference, $account)
{
	throw new \RuntimeException('connection reset');
}

function pg_label_pgtestship_cancel($reference, $account)
{
	return pg_label_pgtestship_undefined_helper();
}

function carrier_labels_test_register()
{
	static $done = false;

	if (!$done) {
		pg_label_driver_register('pgtestship', array('label' => 'Test Shipping', 'file' => __FILE__, 'portal' => 'https://example.com/keys'));
		$done = true;
	}
}

function carrier_labels_test_stored($values = array(), $secrets = array(), $enabled = false, $exists = true)
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

function test_carrier_labels_key_rules()
{
	foreach (array('aggregator', 'yurtici', 'aras_v2', str_repeat('a', 32)) as $key) {
		pg_assert_true(pg_label_key_valid($key), $key);
	}

	foreach (array('a', 'Aras', '1ship', 'ship__x', 'ship_', 'ship-x', str_repeat('a', 33), 12, null) as $key) {
		pg_assert_false(pg_label_key_valid($key), var_export($key, true));
	}

	pg_assert_same('label_a_b__c', pg_label_input_name('a_b', 'c'));
	pg_assert_same('label_yurtici', pg_label_settings_key('yurtici'));
}

function test_carrier_labels_definition_needs_label_and_safe_file()
{
	pg_assert_same(null, pg_label_driver_definition_normalize('x1', array('file' => '/a/b.php'), ''));
	pg_assert_same(null, pg_label_driver_definition_normalize('x1', array('label' => 'X', 'file' => 'b.php'), ''), 'relative file for an add-on');
	pg_assert_same(null, pg_label_driver_definition_normalize('x1', array('label' => 'X', 'file' => '/a/../b.php'), ''));
	pg_assert_same(null, pg_label_driver_definition_normalize('x1', array('label' => 'X', 'file' => '/a/b.txt'), ''));

	$definition = pg_label_driver_definition_normalize('x1', array('label' => ' X ', 'file' => 'b.php', 'portal' => 'http://insecure.example'), '/dir/');
	pg_assert_same(array('key' => 'x1', 'label' => 'X', 'file' => '/dir/b.php', 'portal' => ''), $definition);
}

function test_carrier_labels_register_refuses_taken_key()
{
	carrier_labels_test_register();

	pg_assert_true(pg_label_driver('pgtestship') !== null);
	pg_assert_false(pg_label_driver_register('pgtestship', array('label' => 'Again', 'file' => __FILE__)));
	pg_assert_same('Test Shipping', pg_label_driver('pgtestship')['label']);
}

// Only carriers and formats the store knows, so the card and the shipment
// row never see a name of the service's own.
function test_carrier_labels_info_keeps_known_carriers_and_formats()
{
	carrier_labels_test_register();

	pg_assert_same(array('carriers' => array('yurtici', 'aras'), 'formats' => array('pdf', 'zpl')), pg_label_info('pgtestship'));
	pg_assert_same(array('carriers' => array(), 'formats' => array()), pg_label_info('nodriver'));
}

function test_carrier_labels_can_asks_the_functions()
{
	carrier_labels_test_register();

	pg_assert_true(pg_label_can('pgtestship', 'create'));
	pg_assert_false(pg_label_can('pgtestship', 'label'));
	pg_assert_false(pg_label_can('pgtestship', 'info'), 'info is not an operation');
	pg_assert_false(pg_label_can('nodriver', 'create'));
}

function test_carrier_labels_answer_drops_unknown_keys()
{
	$answer = pg_label_answer('create', array(
		'success'         => true,
		'reference'       => 'SHP-1001',
		'tracking_number' => ' 1234567890 ',
		'carrier'         => 'Aras Kargo',
		'tracking_url'    => 'https://kargotakip.example/1234567890',
		'status'          => 'created',
		'status_at'       => 1790000000,
		'cod_amount'      => 5000,
		'aggregator_fee'  => '12.40',
	));

	pg_assert_true($answer['success']);
	pg_assert_same('1234567890', $answer['tracking_number']);
	pg_assert_same('aras', $answer['carrier']);
	pg_assert_same('created', $answer['status']);
	pg_assert_same(1790000000, $answer['status_at']);
	pg_assert_false(array_key_exists('cod_amount', $answer));
	pg_assert_false(array_key_exists('aggregator_fee', $answer));
}

function test_carrier_labels_answer_refuses_what_cannot_be_trusted()
{
	$answer = pg_label_answer('create', array('success' => true, 'reference' => 'SHP-1', 'tracking_number' => '12'));
	pg_assert_false($answer['success'], 'too short a number');
	pg_assert_true($answer['unclear']);

	$answer = pg_label_answer('create', array('success' => true, 'tracking_number' => '1234567890'));
	pg_assert_false($answer['success'], 'no reference');

	$answer = pg_label_answer('label', array('success' => true, 'label' => 'not a pdf', 'label_format' => 'pdf'));
	pg_assert_false($answer['success'], 'not a PDF');
	pg_assert_same('', $answer['label']);

	$answer = pg_label_answer('label', array('success' => true, 'label' => "%PDF-1.4\n", 'label_format' => 'pdf'));
	pg_assert_true($answer['success']);
	pg_assert_same("%PDF-1.4\n", $answer['label']);

	$answer = pg_label_answer('label', array('success' => true, 'label' => "^XA^FO50,50^FDx^FS^XZ", 'label_format' => 'zpl'));
	pg_assert_true($answer['success']);

	$answer = pg_label_answer('track', array('success' => true, 'status' => 'lost_somewhere'));
	pg_assert_false($answer['success'], 'unknown status');

	$answer = pg_label_answer('create', array('success' => true, 'reference' => 'SHP-1', 'tracking_number' => '1234567890', 'tracking_url' => 'javascript:alert(1)', 'carrier' => 'Unheard Of Cargo'));
	pg_assert_true($answer['success']);
	pg_assert_same('', $answer['tracking_url']);
	pg_assert_same('', $answer['carrier']);

	$answer = pg_label_answer('test', 'yes');
	pg_assert_false($answer['success']);
	pg_assert_true($answer['error'] !== '');

	$answer = pg_label_answer('test', array('success' => 1));
	pg_assert_false($answer['success'], 'only true is success');
}

function test_carrier_labels_call_pads_arguments_and_masks_secrets()
{
	carrier_labels_test_register();

	$answer = pg_label_call('pgtestship', 'test', array(array('api_key' => 'K1', 'api_secret' => 'right-secret', 'mode' => 'test'), 'extra'));
	pg_assert_true($answer['success']);
	pg_assert_same('Test Shop', $answer['account']);

	$answer = pg_label_call('pgtestship', 'test', array(array('api_key' => 'K1', 'api_secret' => 'wrong-secret', 'mode' => 'test')));
	pg_assert_false($answer['success']);
	pg_assert_same('Invalid key **** for K1', $answer['error']);
	pg_assert_same('****', $answer['raw']['request']['secret']);
}

function test_carrier_labels_call_failures()
{
	carrier_labels_test_register();

	$answer = pg_label_call('pgtestship', 'track', array('SHP-1', array()));
	pg_assert_false($answer['success']);
	pg_assert_true($answer['unclear'], 'an exception may have gone out');
	pg_assert_same('RuntimeException', $answer['raw']['exception']);

	$answer = pg_label_call('pgtestship', 'cancel', array('SHP-1', array()));
	pg_assert_false($answer['success']);
	pg_assert_false($answer['unclear'], 'an Error stops before sending');

	pg_assert_false(pg_label_call('pgtestship', 'label', array())['success']);
	pg_assert_false(pg_label_call('pgtestship', 'explode', array())['success']);
	pg_assert_false(pg_label_call('nodriver', 'test', array())['success']);
}

// The parcel row gets the columns every row has, never the service's own.
function test_carrier_labels_shipment_options()
{
	carrier_labels_test_register();

	$answer = pg_label_call('pgtestship', 'create', array(array(
		'success'         => true,
		'reference'       => 'AGG-77',
		'tracking_number' => '1234567890',
		'carrier'         => 'yurtici',
		'tracking_url'    => 'https://www.yurticikargo.com/x',
		'barcode'         => 'B123',
	), array()));

	pg_assert_true($answer['success']);
	pg_assert_same(array('source' => 'carrier', 'carrier' => 'yurtici', 'tracking_url' => 'https://www.yurticikargo.com/x'), pg_label_shipment_options($answer));
	pg_assert_same(array('source' => 'carrier'), pg_label_shipment_options(pg_label_answer('create', array('success' => true, 'reference' => 'A', 'tracking_number' => '1234567890'))));
}

function test_carrier_labels_fields_and_account()
{
	carrier_labels_test_register();

	$fields = pg_label_fields('pgtestship');
	pg_assert_same(array('api_key', 'api_secret', 'mode'), array_column($fields, 'name'), 'switches left out');
	pg_assert_same(array(), pg_label_fields('nodriver'));

	$account = pg_label_account($fields, carrier_labels_test_stored(array('api_key' => 'K1'), array('api_secret' => 'S1')));
	pg_assert_same(array('api_key' => 'K1', 'api_secret' => 'S1', 'mode' => 'test'), $account);
}

function test_carrier_labels_decide_not_posted()
{
	carrier_labels_test_register();

	pg_assert_same(array('posted' => false), pg_label_settings_decide('pgtestship', pg_label_fields('pgtestship'), array('ups_client_id' => ''), carrier_labels_test_stored()));
}

function test_carrier_labels_decide_required_only_when_on()
{
	carrier_labels_test_register();
	$fields = pg_label_fields('pgtestship');

	$off = pg_label_settings_decide('pgtestship', $fields, array('label_pgtestship__present' => '1'), carrier_labels_test_stored(array(), array(), false, false));
	pg_assert_same(array(), $off['marks']);
	pg_assert_false($off['changed'], 'no row for a driver never set up');

	$on = pg_label_settings_decide('pgtestship', $fields, array('label_pgtestship__present' => '1', 'label_pgtestship__enabled' => '1'), carrier_labels_test_stored(array(), array(), false, false));
	pg_assert_same(array('label_pgtestship__api_key', 'label_pgtestship__api_secret'), array_keys($on['marks']));
	pg_assert_true($on['changed']);
}

function test_carrier_labels_decide_keeps_stored_secret()
{
	carrier_labels_test_register();
	$fields = pg_label_fields('pgtestship');
	$stored = carrier_labels_test_stored(array('api_key' => 'K1', 'mode' => 'live'), array('api_secret' => 'S1'), true);

	$post = array(
		'label_pgtestship__present'    => '1',
		'label_pgtestship__enabled'    => '1',
		'label_pgtestship__api_key'    => ' K1 ',
		'label_pgtestship__api_secret' => '',
		'label_pgtestship__mode'       => 'nonsense',
		'label_test'                   => 'pgtestship',
	);

	$decision = pg_label_settings_decide('pgtestship', $fields, $post, $stored);
	pg_assert_same(array(), $decision['marks']);
	pg_assert_same(array(), $decision['secrets']);
	pg_assert_same(array('api_key' => 'K1', 'mode' => 'live'), $decision['values']);
	pg_assert_false($decision['changed'], 'nothing changed');
	pg_assert_true($decision['test']);

	$post['label_pgtestship__api_secret'] = 'S2';
	$post['label_test'] = 'other';
	$decision = pg_label_settings_decide('pgtestship', $fields, $post, $stored);
	pg_assert_same(array('api_secret' => 'S2'), $decision['secrets']);
	pg_assert_true($decision['changed']);
	pg_assert_false($decision['test']);

	unset($post['label_pgtestship__enabled']);
	$post['label_pgtestship__api_secret'] = '';
	$decision = pg_label_settings_decide('pgtestship', $fields, $post, $stored);
	pg_assert_false($decision['enabled']);
	pg_assert_true($decision['changed'], 'switched off');
}

function test_carrier_labels_mask_walks_arrays()
{
	pg_assert_same(array('a' => '****x', 'b' => array('c' => 'y****'), 'n' => 5), pg_label_mask(array('a' => 'sec1x', 'b' => array('c' => 'ysec2')), array('sec1', 'sec2')) + array('n' => 5));
}
