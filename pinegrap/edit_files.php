<?php
/**
 * PineGrap - Enterprise Website Platform
 *
 * Originally developed as LiveSite by Camelback Web Architects.
 * Since 2017, maintained and evolved by Erdal Güral (Kodpen) under the name PineGrap.
 * The final LiveSite update (2019) has been integrated into PineGrap.
 * LiveSite remains available as a separate downloadable legacy version.
 *
 * @author      Camelback Web Architects
 *              Erdal Güral (Kodpen)
 * @link        https://livesite.com
 *              https://kodpen.com
 * @copyright   2001–2019 Camelback Consulting, Inc.
 *              2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Increase limits so image optimization can run for a while.
ini_set('max_execution_time', '9999');
ini_set('memory_limit', '-1');

include('init.php');

include_once('liveform.class.php');
// The screen the form is posted from: the design files list, the only one
// left (the File Manager edits a selection of files itself). It is both the
// liveform the notices are left for and the screen the user is sent back to.
$from = 'view_design_files';
$liveform = new liveform($from);
$user = validate_user();
validate_area_access($user, 'user');

// Modify Selected asks for its changes in a dialog of the design files list
// (pg_design_files_modify_dialog()); this file only takes the post.
if (!$_POST) {
    go(PATH . SOFTWARE_DIRECTORY . '/' . $from . '.php');

// else the form has been submitted, so process it
} else {
    validate_token_field();
    
    // if at least one file was selected then continue
    if ($_POST['files']) {

        // A document the ERP module keeps has no folder, which the folder
        // right below lets staff write to: it is refused by name, before any
        // file of the list is moved or deleted.
        if (pg_files_include_erp_document((array) $_POST['files'])) {
            output_error(lang('ERP documents are read-only in the file manager.'));
        }

        $number_of_files = 0;
        $number_of_images = 0;
        
        switch ($_POST['action']) {
            // if files are being edited, proceed
            case 'edit':
                // if at least one action was selected, then continue
                if (
                    ($_POST['move_to_folder'])
                    ||
                    (
                        ($_POST['edit_design'] != '')
                        && ($user['role'] <= 1)
                    )
                    or $_POST['optimize']
                ) {
                    // if a folder was selected to move the file(s) to
                    // and if user does not have access to the folder that he/she is trying to move files to, output error
                    if (($_POST['move_to_folder']) && (check_edit_access($_POST['move_to_folder']) == false)) {
                        output_error(lang('You do not have access to move files to the folder that you selected') . '. <a href="javascript:history.go(-1);">' . lang('Go back') . '</a>.');
                    }

                    // 'resize' also scales images down; anything else truthy
                    // is the plain compress this screen has always done.
                    $optimize_mode = '';

                    if (!empty($_POST['optimize'])) {
                        $optimize_mode = (($_POST['optimize'] === 'resize') ? 'resize' : 'optimize');
                        require(dirname(__FILE__) . '/optimize_image.php');
                    }
                    
                    // loop through each file and process actions
                    foreach ($_POST['files'] as $file_id) {

                        $file = db_item(
                            "SELECT
                                id,
                                design,
                                folder AS folder_id,
                                type,
                                optimized
                            FROM files
                            WHERE id = '" . e($file_id) . "'");

                        $design = $file['design'];
                        $folder_id = $file['folder_id'];

                        // if the user does not have edit rights to this file's folder,
                        // or this file is a design file and the user is not a designer or administrator,
                        // then log activity and output error
                        if (
                            (check_edit_access($folder_id) == false)
                            ||
                            (
                                ($design == 1)
                                && ($user['role'] > 1)
                            )
                        ) {
                            log_activity(lang('access denied to modify files because user does not have access to file'), $_SESSION['sessionusername']);
                            output_error(lang('You do not have access to modify a file that you selected') . '. <a href="javascript:history.go(-1);">' . lang('Go back') . '</a>.');
                        }

                        $sql_folder = "";

                        // if a folder was selected to move the file(s) to, then move file
                        if ($_POST['move_to_folder']) {
                            $sql_folder = "folder = '" . escape($_POST['move_to_folder'] ?? '') . "',";
                        }

                        $sql_design = "";

                        // if design was selected to be edited and the user is a designer or administrator,
                        // then update design property
                        if (
                            ($_POST['edit_design'] != '')
                            && ($user['role'] <= 1)
                        ) {
                            $sql_design = "design = '" . escape($_POST['edit_design'] ?? '') . "',";
                        }

                        if ($sql_folder or $sql_design) {

                            $audit_before = pg_audit_fetch('files', $file_id);

                            db(
                                "UPDATE files
                                SET
                                    " . $sql_folder . "
                                    " . $sql_design . "
                                    timestamp = UNIX_TIMESTAMP(),
                                    user = '" . $user['id'] . "'
                                WHERE id = '" . e($file_id) . "'");

                            pg_audit_record('update', 'files', $file_id, $audit_before);

                            $number_of_files++;

                        }

                        $file['type'] = mb_strtolower($file['type']);

                        // If this file is an image that we should optimize, then do that.
                        //
                        // The already-optimized flag only holds back the plain
                        // mode. A file can be fully compressed and still be
                        // 6000 pixels wide, which is precisely what the shrink
                        // mode was selected for.
                        if (
                            $optimize_mode
                            and (($optimize_mode === 'resize') or !$file['optimized'])
                            and (
                                ($file['type'] == 'jpg')
                                or ($file['type'] == 'jpeg')
                                or ($file['type'] == 'png')
                                or ($file['type'] == 'gif')
                                or ($file['type'] == 'bmp')
                                or ($file['type'] == 'tiff')
                                or ($file['type'] == 'webp')
                            )
                        ) {

                            $response = optimize_image($file['id'], $optimize_mode);

                            if ($response['status'] == 'success') {
                                $liveform->add_notice(h($response['message']));
                                $number_of_images++;
                            } else  {
                                $liveform->mark_error($file['id'], h($response['message']));
                            }

                        }

                    }
                    
                    // if more than 0 files were modified, then log activity
                    if ($number_of_files) {
                        // Each change is a verb-free phrase; they are joined with ', ' inside one sentence key.
                        $changes = array();

                        // if a folder was selected to move the files(s) to, then name the folder
                        if ($_POST['move_to_folder']) {
                            $query = "SELECT folder_name FROM folder WHERE folder_id = ?";
                            $result = db_q($query, array((string) ($_POST['move_to_folder'] ?? ''))) or output_error('Query failed.');
                            $row = mysqli_fetch_assoc($result);

                            $changes[] = lang(array('string' => 'moved to the folder {var:1}', 'vars' => array($row['folder_name'])));
                        }

                        // if the design property was selected to be edited and the user is a design or administrator,
                        // then say which way
                        if (
                            ($_POST['edit_design'] != '')
                            && ($user['role'] <= 1)
                        ) {
                            $changes[] = ($_POST['edit_design'] == '1') ? lang('design turned on') : lang('design turned off');
                        }

                        // if there is a change, then log it and add notice
                        if ($changes) {
                            if ($number_of_files == 1) {
                                $message = lang(array('string' => '{var:1} file was changed: {var:2}.', 'vars' => array($number_of_files, implode(', ', $changes))));
                            } else {
                                $message = lang(array('string' => '{var:1} files were changed: {var:2}.', 'vars' => array($number_of_files, implode(', ', $changes))));
                            }

                            log_activity($message, $_SESSION['sessionusername']);
                            $liveform->add_notice(h($message));
                        }
                    }

                    if ($number_of_images) {

                        if ($number_of_images > 1) {
                            $message = lang(array('string'=>'{var:1} images were optimized','vars'=>$number_of_images));
                        } else {
                            $message = lang('1 image was optimized');
                        }

                        log_activity($message);

                        $liveform->add_notice($message . '.');

                    }

                }
                
                break;

            // if files are being deleted
            case 'delete':
                foreach ($_POST['files'] as $file_id) {
                    // get properties for file, in order to validate access
                    $query =
                        "SELECT
                            name,
                            design,
                            folder AS folder_id
                        FROM files
                        WHERE id = ?";
                    $result = db_q($query, array((string) $file_id)) or output_error('Query failed.');
                    $row = mysqli_fetch_assoc($result);

                    $name = $row['name'];
                    $design = $row['design'];
                    $folder_id = $row['folder_id'];

                    // if the user does not have edit rights to this file's folder,
                    // or this file is a design file and the user is not a designer or administrator,
                    // then log activity and output error
                    if (
                        (check_edit_access($folder_id) == false)
                        ||
                        (
                            ($design == 1)
                            && ($user['role'] > 1)
                        )
                    ) {
                        log_activity(lang('access denied to delete files because user does not have access to file'), $_SESSION['sessionusername']);
                        output_error(lang('You do not have access to delete the files you selected') . '. <a href="javascript:history.go(-1);">' . lang('Go back') . '</a>.');
                    }

                    $audit_before = pg_audit_fetch('files', $file_id);

                    $query = "DELETE FROM files WHERE id = ?";
                    $result = db_q($query, array((string) $file_id)) or output_error('Query failed.');
                    pg_file_versions_delete_file($file_id);

                    pg_audit_record('delete', 'files', $file_id, $audit_before);

                    // delete file's system css properties in case any exist
                    $query = "DELETE FROM system_theme_css_rules WHERE file_id = ?";
                    $result = db_q($query, array((string) $file_id)) or output_error('Query failed.');

                    db("DELETE FROM preview_styles WHERE theme_id = '" . escape($file_id) . "'");
                    
                    // delete file on filesystem
                    @unlink(FILE_DIRECTORY_PATH . '/' . $name);

                    $number_of_files++;
                }

                // if more than 0 files were deleted, then log activity
                if ($number_of_files > 0) {
                    if ($number_of_files == 1) {
                        $deleted_message = lang(array('string' => '{var:1} file was deleted.', 'vars' => $number_of_files));
                    } else {
                        $deleted_message = lang(array('string' => '{var:1} files were deleted.', 'vars' => $number_of_files));
                    }

                    log_activity($deleted_message, $_SESSION['sessionusername']);
                    $liveform->add_notice($deleted_message);
                }
                
                break;
        }
    }

    // If there is a send to value then send user back to that screen
    if (isset($_POST['send_to']) == TRUE) {
        header('Location: ' . URL_SCHEME . HOSTNAME . pg_safe_redirect_path(($_POST['send_to'] ?? '')));
        
    // else send user to the default view
    } else {
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/' . $from . '.php');
    }
}