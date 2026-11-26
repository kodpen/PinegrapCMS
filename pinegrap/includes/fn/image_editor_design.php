<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Artifact Canvas's design file on the server: a .pptx sent from this
 * computer, read as pages (pg_pptx_design_read()) and mapped into canvas
 * documents (pg_pptx_canvas_slide()); the pages asked for get their pictures
 * taken out of the package, checked as a picture from this computer and
 * kept as files of the site. The editor reaches it as the action design_file
 * (pg_image_editor_api()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The most pages one open turns into documents with their pictures.
define('PG_IMAGE_EDITOR_DESIGN_MAX_PAGES', 100);

// The most pictures one open keeps as files; the rest are left out and
// counted.
define('PG_IMAGE_EDITOR_DESIGN_MAX_PICTURES', 300);

// The largest picture part taken out of the package, and the most pixels
// one may hold to be cut or flipped.
define('PG_IMAGE_EDITOR_DESIGN_MAX_PICTURE', 32 * 1024 * 1024);
define('PG_IMAGE_EDITOR_DESIGN_MAX_PIXELS', 50 * 1000 * 1000);

/**
 * The pages a request asks for. Pure.
 *
 * @param mixed $value the request's pages
 * @param int   $count the file's pages
 * @return array|null|false null: none asked (the file is only read); the
 *                          page numbers sorted, once each; false when it is
 *                          no such list
 */
function pg_image_editor_design_pages($value, $count)
{
    if ($value === null) {
        return null;
    }

    if (!is_array($value) || !$value) {
        return false;
    }

    $pages = array();

    foreach ($value as $n) {
        if (!is_int($n) || ($n < 1) || ($n > (int) $count)) {
            return false;
        }

        $pages[$n] = $n;
    }

    if (count($pages) > PG_IMAGE_EDITOR_DESIGN_MAX_PAGES) {
        return false;
    }

    ksort($pages);

    return array_values($pages);
}

/**
 * A design file told by its first bytes: a ZIP package, as every .pptx is;
 * the reader tells a presentation from another package. Pure.
 *
 * @param string $head
 * @return string 'pptx' or ''
 */
function pg_image_editor_design_format($head)
{
    return (strncmp((string) $head, "PK\x03\x04", 4) === 0) ? 'pptx' : '';
}

// The refusal of a file that is no design file, with how one is made.
function pg_image_editor_design_not_pptx()
{
    return lang('This is not a .pptx file. In Canva: Share, Download, then PPTX.');
}

/**
 * A picture's type told by its bytes. Pure.
 *
 * @param string $bytes
 * @return string png, jpg, gif, webp or '' (SVG, EMF and others are left out)
 */
function pg_image_editor_design_picture_type($bytes)
{
    $bytes = (string) $bytes;

    if (strncmp($bytes, "\x89PNG\r\n\x1a\n", 8) === 0) {
        return 'png';
    }

    if (strncmp($bytes, "\xff\xd8\xff", 3) === 0) {
        return 'jpg';
    }

    if ((strncmp($bytes, 'GIF87a', 6) === 0) || (strncmp($bytes, 'GIF89a', 6) === 0)) {
        return 'gif';
    }

    return ((strncmp($bytes, 'RIFF', 4) === 0) && (substr($bytes, 8, 4) === 'WEBP')) ? 'webp' : '';
}

/**
 * The pixels a picture's crop keeps. Pure.
 *
 * @param int   $width
 * @param int   $height
 * @param array $crop shares cut on each side (l, t, r, b), 0 or more
 * @return array x, y, w, h in whole pixels, at least one each way
 */
function pg_image_editor_design_cut_box($width, $height, $crop)
{
    $width = max(1, (int) $width);
    $height = max(1, (int) $height);
    $x = min($width - 1, max(0, (int) round($width * (float) ($crop['l'] ?? 0))));
    $y = min($height - 1, max(0, (int) round($height * (float) ($crop['t'] ?? 0))));
    $right = min($width, max($x + 1, (int) round($width * (1 - (float) ($crop['r'] ?? 0)))));
    $bottom = min($height, max($y + 1, (int) round($height * (1 - (float) ($crop['b'] ?? 0)))));

    return array($x, $y, $right - $x, $bottom - $y);
}

/**
 * Whether a picture's pixels are drawn again before they are kept: a type
 * other than PNG or JPEG (as a picture from this computer becomes a PNG), a
 * cut or a flip. Pure.
 *
 * @param string $type    pg_image_editor_design_picture_type()
 * @param array  $picture pg_pptx_canvas_slide()'s: crop, flip_h, flip_v
 * @return bool
 */
function pg_image_editor_design_redrawn($type, $picture)
{
    if (($type !== 'png') && ($type !== 'jpg')) {
        return true;
    }

    foreach (array('l', 't', 'r', 'b') as $side) {
        if ((float) ($picture['crop'][$side] ?? 0) > 0) {
            return true;
        }
    }

    return !empty($picture['flip_h']) || !empty($picture['flip_v']);
}

/**
 * A picture's pixels cut and flipped with GD: a JPEG stays a JPEG, any
 * other type becomes a PNG with its transparency.
 *
 * @param string $bytes
 * @param string $type    pg_image_editor_design_picture_type()
 * @param array  $picture crop, flip_h, flip_v
 * @return array|null array(bytes, 'png'|'jpg'); null when GD cannot read or
 *                    write it
 */
function pg_image_editor_design_redraw($bytes, $type, $picture)
{
    if (!function_exists('imagecreatefromstring')) {
        return null;
    }

    $image = @imagecreatefromstring((string) $bytes);

    if (!$image) {
        return null;
    }

    list($x, $y, $w, $h) = pg_image_editor_design_cut_box(imagesx($image), imagesy($image), isset($picture['crop']) ? $picture['crop'] : array());
    $cut = imagecreatetruecolor($w, $h);
    imagealphablending($cut, false);
    imagesavealpha($cut, true);
    imagefill($cut, 0, 0, imagecolorallocatealpha($cut, 0, 0, 0, 127));
    imagecopy($cut, $image, 0, 0, $x, $y, $w, $h);
    imagedestroy($image);

    if (!empty($picture['flip_h']) && !empty($picture['flip_v'])) {
        imageflip($cut, IMG_FLIP_BOTH);
    } elseif (!empty($picture['flip_h'])) {
        imageflip($cut, IMG_FLIP_HORIZONTAL);
    } elseif (!empty($picture['flip_v'])) {
        imageflip($cut, IMG_FLIP_VERTICAL);
    }

    $out = ($type === 'jpg') ? 'jpg' : 'png';
    ob_start();
    $written = ($out === 'jpg') ? imagejpeg($cut, null, 92) : imagepng($cut);
    $result = (string) ob_get_clean();
    imagedestroy($cut);

    return ($written && ($result !== '')) ? array($result, $out) : null;
}

/**
 * A picture of a page taken out of the package, through the checks of a
 * picture from this computer: within the part's bound, a PNG, JPEG, GIF or
 * WebP by its bytes that reads as a picture, cut and flipped as the file
 * shows it.
 *
 * @param ZipArchive $zip
 * @param array      $picture pg_pptx_canvas_slide()'s: part, crop, flip_h, flip_v
 * @return array|null array(bytes, 'png'|'jpg'); null when it is left out
 */
function pg_image_editor_design_picture_bytes($zip, $picture)
{
    $bytes = pg_zip_entry_contents($zip, (string) $picture['part'], PG_IMAGE_EDITOR_DESIGN_MAX_PICTURE, $unread);
    $type = ($bytes === null) ? '' : pg_image_editor_design_picture_type($bytes);
    $size = ($type === '') ? false : @getimagesizefromstring($bytes);

    if (!$size || ((int) $size[0] < 1) || ((int) $size[1] < 1)) {
        return null;
    }

    if (!pg_image_editor_design_redrawn($type, $picture)) {
        return array($bytes, $type);
    }

    if ((int) $size[0] * (int) $size[1] > PG_IMAGE_EDITOR_DESIGN_MAX_PIXELS) {
        return null;
    }

    return pg_image_editor_design_redraw($bytes, $type, $picture);
}

/**
 * A page's picture layers pointed at the files their pictures became (src,
 * fileId); a layer whose picture was left out is taken out. Pure.
 *
 * @param array $doc   pg_pptx_canvas_slide()'s document, changed
 * @param array $saved layer id => {id, url}
 * @return int the picture layers taken out
 */
function pg_image_editor_design_point(&$doc, $saved)
{
    $layers = array();
    $lost = 0;

    foreach ($doc['layers'] as $layer) {
        if (($layer['type'] === 'image') && (strncmp((string) ($layer['src'] ?? ''), 'pptx:', 5) === 0)) {
            if (!isset($saved[$layer['id']])) {
                $lost++;
                continue;
            }

            $layer['src'] = $saved[$layer['id']]['url'];
            $layer['fileId'] = (int) $saved[$layer['id']]['id'];
        }

        $layers[] = $layer;
    }

    $doc['layers'] = $layers;

    return $lost;
}

/**
 * A design file read into pages: every page, or those asked for, each a
 * canvas document with the pictures its caller keeps and its report. Writes
 * nothing.
 *
 * @param string     $path
 * @param array|null $pages page numbers, null for all
 * @return array error ('' read), count, width, height, truncated, pages
 *               [{n, hidden, doc, pictures, report}], report (the pages'
 *               added up)
 */
function pg_image_editor_design_read($path, $pages)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/office_pptx_canvas.php');

    $out = array('error' => '', 'count' => 0, 'width' => 0, 'height' => 0, 'truncated' => false, 'pages' => array(), 'report' => pg_pptx_canvas_report(array()));
    $read = pg_pptx_design_read($path);

    if (!$read['ok']) {
        $out['error'] = ($read['error'] !== '') ? $read['error'] : lang('The file could not be read.');

        return $out;
    }

    $options = array('fonts' => pg_pptx_canvas_catalogue());
    $reports = array();

    foreach ($read['slides'] as $slide) {
        if (($pages !== null) && !in_array((int) $slide['n'], $pages, true)) {
            continue;
        }

        $mapped = pg_pptx_canvas_slide($read, $slide, $options);
        $out['pages'][] = array('n' => (int) $slide['n'], 'hidden' => !empty($slide['hidden']), 'doc' => $mapped['doc'], 'pictures' => $mapped['pictures'], 'report' => $mapped['report']);
        $reports[] = $mapped['report'];
    }

    $first = $out['pages'] ? $out['pages'][0]['doc']['artboard'] : array('width' => 0, 'height' => 0);
    $out['count'] = count($read['slides']);
    $out['width'] = (int) $first['width'];
    $out['height'] = (int) $first['height'];
    $out['truncated'] = !empty($read['truncated']);
    $out['report'] = pg_pptx_canvas_report($reports);

    return $out;
}

/**
 * A page's pictures kept as files in $folder, as a picture from this
 * computer is kept on a save (pg_image_editor_pictures_saved()): the site's
 * upload cap, a unique name after the design file, its own files row. One
 * part with the same cut and flips is kept once over the pages.
 *
 * @param ZipArchive $zip
 * @param array      $pictures layer id => pg_pptx_canvas_slide()'s picture
 * @param int        $folder
 * @param array      $user
 * @param string     $stem   the design file's name without its extension
 * @param array      $kept   part key => {id, url}, shared by the pages
 * @param int        $room   pictures still to be kept in this open
 * @return array layer id => {id, url}
 */
function pg_image_editor_design_keep($zip, $pictures, $folder, $user, $stem, &$kept, &$room)
{
    $saved = array();

    foreach ($pictures as $layer_id => $picture) {
        $key = json_encode(array($picture['part'], $picture['crop'], !empty($picture['flip_h']), !empty($picture['flip_v'])));

        if (isset($kept[$key])) {
            $saved[$layer_id] = $kept[$key];
            continue;
        }

        if ($room <= 0) {
            continue;
        }

        $taken = pg_image_editor_design_picture_bytes($zip, $picture);

        if ($taken === null) {
            continue;
        }

        $name = get_unique_name(array('name' => prepare_file_name((($stem !== '') ? $stem : lang('Picture')) . '.' . $taken[1]), 'type' => 'file'));

        if (pg_upload_name_blocked($name)) {
            continue;
        }

        $path = FILE_DIRECTORY_PATH . '/' . $name;
        $temporary = $path . '.' . (function_exists('getmypid') ? getmypid() : uniqid()) . '.tmp';

        if (@file_put_contents($temporary, $taken[0]) !== strlen($taken[0])) {
            @unlink($temporary);
            continue;
        }

        unset($taken[0]);

        // The site's upload cap, as a File Manager upload has it.
        $settings = pg_image_settings();

        if (!pg_image_upload_cap_apply($temporary, $settings['upload_max_dimension'], $settings['resize_quality'])) {
            pg_process_image_file($temporary);
        }

        if (!@rename($temporary, $path)) {
            @unlink($temporary);
            continue;
        }

        $row = pg_image_editor_record('new', $name, $folder, $taken[1], null, $user);

        if ($row[0]['status'] === 'success') {
            $kept[$key] = array('id' => (int) $row[0]['id'], 'url' => PATH . encode_url_path($name));
            $saved[$layer_id] = $kept[$key];
            $room--;
        }
    }

    return $saved;
}

/**
 * The design file of a request, kept in the large files' folder under an
 * id of kind design so its pages are opened without sending it again: the
 * bytes after the request's line or a finished upload in parts (upload), or
 * one kept before (design).
 *
 * @return array array(refusal|null, id, path)
 */
function pg_image_editor_design_package_of($request, $user)
{
    $kept = isset($request['design']) ? $request['design'] : '';

    if ($kept !== '') {
        list($refusal, $meta, $part, $received) = pg_image_editor_upload_load($kept, $user);

        if (!$refusal && (($meta['kind'] !== 'design') || ($received !== $meta['size']))) {
            $refusal = pg_image_editor_refusal(lang('This upload is no longer on the server. Pick the file again to start over.'), 410, 'upload_gone');
        }

        return $refusal ? array($refusal, '', '') : array(null, $kept, $part);
    }

    list($refusal, $head, $size, $id) = pg_image_editor_upload_take($request, $user, 'design');

    if ($refusal) {
        return array($refusal, '', '');
    }

    if (pg_image_editor_design_format($head) === '') {
        pg_image_editor_upload_drop($id);

        return array(pg_image_editor_refusal(pg_image_editor_design_not_pptx()), '', '');
    }

    if ($id !== '') {
        list($part) = pg_image_editor_upload_paths($id);

        return array(null, $id, $part);
    }

    $dir = pg_image_editor_upload_dir();

    if ((!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) || !pg_dir_writable($dir)) {
        return array(pg_image_editor_refusal(lang('Sorry, the file could not be saved.')), '', '');
    }

    pg_image_editor_upload_sweep();
    $id = bin2hex(random_bytes(16));
    list($part, $json) = pg_image_editor_upload_paths($id);

    if ((@file_put_contents($part, $head) !== $size) || (@file_put_contents($json, json_encode(array('user' => (int) $user['id'], 'kind' => 'design', 'size' => $size))) === false)) {
        pg_image_editor_upload_drop($id);

        return array(pg_image_editor_refusal(lang('Sorry, the file could not be saved.')), '', '');
    }

    return array(null, $id, $part);
}

/**
 * The folder a design file's pictures land in: the open canvas's, or the
 * one a canvas not saved yet is made in, under the folder's edit right and
 * never in the Recycle Bin.
 *
 * @return array array(refusal|null, folder id)
 */
function pg_image_editor_design_folder($request, $user)
{
    $file_id = isset($request['file_id']) ? (int) $request['file_id'] : 0;

    if ($file_id > 0) {
        $file = pg_image_editor_file($file_id, $user);

        if ($file['refusal']) {
            return array($file['refusal'], 0);
        }

        $folder = (int) $file['row']['folder'];
    } else {
        $folder = isset($request['folder_id']) ? (int) $request['folder_id'] : 0;
    }

    if ($folder <= 0) {
        return array(pg_image_editor_refusal(lang('A design file is opened into Files: save this image in the File Manager first.')), 0);
    }

    if (!check_edit_access($folder) || pg_folder_binned($folder)) {
        return array(pg_image_editor_refusal(lang('Access denied'), 403), 0);
    }

    return array(null, $folder);
}

/**
 * Action design_file: a .pptx from this computer read into pages. Without
 * pages it is only read: every page's document and report, the pictures
 * still parts of the package, and the file kept a day under its id. With
 * pages the pages asked for come back with their pictures kept as files in
 * the canvas's folder and their layers pointed at them; a picture that
 * cannot be kept is left out and counted as left (picture). The kept file
 * goes once its pages are opened.
 *
 *   { name?, pages?: [n], design? | upload?, file_id | folder_id } + bytes?
 *       -> { status, design, name, count, width, height, truncated,
 *            pages: [{n, hidden, doc, report}], report }
 *
 * @return array array(payload, HTTP status)
 */
function pg_image_editor_action_design_file($request, $user)
{
    $asked = isset($request['pages']) ? $request['pages'] : null;
    $folder = 0;

    // The folder is checked before anything is read or kept.
    if ($asked !== null) {
        list($refusal, $folder) = pg_image_editor_design_folder($request, $user);

        if ($refusal) {
            return $refusal;
        }
    }

    list($refusal, $id, $path) = pg_image_editor_design_package_of($request, $user);

    if ($refusal) {
        return $refusal;
    }

    @set_time_limit(300);

    // A file with no page the reader finds is read whole for its refusal.
    $count = pg_image_editor_design_read_count($path);
    $pages = ($count > 0) ? pg_image_editor_design_pages($asked, $count) : null;

    if ($pages === false) {
        return pg_image_editor_refusal(lang('Invalid request.'));
    }

    $read = pg_image_editor_design_read($path, $pages);

    if ($read['error'] !== '') {
        pg_image_editor_upload_drop($id);

        return pg_image_editor_refusal($read['error']);
    }

    $name = pg_basename(str_replace('\\', '/', isset($request['name']) ? (string) $request['name'] : ''));

    if ($pages !== null) {
        $zip = pg_office_open($path, $error);

        if ($zip === null) {
            pg_image_editor_upload_drop($id);

            return pg_image_editor_refusal($error);
        }

        $kept = array();
        $room = PG_IMAGE_EDITOR_DESIGN_MAX_PICTURES;
        $stem = trim(pg_file_name_stem($name));
        $reports = array();

        foreach ($read['pages'] as $index => $page) {
            $saved = pg_image_editor_design_keep($zip, $page['pictures'], $folder, $user, ($stem !== '') ? $stem . ' ' . $page['n'] : '', $kept, $room);
            $lost = pg_image_editor_design_point($read['pages'][$index]['doc'], $saved);

            if ($lost > 0) {
                $report = &$read['pages'][$index]['report'];
                $report['pictures'] -= $lost;
                $report['left']['picture'] = ($report['left']['picture'] ?? 0) + $lost;
                ksort($report['left']);
                unset($report);
            }

            $reports[] = $read['pages'][$index]['report'];
        }

        $zip->close();
        pg_image_editor_upload_drop($id);
        $read['report'] = pg_pptx_canvas_report($reports);
    }

    $out = array();

    foreach ($read['pages'] as $page) {
        $out[] = array('n' => $page['n'], 'hidden' => $page['hidden'], 'doc' => $page['doc'], 'report' => $page['report']);
    }

    return array(array(
        'status'    => 'success',
        'design'    => ($pages === null) ? $id : '',
        'name'      => $name,
        'count'     => $read['count'],
        'width'     => $read['width'],
        'height'    => $read['height'],
        'truncated' => $read['truncated'],
        'pages'     => $out,
        'report'    => $read['report'],
    ), 200);
}

/**
 * How many pages a design file has, for the check of the pages asked for:
 * its slides as the preview finds them, at most PG_OFFICE_MAX_SLIDES; 0 when
 * it cannot be read (the reader then gives the refusal).
 *
 * @param string $path
 * @return int
 */
function pg_image_editor_design_read_count($path)
{
    $zip = pg_office_open($path, $error);

    if ($zip === null) {
        return 0;
    }

    $parts = pg_office_pptx_slides($zip, $refused, $found);
    $zip->close();

    return (($refused === '') && $found) ? min(PG_OFFICE_MAX_SLIDES, count($parts)) : 0;
}
