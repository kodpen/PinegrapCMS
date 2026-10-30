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

$upload_limits = pg_upload_limits();

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

    // The server's own ceilings, as on Upload File (add_file.php): a file
    // over upload_max_filesize arrives with no content, and a queue over
    // post_max_size arrives with no body at all.
    $dropzone_file_max_mb = ($upload_limits['file_max'] > 0)
        ? round($upload_limits['file_max'] / (1024 * 1024), 2)
        : 9999;

    // uploadMultiple sends the whole queue as one request.
    $dropzone_request_max = (int) $upload_limits['request_max'];

    $output_upload_limits = '';

    if ($upload_limits['file_max'] > 0) {
        $output_upload_limits =
            '<p class="fg-secondary mb-0">'
            . h(lang(array(
                'string' => 'At most {var:1} per file, and {var:2} in one upload.',
                'vars'   => array(
                    convert_bytes_to_string($upload_limits['file_max'], 1),
                    convert_bytes_to_string($upload_limits['post_max'], 1)))) )
            . '</p>';
    }

    echo
    
    pg_page_shell(
        array(
            'title'=> lang('Upload Design Files'),
            'extra classes'=>'designer',
            'icon'=>'design', 
            'heading'=> lang('Upload Design Files'),
            'heading_description' => lang('Drop design files below, update the settings (if necessary), and then click Upload.'),
            'cancel'=>array('enable'=>'true','url'=>pg_send_to_url('view_design_files.php'))
        ,
            'breadcrumb' => array(array('label' => lang('All Design Files'), 'url' => pg_send_to_list_url(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_design_files.php')), array('label' => lang('Upload Design Files'))),
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
            // What this server accepts for one file, in MB.
            maxFilesize: ' . $dropzone_file_max_mb . ',
            dictFileTooBig: "' . lang('This file is too big to upload ({{filesize}} MB). The limit is {{maxFilesize}} MB.') . '",
            previewsContainer: ".dropzone_previews",
            // The drop area is a plain div that takes no focus; the button
            // beside it opens the same file dialog from the keyboard.
            clickable: [".dropzone_previews", "#pg_upload_choose"],
            ' . $output_maxfiles . '

            // Every accepted file goes up in one request, so the queue as a
            // whole has to fit inside post_max_size.
            accept: function (file, done) {

                var request_max = ' . $dropzone_request_max . ';
                var total       = file.size;
                var i;

                if (request_max > 0) {

                    for (i = 0; i < this.files.length; i++) {
                        if ((this.files[i] !== file) && this.files[i].accepted) {
                            total += this.files[i].size;
                        }
                    }

                    if (total > request_max) {
                        done("' . lang(array(
                            'string' => 'This would take the upload over {var:1}. Upload the rest separately.',
                            'vars'   => array(convert_bytes_to_string($upload_limits['post_max'], 1)))) . '");
                        return;
                    }
                }

                done();
            },

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
                    // send_to is checked on the server: only a path on this site.
                    window.location = ' . json_encode(pg_send_to_url('view_design_files.php'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';
                    
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

                        <p class="mb-0">' . lang(array('string'=>'Maximum File{suffix:1}','suffix'=>array($max_file_uploads_suffix) )) . ': ' . $max_file_uploads . '</p>
                        ' . $output_upload_limits . '
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

    // Names of the files written, and the files that were not with the reason.
    // A file over upload_max_filesize arrives with an error code and an empty
    // temporary name; copying that name threw on PHP 8 half way through the
    // batch, and on PHP 7 left a row for a file that was never written.
    $uploaded_files = array();
    $failed_files = array();

    foreach ($_FILES['file']['name'] as $index => $original_name) {

        $upload_error = isset($_FILES['file']['error'][$index])
            ? (int) $_FILES['file']['error'][$index]
            : UPLOAD_ERR_NO_FILE;

        if ($upload_error !== UPLOAD_ERR_OK) {

            // An empty slot of the fallback file input, not a failure.
            if ($upload_error === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            // The same sentences Upload File (add_file.php) gives.
            if (($upload_error === UPLOAD_ERR_INI_SIZE) || ($upload_error === UPLOAD_ERR_FORM_SIZE)) {
                $failed_message = lang(array(
                    'string' => '{var:1} is larger than {var:2}, the biggest single file this server accepts.',
                    'vars'   => array($original_name, convert_bytes_to_string($upload_limits['file_max'], 1))));

            } elseif ($upload_error === UPLOAD_ERR_PARTIAL) {
                $failed_message = lang(array(
                    'string' => '{var:1} only arrived in part, so it was not saved. Please try again.',
                    'vars'   => array($original_name)));

            } else {
                $failed_message = lang(array(
                    'string' => 'The server could not accept {var:1}. Please try again.',
                    'vars'   => array($original_name)));
            }

            log_activity($failed_message);
            $failed_files[] = array('name' => $original_name, 'message' => $failed_message);
            continue;
        }

        $file_name = prepare_file_name($original_name);

        $file_name = get_unique_name(array(
            'name' => $file_name,
            'type' => 'file'));

        $array_file_extension = explode('.', $file_name);
        $size_of_array = count($array_file_extension);
        $file_extension = $array_file_extension[$size_of_array - 1];

        // A failed copy writes no row.
        if (!@copy($_FILES['file']['tmp_name'][$index], FILE_DIRECTORY_PATH . '/' . $file_name)) {
            $failed_message = lang(array(
                'string' => 'The server could not accept {var:1}. Please try again.',
                'vars'   => array($original_name)));

            log_activity($failed_message);
            $failed_files[] = array('name' => $original_name, 'message' => $failed_message);
            continue;
        }

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

        $uploaded_files[] = $file_name;
    }

    // Nothing was written: say why instead of landing on the list. The
    // failures are not kept as warnings, because the operator stays here.
    if (!$uploaded_files) {
        $upload_failed_message = $failed_files
            ? implode(' ', array_column($failed_files, 'message'))
            : lang('Nothing was uploaded.');

        if ($upload_wants_json) {
            $upload_refusal_answer(400, $upload_failed_message);
        }

        output_error(h($upload_failed_message) . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
    }

    include_once('liveform.class.php');

    $liveform_view_design_files = new liveform('view_design_files');

    // Files that did not make it, said on the list the operator lands on.
    foreach ($failed_files as $failed_file) {
        $liveform_view_design_files->add_warning(h($failed_file['message']));
    }

    $number_of_uploaded_files = count($uploaded_files);

    // If one file was uploaded, then prepare notice for that.
    if ($number_of_uploaded_files == 1) {
        $file_name = $uploaded_files[0];
        $liveform_view_design_files->add_notice( lang(array('string'=>'The design file, {var:1}, has been uploaded.','vars'=>array('<a href="' . OUTPUT_PATH . h($file_name) . '" target="_blank">' . h($file_name) . '</a>'))) );


    // Otherwise more than one file was uploaded, so prepare different notice for that.
    } else {
        $liveform_view_design_files->add_notice(lang(array('string'=>'{var:1} design files have been uploaded.','vars'=>array(pg_format_number($number_of_uploaded_files, 0)) )) );

    }

    // If visitor has an old browser and did not use drag-and-drop,
    // then forward visitor to next scren.  Drag-and-drop uses AJAX post
    // to this script, so we don't need to forward visitor anywhere in that case.
    if (($_POST['fallback'] ?? '') == 'true') {
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_design_files.php');
    }
}
?>
