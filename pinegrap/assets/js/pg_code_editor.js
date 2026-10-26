/**
 * Pinegrap - Enterprise Website Platform
 *
 * Code editor facade: window.PgCodeEditor.
 *
 * The one place the panel talks to the code editor engine. The engine is
 * CodeMirror 6, served as a single bundle (assets/lib/code_editor/
 * cm6.bundle.js, window.PgCM) that this file loads the first time a screen
 * asks for an editor, so a page that never opens one never downloads it.
 * Screens never touch PgCM directly: what they need is a method here, and a
 * feature added later (a completion source, a lint, a keymap, Emmet) is
 * registered here once and reaches every editor.
 *
 *   PgCodeEditor.configure({ bundle: url, phrases: {...},     once per page
 *                            minimap: bool })
 *   PgCodeEditor.open(host, opts).then(function (ed) { ... })  load + create
 *   PgCodeEditor.attach(textarea, opts)                        like fromTextArea
 *   PgCodeEditor.addCompletionSource(language, source)         registry
 *   PgCodeEditor.addCompletionRanker(language, ranker)         registry
 *   PgCodeEditor.addExtension(function (opts) { return ext })  registry
 *   PgCodeEditor.findAll(text, query)                          hits, no editor
 *
 * An editor instance (see pgCeCreate) offers getValue / setValue,
 * setLanguage, setReadOnly, setTheme, goTo and scrollTo, the search panel,
 * format (CSS, JavaScript and JSON; Shift+Alt+F in both keymaps), an
 * optional minimap beside the scroll bar (`minimap`, setMinimap), tracked
 * ranges that follow the text as it is edited (track / tracked / trackedAt)
 * and locks: ranges no edit may touch, each with optional "islands" inside
 * it that stay editable. A lock without islands is read-only as a whole; a
 * lock with islands lets only the islands change (an attribute's value, an
 * element's text). The caller decides the ranges; this file only enforces
 * them, through the engine's change filter, and reports a refused edit to
 * onLockHit so the screen can say why. With `lockGutter` the locked lines
 * carry a lock marker in the gutter (closed: nothing on the line may
 * change; open: the line has an island), titled by `lockTitle(tag, partly)`.
 *
 * Labels are the caller's: the engine's own panel texts come through
 * `phrases` (the search panel, the line prompt), everything else is drawn
 * by the screen. No _sdT() / lang() here.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    if (window.PgCodeEditor) return;

    var _cfg = {
        bundle: '',          // URL of cm6.bundle.js; derived from this script's URL when empty
        phrases: null,       // engine texts: { 'Find': '…', 'Replace': '…', … }
        minimap: false       // page default of opts.minimap
    };
    var _loading = null;     // Promise while the bundle is on its way
    var _completions = {};   // language → [source]
    var _rankers = {};       // language → [ranker]
    var _extensions = [];    // function (opts) → extension (or array / null)
    var _own = null;         // this script's URL, for the bundle default

    try { _own = (document.currentScript && document.currentScript.src) ? document.currentScript.src : null; } catch (e) {}

    var LANGUAGES = { html: 1, css: 1, javascript: 1, json: 1 };

    function _normLanguage(lang) {
        lang = String(lang || '').toLowerCase();
        if (lang === 'js' || lang === 'module') return 'javascript';
        if (lang === 'htmlmixed' || lang === 'xml' || lang === 'svg') return 'html';
        return LANGUAGES[lang] ? lang : 'html';
    }

    function configure(opts) {
        opts = opts || {};
        if (typeof opts.bundle === 'string' && opts.bundle) _cfg.bundle = opts.bundle;
        if (opts.phrases && typeof opts.phrases === 'object') _cfg.phrases = opts.phrases;
        if (typeof opts.minimap === 'boolean') _cfg.minimap = opts.minimap;
    }

    function isLoaded() {
        return !!(window.PgCM && window.PgCM.view && window.PgCM.state);
    }

    function _bundleUrl() {
        if (_cfg.bundle) return _cfg.bundle;
        if (_own) return _own.replace(/assets\/js\/pg_code_editor\.js(\?.*)?$/, 'assets/lib/code_editor/cm6.bundle.js');
        return 'assets/lib/code_editor/cm6.bundle.js';
    }

    // Loads the bundle once. Resolves with PgCM; rejects when the script
    // cannot be fetched, so a screen can fall back to its plain textarea.
    function load() {
        if (isLoaded()) return Promise.resolve(window.PgCM);
        if (_loading) return _loading;
        _loading = new Promise(function (resolve, reject) {
            var s = document.createElement('script');
            s.src = _bundleUrl();
            s.async = true;
            s.onload = function () {
                if (isLoaded()) resolve(window.PgCM);
                else { _loading = null; reject(new Error('The code editor bundle loaded but PgCM is missing.')); }
            };
            s.onerror = function () {
                _loading = null;
                if (s.parentNode) s.parentNode.removeChild(s);
                reject(new Error('The code editor bundle could not be loaded: ' + s.src));
            };
            document.head.appendChild(s);
        });
        return _loading;
    }

    // ── Registries ───────────────────────────────────────────────────────
    // A completion source is the engine's shape: function (context) →
    // { from, options: [{ label, type, detail, apply }] } | null. Registered
    // for 'html', 'css', 'javascript' or 'json', it joins every editor of that
    // language, those already open included at their next reconfigure. It
    // also receives the syntax around the cursor as a second argument
    // ({ tree, node }: the state's tree and the innermost node ending at or
    // holding the position), so a screen never asks the engine for it.
    function addCompletionSource(language, source) {
        var lang = _normLanguage(language);
        if (typeof source !== 'function') return;
        if (!_completions[lang]) _completions[lang] = [];
        _completions[lang].push(source);
    }

    // A ranker moves completion options up or down the list:
    // function (option, context, { tree, node, language }) → number, added
    // to the option's boost (the sum is kept within the engine's -99..99).
    // It sees every option offered where `language` is spoken, the engine's
    // own (CSS properties, HTML tags) included: inside an HTML editor a
    // 'css' ranker ranks what is offered in <style> and style="", a
    // 'javascript' one what is offered in <script> and on…="". It applies
    // to editors already open from their next completion.
    function addCompletionRanker(language, ranker) {
        var lang = _normLanguage(language);
        if (typeof ranker !== 'function') return;
        if (!_rankers[lang]) _rankers[lang] = [];
        _rankers[lang].push(ranker);
    }

    // An extension factory: function (opts, cm) → extension | extension[] |
    // null. Called for every editor created after the registration.
    function addExtension(factory) {
        if (typeof factory === 'function') _extensions.push(factory);
    }

    // ── Theme ────────────────────────────────────────────────────────────
    // The dark theme follows the designer's own code colours (the HTML tree
    // of the bottom panel); the light one follows the panel's light scheme.
    function _themeExtensions(cm, dark) {
        var t = cm.highlight.tags;
        var c = dark ? {
            bg: '#181a1e', fg: '#c9d1d9', gutter: '#161b22', gutterFg: '#484f58', border: '#2e333a',
            line: 'rgba(255,255,255,.04)', sel: 'rgba(13,110,253,.28)', match: 'rgba(13,110,253,.18)',
            caret: '#ffffff', tag: '#79c0ff', bracket: '#89b4e8', attr: '#a5c8e1', value: '#a8c97d',
            content: '#d4a574', comment: '#6a737d', keyword: '#ff7b72', number: '#79c0ff', prop: '#79c0ff',
            fn: '#d2a8ff', punct: '#8b949e', invalid: '#ff7b72', panel: '#22262b', panelFg: '#c9d1d9'
        } : {
            bg: '#ffffff', fg: '#24292f', gutter: '#f6f8fa', gutterFg: '#8c959f', border: '#d0d7de',
            line: 'rgba(0,0,0,.035)', sel: 'rgba(13,110,253,.18)', match: 'rgba(13,110,253,.12)',
            caret: '#000000', tag: '#116329', bracket: '#24292f', attr: '#0550ae', value: '#0a3069',
            content: '#24292f', comment: '#6e7781', keyword: '#cf222e', number: '#0550ae', prop: '#0550ae',
            fn: '#8250df', punct: '#57606a', invalid: '#cf222e', panel: '#f6f8fa', panelFg: '#24292f'
        };
        var theme = cm.view.EditorView.theme({
            '&': { backgroundColor: c.bg, color: c.fg, height: '100%', fontSize: '12px' },
            '.cm-scroller': { fontFamily: "'Consolas', 'Cascadia Code', 'SFMono-Regular', Menlo, monospace", lineHeight: '1.55' },
            '.cm-content': { caretColor: c.caret, padding: '4px 0' },
            '.cm-cursor, .cm-dropCursor': { borderLeftColor: c.caret },
            '&.cm-focused > .cm-scroller > .cm-selectionLayer .cm-selectionBackground, .cm-selectionBackground, .cm-content ::selection': { backgroundColor: c.sel },
            '.cm-activeLine': { backgroundColor: c.line },
            '.cm-activeLineGutter': { backgroundColor: c.line },
            '.cm-selectionMatch': { backgroundColor: c.match },
            '.cm-searchMatch': { backgroundColor: 'rgba(255,193,7,.25)', outline: '1px solid rgba(255,193,7,.5)' },
            '.cm-searchMatch.cm-searchMatch-selected': { backgroundColor: 'rgba(255,193,7,.5)' },
            '.cm-gutters': { backgroundColor: c.gutter, color: c.gutterFg, borderRight: '1px solid ' + c.border },
            '.cm-lineNumbers .cm-gutterElement': { minWidth: '2.6em', padding: '0 6px 0 4px' },
            '.cm-foldGutter .cm-gutterElement': { cursor: 'pointer' },
            '.cm-foldPlaceholder': { backgroundColor: 'transparent', border: '1px solid ' + c.border, color: c.gutterFg, margin: '0 2px', padding: '0 4px' },
            '.cm-matchingBracket, &.cm-focused .cm-matchingBracket': { backgroundColor: 'rgba(137,180,232,.25)', outline: '1px solid rgba(137,180,232,.5)' },
            '.cm-nonmatchingBracket': { color: c.invalid },
            '.cm-panels': { backgroundColor: c.panel, color: c.panelFg, borderColor: c.border, fontSize: '12px' },
            '.cm-panels.cm-panels-top': { borderBottom: '1px solid ' + c.border },
            '.cm-panels.cm-panels-bottom': { borderTop: '1px solid ' + c.border },
            '.cm-panel input, .cm-panel button': { fontSize: '12px' },
            '.cm-panel.cm-search [name=close]': { color: c.panelFg },
            '.cm-tooltip': { backgroundColor: c.panel, color: c.panelFg, border: '1px solid ' + c.border },
            '.cm-tooltip.cm-tooltip-autocomplete > ul > li[aria-selected]': { backgroundColor: c.sel, color: c.fg },
            '.cm-tooltip.cm-tooltip-autocomplete > ul': { fontFamily: "'Consolas', 'Cascadia Code', 'SFMono-Regular', Menlo, monospace" },
            '.cm-diagnostic': { padding: '3px 6px 3px 8px' },
            '.cm-lintRange-error': { backgroundImage: 'none', borderBottom: '1px wavy ' + c.invalid, textDecorationSkipInk: 'none' },
            // Locks: a tinted band over text no edit may touch; an island
            // inside it keeps the plain background so what may change stands out.
            '.pg-ce-lock': { backgroundColor: 'rgba(240,120,32,.09)' },
            '.pg-ce-mark': { backgroundColor: 'rgba(13,110,253,.16)', borderRadius: '2px' },
            '.pg-ce-mark-line': { backgroundColor: 'rgba(13,110,253,.10)' },
            // The lock gutter: one marker per locked line, in the tint's hue.
            '.pg-ce-lock-gutter .cm-gutterElement': { width: '14px', textAlign: 'center' },
            '.pg-ce-lock-marker': { display: 'inline-block', color: dark ? 'rgba(240,140,60,.9)' : 'rgba(205,100,20,.9)', lineHeight: '1' },
            '.pg-ce-lock-marker svg': { width: '9px', height: '9px', verticalAlign: '-1px' },
            '.pg-ce-lock-marker-partly': { opacity: '.6' },
            '.pg-ce-line-gutter .cm-gutterElement': { position: 'relative', width: '18px' },
            // A colour option's swatch in the completion list.
            '.pg-ce-swatch': { display: 'inline-block', width: '10px', height: '10px', marginRight: '5px', verticalAlign: '-1px', borderRadius: '2px', border: '1px solid rgba(128,128,128,.55)' }
        }, { dark: !!dark });
        var style = cm.language.HighlightStyle.define([
            { tag: t.tagName, color: c.tag, fontWeight: '600' },
            { tag: [t.angleBracket, t.bracket, t.squareBracket, t.brace, t.paren], color: c.bracket },
            { tag: t.attributeName, color: c.attr },
            { tag: [t.attributeValue, t.string, t.special(t.string)], color: c.value },
            { tag: t.content, color: c.content },
            { tag: [t.comment, t.lineComment, t.blockComment, t.docComment], color: c.comment, fontStyle: 'italic' },
            { tag: [t.keyword, t.controlKeyword, t.operatorKeyword, t.modifier, t.definitionKeyword, t.moduleKeyword], color: c.keyword },
            { tag: [t.number, t.integer, t.float, t.bool, t.null, t.atom, t.unit], color: c.number },
            { tag: [t.propertyName, t.definition(t.propertyName), t.labelName, t.className], color: c.prop },
            { tag: [t.function(t.variableName), t.function(t.propertyName), t.macroName], color: c.fn },
            { tag: [t.variableName, t.definition(t.variableName), t.typeName, t.namespace], color: c.fg },
            { tag: [t.operator, t.punctuation, t.separator, t.derefOperator, t.meta, t.processingInstruction], color: c.punct },
            { tag: t.invalid, color: c.invalid },
            { tag: t.heading, fontWeight: 'bold' },
            { tag: t.emphasis, fontStyle: 'italic' },
            { tag: t.strong, fontWeight: 'bold' },
            { tag: t.link, color: c.attr, textDecoration: 'underline' },
            { tag: t.url, color: c.value }
        ]);
        return [theme, cm.language.syntaxHighlighting(style)];
    }

    // ── Language ─────────────────────────────────────────────────────────
    function _languageExtension(cm, lang) {
        switch (lang) {
            case 'css':        return cm.langCss.css();
            case 'javascript': return cm.langJavascript.javascript();
            case 'json':       return cm.langJson.json();
            default:           return cm.langHtml.html({ matchClosingTags: true, autoCloseTags: true });
        }
    }

    function _languageObject(cm, lang) {
        switch (lang) {
            case 'css':        return cm.langCss.cssLanguage;
            case 'javascript': return cm.langJavascript.javascriptLanguage;
            case 'json':       return cm.langJson.jsonLanguage;
            default:           return cm.langHtml.htmlLanguage;
        }
    }

    // ── Completion ───────────────────────────────────────────────────────
    // The registered completion sources, as language data so they only fire
    // where their language is spoken: an HTML editor also carries the CSS
    // ones (for <style> and style="") and the JavaScript ones (for <script>
    // and on…=""), and its HTML ones stay out of those regions.
    function _completionExtensions(cm, lang) {
        var langs = (lang === 'html') ? ['html', 'css', 'javascript'] : [lang];
        var out = [];
        langs.forEach(function (name) {
            var langObj = _languageObject(cm, name);
            (_completions[name] || []).forEach(function (source) {
                out.push(langObj.data.of({ autocomplete: function (context) {
                    var tree = cm.language.syntaxTree(context.state);
                    return source(context, { tree: tree, node: tree.resolveInner(context.pos, -1) });
                } }));
            });
        });
        return out;
    }

    // The language spoken around a node: the innermost top node that
    // carries language data (a <style> block, a style="" attribute, a
    // <script>) names it. null when none does.
    function _regionLanguage(cm, node) {
        var names = ['html', 'css', 'javascript', 'json'];
        for (var n = node; n; n = n.parent) {
            if (!n.type.isTop) continue;
            var data = n.type.prop(cm.language.languageDataProp);
            if (!data) continue;
            for (var i = 0; i < names.length; i++) {
                if (_languageObject(cm, names[i]).data === data) return names[i];
            }
        }
        return null;
    }

    // The registered rankers of the region's language applied to a
    // result: each option a ranker moves is copied with its new boost, the
    // others are passed on as they are.
    function _rankResult(cm, context, result) {
        if (!result || !result.options || !result.options.length) return result;
        var tree = cm.language.syntaxTree(context.state);
        tree.resolveInner(0, 0);
        var node = tree.resolveInner(context.pos, -1);
        var lang = _regionLanguage(cm, node);
        var rankers = lang ? _rankers[lang] : null;
        if (!rankers || !rankers.length) return result;
        var info = { tree: tree, node: node, language: lang };
        var options = result.options.map(function (option) {
            var boost = 0;
            for (var i = 0; i < rankers.length; i++) {
                var b;
                try { b = Number(rankers[i](option, context, info)); } catch (e) { b = 0; }
                if (isFinite(b)) boost += b;
            }
            if (!boost) return option;
            var copy = {};
            for (var k in option) { if (Object.prototype.hasOwnProperty.call(option, k)) copy[k] = option[k]; }
            copy.boost = Math.max(-99, Math.min(99, (option.boost || 0) + boost));
            return copy;
        });
        var out = {};
        for (var key in result) { if (Object.prototype.hasOwnProperty.call(result, key)) out[key] = result[key]; }
        out.options = options;
        return out;
    }

    // The engine asks its completion sources through these slots: slot i
    // runs the i-th source of the language data at the cursor (the engine's
    // own and the registered ones, in the order the engine would ask them)
    // and hands its result through the rankers. 16 slots are far more than
    // any region has (three to four).
    //
    // Before each source the tree's lookup cache is reset to the top
    // (`resolveInner(0, 0)`). resolveInner() starts from the node it found
    // last, and from the value of a style="" or on…="" attribute (or a node
    // inside it) it does not enter the CSS or JavaScript mounted over that
    // value: after such a lookup a source got the attribute value instead
    // of the property name, and offered nothing there.
    var COMPLETION_SLOTS = 16;

    function _completionSlots(cm) {
        var slots = [];
        for (var i = 0; i < COMPLETION_SLOTS; i++) {
            slots.push((function (index) {
                return function (context) {
                    var list = context.state.languageDataAt('autocomplete', context.pos);
                    var source = list[index];
                    if (!source) return null;
                    if (typeof source !== 'function') source = cm.autocomplete.completeFromList(source);
                    cm.language.syntaxTree(context.state).resolveInner(0, 0);
                    var result = source(context);
                    if (result && typeof result.then === 'function') {
                        return result.then(function (r) { return _rankResult(cm, context, r); });
                    }
                    return _rankResult(cm, context, result);
                };
            })(i));
        }
        return slots;
    }

    // The colour drawn beside a completion option: its own `swatch` (a
    // design token's value, which the label does not show), else its label
    // when that is a colour: #hex, a colour function, or a named colour the
    // CSS completions offer (type 'constant'). '' when there is none.
    function _swatchColor(option) {
        if (!option) return '';
        if (typeof option.swatch === 'string' && option.swatch) return option.swatch;
        var label = String(option.label || '');
        if (/^#(?:[0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/i.test(label)) return label;
        if (/^(?:rgba?|hsla?|hwb|lab|lch|oklab|oklch)\([^()]*\)$/i.test(label)) return label;
        if ((option.type === 'constant') && /^[a-z]+$/i.test(label)
            && !/^(?:transparent|currentcolor|inherit|initial|unset|revert)$/i.test(label)
            && (typeof CSS !== 'undefined') && CSS.supports && CSS.supports('color', label)) return label;
        return '';
    }

    function _swatchOption() {
        return {
            position: 30,
            render: function (completion) {
                var color = _swatchColor(completion);
                if (!color) return null;
                var el = document.createElement('span');
                el.className = 'pg-ce-swatch';
                el.setAttribute('aria-hidden', 'true');
                el.style.backgroundColor = color;
                return el;
            }
        };
    }

    // Syntax errors as diagnostics: the parser leaves an error node where a
    // tag or a block does not close. JSON has a real parser to ask. An empty
    // document has nothing to check: the HTML parser leaves a zero-length
    // error node at its start and the JSON parser reports an unexpected end,
    // which would mark every empty field red. A blank JSON document counts as
    // empty too.
    //
    // The tree is read only once it covers the whole document. The engine
    // parses in the background no further than 100,000 characters past the
    // visible part, and a tree cut there carries an error node at the cut:
    // a valid line of a larger file was marked, and nothing after it was
    // checked. The rest is parsed here in slices of 50 ms with a pause
    // between them, so a 2 MB file does not freeze the page; the parse goes
    // on from where the last slice stopped, and an edit reuses the tree
    // outside the changed part. A result for a text that has changed since
    // is dropped (by the lint extension as well).
    //
    // `alive()` says whether this linter is still the editor's current one.
    // When it was replaced (setLanguage, setLint) or the editor was closed,
    // the run stops at its next slice and never delivers: the lint extension
    // applies any result whose document is unchanged, and adds its state
    // back when the linter was switched off, so a late result would leave
    // marks that no linter clears.
    function _syntaxLinter(cm, lang, labels, alive) {
        if (lang === 'json') {
            var json = cm.langJson.jsonParseLinter();
            return cm.lint.linter(function (view) {
                return /\S/.test(view.state.doc.toString()) ? json(view) : [];
            });
        }
        var message = (labels && labels.syntaxError) ? String(labels.syntaxError) : 'Syntax error';
        return cm.lint.linter(function (view) {
            var state = view.state, len = state.doc.length;
            if (len === 0) return [];
            return new Promise(function (resolve) {
                (function slice() {
                    if (alive && !alive()) return;
                    if (view.state.doc !== state.doc) { resolve([]); return; }
                    var tree = cm.language.ensureSyntaxTree(state, len, 50);
                    if (!tree) { setTimeout(slice, 0); return; }
                    var out = [];
                    tree.iterate({
                        enter: function (node) {
                            if (out.length >= 200) return false;
                            if (!node.type.isError) return;
                            var from = node.from, to = node.to;
                            if (from === to) { to = Math.min(len, from + 1); }
                            out.push({ from: from, to: to, severity: 'error', message: message });
                        }
                    });
                    resolve(out);
                })();
            });
        }, { delay: 500 });
    }

    // ── Tracked ranges and locks ─────────────────────────────────────────
    // Both are range sets the engine maps through every change. A range that
    // "grows" keeps text typed at either edge (an island, a text run); one
    // that does not (a tag, a lock) leaves such text outside.
    function _rangeClasses(cm) {
        if (cm._pgRanges) return cm._pgRanges;
        var RangeValue = cm.state.RangeValue;

        function Tracked(id, data, grow, cls) {
            this.id = id;
            this.data = data;
            this.cls = cls || '';
            this.startSide = grow ? -1 : 1;
            this.endSide = grow ? 1 : -1;
        }
        Tracked.prototype = Object.create(RangeValue.prototype);
        Tracked.prototype.constructor = Tracked;
        Tracked.prototype.eq = function (other) {
            return other instanceof Tracked && other.id === this.id && other.cls === this.cls
                && other.startSide === this.startSide && other.endSide === this.endSide;
        };

        function Lock(kind, tag) {
            this.kind = kind;            // 'lock' | 'allow'
            this.tag = tag || '';        // caller's label for the lock (e.g. 'head', 'widget')
            this.startSide = (kind === 'allow') ? -1 : 1;
            this.endSide = (kind === 'allow') ? 1 : -1;
        }
        Lock.prototype = Object.create(RangeValue.prototype);
        Lock.prototype.constructor = Lock;
        Lock.prototype.eq = function (other) {
            return other instanceof Lock && other.kind === this.kind && other.tag === this.tag;
        };

        cm._pgRanges = { Tracked: Tracked, Lock: Lock };
        return cm._pgRanges;
    }

    function _buildRangeSet(cm, ranges, make) {
        var list = [];
        for (var i = 0; i < ranges.length; i++) {
            var r = ranges[i];
            var from = Math.max(0, r.from | 0), to = Math.max(from, r.to | 0);
            var value = make(r);
            if (!value) continue;
            list.push(value.range(from, to));
        }
        return cm.state.RangeSet.of(list, true);
    }

    function _rangeFields(cm) {
        if (cm._pgFields) return cm._pgFields;
        var classes = _rangeClasses(cm);
        var StateField = cm.state.StateField, StateEffect = cm.state.StateEffect, RangeSet = cm.state.RangeSet;
        var Decoration = cm.view.Decoration, EditorView = cm.view.EditorView;

        var setTracked = StateEffect.define({ map: function (v, m) { return v; } });
        var trackedField = StateField.define({
            create: function () { return RangeSet.empty; },
            update: function (set, tr) {
                for (var i = 0; i < tr.effects.length; i++) {
                    if (tr.effects[i].is(setTracked)) return tr.effects[i].value;
                }
                return tr.docChanged ? set.map(tr.changes) : set;
            }
        });

        var setLocks = StateEffect.define({ map: function (v, m) { return v; } });
        var lockField = StateField.define({
            create: function () { return RangeSet.empty; },
            update: function (set, tr) {
                for (var i = 0; i < tr.effects.length; i++) {
                    if (tr.effects[i].is(setLocks)) return tr.effects[i].value;
                }
                return tr.docChanged ? set.map(tr.changes) : set;
            }
        });

        // The tint: the locked text minus the islands, as flat segments.
        // Nested locks (a widget inside a locked block) would otherwise
        // nest their spans and darken with every level, and an island's
        // span would sit inside its lock's and never clear the tint.
        var lockMark = Decoration.mark({ class: 'pg-ce-lock' });
        var lockDecorations = EditorView.decorations.compute([lockField], function (state) {
            var set = state.field(lockField);
            var locks = [], allows = [];
            set.between(0, state.doc.length, function (from, to, value) {
                if (from < to) (value.kind === 'allow' ? allows : locks).push([from, to]);
            });
            var segments = _subtractRanges(_unionRanges(locks), _unionRanges(allows));
            return Decoration.set(segments.map(function (r) { return lockMark.range(r[0], r[1]); }), true);
        });

        var trackDecorations = EditorView.decorations.compute([trackedField], function (state) {
            var set = state.field(trackedField);
            var out = [];
            set.between(0, state.doc.length, function (from, to, value) {
                if (from >= to || !value.cls) return;
                out.push(Decoration.mark({ class: value.cls }).range(from, to));
            });
            return Decoration.set(out, true);
        });

        cm._pgFields = {
            setTracked: setTracked, trackedField: trackedField,
            setLocks: setLocks, lockField: lockField,
            decorations: [lockDecorations, trackDecorations],
            classes: classes
        };
        return cm._pgFields;
    }

    // Sorted, merged [from, to] pairs.
    function _unionRanges(list) {
        if (!list.length) return [];
        list.sort(function (a, b) { return a[0] - b[0] || a[1] - b[1]; });
        var out = [list[0].slice()];
        for (var i = 1; i < list.length; i++) {
            var last = out[out.length - 1];
            if (list[i][0] <= last[1]) { if (list[i][1] > last[1]) last[1] = list[i][1]; }
            else out.push(list[i].slice());
        }
        return out;
    }

    // `base` minus `holes`, both merged and sorted.
    function _subtractRanges(base, holes) {
        var out = [], h = 0;
        for (var i = 0; i < base.length; i++) {
            var from = base[i][0], to = base[i][1];
            while (h < holes.length && holes[h][1] <= from) h++;
            for (var k = h; k < holes.length && holes[k][0] < to; k++) {
                if (holes[k][0] > from) out.push([from, holes[k][0]]);
                if (holes[k][1] > from) from = holes[k][1];
            }
            if (from < to) out.push([from, to]);
        }
        return out;
    }

    // ── Lock gutter ──────────────────────────────────────────────────────
    // A marker beside every line a lock reaches: a closed lock when nothing
    // on the line may change, an open one when the line holds an island (an
    // attribute's value, an element's text). The marker's title is the
    // caller's: opts.lockTitle(tag, partly), else labels.locked /
    // labels.lockedPartly.
    var _LOCK_SVG_CLOSED = '<svg viewBox="0 0 16 16" aria-hidden="true"><path d="M5 7V5a3 3 0 0 1 6 0v2" fill="none" stroke="currentColor" stroke-width="1.6"/><rect x="3" y="7" width="10" height="8" rx="1.5" fill="currentColor"/></svg>';
    var _LOCK_SVG_OPEN = '<svg viewBox="0 0 16 16" aria-hidden="true"><path d="M5 7V4.5a3 3 0 0 1 6 0" fill="none" stroke="currentColor" stroke-width="1.6"/><rect x="3" y="7" width="10" height="8" rx="1.5" fill="currentColor"/></svg>';

    function _lockGutter(cm, fields, opts) {
        var V = cm.view;

        function LockMarker(partly, title) {
            this.partly = partly;
            this.title = title;
        }
        LockMarker.prototype = Object.create(V.GutterMarker.prototype);
        LockMarker.prototype.constructor = LockMarker;
        LockMarker.prototype.eq = function (other) {
            return other instanceof LockMarker && other.partly === this.partly && other.title === this.title;
        };
        LockMarker.prototype.toDOM = function () {
            var el = document.createElement('span');
            el.className = 'pg-ce-lock-marker' + (this.partly ? ' pg-ce-lock-marker-partly' : '');
            if (this.title) el.title = this.title;
            el.innerHTML = this.partly ? _LOCK_SVG_OPEN : _LOCK_SVG_CLOSED;
            return el;
        };

        function titleFor(tag, partly) {
            if (typeof opts.lockTitle === 'function') {
                try { var t = opts.lockTitle(tag, partly); if (t) return String(t); } catch (e) {}
            }
            var labels = opts.labels || {};
            return String((partly ? (labels.lockedPartly || labels.locked) : labels.locked) || '');
        }

        return V.gutter({
            class: 'pg-ce-lock-gutter',
            lineMarker: function (view, line) {
                var set = view.state.field(fields.lockField, false);
                if (!set || set === cm.state.RangeSet.empty) return null;
                var locked = false, island = false, tag = '';
                set.between(line.from, line.to, function (from, to, value) {
                    // A range that only touches the line's edge is not on
                    // the line; an empty line is on it when a range spans it.
                    var hits = (line.from === line.to) ? (from <= line.from && to >= line.to) : (from < line.to && to > line.from);
                    if (!hits) return;
                    if (value.kind === 'allow') island = true;
                    else { locked = true; if (!tag) tag = value.tag; }
                });
                return locked ? new LockMarker(island, titleFor(tag, island)) : null;
            },
            lineMarkerChange: function (update) {
                return update.docChanged || update.startState.field(fields.lockField, false) !== update.state.field(fields.lockField, false);
            }
        });
    }

    // ── Active line marker ───────────────────────────────────────────────
    // A gutter that carries one element, made once by the caller
    // (opts.activeLineMarker()), on the line the cursor is on: a button that
    // acts on that line, such as "insert a region here".
    function _activeLineGutter(cm, opts) {
        var V = cm.view;
        var marker = null;

        function LineMarker(el) { this.el = el; }
        LineMarker.prototype = Object.create(V.GutterMarker.prototype);
        LineMarker.prototype.constructor = LineMarker;
        LineMarker.prototype.eq = function (other) { return other === this; };
        LineMarker.prototype.toDOM = function () { return this.el; };

        return V.gutter({
            class: 'pg-ce-line-gutter',
            lineMarker: function (view, line) {
                var head = view.state.selection.main.head;
                if (head < line.from || head > line.to) return null;
                if (!marker) {
                    var el = null;
                    try { el = opts.activeLineMarker(); } catch (e) {}
                    if (!el) return null;
                    marker = new LineMarker(el);
                }
                return marker;
            },
            lineMarkerChange: function (update) { return update.selectionSet || update.docChanged; }
        });
    }

    // Does the lock set permit a change of (fromA, toA) in the document the
    // transaction started from? Allowed when no lock is touched, or when an
    // island of the touched lock holds the whole change.
    function _lockVerdict(set, fromA, toA) {
        var locks = [], islands = [];
        var insertion = (fromA === toA);
        set.between(fromA, toA, function (from, to, value) {
            if (value.kind === 'allow') {
                if (from <= fromA && toA <= to) islands.push({ from: from, to: to, tag: value.tag });
                return;
            }
            var touched = insertion ? (from < fromA && fromA < to) : (fromA < to && toA > from);
            if (touched) locks.push({ from: from, to: to, tag: value.tag });
        });
        if (!locks.length) return null;
        if (islands.length) return null;
        return locks[0];
    }

    // A single change that touches a lock is dropped whole. A transaction
    // of several changes (replace all, several cursors) keeps the ones
    // outside the locks: the locked text minus its islands is handed back
    // as the ranges to suppress, so the matches inside a lock stay as they
    // are and the rest are replaced.
    function _lockFilter(cm, fields, inst) {
        return cm.state.EditorState.changeFilter.of(function (tr) {
            if (!tr.docChanged) return true;
            var set = tr.startState.field(fields.lockField, false);
            if (!set || set === cm.state.RangeSet.empty) return true;
            var hit = null, count = 0;
            tr.changes.iterChangedRanges(function (fromA, toA) {
                count++;
                if (!hit) hit = _lockVerdict(set, fromA, toA);
            });
            if (!hit) return true;
            inst._lockHit(hit, tr);
            if (count <= 1) return false;
            var locks = [], allows = [];
            set.between(0, tr.startState.doc.length, function (from, to, value) {
                if (from < to) (value.kind === 'allow' ? allows : locks).push([from, to]);
            });
            var suppressed = [];
            _subtractRanges(_unionRanges(locks), _unionRanges(allows)).forEach(function (r) { suppressed.push(r[0], r[1]); });
            return suppressed.length ? suppressed : false;
        });
    }

    // The text formatted by the bundle's js-beautify with the editor's indent
    // unit, or null when the language has no formatter (HTML: the designer's
    // page view is printed by its emitter already) or the formatter failed.
    // JSON goes through the JavaScript formatter, which keeps its quotes.
    function _formatText(cm, lang, text, unit) {
        var fn = (lang === 'css') ? cm.format.css : ((lang === 'javascript' || lang === 'json') ? cm.format.js : null);
        if (!fn) return null;
        unit = String(unit || '    ');
        var tab = (unit.indexOf('\t') !== -1);
        try {
            return fn(String(text), {
                indent_size: tab ? 1 : unit.length,
                indent_char: tab ? '\t' : ' ',
                indent_with_tabs: tab,
                eol: '\n',
                end_with_newline: /\n$/.test(text),
                preserve_newlines: true,
                max_preserve_newlines: 2
            });
        } catch (e) {
            return null;
        }
    }

    // The minimap is opt-in: the editor's own `minimap` option, else the page
    // default from configure().
    function _minimapOn(opts) {
        return (typeof opts.minimap === 'boolean') ? opts.minimap : _cfg.minimap;
    }

    // The bundle's minimap beside the scroll bar, or nothing. Lines are drawn
    // as blocks (cheaper than characters on a long page) and the viewport
    // overlay shows only while the pointer is over the minimap.
    function _minimapExtension(cm, on) {
        if (!on || !cm.minimap || !cm.minimap.showMinimap) return [];
        return cm.minimap.showMinimap.of({
            create: function () {
                var dom = document.createElement('div');
                dom.className = 'pg-ce-minimap';
                return { dom: dom };
            },
            displayText: 'blocks',
            showOverlay: 'mouse-over'
        });
    }

    // ── Instance ─────────────────────────────────────────────────────────
    function pgCeCreate(host, opts) {
        if (!isLoaded()) throw new Error('PgCodeEditor.create() before the bundle loaded; use PgCodeEditor.open().');
        if (!host || !host.nodeType) throw new Error('PgCodeEditor.create() needs a host element.');
        opts = opts || {};
        var cm = window.PgCM;
        var S = cm.state, V = cm.view, L = cm.language, C = cm.commands;
        var fields = _rangeFields(cm);
        var lang = _normLanguage(opts.language);
        var inst = {};
        var langComp = new S.Compartment(), readOnlyComp = new S.Compartment(), wrapComp = new S.Compartment();
        var completionsComp = new S.Compartment(), lintComp = new S.Compartment(), keymapComp = new S.Compartment();
        var themeComp = new S.Compartment(), minimapComp = new S.Compartment();
        var useVscode = (opts.keymap !== 'default');
        var lockHitAt = 0;
        var destroyed = false;
        // Bumped by every linter built, by setLint() and by destroy(): a lint
        // run still parsing for an older generation stops without a result.
        var lintGen = 0;

        function lintExtension() {
            var gen = ++lintGen;
            if (opts.lint === false) return [];
            return _syntaxLinter(cm, lang, opts.labels, function () {
                return !destroyed && gen === lintGen;
            });
        }

        inst.cm = cm;
        inst.host = host;
        inst.options = opts;
        inst._refused = 0;      // refused changes so far (replaceRange reads it)
        inst._lockHit = function (lock, tr) {
            var now = Date.now();
            inst._refused++;
            if (typeof opts.onLockHit !== 'function') return;
            if (now - lockHitAt < 1200) return;   // one notice per burst of refused keystrokes
            lockHitAt = now;
            try { opts.onLockHit({ from: lock.from, to: lock.to, tag: lock.tag, userEvent: tr.annotation(S.Transaction.userEvent) || '' }, inst); } catch (e) {}
        };

        function keymapExtensions() {
            var list = [V.keymap.of([{ key: 'Shift-Alt-f', run: function () { return inst.format(); } }])];
            if (useVscode) {
                list.push(V.keymap.of(cm.vscodeKeymap));
            } else {
                list.push(V.keymap.of([].concat(
                    cm.autocomplete.closeBracketsKeymap, C.defaultKeymap, cm.search.searchKeymap,
                    C.historyKeymap, L.foldKeymap, cm.autocomplete.completionKeymap, cm.lint.lintKeymap
                )));
            }
            if (opts.indentWithTab !== false) list.push(V.keymap.of([C.indentWithTab]));
            if (Array.isArray(opts.keys) && opts.keys.length) list.push(S.Prec.high(V.keymap.of(opts.keys)));
            return list;
        }

        var phrases = opts.phrases || _cfg.phrases;
        var extensions = [
            V.lineNumbers(),
            V.highlightActiveLineGutter(),
            V.highlightSpecialChars(),
            C.history(),
            L.foldGutter(),
            opts.lockGutter ? _lockGutter(cm, fields, opts) : [],
            (typeof opts.activeLineMarker === 'function') ? _activeLineGutter(cm, opts) : [],
            V.drawSelection(),
            V.dropCursor(),
            S.EditorState.allowMultipleSelections.of(true),
            L.indentOnInput(),
            L.bracketMatching(),
            cm.autocomplete.closeBrackets(),
            cm.autocomplete.autocompletion({
                defaultKeymap: !useVscode,
                activateOnTyping: opts.completeOnTyping !== false,
                override: _completionSlots(cm),
                addToOptions: [_swatchOption()]
            }),
            V.rectangularSelection(),
            V.crosshairCursor(),
            V.highlightActiveLine(),
            cm.search.highlightSelectionMatches(),
            cm.search.search({ top: opts.searchPanel !== 'bottom' }),
            cm.lint.lintGutter(),
            L.indentUnit.of(opts.indentUnit || '    '),
            S.EditorState.tabSize.of(opts.tabSize || 4),
            keymapComp.of(keymapExtensions()),
            langComp.of(_languageExtension(cm, lang)),
            completionsComp.of(_completionExtensions(cm, lang)),
            lintComp.of(lintExtension()),
            // Read-only keeps the content focusable: the cursor, the keyboard
            // and the search panel work, only changes are refused.
            readOnlyComp.of(S.EditorState.readOnly.of(!!opts.readOnly)),
            wrapComp.of(opts.lineWrapping ? V.EditorView.lineWrapping : []),
            minimapComp.of(_minimapExtension(cm, _minimapOn(opts))),
            fields.trackedField,
            fields.lockField,
            fields.decorations,
            _lockFilter(cm, fields, inst),
            themeComp.of(_themeExtensions(cm, opts.theme !== 'light')),
            V.EditorView.updateListener.of(function (update) {
                if (destroyed) return;
                if (update.docChanged && typeof opts.onChange === 'function') opts.onChange(inst, update);
                if (update.selectionSet && typeof opts.onSelection === 'function') opts.onSelection(inst, update);
                if (update.focusChanged) {
                    if (update.view.hasFocus && typeof opts.onFocus === 'function') opts.onFocus(inst);
                    if (!update.view.hasFocus && typeof opts.onBlur === 'function') opts.onBlur(inst);
                }
            })
        ];
        if (phrases && typeof phrases === 'object') extensions.push(S.EditorState.phrases.of(phrases));
        if (opts.placeholder) extensions.push(V.placeholder(String(opts.placeholder)));
        for (var i = 0; i < _extensions.length; i++) {
            var ext = null;
            try { ext = _extensions[i](opts, cm, inst); } catch (e) { ext = null; }
            if (ext) extensions.push(ext);
        }
        if (opts.extensions) extensions.push(opts.extensions);

        var view = new V.EditorView({
            state: S.EditorState.create({ doc: String(opts.value || ''), extensions: extensions }),
            parent: host
        });
        inst.view = view;

        // An Escape the editor used (closing its completion list, its search
        // panel, a full-window view a screen bound to it) ends at the editor.
        // A Bootstrap modal closes on every Escape that reaches it, prevented
        // or not, so the dialog around the editor -- and the code typed into
        // it -- went with the list. An Escape the editor leaves alone still
        // reaches the dialog. The listener sits on the editor's outer box so
        // it also hears the search panel's fields.
        view.dom.addEventListener('keydown', function (event) {
            if ((event.key === 'Escape') && event.defaultPrevented) event.stopPropagation();
        });

        // ── Text ──
        inst.getValue = function () { return view.state.doc.toString(); };
        // Replaces the whole text. `keepHistory` false (the default for a
        // text that comes from outside) leaves the replacement out of the
        // undo history, and the locks pass it through untouched.
        inst.setValue = function (text, keepHistory) {
            text = String(text == null ? '' : text);
            var len = view.state.doc.length;
            if (text === view.state.doc.toString()) return;
            view.dispatch({
                changes: { from: 0, to: len, insert: text },
                filter: false,
                annotations: keepHistory ? [] : [S.Transaction.addToHistory.of(false)]
            });
        };
        // A change at one place, mapped through the locks like a keystroke
        // unless `force` is set. Returns false when a lock refused it.
        inst.replaceRange = function (from, to, text, force) {
            var refused = inst._refused;
            view.dispatch({ changes: { from: from, to: to, insert: String(text == null ? '' : text) }, filter: !force, userEvent: 'input' });
            return inst._refused === refused;
        };
        // A change that comes from outside the editor (the screen's own model
        // changed): applied whole, past the locks, and kept out of the undo
        // history, so Ctrl+Z undoes only what was typed here. The cursor,
        // the scroll position and the folds around it stay where they are.
        inst.applyExternal = function (from, to, text) {
            view.dispatch({
                changes: { from: from, to: to, insert: String(text == null ? '' : text) },
                filter: false,
                annotations: [S.Transaction.addToHistory.of(false)]
            });
        };
        // Formats the whole text (_formatText). Only the part that differs is
        // replaced, in one change through the lock filter: one undo step that
        // onChange hears like typing. The cursor stays before the same
        // non-blank character. False when the language has no formatter, the
        // editor is read-only or a lock covers the part that would change.
        inst.format = function () {
            if (view.state.readOnly) return false;
            var text = view.state.doc.toString();
            var out = _formatText(cm, lang, text, view.state.facet(L.indentUnit));
            if (out === null) return false;
            if (out === text) return true;
            var from = 0, toA = text.length, toB = out.length;
            while (from < toA && from < toB && text.charCodeAt(from) === out.charCodeAt(from)) from++;
            while (toA > from && toB > from && text.charCodeAt(toA - 1) === out.charCodeAt(toB - 1)) { toA--; toB--; }
            if (!inst.canEdit(from, toA)) return false;
            var head = view.state.selection.main.head, seen = 0, at = 0;
            for (var i = 0; i < head; i++) if (!/\s/.test(text.charAt(i))) seen++;
            while (at < out.length && (seen > 0 || /\s/.test(out.charAt(at)))) { if (!/\s/.test(out.charAt(at))) seen--; at++; }
            var refused = inst._refused;
            view.dispatch({
                changes: { from: from, to: toA, insert: out.slice(from, toB) },
                selection: { anchor: Math.min(at, out.length) },
                userEvent: 'input.format',
                scrollIntoView: true
            });
            return inst._refused === refused;
        };
        // The engine's syntax tree of the whole document (parsed to the end
        // when it is not yet), for callers that read the structure back.
        inst.syntaxTree = function () {
            var len = view.state.doc.length;
            return L.ensureSyntaxTree(view.state, len, 2000) || L.syntaxTree(view.state);
        };
        inst.sliceDoc = function (from, to) { return view.state.sliceDoc(from, to); };
        inst.docLength = function () { return view.state.doc.length; };
        inst.getLanguage = function () { return lang; };
        inst.setLanguage = function (next) {
            next = _normLanguage(next);
            if (next === lang) return;
            lang = next;
            opts.language = lang;
            view.dispatch({ effects: [
                langComp.reconfigure(_languageExtension(cm, lang)),
                completionsComp.reconfigure(_completionExtensions(cm, lang)),
                lintComp.reconfigure(lintExtension())
            ] });
        };
        inst.refreshCompletions = function () {
            view.dispatch({ effects: completionsComp.reconfigure(_completionExtensions(cm, lang)) });
        };
        // Syntax errors marked or not, for a field whose language can be
        // plain text (a file whose type is chosen after it is opened).
        inst.setLint = function (on) {
            opts.lint = !!on;
            view.dispatch({ effects: lintComp.reconfigure(lintExtension()) });
        };
        inst.setReadOnly = function (on) {
            view.dispatch({ effects: readOnlyComp.reconfigure(S.EditorState.readOnly.of(!!on)) });
        };
        inst.isReadOnly = function () { return view.state.readOnly; };
        inst.setLineWrapping = function (on) {
            view.dispatch({ effects: wrapComp.reconfigure(on ? V.EditorView.lineWrapping : []) });
        };
        inst.setMinimap = function (on) {
            opts.minimap = !!on;
            view.dispatch({ effects: minimapComp.reconfigure(_minimapExtension(cm, opts.minimap)) });
        };
        // 'dark' | 'light', while the editor is open: the panel's colour
        // scheme switch pushes it to every editor on the page.
        inst.setTheme = function (name) {
            opts.theme = (name === 'light') ? 'light' : 'dark';
            view.dispatch({ effects: themeComp.reconfigure(_themeExtensions(cm, opts.theme !== 'light')) });
        };
        inst.setKeymap = function (name) {
            useVscode = (name !== 'default');
            view.dispatch({ effects: keymapComp.reconfigure(keymapExtensions()) });
        };

        // ── Cursor, selection, scrolling ──
        inst.focus = function () { view.focus(); };
        inst.hasFocus = function () { return view.hasFocus; };
        // Text typed in at the cursor by a button: replaces the selection,
        // leaves the cursor after it, and is undoable like typing.
        inst.insertAtCursor = function (text) {
            text = String(text == null ? '' : text);
            var r = view.state.selection.main;
            view.dispatch({
                changes: { from: r.from, to: r.to, insert: text },
                selection: { anchor: r.from + text.length },
                userEvent: 'input',
                scrollIntoView: true
            });
            view.focus();
        };
        inst.selection = function () {
            var r = view.state.selection.main;
            return { from: r.from, to: r.to, anchor: r.anchor, head: r.head };
        };
        // Moves the cursor (or selects `from`-`to`) and brings it into view.
        inst.goTo = function (from, to, how) {
            var len = view.state.doc.length;
            from = Math.max(0, Math.min(len, from | 0));
            to = (typeof to === 'number') ? Math.max(0, Math.min(len, to | 0)) : from;
            var spec = { selection: { anchor: from, head: to }, scrollIntoView: false };
            spec.effects = V.EditorView.scrollIntoView(from, { y: (how && how.y) || 'center', yMargin: 24 });
            view.dispatch(spec);
            if (how && how.focus) view.focus();
        };
        inst.scrollTo = function (pos, how) {
            view.dispatch({ effects: V.EditorView.scrollIntoView(Math.max(0, Math.min(view.state.doc.length, pos | 0)), { y: (how && how.y) || 'nearest', yMargin: 24 }) });
        };
        inst.lineAt = function (pos) {
            var l = view.state.doc.lineAt(Math.max(0, Math.min(view.state.doc.length, pos | 0)));
            return { number: l.number, from: l.from, to: l.to, text: l.text };
        };
        inst.posAtCoords = function (x, y) { return view.posAtCoords({ x: x, y: y }); };

        // ── Search ──
        inst.openSearch = function (query) {
            cm.search.openSearchPanel(view);
            if (query !== undefined) inst.setSearchQuery(query);
        };
        inst.closeSearch = function () { cm.search.closeSearchPanel(view); };
        inst.setSearchQuery = function (query) {
            var q = (query && typeof query === 'object') ? query : { search: String(query == null ? '' : query) };
            view.dispatch({ effects: cm.search.setSearchQuery.of(new cm.search.SearchQuery({
                search: q.search || '', caseSensitive: !!q.caseSensitive, regexp: !!q.regexp,
                wholeWord: !!q.wholeWord, replace: q.replace || ''
            })) });
        };
        inst.findNext = function () { return cm.search.findNext(view); };
        inst.findPrevious = function () { return cm.search.findPrevious(view); };
        inst.replaceNext = function () { return cm.search.replaceNext(view); };
        inst.replaceAll = function () { return cm.search.replaceAll(view); };
        inst.gotoLine = function () { return cm.search.gotoLine(view); };

        // ── Tracked ranges ──
        // track([{ id, from, to, data, grow, cls }]) replaces the set. The
        // ranges then follow the text; tracked(id) says where one is now,
        // trackedAt(pos) which ones hold a position, innermost first.
        var trackedCache = null, trackedCacheState = null;
        inst.track = function (ranges) {
            var set = _buildRangeSet(cm, ranges || [], function (r) {
                return new fields.classes.Tracked(String(r.id), r.data, !!r.grow, r.cls);
            });
            view.dispatch({ effects: fields.setTracked.of(set) });
        };
        inst.clearTracked = function () { inst.track([]); };
        function trackedMap() {
            if (trackedCache && trackedCacheState === view.state) return trackedCache;
            var map = {};
            view.state.field(fields.trackedField).between(0, view.state.doc.length, function (from, to, value) {
                if (map[value.id] === undefined) {
                    map[value.id] = { id: value.id, from: from, to: to, data: value.data, grow: value.startSide < 0, cls: value.cls };
                }
            });
            trackedCache = map;
            trackedCacheState = view.state;
            return map;
        }
        inst.tracked = function (id) { var m = trackedMap(); return m[String(id)] || null; };
        inst.allTracked = function () { var m = trackedMap(), out = []; for (var k in m) if (Object.prototype.hasOwnProperty.call(m, k)) out.push(m[k]); return out; };
        inst.trackedAt = function (pos) {
            var out = [];
            view.state.field(fields.trackedField).between(pos, pos, function (from, to, value) {
                if (from <= pos && pos <= to) out.push({ id: value.id, from: from, to: to, data: value.data });
            });
            out.sort(function (a, b) { return (a.to - a.from) - (b.to - b.from); });
            return out;
        };

        // ── Locks ──
        // lock([{ from, to, tag, allow: [[from, to], …] }]) replaces the set.
        inst.lock = function (locks) {
            var ranges = [];
            (locks || []).forEach(function (l) {
                ranges.push({ from: l.from, to: l.to, kind: 'lock', tag: l.tag || '' });
                (l.allow || []).forEach(function (a) {
                    var from = Array.isArray(a) ? a[0] : a.from, to = Array.isArray(a) ? a[1] : a.to;
                    ranges.push({ from: from, to: to, kind: 'allow', tag: l.tag || '' });
                });
            });
            var set = _buildRangeSet(cm, ranges, function (r) { return new fields.classes.Lock(r.kind, r.tag); });
            view.dispatch({ effects: fields.setLocks.of(set) });
        };
        inst.unlock = function () { inst.lock([]); };
        // The lock holding a position, or null when it is free to edit.
        inst.lockAt = function (pos) {
            var set = view.state.field(fields.lockField);
            return _lockVerdict(set, pos, pos);
        };
        inst.canEdit = function (from, to) {
            var set = view.state.field(fields.lockField);
            return _lockVerdict(set, from, (typeof to === 'number') ? to : from) === null;
        };

        // ── Marks (a highlight that follows the text, e.g. the selected node) ──
        // The set is rebuilt with the other ranges as they are: a range that
        // grows keeps growing (an empty one that did not would be dropped by
        // the first keystroke at its position), and another mark keeps its
        // class.
        inst.mark = function (id, from, to, cls) {
            var all = inst.allTracked().filter(function (r) { return r.id !== String(id); }).map(function (r) {
                return { id: r.id, from: r.from, to: r.to, data: r.data, grow: r.grow, cls: r.cls };
            });
            if (typeof from === 'number') all.push({ id: String(id), from: from, to: to, grow: false, cls: cls || 'pg-ce-mark' });
            inst.track(all);
        };

        inst.destroy = function () {
            if (destroyed) return;
            destroyed = true;
            lintGen++;
            view.destroy();
        };
        inst.isDestroyed = function () { return destroyed; };

        return inst;
    }

    // Every match of a search in a text, without an editor: a screen that
    // searches several editors at once (the designer's file tabs) reads
    // their texts and lists the hits itself. `query` is the search panel's
    // { search, caseSensitive, regexp, wholeWord } or a plain string. A
    // regexp is read per line for ^ and $, as the search panel reads it;
    // an empty match (^, a*) is skipped. A hit is { from, to, text, line
    // (1-based), col, lineFrom, lineText }; at most `limit` hits (1000),
    // `truncated` when more were left out; an invalid pattern gives `error`
    // and no hits.
    function findAll(text, query, limit) {
        text = String(text == null ? '' : text);
        var q = (query && typeof query === 'object') ? query : { search: String(query == null ? '' : query) };
        var out = { matches: [], truncated: false, error: null };
        var search = String(q.search == null ? '' : q.search);
        if (search === '') return out;
        limit = (limit > 0) ? limit : 1000;
        var re;
        try {
            re = new RegExp(q.regexp ? search : search.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'gm' + (q.caseSensitive ? '' : 'i'));
        } catch (e) {
            out.error = e && e.message ? e.message : String(e);
            return out;
        }
        var word = /[\w\u00c0-\uffff]/;
        var line = 1, lineFrom = 0, nl = text.indexOf('\n'), m;
        while ((m = re.exec(text)) !== null) {
            if (m[0] === '') { re.lastIndex++; continue; }
            var from = m.index, to = from + m[0].length;
            if (q.wholeWord && ((from > 0 && word.test(text.charAt(from - 1))) || (to < text.length && word.test(text.charAt(to))))) continue;
            if (out.matches.length >= limit) { out.truncated = true; break; }
            while (nl !== -1 && nl < from) { line++; lineFrom = nl + 1; nl = text.indexOf('\n', lineFrom); }
            out.matches.push({ from: from, to: to, text: m[0], line: line, col: from - lineFrom, lineFrom: lineFrom,
                               lineText: text.slice(lineFrom, nl === -1 ? text.length : nl) });
        }
        return out;
    }

    // load() + create(): the way a screen opens an editor.
    function open(host, opts) {
        return load().then(function () { return pgCeCreate(host, opts); });
    }

    // Like fromTextArea: the editor takes the textarea's place and keeps its
    // value current, so the form that submits the textarea sees every edit.
    // Each write raises an input event on the textarea, as typing into it
    // would, so a screen that watches its form for changes hears this one.
    // Besides create()'s options: `height` and `width` size the host,
    // `className` is added to it, `isolate` builds the editor in a shadow
    // root (below). The instance carries `textarea` and `container`, the
    // host element in the page.
    function attach(textarea, opts) {
        opts = opts || {};
        if (!textarea || textarea.tagName !== 'TEXTAREA') return Promise.reject(new Error('PgCodeEditor.attach() needs a textarea.'));
        var host = document.createElement('div');
        host.className = 'pg-code-editor' + (opts.className ? ' ' + opts.className : '');
        if (opts.height) host.style.height = opts.height;
        if (opts.width) host.style.width = opts.width;
        textarea.parentNode.insertBefore(host, textarea.nextSibling);
        // `isolate` builds the editor inside a shadow root of the host, out of
        // reach of a stylesheet that resets every element of its container
        // (CKEditor's dialogs: .cke_reset_all * flattens the editor's height,
        // font and colours). The engine finds the shadow root by itself and
        // puts its styles there.
        var mount = host;
        if (opts.isolate && host.attachShadow) {
            mount = document.createElement('div');
            mount.style.height = '100%';
            host.attachShadow({ mode: 'open' }).appendChild(mount);
        }
        var userChange = opts.onChange;
        var merged = {};
        for (var k in opts) if (Object.prototype.hasOwnProperty.call(opts, k)) merged[k] = opts[k];
        merged.language = opts.language || textarea.getAttribute('data-language') || 'html';
        merged.readOnly = (opts.readOnly !== undefined) ? opts.readOnly : textarea.readOnly;
        merged.onChange = function (inst, update) {
            textarea.value = inst.getValue();
            try { textarea.dispatchEvent(new Event('input', { bubbles: true })); } catch (e) {}
            if (typeof userChange === 'function') userChange(inst, update);
        };
        // The text is read once the engine is here: what was typed into the
        // textarea while it was on its way is kept.
        return load().then(function () {
            merged.value = textarea.value;
            return pgCeCreate(mount, merged);
        }).then(function (inst) {
            textarea.style.display = 'none';
            inst.textarea = inst.textarea || textarea;
            // The element in the page, where the editor's events arrive
            // (the host itself unless the editor is isolated).
            inst.container = host;
            var destroy = inst.destroy;
            inst.destroy = function () {
                destroy();
                textarea.style.display = '';
                if (host.parentNode) host.parentNode.removeChild(host);
            };
            return inst;
        }, function (err) {
            if (host.parentNode) host.parentNode.removeChild(host);
            throw err;
        });
    }

    window.PgCodeEditor = {
        version: '1',
        configure: configure,
        load: load,
        isLoaded: isLoaded,
        create: pgCeCreate,
        open: open,
        attach: attach,
        addCompletionSource: addCompletionSource,
        addCompletionRanker: addCompletionRanker,
        addExtension: addExtension,
        findAll: findAll,
        normalizeLanguage: _normLanguage
    };
})();
