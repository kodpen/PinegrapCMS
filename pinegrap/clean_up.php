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

// The retired places of earlier releases (pg_clean_up_file_list(), shared
// with the Security Scan), then the sweep of data/temp below.
$file_list = pg_clean_up_file_list();

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
//
// The rest of what stays is data nothing writes again, listed with the
// reason for each in pg_clean_up_temp_keep() (includes/fn/files.php): the
// accountant packs, the firewall's CSP reports and queues, the PHP error log,
// today's AI counter and the server rules repair's backups.
$temp_directory = 'data/temp';
$temp_keep = pg_clean_up_temp_keep();

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
    // The Summary shows the expired payment records on their own row; the
    // counts sentence and the confirm text count them with the files.
    $record_counter = null;
    foreach($file_directory_files_diff as $file){
        $file_counter++;
        $files .= pg_clean_up_list_row('insert_drive_file', $file);
    }
    foreach($file_list as $file){
        if(file_exists($file)){
            if(is_dir($file)){
                $file_icon = 'folder';
                $folder_counter++;
            }else{
                $file_icon = 'insert_drive_file';
                $file_counter++;
            }
            $files .= pg_clean_up_list_row($file_icon, $file);
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
        $record_counter = $expired_3ds_count;
        if ($expired_3ds_count > 0) {
            $file_counter++;
            $files .= '<li class="list-group-item bg-reset border-0 fg-danger"><span class="material-icons me-5">storage</span>iyzipay_3ds_state &mdash; ' . $expired_3ds_count . ' ' . lang(array('string' => 'expired record{suffix}', 'suffix' => ($expired_3ds_count != 1 ? 's' : ''))) . ' (&gt;30 ' . lang('days') . ')</li>';
        }
    }

    // if there is at least 1 folder or file.
    if($folder_counter != 0 || $file_counter != 0){
        $output_counter_info = '<p class="mb-5">' . pg_clean_up_count_text('Found{var:1}{suffix:1}{var:2}{var:3}{suffix:2} that can be cleaned.', $folder_counter, $file_counter) . '</p>';

        // The delete is permanent, so the panel's click handler asks first
        // (data-confirm-content) and the button wears the danger colour.
        $confirm_text = lang(array('string' => 'Permanently delete everything listed ({var:1})? This cannot be undone.', 'vars' => ($folder_counter + $file_counter)));
        $output_delete_button = '<button type="submit" id="delete_button" name="submit_delete" value="Delete" class="btn my-1 btn-solid theme-danger" data-confirm-content="' . h($confirm_text) . '"><span class="material-icons me-3">auto_delete</span><span class="pg-btn-label" >' . lang(array('string'=>'Delete all unnecessary files and folders') ) . '</span></button>';
    }else{
        include_once('liveform.class.php');
        $liveform = new liveform('system_tools');
        $liveform->add_notice(lang('Congratulations, the files or folders that need to be deleted were not found'));
        header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/system_tools.php');
        exit();
    }
    
    print
    pg_page_shell([
        'title'=> lang('Clean Up'),
        'extra classes'=>'setting pg-measure-edit',
        'icon'=>'setting',
        'heading'=>lang('Clean Up'),
        'heading_description' => lang('Tool to remove obsolete files and folders inside the software folder.'),
    ]) . '
<main id="content" class="container-fluid">
    <form name="form" action="" method="post" class="disable_shortcut">
        ' . get_token_field() . '
        <div class="row">
            <div class="col-12 lg:col-8 2xl:col-9 order-1">
                <div class="card mb-7">
                    <h2 class="card-header bg-reset border-0 pg-title-section">
                        ' . lang('Items to Delete') . '
                    </h2>
                    <div class="card-body pt-0">
                        ' . $output_counter_info . '
                        <div class="overflow-auto position-relative w-100" style="max-height:500px">
                            <ul class="list-group bg-reset">' . $files . '</ul>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 lg:col-4 2xl:col-3 order-2">
                <!-- 3.3rem: the fixed control panel header covers the top of the window. -->
                <div class="position-sticky" style="top:3.3rem;">' . pg_clean_up_summary($folder_counter, ($record_counter > 0) ? $file_counter - 1 : $file_counter, $record_counter) . '
                </div>
            </div>' . pg_save_bar($output_delete_button) . '
        </div>
    </form>
</main>' . output_footer();

}else{
    validate_token_field();
    $folder_counter = 0;
    $file_counter = 0;
    $output_counter_info = '';
    $deleted_names = array();
    // What was listed but is still there: no right to delete it, or held
    // open by another request. Reported by name, never counted as deleted.
    $failed_names = array();

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

    // A Figma import's work file left behind is stopped from that file
    // first: the pictures it fetched and the shared rows it made go with
    // it, as "Stop" in the New design window takes them.
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/figma_start.php');

    foreach($file_list as $file){
        if((dirname($file) === $temp_directory) && pg_figma_is_work_file(basename($file))){
            pg_figma_import_stop_path($file);
            if(!file_exists($file)){
                $file_counter++;
                $deleted_names[] = $file;
            }
        }
        if(file_exists($file)){
            $is_folder = is_dir($file);
            if(!pg_clean_up_delete_path($file)){
                $failed_names[] = $file;
            }elseif($is_folder){
                $folder_counter++;
                $deleted_names[] = $file;
            }else{
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

    include_once('liveform.class.php');
    $liveform = new liveform('system_tools');

    $output_counter_info = pg_clean_up_count_text('{var:1}{suffix:1}{var:2}{var:3}{suffix:2} that deleted.', $folder_counter, $file_counter);

    if ($output_counter_info !== '') {
        log_activity(pg_clean_up_count_text('{var:1}{suffix:1}{var:2}{var:3}{suffix:2} that deleted with clean up tool. [{var:4}]', $folder_counter, $file_counter, array(implode(', ', $deleted_names))), $_SESSION['sessionusername']);
        $liveform->add_notice($output_counter_info);
    }

    if ($failed_names) {
        log_activity(lang(array('string' => 'The clean up tool could not delete: {var:1}', 'vars' => implode(', ', $failed_names))), $_SESSION['sessionusername']);
        $liveform->add_warning(h(lang(array('string' => 'These could not be deleted (the web server may not remove them, or they are in use): {var:1}', 'vars' => implode(', ', $failed_names)))));
    } elseif ($output_counter_info === '') {
        // The list emptied between the preview and the click (a second tab,
        // another run): there was nothing left to delete.
        $liveform->add_notice(lang('Congratulations, the files or folders that need to be deleted were not found'));
    }

    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/system_tools.php');
    exit();

}
?>