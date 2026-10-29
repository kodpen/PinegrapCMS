<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Kargonomi label driver (includes/shipping/kargonomi.php),
 * every operation called through pg_label_call(). Kargonomi has no test
 * mode and is never called: a stand-in transport answers with the answers
 * of tests/fixtures/kargonomi/, built in the shape of its public reference
 * (www.kargonomi.com.tr/help/api-dokumantasyonu/kargonomi-api/), and keeps
 * the requests it was sent.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_carrier_label_kargonomi_is_a_builtin_driver()
{
	pg_assert_true(pg_label_driver('kargonomi') !== null);
	pg_assert_same(array('carriers' => array('surat', 'aras', 'ptt'), 'formats' => array('pdf')), pg_label_info('kargonomi'));
	pg_assert_false(pg_label_can('kargonomi', 'pickup'));

	$fields = array();

	foreach (pg_label_fields('kargonomi') as $field) {
		$fields[$field['name']] = $field['type'] . (empty($field['required']) ? '' : ' required');
	}

	pg_assert_same(array(
		'api_token'    => 'secret required',
		'warehouse_id' => 'text required',
		'provider'     => 'text',
	), $fields);

	$help = pg_label_fields('kargonomi')[0]['help'];
	pg_assert_contains('no test mode', $help);
	pg_assert_contains('real, charged label', $help);
}
