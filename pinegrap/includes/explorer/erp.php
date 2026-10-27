<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP documents in the explorer: the file-id sub-actions that refuse ERP
 * documents, the ids a request names, the ERP module's read-only seam, and
 * the virtual folder and file rows the listing draws. Loaded by
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

// The ERP module's documents are shown here as read-only virtual folders. All
// of it is read through includes/erp/files.php: what counts as a draft, a
// cancelled invoice or an official e-document is decided there, and nothing
// in this file queries the module's tables.

// The sub-actions that change or read a file by its id. The documents the ERP
// module keeps are `files` rows too (no folder, erp_doc_type set), so each of
// these refuses them before it runs -- see pg_files_include_erp_document().
function pg_explorer_file_id_actions()
{
    return array(
        'explorer_rename', 'explorer_move', 'explorer_paste', 'explorer_delete_files',
        'explorer_recycle_delete', 'explorer_recycle_restore', 'explorer_hard_delete',
        'explorer_optimize', 'explorer_webp', 'explorer_files_design', 'explorer_files_bulk_edit',
        'explorer_file_get', 'explorer_file_save', 'explorer_rotate', 'explorer_file_usage',
        'explorer_zip_create', 'explorer_zip_extract');
}

// The file ids one of those sub-actions names. An item without a kind is a
// file only where the sub-action acts on nothing but files.
function pg_explorer_request_file_ids($type, $request)
{
    $ids = array();
    $files_only = in_array($type, array('explorer_optimize', 'explorer_webp', 'explorer_files_design', 'explorer_files_bulk_edit'), true);

    if (isset($request['items']) && is_array($request['items'])) {
        foreach ($request['items'] as $item) {

            if ((is_array($item) == false) || (isset($item['id']) == false)) {
                continue;
            }

            $kind = isset($item['kind']) ? (string) $item['kind'] : '';

            if (($kind === 'file') || (($kind === '') && $files_only)) {
                $ids[] = (int) $item['id'];
            }
        }
    }

    if (isset($request['file_ids']) && is_array($request['file_ids'])) {
        foreach ($request['file_ids'] as $file_id) {
            $ids[] = (int) $file_id;
        }
    }

    if (isset($request['file_id']) && is_scalar($request['file_id'])) {
        $ids[] = (int) $request['file_id'];
    }

    if (isset($request['id']) && is_scalar($request['id']) && in_array($type, array('explorer_file_get', 'explorer_file_save', 'explorer_rotate'), true)) {
        $ids[] = (int) $request['id'];
    }

    if (isset($request['item_id']) && is_scalar($request['item_id'])) {

        $item_kind = isset($request['item_kind']) ? (string) $request['item_kind'] : '';

        if (($item_kind === 'file') || ($type === 'explorer_zip_extract')) {
            $ids[] = (int) $request['item_id'];
        }
    }

    return $ids;
}

// Loads the ERP module's read-only seam. False when the module is off, not
// installed, or this person has no ERP right -- the caller then leaves the
// documents out instead of printing an error page.
function pg_explorer_erp_ready($user)
{
    if ((defined('ERP_ENABLED') == false) || (ERP_ENABLED == false)) {
        return false;
    }

    if ((is_file(PG_FUNCTIONS_DIR . '/includes/erp/bootstrap.php') == false) || (is_file(PG_FUNCTIONS_DIR . '/includes/erp/files.php') == false)) {
        return false;
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/erp/bootstrap.php');
    require_once(PG_FUNCTIONS_DIR . '/includes/erp/files.php');

    return erp_files_allowed($user);
}

// The virtual folders below the ERP root, with their live counts.
function pg_explorer_erp_tree()
{
    $tree = array();

    foreach (erp_files_folders() as $key => $folder) {

        if ($key === 'erp') {
            continue;
        }

        $tree[] = array(
            'key' => (string) $key,
            'label' => (string) $folder['label'],
            'icon' => (string) $folder['icon'],
            'count' => (int) $folder['count'],
            'note' => (string) $folder['note']);
    }

    return $tree;
}

// One virtual folder, dressed as a folder row so the grid, the list, the sort
// and the selection draw it with the code they already have. The id is
// negative on purpose: no folder action and no drop target accepts one, and it
// cannot be mistaken for a real folder id.
function pg_explorer_erp_folder_item($node, $index)
{
    return array(
        'kind' => 'folder',
        'id' => -1 * (int) $index,
        'erp' => true,
        'erp_folder' => $node['key'],
        'erp_icon' => $node['icon'],
        'note' => $node['note'],
        'name' => $node['label'],
        'own_access_control_type' => '',
        'access_control_type' => 'private',
        'access_icon' => pg_explorer_access_icon('private'),
        'archived' => false,
        'is_root' => false,
        'parent_id' => 0,
        'order' => 0,
        'style_name' => '',
        'counts' => array('folders' => 0, 'pages' => 0, 'files' => (int) $node['count']),
        'empty' => ((int) $node['count'] == 0),
        'timestamp' => 0,
        'modified' => '',
        'username' => '',
        'can_edit' => false);
}

// One document from includes/erp/files.php, dressed as a file row. Its own
// fields ride along untouched; 'kind' becomes 'file' (the ERP kind moves to
// erp_kind), the id is negative and unique within the listing (the real id is
// erp_id -- an invoice can be listed twice, as the store's PDF and as the
// e-document), and can_edit is false, which is what keeps every editing
// action off it. The addresses of the related screens are worked out here so
// the panel and the menu only have to draw them.
function pg_explorer_erp_file_item($entry, $folder_key, $folder_label, $index, $user)
{
    $panel = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/';
    $size = (($entry['size'] === null) || ($entry['size'] === '')) ? null : (int) $entry['size'];
    $date = (string) ($entry['date'] ?? '');
    $item = $entry;

    $item['kind'] = 'file';
    $item['id'] = -1 * (int) $index;
    $item['erp'] = true;
    $item['erp_kind'] = (string) $entry['kind'];
    $item['erp_id'] = (int) $entry['id'];
    $item['erp_folder'] = (string) $folder_key;
    $item['folder_id'] = 0;
    $item['folder_name'] = (string) $folder_label;
    $item['type'] = mb_strtolower((string) ($entry['type'] ?? ''));
    $item['is_image'] = false;
    $item['size'] = ($size === null) ? 0 : $size;
    $item['size_known'] = ($size !== null);
    $item['size_label'] = ($size === null) ? '' : convert_bytes_to_string($size);
    $item['design'] = false;
    $item['optimized'] = false;
    $item['image_width'] = 0;
    $item['image_height'] = 0;
    $item['description'] = (string) ($entry['title'] ?? '');
    $item['access_control_type'] = 'private';
    $item['access_icon'] = pg_explorer_access_icon('private');
    $item['archived'] = false;
    $item['can_edit'] = false;
    $item['date_label'] = (($date !== '') && ($date !== '0000-00-00')) ? prepare_form_data_for_output($date, 'date') : '';

    if (isset($entry['ship_date']) && ((string) $entry['ship_date'] !== '') && ((string) $entry['ship_date'] !== '0000-00-00')) {
        $item['ship_date_label'] = prepare_form_data_for_output((string) $entry['ship_date'], 'date');
    }

    if (!empty($entry['kept_at'])) {
        $item['kept_label'] = get_relative_time(array('timestamp' => (int) $entry['kept_at']));
    }

    $item['account_url'] = ((int) ($entry['account_id'] ?? 0) > 0) ? ($panel . 'edit_erp_account.php?id=' . (int) $entry['account_id']) : '';

    // The order screen is a commerce screen and asks for commerce rights at
    // its own door; a link that ends in "access denied" is not offered.
    $item['order_url'] = (((int) ($entry['order_id'] ?? 0) > 0) && (($user['role'] < 3) || !empty($user['manage_ecommerce'])))
        ? ($panel . 'view_order.php?id=' . (int) $entry['order_id'])
        : '';

    $item['invoice_url'] = ((int) ($entry['invoice_id'] ?? 0) > 0) ? ($panel . 'edit_erp_invoice.php?id=' . (int) $entry['invoice_id']) : '';

    return $item;
}
