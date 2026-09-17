<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: Image settings and processing (GD / Imagick), optimisation.
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

            // Round color to reduce noise (group similar tones)
            $r = round($r / 32) * 32;
            $g = round($g / 32) * 32;
            $b = round($b / 32) * 32;

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
 * Image types this software can rewrite, as IMAGETYPE_* constants.
 *
 * Which of them are actually usable depends on the extension present, so this
 * is the outer set and pg_process_image_file() decides per file.
 *
 * @return array
 */
function pg_image_supported_types()
{
    $types = array(IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_BMP);

    if (defined('IMAGETYPE_WEBP')) {
        $types[] = IMAGETYPE_WEBP;
    }

    // TIFF is Imagick-only; GD has no reader for it at all.
    if (class_exists('Imagick')) {
        $types[] = IMAGETYPE_TIFF_II;
        $types[] = IMAGETYPE_TIFF_MM;
    }

    return $types;
}


/**
 * Whether a GIF carries more than one frame.
 *
 * Both libraries read the first frame of an animated GIF and write it back as
 * a still, so an animation optimized in place stops moving and nothing says
 * why. Cheaper to leave those files alone.
 *
 * Counts Graphic Control Extension blocks (00 21 F9 04); a still GIF has at
 * most one. Read in chunks with an overlap so a marker straddling a chunk
 * boundary is still seen.
 *
 * @param string $file_path
 * @return bool
 */
function pg_image_is_animated_gif($file_path)
{
    $handle = @fopen($file_path, 'rb');

    if (!$handle) {
        return false;
    }

    $count     = 0;
    $carry     = '';
    $marker    = "\x00\x21\xF9\x04";

    while (!feof($handle)) {

        $chunk = @fread($handle, 262144);

        if ($chunk === false || $chunk === '') {
            break;
        }

        $buffer = $carry . $chunk;
        $count += substr_count($buffer, $marker);

        if ($count > 1) {
            fclose($handle);
            return true;
        }

        // Keep the last three bytes: a four byte marker can start there.
        $carry = substr($buffer, -3);
    }

    fclose($handle);

    return ($count > 1);
}


/**
 * Make sure there is room to decode an image of this size, raising the limit
 * when that is allowed.
 *
 * GD holds an uncompressed image as four bytes per pixel, and a resize holds
 * two of them at once — a 24 megapixel photo is roughly 200 MB before any
 * overhead. On a host with a 128 MB limit that is not a slow operation, it is
 * a fatal error part way through a request, which on the upload path would
 * leave the operator looking at a blank page.
 *
 * So the requirement is worked out first. If the current limit covers it,
 * nothing happens. If it does not, the limit is raised for this request only,
 * and only up to a ceiling — a limit of -1 on a shared host takes the whole
 * box down instead of failing one upload.
 *
 * Imagick streams through its own pixel cache and does not need this, but it
 * is asked for anyway: the caller cannot know which library will answer.
 *
 * @param int $width
 * @param int $height
 * @return bool false when the image is too big to handle safely
 */
function pg_image_ensure_memory($width, $height)
{
    $width  = (int) $width;
    $height = (int) $height;

    if (($width < 1) || ($height < 1)) {
        return false;
    }

    // Source plus destination, four bytes a pixel, and half again for the
    // decoder's own working space.
    $needed = (int) ($width * $height * 4 * 2 * 1.5) + (16 * 1024 * 1024) + memory_get_usage(true);

    // pg_ini_bytes() answers 0 for an unlimited or unset memory_limit.
    $bytes = pg_ini_bytes(@ini_get('memory_limit'));

    if (($bytes === 0) || ($bytes >= $needed)) {
        return true;
    }

    // Above this the image is refused rather than granted whatever it asks
    // for. A photo needing more than a gigabyte to open is not a product
    // photo, and honouring it would take the site down rather than one upload.
    if ($needed > (1024 * 1024 * 1024)) {
        return false;
    }

    @ini_set('memory_limit', ((int) ceil($needed / (1024 * 1024))) . 'M');

    $bytes = pg_ini_bytes(@ini_get('memory_limit'));

    return (($bytes === 0) || ($bytes >= $needed));
}


/**
 * Scale a pair of dimensions so the longest edge lands on $max, aspect ratio
 * kept and neither edge allowed to round down to zero.
 *
 * @param int $width
 * @param int $height
 * @param int $max
 * @return array width, height
 */
function pg_image_scaled_dimensions($width, $height, $max)
{
    $width  = (int) $width;
    $height = (int) $height;
    $max    = (int) $max;

    $longest = max($width, $height);

    if (($longest < 1) || ($max < 1) || ($longest <= $max)) {
        return array($width, $height);
    }

    $ratio = $max / $longest;

    return array(
        max(1, (int) round($width * $ratio)),
        max(1, (int) round($height * $ratio)));
}


/**
 * Recompress an image, optionally scaling it down first.
 *
 * The one place that touches image pixels. Callers describe what they want and
 * read the report; none of them opens an image itself.
 *
 * Options:
 *   max_dimension   int   ceiling for the longest edge. 0 (default) never
 *                         resizes, which is the plain optimize behaviour.
 *   resize_trigger  int   only resize when the longest edge is above this.
 *                         Defaults to max_dimension.
 *   min_dimension   int   report too_small when the shortest edge is under
 *                         this. Reported only — nothing is ever refused or
 *                         enlarged over it.
 *   quality         int   0 (default) picks by dimension, the way the optimize
 *                         button always has. Set explicitly when resizing.
 *   write           bool  true (default) rewrites the file in place.
 *
 * Two rules about writing, both of which the old code got wrong:
 *
 *   A file is only replaced when the result is actually smaller, unless it was
 *   resized. Recompressing a well-packed PNG can produce a bigger one, and
 *   the old optimize wrote it anyway and then reported a negative saving.
 *
 *   The new bytes go to a temporary file in the same directory and are moved
 *   over the original. Writing straight over the file leaves a truncated
 *   image behind if the disk fills or the process is killed mid-write, and
 *   files.name is the public address of that file.
 *
 * @param string $file_path
 * @param array  $options
 * @return array report; 'status' is 'success', 'skipped' or 'error'
 */
function pg_process_image_file($file_path, $options = array())
{
    $report = array(
        'status'          => 'skipped',
        'reason'          => '',
        'data'            => null,
        'changed'         => false,
        'resized'         => false,
        'width'           => 0,
        'height'          => 0,
        'original_width'  => 0,
        'original_height' => 0,
        'bytes_before'    => 0,
        'bytes_after'     => 0,
        'percent'         => 0,
        'too_small'       => false);

    if (!is_file($file_path) || !is_readable($file_path)) {
        $report['reason'] = 'missing';
        return $report;
    }

    $max_dimension  = isset($options['max_dimension'])  ? (int) $options['max_dimension']  : 0;
    $resize_trigger = isset($options['resize_trigger']) ? (int) $options['resize_trigger'] : $max_dimension;
    $min_dimension  = isset($options['min_dimension'])  ? (int) $options['min_dimension']  : 0;
    $quality        = isset($options['quality'])        ? (int) $options['quality']        : 0;
    $write          = isset($options['write'])          ? (bool) $options['write']         : true;

    // 'format' re-encodes into another format instead of the source's own.
    // Only webp so far; anything else is ignored rather than guessed at.
    $format = isset($options['format']) ? strtolower((string) $options['format']) : '';

    if ($format !== 'webp') {
        $format = '';
    }

    // 'rotate' turns the picture clockwise by a quarter, a half or three
    // quarters, after its EXIF orientation has been turned into pixels -- so
    // "a quarter to the right" means a quarter to the right of what the
    // operator was looking at, not of whatever the camera happened to store.
    // The same engine as the optimize and webp buttons, so the write, the
    // orientation fix and the dimension report are the same code.
    $rotate = isset($options['rotate']) ? (((int) $options['rotate']) % 360 + 360) % 360 : 0;

    if (($rotate % 90) !== 0) {
        $rotate = 0;
    }

    $size = @getimagesize($file_path);

    if (!$size || empty($size[0]) || empty($size[1])) {
        $report['reason'] = 'unsupported';
        return $report;
    }

    $width  = (int) $size[0];
    $height = (int) $size[1];
    $type   = isset($size[2]) ? (int) $size[2] : 0;

    $report['width']           = $width;
    $report['height']          = $height;
    $report['original_width']  = $width;
    $report['original_height'] = $height;
    $report['bytes_before']    = (int) filesize($file_path);
    $report['bytes_after']     = $report['bytes_before'];
    $report['too_small']       = (($min_dimension > 0) && (min($width, $height) < $min_dimension));

    if (!in_array($type, pg_image_supported_types(), true)) {
        $report['reason'] = 'unsupported';
        return $report;
    }

    if (($type == IMAGETYPE_GIF) && pg_image_is_animated_gif($file_path)) {
        $report['reason'] = 'animated';
        return $report;
    }

    $use_imagick = class_exists('Imagick');

    if (!$use_imagick && !function_exists('imagecreatetruecolor')) {
        $report['reason'] = 'no_library';
        return $report;
    }

    // A format conversion needs a library that can actually write the target.
    if ($format === 'webp') {

        $imagick_webp = false;

        if ($use_imagick) {
            try {
                $imagick_webp = (count(Imagick::queryFormats('WEBP')) > 0);
            } catch (\Exception $exception) {
                $imagick_webp = false;
            }
        }

        $gd_webp = function_exists('imagewebp');

        if (!$imagick_webp && !$gd_webp) {
            $report['reason'] = 'no_webp';
            return $report;
        }

        $use_imagick = $imagick_webp;
    }

    $resize = (($max_dimension > 0) && (max($width, $height) > max(1, $resize_trigger)));

    list($target_width, $target_height) = $resize
        ? pg_image_scaled_dimensions($width, $height, $max_dimension)
        : array($width, $height);

    if ($quality < 1) {

        // The ladder the optimize button has always used. Kept exactly so that
        // pressing optimize on an existing file produces the same bytes it did
        // before this engine existed.
        $longest = max($width, $height);

        if ($longest <= 720) {
            $quality = 85;
        } elseif ($longest <= 1280) {
            $quality = 75;
        } elseif ($longest <= 1920) {
            $quality = 65;
        } else {
            $quality = 60;
        }
    }

    $quality = max(1, min(100, $quality));

    if (!$use_imagick && !pg_image_ensure_memory($width, $height)) {
        $report['reason'] = 'too_large_to_process';
        return $report;
    }

    $data = $use_imagick
        ? pg_process_image_with_imagick($file_path, $type, $resize, $target_width, $target_height, $quality, $format, $rotate)
        : pg_process_image_with_gd($file_path, $type, $resize, $target_width, $target_height, $quality, $format, $rotate);

    if (($data === false) || ($data === '') || ($data === null)) {
        $report['status'] = 'error';
        $report['reason'] = 'failed';
        return $report;
    }

    $report['status']  = 'success';
    $report['resized'] = $resize;

    // Measured from the bytes that were actually produced, not from the target
    // worked out above.
    //
    // An EXIF-rotated photo decodes sideways and is turned upright here, which
    // swaps its edges — so a 4000x3000 original that was taken with the phone
    // on its side comes out 2400 tall and 1800 wide, the opposite of what the
    // target said. The caller writes these two numbers into files.image_width /
    // image_height, and a row that disagrees with its own file sends the resize
    // button and the thumbnail after the wrong shape.
    $produced = @getimagesizefromstring($data);

    if ($produced && !empty($produced[0]) && !empty($produced[1])) {
        $report['width']  = (int) $produced[0];
        $report['height'] = (int) $produced[1];

    } elseif ($resize) {
        $report['width']  = $target_width;
        $report['height'] = $target_height;
    }

    if (!$write) {
        $report['data']         = $data;
        $report['bytes_after']  = strlen($data);
        $report['percent']      = ($report['bytes_before'] > 0)
            ? max(0, (int) round((1 - (strlen($data) / $report['bytes_before'])) * 100))
            : 0;
        return $report;
    }

    // Nothing that is bigger than what was already there gets written, and a
    // resize is no exception. This looks like an odd rule until a flat-colour
    // PNG turns up: a 3000 px diagram stored as a 256-colour palette is a few
    // tens of kilobytes, and scaling it produces smooth edges that no longer
    // fit in a palette, so the smaller picture is the heavier file. Both
    // buttons promise a lighter file; handing back a heavier one because the
    // pixel count went down is not a promise anybody made.
    //
    // The cost is that such a file keeps its original dimensions. It is also
    // already light, so it is not the problem either button was for.
    //
    // A format conversion is exempt: there the caller asked for the format,
    // not for fewer bytes, and refusing to deliver it because the palette
    // original happened to be smaller would turn "convert" into "sometimes".
    //
    // A rotation is exempt for the same reason: the operator asked for the
    // picture the other way up, and a rotated file is nearly always a little
    // heavier than the one it replaces.
    $format_conversion = (($format !== '') && ($format !== image_type_to_extension($type, false)));

    if ((strlen($data) >= $report['bytes_before']) && ($format_conversion == false) && ($rotate == 0)) {
        $report['reason']  = 'no_gain';
        $report['resized'] = false;

        // Nothing was written, so the file on disk still has whatever
        // getimagesize() reported at the top — including its unapplied EXIF
        // rotation.
        $report['width']   = $width;
        $report['height']  = $height;
        return $report;
    }

    $temporary_path = $file_path . '.pgopt' . (function_exists('getmypid') ? getmypid() : uniqid()); // disable_functions on some hosts

    if (@file_put_contents($temporary_path, $data) === false) {
        $report['status'] = 'error';
        $report['reason'] = 'write_failed';
        return $report;
    }

    if (!@rename($temporary_path, $file_path)) {
        @unlink($temporary_path);
        $report['status'] = 'error';
        $report['reason'] = 'write_failed';
        return $report;
    }

    @clearstatcache(true, $file_path);

    $report['changed']      = true;
    $report['bytes_after']  = strlen($data);
    $report['percent']      = ($report['bytes_before'] > 0)
        ? max(0, (int) round((1 - ($report['bytes_after'] / $report['bytes_before'])) * 100))
        : 0;

    return $report;
}


/**
 * The Imagick half of pg_process_image_file(). Returns the encoded bytes.
 *
 * @return string|false
 */
function pg_process_image_with_imagick($file_path, $type, $resize, $target_width, $target_height, $quality, $format = '', $rotate = 0)
{
    $image = null;

    try {
        $image = new Imagick();
        $image->readImage($file_path);

        // Rotate the pixels to match the EXIF orientation and then drop the
        // tag, rather than stripping the metadata and leaving the pixels
        // sideways. The old code kept the tag through the strip, which works
        // only for as long as every viewer honours it — and the GD path did
        // not even do that, so the same photo came out upright on one server
        // and on its side on another.
        pg_image_apply_imagick_orientation($image);

        $image->stripImage();

        // Only converted when it has to be. A CMYK JPEG shown in a browser is
        // the classic "colours went wrong after optimizing" report; tagging an
        // image that is already sRGB as linear RGB, which is what the old code
        // did with setColorspace(), is the other half of the same problem.
        if ($image->getImageColorspace() == Imagick::COLORSPACE_CMYK) {
            $image->transformImageColorspace(Imagick::COLORSPACE_SRGB);
        }

        if ($resize) {
            $image->resizeImage($target_width, $target_height, Imagick::FILTER_LANCZOS, 1);
        }

        // After the resize: fewer pixels to turn. Imagick's positive angle
        // is clockwise, which is the direction the option is written in.
        if ($rotate > 0) {
            $image->rotateImage(new ImagickPixel('none'), $rotate);
        }

        if ($format === 'webp') {
            $image->setImageFormat('webp');
            $image->setImageCompressionQuality($quality);
            $image->setOption('webp:method', '6');

        } elseif ($type == IMAGETYPE_JPEG) {
            $image->setImageCompression(Imagick::COMPRESSION_JPEG);
            // Progressive: the picture appears at low detail and sharpens,
            // instead of filling in one band at a time.
            $image->setInterlaceScheme(Imagick::INTERLACE_PLANE);
            $image->setImageCompressionQuality($quality);

        } elseif ($type == IMAGETYPE_PNG) {
            // PNG is lossless, so quality does nothing here; the saving comes
            // from the zlib level and the filter.
            $image->setImageCompression(Imagick::COMPRESSION_ZIP);
            $image->setOption('png:compression-level', '9');
            $image->setOption('png:compression-filter', '5');

        } elseif (defined('IMAGETYPE_WEBP') && ($type == IMAGETYPE_WEBP)) {
            $image->setImageCompressionQuality($quality);
            $image->setOption('webp:method', '6');

        } else {
            $image->setImageCompressionQuality($quality);
        }

        $data = $image->getImageBlob();

        $image->clear();
        $image->destroy();

        return $data;

    } catch (\Exception $exception) {

        if ($image) {
            @$image->clear();
            @$image->destroy();
        }

        return false;

    } catch (Throwable $throwable) {

        if ($image) {
            @$image->clear();
            @$image->destroy();
        }

        return false;
    }
}


/**
 * Turn an EXIF orientation into real pixels and clear the tag.
 *
 * @param Imagick $image
 * @return void
 */
function pg_image_apply_imagick_orientation($image)
{
    try {
        $orientation = $image->getImageOrientation();
    } catch (\Exception $exception) {
        return;
    }

    $background = new ImagickPixel('none');

    switch ($orientation) {

        case Imagick::ORIENTATION_TOPRIGHT:
            $image->flopImage();
            break;

        case Imagick::ORIENTATION_BOTTOMRIGHT:
            $image->rotateImage($background, 180);
            break;

        case Imagick::ORIENTATION_BOTTOMLEFT:
            $image->flopImage();
            $image->rotateImage($background, 180);
            break;

        case Imagick::ORIENTATION_LEFTTOP:
            $image->flopImage();
            $image->rotateImage($background, -90);
            break;

        case Imagick::ORIENTATION_RIGHTTOP:
            $image->rotateImage($background, 90);
            break;

        case Imagick::ORIENTATION_RIGHTBOTTOM:
            $image->flopImage();
            $image->rotateImage($background, 90);
            break;

        case Imagick::ORIENTATION_LEFTBOTTOM:
            $image->rotateImage($background, -90);
            break;

        default:
            return;
    }

    $image->setImageOrientation(Imagick::ORIENTATION_TOPLEFT);
}


/**
 * The GD half of pg_process_image_file(). Returns the encoded bytes.
 *
 * @return string|false
 */
function pg_process_image_with_gd($file_path, $type, $resize, $target_width, $target_height, $quality, $format = '', $rotate = 0)
{
    $source = pg_image_gd_read($file_path, $type);

    if (!$source) {
        return false;
    }

    // JPEG only: it is the one format here that carries EXIF.
    if ($type == IMAGETYPE_JPEG) {
        $source = pg_image_apply_gd_orientation($source, $file_path);
    }

    $source_width  = imagesx($source);
    $source_height = imagesy($source);

    $image = $source;

    if ($resize) {

        // The orientation fix above can have swapped the edges, so the target
        // is worked out again from what is actually in memory rather than
        // from what getimagesize() reported.
        list($target_width, $target_height) = pg_image_scaled_dimensions(
            $source_width,
            $source_height,
            max($target_width, $target_height));

        $image = imagecreatetruecolor($target_width, $target_height);

        if (!$image) {
            imagedestroy($source);
            return false;
        }

        // Without this a transparent PNG or GIF comes back on a black
        // rectangle: a fresh truecolor canvas is opaque black, and blending
        // the source onto it keeps the black.
        if (($type == IMAGETYPE_PNG)
            || ($type == IMAGETYPE_GIF)
            || (defined('IMAGETYPE_WEBP') && ($type == IMAGETYPE_WEBP))
        ) {
            imagealphablending($image, false);
            imagesavealpha($image, true);
            $transparent = imagecolorallocatealpha($image, 255, 255, 255, 127);
            imagefilledrectangle($image, 0, 0, $target_width, $target_height, $transparent);
        }

        imagecopyresampled(
            $image, $source,
            0, 0, 0, 0,
            $target_width, $target_height,
            $source_width, $source_height);

        imagedestroy($source);

    } elseif (($type == IMAGETYPE_PNG) || (defined('IMAGETYPE_WEBP') && ($type == IMAGETYPE_WEBP))) {
        imagesavealpha($image, true);
    }

    // GD's positive angle is counter-clockwise, the opposite of Imagick's,
    // so the clockwise quarter the option asks for is a negative turn here.
    // The uncovered corners of a quarter turn are none, of an odd angle
    // would be the fill colour -- a transparent one, so a PNG or WebP with
    // an alpha channel keeps it.
    if ($rotate > 0) {

        if (function_exists('imagepalettetotruecolor') && function_exists('imageistruecolor') && !imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }

        $fill = imagecolorallocatealpha($image, 255, 255, 255, 127);
        $turned = @imagerotate($image, -$rotate, $fill);

        if (!$turned) {
            imagedestroy($image);
            return false;
        }

        imagedestroy($image);
        $image = $turned;

        if (($type == IMAGETYPE_PNG) || (defined('IMAGETYPE_WEBP') && ($type == IMAGETYPE_WEBP))) {
            imagealphablending($image, false);
            imagesavealpha($image, true);
        }
    }

    ob_start();

    $written = false;

    if ($format === 'webp') {

        // imagewebp() needs a truecolor canvas; a palette GIF or PNG-8 that
        // skipped the resize path above is still in palette form here. The
        // conversion keeps the alpha channel.
        if (function_exists('imageistruecolor') && !imageistruecolor($image) && function_exists('imagepalettetotruecolor')) {
            imagepalettetotruecolor($image);
        }

        imagesavealpha($image, true);
        $written = function_exists('imagewebp') ? imagewebp($image, null, $quality) : false;

        $data = ob_get_clean();

        imagedestroy($image);

        if (!$written || ($data === '')) {
            return false;
        }

        return $data;
    }

    switch ($type) {

        case IMAGETYPE_JPEG:
            imageinterlace($image, true);
            $written = imagejpeg($image, null, $quality);
            break;

        case IMAGETYPE_PNG:
            // GD's png argument is a zlib level, 0-9, not a quality.
            $written = imagepng($image, null, 9);
            break;

        case IMAGETYPE_GIF:
            $written = imagegif($image);
            break;

        case IMAGETYPE_BMP:
            $written = function_exists('imagebmp') ? imagebmp($image) : false;
            break;

        default:
            if (defined('IMAGETYPE_WEBP') && ($type == IMAGETYPE_WEBP) && function_exists('imagewebp')) {
                $written = imagewebp($image, null, $quality);
            }
            break;
    }

    $data = ob_get_clean();

    imagedestroy($image);

    if (!$written || ($data === '')) {
        return false;
    }

    return $data;
}


/**
 * Open an image with GD by its detected type.
 *
 * @return resource|GdImage|false
 */
function pg_image_gd_read($file_path, $type)
{
    switch ($type) {

        case IMAGETYPE_JPEG:
            return function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($file_path) : false;

        case IMAGETYPE_PNG:
            return function_exists('imagecreatefrompng') ? @imagecreatefrompng($file_path) : false;

        case IMAGETYPE_GIF:
            return function_exists('imagecreatefromgif') ? @imagecreatefromgif($file_path) : false;

        case IMAGETYPE_BMP:
            return function_exists('imagecreatefrombmp') ? @imagecreatefrombmp($file_path) : false;
    }

    if (defined('IMAGETYPE_WEBP') && ($type == IMAGETYPE_WEBP) && function_exists('imagecreatefromwebp')) {
        return @imagecreatefromwebp($file_path);
    }

    return false;
}


/**
 * Rotate a decoded JPEG to match its EXIF orientation.
 *
 * GD reads pixels and nothing else, so a photo taken with the phone turned
 * sideways decodes sideways. It looked right before optimizing only because
 * the browser was reading the EXIF tag that recompressing then removed —
 * which is why "the picture rotated itself after I optimized it" was always
 * reported about phone photos and never about anything else.
 *
 * @param resource|GdImage $image
 * @param string           $file_path
 * @return resource|GdImage
 */
function pg_image_apply_gd_orientation($image, $file_path)
{
    if (!function_exists('exif_read_data') || !function_exists('imagerotate')) {
        return $image;
    }

    $exif = @exif_read_data($file_path);

    if (!$exif || empty($exif['Orientation'])) {
        return $image;
    }

    $orientation = (int) $exif['Orientation'];
    $rotated     = null;
    $flip        = false;

    switch ($orientation) {

        case 2:
            $flip = true;
            break;

        case 3:
            $rotated = @imagerotate($image, 180, 0);
            break;

        case 4:
            $rotated = @imagerotate($image, 180, 0);
            $flip    = true;
            break;

        case 5:
            $rotated = @imagerotate($image, -90, 0);
            $flip    = true;
            break;

        case 6:
            $rotated = @imagerotate($image, -90, 0);
            break;

        case 7:
            $rotated = @imagerotate($image, 90, 0);
            $flip    = true;
            break;

        case 8:
            $rotated = @imagerotate($image, 90, 0);
            break;

        default:
            return $image;
    }

    if ($rotated) {
        imagedestroy($image);
        $image = $rotated;
    }

    if ($flip && function_exists('imageflip')) {
        imageflip($image, IMG_FLIP_HORIZONTAL);
    }

    return $image;
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
