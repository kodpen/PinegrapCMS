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
 *   PgCodeEditor.configure({ bundle: url, phrases: {...} })   once per page
 *   PgCodeEditor.open(host, opts).then(function (ed) { ... })  load + create
 *   PgCodeEditor.attach(textarea, opts)                        like fromTextArea
 *   PgCodeEditor.addCompletionSource(language, source)         registry
 *   PgCodeEditor.addExtension(function (opts) { return ext })  registry
 *
 * An editor instance (see pgCeCreate) offers getValue / setValue,
 * setLanguage, setReadOnly, setTheme, goTo and scrollTo, the search panel, tracked
 * ranges that follow the text as it is edited (track / tracked / trackedAt)
 * and locks: ranges no edit may touch, each with optional "islands" inside
 * it that stay editable. A lock without islands is read-only as a whole; a
 * lock with islands lets only the islands change (an attribute's value, an
 * element's text). The caller decides the ranges; this file only enforces
 * them, through the engine's change filter, and reports a refused edit to
 * onLockHit so the screen can say why.
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
        phrases: null        // engine texts: { 'Find': '…', 'Replace': '…', … }
    };
    var _loading = null;     // Promise while the bundle is on its way
    var _completions = {};   // language → [source]
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
            '.pg-ce-mark-line': { backgroundColor: 'rgba(13,110,253,.10)' }
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

    // The registered completion sources of a language, as language data so
    // they only fire in that language (inside <style> the CSS ones).
    function _completionExtensions(cm, lang) {
        var list = _completions[lang] || [];
        if (!list.length) return [];
        var langObj = _languageObject(cm, lang);
        return list.map(function (source) {
            return langObj.data.of({ autocomplete: function (context) {
                var tree = cm.language.syntaxTree(context.state);
                return source(context, { tree: tree, node: tree.resolveInner(context.pos, -1) });
            } });
        });
    }

    // Syntax errors as diagnostics: the parser leaves an error node where a
    // tag or a block does not close. JSON has a real parser to ask.
    function _syntaxLinter(cm, lang, labels) {
        if (lang === 'json') return cm.lint.linter(cm.langJson.jsonParseLinter());
        var message = (labels && labels.syntaxError) ? String(labels.syntaxError) : 'Syntax error';
        return cm.lint.linter(function (view) {
            var out = [];
            var tree = cm.language.syntaxTree(view.state);
            tree.iterate({
                enter: function (node) {
                    if (!node.type.isError) return;
                    var from = node.from, to = node.to;
                    if (from === to) { to = Math.min(view.state.doc.length, from + 1); }
                    out.push({ from: from, to: to, severity: 'error', message: message });
                    if (out.length >= 200) return false;
                }
            });
            return out;
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
        var themeComp = new S.Compartment();
        var useVscode = (opts.keymap !== 'default');
        var lockHitAt = 0;
        var destroyed = false;

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
            var list = [];
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
            V.drawSelection(),
            V.dropCursor(),
            S.EditorState.allowMultipleSelections.of(true),
            L.indentOnInput(),
            L.bracketMatching(),
            cm.autocomplete.closeBrackets(),
            cm.autocomplete.autocompletion({ defaultKeymap: !useVscode, activateOnTyping: opts.completeOnTyping !== false }),
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
            lintComp.of(opts.lint === false ? [] : _syntaxLinter(cm, lang, opts.labels)),
            // Read-only keeps the content focusable: the cursor, the keyboard
            // and the search panel work, only changes are refused.
            readOnlyComp.of(S.EditorState.readOnly.of(!!opts.readOnly)),
            wrapComp.of(opts.lineWrapping ? V.EditorView.lineWrapping : []),
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
            view.dispatch({ effects: [
                langComp.reconfigure(_languageExtension(cm, lang)),
                completionsComp.reconfigure(_completionExtensions(cm, lang)),
                lintComp.reconfigure(opts.lint === false ? [] : _syntaxLinter(cm, lang, opts.labels))
            ] });
        };
        inst.refreshCompletions = function () {
            view.dispatch({ effects: completionsComp.reconfigure(_completionExtensions(cm, lang)) });
        };
        inst.setReadOnly = function (on) {
            view.dispatch({ effects: readOnlyComp.reconfigure(S.EditorState.readOnly.of(!!on)) });
        };
        inst.isReadOnly = function () { return view.state.readOnly; };
        inst.setLineWrapping = function (on) {
            view.dispatch({ effects: wrapComp.reconfigure(on ? V.EditorView.lineWrapping : []) });
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
                if (map[value.id] === undefined) map[value.id] = { id: value.id, from: from, to: to, data: value.data };
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
        inst.mark = function (id, from, to, cls) {
            var all = inst.allTracked().filter(function (r) { return r.id !== String(id); }).map(function (r) {
                return { id: r.id, from: r.from, to: r.to, data: r.data, grow: false, cls: '' };
            });
            if (typeof from === 'number') all.push({ id: String(id), from: from, to: to, grow: false, cls: cls || 'pg-ce-mark' });
            inst.track(all);
        };

        inst.destroy = function () {
            if (destroyed) return;
            destroyed = true;
            view.destroy();
        };
        inst.isDestroyed = function () { return destroyed; };

        return inst;
    }

    // load() + create(): the way a screen opens an editor.
    function open(host, opts) {
        return load().then(function () { return pgCeCreate(host, opts); });
    }

    // Like fromTextArea: the editor takes the textarea's place and keeps its
    // value current, so the form that submits the textarea sees every edit.
    function attach(textarea, opts) {
        opts = opts || {};
        if (!textarea || textarea.tagName !== 'TEXTAREA') return Promise.reject(new Error('PgCodeEditor.attach() needs a textarea.'));
        var host = document.createElement('div');
        host.className = 'pg-code-editor' + (opts.className ? ' ' + opts.className : '');
        if (opts.height) host.style.height = opts.height;
        textarea.parentNode.insertBefore(host, textarea.nextSibling);
        var userChange = opts.onChange;
        var merged = {};
        for (var k in opts) if (Object.prototype.hasOwnProperty.call(opts, k)) merged[k] = opts[k];
        merged.value = textarea.value;
        merged.language = opts.language || textarea.getAttribute('data-language') || 'html';
        merged.readOnly = (opts.readOnly !== undefined) ? opts.readOnly : textarea.readOnly;
        merged.onChange = function (inst, update) {
            textarea.value = inst.getValue();
            if (typeof userChange === 'function') userChange(inst, update);
        };
        return open(host, merged).then(function (inst) {
            textarea.style.display = 'none';
            inst.textarea = inst.textarea || textarea;
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
        addExtension: addExtension,
        normalizeLanguage: _normLanguage
    };
})();
