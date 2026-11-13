<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the WhatsApp account's templates (whatsapp_templates): the list
 * request and how a page of Meta's answer is read, what a refresh adds,
 * changes and removes, when the general job reads them again, the labels,
 * and the migration step and job wiring. No database and no call to Meta.
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
require_once(PG_FUNCTIONS_DIR . '/includes/whatsapp/cloud_api.php');

function whatsapp_templates_test_app()
{
	return array(
		'enabled' => true, 'app_id' => '1234567890123', 'app_secret' => 'secret', 'token' => 'token',
		'phone_number_id' => '106540352242922', 'account_id' => '102290129340398',
	);
}

function test_whatsapp_templates_request()
{
	$request = pg_whatsapp_cloud_templates_request(whatsapp_templates_test_app());
	pg_assert_contains('/v26.0/102290129340398/message_templates?', $request['url'], 'the account\'s templates');
	pg_assert_contains('fields=id%2Cname%2Clanguage%2Cstatus%2Ccategory%2Ccomponents%2Cparameter_format%2Crejected_reason%2Cquality_score', $request['url'], 'fields');
	pg_assert_contains('appsecret_proof=', $request['url'], 'proof');
	pg_assert_false(strpos($request['url'], 'after=') !== false, 'first page has no cursor');

	$request = pg_whatsapp_cloud_templates_request(whatsapp_templates_test_app(), 'QVFIUk');
	pg_assert_contains('after=QVFIUk', $request['url'], 'next page');
}

function test_whatsapp_templates_parse()
{
	$body = json_encode(array(
		'data' => array(
			array('id' => '594425479261596', 'name' => 'order_received', 'language' => 'tr', 'status' => 'APPROVED', 'category' => 'UTILITY',
				'parameter_format' => 'POSITIONAL', 'rejected_reason' => 'NONE', 'quality_score' => array('score' => 'GREEN'),
				'components' => array(array('type' => 'BODY', 'text' => 'Siparişiniz {{1}} alındı.'))),
			array('id' => '594425479261597', 'name' => 'spring_offer', 'language' => 'en_US', 'status' => 'REJECTED', 'category' => 'MARKETING',
				'rejected_reason' => 'INVALID_FORMAT'),
			array('id' => 'x', 'name' => 'broken', 'language' => 'tr'),
			array('id' => '594425479261598', 'name' => 'Bad Name', 'language' => 'tr'),
			'not a template',
		),
		'paging' => array('cursors' => array('before' => 'A', 'after' => 'B'), 'next' => 'https://graph.facebook.com/next'),
	));

	$read = pg_whatsapp_cloud_templates_parse(pg_whatsapp_cloud_answer(200, $body));
	pg_assert_true($read['ok'], 'read');
	pg_assert_same(2, count($read['templates']), 'unusable entries skipped');
	pg_assert_same('B', $read['after'], 'cursor while a next page exists');

	$first = $read['templates'][0];
	pg_assert_same('594425479261596', $first['meta_id'], 'id as text');
	pg_assert_same('', $first['reason'], 'NONE is no reason');
	pg_assert_same('GREEN', $first['quality'], 'quality');
	pg_assert_same('Siparişiniz {{1}} alındı.', $first['components'][0]['text'], 'components kept');
	pg_assert_same('INVALID_FORMAT', $read['templates'][1]['reason'], 'rejection reason');
	pg_assert_same(array(), $read['templates'][1]['components'], 'no components');

	$last = pg_whatsapp_cloud_templates_parse(pg_whatsapp_cloud_answer(200, json_encode(array('data' => array(), 'paging' => array('cursors' => array('after' => 'B'))))));
	pg_assert_same('', $last['after'], 'no next link: the last page');

	$refused = pg_whatsapp_cloud_templates_parse(pg_whatsapp_cloud_answer(400, json_encode(array('error' => array('code' => 200, 'message' => 'Permission')))));
	pg_assert_false($refused['ok'], 'Meta refused');

	$odd = pg_whatsapp_cloud_templates_parse(pg_whatsapp_cloud_answer(200, json_encode(array('templates' => array()))));
	pg_assert_false($odd['ok'], 'an answer without data is not an empty list');
}

function test_whatsapp_templates_plan()
{
	$stored = array(
		array('id' => 1, 'meta_id' => '100', 'name' => 'order_received', 'language' => 'tr'),
		array('id' => 2, 'meta_id' => '200', 'name' => 'old_offer', 'language' => 'tr'),
		array('id' => 3, 'meta_id' => null, 'name' => 'my_draft', 'language' => 'tr'),
		array('id' => 4, 'meta_id' => null, 'name' => 'sent_draft', 'language' => 'en_US'),
	);
	$fetched = array(
		array('meta_id' => '100', 'name' => 'order_received', 'language' => 'tr', 'status' => 'APPROVED'),
		array('meta_id' => '400', 'name' => 'sent_draft', 'language' => 'en_US', 'status' => 'PENDING'),
		array('meta_id' => '500', 'name' => 'shipped', 'language' => 'tr', 'status' => 'APPROVED'),
	);

	$plan = pg_whatsapp_templates_plan($stored, $fetched);
	pg_assert_same(array(1, 4), array_keys($plan['update']), 'matched by id, and a draft by name and language');
	pg_assert_same('400', $plan['update'][4]['meta_id'], 'the draft gets Meta\'s id');
	pg_assert_same(array('shipped'), array_map(function ($template) { return $template['name']; }, $plan['insert']), 'a new one added');
	pg_assert_same(array(2), $plan['delete'], 'one Meta no longer lists removed; drafts never');

	$again = pg_whatsapp_templates_plan(array(array('id' => 1, 'meta_id' => '100', 'name' => 'order_received', 'language' => 'tr')),
		array(array('meta_id' => '900', 'name' => 'order_received', 'language' => 'tr')));
	pg_assert_same(array(1), array_keys($again['update']), 'made again under the same name: the row takes the new id');
	pg_assert_same(array(), $again['delete'], 'and is not removed');
}

function test_whatsapp_templates_due_and_labels()
{
	pg_assert_true(pg_whatsapp_templates_due(0, 1000), 'never read');
	pg_assert_false(pg_whatsapp_templates_due(100000, 100000 + 3600), 'an hour later');
	pg_assert_true(pg_whatsapp_templates_due(100000, 100000 + 21600), 'six hours later');

	pg_assert_same('Approved', pg_whatsapp_template_status_label('APPROVED'), 'status');
	pg_assert_same('SOMETHING_NEW', pg_whatsapp_template_status_label('SOMETHING_NEW'), 'unknown status as Meta wrote it');
	pg_assert_same('Utility', pg_whatsapp_template_category_label('UTILITY'), 'category');
}

function test_whatsapp_templates_wiring()
{
	$migration = file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/2026.11.0.php');
	pg_assert_true(strpos($migration, 'upgrade_2026_11_0_whatsapp_templates();') !== false, 'step called');
	$table = substr($migration, strpos($migration, 'CREATE TABLE whatsapp_templates'));
	pg_assert_true(strpos($table, 'UNIQUE KEY uq_name_language (name, language)') !== false, 'one row per name and language');
	pg_assert_true(strpos($table, 'name              VARCHAR(512) CHARACTER SET ascii') !== false, 'name in ASCII, so the key fits');

	pg_assert_true(strpos(file_get_contents(PG_FUNCTIONS_DIR . '/install/index.php'), "'whatsapp_templates',") !== false, 'in get_tables()');
	pg_assert_true(strpos(file_get_contents(PG_FUNCTIONS_DIR . '/job.php'), 'pg_whatsapp_templates_job();') !== false, 'the general job refreshes');
	pg_assert_true(pg_whatsapp_templates_sync_key() !== pg_whatsapp_app_key(), 'the refresh never writes the account\'s row');
}
