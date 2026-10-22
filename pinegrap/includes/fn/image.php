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
 * Whether the config row carries the image delivery columns (2026.11.0,
 * 11.0.9): which modern formats are offered and the quality of each.
 *
 * Probed apart from pg_image_settings_ready(): an install that has the
 * 2026.4.4 columns and not these keeps its image limits, and the delivery
 * part of the card is hidden until the upgrade has run.
 *
 * @param bool $recheck skip the static cache
 * @return bool
 */
function pg_image_delivery_ready($recheck = false)
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
            'image_derivative_formats',
            'image_quality_webp',
            'image_quality_avif')");

    $cached = ($result && @mysqli_num_rows($result) == 3);

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
        'resize_quality'        => 82,
        'derivative_formats'    => 3,
        'quality_webp'          => 80,
        'quality_avif'          => 50);

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

    // How derivatives are delivered (includes/image_engine.php). Clamped by
    // the same function get_file.php uses on its own read of the row, so the
    // markup and the files it asks for never disagree.
    if (pg_image_delivery_ready($recheck)) {

        $row = db_item(
            "SELECT
                image_derivative_formats,
                image_quality_webp,
                image_quality_avif
            FROM config
            LIMIT 1");

        if ($row) {
            $settings['derivative_formats'] = (int) $row['image_derivative_formats'];
            $settings['quality_webp']       = (int) $row['image_quality_webp'];
            $settings['quality_avif']       = (int) $row['image_quality_avif'];
        }
    }

    $delivery = pg_image_delivery_settings(array(
        'image_derivative_formats' => $settings['derivative_formats'],
        'image_quality_webp'       => $settings['quality_webp'],
        'image_quality_avif'       => $settings['quality_avif'],
        'image_resize_quality'     => $settings['resize_quality']));

    $settings['derivative_formats'] = ($delivery['formats']['webp'] ? 1 : 0) | ($delivery['formats']['avif'] ? 2 : 0);
    $settings['quality_webp']       = $delivery['quality']['webp'];
    $settings['quality_avif']       = $delivery['quality']['avif'];
    $settings['delivery']           = $delivery;

    $cached = $settings;

    return $cached;
}


/**
 * The delivery settings of derivatives (pg_image_delivery_settings()) as the
 * operator set them: formats offered, quality of each.
 *
 * @return array formats, quality
 */
function pg_image_delivery()
{
    $settings = pg_image_settings();

    return $settings['delivery'];
}


/**
 * The modern formats the responsive markup offers: written by this server
 * and switched on by the operator.
 *
 * @return array webp, avif => bool
 */
function pg_image_delivery_formats()
{
    return pg_image_derivative_offered(pg_image_derivative_formats(pg_image_derivative_directory()), pg_image_delivery());
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


/* ---------------------------------------------------------------------------
 * Responsive <img> markup
 *
 * An image the visual designer places is written with the derivatives
 * get_file.php makes (includes/image_engine.php): a srcset of the fixed widths
 * in the file's own format on the <img>, and, for the designer's Image
 * element, AVIF and WebP <source>s in a <picture> around it. The browser picks
 * the smallest file that fills the slot, in the best format it reads.
 *
 * Only a file of this site's file manager is described: its address is read
 * back to a files row and its pixel width from the file. Anything else (an
 * external picture, a bound ^^token^^, an SVG, a GIF, an animated PNG or
 * WebP) is left as it was.
 *
 * The page's first image is fetched first (fetchpriority="high") and every
 * later one waits until it is near the viewport (loading="lazy"), unless the
 * node says how it loads. That part only runs while a page body is written
 * (generate_style_code_from_tree()), where "first" means first on the page.
 * ------------------------------------------------------------------------- */

/**
 * The file manager file an image address points at, or '' for anything else.
 *
 * Accepted: the name relative to the site ("photo.jpg", "{path}photo.jpg"),
 * under the site's path ("/photo.jpg", "/site/photo.jpg"), and the same as a
 * full address on one of this site's host names. A file has no folder in its
 * address, so a second path segment is somewhere else. A query string is
 * allowed (a cache buster), a fragment is ignored.
 *
 * @param string $src   the address as the node holds it
 * @param string $path  the site's path, PATH ('/' or '/site/')
 * @param array  $hosts the site's host names, lower case
 * @return string the file name, decoded
 */
function pg_img_local_file_name($src, $path, $hosts)
{
    $src = trim((string) $src);

    // A token, a template placeholder or anything that could not be a plain
    // address.
    if (($src === '') || preg_match('/\^\^|\{\{|[<>"\s,\\\\]/', $src)) {
        return '';
    }

    $hash = strpos($src, '#');

    if ($hash !== false) {
        $src = substr($src, 0, $hash);
    }

    $question = strpos($src, '?');
    $address = ($question !== false) ? substr($src, 0, $question) : $src;

    $path = '/' . trim((string) $path, '/') . '/';
    $path = ($path === '//') ? '/' : $path;

    if (stripos($address, '{path}') === 0) {
        $address = $path . substr($address, 6);
    }

    if (preg_match('#^(?:https?:)?//([^/]+)(/.*)?$#i', $address, $match)) {

        $host = strtolower(preg_replace('/:\d+$/', '', $match[1]));

        if (!in_array($host, $hosts, true)) {
            return '';
        }

        $address = isset($match[2]) ? $match[2] : '/';

    } elseif (preg_match('#^[a-z][a-z0-9+.-]*:#i', $address)) {
        // data:, mailto: and the like.
        return '';
    }

    if (substr($address, 0, 1) === '/') {

        if (strpos($address, $path) !== 0) {
            return '';
        }

        $address = substr($address, strlen($path));
    }

    if (($address === '') || (strpos($address, '/') !== false)) {
        return '';
    }

    return rawurldecode($address);
}


/**
 * One derivative's address: the image's own address with w and f added.
 *
 * @param string $src          the image's address, without a fragment
 * @param int    $width        the derivative's width
 * @param int    $source_width the file's own width; at it, no w is asked
 * @param string $format       webp, avif or '' for the file's own format
 * @return string
 */
function pg_img_derivative_url($src, $width, $source_width, $format)
{
    $query = array();

    if ((int) $width < (int) $source_width) {
        $query[] = 'w=' . (int) $width;
    }

    if ($format !== '') {
        $query[] = 'f=' . $format;
    }

    if (!$query) {
        return $src;
    }

    return $src . ((strpos($src, '?') === false) ? '?' : '&') . implode('&', $query);
}


/**
 * The srcset of an image in its own format and of each <source>.
 *
 * Every fixed width below the file's own is a candidate, and the file itself
 * at its full width closes the <img>'s list: never larger than what was there
 * before. A <source> stops at the widest fixed width, because a format change
 * of a 6000 px photo is a long encode for a picture no screen shows whole; a
 * file at most that wide is offered whole in the new format.
 *
 * A format is offered only when this server writes it and the operator has
 * it switched on (pg_image_delivery_formats()), and the file is not in it
 * already.
 *
 * @param string $src              the image's address, without a fragment
 * @param int    $source_width     the file's displayed width
 * @param string $source_extension lower case
 * @param array  $formats          webp, avif => bool
 * @return array srcset ('' when the file is no wider than the narrowest
 *               width), sources (MIME type => srcset, AVIF first), width
 */
function pg_img_responsive_sets($src, $source_width, $source_extension, $formats)
{
    $source_width = (int) $source_width;
    $widths = array();

    foreach (pg_image_derivative_widths() as $width) {
        if ($width < $source_width) {
            $widths[] = $width;
        }
    }

    $sets = array('srcset' => '', 'sources' => array(), 'width' => $source_width);

    if ($source_width < 1) {
        return $sets;
    }

    if ($widths) {
        $own = array();

        foreach ($widths as $width) {
            $own[] = pg_img_derivative_url($src, $width, $source_width, '') . ' ' . $width . 'w';
        }

        $own[] = $src . ' ' . $source_width . 'w';
        $sets['srcset'] = implode(', ', $own);
    }

    $widest = max(pg_image_derivative_widths());

    foreach (array('avif' => 'image/avif', 'webp' => 'image/webp') as $format => $type) {

        if (empty($formats[$format]) || ($format === $source_extension)) {
            continue;
        }

        $candidates = array();

        foreach ($widths as $width) {
            $candidates[] = pg_img_derivative_url($src, $width, $source_width, $format) . ' ' . $width . 'w';
        }

        if ($source_width <= $widest) {
            $candidates[] = pg_img_derivative_url($src, $source_width, $source_width, $format) . ' ' . $source_width . 'w';
        }

        $sets['sources'][$type] = implode(', ', $candidates);
    }

    return $sets;
}


/**
 * The sizes attribute: how wide the image is drawn.
 *
 * A width set on the node in pixels is the answer. Without one the image is
 * at most as wide as the window and at most as wide as the file: the browser
 * picks a file no larger than the one it fetched before.
 *
 * It also keeps the layout as it was. With w descriptors the browser lays out
 * an image that has no CSS width at the size sizes names, not at the file's
 * own size; naming the file's width as the ceiling keeps that size wherever
 * the window is wider than the file.
 *
 * auto (the width the layout gives the image, where the browser knows it)
 * comes first only for a lazy image whose CSS width fills its box
 * (pg_img_fills_width()): there the layout does not depend on the file, and
 * a catalog card 300 px wide is sent a 480 px file instead of one as wide as
 * the window. Without a CSS width Chrome lays an auto-sized image out at
 * 300 px, so it is never used there.
 *
 * @param string $width         the node's width ('48', '48px', '50%', '')
 * @param int    $natural_width the file's displayed width
 * @param bool   $auto          lazy, and its CSS width fills its box
 * @return string
 */
function pg_img_sizes($width, $natural_width, $auto = false)
{
    $width = trim((string) $width);

    if (preg_match('/^([0-9]{1,5})(?:px)?$/i', $width, $match) && ((int) $match[1] > 0)) {
        return ((int) $match[1]) . 'px';
    }

    $natural_width = (int) $natural_width;
    $sizes = ($natural_width < 1) ? '100vw' : '(max-width: ' . $natural_width . 'px) 100vw, ' . $natural_width . 'px';

    return $auto ? 'auto, ' . $sizes : $sizes;
}


/**
 * How a designer image's width or height prop is written. A whole number is
 * the width / height attribute: it is in pixels and keeps the image's layout
 * box before the file arrives. Anything else is CSS with its own unit
 * ('50%', '12rem', '120px', 'auto'); a bare decimal is taken as pixels
 * (pg_designer_css_length()), so a style never carries a number without a
 * unit (a browser drops that).
 * A prop the editor takes as false (the number 0, false, no value) is no
 * size; the string '0' is one (pg_designer_prop_text()).
 * The editor writes the same (_sdImgDim() in style_designer.js).
 *
 * @param mixed $value the node's width or height prop
 * @return array{attr: string, css: string} at most one of them is not ''
 */
function pg_img_dimension($value)
{
    $value = pg_designer_prop_text($value);

    if ($value === '') {
        return array('attr' => '', 'css' => '');
    }

    if (preg_match('/^[0-9]+$/', $value)) {
        return array('attr' => $value, 'css' => '');
    }

    return array('attr' => '', 'css' => pg_designer_css_length($value));
}


/**
 * Whether an image's classes give it a CSS width that fills its box:
 * Bootstrap's w-100 and the card image classes (width: 100%). img-fluid is
 * only a max-width, and the image's own size still decides below it.
 *
 * @param string $class the class attribute
 * @return bool
 */
function pg_img_fills_width($class)
{
    return (bool) preg_match('/(?:^|\s)(?:w-100|card-img|card-img-top|card-img-bottom)(?:\s|$)/', (string) $class);
}


/**
 * The file manager file an image address points at, when it is one that can
 * have derivatives: a JPEG, PNG or WebP that is not an ERP document. '' for
 * anything else. Host names and path come from this request.
 *
 * @param string $src the address as written, without a fragment
 * @return string
 */
function pg_img_file_name($src)
{
    $hosts = array();

    foreach (array('HOSTNAME_SETTING', 'HOSTNAME') as $constant) {
        if (defined($constant) && (constant($constant) !== '')) {
            $hosts[] = strtolower(preg_replace('/:\d+$/', '', (string) constant($constant)));
        }
    }

    $name = pg_img_local_file_name($src, defined('PATH') ? PATH : '/', array_values(array_unique($hosts)));

    if ($name === '') {
        return '';
    }

    $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));

    // ERP documents and attachments get no derivatives (get_file.php); a GIF
    // may be animated, which the engine leaves as it is. An animated PNG or
    // WebP is told by its bytes in pg_img_responsive().
    if (!in_array($extension, array('jpg', 'jpeg', 'png', 'webp'), true) || (strncasecmp($name, 'erp-', 4) === 0)) {
        return '';
    }

    return $name;
}


/**
 * The files rows of these names, read with one query for the names not
 * asked about before in this request: name => row, or false when there is
 * no such file.
 *
 * @param array $names
 * @return array
 */
function pg_img_file_rows($names)
{
    static $rows = array();

    $connected = class_exists('db', false) && isset(db::$con) && db::$con;
    $missing = array();

    foreach ($names as $name) {
        if (!array_key_exists($name, $rows)) {
            $rows[$name] = false;

            if ($connected) {
                $missing[] = "'" . e($name) . "'";
            }
        }
    }

    if ($missing) {
        foreach (array_chunk($missing, 200) as $chunk) {
            $found = db_items("SELECT id, name, attachment FROM files WHERE name IN (" . implode(', ', $chunk) . ")");

            foreach ((array) $found as $row) {
                if (array_key_exists($row['name'], $rows)) {
                    $rows[$row['name']] = $row;
                }
            }
        }
    }

    $out = array();

    foreach ($names as $name) {
        $out[$name] = $rows[$name];
    }

    return $out;
}


/**
 * Read the files rows of many image addresses at once, so the
 * pg_img_responsive() calls that follow ask the database nothing more: a
 * catalog page of 24 products is one query, not 24.
 *
 * @param array $srcs addresses as written
 * @return void
 */
function pg_img_prefetch($srcs)
{
    $names = array();

    foreach ($srcs as $src) {
        $name = pg_img_file_name(preg_replace('/#.*$/s', '', trim((string) $src)));

        if ($name !== '') {
            $names[$name] = true;
        }
    }

    if ($names) {
        pg_img_file_rows(array_keys($names));
    }
}


/**
 * The responsive sets of an image address, read from the file it points at:
 * null when it is not a file of this site that can have derivatives.
 *
 * One files row (pg_img_file_rows(), shared with pg_img_prefetch()) and one
 * header read per address per request.
 *
 * @param string $src the address as the node holds it
 * @return array|null srcset, sources, width (see pg_img_responsive_sets())
 */
function pg_img_responsive($src)
{
    static $cache = array();

    $src = trim((string) $src);
    $hash = strpos($src, '#');

    if ($hash !== false) {
        $src = substr($src, 0, $hash);
    }

    if (array_key_exists($src, $cache)) {
        return $cache[$src];
    }

    $cache[$src] = null;

    if (!defined('FILE_DIRECTORY_PATH') || !class_exists('db', false) || !isset(db::$con) || !db::$con) {
        return null;
    }

    $name = pg_img_file_name($src);

    if ($name === '') {
        return null;
    }

    $rows = pg_img_file_rows(array($name));
    $row = $rows[$name];

    if (!$row || !empty($row['attachment'])) {
        return null;
    }

    // An APNG or animated WebP: every derivative would be one still frame
    // (the engine refuses to make them), and a <source> or srcset would send
    // the browser after it instead of the animation.
    if (pg_image_is_animated(FILE_DIRECTORY_PATH . '/' . $name)) {
        return null;
    }

    list($width, $height) = pg_image_oriented_size(FILE_DIRECTORY_PATH . '/' . $name);

    if (($width < 1) || ($height < 1)) {
        return null;
    }

    $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
    $sets = pg_img_responsive_sets($src, $width, $extension, pg_image_delivery_formats());

    if (($sets['srcset'] === '') && !$sets['sources']) {
        return null;
    }

    $cache[$src] = $sets;

    return $sets;
}


/**
 * The <picture> around an <img> that has <source>s.
 *
 * display:contents takes the <picture> out of the layout: the <img> is laid
 * out as the child of the element around it, as it was before, so flex and
 * grid placement, widths and Bootstrap's utilities on the <img> act as
 * before. Inside a .ratio box (inside=true) the <picture> is the box's child
 * instead, and the <img> must carry w-100 h-100 to fill it.
 *
 * @param string $img     the finished <img> tag
 * @param array  $sources MIME type => srcset
 * @param string $sizes
 * @param string $pad     indentation
 * @param bool   $inside  the <picture> is the child of a .ratio box
 * @return string
 */
function pg_img_picture($img, $sources, $sizes, $pad = '', $inside = false)
{
    $html = '<picture data-pg-picture' . ($inside ? '' : ' style="display:contents"') . '>' . "\n";

    foreach ($sources as $type => $srcset) {
        $html .= $pad . '    <source type="' . h($type) . '" srcset="' . h($srcset) . '" sizes="' . h($sizes) . '">' . "\n";
    }

    return $html . $pad . '    ' . $img . "\n" . $pad . '</picture>';
}


/**
 * The page-wide state of the loading rule: whether a page body is being
 * written, and whether its first image has been met.
 *
 * @return array
 */
function &pg_img_page_state()
{
    static $state = array('active' => false, 'first_seen' => false);

    return $state;
}


/**
 * How an image loads: the attributes to add to its <img>.
 *
 * The node's own choice wins (the multi-select Options "Loading attribute",
 * or a loading attribute written in the Attributes panel, which is printed
 * there and needs nothing here). Otherwise, while a page body is written, the
 * page's first image gets fetchpriority="high" and every later one
 * loading="lazy" and decoding="async". An attribute the node already carries
 * is never written twice.
 *
 * @param string $chosen  props.loading
 * @param array  $written attribute names the node's Attributes panel carries,
 *                        lower case
 * @return array name => value, in order
 */
function pg_img_loading_attrs($chosen, $written)
{
    $state = &pg_img_page_state();

    $first = false;

    if ($state['active'] && !$state['first_seen']) {
        $state['first_seen'] = true;
        $first = true;
    }

    $attrs = array();
    $chosen = strtolower(trim((string) $chosen));

    if (in_array('loading', $written, true)) {
        return $attrs;
    }

    if (($chosen === 'lazy') || ($chosen === 'eager')) {
        $attrs['loading'] = $chosen;
        return $attrs;
    }

    if (!$state['active']) {
        return $attrs;
    }

    if ($first) {
        if (!in_array('fetchpriority', $written, true)) {
            $attrs['fetchpriority'] = 'high';
        }

        return $attrs;
    }

    $attrs['loading'] = 'lazy';

    if (!in_array('decoding', $written, true)) {
        $attrs['decoding'] = 'async';
    }

    return $attrs;
}


/**
 * The attributes of an attribute list as one string: ' a="1" b="2"'.
 *
 * @param array $attrs name => value
 * @return string
 */
function pg_img_attr_string($attrs)
{
    $out = '';

    foreach ($attrs as $name => $value) {
        $out .= ' ' . $name . '="' . h($value) . '"';
    }

    return $out;
}


/**
 * One attribute of an HTML tag, entities decoded; '' when it is not there.
 *
 * @param string $tag  the tag as written
 * @param string $name
 * @return string
 */
function pg_img_tag_attr($tag, $name)
{
    if (!preg_match('~\s' . preg_quote($name, '~') . '\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'>]+))~i', $tag, $match)) {
        return '';
    }

    $value = isset($match[3]) ? $match[3] : (isset($match[2]) && $match[2] !== '' ? $match[2] : $match[1]);

    return html_entity_decode($value, ENT_QUOTES, 'UTF-8');
}


/**
 * Give every <img> of a piece of HTML that shows a file of this site its
 * srcset and sizes, in the file's own format.
 *
 * For HTML no renderer of the designer writes image by image: a system
 * widget's records (catalog cards, a product's photos, a cart line), a
 * carousel's slides, an image inside a text or a custom HTML block. The tag
 * keeps everything it has and gains two attributes; no <picture> is put
 * around it, because scripts swap these images (the variant chooser) and
 * CSS may expect the <img> where it is. An <img> that already has a srcset,
 * a ^^token^^ or an address elsewhere is left as it is.
 *
 * @param string   $html
 * @param callable $resolve address => sets or null; pg_img_responsive()
 * @return string
 */
function pg_img_responsive_html($html, $resolve = 'pg_img_responsive')
{
    if (!is_string($html) || (stripos($html, '<img') === false) || !preg_match_all('~<img\b[^>]*>~i', $html, $tags)) {
        return $html;
    }

    $srcs = array();

    foreach ($tags[0] as $tag) {
        if (!preg_match('~\ssrcset\s*=~i', $tag)) {
            $srcs[] = pg_img_tag_attr($tag, 'src');
        }
    }

    pg_img_prefetch($srcs);

    return preg_replace_callback('~<img\b[^>]*>~i', function ($match) use ($resolve) {

        $tag = $match[0];

        // Already described, or a tag whose > sits inside an attribute value
        // (the match ended early): left as written.
        if (preg_match('~\ssrcset\s*=~i', $tag) || (substr_count($tag, '"') % 2 !== 0) || (substr_count($tag, "'") % 2 !== 0)) {
            return $tag;
        }

        $sets = call_user_func($resolve, pg_img_tag_attr($tag, 'src'));

        if (!$sets || ($sets['srcset'] === '')) {
            return $tag;
        }

        $add = ' srcset="' . h($sets['srcset']) . '"';

        if (!preg_match('~\ssizes\s*=~i', $tag)) {
            $auto = (strtolower(pg_img_tag_attr($tag, 'loading')) === 'lazy') && pg_img_fills_width(pg_img_tag_attr($tag, 'class'));
            $add .= ' sizes="' . h(pg_img_sizes(pg_img_tag_attr($tag, 'width'), $sets['width'], $auto)) . '"';
        }

        // Before the closing > or />, keeping the space a self-closing tag
        // has in front of its slash.
        $close = (substr($tag, -2) === '/>') ? 2 : 1;
        $body = substr($tag, 0, -$close);
        $kept = rtrim($body);

        return $kept . $add . substr($body, strlen($kept)) . substr($tag, -$close);

    }, $html);
}


/**
 * The page HTML with the responsive markup taken out, for an e-mail.
 *
 * Mail programs do not choose between candidates the way a browser does, and
 * the e-mail pass makes only src addresses absolute: a <source> or a srcset
 * would send a reader's mail program after a relative address. The <img> in a
 * <picture> written here stays, and every srcset and sizes goes.
 *
 * @param string $html
 * @return string
 */
function pg_img_strip_responsive($html)
{
    if ((stripos($html, 'srcset') === false) && (stripos($html, 'data-pg-picture') === false)) {
        return $html;
    }

    $html = preg_replace_callback('~<picture\b[^>]*\bdata-pg-picture\b[^>]*>(.*?)</picture>~is', function ($match) {
        return trim(preg_replace('~\s*<source\b[^>]*>~i', '', $match[1]));
    }, $html);

    return preg_replace_callback('~<img\b[^>]*>~i', function ($match) {
        return preg_replace('~\s(?:srcset|sizes)\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)~i', '', $match[0]);
    }, $html);
}


/* ---------------------------------------------------------------------------
 * Derivative cache upkeep
 *
 * A derivative is replaced on its own when its source changes (the signature
 * in its name, includes/image_engine.php), but the old file of a width nobody
 * asks for again, and the folder of a file that was deleted, stay on the
 * disk. A daily pass of the general job takes them away
 * (pg_image_derivative_sweep_run()); it reads the files table, so every
 * door that deletes a file is covered without a hook in each. Nothing is
 * deleted for being old: a current derivative of an existing file is the
 * cache working, and there are at most a dozen per file.
 * ------------------------------------------------------------------------- */

/**
 * Which entries of one file's derivative folder are no longer of use.
 *
 * A derivative or a .skip marker whose signature is not the one its source
 * has now (the source was optimized, rotated or replaced), every entry when
 * the source is gone, a .lock or temporary file older than an hour (its
 * request died), and a .try marker of an old signature. Names this software
 * does not write are left alone.
 *
 * The signature carries the encoding quality, so a quality changed in the
 * settings makes every derivative of that format stale. So does a format
 * switched off: nothing asks for it any more (get_file.php answers such a
 * request in another format). A resized copy in the source's own format is
 * not a conversion and stays.
 *
 * @param array      $entries  name => modification time
 * @param array|null $source   mtime, size and extension (lower case) of the
 *                             file now; null when it is gone
 * @param int        $now
 * @param array|null $settings pg_image_delivery_settings(); null for the defaults
 * @return array names
 */
function pg_image_derivative_stale_entries($entries, $source, $now, $settings = null)
{
    if (!is_array($settings) || !isset($settings['formats'], $settings['quality'])) {
        $settings = pg_image_delivery_settings(null);
    }

    $source_extension = (is_array($source) && isset($source['extension'])) ? strtolower((string) $source['extension']) : '';

    $stale = array();

    foreach ($entries as $name => $mtime) {

        if ($source === null) {
            $stale[] = $name;
            continue;
        }

        if (!preg_match('/^(\d+)-([0-9a-f]{12})\.([a-z]+)(\.skip|\.try|\.lock|\.tmp[0-9a-z]*)?$/', $name, $match)) {
            continue;
        }

        $suffix = isset($match[4]) ? $match[4] : '';

        if (($suffix === '.lock') || (strpos($suffix, '.tmp') === 0)) {
            if (($now - (int) $mtime) > 3600) {
                $stale[] = $name;
            }
            continue;
        }

        $extension = $match[3];

        if (isset($settings['formats'][$extension]) && empty($settings['formats'][$extension]) && ($extension !== $source_extension)) {
            $stale[] = $name;
            continue;
        }

        $current = pg_image_derivative_signature($source['mtime'], $source['size'], pg_image_derivative_quality($extension, $settings));

        if ($match[2] !== $current) {
            $stale[] = $name;
        }
    }

    return $stale;
}


/**
 * Delete a folder and everything in it, until a deadline.
 *
 * @param string $path
 * @param float  $deadline microtime(true) to stop at
 * @return array finished (bool), files, bytes
 */
function pg_image_derivative_remove_tree($path, $deadline)
{
    $result = array('finished' => true, 'files' => 0, 'bytes' => 0);
    $entries = @scandir($path);

    if (!is_array($entries)) {
        return $result;
    }

    foreach ($entries as $entry) {

        if (($entry === '.') || ($entry === '..')) {
            continue;
        }

        if (microtime(true) > $deadline) {
            $result['finished'] = false;
            return $result;
        }

        $item = $path . '/' . $entry;

        if (is_dir($item) && !is_link($item)) {
            $inner = pg_image_derivative_remove_tree($item, $deadline);
            $result['files'] += $inner['files'];
            $result['bytes'] += $inner['bytes'];

            if (!$inner['finished']) {
                $result['finished'] = false;
                return $result;
            }

            continue;
        }

        $bytes = (int) @filesize($item);

        if (@unlink($item)) {
            $result['files']++;
            $result['bytes'] += $bytes;
        }
    }

    @rmdir($path);

    return $result;
}


/**
 * Empty the derivative cache, for the cache purge.
 *
 * The folder is renamed out of the way first, which is instant whatever it
 * holds, so the next request starts from an empty cache (and tests the
 * formats again, formats.json going with it). What can be deleted in two
 * seconds is deleted now; the rest of the renamed folder goes with the next
 * pass of the general job.
 *
 * @param string $directory pg_image_derivative_directory()
 * @return bool whether there was a cache to empty
 */
function pg_image_derivative_purge($directory)
{
    $directory = rtrim((string) $directory, '/\\');

    if (($directory === '') || !is_dir($directory)) {
        return false;
    }

    $aside = $directory . '.old-' . time() . '-' . (function_exists('getmypid') ? getmypid() : mt_rand());

    if (!@rename($directory, $aside)) {
        return false;
    }

    pg_image_derivative_remove_tree($aside, microtime(true) + 2);

    return true;
}


/**
 * Where the daily pass stands: last_id (the file id it got to, 0 between
 * passes) and done_at (when the last pass finished).
 *
 * @param string $directory
 * @return array
 */
function pg_image_derivative_sweep_state($directory)
{
    $state = @json_decode((string) @file_get_contents(rtrim((string) $directory, '/\\') . '/sweep.json'), true);

    return array(
        'last_id' => (is_array($state) && isset($state['last_id'])) ? (int) $state['last_id'] : 0,
        'done_at' => (is_array($state) && isset($state['done_at'])) ? (int) $state['done_at'] : 0,
    );
}


/**
 * The folders a purge set aside next to the cache, still to be deleted.
 *
 * @param string $directory
 * @return array paths
 */
function pg_image_derivative_set_aside($directory)
{
    $directory = rtrim((string) $directory, '/\\');
    $parent = dirname($directory);
    $prefix = basename($directory) . '.old-';
    $found = array();

    foreach ((array) @scandir($parent) as $entry) {
        if ((strpos((string) $entry, $prefix) === 0) && is_dir($parent . '/' . $entry)) {
            $found[] = $parent . '/' . $entry;
        }
    }

    return $found;
}


/**
 * Whether the general job should run a pass now: a pass under way, a purged
 * cache still to delete, or a day since the last pass finished.
 *
 * @param string $directory
 * @return bool
 */
function pg_image_derivative_sweep_due($directory)
{
    if ($directory === '') {
        return false;
    }

    if (pg_image_derivative_set_aside($directory)) {
        return true;
    }

    if (!is_dir($directory)) {
        return false;
    }

    $state = pg_image_derivative_sweep_state($directory);

    return ($state['last_id'] > 0) || ($state['done_at'] < (time() - 86400));
}


/**
 * One pass over the derivative cache, for as many seconds as it is given:
 * what a purge set aside, then the folder of every file id in turn. A pass
 * that runs out of time remembers the id it got to and goes on from there on
 * the next tick.
 *
 * @param string $directory pg_image_derivative_directory()
 * @param int    $seconds
 * @return array state ('done' or 'more'), folders, files, bytes
 */
function pg_image_derivative_sweep_run($directory, $seconds = 10)
{
    $directory = rtrim((string) $directory, '/\\');
    $deadline = microtime(true) + max(1, (int) $seconds);
    $result = array('state' => 'done', 'folders' => 0, 'files' => 0, 'bytes' => 0);

    foreach (pg_image_derivative_set_aside($directory) as $aside) {

        $removed = pg_image_derivative_remove_tree($aside, $deadline);
        $result['files'] += $removed['files'];
        $result['bytes'] += $removed['bytes'];

        if (!$removed['finished']) {
            $result['state'] = 'more';
            return $result;
        }
    }

    if (!is_dir($directory) || !defined('FILE_DIRECTORY_PATH')) {
        return $result;
    }

    $state = pg_image_derivative_sweep_state($directory);
    $delivery = pg_image_delivery();
    $ids = array();

    foreach ((array) @scandir($directory) as $entry) {

        $entry = (string) $entry;

        if (preg_match('/^[1-9][0-9]{0,9}$/', $entry) && ((int) $entry > $state['last_id']) && is_dir($directory . '/' . $entry)) {
            $ids[] = (int) $entry;

        // The source of a format test whose request died
        // (pg_image_format_probe()).
        } elseif (preg_match('/^probe-[0-9a-z]+\.png$/', $entry) && ((time() - (int) @filemtime($directory . '/' . $entry)) > 3600)) {
            @unlink($directory . '/' . $entry);
        }
    }

    sort($ids);

    foreach (array_chunk($ids, 200) as $chunk) {

        $names = array();

        foreach ((array) db_items("SELECT id, name FROM files WHERE id IN (" . implode(',', $chunk) . ")") as $row) {
            $names[(int) $row['id']] = (string) $row['name'];
        }

        foreach ($chunk as $id) {

            if (microtime(true) > $deadline) {
                $result['state'] = 'more';
                @file_put_contents($directory . '/sweep.json', json_encode(array('last_id' => $state['last_id'], 'done_at' => $state['done_at'])));
                return $result;
            }

            $folder = $directory . '/' . $id;
            $source = null;

            if (isset($names[$id])) {
                $path = FILE_DIRECTORY_PATH . '/' . $names[$id];
                $mtime = @filemtime($path);
                $size = @filesize($path);

                if (($mtime !== false) && ($size !== false)) {
                    $source = array('mtime' => $mtime, 'size' => $size, 'extension' => strtolower((string) pathinfo($names[$id], PATHINFO_EXTENSION)));
                }
            }

            $entries = array();

            foreach ((array) @scandir($folder) as $entry) {
                if (($entry !== '.') && ($entry !== '..') && is_file($folder . '/' . $entry)) {
                    $entries[$entry] = (int) @filemtime($folder . '/' . $entry);
                }
            }

            foreach (pg_image_derivative_stale_entries($entries, $source, time(), $delivery) as $entry) {
                $bytes = (int) @filesize($folder . '/' . $entry);

                if (@unlink($folder . '/' . $entry)) {
                    $result['files']++;
                    $result['bytes'] += $bytes;
                }
            }

            // Empty now: the file is gone, or none of its derivatives was current.
            if (@rmdir($folder)) {
                $result['folders']++;
            }

            $state['last_id'] = $id;
        }
    }

    @file_put_contents($directory . '/sweep.json', json_encode(array('last_id' => 0, 'done_at' => time())));

    return $result;
}
