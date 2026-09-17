<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Web push: the site's identity, and the browsers that have asked to hear from
// it.
//
// A subscription belongs to a browser rather than to a person - the same
// operator on a laptop and on a phone is two rows - and the browser's endpoint
// at its push service is what identifies it. Nothing here sends anything yet;
// this is the half that lets a browser say "notify me" and be remembered.
//
// Loaded on demand from api.php, which has already run init.php.
if (!function_exists('validate_user')) {
	exit;
}

// Web push needs an elliptic-curve key pair on prime256v1, which is the only
// curve the specification allows. A shared host can remove any of these
// functions with disable_functions, and on PHP 8 a removed function is an
// undefined one, so each is asked for rather than assumed.
function pg_push_available()
{
	static $available = null;

	if ($available === null) {
		$available = (function_exists('openssl_pkey_new')
			&& function_exists('openssl_pkey_get_details')
			&& function_exists('openssl_get_curve_names')
			&& in_array('prime256v1', openssl_get_curve_names()));
	}

	return $available;
}

// The push_subscriptions table arrives with the upgrade, and files land before
// the schema does. Probed once per request against information_schema, because
// '_' is a wildcard in LIKE.
function pg_push_schema_available()
{
	static $available = null;

	if ($available === null) {
		$count = db_value("SELECT COUNT(*) FROM information_schema.TABLES
			WHERE TABLE_SCHEMA = DATABASE()
			AND TABLE_NAME = 'push_subscriptions'");
		$available = ((int) $count > 0);
	}

	return $available;
}

// The column that ties a subscription to a browser arrives with the upgrade,
// and files land before the schema does.
function pg_push_has_selector()
{
	static $available = null;

	if ($available === null) {
		$count = db_value("SELECT COUNT(*) FROM information_schema.COLUMNS
			WHERE TABLE_SCHEMA = DATABASE()
			AND TABLE_NAME = 'push_subscriptions'
			AND COLUMN_NAME = 'auth_selector'");
		$available = ((int) $count > 0);
	}

	return $available;
}

// The column that records why a device last refused a delivery arrives with the
// upgrade, and files land before the schema does.
function pg_push_has_last_error()
{
	static $available = null;

	if ($available === null) {
		$count = db_value("SELECT COUNT(*) FROM information_schema.COLUMNS
			WHERE TABLE_SCHEMA = DATABASE()
			AND TABLE_NAME = 'push_subscriptions'
			AND COLUMN_NAME = 'last_error'");
		$available = ((int) $count > 0);
	}

	return $available;
}

function pg_push_base64url_encode($value)
{
	return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function pg_push_base64url_decode($value)
{
	$value = strtr($value, '-_', '+/');
	$padding = strlen($value) % 4;

	if ($padding > 0) {
		$value .= str_repeat('=', 4 - $padding);
	}

	return base64_decode($value);
}

// The public half of the site's VAPID pair, in the form the browser wants:
// the uncompressed curve point, base64url, no padding. Generated the first time
// it is asked for and then kept, because handing out a new public key silently
// invalidates every subscription already given away.
function pg_push_vapid_public_key()
{
	if ((!pg_push_available()) || (!pg_push_schema_available())) {
		return '';
	}

	$row = db_item("SELECT push_vapid_public, push_vapid_private FROM config LIMIT 1");

	if (!$row) {
		return '';
	}

	if ((trim((string) $row['push_vapid_public']) != '') && (trim((string) $row['push_vapid_private']) != '')) {
		return $row['push_vapid_public'];
	}

	return pg_push_create_vapid_keys();
}

// Makes the pair once. Two requests arriving together would each write a pair
// and the second would win, so the write only fills a row that is still empty -
// whichever pair lands first is the one every browser is then told about.
function pg_push_create_vapid_keys()
{
	$arguments = array(
		'curve_name'       => 'prime256v1',
		'private_key_type' => OPENSSL_KEYTYPE_EC
	);

	$key = @openssl_pkey_new($arguments);

	// OpenSSL reads a configuration file before it will make a key, and on a
	// server where none is installed - Windows unless someone put one there, or
	// a host whose OPENSSL_CONF points at a path that has since gone - the call
	// fails with "configuration file routines: no such file" and no amount of
	// correct arguments helps. The file shipped beside this one is the fallback,
	// which is why the second attempt is worth making before giving up.
	if (!$key) {
		$configuration = dirname(__FILE__) . '/openssl.cnf';

		if (file_exists($configuration)) {
			$arguments['config'] = $configuration;
			$key = @openssl_pkey_new($arguments);
		}
	}

	if (!$key) {
		return '';
	}

	$details = @openssl_pkey_get_details($key);

	if ((!$details) || (!isset($details['ec']['x'])) || (!isset($details['ec']['y'])) || (!isset($details['ec']['d']))) {
		return '';
	}

	// The point the browser is given is 0x04 followed by X and Y, each padded to
	// the curve's 32 bytes. OpenSSL returns them without leading zeroes, so a
	// coordinate that happens to start with one would otherwise be a byte short
	// and the subscription would be rejected as malformed.
	$public = "\x04"
		. str_pad($details['ec']['x'], 32, "\0", STR_PAD_LEFT)
		. str_pad($details['ec']['y'], 32, "\0", STR_PAD_LEFT);

	$public = pg_push_base64url_encode($public);

	// PEM is the convenient form for signing later, but exporting one needs an
	// OpenSSL configuration file and shared hosts do not always have a usable
	// one. The raw private scalar always exists, so it is what gets stored when
	// the export is refused; both forms are recognised when the key is read
	// back, and the 'raw:' marker is what tells them apart.
	$private = '';

	if ((!function_exists('openssl_pkey_export')) || (!@openssl_pkey_export($key, $private, null, $arguments)) || (trim((string) $private) == '')) {
		$private = 'raw:' . pg_push_base64url_encode(str_pad($details['ec']['d'], 32, "\0", STR_PAD_LEFT));
	}

	db("UPDATE config
		SET push_vapid_public = '" . escape($public) . "',
			push_vapid_private = '" . escape($private) . "'
		WHERE push_vapid_public = '' OR push_vapid_private = '' OR push_vapid_private IS NULL");

	return (string) db_value("SELECT push_vapid_public FROM config LIMIT 1");
}

// Remembers one browser. The endpoint is the natural key but runs past what an
// index can carry, so the unique key is over its digest; re-subscribing the same
// browser therefore updates the row it already has instead of adding a second.
//
// The row is re-pointed at whoever is signed in now: a browser shared by two
// people must stop delivering the previous account's notifications the moment
// the second one subscribes.
function pg_push_subscription_save($user_id, $endpoint, $p256dh, $auth, $user_agent)
{
	if (!pg_push_schema_available()) {
		return false;
	}

	$user_id  = (int) $user_id;
	$endpoint = trim((string) $endpoint);

	// Only an https address from the browser's own push service is stored. This
	// is not the send-time check - that one belongs to the sender, which resolves
	// the name and pins it - but a subscription that could never be delivered has
	// no business being written down.
	if (($user_id < 1) || (strlen($endpoint) > 500) || (stripos($endpoint, 'https://') !== 0)) {
		return false;
	}

	if (filter_var($endpoint, FILTER_VALIDATE_URL) === false) {
		return false;
	}

	$now = time();

	// Which browser this is, in the software's own terms: the selector half of
	// the remember-me cookie. Stored so that signing out here - or an operator
	// ending this session from the list - can take the subscription with it.
	$selector = '';

	if (isset($_COOKIE['software']['auth'])) {
		$parts = explode(':', (string) $_COOKIE['software']['auth'], 2);
		$selector = (string) $parts[0];
	}

	$selector_column = (pg_push_has_selector()) ? ', auth_selector' : '';
	$selector_value = (pg_push_has_selector()) ? ", '" . escape($selector) . "'" : '';

	db("INSERT INTO push_subscriptions
			(user_id, endpoint, endpoint_hash, p256dh, auth, user_agent, created_timestamp, last_seen_timestamp" . $selector_column . ")
		VALUES (
			'" . $user_id . "',
			'" . escape($endpoint) . "',
			'" . escape(hash('sha256', $endpoint)) . "',
			'" . escape(substr((string) $p256dh, 0, 255)) . "',
			'" . escape(substr((string) $auth, 0, 64)) . "',
			'" . escape(substr((string) $user_agent, 0, 255)) . "',
			'" . $now . "',
			'" . $now . "'" . $selector_value . ")
		ON DUPLICATE KEY UPDATE
			user_id = VALUES(user_id),
			p256dh = VALUES(p256dh),
			auth = VALUES(auth),
			user_agent = VALUES(user_agent),
			last_seen_timestamp = VALUES(last_seen_timestamp),
			fail_count = 0"
		. ((pg_push_has_selector()) ? ", auth_selector = VALUES(auth_selector)" : "") . "");

	return true;
}

// Forgetting a browser. Keyed on the endpoint rather than on the person,
// because the browser is what unsubscribed - and a browser whose subscription
// the push service has already retired reports the same endpoint back.
function pg_push_subscription_delete($endpoint)
{
	if (!pg_push_schema_available()) {
		return;
	}

	$endpoint = trim((string) $endpoint);

	if ($endpoint == '') {
		return;
	}

	db("DELETE FROM push_subscriptions
		WHERE endpoint_hash = '" . escape(hash('sha256', $endpoint)) . "'");
}

function pg_push_subscription_exists($endpoint)
{
	if (!pg_push_schema_available()) {
		return false;
	}

	$endpoint = trim((string) $endpoint);

	if ($endpoint == '') {
		return false;
	}

	$count = db_value("SELECT COUNT(*) FROM push_subscriptions
		WHERE endpoint_hash = '" . escape(hash('sha256', $endpoint)) . "'");

	return ((int) $count > 0);
}

function pg_push_subscriptions_for_user($user_id)
{
	if (!pg_push_schema_available()) {
		return array();
	}

	return db_items("SELECT id, endpoint, p256dh, auth, user_agent, last_seen_timestamp
		FROM push_subscriptions
		WHERE user_id = '" . (int) $user_id . "'");
}

// ---------------------------------------------------------------------------
// Sending
// ---------------------------------------------------------------------------

// The push is sent with no body at all.
//
// A push service will carry an encrypted payload, but then the text of every
// notification passes through Google's or Apple's servers. An empty push is
// only a signal to wake up; the worker then asks this site what to show, over
// the operator's own session. Nothing about the notification leaves the site,
// and the encryption layer - a second key agreement per message - is not
// needed at all.
//
// The cost is that a device whose panel session has expired gets the generic
// wording instead of the detail. That is the right way round: the detail is
// what needed protecting.
function pg_push_private_key()
{
	$private = trim((string) db_value("SELECT push_vapid_private FROM config LIMIT 1"));

	if ($private == '') {
		return false;
	}

	if (strpos($private, 'raw:') === 0) {
		return pg_push_key_from_scalar(pg_push_base64url_decode(substr($private, 4)));
	}

	return @openssl_pkey_get_private($private);
}

// Rebuilds a usable key from the bare private scalar, for the servers where the
// PEM export was refused when the pair was made. The structure is the SEC1
// private key OpenSSL would have written itself: version 1, the 32 byte scalar,
// then the curve it belongs to.
function pg_push_key_from_scalar($scalar)
{
	if (strlen($scalar) != 32) {
		return false;
	}

	$sequence = "\x02\x01\x01"
		. "\x04\x20" . $scalar
		. "\xa0\x0a" . "\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07";

	$der = "\x30" . chr(strlen($sequence)) . $sequence;

	$pem = "-----BEGIN EC PRIVATE KEY-----\n"
		. chunk_split(base64_encode($der), 64, "\n")
		. "-----END EC PRIVATE KEY-----\n";

	return @openssl_pkey_get_private($pem);
}

// OpenSSL signs into ASN.1: a sequence of two integers. JWS wants the two
// numbers laid out flat, 32 bytes each. The integers are stored signed, so one
// that begins above 0x7f carries a leading zero byte that has to come off, and
// one shorter than 32 bytes has to be padded back up.
function pg_push_der_to_signature($der)
{
	$length = strlen($der);

	if (($length < 8) || (ord($der[0]) != 0x30)) {
		return '';
	}

	$offset = 1;
	$header = ord($der[$offset]);
	$offset++;

	if ($header & 0x80) {
		$offset += ($header & 0x7f);
	}

	$parts = array();

	for ($part = 0; $part < 2; $part++) {

		if (($offset + 2 > $length) || (ord($der[$offset]) != 0x02)) {
			return '';
		}

		$offset++;
		$size = ord($der[$offset]);
		$offset++;

		if ($offset + $size > $length) {
			return '';
		}

		$value = ltrim(substr($der, $offset, $size), "\x00");
		$offset += $size;

		if (strlen($value) > 32) {
			return '';
		}

		$parts[] = str_pad($value, 32, "\0", STR_PAD_LEFT);
	}

	return $parts[0] . $parts[1];
}

// The token that proves the push came from this site. Signed per push service
// rather than per subscription: the audience is the service's origin, so one
// token covers every browser that uses the same service.
function pg_push_vapid_token($endpoint)
{
	static $tokens = array();

	$parts = parse_url($endpoint);

	if ((!$parts) || (!isset($parts['scheme'])) || (!isset($parts['host']))) {
		return '';
	}

	$audience = $parts['scheme'] . '://' . $parts['host']
		. (isset($parts['port']) ? ':' . $parts['port'] : '');

	if (isset($tokens[$audience])) {
		return $tokens[$audience];
	}

	$key = pg_push_private_key();

	if (!$key) {
		return '';
	}

	$header = pg_push_base64url_encode(json_encode(array('typ' => 'JWT', 'alg' => 'ES256')));

	// Who to complain to. The specification accepts an https address or a
	// mailto, and Apple's service has been the fussy one: a contact address is
	// what it expects, so the site's own e-mail is preferred and the site
	// address is only the fallback for an installation that has none.
	$subject = URL_SCHEME . HOSTNAME_SETTING;

	if ((defined('EMAIL_ADDRESS')) && (filter_var(EMAIL_ADDRESS, FILTER_VALIDATE_EMAIL) !== false)) {
		$subject = 'mailto:' . EMAIL_ADDRESS;
	}

	// Twelve hours: a push service refuses a token that claims more than a day,
	// and a short life limits what a copied token is worth.
	$claims = pg_push_base64url_encode(json_encode(array(
		'aud' => $audience,
		'exp' => time() + (12 * 60 * 60),
		'sub' => $subject
	), JSON_UNESCAPED_SLASHES));

	$signature = '';

	if (!@openssl_sign($header . '.' . $claims, $signature, $key, OPENSSL_ALGO_SHA256)) {
		return '';
	}

	$signature = pg_push_der_to_signature($signature);

	if ($signature == '') {
		return '';
	}

	$tokens[$audience] = $header . '.' . $claims . '.' . pg_push_base64url_encode($signature);

	return $tokens[$audience];
}

// One delivery. The address is a URL the browser gave us, so it goes out
// through the outbound client that resolves the name once and calls the address
// it resolved - the same protection the webhook sender uses.
function pg_push_send_to_subscription($subscription)
{
	include_once(dirname(__FILE__) . '/api/outbound/http.php');

	$token = pg_push_vapid_token($subscription['endpoint']);

	if ($token == '') {
		return array('ok' => false, 'status' => 0, 'error' => 'The VAPID token could not be signed.');
	}

	$result = api_http_post($subscription['endpoint'], '', array(
		'Authorization: vapid t=' . $token . ', k=' . pg_push_vapid_public_key(),
		'TTL: 3600',
		'Urgency: normal',
		'Content-Length: 0'
	), 10);

	$id = (int) $subscription['id'];

	// 404 and 410 are the push service saying this browser is gone for good -
	// the application was uninstalled, or the subscription was replaced. Keeping
	// the row would mean failing on it forever.
	if (($result['status'] == 404) || ($result['status'] == 410)) {

		pg_push_subscription_delete($subscription['endpoint']);

	} elseif ($result['ok']) {

		db("UPDATE push_subscriptions
			SET last_ok_timestamp = '" . time() . "', fail_count = 0" . ((pg_push_has_last_error()) ? ", last_error = ''" : "") . "
			WHERE id = '" . $id . "'");

	} else {

		$reason = ($result['error'] != '') ? $result['error'] : ('HTTP ' . (int) $result['status']);

		db("UPDATE push_subscriptions
			SET fail_count = fail_count + 1" . ((pg_push_has_last_error()) ? ", last_error = '" . escape(substr($reason, 0, 255)) . "'" : "") . "
			WHERE id = '" . $id . "'");
	}

	return $result;
}

// Wakes every browser one person has subscribed. Answers a row per device so a
// caller can say which one refused.
function pg_push_notify_user($user_id)
{
	$sent = array();

	foreach (pg_push_subscriptions_for_user($user_id) as $subscription) {

		$result = pg_push_send_to_subscription($subscription);

		$sent[] = array(
			'device' => substr((string) $subscription['user_agent'], 0, 60),
			'status' => $result['status'],
			'ok'     => $result['ok'],
			'error'  => $result['error']
		);
	}

	return $sent;
}

// ---------------------------------------------------------------------------
// The queue
// ---------------------------------------------------------------------------

function pg_push_queue_available()
{
	static $available = null;

	if ($available === null) {
		$count = db_value("SELECT COUNT(*) FROM information_schema.TABLES
			WHERE TABLE_SCHEMA = DATABASE()
			AND TABLE_NAME = 'push_queue'");
		$available = ((int) $count > 0);
	}

	return $available;
}

// How long a chat message waits before it counts as unanswered.
//
// The wait is the whole mechanism: a minute is long enough that somebody
// reading the message on the screen in front of them is not buzzed as well, and
// short enough that a real absence is noticed while the sender is still
// waiting. A development installation can shorten it in data/config.php to make
// the path testable without sitting through the wait; the product default is
// not affected by that constant being absent, which it is everywhere else.
function pg_push_chat_delay()
{
	if ((defined('PUSH_CHAT_DELAY')) && (((int) PUSH_CHAT_DELAY) > 0)) {
		return (int) PUSH_CHAT_DELAY;
	}

	return 60;
}

// Asks for one person to be woken about one thing.
//
// INSERT ... ON DUPLICATE KEY is what lets the callers be careless: the same
// event queued twice is one row, and the later delay wins - a chat message
// re-sent by a retried request pushes its own wait out rather than arriving
// twice.
function pg_push_enqueue($user_id, $source, $reference_id, $delay = 0)
{
	if (!pg_push_queue_available()) {
		return;
	}

	$user_id = (int) $user_id;

	if ($user_id < 1) {
		return;
	}

	$now = time();
	$due = $now + (int) $delay;

	db("INSERT INTO push_queue (user_id, source, reference_id, send_after, created_timestamp)
		VALUES (
			'" . $user_id . "',
			'" . escape($source) . "',
			'" . (int) $reference_id . "',
			'" . $due . "',
			'" . $now . "')
		ON DUPLICATE KEY UPDATE send_after = VALUES(send_after)");
}

// Queues everybody who is allowed to see a panel notification.
//
// The visibility test is the one the bell uses, asked once per account against
// that account's own row - so a device is only ever woken about something its
// owner could have opened the panel and read.
function pg_push_enqueue_notification($notification)
{
	if ((!pg_push_queue_available()) || (!pg_push_schema_available())) {
		return;
	}

	include_once(dirname(__FILE__) . '/notifications.php');
	include_once(dirname(__FILE__) . '/authentication.php');

	// Only the accounts that have a device subscribed are worth asking about.
	// On a site where nobody turned notifications on this is a single indexed
	// read and nothing else happens.
	$user_ids = db_values("SELECT DISTINCT user_id FROM push_subscriptions");

	foreach ($user_ids as $user_id) {

		$user_row = pg_load_user_row((int) $user_id);

		if (!$user_row) {
			continue;
		}

		if (pg_notification_visible_to_user($notification, $user_row)) {
			pg_push_enqueue($user_id, 'notification', $notification['id']);
		}
	}
}

// Whether the thing a row was queued for still deserves a banner.
//
// Checked at send time rather than at queue time, because the wait is the whole
// point: somebody who read the message on their screen in the meantime should
// not be told about it again by their phone.
function pg_push_queue_still_relevant($row)
{
	$user_id = (int) $row['user_id'];
	$reference_id = (int) $row['reference_id'];

	if ($row['source'] == 'notification') {

		include_once(dirname(__FILE__) . '/notifications.php');

		$notification = db_item("SELECT id, readed FROM notifications WHERE id = '" . $reference_id . "'");

		if (!$notification) {
			return false;
		}

		return (!pg_notification_is_read($notification, $user_id));
	}

	if ($row['source'] == 'chat') {

		// The reference is the message. It is still worth a banner while the
		// person it was sent to has not read past it.
		$message = db_item("SELECT id, conversation_id, sender_user_id FROM chat_messages
			WHERE id = '" . $reference_id . "'");

		if ((!$message) || ((int) $message['sender_user_id'] == $user_id)) {
			return false;
		}

		$conversation = db_item("SELECT initiator_user_id, target_user_id,
				initiator_last_read_id, target_last_read_id
			FROM chat_conversations
			WHERE id = '" . (int) $message['conversation_id'] . "'");

		if (!$conversation) {
			return false;
		}

		$read_id = ((int) $conversation['initiator_user_id'] == $user_id)
			? (int) $conversation['initiator_last_read_id']
			: (int) $conversation['target_last_read_id'];

		return ($read_id < $reference_id);
	}

	return false;
}

// Works the queue for as long as it is allowed to.
//
// Everything due for one person collapses into a single wake-up: the push
// carries no text, so two reasons to look are still one look. The rows are
// taken away first and the send follows - a row that is sent twice is a
// duplicate banner, while a row left behind after a successful send would be
// sent again on the next tick anyway.
function pg_push_queue_run($budget_seconds = 10)
{
	if ((!pg_push_queue_available()) || (!pg_push_schema_available())) {
		return 0;
	}

	$started = microtime(true);
	$now = time();
	$woken = 0;

	$due = db_items("SELECT user_id, MIN(id) AS first_id, COUNT(*) AS items
		FROM push_queue
		WHERE send_after <= '" . $now . "'
		GROUP BY user_id
		ORDER BY first_id
		LIMIT 20");

	foreach ($due as $person) {

		if ((microtime(true) - $started) > $budget_seconds) {
			break;
		}

		$user_id = (int) $person['user_id'];

		$rows = db_items("SELECT id, user_id, source, reference_id, attempts
			FROM push_queue
			WHERE user_id = '" . $user_id . "' AND send_after <= '" . $now . "'");

		$relevant = array();
		$stale = array();

		foreach ($rows as $row) {

			if (pg_push_queue_still_relevant($row)) {
				$relevant[] = (int) $row['id'];
			} else {
				$stale[] = (int) $row['id'];
			}
		}

		if ($stale) {
			db("DELETE FROM push_queue WHERE id IN (" . implode(',', $stale) . ")");
		}

		if (!$relevant) {
			continue;
		}

		$sent = pg_push_notify_user($user_id);
		$delivered = false;
		$error = '';

		foreach ($sent as $result) {

			if ($result['ok']) {
				$delivered = true;
			} elseif ($error == '') {
				$error = (string) $result['error'];
			}
		}

		// No devices left at all is not a failure to retry - the person
		// unsubscribed, or the push service retired every subscription they
		// had.
		if (($delivered) || (!$sent)) {

			db("DELETE FROM push_queue WHERE id IN (" . implode(',', $relevant) . ")");
			$woken++;

		} else {

			// The push service is having a moment. Six tries over about a day,
			// the same shape the webhook queue backs off with, then the row is
			// given up: a banner nobody could be shown for a day is not news
			// any more.
			db("UPDATE push_queue
				SET attempts = attempts + 1,
					send_after = '" . $now . "' + CASE
						WHEN attempts < 1 THEN 60
						WHEN attempts < 2 THEN 300
						WHEN attempts < 3 THEN 1800
						WHEN attempts < 4 THEN 7200
						ELSE 21600 END,
					last_error = '" . escape(substr($error, 0, 255)) . "'
				WHERE id IN (" . implode(',', $relevant) . ")");

			db("DELETE FROM push_queue WHERE attempts >= 6 AND id IN (" . implode(',', $relevant) . ")");
		}
	}

	return $woken;
}
