<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visitor notifications (2026.11.0, 11.0.227): a site visitor's browser
 * subscribed to the site's news without an account.
 *
 * The "Notify me" block posts the browser's push subscription (endpoint and
 * keys) to site_push.php, which keeps it in site_push_subscription with the
 * hash of a secret handed back once; the site's worker keeps the secret and,
 * woken by an empty push, asks site_push.php with it for its waiting message.
 * The push itself carries no text, so no payload encryption is needed.
 *
 * Tables of their own: a visitor's row never goes into the panel's
 * push_subscriptions or push_queue, whose pass deletes the rows of an
 * account it cannot wake. An endpoint is taken only as https on a known push
 * service's host, with keys of the right length, from a page of this site
 * and under a rate limit per address. A yes and a stop are written to the
 * consent log (channel push, purpose news, source notify). Signed in, the
 * row carries the account, so the privacy export and erasure find it (the
 * account's contact through it); signed out, it names nobody.
 *
 * Everything here works as "not there yet" until the 11.0.227 step has run
 * (pg_site_push_ready()), and nothing is taken while the "Visitor
 * notifications" switch in Features is off (pg_site_push_switch_on()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * The settings text of the "Visitor notifications" switch: '1' on, missing
 * off, so every site and a new install start off.
 */
define('PG_SITE_PUSH_SWITCH', 'site_push');
/**
 * Subscriptions and stops one address may post in the window.
 */
define('PG_SITE_PUSH_WRITE_LIMIT', 10);
define('PG_SITE_PUSH_WRITE_WINDOW', 3600);
/**
 * Message reads one address may make in the window: a worker asks once a
 * push, so this only stops a script guessing secrets.
 */
define('PG_SITE_PUSH_READ_LIMIT', 120);
define('PG_SITE_PUSH_READ_WINDOW', 3600);
/**
 * A message is read within this many seconds of its push, or never.
 */
define('PG_SITE_PUSH_MESSAGE_TTL', 86400);
/**
 * Queue statuses: waiting to be pushed, pushed and not yet read by the
 * worker, read by the worker, given up.
 */
define('PG_SITE_PUSH_WAITING', 0);
define('PG_SITE_PUSH_PUSHED', 1);
define('PG_SITE_PUSH_READ', 2);
define('PG_SITE_PUSH_FAILED', 3);

/**
 * Whether the 11.0.227 step has run: the last column of its last table.
 *
 * @return bool
 */
function pg_site_push_ready()
{
    return pg_schema_has('site_push_queue', 'fetched_at');
}

/**
 * The switch's stored value for on or off: '1', or '' (the row removed).
 * Pure.
 *
 * @param bool $on
 * @return string
 */
function pg_site_push_switch_value($on)
{
    return $on ? '1' : '';
}

/**
 * Whether the "Visitor notifications" switch is on. The one reader.
 *
 * @return bool
 */
function pg_site_push_switch_on()
{
    if (!function_exists('pg_config_text')) {
        require_once(PG_FUNCTIONS_DIR . '/includes/fn/config_text.php');
    }

    return pg_config_text(PG_SITE_PUSH_SWITCH) === '1';
}

/**
 * Turns the switch on or off. The one saver; whoever calls it has checked
 * the right to change Features and the form's token.
 *
 * @param bool $on
 * @return bool written
 */
function pg_site_push_switch_save($on)
{
    if (!function_exists('pg_config_text_save')) {
        require_once(PG_FUNCTIONS_DIR . '/includes/fn/config_text.php');
    }

    return (bool) pg_config_text_save(PG_SITE_PUSH_SWITCH, pg_site_push_switch_value((bool) $on));
}

/**
 * Whether visitors may subscribe now: the switch is on and the tables are
 * there.
 *
 * @return bool
 */
function pg_site_push_enabled()
{
    return pg_site_push_switch_on() && pg_site_push_ready();
}

/**
 * The push services an endpoint may belong to, each with the host names it
 * sends from: an exact name, or ".suffix" for any name under it. An
 * anonymous visitor names the address the server will call, so no other
 * host is taken.
 *
 * @return array  service => list of hosts
 */
function pg_site_push_services()
{
    return array(
        'fcm'     => array('fcm.googleapis.com', 'android.googleapis.com'),
        'mozilla' => array('.push.services.mozilla.com'),
        'windows' => array('.notify.windows.com'),
        'apple'   => array('web.push.apple.com', '.push.apple.com'),
    );
}

/**
 * The push service of an endpoint, or '' when it is not taken: https on the
 * default port, a known service's host, no user part, at most 500
 * characters (the column). Pure.
 *
 * @param mixed $endpoint
 * @return string
 */
function pg_site_push_endpoint_service($endpoint)
{
    if (!is_string($endpoint) || ($endpoint === '') || (strlen($endpoint) > 500)) {
        return '';
    }

    if (preg_match('/[^\x21-\x7E]/', $endpoint) || (filter_var($endpoint, FILTER_VALIDATE_URL) === false)) {
        return '';
    }

    $parts = parse_url($endpoint);

    if (!is_array($parts) || !isset($parts['scheme'], $parts['host']) || (strtolower($parts['scheme']) !== 'https')) {
        return '';
    }

    if (isset($parts['user']) || isset($parts['pass']) || (isset($parts['port']) && ((int) $parts['port'] !== 443))) {
        return '';
    }

    $host = strtolower($parts['host']);

    foreach (pg_site_push_services() as $service => $hosts) {
        foreach ($hosts as $known) {
            if ($known[0] === '.') {
                if ((strlen($host) > strlen($known)) && (substr($host, -strlen($known)) === $known)) {
                    return $service;
                }
            } elseif ($host === $known) {
                return $service;
            }
        }
    }

    return '';
}

/**
 * Whether a subscription's keys have the right length: p256dh an uncompressed
 * P-256 point (65 bytes, starting 0x04), auth 16 bytes, both base64url as the
 * browser gives them. Pure.
 *
 * @param mixed $p256dh
 * @param mixed $auth
 * @return bool
 */
function pg_site_push_keys_valid($p256dh, $auth)
{
    if (!is_string($p256dh) || !is_string($auth)) {
        return false;
    }

    if ((preg_match('/^[A-Za-z0-9_-]{86,88}={0,2}$/', $p256dh) !== 1) || (preg_match('/^[A-Za-z0-9_-]{22,24}={0,2}$/', $auth) !== 1)) {
        return false;
    }

    $point = base64_decode(strtr(rtrim($p256dh, '='), '-_', '+/'), true);
    $secret = base64_decode(strtr(rtrim($auth, '='), '-_', '+/'), true);

    return is_string($point) && (strlen($point) === 65) && ($point[0] === "\x04")
        && is_string($secret) && (strlen($secret) === 16);
}

/**
 * A page language as the block sends it ("tr", "en-GB"), or '' when it is
 * not one. Pure.
 *
 * @param mixed $value
 * @return string
 */
function pg_site_push_language($value)
{
    if (!is_string($value) || (preg_match('/^[a-z]{2,3}(-[A-Za-z0-9]{2,4})?$/', $value) !== 1)) {
        return '';
    }

    return $value;
}

/**
 * The subscription a post carries, checked, or the reason it is refused:
 * endpoint (not https or not a known service), keys. Pure.
 *
 * @param array $post  endpoint, p256dh, auth, language
 * @return array|string  endpoint, p256dh, auth, service, language; or an error code
 */
function pg_site_push_subscription_check(array $post)
{
    $endpoint = isset($post['endpoint']) && is_string($post['endpoint']) ? trim($post['endpoint']) : '';
    $service = pg_site_push_endpoint_service($endpoint);

    if ($service === '') {
        return 'endpoint';
    }

    $p256dh = isset($post['p256dh']) ? $post['p256dh'] : '';
    $auth = isset($post['auth']) ? $post['auth'] : '';

    if (!pg_site_push_keys_valid($p256dh, $auth)) {
        return 'keys';
    }

    return array(
        'endpoint' => $endpoint,
        'p256dh'   => rtrim($p256dh, '='),
        'auth'     => rtrim($auth, '='),
        'service'  => $service,
        'language' => pg_site_push_language(isset($post['language']) ? $post['language'] : ''),
    );
}

/**
 * A new secret for a subscription: 32 random bytes as hex, handed to the
 * browser once and stored only as its hash.
 *
 * @return string
 */
function pg_site_push_secret_new()
{
    return bin2hex(random_bytes(32));
}

/**
 * Whether a value has the shape of a secret. Pure.
 *
 * @param mixed $secret
 * @return bool
 */
function pg_site_push_secret_valid($secret)
{
    return is_string($secret) && (preg_match('/^[0-9a-f]{64}$/', $secret) === 1);
}

/**
 * The stored form of a secret. Pure.
 *
 * @param string $secret
 * @return string
 */
function pg_site_push_secret_hash($secret)
{
    return hash('sha256', (string) $secret);
}

/**
 * Whether this request's address is over a limit of the door. The
 * same-origin check stops other sites' pages, not scripts, and the
 * firewall's own limits refuse nothing while it is off or in monitor mode,
 * so the door counts for itself.
 *
 * @param string $kind  write | read
 * @return bool
 */
function pg_site_push_throttled($kind)
{
    if (!function_exists('waf_rate_exceeded') || !function_exists('waf_client_ip') || !function_exists('waf_ip_subject')) {
        return false;
    }

    $ip = (string) waf_client_ip();

    if ($ip === '') {
        return false;
    }

    $read = ($kind === 'read');
    $limit = $read ? PG_SITE_PUSH_READ_LIMIT : PG_SITE_PUSH_WRITE_LIMIT;
    $window = $read ? PG_SITE_PUSH_READ_WINDOW : PG_SITE_PUSH_WRITE_WINDOW;

    if (!waf_rate_exceeded(waf_ip_subject($ip), $read ? 'site_push_read' : 'site_push_write', $limit, $window)) {
        return false;
    }

    if (function_exists('waf_log_event') && isset(db::$con) && db::$con) {
        waf_log_event('rate', 'rate-site-push', 'rate', 5, 'site_push.php', $limit . '/' . (int) round($window / 60) . 'min');
    }

    return true;
}

/**
 * The door's answer before anything is read or written: the status and
 * error code that refuse the request, or null when it may go on. The order
 * is method, origin, switch, rate, so a refused request costs no rate
 * count. Pure: the caller passes what it found.
 *
 * @param string $method     the request method
 * @param array  $server     $_SERVER, for the origin check
 * @param bool   $enabled    pg_site_push_enabled()
 * @param bool   $throttled  pg_site_push_throttled() of the action's kind
 * @return array|null  array(status, error)
 */
function pg_site_push_door_refusal($method, array $server, $enabled, $throttled)
{
    if ($method !== 'POST') {
        return array(405, 'method');
    }

    if (!pg_consent_answer_same_origin($server)) {
        return array(403, 'origin');
    }

    if (!$enabled) {
        return array(404, 'off');
    }

    if ($throttled) {
        return array(429, 'rate');
    }

    return null;
}

/**
 * The account a subscription is tied to: the signed-in account, or 0.
 * Staff browsing as a customer subscribe as nobody.
 *
 * @return int
 */
function pg_site_push_user_id()
{
    if (defined('USER_LOGGED_IN') && USER_LOGGED_IN && empty($_SESSION['software']['ghost'])) {
        return (int) USER_ID;
    }

    return 0;
}

/**
 * Keeps a checked subscription and returns its new secret. The same browser
 * subscribing again (the same endpoint) gets new keys and a new secret; an
 * account it was tied to is kept when the visitor is now signed out. Writes
 * nothing while the switch is off or the step has not run.
 *
 * @param array $subscription  pg_site_push_subscription_check()'s array
 * @param int   $user_id       the signed-in account (pg_site_push_user_id()), or 0
 * @return string|false  the secret, or false
 */
function pg_site_push_subscription_save(array $subscription, $user_id)
{
    if (!pg_site_push_enabled()) {
        return false;
    }

    $checked = pg_site_push_subscription_check($subscription);

    if (!is_array($checked)) {
        return false;
    }

    $secret = pg_site_push_secret_new();
    $now = time();
    $user_id = max(0, (int) $user_id);

    $saved = db_q("INSERT INTO site_push_subscription
            (endpoint, endpoint_hash, p256dh, auth, service, secret_hash, language, user_id, created_at, last_seen_at, fail_count)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)
        ON DUPLICATE KEY UPDATE
            p256dh = VALUES(p256dh),
            auth = VALUES(auth),
            service = VALUES(service),
            secret_hash = VALUES(secret_hash),
            language = VALUES(language),
            user_id = IF(VALUES(user_id) > 0, VALUES(user_id), user_id),
            last_seen_at = VALUES(last_seen_at),
            fail_count = 0",
        array(
            $checked['endpoint'],
            hash('sha256', $checked['endpoint']),
            $checked['p256dh'],
            $checked['auth'],
            $checked['service'],
            pg_site_push_secret_hash($secret),
            $checked['language'],
            (string) $user_id,
            (string) $now,
            (string) $now,
        ));

    if (!$saved) {
        return false;
    }

    pg_site_push_consent_record('granted', $user_id);

    return $secret;
}

/**
 * The subscription a secret belongs to, or null.
 *
 * @param string $secret
 * @return array|null  id, user_id
 */
function pg_site_push_subscription_by_secret($secret)
{
    if (!pg_site_push_secret_valid($secret) || !pg_site_push_ready()) {
        return null;
    }

    $row = db_item("SELECT id, user_id FROM site_push_subscription
        WHERE secret_hash = '" . e(pg_site_push_secret_hash($secret)) . "'");

    return $row ? $row : null;
}

/**
 * Removes the subscription of a secret ("Stop"), with its queue rows, and
 * writes the stop to the consent log.
 *
 * @param string $secret
 * @return bool  a subscription was removed
 */
function pg_site_push_subscription_remove($secret)
{
    if (!pg_site_push_enabled()) {
        return false;
    }

    $row = pg_site_push_subscription_by_secret($secret);

    if (!$row) {
        return false;
    }

    $id = (int) $row['id'];

    db_q("DELETE FROM site_push_queue WHERE subscription_id = ?", array((string) $id));

    if (!db_q("DELETE FROM site_push_subscription WHERE id = ?", array((string) $id))) {
        return false;
    }

    pg_site_push_consent_record('refused', (int) $row['user_id']);

    return true;
}

/**
 * Writes a yes or a stop to the consent log: channel push, purpose news,
 * source notify, with the account when there is one.
 *
 * @param string $status   granted | refused
 * @param int    $user_id
 * @return int  rows written
 */
function pg_site_push_consent_record($status, $user_id)
{
    if (!function_exists('pg_consent_log_record')) {
        return 0;
    }

    return (int) pg_consent_log_record(array(
        'channel'    => 'push',
        'purpose'    => 'news',
        'status'     => (string) $status,
        'source'     => 'notify',
        'user_id'    => (int) $user_id,
    ));
}

/**
 * The query of the message waiting for one subscription: pushed, not yet
 * read, pushed within PG_SITE_PUSH_MESSAGE_TTL, the newest first. Only that
 * subscription's queue rows are looked at: a secret never reads another
 * browser's message. Pure.
 *
 * @param int $subscription_id
 * @param int $now
 * @return array  array(sql, params)
 */
function pg_site_push_waiting_query($subscription_id, $now)
{
    return array(
        "SELECT q.message_id, m.title, m.body, m.url
        FROM site_push_queue AS q
        INNER JOIN site_push_message AS m ON m.id = q.message_id
        WHERE q.subscription_id = ? AND q.status = ? AND q.sent_at >= ?
        ORDER BY q.sent_at DESC, q.message_id DESC
        LIMIT 1",
        array((string) (int) $subscription_id, (string) PG_SITE_PUSH_PUSHED, (string) ((int) $now - PG_SITE_PUSH_MESSAGE_TTL)),
    );
}

/**
 * The message waiting for the browser of a secret, marked read, or null when
 * there is none (the worker then shows its fixed short notification).
 *
 * @param string $secret
 * @return array|null  title, body, url
 */
function pg_site_push_waiting_message($secret)
{
    if (!pg_site_push_enabled()) {
        return null;
    }

    $subscription = pg_site_push_subscription_by_secret($secret);

    if (!$subscription) {
        return null;
    }

    $now = time();
    list($sql, $params) = pg_site_push_waiting_query((int) $subscription['id'], $now);
    $result = db_q($sql, $params);
    $row = $result ? mysqli_fetch_assoc($result) : null;

    if (!$row) {
        return null;
    }

    db_q("UPDATE site_push_queue SET status = ?, fetched_at = ? WHERE message_id = ? AND subscription_id = ?", array(
        (string) PG_SITE_PUSH_READ,
        (string) $now,
        (string) (int) $row['message_id'],
        (string) (int) $subscription['id'],
    ));

    db_q("UPDATE site_push_subscription SET last_seen_at = ? WHERE id = ?", array((string) $now, (string) (int) $subscription['id']));

    return array(
        'title' => (string) $row['title'],
        'body'  => (string) $row['body'],
        'url'   => (string) $row['url'],
    );
}
