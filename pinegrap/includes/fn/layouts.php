<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A page's custom layout: the PHP view template render_layout() includes
 * for a page whose layout type is 'custom'. While the page is unmodified the
 * layout is generated from the page type's template
 * (generate_layout_content()); once someone saves a text that differs from
 * the generated one it is written to data/layouts/<page id>.php and
 * page.layout_modified is set. A hosted site neither writes nor uses the
 * file. Read and saved by the classic page designer (get_layout,
 * update_layout) and the visual designer's layout code tab (layout_code).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The file name of a page's layout under LAYOUT_DIRECTORY_PATH, built from
// the page id alone so a request can never name another path; '' for an id
// that is not a positive number.
function pg_layout_file_name($page_id)
{
    if (is_int($page_id)) {
        $id = $page_id;
    } else if (is_string($page_id) && preg_match('/^[0-9]+$/', $page_id)) {
        $id = (int) $page_id;
    } else {
        return '';
    }

    return ($id > 0) ? $id . '.php' : '';
}

// Whether a layout text is the generated one. White space is ignored: the
// code editor and the generator do not agree on line endings and
// indentation, and a layout saved unchanged must stay unmodified, so it
// keeps following the form's fields.
function pg_layout_same_as_generated($content, $generated)
{
    return preg_replace('/\s+/', '', (string) $content) === preg_replace('/\s+/', '', (string) $generated);
}

function pg_layout_generated($page_id)
{
    require_once(PG_FUNCTIONS_DIR . '/generate_layout_content.php');

    return generate_layout_content($page_id);
}

// The page's layout as the site draws it: the saved file while the page is
// modified, else the generated text (always the generated text on a hosted
// site, which ignores the file). False when the page does not exist.
function pg_layout_read($page_id)
{
    $file = pg_layout_file_name($page_id);

    if ($file === '') {
        return false;
    }

    $layout = db_item(
        "SELECT
            page_id AS id,
            page_name AS name,
            page_type AS type,
            layout_type,
            layout_modified AS modified
        FROM page
        WHERE page_id = '" . (int) $page_id . "'"
    );

    if (!$layout) {
        return false;
    }

    if ($layout['modified'] && !pg_hosted()) {
        $layout['content'] = (string) @file_get_contents(LAYOUT_DIRECTORY_PATH . '/' . $file);
    } else {
        $layout['content'] = pg_layout_generated($layout['id']);
    }

    return $layout;
}

// Saves a page's layout: a text equal to the generated one (white space
// aside) removes the file and marks the page unmodified, any other text is
// written to the page's file and marks it modified. Returns
// array('ok' => bool, 'modified' => 0|1, 'message' => string).
function pg_layout_save($page_id, $content)
{
    if (pg_hosted()) {
        return array('ok' => false, 'modified' => 0, 'message' => lang('Custom layouts are not available on this site.'));
    }

    $file = pg_layout_file_name($page_id);
    $layout = ($file !== '')
        ? db_item("SELECT page_id AS id, page_name AS name FROM page WHERE page_id = '" . (int) $page_id . "'")
        : false;

    if (!$layout) {
        return array('ok' => false, 'modified' => 0, 'message' => 'Layout could not be found.');
    }

    $path = LAYOUT_DIRECTORY_PATH . '/' . $file;

    if (pg_layout_same_as_generated($content, pg_layout_generated($layout['id']))) {
        if (file_exists($path)) {
            unlink($path);
        }

        $modified = 0;
    } else {
        @file_put_contents($path, (string) $content);

        $modified = 1;
    }

    // Marked so the layout is no longer generated when the form's fields
    // change.
    db(
        "UPDATE page
        SET
            layout_modified = '$modified',
            page_user = '" . USER_ID . "',
            page_timestamp = UNIX_TIMESTAMP()
        WHERE page_id = '" . (int) $layout['id'] . "'"
    );

    log_activity('layout for page (' . $layout['name'] . ') was modified');

    return array('ok' => true, 'modified' => $modified, 'message' => '');
}

// Of the given pages, the ids of those that draw a custom layout (a page
// type with a layout and layout type 'custom'): the pages whose system
// region opens the layout code tab in the visual designer.
function pg_layout_custom_page_ids(array $page_ids)
{
    $ids = array();

    foreach ($page_ids as $id) {
        if ((int) $id > 0) {
            $ids[(int) $id] = true;
        }
    }

    if (!$ids) {
        return array();
    }

    $rows = db_items(
        "SELECT page_id, page_type
        FROM page
        WHERE
            page_id IN (" . implode(',', array_keys($ids)) . ")
            AND layout_type = 'custom'"
    );

    $out = array();

    foreach (is_array($rows) ? $rows : array() as $row) {
        if (check_if_page_type_supports_layout($row['page_type'])) {
            $out[] = (int) $row['page_id'];
        }
    }

    return $out;
}

// The parameter the toolbar's style designer button (Ctrl+G) adds so the
// visual designer opens on the page's layout tab, where the classic page
// designer used to open: for a page that draws a custom layout, for the
// layout editor's role (designers and administrators). Not on a hosted
// site, which draws the generated layout and keeps the tab read only; the
// button opens the canvas there.
function pg_layout_tab_param($page_id, array $layout_page_ids, $role, $hosted)
{
    $page_id = (int) $page_id;

    if (($page_id <= 0) || ((int) $role > 1) || $hosted) {
        return '';
    }

    return in_array($page_id, array_map('intval', $layout_page_ids), true) ? '&layout=1' : '';
}

// The page whose layout tab the designer opens on load (?layout=1): the page
// it lands on, when that page draws a custom layout; 0 otherwise.
function pg_layout_open_page_id($flag, $page_id, array $layout_page_ids)
{
    $page_id = (int) $page_id;

    if (!is_scalar($flag) || ((string) $flag === '') || ((string) $flag === '0') || ($page_id <= 0)) {
        return 0;
    }

    return in_array($page_id, array_map('intval', $layout_page_ids), true) ? $page_id : 0;
}
