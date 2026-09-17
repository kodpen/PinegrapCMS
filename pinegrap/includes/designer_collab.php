<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

/* =====================================================================
 * VISUAL PAGE EDITOR — TWO PEOPLE IN THE SAME ROOM
 * ---------------------------------------------------------------------
 * The editor saves a whole page in one POST; there is no merge. So two
 * operators editing one page do not collide loudly, they collide
 * silently: the second save replaces the first and the first person's
 * work is gone with nothing on screen having said so.
 *
 * Three pieces answer that:
 *
 *   presence  - who else has this design open, and on which page. Drawn
 *               as avatars in the toolbar so the room is visible before
 *               anything goes wrong.
 *   lock      - one editor per page. Held by the first tab to claim it,
 *               released when that tab leaves the page or goes quiet.
 *   notes     - what the locked-out person can still do. Read-only with
 *               no way to speak is a dead end, and the editor already had
 *               a per-node note; a second mechanism for the same job would
 *               only make the operator ask which one to use.
 *
 * The lock is a permission, not a hint. It is checked on the server at
 * save time as well as drawn in the UI - hiding the Save button is not
 * an authorisation check (the same lesson as the offline-payment
 * checkbox in the cart).
 *
 * It is also the foundation the future preview mode builds on: that mode
 * is this one plus a list of node types the viewer may still change.
 * Anything added here should be expressible as "what may this session do
 * to this page", not as "is this session the owner".
 * ===================================================================== */

// Presence is refreshed on this cadence by the editor; anything older than
// PG_COLLAB_STALE has stopped beating and no longer counts. Two missed beats
// plus a margin - a laptop that sleeps for twenty seconds should not lose
// its lock, and a browser that was killed should not hold one for minutes.
if (!defined('PG_COLLAB_BEAT'))  { define('PG_COLLAB_BEAT', 20); }
if (!defined('PG_COLLAB_STALE')) { define('PG_COLLAB_STALE', 65); }

/**
 * True when this installation can track presence.
 *
 * Same defensive shape as pg_page_noindex_ready(): the code ships before the
 * database upgrade runs, and in that window the feature has to be absent
 * rather than fatal. Both tables are probed because the upgrade creates them
 * in two separate statements and can stop between them.
 */
function pg_collab_ready($recheck = false)
{
    static $ready = null;
    if ($ready !== null && !$recheck) return $ready;

    $ready = false;
    if (!class_exists('db') || !isset(db::$con) || !db::$con) return $ready;

    foreach (array('designer_presence', 'designer_page_lock') as $table) {
        $row = @mysqli_query(db::$con, "SHOW TABLES LIKE '" . e($table) . "'");
        if (!$row || mysqli_num_rows($row) === 0) return $ready;
    }
    $ready = true;
    return $ready;
}

// One key per open editor tab, not per user and not per PHP session: the same
// person may legitimately have two tabs open on two pages of one design, and
// they must not fight each other for one lock. Generated in the browser and
// only ever compared, never trusted as identity - authority comes from
// validate_user(), the key only says "which tab".
function pg_collab_clean_key($key)
{
    $key = preg_replace('/[^A-Za-z0-9_\-]/', '', (string)$key);
    return substr($key, 0, 64);
}

/**
 * Record that this tab is alive, on this design, looking at this page.
 *
 * One row per tab, replaced in place. Rows are never deleted on the way out
 * by anything other than an explicit leave: a browser that is killed never
 * gets to say goodbye, so staleness has to be the real retirement mechanism
 * and the explicit path is only there to make handover immediate.
 */
function pg_collab_beat($session_key, $style_id, $page_id, $user_id)
{
    if (!pg_collab_ready()) return;
    $session_key = pg_collab_clean_key($session_key);
    if ($session_key === '') return;

    $style_id = (int)$style_id;
    $page_id  = (int)$page_id;
    $user_id  = (int)$user_id;
    $now      = time();

    db("INSERT INTO designer_presence (session_key, style_id, page_id, user_id, started_at, last_seen)
        VALUES ('" . e($session_key) . "', '$style_id', '$page_id', '$user_id', '$now', '$now')
        ON DUPLICATE KEY UPDATE
            style_id  = VALUES(style_id),
            page_id   = VALUES(page_id),
            user_id   = VALUES(user_id),
            last_seen = VALUES(last_seen)");

    // Retire what stopped beating. Cheap (the table holds one row per open
    // editor tab site-wide) and it keeps the avatar row honest without a
    // separate sweep anywhere else.
    $cut = $now - PG_COLLAB_STALE;
    db("DELETE FROM designer_presence WHERE last_seen < '$cut'");
    db("DELETE FROM designer_page_lock WHERE last_seen < '$cut'");
}

// This tab is closing or leaving the design. Explicit, so the next person does
// not have to wait out the staleness window.
function pg_collab_leave($session_key)
{
    if (!pg_collab_ready()) return;
    $session_key = pg_collab_clean_key($session_key);
    if ($session_key === '') return;
    db("DELETE FROM designer_presence  WHERE session_key = '" . e($session_key) . "'");
    db("DELETE FROM designer_page_lock WHERE session_key = '" . e($session_key) . "'");
}

/**
 * Claim - or renew - the right to edit one page.
 *
 * A single statement decides it. SELECT-then-INSERT would let two tabs that
 * ask in the same instant both read "free" and both write; the primary key on
 * page_id plus ON DUPLICATE KEY UPDATE makes a second holder impossible.
 *
 * The assignment order matters: MySQL evaluates the SET list left to right and
 * later expressions see the already-updated column, so `session_key` is
 * assigned LAST - every other column tests the OLD holder.
 *
 * `$take_over_own`: also evict a live holder when it is another tab of the
 * SAME user. Their other tab drops to view mode on its next heartbeat; what
 * it had not saved stays there unsaved. Never crosses users - one person
 * cannot take a page from another this way.
 *
 * Returns array('held' => bool, 'holder' => row|null).
 */
function pg_collab_claim_page($session_key, $page_id, $style_id, $user_id, $take_over_own = false)
{
    $out = array('held' => false, 'holder' => null);
    if (!pg_collab_ready()) { $out['held'] = true; return $out; }   // no schema → no locking

    $session_key = pg_collab_clean_key($session_key);
    $page_id     = (int)$page_id;
    if ($session_key === '' || $page_id <= 0) { $out['held'] = true; return $out; }

    $style_id = (int)$style_id;
    $user_id  = (int)$user_id;
    $now      = time();
    $cut      = $now - PG_COLLAB_STALE;
    $key_sql  = e($session_key);

    // "Mine, or nobody's any more" — the condition every column below shares.
    // With an own-tab takeover, "mine" includes any session of this user.
    $takeover = $take_over_own && $user_id > 0
        ? "(session_key = '$key_sql' OR last_seen < '$cut' OR user_id = '$user_id')"
        : "(session_key = '$key_sql' OR last_seen < '$cut')";

    db("INSERT INTO designer_page_lock (page_id, session_key, user_id, style_id, acquired_at, last_seen)
        VALUES ('$page_id', '$key_sql', '$user_id', '$style_id', '$now', '$now')
        ON DUPLICATE KEY UPDATE
            user_id     = IF($takeover, '$user_id', user_id),
            style_id    = IF($takeover, '$style_id', style_id),
            acquired_at = IF($takeover, IF(session_key = '$key_sql', acquired_at, '$now'), acquired_at),
            last_seen   = IF($takeover, '$now', last_seen),
            session_key = IF($takeover, '$key_sql', session_key)");

    $row = db_item("SELECT page_id, session_key, user_id, acquired_at, last_seen
                    FROM designer_page_lock WHERE page_id = '$page_id' LIMIT 1");
    if (!is_array($row)) { $out['held'] = true; return $out; }

    $out['held']   = ((string)$row['session_key'] === $session_key);
    $out['holder'] = $row;
    return $out;
}

// Give up the lock on one page (switching tabs inside the editor). Scoped to
// this tab's key so a stale request cannot free somebody else's lock.
function pg_collab_release_page($session_key, $page_id)
{
    if (!pg_collab_ready()) return;
    $session_key = pg_collab_clean_key($session_key);
    $page_id     = (int)$page_id;
    if ($session_key === '' || $page_id <= 0) return;
    db("DELETE FROM designer_page_lock
        WHERE page_id = '$page_id' AND session_key = '" . e($session_key) . "'");
}

/**
 * May this session write this page?
 *
 * Called from the save path, which is the only answer that counts. A page
 * nobody has claimed is writable - the lock exists to stop a SECOND editor,
 * not to require a ceremony before the first one can work. An unknown session
 * key (an old tab, a script) is treated as not holding anything.
 */
function pg_collab_may_edit_page($session_key, $page_id)
{
    if (!pg_collab_ready()) return true;
    $page_id = (int)$page_id;
    if ($page_id <= 0) return true;                 // a page that does not exist yet has no lock

    $cut = time() - PG_COLLAB_STALE;
    $row = db_item("SELECT session_key, user_id FROM designer_page_lock
                    WHERE page_id = '$page_id' AND last_seen >= '$cut' LIMIT 1");
    if (!is_array($row)) return true;               // free

    return ((string)$row['session_key'] === pg_collab_clean_key($session_key));
}

// Display name / avatar for the people in the room. The chat module already
// answers this question for the staff list, and two different answers to
// "what does this user look like" would drift; reuse it when it is present
// and fall back to the username when chat has never been installed.
function pg_collab_user_brief($user_id)
{
    $user_id = (int)$user_id;
    if ($user_id <= 0) return array('id' => 0, 'name' => '', 'username' => '', 'avatar' => '');

    // The chat module owns the "what does this user look like" answer, but it
    // is only loaded on the chat endpoints - so load it here rather than
    // silently falling through to a lesser answer on every presence beat.
    if (!function_exists('pg_chat_user_row') && file_exists(dirname(__FILE__) . '/../chat.php')) {
        require_once(dirname(__FILE__) . '/../chat.php');
    }

    if (function_exists('pg_chat_user_row') && function_exists('pg_chat_user_brief')) {
        $row = pg_chat_user_row($user_id);
        if (is_array($row) && !empty($row)) {
            $brief = pg_chat_user_brief($row);
            if (is_array($brief)) {
                return array(
                    'id'       => (int)$user_id,
                    'name'     => isset($brief['name']) ? (string)$brief['name'] : '',
                    'username' => isset($brief['username']) ? (string)$brief['username'] : '',
                    'avatar'   => isset($brief['avatar']) ? (string)$brief['avatar'] : '',
                );
            }
        }
    }

    // Chat is not installed. A person's name is not on `user` - it is on the
    // contact the account is linked to (contacts.first_name / last_name), and
    // an account with no contact has only its username.
    $row = db_item("SELECT user.user_username, contacts.first_name, contacts.last_name
                    FROM user
                    LEFT JOIN contacts ON contacts.id = user.user_contact
                    WHERE user.user_id = '$user_id' LIMIT 1");
    if (!is_array($row)) return array('id' => $user_id, 'name' => '#' . $user_id, 'username' => '', 'avatar' => '');
    $name = trim((string)$row['first_name'] . ' ' . (string)$row['last_name']);
    if ($name === '') $name = (string)$row['user_username'];
    return array(
        'id'       => $user_id,
        'name'     => $name,
        'username' => (string)$row['user_username'],
        'avatar'   => '',
    );
}

/**
 * Everyone else in this design right now.
 *
 * "Else" is by SESSION, not by user: the same person in two tabs is two
 * presences and the second one still holds a lock the first has to respect.
 * Page names are resolved here so the tooltip can say where somebody is
 * rather than showing an id.
 */
function pg_collab_peers($session_key, $style_id)
{
    if (!pg_collab_ready()) return array();
    $session_key = pg_collab_clean_key($session_key);
    $style_id    = (int)$style_id;
    if ($style_id <= 0) return array();

    $cut  = time() - PG_COLLAB_STALE;
    $rows = db_items("SELECT p.session_key, p.page_id, p.user_id, p.started_at, p.last_seen,
                             page.page_name, page.page_title,
                             l.session_key AS lock_key
                      FROM designer_presence p
                      LEFT JOIN page ON page.page_id = p.page_id
                      LEFT JOIN designer_page_lock l
                             ON l.page_id = p.page_id AND l.last_seen >= '$cut'
                      WHERE p.style_id = '$style_id'
                        AND p.last_seen >= '$cut'
                        AND p.session_key <> '" . e($session_key) . "'
                      ORDER BY p.started_at ASC");

    $out = array();
    foreach ((array)$rows as $row) {
        $brief = pg_collab_user_brief($row['user_id']);
        $label = trim((string)$row['page_title']);
        if ($label === '') $label = (string)$row['page_name'];
        $out[] = array(
            'user_id'  => (int)$row['user_id'],
            'name'     => $brief['name'],
            'username' => $brief['username'],
            'avatar'   => $brief['avatar'],
            'page_id'  => (int)$row['page_id'],
            'page'     => $label,
            'editing'  => (!empty($row['lock_key']) && (string)$row['lock_key'] === (string)$row['session_key']),
            'since'    => (int)$row['started_at'],
        );
    }
    return $out;
}


/* ── Notes: the one write a locked-out session may make ──────────────────
 *
 * A note lives on the node, in the page's tree — which is exactly what a
 * view-mode session cannot save. Without this endpoint the "you may still
 * leave a note" promise would be a lie: the note would sit in the browser
 * until the tab closed and then be gone.
 *
 * So the note is written straight into the stored tree, and this is the ONLY
 * exception to the page lock. It is bounded on purpose: one property, on one
 * node, on one page. It cannot move, delete or restyle anything, so it cannot
 * be turned into a way around the lock.
 */
function pg_collab_note_save($page_id, $node_id, $note, $user)
{
    $page_id = (int)$page_id;
    $node_id = substr(preg_replace('/[^A-Za-z0-9_\-]/', '', (string)$node_id), 0, 64);
    if ($page_id <= 0 || $node_id === '') return false;
    if (!function_exists('pg_multi_page_design_ready') || !pg_multi_page_design_ready()) return false;

    // Only pages the editor owns, and only for somebody who may open the
    // editor at all — the caller has already been through validate_area_access.
    $row = db_item("SELECT page_tree_json, page_folder FROM page
                    WHERE page_id = '$page_id' AND layout_type = 'system' LIMIT 1");
    if (!is_array($row)) return false;
    if (function_exists('check_edit_access') && !check_edit_access($row['page_folder'])) return false;

    $tree = json_decode((string)$row['page_tree_json'], true);
    if (!is_array($tree)) return false;

    $note  = trim((string)$note);
    $note  = mb_substr($note, 0, 4000);
    $found = false;

    // pg_collab_user_brief() takes an id, and $user here is the row.
    $author_id = is_array($user) ? (int)(isset($user['id']) ? $user['id'] : 0) : (int)$user;
    $brief  = function_exists('pg_collab_user_brief') ? pg_collab_user_brief($author_id) : array();
    $author = '';
    if (is_array($brief) && !empty($brief['name'])) $author = (string)$brief['name'];
    if ($author === '' && isset($_SESSION['sessionusername'])) $author = (string)$_SESSION['sessionusername'];

    $walk = function (&$node) use (&$walk, $node_id, $note, $author, &$found) {
        if ($found || !is_array($node)) return;
        if (isset($node['_id']) && (string)$node['_id'] === $node_id) {
            if (!isset($node['props']) || !is_array($node['props'])) $node['props'] = array();
            if ($note === '') {
                unset($node['props']['_notes'], $node['props']['_notes_by'], $node['props']['_notes_at']);
            } else {
                $node['props']['_notes'] = $note;
                // Who wrote it and when. Stamped HERE rather than taken from
                // the request: a note whose author the browser supplies is a
                // note anybody can sign with anybody's name, and the whole
                // point of the name is that the next person can ask them.
                $node['props']['_notes_by'] = $author;
                $node['props']['_notes_at'] = time();
            }
            $found = true;
            return;
        }
        if (!empty($node['children']) && is_array($node['children'])) {
            foreach ($node['children'] as $i => $_unused) {
                $walk($node['children'][$i]);
                if ($found) return;
            }
        }
    };
    $walk($tree);
    if (!$found) return false;

    // Only page_tree_json is touched. page_tree_code is the RENDERED html and
    // notes never reach it, so leaving it alone keeps this write from
    // republishing a page whose layout somebody else is mid-way through
    // editing.
    // The stamp is what the other open editors poll. Bumped in the same
    // statement as the tree, so there is no window in which the note is
    // stored and nobody is told about it.
    $stamp_sql = pg_collab_notes_ready() ? ", page_notes_at = '" . time() . "'" : '';
    db("UPDATE page SET page_tree_json = '" . e(pg_designer_tree_encode($tree)) . "'
        $stamp_sql
        WHERE page_id = '$page_id'");
    return true;
}

/**
 * Is the notes stamp column there?
 *
 * `page.page_notes_at` arrived with 2026.4.4. A site that takes the files
 * before running the upgrade keeps working — notes are written and read
 * exactly as before, they simply do not announce themselves to the other
 * people in the room until the page is reloaded. Same shape as
 * pg_page_noindex_ready().
 */
function pg_collab_notes_ready($recheck = false)
{
    static $ready = null;
    if ($ready !== null && !$recheck) return $ready;
    $ready = (db_value("SHOW COLUMNS FROM page WHERE Field = 'page_notes_at'") !== null);
    return $ready;
}


/**
 * When each page of this design last had a note written to it.
 *
 * This is what makes the heartbeat able to say "somebody left a note" without
 * reading a single page tree. The trees are LONGTEXT and there is one per
 * open tab; hashing them every twenty seconds, in every open editor, to
 * answer a question that is almost always "nothing changed" is the kind of
 * cost that only shows up on the busiest site.
 *
 * Pages with no notes are left out: their stamp is 0 and the client's absent
 * entry already means the same thing.
 */
function pg_collab_note_stamps($style_id)
{
    $style_id = (int)$style_id;
    if ($style_id <= 0 || !pg_collab_notes_ready()) return array();

    $rows = db_items("SELECT page_id, page_notes_at FROM page
                      WHERE page_style = '$style_id' AND page_notes_at > 0");
    $out = array();
    foreach ((array)$rows as $row) {
        $out[(string)(int)$row['page_id']] = (int)$row['page_notes_at'];
    }
    return $out;
}


/**
 * Every note on one page, as stored.
 *
 * Only the notes — not the tree. The person asking has the page open and is
 * part-way through their own edits; handing them a whole tree back would
 * mean choosing between throwing their work away and merging two layouts,
 * and neither is something a notification should be doing. A note is an
 * annotation, so annotations are all that travel.
 */
function pg_collab_page_notes($page_id)
{
    $page_id = (int)$page_id;
    if ($page_id <= 0) return array();
    if (!function_exists('pg_multi_page_design_ready') || !pg_multi_page_design_ready()) return array();

    $row = db_item("SELECT page_tree_json FROM page WHERE page_id = '$page_id' LIMIT 1");
    if (!is_array($row)) return array();

    $tree = json_decode((string)$row['page_tree_json'], true);
    if (!is_array($tree)) return array();

    $out = array();
    $walk = function ($node) use (&$walk, &$out) {
        if (!is_array($node)) return;
        $props = (isset($node['props']) && is_array($node['props'])) ? $node['props'] : array();
        if (isset($props['_notes']) && trim((string)$props['_notes']) !== '' && isset($node['_id'])) {
            $out[] = array(
                'node_id' => (string)$node['_id'],
                'text'    => (string)$props['_notes'],
                'by'      => isset($props['_notes_by']) ? (string)$props['_notes_by'] : '',
                'at'      => isset($props['_notes_at']) ? (int)$props['_notes_at'] : 0,
            );
        }
        if (!empty($node['children']) && is_array($node['children'])) {
            foreach ($node['children'] as $child) $walk($child);
        }
    };
    $walk($tree);
    return $out;
}

