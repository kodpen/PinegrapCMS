<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A presentation's slides for the preview's Slides tab: each slide mapped
 * into an Artifact Canvas document (pg_image_editor_design_read()), which
 * the browser draws with the editor's painter, and one line telling what
 * the drawing leaves out. A picture stays named by its part (src pptx:...);
 * the preview asks for one slide's pictures at a time through its own door
 * (explorer_office_preview / ws_file_office with slide), read with the
 * preview's view rights, cut and flipped as the file shows them and
 * answered as data: addresses. Nothing becomes a file of the site and
 * nothing is kept on the server. Loaded where used, not by functions.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The most bytes of pictures one slide's answer carries; a picture past it
// is left out and counted, and the slide is drawn without it.
define('PG_OFFICE_PPTX_SLIDE_PICTURES', 24 * 1024 * 1024);

/**
 * The slides of a presentation as documents for the painter: the board's
 * size, each slide's number, hidden mark and document, the summed report and
 * its line. Null when the design reader refuses the file: the preview then
 * shows the text list alone.
 *
 * @param string $path
 * @return array|null width, height, count, truncated, pages [{n, hidden,
 *                    doc}], report, left (pg_office_pptx_left_line())
 */
function pg_office_pptx_slides_drawn($path)
{
    $read = pg_image_editor_design_read($path, null);

    if (($read['error'] !== '') || !$read['pages']) {
        return null;
    }

    $pages = array();

    foreach ($read['pages'] as $page) {
        $pages[] = array('n' => $page['n'], 'hidden' => $page['hidden'], 'doc' => $page['doc']);
    }

    return array(
        'width'     => $read['width'],
        'height'    => $read['height'],
        'count'     => $read['count'],
        'truncated' => $read['truncated'],
        'pages'     => $pages,
        'report'    => $read['report'],
        'left'      => pg_office_pptx_left_line($read['report']),
    );
}

/**
 * The quiet line over the slide: what the mapper's report counts as not
 * drawn, the fonts replaced and the elements simplified; '' when nothing.
 * Pure.
 *
 * @param array $report pg_pptx_canvas_report()'s
 * @return string
 */
function pg_office_pptx_left_line($report)
{
    $labels = array(
        'chart'    => lang('Charts'),
        'table'    => lang('Tables'),
        'diagram'  => lang('Diagrams (SmartArt)'),
        'object'   => lang('Embedded objects'),
        'media'    => lang('Video and sound'),
        'ink'      => lang('Ink drawings'),
        'gradient' => lang('Gradient fills'),
        'pattern'  => lang('Pattern fills'),
        'picture'  => lang('Pictures that could not be read'),
        'mask'     => lang('Picture masks'),
    );
    $kinds = array();
    $other = 0;

    foreach ((array) ($report['left'] ?? array()) as $kind => $count) {
        if ((int) $count < 1) {
            continue;
        }

        if (isset($labels[$kind])) {
            $kinds[] = str_replace(array('{1}', '{2}'), array($labels[$kind], (string) (int) $count), lang('{1} ({2})'));
        } else {
            $other += (int) $count;
        }
    }

    if ($other > 0) {
        $kinds[] = str_replace(array('{1}', '{2}'), array(lang('Other elements'), (string) $other), lang('{1} ({2})'));
    }

    $parts = array();

    if ($kinds) {
        $parts[] = str_replace('{1}', implode(', ', $kinds), lang('Not drawn: {1}.'));
    }

    $fonts = count((array) ($report['fonts'] ?? array()));

    if ($fonts > 0) {
        $parts[] = str_replace('{1}', (string) $fonts, lang('{1} font(s) replaced.'));
    }

    $simplified = (int) ($report['simplified'] ?? 0);

    if ($simplified > 0) {
        $parts[] = str_replace('{1}', (string) $simplified, lang('{1} element(s) simplified.'));
    }

    return $parts ? lang('Shown approximately.') . ' ' . implode(' ', $parts) : '';
}

/**
 * The pictures of one slide as data: addresses, by the layer that shows
 * each: taken out of the package and cut as the design file door cuts them
 * (pg_image_editor_design_picture_bytes()), within the answer's bound. A
 * part shown twice with the same cut is read once.
 *
 * @param string $path
 * @param mixed  $slide the slide's number, 1-based
 * @return array ok, error, pictures (layer id => data: address), lost
 */
function pg_office_pptx_slide_pictures($path, $slide)
{
    $n = (is_int($slide) || (is_string($slide) && ctype_digit($slide))) ? (int) $slide : 0;

    if (($n < 1) || ($n > PG_OFFICE_MAX_SLIDES)) {
        return array('ok' => false, 'error' => lang('Invalid request.'), 'pictures' => array(), 'lost' => 0);
    }

    $read = pg_image_editor_design_read($path, array($n));

    if ($read['error'] !== '') {
        return array('ok' => false, 'error' => $read['error'], 'pictures' => array(), 'lost' => 0);
    }

    if (!$read['pages']) {
        return array('ok' => false, 'error' => lang('Invalid request.'), 'pictures' => array(), 'lost' => 0);
    }

    $wanted = $read['pages'][0]['pictures'];

    if (!$wanted) {
        return array('ok' => true, 'error' => '', 'pictures' => array(), 'lost' => 0);
    }

    $zip = pg_office_open($path, $error);

    if ($zip === null) {
        return array('ok' => false, 'error' => $error, 'pictures' => array(), 'lost' => 0);
    }

    $pictures = array();
    $taken = array();
    $room = PG_OFFICE_PPTX_SLIDE_PICTURES;
    $lost = 0;

    foreach ($wanted as $layer => $picture) {
        $key = md5(serialize(array($picture['part'], $picture['crop'] ?? null, !empty($picture['flip_h']), !empty($picture['flip_v']))));

        if (!array_key_exists($key, $taken)) {
            $bytes = pg_image_editor_design_picture_bytes($zip, $picture);
            $taken[$key] = null;

            if ($bytes && (strlen($bytes[0]) <= $room)) {
                $room -= strlen($bytes[0]);
                $taken[$key] = 'data:' . (($bytes[1] === 'jpg') ? 'image/jpeg' : 'image/' . $bytes[1]) . ';base64,' . base64_encode($bytes[0]);
            }
        }

        if ($taken[$key] === null) {
            $lost++;
            continue;
        }

        $pictures[(string) $layer] = $taken[$key];
    }

    $zip->close();

    return array('ok' => true, 'error' => '', 'pictures' => $pictures, 'lost' => $lost);
}
