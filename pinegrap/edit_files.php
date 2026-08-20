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
 *              2016–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Increase limits so image optimization can run for a while.
ini_set('max_execution_time', '9999');
ini_set('memory_limit', '-1');

include('init.php');

include_once('liveform.class.php');
$liveform = new liveform($_POST['from']);
$user = validate_user();
validate_area_access($user, 'user');

// if the form has not been submitted yet, then output form
if (!$_POST) {
    $output_design_row = '';

    // if this user is a designer or administrator, then allow the user to update the design property
    if ($user['role'] <= 1) {
        // set options for design pick list
        $design_options = array(
            '' => '',
            lang('Yes') => '1',
            lang('No') => '0'
        );

        $output_design_row =
            '<div class="col-12 col-xs-4 col-sm-4">
                <div class="form-floating mt-1 mb-2">
                    ' . $liveform->output_field(array('type'=>'select', 'class'=>'form-select', 'id'=>'design', 'options'=>$design_options)) . '
                    <label for="folder" class="form-label">' . lang('Design') . '</label>
                </div>
            </div>';
    }

    echo 
    output_header_secure(array('title'=>lang('Modify Files'),'icon'=>'file')) . '
    <nav id="header" class="navbar sticky-top rounded-0 navbar-expand border-bottom shadow-sm bg-body">
        <div class="container-fluid">
            <span class="navbar-text me-auto" data-bs-content="' . lang('You may update the selected Files via the form below. You may leave an option unselected if you do not want to modify a property.') . '" title="' . lang('Modify Files') . '">' . lang('Modify Files') . '</span>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item dropdown no-popover"  title="' . lang('Software Theme') . '">
                    <button class="nav-link nav-link-sm position-relative dropdown-toggle dropdown-menu-right d-none" data-bs-toggle="dropdown" id="bd-theme" type="button"><span class="bi bi-circle-half"></span></button>
                    <ul aria-labelledby="bd-theme" class="dropdown-menu shadow dropdown-menu-end p-1 bg-body backdrop mt-nav-link-sm border-dropdown-menu" data-bs-popper="static" style="--bs-dropdown-min-width: 8rem;">
                        <li><button class="dropdown-item dropdown-item-sm rounded p-0 my-1 d-flex align-items-center" data-bs-theme-value="light" type="button"><i class="bi bi-sun-fill m-2"></i>' . lang('Light') . '</button></li>
                        <li><button class="dropdown-item dropdown-item-sm rounded p-0 my-1 d-flex align-items-center active" data-bs-theme-value="dark" type="button"><i class="bi bi-moon-stars-fill m-2"></i>' . lang('Dark') . '</button></li>
                        <li><button class="dropdown-item dropdown-item-sm rounded p-0 my-1 d-flex align-items-center" data-bs-theme-value="auto" type="button"><i class="bi bi-circle-half m-2"></i>' . lang('Auto') . '</button></li>
                    </ul>
                </li>
                <li class="nav-item">
                    <button title="' . lang('Close') . '" type="button" class="nav-link nav-link-sm position-relative no-popover" onclick="window.close()" aria-label="Close">
                        <span class="bi bi-x-lg"></span>
                    </button>
                </li>
            </ul>
        </div>
    </nav>
    <script type="text/javascript">
        function edit_files()
        {
            // if there is a value then update field in the form
            if (document.getElementById("folder").value != "") {
                opener.document.form.move_to_folder.value = document.getElementById("folder").value;
            }
            
            // if the design field exists and there is a value then update field in the form
            if (
                (document.getElementById("design"))
                && (document.getElementById("design").value != "")
            ) {
                opener.document.form.edit_design.value = document.getElementById("design").value;
            }

            if (document.getElementById("optimize").checked) {

                var number_of_files = opener.$(\'input[name="files[]"]:checked\').length;

             

                opener.document.form.optimize.value = 1;

                opener.scrollTo(0, 0);

                opener.$("#content").prepend(\'\
                    <div class=" alert alert-secondary software_notice">\
                        <h4 class="alert-heading">' . lang('Optimizing images') . '...</h4>\
                        <p>' . lang('Please stay on this page until this process is complete.') . '<p>\
                        <div class="progress">\
                          <div class="progress-bar progress-bar-striped progress-bar-animated bg-secondary" role="progressbar" aria-valuenow="75" aria-valuemin="0" aria-valuemax="100" style="width: 100%"></div>\
                        </div>\
                    </div>\');

                // Add delay before submitting form in parent window so loading image
                // has time to load.
                setTimeout (function () {
                    opener.document.form.submit();
                    window.close();
                }, 500);

            } else {
                opener.document.form.submit();
                window.close();
            }

        }
    </script>
    <main id="content" class="container">
        <div class="row">
            <div class="col-12 col-xs-8 col-sm-8 col-md-auto">
                <div class="form-floating mt-1 mb-2">
                    <select class="form-select" id="folder"><option value=""></option>' . select_folder() . '</select>
                    <label for="folder" class="form-label">' . lang('Folder') . '</label>
                </div>
            </div>
            ' . $output_design_row . '
            <div class="col-12 my-2">
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" id="optimize" value="1" />
                  <label class="form-check-label" for="optimize">' . lang('Optimize Images') . '</label>
                </div>
            </div>
        </div>
        <nav class="buttons navigation text-center position-sticky" style="bottom:.5rem;" aria-label="data edit buttons ">
            <div class="container">
                <div class=" btn-group btn-group-sm flex-wrap justify-content-center mb-0">
                    <button type="button" name="submit_save" value="Modify Files" class="btn mb-1 mt-1  btn-primary submit-primary" onclick="edit_files()"><span class="material-icons me-2">save</span>' . lang(array('string'=>'Modify Files') ) . '</button>
                </div>
            </div>
        </nav>
    </main>' . output_footer_secure();


// else the form has been submitted, so process it
} else {
    validate_token_field();
    
    // if at least one file was selected then continue
    if ($_POST['files']) {

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

                    if ($_POST['optimize']) {
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

                            db(
                                "UPDATE files
                                SET
                                    " . $sql_folder . "
                                    " . $sql_design . "
                                    timestamp = UNIX_TIMESTAMP(),
                                    user = '" . $user['id'] . "'
                                WHERE id = '" . e($file_id) . "'");

                            $number_of_files++;

                        }

                        $file['type'] = mb_strtolower($file['type']);

                        // If this file is an image that we should optimize, then do that.
                        if (
                            $_POST['optimize']
                            and !$file['optimized']
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

                            $response = optimize_image($file['id']);

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
                        $log_message = '';
                        
                        // if a folder was selected to move the files(s) to, then output message for log
                        if ($_POST['move_to_folder']) {
                            // get folder name for log
                            $query = "SELECT folder_name FROM folder WHERE folder_id = '" . escape($_POST['move_to_folder'] ?? '') . "'";
                            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                            $row = mysqli_fetch_assoc($result);
                            
                            // output message for log
                            $log_message = lang(array('string'=>'were moved to {var:1}','vars'=>$row['folder_name']));
                        }
                        
                        // if the design property was selected to be edited and the user is a design or administrator,
                        // then set message for log
                        if (
                            ($_POST['edit_design'] != '')
                            && ($user['role'] <= 1)
                        ) {
                            // if the log message is not blank, then add separator
                            if ($log_message != '') {
                                $log_message .= ', ' . lang('and') . ' ';
                            }
                            
                            // prepare design value for log
                            if ($_POST['edit_design'] == '1') {
                                $on_off = lang('on');
                            } else {
                                $on_off = lang('off');
                            }
                            
                            // output message for log
                            $log_message .= lang(array('string'=>'had design turned {var:1}','vars'=>$on_off));
                        }
                        
                        // if there is a log message, then log it and add notice
                        if ($log_message != '') {
                            log_activity($number_of_files . ' ' . lang('file(s)') . ' ' . $log_message, $_SESSION['sessionusername']);
                            $liveform->add_notice($number_of_files . ' ' . lang('file(s)') . ' ' . h($log_message) . '.');
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
                        WHERE id = '" . escape($file_id) . "'";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
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

                    $query = "DELETE FROM files WHERE id = '" . escape($file_id) . "'";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

                    // delete file's system css properties in case any exist
                    $query = "DELETE FROM system_theme_css_rules WHERE file_id = '" . escape($file_id) . "'";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

                    db("DELETE FROM preview_styles WHERE theme_id = '" . escape($file_id) . "'");
                    
                    // delete file on filesystem
                    @unlink(FILE_DIRECTORY_PATH . '/' . $name);

                    $number_of_files++;
                }

                // if more than 0 files were deleted, then log activity
                if ($number_of_files > 0) {
                    log_activity(lang(array('string'=>'{var:1} file(s) were deleted','vars'=>$number_of_files)), $_SESSION['sessionusername']);
                    $liveform->add_notice(lang(array('string'=>'{var:1} file(s) were deleted','vars'=>$number_of_files)));
                }
                
                break;
        }
    }

    // If there is a send to value then send user back to that screen
    if (isset($_POST['send_to']) == TRUE) {
        header('Location: ' . URL_SCHEME . HOSTNAME . $_POST['send_to']);
        
    // else send user to the default view
    } else {
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/' . $_POST['from'] . '.php');
    }
}