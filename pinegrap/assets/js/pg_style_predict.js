/**
 * Pinegrap - Enterprise Website Platform
 *
 * The predictor of the visual designer's smart completion: given what is
 * typed and where (the element, its parents, the design), it ranks Bootstrap
 * classes, CSS properties and values, units, --bs-* tokens, selectors,
 * attributes and their values, and the Bootstrap JavaScript API. It knows
 * Bootstrap through the generated vocabulary
 * (assets/js/designer_vocab/bootstrap5.json, its shape in
 * docs/memory/designer/designer-bootstrap-vocabulary.md) and nothing about
 * the page: every input comes in as plain data.
 *
 * Deterministic and text-free: the same input gives the same list, and each
 * suggestion carries reason codes ('ctx', 'design', 'fixed'...) the caller
 * turns into words. Nothing here touches the document.
 *
 *   var p = PgStylePredict.create(vocab);
 *   p.classes('fl', { tag: 'div', classes: ['row'], parents: [...] });
 *
 * The context object every method takes (all keys optional):
 *   tag       the element's tag name, lower case
 *   classes   its classes
 *   attrs     its attributes, { name: value }
 *   styles    properties already set on it, { property: value }
 *   parents   ancestors nearest first, each { tag, classes }
 *   design    { classes: { name: count }, tokens: { '--x': value },
 *               ids: [{ id, classes }] } of the whole design
 *   limit     the most suggestions to return (default 15)
 *
 * Each suggestion: { label, score, codes } plus, where they apply, insert
 * (the text to put in when it differs from label), replace (the class to
 * use instead), conflicts (classes on the element it would override), bp,
 * decl (a class's declarations), swatch (a CSS colour).
 *
 * window.PgStylePredict in a browser; module.exports under Node. Plain ES5.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function (root) {
    'use strict';

    // How well the typed text matches a label. A label matches at a segment
    // start too: 'flex' finds 'd-flex', 'pri' finds 'btn-primary'.
    var MATCH_EXACT = 1000, MATCH_PREFIX = 600, MATCH_SEGMENT = 350, MATCH_CONTAINS = 100;

    // Classes an element of a tag usually carries. Bootstrap's CSS styles
    // these through the class alone, so its selectors cannot say it.
    var TAG_CLASSES = {
        a: ['btn', 'nav-link', 'dropdown-item', 'navbar-brand', 'link-primary'],
        button: ['btn', 'btn-close', 'navbar-toggler', 'dropdown-item', 'nav-link', 'accordion-button'],
        img: ['img-fluid', 'img-thumbnail', 'rounded', 'card-img-top'],
        input: ['form-control', 'form-check-input', 'form-range', 'btn-check'],
        textarea: ['form-control'],
        select: ['form-select'],
        label: ['form-label', 'form-check-label', 'col-form-label'],
        table: ['table', 'table-striped', 'table-hover'],
        ul: ['list-unstyled', 'list-inline', 'list-group', 'nav', 'navbar-nav', 'dropdown-menu', 'pagination'],
        ol: ['list-unstyled', 'list-group', 'breadcrumb'],
        li: ['list-group-item', 'nav-item', 'list-inline-item', 'breadcrumb-item', 'page-item'],
        nav: ['navbar', 'nav'],
        form: ['row', 'needs-validation'],
        h1: ['display-1', 'display-4', 'fw-bold'],
        h2: ['fw-bold', 'h4'],
        p: ['lead', 'mb-0', 'small'],
        span: ['badge', 'visually-hidden']
    };


    // Child classes a parent class implies that its CSS reaches through a
    // universal selector ('.row>*'), so the vocabulary's ctx lists cannot.
    var PARENT_CHILDREN = {
        row: /^col(-|$)/,
        'row-cols': /^col(-|$)/
    };

    // The children pattern of a parent class, its count and breakpoint left
    // out ('row-cols-md-3' is 'row-cols').
    function childrenOf(name) {
        var key = name.replace(/-\d+$/, '').replace(/-(sm|md|lg|xl|xxl)$/, '');
        return Object.prototype.hasOwnProperty.call(PARENT_CHILDREN, key) ? PARENT_CHILDREN[key] : null;
    }

    // Components a trigger element opens, by the classes around it.
    var TOGGLE_BY_CLASS = {
        'dropdown-toggle': 'dropdown',
        'navbar-toggler': 'collapse',
        'accordion-button': 'collapse',
        'carousel-control-prev': 'carousel',
        'carousel-control-next': 'carousel'
    };
    var TOGGLE_BY_PARENT = {
        dropdown: 'dropdown', dropup: 'dropdown', dropend: 'dropdown', dropstart: 'dropdown',
        'btn-group': 'dropdown', 'nav-tabs': 'tab', 'nav-pills': 'tab', 'list-group': 'tab'
    };
    // Which component a plain button most often opens.
    var TOGGLE_PRIOR = { modal: 40, collapse: 30, offcanvas: 20, dropdown: 20 };
    var TOGGLE_VALUES_BY_PARENT = { 'nav-tabs': 'tab', 'nav-pills': 'pill', 'list-group': 'list' };

    // Values Bootstrap compares in its source but the vocabulary cannot list.
    var ATTR_VALUES = {
        'data-bs-slide': ['next', 'prev'],
        'data-bs-placement': ['auto', 'bottom', 'left', 'right', 'top'],
        'data-bs-trigger': ['click', 'focus', 'hover', 'hover focus', 'manual'],
        'data-bs-backdrop': ['false', 'static', 'true'],
        'data-bs-auto-close': ['false', 'inside', 'outside', 'true'],
        'data-bs-ride': ['carousel', 'false', 'true'],
        'data-bs-theme': ['dark', 'light']
    };

    // Attributes any element may take, then those a tag adds.
    var COMMON_ATTRS = ['id', 'title', 'role', 'tabindex', 'hidden', 'lang', 'dir', 'data-bs-theme',
        'aria-label', 'aria-labelledby', 'aria-describedby', 'aria-hidden', 'aria-expanded',
        'aria-controls', 'aria-current', 'aria-live'];
    var TAG_ATTRS = {
        a: ['href', 'target', 'rel', 'download'],
        img: ['alt', 'src', 'loading', 'width', 'height', 'srcset', 'sizes', 'decoding'],
        input: ['type', 'name', 'value', 'placeholder', 'required', 'disabled', 'readonly', 'autocomplete', 'min', 'max', 'step', 'pattern', 'checked'],
        textarea: ['name', 'placeholder', 'rows', 'required', 'disabled', 'readonly', 'maxlength'],
        select: ['name', 'required', 'disabled', 'multiple'],
        button: ['type', 'disabled', 'name', 'value'],
        form: ['action', 'method', 'novalidate', 'autocomplete'],
        label: ['for'],
        iframe: ['src', 'loading', 'allow', 'allowfullscreen', 'width', 'height'],
        video: ['src', 'controls', 'autoplay', 'muted', 'loop', 'playsinline', 'poster'],
        th: ['scope', 'colspan', 'rowspan'],
        td: ['colspan', 'rowspan']
    };

    var PSEUDOS = [':hover', ':focus', ':focus-visible', ':active', ':disabled', ':first-child',
        ':last-child', ':not()', '::before', '::after', '::placeholder'];

    // Units by the kind of value a property takes; the first is the usual one.
    var UNITS = {
        length: ['rem', 'px', 'em', '%', 'vw', 'vh', 'ch'],
        size: ['%', 'rem', 'px', 'vw', 'vh', 'em', 'dvh'],
        time: ['ms', 's'],
        angle: ['deg', 'turn'],
        none: []
    };

    function unitKind(property) {
        if (/^(transition|animation)(-duration|-delay)?$/.test(property)) return 'time';
        if (/^(rotate|hue-rotate)$/.test(property)) return 'angle';
        if (/^(opacity|z-index|flex-grow|flex-shrink|order|font-weight|line-height|zoom)$/.test(property)) return 'none';
        if (/(width|height|^inset|^top$|^left$|^right$|^bottom$|flex-basis)/.test(property)) return 'size';
        return 'length';
    }

    function isColorProperty(property) {
        return /(^color$|-color$|^background$|^fill$|^stroke$|^outline$|^border(-(top|right|bottom|left))?$|^text-decoration$|^box-shadow$)/.test(property);
    }

    function isColorValue(value) {
        return /^(#[0-9a-f]{3,8}|rgba?\(|hsla?\()/i.test(String(value).trim());
    }

    function isLengthValue(value) {
        return /^-?[0-9.]+(rem|px|em|%|vw|vh)$/.test(String(value).trim());
    }

    // -- matching ---------------------------------------------------------

    // Score of a label against the typed text, or -1 when it does not match.
    // An empty query matches everything at 0.
    function match(label, query) {
        if (query === '') return 0;
        var l = label.toLowerCase(), q = query.toLowerCase();
        if (l === q) return MATCH_EXACT;
        if (l.indexOf(q) === 0) return MATCH_PREFIX - Math.min(l.length - q.length, 200);
        var at = l.indexOf(q);
        if (at === -1) return -1;
        // A segment starts after '-', '.', ':', '#' or a space.
        var re = /[-.:# ]/g, m;
        while ((m = re.exec(l)) !== null) {
            if (l.indexOf(q, m.index + 1) === m.index + 1) return MATCH_SEGMENT - Math.min(l.length - q.length, 200);
        }
        return MATCH_CONTAINS - Math.min(l.length, 90);
    }

    function list(value) {
        return Array.isArray(value) ? value : [];
    }

    function setOf(items) {
        var set = {};
        for (var i = 0; i < items.length; i++) set[items[i]] = true;
        return set;
    }

    function addCode(item, code) {
        if (item.codes.indexOf(code) === -1) item.codes.push(code);
    }

    // Highest score first; then shorter label, then label order, so ties are
    // stable across browsers.
    function finish(items, ctx) {
        items.sort(function (a, b) {
            if (b.score !== a.score) return b.score - a.score;
            if (a.label.length !== b.label.length) return a.label.length - b.label.length;
            return a.label < b.label ? -1 : (a.label > b.label ? 1 : 0);
        });
        var limit = (ctx && ctx.limit > 0) ? ctx.limit : 15;
        return items.slice(0, limit);
    }

    function normalize(ctx) {
        ctx = ctx || {};
        var design = ctx.design || {};
        var parents = list(ctx.parents).map(function (p) {
            return { tag: String((p && p.tag) || '').toLowerCase(), classes: list(p && p.classes) };
        });
        return {
            tag: String(ctx.tag || '').toLowerCase(),
            classes: list(ctx.classes),
            attrs: ctx.attrs || {},
            styles: ctx.styles || {},
            parents: parents,
            design: {
                classes: design.classes || {},
                tokens: design.tokens || {},
                ids: list(design.ids)
            },
            limit: ctx.limit
        };
    }

    // Gathers suggestions by label, keeping the best score a label gets.
    function collector() {
        var byLabel = {};
        var items = [];
        return {
            items: items,
            add: function (label, score, code, extra) {
                var item = byLabel[label];
                if (item && item.score >= score) return;
                if (!item) {
                    item = byLabel[label] = { label: label, score: score, codes: [code] };
                    items.push(item);
                }
                item.score = score;
                item.codes = [code];
                if (extra) for (var k in extra) if (Object.prototype.hasOwnProperty.call(extra, k)) item[k] = extra[k];
            }
        };
    }

    // -- the predictor ----------------------------------------------------

    function create(vocab) {
        vocab = vocab || {};
        var classes = vocab.classes || {};
        var tokens = vocab.tokens || {};
        var js = vocab.js || {};
        var fixed = vocab.fixed || {};
        var classNames = Object.keys(classes).sort();
        var tokenNames = Object.keys(tokens).sort();

        // What a class sets, as one key: two classes with the same key at the
        // same breakpoint override each other (d-flex, d-block; btn-primary,
        // btn-secondary). Classes that set nothing have none.
        var slotOf = {};
        classNames.forEach(function (name) {
            var decl = classes[name].p;
            if (!decl) return;
            var keys = Object.keys(decl).sort();
            if (keys.length) slotOf[name] = (classes[name].r ? classes[name].r[0] : '') + '|' + keys.join(',');
        });

        // CSS properties Bootstrap sets, how many classes set each, and the
        // values it gives them.
        var propertyCount = {};
        var propertyValues = {};
        classNames.forEach(function (name) {
            var decl = classes[name].p;
            if (!decl) return;
            Object.keys(decl).forEach(function (property) {
                if (property.indexOf('--') === 0) return;
                propertyCount[property] = (propertyCount[property] || 0) + 1;
                var value = String(decl[property]).replace(/\s*!important$/, '');
                if (!propertyValues[property]) propertyValues[property] = {};
                propertyValues[property][value] = (propertyValues[property][value] || 0) + 1;
            });
        });
        var propertyNames = Object.keys(propertyCount).sort();

        // Whether a class is a variant of another rather than a part inside
        // it: 'nav-tabs' sets --bs-nav-* tokens, 'card-body' sets none. A
        // class with no rule of its own ('nav-fill') only styles others and
        // is not taken for a part either.
        function modifierOf(name, base) {
            var decl = classes[name] && classes[name].p;
            if (!decl) return false;
            var prefix = '--bs-' + base + '-';
            return Object.keys(decl).some(function (k) { return k.indexOf(prefix) === 0; });
        }

        // A token's value with var() references followed, for its swatch.
        function resolve(value, own, depth) {
            var m = /^var\((--[\w-]+)\)$/.exec(String(value).trim());
            if (!m || depth > 4) return String(value).trim();
            var next = (own && own[m[1]] !== undefined) ? own[m[1]] : (tokens[m[1]] ? tokens[m[1]].v : undefined);
            return next === undefined ? String(value).trim() : resolve(next, own, depth + 1);
        }

        function swatchOf(value, own) {
            var resolved = resolve(value, own, 0);
            return isColorValue(resolved) ? resolved : '';
        }

        // Classes on the element's ancestors, nearest first, with the weight
        // of each level.
        function ancestorClasses(c) {
            var out = [];
            c.parents.forEach(function (p, depth) {
                var weight = depth === 0 ? 1 : (depth === 1 ? 0.6 : 0.3);
                p.classes.forEach(function (name) { out.push({ name: name, weight: weight }); });
            });
            return out;
        }

        // Components the element belongs to or opens, by class and parents:
        // { name: weight }. Those its own classes name (a trigger, the
        // component itself) are also set in own, when given.
        function componentsOf(c, own) {
            var found = {};
            var level = 1;
            var bump = function (name, weight) {
                if (!js[name]) return;
                if (!found[name] || found[name] < weight) found[name] = weight;
                if (own && level === 1) own[name] = true;
            };
            var look = function (names, weight) {
                names.forEach(function (cls) {
                    if (TOGGLE_BY_CLASS[cls]) bump(TOGGLE_BY_CLASS[cls], weight);
                    if (TOGGLE_BY_PARENT[cls]) bump(TOGGLE_BY_PARENT[cls], weight * 0.8);
                    var base = cls.split('-')[0];
                    bump(cls, weight);
                    bump(base, weight * 0.9);
                    if (cls === 'btn') bump('button', weight * 0.1);
                });
            };
            look(c.classes, 1);
            level = 2;
            c.parents.forEach(function (p, depth) { look(p.classes, depth === 0 ? 0.8 : 0.5); });
            return found;
        }

        // -- classes --

        function suggestClasses(query, ctx) {
            var c = normalize(ctx);
            query = String(query || '').trim();
            var present = setOf(c.classes);
            var ancestors = ancestorClasses(c);
            var tagHints = TAG_CLASSES[c.tag] || [];
            var presentSlots = {};
            c.classes.forEach(function (name) {
                if (slotOf[name]) (presentSlots[slotOf[name]] = presentSlots[slotOf[name]] || []).push(name);
            });
            var withOf = {};
            c.classes.forEach(function (name) {
                list(classes[name] && classes[name].with).forEach(function (w) { withOf[w] = true; });
            });
            var designNames = Object.keys(c.design.classes);
            var pool = classNames.concat(designNames.filter(function (n) { return !classes[n]; }).sort());
            var items = [];

            pool.forEach(function (name) {
                if (present[name]) return;
                var m = match(name, query);
                if (m < 0) return;
                var entry = classes[name] || {};
                var item = { label: name, score: m, codes: [] };
                var context = 0;

                // A parent carries a class this one is styled under.
                var ctxSet = entry.ctx ? setOf(entry.ctx) : null;
                ancestors.forEach(function (a) {
                    var w = 0;
                    if (ctxSet && ctxSet[a.name]) w = 220;
                    else if (a.weight === 1 && name.indexOf(a.name + '-') === 0 && a.name.length > 2 && entry.p && !modifierOf(name, a.name)) w = 160;
                    else if (childrenOf(a.name) && childrenOf(a.name).test(name)) w = 200;
                    if (w * a.weight > context) context = w * a.weight;
                });
                if (context > 0) { item.score += context; addCode(item, 'ctx'); }

                // A state or modifier of a class the element has.
                if (withOf[name]) { item.score += 150; addCode(item, 'state'); }

                // A variant of a class the element has (btn → btn-primary).
                for (var i = 0; i < c.classes.length; i++) {
                    if (name.indexOf(c.classes[i] + '-') === 0) { item.score += 120; addCode(item, 'family'); break; }
                }

                // What elements of this tag usually carry.
                if (tagHints.indexOf(name) !== -1) { item.score += 100; addCode(item, 'tag'); }

                // Used elsewhere in the design.
                var used = c.design.classes[name] || 0;
                if (used > 0) { item.score += Math.min(90, 15 * used); addCode(item, 'design'); }
                if (!classes[name]) addCode(item, 'custom');

                // Responsive variant of what the element sets (d-none → d-md-block).
                if (entry.r) {
                    item.bp = entry.r[0];
                    var base = slotOf[entry.r[1]] || (slotOf[name] || '').replace(/^[a-z]*\|/, '|');
                    if (base && presentSlots[base]) {
                        // d-md-none beside d-none changes nothing.
                        var same = presentSlots[base].some(function (other) {
                            return JSON.stringify(classes[other].p) === JSON.stringify(entry.p);
                        });
                        if (same) { item.score -= 200; addCode(item, 'redundant'); }
                        else { item.score += 80; addCode(item, 'bp'); }
                    }
                    else if (query === '' || m < MATCH_EXACT) item.score -= 40;
                }

                // Would override a class the element has.
                if (slotOf[name] && presentSlots[slotOf[name]]) {
                    item.conflicts = presentSlots[slotOf[name]].slice();
                    item.score -= 150;
                    addCode(item, 'conflict');
                }

                // Fixed colour: last, with the class that follows the colour mode.
                if (fixed[name]) {
                    item.replace = fixed[name];
                    item.score -= 300;
                    addCode(item, 'fixed');
                }

                if (entry.p) item.decl = entry.p;

                // With nothing typed, only what the context points at.
                if (query === '' && !item.codes.length) return;
                if (query === '' && item.codes.length === 1 && (item.codes[0] === 'custom' || item.codes[0] === 'fixed' || item.codes[0] === 'conflict')) return;
                items.push(item);
            });

            return finish(items, c);
        }

        // -- CSS properties and values --

        function suggestProperties(query, ctx, extra) {
            var c = normalize(ctx);
            query = String(query || '').trim();
            var names = propertyNames.slice();
            list(extra).forEach(function (n) { if (!propertyCount[n] && names.indexOf(n) === -1) names.push(n); });
            var items = [];
            names.forEach(function (property) {
                if (c.styles[property] !== undefined) return;
                var m = match(property, query);
                if (m < 0 || (query === '' && !propertyCount[property])) return;
                var item = { label: property, score: m, codes: [] };
                if (propertyCount[property]) {
                    item.score += Math.min(120, Math.round(20 * Math.log(1 + propertyCount[property])));
                    addCode(item, 'bs');
                }
                items.push(item);
            });
            return finish(items, c);
        }

        // Values for a property: what Bootstrap gives it, fitting tokens,
        // design tokens, and units after a number.
        function suggestValues(property, query, ctx) {
            var c = normalize(ctx);
            property = String(property || '').trim().toLowerCase();
            query = String(query || '').trim();
            var items = [];
            var seen = {};
            var push = function (label, score, code, extra) {
                if (seen[label]) return;
                seen[label] = true;
                var item = { label: label, score: score, codes: [code] };
                if (extra) for (var k in extra) if (Object.prototype.hasOwnProperty.call(extra, k)) item[k] = extra[k];
                items.push(item);
            };

            // A number typed: the units the property takes.
            var number = /^(-?[0-9]*\.?[0-9]+)([a-z%]*)$/i.exec(query);
            if (number) {
                var units = UNITS[unitKind(property)];
                units.forEach(function (unit, i) {
                    if (number[2] && unit.indexOf(number[2].toLowerCase()) !== 0) return;
                    push(number[1] + unit, 700 - i * 20, 'unit');
                });
            }

            var color = isColorProperty(property);
            var values = propertyValues[property] || {};
            Object.keys(values).forEach(function (value) {
                var m = match(value, query);
                if (m < 0) return;
                var extra = {};
                var sw = color ? swatchOf(value) : '';
                if (sw) extra.swatch = sw;
                push(value, m + Math.min(100, 15 * values[value]), 'bs', extra);
            });

            // Tokens whose value fits the property: colours for a colour,
            // lengths for a length.
            var fits = function (value) {
                var resolved = resolve(value, c.design.tokens, 0);
                if (color) return isColorValue(resolved);
                if (unitKind(property) === 'length' || unitKind(property) === 'size') return isLengthValue(resolved);
                if (/font-family/.test(property)) return /,/.test(resolved);
                return false;
            };
            var tokenItem = function (name, value, code, bonus) {
                if (!fits(value)) return;
                var label = 'var(' + name + ')';
                var m = Math.max(match(label, query), match(name, query));
                if (m < 0) return;
                var extra = {};
                var sw = color ? swatchOf(value, c.design.tokens) : '';
                if (sw) extra.swatch = sw;
                push(label, m + bonus, code, extra);
            };
            Object.keys(c.design.tokens).sort().forEach(function (name) {
                tokenItem(name, c.design.tokens[name], 'design', 120);
            });
            tokenNames.forEach(function (name) {
                if (/-rgb$/.test(name) || tokens[name].v === undefined || tokens[name].on) return;
                tokenItem(name, tokens[name].v, 'token', 40);
            });
            return finish(items, c);
        }

        // -- tokens --

        function suggestTokens(query, ctx) {
            var c = normalize(ctx);
            query = String(query || '').trim();
            var own = setOf(c.classes);
            var ancestors = ancestorClasses(c);
            var items = [];
            var push = function (name, value, codes, bonus) {
                var m = match(name, query);
                if (m < 0) return;
                var item = { label: name, score: m + bonus, codes: codes };
                var sw = swatchOf(value, c.design.tokens);
                if (sw) item.swatch = sw;
                items.push(item);
            };
            Object.keys(c.design.tokens).sort().forEach(function (name) {
                push(name, c.design.tokens[name], ['design'], 150);
            });
            tokenNames.forEach(function (name) {
                if (c.design.tokens[name] !== undefined) return;
                var entry = tokens[name];
                var bonus = 0, codes = ['token'];
                list(entry.on).forEach(function (cls) {
                    if (own[cls] && bonus < 250) { bonus = 250; codes = ['token', 'own']; }
                });
                if (!bonus) {
                    ancestors.forEach(function (a) {
                        if (list(entry.on).indexOf(a.name) !== -1 && bonus < 150 * a.weight) { bonus = 150 * a.weight; codes = ['token', 'ctx']; }
                    });
                }
                // A component's token far from its component is noise.
                if (entry.on && !bonus) {
                    if (query === '') return;
                    bonus = -100;
                }
                if (query === '' && !bonus) return;
                push(name, entry.v === undefined ? '' : entry.v, codes, bonus);
            });
            return finish(items, c);
        }

        // -- selectors --

        function suggestSelectors(query, ctx) {
            var c = normalize(ctx);
            query = String(query || '').trim();
            var items = [];
            var seen = {};
            var push = function (label, bonus, code) {
                if (seen[label]) return;
                var m = match(label, query);
                if (m < 0) return;
                seen[label] = true;
                items.push({ label: label, score: m + bonus, codes: [code] });
            };
            var own = c.classes.map(function (n) { return '.' + n; });
            if (c.attrs.id) push('#' + c.attrs.id, 300, 'own');
            own.forEach(function (sel) { push(sel, 250, 'own'); });
            if (own.length > 1) push(own.join(''), 200, 'own');
            if (c.parents.length && c.parents[0].classes.length && own.length) {
                push('.' + c.parents[0].classes[0] + ' ' + own[0], 150, 'ctx');
            }
            // After a selector, its states.
            var typed = /^(.*?)(:[\w-]*)?$/.exec(query);
            if (typed && typed[1] && (seen[typed[1]] || /^[.#]/.test(typed[1]))) {
                PSEUDOS.forEach(function (p, i) {
                    push(typed[1] + p, 100 - i, 'pseudo');
                });
            }
            return finish(items, c);
        }

        // -- attributes --

        function suggestAttributes(query, ctx) {
            var c = normalize(ctx);
            query = String(query || '').trim();
            var out = collector();
            var add = function (name, bonus, code) {
                if (c.attrs[name] !== undefined) return;
                var m = match(name, query);
                if (m >= 0) out.add(name, m + Math.round(bonus), code);
            };
            var own = {};
            var found = componentsOf(c, own);
            Object.keys(found).sort().forEach(function (component) {
                var weight = found[component];
                Object.keys(js[component].attrs || {}).sort().forEach(function (attr) {
                    // The component's own element takes its options, a trigger
                    // names what it opens; an element inside closes it.
                    var bonus = own[component] ? 150 * weight : 30 * weight;
                    if (own[component] && (attr === 'data-bs-toggle' || attr === 'data-bs-target')) bonus += 100;
                    if (attr === 'data-bs-dismiss' && !own[component]) bonus += 250 * weight;
                    add(attr, bonus, 'component');
                });
            });
            (TAG_ATTRS[c.tag] || []).forEach(function (attr) { add(attr, 120, 'tag'); });
            COMMON_ATTRS.forEach(function (attr) { add(attr, 0, 'common'); });
            if (query !== '') {
                Object.keys(js).sort().forEach(function (component) {
                    Object.keys(js[component].attrs || {}).sort().forEach(function (attr) { add(attr, -50, 'component'); });
                });
            }
            return finish(out.items, c);
        }

        function suggestAttributeValues(attr, query, ctx) {
            var c = normalize(ctx);
            attr = String(attr || '').trim().toLowerCase();
            query = String(query || '').trim();
            var out = collector();
            var add = function (label, bonus, code) {
                var m = match(label, query);
                if (m >= 0) out.add(label, m + Math.round(bonus), code);
            };
            var found = componentsOf(c);

            if (attr === 'data-bs-target' || attr === 'aria-controls' || attr === 'href') {
                // Ids of the design's elements a trigger can open, those of a
                // component the context points at first.
                var prefix = attr === 'aria-controls' ? '' : '#';
                c.design.ids.forEach(function (entry) {
                    if (!entry || !entry.id) return;
                    var bonus = 0;
                    list(entry.classes).forEach(function (cls) {
                        if (js[cls]) bonus = Math.max(bonus, 100 + (found[cls] ? 150 * found[cls] : 0));
                    });
                    if (attr === 'href' && !bonus) return;
                    add(prefix + entry.id, bonus, 'design');
                });
                return finish(out.items, c);
            }

            if (attr === 'data-bs-toggle') {
                // The parent decides tab, pill or list.
                c.parents.forEach(function (p, depth) {
                    p.classes.forEach(function (cls) {
                        if (TOGGLE_VALUES_BY_PARENT[cls]) add(TOGGLE_VALUES_BY_PARENT[cls], 300 - depth * 50, 'ctx');
                    });
                });
            }

            var fixedValues = false;
            Object.keys(js).sort().forEach(function (component) {
                var values = (js[component].attrs || {})[attr];
                if (!values) return;
                if (values.length) fixedValues = true;
                values.forEach(function (value) {
                    var bonus = found[component] ? 250 * found[component] : (TOGGLE_PRIOR[value] || 0);
                    add(value, bonus, found[component] ? 'ctx' : 'component');
                });
            });

            // A boolean option takes true or false, unless the attribute is
            // one with named values (Collapse's toggle option is also
            // data-bs-toggle).
            if (!fixedValues) {
                var option = attr.replace(/^data-bs-/, '').replace(/-([a-z])/g, function (all, ch) { return ch.toUpperCase(); });
                Object.keys(js).sort().forEach(function (component) {
                    var spec = (js[component].options || {})[option];
                    if (spec && /boolean/.test(spec[0]) && (js[component].attrs || {})[attr]) {
                        add('true', found[component] ? 100 : 0, 'option');
                        add('false', found[component] ? 100 : 0, 'option');
                    }
                });
            }
            list(ATTR_VALUES[attr]).forEach(function (value) { add(value, 0, 'known'); });
            return finish(out.items, c);
        }

        // -- Bootstrap JavaScript API --

        // 'bootstrap.Modal' style names, those of the components the
        // context points at first.
        function suggestJsClasses(query, ctx) {
            var c = normalize(ctx);
            query = String(query || '').trim();
            var found = componentsOf(c);
            var items = [];
            Object.keys(js).sort().forEach(function (component) {
                var label = 'bootstrap.' + js[component]['class'];
                var m = Math.max(match(label, query), match(js[component]['class'], query));
                if (m < 0) return;
                var bonus = found[component] ? Math.round(200 * found[component]) : 0;
                items.push({ label: label, score: m + bonus, codes: [bonus ? 'ctx' : 'component'], component: component });
            });
            return finish(items, c);
        }

        // Members of one class: static ones after 'bootstrap.Modal.', the
        // instance's after an instance.
        function suggestJsMembers(className, query, isStatic) {
            query = String(query || '').trim();
            var component = null;
            Object.keys(js).forEach(function (name) {
                if (js[name]['class'] === className || name === className) component = name;
            });
            if (!component) return [];
            var names = isStatic ? list(js[component]['static']) : list(js[component].methods);
            var items = [];
            names.forEach(function (name) {
                var m = match(name, query);
                if (m < 0) return;
                // The everyday calls first.
                var bonus = /^(getOrCreateInstance|show|hide|toggle)$/.test(name) ? 100 : 0;
                items.push({ label: name, score: m + bonus, codes: [isStatic ? 'static' : 'method'] });
            });
            return finish(items, { limit: 50 });
        }

        // Event names in full ('shown.bs.modal').
        function suggestJsEvents(query, ctx) {
            var c = normalize(ctx);
            query = String(query || '').trim();
            var found = componentsOf(c);
            var items = [];
            Object.keys(js).sort().forEach(function (component) {
                list(js[component].events).forEach(function (event) {
                    var label = event + '.bs.' + component;
                    var m = match(label, query);
                    if (m < 0) return;
                    var bonus = found[component] ? Math.round(200 * found[component]) : 0;
                    if (query === '' && !bonus) return;
                    items.push({ label: label, score: m + bonus, codes: [bonus ? 'ctx' : 'component'] });
                });
            });
            return finish(items, c);
        }

        return {
            version: vocab.version || '',
            breakpoints: vocab.breakpoints || {},
            classes: suggestClasses,
            properties: suggestProperties,
            values: suggestValues,
            tokens: suggestTokens,
            selectors: suggestSelectors,
            attributes: suggestAttributes,
            attributeValues: suggestAttributeValues,
            jsClasses: suggestJsClasses,
            jsMembers: suggestJsMembers,
            jsEvents: suggestJsEvents,
            components: function (ctx) { return componentsOf(normalize(ctx)); },
            slot: function (name) { return slotOf[name] || ''; }
        };
    }

    var api = { create: create, match: match };

    root.PgStylePredict = api;

    if (typeof module === 'object' && module && module.exports) {
        module.exports = api;
    }
})(typeof window !== 'undefined' ? window : this);
