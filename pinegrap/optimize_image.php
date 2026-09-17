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

/**
 * Run one file through the image engine and write the result back to its row.
 *
 * Two modes, and the difference is the reason there are two buttons:
 *
 *   'optimize'  strips metadata and recompresses at the same pixel size. What
 *               this function has always done, and what every screen that
 *               calls it without a mode still gets.
 *
 *   'resize'    scales the longest edge down to the configured ceiling first,
 *               and only for images above the configured trigger. This is the
 *               only thing that helps with a camera original: compression
 *               alone takes an 8 MB photo to about 6 MB, because the weight is
 *               in the pixel count.
 *
 * Resizing is never the default. A design asset placed at exact dimensions
 * must survive the optimize button, so the button that changes dimensions is
 * a separate one that says so.
 *
 * @param int    $file_id
 * @param string $mode 'optimize' (default) or 'resize'
 * @return array status => 'success' | 'error', message => string
 */
function optimize_image($file_id, $mode = 'optimize') {

    $resize = ($mode === 'resize');

    $file = db_item(
        "SELECT
            id,
            name,
            type,
            size,
            optimized
        FROM files
        WHERE id = '" . e($file_id) . "'");

    if (!$file) {
        return array(
            'status' => 'error',
            'message' => lang('Sorry, we could not find that file.'));
    }

    $file['type'] = mb_strtolower($file['type']);

    if (
        ($file['type'] != 'jpg')
        and ($file['type'] != 'jpeg')
        and ($file['type'] != 'png')
        and ($file['type'] != 'gif')
        and ($file['type'] != 'bmp')
        and ($file['type'] != 'tiff')
        and ($file['type'] != 'webp')
    ) {
        return array(
            'status' => 'error',
            'message' => lang(array('string'=>'Sorry, we don\'t support optimizing that type of file ({var:1}). The following types are supported: jpg, jpeg, png, gif, bmp, tiff.','vars'=>$file['name'])) );
    }

    // Only the plain mode refuses a file that has already been through it.
    // An image can be optimized and still be 6000 pixels wide — that is the
    // exact case the resize button exists for, so the flag must not lock it
    // out of the one operation that would help.
    if ($file['optimized'] and !$resize) {
        return array(
            'status' => 'error',
            'message' => lang(array('string'=>'Sorry, that image ({var:1}) has already been optimized.','vars'=>$file['name'] )) );
    }

    $file['path'] = FILE_DIRECTORY_PATH . '/' . $file['name'];

    $settings = pg_image_settings();

    // A large image takes seconds to resample and the caller may be working
    // through a selection of them.
    @set_time_limit(300);

    $report = pg_process_image_file($file['path'], $resize
        ? array(
            'max_dimension'  => $settings['file_max_dimension'],
            'resize_trigger' => $settings['file_resize_trigger'],
            'quality'        => $settings['resize_quality'])
        : array());

    if ($report['status'] !== 'success') {

        // Each of these is a different thing to do about it, so each gets its
        // own sentence. "Could not optimize" for all of them sends the
        // operator looking for a fault that is not there.
        if ($report['reason'] === 'animated') {
            $message = lang(array(
                'string' => 'Sorry, {var:1} is an animated image. Optimizing it would leave only the first frame, so it was left alone.',
                'vars'   => $file['name']));

        } elseif ($report['reason'] === 'too_large_to_process') {
            $message = lang(array(
                'string' => 'Sorry, {var:1} needs more memory than this server allows. Please resize it on your computer and upload it again.',
                'vars'   => $file['name']));

        } elseif ($report['reason'] === 'no_library') {
            $message = lang('No image library (Imagick or GD) is installed on this server, so images cannot be optimized or resized.');

        } elseif ($report['reason'] === 'missing') {
            $message = lang(array(
                'string' => 'Sorry, {var:1} is recorded here but is not on the disk.',
                'vars'   => $file['name']));

        } elseif ($report['reason'] === 'write_failed') {
            $message = lang(array('string'=>'Sorry, we could not save the optimized image to the file system, so the image has not been optimized ({var:1}).','vars'=>$file['name']));

        } else {
            $message = lang(array('string'=>'Sorry, we could not download the optimized image from the image optimization service ({var:1}).','vars'=>$file['name']));
        }

        log_activity($message);

        return array(
            'status' => 'error',
            'message' => $message);
    }

    // Nothing was worth writing. Still flagged, so the file stops appearing on
    // the "needs optimizing" lists and offering a saving it cannot deliver —
    // without that, the same file is picked up by every bulk run for ever.
    if (!$report['changed']) {

        db(
            "UPDATE files
            SET
                optimized = '1',
                image_width = '" . (int) $report['width'] . "',
                image_height = '" . (int) $report['height'] . "',
                optimization_percent = '0'
            WHERE id = '" . $file['id'] . "'");

        return array(
            'status' => 'success',
            'message' => lang(array(
                'string' => '{var:1} is already as small as we can make it.',
                'vars'   => $file['name'])));
    }

    // timestamp and user move because the bytes on disk changed; the file
    // screens sort and report on those two columns.
    db(
        "UPDATE files
        SET
            size = '" . e($report['bytes_after']) . "',
            optimized = '1',
            image_width = '" . (int) $report['width'] . "',
            image_height = '" . (int) $report['height'] . "',
            optimization_percent = '0',
            timestamp = UNIX_TIMESTAMP(),
            user = '" . USER_ID . "'
        WHERE id = '" . $file['id'] . "'");

    if ($report['resized']) {

        $message = lang(array(
            'string' => '{var:1} was resized to {var:2} x {var:3} pixels and optimized ({var:4} -> {var:5}, {var:6}%).',
            'vars' => array(
                $file['name'],
                $report['width'],
                $report['height'],
                convert_bytes_to_string($report['bytes_before']),
                convert_bytes_to_string($report['bytes_after']),
                $report['percent'])));

    } else {

        $message = lang(array(
            'string' => '{var:1} has been optimized ({var:2} -> {var:3}, {var:4}%).',
            'vars' => array(
                $file['name'],
                convert_bytes_to_string($report['bytes_before']),
                convert_bytes_to_string($report['bytes_after']),
                $report['percent'])));
    }

    log_activity($message);

    return array(
        'status' => 'success',
        'message' => $message);
}
