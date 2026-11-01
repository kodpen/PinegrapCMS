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

function sms_campaign_test_marketing()
{
	return array('trade_name' => 'Örnek Ticaret A.Ş.', 'mersis' => '0123456789012345', 'label' => '');
}

// The text limit leaves room for the sender identity and the type label the
// layer appends: a text of exactly the limit passes pg_sms_check() with them,
// one character more is refused there, so the screen refuses it on save.
function test_sms_campaign_body_limit_counts_the_footer()
{
	$marketing = sms_campaign_test_marketing();
	$footer = pg_sms_marketing_footer($marketing);
	$limit = pg_sms_campaign_body_limit($marketing);

	pg_assert_same('x' . "\n" . $footer, pg_sms_marketing_text('x', $marketing));
	pg_assert_same(pg_sms_max_length() - mb_strlen("\n" . $footer, 'UTF-8'), $limit);

	$fits = str_repeat('ş', $limit);
	$over = $fits . 'ş';

	pg_assert_true(pg_sms_check('05321234567', $fits, 'marketing', $marketing)['ok'], 'a text of the limit is sent');
	pg_assert_false(pg_sms_check('05321234567', $over, 'marketing', $marketing)['ok'], 'one more character is refused by the layer');

	pg_assert_same(array(), pg_sms_campaign_decide(sms_campaign_test_post(array('body' => $fits)), array(3, 4), 1000, null, $limit)['errors']);
	pg_assert_true(count(pg_sms_campaign_decide(sms_campaign_test_post(array('body' => $over)), array(3, 4), 1000, null, $limit)['errors']) === 1, 'too long to send');
	pg_assert_true(count(pg_sms_campaign_decide(sms_campaign_test_post(array('body' => $over, 'send' => 'draft')), array(3, 4), 1000, null, $limit)['errors']) === 1, 'a draft that could never be sent');
}

// The screen gives the limit to the box, the counter and the save check.
function test_sms_campaign_screen_uses_the_body_limit()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/edit_sms_campaign.php');

	pg_assert_contains('pg_sms_campaign_body_limit(', $source);
	pg_assert_contains('$body_limit)', $source, 'the save check gets the limit');
	pg_assert_contains('data-pg-sms-limit', $source, 'the counter');
	pg_assert_true(strpos($source, 'maxlength="\' . (int) pg_sms_max_length()') === false, 'the box is not given the whole layer limit');
}

// A scheduled campaign and a queued one read as two states in Turkish.
function test_sms_campaign_statuses_read_apart()
{
	$tr = array();

	foreach (array('s', 'q') as $shard) {
		$tr += json_decode(file_get_contents(PG_FUNCTIONS_DIR . '/includes/local/tr/' . $shard . '.json'), true);
	}

	pg_assert_same('Zamanlandı', $tr['Scheduled'] ?? null);
	pg_assert_same('Kuyruğa alındı', $tr['Queued for sending'] ?? null);

	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/sms_campaigns.php');
	pg_assert_contains("'queued'    => lang('Queued for sending')", $source);
}

// A deleted contact group takes its SMS campaign rows with it, on the
// screen and through the API, as its e-mail campaign rows.
function test_sms_campaign_group_rows_go_with_the_group()
{
	foreach (array('/edit_contact_group.php', '/includes/api/resources/contact_groups.php') as $file) {
		$source = file_get_contents(PG_FUNCTIONS_DIR . $file);

		pg_assert_contains("pg_schema_has('contact_groups_sms_campaigns_xref')", $source, $file);
		pg_assert_contains('DELETE FROM contact_groups_sms_campaigns_xref WHERE contact_group_id', $source, $file);
	}
}

// Statement-level db_q() writes in $source whose result is dropped: the call
// starts a statement and is followed straight by ';'. String literals are
// skipped while the parentheses are counted.
function sms_campaign_test_dropped_writes($source)
{
	$dropped = array();

	if (!preg_match_all('/(?:^|[;{}])\s*db_q\(/', $source, $matches, PREG_OFFSET_CAPTURE)) {
		return $dropped;
	}

	foreach ($matches[0] as $match) {
		$start = $match[1] + strlen($match[0]);
		$depth = 1;
		$quote = '';
		$length = strlen($source);

		for ($i = $start; ($i < $length) && ($depth > 0); $i++) {
			$char = $source[$i];

			if ($quote !== '') {
				if ($char === '\\') {
					$i++;
				} elseif ($char === $quote) {
					$quote = '';
				}
			} elseif (($char === '"') || ($char === "'")) {
				$quote = $char;
			} elseif ($char === '(') {
				$depth++;
			} elseif ($char === ')') {
				$depth--;
			}
		}

		$call = substr($source, $start, $i - $start);

		if (preg_match('/^\s*"\s*(INSERT|UPDATE|DELETE)\b/i', $call) && preg_match('/^\s*;/', substr($source, $i))) {
			$dropped[] = trim(substr($call, 0, 60));
		}
	}

	return $dropped;
}

// Delete Selected counts a campaign only after both of its DELETEs worked,
// and Cancel logs only the campaign its UPDATE really cancelled.
function test_sms_campaign_screen_writes_are_checked()
{
	foreach (array('/view_sms_campaigns.php', '/edit_sms_campaign.php') as $file) {
		$source = file_get_contents(PG_FUNCTIONS_DIR . $file);
		pg_assert_same(array(), sms_campaign_test_dropped_writes($source), $file . ' drops no write result');
	}

	$source = file_get_contents(PG_FUNCTIONS_DIR . '/edit_sms_campaign.php');
	$affected = strpos($source, 'mysqli_affected_rows(db::$con) === 1');
	$logged = strpos($source, "'SMS campaign cancelled: {var:1}'");
	pg_assert_true(($affected !== false) && ($logged !== false) && ($affected < $logged), 'cancel log follows the affected-rows check');
}

// An edit saved after the job claimed the campaign (or after it was
// cancelled) changes no row: the save must stop there, not rewrite the
// groups of a campaign already queued and report the changes as saved.
function test_sms_campaign_update_landed()
{
	pg_assert_true(pg_sms_campaign_update_landed(1, null), 'one row changed');
	pg_assert_true(pg_sms_campaign_update_landed(0, 'draft'), 'the same values saved again in the same second');
	pg_assert_true(pg_sms_campaign_update_landed(0, 'ready'), 'still scheduled');
	pg_assert_false(pg_sms_campaign_update_landed(0, 'queued'), 'claimed by the job meanwhile');
	pg_assert_false(pg_sms_campaign_update_landed(0, 'cancelled'), 'cancelled meanwhile');
	pg_assert_false(pg_sms_campaign_update_landed(0, null), 'deleted meanwhile');
	pg_assert_false(pg_sms_campaign_update_landed(-1, 'draft'), 'the query failed');

	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/sms_campaigns.php');
	$check = strpos($source, 'pg_sms_campaign_update_landed($affected');
	$delete = strpos($source, 'DELETE FROM contact_groups_sms_campaigns_xref');
	pg_assert_true(($check !== false) && ($delete !== false) && ($check < $delete), 'the groups are rewritten only after the check');

	$screen = file_get_contents(PG_FUNCTIONS_DIR . '/edit_sms_campaign.php');
	pg_assert_true(strpos($screen, '$saved === -1') !== false, 'the screen tells a campaign that left the editable states');
}
