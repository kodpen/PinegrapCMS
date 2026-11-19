<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for visitor notifications (includes/fn/site_push.php, site_push.php):
 * which endpoints and keys are taken, the door's origin, switch and rate
 * checks, a secret reading only its own message, nothing written with the
 * switch off, the consent log's push channel and the privacy lists. The
 * runner has no connection, so only the pure rules and the code's shape are
 * checked here.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/site_push.php');
require_once(__DIR__ . '/helpers/source_reader.php');

function _site_push_test_b64url($bytes)
{
	return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
}

function _site_push_test_post(array $over = array())
{
	return $over + array(
		'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc:def',
		'p256dh'   => _site_push_test_b64url("\x04" . str_repeat("\x11", 64)),
		'auth'     => _site_push_test_b64url(str_repeat("\x22", 16)),
		'language' => 'tr',
	);
}

function test_site_push_endpoint_known_services_only()
{
	pg_assert_same('fcm', pg_site_push_endpoint_service('https://fcm.googleapis.com/fcm/send/x'), 'Chrome');
	pg_assert_same('mozilla', pg_site_push_endpoint_service('https://updates.push.services.mozilla.com/wpush/v2/x'), 'Firefox');
	pg_assert_same('windows', pg_site_push_endpoint_service('https://wns2-par02p.notify.windows.com/w/?token=x'), 'Edge');
	pg_assert_same('apple', pg_site_push_endpoint_service('https://web.push.apple.com/QGx'), 'Safari');
	pg_assert_same('fcm', pg_site_push_endpoint_service('https://FCM.googleapis.com/fcm/send/x'), 'the host in any case');
}

function test_site_push_endpoint_refused()
{
	foreach (array(
		'http://fcm.googleapis.com/fcm/send/x'              => 'without https',
		'https://example.com/fcm/send/x'                    => 'an unknown host',
		'https://fcm.googleapis.com.evil.test/x'            => 'a known name as a prefix',
		'https://evilfcm.googleapis.com/x'                  => 'a name that only ends like a known one',
		'https://push.services.mozilla.com.evil.test/x'     => 'a suffix inside another host',
		'https://notify.windows.com/x'                      => 'a suffix host itself',
		'https://fcm.googleapis.com:8443/x'                 => 'another port',
		'https://user:pw@fcm.googleapis.com/x'              => 'a user part',
		'https://127.0.0.1/x'                               => 'an address',
		'https://fcm.googleapis.com/x y'                    => 'a space',
		'https://fcm.googleapis.com/' . str_repeat('a', 480) => 'longer than the column',
		''                                                  => 'empty',
	) as $endpoint => $why) {
		pg_assert_same('', pg_site_push_endpoint_service($endpoint), $why);
	}

	pg_assert_same('', pg_site_push_endpoint_service(array('https://fcm.googleapis.com/x')), 'an array');
	pg_assert_same('endpoint', pg_site_push_subscription_check(_site_push_test_post(array('endpoint' => 'https://example.com/x'))), 'the check names the endpoint');
}

function test_site_push_key_lengths()
{
	$p256dh = _site_push_test_b64url("\x04" . str_repeat("\x11", 64));
	$auth = _site_push_test_b64url(str_repeat("\x22", 16));

	pg_assert_true(pg_site_push_keys_valid($p256dh, $auth), 'a 65-byte point and a 16-byte secret');
	pg_assert_true(pg_site_push_keys_valid($p256dh . '=', $auth . '=='), 'with padding');
	pg_assert_false(pg_site_push_keys_valid(_site_push_test_b64url("\x04" . str_repeat("\x11", 63)), $auth), 'a 64-byte point');
	pg_assert_false(pg_site_push_keys_valid(_site_push_test_b64url("\x04" . str_repeat("\x11", 65)), $auth), 'a 66-byte point');
	pg_assert_false(pg_site_push_keys_valid(_site_push_test_b64url("\x02" . str_repeat("\x11", 64)), $auth), 'a point not uncompressed');
	pg_assert_false(pg_site_push_keys_valid($p256dh, _site_push_test_b64url(str_repeat("\x22", 15))), 'a 15-byte secret');
	pg_assert_false(pg_site_push_keys_valid($p256dh, _site_push_test_b64url(str_repeat("\x22", 17))), 'a 17-byte secret');
	pg_assert_false(pg_site_push_keys_valid(str_replace('E', '+', $p256dh), $auth), 'not base64url');
	pg_assert_false(pg_site_push_keys_valid(null, $auth), 'missing');
	pg_assert_same('keys', pg_site_push_subscription_check(_site_push_test_post(array('auth' => 'short'))), 'the check names the keys');

	$checked = pg_site_push_subscription_check(_site_push_test_post(array('p256dh' => $p256dh . '=', 'language' => 'tr"><')));
	pg_assert_same('fcm', $checked['service'], 'the service is kept');
	pg_assert_same($p256dh, $checked['p256dh'], 'padding is dropped');
	pg_assert_same('', $checked['language'], 'a language that is not one is dropped');
	pg_assert_same('en-GB', pg_site_push_language('en-GB'), 'a region');
}

function test_site_push_door_origin_and_rate()
{
	$same = array('HTTP_SEC_FETCH_SITE' => 'same-origin');

	pg_assert_same(array(405, 'method'), pg_site_push_door_refusal('GET', $same, true, false), 'only POST');
	pg_assert_same(array(403, 'origin'), pg_site_push_door_refusal('POST', array('HTTP_SEC_FETCH_SITE' => 'cross-site'), true, false), 'another site');
	pg_assert_same(array(403, 'origin'), pg_site_push_door_refusal('POST', array('HTTP_ORIGIN' => 'https://evil.test', 'HTTP_HOST' => 'shop.test'), true, false), 'another origin');
	pg_assert_same(array(403, 'origin'), pg_site_push_door_refusal('POST', array('HTTP_HOST' => 'shop.test'), true, false), 'no origin at all');
	pg_assert_same(null, pg_site_push_door_refusal('POST', array('HTTP_ORIGIN' => 'https://shop.test', 'HTTP_HOST' => 'shop.test'), true, false), 'the same origin');
	pg_assert_same(array(429, 'rate'), pg_site_push_door_refusal('POST', $same, true, true), 'over the rate');
	pg_assert_same(array(404, 'off'), pg_site_push_door_refusal('POST', $same, false, true), 'the switch before the rate');
	pg_assert_same(null, pg_site_push_door_refusal('POST', $same, true, false), 'let in');
	pg_assert_true(PG_SITE_PUSH_WRITE_LIMIT <= PG_SITE_PUSH_READ_LIMIT, 'writes are held tighter than reads');

	$door = file_get_contents(PG_FUNCTIONS_DIR . '/site_push.php');
	pg_assert_contains('pg_site_push_door_refusal(', $door, 'the door asks the refusal first');
	pg_assert_contains('pg_site_push_throttled($kind)', $door, 'the door counts its rate');
	pg_assert_true(strpos($door, 'pg_site_push_door_refusal(') < strpos($door, 'pg_site_push_subscription_save('), 'refused before anything is saved');
}

function test_site_push_secret_reads_only_its_own_message()
{
	list($sql, $params) = pg_site_push_waiting_query(7, 1794868200);

	pg_assert_contains('q.subscription_id = ?', $sql, 'scoped to the subscription');
	pg_assert_same('7', $params[0], 'the subscription the secret named');
	pg_assert_same((string) PG_SITE_PUSH_PUSHED, $params[1], 'a pushed row');
	pg_assert_same((string) (1794868200 - PG_SITE_PUSH_MESSAGE_TTL), $params[2], 'within a day');
	pg_assert_same((string) PG_SITE_PUSH_READ, $params[3], 'or a row already read');
	pg_assert_same((string) (1794868200 - PG_SITE_PUSH_REREAD), $params[4], 'within the last few minutes');
	pg_assert_same(6, substr_count($sql, '?'), 'every value is bound');

	pg_assert_true(pg_site_push_secret_valid(pg_site_push_secret_new()), 'a new secret has the shape');
	pg_assert_false(pg_site_push_secret_new() === pg_site_push_secret_new(), 'secrets differ');
	pg_assert_false(pg_site_push_secret_valid(str_repeat('A', 64)), 'upper case');
	pg_assert_false(pg_site_push_secret_valid('1 OR 1=1'), 'not a secret');
	pg_assert_same(null, pg_site_push_subscription_by_secret("' OR '1'='1"), 'a bad secret looks nothing up');

	$body = pg_test_function_source('pg_site_push_waiting_message');
	pg_assert_contains('pg_site_push_subscription_by_secret($secret)', $body, 'the subscription comes from the secret');
	pg_assert_contains('AND subscription_id = ? AND status = ?', $body, 'it marks only its own pushed row read, so a second read keeps the first time');
}

/**
 * Runs the waiting query and the read mark of pg_site_push_waiting_message()
 * on an in-memory SQLite copy of the two tables, one wake per call; returns
 * the titles shown. Null when the runner has no pdo_sqlite.
 */
function _site_push_test_wakes(array $queue_rows, array $wakes)
{
	if (!class_exists('PDO') || !in_array('sqlite', PDO::getAvailableDrivers(), true)) {
		return null;
	}

	$db = new PDO('sqlite::memory:');
	$db->exec('CREATE TABLE site_push_message (id INTEGER PRIMARY KEY, title TEXT, body TEXT, url TEXT)');
	$db->exec('CREATE TABLE site_push_queue (message_id INT, subscription_id INT, status INT, sent_at INT, fetched_at INT)');
	$db->exec("INSERT INTO site_push_message VALUES (1, 'News A', '', '/'), (2, 'Stock B', '', '/'), (3, 'Order C', '', '/')");
	$insert = $db->prepare('INSERT INTO site_push_queue VALUES (?, 7, ?, ?, ?)');

	foreach ($queue_rows as $row) {
		$insert->execute($row);
	}

	$shown = array();

	foreach ($wakes as $now) {
		list($sql, $params) = pg_site_push_waiting_query(7, $now);
		$st = $db->prepare($sql);
		$st->execute($params);
		$row = $st->fetch(PDO::FETCH_ASSOC);

		if (!$row) {
			$shown[] = null;
			continue;
		}

		$shown[] = $row['title'];
		$db->prepare('UPDATE site_push_queue SET status = ?, fetched_at = ? WHERE message_id = ? AND subscription_id = 7 AND status = ?')
			->execute(array(PG_SITE_PUSH_READ, $now, $row['message_id'], PG_SITE_PUSH_PUSHED));
	}

	return $shown;
}

function test_site_push_unread_before_a_reread()
{
	list($sql, $params) = pg_site_push_waiting_query(7, 1794868200);

	pg_assert_contains('ORDER BY (q.status = ?) DESC, q.sent_at DESC, q.message_id DESC', $sql, 'an unread row before a re-read one, then the newest');
	pg_assert_same((string) PG_SITE_PUSH_PUSHED, $params[5], 'the unread status is bound for the order');
	pg_assert_same(6, substr_count($sql, '?'), 'every value is bound');

	$now = 1794868200;
	$pushed = PG_SITE_PUSH_PUSHED;

	// Two messages pushed close together, before the worker's first read.
	$shown = _site_push_test_wakes(
		array(array(1, $pushed, $now - 2, 0), array(2, $pushed, $now - 1, 0)),
		array($now, $now + 1, $now + 2)
	);

	if ($shown !== null) {
		pg_assert_same(array('Stock B', 'News A', 'Stock B'), $shown, 'the newest first, then the older one, then a re-read once nothing is unread');
	}

	// Three messages: each wake shows a different one.
	$shown = _site_push_test_wakes(
		array(array(1, $pushed, $now - 3, 0), array(2, $pushed, $now - 2, 0), array(3, $pushed, $now - 1, 0)),
		array($now, $now + 1, $now + 2)
	);

	if ($shown !== null) {
		pg_assert_same(array('Order C', 'Stock B', 'News A'), $shown, 'three wakes show three messages');
	}

	// A worker woken twice for one message still reads it again, and not
	// after the re-read window.
	$shown = _site_push_test_wakes(
		array(array(1, $pushed, $now - 1, 0)),
		array($now, $now + 1, $now + PG_SITE_PUSH_REREAD + 1)
	);

	if ($shown !== null) {
		pg_assert_same(array('News A', 'News A', null), $shown, 'a second wake re-reads the message within the window');
	}
}

function test_site_push_nothing_written_with_the_switch_off()
{
	pg_assert_false(pg_site_push_switch_on(), 'off on a site that never set it');
	pg_assert_same('1', pg_site_push_switch_value(true), 'on');
	pg_assert_same('', pg_site_push_switch_value(false), 'off removes the row');
	pg_assert_false(pg_site_push_subscription_save(_site_push_test_post(), 0), 'no subscription kept');
	pg_assert_false(pg_site_push_subscription_remove(str_repeat('a', 64)), 'nothing removed');
	pg_assert_same(null, pg_site_push_waiting_message(str_repeat('a', 64)), 'no message read');

	foreach (array('pg_site_push_subscription_save', 'pg_site_push_subscription_remove', 'pg_site_push_waiting_message') as $function) {
		$body = pg_test_function_source($function);
		pg_assert_true(strpos($body, 'pg_site_push_enabled()') < strpos($body, 'db'), $function . ' asks the switch before the database');
	}
}

function test_site_push_consent_channel()
{
	$purposes = pg_consent_log_purposes();

	pg_assert_same(array('news'), $purposes['push'], 'the push channel');
	pg_assert_contains('notify', pg_consent_log_sources(), 'the block is a source');

	$row = pg_consent_log_row(array('channel' => 'push', 'purpose' => 'news', 'status' => 'granted', 'source' => 'notify'), 100);
	pg_assert_true(is_array($row), 'an anonymous visitor\'s yes is kept');
	pg_assert_same('person', pg_consent_log_row(array('channel' => 'sms', 'purpose' => 'marketing', 'status' => 'granted', 'source' => 'notify'), 100), 'other channels still need a person');
}

function test_site_push_privacy_lists()
{
	$erase = pg_privacy_erase_tables();
	pg_assert_same(array('user_id' => 'user_ids'), $erase['site_push_subscription']['find'], 'erasure finds the account\'s rows');
	pg_assert_same('delete', $erase['site_push_subscription']['action'], 'and deletes them');
	pg_assert_true(isset(pg_privacy_erase_labels()['site_push_subscription']), 'erasure names it');
	pg_assert_true(isset(pg_privacy_export_sections()['site_push']), 'the export has a section');
	pg_assert_contains('site_push_subscription WHERE', pg_test_function_source('pg_privacy_export_collect'), 'the export reads it');
	pg_assert_false(strpos(pg_test_function_source('pg_privacy_export_collect'), 'secret_hash') !== false, 'the export leaves the secret out');
}

function test_site_push_never_in_the_panel_queue()
{
	$module = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/site_push.php');
	$door = file_get_contents(PG_FUNCTIONS_DIR . '/site_push.php');

	foreach (array('INSERT INTO push_', 'UPDATE push_', 'pg_push_enqueue', 'pg_push_subscription_save') as $needle) {
		pg_assert_false(strpos($module . $door, $needle) !== false, $needle);
	}

	$migration = file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/2026.11.0.php');
	pg_assert_contains('upgrade_2026_11_0_site_push();               // 11.0.227', $migration, 'the step is called');

	$install = file_get_contents(PG_FUNCTIONS_DIR . '/install/index.php');
	foreach (array('site_push_message', 'site_push_queue', 'site_push_subscription') as $table) {
		pg_assert_contains("'" . $table . "',", $install, $table . ' in get_tables()');
	}
}

function test_site_push_message_check()
{
	$hosts = array('shop.test', 'www.shop.test');
	$ok = pg_site_push_message_check(array('title' => '  New season ', 'body' => "Line one\nline two", 'url' => 'https://www.shop.test/new?x=1#top'), $hosts);

	pg_assert_same(array('title' => 'New season', 'body' => 'Line one line two', 'url' => '/new?x=1#top'), $ok, 'cleaned, the link kept as a path');
	pg_assert_same('title', pg_site_push_message_check(array('title' => '', 'url' => '/'), $hosts), 'a title is needed');
	pg_assert_same('title', pg_site_push_message_check(array('title' => str_repeat('ş', 61)), $hosts), 'at most 60 characters');
	pg_assert_true(is_array(pg_site_push_message_check(array('title' => str_repeat('ş', 60), 'body' => str_repeat('ğ', 200)), $hosts)), '60 and 200 characters, not bytes');
	pg_assert_same('body', pg_site_push_message_check(array('title' => 'x', 'body' => str_repeat('a', 201)), $hosts), 'at most 200');

	pg_assert_same('/', pg_site_push_url_path('', $hosts), 'empty is the home page');
	pg_assert_same('/a/b', pg_site_push_url_path('/a/b', $hosts), 'a path');
	pg_assert_same('/', pg_site_push_url_path('http://SHOP.test', $hosts), 'the site\'s address');

	foreach (array('https://evil.test/a', '//evil.test/a', '/\\evil.test', 'javascript:alert(1)', 'a/b', "/a\nb", 'https://u:p@shop.test/a', 'ftp://shop.test/a', '/' . str_repeat('a', 500)) as $bad) {
		pg_assert_same('', pg_site_push_url_path($bad, $hosts), var_export($bad, true));
	}
}

function test_site_push_batches()
{
	$batches = pg_site_push_batches(range(1, 1001), 500);

	pg_assert_same(3, count($batches), '1001 subscriptions are three batches');
	pg_assert_same(500, count($batches[0]), 'a full batch');
	pg_assert_same(array(1001), $batches[2], 'the rest');
	pg_assert_same(array(), pg_site_push_batches(array()), 'no subscribers, nothing written');

	list($sql, $params) = pg_site_push_queue_insert(9, array(4, 5));
	pg_assert_contains('INSERT IGNORE INTO site_push_queue', $sql, 'a batch written twice queues each browser once');
	pg_assert_same(2, substr_count($sql, '(?, ?, 0, 0, 0)'), 'one row each, waiting');
	pg_assert_same(array('9', '4', '9', '5'), $params, 'every value bound');

	$body = pg_test_function_source('pg_site_push_message_send');
	pg_assert_true(strpos($body, 'pg_site_push_enabled()') < strpos($body, 'db_'), 'nothing is written with the switch off');
	pg_assert_contains('PG_SITE_PUSH_KIND_NEWS, PG_SITE_PUSH_KIND_TEST', $body, 'only news and a test are written here');
	pg_assert_same('off', pg_site_push_message_send(array('title' => 'x'), PG_SITE_PUSH_KIND_NEWS, 1), 'refused with the switch off');
}

function test_site_push_send_plan()
{
	pg_assert_same(array('action' => 'sent', 'count' => 'sent_count', 'wait' => 0), pg_site_push_send_plan('ok', 0), 'accepted: pushed, counted');
	pg_assert_same(array('action' => 'remove', 'count' => 'removed_count', 'wait' => 0), pg_site_push_send_plan('gone', 2), 'a gone subscription is removed and counted');
	pg_assert_same(array('action' => 'hold', 'count' => '', 'wait' => 0), pg_site_push_send_plan('busy', 4), 'a held service counts no try');
	pg_assert_same(array('action' => 'retry', 'count' => '', 'wait' => 60), pg_site_push_send_plan('failed', 0), 'a first failure waits a minute');
	pg_assert_same(array('action' => 'retry', 'count' => '', 'wait' => 7200), pg_site_push_send_plan('failed', 3), 'the fourth waits two hours');
	pg_assert_same(array('action' => 'fail', 'count' => 'failed_count', 'wait' => 0), pg_site_push_send_plan('failed', PG_SITE_PUSH_ATTEMPTS - 1), 'the last try gives up and counts');
	pg_assert_same('gone', pg_push_outcome(410), '410 is gone');
	pg_assert_same('busy', pg_push_outcome(429), '429 is busy');

	$run = pg_test_function_source('pg_site_push_queue_run');
	pg_assert_contains('pg_site_push_group_due($group, $held, $dropped)', $run, 'a held service is skipped for the rest of the pass');
	pg_assert_contains('s.service = ?', $run, 'every waiting row of the held service waits');
	pg_assert_contains('pg_cron_claim(PG_SITE_PUSH_LOCK', $run, 'one run at a time');
	pg_assert_true(strpos($run, 'pg_cron_claim(') < strpos($run, 'pg_site_push_deliver_many('), 'claimed before sending');
	pg_assert_false(strpos($run, 'push_subscriptions') !== false, 'never the panel\'s table');
}

function test_site_push_refused_subscription_removed()
{
	pg_assert_true(pg_site_push_refused(400), 'BadDeviceToken is a refusal');
	pg_assert_true(pg_site_push_refused(403), 'a push signed with another key is a refusal');
	pg_assert_true(pg_site_push_refused(401), 'an unauthorised push is a refusal');
	pg_assert_false(pg_site_push_refused(408), 'a timeout is no refusal');
	pg_assert_false(pg_site_push_refused(429), 'a busy service is no refusal');
	pg_assert_false(pg_site_push_refused(500), 'a service error is an outage, not a refusal');
	pg_assert_false(pg_site_push_refused(503), 'an unavailable service is an outage');
	pg_assert_false(pg_site_push_refused(0), 'no answer is an outage');
	pg_assert_false(pg_site_push_refused(201), 'an accepted push is no refusal');

	$now = 10000000;
	$old = $now - PG_SITE_PUSH_UNSEEN;
	pg_assert_same(15, PG_SITE_PUSH_REFUSALS, 'three given-up messages of five tries');
	pg_assert_true(pg_site_push_failing(PG_SITE_PUSH_REFUSALS, $old, $now), 'refused three messages long and unseen a week: removed');
	pg_assert_false(pg_site_push_failing(PG_SITE_PUSH_REFUSALS - 1, $old, $now), 'fewer refusals: kept');
	pg_assert_false(pg_site_push_failing(PG_SITE_PUSH_REFUSALS * 4, $old + 1, $now), 'seen within the week: kept, whatever the refusals');

	$run = pg_test_function_source('pg_site_push_queue_run');
	pg_assert_contains('pg_site_push_failing(', $run, 'the failure path asks whether the subscription keeps failing');
	pg_assert_contains('pg_site_push_refused(', $run, 'only a refusal raises fail_count');
	pg_assert_contains("'removed_count'", $run, 'a dropped subscription counts as removed');
	pg_assert_true(substr_count($run, 'pg_site_push_subscription_drop(') >= 2, 'a gone and a failing subscription go the same way');
	pg_assert_contains('isset($dropped[$subscription_id])', $run, 'its later rows in the pass are not pushed');

	$drop = pg_test_function_source('pg_site_push_subscription_drop');
	pg_assert_contains('DELETE FROM site_push_queue', $drop, 'its queue rows go');
	pg_assert_contains('DELETE FROM site_push_subscription', $drop, 'the subscription goes');
	pg_assert_contains('pg_site_push_watch_forget(', $drop, 'its waits go');
}

function _site_push_test_row($message, $subscription, $service)
{
	return array('message_id' => (string) $message, 'subscription_id' => (string) $subscription, 'service' => $service, 'endpoint' => 'https://push.example/' . $subscription);
}

function test_site_push_page_groups()
{
	$rows = array();

	for ($i = 1; $i <= PG_SITE_PUSH_PASS_ROWS; $i++) {
		$rows[] = _site_push_test_row(7, $i, 'fcm');
	}

	$groups = pg_site_push_groups($rows);
	pg_assert_same(20, PG_SITE_PUSH_GROUP, 'twenty pushes on the wire at once');
	pg_assert_same(5, count($groups), 'a page of 100 is five groups');
	pg_assert_same('1', $groups[0][0]['subscription_id'], 'the page keeps its order');
	pg_assert_same('100', $groups[4][19]['subscription_id'], 'the last group ends with the last row');
	pg_assert_same(array(array($rows[0]), array($rows[1])), pg_site_push_groups(array_slice($rows, 0, 2), 1), 'a group of one');
	pg_assert_same(array(), pg_site_push_groups(array()), 'an empty page sends nothing');
}

function test_site_push_held_service_leaves_later_groups()
{
	$group = array(_site_push_test_row(1, 1, 'fcm'), _site_push_test_row(1, 2, 'apple'), _site_push_test_row(1, 3, 'fcm'), _site_push_test_row(1, 4, 'mozilla'));

	pg_assert_same($group, pg_site_push_group_due($group, array(), array()), 'nothing held: the whole group goes');

	$due = pg_site_push_group_due($group, array('fcm' => true), array());
	pg_assert_same(array('2', '4'), array_column($due, 'subscription_id'), 'a service held in an earlier group is left');

	$due = pg_site_push_group_due($group, array(), array(4 => true));
	pg_assert_same(array('1', '2', '3'), array_column($due, 'subscription_id'), 'a subscription removed earlier is left');
}

function test_site_push_page_after_the_last_row()
{
	$first = pg_site_push_page_sql(5000, 0, 0);
	pg_assert_contains("q.next_at <= '5000'", $first, 'only due rows');
	pg_assert_contains("q.message_id > '0' OR (q.message_id = '0' AND q.subscription_id > '0')", $first, 'the first page starts at the beginning');
	pg_assert_contains('ORDER BY q.message_id, q.subscription_id', $first, 'oldest message first');
	pg_assert_contains('LIMIT ' . PG_SITE_PUSH_PASS_ROWS, $first, 'one page at a time');

	$next = pg_site_push_page_sql(5000, 12, 340);
	pg_assert_contains("q.message_id > '12' OR (q.message_id = '12' AND q.subscription_id > '340')", $next, 'the next page starts after the last row');
	pg_assert_contains("q.subscription_id > '0'", pg_site_push_page_sql(5000, '1 OR 1=1', 'x'), 'the cursor is a number');

	$run = pg_test_function_source('pg_site_push_queue_run');
	pg_assert_contains('pg_site_push_page_sql($now, $after[0], $after[1])', $run, 'the pass reads page after page');
	pg_assert_contains('count($rows) === PG_SITE_PUSH_PASS_ROWS', $run, 'a short page is the last');
	pg_assert_contains('break 2', $run, 'the budget ends the pass between groups');
	pg_assert_contains('api_http_request_many(', pg_test_function_source('pg_site_push_deliver_many'), 'a group goes out together');
}

function test_site_push_dropped_after_a_day_and_tidied()
{
	pg_assert_false(pg_site_push_expired(1000 - PG_SITE_PUSH_MESSAGE_TTL, 1000), 'exactly a day: still sent');
	pg_assert_true(pg_site_push_expired(999 - PG_SITE_PUSH_MESSAGE_TTL, 1000), 'past the day: dropped');
	pg_assert_same(7 * 86400, PG_SITE_PUSH_KEEP, 'finished rows are kept a week');

	$tidy = pg_test_function_source('pg_site_push_queue_tidy');
	pg_assert_contains('failed_count = failed_count + ?', $tidy, 'a dropped row counts as failed');
	pg_assert_contains('PG_SITE_PUSH_KEEP', $tidy, 'finished rows go after a week');
	pg_assert_contains('q.status <> ?', $tidy, 'a waiting row is never deleted by the tidy-up');
}

function test_site_push_counts()
{
	$body = pg_test_function_source('pg_site_push_count');
	pg_assert_contains("array('sent_count', 'failed_count', 'removed_count')", $body, 'only the three counts are raised');

	foreach (array('ok', 'gone', 'busy', 'failed') as $outcome) {
		$plan = pg_site_push_send_plan($outcome, PG_SITE_PUSH_ATTEMPTS - 1);
		pg_assert_true(in_array($plan['count'], array('', 'sent_count', 'failed_count', 'removed_count'), true), $outcome);
	}

	pg_assert_contains('queued_count', pg_test_function_source('pg_site_push_message_send'), 'the queued count is written');
}

function test_site_push_runners()
{
	$job = file_get_contents(PG_FUNCTIONS_DIR . '/job.php');
	$push_job = file_get_contents(PG_FUNCTIONS_DIR . '/push_job.php');

	pg_assert_contains('pg_site_push_queue_run(10)', $job, 'the general job runs the pass');
	pg_assert_contains('pg_site_push_queue_run(20)', $push_job, 'push_job.php runs the pass');
	pg_assert_contains('pg_site_push_switch_on()', $job, 'only with the switch on');
}

function test_site_push_panel_channel_card()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/fn/site_push_panel.php');

	$off = pg_site_push_channel_card(array('ready' => true, 'on' => false, 'subscribers' => 0, 'last_title' => '', 'last_at' => 0));
	pg_assert_same('site_push', $off['key'], 'its own key');
	pg_assert_same('off', $off['state'], 'off while the switch is off');
	pg_assert_same('messages_notifications.php', $off['url'], 'it leads to the screen');

	$on = pg_site_push_channel_card(array('ready' => true, 'on' => true, 'subscribers' => 1234, 'last_title' => 'Spring <sale>', 'last_at' => 0));
	pg_assert_same('on', $on['state'], 'on');
	pg_assert_contains('1,234', $on['line'], 'the subscribers, formatted');
	pg_assert_contains('Spring <sale>', $on['line'], 'the last message, raw (the card escapes)');

	$none = pg_site_push_channel_card(array('ready' => true, 'on' => true, 'subscribers' => 3, 'last_title' => '', 'last_at' => 0));
	pg_assert_contains(lang('No notification sent yet'), $none['line'], 'nothing sent yet');

	$not_ready = pg_site_push_channel_card(array('ready' => false));
	pg_assert_same('off', $not_ready['state'], 'before the upgrade');

	$cards = pg_messages_channel_cards(array('site_push' => array('ready' => true, 'on' => true, 'subscribers' => 2)), array());
	pg_assert_same('site_push', $cards[count($cards) - 1]['key'], 'the Channels tab shows the card last');
	pg_assert_same(4, count(pg_messages_channel_cards(array(), array())), 'no card without its facts');
	pg_assert_contains('Spring &lt;sale&gt;', pg_messages_channel_cards_html(array($on)), 'escaped on the tab');
}

function test_site_push_panel_pick_items()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/fn/site_push_panel.php');

	$items = pg_site_push_pick_items(array(
		array('url' => 'spring', 'title' => 'Spring', 'description' => str_repeat('d', 300), 'page_id' => 4, 'page_name' => 'spring'),
		array('url' => 'a.pdf', 'title' => 'A file', 'description' => '', 'page_id' => 0, 'page_name' => null),
		array('url' => 'shoes?r=7', 'title' => 'Red shoes', 'description' => 'Soft', 'page_id' => 5, 'page_name' => 'shoes'),
		array('url' => 'https://elsewhere.test/x', 'title' => 'Away', 'description' => '', 'page_id' => 6, 'page_name' => 'x'),
	), array('shop.test'), '/');

	pg_assert_same(2, count($items), 'a file and an address elsewhere are left out');
	pg_assert_same(array('title' => 'Spring', 'body' => str_repeat('d', 200), 'url' => '/spring', 'kind' => 'page'), $items[0], 'a page fills the form, the text cut to 200');
	pg_assert_same('record', $items[1]['kind'], 'a record');
	pg_assert_same('/shoes?r=7', $items[1]['url'], 'its address');

	$sub = pg_site_push_pick_items(array(array('url' => 'spring', 'title' => 'S', 'description' => '', 'page_id' => 4, 'page_name' => 'spring')), array(), '/shop/');
	pg_assert_same('/shop/spring', $sub[0]['url'], 'a site in a folder keeps its folder');
}

function test_site_push_panel_send_errors()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/fn/site_push_panel.php');

	foreach (array('off', 'kind', 'subscription', 'save', 'title', 'body', 'url', 'device') as $code) {
		pg_assert_true(pg_site_push_send_error($code) !== '', $code . ' has a sentence');
	}

	pg_assert_same(lang('The notification was not sent.'), pg_site_push_send_error('unknown'), 'anything else');
	pg_assert_same(array(PG_SITE_PUSH_KIND_NEWS, PG_SITE_PUSH_KIND_TEST), pg_site_push_listed_kinds(), 'stock and order messages are not listed');
}

function test_site_push_panel_list_html()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/fn/site_push_panel.php');

	$when = function ($time) {
		return 'at ' . $time;
	};
	$html = pg_site_push_list_html(array(
		array('id' => 1, 'title' => 'Spring <sale>', 'body' => 'Up to 40%', 'url' => '/spring', 'kind' => 0, 'created_at' => 100, 'queued_count' => 1284, 'sent_count' => 1280, 'failed_count' => 1, 'removed_count' => 3),
		array('id' => 2, 'title' => 'Try', 'body' => '', 'url' => '/', 'kind' => 1, 'created_at' => 50, 'queued_count' => 1, 'sent_count' => 1, 'failed_count' => 0, 'removed_count' => 0),
	), $when);

	pg_assert_contains('Spring &lt;sale&gt;', $html, 'escaped');
	pg_assert_contains('pg-embedded-table', $html, 'a table that grows with its rows');
	pg_assert_false(strpos($html, 'pg-list-table') !== false, 'not the driver: no inner scroll, no selection');
	pg_assert_false(strpos($html, 'checkbox') !== false, 'nothing to select');
	pg_assert_contains(lang('Ended subscriptions'), $html, 'the ended column says what ended');
	pg_assert_contains(h(lang('Subscribers whose browser no longer takes notifications; taken off the list.')), $html, 'with its hint');
	pg_assert_false(strpos($html, '>' . lang('Removed') . '<') !== false, 'no bare "Removed"');
	pg_assert_contains('sm:d-none', $html, 'phone cards');
	pg_assert_same(2, substr_count($html, '<li class="pg-card-inner p-5">'), 'one card a message');
	pg_assert_contains(lang('Subscribers') . ' <strong>1,284</strong>', $html, 'the card shows the subscribers');
	pg_assert_contains(lang('Delivered') . ' <strong>1,280</strong>', $html, 'delivered');
	pg_assert_contains(lang('Failed') . ' <strong>1</strong>', $html, 'and failed');
	pg_assert_contains(lang('No notification sent yet'), pg_site_push_list_html(array(), $when), 'empty');
}

function test_site_push_panel_send_asks_first()
{
	$script = file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_site_push_panel.js');

	pg_assert_contains('window.pgConfirm(', $script, 'the panel\'s own confirmation');
	pg_assert_contains("submitter.value === 'test'", $script, 'a test goes without asking');
	pg_assert_contains("form.addEventListener('submit'", $script, 'Enter in a field asks too');

	require_once(PG_FUNCTIONS_DIR . '/includes/fn/site_push_panel.php');
	$form = pg_site_push_form_html(array('token' => '', 'subscribers' => 1284, 'enabled' => true));
	pg_assert_contains('data-confirm-text="' . h(lang(array('string' => 'It goes to {var:1} subscriber(s) at once and cannot be called back.', 'vars' => array('1,284')))) . '"', $form, 'how many it goes to');
}
