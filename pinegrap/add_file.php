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
 *              2016–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();
validate_area_access($user, 'user');

$folder_id = 0;
if(isset($_GET['folder_id']) && $_GET['folder_id'] != ''){
    $folder_id = $_GET['folder_id'];
}

$output_breadcrumb_link = '<li class="breadcrumb-item"><a class="link-secondary " data-loading-content="' . lang('Loading') . '" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_files.php">' . lang('All My Files') . '</a></li>';

if (!$_POST) {
    $output_design_rows = '';

    // if the user is a designer or administrator, then output design rows
    if ($user['role'] <= 1) {
        $output_design_rows =
            '<div class="col-12 my-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="design" name="design" value="1">
                    <label class="form-check-label" for="design" data-bs-content="' . lang('Check if File is a Design File that is Managed by Site Designers') . '" title="" data-bs-original-title="' . lang('Design File') . '">' . lang('Design File') . ' (?)</label>
                </div>
            </div>';
    }

    $max_file_uploads = ini_get('max_file_uploads');

    $max_file_uploads_suffix = '';
    if($max_file_uploads > 1){
        $max_file_uploads_suffix = 's';
    }

    $max_number_of_files = 0;
    if (is_numeric($max_file_uploads) == true) {
        $max_number_of_files = $max_file_uploads;
    }

    $output_maxfiles = '';
    if ($max_number_of_files > 0) {
        //for dropzone
        $output_maxfiles = 'maxFiles: ' . $max_number_of_files . ',';
    }

    echo
    pg_page_shell(
        array(
            'title'=> lang('Upload File'),
            'extra classes'=>'file',
            'icon'=>'file',
            'heading'=>lang('Upload File'),
            'cancel'=>array('enable'=>'true','url'=>'view_files.php'),
            'breadcrumb' => array(
                array('label' => lang('All My Files'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_files.php'),
                array('label' => lang('Upload Files')),
            ),

        )
    ) . '
    <link rel="stylesheet" type="text/css" href="assets/dropzone/dropzone.' . ENVIRONMENT_SUFFIX . '.css?v=' . @filemtime(dirname(__FILE__) . '/assets/dropzone/dropzone.' . ENVIRONMENT_SUFFIX . '.css') . '" />
    <script src="assets/dropzone/dropzone.' . ENVIRONMENT_SUFFIX . '.js?v=' . @filemtime(dirname(__FILE__) . '/assets/dropzone/dropzone.' . ENVIRONMENT_SUFFIX . '.js') . '"></script>
    <script>
        Dropzone.options.dropzone = {
            autoProcessQueue: false,
            uploadMultiple: true,
            addRemoveLinks: true,
            dictMaxFilesExceeded : "' . lang('You can not upload any more files.') . '",
            dictDefaultMessage : "' . lang('Drop files here to upload') . '",
            dictFallbackMessage : "' . lang('Your browser does not support drag and drop file uploads.') . '",
            dictFallbackText : "' . lang('Please use the fallback form below to upload your files like in the olden days.') . '",
            dictInvalidFileType : "' . lang('You can not upload files of this type.') . '",
            dictCancelUploadConfirmation : "' . lang('Are you sure you want to cancel this upload?') . '",
            dictMaxFilesExceeded : "' . lang('You can not upload any more files.') . '",
            dictCancelUpload : "' . lang('Cancel upload') . '",

            dictRemoveFile : "<span class=\'material-icons btn btn-sm text-danger p-1 rounded position-absolute top-0 end-0\' style=\'z-index:20;\'>delete</span>",
            // Had to set high value for parallelUploads in order for
            // large number of uploads to work (overrides default which is 2).
            parallelUploads: 9999,
            // Had to set high value for maxFilesize in order to override
            // the default of 256 MB (value is in MB).
            maxFilesize: 9999,
            previewsContainer: ".dropzone_previews",
            clickable: ".dropzone_previews",
            ' . $output_maxfiles . '

            init: function() {
                var myDropzone = this;
               
                // First change the button to actually tell Dropzone to process the queue.
                this.element.querySelector("button[type=submit]").addEventListener("click", function(e) {
                  // Make sure that the form isnt actually being sent.
                  e.preventDefault();
                  e.stopPropagation();
                  myDropzone.processQueue();
                });

                this.on("sendingmultiple", function() {
                    $("#submit").prop("disabled", true);
                    $("#submit").addClass("disabled");
                    $("#submit").html("<span class=\'spinner-border spinner-border-sm\'></span> ' . lang('Uploading') . '...");
                });

                this.on("successmultiple", function(files, response) {
                    var send_to = $("#send_to").val();

                    if (send_to) {
                        window.location = send_to;
                    } else {
                        window.location = "view_files.php";
                    }
                    
                });
                this.on("errormultiple", function(files, response) {
                    console.log("' . lang('Sorry, the files could not be uploaded.  Please refresh and try again.') . '");
                });
            }
        }
    </script>
            <div class="row">
            <div class="col-12">
                <div class="row mb-2  flex-wrap">
                    <div class="col-12 col-sm-12 text-center text-md-start">
<h2 class="d-inline-block text-break header-content-for-add-page" data-bs-content="' . lang('Drop files below, update the settings (if necessary), and then click Upload.') . '" title="' . lang('Upload Files') . '">[' . lang('New File') . ']</h2>
                        <p>' . lang(array('string'=>'Maximum File{suffix:1}','suffix'=>array($max_file_uploads_suffix) )) . ': ' . $max_file_uploads . '</p>
                    </div>
                </div>
                <form enctype="multipart/form-data" action="add_file.php" method="post" id="dropzone" class="dropzone" style="border: none;background: unset;padding: unset;">
                    ' . get_token_field() . '
                    <input type="hidden" id="send_to" name="send_to" value="' . (isset($_REQUEST['send_to']) ? h($_REQUEST['send_to']) : '') . '" />
                    <div class="row">
                        <div class="col-12">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Files To Upload') . '
                                </div>
                                <div class="card-body">
                                    <div class="dropzone_previews ">
                                        <div class="dz-message needsclick" data-dz-message>
                                            <div class="dz-message ">' . lang('Drop files here or click to browse') . '</div>
                                        </div>
                                        <div class="fallback">
                                            <div class="alert alert-primary">' . lang('It appears that you are using an older browser that does not support dragging & dropping files to upload them. Please use the field below to select one or more files.') . '</div>
                                            <input type="hidden" name="fallback" value="true" />
                                            <div class="mb-3">
                                                <label for="fallback_input" class="form-label">' . lang('Select File(s)') . '</label>
                                                <input class="form-control form-control-sm" id="fallback_input" name="file[]" type="file" multiple="multiple">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('File Access Control') . '
                                </div>
                                <div class="card-body">
                                    <div class="col-12 my-2">
                                        <label for="folder" class="form-label">' . lang('Folder') . '</label>
                                        <select class="form-select" id="folder" name="folder">' . select_folder($folder_id, 0) . '</select>
                                    </div>
                                    ' . $output_design_rows . '
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="card my-4 ">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Description') . '
                                </div>
                                <div class="card-body">
                                    <div class="col-12 my-2">
                                        <label for="description" class="form-label">' . lang('File Description / Photo Gallery Caption') . '</label>
                                        <textarea class="form-control" id="description" name="description" style="min-height:85px;"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>         
                    <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons ">
                        <div class="container">
                            <div class=" btn-group flex-wrap justify-content-center">
                                <button type="submit" id="submit" name="submit" value="Upload" class="btn my-1  btn-success " ><span class="material-icons me-2">file_upload</span><span class="btn-text">' . lang(array('string'=>'Upload') ) . '</span></button>
                            </div>
                        </div>
                    </nav>
                </form>
            </div>
        </div>
    </main>' .
    output_footer();

} else {
    validate_token_field();

    // If the user didn't select a file then output error.
    if ($_FILES['file']['name'][0] == '') {
        output_error(lang('Please select a file.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
    }

    // check that user has access to place file in selected folder
    if (check_edit_access($_POST['folder']) == false) {
        log_activity(lang('access denied to upload file into folder'), $_SESSION['sessionusername']);
        output_error(lang('Access denied.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
    }

    // Names of everything written, for the JSON caller further down.
    $uploaded_files = array();

    foreach ($_FILES['file']['name'] as $index => $file_name) {
        $file_name = prepare_file_name($file_name);

        $file_name = get_unique_name(array(
            'name' => $file_name,
            'type' => 'file'));

        $array_file_extension = explode('.', $file_name);
        $size_of_array = count($array_file_extension);
        $file_extension = $array_file_extension[$size_of_array - 1];

        copy($_FILES['file']['tmp_name'][$index], FILE_DIRECTORY_PATH . '/' . $file_name);

        $sql_design_1 = "";
        $sql_design_2 = "";

        // if the user is a designer or administrator, then save design property
        if ($user['role'] <= 1) {
            $sql_design_1 = "design,";
            $sql_design_2 = "'" . escape($_POST['design'] ?? '') . "',";
        }

        db(
            "INSERT INTO files (
                name,
                folder,
                description,
                type,
                size,
                " . $sql_design_1 . "
                user,
                timestamp)
            VALUES (
                '" . escape($file_name) . "',
                '" . escape($_POST['folder'] ?? '') . "',
                '" . escape($_POST['description'] ?? '') . "',
                '" . escape($file_extension) . "',
                '" . escape($_FILES['file']['size'][$index]) . "',
                " . $sql_design_2 . "
                '" . USER_ID . "',
                UNIX_TIMESTAMP())");

        log_activity(lang(array('string'=>'file ({var:1}) was created','vars'=>$file_name )), $_SESSION['sessionusername']);

        $uploaded_files[] = array(
            'id'   => mysqli_insert_id(db::$con),
            'name' => $file_name,
            'type' => $file_extension,
            'size' => $_FILES['file']['size'][$index]);
    }

    // Screens that upload in the background and then have to show the file
    // straight away ask for JSON. They need the stored name back, which is not
    // the name the browser sent: prepare_file_name() replaces spaces and a few
    // other characters that break the file's URL, and get_unique_name() adds a
    // suffix when the name is taken.
    //
    // Opt-in, so nothing that posts to this script today changes: the file
    // manager form and the picker's dropzone never send this field.
    if (isset($_POST['jsonreturn']) && $_POST['jsonreturn'] == 'true') {
        header('Content-Type: application/json; charset=utf-8');
        echo encode_json(array(
            'status' => 'success',
            'files'  => $uploaded_files));
        exit();
    }

    include_once('liveform.class.php');

    $liveform_view_files = new liveform('view_files');

    $number_of_uploaded_files = count($_FILES['file']['name']);

    // If one file was uploaded, then prepare notice for that.
    if ($number_of_uploaded_files == 1) {
        $liveform_view_files->add_notice( lang(array('string'=>'The file, {var:1}, has been uploaded.','vars'=>array('<a href="' . OUTPUT_PATH . h($file_name) . '" target="_blank">' . h($file_name) . '</a>'))) );

    // Otherwise more than one file was uploaded, so prepare different notice for that.
    } else {
        $liveform_view_files->add_notice(lang(array('string'=>'{var:1} files have been uploaded.','vars'=>array(number_format($number_of_uploaded_files)) )) );
    }


    if($_POST['uploadreturn'] == 'true'){

        if ($_POST['fallback'] == 'true') {

            $url_parameters = '';
            if($_POST['CKEditorFuncNum']){
                $url_parameters = 'CKEditorFuncNum=' . h(urlencode($_POST['CKEditorFuncNum']));
            }
            
            if($_POST['SingleImage']){
                if($url_parameters != ''){
                    $url_parameters .= '&';
                }
                $url_parameters .= 'SingleImage=' . h(urlencode($_POST['SingleImage']));
            }
            
            if($_POST['file_input_name']){
                if($url_parameters != ''){
                    $url_parameters .= '&';
                }
                $url_parameters .= 'file_input_name=' . h(urlencode($_POST['file_input_name']));
            }
            
            if($_POST['send_to']){
                if($url_parameters != ''){
                    $url_parameters .= '&';
                }
                $url_parameters .= 'send_to=' . h(urlencode($_POST['send_to']));
            }
            
            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/editor_select_image.php?return=true&' . $url_parameters);
        }

    }else{
        // If visitor has an old browser and did not use drag-and-drop,
        // then forward visitor to next scren.  Drag-and-drop uses AJAX post
        // to this script, so we don't need to forward visitor anywhere in that case.
        if ($_POST['fallback'] == 'true') {
            // If there is a send to value then send user back to that screen
            if ((isset($_REQUEST['send_to']) == TRUE) && ($_REQUEST['send_to'] != '')) {
                header('Location: ' . URL_SCHEME . HOSTNAME . $_REQUEST['send_to']);
            
            // else send user to the default view
            } else {
                header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_files.php');
            }
        }
    }


}
?>
