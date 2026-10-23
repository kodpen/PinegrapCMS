<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The image engine and the responsive derivatives built on it.
 *
 * pg_process_image_file() is the one place that touches image pixels: the
 * optimize, resize, rotate and WebP buttons, the product upload pass, the
 * workspace thumbnails and the derivatives below all go through it. It lives
 * here, outside functions.php, because get_file.php makes the derivatives on
 * the first request for them and never loads functions.php (router.php
 * dispatches it that way on purpose - a file request should not parse
 * megabytes of PHP). One copy, two includers:
 *
 *   functions.php   require_once at the top - everything ordinary
 *   get_file.php    require_once when a request asks for a derivative
 *
 * CONTRACT - the functions in this file may only call each other and PHP
 * itself: no db(), no lang(), no output_error(). Reading the operator's
 * settings belongs to includes/fn/image.php (pg_image_settings()), which only
 * the functions.php side has; anything a derivative needs is passed in or is
 * a fixed default here.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR') && !defined('PG_GET_FILE')) {
    exit;
}


/**
 * A php.ini shorthand size ("8M", "128K", "1G") as bytes.
 *
 * ini_get() hands back the string the operator typed, and comparing that to a
 * file size compares "8M" to 14543599 — which in PHP is 8 against 14543599,
 * so the check passes and the upload fails anyway.
 *
 * @param string $value
 * @return int bytes; 0 for an unset value or an explicit no-limit (-1 or 0)
 */
function pg_ini_bytes($value)
{
    $value = trim((string) $value);

    if (($value === '') || ($value === '-1') || ($value === '0')) {
        return 0;
    }

    $bytes = (int) $value;
    $unit  = strtolower(substr($value, -1));

    if ($unit === 'g') {
        $bytes *= 1024 * 1024 * 1024;
    } elseif ($unit === 'm') {
        $bytes *= 1024 * 1024;
    } elseif ($unit === 'k') {
        $bytes *= 1024;
    }

    return max(0, $bytes);
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
 * Which library can write an image in this format: 'imagick', 'gd' or ''.
 *
 * Imagick is preferred, as everywhere in this engine. It is asked through
 * queryFormats(), which lists a format only when ImageMagick was built with
 * its delegate (libwebp, libheif). GD registers imagewebp() and imageavif()
 * only when it was compiled with the library, so the function's existence is
 * the answer there (imageavif() since PHP 8.1).
 *
 * The answer says the library claims the format, not that an encode will
 * succeed: a libheif without an AV1 encoder is listed and still fails to
 * write. pg_process_image_file() reports that as a failed write.
 *
 * Asked once per request per format.
 *
 * @param string $format webp or avif
 * @return string
 */
function pg_image_format_writer($format)
{
    static $cache = array();

    $format = strtolower((string) $format);

    if (isset($cache[$format])) {
        return $cache[$format];
    }

    $gd_writers = array('webp' => 'imagewebp', 'avif' => 'imageavif');

    $writer = '';

    if (isset($gd_writers[$format])) {

        if (class_exists('Imagick')) {
            try {
                if (count(Imagick::queryFormats(strtoupper($format))) > 0) {
                    $writer = 'imagick';
                }
            } catch (\Exception $exception) {
                $writer = '';
            }
        }

        if (($writer === '') && function_exists($gd_writers[$format]) && function_exists('imagecreatetruecolor')) {
            $writer = 'gd';
        }
    }

    $cache[$format] = $writer;

    return $writer;
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
 * Whether a PNG is an animated PNG (APNG).
 *
 * Both libraries decode only the default image of an APNG, so a derivative
 * or an optimized copy of one is a still picture and the animation is gone
 * from the file for good. An APNG says so with an acTL chunk before its first
 * IDAT; a still PNG has none there. Only the chunk headers are read, the
 * data of each chunk is skipped with a seek.
 *
 * @param string $file_path
 * @return bool
 */
function pg_image_is_animated_png($file_path)
{
    $handle = @fopen($file_path, 'rb');

    if (!$handle) {
        return false;
    }

    $animated = false;

    if (@fread($handle, 8) === "\x89PNG\r\n\x1A\n") {

        while (true) {

            $header = @fread($handle, 8);

            if (!is_string($header) || (strlen($header) !== 8)) {
                break;
            }

            $length = unpack('N', substr($header, 0, 4));
            $length = $length[1];
            $type = substr($header, 4, 4);

            if ($type === 'acTL') {
                $animated = true;
                break;
            }

            // The animation control chunk comes before the image data, so the
            // first IDAT ends the search.
            if (($type === 'IDAT') || ($type === 'IEND')) {
                break;
            }

            // The chunk's data and its CRC. A length that would overflow the
            // offset on 32-bit PHP ends the search: fseek() throws a TypeError
            // on a float, and no real PNG chunk comes near that size.
            if (($length < 0) || ($length > 0x7FFFFFFF) || ($length > PHP_INT_MAX - 4) || (@fseek($handle, $length + 4, SEEK_CUR) !== 0)) {
                break;
            }
        }
    }

    fclose($handle);

    return $animated;
}


/**
 * Whether a WebP is animated: the animation flag of its VP8X header (bit 1 of
 * the first byte after the chunk size). GD cannot decode an animated WebP at
 * all and Imagick writes its first frame back as a still.
 *
 * @param string $file_path
 * @return bool
 */
function pg_image_is_animated_webp($file_path)
{
    $head = @file_get_contents($file_path, false, null, 0, 21);

    return is_string($head)
        && (strlen($head) === 21)
        && (substr($head, 0, 4) === 'RIFF')
        && (substr($head, 8, 8) === 'WEBPVP8X')
        && ((ord($head[20]) & 0x02) !== 0);
}


/**
 * Whether an image file holds an animation: a GIF with more than one frame,
 * an APNG or an animated WebP. Told by the file's own first bytes, not by its
 * name. The engine leaves these files as they are (no optimize, no resize, no
 * derivative), and the markup gives them no srcset.
 *
 * @param string $file_path
 * @return bool
 */
function pg_image_is_animated($file_path)
{
    $head = @file_get_contents($file_path, false, null, 0, 12);

    if (!is_string($head) || (strlen($head) < 12)) {
        return false;
    }

    if (substr($head, 0, 4) === 'GIF8') {
        return pg_image_is_animated_gif($file_path);
    }

    if (substr($head, 0, 8) === "\x89PNG\r\n\x1A\n") {
        return pg_image_is_animated_png($file_path);
    }

    if ((substr($head, 0, 4) === 'RIFF') && (substr($head, 8, 4) === 'WEBP')) {
        return pg_image_is_animated_webp($file_path);
    }

    return false;
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
 *   format          str   'webp' or 'avif' re-encodes into that format;
 *                         reason no_webp / no_avif when nothing here can
 *                         write it.
 *   rotate          int   90, 180 or 270, clockwise.
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

    // 'format' re-encodes into another format instead of the source's own:
    // webp or avif. Anything else is ignored rather than guessed at.
    $format = isset($options['format']) ? strtolower((string) $options['format']) : '';

    if (!in_array($format, array('webp', 'avif'), true)) {
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

    // An animated GIF, PNG or WebP: both libraries would keep only one frame.
    if (in_array($type, array(IMAGETYPE_GIF, IMAGETYPE_PNG, defined('IMAGETYPE_WEBP') ? IMAGETYPE_WEBP : -1), true)
        && pg_image_is_animated($file_path)
    ) {
        $report['reason'] = 'animated';
        return $report;
    }

    $use_imagick = class_exists('Imagick');

    if (!$use_imagick && !function_exists('imagecreatetruecolor')) {
        $report['reason'] = 'no_library';
        return $report;
    }

    // A format conversion needs a library that can actually write the target:
    // Imagick when it can, GD when only GD can.
    if ($format !== '') {

        $writer = pg_image_format_writer($format);

        if ($writer === '') {
            $report['reason'] = 'no_' . $format;
            return $report;
        }

        $use_imagick = ($writer === 'imagick');
    }

    // GD turns a JPEG upright from its EXIF orientation (Imagick reads the
    // tag itself). When the file carries an Exif block whose orientation
    // cannot be read, which way up a browser shows it is not known: a copy
    // written without the tag could come out on its side, so the file is
    // left as it is.
    if (!$use_imagick && ($type == IMAGETYPE_JPEG) && (pg_image_jpeg_orientation($file_path) === 0)) {
        $report['reason'] = 'orientation_unreadable';
        return $report;
    }

    // GD writes no colour profile, and pixels written without theirs are
    // shown as sRGB: a Display P3 (phone) or Adobe RGB photo comes out
    // washed out, and on Optimize for good. Imagick carries the profile
    // over, so only the GD half leaves such a file as it is.
    if (!$use_imagick && pg_image_has_foreign_color_profile($file_path)) {
        $report['reason'] = 'color_profile';
        return $report;
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

        // stripImage() drops the ICC profile with the rest of the metadata,
        // and pixels without their profile are shown as sRGB: a Display P3
        // or Adobe RGB photo turned pale. The profile is taken first and put
        // back after the strip, unless the pixels are CMYK and are converted
        // below (a CMYK profile no longer describes them then).
        // setImageProfile() attaches the bytes on every build; profileImage()
        // attaches nothing on an ImageMagick built without lcms. An sRGB
        // profile is not put back: untagged pixels are shown as sRGB anyway,
        // and it would add about 3 KB to every derivative and Optimize result.
        $icc = '';

        if ($image->getImageColorspace() != Imagick::COLORSPACE_CMYK) {
            $profiles = $image->getImageProfiles('icc', true);
            $icc = isset($profiles['icc']) ? (string) $profiles['icc'] : '';
        }

        $image->stripImage();

        if (($icc !== '') && !pg_image_icc_is_srgb($icc)) {
            $image->setImageProfile('icc', $icc);
        }

        // Only converted when it has to be. A CMYK JPEG shown in a browser is
        // the classic "colours went wrong after optimizing" report; tagging an
        // image that is already sRGB as linear RGB, which is what the old code
        // did with setColorspace(), is the other half of the same problem.
        if ($image->getImageColorspace() == Imagick::COLORSPACE_CMYK) {
            $image->transformImageColorspace(Imagick::COLORSPACE_SRGB);
        }

        if ($resize) {

            // The orientation fix above can have swapped the edges, so the
            // target is worked out again from the upright picture, as the GD
            // half does. Taken from getimagesize() alone, a phone photo stored
            // 4000x3000 on its side was turned to 3000x4000 and then squeezed
            // into 2400x1800.
            list($target_width, $target_height) = pg_image_scaled_dimensions(
                $image->getImageWidth(),
                $image->getImageHeight(),
                max($target_width, $target_height));

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

        } elseif ($format === 'avif') {
            // AVIF is written by ImageMagick's heic coder through libheif.
            // Its speed runs from 0 (smallest file, slowest) to 9; 6 keeps a
            // 1920 px encode within a request. A build that does not know the
            // option ignores it.
            $image->setImageFormat('avif');
            $image->setImageCompressionQuality($quality);
            $image->setOption('heic:speed', '6');

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

    // A GIF decodes as a palette with at most one transparent index. The
    // resize canvas and imagerotate() are truecolor, and the GIF writer only
    // gets that transparency back when told which truecolor value stands for
    // it (see the GIF case below), so whether there was one is noted here.
    $gif_transparent = ($type == IMAGETYPE_GIF) && (imagecolortransparent($source) >= 0);

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

    if (($format === 'webp') || ($format === 'avif')) {

        // imagewebp() and imageavif() need a truecolor canvas; a palette GIF
        // or PNG-8 that skipped the resize path above is still in palette
        // form here. The conversion keeps the alpha channel.
        if (function_exists('imageistruecolor') && !imageistruecolor($image) && function_exists('imagepalettetotruecolor')) {
            imagepalettetotruecolor($image);
        }

        imagesavealpha($image, true);

        if ($format === 'webp') {
            $written = function_exists('imagewebp') ? imagewebp($image, null, $quality) : false;

        // A GD built against a libavif that can decode but not encode has
        // the function and fails with a warning; that is a failed write here.
        } else {
            $written = function_exists('imageavif') ? @imageavif($image, null, $quality) : false;
        }

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
            // A truecolor canvas (after a resize or a turn) reaches imagegif()
            // with its transparent pixels as rgb 0,0,0 alpha 127: that is what
            // imagepalettetotruecolor() writes for the transparent index and
            // what resampling leaves where every source pixel was transparent.
            // libgd's palette quantizer drops alpha, so those pixels would
            // land on the nearest opaque colour (a black background); it only
            // maps pixels that equal the image's transparent value to the
            // GIF's transparent index. imagepalettetotruecolor() sets that
            // value to the hidden colour under the index instead, which no
            // pixel has, so it is pointed at the pixels' actual value here.
            if ($gif_transparent && function_exists('imageistruecolor') && imageistruecolor($image)) {
                imagecolortransparent($image, imagecolorallocatealpha($image, 0, 0, 0, 127));
            }

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
    if (!function_exists('imagerotate')) {
        return $image;
    }

    // The exif extension when there is one, else the file's own bytes: a
    // server without exif (Windows PHP and the upstream php Docker images
    // leave it out) used to keep every phone photo on its side.
    $orientation = pg_image_jpeg_orientation($file_path);
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


/* ---------------------------------------------------------------------------
 * Responsive derivatives
 *
 * The same picture at one of a few fixed widths, in its own format or as
 * WebP / AVIF, for srcset and <picture>. Asked for on the file's own address:
 *
 *   /photo.jpg?w=960          960 px wide, JPEG
 *   /photo.jpg?w=960&f=webp   960 px wide, WebP
 *   /photo.jpg?f=avif         full size, AVIF (WebP when AVIF cannot be made)
 *
 * get_file.php makes a derivative on the first request for it, never in the
 * upload path: uploads stay as fast as they were, and a site that has been
 * running for years gains derivatives for its old files without a rebuild.
 * It is kept as data/cache/img/<file id>/<width>-<signature>.<extension>. The
 * signature is taken from the source file's modification time and size and
 * from the encoding quality, so a file that is optimized, rotated or replaced
 * stops matching its old derivatives and gets new ones on the next request.
 *
 * Only the fixed widths are made, whatever is asked: a requested width is
 * rounded up to the next one, and none is wider than the source. Any other
 * number therefore lands on a derivative that exists anyway, which keeps a
 * crawler or a hostile client from filling the disk with one copy per pixel.
 * ------------------------------------------------------------------------- */

/**
 * The widths a derivative is made at, narrowest first.
 *
 * @return array
 */
function pg_image_derivative_widths()
{
    return array(480, 960, 1440, 1920);
}


/**
 * What a query string asks of the image: a width, a format, or both.
 *
 * w is a whole number of pixels; f is webp or avif. Anything else is not a
 * derivative request and the original is served as it always was.
 *
 * @param array $query $_GET
 * @return array|null width (0 = the source's own), format ('' = the source's own)
 */
function pg_image_derivative_request($query)
{
    if (!is_array($query)) {
        return null;
    }

    $width = 0;

    if (isset($query['w']) && is_string($query['w']) && preg_match('/^[0-9]{1,5}$/', $query['w'])) {
        $width = (int) $query['w'];
    }

    $format = '';

    if (isset($query['f']) && is_string($query['f'])) {

        $candidate = strtolower(trim($query['f']));

        if (($candidate === 'webp') || ($candidate === 'avif')) {
            $format = $candidate;
        }
    }

    if (($width < 1) && ($format === '')) {
        return null;
    }

    return array('width' => $width, 'format' => $format);
}


/**
 * Whether a file with this extension can have derivatives.
 *
 * The raster types the engine reads. SVG is drawn at any size already, and an
 * AVIF source is left alone: nothing here reads it.
 *
 * @param string $extension lower case, without the dot
 * @return bool
 */
function pg_image_derivative_source_ok($extension)
{
    return in_array((string) $extension, array('jpg', 'jpeg', 'jpe', 'png', 'gif', 'webp', 'bmp', 'tif', 'tiff'), true);
}


/**
 * The width a derivative is actually made at.
 *
 * Rounded up to the next fixed width (a request for 700 gets 960, so the
 * browser never has to stretch it), the widest one for anything above it, and
 * never wider than the source: a 1200 px photo asked for at 1440 is served at
 * 1200, and asked for at 1920 the same file answers.
 *
 * @param int $requested    0 when only a format was asked for
 * @param int $source_width as displayed, after EXIF orientation
 * @return int 0 when the source has no width; the source width when no
 *             resize is due
 */
function pg_image_derivative_width($requested, $source_width)
{
    $requested = (int) $requested;
    $source_width = (int) $source_width;

    if ($source_width < 1) {
        return 0;
    }

    if ($requested < 1) {
        return $source_width;
    }

    $widths = pg_image_derivative_widths();
    $width = end($widths);

    foreach ($widths as $step) {
        if ($step >= $requested) {
            $width = $step;
            break;
        }
    }

    return min($width, $source_width);
}


/**
 * The longest-edge ceiling that gives a derivative this width.
 *
 * The engine scales by the longest edge; srcset counts width. For a landscape
 * picture the two are the same number, for a portrait one the ceiling is the
 * height the picture has at that width.
 *
 * @param int $width         the derivative's width
 * @param int $source_width  as displayed
 * @param int $source_height as displayed
 * @return int
 */
function pg_image_derivative_long_edge($width, $source_width, $source_height)
{
    $width = (int) $width;
    $source_width = (int) $source_width;
    $source_height = (int) $source_height;

    if (($width < 1) || ($source_width < 1) || ($source_height < 1)) {
        return 0;
    }

    if ($source_width >= $source_height) {
        return $width;
    }

    return (int) round($width * $source_height / $source_width);
}


/**
 * The format a derivative is written in: 'webp', 'avif', or '' for the
 * source's own.
 *
 * AVIF falls back to WebP when this server cannot write AVIF, and either
 * falls back to the source's own format when it cannot write that either.
 * Asking a WebP file for WebP is not a conversion.
 *
 * @param string $requested        webp, avif or ''
 * @param string $source_extension lower case
 * @param array  $writable         format => bool, pg_image_format_writer()
 * @return string
 */
function pg_image_derivative_format($requested, $source_extension, $writable)
{
    $requested = strtolower((string) $requested);
    $source_extension = strtolower((string) $source_extension);

    $order = array();

    if ($requested === 'avif') {
        $order = array('avif', 'webp');
    } elseif ($requested === 'webp') {
        $order = array('webp');
    }

    foreach ($order as $format) {

        if ($format === $source_extension) {
            return '';
        }

        if (!empty($writable[$format])) {
            return $format;
        }
    }

    return '';
}


/**
 * How derivatives are delivered, from the config row: which modern formats
 * are offered and the encoding quality of each.
 *
 * get_file.php reads the row itself (it never loads functions.php) and
 * pg_image_delivery() reads it for everything else; both come through here,
 * so a value out of range is clamped the same way on both sides. A column the
 * row does not have (the 2026.11.0 upgrade has not run, or the read failed)
 * takes its default, which is what the software did before the settings.
 *
 * The three scales are not the same one: AVIF 50 and WebP 80 are the common
 * web defaults for those formats, and 82 is what the resize button uses for
 * JPEG (image_resize_quality). AVIF holds up lower on its own scale than the
 * other two, hence its lower floor.
 *
 * @param array|null $row image_derivative_formats (bits: 1 WebP, 2 AVIF),
 *                        image_quality_webp, image_quality_avif,
 *                        image_resize_quality
 * @return array formats (webp, avif => bool), quality (webp, avif, source)
 */
function pg_image_delivery_settings($row)
{
    $row = is_array($row) ? $row : array();

    $value = function ($key, $default, $min) use ($row) {
        if (!isset($row[$key]) || !is_numeric($row[$key])) {
            return $default;
        }

        return max($min, min(100, (int) $row[$key]));
    };

    $bits = (isset($row['image_derivative_formats']) && is_numeric($row['image_derivative_formats']))
        ? (int) $row['image_derivative_formats']
        : 3;

    return array(
        'formats' => array(
            'webp' => (($bits & 1) === 1),
            'avif' => (($bits & 2) === 2)),
        'quality' => array(
            'webp'   => $value('image_quality_webp', 80, 40),
            'avif'   => $value('image_quality_avif', 50, 20),
            'source' => $value('image_resize_quality', 82, 40)));
}


/**
 * The encoding quality of a derivative.
 *
 * PNG and GIF are lossless and ignore it.
 *
 * @param string     $format   the derivative's format or the source's extension
 * @param array|null $settings pg_image_delivery_settings(); null for the defaults
 * @return int
 */
function pg_image_derivative_quality($format, $settings = null)
{
    if (!is_array($settings) || !isset($settings['quality'])) {
        $settings = pg_image_delivery_settings(null);
    }

    $format = strtolower((string) $format);

    return isset($settings['quality'][$format])
        ? (int) $settings['quality'][$format]
        : (int) $settings['quality']['source'];
}


/**
 * The formats a derivative may be written in on this server under these
 * settings: written by a library (pg_image_derivative_formats()) and offered
 * by the operator.
 *
 * @param array      $writable format => bool, pg_image_derivative_formats()
 * @param array|null $settings pg_image_delivery_settings(); null for the defaults
 * @return array webp, avif => bool
 */
function pg_image_derivative_offered($writable, $settings = null)
{
    if (!is_array($settings) || !isset($settings['formats'])) {
        $settings = pg_image_delivery_settings(null);
    }

    $offered = array();

    foreach (array('webp', 'avif') as $format) {
        $offered[$format] = !empty($writable[$format]) && !empty($settings['formats'][$format]);
    }

    return $offered;
}


/**
 * The part of a derivative's name that ties it to the bytes it was made from.
 *
 * Modification time and size stand for the source's content: every writer in
 * the software replaces a file through rename(), which gives it a new time. A
 * file restored from a backup with its old time keeps the signature only if
 * its size is also unchanged. The leading 1 is the recipe: raised when the way
 * derivatives are made changes, it replaces every older one on its next
 * request.
 *
 * @param int $mtime
 * @param int $size
 * @param int $quality
 * @return string 12 hex characters
 */
function pg_image_derivative_signature($mtime, $size, $quality)
{
    return substr(md5('1:' . (int) $mtime . ':' . (int) $size . ':' . (int) $quality), 0, 12);
}


/**
 * A derivative's file name inside its file's cache folder.
 *
 * @param int    $width
 * @param string $signature pg_image_derivative_signature()
 * @param string $extension webp, avif or the source's own
 * @return string
 */
function pg_image_derivative_file_name($width, $signature, $extension)
{
    return ((int) $width) . '-' . $signature . '.' . $extension;
}


/**
 * The name a browser saves a derivative under: photo.jpg at 960 as WebP is
 * photo-960.webp, so the extension says what the bytes are.
 *
 * @param string $name      the original's file name
 * @param int    $width
 * @param string $extension
 * @return string
 */
function pg_image_derivative_download_name($name, $width, $extension)
{
    $base = (string) $name;
    $dot = strrpos($base, '.');

    if (($dot !== false) && ($dot > 0)) {
        $base = substr($base, 0, $dot);
    }

    return $base . '-' . ((int) $width) . '.' . $extension;
}


/**
 * Where derivatives are kept: data/cache/img, beside data/files.
 *
 * Taken from FILE_DIRECTORY_PATH, as data/backups is, rather than from the
 * software directory: two sites that share one software directory with their
 * own file directories must not answer each other's file id 12. data/ is
 * refused over HTTP by the server rules, so nothing here is reachable except
 * through get_file.php and its access checks.
 *
 * @return string '' when the file directory is not known
 */
function pg_image_derivative_directory()
{
    if (!defined('FILE_DIRECTORY_PATH')) {
        return '';
    }

    return dirname(FILE_DIRECTORY_PATH) . '/cache/img';
}


/**
 * Whether this server can really encode WebP and AVIF: format => bool.
 *
 * pg_image_format_writer() only says a library claims the format, and an
 * ImageMagick built with a libheif that has no AV1 encoder lists AVIF and then
 * fails every write. Taken on trust, every ?f=avif request on such a host
 * would decode the picture, fail, and send the full-size original where WebP
 * at the asked width was possible. So each claimed format is tried once, on a
 * 16 px picture, and the answer is kept in <directory>/formats.json under
 * pg_image_derivative_formats_key(): a server upgrade asks again. So does a
 * week, because a codec plugin can be installed without any version that the
 * key sees changing.
 *
 * Without a writable directory nothing is tried (no derivative can be kept
 * there either) and the claims are answered as they are.
 *
 * @param string $directory pg_image_derivative_directory()
 * @return array webp, avif
 */
function pg_image_derivative_formats($directory)
{
    static $cache = array();

    $directory = rtrim((string) $directory, '/\\');

    if (isset($cache[$directory])) {
        return $cache[$directory];
    }

    $formats = array();

    foreach (array('webp', 'avif') as $format) {
        $formats[$format] = (pg_image_format_writer($format) !== '');
    }

    $key = pg_image_derivative_formats_key();
    $file = $directory . '/formats.json';

    $stored = ($directory !== '') ? @json_decode((string) @file_get_contents($file), true) : null;

    if (
        is_array($stored)
        && isset($stored['key'], $stored['time'], $stored['formats'])
        && ($stored['key'] === $key)
        && ((int) $stored['time'] > (time() - 604800))
        && is_array($stored['formats'])
    ) {

        foreach ($formats as $format => $claimed) {
            $formats[$format] = $claimed && !empty($stored['formats'][$format]);
        }

    } elseif (($directory !== '') && (@is_dir($directory) || @mkdir($directory, 0755, true)) && @is_writable($directory)) {

        foreach ($formats as $format => $claimed) {
            $formats[$format] = $claimed && pg_image_format_probe($format, $directory);
        }

        pg_image_derivative_put($file, json_encode(array('key' => $key, 'time' => time(), 'formats' => $formats)));
    }

    $cache[$directory] = $formats;

    return $formats;
}


/**
 * What the stored answer of pg_image_derivative_formats() was found under:
 * the PHP, GD and ImageMagick versions and which library claims which format.
 *
 * @return string
 */
function pg_image_derivative_formats_key()
{
    $gd = function_exists('gd_info') ? gd_info() : array();
    $imagick = '';

    if (class_exists('Imagick')) {
        try {
            $version = Imagick::getVersion();
            $imagick = isset($version['versionString']) ? (string) $version['versionString'] : '';
        } catch (\Exception $exception) {
            $imagick = '';
        }
    }

    return md5(json_encode(array(
        PHP_VERSION,
        isset($gd['GD Version']) ? $gd['GD Version'] : '',
        $imagick,
        pg_image_format_writer('webp'),
        pg_image_format_writer('avif'))));
}


/**
 * Encode a 16 px picture with alpha into a format through the engine, and say
 * whether real bytes of that format came out.
 *
 * @param string $format    webp or avif
 * @param string $directory a writable folder for the probe's source file
 * @return bool
 */
function pg_image_format_probe($format, $directory)
{
    // RIFF....WEBP, and an ISO box ....ftypavif.
    $signatures = array('webp' => array(8, 'WEBP'), 'avif' => array(8, 'avif'));

    if (!isset($signatures[$format])) {
        return false;
    }

    $source = $directory . '/probe-' . (function_exists('getmypid') ? getmypid() : uniqid()) . '.png';

    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAM0lEQVQ4y+3OsQ0AIAgAwTNxAMaltJOx3YFYUn31yS3ODTKoTreQKHrdoj8bwQhG8E/wAGnJgp2pmVIpAAAAAElFTkSuQmCC');

    if (@file_put_contents($source, $png) === false) {
        return false;
    }

    $report = pg_process_image_file($source, array('format' => $format, 'quality' => pg_image_derivative_quality($format), 'write' => false));

    @unlink($source);

    if (($report['status'] !== 'success') || !is_string($report['data'])) {
        return false;
    }

    list($offset, $magic) = $signatures[$format];

    return (substr($report['data'], $offset, 4) === $magic);
}


/**
 * The EXIF orientation of a JPEG, 1 to 8; 1 when it has none; 0 when it has
 * an Exif block whose orientation cannot be read.
 *
 * Read with the exif extension when it is loaded and it read the first IFD;
 * else from the file's own bytes (pg_image_jpeg_exif_orientation()). Imagick
 * (a header ping) is asked only for a block those bytes cannot read, or when
 * the exif extension is missing; it is then also what the engine uses. The
 * GD half of the engine turns the pixels by this number.
 *
 * @param string $file_path
 * @return int
 */
function pg_image_jpeg_orientation($file_path)
{
    if (function_exists('exif_read_data')) {
        $exif = @exif_read_data($file_path);

        if (is_array($exif) && isset($exif['Orientation']) && is_numeric($exif['Orientation'])) {
            $orientation = (int) $exif['Orientation'];

            return (($orientation >= 1) && ($orientation <= 8)) ? $orientation : 1;
        }

        // IFD0 was read and holds no orientation.
        if (is_array($exif) && isset($exif['SectionsFound']) && in_array('IFD0', explode(',', str_replace(' ', '', (string) $exif['SectionsFound'])), true)) {
            return 1;
        }

        // exif read no IFD0: the file's own bytes tell "no Exif block" (1)
        // from a broken one (0) without an Imagick ping on every page render.
        $own = pg_image_jpeg_exif_orientation($file_path);

        if (($own !== 0) || !class_exists('Imagick')) {
            return $own;
        }
    }

    if (class_exists('Imagick')) {

        $image = null;

        try {
            // ping reads the header, not the pixels. Imagick's orientation
            // constants are the EXIF numbers.
            $image = new Imagick();
            $image->pingImage($file_path);
            $orientation = (int) $image->getImageOrientation();
            $image->clear();

            return (($orientation >= 1) && ($orientation <= 8)) ? $orientation : 1;

        } catch (\Exception $exception) {
            return 1;
        }
    }

    return pg_image_jpeg_exif_orientation($file_path);
}


/**
 * The EXIF orientation of a JPEG read from its own bytes, for a server
 * without the exif extension: 1 to 8; 1 when the file has no Exif block or
 * the block has no orientation (or one outside 1 to 8, which browsers ignore
 * too); 0 when there is an Exif block that cannot be read.
 *
 * The marker segments are walked from the start of the file to the Exif
 * APP1 segment ("Exif\0\0"); every other segment's data is skipped with a
 * seek. The walk ends at the start of the image data (SOS).
 *
 * @param string $file_path
 * @return int
 */
function pg_image_jpeg_exif_orientation($file_path)
{
    $segments = pg_image_jpeg_app_segments($file_path, 0xE1, "Exif\x00");

    if ($segments === false) {
        return 0;
    }

    // An APP1 segment is also where XMP lives; only the Exif one holds the
    // orientation, and the first one counts.
    return $segments ? pg_image_exif_tiff_orientation(substr($segments[0], 6)) : 1;
}


/**
 * The data of a JPEG's APPn segments of one marker that start with $prefix,
 * in file order, prefix included.
 *
 * The marker segments are walked from the start of the file; every other
 * segment's data is skipped with a seek. The walk ends at the start of the
 * image data (SOS), which no metadata segment follows.
 *
 * @param string $file_path
 * @param int    $marker 0xE1 for APP1, 0xE2 for APP2 ...
 * @param string $prefix
 * @return array|false false when a matching segment is cut short by the end of the file
 */
function pg_image_jpeg_app_segments($file_path, $marker, $prefix)
{
    $segments = array();
    $handle = @fopen($file_path, 'rb');

    if (!$handle) {
        return $segments;
    }

    if (@fread($handle, 2) === "\xFF\xD8") {

        while (true) {

            $byte = @fread($handle, 1);

            if (!is_string($byte) || ($byte !== "\xFF")) {
                break;
            }

            // A marker may be preceded by any number of 0xFF fill bytes.
            do {
                $byte = @fread($handle, 1);
            } while (is_string($byte) && ($byte === "\xFF"));

            if (!is_string($byte) || ($byte === '')) {
                break;
            }

            $code = ord($byte);

            // RSTn and TEM stand alone, without a length.
            if ((($code >= 0xD0) && ($code <= 0xD7)) || ($code === 0x01)) {
                continue;
            }

            // Start of scan or end of image.
            if (($code === 0xDA) || ($code === 0xD9)) {
                break;
            }

            $length = @fread($handle, 2);

            if (!is_string($length) || (strlen($length) !== 2)) {
                break;
            }

            $length = unpack('n', $length);
            $length = $length[1] - 2;

            if ($length < 0) {
                break;
            }

            if (($code === $marker) && ($length >= strlen($prefix))) {

                $data = (string) @fread($handle, $length);

                if (strncmp($data, $prefix, strlen($prefix)) === 0) {

                    if (strlen($data) !== $length) {
                        $segments = false;
                        break;
                    }

                    $segments[] = $data;
                    continue;
                }

                if (strlen($data) !== $length) {
                    break;
                }

                continue;
            }

            if (@fseek($handle, $length, SEEK_CUR) !== 0) {
                break;
            }
        }
    }

    fclose($handle);

    return $segments;
}


/**
 * The ICC colour profile embedded in an image file: its bytes, '' when there
 * is none, false when the file announces one that cannot be read.
 *
 * Told by the file's own first bytes: a JPEG's APP2 "ICC_PROFILE" chunks
 * (joined in their sequence order), a PNG's iCCP chunk (an sRGB chunk means
 * sRGB, no profile), a WebP's ICCP chunk. Any other file has none here.
 *
 * @param string $file_path
 * @return string|false
 */
function pg_image_icc_profile($file_path)
{
    $head = @file_get_contents($file_path, false, null, 0, 12);

    if (!is_string($head) || (strlen($head) < 12)) {
        return '';
    }

    if (substr($head, 0, 2) === "\xFF\xD8") {

        $segments = pg_image_jpeg_app_segments($file_path, 0xE2, "ICC_PROFILE\x00");

        if ($segments === false) {
            return false;
        }

        $chunks = array();

        foreach ($segments as $segment) {

            if (strlen($segment) < 14) {
                return false;
            }

            $chunks[ord($segment[12])] = substr($segment, 14);
        }

        ksort($chunks);

        return implode('', $chunks);
    }

    if (substr($head, 0, 8) === "\x89PNG\r\n\x1A\n") {
        return pg_image_png_icc_profile($file_path);
    }

    if ((substr($head, 0, 4) === 'RIFF') && (substr($head, 8, 4) === 'WEBP')) {
        return pg_image_webp_icc_profile($file_path);
    }

    return '';
}


/**
 * A PNG's ICC profile from its iCCP chunk (zlib-compressed): '' when there is
 * none or an sRGB chunk says the pixels are sRGB; false when the chunk cannot
 * be read. Only chunk headers are read on the way; the search ends at IDAT.
 *
 * @param string $file_path
 * @return string|false
 */
function pg_image_png_icc_profile($file_path)
{
    $handle = @fopen($file_path, 'rb');

    if (!$handle) {
        return '';
    }

    $profile = '';

    if (@fread($handle, 8) === "\x89PNG\r\n\x1A\n") {

        while (true) {

            $header = @fread($handle, 8);

            if (!is_string($header) || (strlen($header) !== 8)) {
                break;
            }

            $length = unpack('N', substr($header, 0, 4));
            $length = $length[1];
            $type = substr($header, 4, 4);

            if (($type === 'sRGB') || ($type === 'IDAT') || ($type === 'IEND')) {
                break;
            }

            if ($type === 'iCCP') {

                $profile = false;

                // A profile is a few kilobytes; 16 MB keeps a hostile length
                // from being read into memory.
                $data = (($length > 0) && ($length <= 16777216)) ? (string) @fread($handle, $length) : '';
                $name_end = strpos($data, "\x00");

                // Name, NUL, compression method, then the zlib stream.
                if ((strlen($data) === $length) && ($name_end !== false) && function_exists('gzuncompress')) {
                    // A real profile inflates to a few kilobytes, a few hundred at
                    // most. A larger stream counts as unreadable (and so foreign)
                    // instead of being inflated: a small zlib bomb would otherwise
                    // exhaust memory, which @ does not stop.
                    $icc = @gzuncompress(substr($data, $name_end + 2), 4194304);

                    if (is_string($icc) && ($icc !== '')) {
                        $profile = $icc;
                    }
                }

                break;
            }

            // The chunk's data and its CRC; a length that would overflow the
            // offset on 32-bit PHP ends the search, as in pg_image_is_animated_png().
            if (($length < 0) || ($length > 0x7FFFFFFF) || ($length > PHP_INT_MAX - 4) || (@fseek($handle, $length + 4, SEEK_CUR) !== 0)) {
                break;
            }
        }
    }

    fclose($handle);

    return $profile;
}


/**
 * A WebP's ICC profile from its ICCP chunk: '' when there is none; false when
 * the VP8X header announces one (flag 0x20) that is not there or cut short.
 * The ICCP chunk follows VP8X, before the image or animation data.
 *
 * @param string $file_path
 * @return string|false
 */
function pg_image_webp_icc_profile($file_path)
{
    $handle = @fopen($file_path, 'rb');

    if (!$handle) {
        return '';
    }

    $profile = '';
    $head = @fread($handle, 12);

    if (is_string($head) && (strlen($head) === 12) && (substr($head, 0, 4) === 'RIFF') && (substr($head, 8, 4) === 'WEBP')) {

        $announced = false;

        while (true) {

            $header = @fread($handle, 8);

            if (!is_string($header) || (strlen($header) !== 8)) {
                break;
            }

            $type = substr($header, 0, 4);
            $size = unpack('V', substr($header, 4, 4));
            $size = $size[1];

            if (($size < 0) || ($size > 16777216)) {
                break;
            }

            if ($type === 'ICCP') {
                $data = ($size > 0) ? (string) @fread($handle, $size) : '';
                $profile = (($size > 0) && (strlen($data) === $size)) ? $data : false;
                $announced = false;
                break;
            }

            if ($type === 'VP8X') {
                $data = (string) @fread($handle, $size);
                $announced = (strlen($data) >= 1) && ((ord($data[0]) & 0x20) !== 0);

                if (!$announced || (($size % 2) && (@fseek($handle, 1, SEEK_CUR) !== 0))) {
                    break;
                }

                continue;
            }

            // The image or animation data: no profile comes after it.
            break;
        }

        if ($announced) {
            $profile = false;
        }
    }

    fclose($handle);

    return $profile;
}


/**
 * Whether an ICC profile describes sRGB: an RGB profile whose description
 * ('desc' tag, a v2 text or a v4 multi-language record) names sRGB.
 *
 * A profile that cannot be read counts as not sRGB.
 *
 * @param string $icc
 * @return bool
 */
function pg_image_icc_is_srgb($icc)
{
    $icc = (string) $icc;
    $length = strlen($icc);

    if (($length < 132) || (substr($icc, 16, 4) !== 'RGB ') || (substr($icc, 36, 4) !== 'acsp')) {
        return false;
    }

    $count = unpack('N', substr($icc, 128, 4));
    $count = $count[1];

    if (($count < 0) || (132 + ($count * 12) > $length)) {
        return false;
    }

    for ($i = 0; $i < $count; $i++) {

        $entry = substr($icc, 132 + ($i * 12), 12);

        if (substr($entry, 0, 4) !== 'desc') {
            continue;
        }

        $offset = unpack('N', substr($entry, 4, 4));
        $offset = $offset[1];
        $size = unpack('N', substr($entry, 8, 4));
        $size = $size[1];

        if (($offset < 0) || ($size < 12) || ($offset + $size > $length)) {
            return false;
        }

        $tag = substr($icc, $offset, $size);
        $text = '';

        if (substr($tag, 0, 4) === 'desc') {
            $text_length = unpack('N', substr($tag, 8, 4));
            $text = substr($tag, 12, max(0, min($text_length[1], $size - 12)));

        } elseif ((substr($tag, 0, 4) === 'mluc') && ($size >= 28)) {
            // The first record: UTF-16BE; an ASCII name reads with the NULs
            // taken out.
            $text_length = unpack('N', substr($tag, 20, 4));
            $text_offset = unpack('N', substr($tag, 24, 4));
            $text = str_replace("\x00", '', (string) substr($tag, max(0, $text_offset[1]), max(0, $text_length[1])));
        }

        return (stripos($text, 'srgb') !== false);
    }

    return false;
}


/**
 * Whether an image file carries a colour profile other than sRGB (or one
 * that cannot be read). A file without a profile is shown as sRGB.
 *
 * @param string $file_path
 * @return bool
 */
function pg_image_has_foreign_color_profile($file_path)
{
    $icc = pg_image_icc_profile($file_path);

    if ($icc === '') {
        return false;
    }

    return ($icc === false) || !pg_image_icc_is_srgb($icc);
}


/**
 * The orientation tag (0x0112) of the first IFD of an Exif TIFF block: 1 to
 * 8; 1 when there is no such tag or its value is outside 1 to 8; 0 when the
 * block cannot be read (unknown byte order, an offset or entry outside it,
 * a tag of an unexpected type).
 *
 * @param string $tiff the bytes after "Exif\0\0"
 * @return int
 */
function pg_image_exif_tiff_orientation($tiff)
{
    $tiff = (string) $tiff;
    $length = strlen($tiff);

    // An Exif header followed by no TIFF block holds no orientation, and
    // browsers show the file as stored.
    if ($length === 0) {
        return 1;
    }

    if ($length < 8) {
        return 0;
    }

    $order = substr($tiff, 0, 2);

    if ($order === 'II') {
        $short = 'v';
        $long = 'V';
    } elseif ($order === 'MM') {
        $short = 'n';
        $long = 'N';
    } else {
        return 0;
    }

    $magic = unpack($short, substr($tiff, 2, 2));

    if ($magic[1] !== 42) {
        return 0;
    }

    $offset = unpack($long, substr($tiff, 4, 4));
    $offset = $offset[1];

    // A negative offset is a value over 2 GB read on 32-bit PHP.
    if (($offset < 8) || ($offset + 2 > $length)) {
        return 0;
    }

    $count = unpack($short, substr($tiff, $offset, 2));
    $count = $count[1];

    if ($offset + 2 + ($count * 12) > $length) {
        return 0;
    }

    for ($i = 0; $i < $count; $i++) {

        $entry = substr($tiff, $offset + 2 + ($i * 12), 12);
        $tag = unpack($short, substr($entry, 0, 2));

        if ($tag[1] !== 0x0112) {
            continue;
        }

        $type = unpack($short, substr($entry, 2, 2));

        // SHORT as the specification says; LONG from some writers.
        if ($type[1] === 3) {
            $value = unpack($short, substr($entry, 8, 2));
        } elseif ($type[1] === 4) {
            $value = unpack($long, substr($entry, 8, 4));
        } else {
            return 0;
        }

        $value = (int) $value[1];

        return (($value >= 1) && ($value <= 8)) ? $value : 1;
    }

    return 1;
}


/**
 * A picture's width and height as it is displayed: a phone photo stored on
 * its side with an orientation tag that turns it upright has its two edges
 * swapped here, because the engine turns the pixels upright before scaling.
 *
 * A JPEG whose Exif orientation cannot be read has no size here: which way
 * up a browser shows it is not known, so no derivative and no srcset width
 * is made from it.
 *
 * @param string $file_path
 * @return array width, height; zeros when the file is not a readable image
 *               or its orientation cannot be read
 */
function pg_image_oriented_size($file_path)
{
    $size = @getimagesize($file_path);

    if (!$size || empty($size[0]) || empty($size[1])) {
        return array(0, 0);
    }

    $width = (int) $size[0];
    $height = (int) $size[1];

    if (isset($size[2]) && ((int) $size[2] === IMAGETYPE_JPEG)) {

        $orientation = pg_image_jpeg_orientation($file_path);

        if ($orientation === 0) {
            return array(0, 0);
        }

        // Orientations 5 to 8 are the quarter turns.
        if ($orientation >= 5) {
            return array($height, $width);
        }
    }

    return array($width, $height);
}


/**
 * Write a cache file in one step: to a temporary name beside it, then renamed
 * over it, so a request reading it never sees half a picture.
 *
 * @param string $path
 * @param string $data
 * @return bool
 */
function pg_image_derivative_put($path, $data)
{
    $temporary = $path . '.tmp' . (function_exists('getmypid') ? getmypid() : uniqid()); // disable_functions on some hosts

    if (@file_put_contents($temporary, $data) === false) {
        @unlink($temporary);
        return false;
    }

    if (!@rename($temporary, $path)) {
        @unlink($temporary);
        return false;
    }

    return true;
}


/**
 * Remove the derivatives of the same width and format made from earlier bytes
 * of the source, and their skip markers.
 *
 * @param string $folder    the file's cache folder
 * @param int    $width
 * @param string $extension
 * @param string $keep      the current derivative's file name
 * @return void
 */
function pg_image_derivative_prune($folder, $width, $extension, $keep)
{
    $entries = @scandir($folder);

    if (!is_array($entries)) {
        return;
    }

    $pattern = '/^' . ((int) $width) . '-[0-9a-f]{12}\.' . preg_quote($extension, '/') . '(\.skip)?$/';

    foreach ($entries as $entry) {
        if (($entry !== $keep) && ($entry !== $keep . '.skip') && preg_match($pattern, $entry)) {
            @unlink($folder . '/' . $entry);
        }
    }
}


/**
 * Let go of the lock a derivative was made under.
 *
 * @param resource $lock
 * @param string   $path the derivative's path
 * @return void
 */
function pg_image_derivative_unlock($lock, $path)
{
    @flock($lock, LOCK_UN);
    @fclose($lock);
    @unlink($path . '.lock');
}


/**
 * The derivative a request asks for: from the cache, or made now.
 *
 * Statuses:
 *   derivative  'path' is the derivative to send.
 *   original    send the original as usual. Nothing better exists for this
 *               address: not a raster image, already as small as asked, a
 *               derivative would be heavier than the original, the engine
 *               could not read it, or an earlier attempt died part way
 *               (<name>.try left behind). Remembered with an empty
 *               <name>.skip file, so the same bytes are not decoded again for
 *               the same answer.
 *   busy        send the original, but not for keeping under this address:
 *               another request is making the derivative right now, or the
 *               cache folder cannot be written. The next request may do better.
 *
 * The caller decides beforehand that the visitor may have the file at all;
 * nothing here looks at who is asking.
 *
 * @param int    $file_id     files.id, the name of the file's cache folder
 * @param string $source_path the original on disk
 * @param array  $request     pg_image_derivative_request()
 * @param string $directory   pg_image_derivative_directory()
 * @param array|null $settings pg_image_delivery_settings(); null for the defaults
 * @return array status, path, extension, width
 */
function pg_image_derivative($file_id, $source_path, $request, $directory, $settings = null)
{
    $original = array('status' => 'original', 'path' => $source_path, 'extension' => '', 'width' => 0);

    $busy = $original;
    $busy['status'] = 'busy';

    $file_id = (int) $file_id;

    if (($file_id < 1) || !is_array($request) || ((string) $directory === '')) {
        return $original;
    }

    $source_extension = strtolower((string) pathinfo($source_path, PATHINFO_EXTENSION));

    if (!pg_image_derivative_source_ok($source_extension)) {
        return $original;
    }

    $mtime = @filemtime($source_path);
    $size = @filesize($source_path);

    if (($mtime === false) || ($size === false) || ($size < 1)) {
        return $original;
    }

    list($source_width, $source_height) = pg_image_oriented_size($source_path);

    if (($source_width < 1) || ($source_height < 1)) {
        return $original;
    }

    $width = pg_image_derivative_width(isset($request['width']) ? $request['width'] : 0, $source_width);
    $resize = ($width < $source_width);

    // The formats this server really writes are only looked up when one is
    // asked for: a width alone keeps the source's format. A format the
    // operator switched off is answered like one the server cannot write,
    // so a page published before the switch still gets a picture.
    $requested_format = isset($request['format']) ? (string) $request['format'] : '';

    $format = pg_image_derivative_format(
        $requested_format,
        $source_extension,
        ($requested_format !== '') ? pg_image_derivative_offered(pg_image_derivative_formats($directory), $settings) : array());

    // The original already is what was asked for.
    if (!$resize && ($format === '')) {
        return $original;
    }

    $extension = ($format !== '') ? $format : $source_extension;
    $quality = pg_image_derivative_quality($extension, $settings);
    $name = pg_image_derivative_file_name($width, pg_image_derivative_signature($mtime, $size, $quality), $extension);
    $folder = rtrim((string) $directory, '/\\') . '/' . $file_id;
    $path = $folder . '/' . $name;

    $made = array('status' => 'derivative', 'path' => $path, 'extension' => $extension, 'width' => $width);

    if (@is_file($path) && (@filesize($path) > 0)) {
        return $made;
    }

    if (@is_file($path . '.skip')) {
        return $original;
    }

    // Checked before anything is decoded: a folder that cannot be written
    // would otherwise cost a full decode on every request and keep nothing.
    if (!@is_dir($folder) && !@mkdir($folder, 0755, true) && !@is_dir($folder)) {
        return $busy;
    }

    if (!@is_writable($folder)) {
        return $busy;
    }

    // One request makes a derivative. A page full of visitors asking for the
    // same new picture at once is otherwise the same picture decoded once per
    // visitor; the others are sent the original meanwhile.
    $lock = @fopen($path . '.lock', 'c');

    if (!$lock) {
        return $busy;
    }

    if (!@flock($lock, LOCK_EX | LOCK_NB)) {
        @fclose($lock);
        return $busy;
    }

    // Made by the request that held the lock a moment ago.
    if (@is_file($path) && (@filesize($path) > 0)) {
        pg_image_derivative_unlock($lock, $path);
        return $made;
    }

    // An attempt that started and never finished: the process died while
    // making it (out of memory inside the library, or stopped for time). Left
    // to itself every request for this address would die the same way and
    // the visitor would see a broken image, so a picture gets one try and the
    // original is the answer from then on.
    if (@is_file($path . '.try')) {
        @unlink($path . '.try');
        pg_image_derivative_put($path . '.skip', '');
        pg_image_derivative_prune($folder, $width, $extension, $name);
        pg_image_derivative_unlock($lock, $path);
        return $original;
    }

    @touch($path . '.try');

    // A visitor who leaves the page must not leave the work half done, and a
    // large picture can take longer than an ordinary request is given.
    @ignore_user_abort(true);

    if (function_exists('set_time_limit')) {
        @set_time_limit(120);
    }

    $report = pg_process_image_file($source_path, array(
        'max_dimension' => $resize ? pg_image_derivative_long_edge($width, $source_width, $source_height) : 0,
        'format'        => $format,
        'quality'       => $quality,
        'write'         => false));

    @unlink($path . '.try');

    $data = ($report['status'] === 'success') ? (string) $report['data'] : '';

    // The original is the answer when the engine could not read the picture
    // (animated GIF, too large, broken), and when the derivative would weigh
    // as much as the original or more: a flat-colour palette PNG grows when it
    // is scaled or turned into WebP, and the point of a derivative is fewer
    // bytes. Both depend on the source's bytes only, so they are remembered.
    // A missing library is the server's state instead, which can change while
    // the file does not, so that is not. Nor is a colour-profile refusal: it
    // depends on which library writes the format, and it is checked before any
    // decode, so repeating it is cheap.
    if (($data === '') || (strlen($data) >= $size)) {

        if (!in_array($report['reason'], array('no_library', 'no_webp', 'no_avif', 'color_profile'), true)) {
            pg_image_derivative_put($path . '.skip', '');
            pg_image_derivative_prune($folder, $width, $extension, $name);
        }

        pg_image_derivative_unlock($lock, $path);

        return $original;
    }

    if (!pg_image_derivative_put($path, $data)) {
        pg_image_derivative_unlock($lock, $path);
        return $busy;
    }

    pg_image_derivative_prune($folder, $width, $extension, $name);
    pg_image_derivative_unlock($lock, $path);

    return $made;
}
