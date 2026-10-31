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
