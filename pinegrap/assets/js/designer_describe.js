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
 * foot's "Plan my site". Nothing is kept on the server.
 *
 * check() is the twin of pg_site_brief_check() (includes/fn/site_brief.php):
 * tests/site_brief_test.php runs both on the same briefs.
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
            tried: false
        };

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

        result.brief.keys = input.keys;
        keep(result.brief);

        // The plan of pages is asked by parts.describe.plan once the
        // server's action is there; until then the brief is kept.
        if (typeof parts.describe.plan === 'function') {
            parts.describe.plan(result.brief, s);
        } else {
            say(s, s.T.kept);
        }
    }

    function drawFoot(s) {
        var foot = s.win.querySelector('.sd-ns-part-foot');

        if (!foot || foot.getAttribute('data-part') !== 'describe') {
            return;
        }
        foot.innerHTML = '<button type="button" class="pg-button pg-button-fill" data-sb-act="plan"><i class="bi bi-stars" aria-hidden="true"></i>' + esc(s.T.plan) + '</button>';
        foot.onclick = function (event) {
            if (event.target.closest('[data-sb-act="plan"]')) {
                plan(s);
            }
        };
    }

    parts.describe = {
        show: function (pane, win) {
            if (!state || state.pane !== pane) {
                state = setup(pane, win);
            }
            if (state) {
                say(state, '');
                drawFoot(state);
            }
        },
        // The pure parts, for tests/site_brief_test.php.
        check: check,
        text: text,
        colour: colour
    };
}());
