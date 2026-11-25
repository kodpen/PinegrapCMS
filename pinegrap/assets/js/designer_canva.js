/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Canva start of the New design window (pg_designer_canva_pane()),
 * loaded by assets/js/designer_home.js the first time the start is chosen.
 * No Canva connection: the pages exported from Canva are dropped or chosen
 * here and sent one at a time (designer › canva_page), each stored in the
 * file manager and measured on the server. The pages are tiles in their
 * order; "Next" asks the colours of all of them (canva_colors) and shows
 * the colours card. "Create design" writes the pages as the new design's
 * sketch, each with its picture as the reference under the frame (kept in
 * this browser as pg_proto_sketch_new until the design's first save), and
 * opens the design in the Sketch tool (add_system_style.php?start=canva).
 *
 * The texts arrive translated in the pane's data-texts.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
 */

(function () {
    'use strict';

    var parts = window.sdStartParts = window.sdStartParts || {};

    if (parts.canva) {
        return;
    }

    // The sketch frame's width, the room between two frames and how far
    // right of the design's first page the first frame stands; a reference
    // picture's height in frame units is kept between these two.
    var FRAME = { w: 480, gap: 96, left: 640, minH: 24, maxH: 20000 };

    function esc(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function fill(text, values) {
        return String(text || '').replace(/\{var:(\d+)\}/g, function (all, n) {
            return values[n - 1] != null ? values[n - 1] : '';
        });
    }

    function token() {
        return (typeof software_token !== 'undefined') ? software_token : '';
    }

    function api(body) {
        body.action = 'designer';
        body.token = token();

        return fetch('api.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        }).then(function (response) { return response.json(); });
    }

    // What the browser can tell before sending: '' to send, else the
    // refusal's text key. The server reads the type from the bytes again.
    function refusalOf(file, T) {
        var name = String(file.name || '').toLowerCase();
        var type = String(file.type || '').toLowerCase();

        if (type === 'application/pdf' || /\.pdf$/.test(name)) {
            return 'pdf';
        }
        if (!(type === 'image/png' || type === 'image/jpeg' || /\.(png|jpe?g)$/.test(name))) {
            return 'notPng';
        }
        if (T.bytes > 0 && file.size > T.bytes) {
            return 'tooBig';
        }
        return '';
    }

    // The new design's sketch: one frame a page, side by side right of
    // the design's first page, each with its picture under it.
    function sketchOf(pages) {
        return {
            n: pages.length + 1,
            pages: pages.map(function (p, i) {
                var h = Math.round(FRAME.w * p.height / Math.max(1, p.width));

                return {
                    id: i + 1,
                    name: p.name,
                    x: FRAME.left + i * (FRAME.w + FRAME.gap),
                    y: 0,
                    boxes: [],
                    pic: { src: p.src, h: Math.max(FRAME.minH, Math.min(FRAME.maxH, h)), off: false }
                };
            })
        };
    }

    var state = null;
    var seq = 0;

    function setup(pane, win) {
        var root = pane.querySelector('.sd-cv');

        if (!root) {
            return null;
        }

        var T = {};

        try {
            T = JSON.parse(root.getAttribute('data-texts') || '{}');
        } catch (e) { }

        T.max = parseInt(T.max, 10) || 20;
        T.bytes = parseInt(T.bytes, 10) || 0;

        var s = {
            pane: pane,
            win: win,
            root: root,
            T: T,
            intro: root.firstElementChild,
            head: root.querySelector('.sd-cv-head'),
            tiles: root.querySelector('.sd-cv-tiles'),
            drop: root.querySelector('.sd-cv-drop'),
            input: root.querySelector('.sd-cv-drop input[type="file"]'),
            rows: root.querySelector('.sd-cv-rows'),
            colors: root.querySelector('.sd-cv-colors'),
            foot: win.querySelector('.sd-ns-part-foot'),
            pages: [],
            queue: [],
            busy: null,
            bad: [],
            view: 'add',
            found: [],
            brand: '',
            second: '',
            dragged: null
        };

        s.input.addEventListener('change', function () {
            add(s, s.input.files);
            s.input.value = '';
        });

        s.drop.addEventListener('dragover', function (event) {
            if (event.dataTransfer && Array.prototype.indexOf.call(event.dataTransfer.types || [], 'Files') !== -1) {
                event.preventDefault();
                s.drop.classList.add('is-over');
            }
        });
        s.drop.addEventListener('dragleave', function () {
            s.drop.classList.remove('is-over');
        });
        s.drop.addEventListener('drop', function (event) {
            s.drop.classList.remove('is-over');

            if (event.dataTransfer && event.dataTransfer.files && event.dataTransfer.files.length) {
                event.preventDefault();
                add(s, event.dataTransfer.files);
            }
        });

        s.rows.addEventListener('click', function (event) {
            var stop = event.target.closest('[data-cv-stop]');
            var clear = event.target.closest('[data-cv-clear]');

            if (stop && s.busy) {
                s.busy.xhr.abort();
            } else if (clear) {
                var id = parseInt(clear.getAttribute('data-cv-clear'), 10);
                s.bad = s.bad.filter(function (row) { return row.id !== id; });
                drawRows(s);
            }
        });

        s.tiles.addEventListener('click', function (event) {
            var bin = event.target.closest('[data-cv-remove]');

            if (bin) {
                var at = parseInt(bin.getAttribute('data-cv-remove'), 10);
                s.pages.splice(at, 1);
                drawTiles(s);
                drawFoot(s);
                var next = s.tiles.querySelectorAll('[data-cv-remove]')[Math.min(at, s.pages.length - 1)];
                (next || s.input).focus();
            }
        });

        // Alt and an arrow key move the tile in focus one place.
        s.tiles.addEventListener('keydown', function (event) {
            var tile = event.target.closest('.sd-cv-tile');

            if (!tile || !event.altKey || (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight')) {
                return;
            }

            event.preventDefault();
            var from = parseInt(tile.getAttribute('data-at'), 10);
            var to = from + (event.key === 'ArrowLeft' ? -1 : 1);

            if (to >= 0 && to < s.pages.length) {
                move(s, from, to);
                var moved = s.tiles.querySelector('.sd-cv-tile[data-at="' + to + '"]');

                if (moved) {
                    moved.focus();
                }
            }
        });

        s.tiles.addEventListener('dragstart', function (event) {
            var tile = event.target.closest('.sd-cv-tile');

            if (tile) {
                s.dragged = parseInt(tile.getAttribute('data-at'), 10);
                tile.classList.add('is-dragged');
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', String(s.dragged));
            }
        });
        s.tiles.addEventListener('dragover', function (event) {
            if (s.dragged !== null && event.target.closest('.sd-cv-tile')) {
                event.preventDefault();
            }
        });
        s.tiles.addEventListener('drop', function (event) {
            var tile = event.target.closest('.sd-cv-tile');

            if (s.dragged !== null && tile) {
                event.preventDefault();
                move(s, s.dragged, parseInt(tile.getAttribute('data-at'), 10));
            }
            s.dragged = null;
        });
        s.tiles.addEventListener('dragend', function () {
            s.dragged = null;
            drawTiles(s);
        });

        s.colors.addEventListener('click', function (event) {
            var dot = event.target.closest('[data-cv-color]');

            if (dot) {
                s[dot.getAttribute('data-cv-for')] = dot.getAttribute('data-cv-color');
                drawColors(s, dot.getAttribute('data-cv-for'));
            }
        });

        return s;
    }

    function move(s, from, to) {
        if (from === to || from < 0 || to < 0) {
            return;
        }

        var page = s.pages.splice(from, 1)[0];
        s.pages.splice(to, 0, page);
        drawTiles(s);
    }

    // Files dropped or chosen: the ones the browser refuses become red rows
    // at once; the others wait their turn, within the most one design takes.
    function add(s, files) {
        var room = s.T.max - s.pages.length - s.queue.length - (s.busy ? 1 : 0);

        Array.prototype.forEach.call(files || [], function (file) {
            var why = refusalOf(file, s.T);

            if (!why && room <= 0) {
                if (room === 0) {
                    s.bad.push({ id: ++seq, name: file.name, message: fill(s.T.tooMany, [s.T.max, file.name]) });
                }
                room--;
                return;
            }

            if (why) {
                s.bad.push({ id: ++seq, name: file.name, message: fill(s.T[why], [file.name]) });
                return;
            }

            room--;
            s.queue.push(file);
        });

        drawRows(s);
        drawFoot(s);
        send(s);
    }

    // One file a request, as the file manager's uploads: a selection never
    // meets the server's request limits all at once.
    function send(s) {
        if (s.busy || !s.queue.length) {
            return;
        }

        var file = s.queue.shift();
        var form = new FormData();
        var xhr = new XMLHttpRequest();

        form.append('action', 'designer');
        form.append('sub_action', 'canva_page');
        form.append('token', token());
        form.append('file', file, file.name);

        s.busy = { id: ++seq, name: file.name, xhr: xhr, done: 0 };

        var finish = function (message) {
            if (message) {
                s.bad.push({ id: ++seq, name: file.name, message: message });
            }
            s.busy = null;
            drawRows(s);
            drawFoot(s);
            send(s);
        };

        xhr.open('POST', 'api.php');
        xhr.responseType = 'json';
        xhr.upload.onprogress = function (event) {
            if (event.lengthComputable && s.busy) {
                s.busy.done = Math.round(event.loaded * 100 / Math.max(1, event.total));
                drawRows(s);
            }
        };
        xhr.onload = function () {
            var answer = xhr.response;

            if (!answer || answer.status !== 'success' || !answer.page) {
                finish((answer && answer.message) ? answer.message : fill(s.T.failed, [file.name]));
                return;
            }

            if (s.pages.length < s.T.max) {
                s.pages.push(answer.page);
            }
            drawTiles(s);
            finish('');
        };
        xhr.onerror = function () {
            finish(s.T.network);
        };
        xhr.onabort = function () {
            finish('');
        };
        drawRows(s);
        xhr.send(form);
    }

    function drawRows(s) {
        var html = '';

        if (s.busy) {
            html += '<div class="sd-cv-row"><i class="bi bi-file-earmark-image" aria-hidden="true"></i>'
                + '<span class="sd-cv-row-text"><strong>' + esc(s.busy.name) + '</strong><span class="pg-text-meta">' + esc(s.T.reading) + '</span>'
                + '<span class="progress" role="progressbar" aria-label="' + esc(s.busy.name) + '" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' + s.busy.done + '">'
                + '<span class="progress-bar" style="width:' + s.busy.done + '%"></span></span></span>'
                + '<button type="button" class="pg-icon-button pg-icon-button-sm" data-cv-stop="1" aria-label="' + esc(s.T.stop) + '"><i class="bi bi-x-lg" aria-hidden="true"></i></button></div>';
        }

        s.bad.forEach(function (row) {
            html += '<div class="sd-cv-row is-bad" role="alert"><i class="bi bi-file-earmark-x" aria-hidden="true"></i>'
                + '<span class="sd-cv-row-text"><strong>' + esc(row.name) + '</strong><span>' + esc(row.message) + '</span></span>'
                + '<button type="button" class="pg-icon-button pg-icon-button-sm" data-cv-clear="' + row.id + '" aria-label="' + esc(s.T.clear) + '"><i class="bi bi-x-lg" aria-hidden="true"></i></button></div>';
        });

        // A progress update redraws only the bar.
        var bar = s.busy ? s.rows.querySelector('.progress') : null;

        if (bar && s.rows.getAttribute('data-busy') === String(s.busy.id) && s.rows.childElementCount === 1 + s.bad.length) {
            bar.setAttribute('aria-valuenow', String(s.busy.done));
            bar.firstChild.style.width = s.busy.done + '%';
            return;
        }

        s.rows.setAttribute('data-busy', s.busy ? String(s.busy.id) : '');
        s.rows.innerHTML = html;
    }

    function drawTiles(s) {
        var files = {};

        s.pages.forEach(function (p) {
            files[p.file] = true;
        });

        var fileCount = Object.keys(files).length;
        var on = s.pages.length > 0;

        s.head.hidden = !on;
        s.tiles.hidden = !on;
        s.drop.classList.toggle('is-small', on);

        var want = on ? 'small' : 'big';

        if (s.drop.getAttribute('data-now') !== want) {
            s.drop.setAttribute('data-now', want);
            var input = s.input;
            s.drop.innerHTML = s.drop.getAttribute('data-' + want);
            s.drop.appendChild(input);
        }

        if (!on) {
            s.tiles.innerHTML = '';
            return;
        }

        s.head.innerHTML = '<strong>' + esc(s.pages.length === 1 ? s.T.page1 : fill(s.T.pages, [s.pages.length])) + '</strong>'
            + '<span class="pg-text-meta">' + esc(fileCount === 1 ? s.T.file1 : fill(s.T.files, [fileCount])) + '</span>'
            + '<span class="pg-text-meta ms-auto d-none md:d-inline">' + esc(s.T.move) + '</span>';

        s.tiles.innerHTML = s.pages.map(function (p, i) {
            return '<div class="sd-fg-tile sd-cv-tile" role="listitem" tabindex="0" draggable="true" data-at="' + i + '" aria-label="' + esc((i + 1) + '. ' + p.name) + '">'
                + '<span class="sd-fg-tile-head"><span class="sd-cv-no" aria-hidden="true">' + (i + 1) + '</span><strong>' + esc(p.name) + '</strong>'
                + '<button type="button" class="pg-icon-button pg-icon-button-sm" data-cv-remove="' + i + '" aria-label="' + esc(fill(s.T.remove, [p.name])) + '"><i class="bi bi-trash3" aria-hidden="true"></i></button></span>'
                + '<span class="sd-fg-pic"><img src="' + esc(p.src) + '" alt="" loading="lazy" draggable="false"></span>'
                + '<span class="pg-text-meta text-truncate">' + esc(p.file + ' · ' + p.width + ' × ' + p.height) + '</span></div>';
        }).join('');
    }

    function button(label, kind, action, disabled) {
        return '<button type="button" class="pg-button pg-button-' + kind + '" data-cv-act="' + action + '"' + (disabled ? ' disabled' : '') + '>' + esc(label) + '</button>';
    }

    function drawFoot(s) {
        if (!s.foot || s.foot.getAttribute('data-part') !== 'canva') {
            return;
        }

        if (s.view === 'colors') {
            s.foot.innerHTML = button(s.T.back, 'ghost', 'back') + button(s.T.ask, 'quiet', 'ask') + button(s.T.create, 'fill', 'create');
        } else {
            var waiting = !!s.busy || s.queue.length > 0;

            s.foot.innerHTML = (s.pages.length ? '<span class="pg-text-meta sd-cv-hint"><i class="bi bi-info-circle me-1" aria-hidden="true"></i>' + esc(s.T.hint) + '</span>' : '')
                + button(s.T.next, 'fill', 'next', !s.pages.length || waiting);
        }

        s.foot.onclick = function (event) {
            var act = event.target.closest('[data-cv-act]');

            if (!act || act.disabled) {
                return;
            }

            var what = act.getAttribute('data-cv-act');

            if (what === 'next') {
                showColors(s, act);
            } else if (what === 'back') {
                showAdd(s);
            } else if (what === 'create') {
                create(s, answers(s));
            } else if (what === 'ask') {
                window.sdStartAsk(s.pane, s.T.href, answers(s), function () { keep(s); });
            }
        };
    }

    function answers(s) {
        var out = {};

        if (s.brand) {
            out.brand = s.brand;
        }
        if (s.second) {
            out.accent = s.second;
        }
        return out;
    }

    function keep(s) {
        try {
            window.localStorage.setItem('pg_proto_sketch_new', JSON.stringify(sketchOf(s.pages)));
        } catch (e) { }
    }

    function create(s, given) {
        keep(s);
        window.location.href = s.T.href + (Object.keys(given).length ? '&answers=' + encodeURIComponent(JSON.stringify(given)) : '');
    }

    function showColors(s, btn) {
        btn.disabled = true;

        api({ sub_action: 'canva_colors', lists: s.pages.map(function (p) { return p.colors || []; }) }).then(function (answer) {
            s.found = (answer && answer.status === 'success' && Array.isArray(answer.colors)) ? answer.colors : [];
            s.brand = s.found[0] || '';
            s.second = s.found[1] || '';
            s.view = 'colors';
            drawView(s);
            var title = s.colors.querySelector('h3');

            if (title) {
                title.focus();
            }
        }).catch(function () {
            btn.disabled = false;
            s.bad.push({ id: ++seq, name: '', message: s.T.network });
            drawRows(s);
        });
    }

    function showAdd(s) {
        s.view = 'add';
        drawView(s);
    }

    function drawView(s) {
        var colors = s.view === 'colors';

        Array.prototype.forEach.call(s.root.children, function (child) {
            child.hidden = colors ? child !== s.colors : (child === s.colors || ((child === s.head || child === s.tiles) && !s.pages.length));
        });

        if (colors) {
            drawColors(s, '');
        }
        drawFoot(s);
    }

    function swatches(s, name) {
        var current = s[name];

        return '<span class="sd-cv-dots" role="radiogroup" aria-label="' + esc(name === 'brand' ? s.T.brand : s.T.second) + '">'
            + s.found.map(function (hex) {
                return '<button type="button" class="sd-cv-dot" role="radio" aria-checked="' + (hex === current) + '" aria-label="' + esc(hex) + '"'
                    + ' style="background:' + esc(hex) + '" data-cv-color="' + esc(hex) + '" data-cv-for="' + name + '"></button>';
            }).join('') + '</span>';
    }

    function drawColors(s, focusFor) {
        var row = function (label, value, note) {
            return '<div class="sd-cv-var"><span class="sd-cv-var-k">' + esc(label) + '</span><span class="sd-cv-var-v">' + value + '</span>'
                + '<small class="pg-text-meta">' + esc(note) + '</small></div>';
        };
        var rows = s.found.length
            ? row(s.T.brand, swatches(s, 'brand'), s.T.brandNote) + row(s.T.second, swatches(s, 'second'), s.T.secondNote)
            : '<p class="pg-text-meta mb-0 py-3">' + esc(s.T.noColour) + '</p>';

        s.colors.innerHTML = '<h3 class="pg-sm-heading" tabindex="-1">' + esc(s.T.varsTitle) + '</h3>'
            + '<p class="pg-sm-desc">' + esc(s.T.varsLine) + '</p>'
            + '<div class="sd-cv-vars">' + rows + row(s.T.rest, esc(s.T.restValue), s.T.restNote) + '</div>';

        if (focusFor) {
            var on = s.colors.querySelector('[data-cv-for="' + focusFor + '"][aria-checked="true"]');

            if (on) {
                on.focus();
            }
        }
    }

    parts.canva = {
        show: function (pane, win) {
            if (!state || state.pane !== pane) {
                state = setup(pane, win);
            }

            if (state) {
                drawView(state);
            }
        },
        // The pure parts, for tests/canva_start_test.php.
        refusalOf: refusalOf,
        sketchOf: sketchOf
    };
}());
