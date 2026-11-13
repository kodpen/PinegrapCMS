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
// image_editor_save.php was once here as a LiveSite leftover while the name
// served the previous image editor's endpoint -- so running the cleanup
// deleted a live part of the software, and every save from the image editor
// answered 404 from then on. That editor is gone now (the image editor is
// assets/js/pg_image_editor.js, saved through image_editor_api.php), and its
// files are listed below.
//
// Before adding a name here, check that nothing references it:
// grep -rn "<name>" --include=*.php --include=*.js .
$file_list = array(
    'image_editor_save.php',
    'assets/js/image_editor_modal.js',
    'assets/lib/image_editor',
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
    // The config.php editor. A screen that rewrote any define() in the file
    // let an administrator switch PHP execution back on from the panel; the
    // file is edited by hand where a site needs a setting changed.
    'edit_config.php',
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
    // Legacy LiveSite container templates. The root add_container.php,
    // edit_container.php and view_containers.php render their markup inline,
    // and no page type maps to these templates, so nothing includes them.
    'includes/templates/edit_container.php',
    'includes/templates/view_containers.php',
    // The XML endpoint of the old product-group tree widget. No screen or
    // script requests it any more.
    'get_product_group_tree.php',
    // A third-party single-file barcode generator that sat at the web root
    // with its own request handler, callable by anyone without a session and
    // rendering an image of whatever size the request asked for. Nothing in
    // the software requested it: barcodes are drawn in the browser by
    // JsBarcode. Removed from the package with 2026.4.4.
    'barcode.php',


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
    // The designer's fixed list of Bootstrap classes, replaced by the smart
    // completion (assets/js/pg_style_suggest.js with the predictor and the
    // generated vocabulary). Dropped from the package with 2026.11.0.
    'assets/js/class_suggestions.js',
    // The designer's script, now served as assets/js/style_designer.min.js
    // built from assets/js/style_designer.src.js. Renamed with 2026.11.0.
    'assets/js/style_designer.js',
    'assets/codemirror',
    'assets/codemirror-5.65.9',
    'assets/codemirror_modal.js',
    // CodeMirror 5, replaced by the code editor facade (assets/js/
    // pg_code_editor.js, CodeMirror 6 in assets/lib/code_editor) on every
    // screen, and the rich text editor's own copy, replaced by its
    // pgcodeeditor plugin (assets/js/pg_ckeditor_source.js). Dropped from the
    // package with 2026.11.0.
    'assets/lib/codemirror',
    'assets/lib/codemirror-5.65.9',
    'assets/lib/ckeditor_4_20/plugins/codemirror',
    // The jQuery UI timepicker add-on. The panel's and the sites' date and
    // time fields are drawn by assets/js/pg_datetime.js and pg_fields.js, so
    // no page loads it. Dropped from the package with 2026.11.0.
    'assets/lib/Jquery/jquery-ui-timepicker-addon-1.2.1.min.js',
    'assets/lib/Jquery/jquery-ui-timepicker-addon-1.2.1.src.js',
    // DataTables and its checkbox extension. Every panel list is drawn by
    // the list table driver (assets/js/pg_list_table.js) or is a plain
    // table, so no page loads them. Dropped from the package with 2026.11.0.
    'assets/lib/DataTables',
    'assets/lib/Jquery/jquery_datatables_checkbox.min.js',
    'assets/lib/Jquery/jquery_datatables_checkbox.min.js.map',
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
    // The folder screens and the classic files list, retired with 2026.11.0:
    // folders are made, renamed, set up and given their access rules in the
    // File Manager (view_folders.php), whose Files view has the list's
    // filters, sorting and actions.
    'add_folder.php',
    'edit_folder.php',
    'includes/forms/folder_form.php',
    'view_files.php',
    // The legacy page designer, retired with 2026.11.0: a classic style
    // opens in the visual designer and a page's custom layout in
    // edit_page_layout.php.
    'page_designer.php',
    'assets/js/page_designer.js',
    'assets/css/page_designer.css',
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
    // The logo of the third-party image editor the software used before its
    // own; nothing shows it since the image editor became pg_image_editor.js.
    'assets/images/icon_image_editor.png',
    // The rich text editor's old link window; the Link dialog and the
    // visual designer choose through the picker (pg_editor_bridge_script()).
    'editor_select_page_or_file.php',
    // The rich text editor's old image window; the Image dialog, the product
    // builder's pictures and a contact's photo choose through the picker.
    'editor_select_image.php',
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
        'extra classes'=>'setting',
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
                    <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
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
            </div>
            <div class="col-12 order-3 position-sticky" style="bottom:0; z-index:3;">
                <nav class="buttons navigation text-center mb-7" aria-label="' . h(lang('data edit buttons')) . '">
                    <div class="container">
                        <div class="btn-group flex-wrap justify-content-center">
                            ' . $output_delete_button . '
                        </div>
                    </div>
                </nav>
            </div>
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

    foreach($file_list as $file){
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