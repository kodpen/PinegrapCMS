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
$user = validate_user();
include_once('liveform.class.php');
$liveform = new liveform('edit_javascript');
validate_area_access($user, 'designer');

// Stop early when the screen is opened without an existing record (stale
// link, hand-typed URL, row deleted meanwhile, an id sent as an array): a
// "not found" screen instead of PHP notices from the unchecked row below or
// an error page. The screen shows the file as text and Save writes over it,
// so the record must also be a design file stored under a plain .js name in
// the files folder: an image, a font or any other file is not found either,
// on opening and on saving alike.
$id = is_scalar($_REQUEST['id'] ?? null) ? (int) $_REQUEST['id'] : 0;
$file = ($id > 0) ? db_item("SELECT id, name, folder, description FROM files WHERE (id = '" . $id . "') AND (design = '1')") : null;
$file_name = is_array($file) ? (string) $file['name'] : '';
if (
    ($file_name === '')
    || ($file_name !== basename($file_name))
    || (strpos($file_name, '..') !== false)
    || (mb_strtolower(pathinfo($file_name, PATHINFO_EXTENSION)) !== 'js')
) {
    output_error(lang('Record not found.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.', 404);
}

// if there has not been a post then continue to output the page
if (!$_POST) {
    // A file recorded here but missing from the disk opens empty with a
    // note instead of a PHP warning; Save writes it again.
    $output_file_missing = '';

    if (is_file(FILE_DIRECTORY_PATH . '/' . $file_name)) {
        $code = file_get_contents(FILE_DIRECTORY_PATH . '/' . $file_name);
    } else {
        $code = '';
        $output_file_missing = '<div class="alert alert-warning">' . lang('The file is recorded here but is not on the disk.') . '</div>';
    }
    
    // output the page
    print
    pg_page_shell( array(
        'cancel'=>array('enable'=>'true','url'=>pg_safe_back_url(isset($_REQUEST['send_to']) ? $_REQUEST['send_to'] : '', 'view_design_files.php')),
        'breadcrumb' => array(
            array('label' => lang('All Design Files'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_design_files.php'),
            array('label' => lang('Edit Design File'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_design_file.php?id=' . h($_REQUEST['id'])),
            array('label' => lang('Edit JS')),
        ),
    ) ) . '
<main id="content" class="container-fluid">
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->output_notices() . $output_file_missing . '
                <div class="row mb-2  flex-wrap">
                    <div class="col-12 col-sm-12 text-center text-md-start">
<h2 class="d-inline-block text-break" data-bs-content="' . lang('You are editing this JS file.') . '" title="' . lang('Edit JS') . '">[' . h($file_name) . ']</h2>
                    </div>
                </div>
                <form name="form" action="edit_javascript.php" method="post">
                    ' . get_token_field() . '
                    <input type="hidden" name="id" value="' . h($_REQUEST['id']) . '">
                    <input type="hidden" name="name" value="' . h($file_name) . '">
                    <input type="hidden" name="send_to" value="' . h($_REQUEST['send_to'] ?? '') . '">
                    <div class="row">
                        <div class="col-12">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Options') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 my-2">
                                            <label class="form-label">' . lang('JS File') . '</label>
                                            <div id="edit_custom">
                                                <textarea name="code" id="code" rows="25" cols="60" wrap="off">' . h($code) . '</textarea>
                                                ' . pg_code_editor_attach(array('id' => 'code', 'code_type' => 'javascript')) . '
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
                                <button type="submit" id="submit_save_and_return" name="submit_save_and_return" value="Save & Return" class="btn my-1  btn-primary"><span class="material-icons me-2">arrow_backsave</span><span class="pg-btn-label">' . lang(array('string'=>'Save & Return') ) . '</span></button>
                                <button type="submit" id="submit_save" name="submit_save" value="Save" class="btn my-1 btn-success"><span class="material-icons me-2">save</span><span class="pg-btn-label">' . lang(array('string'=>'Save') ) . '</span></button>
                                <button type="submit" id="submit_duplicate" name="submit_duplicate" value="Duplicate" class="btn my-1 btn-secondary"><span class="material-icons me-2">control_point_duplicate</span><span class="pg-btn-label">' . lang(array('string'=>'Duplicate') ) . '</span></button>
                            </div>
                        </div>
                    </nav>
                </form>
            </div>
        </div>
    
</main>' . output_footer();

    // Clears this screen's own form (edit_javascript): remove_form() takes no
    // argument and always acts on the form the object was made for.
    $liveform->remove_form();
    
// else process the file
} else {
    validate_token_field();

    // The code is the file's new content. Sent as an array or not at all it
    // counts as not given: nothing is written and the editor opens again
    // with the file as it is (an array emptied the file and ended in an
    // error page).
    pg_request_single_values(array('code'));

    if (!isset($_POST['code'])) {
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/edit_javascript.php?id=' . $id);
        exit();
    }
    
    // if save new copy was selected, then save a new copy of the file
    if (($_POST['submit_duplicate'] ?? '') == 'Duplicate') {
        // The copy is named after the stored file, never the posted name,
        // so it is always a .js file.
        $new_file_name = prepare_file_name($file_name);

        $new_file_name = get_unique_name(array(
            'name' => $new_file_name,
            'type' => 'file'));

        // Get the position of the last period in order to get the extension.
        $position_of_last_period = mb_strrpos($new_file_name, '.');

        $file_extension = '';
        
        // If there is an extension then remember it.
        if ($position_of_last_period !== false) {
            $file_extension = mb_substr($new_file_name, $position_of_last_period + 1);
        }
        
        // save the file
        $handle = fopen(FILE_DIRECTORY_PATH . '/' . $new_file_name, 'w');
        fwrite($handle, $_POST['code']);
        fclose($handle);
        
        // data from the file we are duplicating
        $row = $file;
        
        // insert duplicated file's data into files table
        $query =
            "INSERT INTO files (
                name,
                folder,
                description,
                type,
                size,
                design,
                user,
                timestamp)
            VALUES (
                '" . escape($new_file_name) . "',
                '" . escape($row['folder']) . "',
                '" . escape($row['description']) . "',
                '" . escape($file_extension) . "',
                '" . escape(filesize(FILE_DIRECTORY_PATH . '/' . $new_file_name)) . "',
                '1',
                '$user[id]',
                UNIX_TIMESTAMP())";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        $new_file_id = mysqli_insert_id(db::$con);

        log_activity(lang(array('string'=>'a new copy of the JS file ({var:1}) was created', 'vars'=>$file_name)), $_SESSION['sessionusername']);
        include_once('liveform.class.php');
        $liveform = new liveform('edit_design_file');
        $liveform->add_notice(lang('A new copy of the JS file has been created.'));
        
        // send user to edit the newly duplicated javascript file
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/edit_design_file.php?id=' . $new_file_id);
    
    // else, save the file
    } else {
        include_once('liveform.class.php');

        // The path is the stored name the gate above checked, never the
        // posted name: a posted name is under the caller's control and could
        // point anywhere on the file system.

        // delete the existing file. we have to do this in order to avoid permission errors in certain cirumstances
        if (is_file(FILE_DIRECTORY_PATH . '/' . $file_name)) {
            unlink(FILE_DIRECTORY_PATH . '/' . $file_name);
        }
        
        // update the content in the file
        $handle = fopen(FILE_DIRECTORY_PATH . '/' . $file_name, 'w');
        fwrite($handle, $_POST['code']);
        fclose($handle);
        
        // update file in database
        $query =
            "UPDATE files 
            SET 
                size = '" . escape(filesize(FILE_DIRECTORY_PATH . '/' . $file_name)) . "',
                timestamp = UNIX_TIMESTAMP(), 
                user = '" . $user['id'] . "' 
            WHERE id = '" . $id . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        // log the change that the file was modified
        log_activity(lang(array('string'=>'the JavaScript for theme file ({var:1}) was modified','vars'=>$file_name)), $_SESSION['sessionusername']);

        if (($_POST['submit_save'] ?? '') == 'Save') {
            // send the user back to reload this screen again
            
            $liveform = new liveform('edit_javascript');
            $liveform->add_notice(lang('The JavaScript file was edited successfully.'));
            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/edit_javascript.php?id=' . $id . '&send_to=' . urlencode($_POST['send_to'] ?? ''));
            exit();
        } else {
            // send user back to edit design file screen
            $liveform = new liveform('edit_design_file');
            $liveform->add_notice(lang('The JavaScript file was edited successfully.'));
            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/edit_design_file.php?id=' . $id . '&send_to=' . urlencode($_POST['send_to'] ?? ''));
            exit();
        }
    }
}
?>
