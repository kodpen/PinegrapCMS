<?php
include('init.php');
$user = validate_user();
include_once('liveform.class.php');
$liveform = new liveform('edit_javascript');
validate_area_access($user, 'designer');

// if there has not been a post then continue to output the page
if (!$_POST) {

    // Get file data from database
    $query = "SELECT name FROM files WHERE id = '" . escape($_REQUEST['id']) . "'";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $row = mysqli_fetch_assoc($result);
    $file_name = $row['name'];

    // Detect file extension
    $extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

    // Map extension to CodeMirror mode
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

    // Get file content
    $code = file_get_contents(FILE_DIRECTORY_PATH . '/' . $file_name);

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
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->output_notices() . '
                <div class="row mb-2 flex-wrap">
                    <div class="col-12 col-sm-12 text-center text-md-start">
<h2 class="d-inline-block text-break" title="' . lang( array('string'=>'Edit {var:1}','vars'=>strtoupper($extension)) ) . '">[' . h($file_name) . ']</h2>
                    </div>
                </div>
                <form name="form" action="editor_edit_file.php" method="post">
                    ' . get_token_field() . '
                    <input type="hidden" name="id" value="' . h($_REQUEST['id']) . '">
                    <input type="hidden" name="name" value="' . h($file_name) . '">
                    <input type="hidden" name="send_to" value="' . h($_REQUEST['send_to']) . '">
                    <div class="row">
                        <div class="col-12">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Options') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 my-2">
                                            <label class="form-label">' . lang( array('string'=>'{var:1} file','vars'=>strtoupper($extension)) )  . '</label>
                                            <div id="edit_custom">
                                                <textarea name="code" id="code" rows="25" cols="60" wrap="off">' . h($code) . '</textarea>
                                                ' . get_codemirror_includes() . '
                                                ' . get_codemirror_javascript(array('id' => 'code', 'code_type' => $code_type)) . '
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;">
                        <div class="container">
                            <div class="btn-group flex-wrap justify-content-center">
                                <button type="submit" name="submit_save_and_return" value="Save & Return" class="btn my-1 btn-primary"><span class="material-icons me-2">arrow_backsave</span><span class="btn-text">' . lang('Save & Return') . '</span></button>
                                <button type="submit" name="submit_save" value="Save" class="btn my-1 btn-success"><span class="material-icons me-2">save</span><span class="btn-text">' . lang('Save') . '</span></button>
                                <button type="submit" name="submit_duplicate" value="Duplicate" class="btn my-1 btn-secondary"><span class="material-icons me-2">control_point_duplicate</span><span class="btn-text">' . lang('Duplicate') . '</span></button>
                            </div>
                        </div>
                    </nav>
                </form>
            </div>
        </div>
    </main>' . output_footer();

    $liveform->remove_form('edit_design_file');

} else {

    validate_token_field();

    if (($_POST['submit_duplicate'] ?? '') == 'Duplicate') {

        $new_file_name = prepare_file_name($_POST['name']);
        $new_file_name = get_unique_name(array('name' => $new_file_name, 'type' => 'file'));
        $file_extension = pathinfo($new_file_name, PATHINFO_EXTENSION);

        $handle = fopen(FILE_DIRECTORY_PATH . '/' . $new_file_name, 'w');
        fwrite($handle, $_POST['code']);
        fclose($handle);

        $query = "SELECT folder, description FROM files WHERE id = '" . escape($_POST['id'] ?? '') . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        $row = mysqli_fetch_assoc($result);

        $query = "INSERT INTO files (
            name, folder, description, type, size, design, user, timestamp
        ) VALUES (
            '" . escape($new_file_name) . "',
            '" . escape($row['folder']) . "',
            '" . escape($row['description']) . "',
            '" . escape($file_extension) . "',
            '" . escape(filesize(FILE_DIRECTORY_PATH . '/' . $new_file_name)) . "',
            '1',
            '$user[id]',
            UNIX_TIMESTAMP()
        )";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        $new_file_id = mysqli_insert_id(db::$con);

        log_activity(lang(array('string'=>'a new copy of the file ({var:1}) was created', 'vars'=>$_POST['name'])), $_SESSION['sessionusername']);

        include_once('liveform.class.php');
        $liveform = new liveform('edit_design_file');
        $liveform->add_notice(lang('A new copy of the file has been created.'));

        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/edit_design_file.php?id=' . $new_file_id);

    } else {

        include_once('liveform.class.php');

        unlink(FILE_DIRECTORY_PATH . '/' . $_POST['name']);
        $handle = fopen(FILE_DIRECTORY_PATH . '/' . $_POST['name'], 'w');
        fwrite($handle, $_POST['code']);
        fclose($handle);

        $query = "UPDATE files 
            SET size = '" . escape(filesize(FILE_DIRECTORY_PATH . '/' . $_POST['name'])) . "',
                timestamp = UNIX_TIMESTAMP(),
                user = '" . $user['id'] . "' 
            WHERE id = '" . escape($_POST['id'] ?? '') . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

        log_activity(lang(array('string'=>'the file ({var:1}) was modified','vars'=>$_POST['name'])), $_SESSION['sessionusername']);

        if (($_POST['submit_save'] ?? '') == 'Save') {
            $liveform = new liveform('editor_edit_file');
            $liveform->add_notice(lang('The file was edited successfully.'));
            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/editor_edit_file.php?id=' . urlencode($_POST['id']) . '&send_to=' . urlencode($_POST['send_to']));
            exit();
        } else {
            $liveform = new liveform('edit_design_file');
            $liveform->add_notice(lang('The file was edited successfully.'));
            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/edit_design_file.php?id=' . urlencode($_POST['id']) . '&send_to=' . urlencode($_POST['send_to']));
            exit();
        }
    }
}