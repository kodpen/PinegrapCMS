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
 * A worker woken twice for one message reads it again within this many
 * seconds of its first read, rather than showing the fixed notification.
 */
define('PG_SITE_PUSH_REREAD', 300);
/**
 * Message kinds: news to every subscriber and a test to one device are
 * written here; 2 (stock) and 3 (an order's moment) by their own steps.
 */
define('PG_SITE_PUSH_KIND_NEWS', 0);
define('PG_SITE_PUSH_KIND_TEST', 1);
/**
 * Queue rows written by one INSERT.
 */
define('PG_SITE_PUSH_BATCH', 500);
/**
 * Queue rows one pass of the sender looks at.
 */
define('PG_SITE_PUSH_PASS_ROWS', 100);
/**
 * Tries before a row is given up, and how long a finished row and a dropped
 * message's rows are kept: the queue is no history, the counts stay on the
 * message.
 */
define('PG_SITE_PUSH_ATTEMPTS', 5);
define('PG_SITE_PUSH_KEEP', 7 * 86400);
/**
 * The lock name of the sender's pass in cron_runs, taken by whichever of
 * job.php and push_job.php comes first.
 */
define('PG_SITE_PUSH_LOCK', 'site_push_send');

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
 * characters (the column). A longer one is refused, never cut: a cut
 * endpoint is a dead subscription. Pure.
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
 * browser gives them. A key of another length is refused, never cut. Pure.
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

    pg_site_push_watch_forget($id);

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
 * The query of the message waiting for one subscription: pushed within
 * PG_SITE_PUSH_MESSAGE_TTL and not yet read, or read within
 * PG_SITE_PUSH_REREAD (a worker woken twice for one message shows it
 * again), the newest first. Only that subscription's queue rows are looked
 * at: a secret never reads another browser's message. Pure.
 *
 * @param int $subscription_id
 * @param int $now
 * @return array  array(sql, params)
 */
function pg_site_push_waiting_query($subscription_id, $now)
{
    return array(
        "SELECT q.message_id, q.status, m.title, m.body, m.url
        FROM site_push_queue AS q
        INNER JOIN site_push_message AS m ON m.id = q.message_id
        WHERE q.subscription_id = ?
            AND ((q.status = ? AND q.sent_at >= ?) OR (q.status = ? AND q.fetched_at >= ?))
        ORDER BY q.sent_at DESC, q.message_id DESC
        LIMIT 1",
        array(
            (string) (int) $subscription_id,
            (string) PG_SITE_PUSH_PUSHED,
            (string) ((int) $now - PG_SITE_PUSH_MESSAGE_TTL),
            (string) PG_SITE_PUSH_READ,
            (string) ((int) $now - PG_SITE_PUSH_REREAD),
        ),
    );
}

/**
 * The message waiting for the browser of a secret, marked read, or null when
 * there is none (the worker then shows its fixed short notification). The
 * url is a path inside the site; the worker opens it on its own origin.
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

    // A second read keeps the first read's time, so the window does not
    // move on with every wake.
    db_q("UPDATE site_push_queue SET status = ?, fetched_at = ? WHERE message_id = ? AND subscription_id = ? AND status = ?", array(
        (string) PG_SITE_PUSH_READ,
        (string) $now,
        (string) (int) $row['message_id'],
        (string) (int) $subscription['id'],
        (string) PG_SITE_PUSH_PUSHED,
    ));

    db_q("UPDATE site_push_subscription SET last_seen_at = ? WHERE id = ?", array((string) $now, (string) (int) $subscription['id']));

    return array(
        'title' => (string) $row['title'],
        'body'  => (string) $row['body'],
        'url'   => (string) $row['url'],
    );
}

/**
 * A message's link as it is stored: a path inside the site, without the host,
 * so the worker opens it on its own origin and a site that changes its name
 * keeps its old messages working. Takes a path ("/news/x") or an address on
 * one of the site's hosts; empty is the home page. '' when it is refused: an
 * address elsewhere, "//host", a backslash, a control character or a path
 * longer than the column. Pure.
 *
 * @param mixed    $url
 * @param string[] $hosts  the site's own host names
 * @return string
 */
function pg_site_push_url_path($url, array $hosts)
{
    if (!is_string($url)) {
        return '';
    }

    $url = trim($url);

    if ($url === '') {
        return '/';
    }

    if (preg_match('/[\x00-\x20\x7F\\\\]/', $url)) {
        return '';
    }

    if ($url[0] !== '/') {
        $parts = parse_url($url);

        if (!is_array($parts) || !isset($parts['scheme'], $parts['host']) || !in_array(strtolower($parts['scheme']), array('http', 'https'), true)) {
            return '';
        }

        if (isset($parts['user']) || isset($parts['pass']) || !in_array(strtolower($parts['host']), array_map('strtolower', $hosts), true)) {
            return '';
        }

        $url = (isset($parts['path']) && ($parts['path'] !== '') ? $parts['path'] : '/')
            . (isset($parts['query']) ? '?' . $parts['query'] : '')
            . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');
    }

    if ((substr($url, 0, 2) === '//') || ($url[0] !== '/') || (strlen($url) > 500)) {
        return '';
    }

    return $url;
}

/**
 * A message as the panel posts it, checked: a title (1 to 60 characters), a
 * text (at most 200) and a link (pg_site_push_url_path()). Plain text: a
 * notification shows no markup, and control characters are dropped. Pure.
 *
 * @param array    $input  title, body, url
 * @param string[] $hosts  the site's own host names
 * @return array|string  title, body, url; or an error code: title, body, url
 */
function pg_site_push_message_check(array $input, array $hosts)
{
    $clean = function ($value) {
        $value = is_string($value) ? preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) : '';

        return is_string($value) ? trim(preg_replace('/ {2,}/', ' ', $value)) : '';
    };

    $title = $clean(isset($input['title']) ? $input['title'] : '');
    $body = $clean(isset($input['body']) ? $input['body'] : '');

    if (($title === '') || (mb_strlen($title, 'UTF-8') > 60)) {
        return 'title';
    }

    if (mb_strlen($body, 'UTF-8') > 200) {
        return 'body';
    }

    $url = pg_site_push_url_path(isset($input['url']) ? $input['url'] : '', $hosts);

    if ($url === '') {
        return 'url';
    }

    return array('title' => $title, 'body' => $body, 'url' => $url);
}

/**
 * The site's own host names, for pg_site_push_url_path(): the host setting
 * and the host of this request.
 *
 * @return string[]
 */
function pg_site_push_hosts()
{
    $hosts = array();

    foreach (array('HOSTNAME_SETTING', 'HOSTNAME') as $constant) {
        if (defined($constant) && (constant($constant) !== '')) {
            $hosts[] = preg_replace('/:\d+$/', '', strtolower((string) constant($constant)));
        }
    }

    return array_values(array_unique($hosts));
}

/**
 * Ids cut into the batches one INSERT writes. Pure.
 *
 * @param int[] $ids
 * @param int   $size
 * @return array[]
 */
function pg_site_push_batches(array $ids, $size = PG_SITE_PUSH_BATCH)
{
    return array_chunk(array_values(array_map('intval', $ids)), max(1, (int) $size));
}

/**
 * The statement that queues one message for a batch of subscriptions. IGNORE:
 * a batch written twice queues each browser once (the primary key). Pure.
 *
 * @param int   $message_id
 * @param int[] $subscription_ids
 * @return array  array(sql, params)
 */
function pg_site_push_queue_insert($message_id, array $subscription_ids)
{
    $rows = array();
    $params = array();

    foreach ($subscription_ids as $id) {
        $rows[] = '(?, ?, ' . PG_SITE_PUSH_WAITING . ', 0, 0)';
        $params[] = (string) (int) $message_id;
        $params[] = (string) (int) $id;
    }

    return array(
        "INSERT IGNORE INTO site_push_queue (message_id, subscription_id, status, attempts, next_at) VALUES " . implode(', ', $rows),
        $params,
    );
}

/**
 * Writes a message and queues it: news to every subscriber, in batches of
 * PG_SITE_PUSH_BATCH, or a test to the one subscription named. The sender's
 * pass sends it. Writes nothing while the switch is off.
 *
 * @param array $input            title, body, url (pg_site_push_message_check())
 * @param int   $kind             PG_SITE_PUSH_KIND_NEWS or PG_SITE_PUSH_KIND_TEST
 * @param int   $created_by       the user who sent it
 * @param int   $subscription_id  a test's one subscription
 * @return int|string  the message id, or an error code: off, kind,
 *                     subscription, save, or one of the check's
 */
function pg_site_push_message_send(array $input, $kind, $created_by, $subscription_id = 0)
{
    if (!pg_site_push_enabled()) {
        return 'off';
    }

    $kind = (int) $kind;

    if (!in_array($kind, array(PG_SITE_PUSH_KIND_NEWS, PG_SITE_PUSH_KIND_TEST), true)) {
        return 'kind';
    }

    $checked = pg_site_push_message_check($input, pg_site_push_hosts());

    if (!is_array($checked)) {
        return $checked;
    }

    $subscription_id = (int) $subscription_id;

    if (($kind === PG_SITE_PUSH_KIND_TEST) && (($subscription_id < 1) || !db_value("SELECT id FROM site_push_subscription WHERE id = '" . $subscription_id . "'"))) {
        return 'subscription';
    }

    if (!db_q("INSERT INTO site_push_message (title, body, url, kind, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?)", array(
        $checked['title'],
        $checked['body'],
        $checked['url'],
        (string) $kind,
        (string) (int) $created_by,
        (string) time(),
    ))) {
        return 'save';
    }

    $message_id = (int) mysqli_insert_id(db::$con);
    $queued = 0;

    if ($kind === PG_SITE_PUSH_KIND_TEST) {
        $batches = array(array($subscription_id));
    } else {
        $batches = array();
        $after = 0;

        // Read by id in pages, so a large list never sits in memory at once.
        do {
            $ids = array_map('intval', db_values("SELECT id FROM site_push_subscription WHERE id > '" . $after . "' ORDER BY id LIMIT " . PG_SITE_PUSH_BATCH));

            if ($ids) {
                $batches = array_merge($batches, pg_site_push_batches($ids));
                $after = end($ids);
            }
        } while (count($ids) === PG_SITE_PUSH_BATCH);
    }

    foreach ($batches as $batch) {
        list($sql, $params) = pg_site_push_queue_insert($message_id, $batch);

        if (db_q($sql, $params)) {
            $queued += (int) mysqli_affected_rows(db::$con);
        }
    }

    db_q("UPDATE site_push_message SET queued_count = ? WHERE id = ?", array((string) $queued, (string) $message_id));

    return $message_id;
}

/**
 * Seconds before a row is tried again after its n-th failure. Pure.
 *
 * @param int $attempts  failures so far, this one included
 * @return int
 */
function pg_site_push_backoff($attempts)
{
    $steps = array(1 => 60, 2 => 300, 3 => 1800, 4 => 7200);
    $attempts = (int) $attempts;

    return isset($steps[$attempts]) ? $steps[$attempts] : 21600;
}

/**
 * What a send's outcome (pg_push_outcome()) does to its queue row, its
 * subscription and its message's counts. Pure.
 *
 * sent:   the row is pushed (1); sent_count.
 * remove: the push service says the browser is gone; the subscription and
 *         its rows go; removed_count.
 * hold:   the service asked to wait; every waiting row of that service waits
 *         for Retry-After, no try is counted.
 * retry:  a failure; the row waits pg_site_push_backoff().
 * fail:   the last try failed; the row is given up (3); failed_count.
 *
 * @param string $outcome   ok, gone, busy or failed
 * @param int    $attempts  tries before this one
 * @return array  action, count (the message column or ''), wait (seconds)
 */
function pg_site_push_send_plan($outcome, $attempts)
{
    $tries = (int) $attempts + 1;

    if ($outcome === 'ok') {
        return array('action' => 'sent', 'count' => 'sent_count', 'wait' => 0);
    }

    if ($outcome === 'gone') {
        return array('action' => 'remove', 'count' => 'removed_count', 'wait' => 0);
    }

    if ($outcome === 'busy') {
        return array('action' => 'hold', 'count' => '', 'wait' => 0);
    }

    if ($tries >= PG_SITE_PUSH_ATTEMPTS) {
        return array('action' => 'fail', 'count' => 'failed_count', 'wait' => 0);
    }

    return array('action' => 'retry', 'count' => '', 'wait' => pg_site_push_backoff($tries));
}

/**
 * Whether a message is past the day it may be delivered in. Pure.
 *
 * @param int $created_at
 * @param int $now
 * @return bool
 */
function pg_site_push_expired($created_at, $now)
{
    return ((int) $created_at) < ((int) $now - PG_SITE_PUSH_MESSAGE_TTL);
}

/**
 * Sends one empty push to an endpoint with the panel's VAPID key, through the
 * outbound client that resolves the name once and calls the address it
 * resolved. The worker fetches its text with its secret.
 *
 * @param string $endpoint
 * @return array  ok, status, error, retry_after (seconds a 429 asked for)
 */
function pg_site_push_deliver($endpoint)
{
    include_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/http.php');

    $token = pg_push_vapid_token($endpoint);

    if ($token === '') {
        return array('ok' => false, 'status' => 0, 'error' => 'The VAPID token could not be signed.', 'retry_after' => 0);
    }

    $result = api_http_request('POST', $endpoint, array(
        'body'             => '',
        'headers'          => array(
            'Authorization: vapid t=' . $token . ', k=' . pg_push_vapid_public_key(),
            'TTL: ' . PG_SITE_PUSH_MESSAGE_TTL,
            'Urgency: normal',
            'Content-Length: 0',
        ),
        'timeout'          => 10,
        'agent'            => 'webhook',
        'response_headers' => true,
    ));

    $result['retry_after'] = ((int) $result['status'] === 429)
        ? pg_push_retry_after(isset($result['headers']['retry-after']) ? $result['headers']['retry-after'] : '', time())
        : 0;

    return $result;
}

/**
 * Raises one count of a message by one.
 *
 * @param int    $message_id
 * @param string $column  sent_count, failed_count or removed_count
 * @return void
 */
function pg_site_push_count($message_id, $column)
{
    if (!in_array($column, array('sent_count', 'failed_count', 'removed_count'), true)) {
        return;
    }

    db_q("UPDATE site_push_message SET " . $column . " = " . $column . " + 1 WHERE id = ?", array((string) (int) $message_id));
}

/**
 * The tidy-up at the start of a pass: waiting rows of a message past its day
 * are given up (failed_count), and rows no longer waiting whose message is
 * older than PG_SITE_PUSH_KEEP are deleted.
 *
 * @param int $now
 * @return void
 */
function pg_site_push_queue_tidy($now)
{
    $expired = (string) ((int) $now - PG_SITE_PUSH_MESSAGE_TTL);

    foreach (db_items("SELECT q.message_id, COUNT(*) AS rows_given_up
        FROM site_push_queue AS q
        INNER JOIN site_push_message AS m ON m.id = q.message_id
        WHERE q.status = '" . PG_SITE_PUSH_WAITING . "' AND m.created_at < '" . e($expired) . "'
        GROUP BY q.message_id") as $row) {
        db_q("UPDATE site_push_queue SET status = ? WHERE message_id = ? AND status = ?", array((string) PG_SITE_PUSH_FAILED, (string) (int) $row['message_id'], (string) PG_SITE_PUSH_WAITING));
        db_q("UPDATE site_push_message SET failed_count = failed_count + ? WHERE id = ?", array((string) (int) $row['rows_given_up'], (string) (int) $row['message_id']));
    }

    db_q("DELETE q FROM site_push_queue AS q
        INNER JOIN site_push_message AS m ON m.id = q.message_id
        WHERE q.status <> ? AND m.created_at < ?", array((string) PG_SITE_PUSH_WAITING, (string) ((int) $now - PG_SITE_PUSH_KEEP)));

    pg_site_push_watch_tidy($now);
}

/**
 * The sender's pass, run by the general job and by push_job.php. One run at
 * a time (pg_cron_claim() on PG_SITE_PUSH_LOCK). Tidies the queue, then sends
 * the due rows, oldest message first, until the budget is spent. A row is
 * marked pushed as soon as its push is accepted. A service that answers 429
 * is held: its waiting rows wait for Retry-After and this pass skips it.
 * Sends nothing while the switch is off; the tidy-up still runs.
 *
 * @param int $budget_seconds
 * @return int  rows pushed
 */
function pg_site_push_queue_run($budget_seconds = 10)
{
    if (!pg_site_push_ready() || !pg_cron_claim(PG_SITE_PUSH_LOCK, 120)) {
        return 0;
    }

    // A fatal error or an exit still gives the lock back.
    register_shutdown_function('pg_cron_release', PG_SITE_PUSH_LOCK);

    $started = microtime(true);
    $now = time();
    $pushed = 0;

    pg_site_push_queue_tidy($now);

    require_once(PG_FUNCTIONS_DIR . '/includes/push.php');

    if (!pg_site_push_switch_on() || !pg_push_available() || (pg_push_vapid_public_key() === '')) {
        pg_cron_release(PG_SITE_PUSH_LOCK);

        return 0;
    }

    $rows = db_items("SELECT q.message_id, q.subscription_id, q.attempts, s.endpoint, s.service
        FROM site_push_queue AS q
        INNER JOIN site_push_subscription AS s ON s.id = q.subscription_id
        WHERE q.status = '" . PG_SITE_PUSH_WAITING . "' AND q.next_at <= '" . $now . "'
        ORDER BY q.message_id, q.subscription_id
        LIMIT " . PG_SITE_PUSH_PASS_ROWS);

    $held = array();

    foreach ($rows as $row) {
        if ((microtime(true) - $started) > $budget_seconds) {
            break;
        }

        if (isset($held[$row['service']])) {
            continue;
        }

        $message_id = (int) $row['message_id'];
        $subscription_id = (int) $row['subscription_id'];
        $result = pg_site_push_deliver((string) $row['endpoint']);
        $plan = pg_site_push_send_plan(pg_push_outcome($result['status']), (int) $row['attempts']);
        $time = time();
        $key = array((string) $message_id, (string) $subscription_id);

        if ($plan['action'] === 'sent') {
            db_q("UPDATE site_push_queue SET status = ?, attempts = attempts + 1, sent_at = ? WHERE message_id = ? AND subscription_id = ?", array_merge(array((string) PG_SITE_PUSH_PUSHED, (string) $time), $key));
            db_q("UPDATE site_push_subscription SET fail_count = 0 WHERE id = ?", array((string) $subscription_id));
            $pushed++;
        } elseif ($plan['action'] === 'remove') {
            db_q("DELETE FROM site_push_queue WHERE subscription_id = ?", array((string) $subscription_id));
            db_q("DELETE FROM site_push_subscription WHERE id = ?", array((string) $subscription_id));
            pg_site_push_watch_forget($subscription_id);
        } elseif ($plan['action'] === 'hold') {
            $held[$row['service']] = true;

            db_q("UPDATE site_push_queue AS q
                INNER JOIN site_push_subscription AS s ON s.id = q.subscription_id
                SET q.next_at = ?
                WHERE q.status = ? AND s.service = ? AND q.next_at < ?", array(
                (string) ($time + max(1, (int) $result['retry_after'])),
                (string) PG_SITE_PUSH_WAITING,
                (string) $row['service'],
                (string) ($time + max(1, (int) $result['retry_after'])),
            ));
        } else {
            db_q("UPDATE site_push_queue SET status = ?, attempts = attempts + 1, next_at = ? WHERE message_id = ? AND subscription_id = ?", array_merge(array(
                (string) (($plan['action'] === 'fail') ? PG_SITE_PUSH_FAILED : PG_SITE_PUSH_WAITING),
                (string) ($time + $plan['wait']),
            ), $key));
            db_q("UPDATE site_push_subscription SET fail_count = fail_count + 1 WHERE id = ?", array((string) $subscription_id));
        }

        pg_site_push_count($message_id, $plan['count']);
    }

    pg_cron_release(PG_SITE_PUSH_LOCK);

    return $pushed;
}

/**
 * How long a device's wait for a record (site_push_watch) is kept when no
 * moment ends it: an order never delivered, a product never back.
 */
define('PG_SITE_PUSH_WATCH_KEEP', 180 * 86400);

/**
 * Whether the 11.0.229 step has run: the table a device's wait for a record
 * (a stock request, an order's address) is kept in.
 *
 * @return bool
 */
function pg_site_push_watch_ready()
{
    return pg_site_push_ready() && pg_schema_has('site_push_watch', 'created_at');
}

/**
 * The statement that makes one subscription wait for records of a kind.
 * IGNORE: pressing twice keeps the first row and the moments it has had.
 * Pure.
 *
 * @param int   $kind             the message kind (2 stock, 3 order)
 * @param int[] $record_ids
 * @param int   $subscription_id
 * @param int   $now
 * @return array  array(sql, params)
 */
function pg_site_push_watch_insert($kind, array $record_ids, $subscription_id, $now)
{
    $rows = array();
    $params = array();

    foreach ($record_ids as $record_id) {
        $rows[] = '(?, ?, ?, 0, ?)';
        $params[] = (string) (int) $kind;
        $params[] = (string) (int) $record_id;
        $params[] = (string) (int) $subscription_id;
        $params[] = (string) (int) $now;
    }

    return array(
        "INSERT IGNORE INTO site_push_watch (kind, record_id, subscription_id, moments, created_at) VALUES " . implode(', ', $rows),
        $params,
    );
}

/**
 * Makes a subscription wait for records of a kind.
 *
 * @param int   $kind
 * @param int[] $record_ids
 * @param int   $subscription_id
 * @return int  rows written
 */
function pg_site_push_watch_add($kind, array $record_ids, $subscription_id)
{
    $record_ids = array_values(array_filter(array_map('intval', $record_ids)));

    if (!$record_ids || ((int) $subscription_id < 1) || !pg_site_push_watch_ready()) {
        return 0;
    }

    list($sql, $params) = pg_site_push_watch_insert($kind, $record_ids, $subscription_id, time());

    return db_q($sql, $params) ? (int) mysqli_affected_rows(db::$con) : 0;
}

/**
 * The statement that takes one moment of a wait, so it is sent once. A
 * moment before the last sets its bit only where it is not set yet; the last
 * moment deletes the row, so a finished wait leaves nothing behind. Whoever
 * changes the row (one affected row) sends. Pure.
 *
 * @param int  $kind
 * @param int  $record_id
 * @param int  $subscription_id
 * @param int  $bit   the moment's bit in moments (1, 2, 4 …); unused when last
 * @param bool $last  the wait's last moment
 * @return array  array(sql, params)
 */
function pg_site_push_watch_take_sql($kind, $record_id, $subscription_id, $bit, $last)
{
    $key = array((string) (int) $kind, (string) (int) $record_id, (string) (int) $subscription_id);

    if ($last) {
        return array("DELETE FROM site_push_watch WHERE kind = ? AND record_id = ? AND subscription_id = ?", $key);
    }

    $bit = (string) (int) $bit;

    return array(
        "UPDATE site_push_watch SET moments = moments | ? WHERE kind = ? AND record_id = ? AND subscription_id = ? AND (moments & ?) = 0",
        array_merge(array($bit), $key, array($bit)),
    );
}

/**
 * Takes one moment of a wait: true when this call is the one to send it.
 *
 * @param int  $kind
 * @param int  $record_id
 * @param int  $subscription_id
 * @param int  $bit
 * @param bool $last
 * @return bool
 */
function pg_site_push_watch_take($kind, $record_id, $subscription_id, $bit, $last)
{
    list($sql, $params) = pg_site_push_watch_take_sql($kind, $record_id, $subscription_id, $bit, $last);

    return db_q($sql, $params) && ((int) mysqli_affected_rows(db::$con) === 1);
}

/**
 * The subscriptions waiting for a record, with their language. Joined to the
 * subscription: a wait whose browser is gone reaches nobody.
 *
 * @param int $kind
 * @param int $record_id
 * @return array  rows of subscription_id, language
 */
function pg_site_push_watchers($kind, $record_id)
{
    if (!pg_site_push_watch_ready()) {
        return array();
    }

    return db_items("SELECT w.subscription_id, s.language
        FROM site_push_watch AS w
        INNER JOIN site_push_subscription AS s ON s.id = w.subscription_id
        WHERE w.kind = '" . (int) $kind . "' AND w.record_id = '" . (int) $record_id . "'
        ORDER BY w.subscription_id");
}

/**
 * Drops every wait of a subscription: called wherever a subscription is
 * deleted (Stop, a gone endpoint).
 *
 * @param int $subscription_id
 * @return void
 */
function pg_site_push_watch_forget($subscription_id)
{
    if (pg_site_push_watch_ready()) {
        db_q("DELETE FROM site_push_watch WHERE subscription_id = ?", array((string) (int) $subscription_id));
    }
}

/**
 * Drops the waits for records that were deleted (an order's addresses, a
 * stock request).
 *
 * @param int   $kind
 * @param int[] $record_ids
 * @return void
 */
function pg_site_push_watch_drop($kind, array $record_ids)
{
    $record_ids = array_values(array_filter(array_map('intval', $record_ids)));

    if (!$record_ids || !pg_site_push_watch_ready()) {
        return;
    }

    db_q("DELETE FROM site_push_watch WHERE kind = ? AND record_id IN (" . implode(', ', array_fill(0, count($record_ids), '?')) . ")",
        array_merge(array((string) (int) $kind), array_map('strval', $record_ids)));
}

/**
 * The sender's tidy-up of waits: older than PG_SITE_PUSH_WATCH_KEEP, or
 * whose subscription is gone (an erasure deletes the subscription by its
 * account and does not know this table).
 *
 * @param int $now
 * @return void
 */
function pg_site_push_watch_tidy($now)
{
    if (!pg_site_push_watch_ready()) {
        return;
    }

    db_q("DELETE FROM site_push_watch WHERE created_at < ?", array((string) ((int) $now - PG_SITE_PUSH_WATCH_KEEP)));
    db("DELETE w FROM site_push_watch AS w
        LEFT JOIN site_push_subscription AS s ON s.id = w.subscription_id
        WHERE s.id IS NULL");
}

/**
 * Writes a message for one device and queues it: a stock notice or an
 * order's moment, whose text the caller made (title at most 60, text at
 * most 200, url a path inside the site). Not listed as sent in the panel.
 *
 * @param array $text             title, body, url
 * @param int   $kind             2 or 3
 * @param int   $subscription_id
 * @return int  the message id, 0 when nothing was written
 */
function pg_site_push_message_to_one(array $text, $kind, $subscription_id)
{
    if (!pg_site_push_enabled() || ((int) $kind < 2) || ((int) $subscription_id < 1)) {
        return 0;
    }

    if (!db_q("INSERT INTO site_push_message (title, body, url, kind, created_by, created_at, queued_count) VALUES (?, ?, ?, ?, 0, ?, 1)", array(
        (string) $text['title'],
        (string) $text['body'],
        (string) $text['url'],
        (string) (int) $kind,
        (string) time(),
    ))) {
        return 0;
    }

    $message_id = (int) mysqli_insert_id(db::$con);
    list($sql, $params) = pg_site_push_queue_insert($message_id, array((int) $subscription_id));
    db_q($sql, $params);

    return $message_id;
}
