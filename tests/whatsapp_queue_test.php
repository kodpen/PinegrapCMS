<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the WhatsApp queue (whatsapp_outbox, includes/fn/whatsapp.php):
 * the row a queued template message gets, what refuses it, the reasons a
 * claimed row is not sent, how a send's answer becomes the row's state, the
 * migration step and the general job's pass. No database and no call to Meta.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/whatsapp.php');

function whatsapp_queue_test_template($changes = array())
{
	return $changes + array(
		'name'       => 'order_received',
		'language'   => 'tr',
		'category'   => 'UTILITY',
		'components' => array(array('type' => 'body', 'parameters' => array(array('type' => 'text', 'text' => '438')))),
		'id'         => 7,
	);
}

function test_whatsapp_queue_row_shape()
{
	$row = pg_whatsapp_queue_row('0532 123 45 67', whatsapp_queue_test_template(), array(
		'kind'       => 'order',
		'reference'  => 'order:438:received',
		'contact_id' => 12,
	), 1000);

	pg_assert_same('905321234567', $row['recipient'], 'national number read as a Turkish mobile');
	pg_assert_same('queued', $row['status'], 'queued');
	pg_assert_same(1000, $row['send_after'], 'due now');
	pg_assert_same(0, $row['expires_at'], 'no expiry');
	pg_assert_same('order:438:received', $row['reference'], 'reference kept');
	pg_assert_same(12, $row['contact_id'], 'contact');
	pg_assert_same(7, $row['template_id'], 'template row');
	pg_assert_same('UTILITY', $row['category'], 'category');
	pg_assert_same('[{"type":"body","parameters":[{"type":"text","text":"438"}]}]', $row['payload'], 'components as JSON');

	$row = pg_whatsapp_queue_row('+44 7700 900123', whatsapp_queue_test_template(array('category' => 'marketing', 'components' => null)), array('kind' => 'flow'), 1000);
	pg_assert_same('447700900123', $row['recipient'], 'international number');
	pg_assert_same('MARKETING', $row['category'], 'category upper-cased');
	pg_assert_same('[]', $row['payload'], 'no components');
	pg_assert_same(null, $row['reference'], 'no reference');
}

function test_whatsapp_queue_row_refusals()
{
	$refused = function ($to, $template, $options) {
		$row = pg_whatsapp_queue_row($to, $template, $options + array('kind' => 'order'), 1000);
		return isset($row['error']) && (count($row) === 1);
	};

	pg_assert_true($refused('abc', whatsapp_queue_test_template(), array()), 'no number');
	pg_assert_true($refused('05321234567', whatsapp_queue_test_template(array('name' => 'Order Received')), array()), 'name with capitals and a space');
	pg_assert_true($refused('05321234567', whatsapp_queue_test_template(array('name' => '')), array()), 'empty name');
	pg_assert_true($refused('05321234567', whatsapp_queue_test_template(array('language' => 'turkish')), array()), 'language not a code');
	pg_assert_true($refused('05321234567', whatsapp_queue_test_template(array('category' => 'SERVICE')), array()), 'unknown category');
	pg_assert_true($refused('05321234567', whatsapp_queue_test_template(array('components' => array(str_repeat('x', 70000)))), array()), 'payload too large');
	pg_assert_true($refused('05321234567', whatsapp_queue_test_template(), array('kind' => '')), 'no kind');
	pg_assert_true($refused('05321234567', whatsapp_queue_test_template(), array('kind' => 'Order received')), 'kind not a name');
	pg_assert_true($refused('05321234567', whatsapp_queue_test_template(), array('reference' => 'order 438')), 'reference with a space');
	pg_assert_true($refused('05321234567', whatsapp_queue_test_template(), array('send_after' => 2000, 'expires_at' => 2000)), 'expires before it is due');

	$row = pg_whatsapp_queue_row('05321234567', whatsapp_queue_test_template(array('language' => 'en_US')), array('kind' => 'order', 'send_after' => 500, 'expires_at' => 5000), 1000);
	pg_assert_same(1000, $row['send_after'], 'a past send_after is now');
	pg_assert_same(5000, $row['expires_at'], 'expiry kept');
}

function test_whatsapp_queue_refusal_at_send()
{
	$app = array(
		'enabled' => true, 'app_id' => '1234567890123', 'app_secret' => 'secret', 'token' => 'token',
		'phone_number_id' => '106540352242922', 'account_id' => '102290129340398',
	);
	$row = array('category' => 'UTILITY', 'payload' => '[]');

	pg_assert_same(null, pg_whatsapp_queue_refusal($row, $app, false), 'utility needs no consent');
	pg_assert_same(null, pg_whatsapp_queue_refusal(array('category' => 'MARKETING') + $row, $app, true), 'marketing with consent');

	$refusal = pg_whatsapp_queue_refusal(array('category' => 'MARKETING') + $row, $app, false);
	pg_assert_true(is_array($refusal) && !$refusal['ok'] && !$refusal['retry'] && !$refusal['uncertain'], 'marketing without consent fails for good');

	$refusal = pg_whatsapp_queue_refusal($row, array('enabled' => false) + $app, true);
	pg_assert_true(is_array($refusal) && !$refusal['retry'], 'switched off fails, never a burst later');

	$refusal = pg_whatsapp_queue_refusal($row, array('token' => '') + $app, true);
	pg_assert_true(is_array($refusal), 'incomplete account');

	$refusal = pg_whatsapp_queue_refusal(array('payload' => '{broken') + $row, $app, true);
	pg_assert_true(is_array($refusal), 'unreadable payload');
}

function test_whatsapp_queue_outcome_follows_the_sms_rules()
{
	$row = array('attempts' => 0, 'send_after' => 1000, 'expires_at' => 0);

	$sent = pg_sms_queue_outcome($row, array('ok' => true, 'id' => 'wamid.HBg', 'code' => '', 'error' => '', 'retry' => false, 'uncertain' => false), 1000);
	pg_assert_same('sent', $sent['status'], 'sent');
	pg_assert_same('wamid.HBg', $sent['provider_id'], 'wamid kept');

	$later = pg_sms_queue_outcome($row, array('ok' => false, 'id' => '', 'code' => '130429', 'error' => 'throughput', 'retry' => true, 'uncertain' => false), 1000);
	pg_assert_same('queued', $later['status'], 'rate limit tried again');
	pg_assert_true($later['send_after'] > 1000, 'later');

	$unknown = pg_sms_queue_outcome($row, array('ok' => false, 'id' => '', 'code' => '', 'error' => 'timeout', 'retry' => false, 'uncertain' => true), 1000);
	pg_assert_same('uncertain', $unknown['status'], 'unknown outcome never sent again by itself');

	$closed = pg_sms_queue_outcome($row, array('ok' => false, 'id' => '', 'code' => '131026', 'error' => 'not a WhatsApp number', 'retry' => false, 'uncertain' => false), 1000);
	pg_assert_same('failed', $closed['status'], 'refused for good');

	$expired = pg_sms_queue_outcome($row, null, 1000);
	pg_assert_same('failed', $expired['status'], 'expired');
	pg_assert_same(0, $expired['attempts'], 'expired without an attempt');
}

function test_whatsapp_queue_wiring()
{
	$migration = file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/2026.11.0.php');
	pg_assert_true(strpos($migration, "upgrade_2026_11_0_whatsapp_outbox();") !== false, 'step called');
	pg_assert_true(strpos($migration, "install_create_table('whatsapp_outbox'") !== false, 'table created with the helper');
	pg_assert_true((bool) preg_match('/UNIQUE KEY uq_reference \(reference\)/', substr($migration, strpos($migration, 'CREATE TABLE whatsapp_outbox'))), 'reference unique');

	$install = file_get_contents(PG_FUNCTIONS_DIR . '/install/index.php');
	pg_assert_true(strpos($install, "'whatsapp_outbox',") !== false, 'in get_tables()');

	$job = file_get_contents(PG_FUNCTIONS_DIR . '/job.php');
	pg_assert_true(strpos($job, 'pg_whatsapp_queue_run(10, 15);') !== false, 'the general job sends');

	pg_assert_true(isset(pg_contact_holders()['whatsapp_outbox']), 'a contact holder');
}
