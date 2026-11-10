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
 * The kind names a file kind of the picker's registry (includes/fn/picker.php,
 * pg_picker_kinds()); the browser never sends a condition. Names are matched folded (fold_keyword_for_matching()), so
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

require_once(PG_FUNCTIONS_DIR . '/includes/fn/picker.php');

/**
 * The kinds this search answers, by name: the file kinds of the picker's
 * registry (pg_picker_kinds()) it answered before the registry existed. Any
 * other name stays unknown here, so the contract of file_picker_search.php
 * does not grow with the registry.
 *
 *   types   files.type values listed; empty lists every type
 *   public  only files every visitor may open
 *   design  design files listed too
 *   value   what an answer posts: 'name' or 'id'
 *   role    the highest role that may ask (0 Administrator ... 3 User)
 *
 * @return array name => definition
 */
function pg_file_picker_kinds()
{
    return array_intersect_key(pg_picker_kinds(), array_flip(array('site_icon', 'public_image', 'file')));
}

/** The most answers one search sends. */
function pg_file_picker_limit()
{
    return 30;
}

/** The most rows one search reads (pg_picker_scan_limit()). */
function pg_file_picker_scan_limit()
{
    return pg_picker_scan_limit();
}

/**
 * Whether a user may search a kind (pg_picker_access_verdict() over
 * pg_file_picker_kinds()). Pure.
 *
 * @param mixed $kind        the requested kind name
 * @param mixed $role        user.user_role
 * @param bool  $edit_rights a role 3 user may edit at least one folder (no_acl_check())
 * @return string 'ok', 'unknown' (no such kind) or 'denied'
 */
function pg_file_picker_access_verdict($kind, $role, $edit_rights)
{
    return pg_picker_access_verdict($kind, $role, $edit_rights, pg_file_picker_kinds());
}

/**
 * The `files` condition of a kind (pg_picker_files_where()). Pure.
 *
 * @param array      $kind         one of pg_file_picker_kinds()
 * @param string     $public_where pg_public_image_files_where() for a public kind
 * @param int[]      $bin_ids      the Recycle Bin and its sub-folders
 * @param int[]|null $folders      the folders a role 3 user may edit; null for all
 * @return string
 */
function pg_file_picker_where($kind, $public_where, $bin_ids, $folders)
{
    return pg_picker_files_where($kind, $public_where, $bin_ids, $folders);
}

/**
 * A name or a typed text folded for matching (pg_picker_fold()). Pure.
 *
 * @param mixed $text
 * @return string
 */
function pg_file_picker_fold($text)
{
    return pg_picker_fold($text);
}

/**
 * The LIKE term the rows are read with (pg_picker_like_term()). Pure.
 *
 * @param mixed $query
 * @return string
 */
function pg_file_picker_like_term($query)
{
    return pg_picker_like_term($query);
}

/**
 * The answers among rows read with the LIKE term: names whose folded form
 * holds the folded text, those starting with it first (pg_picker_matches()),
 * as {id, text}. Pure.
 *
 * @param array  $rows  id, name
 * @param mixed  $query the typed text
 * @param string $value 'name' or 'id': what an answer posts
 * @param int    $limit
 * @return array of array(id, text)
 */
function pg_file_picker_results($rows, $query, $value, $limit)
{
    $answers = array();

    foreach (array_slice(pg_picker_matches($rows, $query), 0, max(1, (int) $limit)) as $row) {
        $name = (string) $row['name'];
        $answers[] = array('id' => ($value === 'id') ? (int) ($row['id'] ?? 0) : $name, 'text' => $name);
    }

    return $answers;
}

/**
 * The files of a kind whose name matches the typed text, for a user the
 * caller has let through pg_file_picker_access_verdict(): the picker's search
 * (pg_picker_search_rows()), at most pg_file_picker_limit() answers.
 *
 * @param string $kind_name
 * @param mixed  $query
 * @param array  $user validate_user()
 * @return array of array(id, text)
 */
function pg_file_picker_search($kind_name, $query, $user)
{
    $kinds = pg_file_picker_kinds();

    if (!isset($kinds[$kind_name])) {
        return array();
    }

    $rows = pg_picker_search_rows($kind_name, $query, $user);

    return pg_file_picker_results($rows, $query, $kinds[$kind_name]['value'], pg_file_picker_limit());
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
