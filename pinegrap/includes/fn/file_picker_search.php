<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Server search of a file field that is a server-search combobox
 * (assets/js/pg_fields.js), such as the short link list's quick edit File
 * cell: as the operator types it asks file_picker_search.php with a named
 * kind and the typed text, and only the matches travel. The other file and
 * picture fields are picker fields (pg_picker_field(), includes/fn/picker.php).
 *
 * The kind names a file kind of the picker's registry (includes/fn/picker.php,
 * pg_picker_kinds()); the browser never sends a condition. Names are matched
 * folded (fold_keyword_for_matching()), so
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
