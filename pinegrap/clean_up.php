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
 *              2016–2025 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */


 
ini_set('max_execution_time', 0);
ini_set('memory_limit', '-1');


include('init.php');
$user = validate_user();
validate_area_access($user, 'manager');

//list of files and directories that can be delete securely.
$file_list = array(
    'image_editor_send.php',
    'image_editor_save.php',
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
    // Variant wizard, replaced by add_product.php. Listed here so that sites
    // upgrading from an older release do not keep a reachable copy of a screen
    // that no longer exists in the software.
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
    'data/temp/hash_reference.json',
    'assets/images/dashboard_bg_l.jpg',
    'assets/images/dashboard_bg_d.jpg',
);


$files = array();
$directory_files = array();

// Get file informations from files.
$query ="SELECT * FROM files";
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
// Loop through the results
while ($row = mysqli_fetch_assoc($result)) {
    $files[] = $row['name']; 
}
// get files from files/ directory
foreach(glob('files/*.*') as $directory_file) {
    //remove files/ directory name from them
    $directory_files[] = str_replace('files/','',$directory_file);
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
        header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/settings.php');
    }
    
    print
    pg_page_shell([
        'title'=> lang('Clean Up'),
        'extra classes'=>'setting',
        'icon'=>'setting',
        'heading'=>lang('Clean Up'),
        'cancel'        => [
            'enable'  => true,
            'title'   => lang('Return to Settings'),
            'onclick' => "window.location.href='settings.php'"
        ]
    ]) . '
            <div class="row">
            <div class="col-12">
                <div class="row mb-2  flex-wrap">
                    <div class="col-12 col-sm-12 text-center text-md-start">
                        <h2 class="d-inline-block " data-bs-content="' . lang('Tool to remove obsolete files and folders inside the software folder.') . '" title="' . lang('Clean Up') . '">' . lang('Clean Up') . '</h2>
                    </div>
                </div>
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

    foreach($file_directory_files_diff as $file){
        if($file){
            unlink('files/'. $file);
            $file_counter++;
            $deleted_names[] = 'files/' . $file;
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
    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/settings.php');
    exit();

}
?>