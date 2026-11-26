/**
 * Pinegrap - Enterprise Website Platform
 *
 * The design variables' start questions: five, one at a time, each can be
 * skipped (brand colour, second colour, feel, type, room). One part for two
 * callers: the New design window asks them after a start's own form
 * (assets/js/designer_home.js) and the Variables tab asks them again from
 * its "More" menu (the editor's part 'questions'). The words and choices
 * come from the server (pg_design_variables_questions()); the answers go
 * back to it, which turns them into the variables model.
 *
 * window.pgStartQuestions.mount(host) draws into host.el and answers
 * host.done(answers), host.cancel() or host.back(). host: el, foot (where
 * the buttons go; none: under the question), q (the data), mode ('window'
 * or 'pane'), btn (the classes of the fill, quiet and ghost buttons).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
 */
(function () {
    'use strict';

    var STEPS = ['brand', 'accent', 'look', 'type', 'room'];

    function esc(v) {
        return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function hex(v) {
        v = String(v || '').trim();
        if (v && v.charAt(0) !== '#') v = '#' + v;
        return /^#[0-9a-f]{6}$/i.test(v) ? v.toLowerCase() : '';
    }

    // The part's own few rules, added once, so neither caller's stylesheet
    // carries them before the questions are asked.
    function styles() {
        if (document.getElementById('pgq-style')) return;
        var s = document.createElement('style');
        s.id = 'pgq-style';
        s.textContent =
            '.pgq-steps{display:flex;gap:.5rem;align-items:center;margin:0 0 1.25rem;padding:0;list-style:none;font-size:.8125rem;flex-wrap:wrap}' +
            '.pgq-steps li{display:flex;align-items:center;gap:.375rem;opacity:.7}' +
            '.pgq-steps li+li:before{content:"";width:1.5rem;border-top:1px solid currentColor;opacity:.3;margin-right:.25rem}' +
            '.pgq-steps li.is-on{opacity:1;font-weight:600}' +
            '.pgq-n{display:inline-flex;width:1.25rem;height:1.25rem;border-radius:50%;align-items:center;justify-content:center;font-size:.6875rem;border:1px solid rgba(127,127,127,.5)}' +
            '.is-on .pgq-n{background:var(--bs-primary,#2563eb);border-color:var(--bs-primary,#2563eb);color:#fff}' +
            '.is-done .pgq-n{border-color:#0d9488;color:#0d9488}' +
            '.pgq-ask{font-size:1.125rem;font-weight:600;margin:0 0 .25rem}' +
            '.pgq-why{opacity:.75;margin:0 0 1rem;font-size:.875rem}' +
            '.pgq-sw{display:flex;flex-wrap:wrap;gap:.5rem;margin-bottom:.75rem}' +
            '.pgq-sw button{width:2rem;height:2rem;border-radius:.5rem;border:2px solid transparent;padding:0;cursor:pointer}' +
            '.pgq-sw button[aria-checked=true]{outline:2px solid var(--bs-primary,#2563eb);outline-offset:2px}' +
            '.pgq-hexrow{display:flex;gap:.5rem;align-items:center;margin-bottom:1rem}' +
            '.pgq-hexrow input[type=text]{max-width:9rem}' +
            '.pgq-pick{position:relative;overflow:hidden}' +
            '.pgq-pick input{position:absolute;inset:0;opacity:0;cursor:pointer}' +
            '.pgq-demo{display:inline-flex;gap:1rem;align-items:center;padding:.75rem 1rem;border:1px solid rgba(127,127,127,.3);border-radius:.5rem}' +
            '.pgq-demo b{color:#fff;padding:.25rem .75rem;border-radius:.375rem;font-weight:500}' +
            '.pgq-demo u{text-underline-offset:3px}' +
            '.pgq-demo i{font-style:normal;padding:.125rem .5rem;border-radius:1rem;font-size:.8125rem}' +
            '.pgq-cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(11rem,1fr));gap:.75rem}' +
            '.pgq-card{position:relative;text-align:start;border:1px solid rgba(127,127,127,.35);border-radius:.5rem;padding:.75rem;background:transparent;color:inherit;cursor:pointer}' +
            '.pgq-card[aria-checked=true]{border-color:var(--bs-primary,#2563eb);box-shadow:0 0 0 2px var(--bs-primary,#2563eb)}' +
            '.pgq-card strong{display:block;margin:.25rem 0}' +
            '.pgq-card small{display:block;opacity:.75}' +
            '.pgq-card .pgq-tick{position:absolute;top:.5rem;right:.5rem;opacity:0;color:var(--bs-primary,#2563eb)}' +
            '.pgq-card[aria-checked=true] .pgq-tick{opacity:1}' +
            '.pgq-btn{display:inline-block;font-size:.625rem;padding:.125rem .5rem;color:#fff;background:var(--bs-primary,#2563eb)}' +
            '.pgq-aa{font-size:1.5rem;line-height:1}' +
            '.pgq-room{display:grid;gap:.25rem;margin-bottom:.5rem}' +
            '.pgq-room span{display:block;height:.5rem;border-radius:.125rem;background:rgba(127,127,127,.3)}' +
            '.pgq-more{background:none;border:0;padding:.5rem 0;color:inherit;opacity:.8;cursor:pointer}' +
            '.pgq-note{font-size:.8125rem;opacity:.75;margin:1rem 0 0}' +
            '.pgq-foot{display:flex;gap:.5rem;justify-content:flex-end;align-items:center;margin-top:1rem}' +
            '.pgq-foot .pgq-left{margin-right:auto}';
        document.head.appendChild(s);
    }

    function mount(host) {
        styles();
        var q = host.q;
        var t = q.t;
        var at = 0;
        var all = false;
        var a = { brand: '', accent: 'auto', look: '', type: '', room: '' };
        // Answers given before the questions (the Canva start's colours):
        // a colour of the form #rrggbb only.
        if (host.start) {
            ['brand', 'accent'].forEach(function (k) {
                if (/^#[0-9a-f]{6}$/i.test(String(host.start[k] || ''))) a[k] = String(host.start[k]).toLowerCase();
            });
        }
        var state = {};
        var b = host.btn || {};

        function stepLine() {
            var h = '<ol class="pgq-steps">';
            STEPS.forEach(function (k, i) {
                var cls = i === at ? 'is-on' : (state[k] === 'done' ? 'is-done' : '');
                var n = state[k] === 'done' && i !== at ? '<i class="bi bi-check" aria-hidden="true"></i>' : String(i + 1);
                h += '<li class="' + cls + '"' + (i === at ? ' aria-current="step"' : '') + '><span class="pgq-n">' + n + '</span>' + esc(q.steps[k].label) + '</li>';
            });
            return h + '</ol>';
        }

        function swatches(cur, name) {
            var h = '<div class="pgq-sw" role="radiogroup" aria-label="' + esc(t.colour) + '">';
            q.swatches.forEach(function (c) {
                h += '<button type="button" role="radio" aria-checked="' + (c === cur) + '" aria-label="' + esc(c) + '" style="background:' + esc(c) + '" data-q-color="' + esc(c) + '" data-q-for="' + name + '"></button>';
            });
            h += '</div><div class="pgq-hexrow">' +
                '<input type="text" class="form-control form-control-sm" maxlength="7" spellcheck="false" value="' + esc(cur) + '" placeholder="#2563eb" aria-label="' + esc(t.colour) + '" data-q-hex="' + name + '">' +
                '<span class="' + esc(b.quiet) + ' pgq-pick"><i class="bi bi-eyedropper me-1" aria-hidden="true"></i>' + esc(t.pick) +
                '<input type="color" value="' + esc(cur || '#2563eb') + '" aria-label="' + esc(t.pick) + '" data-q-hex="' + name + '"></span></div>';
            return h;
        }

        function card(name, key, on, body) {
            return '<button type="button" class="pgq-card" role="radio" aria-checked="' + on + '" data-q-card="' + name + '" data-q-key="' + esc(key) + '">' +
                '<i class="bi bi-check-circle-fill pgq-tick" aria-hidden="true"></i>' + body + '</button>';
        }

        function body() {
            var k = STEPS[at], h = '';
            if (k === 'brand') {
                var c = a.brand || '#2563eb';
                h = swatches(a.brand, 'brand') +
                    '<div class="pgq-demo" aria-hidden="true"><b style="background:' + esc(c) + '">' + esc(t.button) + '</b>' +
                    '<u style="color:' + esc(c) + '">' + esc(t.link) + '</u>' +
                    '<i style="background:' + esc(c) + '22;color:' + esc(c) + '">' + esc(t.highlight) + '</i></div>';
            } else if (k === 'accent') {
                var own = a.accent !== 'auto';
                h = '<div class="pgq-cards" role="radiogroup">' +
                    card('accent', 'auto', !own, '<strong>' + esc(t.auto) + '</strong><small>' + esc(t.autoLine.replace('{var:1}', a.brand || '')) + '</small>') +
                    card('accent', 'own', own, '<strong>' + esc(t.own) + '</strong><small>' + esc(t.ownLine) + '</small>') +
                    '</div>' + (own ? '<div class="mt-3">' + swatches(a.accent, 'accent') + '</div>' : '');
            } else if (k === 'look') {
                h = '<div class="pgq-cards" role="radiogroup">';
                q.looks.forEach(function (l, i) {
                    if (i >= q.shown && !all && l.key !== a.look) return;
                    h += card('look', l.key, a.look === l.key,
                        '<span class="pgq-btn" style="border-radius:' + Math.min(l.radius, 99) + 'px' + (l.caps ? ';text-transform:uppercase' : '') + '">' + esc(t.button) + '</span>' +
                        '<strong>' + esc(l.name) + '</strong><small>' + esc(l.line) + '</small>');
                });
                h += '</div>' + (all ? '' : '<button type="button" class="pgq-more" data-q-act="all">' + esc(t.allLooks) + ' <i class="bi bi-chevron-down" aria-hidden="true"></i></button>');
            } else if (k === 'type') {
                h = '<div class="pgq-cards" role="radiogroup">';
                Object.keys(q.types).forEach(function (key) {
                    var ty = q.types[key];
                    h += card('type', key, a.type === key,
                        '<span class="pgq-aa" style="font-family:' + esc(ty.values.font_heading) + '">Aa</span>' +
                        '<strong>' + esc(ty.name) + '</strong><small>' + esc(ty.line) + '</small>');
                });
                h += '</div>';
            } else {
                h = '<div class="pgq-cards" role="radiogroup">';
                Object.keys(q.rooms).forEach(function (key) {
                    var r = q.rooms[key], bars = '', gap = key === 'roomy' ? '.625rem' : '.25rem';
                    for (var i = 0; i < 3; i++) bars += '<span></span>';
                    h += card('room', key, a.room === key,
                        '<span class="pgq-room" style="gap:' + gap + '" aria-hidden="true">' + bars + '</span>' +
                        '<strong>' + esc(r.name) + '</strong><small>' + esc(r.line) + '</small>');
                });
                h += '</div>';
            }
            return h;
        }

        function foot() {
            var last = at === STEPS.length - 1;
            var next = last ? (host.mode === 'pane' ? t.use : t.create) : t.next;
            var first = host.mode === 'pane'
                ? '<button type="button" class="' + esc(b.ghost) + ' pgq-left" data-q-act="cancel">' + esc(t.cancel) + '</button>'
                : '<span class="pg-text-meta pgq-left"><i class="bi bi-info-circle me-1" aria-hidden="true"></i>' + esc(t.hint) + '</span>' +
                  '<button type="button" class="' + esc(b.ghost) + '" data-q-act="back">' + esc(t.back) + '</button>';
            return first +
                '<button type="button" class="' + esc(b.quiet) + '" data-q-act="skip">' + esc(t.skip) + '</button>' +
                '<button type="button" class="' + esc(b.fill) + '" data-q-act="next">' + esc(next) + '</button>';
        }

        function draw(focus) {
            var k = STEPS[at];
            host.el.innerHTML = '<div class="pgq">' + stepLine() +
                '<h3 class="pgq-ask" tabindex="-1">' + esc(q.steps[k].ask) + '</h3>' +
                '<p class="pgq-why">' + esc(q.steps[k].why) + '</p>' + body() +
                (host.mode === 'pane' ? '<p class="pgq-note"><i class="bi bi-info-circle me-1" aria-hidden="true"></i>' + esc(t.replace) + '</p>' : '') +
                (host.foot ? '' : '<div class="pgq-foot">' + foot() + '</div>') + '</div>';
            if (host.foot) host.foot.innerHTML = foot();
            if (focus) {
                var h = host.el.querySelector('.pgq-ask');
                if (h) h.focus();
            }
        }

        // The answer of the step on show, or '' when nothing is chosen yet.
        function answered(k) {
            if (k === 'accent') return a.accent === 'auto' ? 'auto' : a.accent;
            return a[k];
        }

        function go(skip) {
            var k = STEPS[at];
            if (skip) {
                a[k] = '';
                state[k] = 'skipped';
            } else {
                state[k] = answered(k) ? 'done' : 'skipped';
            }
            if (at === STEPS.length - 1) {
                var out = {};
                STEPS.forEach(function (s) { out[s] = state[s] === 'done' ? answered(s) : ''; });
                host.done(out);
                return;
            }
            at++;
            if (STEPS[at] === 'accent' && state.accent === 'skipped' && !a.accent) a.accent = 'auto';
            draw(true);
        }

        function setColor(name, v, fromField) {
            v = hex(v);
            if (!v) return;
            a[name] = v;
            if (fromField) {
                // Typing keeps the field: only the swatches and the sample follow.
                host.el.querySelectorAll('[data-q-color][data-q-for="' + name + '"]').forEach(function (s) {
                    s.setAttribute('aria-checked', String(s.getAttribute('data-q-color') === v));
                });
                var demo = host.el.querySelector('.pgq-demo');
                if (demo && name === 'brand') {
                    demo.querySelector('b').style.background = v;
                    demo.querySelector('u').style.color = v;
                    demo.querySelector('i').style.color = v;
                    demo.querySelector('i').style.background = v + '22';
                }
                return;
            }
            draw(false);
        }

        function onClick(e) {
            var el = e.target.closest ? e.target.closest('[data-q-act],[data-q-color],[data-q-card]') : null;
            if (!el) return;
            if (el.hasAttribute('data-q-color')) {
                setColor(el.getAttribute('data-q-for'), el.getAttribute('data-q-color'), false);
                return;
            }
            if (el.hasAttribute('data-q-card')) {
                var name = el.getAttribute('data-q-card'), key = el.getAttribute('data-q-key');
                if (name === 'accent') a.accent = key === 'auto' ? 'auto' : (hex(a.accent) || '');
                else a[name] = key;
                draw(false);
                return;
            }
            var act = el.getAttribute('data-q-act');
            if (act === 'all') { all = true; draw(false); }
            else if (act === 'skip') go(true);
            else if (act === 'next') go(false);
            else if (act === 'cancel') { off(); host.cancel(); }
            else if (act === 'back') {
                if (at === 0) { off(); host.back(); return; }
                at--;
                draw(true);
            }
        }

        function onInput(e) {
            var el = e.target;
            if (el && el.hasAttribute && el.hasAttribute('data-q-hex')) {
                setColor(el.getAttribute('data-q-hex'), el.value, el.type === 'text');
            }
        }

        var roots = host.foot ? [host.el, host.foot] : [host.el];
        roots.forEach(function (r) {
            r.addEventListener('click', onClick);
            r.addEventListener('input', onInput);
        });

        function off() {
            roots.forEach(function (r) {
                r.removeEventListener('click', onClick);
                r.removeEventListener('input', onInput);
            });
        }

        draw(true);
        return { off: off };
    }

    window.pgStartQuestions = { mount: mount };

    // In the editor: the part 'questions', which "Ask me again" of the
    // Variables tab runs. The host is the tab's (_sdVarsAskAgain()): the
    // pane, the look and palette, the model, the try-on, setLook, close and
    // the editor's button classes.
    if (window.StyleDesigner && window.StyleDesigner.definePart) {
        window.StyleDesigner.definePart('questions', function (K) {
            var esc2 = K.esc;
            var _sdT = K._sdT;
            var sdToast = K.sdToast;
            var _pgApiPost = K._pgApiPost;
            var data = null;

            function fail(res) {
                sdToast(esc2((res && res.message) ? res.message : _sdT('Network error.')), 'error', 5000);
            }

            function ask(host) {
                var start = data ? Promise.resolve(data) : _pgApiPost('variables_state', {
                    look: host.look(), palette: host.palette(), questions: 1
                }).then(function (res) {
                    if (!res || res.status !== 'success' || !res.questions) { fail(res); return null; }
                    data = res.questions;
                    return data;
                });
                return start.then(function (q) {
                    if (!q) return;
                    mount({
                        el: host.el, q: q, mode: 'pane',
                        btn: host.btn,
                        cancel: host.close,
                        back: host.close,
                        done: function (answers) {
                            // The answers are worked out on the look the
                            // feel chose; the model is in place before the
                            // look changes, so the tab's own follow-up
                            // reads the new model and answers the same.
                            var look = answers.look || host.look();
                            var body = { look: look, palette: host.palette(), answers: answers };
                            var m = host.model();
                            if (m) body.model = m;
                            else if (host.styleId) body.style_id = host.styleId;
                            _pgApiPost('variables_state', body).then(function (res) {
                                if (!res || res.status !== 'success') { fail(res); return; }
                                host.setModel(res.model);
                                if (look !== host.look()) host.setLook(look);
                                host.tryCss(res.css);
                                host.close();
                            }).catch(function () { fail(null); });
                        }
                    });
                });
            }

            return { ask: ask };
        });
    }
})();
