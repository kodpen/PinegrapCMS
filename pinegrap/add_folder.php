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
include_once('liveform.class.php');
$liveform_add_folder = new liveform('add_folder');
$user = validate_user();
validate_area_access($user, 'user');

$folder_id = 0;
if(isset($_GET['id']) && $_GET['id'] != ''){
    $folder_id = $_GET['id'];
}


$output_breadcrumb_link = '<li class="breadcrumb-item"><a class="link-secondary " data-loading-content="' . lang('Loading') . '" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_folders.php">' . lang('All Folders') . '</a></li>';

if (!$_POST) {
    // if user role is Administrator, Designer, or Manager, then allow user to select style and mobile style for folder
    if ($user['role'] < 3) {
        $output_style = '<select class="form-select" id="style" name="style">' . select_style() . '</select>';
        $output_mobile_style = '<select class="form-select" id="mobile_style_id" name="mobile_style_id">' . get_mobile_style_options() . '</select>';
        
    // else user has a user role so don't allow user to select style and mobile style for folder
    } else {
        $output_style = lang('Default') . ' (' . lang('inherit') . ')';
        $output_mobile_style = lang('Default') . ' (' . lang('inherit') . ')';
    }
    
    print
    pg_page_shell(
        array(
            'title'=> lang('Create Folder'),
            'extra classes'=>'folders',
            'icon'=>'folder',
            'heading'=>lang('Create Folder'),
            'cancel'=>array('enable'=>'true','url'=>'view_folders.php'),
            'breadcrumb' => array(
                array('label' => lang('All Folders'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_folders.php'),
                array('label' => lang('Create Folder')),
            ),
        )
    ) . '
            <div class="row">
            <div class="col-12">
                ' . $liveform_add_folder->output_errors() . '
                ' . $liveform_add_folder->get_warnings() . '
                ' . $liveform_add_folder->output_notices() . '
                <div class="row mb-2  flex-wrap">
                    <div class="col-12 col-sm-12 text-center text-md-start">
<h2 class="d-inline-block text-break header-content-for-add-page" data-bs-content="' . lang('Create a new folder to secure pages & files.') . '" title="' . lang('Create Folder') . '">[' . lang('new folder') . ']</h2>
                    </div>
                </div>
                <form name="form" action="add_folder.php" method="post">
                    ' . get_token_field() . '
                    <input type="hidden" id="send_to" name="send_to" value="' . (isset($_REQUEST['send_to']) ? h($_REQUEST['send_to']) : '') . '" />
                    <div class="row">
                        <div class="col-12">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('New Folder Information') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 col-md-8 my-2">
                                            <label for="name" class="form-label">*' . lang('Folder Name') . '</label>
                                            ' . $liveform_add_folder->output_field(array('type'=>'text','id'=>'name','name'=>'name', 'class'=>'form-control add-header-content-updater ', 'required'=>'required')) . '
                                            <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                        </div>
                                        <div class="col-12 col-md-4  my-2">
                                            <label for="order" class="form-label" data-bs-content="' . lang('Display order of this Folder to other Folders') . '" title="' . lang('Sorting') . '">' . lang('Sorting') . ' (?)</label>
                                            <div class="input-group number-controls">
                                                <button class="btn material-icons minus border border-end-0" type="button">remove</button>
                                                <input class="form-control text-center border-start-0 border-end-0" value="" type="text" name="order" id="order" maxlength="9" inputmode="numeric" data-inputmask-alias="decimal"  data-inputmask-placeholder="0"/>
                                                <button class="btn material-icons plus border border-start-0" type="button">add</button>

                                            </div>
                                        </div>
                                        <div class="col-12 my-3">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="folder_archived" name="folder_archived" value="1" />
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
                                        <div class="col-12 col-md-6 my-2">
                                            <label for="folder" class="form-label">' . lang('Parent Folder') . '</label>
                                            <select class="form-select" id="folder" name="folder">' . select_folder($folder_id) . '</select>
                                        </div>
                                        <div class="col-12 col-md-6 col-lg-auto my-2">
                                            <label for="access_control_type" class="form-label" data-bs-content="' . lang('Access Control for all Pages and Files within this Folder') . '" title="' . lang('Folder Access Control Type') . '">' . lang('Folder Access Control Type') . ' (?)</label>
                                            <select class="form-select" id="access_control_type" name="access_control_type">' . select_access_control_type() . '</select>
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
                    <input type="hidden" id="submitted_button_field" name="submitted_button_field" value="submit" />          
                    <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons ">
                        <div class="container">
                            <div class=" btn-group flex-wrap justify-content-center">
                                <button type="submit" id="create_button" name="submit_create" value="Create" class="btn my-1  btn-success " data-loading-content="' . lang(array('string'=>'Creating') ) . '"><span class="bi bi-plus-circle me-2"></span><span class="btn-text">' . lang(array('string'=>'Create') ) . '</span></button>
                            </div>
                        </div>
                    </nav>
                </form>
            </div>
        </div>
    </main>' .
    output_footer();
    
    $liveform_add_folder->unmark_errors('add_page');
    $liveform_add_folder->clear_notices('add_page');
    
} else {
    validate_token_field();
    
    // validate access to create folder in parent folder
    if (check_edit_access($_POST['folder']) == false) {
        log_activity(lang("access denied because user does not have access to create folder in parent folder"), $_SESSION['sessionusername']);
        output_error(lang('Access denied') . '. <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
    }
    
    // find level of parent
    $result=mysqli_query(db::$con, "SELECT folder_level FROM folder WHERE folder_id = '" . escape($_POST['folder'] ?? '') . "'") or output_error('Query failed');
    $row=mysqli_fetch_array($result);
    $level = ++$row['folder_level'];
    $name = trim($_POST['name']);
    
    // If the folder name field is blank.
    if ($name == '') {
        // Create notice.
		$liveform_add_folder->mark_error('name', lang('The folder must have a name. Please type in a name for the folder.') );
        
        
        if ((isset($_REQUEST['send_to']) == TRUE) && ($_REQUEST['send_to'] != '')) {
            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/add_folder.php?send_to=' . $_REQUEST['send_to']);
        } else {
            header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/add_folder.php');
        }
        
        exit();
    }

    $sql_style_fields = "";
    $sql_style_values = "";
    
    // if user role is Administrator, Designer, or Manager, then allow user to set style and mobile style for folder
    if ($user['role'] < 3) {
        $sql_style_fields =
            "folder_style,
            mobile_style_id,";
        
        $sql_style_values =
            "'" . escape($_POST['style'] ?? '') . "',
            '" . escape($_POST['mobile_style_id'] ?? '') . "',";
    }
    
    // insert row into folder table
    $query =
        "INSERT INTO folder (
            folder_name,
            folder_parent,
            folder_level,
            folder_order,
            folder_access_control_type,
            folder_archived,
            $sql_style_fields
            folder_timestamp,
            folder_user)
        VALUES (
            '" . escape($name) . "',
            '" . escape($_POST['folder'] ?? '') . "',
            '" . escape($level) . "',
            '" . escape($_POST['order'] ?? '') . "',
            '" . escape($_POST['access_control_type'] ?? '') . "',
            '" . escape($_POST['folder_archived'] ?? '') . "',
            $sql_style_values
            UNIX_TIMESTAMP(),
            '$user[id]')";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed');
    
    log_activity(lang(array('string'=>'folder ({var:1}) was created','vars'=>array($name) )) , $_SESSION['sessionusername']);
    
    $notice = lang('The folder was created successfully.');
    $liveform_view_folders = new liveform('view_folders');
    $liveform_view_folders->add_notice($notice);
    
    if ((isset($_REQUEST['send_to']) == TRUE) && ($_REQUEST['send_to'] != '')) {
        header('Location: ' . URL_SCHEME . HOSTNAME . $_REQUEST['send_to']);
    // else send user to the default view
    } else {
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_folders.php');
    }
    
   
}
?>
