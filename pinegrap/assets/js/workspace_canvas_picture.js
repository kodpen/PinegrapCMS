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
 * A canvas whose newest version holds a data layer (file.canvas_data)
 * follows its tables: follow() draws the document again in this browser,
 * the data layers from the rows this reader may read (action canvas_data,
 * pg_image_editor_data_card_reads()), and shows that drawing in the card
 * and its image viewer. Nothing is posted or kept: changed rows make no
 * version, and each reader sees only the rows they may read.
 *
 * assets/js/workspace.js calls mount() and follow() from filePreview() with
 * its own helpers; workspace_page_card.js does for a joined card's drawing.
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

    // The rows of each table the canvas's data layers name, read for this
    // reader: { table id: read }, null when they could not be read.
    function fetchReads(url, token) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({ token: token, action: 'canvas_data' })
        }).then(function (res) {
            return res.json();
        }).then(function (data) {
            return (data && (data.status === 'success') && data.reads) ? data.reads : null;
        });
    }

    // The reads a painter holds: one for every table a data layer of the
    // document names, so it never asks the editor's own action; a table
    // with no answer draws as no rows (null).
    function readsFor(doc, reads) {
        var held = {};

        ((doc && Array.isArray(doc.layers)) ? doc.layers : []).forEach(function (l) {
            var key = (l && (l.type === 'data') && l.data) ? String(parseInt(l.data.table, 10) || 0) : '0';

            if (key !== '0') {
                held[key] = (reads && reads[key]) ? reads[key] : null;
            }
        });

        return held;
    }

    // The document drawn as the editor's Save draws an artboard, without
    // opening the editor: a painter on the editor's prototype with only the
    // state its layer types read while loading and painting; its data
    // layers drawn from reads.
    function render(doc, reads) {
        var parts = window.PgImageEditor && window.PgImageEditor.parts;
        var opened = parts ? parts.M.canvasOpenDoc(doc) : null;

        if (!opened) {
            return Promise.resolve(null);
        }

        var painter = Object.create(parts.Editor.prototype);

        painter.doc = opened;
        painter.pictures = {};
        painter.textFonts = {};
        painter.dataReads = readsFor(opened, reads);
        painter.dataAsked = {};
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
        var key = message.id + ':' + (file.canvas_part || '') + ':' + version;

        if (!version || started[key] || !window.pgImageEditor || !window.fetch) {
            return Promise.resolve(null);
        }

        started[key] = true;

        // A drawing added beside a page is its canvas's part, not the
        // message's own file (includes/workspace/canvas_parts.php).
        var url = h.cfg.urls.file_save + '?message=' + encodeURIComponent(message.id)
            + ((file.canvas_part === 'drawing') ? '&part=drawing' : '');

        return Promise.all([window.pgImageEditor.load(), fetchDoc(url, h.cfg.token), file.canvas_data ? fetchReads(url, h.cfg.token) : null]).then(function (loaded) {
            return loaded[1] ? render(loaded[1], loaded[2]) : null;
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

            // The card's own drawing of the reader's rows stays.
            if (!message.file.drawn) {
                img.src = url;
            }

            if (link) {
                link.href = url;
            }
        });
    }

    // Drawings of followed canvases made on this page, by message, part and
    // version: { at, picture (a promise of the data address) }. A card drawn
    // again soon after (the conversation redrawn) shows the same drawing.
    var drawn = {};
    var FRESH_MS = 60000;

    // The canvas drawn again from this reader's rows: a promise of its PNG's
    // data address, null when it could not be drawn here.
    function drawFollowed(message, h) {
        var file = message.file || {};
        var key = message.id + ':' + (file.canvas_part || '') + ':' + String(file.url || '');
        var now = Date.now();
        var url;

        if (drawn[key] && ((now - drawn[key].at) < FRESH_MS)) {
            return drawn[key].picture;
        }

        url = h.cfg.urls.file_save + '?message=' + encodeURIComponent(message.id)
            + ((file.canvas_part === 'drawing') ? '&part=drawing' : '');

        drawn[key] = {
            at: now,
            picture: Promise.all([window.pgImageEditor.load(), fetchDoc(url, h.cfg.token), fetchReads(url, h.cfg.token)]).then(function (loaded) {
                return (loaded[1] && loaded[2]) ? render(loaded[1], loaded[2]) : null;
            }).catch(function () {
                return null;
            })
        };

        drawn[key].picture.then(function (picture) {
            if (!picture) {
                delete drawn[key];
            }
        });

        return drawn[key].picture;
    }

    // A canvas with data layers shows its tables' current rows: drawn once
    // its picture comes into view, in place of the stored picture, and
    // handed to the image viewer as file.drawn. The stored picture stays
    // where the drawing fails.
    function follow(message, img, h) {
        var file = message.file || {};
        var watch;

        if (!file.canvas_data || !window.pgImageEditor || !window.fetch || !img) {
            return;
        }

        function start() {
            drawFollowed(message, h).then(function (picture) {
                if (picture) {
                    file.drawn = picture;
                    img.src = picture;
                }
            });
        }

        if (!window.IntersectionObserver) {
            start();

            return;
        }

        watch = new window.IntersectionObserver(function (seen) {
            if (seen.some(function (x) { return x.isIntersecting; })) {
                watch.disconnect();
                start();
            }
        });
        watch.observe(img);
    }

    window.PGWsCanvasPicture = { draw: draw, mount: mount, render: render, follow: follow, readsFor: readsFor };
}());
