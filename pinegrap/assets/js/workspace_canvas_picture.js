/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the picture of an agent's canvas. An agent writes SVG and the
 * site keeps the canvas's document with a blank picture marked pending
 * (includes/workspace/canvas_svg.php): the server never rasterises SVG. The
 * first browser of a member who may write in the channel that shows such a
 * message (file.canvas_pending, the version to draw) draws the document
 * with the image editor's own renderer (M.canvasOpenDoc(), paintLayers()
 * and drawArtboard() of a painter that opens no dialog) and posts the PNG
 * once to workspace_file_save.php, action canvas_picture. The server takes
 * the first one (ws_canvas_picture_take()) and answers the others 409;
 * nothing is shown to the person either way.
 *
 * assets/js/workspace.js calls draw() from filePreview() with its own
 * helpers.
 *
 * Served minified inside assets/js/workspace.min.js (pg_js_bundles(),
 * includes/fn/output.php); after editing run `php tools/build_js.php`.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    // Message id + version already drawn or being drawn by this page: each
    // is posted at most once, however often the conversation is drawn again.
    var started = {};

    // The canvas's document, as the editor's "Edit the picture" asks for it.
    function fetchDoc(url, token) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({ token: token, action: 'canvas' })
        }).then(function (res) {
            return res.json();
        }).then(function (data) {
            return (data && (data.status === 'success') && data.doc && data.doc.artboard) ? data.doc : null;
        });
    }

    // The document drawn as the editor's Save draws an artboard, without
    // opening the editor: a painter on the editor's prototype with only the
    // state its layer types read while loading and painting.
    function render(doc) {
        var parts = window.PgImageEditor && window.PgImageEditor.parts;
        var opened = parts ? parts.M.canvasOpenDoc(doc) : null;

        if (!opened) {
            return Promise.resolve(null);
        }

        var painter = Object.create(parts.Editor.prototype);

        painter.doc = opened;
        painter.pictures = {};
        painter.textFonts = {};
        painter.layersDirty = true;
        painter.renderView = function () { /* no stage to draw */ };

        return painter.layersReady().then(function () {
            return painter.drawArtboard();
        });
    }

    // Draws and posts the picture of a pending canvas; answers the drawn
    // picture's address, or null when another browser drew it first or it
    // could not be drawn here.
    function draw(message, h) {
        var file = message.file || {};
        var version = parseInt(file.canvas_pending, 10) || 0;
        var key = message.id + ':' + version;

        if (!version || started[key] || !window.pgImageEditor || !window.fetch) {
            return Promise.resolve(null);
        }

        started[key] = true;

        var url = h.cfg.urls.file_save + '?message=' + encodeURIComponent(message.id);

        return Promise.all([window.pgImageEditor.load(), fetchDoc(url, h.cfg.token)]).then(function (loaded) {
            return loaded[1] ? render(loaded[1]) : null;
        }).then(function (picture) {
            if (!picture) {
                return null;
            }

            return fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify({ token: h.cfg.token, action: 'canvas_picture', version: version, picture: picture })
            }).then(function (res) {
                return res.json().catch(function () { return null; });
            }).then(function (data) {
                return (data && (data.status === 'success') && data.url) ? data.url : null;
            });
        }).catch(function () {
            // Another member's browser may still draw it.
            return null;
        });
    }

    // The picture in the conversation shows the drawn one once it is taken.
    function mount(message, img, link, h) {
        draw(message, h).then(function (url) {
            if (!url) {
                return;
            }

            message.file.url = url;
            message.file.canvas_pending = 0;
            img.src = url;

            if (link) {
                link.href = url;
            }
        });
    }

    window.PGWsCanvasPicture = { draw: draw, mount: mount, render: render };
}());
