<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The designer's "From a document" start (includes/fn/document_start.php).
 * A separate script rather than an api.php action because a file is posted
 * as multipart/form-data.
 *
 *   GET  ?texts=1                  the form's texts (the designer's window
 *                                  fetches them on first use)
 *   POST file | file_id, token     a .docx, .md or .txt read into paragraphs
 *                                  or text; a Word document's pictures kept
 *                                  under the answer's `keep` token
 *   POST sort, sections, token     "Sort it for me": the rows' sections to
 *                                  Pinegrap AI, its proposals back
 *                                  (includes/fn/document_sort.php)
 *   POST keep, pictures, name, token
 *                                  the kept pictures stored in the file
 *                                  manager when the page is built: n => address
 *
 * Memory: docs/memory/designer/document-start.md
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
include_once('includes/designer_import.php');
require_once('includes/fn/document_start.php');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (function_exists('pg_post_body_discarded') && pg_post_body_discarded()) {
    echo encode_json(array('status' => 'error', 'title' => lang('The file is larger than 8 MB'), 'message' => lang('Save a shorter part of it, or paste its text.')));
    exit();
}

// Answered in JSON before the HTML gates: a missing session, a lower role
// or a stale token (a GET for the texts carries no token).
$doc_refusal = pg_designer_import_refusal(
    defined('USER_LOGGED_IN') && USER_LOGGED_IN,
    defined('USER_ROLE') ? USER_ROLE : 3,
    $_SESSION['software']['token'] ?? '',
    ($_SERVER['REQUEST_METHOD'] === 'GET') ? ($_SESSION['software']['token'] ?? '') : ($_POST['token'] ?? '')
);

if ($doc_refusal !== null) {
    http_response_code($doc_refusal[0]);
    echo encode_json(array('status' => 'error', 'message' => $doc_refusal[1]));
    exit();
}

$user = validate_user();
validate_area_access($user, 'designer');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo encode_json(array('status' => 'success', 'texts' => pg_document_start_texts()));
    exit();
}

validate_token_field();

// "Sort it for me": proposals for the rows, nothing written.
if (isset($_POST['sort'])) {
    echo encode_json(pg_document_sort_request(json_decode((string) ($_POST['sections'] ?? ''), true), $user));
    exit();
}

// The pictures of a read document, stored as the page is built.
if (isset($_POST['keep'])) {
    $doc_numbers = json_decode((string) ($_POST['pictures'] ?? '[]'), true);
    $doc_stored = pg_document_start_store((string) $_POST['keep'], is_array($doc_numbers) ? $doc_numbers : array(),
        pg_file_name_stem((string) ($_POST['name'] ?? 'document')), (int) $user['id']);
    echo encode_json(array('status' => 'success', 'pictures' => (object) $doc_stored));
    exit();
}

// The file: posted, or one of the file manager's.
$doc_name = '';
$doc_path = '';
$doc_file_id = isset($_POST['file_id']) ? (int) $_POST['file_id'] : 0;

if ($doc_file_id > 0) {
    $doc_kinds = pg_picker_kinds();
    $doc_row = db_item("SELECT name FROM files WHERE " . pg_document_start_file_condition($doc_file_id,
        pg_picker_closed_folder_ids($doc_kinds['document']), pg_picker_granted_folders($user, $doc_kinds['document'])) . " LIMIT 1");
    if (is_array($doc_row) && is_file(FILE_DIRECTORY_PATH . '/' . $doc_row['name'])) {
        $doc_name = (string) $doc_row['name'];
        $doc_path = FILE_DIRECTORY_PATH . '/' . $doc_name;
    }
} elseif (!empty($_FILES['file']) && is_array($_FILES['file'])) {
    $doc_error = (int) $_FILES['file']['error'];
    $doc_name = (string) $_FILES['file']['name'];
    if ($doc_error === UPLOAD_ERR_INI_SIZE || $doc_error === UPLOAD_ERR_FORM_SIZE) {
        $doc_refused = pg_document_start_refusal('a.txt', PG_DOCUMENT_START_MAX_BYTES + 1);
        echo encode_json(array('status' => 'error', 'title' => $doc_refused['title'], 'message' => $doc_refused['text']));
        exit();
    }
    if ($doc_error === UPLOAD_ERR_OK && is_uploaded_file($_FILES['file']['tmp_name'])) {
        $doc_path = (string) $_FILES['file']['tmp_name'];
    }
}

if ($doc_path === '') {
    echo encode_json(array('status' => 'error', 'title' => lang('The file could not be opened'), 'message' => lang('No file was uploaded.')));
    exit();
}

$doc_size = (int) @filesize($doc_path);
$doc_refused = pg_document_start_refusal($doc_name, $doc_size);
$doc_read = ($doc_refused === null) ? pg_document_paragraphs($doc_path, $doc_name) : null;
$doc_refused = $doc_refused ?: pg_document_start_refusal($doc_name, $doc_size, $doc_read);

if ($doc_refused !== null) {
    echo encode_json(array('status' => 'error', 'title' => $doc_refused['title'], 'message' => $doc_refused['text']));
    exit();
}

$doc_keep = $doc_read['pictures'] ? pg_document_start_keep($doc_path, $doc_read['pictures']) : '';
echo encode_json(pg_document_start_answer($doc_read, $doc_name, $doc_size, $doc_keep));
