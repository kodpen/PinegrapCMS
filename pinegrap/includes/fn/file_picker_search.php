<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Server search of the panel's file and image pickers. A picker is a
 * <select data-pg-combobox data-pg-source> (assets/js/pg_fields.js) that
 * prints only the stored choice; as the operator types it asks
 * file_picker_search.php with a named kind and the typed text, and only the
 * matches travel. Before, each picker listed every file of the site on every
 * load of its screen, with no way to search.
 *
 * The kind names a filter of pg_file_picker_kinds(); the browser never sends
 * a condition. Names are matched folded (fold_keyword_for_matching()), so
 * "isik" finds "Işık.png" and "ist" finds "İstanbul.jpg".
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
 * The kinds a picker may ask for, by name.
 *
 *   types   files.type values listed; empty lists every type
 *   public  only files every visitor may open (pg_public_image_files_where()):
 *           what is chosen is shown to everyone
 *   design  design files listed too
 *   value   what an answer posts: 'name' or 'id'
 *   role    the highest role that may ask (0 Administrator ... 3 User)
 *
 * site_icon and public_image are the Site Settings pictures (Site Icon; App
 * Icon, Default Share Image, Organization Logo, Fediverse avatar), behind the
 * settings gate; file is the short link destination, which a role 3 user with
 * edit rights may search within the folders they may edit.
 *
 * @return array name => definition
 */
function pg_file_picker_kinds()
{
    return array(
        'site_icon' => array('types' => array('png', 'svg'), 'public' => true, 'design' => true, 'value' => 'name', 'role' => 2),
        'public_image' => array('types' => pg_public_image_file_types(), 'public' => true, 'design' => true, 'value' => 'name', 'role' => 2),
        'file' => array('types' => array(), 'public' => false, 'design' => true, 'value' => 'id', 'role' => 3),
    );
}

/** The most answers one search sends. */
function pg_file_picker_limit()
{
    return 30;
}

/**
 * The most rows one search reads before the folded match drops the ones the
 * LIKE wildcards let through (pg_file_picker_like_term()).
 */
function pg_file_picker_scan_limit()
{
    return 400;
}

/**
 * Whether a user may search a kind. Pure.
 *
 * @param mixed $kind        the requested kind name
 * @param mixed $role        user.user_role
 * @param bool  $edit_rights a role 3 user may edit at least one folder (no_acl_check())
 * @return string 'ok', 'unknown' (no such kind) or 'denied'
 */
function pg_file_picker_access_verdict($kind, $role, $edit_rights)
{
    $kinds = pg_file_picker_kinds();

    if (!is_string($kind) || !isset($kinds[$kind])) {
        return 'unknown';
    }

    $role = (int) $role;

    if ($role > (int) $kinds[$kind]['role']) {
        return 'denied';
    }

    // A User without edit rights has no panel screen to pick from.
    if (($role >= 3) && !$edit_rights) {
        return 'denied';
    }

    return 'ok';
}

/**
 * The `files` condition of a kind. Pure.
 *
 * @param array      $kind         one of pg_file_picker_kinds()
 * @param string     $public_where pg_public_image_files_where() for a public kind
 * @param int[]      $bin_ids      the Recycle Bin and its sub-folders
 * @param int[]|null $folders      the folders a role 3 user may edit; null for all
 * @return string
 */
function pg_file_picker_where($kind, $public_where, $bin_ids, $folders)
{
    $ints = function ($ids) {
        return array_values(array_unique(array_map('intval', (array) $ids)));
    };

    $where = array("(name != '')", '(attachment = 0)');

    if (empty($kind['design'])) {
        $where[] = '(design = 0)';
    }

    // The types come from pg_file_picker_kinds(), never from the request;
    // anything but a plain extension is dropped all the same.
    $types = array();

    foreach ((array) ($kind['types'] ?? array()) as $type) {
        $type = strtolower((string) $type);

        if (preg_match('/^[a-z0-9]{1,10}$/', $type)) {
            $types[] = $type;
        }
    }

    if ($types) {
        $where[] = "(type IN ('" . implode("', '", array_unique($types)) . "'))";
    }

    // A public kind without its condition lists nothing.
    if (!empty($kind['public'])) {
        $where[] = (trim((string) $public_where) !== '') ? '(' . $public_where . ')' : '(folder IN (-1))';
    }

    $bin = $ints($bin_ids);

    if ($bin) {
        $where[] = '(folder NOT IN (' . implode(', ', $bin) . '))';
    }

    if ($folders !== null) {
        $folders = $ints($folders);
        $where[] = '(folder IN (' . ($folders ? implode(', ', $folders) : '-1') . '))';
    }

    return implode(' AND ', $where);
}

/**
 * A name or a typed text folded for matching: lowercase, every i-letter a
 * plain i, accents dropped. Pure.
 *
 * @param mixed $text
 * @return string
 */
function pg_file_picker_fold($text)
{
    return fold_keyword_for_matching(trim((string) $text));
}

/**
 * The LIKE term (escaped for LIKE, not yet for SQL) the rows are read with:
 * the folded text, each i a one-letter wildcard. The column's collation
 * ignores case and accents but keeps i and ı apart, so "isik" would miss
 * "ışık"; the wildcard reads both and pg_file_picker_results() keeps only the
 * real matches. Pure.
 *
 * @param mixed $query
 * @return string
 */
function pg_file_picker_like_term($query)
{
    return implode('_', array_map('escape_like', explode('i', pg_file_picker_fold($query))));
}

/**
 * The answers among rows read with the LIKE term: names whose folded form
 * holds the folded text, those starting with it first, each list in the
 * order read. Pure.
 *
 * @param array  $rows  id, name
 * @param mixed  $query the typed text
 * @param string $value 'name' or 'id': what an answer posts
 * @param int    $limit
 * @return array of array(id, text)
 */
function pg_file_picker_results($rows, $query, $value, $limit)
{
    $needle = pg_file_picker_fold($query);

    if ($needle === '') {
        return array();
    }

    $first = array();
    $rest = array();

    foreach ((array) $rows as $row) {
        $name = (string) ($row['name'] ?? '');

        if ($name === '') {
            continue;
        }

        $at = mb_strpos(pg_file_picker_fold($name), $needle, 0, 'UTF-8');

        if ($at === false) {
            continue;
        }

        $answer = array('id' => ($value === 'id') ? (int) ($row['id'] ?? 0) : $name, 'text' => $name);

        if ($at === 0) {
            $first[] = $answer;
        } else {
            $rest[] = $answer;
        }
    }

    return array_slice(array_merge($first, $rest), 0, max(1, (int) $limit));
}

/**
 * The files of a kind whose name matches the typed text, for a user the
 * caller has let through pg_file_picker_access_verdict().
 *
 * @param string $kind_name
 * @param mixed  $query
 * @param array  $user validate_user()
 * @return array of array(id, text)
 */
function pg_file_picker_search($kind_name, $query, $user)
{
    $kinds = pg_file_picker_kinds();
    $query = mb_substr(trim((string) $query), 0, 100, 'UTF-8');

    if (!isset($kinds[$kind_name]) || (pg_file_picker_fold($query) === '')) {
        return array();
    }

    $kind = $kinds[$kind_name];

    $where = pg_file_picker_where(
        $kind,
        !empty($kind['public']) ? pg_public_image_files_where() : '',
        pg_recycle_bin_folder_ids(),
        ((int) $user['role'] >= 3) ? get_folders_that_user_has_access_to($user['id']) : null);

    $rows = db_items(
        "SELECT id, name FROM files
        WHERE " . $where . "
            AND (LOWER(name) LIKE '%" . e(pg_file_picker_like_term($query)) . "%')
        ORDER BY name
        LIMIT " . pg_file_picker_scan_limit());

    return pg_file_picker_results(is_array($rows) ? $rows : array(), $query, $kind['value'], pg_file_picker_limit());
}

/**
 * The address a picker of a kind searches.
 *
 * @param string      $kind
 * @param string|null $base the software directory's address with its slash;
 *                          PATH . SOFTWARE_DIRECTORY . '/' when null
 * @return string
 */
function pg_file_picker_source($kind, $base = null)
{
    if ($base === null) {
        $base = (defined('PATH') ? PATH : '/') . (defined('SOFTWARE_DIRECTORY') ? SOFTWARE_DIRECTORY : '') . '/';
    }

    return $base . 'file_picker_search.php?kind=' . rawurlencode((string) $kind);
}

/**
 * The data-pg-* hooks of a select that searches files of a kind, as
 * liveform's output_field() takes them.
 *
 * @param string      $kind
 * @param string|null $base see pg_file_picker_source()
 * @return array
 */
function pg_file_picker_field_attributes($kind, $base = null)
{
    return array(
        'data-pg-combobox' => '',
        'data-pg-source' => pg_file_picker_source($kind, $base),
        'data-pg-min-length' => '1',
    );
}

/**
 * The same hooks as markup, for a <select> written by hand.
 *
 * @param string      $kind
 * @param string|null $base see pg_file_picker_source()
 * @return string
 */
function pg_file_picker_attributes($kind, $base = null)
{
    $output = '';

    foreach (pg_file_picker_field_attributes($kind, $base) as $name => $value) {
        $output .= ' ' . $name . (($value === '') ? '' : '="' . h($value) . '"');
    }

    return $output;
}

/**
 * The options of a public picture picker: the empty choice and the stored
 * one, marked by pg_public_image_stored_option() when visitors cannot open it
 * or no file carries it. The search brings the rest.
 *
 * @param string $value the stored name
 * @param string $empty the empty choice's text
 * @return string
 */
function pg_file_picker_public_options($value, $empty)
{
    $value = (string) $value;

    return '<option value="">' . h($empty) . '</option>' . (($value !== '') ? pg_public_image_stored_option($value) : '');
}

/**
 * The label a file id picker shows for its stored file: its name, or for a
 * file in the Recycle Bin the name it had before the bin parked it, marked.
 * Pure.
 *
 * @param string $name     files.name
 * @param bool   $binned   the file is in the Recycle Bin
 * @param string $original the name it had before (pg_recycle_parked_original())
 * @return string
 */
function pg_file_picker_id_label($name, $binned, $original = '')
{
    if (!$binned) {
        return (string) $name;
    }

    return ((trim((string) $original) !== '') ? (string) $original : (string) $name) . ' [' . lang('IN RECYCLE BIN') . ']';
}

/**
 * The options of a file id picker (liveform, label => value): the empty
 * choice and the stored file, when it still exists, is no attachment and the
 * user may edit its folder. A file in the Recycle Bin is shown marked; the
 * save decides whether it may stay.
 *
 * @param mixed $file_id the stored or posted id
 * @return array
 */
function pg_file_picker_id_options($file_id)
{
    global $user;

    $options = array('-' . lang(array('string' => 'Select {var:1}', 'vars' => array(lang('File')))) . '-' => '');
    $file_id = is_scalar($file_id) ? (int) $file_id : 0;

    if ($file_id < 1) {
        return $options;
    }

    $row = db_item("SELECT id, name, folder FROM files WHERE (id = '" . $file_id . "') AND (attachment = 0) AND (name != '')");

    if (!is_array($row) || !$row) {
        return $options;
    }

    if (isset($user['role']) && ((int) $user['role'] >= 3) && !in_array((int) $row['folder'], array_map('intval', get_folders_that_user_has_access_to($user['id'])), true)) {
        return $options;
    }

    $binned = in_array((int) $row['folder'], pg_recycle_bin_folder_ids(), true);
    $original = '';

    if ($binned) {
        require_once(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');
        $original = pg_recycle_parked_original($row['name'], $row['id']);
    }

    $options[h(pg_file_picker_id_label($row['name'], $binned, $original))] = (int) $row['id'];

    return $options;
}
