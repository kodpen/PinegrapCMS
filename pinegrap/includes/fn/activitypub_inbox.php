<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ActivityPub inbox (ap_inbox.php): what other servers deliver to the
 * site's actor. A delivery is checked in this order: the sender is not
 * blocked (ap_blocks, by actor or domain, before any key is fetched), the
 * HTTP Signature verifies (pg_ap_verify()), and the key belongs to the
 * activity's actor. Then pg_ap_inbox_plan() reads the activity into actions
 * and pg_ap_inbox_apply() carries them out:
 *
 *   Follow of the actor     an ap_followers row and an Accept queued in ap_outbox
 *   Undo Follow             the row removed
 *   Like, Announce          one ap_reactions row per actor, kind and object
 *   Undo Like / Announce    that row removed
 *   Delete of the actor     its follower row, reactions and comments removed
 *   Create(Note) replying   handed to pg_ap_reply_receive() when it exists
 *     to one of our objects
 *   Delete of a note        handed to pg_ap_reply_deleted() when it exists
 *
 * Anything else is answered 202 and written to the inbox log. Every action
 * is idempotent, so a repeated delivery (a retry, or a replayed request
 * inside the signature window) changes nothing and queues no second Accept.
 *
 * The planner is a pure function of the activity and the actor's addresses,
 * so the tests check it without a database.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/activitypub.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/activitypub_outbox.php');

// The largest delivery the inbox reads; a Mastodon activity is a few KB.
if (!defined('PG_AP_INBOX_MAX_BODY')) {
    define('PG_AP_INBOX_MAX_BODY', 262144);
}

// The inbox log is moved aside to inbox.log.1 above this size.
if (!defined('PG_AP_INBOX_LOG_MAX')) {
    define('PG_AP_INBOX_LOG_MAX', 524288);
}

/**
 * The address an ActivityPub value names: the string itself, or the id of
 * an embedded object.
 *
 * @param mixed $value
 * @return string '' when there is none
 */
function pg_ap_id($value)
{
    if (is_string($value)) {
        return $value;
    }

    if (is_array($value) && isset($value['id']) && is_string($value['id'])) {
        return $value['id'];
    }

    return '';
}

/**
 * The host of an address, lower-case.
 *
 * @param string $url
 * @return string
 */
function pg_ap_host($url)
{
    return strtolower((string) parse_url((string) $url, PHP_URL_HOST));
}

/**
 * Whether an actor is blocked: listed itself, or its host is a blocked
 * domain or under one.
 *
 * @param string $actor_id the actor's address (or a key id)
 * @param array  $blocks   rows with kind ('actor' or 'domain') and value
 * @return bool
 */
function pg_ap_blocked($actor_id, $blocks)
{
    $actor_id = preg_replace('/#.*$/', '', (string) $actor_id);
    $host = pg_ap_host($actor_id);

    foreach ((array) $blocks as $block) {
        $value = isset($block['value']) ? trim((string) $block['value']) : '';

        if ($value === '') {
            continue;
        }

        if ($block['kind'] === 'actor') {
            if ($value === $actor_id) {
                return true;
            }
        } else {
            $domain = strtolower(ltrim($value, '.'));

            if (($host !== '') && (($host === $domain) || (substr($host, -strlen($domain) - 1) === '.' . $domain))) {
                return true;
            }
        }
    }

    return false;
}

/**
 * The blocked actors and domains (ap_blocks).
 *
 * @return array
 */
function pg_ap_blocks()
{
    $rows = array();
    $result = db_q("SELECT kind, value FROM ap_blocks");

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }
    }

    return $rows;
}

/**
 * Whether the signing key speaks for the activity's actor: the key's owner,
 * or the key id without its fragment, is the actor.
 *
 * @param array $activity
 * @param array $verified pg_ap_verify()'s answer
 * @return bool
 */
function pg_ap_inbox_authorized($activity, $verified)
{
    $actor = pg_ap_id(isset($activity['actor']) ? $activity['actor'] : '');

    if (($actor === '') || empty($verified['ok'])) {
        return false;
    }

    $signer = ($verified['owner'] !== '') ? $verified['owner'] : preg_replace('/#.*$/', '', (string) $verified['key_id']);

    return $signer === $actor;
}

/**
 * Whether an address is one of the site's own: under its base address.
 *
 * @param string $id
 * @param array  $site pg_ap_site() (base is used)
 * @return bool
 */
function pg_ap_is_ours($id, $site)
{
    $base = (string) $site['base'];

    return ($id !== '') && ($base !== '') && (strpos($id, $base) === 0);
}

/**
 * The key of a reaction: one per kind, actor and object.
 *
 * @param string $kind 'like' or 'announce'
 * @param string $actor_id
 * @param string $object_id
 * @return string sha1
 */
function pg_ap_reaction_key($kind, $actor_id, $object_id)
{
    return sha1($kind . "\n" . $actor_id . "\n" . $object_id);
}

/**
 * The Accept that answers a Follow. Its id follows from the Follow's, so a
 * repeated Follow answers with the same Accept.
 *
 * @param array $follow the Follow as received
 * @param array $site   pg_ap_site()
 * @return array
 */
function pg_ap_accept_activity($follow, $site)
{
    $urls = pg_ap_urls($site);

    return array(
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id'       => $urls['id'] . '#accepts/' . sha1(pg_ap_id($follow)),
        'type'     => 'Accept',
        'actor'    => $urls['id'],
        'object'   => array(
            'id'     => pg_ap_id($follow),
            'type'   => 'Follow',
            'actor'  => pg_ap_id($follow['actor']),
            'object' => $urls['id'],
        ),
    );
}

/**
 * What a verified activity asks the site to do.
 *
 * @param array $activity the delivered activity, its actor already checked
 * @param array $site     pg_ap_site()
 * @return array actions, each with 'do' and its fields:
 *               follow (actor, accept), unfollow (actor),
 *               react (kind, actor, object, activity),
 *               unreact (kind, actor, object, activity),
 *               actor_gone (actor), reply (actor, note),
 *               note_deleted (actor, object), ignore (reason)
 */
function pg_ap_inbox_plan($activity, $site)
{
    $urls = pg_ap_urls($site);
    $type = isset($activity['type']) && is_string($activity['type']) ? $activity['type'] : '';
    $actor = pg_ap_id(isset($activity['actor']) ? $activity['actor'] : '');
    $object = isset($activity['object']) ? $activity['object'] : null;
    $object_id = pg_ap_id($object);
    $activity_id = pg_ap_id($activity);
    $ignore = function ($reason) {
        return array(array('do' => 'ignore', 'reason' => $reason));
    };

    if ($actor === '') {
        return $ignore('no actor');
    }

    switch ($type) {
        case 'Follow':
            if ($object_id !== $urls['id']) {
                return $ignore('a Follow of another actor');
            }

            return array(array('do' => 'follow', 'actor' => $actor, 'accept' => pg_ap_accept_activity($activity, $site)));

        case 'Like':
        case 'Announce':
            if (!pg_ap_is_ours($object_id, $site) || ($object_id === $urls['id'])) {
                return $ignore('a ' . $type . ' of another object');
            }

            return array(array('do' => 'react', 'kind' => strtolower($type), 'actor' => $actor, 'object' => $object_id, 'activity' => $activity_id));

        case 'Undo':
            $inner_type = (is_array($object) && isset($object['type'])) ? $object['type'] : '';
            $inner_actor = is_array($object) ? pg_ap_id(isset($object['actor']) ? $object['actor'] : $actor) : $actor;

            if ($inner_actor !== $actor) {
                return $ignore('an Undo of another actor\'s activity');
            }

            if ($inner_type === 'Follow') {
                return array(array('do' => 'unfollow', 'actor' => $actor));
            }

            if (($inner_type === 'Like') || ($inner_type === 'Announce') || ($inner_type === '')) {
                // Mastodon embeds the undone activity; a bare id still finds
                // the reaction by its activity id.
                return array(array(
                    'do'       => 'unreact',
                    'kind'     => ($inner_type === '') ? '' : strtolower($inner_type),
                    'actor'    => $actor,
                    'object'   => is_array($object) ? pg_ap_id(isset($object['object']) ? $object['object'] : '') : '',
                    'activity' => $object_id,
                ));
            }

            return $ignore('an Undo of ' . $inner_type);

        case 'Delete':
            if ($object_id === $actor) {
                return array(array('do' => 'actor_gone', 'actor' => $actor));
            }

            if (($object_id !== '') && (pg_ap_host($object_id) === pg_ap_host($actor))) {
                return array(array('do' => 'note_deleted', 'actor' => $actor, 'object' => $object_id));
            }

            return $ignore('a Delete of another server\'s object');

        case 'Create':
            $reply_to = is_array($object) ? pg_ap_id(isset($object['inReplyTo']) ? $object['inReplyTo'] : '') : '';

            if (is_array($object) && isset($object['type']) && ($object['type'] === 'Note') && pg_ap_is_ours($reply_to, $site)
                && (pg_ap_id(isset($object['attributedTo']) ? $object['attributedTo'] : $actor) === $actor)) {
                return array(array('do' => 'reply', 'actor' => $actor, 'note' => $object));
            }

            return $ignore('a Create that answers none of ours');
    }

    return $ignore('type ' . ($type !== '' ? $type : 'missing'));
}

/**
 * Carries out the planned actions.
 *
 * @param array $actions pg_ap_inbox_plan()
 * @param array $site    pg_ap_site()
 * @return array one line per action for the log
 */
function pg_ap_inbox_apply($actions, $site)
{
    $done = array();
    $now = time();

    foreach ($actions as $action) {
        switch ($action['do']) {
            case 'follow':
                $remote = pg_ap_remote_actor($action['actor']);

                if (!$remote) {
                    $done[] = 'follow refused: the actor\'s document could not be read';
                    break;
                }

                db_q(
                    "INSERT INTO ap_followers (actor_id, inbox, shared_inbox, name, avatar, followed_at) VALUES (?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE inbox = VALUES(inbox), shared_inbox = VALUES(shared_inbox), name = VALUES(name), avatar = VALUES(avatar)",
                    array($action['actor'], $remote['inbox'], $remote['shared_inbox'], $remote['name'], $remote['avatar'], $now)
                );

                pg_ap_queue($action['accept'], $remote['inbox']);
                $done[] = 'follow';
                break;

            case 'unfollow':
                db_q("DELETE FROM ap_followers WHERE actor_id = ?", array($action['actor']));
                $done[] = 'unfollow';
                break;

            case 'react':
                db_q(
                    "INSERT IGNORE INTO ap_reactions (kind, actor_id, object_id, activity_id, reaction_key, created_at) VALUES (?, ?, ?, ?, ?, ?)",
                    array($action['kind'], $action['actor'], $action['object'], $action['activity'], pg_ap_reaction_key($action['kind'], $action['actor'], $action['object']), $now)
                );
                $done[] = $action['kind'];

                // The comments widget shows the counts (pg_comments_widget_reactions()).
                if (function_exists('pg_page_cache_mark_dirty')) {
                    pg_page_cache_mark_dirty();
                }
                break;

            case 'unreact':
                if (($action['kind'] !== '') && ($action['object'] !== '')) {
                    db_q(
                        "DELETE FROM ap_reactions WHERE actor_id = ? AND (activity_id = ? OR reaction_key = ?)",
                        array($action['actor'], $action['activity'], pg_ap_reaction_key($action['kind'], $action['actor'], $action['object']))
                    );
                } else {
                    db_q("DELETE FROM ap_reactions WHERE actor_id = ? AND activity_id = ?", array($action['actor'], $action['activity']));
                }

                $done[] = 'undo';

                if (function_exists('pg_page_cache_mark_dirty')) {
                    pg_page_cache_mark_dirty();
                }
                break;

            case 'actor_gone':
                pg_ap_forget_actor($action['actor']);
                $done[] = 'actor deleted';
                break;

            case 'reply':
                if (function_exists('pg_ap_reply_receive')) {
                    $done[] = pg_ap_reply_receive($action['note'], $action['actor'], $site);
                } else {
                    $done[] = 'reply not stored: comments from the Fediverse are not handled yet';
                }
                break;

            case 'note_deleted':
                if (function_exists('pg_ap_reply_deleted')) {
                    $done[] = 'note deleted, comments removed: ' . pg_ap_reply_deleted($action['object'], $action['actor']);
                } else {
                    $done[] = 'note deleted';
                }
                break;

            default:
                $done[] = 'ignored: ' . $action['reason'];
        }
    }

    return $done;
}

/**
 * Removes what the site keeps of a remote actor: its follower row, its
 * reactions and its comments (pg_ap_forget_comments(), loaded with
 * activitypub_moderation.php). A Delete of the actor and a block
 * (pg_ap_block_add()) come here.
 *
 * @param string $actor_id
 */
function pg_ap_forget_actor($actor_id)
{
    db_q("DELETE FROM ap_followers WHERE actor_id = ?", array($actor_id));
    db_q("DELETE FROM ap_reactions WHERE actor_id = ?", array($actor_id));

    if (function_exists('pg_ap_forget_comments')) {
        pg_ap_forget_comments($actor_id);
    }
}

/**
 * The parts of a remote actor document a follower row keeps.
 *
 * @param array $document
 * @return array|false inbox, shared_inbox, name, avatar; false without an
 *                     https inbox
 */
function pg_ap_follower_from_document($document)
{
    if (!is_array($document)) {
        return false;
    }

    $inbox = isset($document['inbox']) && is_string($document['inbox']) ? $document['inbox'] : '';
    $shared = (isset($document['endpoints']['sharedInbox']) && is_string($document['endpoints']['sharedInbox'])) ? $document['endpoints']['sharedInbox'] : '';

    if (!preg_match('#^https://#i', $inbox) || (strlen($inbox) > 255)) {
        return false;
    }

    if (!preg_match('#^https://#i', $shared) || (strlen($shared) > 255)) {
        $shared = '';
    }

    $name = isset($document['name']) && is_string($document['name']) && (trim($document['name']) !== '') ? $document['name']
        : (isset($document['preferredUsername']) && is_string($document['preferredUsername']) ? $document['preferredUsername'] : '');
    $avatar = isset($document['icon']) ? pg_ap_id(isset($document['icon']['url']) ? $document['icon']['url'] : $document['icon']) : '';

    if (!preg_match('#^https://#i', $avatar) || (strlen($avatar) > 255)) {
        $avatar = '';
    }

    $name = function_exists('mb_substr') ? mb_substr(strip_tags($name), 0, 100, 'UTF-8') : substr(strip_tags($name), 0, 100);

    return array('inbox' => $inbox, 'shared_inbox' => $shared, 'name' => $name, 'avatar' => $avatar);
}

/**
 * A remote actor's document, fetched by a signed GET.
 *
 * @param string $actor_id
 * @return array|false pg_ap_follower_from_document()
 */
function pg_ap_remote_actor($actor_id)
{
    $answer = pg_ap_fetch($actor_id);

    if (!$answer['ok']) {
        return false;
    }

    $document = json_decode($answer['body'], true);

    if (!is_array($document) || !isset($document['id']) || ($document['id'] !== $actor_id)) {
        return false;
    }

    return pg_ap_follower_from_document($document);
}

/**
 * A signed GET of an ActivityPub document through pg_curl_run().
 *
 * @param string $url
 * @return array pg_curl_run()'s answer (ok, status, body)
 */
function pg_ap_fetch($url)
{
    if (!preg_match('#^https://#i', (string) $url)) {
        return array('ok' => false, 'status' => 0, 'body' => '');
    }

    if (!function_exists('api_http_check_url')) {
        require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/http.php');
    }

    $headers = array('Accept: application/activity+json, application/ld+json; profile="https://www.w3.org/ns/activitystreams"');
    $signed = pg_ap_sign('GET', $url);

    if ($signed) {
        $headers = array_merge($headers, $signed);
    }

    return pg_curl_run('GET', $url, array(
        'headers'       => $headers,
        'timeout'       => 10,
        'max_size'      => 1048576,
        'check_address' => true,
    ));
}

/**
 * The request's headers, lower-case names, as pg_ap_verify() reads them.
 *
 * @param array $server $_SERVER
 * @return array
 */
function pg_ap_request_headers($server)
{
    $headers = array();

    foreach ($server as $name => $value) {
        if (strpos($name, 'HTTP_') === 0) {
            $headers[strtolower(str_replace('_', '-', substr($name, 5)))] = (string) $value;
        }
    }

    foreach (array('CONTENT_TYPE' => 'content-type', 'CONTENT_LENGTH' => 'content-length') as $name => $header) {
        if (isset($server[$name]) && !isset($headers[$header])) {
            $headers[$header] = (string) $server[$name];
        }
    }

    return $headers;
}

/**
 * Writes one line to data/cache/ap/inbox.log, moving a full log aside.
 *
 * @param string $line
 */
function pg_ap_inbox_log($line)
{
    $directory = pg_ap_cache_directory();

    if (!is_dir($directory) && !@mkdir($directory, 0755, true)) {
        return;
    }

    $file = $directory . '/inbox.log';

    if (is_file($file) && (@filesize($file) > PG_AP_INBOX_LOG_MAX)) {
        @rename($file, $file . '.1');
    }

    @file_put_contents($file, gmdate('Y-m-d H:i:s') . ' ' . str_replace(array("\r", "\n"), ' ', $line) . "\n", FILE_APPEND | LOCK_EX);
}

/**
 * Refuses a delivery: logs it in the inbox log, the firewall's log and
 * data/firewall.log, and answers with the status.
 *
 * @param int    $status
 * @param string $rule  the firewall rule id
 * @param string $why
 */
function pg_ap_inbox_refuse($status, $rule, $why)
{
    pg_ap_inbox_log($status . ' ' . $why);

    if (function_exists('waf_log_event')) {
        waf_log_event('block', $rule, 'activitypub', 0, 'ap_inbox.php', $why);
    }

    if (function_exists('waf_text_log') && function_exists('waf_client_ip')) {
        waf_text_log('DENY', waf_client_ip(), array('status' => $status, 'rule' => $rule));
    }

    pg_ap_inbox_answer($status, $why);
}

/**
 * Answers the sender and ends the request.
 *
 * @param int    $status
 * @param string $text
 */
function pg_ap_inbox_answer($status, $text = '')
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    echo $text;
    exit();
}

/**
 * Handles one delivery to the inbox.
 */
function pg_ap_inbox_receive()
{
    if (!pg_ap_enabled()) {
        pg_ap_inbox_answer(404);
    }

    if (!isset($_SERVER['REQUEST_METHOD']) || ($_SERVER['REQUEST_METHOD'] !== 'POST')) {
        header('Allow: POST');
        pg_ap_inbox_answer(405);
    }

    $body = (string) file_get_contents('php://input', false, null, 0, PG_AP_INBOX_MAX_BODY + 1);

    if (strlen($body) > PG_AP_INBOX_MAX_BODY) {
        pg_ap_inbox_answer(413);
    }

    $activity = json_decode($body, true);

    if (!is_array($activity) || !isset($activity['type'])) {
        pg_ap_inbox_answer(400, 'The body is not an activity.');
    }

    $actor = pg_ap_id(isset($activity['actor']) ? $activity['actor'] : '');
    $headers = pg_ap_request_headers($_SERVER);
    $signature = pg_ap_signature_parse(isset($headers['signature']) ? $headers['signature'] : '');
    $key_id = isset($signature['keyid']) ? (string) $signature['keyid'] : '';
    $blocks = pg_ap_blocks();

    // A blocked sender is refused before its key is fetched.
    if (pg_ap_blocked($actor, $blocks) || (($key_id !== '') && pg_ap_blocked($key_id, $blocks))) {
        pg_ap_inbox_refuse(403, 'ap-inbox-blocked', 'blocked: ' . $actor);
    }

    $verified = pg_ap_verify(array(
        'method'  => 'POST',
        'target'  => isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '',
        'headers' => $headers,
        'body'    => $body,
    ));

    $site = pg_ap_site();

    if (!$verified['ok']) {
        // A deleted account's key is gone with it; its Delete is honoured
        // when the actor's own address answers that it no longer exists.
        if (isset($activity['type']) && ($activity['type'] === 'Delete') && ($actor !== '')
            && (pg_ap_id(isset($activity['object']) ? $activity['object'] : '') === $actor)) {
            $answer = pg_ap_fetch($actor);

            if (in_array((int) $answer['status'], array(404, 410), true)) {
                pg_ap_forget_actor($actor);
                pg_ap_inbox_log('202 Delete ' . $actor . ': actor gone');
                pg_ap_inbox_answer(202);
            }

            pg_ap_inbox_log('202 Delete ' . $actor . ': not verified, kept');
            pg_ap_inbox_answer(202);
        }

        pg_ap_inbox_refuse(401, 'ap-inbox-signature', $verified['error'] . ' ' . $verified['key_id']);
    }

    if (pg_ap_blocked($verified['owner'], $blocks)) {
        pg_ap_inbox_refuse(403, 'ap-inbox-blocked', 'blocked: ' . $verified['owner']);
    }

    if (!pg_ap_inbox_authorized($activity, $verified)) {
        pg_ap_inbox_refuse(401, 'ap-inbox-actor', 'the key ' . $verified['key_id'] . ' does not speak for ' . $actor);
    }

    $done = pg_ap_inbox_apply(pg_ap_inbox_plan($activity, $site), $site);

    pg_ap_inbox_log('202 ' . $activity['type'] . ' ' . $actor . ': ' . implode(', ', $done));
    pg_ap_inbox_answer(202);
}
