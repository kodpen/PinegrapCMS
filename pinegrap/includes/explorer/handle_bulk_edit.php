<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The file manager's bulk page and bulk file edit sub-actions: the choices
 * each panel can offer and the apply. Each handler answers through
 * respond(), which ends the request.
 * Called by pg_explorer_handle() and loaded by view_folder_and_files_f.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// A text that ends like a sentence: a full stop is added when it has none.
function pg_explorer_sentence_end($text)
{
    if (mb_substr($text, -1) !== '.') {
        $text .= '.';
    }

    return $text;
}

// A bulk page edit's answer: a page refused, or a style left alone on a page
// the visual editor opens, makes it partial; error when nothing was let
// through and something was refused.
function pg_explorer_bulk_pages_status($updated, $errors, $style_skipped)
{
    if (count($errors) == 0) {
        return ($style_skipped > 0) ? 'partial' : 'success';
    }

    return ($updated > 0) ? 'partial' : 'error';
}

// The bulk page panel's choices: the styles a manager may pick, and whether
// the noindex/nofollow pair exists.
function pg_explorer_handle_bulk_page_options($type, $request, $user)
{
    $styles = null;

    if ($user['role'] < 3) {
        $styles = array(
            'desktop' => select_style(''),
            'mobile' => get_mobile_style_options(''));
    }

    respond(array(
        'status' => 'success',
        'request' => $type,
        'noindex_ready' => pg_page_noindex_ready(),
        'styles' => $styles));
}

// Every field is optional; only what the panel actually set gets
// written. Style fields follow the edit screens' role rule, the
// noindex/nofollow pair follows its schema probe, and each page is
// let through only with edit access to its folder. Whatever changed
// marks the SEO analysis stale, the same write the edit screens do.
function pg_explorer_handle_pages_bulk_edit($type, $request, $user)
{
    $items = isset($request['items']) ? $request['items'] : array();
    $set = (isset($request['set']) && is_array($request['set'])) ? $request['set'] : array();

    if ((is_array($items) == false) || (count($items) == 0) || (count($set) == 0)) {
        respond(array('status' => 'error', 'message' => lang('Invalid request.')));
    }

    $assignments = array();

    foreach (array('sitemap' => 'sitemap', 'search' => 'page_search', 'comments' => 'comments') as $field => $column) {
        if (isset($set[$field]) && in_array((string) $set[$field], array('0', '1'), true)) {
            $assignments[] = $column . " = '" . e($set[$field]) . "'";
        }
    }

    if (pg_page_noindex_ready()) {
        foreach (array('noindex', 'nofollow') as $field) {
            if (isset($set[$field]) && in_array((string) $set[$field], array('0', '1'), true)) {
                $assignments[] = $field . " = '" . e($set[$field]) . "'";
            }
        }
    }

    // The two style columns are held apart from the rest, because they
    // are not written to every page in the selection -- see below.
    $style_assignments = array();

    if ($user['role'] < 3) {
        if (isset($set['style']) && ($set['style'] !== '')) {
            $style_assignments[] = "page_style = '" . e((int) $set['style']) . "'";
        }

        if (isset($set['mobile_style_id']) && ($set['mobile_style_id'] !== '')) {
            $style_assignments[] = "mobile_style_id = '" . e((int) $set['mobile_style_id']) . "'";
        }
    }

    if ((count($assignments) == 0) && (count($style_assignments) == 0)) {
        respond(array('status' => 'error', 'message' => lang('Invalid request.')));
    }

    $allowed = array();
    $errors = array();

    foreach ($items as $item) {

        $item_id = (int) (isset($item['id']) ? $item['id'] : 0);

        if ($item_id <= 0) {
            continue;
        }

        $page = db_item("SELECT page_id, page_name, page_folder FROM page WHERE page_id = '" . e($item_id) . "'");

        if (!$page) {
            continue;
        }

        if (check_edit_access($page['page_folder']) == false) {
            $errors[] = lang(array('string' => 'Access denied for {var:1}.', 'vars' => $page['page_name']));
            continue;
        }

        $allowed[] = (int) $page['page_id'];
    }

    // A page the visual editor opens keeps its style out of this.
    //
    // Since the multi-page design swap, the style is not a skin that
    // can be exchanged underneath such a page: the page carries its own
    // layout tree and the style it points at owns the assets that tree
    // was built against. Pointing a batch of them at another style
    // leaves each one rendering a tree whose blocks, fonts and custom
    // CSS live somewhere else -- which is not "restyled", it is broken,
    // and nothing in a bulk panel can put it back.
    //
    // Two shapes count as such a page: one that carries a tree of its
    // own, and one whose style was made by the editor. The rest -- the
    // classic pages the style picker was written for -- are updated as
    // before.
    $style_targets = $allowed;
    $style_skipped = 0;

    if ((count($style_assignments) > 0) && (count($allowed) > 0)) {

        $own_tree = pg_multi_page_design_ready()
            ? " OR ((page.page_tree_json IS NOT NULL) AND (page.page_tree_json <> ''))"
            : '';

        $visual_ids = (array) db_values(
            "SELECT page.page_id
            FROM page
            LEFT JOIN style ON page.page_style = style.style_id
            WHERE (page.page_id IN (" . implode(',', $allowed) . "))
                AND ((style.style_layout = 'visual_designer')" . $own_tree . ")");

        $visual_ids = array_map('intval', $visual_ids);
        $style_targets = array_values(array_diff($allowed, $visual_ids));
        $style_skipped = count($allowed) - count($style_targets);
    }

    $audit_before = (count($allowed) > 0) ? pg_audit_fetch_many('page', $allowed) : array();

    if ((count($assignments) > 0) && (count($allowed) > 0)) {
        db(
            "UPDATE page
            SET
                " . implode(",\n                        ", $assignments) . ",
                seo_analysis_current = '0',
                page_timestamp = UNIX_TIMESTAMP(),
                page_user = '" . USER_ID . "'
            WHERE page_id IN (" . implode(',', $allowed) . ")");
    }

    if ((count($style_assignments) > 0) && (count($style_targets) > 0)) {
        db(
            "UPDATE page
            SET
                " . implode(",\n                        ", $style_assignments) . ",
                seo_analysis_current = '0',
                page_timestamp = UNIX_TIMESTAMP(),
                page_user = '" . USER_ID . "'
            WHERE page_id IN (" . implode(',', $style_targets) . ")");
    }

    pg_audit_record_many('update', 'page', $audit_before);

    if (count($allowed) > 0) {
        log_activity(((count($allowed) == 1) ? lang(array('string' => '{var:1} page was updated', 'vars' => 1)) : lang(array('string' => '{var:1} pages were updated', 'vars' => count($allowed)))), $_SESSION['sessionusername']);
    }

    // The skip note below is appended as its own sentence, so the
    // count has to end like one.
    $bulk_pages_message = pg_explorer_sentence_end(((count($allowed) == 1) ? lang(array('string' => '{var:1} page was updated', 'vars' => 1)) : lang(array('string' => '{var:1} pages were updated', 'vars' => count($allowed)))));

    // Said rather than left to be noticed: an operator who picked a
    // style and saw nothing change on half the list would go looking
    // for a bug.
    if ($style_skipped > 0) {
        $bulk_pages_message .= ' ' . lang(array(
            'string' => 'The style was left alone on {var:1} of them, because a page the visual editor opens carries its own layout.',
            'vars' => $style_skipped));
    }

    respond(array(
        'status' => pg_explorer_bulk_pages_status(count($allowed), $errors, $style_skipped),
        'request' => $type,
        'updated' => count($allowed),
        'style_skipped' => $style_skipped,
        'errors' => $errors,
        'message' => (count($errors) > 0) ? implode(' ', $errors) : $bulk_pages_message));
}

// The folder list is not here: explorer_folder_options already builds
// it, filtered to what this operator may write to, and the upload
// window and the file window both read it. One list, three panels.
function pg_explorer_handle_bulk_file_options($type, $request, $user)
{
    $bulk_image_settings = pg_image_settings();

    respond(array(
        'status' => 'success',
        'request' => $type,
        // The design flag is a designer's decision, the rule
        // edit_files.php applies and the one the right-click menu
        // already follows.
        'is_designer' => ($user['role'] <= 1),
        'resize_trigger' => (int) $bulk_image_settings['file_resize_trigger'],
        'resize_target' => (int) $bulk_image_settings['file_max_dimension']));
}

// What edit_files.php did to a checked list, on the selection: move to
// a folder, set or clear the design flag, and write a description.
// Every field is optional and untouched fields are not written.
//
// Optimizing is deliberately not here. The screen already has it --
// one endpoint, the progress bar and the per-file report the operator
// knows -- so the panel runs that afterwards rather than growing a
// second copy of it on this side.
function pg_explorer_handle_files_bulk_edit($type, $request, $user)
{
    $items = isset($request['items']) ? $request['items'] : array();
    $set = (isset($request['set']) && is_array($request['set'])) ? $request['set'] : array();

    if ((is_array($items) == false) || (count($items) == 0) || (count($set) == 0)) {
        respond(array('status' => 'error', 'message' => lang('Invalid request.')));
    }

    $assignments = array();
    $target_folder_id = 0;

    if (isset($set['folder_id']) && ((string) $set['folder_id'] !== '')) {

        $target_folder_id = (int) $set['folder_id'];

        // Checked once for the whole run rather than per file: the
        // destination is the same for all of them, and a refusal is
        // about the folder, not about any one file.
        if (($target_folder_id <= 0) || (check_edit_access($target_folder_id) == false) || pg_recycle_is_inside($target_folder_id)) {
            respond(array('status' => 'error', 'message' => lang('You do not have access to move files to the folder that you selected')));
        }

        $assignments[] = "folder = '" . e($target_folder_id) . "'";
    }

    // Only a designer may change it, and the switch is not drawn for
    // anyone else -- so for anyone else it is simply not read.
    if (($user['role'] <= 1) && isset($set['design']) && in_array((string) $set['design'], array('0', '1'), true)) {
        $assignments[] = "design = '" . e($set['design']) . "'";
    }

    // Three states, not two: leave it alone, write this text, or empty
    // it. "Replace with nothing" and "do not touch" are different
    // instructions and a blank box cannot tell them apart on its own.
    if (isset($set['description_mode'])) {

        if ((string) $set['description_mode'] === 'clear') {
            $assignments[] = "description = ''";

        } elseif ((string) $set['description_mode'] === 'set') {
            $assignments[] = "description = '" . e(isset($set['description']) ? (string) $set['description'] : '') . "'";
        }
    }

    if (count($assignments) == 0) {
        respond(array('status' => 'error', 'message' => lang('Invalid request.')));
    }

    $allowed = array();
    $errors = array();

    foreach ($items as $item) {

        $item_id = (int) (isset($item['id']) ? $item['id'] : 0);

        if ($item_id <= 0) {
            continue;
        }

        $file = db_item("SELECT id, name, folder, design FROM files WHERE id = '" . e($item_id) . "'");

        if (!$file) {
            continue;
        }

        // The two rules every file action applies: edit rights on the
        // folder it sits in, and designer rank for a design file.
        if (
            (check_edit_access($file['folder']) == false)
            || (($file['design'] == 1) && ($user['role'] > 1))
        ) {
            $errors[] = lang(array('string' => 'Access denied for {var:1}.', 'vars' => $file['name']));
            continue;
        }

        $allowed[] = (int) $file['id'];
    }

    if (count($allowed) > 0) {
        $audit_before = pg_audit_fetch_many('files', $allowed);

        db(
            "UPDATE files
            SET
                " . implode(",\n                        ", $assignments) . ",
                timestamp = UNIX_TIMESTAMP(),
                user = '" . e($user['id']) . "'
            WHERE id IN (" . implode(',', $allowed) . ")");

        pg_audit_record_many('update', 'files', $audit_before);

        log_activity(((count($allowed) == 1) ? lang(array('string' => '{var:1} file was updated', 'vars' => 1)) : lang(array('string' => '{var:1} files were updated', 'vars' => count($allowed)))), $_SESSION['sessionusername']);
    }

    respond(array(
        'status' => (count($errors) == 0) ? 'success' : ((count($allowed) > 0) ? 'partial' : 'error'),
        'request' => $type,
        'updated' => count($allowed),
        'errors' => $errors,
        'message' => (count($errors) > 0)
            ? implode(' ', $errors)
            : ((count($allowed) == 1) ? lang(array('string' => '{var:1} file was updated', 'vars' => 1)) : lang(array('string' => '{var:1} files were updated', 'vars' => count($allowed))))));
}
