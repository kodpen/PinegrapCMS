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

require_once(PG_FUNCTIONS_DIR . '/includes/forms/design_file_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/cloud_file_picker.php');

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

$output_breadcrumb_link = '<li class="breadcrumb-item"><a class="pg-link-secondary " href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_files.php">' . lang('All My Files') . '</a></li>';

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
            '<p class="fg-secondary mb-0">'
            . h(lang(array(
                'string' => 'At most {var:1} per file, and {var:2} in one upload.',
                'vars'   => array(
                    convert_bytes_to_string($upload_limits['file_max'], 1),
                    convert_bytes_to_string($upload_limits['post_max'], 1)))) )
            . '</p>';
    }

    // Picking a file from the person's own cloud storage: a button per
    // provider they can pick from, a Connect button per provider whose
    // application is set up but whose account they have not connected.
    // Nothing set up, nothing shown.
    $cloud_choices = pg_cloud_picker_choices((int) $user['id']);

    $output_cloud = '';
    $output_cloud_forms = '';

    if ($cloud_choices['pick'] || $cloud_choices['connect']) {

        $cloud_config = array(
            'pick'  => array(),
            'texts' => array(
                'downloading' => lang('Downloading the file to the server…'),
                'failed'      => lang('Sorry, the file could not be added. Please try again.'),
                'load_failed' => lang('The picker could not be loaded. Check the connection and try again.'),
                'popup_blocked' => lang('The browser blocked the picker window. Allow pop-ups for this site and try again.'),
            ));

        $cloud_icons = array('google' => 'bi-google', 'onedrive' => 'bi-microsoft', 'dropbox' => 'bi-dropbox');

        foreach ($cloud_choices['pick'] as $cloud_key => $cloud_pick) {
            $cloud_config['pick'][$cloud_key] = $cloud_pick['client'];

            $output_cloud .= '
                <button type="button" class="btn btn-sm btn-outline theme-primary mt-4 ms-2" data-pg-cloud-pick="' . h($cloud_key) . '">
                    <i class="bi ' . h($cloud_icons[$cloud_key]) . ' me-3" aria-hidden="true"></i><span class="pg-btn-label">' . h(lang(array('string' => 'Pick from {var:1}', 'vars' => array($cloud_pick['label'])))) . '</span></button>';
        }

        foreach ($cloud_choices['connect'] as $cloud_key => $cloud_label) {
            // The button sits inside the upload form but submits its own
            // form, placed outside it (the settings Connect-button pattern).
            $output_cloud .= '
                <button type="submit" form="pg_cloud_connect_' . h($cloud_key) . '" class="btn btn-sm btn-outline theme-secondary mt-4 ms-2">
                    <i class="bi ' . h($cloud_icons[$cloud_key]) . ' me-3" aria-hidden="true"></i><span class="pg-btn-label">' . h(lang(array('string' => 'Connect your {var:1} account', 'vars' => array($cloud_label)))) . '</span></button>';

            $output_cloud_forms .= '
    <form id="pg_cloud_connect_' . h($cloud_key) . '" action="connect.php" method="post" hidden>
        ' . pg_connect_start_fields(pg_cloud_picker_connect_provider($cloud_key), 'file_picker', 'add_file.php?picker_connect=1', 'person') . '
    </form>';
        }

        $cloud_flash = isset($_GET['picker_connect']) ? pg_connect_flash_take() : null;

        $output_cloud .= '
                <p id="pg_cloud_status" class="small mt-3 mb-0' . (($cloud_flash && ($cloud_flash['kind'] === 'error')) ? ' fg-danger' : '') . '" role="status" aria-live="polite">' . ($cloud_flash ? h($cloud_flash['text']) : '') . '</p>';

        if ($cloud_choices['pick']) {
            $output_cloud_forms .= '
    <script type="application/json" id="pg_cloud_picker_config">' . str_replace('</', '<\/', encode_json($cloud_config)) . '</script>'
                . pg_js_bundle_tags('assets/js/cloud_file_picker.min.js');
        }
    }

    echo
    pg_page_shell(
        array(
            'title'=> lang('Upload File'),
            'extra classes'=>'file',
            'icon'=>'file',
            'heading'=>lang('Upload File'),
            'heading_description' => lang('Drop files below, update the settings (if necessary), and then click Upload.'),
            'cancel'=>array('enable'=>'true','url'=>pg_send_to_url('view_files.php')),
            'breadcrumb' => array(
                array('label' => lang('All My Files'), 'url' => pg_send_to_list_url(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_files.php')),
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

                // Shown only once Dropzone runs; without it the fallback input below is used.
                document.getElementById("pg_upload_choose").hidden = false;
               
                // First change the button to actually tell Dropzone to process the queue.
                // By id: a Connect button of the cloud picker is a submit button too.
                document.getElementById("submit").addEventListener("click", function(e) {
                  // Make sure that the form isnt actually being sent.
                  e.preventDefault();
                  e.stopPropagation();
                  myDropzone.processQueue();
                });

                this.on("sendingmultiple", function() {
                    $("#submit").prop("disabled", true);
                    $("#submit").addClass("disabled");
                });

                this.on("successmultiple", function(files, response) {
                    // send_to is checked on the server: only a path on this site.
                    window.location = ' . json_encode(pg_send_to_url('view_files.php'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';
                    
                });
                this.on("errormultiple", function(files, response) {
                    $("#submit").prop("disabled", false);
                    $("#submit").removeClass("disabled");
                    console.log("' . lang('Sorry, the files could not be uploaded.  Please refresh and try again.') . '");
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
                <form enctype="multipart/form-data" action="add_file.php" method="post" id="dropzone" class="dropzone" style="border: none;background: unset;padding: unset;">
                    ' . get_token_field() . '
                    <input type="hidden" id="send_to" name="send_to" value="' . (isset($_REQUEST['send_to']) ? h($_REQUEST['send_to']) : '') . '" />
                    <div class="row">
                        <div class="col-12">
                            <div class="card my-7">
                                <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Files To Upload') . '
                                </div>
                                <div class="card-body">
                                    <div class="dropzone_previews ">
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
                                    <button type="button" id="pg_upload_choose" class="btn btn-sm btn-outline theme-primary mt-4" hidden><span class="material-icons me-3" aria-hidden="true">upload_file</span><span class="pg-btn-label">' . lang('Select File(s)') . '</span></button>' . $output_cloud . '
                                </div>
                            </div>
                        </div>
                        <div class="col-12 md:col-6">' .
                            pg_design_file_form_access_card(select_folder($folder_id, 0), array('design' => ($user['role'] <= 1), 'design_checked' => false)) . '
                        </div>
                        <div class="col-12 md:col-6">' .
                            pg_design_file_form_description_card() . '
                        </div>
                    </div>         
                    <nav class="buttons navigation text-center position-sticky mb-7" style="bottom:.5rem;" aria-label="' . h(lang('data edit buttons')) . '">
                        <div class="container">
                            <div class=" btn-group flex-wrap justify-content-center">
                                <button type="submit" id="submit" name="submit" value="Upload" class="btn my-1  btn-solid theme-success " ><span class="material-icons me-3">file_upload</span><span class="pg-btn-label">' . lang(array('string'=>'Upload') ) . '</span></button>
                            </div>
                        </div>
                    </nav>
                </form>' . $output_cloud_forms . '
            </div>
        </div>
    
</main>' .
    output_footer();

} else {
    // The Google Picker's or the OneDrive picker's token, asked for when the
    // person opens it.
    if (($_POST['cloud_action'] ?? '') === 'token') {
        pg_cloud_picker_token_answer($user);
    }

    // A file picked from the person's cloud storage: the browser sends only
    // its id or link, the server downloads it and stores it below through
    // the same function as a browser upload. Answered in JSON.
    $cloud_provider = (string) ($_POST['cloud_provider'] ?? '');

    if ($cloud_provider !== '') {
        if (!pg_cloud_picker_token_valid()) {
            pg_cloud_picker_json(403, array('status' => 'error', 'message' => lang('Your session expired. Reload the page and try again.')));
        }

        $cloud_choices = pg_cloud_picker_choices((int) $user['id']);

        if (!isset($cloud_choices['pick'][$cloud_provider])) {
            pg_cloud_picker_json(404, array('status' => 'error', 'message' => lang('This kind of connection is not available.')));
        }

        if (check_edit_access($_POST['folder'] ?? '') == false) {
            log_activity(lang('access denied to upload file into folder'), $_SESSION['sessionusername']);
            pg_cloud_picker_json(403, array('status' => 'error', 'message' => lang('Access denied.')));
        }

        $_POST['jsonreturn'] = 'true';

    } else {
        validate_token_field();

        // If the user didn't select a file then output error.
        if (empty($_FILES['file']['name'][0])) {
            output_error(lang('Please select a file.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
        }
    }

    // check that user has access to place file in selected folder
    if (check_edit_access($_POST['folder'] ?? '') == false) {
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

    // Every file is stored by pg_file_store_upload() (includes/fn/files.php),
    // the browser's and the cloud picker's alike, so neither skips a rule.
    $store_options = array(
        'folder'         => $_POST['folder'] ?? '',
        'description'    => $_POST['description'] ?? '',
        'design'         => ($user['role'] <= 1) ? (string) ($_POST['design'] ?? '') : null,
        'image_profile'  => $image_profile,
        'image_settings' => $image_settings,
        'file_max'       => $upload_limits['file_max']);

    foreach (($_FILES['file']['name'] ?? array()) as $index => $file_name) {

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

        $stored = pg_file_store_upload(
            $_FILES['file']['tmp_name'][$index],
            $file_name,
            (int) $_FILES['file']['size'][$index],
            $store_options);

        if ($stored['ok']) {
            $uploaded_files[] = $stored['file'];
        } else {
            log_activity($stored['message']);

            $failed_files[] = array(
                'name'    => $file_name,
                'message' => $stored['message']);
        }
    }

    if ($cloud_provider !== '') {
        $cloud_choice = json_decode((string) ($_POST['cloud_choice'] ?? ''), true);

        $fetched = pg_cloud_picker_fetch(
            $cloud_provider,
            $cloud_choice,
            pg_cloud_picker_connection($cloud_provider, (int) $user['id']),
            $upload_limits['file_max']);

        $stored = $fetched['ok']
            ? pg_file_store_upload($fetched['path'], $fetched['name'], $fetched['size'], $store_options)
            : array('ok' => false, 'message' => $fetched['message']);

        if ($fetched['ok']) {
            @unlink($fetched['path']);
        }

        if ($stored['ok']) {
            $uploaded_files[] = $stored['file'];
        } else {
            log_activity($stored['message']);

            $failed_files[] = array(
                'name'    => ($fetched['name'] !== '') ? $fetched['name'] : $cloud_provider,
                'message' => $stored['message']);
        }
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
        $liveform_view_files->add_notice(lang(array('string'=>'{var:1} files have been uploaded.','vars'=>array(pg_format_number($number_of_uploaded_files, 0)) )) );
    }


    if(($_POST['uploadreturn'] ?? '') == 'true'){

        if (($_POST['fallback'] ?? '') == 'true') {

            $url_parameters = '';
            if(!empty($_POST['CKEditorFuncNum'])){
                $url_parameters = 'CKEditorFuncNum=' . h(urlencode($_POST['CKEditorFuncNum']));
            }
            
            if(!empty($_POST['SingleImage'])){
                if($url_parameters != ''){
                    $url_parameters .= '&';
                }
                $url_parameters .= 'SingleImage=' . h(urlencode($_POST['SingleImage']));
            }
            
            if(!empty($_POST['file_input_name'])){
                if($url_parameters != ''){
                    $url_parameters .= '&';
                }
                $url_parameters .= 'file_input_name=' . h(urlencode($_POST['file_input_name']));
            }
            
            if(!empty($_POST['send_to'])){
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
        if (($_POST['fallback'] ?? '') == 'true') {
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
