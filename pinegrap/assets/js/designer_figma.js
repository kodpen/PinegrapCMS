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

        if (s.view === 'list') {
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

            if (!act) {
                return;
            }

            var what = act.getAttribute('data-fg-act');

            if (what === 'read') {
                read(s);
            } else if (what === 'back') {
                showAsk(s);
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
        s.ask.hidden = true;
        s.limit.hidden = true;
        s.list.hidden = false;
        s.list.innerHTML = html;
        drawPages(s);
        drawPictures(s);
        drawFoot(s);
    }

    parts.figma = {
        show: function (pane, win) {
            if (!state || state.pane !== pane) {
                state = setup(pane, win);
            }

            if (state) {
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
