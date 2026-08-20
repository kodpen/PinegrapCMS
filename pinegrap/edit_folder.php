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

include('init.php');
$user = validate_user();
validate_area_access($user, 'user');


$output_breadcrumb_link = '<li class="breadcrumb-item"><a class="link-secondary " data-loading-content="' . lang('Loading') . '" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_folders.php">' . lang('All Folders') . '</a></li>';

$form = new liveform('edit_folder');

if (check_edit_access($_REQUEST['id']) == false) {
    log_activity(lang('access denied because user does not have access to modify folder'), $_SESSION['sessionusername']);
    output_error(lang('Access denied') . '. <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
}

if (!$_POST['name']) {
    // get folder data
    $query =
        "SELECT
            folder_id,
            folder_name,
            folder_parent,
            folder_order,
            folder_access_control_type,
            folder_style,
            mobile_style_id,
            folder_archived
        FROM folder
        WHERE folder_id = '" . escape($_GET['id']) . "'";
    $result=mysqli_query(db::$con, $query) or output_error('Query failed.');
    $row = mysqli_fetch_array($result);
    
    $folder_id = $row['folder_id'];
    $folder_name = $row['folder_name'];
    $folder_parent = $row['folder_parent'];
    $folder_order = $row['folder_order'];
    $folder_access_control_type = $row['folder_access_control_type'];
    $folder_style = $row['folder_style'];
    $mobile_style_id = $row['mobile_style_id'];
    $folder_archived = $row['folder_archived'];

    $duplicate = '';

    // If this is not root folder and user has edit access to parent folder and has access to
    // create/duplicate pages, then show duplicate button.
    if (
        $folder_parent and check_edit_access($folder_parent)
        and (USER_ROLE < 3 or $user['create_pages'])
    ) {
        $duplicate = '<nav id="button_bar" class="navigation " aria-label="Button Bar">
                            <div class=" btn-group btn-group-sm flex-wrap">
                                <a class="btn btn-link link-secondary py-0 mb-2 " data-loading-content="' . lang('Duplicating') . '" href="duplicate_folder.php?id=' . h($_GET['id']) . '' . $send_to . '"><span class="material-icons me-1">control_point_duplicate</span>' . lang('Duplicate') . '</a>
                            </div>
                        </nav>';
    }
    
    // only display parent folder selection if folder is not root
    if ($folder_parent != 0) {
        $output_folder_row =
            '<div class="col-12 col-md-6 my-2">
                <label for="folder" class="form-label">' . lang('Parent Folder') . '</label>
                <select class="form-select" id="folder" name="folder">' . select_folder($folder_parent, 0, $excluded_folder_id = $folder_id) . '</select>
            </div>';
    } else {
        $output_folder_row = '';
    }
    
    // if user role is Administrator, Designer, or Manager, then allow user to select style and mobile style for folder
    if ($user['role'] < 3) {
        $output_style = '<select class="form-select" id="style" name="style">' . select_style($folder_style) . '</select>';
        $output_mobile_style = '<select class="form-select" id="mobile_style_id" name="mobile_style_id">' . get_mobile_style_options($mobile_style_id) . '</select>';
        
    // else user has a user role
    } else {
        // if there is a style set for this folder
        if ($folder_style) {
            // get style name
            $query = "SELECT style_name FROM style WHERE style_id = '$folder_style'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            $row = mysqli_fetch_assoc($result);
            
            $output_style = h($row['style_name']);
            
        // else there is not a style set for this folder, so get inherited style
        } else {
            // get inherited style
            $style_id = get_style($folder_id, 'desktop');
            
            // get inherited style name
            $query = "SELECT style_name FROM style WHERE style_id = '$style_id'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            $row = mysqli_fetch_assoc($result);
            
            $output_style = lang('Default') . ' (' . lang('inherit') . '): ' . h($row['style_name']);
        }

        // if there is a mobile style set for this folder, then output style name
        if ($mobile_style_id != 0) {
            // get style name
            $query = "SELECT style_name FROM style WHERE style_id = '$mobile_style_id'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            $row = mysqli_fetch_assoc($result);
            
            $output_mobile_style = h($row['style_name']);
            
        // else there is not a mobile style set for this folder, so output inherited mobile style name
        } else {
            // get inherited style
            $mobile_style_id = get_style($folder_id, 'mobile');
            
            // get inherited style name
            $query = "SELECT style_name FROM style WHERE style_id = '$mobile_style_id'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            $row = mysqli_fetch_assoc($result);
            
            $output_mobile_style = lang('Default') . ' (' . lang('inherit') . '): ' . h($row['style_name']);
        }
    }
    
    // is there a child folder in this folder?
    $query = "SELECT folder_id FROM folder WHERE folder_parent = '" . escape($_GET['id']) . "' LIMIT 1";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed');
    if (mysqli_num_rows($result)) {
        $child_exists = true;
    }

    // is there a child page in this folder?
    $query = "SELECT page_id FROM page WHERE page_folder = '" . escape($_GET['id']) . "' LIMIT 1";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed');
    if (mysqli_num_rows($result)) {
        $child_exists = true;
    }

    // is there a child file in this folder?
    $query = "SELECT id FROM files WHERE folder = '" . escape($_GET['id']) . "' LIMIT 1";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed');
    if (mysqli_num_rows($result)) {
        $child_exists = true;
    }

    // if there is a child object in this folder, then disable delete button
    if ($child_exists == true) {
        // check if there is a design file in this folder
        $query = "SELECT id FROM files WHERE (folder = '" . escape($_GET['id']) . "') AND (design = '1') LIMIT 1";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed');
        
        $output_design_file_notice = '';
        
        // if a design file exists then add extra message about that
        if (mysqli_num_rows($result) > 0) {
            $output_design_file_notice = lang(' Also, at least one design file exists in this folder. Design files can be deleted by a user that has access to the design tab.');
        }
        
        $output_delete_button = '<button type="button" name="submit_delete" value="Delete" class="btn my-1  btn-danger " onclick="alert(\'' . lang('Please delete all folders, pages, and files in this folder before deleting this folder.') . $output_design_file_notice . '\');return false; " ><span class="material-icons me-2">delete</span><span class="btn-text" >' . lang(array('string'=>'Delete') ) . '</span></button>';
    // else there is not a child object in this folder, so allow delete
    } else {
        $output_delete_button = '<button type="submit" name="submit_delete" value="Delete" class="btn my-1  btn-danger " data-loading-content="' . lang(array('string'=>'Deleting') ) . '" data-confirm-content="' . lang(array('string'=>'WARNING: This {var:1} will be permanently deleted.','vars'=>array(lang('folder')))) . '"><span class="material-icons me-2">delete</span><span class="btn-text" >' . lang(array('string'=>'Delete') ) . '</span></button>';
    }
    
    $folder_archived_checked = '';
    
    // if folder archived is 1, then check the checkbox
    if ($folder_archived == '1') {
        $folder_archived_checked = ' checked="checked"';
    }
    
    echo
        pg_page_shell(
            array(
                'title'=> lang('Edit Folder'),
                'extra classes'=>'folders',
                'icon'=>'folder',
                'heading'=> lang('Edit Folder'),
                'cancel'=>array('enable'=>'true','url'=>'view_folders.php'),
                'breadcrumb' => array(
                    array('label' => lang('All Folders'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_folders.php'),
                    array('label' => lang('Edit Folder')),
                ),
            )
        ) . '
                    <div class="row">
                <div class="col-12">
                    ' . $form->get_messages() . '
                    <div class="row mb-2  flex-wrap">
                        <div class="col-12 col-sm-12 text-center text-md-start">
<h2 class="d-inline-block text-break header-content-for-add-page" data-bs-content="' . lang('View and update this folder.') . '" title="' . lang('Edit Folder') . '">[' . h($folder_name) . ']</h2>
                            ' . $duplicate . '
                        </div>
                    </div>
                    <form name="form" action="edit_folder.php" method="post">
                        ' . get_token_field() . '
                        <input type="hidden" id="send_to" name="send_to" value="' . (isset($_REQUEST['send_to']) ? h($_REQUEST['send_to']) : '') . '" />
                        <div class="row">
                            <div class="col-12">
                                <div class="card my-4">
                                    <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                        ' . lang('Folder Information') . '
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-12 col-md-8 my-2">
                                                <label for="name" class="form-label">*' . lang('Folder Name') . '</label>
                                                <input id="name" name="name" type="text" value="' . h($folder_name) . '" class="form-control add-header-content-updater ">
                                                <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                            </div>
                                            <div class="col-12 col-md-4  my-2">
                                                <label for="order" class="form-label" data-bs-content="' . lang('Display order of this Folder to other Folders') . '" title="' . lang('Sorting') . '">' . lang('Sorting') . ' (?)</label>
                                                <div class="input-group number-controls">
                                                    <button class="btn material-icons minus border border-end-0" type="button">remove</button>
                                                    <input class="form-control text-center border-start-0 border-end-0"  value="' . $folder_order . '" type="text" name="order" id="order" maxlength="9" inputmode="numeric" data-inputmask-alias="decimal"  data-inputmask-placeholder="0"/>
                                                    <button class="btn material-icons plus border border-start-0" type="button">add</button>
    
                                                </div>
                                            </div>
                                            <div class="col-12 my-3">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" id="folder_archived" name="folder_archived" value="1"' . $folder_archived_checked . ' />
                                                    <label class="form-check-label" for="folder_archived" data-bs-content="' . lang('Archive Folder for Pages and Files that are no longer being used') . '" title="' . lang('Archive') . '">' . lang('Archive') . ' (?)</label>
                                                </div>
                                                
                                            </div>
    
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="card my-4">
                                    <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                        ' . lang('Content Options') . '
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            ' . $output_folder_row . '
                                            <div class="col-12 col-md-6 col-lg-auto my-2">
                                                <label for="access_control_type" class="form-label" data-bs-content="' . lang('Access Control for all Pages and Files within this Folder') . '" title="' . lang('Folder Access Control Type') . '">' . lang('Folder Access Control Type') . ' (?)</label>
                                                <select class="form-select" id="access_control_type" name="access_control_type">' . select_access_control_type($folder_access_control_type) . '</select>
                                            </div>
                                        </div>
                                        <div class="row mt-4">
                                            <div class="col-12 col-md-6 col-lg-auto my-2">
                                                <label for="style" class="form-label">' . lang('Desktop Page Style') . '</label>
                                                ' . $output_style . '
                                            </div>
                                            <div class="col-12 col-md-6 col-lg-auto my-2">
                                                <label for="mobile_style_id" class="form-label">' . lang('Mobile Page Style') . '</label>
                                                ' . $output_mobile_style . '
                                            </div>
                                            <div class="col-12 form-text">' . lang('Default Page Styles for Pages within this Folder') . '</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <input type="hidden" name="id" value="' . h($_GET['id']) . '">      
                        <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons ">
                            <div class="container">
                                <div class=" btn-group flex-wrap justify-content-center">
                                    <button type="submit" id="submit_save" name="submit_save" value="Save" class="btn my-1  btn-success " data-loading-content="' . lang(array('string'=>'Saving') ) . '"><span class="material-icons me-2">save</span><span class="btn-text">' . lang(array('string'=>'Save') ) . '</span></button>
                                    ' . $output_delete_button . '
                                </div>
                            </div>
                        </nav>
                    </form>
                </div>
            </div>
        </main>' .
        output_footer();

        $form->remove();
    
} else {
    validate_token_field();
    
    // Add liveform notices
    include_once('liveform.class.php');
    $liveform_view_folders = new liveform('view_folders');
    
    // if folder was selected for deletion
    if (($_POST['submit_delete'] ?? '') == 'Delete') {
        // assume that a child item does not exist until we find out otherwise
        $child_exists = false;
        
        // is there a child folder in this folder?
        $query = "SELECT folder_id FROM folder WHERE folder_parent = '" . escape($_POST['id'] ?? '') . "' LIMIT 1";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed');
        if (mysqli_num_rows($result)) {
            $child_exists = true;
        }

        // is there a child page in this folder?
        $query = "SELECT page_id FROM page WHERE page_folder = '" . escape($_POST['id'] ?? '') . "' LIMIT 1";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed');
        if (mysqli_num_rows($result)) {
            $child_exists = true;
        }

        // is there a child file in this folder?
        $query = "SELECT id FROM files WHERE folder = '" . escape($_POST['id'] ?? '') . "' LIMIT 1";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed');
        if (mysqli_num_rows($result)) {
            $child_exists = true;
        }

        // if there is a child object in this folder, then output error
        if ($child_exists == true) {
            output_error(lang('Please delete all folders, pages, and files in this folder before deleting this folder.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
        }
        
        // delete entries in acl for this folder
        $result=mysqli_query(db::$con, "DELETE FROM aclfolder WHERE aclfolder_folder = '" . escape($_POST['id'] ?? '') . "'") or output_error('Query failed');
        // delete folder
        $result=mysqli_query(db::$con, "DELETE FROM folder WHERE folder_id = '" . escape($_POST['id'] ?? '') . "'") or output_error('Query failed');
        log_activity(lang(array('string'=>'folder ({var:1}) was deleted','vars'=>array($_POST['name']) )), $_SESSION['sessionusername']);
        $notice = lang('The folder was deleted successfully.');
        
    } else {
        
        // get parent
        $result=mysqli_query(db::$con, "SELECT folder_parent FROM folder WHERE folder_id = '" . escape($_POST['id'] ?? '') . "'") or output_error('Query failed');
        $row=mysqli_fetch_array($result);

        // if select form was blank or not displayed
        if (!$_POST['folder']) {
            // if folder is root
            if ($row['folder_parent'] == 0) {
                $folder = 0;
            // else select form was blank
            } else {
                $folder = $row['folder_parent'];
            }
        // else select form was displayed
        } else {
            $folder = $_POST['folder'];
        }

        // if parent has changed then execute
        if ($folder != $row['folder_parent'])
        {
            
            // find level of folder being moved
            $result=mysqli_query(db::$con, "SELECT folder_level FROM folder WHERE folder_id = '" . escape($_POST['id'] ?? '') . "'") or output_error('Query failed');
            $row=mysqli_fetch_array($result);
            $level_folder = $row['folder_level'];
            
            // get level of parent
            $result=mysqli_query(db::$con, "SELECT folder_level FROM folder WHERE folder_id = '" . escape($folder) . "'") or output_error('Query failed');
            $row=mysqli_fetch_array($result);
            $level_parent = $row['folder_level'];
            // how much each level will change
            $change_level = $level_parent + 1;
            // update the parent and level attributes of the folder being moved
            $result=mysqli_query(db::$con, "UPDATE folder SET folder_parent = '" . escape($folder) . "', folder_level = '$change_level' WHERE folder_id = '" . escape($_POST['id'] ?? '') . "'") or output_error('Query failed');
            
            // if the level of the subfolders do not need to be changed then don't execute code
            if (($level_folder - $level_parent) != 1)
            {
                // change level of sub-folders
                change_level($_POST['id'], $change_level);
            }
        }

        $name = trim($_POST['name']);

        $sql_style_fields = "";
        
        // if user role is Administrator, Designer, or Manager, then allow user to change style and mobile style for folder
        if ($user['role'] < 3) {
            $sql_style_fields =
                "folder_style = '" . escape($_POST['style'] ?? '') . "',
                mobile_style_id = '" . escape($_POST['mobile_style_id'] ?? '') . "',";
        }
        
        $query =
            "UPDATE folder
            SET
                folder_name = '" . escape($name) . "',
                folder_order = '" . escape($_POST['order'] ?? '') . "',
                folder_access_control_type = '" . escape($_POST['access_control_type'] ?? '') . "',
                folder_archived = '" . escape($_POST['folder_archived'] ?? '') . "',
                $sql_style_fields
                folder_timestamp = UNIX_TIMESTAMP(),
                folder_user = '" . $user['id'] . "'
            WHERE folder_id = '" . escape($_POST['id'] ?? '') . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        log_activity(lang(array('string'=>'folder ({var:1}) was modified','vars'=>array($name) )) , $_SESSION['sessionusername']);
        $notice = lang('The folder was edited successfully.');
    }
    // Add notice to liveform.
    $liveform_view_folders->add_notice($notice);


    
    if ((isset($_REQUEST['send_to']) == TRUE) && ($_REQUEST['send_to'] != '')) {
        header('Location: ' . URL_SCHEME . HOSTNAME . $_REQUEST['send_to']);
    // else send user to the default view
    } else {
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_folders.php');
    }
}

// functions

// change level of sub-folders using recursion
function change_level($parent_id, $parent_level)
{
    // find sub-folders that need to be moved so that we can change their level
    $result=mysqli_query(db::$con, "SELECT folder_id, folder_level FROM folder WHERE folder_parent = '" . escape($parent_id) . "'") or output_error('Query failed');
    while($row=mysqli_fetch_array($result))
    {
        // update the level of the sub-folder
        $result2=mysqli_query(db::$con, "UPDATE folder SET folder_level = '" . escape($parent_level) . "' + 1 WHERE folder_id = '" . $row['folder_id'] . "'") or output_error('Query failed');
        // check for subfolders
        $result_check=mysqli_query(db::$con, "SELECT folder_id FROM folder WHERE folder_parent = '" . $row['folder_id'] . "'") or output_error('Query failed');
        if(mysqli_num_rows($result_check) > 0)
        {
            // recursion
            change_level($row['folder_id'], $parent_level + 1);
        }
    }
}