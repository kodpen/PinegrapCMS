<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Canva start of the New design window: no Canva connection and no
 * Canva call. The person exports the pages from Canva and adds the
 * pictures in the window; each is stored in the file manager under the
 * rules of an upload (one file a request, the server's single-file limit,
 * the type read from the bytes), and becomes the reference picture of one
 * sketch page of the new design. The colours most used in the pictures
 * are measured here and offered for the design's variables; a picture
 * carries no fonts.
 *
 * A PDF is refused with its reason: turning a PDF page into a picture
 * needs Ghostscript behind ImageMagick, which a host may not have and
 * which runs the PDF's own program; no part of the software does that.
 *
 * The pure parts (the type, a page's name, the colours of a set of
 * pixels, the colours of several pictures together) are tested in
 * tests/canva_start_test.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * The most pictures one design takes from Canva: the sketch keeps 50
 * pages, and twenty is a site's worth without a long wait in the window.
 */
function pg_canva_max_pages()
{
    return 20;
}

/**
 * How many colours a row of the colours card offers.
 */
function pg_canva_max_colors()
{
    return 5;
}

/**
 * The kind of a file from its first bytes, never from its name: 'png',
 * 'jpg', 'pdf' or '' for anything else. Pure.
 *
 * @param string $head at least the first 8 bytes
 * @return string
 */
function pg_canva_file_kind($head)
{
    $head = (string) $head;
    if (strncmp($head, "\x89PNG\r\n\x1a\n", 8) === 0) {
        return 'png';
    }
    if (strncmp($head, "\xFF\xD8\xFF", 3) === 0) {
        return 'jpg';
    }
    if (strncmp($head, '%PDF-', 5) === 0) {
        return 'pdf';
    }
    return '';
}

/**
 * The name a picture gives its sketch page: the file's name without its
 * extension, dashes and underscores read as spaces, at most 120
 * characters (the sketch's own cut); '' when nothing is left. Pure.
 *
 * @param string $file_name the name the browser sent
 * @return string
 */
function pg_canva_page_name($file_name)
{
    $stem = pg_file_name_stem((string) $file_name);
    $stem = trim(preg_replace('/[\s_-]+/u', ' ', $stem));
    if (function_exists('mb_substr')) {
        return mb_substr($stem, 0, 120, 'UTF-8');
    }
    return substr($stem, 0, 120);
}

/**
 * The colours a picture is made of, from its pixels: each pixel falls into
 * one of 4,096 boxes (four bits a channel), a box's colour is the mean of
 * its pixels. Near white, near black and greys are left out: they are the
 * page's ground and text, not its brand. Pure.
 *
 * @param array $pixels 0xRRGGBB integers
 * @return array list of array('hex' => '#rrggbb', 'w' => share of all the
 *               pixels, 0 to 1), most used first, at most 24
 */
function pg_canva_colors_from_pixels($pixels)
{
    $total = count($pixels);
    if ($total === 0) {
        return array();
    }
    $boxes = array();
    foreach ($pixels as $p) {
        $p = (int) $p;
        $r = ($p >> 16) & 255;
        $g = ($p >> 8) & 255;
        $b = $p & 255;
        $key = (($r >> 4) << 8) | (($g >> 4) << 4) | ($b >> 4);
        if (!isset($boxes[$key])) {
            $boxes[$key] = array(0, 0, 0, 0);
        }
        $boxes[$key][0]++;
        $boxes[$key][1] += $r;
        $boxes[$key][2] += $g;
        $boxes[$key][3] += $b;
    }
    $out = array();
    foreach ($boxes as $box) {
        $n = $box[0];
        $rgb = array((int) round($box[1] / $n), (int) round($box[2] / $n), (int) round($box[3] / $n));
        if (!pg_canva_color_is_brand($rgb)) {
            continue;
        }
        $out[] = array('hex' => sprintf('#%02x%02x%02x', $rgb[0], $rgb[1], $rgb[2]), 'n' => $n);
    }
    usort($out, function ($a, $b) {
        return ($b['n'] - $a['n']) ?: strcmp($a['hex'], $b['hex']);
    });
    $out = array_slice($out, 0, 24);
    foreach ($out as $i => $c) {
        $out[$i] = array('hex' => $c['hex'], 'w' => round($c['n'] / $total, 4));
    }
    return $out;
}

/**
 * Whether a colour can carry a brand: saturated enough and neither near
 * white nor near black (HSL saturation at least .25, lightness .12 to
 * .88). Pure.
 *
 * @param array $rgb three 0-255 integers
 * @return bool
 */
function pg_canva_color_is_brand($rgb)
{
    $max = max($rgb) / 255;
    $min = min($rgb) / 255;
    $l = ($max + $min) / 2;
    if ($l < 0.12 || $l > 0.88) {
        return false;
    }
    $d = $max - $min;
    $s = ($d == 0) ? 0 : $d / (1 - abs(2 * $l - 1));
    return $s >= 0.25;
}

/**
 * The colours of several pictures together, as the colours card offers
 * them: every picture counts the same whatever its size, colours closer
 * than a small distance join the more used one, the most used first. Pure.
 *
 * @param array $lists one list per picture, as pg_canva_colors_from_pixels()
 *                     gives it (anything else in it is skipped)
 * @param int   $max   how many colours at most
 * @return array list of '#rrggbb'
 */
function pg_canva_pick_colors($lists, $max = 5)
{
    $all = array();
    foreach ((array) $lists as $list) {
        foreach ((array) $list as $c) {
            if (!is_array($c) || !isset($c['hex'], $c['w']) || !is_string($c['hex']) || !preg_match('/^#[0-9a-f]{6}$/i', $c['hex'])) {
                continue;
            }
            $w = (float) $c['w'];
            if ($w > 0) {
                $all[] = array('hex' => strtolower($c['hex']), 'w' => min(1.0, $w));
            }
        }
    }
    usort($all, function ($a, $b) {
        return ($b['w'] <=> $a['w']) ?: strcmp($a['hex'], $b['hex']);
    });
    // Each colour joins the first group it is close to; a group keeps the
    // colour that started it, the most used of its members.
    $groups = array();
    foreach ($all as $c) {
        $rgb = pg_canva_rgb($c['hex']);
        $joined = false;
        foreach ($groups as $i => $g) {
            if (pg_canva_color_distance($rgb, $g['rgb']) < 64) {
                $groups[$i]['w'] += $c['w'];
                $joined = true;
                break;
            }
        }
        if (!$joined) {
            $groups[] = array('hex' => $c['hex'], 'rgb' => $rgb, 'w' => $c['w']);
        }
    }
    usort($groups, function ($a, $b) {
        return ($b['w'] <=> $a['w']) ?: strcmp($a['hex'], $b['hex']);
    });
    return array_map(function ($g) {
        return $g['hex'];
    }, array_slice($groups, 0, max(0, (int) $max)));
}

/**
 * '#rrggbb' as three integers. Pure.
 */
function pg_canva_rgb($hex)
{
    return array(hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2)));
}

/**
 * How far apart two colours look, the red and blue weighted by how red the
 * pair is ("redmean"); 0 to about 765. Pure.
 */
function pg_canva_color_distance($a, $b)
{
    $rm = ($a[0] + $b[0]) / 2;
    $dr = $a[0] - $b[0];
    $dg = $a[1] - $b[1];
    $db = $a[2] - $b[2];
    return sqrt((2 + $rm / 256) * $dr * $dr + 4 * $dg * $dg + (2 + (255 - $rm) / 256) * $db * $db);
}

/**
 * The colours of a stored picture: sampled down to 64 pixels wide (at most
 * 256 high) with GD and read with pg_canva_colors_from_pixels(); mostly
 * see-through pixels are skipped. An empty list when GD is missing, the
 * picture cannot be read or is too large to open in memory.
 *
 * @param string $path
 * @param string $kind   'png' or 'jpg'
 * @param int    $width  as uploaded, for the memory check before opening
 * @param int    $height as uploaded
 * @return array
 */
function pg_canva_measure($path, $kind, $width, $height)
{
    $open = ($kind === 'png') ? 'imagecreatefrompng' : 'imagecreatefromjpeg';
    if (!function_exists($open) || !function_exists('imagecreatetruecolor') || $width < 1 || $height < 1) {
        return array();
    }
    // GD holds four bytes a pixel and more; a picture over 40 megapixels
    // is stored without its colours rather than end the request.
    if ($width * $height > 40000000) {
        return array();
    }
    $src = @$open($path);
    if (!$src) {
        return array();
    }
    // The stored file may be smaller than the upload it came from (the
    // site's upload cap shrinks it): the picture's own size is sampled, so
    // no part of it falls outside the copy and reads as black.
    $width = imagesx($src);
    $height = imagesy($src);
    $w = 64;
    $h = max(1, min(256, (int) round($height * $w / $width)));
    $small = imagecreatetruecolor($w, $h);
    imagealphablending($small, false);
    imagesavealpha($small, true);
    // Nearest pixels, not resampled: a blend of two colours at an edge is
    // no colour of the picture.
    imagecopyresized($small, $src, 0, 0, 0, 0, $w, $h, $width, $height);
    pg_image_destroy($src);
    $pixels = array();
    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            $c = imagecolorat($small, $x, $y);
            if ((($c >> 24) & 127) > 96) {
                continue;
            }
            $pixels[] = $c & 0xFFFFFF;
        }
    }
    pg_image_destroy($small);
    return pg_canva_colors_from_pixels($pixels);
}

/**
 * The refusal of a file that is not a picture this start takes. Pure.
 *
 * @param string $kind pg_canva_file_kind()
 * @param string $name
 * @return string '' for a PNG or a JPG
 */
function pg_canva_kind_refusal($kind, $name)
{
    if ($kind === 'png' || $kind === 'jpg') {
        return '';
    }
    if ($kind === 'pdf') {
        return lang(array('string' => '{var:1} is a PDF, which this site cannot turn into pictures. Download the pages from Canva as PNG.', 'vars' => array($name)));
    }
    return lang(array('string' => '{var:1} is not a PNG. Download the page from Canva as PNG.', 'vars' => array($name)));
}

/**
 * The folder a Canva picture is stored in: the top folder of the file
 * manager, which a designer may always write to. 0 when there is none.
 *
 * @return int
 */
function pg_canva_folder()
{
    $top = (int) db_value("SELECT folder_id FROM folder WHERE folder_parent = '0' ORDER BY folder_id LIMIT 1");
    return ($top > 0 && check_edit_access($top)) ? $top : 0;
}

/**
 * One picture added in the Canva start (designer › canva_page): checked
 * as an upload, stored in the file manager and measured. The caller has
 * checked the token and that the person is a designer.
 *
 * @param mixed $file the $_FILES entry
 * @return array the JSON answer: status, and on success page (name, src,
 *               width, height, colors)
 */
function pg_canva_store_page($file)
{
    $refuse = function ($message) {
        return array('status' => 'error', 'message' => $message);
    };
    if (!is_array($file) || !isset($file['error']) || is_array($file['error']) || ((int) $file['error'] === UPLOAD_ERR_NO_FILE)) {
        return $refuse(lang('Please select a file.'));
    }
    $name = pg_basename((string) (isset($file['name']) ? $file['name'] : ''));
    $limits = pg_upload_limits();
    $refusal = pg_upload_error_message($name, $file['error'], $limits['file_max']);
    if ($refusal !== '') {
        return $refuse($refusal);
    }
    $path = (string) $file['tmp_name'];
    if ($path === '' || !is_uploaded_file($path)) {
        return $refuse(lang(array('string' => 'The server could not accept {var:1}. Please try again.', 'vars' => array($name))));
    }
    $kind = pg_canva_file_kind((string) @file_get_contents($path, false, null, 0, 8));
    $refusal = pg_canva_kind_refusal($kind, $name);
    if ($refusal !== '') {
        return $refuse($refusal);
    }
    $size = @getimagesize($path);
    if (!is_array($size) || (int) $size[0] < 1 || (int) $size[1] < 1) {
        return $refuse(lang(array('string' => '{var:1} could not be read as a picture.', 'vars' => array($name))));
    }
    $folder = pg_canva_folder();
    if ($folder <= 0) {
        return $refuse(lang('You do not have access to upload files to this folder.'));
    }
    // The stored name ends as the bytes are: a JPG sent as "page.png" is
    // stored as page.jpg.
    $stem = pg_file_name_stem($name);
    $stored = pg_file_store_upload($path, (($stem !== '') ? $stem : 'canva-page') . '.' . $kind, (int) @filesize($path), array(
        'folder'         => $folder,
        'description'    => lang('A Canva page, the model of a sketch page'),
        'design'         => null,
        'image_profile'  => '',
        'image_settings' => pg_image_settings(),
        'file_max'       => $limits['file_max']));
    if (!$stored['ok']) {
        log_activity($stored['message']);
        return $refuse($stored['message']);
    }
    $page_name = pg_canva_page_name($name);
    return array(
        'status' => 'success',
        'page'   => array(
            'name'   => ($page_name !== '') ? $page_name : lang('Page'),
            'file'   => $name,
            'src'    => PATH . encode_url_path($stored['file']['name']),
            'width'  => (int) $size[0],
            'height' => (int) $size[1],
            'colors' => pg_canva_measure(FILE_DIRECTORY_PATH . '/' . $stored['file']['name'], $kind, (int) $size[0], (int) $size[1]),
        ),
    );
}
