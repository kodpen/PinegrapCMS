<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: image settings and the helpers around the image
// engine. The engine itself (pg_process_image_file() and the GD / Imagick
// halves) and the responsive derivatives are in includes/image_engine.php,
// which get_file.php loads as well; functions.php requires it at the top.
//
// Loaded by functions.php through require_once, never on its own. The
// functions were moved here from functions.php as they were; PG_FUNCTIONS_DIR
// is the software directory (what dirname(__FILE__) was in functions.php),
// see the note at the top of functions.php.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}


/**
 * Get the dominant color area of an image file.
 * Works for raster formats (jpg, png, gif, webp).
 * Returns the most frequent color in hex format (e.g. "#aabbcc").
 *
 * @param string $file_path Full path to the image file.
 * @return string Hex color code.
 */
function get_dominant_area_color($file_path)
{
    // Fallback color if image is unreadable
    $fallback = '#9696968e';

    // Load image from file
    $image_data = @file_get_contents($file_path);
    if ($image_data === false) {
        return $fallback;
    }

    $image = @imagecreatefromstring($image_data);
    if ($image === false) {
        return $fallback;
    }

    $width = imagesx($image);
    $height = imagesy($image);

    // Sampling resolution (lower = faster, less accurate)
    $sample_step = max(1, round(min($width, $height) / 50));
    $color_count = array();

    // Loop through pixels with sampling
    for ($x = 0; $x < $width; $x += $sample_step) {
        for ($y = 0; $y < $height; $y += $sample_step) {
            $rgb = imagecolorat($image, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;

            // Round color to reduce noise (group similar tones). Rounding pushes
            // values from 240 up to 256, which is one past a byte, so clamp.
            $r = min(255, round($r / 32) * 32);
            $g = min(255, round($g / 32) * 32);
            $b = min(255, round($b / 32) * 32);

            $hex = sprintf("#%02x%02x%02x", $r, $g, $b);

            if (!isset($color_count[$hex])) {
                $color_count[$hex] = 0;
            }
            $color_count[$hex]++;
        }
    }

    // Sort by frequency
    arsort($color_count);
    reset($color_count);

    // Return most frequent color
    return key($color_count);
}


/**
 * Whether the config row carries the image limit columns (2026.4.4).
 *
 * Same pattern as pg_page_noindex_ready(): an install that took the code but
 * not the database upgrade keeps working on the shipped defaults, and the
 * settings screen hides the block rather than saving into columns that are
 * not there.
 *
 * All six columns are probed, because the upgrade runs six separate ALTERs and
 * a run that stopped half way would otherwise look complete.
 *
 * @param bool $recheck skip the static cache (the installer creates the
 *                      columns inside a request that already loaded this file)
 * @return bool
 */
function pg_image_settings_ready($recheck = false)
{
    static $cached = null;

    if ($cached !== null && !$recheck) {
        return $cached;
    }

    $cached = false;

    if (!isset(db::$con) || !db::$con) {
        return false;
    }

    $result = @mysqli_query(
        db::$con,
        "SHOW COLUMNS FROM config
         WHERE Field IN (
            'image_product_optimize',
            'image_product_max_dimension',
            'image_product_min_dimension',
            'image_file_resize_trigger',
            'image_file_max_dimension',
            'image_resize_quality')");

    $cached = ($result && @mysqli_num_rows($result) == 6);

    return $cached;
}


/**
 * The image limits the operator set on the settings screen.
 *
 * Read once per request. Every value is clamped here rather than at the call
 * sites: a ceiling of zero would scale every photo to nothing, and a minimum
 * above the ceiling would warn about every image the software just produced.
 *
 * @param bool $recheck skip the static cache
 * @return array
 */
function pg_image_settings($recheck = false)
{
    static $cached = null;

    if ($cached !== null && !$recheck) {
        return $cached;
    }

    // Shipped defaults, used as-is until the upgrade has run.
    //
    // 2400 px on the long edge is well above what a product page displays and
    // still leaves room to zoom, while taking a typical 8 MP camera photo from
    // megabytes to a few hundred kilobytes. 500 px is the floor Google
    // Merchant Center announced for 2027, so an image under it is worth
    // saying something about even though nothing here can invent detail.
    $settings = array(
        'product_optimize'      => 1,
        'product_max_dimension' => 2400,
        'product_min_dimension' => 500,
        'file_resize_trigger'   => 2560,
        'file_max_dimension'    => 1920,
        'resize_quality'        => 82);

    if (pg_image_settings_ready($recheck)) {

        $row = db_item(
            "SELECT
                image_product_optimize,
                image_product_max_dimension,
                image_product_min_dimension,
                image_file_resize_trigger,
                image_file_max_dimension,
                image_resize_quality
            FROM config
            LIMIT 1");

        if ($row) {
            $settings['product_optimize']      = (int) $row['image_product_optimize'];
            $settings['product_max_dimension'] = (int) $row['image_product_max_dimension'];
            $settings['product_min_dimension'] = (int) $row['image_product_min_dimension'];
            $settings['file_resize_trigger']   = (int) $row['image_file_resize_trigger'];
            $settings['file_max_dimension']    = (int) $row['image_file_max_dimension'];
            $settings['resize_quality']        = (int) $row['image_resize_quality'];
        }
    }

    $settings['product_optimize']      = $settings['product_optimize'] ? 1 : 0;
    $settings['product_max_dimension'] = max(200, min(20000, $settings['product_max_dimension']));
    $settings['product_min_dimension'] = max(0,   min($settings['product_max_dimension'], $settings['product_min_dimension']));
    $settings['file_max_dimension']    = max(200, min(20000, $settings['file_max_dimension']));
    $settings['resize_quality']        = max(40,  min(100,   $settings['resize_quality']));

    // The trigger is what decides whether the shrink button appears at all, so
    // it can never sit below the ceiling: that would offer to "shrink" an
    // image the shrink would leave exactly as it is.
    $settings['file_resize_trigger'] = max($settings['file_max_dimension'], min(20000, $settings['file_resize_trigger']));

    $cached = $settings;

    return $cached;
}


/**
 * The pixel size of a file row, from the cached columns when they are filled
 * and from the file itself when they are not.
 *
 * files.image_width / image_height were added as a cache in 2026.1.25: reading
 * a JPEG header per row turns a file list into hundreds of disk seeks, which
 * on a network-mounted or synced folder is the difference between a screen
 * that opens and one that does not. A row measured here is written back, so
 * each file is opened once ever.
 *
 * @param array $file row with id, name and the two cached columns
 * @return array width, height — zeros when the file cannot be measured
 */
function pg_file_image_dimensions($file)
{
    if (isset($file['image_width'], $file['image_height'])
        && ($file['image_width'] !== null) && ($file['image_width'] !== '')
        && ($file['image_height'] !== null) && ($file['image_height'] !== '')
    ) {
        return array((int) $file['image_width'], (int) $file['image_height']);
    }

    $size = @getimagesize(FILE_DIRECTORY_PATH . '/' . $file['name']);

    $width  = isset($size[0]) ? (int) $size[0] : 0;
    $height = isset($size[1]) ? (int) $size[1] : 0;

    if (($width > 0) && ($height > 0) && isset($file['id'])) {
        db("UPDATE files
            SET image_width = '" . $width . "', image_height = '" . $height . "'
            WHERE id = '" . e($file['id']) . "'");
    }

    return array($width, $height);
}


/**
 * Whether the file screens should offer to shrink this image.
 *
 * Dimensions decide it, not bytes. A 6000 px photo saved at low quality can be
 * under a megabyte and is still six times wider than any page that shows it,
 * while a 1200 px PNG screenshot can be heavy and has nothing to gain from
 * losing pixels.
 *
 * @param int $width
 * @param int $height
 * @return bool
 */
function pg_image_can_be_resized($width, $height)
{
    $settings = pg_image_settings();

    return (max((int) $width, (int) $height) > $settings['file_resize_trigger']);
}


/**
 * Calculate how much an image file can be optimizeable.
 *
 * Estimates the saving by compressing the file without writing it, and
 * returns a string like "10%" for the badge on the file screens.
 *
 * Expensive — it decodes and recompresses the whole image. view_files.php
 * caches the answer in files.optimization_percent and only computes it under
 * a threshold; nothing else should call it in a loop.
 *
 * @param string $file_path Full path to the image file
 * @return string Percentage string (e.g. "10%")
 */
function calculate_optimizable_percent($file_path)
{
    $report = pg_process_image_file($file_path, array('write' => false));

    if ($report['status'] !== 'success') {
        return '0%';
    }

    return $report['percent'] . '%';
}


/**
 * Optimize an image and hand back the bytes without touching the file.
 *
 * Kept for callers written before pg_process_image_file() existed. New code
 * calls the engine directly, so that it can also ask for a resize and read
 * what actually happened.
 *
 * @param string $file_path Full path to the image file
 * @return string|false Optimized binary image data or false on failure
 */
function optimize_this_image($file_path)
{
    $report = pg_process_image_file($file_path, array('write' => false));

    if ($report['status'] !== 'success') {
        return false;
    }

    return $report['data'];
}
