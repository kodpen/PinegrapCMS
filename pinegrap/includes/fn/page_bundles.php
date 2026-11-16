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

    return (array) db_items(
        "SELECT page_bundle_item.*, draft.page_name AS draft_name, live.page_name AS live_name
        FROM page_bundle_item
        LEFT JOIN page AS draft ON draft.page_id = page_bundle_item.draft_page_id
        LEFT JOIN page AS live ON live.page_id = page_bundle_item.live_page_id
        WHERE page_bundle_item.bundle_id = '$bundle_id'
        ORDER BY page_bundle_item.id");
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
 * Deletes a bundle with its pages. Its drafts stay drafts.
 *
 * @param array $bundle pg_page_bundle_get()
 * @return array ok, error
 */
function pg_page_bundle_delete($bundle)
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

    if (!pg_page_bundle_may_change($bundle['status'])) {
        return $fail(lang('A published bundle cannot be changed.'));
    }

    $live = pg_experiment_sheet_live_page($live_page_id);

    if (!$live) {
        return $fail(lang('Only a page of a visual design that is on the site can be copied into a bundle.'));
    }

    $live_id = (int) $live['page_id'];

    if (pg_experiment_sheet_ready() && db_value("SELECT id FROM experiments WHERE page_id = '$live_id' AND status <> 'ended' LIMIT 1")) {
        return $fail(lang('This page has an open experiment; end it before adding a copy of the page to a bundle.'));
    }

    $taken = pg_page_bundle_add_refusal(0, $live_id);

    if ($taken !== '') {
        return $fail($taken);
    }

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
        require_once(PG_FUNCTIONS_DIR . '/includes/explorer/recycle_bin.php');
        pg_delete_page_record($draft_page_id, $user);

        return $fail($written['error']);
    }

    if ($written['id'] <= 0) {
        return $fail(lang('The copy was made in Drafts, but it could not be added to the bundle.'));
    }

    return array('ok' => true, 'error' => '', 'item_id' => $written['id'], 'draft_page_id' => $draft_page_id);
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

    if (!pg_page_bundle_may_change($bundle['status'])) {
        return $fail(lang('A published bundle cannot be changed.'));
    }

    $draft_page_id = (int) $draft_page_id;
    $page = ($draft_page_id > 0)
        ? db_item("SELECT page_id, page_name, page_folder FROM page
            WHERE page_id = '$draft_page_id' AND " . pg_page_visual_design_sql() . pg_designer_not_binned_sql() . " LIMIT 1")
        : null;

    if (!is_array($page) || !pg_page_is_draft($page['page_folder'])) {
        return $fail(lang('Only a draft of a visual design can be added to a bundle.'));
    }

    if (pg_experiment_variant_hold($draft_page_id)) {
        return $fail(lang('This draft is a variant in an open experiment, so it cannot be added to a bundle.'));
    }

    $taken = pg_page_bundle_add_refusal($draft_page_id, 0);

    if ($taken !== '') {
        return $fail($taken);
    }

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
 * draft.
 *
 * @param array $bundle  pg_page_bundle_get()
 * @param int   $item_id
 * @return array ok, error
 */
function pg_page_bundle_remove_item($bundle, $item_id)
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
