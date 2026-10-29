<?php
/**
 * Pinegrap - Enterprise Website Platform
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
validate_area_access($user, 'designer');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/design_file_form.php');

// Dropzone posts with X-Requested-With and counts any 2xx answer as success,
// while output_error() prints an HTML page with status 200. A refusal on that
// path is therefore answered in JSON with a 4xx status; the fallback form
// (no Dropzone) keeps the HTML error pages.
$upload_wants_json = (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest');

// Answers the Dropzone caller with a refusal and stops. "error" repeats the
// sentence because Dropzone writes that key into each file's bubble.
$upload_refusal_answer = function ($status, $message) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo encode_json(array(
        'status'  => 'error',
        'message' => $message,
        'error'   => $message));
    exit();
};

// A POST whose body PHP threw away for being over post_max_size. Without this
// the script falls through to the form branch below, renders the form again
// with 200, and Dropzone reports that the files were uploaded.
if (pg_post_body_discarded()) {

    $upload_limits = pg_upload_limits();
    $upload_sent = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);

    $upload_message = lang(array(
        'string' => 'Nothing was uploaded. The request was {var:1} and this server accepts at most {var:2} in one upload. Send fewer files at a time, or raise post_max_size and upload_max_filesize on the server.',
        'vars'   => array(
            convert_bytes_to_string($upload_sent, 1),
            convert_bytes_to_string($upload_limits['post_max'], 1))));

    log_activity($upload_message);

    if ($upload_wants_json) {
        $upload_refusal_answer(413, $upload_message);
    }

    output_error(h($upload_message) . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
}

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
            'heading_description' => lang('Drop design files below, update the settings (if necessary), and then click Upload.'),
            'cancel'=>array('enable'=>'true','url'=>'view_design_files.php')
        ,
            'breadcrumb' => array(array('label' => lang('All Design Files'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_design_files.php'), array('label' => lang('Upload Design Files'))),
        )
    ) . '
<main id="content" class="container-fluid">
    <link rel="stylesheet" type="text/css" href="assets/lib/dropzone/dropzone.' . ENVIRONMENT_SUFFIX . '.css?v=' . @filemtime(dirname(__FILE__) . '/assets/lib/dropzone/dropzone.' . ENVIRONMENT_SUFFIX . '.css') . '" />
    <script src="assets/lib/dropzone/dropzone.' . ENVIRONMENT_SUFFIX . '.js?v=' . @filemtime(dirname(__FILE__) . '/assets/lib/dropzone/dropzone.' . ENVIRONMENT_SUFFIX . '.js') . '"></script>
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
            dictResponseError : ' . encode_json(lang(array('string' => 'The server answered {var:1} without a readable error message.', 'vars' => array('{{statusCode}}')))) . ',

            dictRemoveFile : "<span class=\'material-icons btn btn-sm fg-danger p-1 rounded-4 position-absolute top-0 end-0\' style=\'z-index:20;\'>delete</span>",
            // Had to set high value for parallelUploads in order for
            // large number of uploads to work (overrides default which is 2).
            parallelUploads: 9999,
            // Had to set high value for maxFilesize in order to override
            // the default of 256 MB (value is in MB).
            maxFilesize: 9999,
            previewsContainer: ".dropzone_previews",
            // The drop area is a plain div that takes no focus; the button
            // beside it opens the same file dialog from the keyboard.
            clickable: [".dropzone_previews", "#pg_upload_choose"],
            ' . $output_maxfiles . '

            init: function() {
                var myDropzone = this;

                // Shown only once Dropzone runs; without it the fallback input below is used.
                document.getElementById("pg_upload_choose").hidden = false;
               
                // First change the button to actually tell Dropzone to process the queue.
                this.element.querySelector("button[type=submit]").addEventListener("click", function(e) {
                  // Make sure that the form isnt actually being sent.
                  e.preventDefault();
                  e.stopPropagation();
                  myDropzone.processQueue();
                });

                this.on("sendingmultiple", function() {
                    $("#pg_upload_error").prop("hidden", true).text("");
                    $("#submit").prop("disabled", true);
                    $("#submit").addClass("disabled");
                });

                this.on("successmultiple", function(files, response) {
                    var send_to = $("#send_to").val();

                    if (send_to) {
                        window.location = send_to;
                    } else {
                        window.location = "view_design_files.php";
                    }
                    
                });
                this.on("errormultiple", function(files, response, xhr) {
                    $("#submit").prop("disabled", false);
                    $("#submit").removeClass("disabled");

                    // A refusal answers JSON {status, message}; a timeout or an
                    // answer with no body gives a string; any other answer is
                    // the raw text of an error page, which is not shown as is.
                    var message = "";
                    if (response && typeof response === "object" && response.message) {
                        message = String(response.message);
                    } else if (typeof response === "string" && $.trim(response) !== "" && !/<[a-z!\/]/i.test(response)) {
                        message = response;
                    } else if (xhr && xhr.status) {
                        message = ' . encode_json(lang('The server answered {var:1} without a readable error message.')) . '.replace("{var:1}", xhr.status);
                    } else {
                        message = ' . encode_json(lang('Sorry, the files could not be uploaded.  Please refresh and try again.')) . ';
                    }

                    $("#pg_upload_error").text(message).prop("hidden", false);

                    // Dropzone wrote the raw answer into each file bubble.
                    $.each(files, function(i, file) {
                        if (file.previewElement) {
                            $(file.previewElement).find("[data-dz-errormessage]").text(message);
                        }
                    });
                });
            }
        }
    </script>
            <div class="row">
            <div class="col-12">
                <div class="row mb-3  flex-wrap">
                    <div class="col-12 sm:col-12 text-center md:text-start">

                        <p>' . lang(array('string'=>'Maximum File{suffix:1}','suffix'=>array($max_file_uploads_suffix) )) . ': ' . $max_file_uploads . '</p>
                    </div>
                </div>
                <div id="pg_upload_error" class="alert theme-danger" role="alert" hidden></div>
                <form enctype="multipart/form-data" action="add_design_file.php" method="post" id="dropzone" class="dropzone" style="border: none;background: unset;padding: unset;">
                    ' . get_token_field() . '
                    <input type="hidden" id="send_to" name="send_to" value="' . (isset($_REQUEST['send_to']) ? h($_REQUEST['send_to']) : '') . '" />
                    <div class="row">
                        <div class="col-12">
                            <div class="card my-7">
                                <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Files To Upload') . '
                                </div>
                                <div class="card-body">
                                    <div class="dropzone_previews design">
                                        <div class="dz-message needsclick" data-dz-message>
                                            <div class="dz-message ">' . lang('Drop files here or click to browse') . '</div>
                                        </div>
                                        <div class="fallback">
                                            <div class="alert theme-primary">' . lang('It appears that you are using an older browser that does not support dragging & dropping files to upload them. Please use the field below to select one or more files.') . '</div>
                                            <input type="hidden" name="fallback" value="true" />
                                            <div class="mb-5">
                                                <label for="fallback_input" class="form-label">' . lang('Select File(s)') . '</label>
                                                <input class="form-control form-control-sm" id="fallback_input" name="file[]" type="file" multiple="multiple">
                                            </div>
                                        </div>
                                    </div>
                                    <button type="button" id="pg_upload_choose" class="btn btn-sm btn-outline theme-primary mt-4" hidden><span class="material-icons me-3" aria-hidden="true">upload_file</span><span class="pg-btn-label">' . lang('Select File(s)') . '</span></button>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 md:col-6">' . pg_design_file_form_access_card(select_folder(isset($_GET['id']) ? $_GET['id'] : '', 0)) . '
                        </div>
                        <div class="col-12 md:col-6">' . pg_design_file_form_description_card('', true) . '
                        </div>
                    </div>         
                    <nav class="buttons navigation text-center position-sticky mb-7" style="bottom:.5rem;" aria-label="' . h(lang('data edit buttons')) . '">
                        <div class="container">
                            <div class=" btn-group flex-wrap justify-content-center">
                                <button type="submit" id="submit" name="submit" value="Upload" class="btn my-1  btn-solid theme-success " ><span class="material-icons me-3">file_upload</span><span class="pg-btn-label">' . lang(array('string'=>'Upload') ) . '</span></button>
                            </div>
                        </div>
                    </nav>
                </form>
            </div>
        </div>
    
</main>' .
    output_footer();

} else {
    if ($upload_wants_json) {
        // The same comparison validate_token_field() makes, answered in JSON.
        $session_token = (string) ($_SESSION['software']['token'] ?? '');
        $post_token = (isset($_POST['token']) && is_string($_POST['token'])) ? $_POST['token'] : '';
        $get_token = (isset($_GET['token']) && is_string($_GET['token'])) ? $_GET['token'] : '';
        if (($session_token === '') || (!hash_equals($session_token, $post_token) && !hash_equals($session_token, $get_token))) {
            $upload_refusal_answer(403, lang('Your session expired. Reload the page and try again.'));
        }
    }

    validate_token_field();

    // If the user didn't select a file then output error.
    if (($_FILES['file']['name'][0] ?? '') == '') {
        if ($upload_wants_json) {
            $upload_refusal_answer(400, lang('Please select a file.'));
        }

        output_error(lang('Please select a file.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
    }

    // Every name is checked before any file is written, so a batch with one
    // refused file in it does not land in halves. A design file is still a
    // file in the web root: a name the server would run is refused here too.
    foreach ($_FILES['file']['name'] as $file_name) {
        if (pg_upload_name_blocked($file_name)) {
            log_activity(lang(array('string' => 'upload of {var:1} was refused because files of that type are not allowed', 'vars' => $file_name)), $_SESSION['sessionusername']);

            if ($upload_wants_json) {
                $upload_refusal_answer(415, pg_upload_blocked_message($file_name));
            }

            output_error(h(pg_upload_blocked_message($file_name)) . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
        }
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
        $liveform_view_design_files->add_notice(lang(array('string'=>'{var:1} design files have been uploaded.','vars'=>array(pg_format_number($number_of_uploaded_files, 0)) )) );

    }

    // If visitor has an old browser and did not use drag-and-drop,
    // then forward visitor to next scren.  Drag-and-drop uses AJAX post
    // to this script, so we don't need to forward visitor anywhere in that case.
    if ($_POST['fallback'] == 'true') {
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_design_files.php');
    }
}
?>
