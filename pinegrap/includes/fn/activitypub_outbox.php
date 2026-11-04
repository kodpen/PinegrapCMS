<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ActivityPub publishing: what the site publishes goes to its followers as
 * Create, Update and Delete activities through the ap_outbox queue.
 *
 * What is published: the records of the forms and the pages of the folders
 * chosen in ap_settings.publish (forms: custom form page ids, folders:
 * folder ids). A page counts while it is a standard page in a chosen
 * folder that is public, not the drafts and not in the recycle bin; a
 * record while it is complete and has an address on a public record page
 * that shows it (the record view open to everyone, its filters passed,
 * none comparing a field with the viewer). Anything else is never sent.
 *
 * How it is found: the general job sweeps (pg_ap_outbox_sweep()). It
 * compares what is published now, with each object's last change, against
 * the activities already logged, and queues the difference: Create for a
 * new object, Update for a changed one, Delete for one gone. Every path that
 * changes a page or a record (the panel, the designer, the API, a scheduled
 * move, the recycle bin) is covered, since none of them has to tell the
 * outbox. pg_announce() of a page or form event makes the next tick sweep;
 * otherwise a sweep runs every PG_AP_SWEEP_EVERY seconds. The first sweep
 * after the feature is switched on logs what is already published without
 * sending it, so followers do not receive the whole archive.
 *
 * The queue: one log row per activity (inbox '', state sent) that keeps the
 * activity for the outbox collection and the sweep, and one row per
 * follower inbox, a shared inbox once for all its followers. Delivery
 * (pg_ap_deliver()) signs and posts; a failure is retried on the mail
 * queue's ladder (pg_ap_backoff()); a refusal that will not change (4xx but
 * 408 and 429) fails at once.
 *
 * Loaded on demand (job.php, activitypub.php, pg_announce(), the tests).
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

// Seconds between two sweeps when nothing announced a change.
if (!defined('PG_AP_SWEEP_EVERY')) {
    define('PG_AP_SWEEP_EVERY', 900);
}

// Activities one sweep queues; the rest wait for the next sweep, so a bulk
// import reaches followers a few at a time.
if (!defined('PG_AP_SWEEP_LIMIT')) {
    define('PG_AP_SWEEP_LIMIT', 20);
}

// Days a delivery row is kept once it was sent or given up.
if (!defined('PG_AP_DELIVERY_KEEP_DAYS')) {
    define('PG_AP_DELIVERY_KEEP_DAYS', 30);
}

// Attempts of one delivery before it is given up, as the mail queue's.
define('PG_AP_MAX_ATTEMPTS', 6);

define('PG_AP_PUBLIC', 'https://www.w3.org/ns/activitystreams#Public');

/**
 * The address of a published object: under the actor, by kind and id, so it
 * stays the same when the page or the record is renamed.
 *
 * @param array  $site pg_ap_site()
 * @param string $kind 'p' page, 'r' record
 * @param int    $id
 * @return string
 */
function pg_ap_object_id($site, $kind, $id)
{
    $urls = pg_ap_urls($site);

    return $urls['id'] . '/' . $kind . '/' . (int) $id;
}

/**
 * An ISO 8601 time in UTC.
 *
 * @param int $time
 * @return string
 */
function pg_ap_time($time)
{
    return gmdate('Y-m-d\TH:i:s\Z', (int) $time);
}

/**
 * Rich text as it goes out: sanitized as for visitors, every site address
 * made absolute (a follower's server reads it far from the site).
 *
 * @param string $html
 * @param array  $site pg_ap_site()
 * @return string
 */
function pg_ap_content_html($html, $site)
{
    $html = (string) $html;

    if (trim($html) === '') {
        return '';
    }

    if (function_exists('pg_sanitize_rich_text')) {
        $html = pg_sanitize_rich_text($html);
    }

    $parts = parse_url($site['base']);
    $origin = (isset($parts['scheme']) ? $parts['scheme'] : 'https') . '://' . (isset($parts['host']) ? $parts['host'] : $site['host']) . (isset($parts['port']) ? ':' . $parts['port'] : '');

    // A root-relative address takes the origin; one relative to the page
    // (no scheme, no slash, no fragment or query alone) takes the base.
    $html = preg_replace('#(\s(?:href|src)=")/(?!/)#i', '$1' . $origin . '/', $html);

    return preg_replace('#(\s(?:href|src)=")(?![a-z][a-z0-9+.\-]*:|/|\#|\?)#i', '$1' . $site['base'], $html);
}

/**
 * The Article for one published object.
 *
 * @param array $site pg_ap_site()
 * @param array $item id, url, name, description (HTML), published (time),
 *                    updated (time), images (list of absolute addresses)
 * @return array
 */
function pg_ap_article($site, $item)
{
    $urls = pg_ap_urls($site);
    $link = htmlspecialchars((string) $item['url'], ENT_QUOTES, 'UTF-8');
    $description = pg_ap_content_html(isset($item['description']) ? $item['description'] : '', $site);

    if (($description !== '') && (stripos($description, '<p') !== 0)) {
        $description = '<p>' . $description . '</p>';
    }

    // The description is part of the content: Mastodon shows an Article's
    // summary as a content warning that hides the post.
    $content = $description . '<p><a href="' . $link . '">' . $link . '</a></p>';

    $article = array(
        'id'           => (string) $item['id'],
        'type'         => 'Article',
        'attributedTo' => $urls['id'],
        'name'         => (string) $item['name'],
        'content'      => $content,
        'mediaType'    => 'text/html',
        'url'          => (string) $item['url'],
        'published'    => pg_ap_time($item['published']),
        'to'           => array(PG_AP_PUBLIC),
        'cc'           => array($urls['followers']),
    );

    if (!empty($item['updated']) && ((int) $item['updated'] > (int) $item['published'])) {
        $article['updated'] = pg_ap_time($item['updated']);
    }

    $attachments = array();

    foreach ((isset($item['images']) ? (array) $item['images'] : array()) as $image) {
        if (preg_match('#^https?://#i', (string) $image)) {
            $attachments[] = array('type' => 'Image', 'mediaType' => pg_ap_media_type($image), 'url' => (string) $image, 'name' => (string) $item['name']);
        }
    }

    if ($attachments) {
        $article['attachment'] = $attachments;
    }

    return $article;
}

/**
 * The Article of a page.
 *
 * @param array $page page_id, page_name, page_title, page_meta_description,
 *                    published (time), updated (time)
 * @param array $site pg_ap_site()
 * @return array
 */
function pg_ap_object_for_page($page, $site)
{
    $name = trim((string) $page['page_title']);
    $path = function_exists('encode_url_path') ? encode_url_path((string) $page['page_name']) : rawurlencode((string) $page['page_name']);
    $description = trim((string) $page['page_meta_description']);

    return pg_ap_article($site, array(
        'id'          => pg_ap_object_id($site, 'p', $page['page_id']),
        'url'         => $site['base'] . $path,
        'name'        => ($name !== '') ? $name : (string) $page['page_name'],
        'description' => ($description !== '') ? htmlspecialchars($description, ENT_QUOTES, 'UTF-8') : '',
        'published'   => (int) $page['published'],
        'updated'     => (int) $page['updated'],
        'images'      => array(),
    ));
}

/**
 * The Article of a record.
 *
 * @param array $row  id, url (absolute), title, description (HTML or
 *                    text), images, published (time), updated (time)
 * @param array $site pg_ap_site()
 * @return array
 */
function pg_ap_object_for_record($row, $site)
{
    $title = trim((string) $row['title']);

    return pg_ap_article($site, array(
        'id'          => pg_ap_object_id($site, 'r', $row['id']),
        'url'         => (string) $row['url'],
        'name'        => ($title !== '') ? $title : (string) $row['url'],
        'description' => (string) $row['description'],
        'published'   => (int) $row['published'],
        'updated'     => (int) $row['updated'],
        'images'      => isset($row['images']) ? (array) $row['images'] : array(),
    ));
}

/**
 * An activity around an object: Create and Update carry the Article, Delete
 * a Tombstone of its address. The activity's address names the object, the
 * type and the object's change time, which is how the sweep reads the log
 * back (pg_ap_activity_key()).
 *
 * @param array        $site   pg_ap_site()
 * @param string       $type   Create, Update or Delete
 * @param array|string $object the Article, or the object's address for Delete
 * @param int          $stamp  the object's change time (Delete: now)
 * @return array
 */
function pg_ap_activity($site, $type, $object, $stamp)
{
    $urls = pg_ap_urls($site);
    $object_id = is_array($object) ? (string) $object['id'] : (string) $object;

    $activity = array(
        '@context'  => 'https://www.w3.org/ns/activitystreams',
        'id'        => $object_id . '#' . strtolower($type) . '-' . (int) $stamp,
        'type'      => $type,
        'actor'     => $urls['id'],
        'published' => pg_ap_time($stamp),
        'to'        => array(PG_AP_PUBLIC),
        'cc'        => array($urls['followers']),
        'object'    => ($type === 'Delete') ? array('id' => $object_id, 'type' => 'Tombstone') : $object,
    );

    return $activity;
}

/**
 * An activity address read back into the object, type and stamp, or null
 * for one this outbox did not write.
 *
 * @param string $activity_id
 * @return array|null object, type (create, update, delete), stamp
 */
function pg_ap_activity_key($activity_id)
{
    if (!preg_match('/^(.+\/[pr]\/\d+)#(create|update|delete)-(\d+)$/', (string) $activity_id, $match)) {
        return null;
    }

    return array('object' => $match[1], 'type' => $match[2], 'stamp' => (int) $match[3]);
}

/**
 * The last logged activity of each object.
 *
 * @param array $activity_ids the log's activity addresses, oldest first
 * @return array object address => array(type, stamp)
 */
function pg_ap_logged_objects($activity_ids)
{
    $out = array();

    foreach ((array) $activity_ids as $activity_id) {
        $key = pg_ap_activity_key($activity_id);

        if ($key !== null) {
            $out[$key['object']] = array('type' => $key['type'], 'stamp' => $key['stamp']);
        }
    }

    return $out;
}

/**
 * What a sweep queues: Create for a published object never sent or sent
 * deleted, Update for one changed after its last activity, Delete for a
 * sent object no longer published. Creates and Updates first in the order
 * given, Deletes after; at most $limit.
 *
 * @param array $current object address => change time, what is published now
 * @param array $logged  pg_ap_logged_objects()
 * @param int   $limit
 * @return array list of array(object, type: Create, Update or Delete)
 */
function pg_ap_sweep_plan($current, $logged, $limit = PG_AP_SWEEP_LIMIT)
{
    $plan = array();

    foreach ((array) $current as $object => $stamp) {
        $last = isset($logged[$object]) ? $logged[$object] : null;

        if (($last === null) || ($last['type'] === 'delete')) {
            $plan[] = array('object' => (string) $object, 'type' => 'Create');
        } elseif ((int) $stamp > $last['stamp']) {
            $plan[] = array('object' => (string) $object, 'type' => 'Update');
        }
    }

    foreach ((array) $logged as $object => $last) {
        if (($last['type'] !== 'delete') && !array_key_exists($object, (array) $current)) {
            $plan[] = array('object' => (string) $object, 'type' => 'Delete');
        }
    }

    return array_slice($plan, 0, max(0, (int) $limit));
}

/**
 * The inboxes one activity goes to: a follower's shared inbox, else its own,
 * each once, https only.
 *
 * @param array $followers rows with inbox and shared_inbox
 * @return array
 */
function pg_ap_delivery_inboxes($followers)
{
    $out = array();

    foreach ((array) $followers as $follower) {
        $inbox = trim((string) (!empty($follower['shared_inbox']) ? $follower['shared_inbox'] : (isset($follower['inbox']) ? $follower['inbox'] : '')));

        if (preg_match('#^https://#i', $inbox) && !in_array($inbox, $out, true)) {
            $out[] = $inbox;
        }
    }

    return $out;
}

/**
 * Seconds before the next attempt after $attempts failed ones, 0 past the
 * ladder: the mail queue's (pg_mail_backoff(), one minute to a day).
 *
 * @param int $attempts
 * @return int
 */
function pg_ap_backoff($attempts)
{
    $ladder = array(60, 300, 1800, 7200, 21600, 86400);
    $index = max(0, (int) $attempts - 1);

    return isset($ladder[$index]) ? $ladder[$index] : 0;
}

/**
 * What a delivery answer means: sent (2xx), failed (a refusal that will not
 * change: 4xx but 408 and 429) or retry (anything else).
 *
 * @param int $status 0 when no answer came
 * @return string
 */
function pg_ap_delivery_outcome($status)
{
    $status = (int) $status;

    if (($status >= 200) && ($status < 300)) {
        return 'sent';
    }

    if (($status >= 400) && ($status < 500) && !in_array($status, array(408, 429), true)) {
        return 'failed';
    }

    return 'retry';
}

/**
 * The row an attempt leaves: sent, failed, or queued again on the ladder.
 *
 * @param string $outcome  pg_ap_delivery_outcome()
 * @param int    $attempts attempts before this one
 * @param int    $now
 * @return array state, attempts, next_at
 */
function pg_ap_delivery_update($outcome, $attempts, $now)
{
    $attempt = (int) $attempts + 1;

    if ($outcome !== 'retry') {
        return array('state' => $outcome, 'attempts' => $attempt, 'next_at' => 0);
    }

    $wait = ($attempt < PG_AP_MAX_ATTEMPTS) ? pg_ap_backoff($attempt) : 0;

    return ($wait > 0)
        ? array('state' => 'queued', 'attempts' => $attempt, 'next_at' => (int) $now + $wait)
        : array('state' => 'failed', 'attempts' => $attempt, 'next_at' => 0);
}
