/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Figma start of the New design window (pg_designer_figma_pane()),
 * loaded by assets/js/designer_home.js the first time the start is chosen.
 * A file's link is read on the server (api.php, designer › figma_file), its
 * frames are listed by page as tiles, each tile's small picture arrives
 * after (figma_pictures). The ticked frames become pages; "Next" hands them
 * on as the sd-figma-frames event on the pane, which the import listens to.
 * A file read is kept while the window is open, so "Another file" and
 * "Back" spend no call.
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

    if (parts.figma) {
        return;
    }

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

    function api(body) {
        body.action = 'designer';
        body.token = (typeof software_token !== 'undefined') ? software_token : '';

        return fetch('api.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        }).then(function (response) { return response.json(); });
    }

    var state = null;

    function setup(pane, win) {
        var root = pane.querySelector('.sd-fg');

        if (!root) {
            return null;
        }

        var T = {};

        try {
            T = JSON.parse(root.getAttribute('data-texts') || '{}');
        } catch (e) { }

        var s = {
            pane: pane,
            win: win,
            root: root,
            T: T,
            max: parseInt(T.max, 10) || 12,
            ask: root.querySelector('.sd-fg-ask'),
            field: root.querySelector('#sd-fg-link'),
            error: root.querySelector('#sd-fg-error'),
            limit: root.querySelector('.sd-fg-limit'),
            list: root.querySelector('.sd-fg-list'),
            foot: win.querySelector('.sd-ns-part-foot'),
            count: win.querySelector('.sd-ns-count'),
            files: {},
            link: '',
            busy: false,
            view: 'ask'
        };

        s.field.addEventListener('input', function () { drawFoot(s); });
        s.field.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                read(s);
            }
        });

        s.run = document.createElement('div');
        s.run.className = 'sd-fg-run';
        s.run.hidden = true;
        root.appendChild(s.run);
        pane.addEventListener('sd-figma-frames', function (event) { importStart(s, event.detail); });

        // Closing the window before "Open the design" does what Stop does.
        if (window.pgUI && pgUI.dialog && pgUI.dialog.on) {
            pgUI.dialog.on(win, 'hidden', function () {
                s.shut = true;

                if (s.leaving) {
                    return;
                }

                abandon(s);

                if (s.view === 'vars' || s.view === 'run' || s.view === 'report') {
                    backToList(s);
                }
            });
        }

        s.list.addEventListener('click', function (event) {
            var tile = event.target.closest('.sd-fg-tile');
            var all = event.target.closest('[data-fg-all]');

            if (tile) {
                tick(s, tile, !tile.classList.contains('is-on'));
            } else if (all) {
                var group = all.closest('.sd-fg-page');
                var on = all.getAttribute('data-fg-all') === '1';

                Array.prototype.forEach.call(group.querySelectorAll('.sd-fg-tile'), function (t) {
                    tick(s, t, on, true);
                });
                drawPages(s);
                drawFoot(s);
            } else if (event.target.closest('[data-fg-another]')) {
                showAsk(s);
            }
        });

        // Space ticks the tile in focus; the arrow keys move between tiles.
        s.list.addEventListener('keydown', function (event) {
            var tile = event.target.closest('.sd-fg-tile');

            if (!tile) {
                return;
            }

            var tiles = Array.prototype.slice.call(s.list.querySelectorAll('.sd-fg-tile'));
            var at = tiles.indexOf(tile);
            var step = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 }[event.key];

            if (event.key === ' ' || event.key === 'Enter') {
                event.preventDefault();
                tick(s, tile, !tile.classList.contains('is-on'));
            } else if (step && tiles[at + step]) {
                event.preventDefault();
                tiles[at + step].focus();
            }
        });

        return s;
    }

    function button(label, kind, action, disabled) {
        return '<button type="button" class="pg-button pg-button-' + kind + '" data-fg-act="' + action + '"' + (disabled ? ' disabled' : '') + '>' + label + '</button>';
    }

    function ticked(s) {
        return Array.prototype.slice.call(s.list.querySelectorAll('.sd-fg-tile.is-on')).map(function (t) {
            return t.getAttribute('data-id');
        });
    }

    function drawFoot(s) {
        if (!s.foot || s.foot.getAttribute('data-part') !== 'figma') {
            return;
        }

        if (s.view === 'vars') {
            s.count.hidden = true;
            s.foot.innerHTML = s.busy
                ? '<button type="button" class="pg-button pg-button-fill" disabled><span class="spinner-border spinner-border-sm me-3" aria-hidden="true"></span>' + esc(s.T.preparing) + '</button>'
                : button(esc(s.T.back), 'ghost', 'list') + button(esc(s.T.create), 'fill', 'create');
        } else if (s.view === 'run') {
            s.count.hidden = true;
            s.foot.innerHTML = button(esc(s.T.stop), 'quiet', 'stop', s.stopping)
                + '<button type="button" class="pg-button pg-button-fill" disabled><span class="spinner-border spinner-border-sm me-3" aria-hidden="true"></span>' + esc(s.T.working) + '</button>';
        } else if (s.view === 'report') {
            s.count.hidden = true;
            s.foot.innerHTML = '<button type="button" class="pg-button pg-button-ghost me-auto" data-fg-copy="1">' + esc(s.T.copy) + '</button>'
                + '<a class="pg-button pg-button-fill" data-fg-open="1" href="' + esc(s.openHref) + '">' + esc(s.T.open) + '</a>';
        } else if (s.view === 'list') {
            var file = s.files[s.link];
            var n = ticked(s).length;
            var over = n > s.max;

            s.count.hidden = false;
            s.count.classList.toggle('fg-warning', over);
            s.count.textContent = over ? fill(s.T.over, [n, file.list.frame_count, n - s.max]) : fill(s.T.count, [n, file.list.frame_count]);
            s.foot.innerHTML = button(esc(s.T.back), 'ghost', 'back') + button(esc(s.T.next), 'fill', 'next', n === 0 || over);
        } else {
            s.count.hidden = true;
            s.foot.innerHTML = s.busy
                ? '<button type="button" class="pg-button pg-button-fill" disabled><span class="spinner-border spinner-border-sm me-3" aria-hidden="true"></span>' + esc(s.T.reading) + '</button>'
                : button(esc(s.T.read), 'fill', 'read', !s.field.value.trim());
        }

        s.foot.onclick = function (event) {
            var act = event.target.closest('[data-fg-act]');
            var copy = event.target.closest('[data-fg-copy]');

            if (event.target.closest('[data-fg-open]')) {
                s.leaving = true;
                return;
            }

            if (copy && s.reportText && navigator.clipboard) {
                navigator.clipboard.writeText(s.reportText).then(function () { copy.textContent = s.T.copied; }, function () { });
                return;
            }

            if (!act) {
                return;
            }

            var what = act.getAttribute('data-fg-act');

            if (what === 'read') {
                read(s);
            } else if (what === 'back') {
                showAsk(s);
            } else if (what === 'list') {
                abandon(s);
                backToList(s);
            } else if (what === 'create') {
                importRun(s);
            } else if (what === 'stop') {
                importStop(s);
            } else if (what === 'next') {
                s.pane.dispatchEvent(new CustomEvent('sd-figma-frames', { bubbles: true, detail: { link: s.link, key: s.files[s.link].key, ids: ticked(s) } }));
            }
        };
    }

    function say(s, text, detail, amber) {
        if (amber) {
            s.error.hidden = true;
            s.limit.hidden = false;
            s.limit.innerHTML = '<div class="alert theme-warning small py-5 mt-7 mb-0"><strong class="d-block">' + esc(text) + '</strong>' + esc(detail || '') + '</div>';
            return;
        }

        s.limit.hidden = true;
        s.error.hidden = !text;
        s.error.textContent = text || '';
        s.field.classList.toggle('is-invalid', !!text);
    }

    function showAsk(s) {
        s.view = 'ask';
        s.run.hidden = true;
        s.list.hidden = true;
        s.ask.hidden = false;
        s.field.focus();
        drawFoot(s);
    }

    function read(s) {
        var link = s.field.value.trim();

        if (!link || s.busy) {
            return;
        }

        if (s.files[link]) {
            s.link = link;
            showList(s);
            return;
        }

        s.busy = true;
        s.field.readOnly = true;
        say(s, '');
        s.list.hidden = false;
        s.list.innerHTML = '<div class="sd-fg-tiles">' + new Array(5).join('<div class="sd-fg-tile is-wait" aria-hidden="true"><span class="sd-fg-pic"></span></div>') + '</div>';
        drawFoot(s);

        api({ sub_action: 'figma_file', link: link }).then(function (answer) {
            s.busy = false;
            s.field.readOnly = false;

            if (!answer || answer.status !== 'success') {
                s.list.hidden = true;
                say(s, answer && answer.message, answer && answer.detail, answer && answer.code === 'limit');
                drawFoot(s);
                return;
            }

            s.files[link] = { key: answer.key, list: answer.list, pictures: null };
            s.link = link;
            showList(s);
            pictures(s, link);
        }).catch(function () {
            s.busy = false;
            s.field.readOnly = false;
            s.list.hidden = true;
            say(s, s.T.network);
            drawFoot(s);
        });
    }

    function pictures(s, link) {
        api({ sub_action: 'figma_pictures', link: link }).then(function (answer) {
            if (answer && answer.status === 'success') {
                s.files[link].pictures = answer.pictures || {};

                if (s.link === link && s.view === 'list') {
                    drawPictures(s);
                }
            } else {
                s.files[link].pictures = {};
                drawPictures(s);
            }
        }).catch(function () { });
    }

    function drawPictures(s) {
        var file = s.files[s.link];

        if (!file || !file.pictures) {
            return;
        }

        Array.prototype.forEach.call(s.list.querySelectorAll('.sd-fg-tile'), function (tile) {
            var pic = tile.querySelector('.sd-fg-pic');
            var url = file.pictures[tile.getAttribute('data-id')];

            pic.classList.remove('is-wait');
            pic.innerHTML = url
                ? '<img src="' + esc(url) + '" alt="" loading="lazy" referrerpolicy="no-referrer">'
                : '<i class="bi bi-image" aria-hidden="true"></i>';
        });
    }

    function tick(s, tile, on, quiet) {
        tile.classList.toggle('is-on', on);
        tile.setAttribute('aria-checked', on ? 'true' : 'false');

        if (!quiet) {
            drawPages(s);
            drawFoot(s);
        }
    }

    // A page's link reads "Untick all" while every frame of it is ticked.
    function drawPages(s) {
        Array.prototype.forEach.call(s.list.querySelectorAll('.sd-fg-page'), function (group) {
            var link = group.querySelector('[data-fg-all]');

            if (!link) {
                return;
            }

            var all = !group.querySelector('.sd-fg-tile:not(.is-on)');

            link.setAttribute('data-fg-all', all ? '0' : '1');
            link.textContent = all ? s.T.untickAll : s.T.tickAll;
        });
    }

    function showList(s) {
        var file = s.files[s.link];
        var list = file.list;
        var html = '<div class="sd-fg-head"><span class="pg-icon-tile pg-tone-violet" aria-hidden="true"><i class="bi bi-vector-pen"></i></span>'
            + '<span class="sd-fg-name"><strong>' + esc(list.name) + '</strong><span class="pg-text-meta">' + esc(fill(s.T.sizes, [list.page_count, list.frame_count])) + '</span></span>'
            + '<button type="button" class="pg-button pg-button-sm pg-button-ghost ms-auto" data-fg-another="1">' + esc(s.T.another) + '</button></div>'
            + '<p class="pg-text-meta mt-3">' + esc(s.T.lead) + '</p>';

        list.pages.forEach(function (page) {
            if (!page.frames.length) {
                return;
            }

            html += '<section class="sd-fg-page"><div class="sd-fg-page-head"><strong>' + esc(page.name) + '</strong>'
                + '<span class="pg-text-meta">' + esc(fill(s.T.frames, [page.frames.length])) + '</span>'
                + '<button type="button" class="pg-button pg-button-sm pg-button-ghost ms-auto" data-fg-all="1">' + esc(s.T.tickAll) + '</button></div>'
                + '<div class="sd-fg-tiles" role="group" aria-label="' + esc(page.name) + '">';

            page.frames.forEach(function (frame) {
                html += '<div class="sd-fg-tile' + (frame.ticked ? ' is-on' : '') + '" role="checkbox" tabindex="0" aria-checked="' + (frame.ticked ? 'true' : 'false') + '" data-id="' + esc(frame.id) + '">'
                    + '<span class="sd-fg-tile-head"><strong>' + esc(frame.name) + '</strong><span class="sd-fg-box" aria-hidden="true"><i class="bi bi-check"></i></span></span>'
                    + '<span class="sd-fg-pic is-wait"></span>'
                    + '<span class="pg-text-meta">' + esc(frame.width + ' × ' + frame.height) + '</span></div>';
            });

            html += '</div></section>';
        });

        s.view = 'list';
        s.run.hidden = true;
        s.ask.hidden = true;
        s.limit.hidden = true;
        s.list.hidden = false;
        s.list.innerHTML = html;
        drawPages(s);
        drawPictures(s);
        drawFoot(s);
    }

    /* ------------------------------------------------------------------
     * The import (api.php designer › figma_import): start reads and
     * converts the ticked frames and finds the variables, the card offers
     * them, then each frame's pictures are fetched in a call of its own so
     * the rows show progress, and finish makes the shared components, the
     * pages and the report. "Open the design" opens the editor with them.
     * ------------------------------------------------------------------ */

    // The frame list as it was left, its ticks kept.
    function backToList(s) {
        s.view = 'list';
        s.run.hidden = true;
        s.ask.hidden = true;
        s.list.hidden = false;
        drawFoot(s);
    }

    function showRun(s, html) {
        s.list.hidden = true;
        s.ask.hidden = true;
        s.run.hidden = false;
        s.run.innerHTML = html;
    }

    function importStart(s, detail) {
        if (s.busy) {
            return;
        }

        s.busy = true;
        s.view = 'vars';
        s.imp = null;
        drawFoot(s);

        api({ sub_action: 'figma_import', step: 'start', link: detail.link, ids: detail.ids }).then(function (answer) {
            s.busy = false;

            // The window was closed while the frames were read.
            if (s.shut && answer && answer.status === 'success') {
                api({ sub_action: 'figma_import', step: 'stop', id: answer.id }).then(function () { }, function () { });
                backToList(s);
                return;
            }

            if (!answer || answer.status !== 'success') {
                backToList(s);
                say(s, answer && answer.message, answer && answer.detail, true);
                return;
            }

            s.imp = { id: answer.id, frames: answer.frames, found: answer.found || {}, done: [] };

            var f = s.imp.found;

            // A file with no colour and no font goes straight to the pages.
            if (!f.brand && !f.accent && !f.heading && !f.body) {
                importRun(s);
                return;
            }

            drawVars(s);
            drawFoot(s);
        }).catch(function () {
            s.busy = false;
            backToList(s);
            say(s, s.T.network, '', true);
        });
    }

    function source(s, row) {
        return row.source ? fill(s.T.fromStyle, [row.source]) : s.T.fromFrames;
    }

    function drawVars(s) {
        var f = s.imp.found;
        var swatch = function (row) {
            return row ? '<span class="sd-fg-swatch" style="background:' + esc(row.value) + '" aria-hidden="true"></span>' + esc(row.value) : '';
        };
        var line = function (label, value, from) {
            return '<div class="pg-card-inner-row sd-fg-var"><span class="sd-fg-var-name">' + esc(label) + '</span>'
                + '<span class="sd-fg-var-value">' + (value || esc(s.T.siteDefault)) + '</span>'
                + '<span class="pg-text-meta ms-auto">' + esc(from || '') + '</span></div>';
        };
        var fonts = [f.heading, f.body].filter(Boolean).map(function (r) { return esc(r.value); });
        var more = (f.more || []).map(function (r) { return '<span class="sd-fg-swatch" style="background:' + esc(r.value) + '" title="' + esc(r.value) + '"></span>'; }).join('');

        showRun(s, '<h3 class="pg-section-title">' + esc(s.T.varsTitle) + '</h3>'
            + '<p class="pg-text-meta">' + esc(s.T.varsLead) + '</p>'
            + '<div class="pg-card-inner">'
            + line(s.T.brand, swatch(f.brand), f.brand ? source(s, f.brand) : '')
            + line(s.T.second, swatch(f.accent), f.accent ? source(s, f.accent) : '')
            + line(s.T.more, more, more ? s.T.fromFrames : '')
            + line(s.T.fonts, fonts.join(' · '), f.heading ? source(s, f.heading) : '')
            + line(s.T.feel, '', '')
            + '</div>');
    }

    function importRun(s) {
        var frames = s.imp.frames;
        var rows = frames.map(function (fr, i) {
            return '<li class="pg-card-inner-row sd-fg-step" data-i="' + i + '"><span class="sd-fg-step-mark" aria-hidden="true"></span>'
                + '<strong>' + esc(fr.name) + '</strong><span class="pg-text-meta ms-auto">' + esc(s.T.waiting) + '</span></li>';
        }).join('');

        s.view = 'run';
        s.stopping = false;
        s.leaving = false;
        showRun(s, '<h3 class="pg-section-title">' + esc(fill(s.T.making, [frames.length])) + '</h3>'
            + '<progress class="sd-fg-bar" max="' + (frames.length + 1) + '" value="0"></progress>'
            + '<ol class="pg-card-inner sd-fg-steps" aria-live="polite">' + rows + '</ol>');
        drawFoot(s);
        step(s, 0);
    }

    function stepRow(s, i, state, text) {
        var row = s.run.querySelector('.sd-fg-step[data-i="' + i + '"]');

        if (row) {
            row.className = 'pg-card-inner-row sd-fg-step is-' + state;
            row.querySelector('.pg-text-meta').innerHTML = (state === 'work' ? '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>' : '') + esc(text);
        }
    }

    function step(s, i) {
        var bar = s.run.querySelector('.sd-fg-bar');

        if (s.stopping) {
            return;
        }

        if (bar) {
            bar.value = i;
        }

        if (i >= s.imp.frames.length) {
            finish(s);
            return;
        }

        stepRow(s, i, 'work', s.T.working);

        api({ sub_action: 'figma_import', step: 'frame', id: s.imp.id, index: i }).then(function (answer) {
            if (!answer || answer.status !== 'success' || answer.failed) {
                stepRow(s, i, 'failed', s.T.notMade);
            } else {
                stepRow(s, i, 'done', fill(s.T.got, [answer.pictures, answer.vectors]));
            }
            step(s, i + 1);
        }).catch(function () {
            stepRow(s, i, 'failed', s.T.notMade);
            step(s, i + 1);
        });
    }

    function finish(s) {
        api({ sub_action: 'figma_import', step: 'finish', id: s.imp.id, variables: 1 }).then(function (answer) {
            if (s.stopping) {
                return;
            }

            if (!answer || answer.status !== 'success') {
                abandon(s);
                backToList(s);
                say(s, (answer && answer.message) || s.T.network, '', true);
                return;
            }

            s.openHref = (s.T.openHref || '') + encodeURIComponent(s.imp.id);
            drawReport(s, answer.report);
            s.view = 'report';
            drawFoot(s);
        }).catch(function () {
            abandon(s);
            backToList(s);
            say(s, s.T.network, '', true);
        });
    }

    // An import left before "Open the design" (the window closed, Back
    // from the variables, a failed finish) is stopped as Stop stops it:
    // the server takes back the pictures, the shared rows and the folder.
    function abandon(s) {
        if (!s.imp) {
            return;
        }

        s.stopping = true;
        api({ sub_action: 'figma_import', step: 'stop', id: s.imp.id }).then(function () { }, function () { });
        s.imp = null;
    }

    function drawReport(s, r) {
        var c = r.counts;
        var pill = function (n, text, tone) {
            return n ? '<span class="pg-pill pg-tone-' + tone + '">' + esc(fill(text, [n])) + '</span>' : '';
        };
        var states = { whole: ['teal', s.T.whole], notes: ['amber', s.T.notes], failed: ['rose', s.T.notMade] };
        var text = [];
        var opened = false;
        var order = r.frames.slice().sort(function (a, b) { return (a.state === 'notes' ? 0 : 1) - (b.state === 'notes' ? 0 : 1); });
        var folds = order.map(function (fr) {
            var st = states[fr.state] || states.whole;
            var open = fr.state === 'notes' && !opened;

            opened = opened || open;
            text.push(fr.name + ': ' + fill(st[1], [fr.notes.length]));
            fr.notes.forEach(function (n) { text.push('  - ' + (n.layer ? n.layer + ': ' : '') + n.text); });

            return '<details class="pg-card-inner sd-fg-fold"' + (open ? ' open' : '') + '><summary><strong>' + esc(fr.name) + '</strong>'
                + '<span class="pg-pill pg-tone-' + st[0] + ' ms-auto">' + esc(fill(st[1], [fr.notes.length])) + '</span></summary>'
                + (fr.notes.length ? '<ul class="sd-fg-notes">' + fr.notes.map(function (n) {
                    return '<li>' + (n.layer ? '<strong>' + esc(n.layer) + '</strong> ' : '') + esc(n.text) + '</li>';
                }).join('') + '</ul>' : '') + '</details>';
        }).join('');
        var title = r.made === r.total ? fill(s.T.made, [r.made, r.name]) : fill(s.T.madeOf, [r.made, r.total]);

        s.reportText = title + '\n' + text.join('\n');
        showRun(s, '<h3 class="pg-section-title">' + esc(title) + '</h3>'
            + '<div class="d-flex flex-wrap gap-2 mb-3">'
            + pill(c.pictures, s.T.nPictures, 'gray') + pill(c.vectors, s.T.nVectors, 'gray') + pill(c.shared, s.T.nShared, 'gray')
            + pill(c.variables, s.T.nVariables, 'gray') + pill(c.notes, s.T.nNotes, 'amber') + pill(c.failed, s.T.nNotMade, 'rose') + '</div>'
            + '<p class="pg-text-meta">' + esc(fill(s.T.folder, [r.folder])) + '</p>' + folds);
    }

    function importStop(s) {
        var ask = (typeof window.pgConfirm === 'function')
            ? window.pgConfirm({ title: s.T.stopTitle, message: s.T.stopText, confirmText: s.T.stop, variant: 'danger' })
            : Promise.resolve(window.confirm(s.T.stopText));

        ask.then(function (yes) {
            if (!yes || !s.imp) {
                return;
            }

            abandon(s);
            drawFoot(s);
            backToList(s);
        });
    }

    parts.figma = {
        show: function (pane, win) {
            if (!state || state.pane !== pane) {
                state = setup(pane, win);
            }

            if (state) {
                state.shut = false;
                drawFoot(state);
            } else {
                // Not connected: the pane is its empty state, the foot has
                // only Cancel.
                var foot = win.querySelector('.sd-ns-part-foot');

                if (foot) {
                    foot.innerHTML = '';
                }
            }
        }
    };
}());
