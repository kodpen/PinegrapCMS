/**
 * Pinegrap - Enterprise Website Platform
 *
 * The page side of the Flows canvas harness (tests/js/flows_canvas.js),
 * loaded before the screen's scripts: what the scenarios read of the flows
 * screen, as window.PGHarness. The screen itself runs as it does in the
 * panel: workspace.js with its own helpers, dialogs and toasts, and the
 * canvas it starts; nothing of theirs is stood in for.
 *
 * Two things are kept for the scenarios on the way: each request the page
 * sends is counted while it is on its way (settled()), and one sent with
 * keepalive says so in a header, which the request itself does not tell
 * the server; each toast shown is kept with its words and tone, since a
 * toast hides itself after five seconds.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    // pending: the requests on their way; toasts: { text, tone } as shown.
    var harness = { pending: 0, toasts: [] };
    var send = window.fetch.bind(window);

    window.fetch = function (input, init) {
        var options = Object.assign({}, init || {});

        if (options.keepalive) {
            options.headers = Object.assign({}, options.headers || {}, { 'X-Harness-Keepalive': '1' });
        }

        harness.pending++;

        return send(input, options).then(function (response) {
            harness.pending--;
            return response;
        }, function (error) {
            harness.pending--;
            throw error;
        });
    };

    function words(node) {
        return node ? node.textContent.replace(/\s+/g, ' ').trim() : '';
    }

    function shown(node) {
        return !!node && !!node.getClientRects().length && (getComputedStyle(node).visibility !== 'hidden');
    }

    new MutationObserver(function (records) {
        records.forEach(function (record) {
            Array.prototype.forEach.call(record.addedNodes, function (node) {
                if ((node.nodeType === 1) && node.classList.contains('toast')) {
                    harness.toasts.push({ text: words(node), tone: (/(?:^|\s)bg-([a-z]+)/.exec(node.className) || [])[1] || '' });
                }
            });
        });
    }).observe(document.documentElement, { childList: true, subtree: true });

    function describe(node) {
        return node.tagName.toLowerCase() + (node.id ? '#' + node.id : '') + (node.getAttribute('data-node') ? '[data-node=' + node.getAttribute('data-node') + ']' : '')
            + ((typeof node.className === 'string') && node.className.trim() ? '.' + node.className.trim().split(/\s+/).join('.') : '');
    }

    function viewport() {
        return document.querySelector('#ws-root [role="application"]');
    }

    // The page has drawn what it was asked: the screen is up and no request
    // is on its way.
    harness.settled = function () {
        return (document.readyState === 'complete') && !harness.pending && !!document.querySelector('#ws-root .wsf-app');
    };

    // The element a step names: a CSS selector, or { css, text, has, label,
    // labelled, within }: the first of the elements css matches (buttons and
    // links when none is given) inside within that is shown, whose words are
    // text, hold has, whose aria-label is label; labelled: the control of
    // the shown <label> with those words.
    harness.find = function (spec) {
        var given = (typeof spec === 'string') ? { css: spec } : spec;
        var scope = given.within ? document.querySelector(given.within) : document;

        if (!scope) {
            return null;
        }

        if (given.labelled !== undefined) {
            return Array.prototype.filter.call(scope.querySelectorAll('label'), function (label) {
                return label.control && shown(label) && (words(label) === given.labelled);
            }).map(function (label) { return label.control; })[0] || null;
        }

        return Array.prototype.filter.call(scope.querySelectorAll(given.css || 'button, a, [role="button"]'), function (node) {
            return shown(node)
                && ((given.text === undefined) || (words(node) === given.text))
                && ((given.has === undefined) || (words(node).indexOf(given.has) !== -1))
                && ((given.label === undefined) || (node.getAttribute('aria-label') === given.label));
        })[0] || null;
    };

    // Where a pointer meets that element: its middle moved by dx, dy, or for
    // an SVG path the point at its middle; hit says whether the element (or
    // one inside it) is the one there, covered what is.
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

    // An element's box on the screen.
    harness.rect = function (spec) {
        var node = harness.find(spec);
        var rect = node ? node.getBoundingClientRect() : null;

        return rect ? { left: rect.left, top: rect.top, right: rect.right, bottom: rect.bottom, width: rect.width, height: rect.height } : null;
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
            id: node.id || '',
            role: node.getAttribute('role') || '',
            label: node.getAttribute('aria-label') || '',
            text: words(node).slice(0, 160),
            value: (typeof node.value === 'string') ? node.value : '',
            node: box ? box.getAttribute('data-node') : '',
            refocus: node.getAttribute('data-refocus') || '',
            canvas: !!node.closest('[role="application"]'),
            dialog: !!node.closest('dialog')
        };
    };

    // The canvas's view: the layer's offset and scale inside the viewport.
    harness.view = function () {
        var layer = viewport() ? viewport().firstElementChild : null;

        if (!layer) {
            return null;
        }

        var matrix = new DOMMatrixReadOnly(getComputedStyle(layer).transform);

        return { x: matrix.e, y: matrix.f, k: matrix.a };
    };

    // A point of the screen in canvas units.
    harness.canvasPoint = function (x, y) {
        var rect = viewport().getBoundingClientRect();
        var view = harness.view();

        return { x: (x - rect.left - view.x) / view.k, y: (y - rect.top - view.y) / view.k };
    };

    // The boxes on the canvas in their order: where each is in canvas units
    // (read from the screen), its size on the screen, its name for screen
    // readers, whether it is the one selected or outlined for a problem, its
    // tooltip, whether it is inside the viewport.
    harness.boxes = function () {
        var view = harness.view();

        if (!viewport() || !view) {
            return [];
        }

        var origin = viewport().getBoundingClientRect();

        return Array.prototype.map.call(viewport().querySelectorAll('.wsf-node[data-node]'), function (box) {
            var rect = box.getBoundingClientRect();

            return {
                id: box.getAttribute('data-node'),
                x: Math.round((rect.left - origin.left - view.x) / view.k),
                y: Math.round((rect.top - origin.top - view.y) / view.k),
                w: Math.round(rect.width),
                h: Math.round(rect.height),
                label: box.getAttribute('aria-label') || '',
                title: words(box.querySelector('.wsf-node-title')),
                current: box.getAttribute('aria-current') === 'true',
                bad: box.classList.contains('wsf-bad'),
                tip: box.title || '',
                run: (/(?:^|\s)wsf-run-([a-z]+)/.exec(box.className) || [])[1] || '',
                inside: (rect.left >= origin.left) && (rect.top >= origin.top) && (rect.right <= origin.right) && (rect.bottom <= origin.bottom),
                screen: { left: Math.round(rect.left), top: Math.round(rect.top), right: Math.round(rect.right), bottom: Math.round(rect.bottom) }
            };
        });
    };

    harness.box = function (id) {
        return harness.boxes().filter(function (item) { return item.id === id; })[0] || null;
    };

    // The arrows on the canvas: their place in the graph, whether selected,
    // outlined for a problem or taken in the run shown, their label, and
    // where they start and end on the screen.
    harness.edges = function () {
        return Array.prototype.map.call(document.querySelectorAll('#ws-root [role="application"] .wsf-edge[data-edge]'), function (edge) {
            var line = edge.querySelector('.wsf-edge-line');
            var matrix = line.getScreenCTM();
            var from = new DOMPoint(line.getPointAtLength(0).x, line.getPointAtLength(0).y).matrixTransform(matrix);
            var to = new DOMPoint(line.getPointAtLength(line.getTotalLength()).x, line.getPointAtLength(line.getTotalLength()).y).matrixTransform(matrix);

            return {
                index: Number(edge.getAttribute('data-edge')),
                current: edge.getAttribute('aria-current') === 'true',
                bad: edge.classList.contains('wsf-bad'),
                taken: edge.classList.contains('wsf-edge-taken'),
                label: words(edge.querySelector('.wsf-edge-label')),
                from: { x: Math.round(from.x), y: Math.round(from.y) },
                to: { x: Math.round(to.x), y: Math.round(to.y) }
            };
        });
    };

    // A point of the canvas with nothing on it, nor within 24 px of it: the
    // first of a grid of places, from the middle of the viewport outwards.
    harness.empty = function () {
        var rect = viewport().getBoundingClientRect();
        var bare = function (x, y) {
            var there = document.elementFromPoint(x, y);

            return (there === viewport()) || (there === viewport().firstElementChild) || (!!there && there.classList.contains('wsf-boxes'))
                || (!!there && (there.tagName.toLowerCase() === 'svg') && there.classList.contains('wsf-edges'));
        };
        var places = [];

        for (var fy = 1; fy < 10; fy++) {
            for (var fx = 1; fx < 10; fx++) {
                places.push({ x: Math.round(rect.left + rect.width * fx / 10), y: Math.round(rect.top + rect.height * fy / 10), d: Math.abs(fx - 5) + Math.abs(fy - 5) });
            }
        }

        places.sort(function (a, b) { return a.d - b.d; });

        return places.filter(function (place) {
            return [[0, 0], [-24, 0], [24, 0], [0, -24], [0, 24], [-24, -24], [24, 24], [-24, 24], [24, -24]].every(function (step) {
                return bare(place.x + step[0], place.y + step[1]);
            });
        }).map(function (place) { return { x: place.x, y: place.y }; })[0] || null;
    };

    // The dialog open over the screen: its words, its buttons, the list it
    // asks to choose from; null for none.
    harness.dialog = function () {
        var dialog = Array.prototype.filter.call(document.querySelectorAll('dialog[open]'), shown).pop();

        if (!dialog) {
            return null;
        }

        var pick = dialog.querySelector('select');
        var body = dialog.querySelector('.dialog-body');

        return {
            id: dialog.id || '',
            text: words(dialog.querySelector('[data-ws-ask-text]') || body || dialog),
            buttons: Array.prototype.filter.call(dialog.querySelectorAll('button'), shown).map(function (button) { return words(button) || button.getAttribute('aria-label') || ''; }),
            options: pick ? Array.prototype.map.call(pick.options, function (option) { return { value: option.value, text: words(option) }; }) : null,
            value: pick ? pick.value : null
        };
    };

    // Sets the list of the dialog open to a value, as a person picks it.
    harness.choose = function (value) {
        var dialog = Array.prototype.filter.call(document.querySelectorAll('dialog[open]'), shown).pop();
        var pick = dialog ? dialog.querySelector('select') : null;

        if (!pick || !Array.prototype.some.call(pick.options, function (option) { return option.value === String(value); })) {
            return false;
        }

        pick.value = String(value);
        pick.dispatchEvent(new Event('input', { bubbles: true }));
        pick.dispatchEvent(new Event('change', { bubbles: true }));

        return true;
    };

    // Sets a select of the screen to a value, as a person picks it.
    harness.pick = function (spec, value) {
        var pick = harness.find(spec);

        if (!pick || !Array.prototype.some.call(pick.options, function (option) { return option.value === String(value); })) {
            return false;
        }

        pick.focus();
        pick.value = String(value);
        pick.dispatchEvent(new Event('input', { bubbles: true }));
        pick.dispatchEvent(new Event('change', { bubbles: true }));

        return true;
    };

    // What the live regions of the screen say now.
    harness.live = function () {
        return Array.prototype.map.call(document.querySelectorAll('#ws-root [aria-live]'), words).filter(Boolean).join(' | ');
    };

    // The screen in a few words: the save state, the problems button, the
    // zoom, which of canvas, outline and palette is shown, the bars over the
    // canvas, the side panel's title, what is selected, the flow's name and
    // status, the address.
    harness.state = function () {
        var layer = viewport() ? viewport().firstElementChild : null;
        var selected = layer ? layer.querySelector('[aria-current="true"]') : null;
        var name = document.querySelector('#ws-root .wsf-name, #ws-root .wsf-name-text');
        var problems = document.querySelector('#ws-root [aria-controls="wsf-problems"]');
        var conflict = document.querySelector('#ws-root .wsf-app') ? document.querySelector('#ws-root .wsf-app').parentNode.querySelector(':scope > .alert') : null;
        var publish = harness.find({ text: (window.PGWsKit ? window.PGWsKit.t('fl_publish') : ''), within: '#ws-root .wsf-bar' });

        return {
            save: words(document.querySelector('#ws-root .wsf-save')),
            problems: problems ? { text: words(problems), disabled: problems.disabled, expanded: problems.getAttribute('aria-expanded') === 'true' } : null,
            problemList: shown(document.getElementById('wsf-problems')) ? Array.prototype.map.call(document.querySelectorAll('#wsf-problems .wsf-problem'), words) : [],
            zoom: words(document.querySelector('#ws-root .wsf-zoom-label')),
            canvas: shown(document.querySelector('#ws-root .wsf-stage')),
            outline: shown(document.querySelector('#ws-root .wsf-outline')),
            palette: shown(document.querySelector('#ws-root .wsf-palette')),
            readOnly: !!document.querySelector('#ws-root .wsf-app.wsf-readonly'),
            conflict: shown(conflict) ? words(conflict) : '',
            runBar: shown(document.querySelector('#ws-root .wsf-run-bar')) ? words(document.querySelector('#ws-root .wsf-run-bar')) : '',
            side: words(document.querySelector('#ws-root .wsf-side .wsf-side-title')),
            selected: selected ? (selected.hasAttribute('data-node') ? { type: 'node', id: selected.getAttribute('data-node') } : { type: 'edge', id: Number(selected.getAttribute('data-edge')) }) : null,
            name: name ? ((typeof name.value === 'string') ? name.value : words(name)) : '',
            status: words(document.querySelector('#ws-root .wsf-bar [data-refocus="status"]')),
            publish: publish ? { disabled: publish.disabled } : null,
            empty: shown(document.querySelector('#ws-root .wsf-empty')) ? words(document.querySelector('#ws-root .wsf-empty')) : '',
            search: window.location.search
        };
    };

    window.PGHarness = harness;
})();
