<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Release bundles: several drafts of a visual design prepared together,
 * looked at together, published at one moment and taken back together.
 *
 * A bundle (page_bundle) is a named list of drafts (page_bundle_item). A
 * draft is a new page, or a replacement standing for a live page: a copy
 * made as an experiment copies its variant (duplicate_page() into the Drafts
 * folder), with the live page's state at that moment kept, so a hand publish
 * since is seen as "changed since". Nothing is staged inside a page: a live
 * page's own Publish still goes live at once.
 *
 * While its bundle holds it (open, scheduled or partly published) a draft is
 * not published alone, binned, deleted over the API or made a variant:
 * pg_experiment_variant_hold_message() asks pg_page_bundle_hold(), so the
 * paths that refuse an experiment's variant refuse a bundled draft too.
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
 * The statuses a bundle takes (page_bundle.status). Pure.
 *
 * @return array
 */
function pg_page_bundle_statuses()
{
    return array('open', 'scheduled', 'published', 'partial', 'reverted');
}

/**
 * Whether a bundle in a status holds its drafts: open, scheduled, and partly
 * published (what was not written waits for "Publish the rest"). Pure.
 *
 * @param string $status
 * @return bool
 */
function pg_page_bundle_holds($status)
{
    return in_array((string) $status, array('open', 'scheduled', 'partial'), true);
}

/**
 * Whether a bundle may move from one status to another. A time set or
 * cleared moves between open and scheduled; a publish ends in published, or
 * in partial when a failure could not be taken back; a take back ends in
 * reverted. A taken back bundle is closed. Pure.
 *
 * @param string $from
 * @param string $to
 * @return bool
 */
function pg_page_bundle_may_move($from, $to)
{
    $moves = array(
        'open'      => array('scheduled', 'published', 'partial'),
        'scheduled' => array('open', 'published', 'partial'),
        'partial'   => array('published', 'reverted'),
        'published' => array('reverted'),
    );

    $from = (string) $from;

    return isset($moves[$from]) && in_array((string) $to, $moves[$from], true);
}

/**
 * Whether pages may be added to or removed from a bundle in a status: only
 * before it is published. Pure.
 *
 * @param string $status
 * @return bool
 */
function pg_page_bundle_may_change($status)
{
    return in_array((string) $status, array('open', 'scheduled'), true);
}

/**
 * Whether a bundle in a status may be deleted. An open or scheduled one
 * releases its drafts, which stay drafts; a taken back one holds nothing. A
 * published or partly published one keeps the record its take back reads.
 * Pure.
 *
 * @param string $status
 * @return bool
 */
function pg_page_bundle_may_delete($status)
{
    return in_array((string) $status, array('open', 'scheduled', 'reverted'), true);
}

/**
 * Whether a user makes and publishes bundles: administrators and designers
 * (roles 0 and 1). Pure.
 *
 * @param array $user
 * @return bool
 */
function pg_page_bundle_user_may($user)
{
    return is_array($user) && isset($user['role']) && ((int) $user['role'] <= 1);
}

/**
 * A bundle's name as kept: control characters as spaces, trimmed, 1 to 100
 * characters; '' when it is not one. Pure.
 *
 * @param string $name
 * @return string
 */
function pg_page_bundle_clean_name($name)
{
    $name = trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) $name));

    if (($name === '') || (mb_strlen($name, 'UTF-8') > 100)) {
        return '';
    }

    return $name;
}

/**
 * Whether a draft or a live page is already taken by an open bundle: a draft
 * is in one open bundle only, and a live page has one replacement in one
 * open bundle. Pure: the open items are given.
 *
 * @param int   $draft_page_id 0 to ask for the live page alone
 * @param int   $live_page_id  0 for a new page
 * @param array $open_items    pg_page_bundle_open_items()
 * @return array kind ('' free, 'draft', 'live'), bundle_name
 */
function pg_page_bundle_add_conflict($draft_page_id, $live_page_id, $open_items)
{
    $draft_page_id = (int) $draft_page_id;
    $live_page_id = (int) $live_page_id;

    foreach ((array) $open_items as $item) {
        if (($draft_page_id > 0) && ((int) $item['draft_page_id'] === $draft_page_id)) {
            return array('kind' => 'draft', 'bundle_name' => (string) $item['bundle_name']);
        }

        if (($live_page_id > 0) && ((int) $item['live_page_id'] === $live_page_id)) {
            return array('kind' => 'live', 'bundle_name' => (string) $item['bundle_name']);
        }
    }

    return array('kind' => '', 'bundle_name' => '');
}

/**
 * Whether a page written into a bundle keeps its place when two people add
 * the same draft, or a replacement of the same live page, at the same
 * moment: both pass the check before either writes, so after the write the
 * waiting rows are read again and the smallest id stays. Pure.
 *
 * @param int   $item_id the row just written
 * @param array $ids     the ids of the waiting rows of open bundles for the
 *                       same draft or the same live page, this one included
 * @return bool
 */
function pg_page_bundle_race_keeps($item_id, $ids)
{
    $item_id = (int) $item_id;
    $ids = array_map('intval', (array) $ids);

    if ($item_id <= 0) {
        return false;
    }

    foreach ($ids as $id) {
        if (($id > 0) && ($id < $item_id)) {
            return false;
        }
    }

    return true;
}

/**
 * A page's state as a bundle compares it: the SHA-1 of its tree, title and
 * description, the three things a replacement writes. Pure.
 *
 * @param string $tree_json
 * @param string $title
 * @param string $description
 * @return string
 */
function pg_page_bundle_state_hash($tree_json, $title, $description)
{
    return sha1(json_encode(array((string) $tree_json, (string) $title, (string) $description)));
}

/**
 * Whether a replacement's live page was changed since the copy was made: its
 * state now is not the one kept, or it is gone (''). A new page never is.
 * Pure.
 *
 * @param array  $item          live_page_id, live_state
 * @param string $current_state pg_page_bundle_live_state()
 * @return bool
 */
function pg_page_bundle_changed_since($item, $current_state)
{
    if ((int) $item['live_page_id'] <= 0) {
        return false;
    }

    return ((string) $current_state === '') || ((string) $current_state !== (string) $item['live_state']);
}

/**
 * The refusal for publishing alone, binning, deleting or making a variant of
 * a draft its bundle holds.
 *
 * @param string $page_name
 * @param string $bundle_name
 * @return string
 */
function pg_page_bundle_hold_text($page_name, $bundle_name)
{
    return lang(array(
        'string' => 'The page "{var:1}" is in the release bundle "{var:2}", so it stays a draft until the bundle is published or deleted, or the page is removed from it.',
        'vars' => array((string) $page_name, (string) $bundle_name)));
}

/**
 * Whether the bundle tables exist (the 2026.11.0 upgrade, 11.0.228).
 *
 * @return bool
 */
function pg_page_bundles_ready()
{
    return function_exists('pg_schema_has') && pg_schema_has('page_bundle') && pg_schema_has('page_bundle_item');
}

/**
 * A bundle.
 *
 * @param int $id
 * @return array|null the page_bundle row
 */
function pg_page_bundle_get($id)
{
    $id = (int) $id;

    if (($id <= 0) || !pg_page_bundles_ready()) {
        return null;
    }

    $row = db_item("SELECT * FROM page_bundle WHERE id = '$id' LIMIT 1");

    return is_array($row) ? $row : null;
}

/**
 * The bundles, newest first.
 *
 * @return array page_bundle rows with item_count
 */
function pg_page_bundle_list()
{
    if (!pg_page_bundles_ready()) {
        return array();
    }

    return (array) db_items(
        "SELECT page_bundle.*, COUNT(page_bundle_item.id) AS item_count
        FROM page_bundle
        LEFT JOIN page_bundle_item ON page_bundle_item.bundle_id = page_bundle.id
        GROUP BY page_bundle.id
        ORDER BY page_bundle.id DESC");
}

/**
 * A bundle's pages, with the draft's and the live page's names.
 *
 * @param int $bundle_id
 * @return array page_bundle_item rows with draft_name, live_name
 */
function pg_page_bundle_items($bundle_id)
{
    $bundle_id = (int) $bundle_id;

    if (($bundle_id <= 0) || !pg_page_bundles_ready()) {
        return array();
    }

    $items = (array) db_items(
        "SELECT page_bundle_item.*, draft.page_name AS draft_name, live.page_name AS live_name
        FROM page_bundle_item
        LEFT JOIN page AS draft ON draft.page_id = page_bundle_item.draft_page_id
        LEFT JOIN page AS live ON live.page_id = page_bundle_item.live_page_id
        WHERE page_bundle_item.bundle_id = '$bundle_id'
        ORDER BY page_bundle_item.id");

    foreach ($items as $key => $item) {
        $items[$key]['draft_name'] = pg_page_bundle_page_label($item['draft_name'], $item['draft_page_id']);
        $items[$key]['live_name'] = pg_page_bundle_page_label($item['live_name'], $item['live_page_id']);
    }

    return $items;
}

/**
 * The name a bundle's page is shown under: a page in the Recycle Bin by its
 * own name, not the parked "pgbin_<id>_" one; a page deleted for good by its
 * id. Pure.
 *
 * @param string|null $name    page_name from the join, null when no row
 * @param int         $page_id
 * @return string
 */
function pg_page_bundle_page_label($name, $page_id)
{
    $page_id = (int) $page_id;
    $name = (string) $name;
    $prefix = (defined('PG_RECYCLE_PARK_PREFIX') ? PG_RECYCLE_PARK_PREFIX : 'pgbin_') . $page_id . '_';

    if (($page_id > 0) && (strpos($name, $prefix) === 0)) {
        $name = (string) substr($name, strlen($prefix));
    }

    return (($name === '') && ($page_id > 0)) ? '#' . $page_id : $name;
}

/**
 * The pages of every bundle that holds its drafts, still waiting to be
 * written.
 *
 * @return array bundle_id, bundle_name, draft_page_id, live_page_id
 */
function pg_page_bundle_open_items()
{
    if (!pg_page_bundles_ready()) {
        return array();
    }

    return (array) db_items(
        "SELECT page_bundle.id AS bundle_id, page_bundle.name AS bundle_name,
            page_bundle_item.draft_page_id, page_bundle_item.live_page_id
        FROM page_bundle_item
        JOIN page_bundle ON page_bundle.id = page_bundle_item.bundle_id
        WHERE page_bundle.status IN ('open', 'scheduled', 'partial') AND page_bundle_item.status = 'waiting'
        ORDER BY page_bundle.id");
}

/**
 * The bundle that holds a draft, or null.
 *
 * @param int $page_id
 * @return array|null bundle_id, bundle_name, status
 */
function pg_page_bundle_hold($page_id)
{
    $page_id = (int) $page_id;

    if (($page_id <= 0) || !pg_page_bundles_ready()) {
        return null;
    }

    $row = db_item(
        "SELECT page_bundle.id AS bundle_id, page_bundle.name AS bundle_name, page_bundle.status
        FROM page_bundle_item
        JOIN page_bundle ON page_bundle.id = page_bundle_item.bundle_id
        WHERE page_bundle_item.draft_page_id = '$page_id' AND page_bundle_item.status = 'waiting'
            AND page_bundle.status IN ('open', 'scheduled', 'partial')
        ORDER BY page_bundle.id
        LIMIT 1");

    if (!is_array($row) || !pg_page_bundle_holds($row['status'])) {
        return null;
    }

    $row['bundle_id'] = (int) $row['bundle_id'];
    $row['bundle_name'] = (string) $row['bundle_name'];

    return $row;
}

/**
 * A live page's state now (pg_page_bundle_state_hash()), '' when it is not
 * a page on the site any more.
 *
 * @param int $page_id
 * @return string
 */
function pg_page_bundle_live_state($page_id)
{
    $page_id = (int) $page_id;

    if ($page_id <= 0) {
        return '';
    }

    $row = db_item(
        "SELECT page_tree_json, page_title, page_meta_description, page_folder FROM page
        WHERE page_id = '$page_id'" . pg_designer_not_binned_sql() . " LIMIT 1");

    if (!is_array($row) || pg_page_is_draft($row['page_folder'])) {
        return '';
    }

    return pg_page_bundle_state_hash($row['page_tree_json'], $row['page_title'], $row['page_meta_description']);
}

/**
 * Whether a bundle's replacement finds its live page changed since the copy.
 *
 * @param array $item page_bundle_item row
 * @return bool
 */
function pg_page_bundle_item_changed($item)
{
    if ((int) $item['live_page_id'] <= 0) {
        return false;
    }

    return pg_page_bundle_changed_since($item, pg_page_bundle_live_state($item['live_page_id']));
}

/**
 * Makes a bundle, open and empty.
 *
 * @param string $name
 * @param array  $user
 * @return array ok, error, id
 */
function pg_page_bundle_create($name, $user)
{
    if (!pg_page_bundles_ready()) {
        return array('ok' => false, 'error' => lang('Release bundles are not available yet. Please run the upgrade.'), 'id' => 0);
    }

    if (!pg_page_bundle_user_may($user)) {
        return array('ok' => false, 'error' => lang('Only administrators and designers can make a release bundle.'), 'id' => 0);
    }

    $name = pg_page_bundle_clean_name($name);

    if ($name === '') {
        return array('ok' => false, 'error' => lang('Give the bundle a name of up to 100 characters.'), 'id' => 0);
    }

    $now = time();

    $ok = db_q(
        "INSERT INTO page_bundle (name, status, created_by, created_at, updated_at) VALUES (?, 'open', ?, ?, ?)",
        array($name, (int) $user['id'], $now, $now));

    $id = $ok ? (int) mysqli_insert_id(db::$con) : 0;

    if ($id <= 0) {
        return array('ok' => false, 'error' => lang('The bundle could not be saved.'), 'id' => 0);
    }

    return array('ok' => true, 'error' => '', 'id' => $id);
}

/**
 * Renames a bundle that is not published yet.
 *
 * @param array  $bundle pg_page_bundle_get()
 * @param string $name
 * @return array ok, error
 */
function pg_page_bundle_rename($bundle, $name)
{
    if (!pg_page_bundle_may_change($bundle['status'])) {
        return array('ok' => false, 'error' => lang('A published bundle cannot be changed.'));
    }

    $name = pg_page_bundle_clean_name($name);

    if ($name === '') {
        return array('ok' => false, 'error' => lang('Give the bundle a name of up to 100 characters.'));
    }

    db_q("UPDATE page_bundle SET name = ?, updated_at = ? WHERE id = ?", array($name, time(), (int) $bundle['id']));

    return array('ok' => true, 'error' => '');
}

/**
 * Moves a bundle to another status. The status in the WHERE keeps two clicks
 * or the job and a person from moving it twice.
 *
 * @param array  $bundle pg_page_bundle_get()
 * @param string $to
 * @param array  $set    more columns => values written with the move
 *                       (publish_at, scheduled_by, published_by,
 *                       published_at, reverted_by, reverted_at,
 *                       failed_page_id, failed_reason); failed_reason is the
 *                       save's answer as it was, cut at 500 characters and
 *                       never translated later
 * @return array ok, error
 */
function pg_page_bundle_move($bundle, $to, $set = array())
{
    if (!pg_page_bundle_may_move($bundle['status'], $to)) {
        return array('ok' => false, 'error' => lang('The bundle cannot make that change now.'));
    }

    $columns = array('publish_at', 'scheduled_by', 'published_by', 'published_at', 'reverted_by', 'reverted_at',
        'failed_page_id', 'failed_reason');
    $sql = 'status = ?, updated_at = ?';
    $values = array((string) $to, time());

    foreach ((array) $set as $column => $value) {
        if (in_array($column, $columns, true)) {
            $sql .= ', ' . $column . ' = ?';
            $values[] = ($column === 'failed_reason') ? mb_substr((string) $value, 0, 500, 'UTF-8') : (int) $value;
        }
    }

    $values[] = (int) $bundle['id'];
    $values[] = (string) $bundle['status'];

    db_q("UPDATE page_bundle SET $sql WHERE id = ? AND status = ?", $values);

    if (mysqli_affected_rows(db::$con) < 1) {
        return array('ok' => false, 'error' => lang('The bundle was changed meanwhile; look again.'));
    }

    return array('ok' => true, 'error' => '');
}

/**
 * Clears the failure named on a bundle (failed_page_id, failed_reason): a
 * new publish attempt starts without the last one's answer.
 *
 * @param array $bundle pg_page_bundle_get()
 */
function pg_page_bundle_clear_failure($bundle)
{
    db_q("UPDATE page_bundle SET failed_page_id = 0, failed_reason = '' WHERE id = ?", array((int) $bundle['id']));
}

/**
 * Sets or clears a bundle's publish time: a time moves an open bundle to
 * scheduled, 0 moves a scheduled one back to open.
 *
 * The person who sets the time is kept (scheduled_by): the general job
 * publishes in that person's name. Clearing the time clears it.
 *
 * @param array $bundle     pg_page_bundle_get()
 * @param int   $publish_at unix time, 0 for none
 * @param array $user       the person setting or clearing the time
 * @param int   $now
 * @return array ok, error
 */
function pg_page_bundle_set_time($bundle, $publish_at, $user, $now = null)
{
    $publish_at = max(0, (int) $publish_at);
    $now = ($now === null) ? time() : (int) $now;

    if (($publish_at > 0) && ($publish_at <= $now)) {
        return array('ok' => false, 'error' => lang('The publish time must be in the future.'));
    }

    $to = ($publish_at > 0) ? 'scheduled' : 'open';
    $scheduled_by = ($publish_at > 0) ? (int) $user['id'] : 0;

    if ($bundle['status'] === $to) {
        db_q("UPDATE page_bundle SET publish_at = ?, scheduled_by = ?, updated_at = ? WHERE id = ? AND status = ?",
            array($publish_at, $scheduled_by, time(), (int) $bundle['id'], $to));

        return array('ok' => true, 'error' => '');
    }

    return pg_page_bundle_move($bundle, $to, array('publish_at' => $publish_at, 'scheduled_by' => $scheduled_by));
}

/**
 * Deletes a bundle with its pages. Its drafts stay drafts. A publish that
 * died is finished first (pg_page_bundle_with_lock()): one that leaves the
 * bundle partly published keeps it.
 *
 * @param array $bundle pg_page_bundle_get()
 * @param array $user   the person deleting it
 * @return array ok, error
 */
function pg_page_bundle_delete($bundle, $user)
{
    return pg_page_bundle_with_lock($bundle, $user, pg_page_bundle_busy_answer(), 'pg_page_bundle_delete_locked');
}

/**
 * Deletes a bundle with its pages, under the bundle's lock.
 *
 * @param array $bundle pg_page_bundle_get(), read under the lock
 * @return array ok, error
 */
function pg_page_bundle_delete_locked($bundle)
{
    if (!pg_page_bundle_may_delete($bundle['status'])) {
        return array('ok' => false, 'error' => lang('A published bundle is kept so that it can be taken back.'));
    }

    $id = (int) $bundle['id'];

    db_q("DELETE FROM page_bundle WHERE id = ? AND status = ?", array($id, (string) $bundle['status']));

    if (mysqli_affected_rows(db::$con) < 1) {
        return array('ok' => false, 'error' => lang('The bundle was changed meanwhile; look again.'));
    }

    db("DELETE FROM page_bundle_item WHERE bundle_id = '$id'");

    // The share links of its preview end with it, and never open a later
    // bundle given the same id.
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/prototype_links.php');
    pg_proto_links_revoke_bundle($id);

    return array('ok' => true, 'error' => '');
}

/**
 * The refusal for adding a page taken by an open bundle, '' when it is free.
 *
 * @param int $draft_page_id
 * @param int $live_page_id
 * @return string
 */
function pg_page_bundle_add_refusal($draft_page_id, $live_page_id)
{
    $conflict = pg_page_bundle_add_conflict($draft_page_id, $live_page_id, pg_page_bundle_open_items());

    if ($conflict['kind'] === 'draft') {
        return lang(array('string' => 'This draft is already in the release bundle "{var:1}".', 'vars' => array($conflict['bundle_name'])));
    }

    if ($conflict['kind'] === 'live') {
        return lang(array('string' => 'This page already has a replacement in the release bundle "{var:1}".', 'vars' => array($conflict['bundle_name'])));
    }

    return '';
}

/**
 * Writes a page into a bundle, then reads the waiting rows of open bundles
 * for the same draft and the same live page again: when somebody added the
 * same one at the same moment, the smaller id stays and this row is taken
 * back (pg_page_bundle_race_keeps()).
 *
 * @param array  $bundle
 * @param int    $draft_page_id
 * @param int    $live_page_id
 * @param string $live_state
 * @param array  $user
 * @return array id (0 when not written), error
 */
function pg_page_bundle_insert_item($bundle, $draft_page_id, $live_page_id, $live_state, $user)
{
    $draft_page_id = (int) $draft_page_id;
    $live_page_id = (int) $live_page_id;

    $ok = db_q(
        "INSERT INTO page_bundle_item (bundle_id, draft_page_id, live_page_id, live_state, status, added_by, added_at)
        VALUES (?, ?, ?, ?, 'waiting', ?, ?)",
        array((int) $bundle['id'], $draft_page_id, $live_page_id, (string) $live_state, (int) $user['id'], time()));

    // Read at once: any later query resets it.
    $id = $ok ? (int) mysqli_insert_id(db::$con) : 0;

    if ($id <= 0) {
        return array('id' => 0, 'error' => '');
    }

    $match = "page_bundle_item.draft_page_id = '$draft_page_id'" . (($live_page_id > 0) ? " OR page_bundle_item.live_page_id = '$live_page_id'" : '');

    $ids = db_values(
        "SELECT page_bundle_item.id
        FROM page_bundle_item
        JOIN page_bundle ON page_bundle.id = page_bundle_item.bundle_id
        WHERE page_bundle.status IN ('open', 'scheduled', 'partial') AND page_bundle_item.status = 'waiting'
            AND ($match)");

    if (!pg_page_bundle_race_keeps($id, $ids)) {
        db("DELETE FROM page_bundle_item WHERE id = '$id'");

        $taken = pg_page_bundle_add_refusal($draft_page_id, $live_page_id);

        return array('id' => 0, 'error' => ($taken !== '') ? $taken : lang('The bundle was changed meanwhile; look again.'));
    }

    db_q("UPDATE page_bundle SET updated_at = ? WHERE id = ?", array(time(), (int) $bundle['id']));

    return array('id' => $id, 'error' => '');
}

/**
 * Loads the Recycle Bin's functions as the panel's screens have them:
 * pg_recycle_page() and pg_delete_page_record() live in the explorer's files
 * and call the folder helpers of view_folder_and_files_f.php, which loads
 * them all. recycle_bin.php alone ends in a call to an undefined function.
 */
function pg_page_bundle_load_recycle()
{
    require_once(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');
}

/**
 * Adds a copy of a live designer page to a bundle, as a replacement: the
 * copy is made in the Drafts folder as pg_experiment_sheet_create() copies a
 * variant, and the live page's state now is kept for "changed since". A
 * classic page, a page whose experiment is open and a page with a
 * replacement in an open bundle are refused.
 *
 * @param array $bundle       pg_page_bundle_get()
 * @param int   $live_page_id
 * @param array $user
 * @return array ok, error, item_id, draft_page_id
 */
function pg_page_bundle_add_copy($bundle, $live_page_id, $user)
{
    $fail = function ($error) {
        return array('ok' => false, 'error' => $error, 'item_id' => 0, 'draft_page_id' => 0);
    };

    $checked = pg_page_bundle_add_copy_check($bundle, $live_page_id);

    if ($checked['error'] !== '') {
        return $fail($checked['error']);
    }

    $live = $checked['live'];
    $live_id = (int) $live['page_id'];

    $folder = pg_page_draft_ready() ? (int) pg_page_draft_folder_id(true) : 0;

    if ($folder <= 0) {
        return $fail(lang('Drafts are not available yet. Please run the upgrade.'));
    }

    // The state is read before the copy: a publish in between is then seen
    // as "changed since", never missed.
    $state = pg_page_bundle_live_state($live_id);

    require_once(PG_FUNCTIONS_DIR . '/duplicate_page_f.php');

    $copy = duplicate_page(array('page' => array('id' => $live_id), 'folder' => array('id' => $folder)));

    if (!isset($copy['status']) || ($copy['status'] !== 'success') || empty($copy['page']['id'])) {
        return $fail(isset($copy['message']) ? $copy['message'] : lang('The copy of the page could not be made.'));
    }

    $draft_page_id = (int) $copy['page']['id'];

    // The copy goes back to the live page's folder if it is ever published
    // as a page of its own.
    db("INSERT IGNORE INTO page_drafts (page_id, folder_id, drafted_at, drafted_by)
        VALUES ('$draft_page_id', '" . (int) $live['page_folder'] . "', UNIX_TIMESTAMP(), '" . (int) $user['id'] . "')");

    $written = pg_page_bundle_insert_item($bundle, $draft_page_id, $live_id, $state, $user);

    if ($written['error'] !== '') {
        // Somebody added a replacement of this page at the same moment: the
        // copy made for this one is taken back with its row.
        pg_page_bundle_load_recycle();
        pg_delete_page_record($draft_page_id, $user);

        return $fail($written['error']);
    }

    if ($written['id'] <= 0) {
        return $fail(lang('The copy was made in Drafts, but it could not be added to the bundle.'));
    }

    return array('ok' => true, 'error' => '', 'item_id' => $written['id'], 'draft_page_id' => $draft_page_id);
}

/**
 * The checks of "Add a copy of a page", before anything is copied: the
 * bundle still changes, the page is a live visual-design page with no open
 * experiment and no replacement waiting in another open bundle. Shared by
 * pg_page_bundle_add_copy() and the API's dry run.
 *
 * @param array $bundle       pg_page_bundle_get()
 * @param int   $live_page_id
 * @return array error ('' when it may be copied), live (the page row)
 */
function pg_page_bundle_add_copy_check($bundle, $live_page_id)
{
    if (!pg_page_bundle_may_change($bundle['status'])) {
        return array('error' => lang('A published bundle cannot be changed.'), 'live' => null);
    }

    $live = pg_experiment_sheet_live_page($live_page_id);

    if (!$live) {
        return array('error' => lang('Only a page of a visual design that is on the site can be copied into a bundle.'), 'live' => null);
    }

    $live_id = (int) $live['page_id'];

    if (pg_experiment_sheet_ready() && db_value("SELECT id FROM experiments WHERE page_id = '$live_id' AND status <> 'ended' LIMIT 1")) {
        return array('error' => lang('This page has an open experiment; end it before adding a copy of the page to a bundle.'), 'live' => null);
    }

    $taken = pg_page_bundle_add_refusal(0, $live_id);

    return array('error' => $taken, 'live' => ($taken === '') ? $live : null);
}

/**
 * The checks of "Add a draft", before anything is written: the bundle still
 * changes, the page is a visual-design draft outside the bin, no open
 * experiment's variant and in no other open bundle. Shared by
 * pg_page_bundle_add_draft() and the API's dry run.
 *
 * @param array $bundle        pg_page_bundle_get()
 * @param int   $draft_page_id
 * @return string the refusal, '' when it may be added
 */
function pg_page_bundle_add_draft_check($bundle, $draft_page_id)
{
    if (!pg_page_bundle_may_change($bundle['status'])) {
        return lang('A published bundle cannot be changed.');
    }

    $draft_page_id = (int) $draft_page_id;
    $page = ($draft_page_id > 0)
        ? db_item("SELECT page_id, page_name, page_folder FROM page
            WHERE page_id = '$draft_page_id' AND " . pg_page_visual_design_sql() . pg_designer_not_binned_sql() . " LIMIT 1")
        : null;

    if (!is_array($page) || !pg_page_is_draft($page['page_folder'])) {
        return lang('Only a draft of a visual design can be added to a bundle.');
    }

    if (pg_experiment_variant_hold($draft_page_id)) {
        return lang('This draft is a variant in an open experiment, so it cannot be added to a bundle.');
    }

    return pg_page_bundle_add_refusal($draft_page_id, 0);
}

/**
 * Adds a draft of a visual design to a bundle, as a new page. An open
 * experiment's variant and a draft in another open bundle are refused; a
 * publish time of the draft's own is cleared, since the bundle publishes it.
 *
 * @param array $bundle        pg_page_bundle_get()
 * @param int   $draft_page_id
 * @param array $user
 * @return array ok, error, item_id, time_cleared
 */
function pg_page_bundle_add_draft($bundle, $draft_page_id, $user)
{
    $fail = function ($error) {
        return array('ok' => false, 'error' => $error, 'item_id' => 0, 'time_cleared' => false);
    };

    $error = pg_page_bundle_add_draft_check($bundle, $draft_page_id);

    if ($error !== '') {
        return $fail($error);
    }

    $draft_page_id = (int) $draft_page_id;
    $written = pg_page_bundle_insert_item($bundle, $draft_page_id, 0, '', $user);

    if (($written['error'] !== '') || ($written['id'] <= 0)) {
        return $fail(($written['error'] !== '') ? $written['error'] : lang('The draft could not be added to the bundle.'));
    }

    // Cleared once the draft is in: the bundle publishes it, not its time.
    $cleared = false;

    if (function_exists('pg_page_schedule_ready') && pg_page_schedule_ready()) {
        db("UPDATE page SET publish_at = 0 WHERE page_id = '$draft_page_id' AND publish_at > 0");
        $cleared = mysqli_affected_rows(db::$con) > 0;
    }

    return array('ok' => true, 'error' => '', 'item_id' => $written['id'], 'time_cleared' => $cleared);
}

/**
 * Removes a page from a bundle that is not published yet. The draft stays a
 * draft. A publish that died is finished first (pg_page_bundle_with_lock()).
 *
 * @param array $bundle  pg_page_bundle_get()
 * @param int   $item_id
 * @param array $user    the person removing it
 * @return array ok, error
 */
function pg_page_bundle_remove_item($bundle, $item_id, $user)
{
    return pg_page_bundle_with_lock($bundle, $user, pg_page_bundle_busy_answer(), function ($bundle) use ($item_id) {
        return pg_page_bundle_remove_item_locked($bundle, $item_id);
    });
}

/**
 * Removes a waiting page from a bundle, under the bundle's lock.
 *
 * @param array $bundle  pg_page_bundle_get(), read under the lock
 * @param int   $item_id
 * @return array ok, error
 */
function pg_page_bundle_remove_item_locked($bundle, $item_id)
{
    if (!pg_page_bundle_may_change($bundle['status'])) {
        return array('ok' => false, 'error' => lang('A published bundle cannot be changed.'));
    }

    db_q("DELETE FROM page_bundle_item WHERE id = ? AND bundle_id = ? AND status = 'waiting'",
        array((int) $item_id, (int) $bundle['id']));

    if (mysqli_affected_rows(db::$con) < 1) {
        return array('ok' => false, 'error' => lang('The page is not in this bundle.'));
    }

    db_q("UPDATE page_bundle SET updated_at = ? WHERE id = ?", array(time(), (int) $bundle['id']));

    return array('ok' => true, 'error' => '');
}

// ── Publishing and taking back ─────────────────────────────────────────
//
// A publish checks every page first (its lock, the page save's dry run, the
// home page, "changed since") and writes nothing while one is in the way.
// Then it writes page by page through what exists: a new page leaves the
// Drafts folder (pg_page_draft_move()), a replacement's tree, title and
// description are saved onto its live page (pg_designer_save_page(), one
// revision with the source 'bundle'). A write that fails takes back at once
// what this publish wrote, so a bundle is never left half live; only when
// that take-back fails too is the bundle partly published. A take-back
// returns a replaced page to the revision it had before, as a new publish
// with the source 'restore', while its state is still the one written, and
// a new page to Drafts. The planners are pure; the writes are callables, so
// the order and the take-back are tested without a database.

/**
 * What a publish does with each waiting page, from the checks' answers.
 * A locked page or one the save refuses stops everything; a replacement
 * whose live page changed since needs the person's choice, "replace"
 * (Replace anyway) or "leave" (Leave out). Pure.
 *
 * @param array $items   waiting page_bundle_item rows (id, live_page_id,
 *                       draft_name, live_name), in the bundle's order
 * @param array $checks  item id => locked (bool), errors (list), changed (bool)
 * @param array $choices item id => 'replace' or 'leave'
 * @return array ok, write (ids), leave (ids), ask (ids), pages (item_id,
 *               name, replacement, state: ready, locked, refused, changed,
 *               left_out; text: the save's answer for a refused page)
 */
function pg_page_bundle_publish_plan($items, $checks, $choices)
{
    $plan = array('ok' => false, 'write' => array(), 'leave' => array(), 'ask' => array(), 'pages' => array());
    $stop = false;

    foreach ((array) $items as $item) {
        $id = (int) $item['id'];
        $check = isset($checks[$id]) ? (array) $checks[$id] : array();
        $choice = isset($choices[$id]) ? (string) $choices[$id] : '';
        $replacement = (int) $item['live_page_id'] > 0;
        $page = array(
            'item_id' => $id,
            'name' => (string) ($replacement ? $item['live_name'] : $item['draft_name']),
            'replacement' => $replacement,
            'state' => 'ready',
            'text' => '',
        );

        if (!empty($check['locked'])) {
            $page['state'] = 'locked';
            $stop = true;
        } elseif (!empty($check['errors'])) {
            $page['state'] = 'refused';
            $page['text'] = implode(' ', (array) $check['errors']);
            $stop = true;
        } elseif (!empty($check['changed']) && ($choice === 'leave')) {
            $page['state'] = 'left_out';
            $plan['leave'][] = $id;
        } elseif (!empty($check['changed']) && ($choice !== 'replace')) {
            $page['state'] = 'changed';
            $plan['ask'][] = $id;
        } else {
            $plan['write'][] = $id;
        }

        $plan['pages'][] = $page;
    }

    if ($stop || $plan['ask']) {
        $plan['write'] = array();
    }

    $plan['ok'] = !$stop && !$plan['ask'] && (count($plan['write']) > 0);

    return $plan;
}

/**
 * Writes the pages in order; at the first failure takes back what this run
 * wrote, newest first. Pure: the writes are given.
 *
 * @param array    $ids   item ids, in order
 * @param callable $write function (id): array ok, error
 * @param callable $undo  function (id): bool
 * @return array outcome (published, taken_back: nothing of this run stands,
 *               partial: the take-back failed too), written (ids), failed
 *               (the id that failed, 0 for none), reason (its answer),
 *               stands (ids written that could not be taken back)
 */
function pg_page_bundle_publish_run($ids, $write, $undo)
{
    $run = array('outcome' => 'published', 'written' => array(), 'failed' => 0, 'reason' => '', 'stands' => array());

    foreach ((array) $ids as $id) {
        $id = (int) $id;
        $done = $write($id);

        if (!empty($done['ok'])) {
            $run['written'][] = $id;
            continue;
        }

        $run['failed'] = $id;
        $run['reason'] = isset($done['error']) ? (string) $done['error'] : '';

        // Every page is tried even after one fails: the more that is taken
        // back, the less stands.
        foreach (array_reverse($run['written']) as $back) {
            if (!$undo($back)) {
                $run['stands'][] = $back;
            }
        }

        $run['stands'] = array_reverse($run['stands']);
        $run['outcome'] = $run['stands'] ? 'partial' : 'taken_back';

        break;
    }

    return $run;
}

/**
 * The status a bundle moves to after a publish run, '' when it stays where
 * it was (a run taken back leaves it open, scheduled or partly published
 * as it was). Pure.
 *
 * @param string $outcome pg_page_bundle_publish_run()
 * @param string $from    the bundle's status
 * @return string
 */
function pg_page_bundle_publish_status($outcome, $from)
{
    $to = ($outcome === 'published') ? 'published' : (($outcome === 'partial') ? 'partial' : '');

    return (($to !== '') && pg_page_bundle_may_move($from, $to)) ? $to : '';
}

/**
 * What a take-back can bring back. A new page goes back to Drafts while it
 * is still on the site and not the home page; a replaced page returns to
 * its revision before while its state is the one written and that revision
 * is still kept. Pure: the probes are given.
 *
 * @param array $items  written page_bundle_item rows (id, live_page_id,
 *                      revision_before, written_state, draft_name, live_name)
 * @param array $probes item id => exists (on the site, outside the bin),
 *                      home (a new page that is the home page now), state
 *                      (a replaced page's state now), revision (the revision
 *                      before is still kept)
 * @return array can (ids), cannot (item_id, name, reason: gone, home,
 *               unfinished: a publish stopped while writing it, changed,
 *               swept)
 */
function pg_page_bundle_take_back_plan($items, $probes)
{
    $plan = array('can' => array(), 'cannot' => array());

    foreach ((array) $items as $item) {
        $id = (int) $item['id'];
        $probe = isset($probes[$id]) ? (array) $probes[$id] : array();
        $replacement = (int) $item['live_page_id'] > 0;
        $reason = '';

        if (empty($probe['exists'])) {
            $reason = 'gone';
        } elseif (!$replacement) {
            $reason = !empty($probe['home']) ? 'home' : '';
        } elseif ((string) $item['written_state'] === '') {
            $reason = 'unfinished';
        } elseif ((string) ($probe['state'] ?? '') !== (string) $item['written_state']) {
            $reason = 'changed';
        } elseif (((int) $item['revision_before'] <= 0) || empty($probe['revision'])) {
            $reason = 'swept';
        }

        if ($reason === '') {
            $plan['can'][] = $id;
        } else {
            $plan['cannot'][] = array(
                'item_id' => $id,
                'name' => (string) ($replacement ? $item['live_name'] : $item['draft_name']),
                'reason' => $reason,
            );
        }
    }

    return $plan;
}

/**
 * Whether a take-back may run: something comes back, and every page that
 * does not was shown to the person (nothing is kept silently). Pure.
 *
 * @param array $plan     pg_page_bundle_take_back_plan()
 * @param array $accepted the item ids the dialog listed as kept
 * @return bool
 */
function pg_page_bundle_take_back_allowed($plan, $accepted)
{
    if (empty($plan['can'])) {
        return false;
    }

    $accepted = array_map('intval', (array) $accepted);

    foreach ((array) $plan['cannot'] as $kept) {
        if (!in_array((int) $kept['item_id'], $accepted, true)) {
            return false;
        }
    }

    return true;
}

/**
 * Why a page cannot be taken back, for the take-back dialog.
 *
 * @param string $reason gone, home, unfinished, changed, swept
 * @return string
 */
function pg_page_bundle_take_back_reason_text($reason)
{
    switch ((string) $reason) {
        case 'gone':
            return lang('The page is no longer on the site.');
        case 'home':
            return lang('The page is the home page now, so it stays on the site.');
        case 'unfinished':
            return lang('A publish stopped while writing this page, so what it holds is not known; it keeps what it has.');
        case 'changed':
            return lang('The page was published again since; it keeps that version.');
        case 'swept':
            return lang('The version before the bundle is no longer kept in the page\'s History.');
    }

    return '';
}

/**
 * Takes back each page; one that fails stays as it is. Pure: the take-back
 * of a page is given.
 *
 * @param array    $ids
 * @param callable $undo function (id): bool
 * @return array done (ids), failed (ids)
 */
function pg_page_bundle_take_back_run($ids, $undo)
{
    $run = array('done' => array(), 'failed' => array());

    foreach ((array) $ids as $id) {
        $id = (int) $id;

        if ($undo($id)) {
            $run['done'][] = $id;
        } else {
            $run['failed'][] = $id;
        }
    }

    return $run;
}

/**
 * The bundle named beside each of a page's revisions with the source
 * 'bundle': the first such revision after the one a bundle's item kept as
 * its revision before. Pure.
 *
 * @param array $revisions revision_no, source (any order)
 * @param array $items     the page's bundle items: revision_before, bundle_name
 * @return array revision_no => bundle name
 */
function pg_page_bundle_revision_bundle_names($revisions, $items)
{
    $numbers = array();

    foreach ((array) $revisions as $revision) {
        if ((string) $revision['source'] === 'bundle') {
            $numbers[] = (int) $revision['revision_no'];
        }
    }

    sort($numbers);
    $out = array();

    foreach ((array) $items as $item) {
        $before = (int) $item['revision_before'];

        if ($before <= 0) {
            continue;
        }

        foreach ($numbers as $no) {
            if ($no > $before) {
                $out[$no] = (string) $item['bundle_name'];
                break;
            }
        }
    }

    return $out;
}

/**
 * The bundle named beside a page's bundle revisions in its History.
 *
 * @param int   $page_id
 * @param array $revisions pg_page_revisions_list() rows
 * @return array revision_no => bundle name
 */
function pg_page_bundle_revision_names($page_id, $revisions)
{
    $page_id = (int) $page_id;

    if (($page_id <= 0) || !pg_page_bundles_ready()) {
        return array();
    }

    $items = db_items(
        "SELECT page_bundle_item.revision_before, page_bundle.name AS bundle_name
        FROM page_bundle_item
        JOIN page_bundle ON page_bundle.id = page_bundle_item.bundle_id
        WHERE page_bundle_item.live_page_id = '$page_id' AND page_bundle_item.revision_before > 0");

    return pg_page_bundle_revision_bundle_names($revisions, (array) $items);
}

/**
 * A page's fields as the page save takes them, with a tree, title and
 * description put in their place; null when the page cannot be read.
 *
 * @param int    $page_id
 * @param string $tree_json
 * @param string $title
 * @param string $description
 * @return array|null style_id, fields
 */
function pg_page_bundle_save_fields($page_id, $tree_json, $title, $description)
{
    $page_id = (int) $page_id;
    $style_id = (int) db_value("SELECT page_style FROM page WHERE page_id = '$page_id' LIMIT 1");

    foreach (($style_id > 0) ? pg_designer_load_pages($style_id) : array() as $row) {
        if ((int) $row['page_id'] === $page_id) {
            // The page keeps its name, folder, flags and meta tags as stored.
            $row['tree_json'] = (string) $tree_json;
            $row['page_title'] = (string) $title;
            $row['page_meta_description'] = (string) $description;

            return array('style_id' => $style_id, 'fields' => $row);
        }
    }

    return null;
}

/**
 * A draft's tree, title and description, and whether it is still a draft
 * and the home page; null when it is gone.
 *
 * @param int $page_id
 * @return array|null
 */
function pg_page_bundle_draft_row($page_id)
{
    $page_id = (int) $page_id;

    $row = db_item(
        "SELECT page_id, page_name, page_folder, page_home, page_title, page_meta_description, page_tree_json FROM page
        WHERE page_id = '$page_id'" . pg_designer_not_binned_sql() . " LIMIT 1");

    return is_array($row) ? $row : null;
}

/**
 * The checks of one waiting page before a publish: its lock (and the live
 * page's), the save's dry run, the home page, "changed since".
 *
 * @param array $item page_bundle_item row with draft_name, live_name
 * @param array $user
 * @return array locked, errors, changed
 */
function pg_page_bundle_check_item($item, $user)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/designer_collab.php');

    $out = array('locked' => false, 'errors' => array(), 'changed' => false);
    $draft_id = (int) $item['draft_page_id'];
    $live_id = (int) $item['live_page_id'];

    if (!pg_collab_may_edit_page('', $draft_id) || (($live_id > 0) && !pg_collab_may_edit_page('', $live_id))) {
        $out['locked'] = true;

        return $out;
    }

    $draft = pg_page_bundle_draft_row($draft_id);

    if (!$draft || !pg_page_is_draft($draft['page_folder'])) {
        $out['errors'][] = lang(array('string' => 'The draft "{var:1}" is no longer in Drafts.', 'vars' => array((string) $item['draft_name'])));

        return $out;
    }

    if ($live_id <= 0) {
        // The site has one home page: a bundle never hands the flag over.
        if ((string) $draft['page_home'] === 'yes') {
            $out['errors'][] = lang(array('string' => 'The draft "{var:1}" is marked as the home page; a bundle cannot publish a new home page.', 'vars' => array((string) $draft['page_name'])));

            return $out;
        }

        // Saved as it is, without page_draft: the save keeps it a draft, so
        // the bundle's own hold does not refuse the check.
        $save = pg_page_bundle_save_fields($draft_id, $draft['page_tree_json'], $draft['page_title'], $draft['page_meta_description']);
    } else {
        if (pg_page_bundle_live_state($live_id) === '') {
            $out['errors'][] = lang(array('string' => 'The page "{var:1}" is no longer on the site.', 'vars' => array((string) $item['live_name'])));

            return $out;
        }

        $out['changed'] = pg_page_bundle_item_changed($item);
        $save = pg_page_bundle_save_fields($live_id, $draft['page_tree_json'], $draft['page_title'], $draft['page_meta_description']);
    }

    if (!$save) {
        $out['errors'][] = lang('The page could not be read.');

        return $out;
    }

    $save['fields']['revision_source'] = 'bundle';
    $dry = pg_designer_save_page($save['style_id'], $save['fields'], $user, true);

    if (empty($dry['ok'])) {
        $out['errors'] = (array) $dry['errors'];
    }

    return $out;
}

/**
 * The checks of a whole bundle before a publish, for the publish dialog
 * and the publish itself.
 *
 * @param array $bundle  pg_page_bundle_get()
 * @param array $user
 * @param array $choices item id => 'replace' or 'leave'
 * @return array error ('' when the bundle may be published), plan
 *               (pg_page_bundle_publish_plan())
 */
function pg_page_bundle_publish_check($bundle, $user, $choices = array())
{
    $none = array('ok' => false, 'write' => array(), 'leave' => array(), 'ask' => array(), 'pages' => array());

    if (!pg_page_bundle_user_may($user)) {
        return array('error' => lang('Only administrators and designers can publish a release bundle.'), 'plan' => $none);
    }

    if (!pg_page_bundle_holds($bundle['status'])) {
        return array('error' => lang('The bundle cannot make that change now.'), 'plan' => $none);
    }

    $items = array();
    $checks = array();

    foreach (pg_page_bundle_items($bundle['id']) as $item) {
        if ((string) $item['status'] === 'waiting') {
            $items[] = $item;
            $checks[(int) $item['id']] = pg_page_bundle_check_item($item, $user);
        }
    }

    $plan = pg_page_bundle_publish_plan($items, $checks, (array) $choices);
    $error = (!$plan['ok'] && !$items) ? lang('The bundle has no page to publish.') : '';

    return array('error' => $error, 'plan' => $plan);
}

/**
 * A line in the Site Log naming the bundle.
 *
 * @param string $string English text with {var:1} the page, {var:2} the bundle
 * @param string $page_name
 * @param array  $bundle
 * @param array  $user
 */
function pg_page_bundle_log($string, $page_name, $bundle, $user)
{
    log_activity(lang(array('string' => $string, 'vars' => array((string) $page_name, (string) $bundle['name']))),
        isset($user['username']) ? (string) $user['username'] : '');
}

/**
 * Writes one page of a bundle: a new page leaves Drafts; a replacement's
 * tree, title and description are saved onto its live page, one revision
 * with the source 'bundle'. The item keeps the revision before and the
 * state written. The item is claimed first (waiting to written), so two
 * publishes never write it twice; a failed write gives it back.
 *
 * @param array $item   page_bundle_item row with draft_name, live_name
 * @param array $bundle
 * @param array $user
 * @return array ok, error; when written also revision_before, written_state
 */
function pg_page_bundle_write_item($item, $bundle, $user)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/designer_collab.php');

    $id = (int) $item['id'];
    $draft_id = (int) $item['draft_page_id'];
    $live_id = (int) $item['live_page_id'];

    db_q("UPDATE page_bundle_item SET status = 'written', written_at = ? WHERE id = ? AND status = 'waiting'", array(time(), $id));

    if (mysqli_affected_rows(db::$con) < 1) {
        return array('ok' => false, 'error' => lang('The bundle was changed meanwhile; look again.'));
    }

    $give_back = function ($error) use ($id) {
        db_q("UPDATE page_bundle_item SET status = 'waiting', written_at = 0 WHERE id = ?", array($id));

        return array('ok' => false, 'error' => $error);
    };

    // A database error inside the page save (output_error() throws while a
    // publish runs) is a failed write: the item is given back, so the run
    // takes back what it wrote and the bundle can be published again.
    try {
        $busy = lang('Somebody has this page open in the Page Design Studio. Try again when they are done.');
        $draft = pg_page_bundle_draft_row($draft_id);

        if (!$draft) {
            return $give_back(lang(array('string' => 'The draft "{var:1}" is no longer in Drafts.', 'vars' => array((string) $item['draft_name']))));
        }

        if ($live_id <= 0) {
            if (!pg_collab_may_edit_page('', $draft_id)) {
                return $give_back($busy);
            }

            if (!pg_page_draft_move($draft_id, false, 0, (int) $user['id'])) {
                return $give_back(lang(array('string' => 'The draft "{var:1}" could not be put on the site.', 'vars' => array((string) $draft['page_name']))));
            }

            $state = pg_page_bundle_state_hash($draft['page_tree_json'], $draft['page_title'], $draft['page_meta_description']);
            db_q("UPDATE page_bundle_item SET revision_before = 0, written_state = ? WHERE id = ?", array($state, $id));
            pg_page_bundle_log('page ({var:1}) was published with the release bundle ({var:2})', $draft['page_name'], $bundle, $user);

            return array('ok' => true, 'error' => '', 'revision_before' => 0, 'written_state' => $state);
        }

        if (!pg_collab_may_edit_page('', $live_id)) {
            return $give_back($busy);
        }

        $save = pg_page_bundle_save_fields($live_id, $draft['page_tree_json'], $draft['page_title'], $draft['page_meta_description']);

        if (!$save) {
            return $give_back(lang('The page could not be read.'));
        }

        $latest = pg_page_revisions_ready() ? (int) db_value("SELECT MAX(revision_no) FROM page_revisions WHERE page_id = '$live_id'") : 0;

        $save['fields']['revision_source'] = 'bundle';
        $saved = pg_designer_save_page($save['style_id'], $save['fields'], $user);

        if (empty($saved['ok'])) {
            return $give_back(implode(' ', (array) $saved['errors']));
        }

        // A save that changed nothing left the page as it was, so a page
        // with no revision at all is seeded now: that seed is the page before.
        $written_no = isset($saved['revision_no']) ? (int) $saved['revision_no'] : 0;
        $before = pg_page_bundle_revision_before($written_no, $latest, function () use ($live_id) {
            return function_exists('pg_page_revision_seed') ? (int) pg_page_revision_seed($live_id) : 0;
        });

        $state = pg_page_bundle_live_state($live_id);
        db_q("UPDATE page_bundle_item SET revision_before = ?, written_state = ? WHERE id = ?", array($before, $state, $id));
        pg_page_bundle_log('page ({var:1}) was replaced by the release bundle ({var:2})', $item['live_name'], $bundle, $user);

        return array('ok' => true, 'error' => '', 'revision_before' => $before, 'written_state' => $state);
    } catch (Throwable $t) {
        $error = trim($t->getMessage());

        return $give_back(($error !== '') ? $error : lang('The page could not be read.'));
    }
}

/**
 * The revision a replaced page goes back to: the one before the revision its
 * write made (a first save seeds revision 1 and writes 2); when the save
 * wrote none, the page's latest; when the page has none either, the page as
 * it is, seeded by $seed. 0 when nothing can be kept. Pure.
 *
 * @param int      $written_no the revision the save wrote, 0 for none
 * @param int      $latest     the page's latest revision before the save
 * @param callable $seed       seeds the page's revision 1; returns 1, or 0
 * @return int
 */
function pg_page_bundle_revision_before($written_no, $latest, $seed)
{
    if ((int) $written_no > 0) {
        return (int) $written_no - 1;
    }

    if ((int) $latest > 0) {
        return (int) $latest;
    }

    return ((int) $seed() > 0) ? 1 : 0;
}

/**
 * The item row as its write left it: a successful write's revision before
 * and state written, which it stored on the item, so a take-back in the same
 * publish reads the revision to go back to.
 *
 * @param array $item page_bundle_item row
 * @param array $done pg_page_bundle_write_item()
 * @return array
 */
function pg_page_bundle_item_written($item, $done)
{
    if (empty($done['ok'])) {
        return $item;
    }

    foreach (array('revision_before', 'written_state') as $key) {
        if (array_key_exists($key, (array) $done)) {
            $item[$key] = $done[$key];
        }
    }

    return $item;
}

/**
 * Runs $action with output_error() throwing instead of ending the request,
 * then gives the setting back, also when $action throws.
 *
 * @param callable $action
 * @return mixed what $action returns
 */
function pg_page_bundle_with_throwing_errors($action)
{
    $saved = pg_error_throws();
    pg_error_throws(true);

    try {
        return $action();
    } finally {
        pg_error_throws($saved);
    }
}

/**
 * The write and take-back of one publish run, by item id, over one shared
 * copy of the item rows: a take-back reads the row its write left, with the
 * revision before that the write stored. A write or take-back that throws
 * (a database error) is one that failed.
 *
 * @param array    $items      item id => page_bundle_item row
 * @param callable $write_item row => pg_page_bundle_write_item()
 * @param callable $undo_item  row => bool
 * @return array the write and the take-back for pg_page_bundle_publish_run()
 */
function pg_page_bundle_publish_ops($items, $write_item, $undo_item)
{
    $write = function ($id) use (&$items, $write_item) {
        try {
            $done = $write_item($items[$id]);
        } catch (Throwable $t) {
            return array('ok' => false, 'error' => trim($t->getMessage()));
        }

        $items[$id] = pg_page_bundle_item_written($items[$id], $done);

        return $done;
    };
    $undo = function ($id) use (&$items, $undo_item) {
        try {
            return (bool) $undo_item($items[$id]);
        } catch (Throwable $t) {
            return false;
        }
    };

    return array($write, $undo);
}

/**
 * Takes one written page back: a new page goes back to Drafts; a replaced
 * page gets the tree, title and description of its revision before, saved
 * as a new publish with the source 'restore'. After a failed publish the
 * item waits again; after a take-back it is taken back.
 *
 * @param array  $item   page_bundle_item row with draft_name, live_name
 * @param array  $bundle
 * @param array  $user
 * @param string $to     'waiting' (a failed publish) or 'reverted'
 * @return bool
 */
function pg_page_bundle_undo_item($item, $bundle, $user, $to)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/designer_collab.php');

    $id = (int) $item['id'];
    $draft_id = (int) $item['draft_page_id'];
    $live_id = (int) $item['live_page_id'];

    if ($live_id <= 0) {
        $page = pg_page_bundle_draft_row($draft_id);

        if (!$page || ((string) $page['page_home'] === 'yes') || !pg_collab_may_edit_page('', $draft_id)) {
            return false;
        }

        if (!pg_page_is_draft($page['page_folder']) && !pg_page_draft_move($draft_id, true, (int) $page['page_folder'], (int) $user['id'])) {
            return false;
        }

        $name = $page['page_name'];
    } else {
        $revision = function_exists('pg_page_revision_get') ? pg_page_revision_get($live_id, (int) $item['revision_before']) : null;

        if (!$revision || !pg_collab_may_edit_page('', $live_id)) {
            return false;
        }

        $settings = (array) $revision['settings'];
        $save = pg_page_bundle_save_fields($live_id, $revision['tree_json'],
            isset($settings['page_title']) ? $settings['page_title'] : '',
            isset($settings['page_meta_description']) ? $settings['page_meta_description'] : '');

        if (!$save) {
            return false;
        }

        $save['fields']['revision_source'] = 'restore';
        $saved = pg_designer_save_page($save['style_id'], $save['fields'], $user);

        if (empty($saved['ok'])) {
            return false;
        }

        $name = $item['live_name'];
    }

    if ($to === 'waiting') {
        db_q("UPDATE page_bundle_item SET status = 'waiting', revision_before = 0, written_state = '', written_at = 0 WHERE id = ?", array($id));
    } else {
        db_q("UPDATE page_bundle_item SET status = 'reverted' WHERE id = ?", array($id));
    }

    pg_page_bundle_log('page ({var:1}) was taken back from the release bundle ({var:2})', $name, $bundle, $user);

    return true;
}

/**
 * Publishes a bundle: the checks, then the writes, then the used
 * replacements to the Recycle Bin. A failure takes back what was written
 * and names the page on the bundle; only when that take-back fails is the
 * bundle partly published.
 *
 * The whole publish holds the bundle's named lock, so a publish that dies
 * is told from a running one (pg_page_bundle_with_lock()).
 *
 * @param array $bundle  pg_page_bundle_get()
 * @param array $user
 * @param array $choices item id => 'replace' or 'leave', for pages changed since
 * @return array ok, error, outcome ('' when nothing was tried), check, run,
 *               busy (another publish of the bundle is running)
 */
function pg_page_bundle_publish($bundle, $user, $choices = array())
{
    $none = array('ok' => false, 'write' => array(), 'leave' => array(), 'ask' => array(), 'pages' => array());
    $busy = pg_page_bundle_busy_answer();
    $busy += array('outcome' => '', 'check' => array('error' => $busy['error'], 'plan' => $none), 'run' => null);

    return pg_page_bundle_with_lock($bundle, $user, $busy, function ($bundle) use ($user, $choices) {
        return pg_page_bundle_publish_locked($bundle, $user, $choices);
    });
}

/**
 * Publishes a bundle under its lock (pg_page_bundle_publish()).
 *
 * @param array $bundle  pg_page_bundle_get(), read under the lock
 * @param array $user
 * @param array $choices
 * @return array as pg_page_bundle_publish()
 */
function pg_page_bundle_publish_locked($bundle, $user, $choices = array())
{
    $check = pg_page_bundle_publish_check($bundle, $user, $choices);
    $out = array('ok' => false, 'error' => $check['error'], 'outcome' => '', 'check' => $check, 'run' => null);

    if (!$check['plan']['ok']) {
        if ($out['error'] === '') {
            $out['error'] = lang('Some pages are in the way; nothing was published.');
        }

        return $out;
    }

    $items = array();

    foreach (pg_page_bundle_items($bundle['id']) as $item) {
        $items[(int) $item['id']] = $item;
    }

    pg_page_bundle_clear_failure($bundle);

    list($write, $undo) = pg_page_bundle_publish_ops($items,
        function ($item) use ($bundle, $user) {
            return pg_page_bundle_write_item($item, $bundle, $user);
        },
        function ($item) use ($bundle, $user) {
            return pg_page_bundle_undo_item($item, $bundle, $user, 'waiting');
        });

    // A failed query inside a page save would otherwise end the request
    // part-way, with a page written, an item claimed and nothing taken back.
    $run = pg_page_bundle_with_throwing_errors(function () use ($check, $write, $undo) {
        return pg_page_bundle_publish_run($check['plan']['write'], $write, $undo);
    });
    $out['run'] = $run;
    $out['outcome'] = $run['outcome'];

    $failed_page = 0;

    if ($run['failed'] > 0) {
        $failed = $items[$run['failed']];
        $failed_page = ((int) $failed['live_page_id'] > 0) ? (int) $failed['live_page_id'] : (int) $failed['draft_page_id'];
    }

    $to = pg_page_bundle_publish_status($run['outcome'], $bundle['status']);

    if ($to === 'published') {
        $moved = pg_page_bundle_move($bundle, 'published', array('published_by' => (int) $user['id'], 'published_at' => time()));

        foreach ($check['plan']['leave'] as $id) {
            db_q("UPDATE page_bundle_item SET status = 'left_out' WHERE id = ? AND status = 'waiting'", array((int) $id));
        }

        // The used replacements: their live pages carry them now.
        pg_page_bundle_load_recycle();

        foreach ($run['written'] as $id) {
            if ((int) $items[$id]['live_page_id'] > 0) {
                pg_recycle_page((int) $items[$id]['draft_page_id'], $user);
            }
        }

        log_activity(lang(array('string' => 'release bundle ({var:1}) was published', 'vars' => array((string) $bundle['name']))),
            isset($user['username']) ? (string) $user['username'] : '');

        $out['ok'] = true;
        $out['error'] = $moved['ok'] ? '' : $moved['error'];

        return $out;
    }

    if ($to === 'partial') {
        pg_page_bundle_move($bundle, 'partial', array('failed_page_id' => $failed_page, 'failed_reason' => $run['reason']));
        $out['error'] = lang('A page could not be published and what was written could not all be taken back; the bundle is partly published.');

        return $out;
    }

    db_q("UPDATE page_bundle SET failed_page_id = ?, failed_reason = ?, updated_at = ? WHERE id = ? AND status = ?",
        array($failed_page, mb_substr((string) $run['reason'], 0, 500, 'UTF-8'), time(), (int) $bundle['id'], (string) $bundle['status']));
    $out['error'] = lang('A page could not be published, so what was written was taken back. The bundle stays as it was.');

    return $out;
}

/**
 * What a take-back of a bundle can bring back, for the take-back dialog
 * and the take-back itself.
 *
 * @param array $bundle pg_page_bundle_get()
 * @return array error ('' when it may be taken back), plan
 *               (pg_page_bundle_take_back_plan()), items (id => row)
 */
function pg_page_bundle_take_back_check($bundle)
{
    $none = array('can' => array(), 'cannot' => array());

    if (!pg_page_bundle_may_move($bundle['status'], 'reverted')) {
        return array('error' => lang('Only a published bundle can be taken back.'), 'plan' => $none, 'items' => array());
    }

    $items = array();
    $probes = array();

    foreach (pg_page_bundle_items($bundle['id']) as $item) {
        if ((string) $item['status'] !== 'written') {
            continue;
        }

        $id = (int) $item['id'];
        $items[$id] = $item;

        if ((int) $item['live_page_id'] <= 0) {
            $page = pg_page_bundle_draft_row($item['draft_page_id']);
            $probes[$id] = array(
                'exists' => $page && !pg_page_is_draft($page['page_folder']),
                'home' => $page && ((string) $page['page_home'] === 'yes'),
            );
        } else {
            $state = pg_page_bundle_live_state($item['live_page_id']);
            $probes[$id] = array(
                'exists' => $state !== '',
                'state' => $state,
                'revision' => function_exists('pg_page_revision_get')
                    && (pg_page_revision_get($item['live_page_id'], (int) $item['revision_before']) !== null),
            );
        }
    }

    return array('error' => '', 'plan' => pg_page_bundle_take_back_plan(array_values($items), $probes), 'items' => $items);
}

/**
 * Takes a bundle back: each page that can come back comes back whole; the
 * pages that cannot must have been shown to the person ($accepted). When
 * every page came back the bundle is taken back; a page that failed leaves
 * it as it was, to be tried again.
 *
 * @param array $bundle   pg_page_bundle_get()
 * @param array $user
 * @param array $accepted item ids the dialog listed as kept
 * @return array ok, error, check, run
 */
function pg_page_bundle_take_back($bundle, $user, $accepted = array())
{
    $out = array('ok' => false, 'error' => '', 'check' => null, 'run' => null);

    if (!pg_page_bundle_user_may($user)) {
        $out['error'] = lang('Only administrators and designers can take back a release bundle.');

        return $out;
    }

    return pg_page_bundle_with_lock($bundle, $user, pg_page_bundle_busy_answer() + $out, function ($bundle) use ($user, $accepted, $out) {
        return pg_page_bundle_take_back_locked($bundle, $user, $accepted, $out);
    });
}

/**
 * Takes a bundle back under its lock (pg_page_bundle_take_back()).
 *
 * @param array $bundle   pg_page_bundle_get(), read under the lock
 * @param array $user
 * @param array $accepted
 * @param array $out      the answer's empty shape
 * @return array as pg_page_bundle_take_back()
 */
function pg_page_bundle_take_back_locked($bundle, $user, $accepted, $out)
{
    $check = pg_page_bundle_take_back_check($bundle);
    $out['check'] = $check;

    if ($check['error'] !== '') {
        $out['error'] = $check['error'];

        return $out;
    }

    if (!pg_page_bundle_take_back_allowed($check['plan'], $accepted)) {
        $out['error'] = empty($check['plan']['can'])
            ? lang('No page of this bundle can be taken back.')
            : lang('Some pages cannot be taken back; look at the list again.');

        return $out;
    }

    $items = $check['items'];
    $undo = function ($id) use ($items, $bundle, $user) {
        return pg_page_bundle_undo_item($items[$id], $bundle, $user, 'reverted');
    };

    $run = pg_page_bundle_take_back_run($check['plan']['can'], $undo);
    $out['run'] = $run;

    if ($run['failed']) {
        $out['error'] = lang('Some pages could not be taken back; the bundle stays published. Try again.');

        return $out;
    }

    foreach ($check['plan']['cannot'] as $kept) {
        db_q("UPDATE page_bundle_item SET status = 'kept' WHERE id = ? AND status = 'written'", array((int) $kept['item_id']));
    }

    $moved = pg_page_bundle_move($bundle, 'reverted', array('reverted_by' => (int) $user['id'], 'reverted_at' => time()));

    log_activity(lang(array('string' => 'release bundle ({var:1}) was taken back', 'vars' => array((string) $bundle['name']))),
        isset($user['username']) ? (string) $user['username'] : '');

    $out['ok'] = $moved['ok'];
    $out['error'] = $moved['ok'] ? '' : $moved['error'];

    return $out;
}

// ── A publish that died ────────────────────────────────────────────────
//
// A fatal error, a timeout or a lost connection cannot be caught: the run
// stops where it is, with pages written and the next item claimed 'written'
// with no written_state. The publish holds the bundle's named lock while it
// runs, and MySQL frees a named lock when its connection ends, so a free
// lock on a bundle with written items means its publish died. The general
// job's pass and the start of every action on the bundle (Publish, Take
// back, Remove, Delete) finish such a run as the failure it was, each only
// when it gets the lock without waiting; a plain read of the screen writes
// nothing.

/**
 * The name of a bundle's lock. GET_LOCK names belong to the whole server,
 * so the database is part of the name; MySQL allows 64 characters.
 *
 * @param int $bundle_id
 * @return string
 */
function pg_page_bundle_lock_name($bundle_id)
{
    $database = defined('DB_DATABASE') ? DB_DATABASE . '@' . (defined('DB_HOST') ? DB_HOST : '') : '';

    return 'pg_page_bundle_' . md5($database) . '_' . (int) $bundle_id;
}

/**
 * Takes a bundle's lock without waiting: false while a publish (or another
 * action) of the bundle is running.
 *
 * @param int $bundle_id
 * @return bool
 */
function pg_page_bundle_lock($bundle_id)
{
    return (int) db_value("SELECT GET_LOCK('" . e(pg_page_bundle_lock_name($bundle_id)) . "', 0)") === 1;
}

/**
 * Gives a bundle's lock back.
 *
 * @param int $bundle_id
 */
function pg_page_bundle_unlock($bundle_id)
{
    db_value("SELECT RELEASE_LOCK('" . e(pg_page_bundle_lock_name($bundle_id)) . "')");
}

/**
 * The answer of an action refused because the bundle is busy.
 *
 * @return array ok, error, busy
 */
function pg_page_bundle_busy_answer()
{
    return array('ok' => false, 'error' => lang('The bundle is being published right now. Try again in a moment.'), 'busy' => true);
}

/**
 * Runs an action on a bundle under its lock: takes the lock without waiting
 * (answers $busy when it is held), finishes a publish that died, and gives
 * $action the bundle as it is then. The lock is given back whatever happens.
 *
 * @param array    $bundle pg_page_bundle_get()
 * @param array    $user   the person acting, who takes a dead publish back
 * @param array    $busy   the answer while the lock is held
 * @param callable $action function (bundle): array
 * @return array
 */
function pg_page_bundle_with_lock($bundle, $user, $busy, $action)
{
    $id = (int) $bundle['id'];

    if (!pg_page_bundle_lock($id)) {
        return $busy;
    }

    try {
        $fresh = pg_page_bundle_finish_dead($bundle, $user);

        if (!$fresh) {
            return array('ok' => false, 'error' => lang('The bundle was not found.')) + $busy;
        }

        return $action($fresh);
    } finally {
        pg_page_bundle_unlock($id);
    }
}

/**
 * What finishing a dead publish does. In an open or scheduled bundle every
 * written item is looked at; in a partly published one only a claim with no
 * written_state (the written ones are what stands). A claim with no
 * written_state whose page is as it was is given back; one whose page is
 * not stays as it is (unfinished); the rest are taken back, newest first.
 * Pure.
 *
 * @param string $status the bundle's status
 * @param array  $items  page_bundle_item rows (id, status, written_state,
 *                       written_at)
 * @param array  $probes item id => as_was (a claim's page is as it was: a
 *                       replaced page's state equals live_state, a new page
 *                       is still in Drafts)
 * @return array give_back (ids), take_back (ids, newest first), unfinished
 *               (ids), failed (the claim's item id, 0 for none)
 */
function pg_page_bundle_dead_plan($status, $items, $probes)
{
    $plan = array('give_back' => array(), 'take_back' => array(), 'unfinished' => array(), 'failed' => 0);
    $status = (string) $status;

    if (!in_array($status, array('open', 'scheduled', 'partial'), true)) {
        return $plan;
    }

    $written = array();

    foreach ((array) $items as $item) {
        if ((string) $item['status'] !== 'written') {
            continue;
        }

        $id = (int) $item['id'];

        if ((string) $item['written_state'] === '') {
            if (!empty($probes[$id]['as_was'])) {
                $plan['give_back'][] = $id;
            } else {
                $plan['unfinished'][] = $id;
            }

            $plan['failed'] = $id;
        } elseif ($status !== 'partial') {
            $written[] = array((int) $item['written_at'], $id);
        }
    }

    rsort($written);

    foreach ($written as $row) {
        $plan['take_back'][] = $row[1];
    }

    return $plan;
}

/**
 * Whether a dead publish's plan has anything to finish. Pure.
 *
 * @param array $plan pg_page_bundle_dead_plan()
 * @return bool
 */
function pg_page_bundle_dead_plan_has_work($plan)
{
    return (bool) ($plan['give_back'] || $plan['take_back'] || $plan['unfinished']);
}

/**
 * Finishes a dead publish by its plan: gives the claims back, then takes
 * the written pages back, newest first; every page is tried. Pure: the
 * writes are given.
 *
 * @param array    $plan      pg_page_bundle_dead_plan()
 * @param callable $give_back function (id): bool
 * @param callable $undo      function (id): bool
 * @return array outcome ('' nothing to do, taken_back, partial), stands
 *               (ids left as they are)
 */
function pg_page_bundle_dead_run($plan, $give_back, $undo)
{
    $run = array('outcome' => '', 'stands' => array());

    if (!pg_page_bundle_dead_plan_has_work($plan)) {
        return $run;
    }

    foreach ($plan['give_back'] as $id) {
        if (!$give_back((int) $id)) {
            $run['stands'][] = (int) $id;
        }
    }

    foreach ($plan['take_back'] as $id) {
        if (!$undo((int) $id)) {
            $run['stands'][] = (int) $id;
        }
    }

    foreach ($plan['unfinished'] as $id) {
        $run['stands'][] = (int) $id;
    }

    $run['outcome'] = $run['stands'] ? 'partial' : 'taken_back';

    return $run;
}

/**
 * Finishes a publish of the bundle that died, as the failure it was (B9).
 * The caller holds the bundle's lock. The bundle keeps its status with the
 * failed page and a reason; a page that cannot be given or taken back moves
 * it to partly published. A scheduled bundle whose time the job had taken
 * goes back to open and tells its people, as a timed publish that failed.
 *
 * @param array $bundle   pg_page_bundle_get()
 * @param array $user     who takes the pages back
 * @param bool  $finished set to true when something was finished
 * @return array|null the bundle as it is now, null when it is gone
 */
function pg_page_bundle_finish_dead($bundle, $user, &$finished = null)
{
    $finished = false;
    $fresh = pg_page_bundle_get((int) $bundle['id']);

    if (!$fresh) {
        return null;
    }

    $rows = array();
    $probes = array();

    foreach (pg_page_bundle_items($fresh['id']) as $item) {
        $id = (int) $item['id'];
        $rows[$id] = $item;

        if (((string) $item['status'] !== 'written') || ((string) $item['written_state'] !== '')) {
            continue;
        }

        if ((int) $item['live_page_id'] > 0) {
            $state = pg_page_bundle_live_state($item['live_page_id']);
            $probes[$id] = array('as_was' => ($state !== '') && ($state === (string) $item['live_state']));
        } else {
            $page = pg_page_bundle_draft_row($item['draft_page_id']);
            $probes[$id] = array('as_was' => $page && pg_page_is_draft($page['page_folder']));
        }
    }

    $plan = pg_page_bundle_dead_plan($fresh['status'], array_values($rows), $probes);

    if (!pg_page_bundle_dead_plan_has_work($plan)) {
        return $fresh;
    }

    list(, $undo) = pg_page_bundle_publish_ops($rows,
        function ($item) {
            return array('ok' => false, 'error' => '');
        },
        function ($item) use ($fresh, $user) {
            return pg_page_bundle_undo_item($item, $fresh, $user, 'waiting');
        });
    $give_back = function ($id) {
        try {
            db_q("UPDATE page_bundle_item SET status = 'waiting', written_at = 0 WHERE id = ? AND status = 'written' AND written_state = ''", array((int) $id));
        } catch (Throwable $t) {
            return false;
        }

        return mysqli_affected_rows(db::$con) > 0;
    };

    $run = pg_page_bundle_with_throwing_errors(function () use ($plan, $give_back, $undo) {
        return pg_page_bundle_dead_run($plan, $give_back, $undo);
    });

    // Nothing was written: a partly published bundle whose claim is still
    // not as it was stays as it is.
    if (!$plan['give_back'] && !$plan['take_back'] && ($fresh['status'] === 'partial')) {
        return $fresh;
    }

    $failed_page = 0;

    if ($plan['failed'] > 0) {
        $failed = $rows[$plan['failed']];
        $failed_page = ((int) $failed['live_page_id'] > 0) ? (int) $failed['live_page_id'] : (int) $failed['draft_page_id'];
    }

    $partial = ($run['outcome'] === 'partial');

    // A partly published bundle still has pages that stand, so its reason
    // never says that everything was taken back.
    $reason = ($partial || ($fresh['status'] === 'partial'))
        ? lang('A publish stopped before it finished (an error, a timeout or a lost connection), and not everything it wrote could be taken back.')
        : lang('A publish stopped before it finished (an error, a timeout or a lost connection), so what it wrote was taken back.');
    $set = array('failed_page_id' => $failed_page, 'failed_reason' => $reason);
    $timed = ($fresh['status'] === 'scheduled') && ((int) $fresh['publish_at'] === 0);

    if ($partial && ($fresh['status'] !== 'partial')) {
        pg_page_bundle_move($fresh, 'partial', $set);
    } elseif (!$partial && $timed) {
        pg_page_bundle_move($fresh, 'open', $set + array('publish_at' => 0, 'scheduled_by' => 0));
    } else {
        db_q("UPDATE page_bundle SET failed_page_id = ?, failed_reason = ?, updated_at = ? WHERE id = ? AND status = ?",
            array($failed_page, mb_substr($reason, 0, 500, 'UTF-8'), time(), (int) $fresh['id'], (string) $fresh['status']));
    }

    if ($timed) {
        pg_page_bundle_bell($fresh);
    }

    $finished = true;

    log_activity(lang(array('string' => 'release bundle ({var:1}): a publish that stopped was finished', 'vars' => array((string) $fresh['name']))),
        isset($user['username']) ? (string) $user['username'] : '');

    return pg_page_bundle_get((int) $fresh['id']);
}

/**
 * Runs $action with the Site Log's name set to $person's, as the page save
 * logs through the session, then gives the old name back.
 *
 * @param array    $person pg_page_bundle_time_person()
 * @param callable $action
 * @return mixed what $action returns
 */
function pg_page_bundle_as_person($person, $action)
{
    $had_name = isset($_SESSION) && array_key_exists('sessionusername', $_SESSION);
    $old_name = $had_name ? $_SESSION['sessionusername'] : null;
    $_SESSION['sessionusername'] = $person['username'];

    try {
        return $action();
    } finally {
        if ($had_name) {
            $_SESSION['sessionusername'] = $old_name;
        } else {
            unset($_SESSION['sessionusername']);
        }
    }
}

/**
 * The general job's part of finishing dead publishes: open, scheduled and
 * partly published bundles with a written item to look at, a few a tick,
 * each only when its lock is free. The pages are taken back in the name of
 * who set the time, else who made the bundle, while they may still publish
 * bundles; otherwise the next person's action finishes it.
 *
 * @param int $limit bundles a tick
 * @return array names of the bundles finished
 */
function pg_page_bundle_job_finish_dead($limit = 5)
{
    $out = array();
    $bundles = (array) db_items("SELECT page_bundle.* FROM page_bundle
        WHERE page_bundle.status IN ('open', 'scheduled', 'partial')
        AND EXISTS (SELECT 1 FROM page_bundle_item WHERE page_bundle_item.bundle_id = page_bundle.id
            AND page_bundle_item.status = 'written'
            AND (page_bundle.status <> 'partial' OR page_bundle_item.written_state = ''))
        ORDER BY page_bundle.id LIMIT " . max(1, (int) $limit));

    foreach ($bundles as $bundle) {
        $person = null;

        foreach (array('scheduled_by', 'created_by') as $column) {
            $person = $person ?: pg_page_bundle_time_person(db_item((string) pg_db_bind(
                "SELECT user_id, user_role, user_username FROM user WHERE user_id = ? LIMIT 1", array((int) $bundle[$column]), 'escape')));
        }

        if (!$person || !pg_page_bundle_lock((int) $bundle['id'])) {
            continue;
        }

        try {
            $finished = false;
            pg_page_bundle_as_person($person, function () use ($bundle, $person, &$finished) {
                return pg_page_bundle_finish_dead($bundle, $person, $finished);
            });

            if ($finished) {
                $out[] = (string) $bundle['name'];
            }
        } finally {
            pg_page_bundle_unlock((int) $bundle['id']);
        }
    }

    return $out;
}

/**
 * The publish dialog of a bundle: every waiting page with its check, a
 * choice of "Replace anyway" or "Leave out" for a page changed since, and
 * Publish, disabled while a page is locked or refused. The form posts
 * action=publish, bundle_id and choice[item id] to $action_url; the screen
 * opens it with pgUI.dialog.
 *
 * @param array  $bundle     pg_page_bundle_get()
 * @param array  $check      pg_page_bundle_publish_check() (with no choices)
 * @param string $action_url
 * @return string
 */
function pg_page_bundle_publish_dialog($bundle, $check, $action_url)
{
    $plan = $check['plan'];
    $stop = false;
    $rows = '';

    $states = array(
        'ready' => array('pg-tone-teal', lang('Ready')),
        'locked' => array('pg-tone-amber', lang('Open in the editor')),
        'refused' => array('pg-tone-rose', lang('Cannot be saved')),
        'changed' => array('pg-tone-amber', lang('Changed since')),
        'left_out' => array('pg-tone-gray', lang('Left out')),
    );

    foreach ($plan['pages'] as $page) {
        $id = (int) $page['item_id'];
        $state = isset($states[$page['state']]) ? $states[$page['state']] : $states['ready'];
        $stop = $stop || in_array($page['state'], array('locked', 'refused'), true);

        if ($page['state'] === 'locked') {
            $text = lang('Somebody has this page open in the Page Design Studio. Publish when they are done.');
        } elseif ($page['state'] === 'refused') {
            $text = $page['text'];
        } elseif ($page['state'] === 'changed') {
            $text = lang('The live page was published again after the copy was made. Replace it, or leave this page out.');
        } else {
            $text = $page['replacement'] ? lang('Replaces the live page; the draft goes to the Recycle Bin.') : lang('Goes on the site as a new page.');
        }

        $choice = '';

        if ($page['state'] === 'changed') {
            $replace = 'page_bundle_choice_' . $id . '_replace';
            $leave = 'page_bundle_choice_' . $id . '_leave';
            $choice = '<div class="btn-group btn-group-sm mt-2" role="group" data-pg-bundle-choice aria-label="' . h(lang(array('string' => 'What to do with {var:1}', 'vars' => array($page['name'])))) . '">'
                . '<input type="radio" class="btn-check" name="choice[' . $id . ']" id="' . $replace . '" value="replace">'
                . '<label class="btn btn-outline theme-secondary" for="' . $replace . '"><i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>' . h(lang('Replace anyway')) . '</label>'
                . '<input type="radio" class="btn-check" name="choice[' . $id . ']" id="' . $leave . '" value="leave">'
                . '<label class="btn btn-outline theme-secondary" for="' . $leave . '"><i class="bi bi-dash-circle me-1" aria-hidden="true"></i>' . h(lang('Leave out')) . '</label>'
                . '</div>';
        }

        $rows .= '<div class="pg-list-row is-read">'
            . '<span class="pg-icon-tile" aria-hidden="true"><i class="bi ' . ($page['replacement'] ? 'bi-arrow-left-right' : 'bi-file-earmark-plus') . '"></i></span>'
            . '<div class="pg-list-row-main">'
            . '<div class="pg-list-row-head"><span class="pg-list-row-title text-break">' . h($page['name']) . '</span>'
            . '<span class="pg-pill ' . $state[0] . '">' . h($state[1]) . '</span></div>'
            . '<div class="pg-text-2">' . h($text) . '</div>'
            . $choice
            . '</div></div>';
    }

    $note = $stop
        ? '<p class="pg-text-2 mb-4">' . h(lang('Nothing is published while a page is in the way. Close the page in the editor, or fix it, and open this again.')) . '</p>'
        : '<p class="pg-text-2 mb-4">' . h(lang('Every page goes live at one moment. If one cannot be written, what was written is taken back and the bundle stays as it is.')) . '</p>';

    return '<dialog class="dialog pg-dialog-centered" id="page_bundle_publish_dialog" aria-labelledby="page_bundle_publish_title">'
        . '<form method="post" action="' . h($action_url) . '" class="d-contents">'
        . '<div class="dialog-header"><h5 class="dialog-title" id="page_bundle_publish_title">'
        . h(lang(array('string' => 'Publish "{var:1}"', 'vars' => array((string) $bundle['name'])))) . '</h5>'
        . '<button type="button" class="btn-close" data-bs-dismiss="dialog" aria-label="' . h(lang('Close')) . '"></button></div>'
        . '<div class="dialog-body">' . get_token_field()
        . '<input type="hidden" name="action" value="publish"><input type="hidden" name="bundle_id" value="' . (int) $bundle['id'] . '">'
        . $note . $rows . '</div>'
        . '<div class="dialog-footer">'
        . '<button type="button" class="pg-button pg-button-quiet" data-bs-dismiss="dialog" autofocus>' . h(lang('Cancel')) . '</button>'
        . '<button type="submit" class="pg-button pg-button-fill" data-pg-bundle-publish' . (($stop || $plan['ask']) ? ' disabled' : '') . '><i class="bi bi-send me-1" aria-hidden="true"></i>' . h(lang('Publish')) . '</button>'
        . '</div></form></dialog>'
        . ($stop ? '' : pg_page_bundle_publish_dialog_script());
}

/**
 * The publish dialog's script, printed once a page: Publish stays disabled
 * until each "changed since" row has its choice. Listened for on the
 * document, so a dialog drawn into the page later is served too.
 *
 * @return string
 */
function pg_page_bundle_publish_dialog_script()
{
    static $printed = false;

    if ($printed) {
        return '';
    }

    $printed = true;

    return '<script>
document.addEventListener("change", function (event) {
    var dialog = event.target.closest ? event.target.closest("#page_bundle_publish_dialog") : null;
    if (!dialog || !event.target.matches("[data-pg-bundle-choice] input")) return;
    var open = Array.prototype.some.call(dialog.querySelectorAll("[data-pg-bundle-choice]"), function (group) {
        return !group.querySelector("input:checked");
    });
    var button = dialog.querySelector("[data-pg-bundle-publish]");
    if (button) button.disabled = open;
});
</script>';
}

/**
 * The take-back dialog of a bundle: the pages that come back and how, the
 * pages that cannot with the reason, and Take back. The pages kept are
 * posted as kept[] so the take-back knows the person saw them. The form
 * posts action=take_back and bundle_id to $action_url.
 *
 * @param array  $bundle     pg_page_bundle_get()
 * @param array  $check      pg_page_bundle_take_back_check()
 * @param string $action_url
 * @return string
 */
function pg_page_bundle_take_back_dialog($bundle, $check, $action_url)
{
    $rows = '';
    $kept = '';

    foreach ($check['plan']['can'] as $id) {
        $item = $check['items'][$id];
        $replacement = (int) $item['live_page_id'] > 0;

        $rows .= '<div class="pg-list-row is-read">'
            . '<span class="pg-icon-tile" aria-hidden="true"><i class="bi ' . ($replacement ? 'bi-clock-history' : 'bi-file-earmark-minus') . '"></i></span>'
            . '<div class="pg-list-row-main">'
            . '<div class="pg-list-row-head"><span class="pg-list-row-title text-break">' . h($replacement ? $item['live_name'] : $item['draft_name']) . '</span>'
            . '<span class="pg-pill pg-tone-teal">' . h(lang('Comes back')) . '</span></div>'
            . '<div class="pg-text-2">' . h($replacement
                ? lang('Returns to the version it had before the bundle, published again.')
                : lang('Goes back to Drafts.')) . '</div>'
            . '</div></div>';
    }

    foreach ($check['plan']['cannot'] as $page) {
        $kept .= '<input type="hidden" name="kept[]" value="' . (int) $page['item_id'] . '">';
        $rows .= '<div class="pg-list-row is-read">'
            . '<span class="pg-icon-tile" aria-hidden="true"><i class="bi bi-lock"></i></span>'
            . '<div class="pg-list-row-main">'
            . '<div class="pg-list-row-head"><span class="pg-list-row-title text-break">' . h($page['name']) . '</span>'
            . '<span class="pg-pill pg-tone-amber">' . h(lang('Stays as it is')) . '</span></div>'
            . '<div class="pg-text-2">' . h(pg_page_bundle_take_back_reason_text($page['reason'])) . '</div>'
            . '</div></div>';
    }

    $none = empty($check['plan']['can']);
    $note = $none
        ? lang('No page of this bundle can be taken back.')
        : ($check['plan']['cannot']
            ? lang('The pages that can come back are taken back; the pages below them stay as they are now.')
            : lang('Every page of the bundle comes back.'));

    return '<dialog class="dialog pg-dialog-centered" id="page_bundle_take_back_dialog" aria-labelledby="page_bundle_take_back_title">'
        . '<form method="post" action="' . h($action_url) . '" class="d-contents">'
        . '<div class="dialog-header"><h5 class="dialog-title" id="page_bundle_take_back_title">'
        . h(lang(array('string' => 'Take back "{var:1}"', 'vars' => array((string) $bundle['name'])))) . '</h5>'
        . '<button type="button" class="btn-close" data-bs-dismiss="dialog" aria-label="' . h(lang('Close')) . '"></button></div>'
        . '<div class="dialog-body">' . get_token_field()
        . '<input type="hidden" name="action" value="take_back"><input type="hidden" name="bundle_id" value="' . (int) $bundle['id'] . '">' . $kept
        . '<p class="pg-text-2 mb-4">' . h($note) . '</p>' . $rows . '</div>'
        . '<div class="dialog-footer">'
        . '<button type="button" class="pg-button pg-button-quiet" data-bs-dismiss="dialog" autofocus>' . h(lang('Cancel')) . '</button>'
        . '<button type="submit" class="pg-button pg-button-fill"' . ($none ? ' disabled' : '') . '><i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>' . h(lang('Take it back')) . '</button>'
        . '</div></form></dialog>';
}

// ── The publish time ───────────────────────────────────────────────────
//
// The general job (job.php) publishes a scheduled bundle once its time has
// come, in the name of the person who set the time, through the same publish
// and its checks. One that cannot go out whole (a page open in the editor, a
// page changed since, a page the save refuses, a failure taken back) goes
// back to open with the reason named on it, and the person who set the time
// and the one who made the bundle get a bell line. Nothing is retried by
// itself: the time is cleared before the publish starts.

/**
 * Why a timed publish did not go out, named on the bundle: the pages in the
 * way with their state, else the check's own error. Pure.
 *
 * @param array  $plan  pg_page_bundle_publish_plan()
 * @param string $error the check's error ('' for none)
 * @return string '' when nothing held it
 */
function pg_page_bundle_time_held_reason($plan, $error)
{
    $lines = array();

    foreach ((array) $plan['pages'] as $page) {
        $name = (string) $page['name'];

        if ($page['state'] === 'locked') {
            $lines[] = lang(array('string' => '"{var:1}" is open in the Page Design Studio.', 'vars' => array($name)));
        } elseif ($page['state'] === 'changed') {
            $lines[] = lang(array('string' => '"{var:1}" was published again after the copy was made.', 'vars' => array($name)));
        } elseif ($page['state'] === 'refused') {
            $lines[] = lang(array('string' => '"{var:1}" cannot be saved: {var:2}', 'vars' => array($name, (string) $page['text'])));
        }
    }

    if ($lines) {
        return implode(' ', $lines);
    }

    return !empty($plan['ok']) ? '' : (string) $error;
}

/**
 * The people a timed publish tells: who set the time, then who made the
 * bundle, each once. Pure.
 *
 * @param array $bundle
 * @return array user ids
 */
function pg_page_bundle_time_people($bundle)
{
    $out = array();

    foreach (array('scheduled_by', 'created_by') as $column) {
        $id = isset($bundle[$column]) ? (int) $bundle[$column] : 0;

        if (($id > 0) && !in_array($id, $out, true)) {
            $out[] = $id;
        }
    }

    return $out;
}

/**
 * The person a timed publish runs as, from their user row: only while they
 * may still publish bundles (roles 0 and 1). Pure.
 *
 * @param array|null $row user_id, user_role, user_username
 * @return array|null id, role, username
 */
function pg_page_bundle_time_person($row)
{
    if (!is_array($row) || ((int) $row['user_id'] <= 0)) {
        return null;
    }

    $user = array('id' => (int) $row['user_id'], 'role' => (int) $row['user_role'], 'username' => (string) $row['user_username']);

    return pg_page_bundle_user_may($user) ? $user : null;
}

/**
 * Whether a timed publish puts the bundle back to open: when nothing went
 * out (the checks held it, '') or what went out was taken back. Pure.
 *
 * @param string $outcome pg_page_bundle_publish()'s outcome
 * @return bool
 */
function pg_page_bundle_time_reopens($outcome)
{
    return in_array((string) $outcome, array('', 'taken_back'), true);
}

/**
 * A bell line about a bundle to each of its people. The row keeps the
 * bundle's name and id; the words are made when it is read.
 *
 * @param array $bundle
 */
function pg_page_bundle_bell($bundle)
{
    include_once(PG_FUNCTIONS_DIR . '/includes/notifications.php');

    if (!pg_notification_targets_available()) {
        return;
    }

    $people = pg_page_bundle_time_people($bundle);

    // A person deleted since gets no row.
    $known = $people ? array_map('intval', (array) db_values("SELECT user_id FROM user WHERE user_id IN (" . implode(',', $people) . ")")) : array();

    foreach (array_values(array_intersect($people, $known)) as $user_id) {
        db_q("INSERT INTO notifications (action, type, title, target_user_id, reference_id, user, readed, timestamp)
            VALUES ('bundle_held', 'info', ?, ?, ?, '', '0', UNIX_TIMESTAMP())",
            array(mb_substr((string) $bundle['name'], 0, 250), $user_id, (int) $bundle['id']));
    }
}

/**
 * A bundle bell line's words and address, in the reader's language: the
 * reason named on the bundle as it is now.
 *
 * @param array $notification
 * @return array title, short, brief, description, url
 */
function pg_page_bundle_bell_display($notification)
{
    $bundle = pg_page_bundle_get(isset($notification['reference_id']) ? (int) $notification['reference_id'] : 0);
    $name = (string) ($notification['title'] ?? '');
    $partial = $bundle && ((string) $bundle['status'] === 'partial');
    $title = $partial ? lang('Release bundle partly published') : lang('Release bundle not published');
    $reason = $bundle ? (string) $bundle['failed_reason'] : '';

    return array(
        'title' => $title,
        'short' => $name,
        'brief' => $name,
        'description' => ($reason !== '') ? $reason : lang('It stays open; publish it from the Bundles screen.'),
        'url' => 'page_bundles.php' . ($bundle ? '?id=' . (int) $bundle['id'] : ''),
    );
}

/**
 * The general job's pass: each scheduled bundle whose time has come is
 * published once. The time is cleared first, with the time read in the
 * WHERE, so two runs never publish one bundle. The save's own log lines
 * carry the person's name, as api_design_save_as_owner() does.
 *
 * @param int|null $now
 * @param int      $limit bundles a tick
 * @return array published (names), held (names), finished (names of bundles
 *               whose dead publish was finished)
 */
function pg_page_bundle_job_run($now = null, $limit = 5)
{
    $out = array('published' => array(), 'held' => array(), 'finished' => array());

    if (!pg_page_bundles_ready()) {
        return $out;
    }

    // A publish that died (a fatal error, a timeout) is finished first, so a
    // due bundle is published from a clean state.
    $out['finished'] = pg_page_bundle_job_finish_dead();

    $now = ($now === null) ? time() : (int) $now;
    $due = (array) db_items((string) pg_db_bind(
        "SELECT * FROM page_bundle WHERE status = 'scheduled' AND publish_at > 0 AND publish_at <= ? ORDER BY publish_at, id LIMIT " . max(1, (int) $limit),
        array($now), 'escape'));

    foreach ($due as $bundle) {
        db_q("UPDATE page_bundle SET publish_at = 0, updated_at = ? WHERE id = ? AND status = 'scheduled' AND publish_at = ?",
            array(time(), (int) $bundle['id'], (int) $bundle['publish_at']));

        if (mysqli_affected_rows(db::$con) < 1) {
            continue;
        }

        $due_at = (int) $bundle['publish_at'];
        $bundle['publish_at'] = 0;
        $person = pg_page_bundle_time_person(db_item((string) pg_db_bind(
            "SELECT user_id, user_role, user_username FROM user WHERE user_id = ? LIMIT 1", array((int) $bundle['scheduled_by']), 'escape')));

        $outcome = '';
        $reason = lang('The person who set the time can no longer publish release bundles.');

        if ($person) {
            $published = pg_page_bundle_as_person($person, function () use ($bundle, $person) {
                return pg_page_bundle_publish($bundle, $person);
            });

            // Another action holds the bundle (a person's publish, a page
            // being removed): the time cleared above is given back, so the
            // next pass publishes it unless that action moved the bundle.
            if (!empty($published['busy'])) {
                db_q("UPDATE page_bundle SET publish_at = ? WHERE id = ? AND status = 'scheduled' AND publish_at = 0",
                    array($due_at, (int) $bundle['id']));
                continue;
            }

            $outcome = (string) $published['outcome'];
            $reason = ($outcome === '')
                ? pg_page_bundle_time_held_reason($published['check']['plan'], $published['error'])
                : (string) ($published['run']['reason'] ?? '');
        }

        if ($outcome === 'published') {
            $out['published'][] = (string) $bundle['name'];
            continue;
        }

        $fresh = pg_page_bundle_get($bundle['id']);

        if ($fresh && pg_page_bundle_time_reopens($outcome)) {
            $set = array('publish_at' => 0, 'scheduled_by' => 0);

            if ($outcome === '') {
                $set['failed_page_id'] = 0;
                $set['failed_reason'] = $reason;
            }

            pg_page_bundle_move($fresh, 'open', $set);
        }

        pg_page_bundle_bell($bundle);
        $out['held'][] = (string) $bundle['name'];
    }

    return $out;
}
