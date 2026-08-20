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
validate_area_access($user, 'designer');

if (!$_POST) {
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
            'title'=> lang('Upload Design Files'),
            'extra classes'=>'designer',
            'icon'=>'design', 
            'heading'=> lang('Upload Design Files'),
            'cancel'=>array('enable'=>'true','url'=>'view_design_files.php')
        ,
            'breadcrumb' => array(array('label' => lang('All Design Files'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_design_files.php'), array('label' => lang('Upload Design Files'))),
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
<h2 class="d-inline-block text-break header-content-for-add-page" data-bs-content="' . lang('Drop design files below, update the settings (if necessary), and then click Upload.') . '" title="' . lang('Upload Design Files') . '">[' . lang('New Design File') . ']</h2>
                        <p>' . lang(array('string'=>'Maximum File{suffix:1}','suffix'=>array($max_file_uploads_suffix) )) . ': ' . $max_file_uploads . '</p>
                    </div>
                </div>
                <form enctype="multipart/form-data" action="add_design_file.php" method="post" id="dropzone" class="dropzone" style="border: none;background: unset;padding: unset;">
                    ' . get_token_field() . '
                    <input type="hidden" id="send_to" name="send_to" value="' . (isset($_REQUEST['send_to']) ? h($_REQUEST['send_to']) : '') . '" />
                    <div class="row">
                        <div class="col-12">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Files To Upload') . '
                                </div>
                                <div class="card-body">
                                    <div class="dropzone_previews design">
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
                                        <select class="form-select" id="folder" name="folder">' . select_folder(isset($_GET['id']) ? $_GET['id'] : '', 0) . '</select>
                                    </div>
                                   
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
                                        <textarea class="form-control" id="description" name="description" rows="1"></textarea>
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

    foreach ($_FILES['file']['name'] as $index => $file_name) {
        $file_name = prepare_file_name($file_name);

        $file_name = get_unique_name(array(
            'name' => $file_name,
            'type' => 'file'));

        $array_file_extension = explode('.', $file_name);
        $size_of_array = count($array_file_extension);
        $file_extension = $array_file_extension[$size_of_array - 1];

        copy($_FILES['file']['tmp_name'][$index], FILE_DIRECTORY_PATH . '/' . $file_name);

        db(
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
                '" . escape($file_name) . "',
                '" . escape($_POST['folder'] ?? '') . "',
                '" . escape($_POST['description'] ?? '') . "',
                '" . escape($file_extension) . "',
                '" . escape($_FILES['file']['size'][$index]) . "',
                '1',
                '" . USER_ID . "',
                UNIX_TIMESTAMP())");
        log_activity(lang(array('string'=>'design file ({var:1}) was created','vars'=>$file_name )), $_SESSION['sessionusername']);

    }

    include_once('liveform.class.php');

    $liveform_view_design_files = new liveform('view_design_files');

    $number_of_uploaded_files = count($_FILES['file']['name']);

    // If one file was uploaded, then prepare notice for that.
    if ($number_of_uploaded_files == 1) {
        $liveform_view_design_files->add_notice( lang(array('string'=>'The design file, {var:1}, has been uploaded.','vars'=>array('<a href="' . OUTPUT_PATH . h($file_name) . '" target="_blank">' . h($file_name) . '</a>'))) );


    // Otherwise more than one file was uploaded, so prepare different notice for that.
    } else {
        $liveform_view_design_files->add_notice(lang(array('string'=>'{var:1} design files have been uploaded.','vars'=>array(number_format($number_of_uploaded_files)) )) );

    }

    // If visitor has an old browser and did not use drag-and-drop,
    // then forward visitor to next scren.  Drag-and-drop uses AJAX post
    // to this script, so we don't need to forward visitor anywhere in that case.
    if ($_POST['fallback'] == 'true') {
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_design_files.php');
    }
}
?>
