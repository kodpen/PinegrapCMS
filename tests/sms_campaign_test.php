<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for SMS campaigns (includes/fn/sms_campaigns.php): the screen's
 * decision, the audience picked from included and excluded contact groups,
 * when a campaign is due, and the queueing, which goes through the SMS
 * layer's queue as `marketing` messages and keeps its refusals. No database
 * and no provider.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/sms_campaigns.php');

function sms_campaign_test_post($overrides = array())
{
	return $overrides + array(
		'name'            => 'Yaz indirimi',
		'body'            => 'Bütün ürünlerde yüzde 20 indirim.',
		'contact_group_3' => 'included',
		'contact_group_4' => 'excluded',
		'contact_group_5' => 'ignored',
		'send'            => 'now',
		'start_at'        => '',
	);
}

// The groups the user may pick, a send now and a name: a ready campaign
// that starts now, with its included and excluded groups.
function test_sms_campaign_decide_send_now()
{
	$decision = pg_sms_campaign_decide(sms_campaign_test_post(), array(3, 4, 5), 1000, null);

	pg_assert_same(array(), $decision['errors']);
	pg_assert_same('ready', $decision['values']['status']);
	pg_assert_same(1000, $decision['values']['start_at']);
	pg_assert_same(array(3), $decision['values']['included']);
	pg_assert_same(array(4), $decision['values']['excluded']);
	pg_assert_same('Yaz indirimi', $decision['values']['name']);
}

// A draft keeps no time; a later time is kept; a time in the past, a time
// that cannot be read, no text, no included group and a group the user may
// not pick are refused.
function test_sms_campaign_decide_refusals()
{
	$draft = pg_sms_campaign_decide(sms_campaign_test_post(array('send' => 'draft')), array(3, 4), 1000, null);
	pg_assert_same('draft', $draft['values']['status']);
	pg_assert_same(0, $draft['values']['start_at']);

	$later = pg_sms_campaign_decide(sms_campaign_test_post(array('send' => 'at')), array(3, 4), 1000, 5000);
	pg_assert_same(array(), $later['errors']);
	pg_assert_same(5000, $later['values']['start_at']);

	pg_assert_true(count(pg_sms_campaign_decide(sms_campaign_test_post(array('send' => 'at')), array(3, 4), 1000, 900)['errors']) === 1, 'past time');
	pg_assert_true(count(pg_sms_campaign_decide(sms_campaign_test_post(array('send' => 'at')), array(3, 4), 1000, null)['errors']) === 1, 'unreadable time');
	pg_assert_true(count(pg_sms_campaign_decide(sms_campaign_test_post(array('body' => "  \n ")), array(3, 4), 1000, null)['errors']) === 1, 'empty text');
	pg_assert_true(count(pg_sms_campaign_decide(sms_campaign_test_post(array('contact_group_3' => 'ignored')), array(3, 4), 1000, null)['errors']) === 1, 'no group');

	$foreign = pg_sms_campaign_decide(sms_campaign_test_post(), array(4), 1000, null);
	pg_assert_same(array(), $foreign['values']['included'], 'a group the user may not pick is dropped');
	pg_assert_true(count($foreign['errors']) === 1, 'and leaves no group');

	// A draft may be saved without a group or a text yet.
	$empty = pg_sms_campaign_decide(sms_campaign_test_post(array('send' => 'draft', 'body' => '', 'contact_group_3' => 'ignored')), array(3, 4), 1000, null);
	pg_assert_same(array(), $empty['errors']);
}

// One message per number: the first contact of a number keeps it, a
// number of an excluded group's contact is left out, and a phone that is not
// a mobile number is skipped.
function test_sms_campaign_audience_pick()
{
	$included = array(
		array('id' => '11', 'mobile_phone' => '0532 111 22 33'),
		array('id' => '12', 'mobile_phone' => '+90 532 111 22 33'),
		array('id' => '13', 'mobile_phone' => '0544 999 88 77'),
		array('id' => '14', 'mobile_phone' => 'yok'),
		array('id' => '15', 'mobile_phone' => '05055554433'),
	);
	$excluded = array(
		array('id' => '20', 'mobile_phone' => '505 555 44 33'),
	);

	pg_assert_same(
		array('905321112233' => 11, '905449998877' => 13),
		pg_sms_campaign_audience_pick($included, $excluded));
}

// Due: a ready campaign whose time has come; a draft, a queued or cancelled
// one, and a ready one still in the future are not.
function test_sms_campaign_due()
{
	pg_assert_true(pg_sms_campaign_due(array('status' => 'ready', 'start_at' => '900'), 1000));
	pg_assert_true(pg_sms_campaign_due(array('status' => 'ready', 'start_at' => '1000'), 1000));
	pg_assert_false(pg_sms_campaign_due(array('status' => 'ready', 'start_at' => '1001'), 1000));
	pg_assert_false(pg_sms_campaign_due(array('status' => 'draft', 'start_at' => '0'), 1000));
	pg_assert_false(pg_sms_campaign_due(array('status' => 'queued', 'start_at' => '0'), 1000));
	pg_assert_false(pg_sms_campaign_due(array('status' => 'cancelled', 'start_at' => '0'), 1000));
}

// Every message goes to the queue the SMS layer offers, as the marketing
// kind, with the campaign's reference and an expiry; what the layer refuses
// (no consent, refused by İYS, no sender identity) is counted, never sent
// another way.
function test_sms_campaign_queue_goes_through_the_sms_layer()
{
	$calls = array();
	$enqueue = function ($to, $text, $kind, $options) use (&$calls) {
		$calls[] = array($to, $text, $kind, $options);

		if ($to === '905449998877') {
			return array('ok' => false, 'row' => 0, 'duplicate' => false, 'error' => 'No SMS consent.');
		}

		return array('ok' => true, 'row' => count($calls), 'duplicate' => ($to === '905550000000'), 'error' => '');
	};

	$counts = pg_sms_campaign_queue(
		array('id' => '7', 'body' => 'Yaz indirimi'),
		array('905321112233' => 11, '905449998877' => 13, '905550000000' => 15),
		1000,
		$enqueue);

	pg_assert_same(array('queued' => 2, 'refused' => 1), $counts);
	pg_assert_same(3, count($calls));

	foreach ($calls as $call) {
		pg_assert_same('marketing', $call[2], 'only the marketing kind');
		pg_assert_same('Yaz indirimi', $call[1], 'the text as written; the layer appends the identity');
		pg_assert_same(1000 + pg_sms_campaign_expiry(), $call[3]['expires_at']);
	}

	pg_assert_same('smscampaign:7:11', $calls[0][3]['reference']);
	pg_assert_same('smscampaign:7:13', $calls[1][3]['reference']);
}

// The module never writes sms_outbox nor calls a driver or pg_sms_send()
// itself: the only way out is pg_sms_enqueue(), which applies the checks.
function test_sms_campaign_module_has_no_bypass()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/sms_campaigns.php');

	pg_assert_false(strpos($source, 'INSERT INTO sms_outbox') !== false, 'no direct insert');
	pg_assert_false(strpos($source, 'pg_sms_send(') !== false, 'no direct send');
	pg_assert_false(strpos($source, 'pg_sms_driver(') !== false, 'no driver');
	pg_assert_true(strpos($source, "'pg_sms_enqueue'") !== false, 'pg_sms_enqueue() is the default');
}

// The general job runs the due campaigns before it sends the SMS queue, so a
// campaign that starts now goes out in the same run.
function test_sms_campaign_job_runs_before_the_queue()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/job.php');
	$campaigns = strpos($source, 'pg_sms_campaigns_run_due(');
	$queue = strpos($source, 'pg_sms_queue_run(10, 15)');

	pg_assert_true($campaigns !== false, 'job.php runs the campaigns');
	pg_assert_true($campaigns < $queue, 'before the queue');
}
