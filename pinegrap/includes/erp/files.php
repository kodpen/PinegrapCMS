<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - the module's documents as file-manager entries.
 *
 * An issued invoice or delivery note is a database row, and since 4.65 also
 * the PDF it was issued with, kept in the file directory as a `files` row
 * with no folder and files.erp_doc_type / erp_doc_id naming the document
 * (includes/erp/archive.php). The provider's copy of an accepted e-document
 * is kept the first time it is opened, and every e-mailed reconciliation
 * letter is kept with a row in erp_reconciliation_log. A document issued
 * before any of that has no copy until it is first opened. This file is the
 * one place that turns all of it into entries a file manager can list,
 * search and open: a folder tree, an item payload in the shape of the file
 * manager's own file rows, and the addresses that open the document.
 *
 * It only reads. Opening, printing and downloading go through the existing
 * endpoints (get_erp_invoice_pdf.php, get_erp_waybill_pdf.php,
 * get_erp_edoc_document.php), which keep their own gates; renaming, moving
 * and deleting do not apply - an issued document keeps its number, and a
 * cancelled one stays in the register.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_ERP_ENTRY')) {
    exit;
}

if (!defined('ERP_FILES_MAX_ROWS')) {
    define('ERP_FILES_MAX_ROWS', 500);
}

/**
 * Whether this person sees the module's documents at all. The same question
 * validate_erp_access() asks, without printing an error page: a file manager
 * leaves the folder out instead.
 *
 * @param array $user
 * @return bool
 */
function erp_files_allowed($user)
{
    if (!defined('ERP_ENABLED') || !ERP_ENABLED) {
        return false;
    }

    return ((int) ($user['role'] ?? 9) < 3) || !empty($user['manage_erp']);
}

/**
 * The virtual folders, root first. Counts are live.
 *
 * @return array  key => ['key', 'label', 'parent', 'icon', 'count', 'note']
 */
function erp_files_folders()
{
    $folders = array(
        'erp' => array('label' => lang('ERP'), 'parent' => '', 'icon' => 'bi-safe2', 'note' => ''),
        'sales_invoices' => array('label' => lang('Sales invoices'), 'parent' => 'erp', 'icon' => 'bi-receipt', 'note' => ''),
        'sales_returns' => array('label' => lang('Return invoices'), 'parent' => 'erp', 'icon' => 'bi-arrow-counterclockwise', 'note' => ''),
        'purchase_invoices' => array('label' => lang('Purchase invoices'), 'parent' => 'erp', 'icon' => 'bi-bag', 'note' => ''),
        'edocs' => array('label' => lang('e-Documents'), 'parent' => 'erp', 'icon' => 'bi-send-check', 'note' => lang('The copy the provider keeps, with the GİB number; fetched from the provider when opened.')),
        'waybills' => array('label' => lang('Delivery Notes'), 'parent' => 'erp', 'icon' => 'bi-truck', 'note' => ''),
        'ewaybills' => array('label' => lang('e-Delivery Notes'), 'parent' => 'erp', 'icon' => 'bi-send', 'note' => lang('No e-document provider sends e-Delivery Notes yet.')),
        'reconciliations' => array('label' => lang('Reconciliation letters'), 'parent' => 'erp', 'icon' => 'bi-envelope-paper', 'note' => lang('Every letter that was e-mailed, as it went.')),
        'drafts' => array('label' => lang('Draft invoices'), 'parent' => 'erp', 'icon' => 'bi-pencil-square', 'note' => lang('A draft has no number and no document yet; it opens in the editor.')),
        'expenses' => array('label' => lang('Expense receipts'), 'parent' => 'erp', 'icon' => 'bi-receipt-cutoff', 'note' => lang('The picture or PDF kept with each expense. An expense with no file kept is not listed here.')),
    );

    // Only for a store whose expense tables are there (4.95).
    if (!function_exists('erp_expenses_ready') || !erp_expenses_ready()) {
        unset($folders['expenses']);
    }

    foreach ($folders as $key => $folder) {
        $folders[$key]['key'] = $key;
        $folders[$key]['count'] = ($key === 'erp') ? 0 : erp_files_count($key);
    }

    // The e-document folders only for a store that works with e-documents,
    // or holds some from before.
    foreach (array('edocs', 'ewaybills') as $key) {
        if (!erp_edoc_in_use() && ((int) $folders[$key]['count'] === 0)) {
            unset($folders[$key]);
        }
    }

    return $folders;
}

/**
 * The WHERE of one folder, over erp_invoices i or erp_waybills w.
 *
 * @param string $folder
 * @return array ['table' => 'invoice'|'waybill'|'', 'where' => string]
 */
function erp_files_folder_scope($folder)
{
    switch ((string) $folder) {
        case 'sales_invoices':
            return array('table' => 'invoice', 'where' => "i.direction = 'sales' AND i.doc_type = 'invoice' AND i.status <> 'draft'");
        case 'sales_returns':
            return array('table' => 'invoice', 'where' => "i.doc_type = 'return' AND i.status <> 'draft'");
        case 'purchase_invoices':
            return array('table' => 'invoice', 'where' => "i.direction = 'purchase' AND i.doc_type = 'invoice' AND i.status <> 'draft'");
        case 'drafts':
            return array('table' => 'invoice', 'where' => "i.status = 'draft'");
        case 'edocs':
            // Only a document the provider has handed to GİB has an official
            // copy; a draft at the provider is not one.
            if (!erp_edoc_installed()) {
                return array('table' => '', 'where' => '');
            }
            return array('table' => 'edoc', 'where' => "i.edoc_provider <> '' AND i.edoc_external_id <> '' AND i.edoc_status IN ('sent', 'accepted', 'rejected')");
        case 'waybills':
            return array('table' => 'waybill', 'where' => "w.status <> 'draft'");
        case 'ewaybills':
            if (!erp_edoc_installed()) {
                return array('table' => '', 'where' => '');
            }
            return array('table' => 'ewaybill', 'where' => "w.edoc_provider <> '' AND w.edoc_external_id <> ''");
        case 'reconciliations':
            if (!function_exists('erp_archive_ready') || !erp_archive_ready() || !waf_table_has_column('erp_reconciliation_log', 'account_id')) {
                return array('table' => '', 'where' => '');
            }
            return array('table' => 'reconciliation', 'where' => '1 = 1');
        case 'expenses':
            // An expense is listed by its kept file, so only one that has one.
            if (!function_exists('erp_archive_ready') || !erp_archive_ready() || !function_exists('erp_expenses_ready') || !erp_expenses_ready()) {
                return array('table' => '', 'where' => '');
            }
            return array('table' => 'expense', 'where' => "EXISTS (SELECT 1 FROM files ef WHERE ef.erp_doc_type = 'expense' AND ef.erp_doc_id = e.id)");
    }

    return array('table' => '', 'where' => '');
}

/**
 * The search condition for one kind of row.
 *
 * @param string $table  'invoice' (also for 'edoc') or 'waybill'
 * @param string $query
 * @return string  Starts with AND, or ''
 */
function erp_files_search_sql($table, $query)
{
    $query = trim((string) $query);

    if ($query === '') {
        return '';
    }

    $like = "'%" . escape(addcslashes($query, '%_\\')) . "%'";

    if ($table === 'waybill') {
        $columns = array('w.full_number', 'w.ship_to_title', 'w.ship_to_city', 'w.carrier_title', 'w.plate', 'a.title', 'o.order_number');
    } elseif ($table === 'reconciliation') {
        $columns = array('r.reference', 'r.sent_to', 'a.title', 'a.tax_number');
    } elseif ($table === 'expense') {
        $columns = array('e.supplier', 'e.supplier_tax_number', 'e.document_no', 'e.description', 'c.name');
    } else {
        $columns = array('i.full_number', 'i.gib_number', 'i.gib_uuid', 'i.account_title', 'i.account_tax_number', 'i.supplier_invoice_no', 'a.title', 'o.order_number');
    }

    $parts = array();
    foreach ($columns as $column) {
        $parts[] = $column . ' LIKE ' . $like;
    }

    return ' AND (' . implode(' OR ', $parts) . ')';
}

/**
 * How many documents one folder holds.
 *
 * @param string $folder
 * @return int
 */
function erp_files_count($folder)
{
    $scope = erp_files_folder_scope($folder);

    if ($scope['table'] === '') {
        return 0;
    }

    if ($scope['table'] === 'reconciliation') {
        return (int) db_value("SELECT COUNT(*) FROM erp_reconciliation_log r WHERE " . $scope['where']);
    }

    if ($scope['table'] === 'expense') {
        return (int) db_value("SELECT COUNT(*) FROM erp_expenses e WHERE " . $scope['where']);
    }

    return (in_array($scope['table'], array('waybill', 'ewaybill'), true))
        ? (int) db_value("SELECT COUNT(*) FROM erp_waybills w WHERE " . $scope['where'])
        : (int) db_value("SELECT COUNT(*) FROM erp_invoices i WHERE " . $scope['where']);
}

/**
 * One folder's documents, newest first.
 *
 * @param string $folder   A key of erp_files_folders()
 * @param array  $options  search (string), year (int), month (1-12),
 *                         limit (default 100, at most ERP_FILES_MAX_ROWS), offset
 * @return array ['items' => [...], 'total' => int]  items: see erp_files_invoice_payload()
 */
function erp_files_list($folder, $options = array())
{
    $scope = erp_files_folder_scope($folder);

    if ($scope['table'] === '') {
        return array('items' => array(), 'total' => 0);
    }

    $limit = max(1, min((int) ($options['limit'] ?? 100), ERP_FILES_MAX_ROWS));
    $offset = max(0, (int) ($options['offset'] ?? 0));

    if ($scope['table'] === 'expense') {
        return erp_files_expense_list($scope['where'], $options, $limit, $offset);
    }

    $is_waybill = in_array($scope['table'], array('waybill', 'ewaybill'), true);
    $is_letter = ($scope['table'] === 'reconciliation');
    $alias = $is_waybill ? 'w' : ($is_letter ? 'r' : 'i');
    $where = $scope['where'] . erp_files_search_sql($is_waybill ? 'waybill' : ($is_letter ? 'reconciliation' : 'invoice'), (string) ($options['search'] ?? ''));

    // The kept copy, when there is one: its size is the file's, and the entry
    // says it opens the document as it was issued.
    $kept_type = array('waybill' => 'waybill', 'ewaybill' => 'waybill', 'edoc' => 'edoc', 'reconciliation' => 'reconciliation');
    $kept_type = $kept_type[$scope['table']] ?? 'invoice';
    $kept_join = (function_exists('erp_archive_ready') && erp_archive_ready())
        ? " LEFT JOIN files kf ON kf.erp_doc_type = '" . $kept_type . "' AND kf.erp_doc_id = " . $alias . ".id"
        : '';
    $kept_columns = ($kept_join !== '') ? ', kf.id AS kept_file_id, kf.name AS kept_file_name, kf.size AS kept_size, kf.timestamp AS kept_at' : '';

    $year = (int) ($options['year'] ?? 0);
    $date_column = $is_letter ? 'r.as_of' : ($alias . '.issue_date');
    if ($year > 0) {
        $where .= " AND YEAR(" . $date_column . ") = '" . $year . "'";
        $month = (int) ($options['month'] ?? 0);
        if (($month >= 1) && ($month <= 12)) {
            $where .= " AND MONTH(" . $date_column . ") = '" . $month . "'";
        }
    }

    if ($is_letter) {
        $from = "FROM erp_reconciliation_log r
            LEFT JOIN erp_accounts a ON a.id = r.account_id
            LEFT JOIN user u ON u.user_id = r.created_by" . $kept_join . "
            WHERE " . $where;
        $rows = (array) db_items("SELECT r.*, a.title AS account_title, a.tax_number, u.user_username" . $kept_columns . " " . $from . "
            ORDER BY r.created_at DESC, r.id DESC LIMIT " . $offset . ", " . $limit);
        $total = (int) db_value("SELECT COUNT(*) " . $from);
    } elseif ($is_waybill) {
        $from = "FROM erp_waybills w
            LEFT JOIN erp_accounts a ON a.id = w.account_id
            LEFT JOIN orders o ON o.id = w.order_id
            LEFT JOIN erp_invoices inv ON inv.id = w.invoice_id
            LEFT JOIN user u ON u.user_id = w.created_by" . $kept_join . "
            WHERE " . $where;
        $rows = (array) db_items("SELECT w.*, a.title AS live_account_title, o.order_number, inv.full_number AS invoice_number, u.user_username" . $kept_columns . " " . $from . "
            ORDER BY w.issue_date DESC, w.id DESC LIMIT " . $offset . ", " . $limit);
        $total = (int) db_value("SELECT COUNT(*) " . $from);
    } else {
        $from = "FROM erp_invoices i
            LEFT JOIN erp_accounts a ON a.id = i.account_id
            LEFT JOIN orders o ON o.id = i.order_id
            LEFT JOIN user u ON u.user_id = i.created_by" . $kept_join . "
            WHERE " . $where;
        $rows = (array) db_items("SELECT i.*, a.title AS live_account_title, o.order_number, u.user_username" . $kept_columns . " " . $from . "
            ORDER BY i.issue_date DESC, i.id DESC LIMIT " . $offset . ", " . $limit);
        $total = (int) db_value("SELECT COUNT(*) " . $from);
    }

    $items = array();
    foreach ($rows as $row) {
        if ($scope['table'] === 'reconciliation') {
            $items[] = erp_files_letter_payload($row);
        } elseif ($scope['table'] === 'waybill') {
            $items[] = erp_files_waybill_payload($row, false);
        } elseif ($scope['table'] === 'ewaybill') {
            $items[] = erp_files_waybill_payload($row, true);
        } else {
            $items[] = erp_files_invoice_payload($row, $scope['table'] === 'edoc');
        }
    }

    return array('items' => $items, 'total' => $total);
}

/**
 * The expenses that have a kept receipt, newest first. Each is listed once,
 * by the file in use: a receipt that was replaced keeps its earlier file
 * with the expense, and only the expense's own screen lists that one.
 *
 * @param string $where    From erp_files_folder_scope()
 * @param array  $options  search, year, month
 * @param int    $limit
 * @param int    $offset
 * @return array ['items' => [...], 'total' => int]
 */
function erp_files_expense_list($where, $options, $limit, $offset)
{
    $where .= erp_files_search_sql('expense', (string) ($options['search'] ?? ''));

    $year = (int) ($options['year'] ?? 0);
    if ($year > 0) {
        $where .= " AND YEAR(e.expense_date) = '" . $year . "'";
        $month = (int) ($options['month'] ?? 0);
        if (($month >= 1) && ($month <= 12)) {
            $where .= " AND MONTH(e.expense_date) = '" . $month . "'";
        }
    }

    $from = "FROM erp_expenses e
        JOIN files kf ON kf.id = (SELECT MAX(f2.id) FROM files f2 WHERE f2.erp_doc_type = 'expense' AND f2.erp_doc_id = e.id)
        LEFT JOIN erp_expense_categories c ON c.id = e.category_id
        LEFT JOIN user u ON u.user_id = kf.user
        WHERE " . $where;

    $rows = (array) db_items("SELECT e.*, c.name AS category_name, c.code AS category_code, u.user_username,
            kf.id AS kept_file_id, kf.name AS kept_file_name, kf.type AS kept_type, kf.size AS kept_size, kf.timestamp AS kept_at,
            (SELECT COUNT(*) FROM files f3 WHERE f3.erp_doc_type = 'expense' AND f3.erp_doc_id = e.id) AS kept_versions
        " . $from . "
        ORDER BY e.expense_date DESC, e.id DESC LIMIT " . (int) $offset . ", " . (int) $limit);

    $items = array();
    foreach ($rows as $row) {
        $items[] = erp_files_expense_payload($row);
    }

    return array('items' => $items, 'total' => (int) db_value("SELECT COUNT(*) " . $from));
}

/**
 * A search across every folder: a number, a GİB number or ETTN, a buyer's
 * name or tax number, an order number, a carrier or a plate.
 *
 * @param string $query
 * @param int    $limit  Per folder
 * @return array  items, each with its 'folder'
 */
function erp_files_search($query, $limit = 50)
{
    if (trim((string) $query) === '') {
        return array();
    }

    $items = array();
    foreach (array_keys(erp_files_folders()) as $folder) {
        if ($folder === 'erp') {
            continue;
        }
        $found = erp_files_list($folder, array('search' => $query, 'limit' => $limit));
        foreach ($found['items'] as $item) {
            $item['folder'] = $folder;
            $items[] = $item;
        }
    }

    return $items;
}

/**
 * One invoice row as a file-manager entry.
 *
 * @param array $row      erp_invoices row, with live_account_title,
 *                        order_number and user_username joined
 * @param bool  $as_edoc  The provider's copy rather than the store's PDF
 * @return array
 */
function erp_files_invoice_payload($row, $as_edoc = false)
{
    $id = (int) $row['id'];
    $is_draft = ((string) $row['status'] === 'draft');
    $number = $is_draft ? '' : (string) $row['full_number'];
    $currency = strtoupper(trim((string) $row['currency']));
    $account_title = (trim((string) ($row['account_title'] ?? '')) !== '') ? (string) $row['account_title'] : (string) ($row['live_account_title'] ?? '');
    $safe_number = preg_replace('/[^A-Za-z0-9._-]+/', '_', $number);
    $states = array(
        'draft' => lang('Draft'),
        'issued' => lang('Issued'),
        'partially_paid' => lang('Partly paid'),
        'paid' => lang('Paid'),
        'cancelled' => lang('Cancelled'),
    );
    $edoc_labels = function_exists('erp_edoc_status_labels') ? erp_edoc_status_labels() : array();
    $edoc_status = (string) ($row['edoc_status'] ?? 'none');
    $timestamp = max((int) $row['created_at'], (int) $row['updated_at']);
    $screen = ($is_draft ? 'edit_erp_invoice_draft.php?id=' : 'edit_erp_invoice.php?id=') . $id;

    if ($as_edoc) {
        $kind = 'erp_edoc';
        $gib = trim((string) ($row['gib_number'] ?? ''));
        $name = (($gib !== '') ? $gib : $safe_number) . '.pdf';
        $url = 'get_erp_edoc_document.php?id=' . $id . '&format=pdf';
        $download_url = $url . '&download=1';
        $source = 'provider';
    } else {
        $kind = 'erp_invoice';
        $name = $is_draft ? (lang('Draft') . ' #' . $id) : ((($safe_number !== '') ? $safe_number : ('invoice_' . $id)) . '.pdf');
        $url = $is_draft ? $screen : ('get_erp_invoice_pdf.php?id=' . $id);
        $download_url = $is_draft ? '' : ($url . '&download=1');
        $source = $is_draft ? 'draft' : 'generated';
    }

    return array(
        'kind' => $kind,
        'id' => $id,
        'name' => $name,
        'title' => trim($number . (($account_title !== '') ? ' — ' . $account_title : '')),
        'type' => $is_draft ? '' : 'pdf',
        'size' => !empty($row['kept_file_id']) ? (int) $row['kept_size'] : null,
        'kept' => !empty($row['kept_file_id']),
        'kept_at' => !empty($row['kept_file_id']) ? (int) $row['kept_at'] : 0,
        'source' => !empty($row['kept_file_id']) ? 'kept' : $source,
        'number' => $number,
        'doc_class' => ((string) $row['doc_type'] === 'return') ? 'return' : (((string) $row['direction'] === 'purchase') ? 'purchase' : 'sales'),
        'date' => (string) $row['issue_date'],
        'account_id' => (int) $row['account_id'],
        'account_title' => $account_title,
        'tax_number' => (string) ($row['account_tax_number'] ?? ''),
        'order_id' => (int) $row['order_id'],
        'order_number' => (string) ($row['order_number'] ?? ''),
        'total' => (int) $row['grand_total'],
        'total_label' => erp_money_out_currency((int) $row['grand_total'], $currency),
        'currency' => $currency,
        'status' => (string) $row['status'],
        'status_label' => $states[$row['status']] ?? (string) $row['status'],
        'cancelled' => ((string) $row['status'] === 'cancelled'),
        'edoc_status' => $edoc_status,
        'edoc_status_label' => isset($edoc_labels[$edoc_status]) ? $edoc_labels[$edoc_status][0] : $edoc_status,
        'edoc_kind' => (string) ($row['edoc_kind'] ?? 'none'),
        'edoc_provider' => (string) ($row['edoc_provider'] ?? ''),
        'gib_number' => (string) ($row['gib_number'] ?? ''),
        'ettn' => (string) ($row['gib_uuid'] ?? ''),
        'timestamp' => $timestamp,
        'modified' => ($timestamp > 0) ? get_relative_time(array('timestamp' => $timestamp)) : '',
        'username' => (string) ($row['user_username'] ?? ''),
        'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/' . $url,
        'download_url' => ($download_url !== '') ? OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/' . $download_url : '',
        'edit_url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/' . $screen,
    );
}

/**
 * One delivery note row as a file-manager entry.
 *
 * @param array $row      erp_waybills row, with live_account_title,
 *                        order_number, invoice_number and user_username
 * @param bool  $as_edoc  The e-Delivery Note rather than the store's PDF
 * @return array
 */
function erp_files_waybill_payload($row, $as_edoc = false)
{
    $id = (int) $row['id'];
    $number = (string) $row['full_number'];
    $safe_number = preg_replace('/[^A-Za-z0-9._-]+/', '_', $number);
    $timestamp = max((int) $row['created_at'], (int) $row['updated_at']);
    $states = array('draft' => lang('Draft'), 'issued' => lang('Issued'), 'cancelled' => lang('Cancelled'));
    $url = 'get_erp_waybill_pdf.php?id=' . $id;

    return array(
        'kind' => $as_edoc ? 'erp_ewaybill' : 'erp_waybill',
        'id' => $id,
        'name' => (($safe_number !== '') ? $safe_number : ('waybill_' . $id)) . '.pdf',
        'title' => trim($number . ' — ' . (string) $row['ship_to_title']),
        'type' => 'pdf',
        'size' => !empty($row['kept_file_id']) ? (int) $row['kept_size'] : null,
        'kept' => !empty($row['kept_file_id']),
        'kept_at' => !empty($row['kept_file_id']) ? (int) $row['kept_at'] : 0,
        // No driver hands an e-Delivery Note back yet; until one does, the
        // entry opens the store's own PDF of the same note.
        'source' => !empty($row['kept_file_id']) ? 'kept' : 'generated',
        'number' => $number,
        'doc_class' => 'waybill',
        'date' => (string) $row['issue_date'],
        'ship_date' => (string) $row['ship_date'],
        'account_id' => (int) $row['account_id'],
        'account_title' => (string) ($row['live_account_title'] ?? ''),
        'recipient' => (string) $row['ship_to_title'],
        'carrier' => (string) $row['carrier_title'],
        'plate' => (string) $row['plate'],
        'order_id' => (int) $row['order_id'],
        'order_number' => (string) ($row['order_number'] ?? ''),
        'invoice_id' => (int) $row['invoice_id'],
        'invoice_number' => (string) ($row['invoice_number'] ?? ''),
        'status' => (string) $row['status'],
        'status_label' => $states[$row['status']] ?? (string) $row['status'],
        'cancelled' => ((string) $row['status'] === 'cancelled'),
        'edoc_provider' => (string) ($row['edoc_provider'] ?? ''),
        'edoc_external_id' => (string) ($row['edoc_external_id'] ?? ''),
        'timestamp' => $timestamp,
        'modified' => ($timestamp > 0) ? get_relative_time(array('timestamp' => $timestamp)) : '',
        'username' => (string) ($row['user_username'] ?? ''),
        'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/' . $url,
        'download_url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/' . $url . '&download=1',
        'edit_url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_erp_waybill.php?id=' . $id,
    );
}

/**
 * One e-mailed reconciliation letter as a file-manager entry. It opens the
 * PDF that went, through get_file.php, which checks the ERP right.
 *
 * @param array $row  erp_reconciliation_log row, with account_title,
 *                    tax_number, user_username and the kept file joined
 * @return array
 */
function erp_files_letter_payload($row)
{
    $id = (int) $row['id'];
    $timestamp = (int) $row['created_at'];
    $kept = !empty($row['kept_file_id']);
    $url = $kept ? (OUTPUT_PATH . (string) $row['kept_file_name']) : '';

    return array(
        'kind' => 'erp_reconciliation',
        'id' => $id,
        'name' => preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) $row['reference']) . '.pdf',
        'title' => trim((string) $row['reference'] . ' — ' . (string) ($row['account_title'] ?? '')),
        'type' => 'pdf',
        'size' => $kept ? (int) $row['kept_size'] : null,
        'kept' => $kept,
        'kept_at' => $kept ? (int) $row['kept_at'] : 0,
        'source' => $kept ? 'kept' : 'missing',
        'number' => (string) $row['reference'],
        'doc_class' => 'reconciliation',
        'date' => (string) $row['as_of'],
        'from_date' => (string) $row['from_date'],
        'account_id' => (int) $row['account_id'],
        'account_title' => (string) ($row['account_title'] ?? ''),
        'tax_number' => (string) ($row['tax_number'] ?? ''),
        'balance' => (int) $row['balance_base'],
        'balance_label' => erp_money_out(abs((int) $row['balance_base'])),
        'sent_to' => (string) $row['sent_to'],
        'cancelled' => false,
        'timestamp' => $timestamp,
        'modified' => ($timestamp > 0) ? get_relative_time(array('timestamp' => $timestamp)) : '',
        'username' => (string) ($row['user_username'] ?? ''),
        'url' => $url,
        'download_url' => $url,
        'edit_url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/erp_reconciliation.php?id=' . (int) $row['account_id'] . '&as_of=' . rawurlencode((string) $row['as_of']),
    );
}

/**
 * One expense's kept receipt as a file-manager entry. It opens the file
 * itself, through get_file.php, which checks the ERP right.
 *
 * @param array $row  erp_expenses row, with category_name, user_username and
 *                    the kept file (kept_file_id, kept_file_name, kept_type,
 *                    kept_size, kept_at, kept_versions) joined
 * @return array
 */
function erp_files_expense_payload($row)
{
    $id = (int) $row['id'];
    $currency = strtoupper(trim((string) $row['currency']));
    $type = strtolower((string) $row['kept_type']);
    $number = (string) $row['document_no'];
    $supplier = (string) $row['supplier'];
    $statuses = function_exists('erp_expense_statuses') ? erp_expense_statuses() : array();
    $timestamp = max((int) $row['updated_at'], (int) $row['kept_at']);
    $url = OUTPUT_PATH . (string) $row['kept_file_name'];

    // The name the file is saved as: the day and the receipt number, the
    // way the accountant's pack names it.
    $label = ($number !== '') ? $number : ('G' . $id);
    $name = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) $row['expense_date'] . '_' . $label) . '.' . preg_replace('/[^a-z0-9]/', '', $type);

    return array(
        'kind' => 'erp_expense',
        'id' => $id,
        'name' => $name,
        'title' => trim((($supplier !== '') ? $supplier : (string) ($row['category_name'] ?? '')) . (((string) $row['description'] !== '') ? ' — ' . (string) $row['description'] : '')),
        'type' => $type,
        'picture' => in_array($type, array('jpg', 'jpeg', 'png', 'gif', 'webp'), true),
        'size' => (int) $row['kept_size'],
        'kept' => true,
        'kept_at' => (int) $row['kept_at'],
        'source' => 'kept',
        'versions' => (int) ($row['kept_versions'] ?? 1),
        'number' => $number,
        'doc_class' => 'expense',
        'date' => (string) $row['expense_date'],
        'account_id' => 0,
        'supplier' => $supplier,
        'tax_number' => (string) $row['supplier_tax_number'],
        'category_name' => (string) ($row['category_name'] ?? ''),
        'total' => (int) $row['total_amount'],
        'total_label' => erp_money_out_currency((int) $row['total_amount'], $currency),
        'currency' => $currency,
        'status' => (string) $row['status'],
        'status_label' => $statuses[$row['status']] ?? (string) $row['status'],
        'cancelled' => ((string) $row['status'] === 'cancelled'),
        'timestamp' => $timestamp,
        'modified' => ($timestamp > 0) ? get_relative_time(array('timestamp' => $timestamp)) : '',
        'username' => (string) ($row['user_username'] ?? ''),
        'url' => $url,
        'download_url' => $url,
        'edit_url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_erp_expense.php?id=' . $id,
    );
}
