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


 
ini_set('max_execution_time', 0);
ini_set('memory_limit', '-1');


include('init.php');
$user = validate_user();
validate_area_access($user, 'manager');

//list of files and directories that can be delete securely.
//
// A name comes off this list the moment the software starts using it again.
// image_editor_save.php was here as a LiveSite leftover and the name was then
// reused for the Pintura editor's endpoint -- so running the cleanup deleted a
// live part of the software, and every save from the image editor answered 404
// from then on. Nothing on screen connected the two: the tool said it had
// tidied up, and the editor said the image could not be saved.
//
// Before adding a name here, check that nothing references it:
// grep -rn "<name>" --include=*.php --include=*.js .
$file_list = array(
    'image_editor_send.php',
    'image_editor_close.php',
    'threeds_payment.php',
    'changelog.php',
    'widget_api.php',
    'backend.min.css',
    'backend.src.css',
    'backend.min.js',
    'backend.src.js',
    'clean_up_software_trashs.php',
    'clean_up_software_trash.php',
    'add_product_variants.php',
    'assets/add_product_variants.js',
    'pinegrap.php',
    '../terminal.php',
    '../terminal_data.php',
    'files',
    'layouts',
    'error_log',      
    'install/error_log', 
    '../error_log',
    'assets/images/dashboard_bg_l.jpg',
    'assets/images/dashboard_bg_d.jpg',
    'data_api.php',
    'view_products_development.php',
    'datatable_serverside_script.php',
    'JSON.php',
    // The offer library screens. An offer now owns its rule and its actions
    // through edit_offer.php, which writes the same tables; these screens
    // edited rows that several offers could share, so a change made here
    // silently changed every offer that used the row. They were taken out of
    // the menu with 2026.4.4 and nothing links to them any more.
    'view_offer_rules.php',
    'add_offer_rule.php',
    'edit_offer_rule.php',
    'view_offer_actions.php',
    'add_offer_action.php',
    'edit_offer_action.php',
    'includes/templates/view_offer_rules.php',
    'includes/templates/edit_offer_rule.php',
    'apps.php', 
    'apps_settings.php',
    // The single Site Settings screen, 5219 lines of it. Its cards and its
    // save code were split into the eight category modules under
    // includes/settings/, which are reached through the dialog in the header
    // or, where there is no JavaScript, through settings_<category>.php.
    // Nothing links here any more and no code includes it. The dialog's
    // address resolver still recognises a settings.php link found in saved
    // content and opens the right pane instead of following it, so removing
    // the file costs only a bookmark typed straight into the address bar.
    'settings.php',
    // The MailChimp screen. Its card is in Site Settings -> Communication now,
    // reading and writing the same seven config columns, and nothing links
    // here any more.
    'mailchimp_settings.php',
    'includes/templates/mailchimp_settings.php',
    // A development leftover: a one-shot diagnostic page for the
    // shared-component placeholder invariant, written while that invariant was
    // being introduced. The designer's save path enforces the rule itself and
    // nothing links to the page.
    'check_shared_invariant.php',
    // The XML endpoint of the classic folder tree screen. Its script
    // (assets/folder_tree.js, listed below) is already retired and no other
    // code requests the endpoint, so it goes the same way.
    'get_folder_tree.php',


    // The pre-lib/ asset tree, superseded in 2026.4.4 and dropped from the
    // package. Every library below is also carried under assets/lib, the
    // bundles moved to assets/js and assets/css, the PHP libraries belong in
    // includes/, and includes/ now owns the language and template folders.
    // Installations upgraded from an older package still have these on disk,
    // so the names stay here until those sites have run the cleanup.
    'assets/DataTables',
    'assets/GoldenLayout',
    'assets/Inputmask-5.x',
    'assets/Jquery',
    'assets/JsBarcode',
    'assets/backend.src.css',
    'assets/backend.src.js',
    'assets/bootstrap-5.3.0',
    'assets/bootstrap-5.3.8',
    'assets/boxpacker',
    'assets/chartjs',
    'assets/chat_backend.min.js',
    'assets/chat_backend.src.js',
    'assets/ckeditor_4_20',
    'assets/class_suggestions.js',
    'assets/codemirror',
    'assets/codemirror-5.65.9',
    'assets/codemirror_modal.js',
    'assets/colorpicker',
    'assets/dropzone',
    'assets/file-explorer',
    'assets/folder_tree.js',
    'assets/image_editor',
    'assets/iyzipay-php',
    'assets/json2',
    'assets/lazy',
    'assets/lightbox',
    'assets/local',
    'assets/multiselect-checkbox',
    'assets/page_designer.css',
    'assets/page_designer.js',
    'assets/pg_carousel_layout.css',
    'assets/pg_civ_variants.js',
    'assets/pg_lightbox.css',
    'assets/pg_lightbox.js',
    'assets/phpexcel',
    'assets/phpmailer',
    'assets/product_builder.js',
    'assets/select2',
    'assets/stripe',
    'assets/style_designer.css',
    'assets/style_designer.js',
    'assets/templates',
    'assets/tiny_mce_3_5_10',
    'assets/vendor',
);

// Everything the software drops in data/temp is a cache or a scratch file that is written
// again the next time it is needed: the status and database health caches, the file
// integrity baseline (re-fetched from the update server when it is missing), and the
// installer's progress and attempt files. hash_reference.json used to be named here one
// by one, which meant every cache added later kept piling up unnoticed, so the list is
// read off the directory instead.
//
// .htaccess is what keeps the directory unreachable from the web, so it stays.
//
// hash_reference.json stays as well. It is not a cache: on the machine that
// generated it the stamp inside names this host, which is what makes it this
// installation's own truth, and deleting it swaps that for the copy on the
// update server - every local difference then reads as tampering. It comes back
// when it is missing, but not as the same file. pg_purge_caches() leaves it
// alone for the same reason; the bookkeeping file beside it really is a cache
// and goes. A live endpoint must never be listed for deletion, here or above.
$temp_directory = 'data/temp';
$temp_keep = array('.', '..', '.htaccess', 'hash_reference.json');

if (is_dir($temp_directory)) {

    foreach (array_diff((array) @scandir($temp_directory), $temp_keep) as $temp_item) {

        $file_list[] = $temp_directory . '/' . $temp_item;
    }
}


$files = array();
$directory_files = array();

// Get file informations from files.
$query ="SELECT * FROM files";
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
// Loop through the results
while ($row = mysqli_fetch_assoc($result)) {
    $files[] = $row['name']; 
}
// Get the files stored in the upload directory.
foreach(glob(FILE_DIRECTORY_PATH . '/*.*') as $directory_file) {
    // Keep only the base name so it can be compared with the files table.
    $directory_files[] = basename($directory_file);
}
// find files that exist on directory but not exist in db
$file_directory_files_diff = array_diff($directory_files, $files);
$output_delete_button = '';
if (!$_POST) {
    $files = '';
    $output_counter_info = '';
    $folder_counter = 0;
    $file_counter = 0;
    foreach($file_directory_files_diff as $file){
        $file_icon = '<span class="material-icons me-3">insert_drive_file</span>';
        $file_counter++;
        $files .= '<li class="list-group-item bg-reset border-0 text-danger">' . $file_icon . $file . '</li>';
    }
    foreach($file_list as $file){
        if(file_exists($file)){
            if(is_dir($file)){
                $file_icon = '<span class="material-icons me-3">folder</span>';
                $folder_counter++;
            }else{
                $file_icon = '<span class="material-icons me-3">insert_drive_file</span>';
                $file_counter++;
            }
            $files .= '<li class="list-group-item bg-reset border-0 text-danger">' . $file_icon . $file . '</li>';
        }
    }

    // Count expired 3DS payment state records (older than 30 days)
    $expired_3ds_count = 0;
    if (defined('ECOMMERCE') && ECOMMERCE === true) {
        $result_3ds = mysqli_query(db::$con, "SELECT COUNT(*) FROM iyzipay_3ds_state WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        if ($result_3ds) {
            $row_3ds = mysqli_fetch_row($result_3ds);
            $expired_3ds_count = (int)$row_3ds[0];
        }
        if ($expired_3ds_count > 0) {
            $file_counter++;
            $files .= '<li class="list-group-item bg-reset border-0 text-danger"><span class="material-icons me-3">storage</span>iyzipay_3ds_state &mdash; ' . $expired_3ds_count . ' ' . lang(array('string' => 'expired record{suffix}', 'suffix' => ($expired_3ds_count != 1 ? 's' : ''))) . ' (&gt;30 ' . lang('days') . ')</li>';
        }
    }

    // if there is at least 1 folder or file.
    if($folder_counter != 0 || $file_counter != 0){
        $folder_text = '';
        $folder_suffix = '';
        // if there is at least 1 folder
        if($folder_counter != 0){
            //create a suffix
            if($folder_counter != 1){
                $folder_suffix = 's';
            }else{
                $folder_suffix = '';
            }
            $folder_text = ' ' . $folder_counter . ' ' . lang('folder');

        }       
        $and_text = '';
        // if folder and file both more than 0 
        if($folder_counter != 0 && $file_counter != 0){
            $and_text = ' ' . lang('and');
        }
        $file_text = '';
        $file_suffix = '';
        // if there is at least 1 file
        if($file_counter != 0){
            //create a suffix
            if($file_counter != 1){
                $file_suffix = 's';
            }else{
                $file_suffix = '';
            }
            $file_text = ' ' . $file_counter . ' ' . lang('file');

        }
        $output_counter_info = '<div class="alert alert-success">' . lang(array('string'=>'Found{var:1}{suffix:1}{var:2}{var:3}{suffix:2} that can be cleaned.','vars'=>array($folder_text,$and_text,$file_text),'suffix'=>array($folder_suffix,$file_suffix) )) . '</div>';

        $output_delete_button = '<button type="submit" id="delete_button" name="submit_delete" value="Delete" class="btn my-1  btn-success " data-loading-content="' . lang(array('string'=>'Loading') ) . '"><span class="material-icons me-2">auto_delete</span><span class="btn-text" >' . lang(array('string'=>'Delete all unnecessary files and folders') ) . '</span></button>';
    }else{
        include_once('liveform.class.php');
        $liveform = new liveform('settings');
        $liveform->add_notice(lang('Congratulations, the files or folders that need to be deleted were not found'));
        header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/' . pg_settings_return_url());
        exit();
    }
    
    print
    pg_page_shell([
        'title'=> lang('Clean Up'),
        'extra classes'=>'setting',
        'icon'=>'setting',
        'heading'=>lang('Clean Up'),
        'heading_description' => lang('Tool to remove obsolete files and folders inside the software folder.'),
    ]) . '
<main id="content" class="container-fluid">
            <div class="row">
            <div class="col-12">
                
                <form name="form" action="" method="post" class="disable_shortcut">
                    ' . get_token_field() . '
                    <div class="row">
                        <div class="col-12">
                            <div class="card my-4">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 my-2">
                                            ' . $output_counter_info . '
                                            <div class="overflow-auto position-relative mb-3 w-100" style="max-height:500px">
                                                <ul class="list-group bg-reset">' . $files . '</ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>          
                    <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons ">
                        <div class="container">
                            <div class=" btn-group flex-wrap justify-content-center">
                                ' . $output_delete_button . '
                            </div>
                        </div>
                    </nav>
                </form>
            </div>
        </div>
    
</main>' . output_footer();

}else{
    validate_token_field();
    //function that delete all files and subfolders with files.
    function deleteAll($dir) {
        // Use scandir instead of glob to avoid matching '.' and '..'
        // (glob with {,.}* pattern was matching parent directory via '..')
        foreach(array_diff(scandir($dir), array('.', '..')) as $item) {
            $path = $dir . '/' . $item;
            if(is_dir($path))
                deleteAll($path);
            else
                unlink($path);
        }
        rmdir($dir);
    }

    $folder_counter = 0;
    $file_counter = 0;
    $output_counter_info = '';
    $deleted_names = array();

    // Orphans were detected under FILE_DIRECTORY_PATH, so delete them there.
    // Re-check the file right before unlinking so a concurrent upload or a
    // file removed elsewhere does not emit a warning.
    foreach($file_directory_files_diff as $file){
        $orphan_path = FILE_DIRECTORY_PATH . '/' . $file;
        if($file && is_file($orphan_path) && unlink($orphan_path)){
            $file_counter++;
            $deleted_names[] = 'data/files/' . $file;
        }
    }

    foreach($file_list as $file){
        if(file_exists($file)){
            if(is_dir($file)){
                deleteAll($file);
                $folder_counter++;
                $deleted_names[] = $file;
            }else{
                unlink($file);
                $file_counter++;
                $deleted_names[] = $file;
            }
        }
    }

    // Delete expired 3DS payment state records (older than 30 days)
    if (defined('ECOMMERCE') && ECOMMERCE === true) {
        mysqli_query(db::$con, "DELETE FROM iyzipay_3ds_state WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $deleted_3ds_count = (int)mysqli_affected_rows(db::$con);
        if ($deleted_3ds_count > 0) {
            $file_counter += $deleted_3ds_count;
            $deleted_names[] = 'iyzipay_3ds_state (' . $deleted_3ds_count . ' ' . lang(array('string' => 'expired record{suffix}', 'suffix' => ($deleted_3ds_count != 1 ? 's' : ''))) . ')';
        }
    }

    $folder_text = '';
    $folder_suffix = '';
    // if there is at least 1 folder
    if($folder_counter != 0){
        //create a suffix
        if($folder_counter != 1){
            $folder_suffix = 's';
        }else{
            $folder_suffix = '';
        }
        $folder_text = ' ' . $folder_counter . ' ' . lang('folder');
    }       
    $and_text = '';
    // if folder and file both more than 0 
    if($folder_counter != 0 && $file_counter != 0){
        $and_text = ' ' . lang('and');
    }
    $file_text = '';
    $file_suffix = '';
    // if there is at least 1 file
    if($file_counter != 0){
        //create a suffix
        if($file_counter != 1){
            $file_suffix = 's';
        }else{
            $file_suffix = '';
        }
        $file_text = ' ' . $file_counter . ' ' . lang('file');

    }
    $output_counter_info = lang(array('string'=>'{var:1}{suffix:1}{var:2}{var:3}{suffix:2} that deleted.','vars'=>array($folder_text,$and_text,$file_text),'suffix'=>array($folder_suffix,$file_suffix) ));
    log_activity( lang(array('string'=>'{var:1}{suffix:1}{var:2}{var:3}{suffix:2} that deleted with clean up tool. [{var:4}]','vars'=>array($folder_text,$and_text,$file_text,implode(', ', $deleted_names)),'suffix'=>array($folder_suffix,$file_suffix) )), $_SESSION['sessionusername']);

    include_once('liveform.class.php');
    $liveform = new liveform('settings');
    $liveform->add_notice($output_counter_info);
    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/' . pg_settings_return_url());
    exit();

}
?>