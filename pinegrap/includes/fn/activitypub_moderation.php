<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ActivityPub moderation: blocked actors and domains (ap_blocks) and what a
 * block removes. A block is written from the comment moderation screen (a
 * comment that came from the Fediverse) or the Fediverse settings screen;
 * from then on the inbox refuses the actor before its key is fetched
 * (pg_ap_inbox_receive()), and everything the site keeps of the blocked
 * actors goes: follower rows, reactions, comments and the deliveries still
 * queued for them. A Delete of the actor (an erasure request from its own
 * server) runs the same pg_ap_forget_actor() path.
 *
 * The normalizing and matching are pure, so the tests check them without a
 * database.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/activitypub_inbox.php');

/**
 * The value a block is stored with: an actor's address (http or https,
 * without its fragment) or a lowercase host name. A domain may be typed as
 * a host, an address on it, or a handle (`@user@host`).
 *
 * @param string $kind  'actor' or 'domain'
 * @param mixed  $value as typed or taken from a comment
 * @return string '' when it names nothing that can be blocked
 */
function pg_ap_block_normalize($kind, $value)
{
    $value = is_scalar($value) ? trim((string) $value) : '';

    if (($value === '') || (strlen($value) > 255)) {
        return '';
    }

    if ($kind === 'actor') {
        $value = preg_replace('/#.*$/', '', $value);

        if (!preg_match('~^https?://[^/?#\s]+~i', $value) || preg_match('/\s/', $value)) {
            return '';
        }

        return $value;
    }

    if ($kind !== 'domain') {
        return '';
    }

    if (strpos($value, '://') !== false) {
        $value = pg_ap_host($value);
    } elseif (strpos($value, '@') !== false) {
        $value = substr($value, strrpos($value, '@') + 1);
    }

    $value = trim(strtolower($value), '.');

    // A port or path left from a typed address is not part of the host.
    $value = preg_replace('~[:/].*$~', '', $value);

    if (!preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/', $value)) {
        return '';
    }

    return $value;
}

/**
 * The actors of a list that a block covers.
 *
 * @param array    $block     array('kind' => …, 'value' => …)
 * @param string[] $actor_ids
 * @return string[] distinct, in the list's order
 */
function pg_ap_block_actors($block, $actor_ids)
{
    $covered = array();

    foreach ($actor_ids as $actor_id) {
        $actor_id = (string) $actor_id;

        if (($actor_id !== '') && !isset($covered[$actor_id]) && pg_ap_blocked($actor_id, array($block))) {
            $covered[$actor_id] = true;
        }
    }

    return array_keys($covered);
}

/**
 * The blocks with their ids, newest first, for the screens.
 *
 * @return array rows of id, kind, value, created_at
 */
function pg_ap_block_list()
{
    $rows = array();
    $result = db_q("SELECT id, kind, value, created_at FROM ap_blocks ORDER BY created_at DESC, id DESC");

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }
    }

    return $rows;
}

/**
 * Blocks an actor or a domain and removes what the site keeps of the
 * actors it covers. Blocking again what is already blocked writes no second
 * row and removes anything that arrived since.
 *
 * @param string $kind  'actor' or 'domain'
 * @param mixed  $value
 * @return array ok, value (as stored), actors (how many were removed)
 */
function pg_ap_block_add($kind, $value)
{
    $value = pg_ap_block_normalize($kind, $value);

    if ($value === '') {
        return array('ok' => false, 'value' => '', 'actors' => 0);
    }

    if (!db_q("INSERT IGNORE INTO ap_blocks (kind, value, created_at) VALUES (?, ?, ?)", array($kind, $value, (string) time()))) {
        return array('ok' => false, 'value' => $value, 'actors' => 0);
    }

    $block = array('kind' => $kind, 'value' => $value);
    $actors = pg_ap_block_actors($block, pg_ap_block_known_actors($block));

    foreach ($actors as $actor_id) {
        pg_ap_forget_actor($actor_id);
    }

    // What is still queued for a blocked domain's inboxes is never sent.
    if ($kind === 'domain') {
        $like = '%' . pg_ap_like_escape($value) . '%';
        $result = db_q("SELECT id, inbox FROM ap_outbox WHERE state = 'queued' AND inbox LIKE ?", array($like));

        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                if (pg_ap_blocked((string) $row['inbox'], array($block))) {
                    db_q("DELETE FROM ap_outbox WHERE id = ?", array((string) $row['id']));
                }
            }
        }
    }

    return array('ok' => true, 'value' => $value, 'actors' => count($actors));
}

/**
 * The actors the site keeps anything of that a block may cover: followers,
 * reactions and the writers of remote comments. A domain block reads only
 * the addresses that contain the domain; pg_ap_block_actors() decides.
 *
 * @param array $block
 * @return string[]
 */
function pg_ap_block_known_actors($block)
{
    if ($block['kind'] === 'actor') {
        return array($block['value']);
    }

    $like = '%' . pg_ap_like_escape($block['value']) . '%';
    $queries = array(
        "SELECT DISTINCT actor_id FROM ap_followers WHERE actor_id LIKE ?",
        "SELECT DISTINCT actor_id FROM ap_reactions WHERE actor_id LIKE ?",
        "SELECT DISTINCT remote_actor AS actor_id FROM comments WHERE remote_actor LIKE ?",
    );
    $ids = array();

    foreach ($queries as $query) {
        $result = db_q($query, array($like));

        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $ids[] = (string) $row['actor_id'];
            }
        }
    }

    return $ids;
}

/**
 * A value for inside a LIKE pattern, its wildcards taken literally.
 *
 * @param string $value
 * @return string
 */
function pg_ap_like_escape($value)
{
    return addcslashes((string) $value, '\\%_');
}

/**
 * Lifts a block. What it removed stays removed.
 *
 * @param mixed $id ap_blocks.id
 * @return bool
 */
function pg_ap_block_remove($id)
{
    $id = is_scalar($id) ? (int) $id : 0;

    return ($id > 0) && (bool) db_q("DELETE FROM ap_blocks WHERE id = ?", array((string) $id));
}

/**
 * Removes the comments a remote actor wrote, with their notifications, and
 * rebuilds the counters of the records they were on. Called by
 * pg_ap_forget_actor() for a block and for a Delete of the actor.
 *
 * @param string $actor_id
 * @return int how many comments were removed
 */
function pg_ap_forget_comments($actor_id)
{
    $actor_id = (string) $actor_id;

    if ($actor_id === '') {
        return 0;
    }

    $result = db_q("SELECT id, page_id, item_type, item_id FROM comments WHERE remote_actor = ?", array($actor_id));

    if (!$result) {
        return 0;
    }

    $ids = array();
    $threads = array();

    while ($row = mysqli_fetch_assoc($result)) {
        $ids[] = (string) (int) $row['id'];
        $threads[$row['page_id'] . '|' . $row['item_type'] . '|' . $row['item_id']] = $row;
    }

    if (!$ids) {
        return 0;
    }

    db_q("DELETE FROM comments WHERE remote_actor = ?", array($actor_id));
    db_q("DELETE FROM notifications WHERE comment_id IN (" . implode(',', $ids) . ")");

    foreach ($threads as $row) {
        if (function_exists('pg_comment_refresh_record_info')) {
            pg_comment_refresh_record_info($row['page_id'], $row['item_type'], $row['item_id']);
        }
    }

    if (function_exists('pg_page_cache_mark_dirty')) {
        pg_page_cache_mark_dirty();
    }

    return count($ids);
}
