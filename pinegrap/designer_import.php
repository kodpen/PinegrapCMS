<?php
/**
 * PineGrap - Enterprise Website Platform
 *
 * Visual designer — HTML / ZIP import endpoint.
 *
 * A separate script rather than an api.php action because api.php reads its
 * request as a JSON body and this one carries a file: multipart/form-data.
 *
 * POST fields:
 *   file          .html / .htm / .zip
 *   project_name  optional; folder name under Import/ (defaults to the file name)
 *   skip_names    optional JSON array of page names already open as tabs
 *   token         CSRF
 *
 * Reply (JSON): { status, pages[], css[], js[], fonts[], warnings[], errors[] }
 * Pages come back UNSAVED — the designer opens them as tabs and the operator
 * decides what to keep. Files, on the other hand, are written immediately
 * (they are what the pages reference) and sit under Import/<project>/ in
 * the file manager whether or not the pages are saved afterwards.
 *
 * @author      Erdal Güral (Kodpen)
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();
validate_area_access($user, 'designer');

header('Content-Type: application/json; charset=utf-8');

// post_max_size exceeded → PHP drops the whole body before we run; without
// this the script would see "no file" and blame the operator.
if (function_exists('pg_post_body_discarded') && pg_post_body_discarded()) {
    http_response_code(413);
    echo encode_json(array('status' => 'error', 'message' => lang('The upload is larger than this server accepts in one request.')));
    exit();
}

validate_token_field();

include_once('includes/designer_import.php');

if (empty($_FILES['file']) || !is_array($_FILES['file']) || (int)$_FILES['file']['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($_FILES['file']['tmp_name'])) {
    $code = isset($_FILES['file']['error']) ? (int)$_FILES['file']['error'] : UPLOAD_ERR_NO_FILE;
    $msg  = ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE)
        ? lang('The file is larger than this server allows.')
        : lang('No file was uploaded.');
    echo encode_json(array('status' => 'error', 'message' => $msg));
    exit();
}

$orig_name = (string)$_FILES['file']['name'];
$tmp       = (string)$_FILES['file']['tmp_name'];
$ext       = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));

$project = isset($_POST['project_name']) ? trim((string)$_POST['project_name']) : '';
if ($project === '') $project = pathinfo($orig_name, PATHINFO_FILENAME);
$project = mb_substr(preg_replace('/[\/\\\\:*?"<>|]+/', '-', $project), 0, 60);
if ($project === '') $project = 'import';

$skip = array();
if (!empty($_POST['skip_names'])) {
    $tmp_skip = json_decode((string)$_POST['skip_names'], true);
    if (is_array($tmp_skip)) foreach ($tmp_skip as $s) if (is_string($s) && $s !== '') $skip[] = $s;
}

$head = @file_get_contents($tmp, false, null, 0, 4);
$is_zip = ($ext === 'zip') || (function_exists('pg_looks_like_zip') && pg_looks_like_zip($head));

if ($is_zip) {
    $res = pg_designer_import_zip($tmp, $project, $user, array('skip_names' => $skip));
} elseif ($ext === 'html' || $ext === 'htm' || $ext === 'xhtml') {
    $html = @file_get_contents($tmp);
    if ($html === false || trim($html) === '') {
        echo encode_json(array('status' => 'error', 'message' => lang('The file is empty.')));
        exit();
    }
    $res = pg_designer_import_single_html($html, $orig_name, $user, array('skip_names' => $skip));
} else {
    echo encode_json(array('status' => 'error', 'message' => lang('Only .html, .htm and .zip files can be imported.')));
    exit();
}

if (!empty($res['errors'])) {
    echo encode_json(array('status' => 'error', 'message' => implode(' ', $res['errors']), 'warnings' => $res['warnings']));
    exit();
}

log_activity(lang(array('string' => 'HTML import ({var:1}): {var:2} page(s)', 'vars' => array($orig_name, count($res['pages'])))), $_SESSION['sessionusername']);

// JSON_HEX_TAG etc. are not needed here — this is a JSON reply, not a
// script block — but trees can carry "</script>" in custom_html blocks and
// the designer embeds nothing of this into markup; it parses it.
$res['status'] = 'success';
echo encode_json($res);
exit();
