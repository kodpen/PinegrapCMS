<?php
/**
 * PineGrap - Enterprise Website Platform
 *
 * File manager → Import → "ZIP for the Visual Page Editor". The archive
 * becomes a saved design: files under Import/<project>/ in the file
 * manager, one page per HTML file in that same folder, one style row with
 * the imported stylesheets / scripts / fonts. The browser then lands in the
 * editor on the new design — the same place the editor's own import ends
 * up, only with everything already saved.
 *
 * Multipart POST (a file upload), so it is its own script rather than an
 * api.php action; api.php reads a JSON body.
 *
 * @author      Erdal Güral (Kodpen)
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();
validate_area_access($user, 'designer');

include_once('liveform.class.php');
include_once('includes/designer_import.php');

// Errors show on the file manager screen it came from.
$liveform = new liveform('view_folder_and_files');
$back = pg_safe_back_url(isset($_POST['send_to']) ? $_POST['send_to'] : '', 'view_folders.php');

if (!$_POST) {
    header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_folders.php');
    exit();
}

// post_max_size exceeded: PHP drops the body before we run, and a script
// that does not check would blame the operator for "no file".
if (function_exists('pg_post_body_discarded') && pg_post_body_discarded()) {
    $liveform->add_error(lang('The upload is larger than this server accepts in one request.'));
    header('Location: ' . URL_SCHEME . HOSTNAME . pg_safe_redirect_path($back));
    exit();
}

validate_token_field();

if (empty($_FILES['file']) || (int)$_FILES['file']['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($_FILES['file']['tmp_name'])) {
    $code = isset($_FILES['file']['error']) ? (int)$_FILES['file']['error'] : UPLOAD_ERR_NO_FILE;
    $liveform->add_error(($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE)
        ? lang('The file is larger than this server allows.')
        : lang('No file was uploaded.'));
    header('Location: ' . URL_SCHEME . HOSTNAME . pg_safe_redirect_path($back));
    exit();
}

$orig_name = (string)$_FILES['file']['name'];
$tmp       = (string)$_FILES['file']['tmp_name'];
$head      = @file_get_contents($tmp, false, null, 0, 4);
if (strtolower(pathinfo($orig_name, PATHINFO_EXTENSION)) !== 'zip' && !(function_exists('pg_looks_like_zip') && pg_looks_like_zip($head))) {
    $liveform->add_error(lang('Only .html, .htm and .zip files can be imported.'));
    header('Location: ' . URL_SCHEME . HOSTNAME . pg_safe_redirect_path($back));
    exit();
}

$project = isset($_POST['project_name']) ? trim((string)$_POST['project_name']) : '';
if ($project === '') $project = pathinfo($orig_name, PATHINFO_FILENAME);
$project = mb_substr(preg_replace('/[\/\\\\:*?"<>|]+/', '-', $project), 0, 60);
if ($project === '') $project = 'import';

$res = pg_designer_import_zip($tmp, $project, $user);
if (!empty($res['errors'])) {
    $liveform->add_error(implode(' ', $res['errors']));
    header('Location: ' . URL_SCHEME . HOSTNAME . pg_safe_redirect_path($back));
    exit();
}

$made = pg_designer_create_design_from_import($res, $project, $user);
if (empty($made['style_id'])) {
    $liveform->add_error(implode(' ', $made['errors']));
    header('Location: ' . URL_SCHEME . HOSTNAME . pg_safe_redirect_path($back));
    exit();
}

log_activity(lang(array('string' => 'HTML import ({var:1}): {var:2} page(s)', 'vars' => array($orig_name, count($made['page_ids'])))), $_SESSION['sessionusername']);

// Notes from the import (skipped framework files, missing stylesheets …)
// travel to the editor's notice area.
$lf_edit = new liveform('edit_system_style', (int)$made['style_id']);
$lf_edit->add_notice(lang(array('string' => '{var:1} page(s) were imported into "{var:2}".', 'vars' => array(count($made['page_ids']), $project))));
foreach (array_slice($made['warnings'], 0, 8) as $w) {
    $lf_edit->add_notice($w);
}

header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/edit_system_style.php?id=' . (int)$made['style_id']);
exit();
?>
