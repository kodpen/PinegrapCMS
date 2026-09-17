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

$upload_limits = pg_upload_limits();

// Whether the caller wants JSON back. Read from the query string as well as
// the body, because the case immediately below is precisely the one where the
// body is gone — a screen that asked for JSON in a field it no longer has
// would be answered with an HTML error page and report "request failed"
// instead of the reason.
$upload_wants_json =
    ((($_REQUEST['jsonreturn'] ?? '') === 'true')
     || (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'));

// A POST whose body PHP threw away for being over post_max_size.
//
// Without this the script falls through to its "no POST yet" branch, renders
// the upload form again and answers 200 — which Dropzone and the product
// picker both read as success. The operator is told the files were uploaded
// and nothing was written anywhere.
if (pg_post_body_discarded()) {

    $upload_sent = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);

    $upload_message = lang(array(
        'string' => 'Nothing was uploaded. The request was {var:1} and this server accepts at most {var:2} in one upload. Send fewer files at a time, or raise post_max_size and upload_max_filesize on the server.',
        'vars'   => array(
            convert_bytes_to_string($upload_sent, 1),
            convert_bytes_to_string($upload_limits['post_max'], 1))));

    log_activity($upload_message);

    if ($upload_wants_json) {
        // 413 rather than 200: the caller has to be able to tell this apart
        // from a successful upload without reading the sentence.
        http_response_code(413);
        header('Content-Type: application/json; charset=utf-8');
        echo encode_json(array(
            'status'  => 'error',
            'message' => $upload_message));
        exit();
    }

    output_error(h($upload_message) . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
}

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

    // The real ceilings, handed to Dropzone instead of the 9999 MB that used
    // to sit here.
    //
    // 9999 was not a limit, it was the absence of one: the browser packed the
    // whole selection into one request, PHP threw the request away for being
    // over post_max_size, and the screen said the files had been uploaded.
    // Refusing a file in the browser costs the operator one sentence; letting
    // it through costs them the upload and the truth about it.
    $dropzone_file_max_mb = ($upload_limits['file_max'] > 0)
        ? round($upload_limits['file_max'] / (1024 * 1024), 2)
        : 9999;

    // uploadMultiple sends the whole queue as one request, so this is the
    // number that actually decides whether an upload survives.
    $dropzone_request_max = (int) $upload_limits['request_max'];

    $output_upload_limits = '';

    if ($upload_limits['file_max'] > 0) {
        $output_upload_limits =
            '<p class="text-body-secondary mb-0">'
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
            'title'=> lang('Upload File'),
            'extra classes'=>'file',
            'icon'=>'file',
            'heading'=>lang('Upload File'),
            'heading_description' => lang('Drop files below, update the settings (if necessary), and then click Upload.'),
            'cancel'=>array('enable'=>'true','url'=>'view_files.php'),
            'breadcrumb' => array(
                array('label' => lang('All My Files'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_files.php'),
                array('label' => lang('Upload Files')),
            ),

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

            dictRemoveFile : "<span class=\'material-icons btn btn-sm text-danger p-1 rounded position-absolute top-0 end-0\' style=\'z-index:20;\'>delete</span>",
            // Had to set high value for parallelUploads in order for
            // large number of uploads to work (overrides default which is 2).
            parallelUploads: 9999,
            // What this server accepts for one file, in MB.
            maxFilesize: ' . $dropzone_file_max_mb . ',
            dictFileTooBig: "' . lang('This file is too big to upload ({{filesize}} MB). The limit is {{maxFilesize}} MB.') . '",
            previewsContainer: ".dropzone_previews",
            clickable: ".dropzone_previews",
            ' . $output_maxfiles . '

            // Every accepted file goes up in one request, so the queue as a
            // whole has to fit inside post_max_size — a limit Dropzone has no
            // option for, because it has no idea the server has one.
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

                        <p class="mb-0">' . lang(array('string'=>'Maximum File{suffix:1}','suffix'=>array($max_file_uploads_suffix) )) . ': ' . $max_file_uploads . '</p>
                        ' . $output_upload_limits . '
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

    // Which set of rules the incoming images are held to.
    //
    // Opt-in and deliberately narrow: 'product' is the only value, and only
    // the product and variant set screens send it. Everything else that posts
    // here — the file manager form, the image picker's own dropzone, the
    // editor's upload box — keeps landing byte for byte as the visitor sent
    // it, because a file in the library can be a design asset or a print
    // original and resizing one behind the operator's back is not a service.
    $image_profile = (($_POST['image_profile'] ?? '') === 'product') ? 'product' : '';

    $image_settings = pg_image_settings();

    if (!$image_settings['product_optimize']) {
        $image_profile = '';
    }

    // Names of everything written, for the JSON caller further down.
    $uploaded_files = array();

    // And everything that did not get written. A file can fail on its own
    // while the rest of the batch succeeds — one photo over
    // upload_max_filesize, one connection dropped half way — and the old code
    // never looked at the error code at all: it copied an empty temporary name
    // over a real path and then wrote a database row for a file that was not
    // there. The file list then showed a row whose link answered 404 and whose
    // optimize button reported the file missing.
    $failed_files = array();

    foreach ($_FILES['file']['name'] as $index => $file_name) {

        $upload_error = isset($_FILES['file']['error'][$index])
            ? (int) $_FILES['file']['error'][$index]
            : UPLOAD_ERR_NO_FILE;

        if ($upload_error !== UPLOAD_ERR_OK) {

            // UPLOAD_ERR_NO_FILE is an empty slot in the form, not a failure,
            // and saying something about it would mean an error message for
            // every unused row of the fallback file input.
            if ($upload_error !== UPLOAD_ERR_NO_FILE) {

                if (($upload_error === UPLOAD_ERR_INI_SIZE) || ($upload_error === UPLOAD_ERR_FORM_SIZE)) {
                    $failed_message = lang(array(
                        'string' => '{var:1} is larger than {var:2}, the biggest single file this server accepts.',
                        'vars'   => array($file_name, convert_bytes_to_string($upload_limits['file_max'], 1))));

                } elseif ($upload_error === UPLOAD_ERR_PARTIAL) {
                    $failed_message = lang(array(
                        'string' => '{var:1} only arrived in part, so it was not saved. Please try again.',
                        'vars'   => array($file_name)));

                } else {
                    $failed_message = lang(array(
                        'string' => 'The server could not accept {var:1}. Please try again.',
                        'vars'   => array($file_name)));
                }

                log_activity($failed_message);

                $failed_files[] = array(
                    'name'    => $file_name,
                    'message' => $failed_message);
            }

            continue;
        }

        // A name the web server would run or read as its own settings is
        // refused, not renamed: the operator should know it did not arrive.
        if (pg_upload_name_blocked($file_name)) {

            $failed_message = pg_upload_blocked_message($file_name);

            log_activity($failed_message);

            $failed_files[] = array(
                'name'    => $file_name,
                'message' => $failed_message);

            continue;
        }

        $file_name = prepare_file_name($file_name);

        $file_name = get_unique_name(array(
            'name' => $file_name,
            'type' => 'file'));

        $array_file_extension = explode('.', $file_name);
        $size_of_array = count($array_file_extension);
        $file_extension = $array_file_extension[$size_of_array - 1];

        // A failed copy used to be followed by an INSERT anyway, which is how
        // a row can point at a file that was never written. Nothing is
        // recorded for a file that is not on the disk.
        if (!@copy($_FILES['file']['tmp_name'][$index], FILE_DIRECTORY_PATH . '/' . $file_name)) {

            $failed_message = lang(array(
                'string' => 'The server could not accept {var:1}. Please try again.',
                'vars'   => array($file_name)));

            log_activity($failed_message);

            $failed_files[] = array(
                'name'    => $file_name,
                'message' => $failed_message);

            continue;
        }

        $file_path = FILE_DIRECTORY_PATH . '/' . $file_name;
        $file_size = $_FILES['file']['size'][$index];

        // Product photos are compressed on the way in, and scaled down when
        // they are over the ceiling. Nothing is refused and nothing is
        // enlarged: an image below the minimum is stored as it is and reported
        // back so the screen can say so.
        $image_report   = NULL;
        $sql_image_1    = "";
        $sql_image_2    = "";

        if ($image_profile === 'product') {

            // A 24 megapixel photo takes a couple of seconds to resample, and
            // the picker sends a whole selection in one request. The default
            // 30 seconds is a page that dies half way through the third file.
            @set_time_limit(120);

            $image_report = pg_process_image_file($file_path, array(
                'max_dimension' => $image_settings['product_max_dimension'],
                'min_dimension' => $image_settings['product_min_dimension'],
                'quality'       => $image_settings['resize_quality']));

            if ($image_report['status'] === 'success') {

                @clearstatcache(true, $file_path);
                $file_size = filesize($file_path);

                // Flagged optimized so the Files screen does not offer to do
                // it again — the badge there would promise a saving that has
                // already been taken, and pressing it would recompress an
                // image that is already at its target quality.
                $sql_image_1 = "optimized, image_width, image_height, optimization_percent,";
                $sql_image_2 =
                    "'1',
                     '" . (int) $image_report['width'] . "',
                     '" . (int) $image_report['height'] . "',
                     '0',";

                if ($image_report['changed']) {
                    log_activity(lang(array(
                        'string' => 'image ({var:1}) was optimized on upload ({var:2} -> {var:3})',
                        'vars'   => array(
                            $file_name,
                            convert_bytes_to_string($image_report['bytes_before']),
                            convert_bytes_to_string($image_report['bytes_after'])))));
                }
            }
        }

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
                " . $sql_image_1 . "
                " . $sql_design_1 . "
                user,
                timestamp)
            VALUES (
                '" . escape($file_name) . "',
                '" . escape($_POST['folder'] ?? '') . "',
                '" . escape($_POST['description'] ?? '') . "',
                '" . escape($file_extension) . "',
                '" . escape($file_size) . "',
                " . $sql_image_2 . "
                " . $sql_design_2 . "
                '" . USER_ID . "',
                UNIX_TIMESTAMP())");

        log_activity(lang(array('string'=>'file ({var:1}) was created','vars'=>$file_name )), $_SESSION['sessionusername']);

        $uploaded_files[] = array(
            'id'        => mysqli_insert_id(db::$con),
            'name'      => $file_name,
            'type'      => $file_extension,
            'size'      => $file_size,
            // What the browser sent, so the caller can add up the saving.
            'original_size' => (int) $_FILES['file']['size'][$index],
            // Zero when the file was not measured, which is every upload that
            // did not ask for a profile. The caller reads too_small rather
            // than comparing these itself, so the minimum lives in one place.
            'width'     => $image_report ? (int) $image_report['width'] : 0,
            'height'    => $image_report ? (int) $image_report['height'] : 0,
            'resized'   => (bool) ($image_report && $image_report['resized']),
            'too_small' => (bool) ($image_report && $image_report['too_small']));
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
            // The request itself was handled, which is what this reports.
            // Whether every file in it survived is the 'failed' list — a batch
            // where one photo was too big and four were fine is not a failed
            // request, and answering that it is would throw away the four.
            'status'            => 'success',
            'files'             => $uploaded_files,
            'failed'            => $failed_files,
            // Sent so the caller can word its own warning without keeping a
            // second copy of the limit.
            'minimum_dimension' => ($image_profile === 'product') ? (int) $image_settings['product_min_dimension'] : 0));
        exit();
    }

    include_once('liveform.class.php');

    $liveform_view_files = new liveform('view_files');

    // Anything that did not make it, said on the screen the operator lands on
    // rather than only in the error log.
    // add_warning() rather than mark_error(): the errors on that screen are
    // tied to a form field, and there is no field here to attach a file to.
    foreach ($failed_files as $failed_file) {
        $liveform_view_files->add_warning(h($failed_file['message']));
    }

    if (!$uploaded_files) {
        output_error(
            ($failed_files
                ? h($failed_files[0]['message'])
                : lang('Nothing was uploaded.'))
            . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
    }

    $number_of_uploaded_files = count($uploaded_files);

    // If one file was uploaded, then prepare notice for that.
    if ($number_of_uploaded_files == 1) {
        // Read from what was actually written rather than from the loop
        // variable, which after a failed last file names the file that did not
        // land.
        $file_name = $uploaded_files[0]['name'];
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
                header('Location: ' . URL_SCHEME . HOSTNAME . pg_safe_redirect_path(($_REQUEST['send_to'] ?? '')));
            
            // else send user to the default view
            } else {
                header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_files.php');
            }
        }
    }


}
?>
