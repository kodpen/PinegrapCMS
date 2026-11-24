<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The version of a page's layout tree an editor tab started from.
 *
 * The Page Design Studio's lock lapses when a tab stops beating (a laptop
 * asleep, a frozen background tab). Somebody else may then open the page,
 * save it and leave; the first tab claims the free lock again on its next
 * heartbeat, and the lock alone cannot tell that its tree is now older than
 * the stored one. So each tab is given the base hash of the tree it loaded,
 * sends it back with Save, and the save path refuses a page whose stored
 * tree no longer has that hash (includes/designer_screen.php).
 *
 * page_timestamp cannot be the base: the scheduled-publish job, the designs
 * list and the API move it without touching the layout. Node notes are left
 * out of the hash: designer/note_save writes them into the stored tree while
 * the lock holder keeps editing, and the editor merges them in on its own.
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
 * The props a node note writes (pg_collab_note_save()): never part of the
 * base hash.
 */
function pg_page_base_note_props()
{
    return array('_notes', '_notes_by', '_notes_at');
}

/**
 * Base hash of a stored layout tree (pure).
 *
 * Decoded and encoded again, so the same tree stored as `[]` or `{}`, or
 * written with other escaping, hashes the same; note props are removed from
 * every node first. A tree that is not JSON is hashed as it is stored.
 *
 * @param string $tree_json the tree as stored ('' for a page with none)
 * @return string 40 hex characters
 */
function pg_page_base_hash($tree_json)
{
    $tree_json = (string)$tree_json;
    if (trim($tree_json) === '') return sha1('');

    $tree = json_decode($tree_json, true);
    if (!is_array($tree)) return sha1($tree_json);

    $tree = pg_page_base_strip_notes($tree);
    $flat = json_encode($tree, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    if (!is_string($flat)) return sha1($tree_json);
    return sha1($flat);
}

/**
 * The tree with the note props removed from every `props` map at any depth
 * (pure). Widget and shared-component slots nest nodes outside `children`,
 * so every array is walked.
 *
 * @param array $tree
 * @return array
 */
function pg_page_base_strip_notes(array $tree)
{
    if (isset($tree['props']) && is_array($tree['props'])) {
        foreach (pg_page_base_note_props() as $k) unset($tree['props'][$k]);
    }
    foreach ($tree as $k => $v) {
        if (is_array($v)) $tree[$k] = pg_page_base_strip_notes($v);
    }
    return $tree;
}

/**
 * Was the page's layout written since the tab loaded the version with
 * $base_hash (pure)?
 *
 * A save that sends no base (an editor loaded before this check, the API) is
 * not compared; neither is a page that does not exist yet.
 *
 * @param int         $page_id
 * @param string|null $base_hash the hash the tab sent, null when none
 * @param string      $stored_tree_json the page's tree as stored now
 * @return bool
 */
function pg_page_base_is_stale($page_id, $base_hash, $stored_tree_json)
{
    if ((int)$page_id <= 0 || $base_hash === null) return false;
    $base_hash = strtolower(trim((string)$base_hash));
    if (!preg_match('/^[0-9a-f]{40}$/', $base_hash)) return false;
    return !hash_equals(pg_page_base_hash($stored_tree_json), $base_hash);
}
