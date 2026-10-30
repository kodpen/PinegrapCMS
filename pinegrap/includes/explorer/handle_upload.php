<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The file manager's upload sub-action: a file dragged from the computer into
 * a folder, written to the file directory and recorded, optionally optimized
 * or turned into WebP on the way in. The handler answers through respond(),
 * which ends the request. Called by pg_explorer_handle() and loaded by
 * view_folder_and_files_f.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The options an uploaded picture goes through pg_process_image_file() with.
//
// Optimize means the site's own picture profile -- the same ceiling and
// quality the upload screen applies -- so a file that comes in through the
// file manager and one that comes in through add_file.php end up the same
// size.
//
// WebP on its own only changes the format: nothing is scaled, because
// "convert to webp" is not a request to make the picture smaller on the page.
function pg_explorer_upload_image_options($image_settings, $optimize, $to_webp, $file_extension)
{
    $image_options = array();

    if ($optimize) {
        $image_options['max_dimension'] = $image_settings['product_max_dimension'];
        $image_options['min_dimension'] = $image_settings['product_min_dimension'];
        $image_options['quality'] = $image_settings['resize_quality'];
    } elseif (isset($image_settings['resize_quality'])) {
        $image_options['quality'] = $image_settings['resize_quality'];
    }

    if ($to_webp && ($file_extension != 'webp')) {
        $image_options['format'] = 'webp';
    }

    return $image_options;
}

// ── Upload (drag files from the computer into the folder) ───────
function pg_explorer_handle_upload($type, $request, $user)
{
    $folder_id = (int) (isset($request['target_folder_id']) ? $request['target_folder_id'] : 0);

    if (($folder_id <= 0) || (check_edit_access($folder_id) == false) || pg_recycle_is_inside($folder_id)) {
        respond(array('status' => 'error', 'message' => lang('You do not have access to upload files to this folder.')));
    }

    $name = isset($request['name']) ? $request['name'] : '';

    // The payload stays inside $request the whole way through. Lifting it into
    // a local first, and then trimming the data URL prefix off that local, made
    // two more copies of a file that is already megabytes long.
    if (($name == '') || (isset($request['data']) == false) || ($request['data'] == '')) {
        respond(array('status' => 'error', 'message' => lang('Invalid request.')));
    }

    // A name the web server would run or read as its own settings is
    // refused, not renamed: the operator should know it did not arrive.
    // Checked before a byte of it is decoded.
    if (pg_upload_name_blocked($name)) {
        log_activity(lang(array('string' => 'upload of {var:1} was refused because files of that type are not allowed', 'vars' => $name)), $_SESSION['sessionusername']);
        respond(array('status' => 'error', 'message' => pg_upload_blocked_message($name)));
    }

    $upload_limits = pg_upload_limits();

    // Measured from the length of the base64 text, so a file that is over the
    // limit is refused before a single byte of it is decoded.
    $expected_size = pg_explorer_base64_length($request['data']);

    if (($upload_limits['json_max'] > 0) && ($expected_size > $upload_limits['json_max'])) {
        respond(array(
            'status' => 'error',
            'message' => lang(array(
                'string' => 'The file is too large. The maximum allowed size is {var:1}.',
                'vars' => convert_bytes_to_string($upload_limits['json_max'])))));
    }

    // Same rules the upload screen applies: URL-safe ASCII name and
    // a unique slot in the shared page/file namespace.
    $name = prepare_file_name($name);
    $name = get_unique_name(array('name' => $name, 'type' => 'file'));

    $file_path = FILE_DIRECTORY_PATH . '/' . $name;

    if (pg_explorer_write_base64($request['data'], $file_path) === false) {
        respond(array('status' => 'error', 'message' => lang('The file could not be saved. Check write permission for the file directory.')));
    }

    $file_extension = mb_strtolower(pathinfo($name, PATHINFO_EXTENSION));

    $image_types = array('jpg', 'jpeg', 'png', 'gif', 'bmp', 'tiff', 'tif', 'webp');
    $is_image = in_array($file_extension, $image_types, true);

    // What the upload window asked for. Both are off unless somebody
    // turned them on: a file that arrives is the file that was sent,
    // and re-encoding it is not something to do quietly.
    $to_webp = (isset($request['to_webp']) && ($request['to_webp'] == true));
    $optimize = (isset($request['optimize']) && ($request['optimize'] == true));

    $image_report = null;

    if ($is_image && ($to_webp || $optimize)) {

        // A 24 megapixel photo takes a couple of seconds to resample,
        // and an upload is a queue of them. The same allowance
        // add_file.php gives itself for the same work.
        @set_time_limit(120);

        $image_options = pg_explorer_upload_image_options(pg_image_settings(), $optimize, $to_webp, $file_extension);


        $image_report = pg_process_image_file($file_path, $image_options);

        // pg_process_image_file() writes back over the path it was
        // given, so after a format change the bytes are webp and the
        // name still says jpg. The name is the address this file is
        // served from, so it has to follow -- and it goes back through
        // the same namespace check, because ".webp" may be taken by
        // something else even though ".jpg" was free.
        if (($image_report['status'] === 'success')
            && (isset($image_options['format']))
            && ($image_report['changed'])) {

            $webp_name = get_unique_name(array(
                'name' => prepare_file_name(pathinfo($name, PATHINFO_FILENAME) . '.webp'),
                'type' => 'file'));

            $webp_path = FILE_DIRECTORY_PATH . '/' . $webp_name;

            if (@rename($file_path, $webp_path)) {
                $name = $webp_name;
                $file_path = $webp_path;
                $file_extension = 'webp';
            }
        }

        @clearstatcache(true, $file_path);
    }

    // Cache image dimensions on insert; getimagesize() reads only the
    // header, and it saves the lazy backfill a full pass later.
    $sql_image_fields = '';
    $sql_image_values = '';

    if ($is_image) {

        // The report already measured what it produced, so an image
        // that has just been through it is not measured twice.
        if (($image_report) && ($image_report['status'] === 'success') && ($image_report['width'] > 0)) {
            $sql_image_fields = 'image_width, image_height,';
            $sql_image_values = "'" . e((int) $image_report['width']) . "', '" . e((int) $image_report['height']) . "',";
        } else {

            $image_size = @getimagesize($file_path);

            if (is_array($image_size)) {
                $sql_image_fields = 'image_width, image_height,';
                $sql_image_values = "'" . e((int) $image_size[0]) . "', '" . e((int) $image_size[1]) . "',";
            }
        }
    }

    // Flagged optimized so the Files screen does not offer to do it
    // again -- the badge there would promise a saving already taken.
    // Only for a real optimize: a plain format change has not been
    // held to the site's quality profile.
    $optimized_flag = (($optimize) && ($image_report) && ($image_report['status'] === 'success')) ? '1' : '0';

    // A designer's flag, and only a designer's: role 2 and 3 have no
    // say over which files the design owns. Same gate add_file.php has.
    $design_flag = ((isset($request['design']) && ($request['design'] == true)) && ($user['role'] <= 1)) ? '1' : '0';

    $description = isset($request['description']) ? trim((string) $request['description']) : '';

    db(
        "INSERT INTO files (
            name,
            folder,
            description,
            type,
            size,
            design,
            optimized,
            " . $sql_image_fields . "
            user,
            timestamp)
        VALUES (
            '" . e($name) . "',
            '" . e($folder_id) . "',
            '" . e($description) . "',
            '" . e($file_extension) . "',
            '" . e(filesize($file_path)) . "',
            '" . e($design_flag) . "',
            '" . e($optimized_flag) . "',
            " . $sql_image_values . "
            '" . e($user['id']) . "',
            UNIX_TIMESTAMP())");

    $file_id = mysqli_insert_id(db::$con);

    pg_audit_record('create', 'files', (int) $file_id);

    log_activity(lang(array('string' => 'The file, {var:1}, has been uploaded.', 'vars' => $name)), $_SESSION['sessionusername']);

    // What was done to the picture is worth its own line: the file on
    // the site is no longer byte for byte the one that was sent, and
    // the only place that is recorded is here.
    if (($image_report) && ($image_report['status'] === 'success') && ($image_report['changed'])) {
        log_activity(lang(array(
            'string' => 'image ({var:1}) was optimized on upload ({var:2} -> {var:3})',
            'vars' => array(
                $name,
                convert_bytes_to_string($image_report['bytes_before']),
                convert_bytes_to_string($image_report['bytes_after'])))),
            $_SESSION['sessionusername']);
    }

    respond(array(
        'status' => 'success',
        'request' => $type,
        'file' => array(
            'id' => (int) $file_id,
            'name' => $name,
            'size_label' => convert_bytes_to_string(filesize($file_path))),
        'message' => lang(array('string' => 'The file, {var:1}, has been uploaded.', 'vars' => $name))));
}
