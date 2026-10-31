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

include('init.php');
include_once('liveform.class.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/folder_form.php');
$liveform_add_folder = new liveform('add_folder');
$user = validate_user();
validate_area_access($user, 'user');

$folder_id = 0;
if(isset($_GET['id']) && $_GET['id'] != ''){
    $folder_id = $_GET['id'];
}


$output_breadcrumb_link = '<li class="breadcrumb-item"><a class="pg-link-secondary " href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_folders.php">' . lang('All Folders') . '</a></li>';

if (!$_POST) {
    print
    pg_page_shell(
        array(
            'title'=> lang('Create Folder'),
            'extra classes'=>'folders',
            'icon'=>'folder',
            'heading'=>lang('Create Folder'),
            'heading_description' => lang('Create a new folder to secure pages & files.'),
            'cancel'=>array('enable'=>'true','url'=>'view_folders.php'),
            'breadcrumb' => array(
                array('label' => lang('All Folders'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_folders.php'),
                array('label' => lang('Create Folder')),
            ),
        )
    ) . '
<main id="content" class="container-fluid">
            <div class="row">
            <div class="col-12">
                ' . $liveform_add_folder->output_errors() . '
                ' . $liveform_add_folder->get_warnings() . '
                ' . $liveform_add_folder->output_notices() . '
                
                <form name="form" action="add_folder.php" method="post">
                    ' . pg_folder_form(array(
                        'name_field' => $liveform_add_folder->output_field(array('type'=>'text','id'=>'name','name'=>'name', 'class'=>'form-control ', 'maxlength'=>'100', 'required'=>'required')),
                        'parent' => $folder_id,
                        'role' => $user['role'],
                    )) . '
                    <input type="hidden" id="submitted_button_field" name="submitted_button_field" value="submit" />          
                    <nav class="buttons navigation text-center position-sticky mb-7" style="bottom:.5rem;" aria-label="' . h(lang('data edit buttons')) . '">
                        <div class="container">
                            <div class=" btn-group flex-wrap justify-content-center">
                                <button type="submit" id="create_button" name="submit_create" value="Create" class="btn my-1  btn-solid theme-success "><span class="bi bi-plus-circle me-3"></span><span class="pg-btn-label">' . lang(array('string'=>'Create') ) . '</span></button>
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
    $result=db_q("SELECT folder_level FROM folder WHERE folder_id = ?", array((string) ($_POST['folder'] ?? ''))) or output_error('Query failed');
    $row=mysqli_fetch_array($result);

    // The parent must exist; otherwise the new folder would hang off a
    // folder id that is not in the tree.
    if ($row === null) {
        output_error(lang('Invalid request.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
    }

    $level = ++$row['folder_level'];
    list($name, $order, $access_control_type, $folder_archived, $style_values) = pg_folder_form_values($_POST, $user['role']);
    
    // If the folder name field is blank.
    if ($name == '') {
        // Create notice.
		$liveform_add_folder->mark_error('name', lang('The folder must have a name. Please type in a name for the folder.') );
    } elseif (pg_name_too_long($name)) {
        // folder_name is varchar(100).
        $liveform_add_folder->mark_error('name', lang(array('string' => 'The name can be at most {var:1} characters long.', 'vars' => array(pg_name_max_length()))));
    }

    if ($liveform_add_folder->check_form_errors() == true) {
        if ((isset($_REQUEST['send_to']) == TRUE) && ($_REQUEST['send_to'] != '')) {
            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/add_folder.php?send_to=' . urlencode($_REQUEST['send_to']));
        } else {
            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/add_folder.php');
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
            "?,
            ?,";
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
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            $sql_style_values
            UNIX_TIMESTAMP(),
            ?)";
    $result = db_q($query, array_merge(
        array(
            $name,
            (string) ($_POST['folder'] ?? ''),
            (string) $level,
            $order,
            $access_control_type,
            $folder_archived,
        ),
        $style_values,
        array((string) $user['id'])
    )) or output_error('Query failed');
    
    log_activity(lang(array('string'=>'folder ({var:1}) was created','vars'=>array($name) )) , $_SESSION['sessionusername']);
    
    $notice = lang('The folder was created successfully.');
    $liveform_view_folders = new liveform('view_folders');
    $liveform_view_folders->add_notice($notice);
    
    if ((isset($_REQUEST['send_to']) == TRUE) && ($_REQUEST['send_to'] != '')) {
        header('Location: ' . URL_SCHEME . HOSTNAME . pg_safe_redirect_path(($_REQUEST['send_to'] ?? '')));
    // else send user to the default view
    } else {
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_folders.php');
    }
    
   
}
?>
