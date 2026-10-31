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
 * Every label of the editor, by the key the script asks for. The script holds
 * no text of its own, so the panel's language reaches it through lang() here
 * and tools/check_lang.php sees each key.
 *
 * @return array key => translated text
 */
function pg_image_editor_strings()
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
        'noLayers'       => lang('No layers yet.'),
        'closePanel'     => lang('Close the panel'),
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
        'out_h'      => max(1, (int) round($out_h)));

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
    $file = pg_image_editor_file(isset($request['file_id']) ? $request['file_id'] : 0, $user);

    if ($file['refusal']) {
        return $file['refusal'];
    }

    return array(array(
        'status' => 'success',
        'file'   => array(
            'id'      => (int) $file['row']['id'],
            'name'    => (string) $file['row']['name'],
            // Stamped: the browser may keep a file for a week under one address.
            'url'     => PATH . encode_url_path($file['row']['name']) . '?v=' . (int) @filemtime($file['path']),
            'replace' => $file['replace'],
            'notice'  => $file['animated'] ? lang('An animated image is saved as a still copy.') : '')), 200);
}

/**
 * A PNG the browser drew (the overlay, an artboard) as a data: URL, decoded
 * onto the disk in pieces, as base64_decode() of the whole string would hold
 * it three times over; kept only when it is a PNG of exactly w x h.
 *
 * @return bool
 */
function pg_image_editor_take_png($data, $destination, $width, $height)
{
    $data = is_string($data) ? $data : '';
    $prefix = 'data:image/png;base64,';

    if (strncmp($data, $prefix, strlen($prefix)) !== 0) {
        return false;
    }

    $handle = @fopen($destination, 'wb');

    if ($handle === false) {
        return false;
    }

    // A multiple of four: every piece is whole base64 groups, and only the
    // last one may carry padding.
    for ($offset = strlen($prefix), $length = strlen($data); $offset < $length; $offset += 65532) {
        $chunk = base64_decode(substr($data, $offset, 65532), true);

        if (($chunk === false) || (fwrite($handle, $chunk) === false)) {
            fclose($handle);
            @unlink($destination);
            return false;
        }
    }

    fclose($handle);

    $size = @getimagesize($destination);

    if (!$size || ((int) $size[2] !== IMAGETYPE_PNG) || ((int) $size[0] !== (int) $width) || ((int) $size[1] !== (int) $height)) {
        @unlink($destination);
        return false;
    }

    return true;
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

        if (!check_edit_access($folder)) {
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

    // Encoded for quality, as the file manager's rotate button does: the
    // operator asked for a changed picture, not a lighter file, and a replace
    // is the next edit's original.
    $report = pg_image_edit_apply($file['path'], $edit, array('quality' => 92));

    if ($overlay !== '') {
        @unlink($overlay);
    }

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

    return pg_image_editor_record($mode, $name, (int) $file['row']['folder'], (string) $file['row']['type'], $file['row'], $user);
}

/**
 * One request of image_editor_api.php: its JSON body, checked and answered.
 * Every answer is JSON, refusals included; a page with a "go back" link is
 * a syntax error to fetch().
 *
 *   { token, action: 'open', file_id }
 *       -> { status, file: { id, name, url, replace, notice } }
 *   { token, action: 'save', mode: 'replace'|'copy'|'new', file_id | folder_id,
 *     doc, overlay?, picture? and name? (new) }
 *       -> { status, mode, id, name, folder, url, width, height, size }
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

    $request = json_decode($body, true);

    if (!is_array($request)) {
        return pg_image_editor_refusal(lang('Invalid request.'));
    }

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

    return pg_image_editor_refusal(lang('Invalid request.'));
}
