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
 * external picture, a bound ^^token^^, an SVG, a GIF) is left as it was.
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
 * A format is offered only when this server writes it
 * (pg_image_derivative_formats()) and the file is not in it already.
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
 * the window is wider than the file. (auto is not used for that reason:
 * Chrome lays an auto-sized image with no CSS width out at 300 px.)
 *
 * @param string $width         the node's width ('48', '48px', '50%', '')
 * @param int    $natural_width the file's displayed width
 * @return string
 */
function pg_img_sizes($width, $natural_width)
{
    $width = trim((string) $width);

    if (preg_match('/^([0-9]{1,5})(?:px)?$/i', $width, $match) && ((int) $match[1] > 0)) {
        return ((int) $match[1]) . 'px';
    }

    $natural_width = (int) $natural_width;

    if ($natural_width < 1) {
        return '100vw';
    }

    return '(max-width: ' . $natural_width . 'px) 100vw, ' . $natural_width . 'px';
}


/**
 * The responsive sets of an image address, read from the file it points at:
 * null when it is not a file of this site that can have derivatives.
 *
 * One files row and one header read per address per request.
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

    if (!defined('FILE_DIRECTORY_PATH') || !isset(db::$con) || !db::$con) {
        return null;
    }

    $hosts = array();

    foreach (array('HOSTNAME_SETTING', 'HOSTNAME') as $constant) {
        if (defined($constant) && (constant($constant) !== '')) {
            $hosts[] = strtolower(preg_replace('/:\d+$/', '', (string) constant($constant)));
        }
    }

    $name = pg_img_local_file_name($src, defined('PATH') ? PATH : '/', array_values(array_unique($hosts)));

    if ($name === '') {
        return null;
    }

    $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));

    // ERP documents and attachments get no derivatives (get_file.php); a GIF
    // may be animated, which the engine leaves as it is.
    if (!in_array($extension, array('jpg', 'jpeg', 'png', 'webp'), true) || (strncasecmp($name, 'erp-', 4) === 0)) {
        return null;
    }

    $row = db_item("SELECT id, attachment FROM files WHERE name = '" . e($name) . "' LIMIT 1");

    if (!$row || !empty($row['attachment'])) {
        return null;
    }

    list($width, $height) = pg_image_oriented_size(FILE_DIRECTORY_PATH . '/' . $name);

    if (($width < 1) || ($height < 1)) {
        return null;
    }

    $sets = pg_img_responsive_sets($src, $width, $extension, pg_image_derivative_formats(pg_image_derivative_directory()));

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
