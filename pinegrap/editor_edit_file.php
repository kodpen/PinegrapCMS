<?php
include('init.php');
$user = validate_user();
include_once('liveform.class.php');
$liveform = new liveform('editor_edit_file');
validate_area_access($user, 'designer');

// Stop early when the screen is opened without an existing record (stale
// link, hand-typed URL, row deleted meanwhile, an id sent as an array): a
// "not found" screen instead of PHP notices from the unchecked row below or
// an error page. The screen shows the file as text and Save writes over it,
// so the record must also be a design file stored under a plain name in the
// files folder, with one of the text formats below: an image, a font or any
// other file is not found either, on opening and on saving alike.
$id = is_scalar($_REQUEST['id'] ?? null) ? (int) $_REQUEST['id'] : 0;
$file = ($id > 0) ? db_item("SELECT id, name, folder, description FROM files WHERE (id = '" . $id . "') AND (design = '1')") : null;
$file_name = is_array($file) ? (string) $file['name'] : '';
if (
    ($file_name === '')
    || ($file_name !== basename($file_name))
    || (strpos($file_name, '..') !== false)
    || !in_array(mb_strtolower(pathinfo($file_name, PATHINFO_EXTENSION)), array('js', 'json', 'txt', 'svg', 'xml'), true)
) {
    output_error(lang('Record not found.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.', 404);
}

// if there has not been a post then continue to output the page
if (!$_POST) {

    // Detect file extension
    $extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

    // Map extension to the editor's language (pg_code_editor_language())
    switch ($extension) {
        case 'js':
            $code_type = 'javascript';
            break;
        case 'json':
            $code_type = 'application/json';
            break;
        case 'svg':
        case 'xml':
            $code_type = 'xml';
            break;
        case 'txt':
        default:
            $code_type = 'text/plain';
            break;
    }

    // A file recorded here but missing from the disk opens empty with a
    // note instead of a PHP warning; Save writes it again.
    $output_file_missing = '';

    if (is_file(FILE_DIRECTORY_PATH . '/' . $file_name)) {
        $code = file_get_contents(FILE_DIRECTORY_PATH . '/' . $file_name);
    } else {
        $code = '';
        $output_file_missing = '<div class="alert theme-warning">' . lang('The file is recorded here but is not on the disk.') . '</div>';
    }

    // Output the page
    print
    pg_page_shell(
        array(
            'title'=> h($file_name) . ' | ' . lang( array('string'=>'Edit {var:1}','vars'=>strtoupper($extension)) ),
            'extra classes'=>'designer',
            'icon'=>'design',
            'heading'=> lang( array('string'=>'Edit {var:1}','vars'=>strtoupper($extension)) ),
            'cancel'=>array(
                'enable'=>'true',
                'title'=>lang('Cancel'),
                'url'=>pg_safe_back_url(isset($_REQUEST['send_to']) ? $_REQUEST['send_to'] : '', 'view_design_files.php')
            ),
            'breadcrumb' => array(
                array('label' => lang('All Design Files'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_design_files.php'),
                array('label' => lang('Edit Design File'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_design_file.php?id=' . h($_REQUEST['id'])),
                array('label' => lang( array('string'=>'Edit {var:1}','vars'=>strtoupper($extension)) )),
            ),
        )
    ) . '
<main id="content" class="container-fluid">
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->output_notices() . $output_file_missing . '
                <div class="row mb-3 flex-wrap">
                    <div class="col-12 sm:col-12 text-center md:text-start">
<h2 class="d-inline-block text-break" title="' . lang( array('string'=>'Edit {var:1}','vars'=>strtoupper($extension)) ) . '">[' . h($file_name) . ']</h2>
                    </div>
                </div>
                <form name="form" action="editor_edit_file.php" method="post">
                    ' . get_token_field() . '
                    <input type="hidden" name="id" value="' . h($_REQUEST['id']) . '">
                    <input type="hidden" name="name" value="' . h($file_name) . '">
                    <input type="hidden" name="send_to" value="' . h($_REQUEST['send_to'] ?? '') . '">
                    <div class="row">
                        <div class="col-12">
                            <div class="card my-7">
                                <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Options') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 my-3">
                                            <label class="form-label" for="code">' . lang( array('string'=>'{var:1} file','vars'=>strtoupper($extension)) )  . '</label>
                                            <div id="edit_custom">
                                                <textarea name="code" id="code" rows="25" cols="60" wrap="off">' . h($code) . '</textarea>
                                                ' . pg_code_editor_attach(array('id' => 'code', 'code_type' => $code_type)) . '
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <nav class="buttons navigation text-center position-sticky mb-7" style="bottom:.5rem;">
                        <div class="container">
                            <div class="btn-group flex-wrap justify-content-center">
                                <button type="submit" name="submit_save_and_return" value="Save & Return" class="btn my-1 btn-solid theme-primary"><span class="material-icons me-3">arrow_backsave</span><span class="pg-btn-label">' . lang('Save & Return') . '</span></button>
                                <button type="submit" name="submit_save" value="Save" class="btn my-1 btn-solid theme-success"><span class="material-icons me-3">save</span><span class="pg-btn-label">' . lang('Save') . '</span></button>
                                <button type="submit" name="submit_duplicate" value="Duplicate" class="btn my-1 btn-solid theme-secondary"><span class="material-icons me-3">control_point_duplicate</span><span class="pg-btn-label">' . lang('Duplicate') . '</span></button>
                            </div>
                        </div>
                    </nav>
                </form>
            </div>
        </div>
    
</main>' . output_footer();

    // Clears this screen's own form (editor_edit_file): remove_form() takes no
    // argument and always acts on the form the object was made for.
    $liveform->remove_form();

} else {

    validate_token_field();

    // The code is the file's new content. Sent as an array or not at all it
    // counts as not given: nothing is written and the editor opens again
    // with the file as it is (an array emptied the file and ended in an
    // error page).
    pg_request_single_values(array('code'));

    if (!isset($_POST['code'])) {
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/editor_edit_file.php?id=' . $id);
        exit();
    }

    if (($_POST['submit_duplicate'] ?? '') == 'Duplicate') {

        // The copy is named after the stored file, never the posted name,
        // so it keeps the stored file's format.
        $new_file_name = prepare_file_name($file_name);
        $new_file_name = get_unique_name(array('name' => $new_file_name, 'type' => 'file'));
        $file_extension = pathinfo($new_file_name, PATHINFO_EXTENSION);

        $handle = fopen(FILE_DIRECTORY_PATH . '/' . $new_file_name, 'w');
        fwrite($handle, $_POST['code']);
        fclose($handle);

        $row = $file;

        $query = "INSERT INTO files (
            name, folder, description, type, size, design, user, timestamp
        ) VALUES (
            ?,
            ?,
            ?,
            ?,
            ?,
            '1',
            ?,
            UNIX_TIMESTAMP()
        )";
        $result = db_q($query, array(
            (string) $new_file_name,
            (string) $row['folder'],
            (string) $row['description'],
            (string) $file_extension,
            (string) filesize(FILE_DIRECTORY_PATH . '/' . $new_file_name),
            (string) $user['id'],
        )) or output_error('Query failed.');
        $new_file_id = mysqli_insert_id(db::$con);

        log_activity(lang(array('string'=>'a new copy of the file ({var:1}) was created', 'vars'=>$file_name)), $_SESSION['sessionusername']);

        include_once('liveform.class.php');
        $liveform = new liveform('edit_design_file');
        $liveform->add_notice(lang('A new copy of the file has been created.'));

        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/edit_design_file.php?id=' . $new_file_id);

    } else {

        include_once('liveform.class.php');

        // The path is the stored name the gate above checked, never the
        // posted name: a posted name is under the caller's control and could
        // point anywhere on the file system.

        if (is_file(FILE_DIRECTORY_PATH . '/' . $file_name)) {
            unlink(FILE_DIRECTORY_PATH . '/' . $file_name);
        }
        $handle = fopen(FILE_DIRECTORY_PATH . '/' . $file_name, 'w');
        fwrite($handle, $_POST['code']);
        fclose($handle);

        $query = "UPDATE files 
            SET size = ?,
                timestamp = UNIX_TIMESTAMP(),
                user = ? 
            WHERE id = ?";
        $result = db_q($query, array(
            (string) filesize(FILE_DIRECTORY_PATH . '/' . $file_name),
            (string) $user['id'],
            (string) $id,
        )) or output_error('Query failed.');

        log_activity(lang(array('string'=>'the file ({var:1}) was modified','vars'=>$file_name)), $_SESSION['sessionusername']);

        if (($_POST['submit_save'] ?? '') == 'Save') {
            $liveform = new liveform('editor_edit_file');
            $liveform->add_notice(lang('The file was edited successfully.'));
            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/editor_edit_file.php?id=' . $id . '&send_to=' . urlencode($_POST['send_to'] ?? ''));
            exit();
        } else {
            $liveform = new liveform('edit_design_file');
            $liveform->add_notice(lang('The file was edited successfully.'));
            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/edit_design_file.php?id=' . $id . '&send_to=' . urlencode($_POST['send_to'] ?? ''));
            exit();
        }
    }
}