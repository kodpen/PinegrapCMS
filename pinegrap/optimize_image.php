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

function optimize_image($file_id) {

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

    if ($file['optimized']) {
        return array(
            'status' => 'error',
            'message' => lang(array('string'=>'Sorry, that image ({var:1}) has already been optimized.','vars'=>$file['name'] )) );
    }

   

    $file['path'] = FILE_DIRECTORY_PATH . '/' . $file['name'];
    $original_size = filesize($file['path']);

    // Download the new optimized image.
    $optimized_image = optimize_this_image($file['path']);

    // If we could not download the optimized image, then log and output error.
    if (($optimized_image === false) or ($optimized_image == '')) {

        $message = lang(array('string'=>'Sorry, we could not download the optimized image from the image optimization service ({var:1}).','vars'=>$file['name']));

        log_activity($message);

        return array(
            'status' => 'error',
            'message' => $message);

    }

    $result = file_put_contents($file['path'], $optimized_image);

    $optimized_size = strlen($optimized_image);


    $percent = round((1 - ($optimized_size / $original_size)) * 100);



    // If the new file could not be saved to the file system, then log and output error.
    if ($result === false) {

        $message = lang(array('string'=>'Sorry, we could not save the optimized image to the file system, so the image has not been optimized ({var:1}).','vars'=>$file['name']));

        log_activity($message);

        return array(
            'status' => 'error',
            'message' => $message);

    }

    db(
        "UPDATE files 
        SET 
            size = '" . e(strlen($optimized_image)) . "',
            optimized = '1',
            timestamp = UNIX_TIMESTAMP(), 
            user = '" . USER_ID . "' 
        WHERE id = '" . $file['id'] . "'");

    
    $message = lang(array(
        'string' => '{var:1} has been optimized ({var:2} -> {var:3}, {var:4}%).',
        'vars' => array(
            $file['name'],
            convert_bytes_to_string($original_size),
            convert_bytes_to_string($optimized_size),
            $percent
        )
    ));
    log_activity($message);

    return array(
        'status' => 'success',
        'message' => $message);

}