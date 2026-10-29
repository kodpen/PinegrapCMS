<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Aras Kargo label driver (includes/shipping/aras.php), every
 * operation called through pg_label_call(). Aras is never called: a
 * stand-in transport answers with the SOAP answers of tests/fixtures/aras/,
 * built in the shape of the service's own published description (the WSDL
 * of arascargoservice.asmx and its IrsaliyeData schema), and keeps the
 * requests it was sent.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_carrier_label_aras_is_a_builtin_driver()
{
	pg_assert_true(pg_label_driver('aras') !== null);
	pg_assert_same(array('carriers' => array('aras'), 'formats' => array('pdf', 'zpl')), pg_label_info('aras'));

	foreach (array('test', 'create', 'label', 'cancel', 'track') as $operation) {
		pg_assert_true(pg_label_can('aras', $operation), $operation);
	}

	pg_assert_false(pg_label_can('aras', 'pickup'));

	$fields = array();

	foreach (pg_label_fields('aras') as $field) {
		$fields[$field['name']] = $field['type'] . (empty($field['required']) ? '' : ' required');
	}

	pg_assert_same(array(
		'username'       => 'text required',
		'password'       => 'secret required',
		'customer_code'  => 'text',
		'mode'           => 'select',
		'sender_address' => 'text',
		'label_format'   => 'select',
	), $fields);
}
