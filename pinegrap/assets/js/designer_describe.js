/**
 * Pinegrap - Enterprise Website Platform
 *
 * The "Describe it" start of the New design window
 * (pg_designer_describe_pane()), loaded by assets/js/designer_home.js the
 * first time the start is chosen. The pane's fields are drawn on the
 * server; this part presses the tone and the page chips, adds a page of
 * the person's own, sets the chips the kind of site starts with while
 * none was pressed by hand, shows the brand colour, checks the brief and
 * keeps it in this browser (localStorage pg_site_brief), and draws the
 * foot's "Plan my site".
 *
 * "Plan my site" asks the plan of pages (designer › site_plan) and draws
 * the plan screen in the pane's .sd-sp (pg_site_plan_screen()): a row a
 * page to rename, reorder, remove, put in a menu, a page to add; the
 * theme as the five questions' answers; the line on the day's drafts the
 * run takes, which stops "Make my site" when fewer are left. "Make my
 * site" writes the pages as the new design's sketch (pg_proto_sketch_new,
 * one empty frame a page with its address, as the Canva start does) and
 * the approved plan (pg_site_plan) for the editor's run, then opens the
 * design with the theme's answers. Nothing is kept on the server.
 *
 * check() is the twin of pg_site_brief_check() (includes/fn/site_brief.php):
 * tests/site_brief_test.php runs both on the same briefs. address() and
 * cost() are the twins of pg_site_plan_address() and pg_site_plan_cost()
 * (includes/fn/site_plan.php): tests/site_plan_screen_test.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    var parts = window.sdStartParts = window.sdStartParts || {};

    if (parts.describe) {
        return;
    }

    var KEY = 'pg_site_brief';
    var PLAN_KEY = 'pg_site_plan';
    var SKETCH_KEY = 'pg_proto_sketch_new';
    var MENUS = ['main', 'footer', 'none'];
    // The sketch frame's width, the room between two frames and how far
    // right of the design's first page the first frame stands (the Canva
    // start's figures).
    var FRAME = { w: 480, gap: 96, left: 640 };
    // pg_site_plan_address()'s folding of Turkish letters and accents.
    var FOLD = {
        'ı': 'i', 'ğ': 'g', 'ü': 'u', 'ş': 's', 'ö': 'o', 'ç': 'c',
        'â': 'a', 'î': 'i', 'û': 'u', 'á': 'a', 'à': 'a', 'ä': 'a',
        'é': 'e', 'è': 'e', 'ê': 'e', 'ë': 'e', 'í': 'i', 'ó': 'o',
        'ú': 'u', 'ñ': 'n', 'ß': 'ss'
    };
    var PURPOSES = ['shop', 'company', 'association', 'school', 'clinic', 'agency', 'blog', 'other'];
    var TONES = ['friendly', 'formal', 'plain'];

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

    // pg_site_brief_text(): the same classes, no Unicode ones. Pure.
    function text(value, lines) {
        if (typeof value !== 'string') {
            return '';
        }
        value = value.replace(/\r\n|\r/g, '\n');
        if (lines) {
            return value.replace(/[ \t\x0B\f]+/g, ' ').replace(/ ?\n ?/g, '\n').replace(/\n{3,}/g, '\n\n').replace(/^[ \n]+|[ \n]+$/g, '');
        }
        return value.replace(/[ \t\n\x0B\f]+/g, ' ').replace(/^ +| +$/g, '');
    }

    // Characters as PHP's /./u counts them: code points, not UTF-16 units.
    function length(value) {
        return Array.from(String(value)).length;
    }

    // pg_site_brief_colour(). Pure.
    function colour(value) {
        value = (typeof value === 'string') ? value.trim() : '';
        if (value !== '' && value.charAt(0) !== '#') {
            value = '#' + value;
        }
        return /^#[0-9a-f]{6}$/i.test(value) ? value.toLowerCase() : '';
    }

    // pg_site_brief_check(): the brief made clean and a code a field that
    // must change (the words are the texts' errors). Pure.
    function check(input, limits) {
        input = (input && typeof input === 'object') ? input : {};
        var get = function (key) { return input[key] == null ? '' : input[key]; };
        var brief = {
            name: text(get('name')),
            what: text(get('what'), true),
            kind: PURPOSES.indexOf(get('kind')) >= 0 ? get('kind') : 'other',
            customers: text(get('customers')),
            tone: TONES.indexOf(get('tone')) >= 0 ? get('tone') : 'friendly',
            pages: [],
            colour: colour(get('colour'))
        };
        var codes = {};
        var seen = {};
        var long = false;

        (Array.isArray(input.pages) ? input.pages : []).forEach(function (page) {
            page = text(page);
            var fold = page.toLowerCase();
            if (page === '' || seen.hasOwnProperty(fold)) {
                return;
            }
            seen[fold] = true;
            if (length(page) > limits.page) {
                long = true;
            }
            brief.pages.push(page);
        });

        if (brief.name === '') {
            codes.name = 'name';
        } else if (length(brief.name) > limits.name) {
            codes.name = 'nameLong';
        }

        var what = length(brief.what);
        if (what < limits.what_min) {
            codes.what = 'what';
        } else if (what > limits.what) {
            codes.what = 'whatLong';
        }

        if (length(brief.customers) > limits.customers) {
            codes.customers = 'customers';
        }

        if (!brief.pages.length) {
            codes.pages = 'pages';
        } else if (brief.pages.length > limits.pages) {
            codes.pages = 'pagesMany';
        } else if (long) {
            codes.pages = 'pageLong';
        }

        var raw = get('colour');
        if (typeof raw === 'string' && raw.trim() !== '' && brief.colour === '') {
            codes.colour = 'colour';
        }

        return { brief: brief, codes: codes };
    }


    // pg_site_plan_address(): a page's address from text, at most `max`
    // characters, 'page' when nothing is left. Pure.
    function address(value, max) {
        var t = (typeof value === 'string' || typeof value === 'number') ? String(value).trim().toLowerCase() : '';

        t = t.replace(/[ığüşöçâîûáàäéèêëíóúñß]/g, function (c) { return FOLD[c]; });
        // The dotted capital I lowers to "i" with a combining dot.
        t = t.replace(/i̇/g, 'i');
        t = t.replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
        t = t.slice(0, max || 60).replace(/-+$/, '');

        return t === '' ? 'page' : t;
    }

    // pg_site_plan_cost(): one draft a page and one a section; a page with
    // no section in words (added here) counts `guess` sections. Pure.
    function cost(pages, guess) {
        return (Array.isArray(pages) ? pages : []).reduce(function (sum, p) {
            var n = (p && Array.isArray(p.sections)) ? p.sections.length : 0;
            return sum + 1 + (n > 0 ? n : guess);
        }, 0);
    }

    // The plan's rows as "Make my site" takes them: names made plain, an
    // address from the name for a row renamed or added (auto), every
    // address used once (-2, -3), a known menu place. codes.rows names the
    // rows without a name by index, codes.pages is 'none' or 'many'. Pure.
    function checkPlan(rows, limits) {
        var pages = [];
        var bad = {};
        var taken = {};
        var codes = {};

        (Array.isArray(rows) ? rows : []).forEach(function (p, i) {
            p = (p && typeof p === 'object') ? p : {};
            var name = text(p.name);
            var base = address((p.auto || !p.address) ? name : p.address, limits.address);
            var at = base;

            if (name === '') {
                bad[i] = 'name';
            }
            for (var n = 2; taken.hasOwnProperty(at); n++) {
                at = base + '-' + n;
            }
            taken[at] = true;
            pages.push({
                name: name,
                address: at,
                purpose: text(p.purpose),
                sections: Array.isArray(p.sections) ? p.sections.slice() : [],
                menu: MENUS.indexOf(p.menu) >= 0 ? p.menu : 'main'
            });
        });

        if (Object.keys(bad).length) {
            codes.rows = bad;
        }
        if (!pages.length) {
            codes.pages = 'none';
        } else if (pages.length > limits.pages) {
            codes.pages = 'many';
        }

        return { pages: pages, codes: codes };
    }

    // The new design's sketch: one empty frame a page, side by side right
    // of the design's first page, its name on the head and its address in
    // the page's details. Pure.
    function sketchOf(pages) {
        return {
            n: pages.length + 1,
            pages: pages.map(function (p, i) {
                return {
                    id: i + 1,
                    name: p.name,
                    x: FRAME.left + i * (FRAME.w + FRAME.gap),
                    y: 0,
                    boxes: [],
                    details: { address: p.address, typed: true, type: 'standard' }
                };
            })
        };
    }

    // The theme's answers the new design opens with (?answers=): the ones
    // given only. Pure.
    function answersOf(theme) {
        var out = {};

        ['brand', 'accent', 'look', 'type', 'room'].forEach(function (key) {
            if (theme && typeof theme[key] === 'string' && theme[key] !== '') {
                out[key] = theme[key];
            }
        });
        return out;
    }

    function read() {
        try {
            var kept = JSON.parse(window.localStorage.getItem(KEY) || 'null');
            return (kept && typeof kept === 'object') ? kept : null;
        } catch (e) {
            return null;
        }
    }

    function keep(brief) {
        try {
            window.localStorage.setItem(KEY, JSON.stringify(brief));
            return true;
        } catch (e) {
            return false;
        }
    }

    var state = null;

    function setup(pane, win) {
        var form = pane.querySelector('.sd-sb');

        if (!form) {
            return null;
        }

        var s = {
            pane: pane,
            win: win,
            form: form,
            T: JSON.parse(form.getAttribute('data-texts') || '{}'),
            limits: JSON.parse(form.getAttribute('data-limits') || '{}'),
            forKind: JSON.parse(form.getAttribute('data-for') || '{}'),
            chips: form.querySelector('.sd-sb-chips'),
            touched: false,
            tried: false,
            view: 'brief',
            sp: pane.querySelector('.sd-sp'),
            plan: null,
            planFor: '',
            busy: false
        };

        if (s.sp) {
            s.P = JSON.parse(s.sp.getAttribute('data-texts') || '{}');
            s.Q = JSON.parse(s.sp.getAttribute('data-theme') || '{}');
            s.PL = JSON.parse(s.sp.getAttribute('data-limits') || '{}');
            s.href = s.sp.getAttribute('data-href') || '';
            setupPlan(s);
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            plan(s);
        });

        form.addEventListener('click', function (event) {
            var tone = event.target.closest('[data-tone]');
            var chip = event.target.closest('.sd-sb-chip');

            if (tone) {
                form.querySelectorAll('[data-tone]').forEach(function (b) {
                    b.setAttribute('aria-pressed', b === tone ? 'true' : 'false');
                });
            } else if (chip) {
                s.touched = true;
                if (chip.hasAttribute('data-own')) {
                    chip.remove();
                    focusAdd(s);
                } else {
                    chip.setAttribute('aria-pressed', chip.getAttribute('aria-pressed') === 'true' ? 'false' : 'true');
                }
                recheck(s);
            } else if (event.target.closest('.sd-sb-page-add')) {
                addOwn(s);
            }
        });

        form.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' && event.target.id === 'sd-sb-page') {
                event.preventDefault();
                addOwn(s);
            }
        });

        form.addEventListener('change', function (event) {
            if (event.target.id === 'sd-sb-kind' && !s.touched) {
                pressFor(s, event.target.value);
            }
            // A colour typed without its # or in capitals is written back
            // as the brief keeps it.
            if (event.target.id === 'sd-sb-colour' && colour(event.target.value)) {
                event.target.value = colour(event.target.value);
            }
            if (event.target.type === 'color') {
                form.querySelector('#sd-sb-colour').value = event.target.value;
                paintColour(s);
                recheck(s);
            }
        });

        form.addEventListener('input', function (event) {
            if (event.target.id === 'sd-sb-colour') {
                paintColour(s);
            }
            if (event.target.id === 'sd-sb-what') {
                count(s);
            }
            recheck(s);
        });

        restore(s, read());
        count(s);
        paintColour(s);

        return s;
    }

    // The chips a kind starts with; a page of the person's own stays.
    function pressFor(s, kind) {
        var list = s.forKind[kind] || s.forKind.other || [];
        s.chips.querySelectorAll('.sd-sb-chip[data-page]').forEach(function (chip) {
            chip.setAttribute('aria-pressed', list.indexOf(chip.getAttribute('data-page')) >= 0 ? 'true' : 'false');
        });
    }

    function ownChip(s, name) {
        return '<button type="button" class="pg-button pg-button-quiet pg-button-sm sd-sb-chip" data-own="' + esc(name) + '"'
            + ' aria-label="' + esc(fill(s.T.remove, [name])) + '"><i class="bi bi-check-lg" aria-hidden="true"></i>' + esc(name)
            + '<i class="bi bi-x sd-sb-x" aria-hidden="true"></i></button>';
    }

    function focusAdd(s) {
        var input = s.form.querySelector('#sd-sb-page');
        if (input) {
            input.focus();
        }
    }

    function addOwn(s) {
        var input = s.form.querySelector('#sd-sb-page');
        var name = text(input.value);

        if (name === '') {
            return;
        }

        var fold = name.toLowerCase();
        var same = null;

        s.chips.querySelectorAll('.sd-sb-chip').forEach(function (chip) {
            if (text(chip.textContent).toLowerCase() === fold) {
                same = chip;
            }
        });

        s.touched = true;
        // A name a chip already carries presses that chip; one of the
        // person's own is there once.
        if (!same) {
            s.chips.insertAdjacentHTML('beforeend', ownChip(s, name));
        } else if (same.hasAttribute('data-page')) {
            same.setAttribute('aria-pressed', 'true');
        }
        input.value = '';
        recheck(s);
        input.focus();
    }

    function paintColour(s) {
        var value = colour(s.form.querySelector('#sd-sb-colour').value);
        var swatch = s.form.querySelector('.sd-sb-swatch');
        var pick = s.form.querySelector('.sd-sb-pick input[type="color"]');

        swatch.classList.toggle('is-set', value !== '');
        swatch.style.setProperty('--sd-sb-colour', value || 'transparent');
        if (value && pick) {
            pick.value = value;
        }
    }

    function count(s) {
        var what = s.form.querySelector('#sd-sb-what');
        var help = s.form.querySelector('#sd-sb-what-count');
        if (!help) {
            help = document.createElement('span');
            help.id = 'sd-sb-what-count';
            help.className = 'pg-text-meta sd-sb-count';
            help.setAttribute('aria-hidden', 'true');
            what.parentNode.insertBefore(help, what.nextSibling);
        }
        var n = length(what.value);
        help.textContent = fill(s.T.chars, [n, s.limits.what]);
        help.classList.toggle('is-over', n > s.limits.what);
    }

    // The brief as the fields hold it, before the check.
    function collect(s) {
        var f = s.form;
        var tone = f.querySelector('[data-tone][aria-pressed="true"]');
        var pages = [];
        var names = {};

        // Document order: a page of the person's own stays where it was added.
        s.chips.querySelectorAll('.sd-sb-chip[aria-pressed="true"], .sd-sb-chip[data-own]').forEach(function (chip) {
            pages.push(chip.hasAttribute('data-own') ? chip.getAttribute('data-own') : text(chip.textContent));
            if (chip.hasAttribute('data-page')) {
                names[text(chip.textContent)] = chip.getAttribute('data-page');
            }
        });

        return {
            name: f.querySelector('#sd-sb-name').value,
            what: f.querySelector('#sd-sb-what').value,
            kind: f.querySelector('#sd-sb-kind').value,
            customers: f.querySelector('#sd-sb-customers').value,
            tone: tone ? tone.getAttribute('data-tone') : 'friendly',
            pages: pages,
            keys: names,
            colour: f.querySelector('#sd-sb-colour').value
        };
    }

    function restore(s, kept) {
        if (!kept) {
            return;
        }
        var f = s.form;
        ['name', 'what', 'customers', 'colour'].forEach(function (field) {
            if (typeof kept[field] === 'string' && (field !== 'name' || kept[field] !== '')) {
                f.querySelector('#sd-sb-' + field).value = kept[field];
            }
        });
        if (PURPOSES.indexOf(kept.kind) >= 0) {
            f.querySelector('#sd-sb-kind').value = kept.kind;
        }
        if (TONES.indexOf(kept.tone) >= 0) {
            f.querySelectorAll('[data-tone]').forEach(function (b) {
                b.setAttribute('aria-pressed', b.getAttribute('data-tone') === kept.tone ? 'true' : 'false');
            });
        }
        if (Array.isArray(kept.pages) && kept.pages.length) {
            var keys = (kept.keys && typeof kept.keys === 'object') ? kept.keys : {};
            var wanted = {};
            kept.pages.forEach(function (name) {
                wanted[keys[name] || ('own:' + String(name).toLowerCase())] = name;
            });
            s.chips.querySelectorAll('.sd-sb-chip[data-page]').forEach(function (chip) {
                var key = chip.getAttribute('data-page');
                chip.setAttribute('aria-pressed', wanted.hasOwnProperty(key) ? 'true' : 'false');
                delete wanted[key];
            });
            Object.keys(wanted).forEach(function (key) {
                s.chips.insertAdjacentHTML('beforeend', ownChip(s, wanted[key]));
            });
            s.touched = true;
        }
    }

    // A field's words under it; aria-invalid on the field.
    function mark(s, codes) {
        ['name', 'what', 'customers', 'pages', 'colour'].forEach(function (field) {
            var box = s.form.querySelector('#sd-sb-' + field + '-error');
            var input = s.form.querySelector('#sd-sb-' + field);
            var code = codes[field] || '';

            if (box) {
                box.textContent = code ? (s.T.errors[code] || '') : '';
                box.hidden = !code;
            }
            if (input) {
                if (code) {
                    input.setAttribute('aria-invalid', 'true');
                } else {
                    input.removeAttribute('aria-invalid');
                }
            }
        });
    }

    // After a first "Plan my site" the marks follow the fields as they change.
    function recheck(s) {
        if (s.tried) {
            mark(s, check(collect(s), s.limits).codes);
        }
    }

    function say(s, words) {
        var line = s.win.querySelector('.sd-ns-count');
        if (line) {
            line.textContent = words;
            line.hidden = !words;
        }
    }

    function plan(s) {
        var input = collect(s);
        var result = check(input, s.limits);
        var first = Object.keys(result.codes)[0];

        s.tried = true;
        mark(s, result.codes);

        if (first) {
            say(s, s.T.fix);
            var at = s.form.querySelector(first === 'pages' ? '.sd-sb-chip' : '#sd-sb-' + first);
            if (at) {
                at.focus();
            }
            return;
        }

        var brief = JSON.parse(JSON.stringify(result.brief));

        result.brief.keys = input.keys;
        keep(result.brief);

        if (s.sp) {
            ask(s, brief);
        } else {
            say(s, s.T.kept);
        }
    }

    function token() {
        return (typeof software_token !== 'undefined') ? software_token : '';
    }

    // The plan of pages for a checked brief: one request, unless the same
    // brief was planned already in this window (Back, then "Plan my site"
    // again without a change keeps the rows as the person left them).
    function ask(s, brief) {
        var key = JSON.stringify(brief);

        if (s.plan && s.planFor === key) {
            showPlan(s);
            return;
        }
        if (s.busy) {
            return;
        }

        s.busy = true;
        drawFoot(s);
        say(s, s.P.planning);

        fetch('api.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'designer', sub_action: 'site_plan', token: token(), brief: brief })
        }).then(function (response) {
            return response.json();
        }).then(function (answer) {
            s.busy = false;
            if (!answer || answer.status !== 'success' || !answer.plan || !Array.isArray(answer.plan.pages)) {
                drawFoot(s);
                say(s, (answer && answer.message) || s.P.network);
                return;
            }
            s.plan = {
                brief: brief,
                pages: answer.plan.pages.map(function (p) {
                    return { name: p.name, address: p.address, purpose: p.purpose, sections: p.sections, menu: p.menu, auto: false };
                }),
                theme: answer.plan.theme || {},
                drafts: answer.drafts || { cap: 0, left: 0, run: 0 }
            };
            s.planFor = key;
            showPlan(s);
        }).catch(function () {
            s.busy = false;
            drawFoot(s);
            say(s, s.P.network);
        });
    }

    function setupPlan(s) {
        var sp = s.sp;

        sp.addEventListener('click', function (event) {
            var act = event.target.closest('[data-sp-act]');

            if (!act || act.disabled) {
                return;
            }

            var what = act.getAttribute('data-sp-act');
            var at = parseInt(act.getAttribute('data-at'), 10);
            var pages = s.plan.pages;

            if (what === 'up' && at > 0) {
                pages.splice(at - 1, 0, pages.splice(at, 1)[0]);
                drawPlan(s, '[data-sp-act="' + (at - 1 > 0 ? 'up' : 'down') + '"][data-at="' + (at - 1) + '"]');
            } else if (what === 'down' && at < pages.length - 1) {
                pages.splice(at + 1, 0, pages.splice(at, 1)[0]);
                drawPlan(s, '[data-sp-act="' + (at + 1 < pages.length - 1 ? 'down' : 'up') + '"][data-at="' + (at + 1) + '"]');
            } else if (what === 'remove') {
                pages.splice(at, 1);
                drawPlan(s, pages.length ? '[data-sp-name="' + Math.min(at, pages.length - 1) + '"]' : '[data-sp-act="add"]');
            } else if (what === 'add' && pages.length < s.PL.pages) {
                pages.push({ name: s.P.newPage, address: '', purpose: '', sections: [], menu: 'main', auto: true });
                drawPlan(s, '[data-sp-name="' + (pages.length - 1) + '"]');
                var input = sp.querySelector('[data-sp-name="' + (pages.length - 1) + '"]');
                if (input) {
                    input.select();
                }
            }
        });

        sp.addEventListener('input', function (event) {
            var t = event.target;

            if (t.hasAttribute('data-sp-name')) {
                var i = parseInt(t.getAttribute('data-sp-name'), 10);
                var p = s.plan.pages[i];
                p.name = t.value;
                p.auto = true;
                var where = sp.querySelector('#sd-sp-row-' + i + '-address');
                if (where) {
                    where.textContent = '/' + address(text(t.value), s.PL.address);
                }
                if (text(t.value) !== '') {
                    markRow(s, i, '');
                }
            } else if (t.id === 'sd-sp-brand') {
                s.plan.theme.brand = t.value.toLowerCase();
                sp.querySelector('#sd-sp-brand-value').textContent = s.plan.theme.brand;
            } else if (t.id === 'sd-sp-accent-pick') {
                s.plan.theme.accent = t.value.toLowerCase();
            }
        });

        sp.addEventListener('change', function (event) {
            var t = event.target;

            if (t.hasAttribute('data-sp-menu')) {
                s.plan.pages[parseInt(t.getAttribute('data-sp-menu'), 10)].menu = t.value;
            } else if (t.id === 'sd-sp-accent') {
                var pick = sp.querySelector('#sd-sp-accent-pick');
                pick.hidden = t.value !== 'own';
                s.plan.theme.accent = t.value === 'own' ? pick.value.toLowerCase() : 'auto';
            } else if (t.hasAttribute('data-sp-theme')) {
                s.plan.theme[t.getAttribute('data-sp-theme')] = t.value;
            }
        });
    }

    function options(list, current, unset) {
        return '<option value=""' + (current ? '' : ' selected') + '>' + esc(unset) + '</option>'
            + (list || []).map(function (o) {
                return '<option value="' + esc(o.key) + '"' + (o.key === current ? ' selected' : '') + '>' + esc(o.name) + '</option>';
            }).join('');
    }

    function themeRow(label, id, control) {
        return '<div class="pg-value-row sd-sp-var"><span class="pg-value-row-text"><label class="pg-value-row-name" for="' + id + '">' + esc(label) + '</label></span>'
            + '<span class="sd-sp-var-v">' + control + '</span></div>';
    }

    function drawTheme(s) {
        var P = s.P;
        var Q = s.Q;
        var steps = Q.steps || {};
        var t = s.plan.theme;
        var own = /^#[0-9a-f]{6}$/.test(t.accent || '');
        var brand = t.brand || '';

        return themeRow(steps.brand, 'sd-sp-brand', '<span class="sd-sp-colour"><input type="color" id="sd-sp-brand" value="' + esc(brand || '#2563eb') + '">'
                + '<span class="pg-text-meta" id="sd-sp-brand-value">' + esc(brand || P.unset) + '</span></span>')
            + themeRow(steps.accent, 'sd-sp-accent', '<span class="sd-sp-colour"><select class="pg-input" id="sd-sp-accent">'
                + '<option value="auto"' + (own ? '' : ' selected') + '>' + esc(P.auto) + '</option>'
                + '<option value="own"' + (own ? ' selected' : '') + '>' + esc(P.own) + '</option></select>'
                + '<input type="color" id="sd-sp-accent-pick" value="' + esc(own ? t.accent : '#0f766e') + '" aria-label="' + esc(steps.accent) + '"' + (own ? '' : ' hidden') + '></span>')
            + themeRow(steps.look, 'sd-sp-look', '<select class="pg-input" id="sd-sp-look" data-sp-theme="look">' + options(Q.looks, t.look, P.unset) + '</select>')
            + themeRow(steps.type, 'sd-sp-type', '<select class="pg-input" id="sd-sp-type" data-sp-theme="type">' + options(Q.types, t.type, P.unset) + '</select>')
            + themeRow(steps.room, 'sd-sp-room', '<select class="pg-input" id="sd-sp-room" data-sp-theme="room">' + options(Q.rooms, t.room, P.unset) + '</select>');
    }

    function drawRow(s, p, i, last) {
        var P = s.P;
        var id = 'sd-sp-row-' + i;
        var n = p.sections.length;
        var icon = function (act, bi, label, off) {
            return '<button type="button" class="pg-icon-button pg-icon-button-sm" data-sp-act="' + act + '" data-at="' + i + '"'
                + ' aria-label="' + esc(fill(label, [text(p.name) || P.newPage])) + '"' + (off ? ' disabled' : '') + '><i class="bi ' + bi + '" aria-hidden="true"></i></button>';
        };
        var menus = MENUS.map(function (m) {
            return '<option value="' + m + '"' + (m === p.menu ? ' selected' : '') + '>' + esc(P.menus[m]) + '</option>';
        }).join('');

        return '<li class="pg-card-inner-row sd-sp-row">'
            + '<span class="sd-sp-no" aria-hidden="true">' + (i + 1) + '</span>'
            + '<div class="pg-look-grow sd-sp-main">'
            + '<input type="text" class="pg-input sd-sp-name" value="' + esc(p.name) + '" maxlength="' + s.PL.name + '" data-sp-name="' + i + '"'
            + ' aria-label="' + esc(P.name + ' ' + (i + 1)) + '" aria-describedby="' + id + '-error ' + id + '-line">'
            + '<div class="pg-field-help pg-field-error" id="' + id + '-error" hidden></div>'
            + '<div class="pg-text-meta sd-sp-line" id="' + id + '-line"><span id="' + id + '-address">/' + esc(p.auto ? address(text(p.name), s.PL.address) : p.address) + '</span>'
            + ' · <span' + (n ? ' title="' + esc(p.sections.join('\n')) + '"' : '') + '>' + esc(n ? (n === 1 ? P.section1 : fill(P.sections, [n])) : P.added) + '</span>'
            + (p.purpose ? '<span class="sd-sp-purpose">' + esc(p.purpose) + '</span>' : '') + '</div>'
            + '</div>'
            + '<select class="pg-input sd-sp-menu" data-sp-menu="' + i + '" aria-label="' + esc(P.menuLabel) + '">' + menus + '</select>'
            + '<span class="sd-sp-acts">' + icon('up', 'bi-arrow-up', P.up, i === 0) + icon('down', 'bi-arrow-down', P.down, i === last)
            + icon('remove', 'bi-trash3', P.remove, false) + '</span>'
            + '</li>';
    }

    // The plan screen drawn again from s.plan; `focus` is the selector of
    // the control to focus after a move, a removal or an addition.
    function drawPlan(s, focus) {
        var P = s.P;
        var pages = s.plan.pages;

        s.sp.innerHTML = '<div class="sd-sp-head"><h3 class="sd-sp-title" id="sd-sp-title" tabindex="-1">' + esc(P.heading) + '</h3>'
            + '<p class="pg-text-meta mb-0">' + esc(P.lead) + '</p></div>'
            + '<ol class="pg-card sd-sp-pages">' + pages.map(function (p, i) { return drawRow(s, p, i, pages.length - 1); }).join('') + '</ol>'
            + '<div class="pg-field-help pg-field-error" id="sd-sp-pages-error" hidden></div>'
            + '<button type="button" class="pg-button pg-button-quiet sd-sp-add" data-sp-act="add"' + (pages.length >= s.PL.pages ? ' disabled' : '') + '>'
            + '<i class="bi bi-plus-lg" aria-hidden="true"></i>' + esc(P.add) + '</button>'
            + '<div class="sd-sp-head mt-4"><h3 class="sd-sp-title">' + esc(P.theme) + '</h3><p class="pg-text-meta mb-0">' + esc(P.themeLead) + '</p></div>'
            + '<div class="pg-card sd-sp-theme">' + drawTheme(s) + '</div>';

        drafts(s);
        drawFoot(s);

        var at = focus ? s.sp.querySelector(focus) : null;
        if (at) {
            at.focus();
        }
    }

    // The line on the day's drafts in the foot; true while the run fits.
    function drafts(s) {
        var run = cost(s.plan.pages, s.PL.guess);
        var left = parseInt(s.plan.drafts.left, 10) || 0;
        var fits = run <= left;
        var line = s.win.querySelector('.sd-ns-count');

        say(s, fill(fits ? s.P.drafts : s.P.tooMany, [run, left]));
        if (line) {
            line.classList.toggle('sd-sp-over', !fits);
        }
        return fits;
    }

    function markRow(s, i, code) {
        var input = s.sp.querySelector('[data-sp-name="' + i + '"]');
        var box = s.sp.querySelector('#sd-sp-row-' + i + '-error');

        if (box) {
            box.textContent = code ? s.P.nameEmpty : '';
            box.hidden = !code;
        }
        if (input) {
            if (code) {
                input.setAttribute('aria-invalid', 'true');
            } else {
                input.removeAttribute('aria-invalid');
            }
        }
    }

    function showPlan(s) {
        s.view = 'plan';
        s.form.hidden = true;
        s.sp.hidden = false;
        drawPlan(s, '#sd-sp-title');
    }

    function showBrief(s) {
        s.view = 'brief';
        s.sp.hidden = true;
        s.form.hidden = false;
        var line = s.win.querySelector('.sd-ns-count');
        if (line) {
            line.classList.remove('sd-sp-over');
        }
        drawFoot(s);
        say(s, '');
        var what = s.form.querySelector('#sd-sb-what');
        if (what) {
            what.focus();
        }
    }

    // "Make my site": the rows checked, then the sketch and the plan kept
    // in this browser and the new design opened with the theme's answers.
    function make(s) {
        var result = checkPlan(s.plan.pages, s.PL);
        var box = s.sp.querySelector('#sd-sp-pages-error');
        var rows = result.codes.rows || {};

        s.plan.pages.forEach(function (p, i) {
            markRow(s, i, rows[i] || '');
        });
        if (box) {
            box.textContent = result.codes.pages === 'none' ? s.P.noPages : '';
            box.hidden = result.codes.pages !== 'none';
        }
        if (Object.keys(rows).length || result.codes.pages) {
            var first = Object.keys(rows)[0];
            var at = first != null ? s.sp.querySelector('[data-sp-name="' + first + '"]') : s.sp.querySelector('[data-sp-act="add"]');
            if (at) {
                at.focus();
            }
            return;
        }
        if (!drafts(s)) {
            return;
        }

        var theme = answersOf(s.plan.theme);

        try {
            window.localStorage.setItem(PLAN_KEY, JSON.stringify({ brief: s.plan.brief, pages: result.pages, theme: theme }));
            window.localStorage.setItem(SKETCH_KEY, JSON.stringify(sketchOf(result.pages)));
        } catch (e) { }

        window.location.href = s.href + (Object.keys(theme).length ? '&answers=' + encodeURIComponent(JSON.stringify(theme)) : '');
    }

    function drawFoot(s) {
        var foot = s.win.querySelector('.sd-ns-part-foot');
        var view = s.view + (s.busy ? ':busy' : '');

        // The window calls show() again on a field's change, which comes
        // between the press and the click on the button: buttons already
        // there for this view are kept, or the click lands on nothing.
        if (!foot || foot.getAttribute('data-part') !== 'describe') {
            return;
        }
        if (foot.getAttribute('data-sb-view') === view && foot.querySelector('[data-sb-act]')) {
            if (s.view === 'plan') {
                foot.querySelector('[data-sb-act="make"]').disabled = cost(s.plan.pages, s.PL.guess) > (parseInt(s.plan.drafts.left, 10) || 0);
            }
            return;
        }
        foot.setAttribute('data-sb-view', view);

        if (s.view === 'plan') {
            var over = cost(s.plan.pages, s.PL.guess) > (parseInt(s.plan.drafts.left, 10) || 0);
            foot.innerHTML = '<button type="button" class="pg-button pg-button-ghost" data-sb-act="back"><i class="bi bi-arrow-left" aria-hidden="true"></i>' + esc(s.P.back) + '</button>'
                + '<button type="button" class="pg-button pg-button-fill" data-sb-act="make"' + (over ? ' disabled' : '') + '><i class="bi bi-stars" aria-hidden="true"></i>' + esc(s.P.make) + '</button>';
        } else {
            if (!s.busy) {
                say(s, '');
            }
            foot.innerHTML = '<button type="button" class="pg-button pg-button-fill" data-sb-act="plan"' + (s.busy ? ' disabled aria-busy="true"' : '') + '>'
                + '<i class="bi bi-stars" aria-hidden="true"></i>' + esc(s.T.plan) + '</button>';
        }
        foot.onclick = function (event) {
            var act = event.target.closest('[data-sb-act]');

            if (!act || act.disabled) {
                return;
            }

            var what = act.getAttribute('data-sb-act');

            if (what === 'plan') {
                plan(s);
            } else if (what === 'back') {
                showBrief(s);
            } else if (what === 'make') {
                make(s);
            }
        };
    }

    parts.describe = {
        show: function (pane, win) {
            if (!state || state.pane !== pane) {
                state = setup(pane, win);
            }
            if (state) {
                drawFoot(state);
            }
        },
        // The pure parts, for tests/site_brief_test.php and
        // tests/site_plan_screen_test.php.
        check: check,
        text: text,
        colour: colour,
        address: address,
        cost: cost,
        checkPlan: checkPlan,
        sketchOf: sketchOf,
        answersOf: answersOf
    };
}());
