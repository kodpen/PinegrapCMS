<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Recycle bin names: a binned row is parked under a "pgbin_<id>_<name>" name
 * and gets its own name back on restore, a binned page stops being the home
 * page, and the daily purge of entries past the retention window. Loaded by
 * view_folder_and_files_f.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// ── The bin should not hold a name hostage ──────────────────────────────
//
// Pages and files share one namespace with the web root, and folder names are
// unique across the site, so a row sitting in the bin still answers to its
// name. Upload the same file again and it comes back as index[1].html, and the
// only way to get the plain name back is to empty the bin -- which is not what
// a bin is for, and not what any desktop does.
//
// So a binned row is parked under a name nobody types: "pgbin_<id>_<name>".
// The original travels inside the parked name, so nothing has to be written
// down anywhere else and no column has to be added; the id keeps it unique even
// against a second parked copy of the same name. Restoring strips the prefix
// and asks for the original back. If something has taken it in the meantime,
// get_unique_name() hands back the "[1]" form -- the suffix the rest of the
// software already uses, and the one Windows shows for the same situation.
//
// Only the row that was binned is parked. What lives inside a binned folder
// keeps its name: those rows have no bin record of their own, and renaming a
// whole subtree on the way to the bin would mean hundreds of disk renames on
// an operation that has to be reliable above all.
define('PG_RECYCLE_PARK_PREFIX', 'pgbin_');

// The name a parked row was called before it was parked, or '' when the name
// is not parked (or belongs to some other row, which cannot happen but is not
// worth trusting).
function pg_recycle_parked_original($name, $item_id)
{
    $prefix = PG_RECYCLE_PARK_PREFIX . (int) $item_id . '_';

    if (strpos((string) $name, $prefix) !== 0) {
        return '';
    }

    return (string) substr($name, strlen($prefix));
}

// A page in the bin is not the home page any more.
//
// The site root is served by any page carrying page_home = 'yes', picked at
// random when there are several (get_page.php), so a binned page that kept
// the flag went on answering the root on some requests - drawn with a design
// that may already have been deleted. The flag leaves with the row, and
// unlike the name it is not handed back on restore: a page coming out of the
// bin is an ordinary page until somebody makes it the home page again.
//
// The whole bin is swept rather than the one row: a folder carries its pages
// in with it, and those rows have no bin record of their own. The folder tree
// is read here instead of through pg_recycle_bin_folder_ids(), which caches
// per request and may have been filled before the move that just happened.
function pg_recycle_drop_home()
{
    $bin_id = (int) pg_recycle_bin_folder_id();

    if ($bin_id <= 0) {
        return;
    }

    $children = array();

    foreach ((array) db_items("SELECT folder_id, folder_parent FROM folder") as $row) {
        $children[(int) $row['folder_parent']][] = (int) $row['folder_id'];
    }

    $ids = array($bin_id => true);
    $stack = array($bin_id);

    while ($stack) {
        $folder_id = array_pop($stack);

        if (empty($children[$folder_id])) {
            continue;
        }

        foreach ($children[$folder_id] as $child_id) {
            // A damaged tree that loops back on itself must not spin forever.
            if (isset($ids[$child_id])) {
                continue;
            }

            $ids[$child_id] = true;
            $stack[] = $child_id;
        }
    }

    // 'no', not '': page_home is ENUM('no','yes') and a strict server rejects
    // a value outside the list.
    db("UPDATE page SET page_home = 'no' WHERE page_home = 'yes' AND page_folder IN (" . implode(',', array_keys($ids)) . ")");
}

// Park the name of a row on its way into the bin. Best effort by design: if the
// disk rename fails the row keeps the name it had, which is the state the whole
// screen worked in before this existed.
function pg_recycle_park_name($item_kind, $item_id, $user)
{
    $item_id = (int) $item_id;

    if ($item_id <= 0) {
        return;
    }

    // Every way into the bin parks the name, so this is also where a binned
    // page - or a binned folder's pages - stops being the home page.
    if (($item_kind == 'page') || ($item_kind == 'folder')) {
        pg_recycle_drop_home();
    }

    $prefix = PG_RECYCLE_PARK_PREFIX . $item_id . '_';

    // A parked name that does not fit gets cut short, and a cut short name
    // cannot be handed back. Those rows keep the name they had -- which is
    // exactly how the bin behaved before any of this, so nothing is lost.
    $limit = ($item_kind == 'folder') ? 200 : 100;

    if ($item_kind == 'folder') {

        $folder = db_item("SELECT folder_id, folder_name FROM folder WHERE folder_id = '" . e($item_id) . "'");

        if ((!$folder) || (pg_recycle_parked_original($folder['folder_name'], $item_id) != '')) {
            return;
        }

        if (mb_strlen($prefix . $folder['folder_name']) > $limit) {
            return;
        }

        db(
            "UPDATE folder SET folder_name = '" . e($prefix . $folder['folder_name']) . "'
            WHERE folder_id = '" . e($item_id) . "'");

        return;
    }

    if ($item_kind == 'page') {

        $page = db_item("SELECT page_id, page_name FROM page WHERE page_id = '" . e($item_id) . "'");

        if ((!$page) || (pg_recycle_parked_original($page['page_name'], $item_id) != '')) {
            return;
        }

        if (mb_strlen($prefix . $page['page_name']) > $limit) {
            return;
        }

        db(
            "UPDATE page SET page_name = '" . e($prefix . $page['page_name']) . "'
            WHERE page_id = '" . e($item_id) . "'");

        return;
    }

    if ($item_kind == 'file') {

        $file = db_item("SELECT id, name FROM files WHERE id = '" . e($item_id) . "'");

        if ((!$file) || (pg_recycle_parked_original($file['name'], $item_id) != '')) {
            return;
        }

        if (mb_strlen($prefix . $file['name']) > $limit) {
            return;
        }

        // The record's name is the address, so the disk file moves first and
        // the row is only updated if it did.
        $parked = $prefix . $file['name'];
        $old_path = FILE_DIRECTORY_PATH . '/' . $file['name'];
        $new_path = FILE_DIRECTORY_PATH . '/' . $parked;

        if ((file_exists($old_path) == true) && (@rename($old_path, $new_path) == false)) {
            return;
        }

        db("UPDATE files SET name = '" . e($parked) . "' WHERE id = '" . e($item_id) . "'");
    }
}

// Give a restored row its name back, or the next free "[1]" form of it.
function pg_recycle_release_name($item_kind, $item_id, $user)
{
    $item_id = (int) $item_id;

    if ($item_id <= 0) {
        return;
    }

    if ($item_kind == 'folder') {

        $folder = db_item("SELECT folder_id, folder_name FROM folder WHERE folder_id = '" . e($item_id) . "'");

        if (!$folder) {
            return;
        }

        $original = pg_recycle_parked_original($folder['folder_name'], $item_id);

        if ($original == '') {
            return;
        }

        $name = get_unique_name(array('name' => $original, 'type' => 'folder'));

        db("UPDATE folder SET folder_name = '" . e($name) . "' WHERE folder_id = '" . e($item_id) . "'");

        return;
    }

    if ($item_kind == 'page') {

        $page = db_item("SELECT page_id, page_name FROM page WHERE page_id = '" . e($item_id) . "'");

        if (!$page) {
            return;
        }

        $original = pg_recycle_parked_original($page['page_name'], $item_id);

        if ($original == '') {
            return;
        }

        $name = get_unique_name(array('name' => $original, 'type' => 'page'));

        db("UPDATE page SET page_name = '" . e($name) . "' WHERE page_id = '" . e($item_id) . "'");

        return;
    }

    if ($item_kind == 'file') {

        $file = db_item("SELECT id, name FROM files WHERE id = '" . e($item_id) . "'");

        if (!$file) {
            return;
        }

        $original = pg_recycle_parked_original($file['name'], $item_id);

        if ($original == '') {
            return;
        }

        $name = prepare_file_name(get_unique_name(array('name' => $original, 'type' => 'file')));

        $old_path = FILE_DIRECTORY_PATH . '/' . $file['name'];
        $new_path = FILE_DIRECTORY_PATH . '/' . $name;

        if ((file_exists($old_path) == true) && (@rename($old_path, $new_path) == false)) {
            return;
        }

        $extension = mb_strtolower(pathinfo($name, PATHINFO_EXTENSION));

        db("UPDATE files SET name = '" . e($name) . "', type = '" . e($extension) . "' WHERE id = '" . e($item_id) . "'");
    }
}

// Purge bin entries older than the retention window. Claimed once a day by
// whichever explorer visit comes first; capped per run so a big bin never
// stalls the screen that triggered it.
function pg_recycle_purge_if_due($user)
{
    if (pg_recycle_ready() == false) {
        return;
    }

    $last = (int) db_value("SELECT recycle_last_purge FROM config");

    if ((time() - $last) < 86400) {
        return;
    }

    // Claim the run before doing the work, so two overlapping requests do
    // not both purge.
    db("UPDATE config SET recycle_last_purge = '" . e(time()) . "'");

    $cutoff = time() - (pg_recycle_retention_days() * 86400);

    $entries = db_items(
        "SELECT id, item_type, item_id
        FROM recycle_bin
        WHERE deleted_at < '" . e($cutoff) . "'
        ORDER BY deleted_at
        LIMIT 30");

    if (!$entries) {
        return;
    }

    $purged = 0;

    // The purge is the software's own work, run on whoever's visit came first;
    // the audit trail records its deletes as a job's, not as that person's.
    $was_job = pg_audit_as_job(true);

    try {
        foreach ($entries as $entry) {
            $result = pg_recycle_hard_delete_item($entry['item_type'], $entry['item_id'], $user);

            if ($result['status'] == 'success') {
                db("DELETE FROM recycle_bin WHERE id = '" . e($entry['id']) . "'");
                $purged++;
            }
        }
    } finally {
        pg_audit_as_job($was_job);
    }

    if ($purged > 0) {
        log_activity(lang(array('string' => '{var:1} item(s) were purged from the recycle bin', 'vars' => $purged)), $_SESSION['sessionusername']);
    }
}
