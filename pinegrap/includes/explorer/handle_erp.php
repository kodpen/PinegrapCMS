<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The file manager's read-only ERP documents sub-action: the tree and the
 * listings. The handler answers through respond(), which ends the
 * request.
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

// The search an ERP listing runs: trimmed, and cut to 100 characters.
function pg_explorer_erp_search_term($request)
{
    $erp_search = isset($request['search']) ? trim((string) $request['search']) : '';

    if (mb_strlen($erp_search) > 100) {
        $erp_search = mb_substr($erp_search, 0, 100);
    }

    return $erp_search;
}

// The tree alone (for the sidebar), or one listing: the virtual
// folders at the top, one folder's documents, or a search. At the top
// a search runs across every folder, the way the module's own lists
// cannot. Nothing is generated here: a document is rendered or fetched
// from the provider only when it is opened.
function pg_explorer_handle_erp_tree_list($type, $request, $user)
{
    if (pg_explorer_erp_ready($user) == false) {
        respond(array('status' => 'error', 'request' => $type, 'message' => lang('Access denied')));
    }

    $erp_tree = pg_explorer_erp_tree();

    if ($type == 'explorer_erp_tree') {
        respond(array('status' => 'success', 'request' => $type, 'tree' => $erp_tree));
    }

    if (isset($request['view_type']) && in_array($request['view_type'], array('grid', 'list'), true)) {
        $_SESSION['software']['explorer']['folder']['view_type'] = $request['view_type'];
    }

    $view_type = ($_SESSION['software']['explorer']['folder']['view_type'] ?? 'grid');

    if (in_array($view_type, array('grid', 'list'), true) == false) {
        $view_type = 'grid';
    }

    // Counting and listing do not touch the session; let the next
    // request of this screen in while they run.
    pg_explorer_release_session();

    $erp_labels = array();
    $erp_notes = array();

    foreach ($erp_tree as $node) {
        $erp_labels[$node['key']] = $node['label'];
        $erp_notes[$node['key']] = $node['note'];
    }

    $erp_folder = isset($request['folder']) ? (string) $request['folder'] : '';

    if (isset($erp_labels[$erp_folder]) == false) {
        $erp_folder = '';
    }

    $erp_search = pg_explorer_erp_search_term($request);

    $erp_offset = max(0, (int) ($request['offset'] ?? 0));
    $erp_page = 200;
    $erp_folders = array();
    $erp_files = array();
    $erp_total = 0;
    $index = 0;

    if ($erp_folder != '') {

        $found = erp_files_list($erp_folder, array('search' => $erp_search, 'limit' => $erp_page, 'offset' => $erp_offset));
        $erp_total = (int) $found['total'];
        $index = $erp_offset;

        foreach ($found['items'] as $entry) {
            $erp_files[] = pg_explorer_erp_file_item($entry, $erp_folder, $erp_labels[$erp_folder], ++$index, $user);
        }

    } elseif ($erp_search != '') {

        // Every folder that holds a match, up to fifty from each; the
        // item says which folder it was found in.
        foreach (erp_files_search($erp_search, 50) as $entry) {
            $entry_folder = (string) ($entry['folder'] ?? '');
            $erp_files[] = pg_explorer_erp_file_item($entry, $entry_folder, $erp_labels[$entry_folder] ?? '', ++$index, $user);
        }

        $erp_total = count($erp_files);

    } else {

        foreach ($erp_tree as $node) {
            $erp_folders[] = pg_explorer_erp_folder_item($node, ++$index);
        }

        $erp_total = count($erp_folders);
    }

    respond(array(
        'status' => 'success',
        'request' => $type,
        'folder' => $erp_folder,
        'folder_label' => ($erp_folder != '') ? $erp_labels[$erp_folder] : '',
        'folder_note' => ($erp_folder != '') ? $erp_notes[$erp_folder] : '',
        'search' => $erp_search,
        'offset' => $erp_offset,
        'total' => $erp_total,
        'view_type' => $view_type,
        'tree' => $erp_tree,
        'folders' => $erp_folders,
        'files' => $erp_files,
        'capabilities' => array(
            'role' => (int) $user['role'],
            'is_manager' => ($user['role'] <= 2),
            'is_designer' => ($user['role'] <= 1),
            'show_product_images' => (bool) ECOMMERCE_SHOW_PRODUCT_IMAGES)));
}
