/**
 * Pinegrap - Enterprise Website Platform
 *
 * The page side of the Flows canvas harness (tests/js/flows_canvas.js): what
 * workspace.js lends the canvas on the flows screen, stood in for, so that
 * assets/js/workspace_flow.js runs as it does there with no server behind
 * it but the harness.
 *
 * window.PGWsKit builds elements as workspace.js does and sends the panel
 * actions as its api() does: a JSON POST to the page's api.php with the form
 * token, keepalive passed on, an error read from a JSON answer given its
 * field and code. The harness answers them. What the screen would show as a
 * toast or ask in a dialog is kept in window.PGHarness instead, where the
 * harness reads it and answers the questions. The canvas is started as
 * workspace.js starts it, with the team the boot answer would carry.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    var page = JSON.parse(document.getElementById('pgh-config').textContent);
    var CFG = page.cfg;
    var S = CFG.strings || {};

    // toasts: { message, kind }; asks: { message, ok, danger, details,
    // answered } (answered null while it waits); tasks: the task ids opened;
    // pending: the panel actions still on their way.
    var harness = { toasts: [], asks: [], tasks: [], pending: 0 };

    function t(key) {
        var text = Object.prototype.hasOwnProperty.call(S, key) ? S[key] : key;
        var values = Array.prototype.slice.call(arguments, 1);

        values.forEach(function (value, index) {
            text = text.split('{' + (index + 1) + '}').join(String(value));
        });

        return text;
    }

    function el(tag, className, text) {
        var node = document.createElement(tag);

        if (className) {
            node.className = className;
        }

        if ((text !== undefined) && (text !== null) && (text !== '')) {
            node.textContent = String(text);
        }

        return node;
    }

    function icon(name, extra) {
        var node = el('i', 'bi ' + name + (extra ? ' ' + extra : ''));

        node.setAttribute('aria-hidden', 'true');

        return node;
    }

    function button(className, label, iconName, title) {
        var node = el('button', className);

        node.type = 'button';

        if (iconName) {
            node.appendChild(icon(iconName, label ? 'me-1' : ''));
        }

        if (label) {
            node.appendChild(document.createTextNode(label));
        }

        if (title) {
            node.title = title;
            node.setAttribute('aria-label', title);
        }

        return node;
    }

    function select(options, value) {
        var node = el('select', 'form-control form-control-sm');

        options.forEach(function (option) {
            var item = el('option', '', option[1]);

            item.value = option[0];

            if (String(option[0]) === String(value)) {
                item.selected = true;
            }

            node.appendChild(item);
        });

        return node;
    }

    function clear(node) {
        while (node && node.firstChild) {
            node.removeChild(node.firstChild);
        }

        return node;
    }

    function avatar(person, size) {
        var face = el('span', 'avatar', (person && person.initials) || '');

        if (size) {
            face.style.setProperty('--bs-avatar-size', size);
        }

        face.setAttribute('aria-hidden', 'true');

        return face;
    }

    function debounce(fn, wait) {
        var timer = null;

        return function () {
            var self = this;
            var args = arguments;

            clearTimeout(timer);
            timer = setTimeout(function () { fn.apply(self, args); }, wait);
        };
    }

    // As workspace.js's api(); the address says whether the request is a
    // keepalive one, which the request itself does not tell the server.
    function api(action, data, options) {
        var payload = { action: action, token: CFG.token };
        var keepalive = !!(options && options.keepalive);

        Object.keys(data || {}).forEach(function (key) {
            payload[key] = data[key];
        });

        harness.pending++;

        return fetch(CFG.api_url + (keepalive ? '?keepalive=1' : ''), {
            method: 'POST',
            credentials: 'same-origin',
            keepalive: keepalive,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        }).then(function (response) {
            return response.json().catch(function () {
                throw new Error(t('error_generic'));
            });
        }).then(function (json) {
            if (!json || json.status !== 'success') {
                var error = new Error((json && json.message) ? json.message : t('error_generic'));

                error.field = json ? json.field : '';
                error.code = (json && json.code) ? json.code : '';
                error.stale = !!(json && json.stale);
                throw error;
            }

            return json.data || {};
        }).then(function (answer) {
            harness.pending--;
            return answer;
        }, function (error) {
            harness.pending--;
            throw error;
        });
    }

    function toast(message, kind) {
        harness.toasts.push({ message: String(message), kind: kind || '' });
    }

    function fail(error) {
        toast((error && error.message) ? error.message : t('error_generic'), 'danger');
    }

    // A question waits until the harness answers it (answer()).
    function ask(message, okLabel, danger, details) {
        return new Promise(function (resolve) {
            harness.asks.push({ message: String(message), ok: okLabel || t('ok'), danger: !!danger, details: details || null, answered: null, resolve: resolve });
        });
    }

    // Answers the oldest question still waiting: yes or no, the control the
    // question carries (a select) set to value first. False when none waits.
    harness.answer = function (yes, value) {
        var item = harness.asks.filter(function (one) { return one.answered === null; })[0];

        if (!item) {
            return false;
        }

        if ((value !== undefined) && item.details) {
            item.details.value = value;
            item.details.dispatchEvent(new Event('change', { bubbles: true }));
        }

        item.answered = !!yes;
        item.resolve(!!yes);

        return true;
    };

    // The questions still waiting, and what each offers to choose from.
    harness.waiting = function () {
        return harness.asks.filter(function (one) { return one.answered === null; }).map(function (one) {
            return {
                message: one.message,
                ok: one.ok,
                danger: one.danger,
                options: (one.details && one.details.options) ? Array.prototype.map.call(one.details.options, function (option) { return option.value; }) : null
            };
        });
    };

    // ── What the harness reads of the page ──────────────────────────────

    function describe(node) {
        return node.tagName.toLowerCase() + (node.id ? '#' + node.id : '') + (node.getAttribute('data-node') ? '[data-node=' + node.getAttribute('data-node') + ']' : '')
            + ((typeof node.className === 'string') && node.className ? '.' + node.className.trim().split(/\s+/).join('.') : '');
    }

    function words(node) {
        return node.textContent.replace(/\s+/g, ' ').trim();
    }

    // The element a step names: a CSS selector, or { css, text, has, label,
    // labelled, within, index }: of the elements css matches (buttons and
    // links when none is given) inside within, those shown whose words are
    // text, hold has, whose aria-label is label, the index-th; labelled:
    // the control of the <label> with those words.
    harness.find = function (spec) {
        var given = (typeof spec === 'string') ? { css: spec } : spec;
        var scope = given.within ? document.querySelector(given.within) : document;

        if (!scope) {
            return null;
        }

        if (given.labelled !== undefined) {
            return Array.prototype.filter.call(scope.querySelectorAll('label'), function (label) {
                return label.control && label.control.getClientRects().length && (words(label) === given.labelled);
            }).map(function (label) { return label.control; })[given.index || 0] || null;
        }

        return Array.prototype.filter.call(scope.querySelectorAll(given.css || 'button, a, [role="button"]'), function (node) {
            return node.getClientRects().length
                && ((given.text === undefined) || (words(node) === given.text))
                && ((given.has === undefined) || (words(node).indexOf(given.has) !== -1))
                && ((given.label === undefined) || (node.getAttribute('aria-label') === given.label));
        })[given.index || 0] || null;
    };

    // Where a pointer meets that element: its middle moved by dx, dy, or for
    // an SVG path the point at its middle; hit says whether the element
    // (or one inside it) is the one there, or what covers it.
    harness.point = function (spec, dx, dy) {
        var node = harness.find(spec);

        if (!node) {
            return { found: false };
        }

        // Scrolling inside the canvas would move its layer under the pointer.
        if (!node.closest('[role="application"]')) {
            node.scrollIntoView({ block: 'nearest', inline: 'nearest' });
        }

        var x;
        var y;
        var path = (node instanceof SVGElement) ? ((node.tagName.toLowerCase() === 'path') ? node : node.querySelector('path')) : null;

        if (path) {
            var middle = path.getPointAtLength(path.getTotalLength() / 2);
            var screen = new DOMPoint(middle.x, middle.y).matrixTransform(path.getScreenCTM());

            x = Math.round(screen.x + (dx || 0));
            y = Math.round(screen.y + (dy || 0));
        } else {
            var rect = node.getBoundingClientRect();

            x = Math.round(rect.left + rect.width / 2 + (dx || 0));
            y = Math.round(rect.top + rect.height / 2 + (dy || 0));
        }

        var there = document.elementFromPoint(x, y);

        return { found: true, x: x, y: y, hit: !!there && ((there === node) || node.contains(there)), covered: there ? describe(there) : 'nothing' };
    };

    // The element that has the focus: what it is, its name, the box it is
    // in, whether it is on the canvas; null for none.
    harness.focused = function () {
        var node = document.activeElement;

        if (!node || (node === document.body)) {
            return null;
        }

        var box = node.closest('[data-node]');

        return {
            tag: node.tagName.toLowerCase(),
            role: node.getAttribute('role') || '',
            label: node.getAttribute('aria-label') || '',
            text: words(node).slice(0, 160),
            value: (typeof node.value === 'string') ? node.value : '',
            node: box ? box.getAttribute('data-node') : '',
            pressed: node.getAttribute('aria-pressed') || '',
            canvas: !!node.closest('[role="application"]')
        };
    };

    // The canvas's view: the layer's offset and scale inside the viewport.
    harness.view = function () {
        var viewport = document.querySelector('#ws-root [role="application"]');
        var layer = viewport ? viewport.firstElementChild : null;

        if (!layer) {
            return null;
        }

        var matrix = new DOMMatrixReadOnly(getComputedStyle(layer).transform);

        return { x: matrix.e, y: matrix.f, k: matrix.a };
    };

    // The boxes on the canvas in their order: where each is in canvas units
    // (read from the screen), its name for screen readers, whether it is
    // the one selected, its tooltip.
    harness.boxes = function () {
        var viewport = document.querySelector('#ws-root [role="application"]');
        var view = harness.view();

        if (!viewport || !view) {
            return [];
        }

        var origin = viewport.getBoundingClientRect();

        return Array.prototype.map.call(viewport.querySelectorAll('[role="group"][data-node]'), function (box) {
            var rect = box.getBoundingClientRect();

            return {
                id: box.getAttribute('data-node'),
                x: Math.round((rect.left - origin.left - view.x) / view.k),
                y: Math.round((rect.top - origin.top - view.y) / view.k),
                label: box.getAttribute('aria-label') || '',
                role: box.getAttribute('aria-roledescription') || '',
                current: box.getAttribute('aria-current') === 'true',
                tip: box.title || '',
                shown: !!box.getClientRects().length
            };
        });
    };

    // The arrows on the canvas: their place in the graph, whether selected.
    harness.edges = function () {
        return Array.prototype.map.call(document.querySelectorAll('#ws-root [role="application"] [data-edge]'), function (edge) {
            return { index: Number(edge.getAttribute('data-edge')), current: edge.getAttribute('aria-current') === 'true' };
        });
    };

    // A point of the canvas with nothing on it, nor within 20 px of it: the
    // first of a grid of places, from the middle of the viewport outwards.
    harness.empty = function () {
        var viewport = document.querySelector('#ws-root [role="application"]');
        var rect = viewport.getBoundingClientRect();
        var bare = function (x, y) {
            var there = document.elementFromPoint(x, y);

            return (there === viewport) || (there === viewport.firstElementChild);
        };
        var places = [];

        for (var fy = 1; fy < 10; fy++) {
            for (var fx = 1; fx < 10; fx++) {
                places.push({ x: Math.round(rect.left + rect.width * fx / 10), y: Math.round(rect.top + rect.height * fy / 10), d: Math.abs(fx - 5) + Math.abs(fy - 5) });
            }
        }

        places.sort(function (a, b) { return a.d - b.d; });

        return places.filter(function (place) {
            return [[0, 0], [-20, 0], [20, 0], [0, -20], [0, 20], [-20, -20], [20, 20], [-20, 20], [20, -20]].every(function (step) {
                return bare(place.x + step[0], place.y + step[1]);
            });
        }).map(function (place) { return { x: place.x, y: place.y }; })[0] || null;
    };

    // A point of the screen in canvas units.
    harness.canvasPoint = function (x, y) {
        var rect = document.querySelector('#ws-root [role="application"]').getBoundingClientRect();
        var view = harness.view();

        return { x: (x - rect.left - view.x) / view.k, y: (y - rect.top - view.y) / view.k };
    };

    // What the live regions of the screen say now.
    harness.live = function () {
        return Array.prototype.map.call(document.querySelectorAll('#ws-root [aria-live]'), words).filter(Boolean).join(' | ');
    };

    harness.words = function (spec) {
        var node = harness.find(spec);

        return node ? words(node) : null;
    };

    window.PGWsKit = {
        cfg: CFG,
        t: t,
        el: el,
        icon: icon,
        button: button,
        select: select,
        avatar: avatar,
        api: api,
        toast: toast,
        fail: fail,
        ask: ask,
        clear: clear,
        debounce: debounce,
        openTask: function (taskId) {
            harness.tasks.push(taskId);
        }
    };

    window.PGHarness = harness;

    function start() {
        window.PGWsFlow.start(document.getElementById('ws-root'), page.boot);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
