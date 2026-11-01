<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The image editor's page side: the labels it shows, where its script and
 * stylesheet are, and the small inline loader behind window.pgImageEditor.
 *
 * A screen that may open the editor prints pg_image_editor_includes() once.
 * That costs one inline script: the editor's own files (the
 * pg_image_editor.min.js bundle and its stylesheet) are fetched the first
 * time pgImageEditor.open() is called, so a page where nobody edits a
 * picture never downloads them.
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
 * The parts of the editor that keep their own labels: the core, then each
 * tool beside its script (assets/js/pg_image_editor_<part>.js), in the
 * bundle's order. Each part's labels are pg_image_editor_strings_<part>(), at
 * the end of this file.
 *
 * @return array part names
 */
function pg_image_editor_parts()
{
    return array('core', 'crop', 'adjust', 'layers', 'draw', 'text', 'sticker', 'redact', 'versions', 'artboard', 'export', 'vector');
}

/**
 * Every label of the editor, by the key the script asks for. The script holds
 * no text of its own, so the panel's language reaches it through lang() here
 * and tools/check_lang.php sees each key. A key belongs to one part
 * (tests/image_editor_includes_test.php).
 *
 * @return array key => translated text
 */
function pg_image_editor_strings()
{
    $strings = array();

    foreach (pg_image_editor_parts() as $part) {
        $strings = array_merge($strings, call_user_func('pg_image_editor_strings_' . $part));
    }

    return $strings;
}

/**
 * The core's labels: the dialog, the top bar, the rail's tool names, the
 * view, saving, and the words the tools share (Reset, Apply, Width).
 *
 * @return array key => translated text
 */
function pg_image_editor_strings_core()
{
    return array(
        'dialog'         => lang('Image editor'),
        'newImage'       => lang('New image'),
        'close'          => lang('Close'),
        'undo'           => lang('Undo'),
        'redo'           => lang('Redo'),
        'save'           => lang('Save'),
        'saveCopy'       => lang('Save as a copy'),
        'moreSave'       => lang('More save options'),
        'saving'         => lang('Saving…'),
        'saveError'      => lang('The image could not be saved.'),
        'done'           => lang('Done'),
        'cancel'         => lang('Cancel'),
        'fullscreen'     => lang('Full screen'),
        'exitFullscreen' => lang('Exit full screen'),
        'zoomIn'         => lang('Zoom in'),
        'zoomOut'        => lang('Zoom out'),
        'zoomFit'        => lang('Fit to screen'),
        'zoomActual'     => lang('Actual size'),
        'tools'          => lang('Tools'),
        'layers'         => lang('Layers'),
        'closePanel'     => lang('Close the panel'),
        'customColor'    => lang('Custom color'),
        'colorTransparent' => lang('Transparent'),
        'colorWhite'     => lang('White'),
        'colorBlack'     => lang('Black color'),
        'colorRed'       => lang('Red'),
        'colorOrange'    => lang('Orange'),
        'colorYellow'    => lang('Yellow'),
        'colorGreen'     => lang('Green'),
        'colorBlue'      => lang('Blue'),
        'colorPurple'    => lang('Purple'),
        'loading'        => lang('Opening the image…'),
        'loadError'      => lang('The image could not be opened.'),
        'unsavedTitle'   => lang('Close without saving?'),
        'unsavedText'    => lang('Your changes to this image will be lost.'),
        'keepEditing'    => lang('Keep editing'),
        'discard'        => lang('Close without saving'),
        'hintView'       => lang('Hold Space or the middle button and drag to move the view. Ctrl and the wheel zoom.'),
        'tool_select'    => lang('Select'),
        'tool_crop'      => lang('Crop'),
        'tool_adjust'    => lang('Adjust'),
        'tool_draw'      => lang('Draw'),
        'tool_text'      => lang('Text'),
        'tool_redact'    => lang('Redact'),
        'tool_sticker'   => lang('Sticker'),
        'tool_frame'     => lang('Frame'),
        'tool_vector'    => lang('Vector'),
        'tool_artboard'  => lang('Canvas'),
        'tool_image'     => lang('Image'),
        'tool_export'    => lang('Export'),
        'tool_arrow'     => lang('Arrow'),
        'tool_line'      => lang('Line'),
        'tool_rect'      => lang('Rectangle'),
        'tool_ellipse'   => lang('Ellipse'),
        'tool_pen'       => lang('Pen'),
        'reset'          => lang('Reset'),
        'apply'          => lang('Apply'),
        'width'          => lang('Width'),
        'height'         => lang('Height'),
    );
}

/**
 * The editor's stylesheet: the minified copy when the site runs minified and
 * the copy is there, else the source (as pg_js_bundle_files() chooses for
 * scripts), versioned by its modification time.
 *
 * @return string URL
 */
function pg_image_editor_style_url()
{
    $minified = 'assets/css/pg_image_editor.min.css';
    $bundles = pg_css_bundles();
    $file = $minified;

    if ((defined('ENVIRONMENT_SUFFIX') && (ENVIRONMENT_SUFFIX !== 'min')) || !is_file(PG_FUNCTIONS_DIR . '/' . $minified)) {
        $file = $bundles[$minified];
    }

    return OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/' . $file . '?v=' . (int) @filemtime(PG_FUNCTIONS_DIR . '/' . $file);
}

function pg_image_editor_decimal()
{
    $separators = function_exists('pg_number_separators') ? pg_number_separators() : array();

    return isset($separators['decimal']) ? $separators['decimal'] : '.';
}

/**
 * What the loader and the editor read from window.pgImageEditorConfig.
 *
 * @return array
 */
function pg_image_editor_config()
{
    return array(
        'scripts' => pg_js_bundle_urls('assets/js/pg_image_editor.min.js'),
        'style'   => pg_image_editor_style_url(),
        'strings' => pg_image_editor_strings(),
        // The decimal separator of the panel's language, for the figures the
        // editor writes (docs/memory/ui/panel-script-number-format.md).
        'decimal' => pg_image_editor_decimal(),
        // Where it opens and saves files, and the session's token for it.
        'api'     => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/image_editor_api.php',
        'token'   => isset($_SESSION['software']['token']) ? (string) $_SESSION['software']['token'] : '',
        // Without Imagick the browser sends the colour step done.
        'serverColor' => class_exists('Imagick'),
    );
}

/**
 * The config and the loader, once per page. pgImageEditor.open(options)
 * loads the stylesheet and the scripts in order the first time, then opens
 * the editor and answers with its promise; pgImageEditor.load() only loads.
 * A plain inline script, so it also works in markup inserted later.
 *
 * @return string HTML
 */
function pg_image_editor_includes()
{
    static $printed = false;

    if ($printed) {
        return '';
    }

    $printed = true;

    return '<script>window.pgImageEditorConfig = ' . pg_code_editor_json(pg_image_editor_config()) . ';
(function () {
    var loading = null;
    function load() {
        if (window.PgImageEditor) { return Promise.resolve(window.PgImageEditor); }
        if (loading) { return loading; }
        var cfg = window.pgImageEditorConfig;
        loading = new Promise(function (resolve, reject) {
            var link = document.createElement("link");
            link.rel = "stylesheet";
            link.href = cfg.style;
            document.head.appendChild(link);
            var i = 0;
            (function next() {
                if (i >= cfg.scripts.length) { resolve(window.PgImageEditor); return; }
                var s = document.createElement("script");
                s.src = cfg.scripts[i++];
                s.onload = next;
                s.onerror = function () { loading = null; reject(new Error("image editor: " + s.src)); };
                document.head.appendChild(s);
            })();
        });
        return loading;
    }
    window.pgImageEditor = {
        load: load,
        open: function (options) { return load().then(function (E) { return E.open(options || {}); }); }
    };
    // The older call: a file by id or name is saved over (or copied when
    // allowReplace is false), saveUrl takes the edit somewhere else, and
    // onSaved gets the saved file.
    window.openImageEditor = function (o) {
        o = o || {};
        var options = { fileId: o.fileId, fileName: o.fileName, tools: o.tools, profile: o.profile, onClose: o.onCancel };
        if (o.saveUrl) {
            options.src = o.src;
            options.save = { mode: "url", url: o.saveUrl, label: o.saveLabel };
        } else {
            options.save = { mode: (o.allowReplace === false) ? "copy" : "replace" };
        }
        options.onSave = function (r) { if (typeof o.onSaved === "function") { o.onSaved(r.file || r); } };
        return window.pgImageEditor.open(options);
    };
    window.imageEditorAvailable = function () { return true; };
    // A button or link with data-pg-image-edit (the name of a file) opens the
    // file. A copy saved from a picture of a page is put in that content
    // (data-pg-image-object, -object-id, -column: action content); then the
    // page is drawn again, past the week the browser may keep a picture.
    document.addEventListener("click", function (e) {
        var b = e.target && e.target.closest ? e.target.closest("[data-pg-image-edit]") : null;
        if (!b) { return; }
        e.preventDefault();
        var cfg = window.pgImageEditorConfig, name = b.getAttribute("data-pg-image-edit");
        window.pgImageEditor.open({ fileName: name, save: { mode: "replace" }, onSave: function (r) {
            var f = r.file || {}, object = b.getAttribute("data-pg-image-object"), steps = [];
            if (f.url) {
                var bare = String(f.url).split("?")[0];
                [bare, decodeURI(bare)].forEach(function (u) { steps.push(fetch(u, { cache: "reload", credentials: "same-origin" }).catch(function () {})); });
            }
            if (object && (f.mode === "copy")) {
                steps.push(fetch(cfg.api, { method: "POST", credentials: "same-origin", headers: { "Content-Type": "application/json", Accept: "application/json" },
                    body: JSON.stringify({ token: cfg.token, action: "content", object_type: object, object_id: b.getAttribute("data-pg-image-object-id") || "",
                        column: b.getAttribute("data-pg-image-column") || "", from: name, to: f.id }) }).catch(function () {}));
            }
            Promise.all(steps).then(function () { window.location.reload(); });
        } });
    });
    // #pg-image-edit=<name>: the address image_editor_edit.php sends to.
    var asked = /^#pg-image-edit=(.+)$/.exec(window.location.hash || "");
    if (asked) {
        if (window.history.replaceState) { window.history.replaceState(null, "", window.location.pathname + window.location.search); }
        try { asked = decodeURIComponent(asked[1]); } catch (err) { asked = ""; }
        if (asked) { window.pgImageEditor.open({ fileName: asked, save: { mode: "replace" }, onSave: function () { window.location.reload(); } }); }
    }
})();</script>';
}

// ---------------------------------------------------------------------------
// The server side: image_editor_api.php
// ---------------------------------------------------------------------------

/**
 * A finite JSON number within [min, max], else null. A numeric string is
 * refused: the editor sends numbers.
 *
 * @return float|null
 */
function pg_image_edit_number($value, $min, $max)
{
    if (!is_int($value) && !is_float($value)) {
        return null;
    }

    $value = (float) $value;

    if (is_nan($value) || is_infinite($value) || ($value < $min) || ($value > $max)) {
        return null;
    }

    return $value;
}

// The adjustments and their ranges; 0 changes nothing (ADJUST of
// assets/js/pg_image_editor_model.js).
function pg_image_edit_adjust_ranges()
{
    return array(
        'brightness' => array(-100, 100), 'contrast' => array(-100, 100), 'saturation' => array(-100, 100),
        'exposure' => array(-100, 100), 'temperature' => array(-100, 100), 'gamma' => array(-100, 100),
        'vignette' => array(0, 100), 'sharpen' => array(0, 100));
}

// The filters: fixed values of the adjustments, added to the sliders'
// (FILTERS of the model).
function pg_image_edit_filters()
{
    return array(
        'none'   => array(),
        'mono'   => array('saturation' => -100),
        'sepia'  => array('saturation' => -100, 'temperature' => 60, 'contrast' => -8, 'brightness' => 4),
        'cool'   => array('temperature' => -35, 'saturation' => 8, 'brightness' => 2),
        'warm'   => array('temperature' => 35, 'saturation' => 15),
        'fade'   => array('contrast' => -28, 'brightness' => 8, 'saturation' => -30),
        'vivid'  => array('contrast' => 15, 'saturation' => 45),
        'noir'   => array('saturation' => -100, 'contrast' => 50, 'brightness' => -6),
        'chrome' => array('contrast' => 22, 'saturation' => 22, 'exposure' => 6));
}

// An affine colour map as rows R, G, B of [r, g, b, offset]; $a after $b.
function pg_image_edit_affine($a, $b)
{
    $out = array();

    foreach ($a as $row) {
        $line = array();

        for ($c = 0; $c < 4; $c++) {
            $line[] = $row[0] * $b[0][$c] + $row[1] * $b[1][$c] + $row[2] * $b[2][$c] + (($c === 3) ? $row[3] : 0);
        }

        $out[] = $line;
    }

    return $out;
}

/**
 * The colour step of the adjustment values: colorMatrix() of the model, so
 * the server draws what the preview's SVG filter shows. Exposure scales,
 * brightness lifts, contrast spreads around the middle, saturation moves from
 * or towards grey (Rec. 709 luma), temperature warms or cools, on sRGB values
 * from 0 to 1; then gamma.
 *
 * @return array|null matrix: 20 numbers, rows R, G, B, A of r, g, b, a,
 *                    offset (0 to 1); gamma: the exponent. null for none.
 */
function pg_image_edit_color($v)
{
    if (!$v['exposure'] && !$v['temperature'] && !$v['brightness'] && !$v['contrast'] && !$v['saturation'] && !$v['gamma']) {
        return null;
    }

    $e = pow(2, $v['exposure'] / 100);
    $t = $v['temperature'] / 100;
    $b = 0.25 * $v['brightness'] / 100;
    $c = pow(2, $v['contrast'] / 100);
    $s = 1 + $v['saturation'] / 100;
    $lr = 0.2126;
    $lg = 0.7152;
    $lb = 0.0722;
    $gains = function ($r, $g, $b, $offset) {
        return array(array($r, 0, 0, $offset), array(0, $g, 0, $offset), array(0, 0, $b, $offset));
    };

    $m = $gains($e, $e, $e, 0);
    $m = pg_image_edit_affine($gains(1, 1, 1, $b), $m);
    $m = pg_image_edit_affine($gains($c, $c, $c, 0.5 * (1 - $c)), $m);
    $m = pg_image_edit_affine(array(
        array($lr + (1 - $lr) * $s, $lg - $lg * $s, $lb - $lb * $s, 0),
        array($lr - $lr * $s, $lg + (1 - $lg) * $s, $lb - $lb * $s, 0),
        array($lr - $lr * $s, $lg - $lg * $s, $lb + (1 - $lb) * $s, 0)), $m);
    $m = pg_image_edit_affine($gains(1 + 0.2 * $t, 1 + 0.04 * $t, 1 - 0.2 * $t, 0), $m);

    $matrix = array(
        $m[0][0], $m[0][1], $m[0][2], 0, $m[0][3],
        $m[1][0], $m[1][1], $m[1][2], 0, $m[1][3],
        $m[2][0], $m[2][1], $m[2][2], 0, $m[2][3],
        0, 0, 0, 1, 0);

    return array(
        'matrix' => array_map(function ($x) { return round($x, 6); }, $matrix),
        'gamma'  => round(pow(2, -$v['gamma'] / 100), 6));
}

/**
 * The document the editor saves, checked before any pixel work and turned
 * into the recipe of pg_process_image_file()'s 'edit' option.
 *
 * Version 1 of the editor's format (newDocument() in
 * assets/js/pg_image_editor_model.js): a picture's source and geometry, or an
 * empty artboard. Each number has to lie in the range the editor keeps to,
 * else the whole document is refused: the recipe goes to the pixel code, and
 * a turn of 37 degrees or a crop outside the picture is no request the
 * editor makes.
 *
 * The crop lies in the turned space of the picture as the browser loaded it
 * (source.width x source.height, upright); space_w and space_h carry that
 * space to the engine. Without an output size the picture keeps the crop's.
 *
 * @param mixed $doc the decoded JSON
 * @return array error: '' or the part refused; edit: a picture's recipe;
 *               artboard: an empty one's width and height
 */
function pg_image_edit_validate($doc)
{
    $result = array('error' => '', 'edit' => null, 'artboard' => null);

    if (!is_array($doc) || !isset($doc['v']) || (pg_image_edit_number($doc['v'], 1, 1) === null)) {
        $result['error'] = 'v';
        return $result;
    }

    // The layers arrive drawn into the overlay; their number is capped all
    // the same, as the document is what a later version keeps.
    if (isset($doc['layers']) && (!is_array($doc['layers']) || (count($doc['layers']) > 500))) {
        $result['error'] = 'layers';
        return $result;
    }

    if (isset($doc['artboard'])) {
        $board = is_array($doc['artboard']) ? $doc['artboard'] : array();
        $width = isset($board['width']) ? pg_image_edit_number($board['width'], 16, 8000) : null;
        $height = isset($board['height']) ? pg_image_edit_number($board['height'], 16, 8000) : null;

        if (($width === null) || ($height === null)) {
            $result['error'] = 'artboard';
            return $result;
        }

        $result['artboard'] = array('width' => (int) round($width), 'height' => (int) round($height));
        return $result;
    }

    $source = (isset($doc['source']) && is_array($doc['source'])) ? $doc['source'] : array();
    $width = isset($source['width']) ? pg_image_edit_number($source['width'], 1, 100000) : null;
    $height = isset($source['height']) ? pg_image_edit_number($source['height'], 1, 100000) : null;
    $g = (isset($doc['geometry']) && is_array($doc['geometry'])) ? $doc['geometry'] : null;

    if (($width === null) || ($height === null) || ($g === null)) {
        $result['error'] = 'source';
        return $result;
    }

    $rot = isset($g['rot']) ? pg_image_edit_number($g['rot'], 0, 270) : 0.0;

    if (($rot === null) || !in_array($rot, array(0.0, 90.0, 180.0, 270.0), true)) {
        $result['error'] = 'rot';
        return $result;
    }

    $flip_x = isset($g['flipX']) ? $g['flipX'] : false;
    $flip_y = isset($g['flipY']) ? $g['flipY'] : false;

    if (!is_bool($flip_x) || !is_bool($flip_y)) {
        $result['error'] = 'flip';
        return $result;
    }

    $straighten = isset($g['straighten']) ? pg_image_edit_number($g['straighten'], -45, 45) : 0.0;

    if ($straighten === null) {
        $result['error'] = 'straighten';
        return $result;
    }

    $rw = (((int) $rot) % 180) ? $height : $width;
    $rh = (((int) $rot) % 180) ? $width : $height;
    $crop = null;

    // Half a pixel of slack: the box is worked out in floating point.
    if (isset($g['crop'])) {
        $c = is_array($g['crop']) ? $g['crop'] : array();
        $x = isset($c['x']) ? pg_image_edit_number($c['x'], -0.5, $rw) : null;
        $y = isset($c['y']) ? pg_image_edit_number($c['y'], -0.5, $rh) : null;
        $w = isset($c['w']) ? pg_image_edit_number($c['w'], 1, $rw + 0.5) : null;
        $h = isset($c['h']) ? pg_image_edit_number($c['h'], 1, $rh + 0.5) : null;

        if (($x === null) || ($y === null) || ($w === null) || ($h === null) || ($x + $w > $rw + 0.5) || ($y + $h > $rh + 0.5)) {
            $result['error'] = 'crop';
            return $result;
        }

        $x = max(0.0, $x);
        $y = max(0.0, $y);
        $crop = array('x' => $x, 'y' => $y, 'w' => min($w, $rw - $x), 'h' => min($h, $rh - $y));
    }

    $shape = isset($g['cropShape']) ? $g['cropShape'] : 'rect';

    if (!in_array($shape, array('rect', 'rounded', 'circle'), true)) {
        $result['error'] = 'shape';
        return $result;
    }

    if (isset($g['output'])) {
        $o = is_array($g['output']) ? $g['output'] : array();
        $out_w = isset($o['w']) ? pg_image_edit_number($o['w'], 1, 8000) : null;
        $out_h = isset($o['h']) ? pg_image_edit_number($o['h'], 1, 8000) : null;

        if (($out_w === null) || ($out_h === null)) {
            $result['error'] = 'output';
            return $result;
        }
    } else {
        $out_w = $crop ? $crop['w'] : $rw;
        $out_h = $crop ? $crop['h'] : $rh;
    }

    // The colour step: the adjustments plus the filter's, each in range.
    $adjust = isset($doc['adjust']) ? $doc['adjust'] : array();
    $filter = isset($doc['filter']) ? $doc['filter'] : 'none';
    $ranges = pg_image_edit_adjust_ranges();
    $filters = pg_image_edit_filters();

    if (!is_array($adjust)) {
        $result['error'] = 'adjust';
        return $result;
    }

    foreach ($adjust as $key => $value) {
        if (!isset($ranges[$key]) || (pg_image_edit_number($value, $ranges[$key][0], $ranges[$key][1]) === null)) {
            $result['error'] = 'adjust';
            return $result;
        }
    }

    if (!is_string($filter) || !isset($filters[$filter])) {
        $result['error'] = 'filter';
        return $result;
    }

    $values = array();

    foreach ($ranges as $key => $range) {
        $sum = (isset($adjust[$key]) ? $adjust[$key] : 0) + (isset($filters[$filter][$key]) ? $filters[$filter][$key] : 0);
        $values[$key] = max($range[0], min($range[1], (int) round($sum)));
    }

    $result['edit'] = array(
        'rot'        => (int) $rot,
        'flip_x'     => $flip_x,
        'flip_y'     => $flip_y,
        'straighten' => $straighten,
        'crop'       => $crop,
        'space_w'    => $rw,
        'space_h'    => $rh,
        'shape'      => $shape,
        'out_w'      => max(1, (int) round($out_w)),
        'out_h'      => max(1, (int) round($out_h)),
        'color'      => pg_image_edit_color($values),
        // Amounts as vignetteStops() and sharpenKernel() of the model use them.
        'vignette'   => 0.8 * $values['vignette'] / 100,
        'sharpen'    => 1.5 * $values['sharpen'] / 100);

    return $result;
}

// An answer of image_editor_api.php that refuses: the message, its HTTP
// status and, for a session that ended, code 'session'.
function pg_image_editor_refusal($message, $status = 200, $code = '')
{
    $payload = array('status' => 'error', 'message' => $message);

    if ($code !== '') {
        $payload['code'] = $code;
    }

    return array($payload, $status);
}

/**
 * The files row the editor opens or saves, and what this user may do with it.
 *
 * @return array refusal (pg_image_editor_refusal()) or null; row, path, type,
 *               animated, replace (whether the file may be written over)
 */
function pg_image_editor_file($file_id, $user)
{
    $file_id = (int) $file_id;
    $row = ($file_id > 0) ? db_item("SELECT id, name, folder, type, design FROM files WHERE id = '" . e($file_id) . "' LIMIT 1") : null;

    if (!$row) {
        return array('refusal' => pg_image_editor_refusal(lang('Sorry, we could not find the file.')));
    }

    // A file in the Recycle Bin is offline: neither opened nor saved, or a
    // copy would land in the bin folder with no recycle_bin row, out of the
    // bin's list and refused by its "move to bin".
    if (pg_folder_binned((int) $row['folder'])) {
        return array('refusal' => pg_image_editor_refusal(lang('Sorry, we could not find the file.')));
    }

    // A document the ERP module keeps (a receipt's picture, an expense's
    // scan) is read-only in the file manager.
    if (pg_files_include_erp_document(array($row['id']))) {
        return array('refusal' => pg_image_editor_refusal(lang('ERP documents are read-only in the file manager.'), 403));
    }

    if (!check_edit_access((int) $row['folder'])) {
        return array('refusal' => pg_image_editor_refusal(lang('Access denied'), 403));
    }

    $path = FILE_DIRECTORY_PATH . '/' . $row['name'];
    $size = is_file($path) ? @getimagesize($path) : false;

    // A picture the browser shows and the engine writes back; a browser shows
    // no TIFF.
    $types = array_diff(pg_image_supported_types(), array(IMAGETYPE_TIFF_II, IMAGETYPE_TIFF_MM));

    if (!$size || !in_array((int) $size[2], $types, true)) {
        return array('refusal' => pg_image_editor_refusal(lang('This file cannot be opened in the image editor.')));
    }

    $animated = pg_image_is_animated($path);

    return array(
        'refusal'  => null,
        'row'      => $row,
        'path'     => $path,
        'type'     => (int) $size[2],
        'animated' => $animated,
        // A design file is written over only by administrators (0) and
        // designers (1), as edit_file.php allows; an animated file never,
        // because only its first frame would be left.
        'replace'  => !$animated && !(((int) $row['design'] === 1) && ($user['role'] > 1)));
}

/**
 * Action open: the file's address and what a save may do with it.
 *
 * @return array array(payload, HTTP status)
 */
function pg_image_editor_open($request, $user)
{
    $file_id = isset($request['file_id']) ? $request['file_id'] : 0;

    // A caller that knows the file only by its address (a page's picture,
    // the designer's image field) names it; the name is the files row's
    // address, so it is unique.
    if (!$file_id && isset($request['file_name']) && is_string($request['file_name']) && ($request['file_name'] !== '')) {
        $file_id = (int) db_value("SELECT id FROM files WHERE name = '" . e($request['file_name']) . "' LIMIT 1");
    }

    $file = pg_image_editor_file($file_id, $user);

    if ($file['refusal']) {
        return $file['refusal'];
    }

    $state = pg_file_versions_open_state((int) $file['row']['id'], $file['path']);

    return array(array(
        'status' => 'success',
        'file'   => array(
            'id'      => (int) $file['row']['id'],
            'name'    => (string) $file['row']['name'],
            // Stamped: the browser may keep a file for a week under one address.
            'url'     => PATH . encode_url_path($file['row']['name']) . '?v=' . (int) @filemtime($file['path']),
            'replace' => $file['replace'],
            'notice'  => $file['animated'] ? lang('An animated image is saved as a still copy.') : ''),
        // The newest save's document and the version it was drawn on, which
        // the browser loads instead of the file (action picture).
        'versions' => $state['count'],
        'doc'      => $state['doc'],
        'base'     => $state['base']), 200);
}

/**
 * A picture the browser drew as a data: URL (PNG or JPEG), decoded onto the
 * disk in pieces, as base64_decode() of the whole string would hold it three
 * times over; kept only when its bytes are the type the URL names.
 *
 * @return array|false getimagesize() of the file written to $destination
 */
function pg_image_editor_take_picture($data, $destination)
{
    $data = is_string($data) ? $data : '';

    if (!preg_match('#^data:image/(png|jpeg);base64,#', substr($data, 0, 32), $match)) {
        return false;
    }

    $handle = @fopen($destination, 'wb');

    if ($handle === false) {
        return false;
    }

    // A multiple of four: every piece is whole base64 groups, and only the
    // last one may carry padding.
    for ($offset = strlen($match[0]), $length = strlen($data); $offset < $length; $offset += 65532) {
        $chunk = base64_decode(substr($data, $offset, 65532), true);

        if (($chunk === false) || (fwrite($handle, $chunk) === false)) {
            fclose($handle);
            @unlink($destination);
            return false;
        }
    }

    fclose($handle);

    $size = @getimagesize($destination);

    if (!$size || ((int) $size[2] !== (($match[1] === 'png') ? IMAGETYPE_PNG : IMAGETYPE_JPEG))) {
        @unlink($destination);
        return false;
    }

    return $size;
}

// A PNG the browser drew (the overlay, an artboard), kept only at exactly
// w x h.
function pg_image_editor_take_png($data, $destination, $width, $height)
{
    $size = (strncmp(is_string($data) ? $data : '', 'data:image/png;', 15) === 0) ? pg_image_editor_take_picture($data, $destination) : false;

    if ($size && ((int) $size[0] === (int) $width) && ((int) $size[1] === (int) $height)) {
        return true;
    }

    if ($size) {
        @unlink($destination);
    }

    return false;
}

/**
 * Steps 1, 2, 5 and 6 of a save on the picture at $path: read it upright,
 * turn, flip, straighten, crop and size it, lay the overlay over it, cut the
 * crop shape and encode it in its own format, through the image engine.
 *
 * @param string $path
 * @param array  $edit    the recipe of pg_image_edit_validate()
 * @param array  $options more of pg_process_image_file()'s options (quality)
 * @return array the engine's report; data holds the bytes
 */
function pg_image_edit_apply($path, $edit, $options = array())
{
    return pg_process_image_file($path, array_merge($options, array('edit' => $edit, 'write' => false)));
}

// Bytes to a file through a temporary one, so the address never answers with
// half a picture.
function pg_image_editor_put($path, $data)
{
    $temporary = $path . '.' . (function_exists('getmypid') ? getmypid() : uniqid()) . '.tmp';

    if ((@file_put_contents($temporary, $data) === false) || !@rename($temporary, $path)) {
        @unlink($temporary);
        return false;
    }

    @clearstatcache(true, $path);

    return true;
}

// Why the engine wrote nothing, in the sentences the file manager's buttons
// use.
function pg_image_editor_report_message($report, $file_name)
{
    switch ($report['reason']) {

        case 'orientation_unreadable':
            return lang(array('string' => 'Sorry, {var:1} has a rotation tag this server cannot read. Changing it here could leave the picture on its side, so it was left alone.', 'vars' => $file_name));

        case 'color_profile':
            return lang(array('string' => 'Sorry, {var:1} has a colour profile this server cannot carry over. Changing it here would wash out its colours, so it was left alone.', 'vars' => $file_name));

        case 'missing':
            return lang(array('string' => 'Sorry, {var:1} is recorded here but is not on the disk.', 'vars' => $file_name));

        case 'too_large_to_process':
            return lang(array('string' => 'Sorry, {var:1} needs more memory than this server allows. Please resize it on your computer and upload it again.', 'vars' => $file_name));

        case 'no_library':
            return lang('No image library (Imagick or GD) is installed on this server, so images cannot be optimized or resized.');
    }

    return lang('Sorry, the file could not be saved.');
}

/**
 * The saved file's row: replace updates the file's own (its id and name stay,
 * so every link keeps working), copy and new add one.
 *
 * optimized = 0: these are fresh bytes, encoded for quality, and the file
 * manager offers Optimize for them as for an upload.
 *
 * @return array array(payload, HTTP status)
 */
function pg_image_editor_record($mode, $name, $folder, $type, $replaced, $user)
{
    $path = FILE_DIRECTORY_PATH . '/' . $name;
    $bytes = (int) @filesize($path);
    $size = @getimagesize($path);
    $width = $size ? (int) $size[0] : 0;
    $height = $size ? (int) $size[1] : 0;

    // The dimension columns came with 2026.4.4.
    $dimensions = pg_schema_has('files', 'image_width');

    if ($mode === 'replace') {
        $id = (int) $replaced['id'];
        $before = pg_audit_fetch('files', $id);
        $saved = $dimensions
            ? db_q("UPDATE files SET size = ?, image_width = ?, image_height = ?, optimized = 0, user = ?, timestamp = UNIX_TIMESTAMP() WHERE id = ?", array($bytes, $width, $height, (int) $user['id'], $id))
            : db_q("UPDATE files SET size = ?, optimized = 0, user = ?, timestamp = UNIX_TIMESTAMP() WHERE id = ?", array($bytes, (int) $user['id'], $id));

        if (!$saved) {
            return pg_image_editor_refusal(lang('Sorry, the file could not be saved.'));
        }

        pg_audit_record('update', 'files', $id, $before);
        log_activity(lang(array('string' => 'file ({var:1}) was updated via Image Editor', 'vars' => $name)), isset($_SESSION['sessionusername']) ? $_SESSION['sessionusername'] : '');

    } else {
        $saved = $dimensions
            ? db_q("INSERT INTO files (name, folder, type, size, image_width, image_height, user, design, optimized, timestamp) VALUES (?, ?, ?, ?, ?, ?, ?, 0, 0, UNIX_TIMESTAMP())", array($name, $folder, $type, $bytes, $width, $height, (int) $user['id']))
            : db_q("INSERT INTO files (name, folder, type, size, user, design, optimized, timestamp) VALUES (?, ?, ?, ?, ?, 0, 0, UNIX_TIMESTAMP())", array($name, $folder, $type, $bytes, (int) $user['id']));

        if (!$saved) {
            @unlink($path);
            return pg_image_editor_refusal(lang('Sorry, the file could not be saved.'));
        }

        $id = (int) mysqli_insert_id(db::$con);

        pg_audit_record('create', 'files', $id);
        log_activity(lang(array('string' => 'file ({var:1}) was created via Image Editor', 'vars' => $name)), isset($_SESSION['sessionusername']) ? $_SESSION['sessionusername'] : '');
    }

    return array(array(
        'status' => 'success',
        'mode'   => $mode,
        'id'     => $id,
        'name'   => $name,
        'folder' => (int) $folder,
        'url'    => PATH . encode_url_path($name) . '?v=' . time(),
        'width'  => $width,
        'height' => $height,
        'size'   => $bytes), 200);
}

/**
 * Action save. The browser sends the document and, when it has layers, an
 * overlay drawn at the output size; the server makes the pixels from the
 * original, so a phone photo edited on a preview is saved at full size.
 *
 *   replace  writes over file_id, keeping its id and name
 *   copy     a new file beside it, in its folder (the first frame of an
 *            animated one)
 *   new      an empty artboard: the browser's PNG (picture) into folder_id
 *
 * @return array array(payload, HTTP status)
 */
function pg_image_editor_save($request, $user)
{
    $check = pg_image_edit_validate(isset($request['doc']) ? $request['doc'] : null);
    $mode = isset($request['mode']) ? (string) $request['mode'] : '';

    if ($check['error'] !== '') {
        return pg_image_editor_refusal(lang('Invalid request.'));
    }

    @set_time_limit(120);

    if ($check['artboard']) {
        $folder = isset($request['folder_id']) ? (int) $request['folder_id'] : 0;

        if ($folder <= 0) {
            return pg_image_editor_refusal(lang('Invalid request.'));
        }

        // The bin folder takes no new file, as the File Manager's upload and
        // move refuse it.
        if (!check_edit_access($folder) || pg_folder_binned($folder)) {
            return pg_image_editor_refusal(lang('Access denied'), 403);
        }

        $base = preg_replace('/\.[a-z0-9]{2,5}$/i', '', trim(isset($request['name']) ? (string) $request['name'] : ''));
        $name = get_unique_name(array('name' => prepare_file_name((($base !== '') ? $base : lang('New image')) . '.png'), 'type' => 'file'));

        if (pg_upload_name_blocked($name)) {
            return pg_image_editor_refusal(pg_upload_blocked_message($name));
        }

        $path = FILE_DIRECTORY_PATH . '/' . $name;
        $temporary = $path . '.' . (function_exists('getmypid') ? getmypid() : uniqid()) . '.tmp';

        if (!pg_image_editor_take_png(isset($request['picture']) ? $request['picture'] : '', $temporary, $check['artboard']['width'], $check['artboard']['height'])) {
            return pg_image_editor_refusal(lang('Invalid request.'));
        }

        // The browser's PNG is recompressed when that makes it smaller.
        pg_process_image_file($temporary);

        if (!@rename($temporary, $path)) {
            @unlink($temporary);
            return pg_image_editor_refusal(lang('Sorry, the file could not be saved.'));
        }

        return pg_image_editor_record('new', $name, $folder, 'png', null, $user);
    }

    if (($mode !== 'replace') && ($mode !== 'copy')) {
        return pg_image_editor_refusal(lang('Invalid request.'));
    }

    $file = pg_image_editor_file(isset($request['file_id']) ? $request['file_id'] : 0, $user);

    if ($file['refusal']) {
        return $file['refusal'];
    }

    if (($mode === 'replace') && !$file['replace']) {
        return $file['animated']
            ? pg_image_editor_refusal(lang('An animated image cannot be replaced. Save it as a new file instead.'))
            : pg_image_editor_refusal(lang('Access denied'), 403);
    }

    $edit = $check['edit'];
    $edit['first_frame'] = ($mode === 'copy');
    $name = (string) $file['row']['name'];
    $overlay = '';

    if (!empty($request['overlay'])) {
        $overlay = FILE_DIRECTORY_PATH . '/' . $name . '.' . (function_exists('getmypid') ? getmypid() : uniqid()) . '.overlay.tmp';

        if (!pg_image_editor_take_png($request['overlay'], $overlay, $edit['out_w'], $edit['out_h'])) {
            return pg_image_editor_refusal(lang('Invalid request.'));
        }

        $edit['overlay'] = $overlay;
    }

    // Step 4, the redaction: masks the browser drew of each area, burned in
    // by the engine after the colour step and before the overlay.
    $redactions = pg_image_editor_take_redactions(isset($request['redact']) ? $request['redact'] : null, $edit, FILE_DIRECTORY_PATH . '/' . $name . '.' . (function_exists('getmypid') ? getmypid() : uniqid()));

    if ($redactions === false) {
        @unlink($overlay);
        return pg_image_editor_refusal(lang('Invalid request.'));
    }

    if ($redactions) {
        $edit['redact'] = $redactions;
    }

    $source = $file['path'];
    $layer = '';
    $base = null;
    $base_id = isset($request['base']) ? (int) $request['base'] : 0;

    // The document is drawn on the version the browser opened (the original
    // when the file has versions): the pixels are made from that one. A
    // replace keeps the pixels it writes over as a version first.
    if (pg_file_versions_ready()) {
        if ($mode === 'replace') {
            $base = pg_file_versions_before_save((int) $file['row']['id'], $file['path'], $base_id, $user['id']);

            if (!$base) {
                @unlink($overlay);
                pg_image_editor_drop_redactions($redactions);
                return pg_image_editor_refusal(($base_id > 0) ? lang('Invalid request.') : lang('Sorry, the file could not be saved.'));
            }
        } elseif ($base_id > 0) {
            $base = pg_file_versions_row((int) $file['row']['id'], $base_id);

            if (!$base || ((string) $base['doc_json'] !== '')) {
                @unlink($overlay);
                pg_image_editor_drop_redactions($redactions);
                return pg_image_editor_refusal(lang('Invalid request.'));
            }
        }

        if ($base_id > 0) {
            $source = pg_file_versions_path($base);
        }
    } elseif ($base_id > 0) {
        @unlink($overlay);
        pg_image_editor_drop_redactions($redactions);
        return pg_image_editor_refusal(lang('Invalid request.'));
    }

    // A server without Imagick has no colour matrix: the browser sends the
    // picture it loaded with the colour step done (the colour layer), of its
    // shape and no larger, and the rest is drawn from that in the file's type.
    if (!empty($request['colour']) && !empty($edit['color'])) {
        $layer = FILE_DIRECTORY_PATH . '/' . $name . '.' . (function_exists('getmypid') ? getmypid() : uniqid()) . '.colour.tmp';
        $size = pg_image_editor_take_picture($request['colour'], $layer);
        $width = ($edit['rot'] % 180) ? $edit['space_h'] : $edit['space_w'];
        $height = ($edit['rot'] % 180) ? $edit['space_w'] : $edit['space_h'];

        if (!$size || ($size[0] > $width + 0.5) || ($size[1] > $height + 0.5) || (abs($size[0] - $size[1] * $width / $height) > 1.5)) {
            @unlink($layer);
            @unlink($overlay);
            pg_image_editor_drop_redactions($redactions);
            return pg_image_editor_refusal(lang('Invalid request.'));
        }

        $source = $layer;
        $edit['color'] = null;
        $edit['type'] = $file['type'];

    } elseif (!empty($edit['color']) && !class_exists('Imagick')) {
        @unlink($overlay);
        pg_image_editor_drop_redactions($redactions);
        return pg_image_editor_refusal(lang('Invalid request.'));
    }

    // Encoded for quality, as the file manager's rotate button does: the
    // operator asked for a changed picture, not a lighter file, and a replace
    // is the next edit's original.
    $report = pg_image_edit_apply($source, $edit, array('quality' => 92));

    if ($overlay !== '') {
        @unlink($overlay);
    }

    if ($layer !== '') {
        @unlink($layer);
    }

    pg_image_editor_drop_redactions($redactions);

    if (($report['status'] !== 'success') || !is_string($report['data'])) {
        return pg_image_editor_refusal(pg_image_editor_report_message($report, $name));
    }

    if ($mode === 'copy') {
        $name = get_unique_name(array('name' => prepare_file_name($name), 'type' => 'file'));

        if (pg_upload_name_blocked($name)) {
            return pg_image_editor_refusal(pg_upload_blocked_message($name));
        }
    }

    if (!pg_image_editor_put(FILE_DIRECTORY_PATH . '/' . $name, $report['data'])) {
        return pg_image_editor_refusal(lang('Sorry, the file could not be saved.'));
    }

    $record = pg_image_editor_record($mode, $name, (int) $file['row']['folder'], (string) $file['row']['type'], $file['row'], $user);

    if (($mode === 'replace') && $base && ($record[0]['status'] === 'success')) {
        pg_file_versions_after_save((int) $file['row']['id'], FILE_DIRECTORY_PATH . '/' . $name, isset($request['doc_object']) ? $request['doc_object'] : null, $base, $user['id'], !empty($redactions));
    }

    return $record;
}

// A value of json_decode() without assoc as json_decode($body, true) gives
// it: objects become arrays. Pure.
function pg_image_editor_arrays($value)
{
    if (is_object($value)) {
        $value = get_object_vars($value);
    }

    if (is_array($value)) {
        foreach ($value as $key => $item) {
            if (is_object($item) || is_array($item)) {
                $value[$key] = pg_image_editor_arrays($item);
            }
        }
    }

    return $value;
}

/**
 * One request of image_editor_api.php: its JSON body, checked and answered.
 * Every answer is JSON, refusals included; a page with a "go back" link is
 * a syntax error to fetch().
 *
 *   { token, action: 'open', file_id | file_name }
 *       -> { status, file: { id, name, url, replace, notice } }
 *       (and versions, doc, base: the versions this file has)
 *   { token, action: 'save', mode: 'replace'|'copy'|'new', file_id | folder_id,
 *     doc, base? (the version the document is drawn on), overlay?, picture?
 *     and name? (new) }
 *       -> { status, mode, id, name, folder, url, width, height, size }
 *   { token, action: '<name>', … }
 *       -> pg_image_editor_action_<name>($request, $user), a tool's own
 *

 * @param string $body the raw request body
 * @param array  $user validate_user()
 * @return array array(payload, HTTP status)
 */
function pg_image_editor_api($body, $user)
{
    if (strtoupper(isset($_SERVER['REQUEST_METHOD']) ? (string) $_SERVER['REQUEST_METHOD'] : 'GET') !== 'POST') {
        return pg_image_editor_refusal(lang('Invalid request.'), 405);
    }

    if (trim((string) $body) === '') {

        // Something was sent and nothing arrived: PHP dropped a body over
        // post_max_size before this script ran.
        if (isset($_SERVER['CONTENT_LENGTH']) && ((int) $_SERVER['CONTENT_LENGTH'] > 0)) {
            $limits = pg_upload_limits();

            return pg_image_editor_refusal(lang(array(
                'string' => 'The image is larger than this server accepts in one request ({var:1}).',
                'vars'   => convert_bytes_to_string($limits['post_max']))), 413);
        }

        return pg_image_editor_refusal(lang('Invalid request.'));
    }

    // Decoded once, as objects: a save keeps its document as objects for the
    // version (an empty {} stays one), the rest is read as arrays. The
    // overlay and colour strings are shared, not copied.
    $objects = json_decode($body);

    if (!is_object($objects)) {
        return pg_image_editor_refusal(lang('Invalid request.'));
    }

    $request = pg_image_editor_arrays($objects);
    $request['doc_object'] = isset($objects->doc) ? $objects->doc : null;

    // Checked here, not by validate_token_field(), which reads $_POST (a JSON
    // body never fills it) and answers with a page.
    $session = isset($_SESSION['software']['token']) ? (string) $_SESSION['software']['token'] : '';
    $token = isset($request['token']) ? (string) $request['token'] : '';

    if (($session === '') || !hash_equals($session, $token)) {
        return pg_image_editor_refusal(lang('Your session expired. Reload the page and try again.'), 403, 'session');
    }

    $action = isset($request['action']) ? (string) $request['action'] : '';

    if ($action === 'open') {
        return pg_image_editor_open($request, $user);
    }

    if ($action === 'save') {
        return pg_image_editor_save($request, $user);
    }

    // Any other action is a tool's, pg_image_editor_action_<name>($request,
    // $user) at the end of this file. The name is checked first, so a request
    // reaches only those functions.
    if (preg_match('/^[a-z][a-z_]{0,30}$/', $action) && function_exists('pg_image_editor_action_' . $action)) {
        return call_user_func('pg_image_editor_action_' . $action, $request, $user);
    }

    return pg_image_editor_refusal(lang('Invalid request.'));
}

// ---- The tools' own parts ----
// Each tool keeps its labels, and the API actions only it asks for
// (pg_image_editor_action_<name>(), reached through pg_image_editor_api()),
// under its own line below, so tools built side by side do not change the
// same lines. The labels of a layer type are layer_<type>.

// -- Crop (pg_image_editor_crop.js) --

/**
 * The crop tool: labels.
 *
 * @return array key => translated text
 */
function pg_image_editor_strings_crop()
{
    return array(
        'cropRatio'      => lang('Ratio'),
        'cropFree'       => lang('Freeform'),
        'cropOriginal'   => lang('Original'),
        'rotateFlip'     => lang('Rotate and flip'),
        'rotateLeft'     => lang('Rotate left'),
        'rotateRight'    => lang('Rotate right'),
        'flipH'          => lang('Flip horizontally'),
        'flipV'          => lang('Flip vertically'),
        'straighten'     => lang('Straighten'),
        'cropShape'      => lang('Crop shape'),
        'shapeRect'      => lang('Rectangle'),
        'shapeRounded'   => lang('Rounded corners'),
        'shapeCircle'    => lang('Circle'),
        'outputSize'     => lang('Output size'),
    );
}

// -- Adjust (pg_image_editor_adjust.js) --

/**
 * The Adjust tool: labels.
 *
 * @return array key => translated text
 */
function pg_image_editor_strings_adjust()
{
    return array(
        'adjustments'     => lang('Adjustments'),
        'filters'         => lang('Filters'),
        'details'         => lang('Details'),
        'adj_brightness'  => lang('Brightness'),
        'adj_contrast'    => lang('Contrast'),
        'adj_saturation'  => lang('Saturation'),
        'adj_exposure'    => lang('Exposure'),
        'adj_temperature' => lang('Temperature'),
        'adj_gamma'       => lang('Gamma'),
        'adj_vignette'    => lang('Vignette'),
        'adj_sharpen'     => lang('Sharpen'),
        'filter_none'     => lang('Original'),
        'filter_mono'     => lang('Black and white'),
        'filter_sepia'    => lang('Sepia'),
        'filter_cool'     => lang('Cool'),
        'filter_warm'     => lang('Warm'),
        'filter_fade'     => lang('Faded'),
        'filter_vivid'    => lang('Vivid'),
        'filter_noir'     => lang('Noir'),
        'filter_chrome'   => lang('Chrome'),
        'hintAdjust'      => lang('Changes show at once. Double-click a slider to set it back to 0.'),
    );
}

// -- Layers and Select (pg_image_editor_layers.js) --

/**
 * The layers and the Select tool: labels.
 *
 * @return array key => translated text
 */
function pg_image_editor_strings_layers()
{
    return array(
        'noLayers'       => lang('No layers yet.'),
        'hintSelect'     => lang('Click a layer to select it, with Shift to add more. Drag to move it; the handles resize and turn it. Ctrl+V pastes a picture.'),
        'layer_image'    => lang('Picture'),
        'rotate'         => lang('Rotate'),
        'show'           => lang('Show'),
        'hide'           => lang('Hide'),
        'lock'           => lang('Lock'),
        'unlock'         => lang('Unlock'),
        'arrange'        => lang('Arrange'),
        'bringFront'     => lang('Bring to front'),
        'bringForward'   => lang('Bring forward'),
        'sendBackward'   => lang('Send backward'),
        'sendBack'       => lang('Send to back'),
        'align'          => lang('Align'),
        'alignLeft'      => lang('Align left'),
        'alignCenter'    => lang('Align center'),
        'alignRight'     => lang('Align right'),
        'alignTop'       => lang('Align top'),
        'alignMiddle'    => lang('Align middle'),
        'alignBottom'    => lang('Align bottom'),
        'distributeX'    => lang('Distribute horizontally'),
        'distributeY'    => lang('Distribute vertically'),
        'opacity'        => lang('Layer opacity'),
        'duplicate'      => lang('Duplicate'),
        'delete'         => lang('Delete'),
    );
}

// -- Draw (pg_image_editor_draw.js) --

/**
 * The drawing tool and its mini bar kinds: labels.
 *
 * @return array key => translated text
 */
function pg_image_editor_strings_draw()
{
    return array(
        'hintDraw'       => lang('Drag to draw. Shift: straight angles and squares.'),
        'hintEraser'     => lang('Drag over pen strokes to erase them.'),
        'drawKinds'      => lang('Shapes'),
        'layer_arrow'    => lang('Arrow'),
        'layer_line'     => lang('Line'),
        'layer_rect'     => lang('Box'),
        'layer_ellipse'  => lang('Ellipse'),
        'layer_triangle' => lang('Triangle'),
        'layer_polygon'  => lang('Polygon'),
        'layer_star'     => lang('Star'),
        'layer_bubble'   => lang('Bubble'),
        'layer_pen'      => lang('Pen'),
        'eraser'         => lang('Eraser'),
        'eraserSize'     => lang('Eraser size'),
        'eraserNote'     => lang('The eraser cuts pen strokes. Delete a shape with Select or the Delete key.'),
        'drawColor'      => lang('Color'),
        'strokeWidth'    => lang('Line width'),
        'strokeThin'     => lang('Thin'),
        'strokeMedium'   => lang('Medium'),
        'strokeThick'    => lang('Thick'),
        'drawFill'       => lang('Shape fill'),
        'fillNone'       => lang('No fill'),
        'fillSolid'      => lang('Color'),
        'fillSoft'       => lang('Tint'),
        'cornerRadius'   => lang('Corner radius'),
        'sides'          => lang('Sides'),
        'starPoints'     => lang('Points'),
        'innerRatio'     => lang('Inner ratio'),
        'arrowHeads'     => lang('Arrow heads'),
        'headsEnd'       => lang('At the end'),
        'headsBoth'      => lang('Both ends'),
        'headStyle'      => lang('Head style'),
        'headFill'       => lang('Filled'),
        'headOpen'       => lang('Open'),
        'headDot'        => lang('Dot'),
        'smoothing'      => lang('Smoothing'),
        'lineStyle'      => lang('Line style'),
        'dashSolid'      => lang('Solid'),
        'dashDashed'     => lang('Dashed'),
        'dashDotted'     => lang('Dotted'),
        'drawOpacity'    => lang('Opacity'),
    );
}

// -- Text (pg_image_editor_text.js) --

/**
 * The text tool: labels.
 *
 * @return array key => translated text
 */
function pg_image_editor_strings_text()
{
    return array(
        'layer_text'          => lang('Text'),
        'hintText'            => lang('Click to add a text there. Double-click a text to change it; the handles size and turn it.'),
        'textPlaceholder'     => lang('Type here'),
        'textNoteAdd'         => lang('Click anywhere on the image to add text.'),
        'textNoteEdit'        => lang('Text stays a layer; double-click it to change it later.'),
        'textAdd'             => lang('Add text'),
        'textColor'           => lang('Text color'),
        'textFont'            => lang('Font'),
        'textFontSans'        => lang('Clean'),
        'textFontSerif'       => lang('Classic'),
        'textFontHand'        => lang('Handwritten'),
        'textFontDisplay'     => lang('Poster'),
        'textFontMono'        => lang('Monospace'),
        'textSize'            => lang('Size'),
        'textStyle'           => lang('Style and alignment'),
        'textBold'            => lang('Bold'),
        'textItalic'          => lang('Italic'),
        'textAlignLeft'       => lang('Align text left'),
        'textAlignCenter'     => lang('Center text'),
        'textAlignRight'      => lang('Align text right'),
        'textBackground'      => lang('Text background'),
        'textNone'            => lang('None'),
        'textSoft'            => lang('Soft'),
        'textWeight'          => lang('Font weight'),
        'textWeight300'       => lang('Light (300)'),
        'textWeight400'       => lang('Regular (400)'),
        'textWeight600'       => lang('Semibold (600)'),
        'textWeight700'       => lang('Bold (700)'),
        'textWeight900'       => lang('Heavy (900)'),
        'textLineHeight'      => lang('Line height'),
        'textSpacing'         => lang('Letter spacing'),
        'textPadding'         => lang('Padding'),
        'textRadius'          => lang('Corner radius'),
        'textOutline'         => lang('Text outline'),
        'textOutlineWidth'    => lang('Outline width'),
        'textShadow'          => lang('Shadow'),
        'textShadowHard'      => lang('Hard'),
        'textShadowGlow'      => lang('Glow'),
    );
}

/**
 * Action text_fonts: the font families the site's styles load from Google
 * Fonts (style_custom_fonts, the JSON list the designer writes), for the
 * text tool's font list. Families only, cleaned as the editor quotes them.
 *
 *   { token, action: 'text_fonts' } -> { status, fonts: [family, ...] }
 *
 * @return array array(payload, HTTP status)
 */
function pg_image_editor_action_text_fonts($request, $user)
{
    $fonts = array();

    if (pg_schema_has('style', 'style_custom_fonts')) {
        $rows = db_values("SELECT DISTINCT style_custom_fonts FROM style WHERE style_custom_fonts != ''");

        foreach (is_array($rows) ? $rows : array() as $json) {
            foreach (pg_image_editor_text_families($json) as $family) {
                $fonts[strtolower($family)] = $family;
            }
        }
    }

    natcasesort($fonts);

    return array(array('status' => 'success', 'fonts' => array_values(array_slice($fonts, 0, 60))), 200);
}

/**
 * The enabled families of one style_custom_fonts value: the JSON list of
 * { family, weights, enabled }; the older one-URL-a-line form names none.
 *
 * @return array families, cleaned to letters, digits, spaces and - _ .
 */
function pg_image_editor_text_families($json)
{
    $list = json_decode((string) $json, true);
    $out = array();

    foreach (is_array($list) ? $list : array() as $font) {
        if (!is_array($font) || (isset($font['enabled']) && ($font['enabled'] === false)) || empty($font['family']) || !is_string($font['family'])) {
            continue;
        }

        $family = trim(preg_replace('/\s+/', ' ', preg_replace('/[^A-Za-z0-9 _\-.]/', '', $font['family'])));

        if ($family !== '') {
            $out[] = mb_substr($family, 0, 60);
        }
    }

    return $out;
}

// -- Stickers and frames (pg_image_editor_sticker.js) --

/**
 * The sticker and frame tools: labels.
 *
 * @return array key => translated text
 */
function pg_image_editor_strings_sticker()
{
    return array(
        'layer_sticker'   => lang('Sticker'),
        'stickerNew'      => lang('NEW'),
        'stickerSale'     => lang('SALE'),
        'stickerSold'     => lang('SOLD OUT'),
        'stickerHello'    => lang('Hello!'),
        'stickerBurst'    => lang('Burst'),
        'stickerCheck'    => lang('Check mark'),
        'stickerStar'     => lang('Star'),
        'stickerHeart'    => lang('Heart'),
        'stickerColor'    => lang('Sticker color'),
        'stickerInk'      => lang('Text color'),
        'stickerLabel'    => lang('Sticker text'),
        'stickerDetails'  => lang('Details'),
        'stickerFiles'    => lang('Picture from Files'),
        'stickerFilesFind' => lang('Search pictures'),
        'stickerFilesNone' => lang('No pictures found.'),
        'stickerFilesError' => lang('The pictures could not be loaded.'),
        'hintSticker'     => lang('Click a sticker, then move it into place'),
        'frameNone'       => lang('None'),
        'frameLine'       => lang('Thin line'),
        'frameThick'      => lang('Thick edge'),
        'framePolaroid'   => lang('Polaroid'),
        'frameCorners'    => lang('Corners'),
        'frameColor'      => lang('Color'),
        'frameWidth'      => lang('Thickness'),
        'frameBackground' => lang('Background'),
        'hintFrame'       => lang('The frame is added to the saved image'),
    );
}

/**
 * The image files a sticker may be made from, out of files rows: the
 * pictures a browser draws, in the folders the user may reach.
 *
 * @param array      $rows    files rows (id, name, type, folder, image_width, image_height)
 * @param array|null $folders the folder ids a role-3 user may reach; null for every folder
 * @return array [{ id, name, url, width, height }]
 */
function pg_image_editor_sticker_rows($rows, $folders)
{
    $types = array('jpg', 'jpeg', 'png', 'gif', 'webp');
    $list = array();

    foreach ($rows as $row) {
        if (!in_array(strtolower((string) $row['type']), $types, true)) {
            continue;
        }

        if (($folders !== null) && !in_array((int) $row['folder'], array_map('intval', $folders), true)) {
            continue;
        }

        $list[] = array(
            'id'     => (int) $row['id'],
            'name'   => (string) $row['name'],
            'url'    => PATH . encode_url_path((string) $row['name']),
            'width'  => (int) $row['image_width'],
            'height' => (int) $row['image_height']);
    }

    return $list;
}

/**
 * The WHERE clause of the sticker panel's listing: pictures only, none in
 * the Recycle Bin (pg_image_editor_file() treats those as offline), a
 * role-3 user's folders only, and the name search. The folders are filtered
 * here, before the LIMIT, so a user whose folders hold none of the newest
 * pictures still sees theirs.
 *
 * @param string     $search  the name search ('' for none)
 * @param array|null $folders the folder ids a role-3 user may reach; null for every folder
 * @param array      $bin     the Recycle Bin's folder ids
 * @return string|null the clause, or null when the user may reach no folder
 */
function pg_image_editor_sticker_where($search, $folders, $bin)
{
    $where = "(type IN ('jpg', 'jpeg', 'png', 'gif', 'webp')) AND (name != '')";
    $ids = function ($list) {
        return implode(', ', array_map(function ($id) { return "'" . (int) $id . "'"; }, $list));
    };

    if ($folders !== null) {
        $folders = array_values(array_diff(array_map('intval', $folders), array_map('intval', $bin)));

        if (!$folders) {
            return null;
        }

        $where .= ' AND (folder IN (' . $ids($folders) . '))';
    } elseif ($bin) {
        $where .= ' AND (folder NOT IN (' . $ids($bin) . '))';
    }

    if ($search !== '') {
        $where .= " AND (name LIKE '" . e(pg_list_like_pattern($search)) . "')";
    }

    return $where;
}

/**
 * Action sticker_files: the newest pictures in Files, or those whose name
 * holds the search, for the sticker panel's "Picture from Files". Read only;
 * a role-3 user sees the folders they may edit, as the File Manager shows
 * them.
 *
 *   { search?: string } -> { status, files: [{ id, name, url, width, height }] }
 *
 * @return array array(payload, HTTP status)
 */
function pg_image_editor_action_sticker_files($request, $user)
{
    $search = (isset($request['search']) && is_string($request['search'])) ? trim(mb_substr($request['search'], 0, 100)) : '';
    $folders = ((int) $user['role'] === 3) ? get_folders_that_user_has_access_to($user['id']) : null;
    $where = pg_image_editor_sticker_where($search, $folders, pg_recycle_bin_folder_ids());
    $rows = ($where === null) ? array() : db_items(
        "SELECT id, name, type, folder, image_width, image_height
        FROM files
        WHERE $where
        ORDER BY timestamp DESC
        LIMIT 60");

    return array(array(
        'status' => 'success',
        'files'  => pg_image_editor_sticker_rows(is_array($rows) ? $rows : array(), $folders)), 200);
}

// -- Redact (pg_image_editor_redact.js) --

/**
 * The redaction tool: labels.
 *
 * @return array key => translated text
 */
function pg_image_editor_strings_redact()
{
    return array(
        'layer_redact'     => lang('Redaction'),
        'redactMethod'     => lang('Method'),
        'redactPixel'      => lang('Pixelate'),
        'redactBlur'       => lang('Blur'),
        'redactSolid'      => lang('Solid box'),
        'redactArea'       => lang('Area'),
        'redactBox'        => lang('Box'),
        'redactBrush'      => lang('Brush'),
        'redactBrushSize'  => lang('Brush size'),
        'redactStrength'   => lang('Strength'),
        'redactIntro'      => lang('Drag over the area you want to hide.'),
        'redactNote'       => lang('Saving burns the redaction into the image and removes its metadata. Earlier versions stay in Versions, where anyone who may edit this folder can restore them; Save as a copy leaves this file as it is.'),
        'hintRedact'       => lang('Drag over the area to hide'));
}

// The redaction's methods (the model's REDACT_MODES).
function pg_image_edit_redact_modes()
{
    return array('pixel', 'blur', 'solid');
}

/**
 * The block of a pixelation and the radius (sigma) of a blur in output
 * pixels: shares of the longer side of the turned picture, as the preview
 * draws them (redactSizes() of the model, in its units), times the output's
 * scale. Pure.
 *
 * @param float $strength  1-100
 * @param float $space_max the longer side of the turned picture
 * @param float $scale     output pixels per turned-picture pixel
 * @return array block (int), sigma (float)
 */
function pg_image_edit_redact_sizes($strength, $space_max, $scale)
{
    $block = max(4, round($strength / 100 * 0.035 * $space_max));
    $sigma = max(2, $strength / 100 * 0.02 * $space_max);

    return array(
        'block' => max(2, (int) round($block * $scale)),
        'sigma' => max(1.0, round($sigma * $scale * 10) / 10));
}

/**
 * The redacted areas of a save, checked and their masks written next to
 * $prefix: each { mode, strength, x, y, mask } with the mask a PNG data: URL
 * whose opaque pixels are the area, its box at x, y inside the output.
 *
 * @param mixed  $regions the request's redact list
 * @param array  $edit    the recipe of pg_image_edit_validate()
 * @param string $prefix  path the mask files start with
 * @return array|false the engine's redact list (mode, block, sigma, x, y, w,
 *                     h, mask), an empty one for none; false when refused
 *                     (masks already written are removed)
 */
function pg_image_editor_take_redactions($regions, $edit, $prefix)
{
    if (($regions === null) || ($regions === array())) {
        return array();
    }

    if (!is_array($regions) || (count($regions) > 50)) {
        return false;
    }

    $crop_w = !empty($edit['crop']) ? $edit['crop']['w'] : $edit['space_w'];
    $scale = $edit['out_w'] / max(1, $crop_w);
    $space_max = max($edit['space_w'], $edit['space_h']);
    $list = array();

    foreach (array_values($regions) as $i => $region) {
        $mode = (is_array($region) && isset($region['mode'])) ? $region['mode'] : null;
        $strength = isset($region['strength']) ? pg_image_edit_number($region['strength'], 1, 100) : null;
        $x = isset($region['x']) ? pg_image_edit_number($region['x'], 0, $edit['out_w'] - 1) : null;
        $y = isset($region['y']) ? pg_image_edit_number($region['y'], 0, $edit['out_h'] - 1) : null;
        $path = $prefix . '.' . $i . '.redact.tmp';
        $size = (!is_string($mode) || !in_array($mode, pg_image_edit_redact_modes(), true) || ($strength === null) || ($x === null) || ($y === null) || ($x != floor($x)) || ($y != floor($y)) || !is_string(isset($region['mask']) ? $region['mask'] : null) || (strncmp($region['mask'], 'data:image/png;', 15) !== 0))
            ? false
            : pg_image_editor_take_picture($region['mask'], $path);

        if (!$size || ($x + $size[0] > $edit['out_w']) || ($y + $size[1] > $edit['out_h'])) {
            if ($size) {
                @unlink($path);
            }

            pg_image_editor_drop_redactions($list);
            return false;
        }

        $sizes = pg_image_edit_redact_sizes($strength, $space_max, $scale);
        $list[] = array('mode' => $mode, 'block' => $sizes['block'], 'sigma' => $sizes['sigma'], 'x' => (int) $x, 'y' => (int) $y, 'w' => (int) $size[0], 'h' => (int) $size[1], 'mask' => $path);
    }

    return $list;
}

// The mask files of pg_image_editor_take_redactions(), removed.
function pg_image_editor_drop_redactions($list)
{
    foreach ((array) $list as $region) {
        @unlink($region['mask']);
    }
}

// -- Versions (pg_image_editor_versions.js) --
// A replace save keeps versions of the file in file_versions (2026.11.0,
// docs/memory/ui/image-editor-versions.md): a base row holds pixels and no
// document (version 1, the original, copied before the first save writes
// over the file; later ones where the file changed outside the editor); a
// save row holds the result pixels and the document, whose source.base names
// the base row it was drawn on. Opening loads that base and the document, so
// layers and texts edit again. The pixels lie under data/file_versions/,
// served only through this API.

/**
 * The versions drawer: labels.
 *
 * @return array key => translated text
 */
function pg_image_editor_strings_versions()
{
    return array(
        'versions'          => lang('Versions'),
        'versionsIntro'     => lang('Every save becomes a new version. Texts and shapes are kept as layers, so they can be changed later.'),
        'versionN'          => lang('Version {n}'),
        'versionLatest'     => lang('Latest'),
        'versionOriginal'   => lang('Original upload'),
        'versionSaved'      => lang('Saved in the image editor'),
        'versionRestored'   => lang('Restored from an earlier version'),
        'versionRedacted'   => lang('Saved with redaction'),
        'versionYou'        => lang('You'),
        'versionRestore'    => lang('Restore'),
        'versionRestoreAsk' => lang('Discard changes and restore'),
        'versionDelete'     => lang('Delete version'),
        'versionPreview'    => lang('Preview'),
        'versionPreviewing' => lang('Previewing version {n}'),
        'versionEndPreview' => lang('Back to editing'),
        'versionsNone'      => lang('No versions yet. The first save keeps the original.'),
        'versionsError'     => lang('The versions could not be loaded.'),
        'versionRestoredMsg' => lang('Version {n} restored.'),
        'versionsKeep'      => lang('The original and the last 10 saves are kept.'));
}

// How many saves are kept besides version 1, the original (#4203).
function pg_file_versions_keep()
{
    return 10;
}

/**
 * The version ids the retention removes: every row but version 1, the newest
 * pg_file_versions_keep() and the base rows those are drawn on (a save whose
 * base went would reopen with its layers drawn in). Pure.
 *
 * @param array $rows version_id, version_no and base_no (the version_no of
 *                    the base row it is drawn on; its own for a base row, 0
 *                    when unknown) of one file's rows
 * @return int[] version ids, oldest first
 */
function pg_file_versions_pruned($rows)
{
    $saves = array();
    $bases = array();

    foreach ($rows as $row) {
        if ((int) $row['version_no'] > 1) {
            $saves[(int) $row['version_no']] = $row;
        }
    }

    krsort($saves);

    foreach (array_slice($saves, 0, pg_file_versions_keep(), true) as $row) {
        $bases[] = isset($row['base_no']) ? (int) $row['base_no'] : 0;
    }

    $removed = array();

    foreach (array_slice($saves, pg_file_versions_keep(), null, true) as $no => $row) {
        if (!in_array($no, $bases, true)) {
            $removed[$no] = (int) $row['version_id'];
        }
    }

    ksort($removed);

    return array_values($removed);
}

/**
 * A version's file name under data/file_versions/: the file, the version and
 * a digest, so no name repeats and nothing a user typed reaches the path.
 * Pure.
 *
 * @return string '' for a part that cannot be used
 */
function pg_file_versions_stored_name($file_id, $version_no, $format, $digest)
{
    $format = strtolower((string) $format);

    if (((int) $file_id <= 0) || ((int) $version_no <= 0) || !preg_match('/^[a-z0-9]{1,8}$/', $format) || !preg_match('/^[a-f0-9]{8,}$/', (string) $digest)) {
        return '';
    }

    return (int) $file_id . '-' . (int) $version_no . '-' . substr((string) $digest, 0, 16) . '.' . $format;
}

/**
 * Whether a document may be kept in a version row: at most 1 MB and no
 * picture inside it (a pasted picture is a data: URL). One that may not is
 * kept as a base row: its pixels, the layers drawn in. Pure.
 */
function pg_file_versions_doc_storable($json)
{
    $json = (string) $json;

    return ($json !== '') && (strlen($json) <= 1048576) && !preg_match('#data:[a-z]+\\\\?/[a-z0-9.+-]+;base64,#i', $json);
}

/**
 * The version_no of the base row a version was drawn on: its own for a base
 * row, the document's source.base for a save row, 0 when unknown. Pure.
 */
function pg_file_versions_base_no($row)
{
    if ((string) $row['doc_json'] === '') {
        return (int) $row['version_no'];
    }

    $doc = json_decode((string) $row['doc_json'], true);

    return (is_array($doc) && isset($doc['source']['base']) && is_int($doc['source']['base'])) ? $doc['source']['base'] : 0;
}

/**
 * The document a save row keeps: the browser's (decoded as objects), with
 * the base it was drawn on, as JSON; '' when it may not be kept
 * (pg_file_versions_doc_storable()). Pure.
 */
function pg_file_versions_doc_json($doc, $base_no)
{
    // An object from json_decode() without assoc keeps an empty {} an object;
    // as an array it would come back as [] and lose what a tool adds to it.
    if (!is_object($doc) || !isset($doc->source) || !is_object($doc->source)) {
        return '';
    }

    $doc = clone $doc;
    $doc->source = clone $doc->source;
    $doc->source->base = (int) $base_no;
    $json = json_encode($doc, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    return pg_file_versions_doc_storable($json) ? $json : '';
}

function pg_file_versions_dir()
{
    return PG_FUNCTIONS_DIR . '/data/file_versions';
}

// Before the 2026.11.0 upgrade has run there is no table: saves keep no
// versions.
function pg_file_versions_ready()
{
    return pg_schema_has('file_versions');
}

/**
 * One file's versions, oldest first, without their documents.
 *
 * @return array rows
 */
function pg_file_versions_rows($file_id)
{
    $rows = db_items("SELECT version_id, file_id, version_no, doc_hash, stored_name, format, width, height, size, redacted, user_id, source, created_at, (doc_json <> '') AS has_doc FROM file_versions WHERE file_id = '" . (int) $file_id . "' ORDER BY version_no");

    return is_array($rows) ? $rows : array();
}

// One version of a file with its document, or null.
function pg_file_versions_row($file_id, $version_id)
{
    $row = db_item("SELECT * FROM file_versions WHERE file_id = '" . (int) $file_id . "' AND version_id = '" . (int) $version_id . "' LIMIT 1");

    return $row ? $row : null;
}

function pg_file_versions_newest($file_id)
{
    $row = db_item("SELECT * FROM file_versions WHERE file_id = '" . (int) $file_id . "' ORDER BY version_no DESC LIMIT 1");

    return $row ? $row : null;
}

function pg_file_versions_path($row)
{
    return pg_file_versions_dir() . '/' . basename((string) $row['stored_name']);
}

// Whether the file holds the version's pixels, byte for byte: false once
// the file was changed outside the editor (rotated, optimized, uploaded over).
function pg_file_versions_matches_file($row, $path)
{
    $version = pg_file_versions_path($row);

    clearstatcache();

    return is_file($path) && is_file($version) && ((int) @filesize($path) === (int) @filesize($version)) && (sha1_file($path) === sha1_file($version));
}

/**
 * A new version of the file from the picture at $source_path, copied under
 * data/file_versions/.
 *
 * @param int    $file_id
 * @param string $source_path the pixels
 * @param string $doc_json    '' for a base row
 * @param string $source      save, restore, redact, copy or new
 * @param int    $user_id
 * @param bool   $redacted a save that burned a redaction in
 * @return array|null the row written
 */
function pg_file_versions_add($file_id, $source_path, $doc_json, $source, $user_id, $redacted = false)
{
    $size = @getimagesize($source_path);
    $bytes = (int) @filesize($source_path);
    $formats = array(IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp', IMAGETYPE_BMP => 'bmp');

    if (defined('IMAGETYPE_AVIF')) {
        $formats[IMAGETYPE_AVIF] = 'avif';
    }

    if (!$size || ($bytes <= 0) || !isset($formats[(int) $size[2]])) {
        return null;
    }

    $dir = pg_file_versions_dir();

    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        return null;
    }

    $version_no = (int) db_value("SELECT MAX(version_no) FROM file_versions WHERE file_id = '" . (int) $file_id . "'") + 1;
    $name = pg_file_versions_stored_name($file_id, $version_no, $formats[(int) $size[2]], sha1(uniqid((string) mt_rand(), true) . $source_path));
    $path = $dir . '/' . $name;

    if (($name === '') || !@copy($source_path, $path)) {
        return null;
    }

    $saved = db_q("INSERT INTO file_versions (file_id, version_no, doc_json, doc_hash, stored_name, format, width, height, size, redacted, user_id, source, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UNIX_TIMESTAMP())", array(
        (int) $file_id, $version_no, (string) $doc_json, ($doc_json === '') ? '' : sha1($doc_json), $name, $formats[(int) $size[2]],
        (int) $size[0], (int) $size[1], $bytes, $redacted ? 1 : 0, (int) $user_id, (string) $source));

    if (!$saved) {
        @unlink($path);
        return null;
    }

    return pg_file_versions_row($file_id, mysqli_insert_id(db::$con));
}

function pg_file_versions_delete_row($row)
{
    db("DELETE FROM file_versions WHERE version_id = '" . (int) $row['version_id'] . "'");
    @unlink(pg_file_versions_path($row));
}

// The retention: the original, the last pg_file_versions_keep() saves and
// the base rows they are drawn on.
function pg_file_versions_prune($file_id)
{
    $rows = pg_file_versions_rows($file_id);

    if (count($rows) <= pg_file_versions_keep() + 1) {
        return;
    }

    foreach ($rows as $i => $row) {
        $rows[$i]['base_no'] = empty($row['has_doc'])
            ? (int) $row['version_no']
            : pg_file_versions_base_no(array('version_no' => $row['version_no'], 'doc_json' => (string) db_value("SELECT doc_json FROM file_versions WHERE version_id = '" . (int) $row['version_id'] . "'")));
    }

    $ids = pg_file_versions_pruned($rows);

    foreach ($rows as $row) {
        if (in_array((int) $row['version_id'], $ids, true)) {
            pg_file_versions_delete_row($row);
        }
    }
}

/**
 * A deleted file takes its versions along: the rows and their pictures.
 * Called where a files row is deleted for good (File Manager, Recycle Bin,
 * the file and design file screens).
 */
function pg_file_versions_delete_file($file_id)
{
    if (((int) $file_id <= 0) || !pg_file_versions_ready()) {
        return;
    }

    foreach (pg_file_versions_rows($file_id) as $row) {
        pg_file_versions_delete_row($row);
    }
}

// When the sweep of versions whose file is gone last ran (data/temp).
function pg_file_versions_sweep_file()
{
    return PG_FUNCTIONS_DIR . '/data/temp/file_versions_sweep.json';
}

// Whether the daily sweep is owed: it has not run in the last day.
function pg_file_versions_sweep_due()
{
    if (!pg_file_versions_ready()) {
        return false;
    }

    $state = @json_decode((string) @file_get_contents(pg_file_versions_sweep_file()), true);
    $done = (is_array($state) && isset($state['done_at'])) ? (int) $state['done_at'] : 0;

    return $done < (time() - 86400);
}

/**
 * The daily pass (job.php): the versions of files deleted where no direct
 * call took them along (a design file, a form, comment, chat or Workspace
 * attachment), rows and pictures, within the time budget. Writes the state
 * file when through.
 *
 * @param int $seconds
 * @return int rows removed
 */
function pg_file_versions_sweep($seconds = 5)
{
    if (!pg_file_versions_ready()) {
        return 0;
    }

    $removed = 0;
    $deadline = microtime(true) + max(1, (int) $seconds);
    $through = false;

    while (microtime(true) < $deadline) {
        $rows = db_items("SELECT file_versions.version_id, file_versions.stored_name FROM file_versions LEFT JOIN files ON files.id = file_versions.file_id WHERE files.id IS NULL LIMIT 200");

        if (!is_array($rows) || !$rows) {
            $through = is_array($rows);
            break;
        }

        foreach ($rows as $row) {
            pg_file_versions_delete_row($row);
            $removed++;
        }
    }

    if ($through) {
        @file_put_contents(pg_file_versions_sweep_file(), json_encode(array('done_at' => time())));
    }

    return $removed;
}

// The bytes the files and their versions take, for Disk Usage.
function pg_files_disk_usage()
{
    $bytes = (float) db_value("SELECT SUM(size) FROM files");

    if (pg_file_versions_ready()) {
        $bytes += (float) db_value("SELECT SUM(size) FROM file_versions");
    }

    return $bytes;
}

/**
 * What opening a file needs from its versions: how many there are, and when
 * the file still holds the newest save, that save's document and the base
 * row it was drawn on.
 *
 * @return array count, doc (object or null), base (version id, 0 for the file
 *               itself)
 */
function pg_file_versions_open_state($file_id, $path)
{
    $state = array('count' => 0, 'doc' => null, 'base' => 0);

    if (!pg_file_versions_ready()) {
        return $state;
    }

    $state['count'] = count(pg_file_versions_rows($file_id));
    $newest = pg_file_versions_newest($file_id);

    if (!$newest || ((string) $newest['doc_json'] === '') || !pg_file_versions_matches_file($newest, $path)) {
        return $state;
    }

    // Decoded as objects, so an empty {} goes back to the browser as one.
    $doc = json_decode((string) $newest['doc_json']);
    $base_no = pg_file_versions_base_no($newest);
    $base = ($base_no > 0) ? db_item("SELECT * FROM file_versions WHERE file_id = '" . (int) $file_id . "' AND version_no = '" . $base_no . "' AND doc_json = '' LIMIT 1") : null;

    // A base the retention removed leaves the save's pixels: the file opens
    // as it is, the layers drawn in.
    if (!is_object($doc) || !$base || !is_file(pg_file_versions_path($base))) {
        return $state;
    }

    $state['doc'] = $doc;
    $state['base'] = (int) $base['version_id'];

    return $state;
}

/**
 * Before a replace save writes over the file: the base row its document is
 * drawn on, added when needed so the pixels being written over stay.
 *
 *   base_id > 0  the version the browser loaded; the file, when it was
 *                changed outside the editor since its newest version, is
 *                kept first as a base row of its own
 *   base_id 0    the file as it is; kept as a base row unless the newest
 *                version is already a base row holding it (version 1 at the
 *                first save)
 *
 * @return array|null the base row (path of its pixels in 'path'), or null
 *                    when it cannot be found or written
 */
function pg_file_versions_before_save($file_id, $path, $base_id, $user_id)
{
    $newest = pg_file_versions_newest($file_id);
    $same = $newest && pg_file_versions_matches_file($newest, $path);

    if ((int) $base_id > 0) {
        $base = pg_file_versions_row($file_id, $base_id);

        if (!$base || ((string) $base['doc_json'] !== '') || !is_file(pg_file_versions_path($base))) {
            return null;
        }

        if ($newest && !$same && !pg_file_versions_add($file_id, $path, '', 'save', $user_id)) {
            return null;
        }

        return $base;
    }

    if ($same && ((string) $newest['doc_json'] === '')) {
        return $newest;
    }

    return pg_file_versions_add($file_id, $path, '', 'save', $user_id);
}

/**
 * After a replace save: the result as a save row with its document, unless
 * the newest version already holds the same document; then the retention.
 */
function pg_file_versions_after_save($file_id, $path, $doc, $base, $user_id, $redacted = false)
{
    $json = pg_file_versions_doc_json($doc, (int) $base['version_no']);
    $newest = pg_file_versions_newest($file_id);

    if (($json !== '') && $newest && ((string) $newest['doc_hash'] === sha1($json))) {
        return;
    }

    pg_file_versions_add($file_id, $path, $json, $redacted ? 'redact' : 'save', $user_id, $redacted);
    pg_file_versions_prune($file_id);
}

// A version as the drawer lists it: kind original, restored, redacted or
// saved; user '' for the one asking.
function pg_file_versions_entry($row, $current, $user)
{
    $user_id = (int) $row['user_id'];

    if ((int) $row['version_no'] === 1) {
        $kind = 'original';
    } elseif ((string) $row['source'] === 'restore') {
        $kind = 'restored';
    } elseif ((int) $row['redacted'] === 1) {
        $kind = 'redacted';
    } else {
        $kind = 'saved';
    }

    return array(
        'id'      => (int) $row['version_id'],
        'no'      => (int) $row['version_no'],
        'kind'    => $kind,
        'when'    => get_absolute_time(array('timestamp' => (int) $row['created_at'], 'format' => 'plain_text')),
        'user'    => (!$user_id || ($user_id === (int) $user['id'])) ? '' : (string) db_value("SELECT user_username FROM user WHERE user_id = '" . $user_id . "' LIMIT 1"),
        'width'   => (int) $row['width'],
        'height'  => (int) $row['height'],
        'size'    => convert_bytes_to_string((int) $row['size']),
        'current' => $current);
}

/**
 * Action versions { file_id }: the file's versions, newest first.
 *
 * @return array array(payload, HTTP status)
 */
function pg_image_editor_action_versions($request, $user)
{
    $file = pg_image_editor_file(isset($request['file_id']) ? $request['file_id'] : 0, $user);

    if ($file['refusal']) {
        return $file['refusal'];
    }

    $list = array();
    $rows = pg_file_versions_ready() ? pg_file_versions_rows($file['row']['id']) : array();
    $newest = $rows ? $rows[count($rows) - 1] : null;
    $current = $newest && pg_file_versions_matches_file($newest, $file['path']);

    foreach (array_reverse($rows) as $row) {
        $entry = pg_file_versions_entry($row, $current && ($row === $newest), $user);
        $entry['delete'] = $file['replace'] && ((int) $row['version_no'] > 1) && ($row !== $newest);
        $list[] = $entry;
    }

    return array(array('status' => 'success', 'versions' => $list, 'restore' => $file['replace']), 200);
}

/**
 * Action picture { file_id, version_id }: a version's pixels, for the
 * drawer's preview and for opening the base a document was drawn on.
 *
 * @return array array(payload, HTTP status); payload stream names the file
 *               image_editor_api.php sends instead of JSON
 */
function pg_image_editor_action_picture($request, $user)
{
    $file = pg_image_editor_file(isset($request['file_id']) ? $request['file_id'] : 0, $user);

    if ($file['refusal']) {
        return $file['refusal'];
    }

    $row = pg_file_versions_ready() ? pg_file_versions_row($file['row']['id'], isset($request['version_id']) ? $request['version_id'] : 0) : null;
    $path = $row ? pg_file_versions_path($row) : '';
    $size = ($path !== '') ? @getimagesize($path) : false;

    if (!$size) {
        return pg_image_editor_refusal(lang('Sorry, we could not find the file.'));
    }

    return array(array('status' => 'success', 'stream' => $path, 'type' => (string) $size['mime']), 200);
}

/**
 * Action restore { file_id, version_id }: the version's pixels become the
 * file again (same id and name) and a new version records it; answers what
 * opening the file answers, so the editor reloads it.
 *
 * @return array array(payload, HTTP status)
 */
function pg_image_editor_action_restore($request, $user)
{
    $file = pg_image_editor_file(isset($request['file_id']) ? $request['file_id'] : 0, $user);

    if ($file['refusal']) {
        return $file['refusal'];
    }

    if (!$file['replace'] || !pg_file_versions_ready()) {
        return pg_image_editor_refusal(lang('Access denied'), 403);
    }

    $file_id = (int) $file['row']['id'];
    $row = pg_file_versions_row($file_id, isset($request['version_id']) ? $request['version_id'] : 0);

    if (!$row || !is_file(pg_file_versions_path($row))) {
        return pg_image_editor_refusal(lang('Sorry, we could not find the file.'));
    }

    $newest = pg_file_versions_newest($file_id);

    // The file changed outside the editor since its newest version: kept
    // first, as a save would keep it.
    if ($newest && !pg_file_versions_matches_file($newest, $file['path']) && !pg_file_versions_add($file_id, $file['path'], '', 'save', $user['id'])) {
        return pg_image_editor_refusal(lang('Sorry, the file could not be saved.'));
    }

    $data = @file_get_contents(pg_file_versions_path($row));

    if (!is_string($data) || !pg_image_editor_put($file['path'], $data)) {
        return pg_image_editor_refusal(lang('Sorry, the file could not be saved.'));
    }

    $record = pg_image_editor_record('replace', (string) $file['row']['name'], (int) $file['row']['folder'], (string) $file['row']['type'], $file['row'], $user);

    if ($record[0]['status'] !== 'success') {
        return $record;
    }

    if ((int) $row['version_id'] !== (int) ($newest ? $newest['version_id'] : 0)) {
        pg_file_versions_add($file_id, $file['path'], (string) $row['doc_json'], 'restore', $user['id'], (int) $row['redacted'] === 1);
        pg_file_versions_prune($file_id);
    }

    $state = pg_file_versions_open_state($file_id, $file['path']);

    return array(array(
        'status'   => 'success',
        'no'       => (int) $row['version_no'],
        'file'     => $record[0],
        'url'      => $record[0]['url'],
        'versions' => $state['count'],
        'doc'      => $state['doc'],
        'base'     => $state['base']), 200);
}

/**
 * Action delete_version { file_id, version_id }: removes a version; never
 * version 1, the original, nor the newest one, which the file holds.
 *
 * @return array array(payload, HTTP status)
 */
function pg_image_editor_action_delete_version($request, $user)
{
    $file = pg_image_editor_file(isset($request['file_id']) ? $request['file_id'] : 0, $user);

    if ($file['refusal']) {
        return $file['refusal'];
    }

    if (!$file['replace'] || !pg_file_versions_ready()) {
        return pg_image_editor_refusal(lang('Access denied'), 403);
    }

    $file_id = (int) $file['row']['id'];
    $row = pg_file_versions_row($file_id, isset($request['version_id']) ? $request['version_id'] : 0);
    $newest = pg_file_versions_newest($file_id);

    if (!$row || ((int) $row['version_no'] === 1) || ((int) $row['version_id'] === (int) $newest['version_id'])) {
        return pg_image_editor_refusal(lang('Invalid request.'));
    }

    pg_file_versions_delete_row($row);

    return array(array('status' => 'success', 'versions' => count(pg_file_versions_rows($file_id))), 200);
}

// -- Canvas and artboard (pg_image_editor_artboard.js) --

/**
 * The canvas, artboard and Image tools: labels.
 *
 * @return array key => translated text
 */
function pg_image_editor_strings_artboard()
{
    return array();
}

// -- Export (pg_image_editor_export.js) --

/**
 * The export settings: labels.
 *
 * @return array key => translated text
 */
function pg_image_editor_strings_export()
{
    return array();
}

// -- Vector (pg_image_editor_vector.js) --

/**
 * The vector pen: labels.
 *
 * @return array key => translated text
 */
function pg_image_editor_strings_vector()
{
    return array(
        'hintVector'     => lang('Click: point. Drag: curve. Enter: finish. Esc: cancel.'),
        'hintNodes'      => lang('Drag points and handles. Double-click the path: add a point. Delete: remove the point. Esc: done.'),
        'vectorNote'     => lang('Click to place corner points, drag to pull curves. Click the first point to close the path; double-click or Enter ends it.'),
        'nodesNote'      => lang('Alt while dragging a handle breaks a smooth point into a corner.'),
        'layer_path'     => lang('Vector path'),
        'editPoints'     => lang('Edit points'),
        'stopEditing'    => lang('Stop editing'),
        'knotSmooth'     => lang('Smooth'),
        'knotSharp'      => lang('Sharp'),
        'pathClosed'     => lang('Closed'),
        'finishPath'     => lang('Finish'),
        'convertToPath'  => lang('Convert to path'),
    );
}

// -- Callers: an endpoint of their own, a page's content --

/**
 * An edit of the picture at $path, drawn as a copy is (first frame, the
 * file's own format), for an endpoint that stores the result itself
 * (workspace_file_save.php, save.url of the editor). The request is the
 * editor's save body: doc, overlay and, without Imagick, colour.
 *
 * @param string $path
 * @param array  $request
 * @return array error: '' or why not; data: the bytes; extension: jpg,
 *               png, webp or gif
 */
function pg_image_editor_render($path, $request)
{
    $check = pg_image_edit_validate(isset($request['doc']) ? $request['doc'] : null);
    $refused = array('error' => lang('Invalid request.'), 'data' => '', 'extension' => '');
    $size = is_file($path) ? @getimagesize($path) : false;
    $types = array(IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif');

    if (($check['error'] !== '') || !$check['edit'] || !$size || !isset($types[(int) $size[2]])) {
        return $refused;
    }

    @set_time_limit(120);

    $edit = $check['edit'];
    $edit['first_frame'] = true;
    // Beside the picture, as a save keeps its temporary files.
    $stem = $path . '.' . (function_exists('getmypid') ? getmypid() : uniqid());
    $overlay = '';
    $layer = '';
    $source = $path;

    if (!empty($request['overlay'])) {
        $overlay = $stem . '.overlay.tmp';

        if (!pg_image_editor_take_png($request['overlay'], $overlay, $edit['out_w'], $edit['out_h'])) {
            return $refused;
        }

        $edit['overlay'] = $overlay;
    }

    // Without Imagick the browser sends the colour step done, as for a save.
    if (!empty($request['colour']) && !empty($edit['color'])) {
        $layer = $stem . '.colour.tmp';
        $taken = pg_image_editor_take_picture($request['colour'], $layer);
        $width = ($edit['rot'] % 180) ? $edit['space_h'] : $edit['space_w'];
        $height = ($edit['rot'] % 180) ? $edit['space_w'] : $edit['space_h'];

        if (!$taken || ($taken[0] > $width + 0.5) || ($taken[1] > $height + 0.5) || (abs($taken[0] - $taken[1] * $width / $height) > 1.5)) {
            @unlink($layer);
            @unlink($overlay);
            return $refused;
        }

        $source = $layer;
        $edit['color'] = null;
        $edit['type'] = (int) $size[2];

    } elseif (!empty($edit['color']) && !class_exists('Imagick')) {
        @unlink($overlay);
        return $refused;
    }

    $report = pg_image_edit_apply($source, $edit, array('quality' => 92));

    @unlink($overlay);
    @unlink($layer);

    if (($report['status'] !== 'success') || !is_string($report['data'])) {
        return array('error' => pg_image_editor_report_message($report, basename($path)), 'data' => '', 'extension' => '');
    }

    $made = @getimagesizefromstring($report['data']);

    if (!$made || !isset($types[(int) $made[2]])) {
        return array('error' => lang('Sorry, the file could not be saved.'), 'data' => '', 'extension' => '');
    }

    return array('error' => '', 'data' => $report['data'], 'extension' => $types[(int) $made[2]]);
}

/**
 * Where a page's picture lives, by the object type of its edit button
 * (includes/fn/content.php, frontend.src.js): the table, its id column and
 * the column holding the content. A product's column is chosen by the
 * button (image_name, details, else full_description).
 *
 * @return array type => array(table, id column, content column)
 */
function pg_image_editor_content_places()
{
    return array(
        'ad'                   => array('ads', 'id', 'content'),
        'calendar_event'       => array('calendar_events', 'id', 'full_description'),
        'cregion'              => array('cregion', 'cregion_id', 'cregion_content'),
        'pregion'              => array('pregion', 'pregion_id', 'pregion_content'),
        'system_region_header' => array('page', 'page_id', 'system_region_header'),
        'system_region_footer' => array('page', 'page_id', 'system_region_footer'),
        'product'              => array('products', 'id', 'full_description'),
        'product_group'        => array('product_groups', 'id', 'full_description'),
        'form_field'           => array('form_fields', 'id', 'information'));
}

/**
 * Whether a user of the user role (3) may change the content of an object;
 * the other roles may. The rules the page editor applies to each kind.
 *
 * @return bool
 */
function pg_image_editor_content_allowed($type, $id, $user)
{
    if ((int) $user['role'] < 3) {
        return true;
    }

    switch ($type) {
        case 'ad':
            return in_array((int) db_value("SELECT ad_region_id FROM ads WHERE id = '" . e($id) . "'"), array_map('intval', get_items_user_can_edit('ad_regions', $user['id'])), true);

        case 'pregion':
            return check_edit_access((int) db_value("SELECT page.page_folder FROM pregion LEFT JOIN page ON pregion.pregion_page = page.page_id WHERE pregion.pregion_id = '" . e($id) . "'"));

        case 'system_region_header':
        case 'system_region_footer':
            return check_edit_access((int) db_value("SELECT page_folder FROM page WHERE page_id = '" . e($id) . "'"));

        case 'cregion':
            return in_array((int) $id, array_map('intval', get_items_user_can_edit('common_regions', $user['id'])), true);

        case 'calendar_event':
            return $user['manage_calendars'] && validate_calendar_event_access($id);

        case 'product':
        case 'product_group':
            return (bool) $user['manage_ecommerce'];

        case 'form_field':
            return check_edit_access((int) db_value("SELECT page.page_folder FROM form_fields LEFT JOIN page ON form_fields.page_id = page.page_id WHERE form_fields.id = '" . e($id) . "'"));
    }

    return false;
}

/**
 * Action content { object_type, object_id, column, from, to }: a copy saved
 * from a page's picture (to: its file id) takes the place of the picture it
 * was made from (from: that file's name) in the content of the page region,
 * ad, product or other object the edit button named.
 *
 * @return array array(payload, HTTP status)
 */
function pg_image_editor_action_content($request, $user)
{
    $type = isset($request['object_type']) ? (string) $request['object_type'] : '';
    $id = isset($request['object_id']) && is_scalar($request['object_id']) ? (int) $request['object_id'] : 0;
    $column = isset($request['column']) ? (string) $request['column'] : '';
    $from = isset($request['from']) && is_string($request['from']) ? $request['from'] : '';
    $places = pg_image_editor_content_places();
    $copy = pg_image_editor_file(isset($request['to']) ? $request['to'] : 0, $user);

    if ($copy['refusal']) {
        return $copy['refusal'];
    }

    if (!isset($places[$type]) || ($id <= 0) || ($from === '') || !db_value("SELECT id FROM files WHERE name = '" . e($from) . "'")) {
        return pg_image_editor_refusal(lang('Invalid request.'));
    }

    // A region made a shared component draws the component; its row is no
    // longer read.
    if (($type === 'cregion') && in_array($id, pg_common_region_converted_ids(), true)) {
        return array(array('status' => 'success'), 200);
    }

    if (!pg_image_editor_content_allowed($type, $id, $user)) {
        log_activity(lang('access denied to update content with image from ImageEditor because user does not have access to edit it'), isset($_SESSION['sessionusername']) ? $_SESSION['sessionusername'] : '');
        return pg_image_editor_refusal(lang('Access denied'), 403);
    }

    list($table, $key, $field) = $places[$type];
    $name = (string) $copy['row']['name'];

    if (($type === 'product') || ($type === 'product_group')) {
        $field = in_array($column, array('image_name', 'details'), true) ? $column : 'full_description';
    }

    if ($field === 'image_name') {
        $content = $name;
    } else {
        $content = update_image_in_content((string) db_value("SELECT `$field` FROM `$table` WHERE `$key` = '" . e($id) . "'"), $from, $name);
    }

    if (!db_q("UPDATE `$table` SET `$field` = ? WHERE `$key` = ?", array($content, $id))) {
        return pg_image_editor_refusal(lang('Sorry, the file could not be saved.'));
    }

    return array(array('status' => 'success'), 200);
}
