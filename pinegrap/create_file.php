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
        <div class="col-12 my-2">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="design" name="design" value="1">
                <label class="form-check-label" for="design">' . lang('Design File') . '</label>
            </div>
        </div>';
    }

    echo  pg_page_shell(array(
        'title'=> lang('Create File'),
        'icon'=>'file',
        'heading'=>lang('Create File'),
        'heading_description' => lang('Create a new editable file'),
        'cancel'=>array('enable'=>'true','url'=>'view_files.php'),
        'breadcrumb' => array(
            array('label' => lang('All My Files'), 'url' => 'view_files.php'),
            array('label' => lang('Create File')),
        ),
    )) . '
<main id="content" class="container-fluid">
            <div class="row">
            <div class="col-12">
                    ' . $liveform->output_errors() . '
                    ' . $liveform->output_notices() . '
                
                <form action="create_file.php" method="post">
                    ' . get_token_field() . '
                    <div class="row justify-content-center">
                        <div class="col-12 col-md">
                            <div class="row">
                                <div class="col-12">
                                    <div class="card my-4">
                                        <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">' . lang('Main Informations') . '</div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-12 my-2">
                                                    <div class="input-group">
                                                        <input type="text" name="name" id="name" class="form-control " style="width:70%"/>
                                                        <select class="form-select" id="type" name="type" required>
                                                            <option value="txt" selected>.txt</option>
                                                            <option value="json">.json</option>
                                                            <option value="svg">.svg</option>
                                                            <option value="xml">.xml</option>
                                                            <option value="css">.css</option>
                                                            <option value="js">.js</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="card my-4">
                                        <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">' . lang('File Access Control') . '</div>
                                        <div class="card-body">
                                            <div class="col-12 my-2">
                                                <label for="folder" class="form-label">' . lang('Folder') . '</label>
                                                <select class="form-select" id="folder" name="folder">' . select_folder(0, 0) . '</select>
                                            </div>
                                            ' . $output_design_rows . '
                                        </div>
                                    </div>

                                    <div class="card my-4">
                                        <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">' . lang('Description') . '</div>
                                        <div class="card-body">
                                            <div class="col-12 my-2">
                                                <label for="description" class="form-label">' . lang('File Description') . '</label>
                                                <textarea class="form-control" id="description" name="description" style="min-height:85px;"></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-8">
                            <div class="card my-4" id="code_block" style="display:block;">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">' . lang('File Content') . '</div>
                                <div class="card-body">
                                    <div class="col-12 my-2">
                                        <label for="code" class="form-label">' . lang('Code') . '</label>
                                        <textarea name="code" id="code" rows="25" class="form-control"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                    <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons">
                        <div class="container">
                            <div class="btn-group flex-wrap justify-content-center">
                                <button type="submit" class="btn my-1 btn-success">
                                    <span class="bi bi-plus-circle me-2"></span>
                                    <span class="btn-text">' . lang('Create File') . '</span>
                                </button>
                            </div>
                        </div>
                    </nav>
                </form>
            </div>
        </div>

    ' . get_codemirror_includes() . '
    ' . get_codemirror_javascript(array('id' => 'code', 'code_type' => 'text')) . '

    <script>
        function updateMode(type) {
            let mode = "text/plain";
            let lint = false;

            switch(type) {
                case "css": mode = "css"; lint = true; break;
                case "js": mode = "javascript"; lint = true; break;
                case "json": mode = "application/json"; lint = true; break;
                case "xml":
                case "svg": mode = "xml"; lint = true; break;
                case "txt": default: mode = "text/plain"; lint = false; break;
            }

            if (typeof editor !== "undefined") {
                editor.setOption("lint", false);
                editor.setOption("mode", mode);
                editor.setOption("lint", lint);
            }
        }

        document.getElementById("type").addEventListener("change", function() {
            var val = this.value;
            var editable = ["json","txt","svg","xml","css","js"];
            document.getElementById("code_block").style.display = editable.includes(val) ? "block" : "none";
            updateMode(val);
        });

        document.addEventListener("DOMContentLoaded", function() {
            updateMode("txt");
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





    // Create file and write content if applicable
    $handle = fopen($file_path, 'w');
    if ($handle) {
        if ($code !== '' && in_array($type, array('json','txt','svg','xml','css','js'))) {
            fwrite($handle, $code);
        }
        fclose($handle);
    }

    $size = filesize($file_path);

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

    log_activity(lang(array('string'=>'file ({var:1}) was created','vars'=>$name)), $_SESSION['sessionusername']);

    $liveform->add_notice(lang('The file was created successfully.'));
    header('Location: view_files.php');
    exit();
}
?>