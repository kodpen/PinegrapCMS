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
validate_area_access($user, 'user');

$liveform = new liveform('create_file');

if (!$_POST) {

    $output_design_rows = '';
    if ($user['role'] <= 1) {
        $output_design_rows = '
                                        <div class="form-field my-5">
                                            <div class="switch"><input type="checkbox" id="design" name="design" value="1"></div>
                                            <label for="design">' . lang('Design File') . '</label>
                                        </div>';
    }

    echo  pg_page_shell(array(
        'title'=> lang('Create File'),
        'icon'=>'file',
        'heading'=>lang('Create File'),
        'heading_description' => lang('Create a new editable file'),
        'cancel'=>array('enable'=>'true','url'=>'view_folders.php?view=files'),
        'breadcrumb' => array(
            array('label' => lang('File Manager'), 'url' => 'view_folders.php?view=files'),
            array('label' => lang('Create File')),
        ),
    )) . '
<main id="content" class="container-fluid">
    ' . $liveform->output_errors() . '
    ' . $liveform->output_notices() . '
    <form action="create_file.php" method="post">
        ' . get_token_field() . '
        <div class="row">
            <div class="col-12 lg:col-8 2xl:col-9 order-1">
                <div class="card mb-7">
                    <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">' . lang('Main Informations') . '</h2>
                    <div class="card-body pt-0">
                        <div class="row">
                            <div class="col-12 my-5">
                                <label for="name" class="form-label">' . lang('File Name') . '</label>
                                <div class="input-group">
                                    <input type="text" name="name" id="name" aria-label="' . h(lang('File Name')) . '" class="form-control" required />
                                    <select class="form-control flex-grow-0 w-auto" id="type" aria-label="' . h(lang('File Type')) . '" name="type" required>
                                        <option value="txt" selected>.txt</option>
                                        <option value="json">.json</option>
                                        <option value="svg">.svg</option>
                                        <option value="xml">.xml</option>
                                        <option value="css">.css</option>
                                        <option value="js">.js</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-12 my-5">
                                <label for="description" class="form-label">' . lang('File Description') . '</label>
                                <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card mb-7" id="code_block">
                    <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">' . lang('File Content') . '</h2>
                    <div class="card-body pt-0">
                        <div class="my-5">
                            <label for="code" class="form-label">' . lang('Code') . '</label>
                            <textarea name="code" id="code" rows="25" class="form-control"></textarea>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 lg:col-4 2xl:col-3 order-2">
                <!-- 3.3rem: the fixed control panel header covers the top of the window. -->
                <div class="position-sticky" style="top:3.3rem;">
                    <div class="card mb-7">
                        <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">' . lang('Summary') . '</h2>
                        <div class="card-body pt-0">
                            <dl class="mb-0">
                                <div class="d-flex justify-content-between gap-5 py-3 border-bottom">
                                    <dt class="fw-normal">' . lang('File Name') . '</dt>
                                    <dd class="mb-0 fw-bold text-end text-break" id="create_file_summary_name">.txt</dd>
                                </div>
                                <div class="d-flex justify-content-between gap-5 py-3">
                                    <dt class="fw-normal">' . lang('Folder') . '</dt>
                                    <dd class="mb-0 fw-bold text-end text-break" id="create_file_summary_folder"></dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                    <div class="card mb-7">
                        <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">' . lang('File Access Control') . '</h2>
                        <div class="card-body pt-0">
                            <div class="my-5">
                                <label for="folder" class="form-label">' . lang('Folder') . '</label>
                                <select class="form-control" id="folder" name="folder">' . select_folder(0, 0) . '</select>
                            </div>' . $output_design_rows . '
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 order-3 position-sticky" style="bottom:0; z-index:3;">
                <nav class="buttons navigation text-center mb-7" aria-label="' . h(lang('data edit buttons')) . '">
                    <div class="container">
                        <div class="btn-group flex-wrap justify-content-center">
                            <button type="submit" class="btn my-1 btn-solid theme-success">
                                <span class="bi bi-plus-circle me-5" aria-hidden="true"></span>
                                <span class="pg-btn-label">' . lang('Create File') . '</span>
                            </button>
                        </div>
                    </div>
                </nav>
            </div>
        </div>
    </form>

    ' . pg_code_editor_attach(array('id' => 'code', 'code_type' => 'text')) . '

    <script>
        // The editor follows the chosen type: its grammar, and the syntax
        // check for everything but plain text. The editor may still be on its
        // way when the page starts; it opens as plain text, the default type.
        function updateMode(type) {
            let language = "html";
            let lint = false;

            switch(type) {
                case "css": language = "css"; lint = true; break;
                case "js": language = "javascript"; lint = true; break;
                case "json": language = "json"; lint = true; break;
                case "xml":
                case "svg": language = "html"; lint = true; break;
                case "txt": default: language = "html"; lint = false; break;
            }

            var editor = window.pgCodeEditors && window.pgCodeEditors.code;

            if (editor && !editor.isDestroyed()) {
                editor.setLanguage(language);
                editor.setLint(lint);
            }
        }

        document.getElementById("type").addEventListener("change", function() {
            var val = this.value;
            var editable = ["json","txt","svg","xml","css","js"];
            document.getElementById("code_block").style.display = editable.includes(val) ? "block" : "none";
            updateMode(val);
        });

        // The Summary follows the name, the type and the folder as they are
        // chosen: the file is stored under that name, made unique on save.
        function showSummary() {
            var folder = document.getElementById("folder");
            var option = folder.options[folder.selectedIndex];
            document.getElementById("create_file_summary_name").textContent = document.getElementById("name").value.trim() + "." + document.getElementById("type").value;
            document.getElementById("create_file_summary_folder").textContent = option ? option.text.trim() : "";
        }

        ["name", "type", "folder"].forEach(function (id) {
            document.getElementById(id).addEventListener("input", showSummary);
            document.getElementById(id).addEventListener("change", showSummary);
        });
        showSummary();

        // The editor arrives after the page; a type chosen before then is
        // applied when it does.
        document.getElementById("code").addEventListener("pgcodeeditorready", function () {
            updateMode(document.getElementById("type").value);
        });
    </script>
    
</main>' . output_footer();

} else {

    validate_token_field();

    $type = strtolower($_POST['type']);
    $name = prepare_file_name($_POST['name'] . '.' . $type);
    $folder = escape($_POST['folder'] ?? '');
    $description = escape($_POST['description'] ?? '');
    $code = isset($_POST['code']) ? $_POST['code'] : '';

    if (!check_edit_access($folder)) {
        output_error(lang('Access denied.'));
    }

    // Creating "shell.php" is uploading it with an extra step. The type is a
    // posted value, not the menu's, so it is checked with the name.
    if (pg_upload_name_blocked($_POST['name'] . '.' . $type)) {
        output_error(h(pg_upload_blocked_message($_POST['name'] . '.' . $type)) . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
    }

    
    // Ensure unique file name
    $name = get_unique_name(array('name' => $name, 'type' => 'file'));
    $file_path = FILE_DIRECTORY_PATH . '/' . $name;





    // Create the file, with the content when the type takes one. A file
    // that could not be written gets no record and no success notice.
    $content = (is_string($code) && in_array($type, array('json','txt','svg','xml','css','js'))) ? $code : '';

    if (!pg_file_write_whole($file_path, $content)) {
        output_error(lang(array('string' => '{var:1} could not be written to disk.', 'vars' => h($name))) . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
    }

    $size = strlen($content);

    $sql_design_1 = '';
    $sql_design_2 = '';
    if ($user['role'] <= 1 && isset($_POST['design'])) {
        $sql_design_1 = "design,";
        $sql_design_2 = "'" . escape($_POST['design'] ?? '') . "',";
    }

    db("INSERT INTO files (
        name, folder, description, type, size, " . $sql_design_1 . " user, timestamp
    ) VALUES (
        '" . escape($name) . "',
        '" . $folder . "',
        '" . $description . "',
        '" . escape($type) . "',
        '" . escape($size) . "',
        " . $sql_design_2 . "
        '" . USER_ID . "',
        UNIX_TIMESTAMP()
    )");
    $new_file_id = (int) mysqli_insert_id(db::$con);
    pg_audit_record('create', 'files', $new_file_id);

    log_activity(lang(array('string'=>'file ({var:1}) was created','vars'=>$name)), $_SESSION['sessionusername']);

    // The notice is left for the File Manager, which prints it (view_folders.php).
    $liveform_view_folders = new liveform('view_folders');
    $liveform_view_folders->add_notice(lang('The file was created successfully.'));
    header('Location: view_folders.php?view=files');
    exit();
}
?>