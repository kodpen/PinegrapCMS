<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Fediverse settings screen's helpers (includes/settings/fediverse.php):
 * what the save stores in the ap_settings row, the handle check, the
 * followers list, the delivery log with "Try again", and the "Look me up"
 * check that asks the site's own WebFinger through its public address.
 *
 * The cleaning and the verdicts are pure, so the tests check them without
 * a database or a network.
 *
 * Loaded on demand (the settings screen, the tests).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/activitypub_moderation.php');

// The longest profile name and summary the screen keeps; Mastodon cuts a
// display name at 30 characters and a bio at 500.
if (!defined('PG_AP_NAME_MAX')) {
    define('PG_AP_NAME_MAX', 100);
}

if (!defined('PG_AP_SUMMARY_MAX')) {
    define('PG_AP_SUMMARY_MAX', 500);
}

/**
 * Why a handle cannot be the site's: '' when it can.
 *
 * A handle that is a panel user's name is refused: `@<name>@<host>` would
 * read as that person's own account while it speaks for the site.
 * Fediverse servers compare usernames without case, so this does too.
 *
 * @param string $handle    as typed
 * @param array  $usernames the panel users' names
 * @return string '', 'empty', 'format' or 'taken'
 */
function pg_ap_settings_handle_error($handle, $usernames)
{
    $handle = (string) $handle;

    if ($handle === '') {
        return 'empty';
    }

    if (!pg_ap_handle_valid($handle)) {
        return 'format';
    }

    $lower = strtolower($handle);

    foreach ((array) $usernames as $username) {
        if (strtolower(trim((string) $username)) === $lower) {
            return 'taken';
        }
    }

    return '';
}

/**
 * The ids of a posted list, each once, in the order given, only those
 * that are allowed.
 *
 * @param mixed $values  the posted list
 * @param array $allowed the ids the screen offered
 * @return array ints
 */
function pg_ap_settings_ids($values, $allowed)
{
    $allowed = array_flip(array_map('intval', (array) $allowed));
    $out = array();

    if (!is_array($values)) {
        return $out;
    }

    foreach ($values as $value) {
        if (!is_scalar($value) || !preg_match('/^[1-9]\d*$/', (string) $value)) {
            continue;
        }

        $id = (int) $value;

        if (isset($allowed[$id]) && !in_array($id, $out, true)) {
            $out[] = $id;
        }
    }

    return $out;
}

/**
 * Plain text of at most $max characters: tags dropped, white space
 * folded.
 *
 * @param mixed $value
 * @param int   $max
 * @return string
 */
function pg_ap_settings_text($value, $max)
{
    $text = is_scalar($value) ? strip_tags((string) $value) : '';
    $text = trim((string) preg_replace('/\s+/u', ' ', $text));

    return mb_substr($text, 0, (int) $max, 'UTF-8');
}

/**
 * The ap_settings row a save writes, from the posted fields.
 *
 * Keys the screen does not draw are kept from what is stored, so a later
 * step's setting is not lost by a save from this screen.
 *
 * @param array $post    enabled, handle, type, name, summary, icon, forms,
 *                       folders
 * @param array $stored  the stored row, decoded (may be empty)
 * @param array $allowed forms, folders: the ids the screen offered
 * @return array
 */
function pg_ap_settings_build($post, $stored, $allowed)
{
    $row = is_array($stored) ? $stored : array();

    $row['enabled'] = !empty($post['enabled']);
    $row['handle'] = isset($post['handle']) && is_scalar($post['handle']) ? trim((string) $post['handle']) : '';
    $row['type'] = (isset($post['type']) && ($post['type'] === 'Organization')) ? 'Organization' : 'Person';
    $row['name'] = pg_ap_settings_text(isset($post['name']) ? $post['name'] : '', PG_AP_NAME_MAX);
    $row['summary'] = pg_ap_settings_text(isset($post['summary']) ? $post['summary'] : '', PG_AP_SUMMARY_MAX);
    $row['icon'] = isset($post['icon']) && is_scalar($post['icon']) ? trim((string) $post['icon']) : '';

    $publish = (isset($row['publish']) && is_array($row['publish'])) ? $row['publish'] : array();
    $publish['forms'] = pg_ap_settings_ids(isset($post['forms']) ? $post['forms'] : array(), isset($allowed['forms']) ? $allowed['forms'] : array());
    $publish['folders'] = pg_ap_settings_ids(isset($post['folders']) ? $post['folders'] : array(), isset($allowed['folders']) ? $allowed['folders'] : array());
    $row['publish'] = $publish;

    return $row;
}

/**
 * The stored ap_settings row, decoded, read past pg_ap_settings()'s
 * per-request copy (the screen is drawn again after its own save).
 *
 * @return array
 */
function pg_ap_settings_stored()
{
    $json = pg_schema_has('config_text') ? (string) db_value("SELECT value FROM config_text WHERE name = 'ap_settings'") : '';
    $data = json_decode($json, true);

    return is_array($data) ? $data : array();
}

/**
 * One delivery row as the log shows it.
 *
 * @param array $row ap_outbox: id, activity_id, inbox, attempts, next_at,
 *                   state, created_at
 * @return array id, type (Create, Update, Delete, Accept, …), object,
 *               host, state, attempts, created_at, next_at, retry (bool)
 */
function pg_ap_delivery_view($row)
{
    $activity_id = isset($row['activity_id']) ? (string) $row['activity_id'] : '';
    $key = pg_ap_activity_key($activity_id);

    if ($key !== null) {
        $type = ucfirst($key['type']);
        $object = $key['object'];
    } elseif (strpos($activity_id, '#accepts/') !== false) {
        $type = 'Accept';
        $object = '';
    } else {
        $type = '';
        $object = '';
    }

    $state = isset($row['state']) ? (string) $row['state'] : '';

    return array(
        'id'         => isset($row['id']) ? (int) $row['id'] : 0,
        'type'       => $type,
        'object'     => $object,
        'host'       => pg_ap_host(isset($row['inbox']) ? (string) $row['inbox'] : ''),
        'state'      => $state,
        'attempts'   => isset($row['attempts']) ? (int) $row['attempts'] : 0,
        'created_at' => isset($row['created_at']) ? (int) $row['created_at'] : 0,
        'next_at'    => isset($row['next_at']) ? (int) $row['next_at'] : 0,
        'retry'      => ($state === 'failed'),
    );
}

/**
 * The newest delivery rows (one per inbox and activity), failures first.
 *
 * @param int $limit
 * @return array pg_ap_delivery_view() rows
 */
function pg_ap_delivery_log($limit = 50)
{
    if (!pg_ap_outbox_ready()) {
        return array();
    }

    $rows = db_items(
        "SELECT id, activity_id, inbox, attempts, next_at, state, created_at
         FROM ap_outbox
         WHERE inbox != ''
         ORDER BY (state = 'failed') DESC, id DESC
         LIMIT " . max(1, (int) $limit));

    return array_map('pg_ap_delivery_view', (array) $rows);
}

/**
 * Queues a failed delivery again, from its first attempt, due now.
 *
 * @param int $id
 * @return bool true when a failed row was queued
 */
function pg_ap_delivery_retry($id)
{
    $id = (int) $id;

    if (($id < 1) || !pg_ap_outbox_ready()) {
        return false;
    }

    db("UPDATE ap_outbox SET state = 'queued', attempts = 0, next_at = '" . time() . "' WHERE id = '$id' AND inbox != '' AND state = 'failed'");

    return mysqli_affected_rows(db::$con) === 1;
}

/**
 * The followers, newest first.
 *
 * @param int $limit
 * @return array actor_id, name, avatar, followed_at, host
 */
function pg_ap_followers_list($limit = 200)
{
    if (!pg_ap_outbox_ready()) {
        return array();
    }

    $rows = db_items("SELECT actor_id, name, avatar, followed_at FROM ap_followers ORDER BY followed_at DESC, id DESC LIMIT " . max(1, (int) $limit));
    $out = array();

    foreach ((array) $rows as $row) {
        $row['host'] = pg_ap_host($row['actor_id']);
        $out[] = $row;
    }

    return $out;
}

/**
 * The address "Look me up" asks: the site's WebFinger for its own handle,
 * at the public address a Fediverse server would use.
 *
 * @param array $site pg_ap_site()
 * @return string
 */
function pg_ap_lookup_url($site)
{
    $host = (string) $site['host'];

    return 'https://' . $host . '/.well-known/webfinger?resource=' . rawurlencode('acct:' . $site['handle'] . '@' . $host);
}

/**
 * What the WebFinger answer says: '' when it names this actor.
 *
 * @param int    $status
 * @param string $body
 * @param array  $site pg_ap_site()
 * @return string '', 'unreachable', 'status', 'json' or 'actor'
 */
function pg_ap_lookup_verdict($status, $body, $site)
{
    $status = (int) $status;

    if ($status === 0) {
        return 'unreachable';
    }

    if (($status < 200) || ($status > 299)) {
        return 'status';
    }

    $data = json_decode((string) $body, true);

    if (!is_array($data) || !isset($data['links']) || !is_array($data['links'])) {
        return 'json';
    }

    $urls = pg_ap_urls($site);

    foreach ($data['links'] as $link) {
        if (
            is_array($link)
            && isset($link['rel'], $link['href'])
            && ($link['rel'] === 'self')
            && ($link['href'] === $urls['id'])
        ) {
            return '';
        }
    }

    return 'actor';
}

/**
 * Asks the site's own WebFinger through its public address, as a
 * Fediverse server would.
 *
 * @param array $site pg_ap_site()
 * @return array url, status, verdict (pg_ap_lookup_verdict())
 */
function pg_ap_lookup($site)
{
    $url = pg_ap_lookup_url($site);

    $answer = pg_curl_run('GET', $url, array(
        'headers'  => array('Accept: application/jrd+json, application/json'),
        'timeout'  => 10,
        'max_size' => 65536,
    ));

    $status = (int) $answer['status'];

    return array(
        'url'     => $url,
        'status'  => $status,
        'verdict' => pg_ap_lookup_verdict($status, isset($answer['body']) ? $answer['body'] : '', $site),
    );
}
