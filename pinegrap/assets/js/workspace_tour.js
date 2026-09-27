/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the guided tour of the channel screen, with a small drawing on
 * every step.
 *
 * assets/js/pg_tour.js is the engine: the dim, the ring, the bubble, the
 * keyboard and remembering who has watched which tour. The steps, their words
 * and what each one points at come from the server
 * (includes/workspace/tour.php) in the #ws-config block; this file adds the
 * drawings and the two things the engine cannot know about this screen: when
 * it has finished drawing itself, and what to do when the thing a step is
 * about is not on the screen right now (a phone, or no channel open). Such a
 * step is shown in the middle with its drawing instead of being passed over,
 * except the ones marked optional, which are left out where there is nothing
 * to point at.
 *
 * The drawings are plain shapes, no words, coloured by the panel's theme
 * (.pg-tour-art in assets/css/pg_tour.css), so they read the same in every
 * language and in the light and the dark appearance alike.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    var configNode = document.getElementById('ws-config');
    var CFG = {};

    try {
        CFG = JSON.parse((configNode && configNode.textContent) || '{}');
    } catch (error) {
        CFG = {};
    }

    var TOUR = CFG.tour || null;

    if (!TOUR || !TOUR.steps || !TOUR.steps.length || !window.PgTour || !window.PG_TOUR || (window.PG_TOUR.key !== TOUR.key)) {
        return;
    }

    // ── Drawing helpers ─────────────────────────────────────────────────

    function rect(x, y, w, h, r, cls) {
        return '<rect x="' + x + '" y="' + y + '" width="' + w + '" height="' + h + '" rx="' + r + '" class="' + cls + '"/>';
    }

    function circle(cx, cy, r, cls) {
        return '<circle cx="' + cx + '" cy="' + cy + '" r="' + r + '" class="' + cls + '"/>';
    }

    function path(d, cls) {
        return '<path d="' + d + '" class="' + cls + '"/>';
    }

    // A line of text, as a rounded bar.
    function bar(x, y, w, cls) {
        return rect(x, y, w, 5, 2.5, cls || 'a-line');
    }

    // A "#" sign.
    function hash(x, y, cls) {
        return path('M' + (x + 2) + ' ' + y + 'l-1 8M' + (x + 6) + ' ' + y + 'l-1 8M' + x + ' ' + (y + 2.5) + 'h8M' + (x - 1) + ' ' + (y + 5.5) + 'h8', cls || 'a-stroke');
    }

    // A padlock.
    function lock(x, y, cls) {
        return path('M' + (x + 1.5) + ' ' + (y + 4) + 'v-1.5a2.5 2.5 0 0 1 5 0v1.5', cls || 'a-stroke') + rect(x, y + 4, 8, 5.5, 1.2, 'a-ink');
    }

    function face(cx, cy, r, cls) {
        return circle(cx, cy, r, cls || 'a-acc-soft') + circle(cx, cy - r * 0.25, r * 0.38, 'a-ink') + path('M' + (cx - r * 0.6) + ' ' + (cy + r * 0.72) + 'a' + (r * 0.6) + ' ' + (r * 0.5) + ' 0 0 1 ' + (r * 1.2) + ' 0', 'a-ink');
    }

    function svg(body) {
        return '<svg class="pg-tour-art" viewBox="0 0 300 120" role="img" aria-hidden="true" focusable="false">' + body + '</svg>';
    }

    // The screen in miniature: the list of channels on the left, a
    // conversation and the writing box on the right. Several drawings start
    // from it and light up their own part.
    function screen(lit) {
        var out = rect(12, 10, 276, 100, 8, 'a-panel');

        out += rect(12, 10, 72, 100, 8, lit === 'side' ? 'a-acc-soft' : 'a-soft');
        out += rect(22, 20, 52, 8, 4, 'a-panel');

        for (var i = 0; i < 5; i++) {
            out += hash(24, 38 + (i * 13), (lit === 'side' && i === 1) ? 'a-stroke-acc' : 'a-stroke');
            out += bar(36, 39 + (i * 13), 30 - (i % 2) * 8, (lit === 'side' && i === 1) ? 'a-acc' : 'a-line');
        }

        out += rect(94, 18, 186, 12, 4, lit === 'head' ? 'a-acc-soft' : 'a-soft');
        out += bar(100, 21.5, 44, lit === 'head' ? 'a-acc' : 'a-ink');

        out += face(104, 46, 6);
        out += rect(114, 40, 96, 13, 6, 'a-soft');
        out += face(104, 67, 6, 'a-ok');
        out += rect(114, 61, 130, 13, 6, lit === 'msg' ? 'a-acc-soft' : 'a-soft');

        out += rect(94, 88, 186, 14, 7, lit === 'compose' ? 'a-acc-soft' : 'a-soft');
        out += circle(271, 95, 4.5, lit === 'compose' ? 'a-acc' : 'a-line');

        return out;
    }

    var ART = {

        welcome: function () {
            return svg(screen('') + circle(252, 46, 12, 'a-acc') + path('M246 46l4 4 8-8', 'a-stroke-white'));
        },

        channels: function () {
            var out = rect(70, 8, 160, 104, 10, 'a-panel');

            out += hash(84, 20, 'a-stroke-acc') + bar(98, 21.5, 70, 'a-acc');
            out += hash(84, 36) + bar(98, 37.5, 54) + circle(210, 40, 4, 'a-acc');
            out += lock(84, 51) + bar(98, 53.5, 62);
            out += path('M84 71h9l2 3h10v8h-21z', 'a-stroke') + bar(112, 73.5, 48, 'a-ink');
            out += hash(96, 88) + bar(110, 89.5, 44) + rect(78, 86, 3, 12, 1.5, 'a-warn');
            out += hash(96, 101) + bar(110, 102.5, 36) + rect(78, 99, 3, 12, 1.5, 'a-ok');

            return svg(out);
        },

        search: function () {
            var out = rect(40, 42, 170, 36, 18, 'a-panel');

            out += circle(62, 58, 7, 'a-stroke') + path('M67 63l6 6', 'a-stroke');
            out += bar(82, 57.5, 90, 'a-line');
            out += circle(238, 60, 18, 'a-acc') + path('M238 51v18M229 60h18', 'a-stroke-white');

            return svg(out);
        },

        head: function () {
            var out = rect(20, 16, 260, 30, 8, 'a-panel');

            out += hash(32, 27, 'a-stroke-acc') + bar(46, 28.5, 70, 'a-ink');
            out += circle(214, 31, 8, 'a-acc-soft') + circle(226, 31, 8, 'a-ok') + circle(238, 31, 8, 'a-warn');
            out += circle(262, 26, 1.8, 'a-ink') + circle(262, 31, 1.8, 'a-ink') + circle(262, 36, 1.8, 'a-ink');

            var tabs = [44, 38, 52, 40, 34];
            var x = 24;

            for (var i = 0; i < tabs.length; i++) {
                out += bar(x, 62, tabs[i], i === 0 ? 'a-acc' : 'a-line');
                x += tabs[i] + 12;
            }

            out += rect(24, 71, 44, 3, 1.5, 'a-acc');
            out += rect(20, 82, 260, 26, 8, 'a-soft');

            return svg(out);
        },

        composer: function () {
            var out = rect(16, 40, 268, 44, 12, 'a-panel');

            out += bar(28, 52, 120, 'a-ink') + rect(152, 49, 2, 11, 1, 'a-acc');

            out += rect(28, 66, 18, 12, 4, 'a-acc-soft') + path('M40 71a3 3 0 1 0-1.5 2.6M40 69v3.5a1.5 1.5 0 0 0 3 0V72a6 6 0 1 0-2.5 4.8', 'a-stroke-acc');
            out += rect(52, 66, 18, 12, 4, 'a-acc-soft') + hash(57, 68, 'a-stroke-acc');
            out += rect(76, 66, 18, 12, 4, 'a-acc-soft') + path('M88 68l-6 8', 'a-stroke-acc');
            out += path('M106 68v6a3 3 0 0 0 6 0v-7a2 2 0 0 0-4 0v7', 'a-stroke');
            out += rect(122, 67, 10, 10, 2, 'a-stroke-box') + path('M122 72h10M127 67v10', 'a-stroke');

            out += circle(266, 70, 9, 'a-acc') + path('M262 70h7M266 66.5l3.5 3.5-3.5 3.5', 'a-stroke-white');

            return svg(out);
        },

        actions: function () {
            var out = face(40, 72, 12);

            out += rect(58, 58, 200, 34, 10, 'a-panel');
            out += bar(70, 68, 150, 'a-ink') + bar(70, 78, 96, 'a-line');

            out += rect(128, 24, 138, 26, 13, 'a-panel');

            out += path('M144 41l-5-4 5-4M139 37h7a5 5 0 0 1 5 5', 'a-stroke-acc');
            out += circle(170, 37, 6, 'a-stroke') + circle(168, 35.5, 0.9, 'a-ink') + circle(172, 35.5, 0.9, 'a-ink') + path('M167.5 39a3 3 0 0 0 5 0', 'a-stroke');
            out += path('M188 37l3 3 6-6', 'a-stroke-ok');
            out += circle(214, 33, 3.5, 'a-stroke') + path('M214 36.5v6.5', 'a-stroke');
            out += path('M232 37h10M238 33l4 4-4 4', 'a-stroke');
            out += path('M252 33h8v8h-8zM254 37l2 2 3-3', 'a-stroke');

            return svg(out);
        },

        tasks: function () {
            var out = '';
            var rows = [
                { done: true, due: 'a-line', w: 110 },
                { done: false, due: 'a-warn', w: 130 },
                { done: false, due: 'a-line', w: 90 }
            ];

            for (var i = 0; i < rows.length; i++) {
                var y = 16 + (i * 32);

                out += rect(30, y, 240, 26, 8, 'a-panel');
                out += rect(40, y + 7, 12, 12, 3, rows[i].done ? 'a-ok' : 'a-soft');

                if (rows[i].done) {
                    out += path('M42.5 ' + (y + 13) + 'l2.5 2.5 5-5', 'a-stroke-white');
                }

                out += bar(60, y + 10.5, rows[i].w, rows[i].done ? 'a-line' : 'a-ink');
                out += rect(208, y + 8, 30, 10, 5, rows[i].due);
                out += circle(252, y + 13, 7, i === 1 ? 'a-acc' : 'a-acc-soft');
            }

            return svg(out);
        },

        board: function () {
            var out = rect(16, 12, 268, 96, 8, 'a-panel');

            for (var d = 0; d < 5; d++) {
                out += bar(66 + (d * 44), 18, 26, 'a-line');
            }

            for (var p = 0; p < 3; p++) {
                var y = 34 + (p * 24);

                out += circle(34, y + 8, 7, p === 0 ? 'a-acc-soft' : (p === 1 ? 'a-ok' : 'a-warn'));
                out += rect(50, y + 18, 230, 1, 0.5, 'a-line');
            }

            out += rect(62, 36, 80, 12, 6, 'a-acc');
            out += rect(150, 36, 36, 12, 6, 'a-acc-soft');
            out += rect(106, 60, 124, 12, 6, 'a-ok');
            out += rect(62, 84, 64, 12, 6, 'a-warn');
            out += rect(118, 82, 44, 16, 6, 'a-bad-soft') + path('M140 85v5M140 93v.5', 'a-stroke-bad');

            return svg(out);
        },

        calendar: function () {
            var out = rect(66, 8, 168, 104, 10, 'a-panel');

            out += rect(66, 8, 168, 18, 10, 'a-acc-soft') + bar(126, 14.5, 48, 'a-acc');

            for (var r = 0; r < 4; r++) {
                for (var c = 0; c < 7; c++) {
                    var x = 76 + (c * 22);
                    var y = 34 + (r * 19);
                    var lit = (r === 1 && c === 3);

                    out += rect(x, y, 16, 14, 3, lit ? 'a-acc' : ((c > 4) ? 'a-line' : 'a-soft'));

                    if ((r * 7 + c) % 5 === 2 && !lit) {
                        out += circle(x + 8, y + 10, 1.8, (c % 2) ? 'a-ok' : 'a-warn');
                    }
                }
            }

            return svg(out);
        },

        notes: function () {
            var out = rect(80, 8, 140, 104, 8, 'a-panel');

            out += bar(92, 20, 70, 'a-ink');
            out += bar(92, 32, 110) + bar(92, 41, 96) + bar(92, 50, 104);

            out += rect(92, 62, 116, 36, 4, 'a-soft');
            out += rect(92, 62, 116, 10, 4, 'a-line');
            out += rect(150, 74, 1, 24, 0.5, 'a-line') + rect(92, 85, 116, 1, 0.5, 'a-line');
            out += bar(98, 77, 40) + bar(98, 89, 34);
            out += rect(156, 87, 46, 9, 3, 'a-acc-soft') + path('M160 90h5M160 93h5', 'a-stroke-acc') + bar(170, 89, 26, 'a-acc');

            return svg(out);
        },

        timeline: function () {
            var out = rect(58, 14, 3, 92, 1.5, 'a-line');

            for (var i = 0; i < 3; i++) {
                var y = 20 + (i * 32);

                out += circle(59.5, y + 10, 8, i === 0 ? 'a-acc' : 'a-acc-soft');
                out += path('M55.5 ' + (y + 10) + 'l3 3 5.5-5.5', i === 0 ? 'a-stroke-white' : 'a-stroke-acc');
                out += rect(78, y, 180, 22, 8, 'a-panel');
                out += bar(88, y + 5, 40 + (i * 18), 'a-ink') + bar(88, y + 13, 110 - (i * 20));
            }

            return svg(out);
        },

        inbox: function () {
            var out = path('M110 58l12-30h56l12 30v26a6 6 0 0 1-6 6h-68a6 6 0 0 1-6-6z', 'a-panel');

            out += path('M110 58h24l6 10h20l6-10h24', 'a-stroke');
            out += circle(190, 30, 11, 'a-bad') + rect(188.5, 24.5, 3, 11, 1.5, 'a-white');
            out += bar(130, 40, 40, 'a-line') + bar(126, 48, 48, 'a-line');

            return svg(out);
        },

        assistants: function () {
            var out = circle(46, 40, 16, 'a-claude') + path('M46 30c1 5 3 7 8 8-5 1-7 3-8 8-1-5-3-7-8-8 5-1 7-3 8-8z', 'a-white');

            out += circle(46, 84, 16, 'a-ai') + rect(39, 77, 14, 14, 3, 'a-stroke-white-box') + path('M42 77v-3M46 77v-3M50 77v-3M42 91v3M46 91v3M50 91v3', 'a-stroke-white');

            out += rect(74, 22, 150, 30, 10, 'a-panel') + bar(86, 30, 110, 'a-ink') + bar(86, 39, 80);

            out += rect(74, 62, 206, 44, 10, 'a-panel');
            out += rect(84, 72, 12, 12, 3, 'a-acc-soft') + path('M86.5 78l2.5 2.5 4.5-4.5', 'a-stroke-acc');
            out += bar(102, 75.5, 90, 'a-ink') + bar(102, 88, 60);
            out += rect(214, 72, 56, 14, 7, 'a-acc') + bar(226, 76.5, 32, 'a-white');
            out += rect(214, 89, 56, 12, 6, 'a-soft');

            return svg(out);
        },

        scheduled: function () {
            var out = circle(66, 60, 30, 'a-panel') + circle(66, 60, 30, 'a-stroke') + path('M66 42v18l12 8', 'a-stroke-acc');

            var x = 116;

            for (var i = 0; i < 3; i++) {
                out += rect(x, 46, 40, 28, 8, i === 0 ? 'a-acc-soft' : 'a-panel');
                out += bar(x + 8, 57.5, 24, i === 0 ? 'a-acc' : 'a-ink');

                if (i < 2) {
                    out += path('M' + (x + 42) + ' 60h14M' + (x + 51) + ' 55l5 5-5 5', 'a-stroke');
                }

                x += 58;
            }

            return svg(out);
        },

        finish: function () {
            var out = path('M60 96c30-6 40-30 80-30s60 20 96-8', 'a-stroke-dash');

            out += circle(60, 96, 6, 'a-acc-soft');
            out += rect(234, 26, 3, 60, 1.5, 'a-ink');
            out += path('M237 28h34l-8 10 8 10h-34z', 'a-acc');
            out += circle(140, 66, 5, 'a-ok') + circle(100, 82, 4, 'a-warn');

            return svg(out);
        }
    };

    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, function (ch) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch];
        });
    }

    // ── Targets ─────────────────────────────────────────────────────────

    // The same test the engine makes: on the screen and big enough to ring.
    function onScreen(element) {
        if (!element || !element.getClientRects().length) {
            return false;
        }

        var box = element.getBoundingClientRect();

        if ((box.width < 4) || (box.height < 4) || (box.bottom <= 0) || (box.right <= 0)
            || (box.top >= window.innerHeight) || (box.left >= window.innerWidth)) {
            return false;
        }

        var style = window.getComputedStyle(element);

        return (style.visibility !== 'hidden') && (style.display !== 'none');
    }

    // The first of a step's selectors that is on the screen; for "last:" the
    // last element it matches (the newest message, say).
    function locate(selectors) {
        var list = [].concat(selectors || []);

        for (var i = 0; i < list.length; i++) {
            var selector = String(list[i]);
            var last = (selector.indexOf('last:') === 0);
            var found = document.querySelectorAll(last ? selector.slice(5) : selector);

            if (last) {
                // The newest one that is not so tall that the bubble has no
                // room beside it; failing that, the newest one.
                var fallback = null;

                for (var j = found.length - 1; j >= 0; j--) {
                    if (onScreen(found[j])) {
                        if (found[j].getBoundingClientRect().height <= window.innerHeight * 0.35) {
                            return found[j];
                        }

                        fallback = fallback || found[j];
                    }
                }

                if (fallback) {
                    return fallback;
                }
            } else {
                for (var k = 0; k < found.length; k++) {
                    if (onScreen(found[k])) {
                        return found[k];
                    }
                }
            }
        }

        return null;
    }

    // A phone draws the screen differently enough that ringing parts of it
    // explains little: there every step is shown in the middle with its
    // drawing.
    function narrow() {
        return window.matchMedia ? window.matchMedia('(max-width: 767.98px)').matches : (window.innerWidth < 768);
    }

    var steps = TOUR.steps.map(function (item) {
        var step = {
            title: item.title,
            html: (ART[item.art] ? ART[item.art]() : '') + '<p class="mb-0">' + escapeHtml(item.text).split('\n').join('<br>') + '</p>',
            placement: item.placement || undefined,
            padding: (item.padding === undefined) ? undefined : item.padding
        };

        if (item.target) {
            // The target is worked out as the step comes up: what is on the
            // screen depends on the channel that is open and on the width of
            // the window. Nothing to point at shows the step in the middle,
            // unless it is only worth showing where its control is.
            step.before = function (done) {
                step.target = narrow() ? null : locate(item.target);
                done();
            };

            step.target = null;
        }

        if (item.optional) {
            step.skipIf = function () {
                return narrow() || !locate(item.target);
            };
        }

        return step;
    });

    window.PgTour.register({
        key: window.PG_TOUR.key,
        seen: window.PG_TOUR.seen,
        token: window.PG_TOUR.token,
        labels: window.PG_TOUR.labels,
        steps: steps,

        // The screen draws itself over the network: the tour waits for the
        // list of channels to be there.
        ready: function () {
            return !!document.querySelector('#ws-root .ws-side-head');
        }
    });

    window.PgTour.autoStart(1200);
})();
