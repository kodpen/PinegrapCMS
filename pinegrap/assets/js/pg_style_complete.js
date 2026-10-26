/**
 * Pinegrap - Enterprise Website Platform
 *
 * The smart completion inside the designer's code editors: completion
 * sources and rankers for the code editor facade (pg_code_editor.js) that
 * ask the predictor (pg_style_predict.js) about the element around the
 * cursor. Loaded with the predictor by pg_style_suggest.js, which registers
 * the delegates; every function here takes the predictor first.
 *
 *   html(P, opts, context, syntax)       attribute names and values
 *   css(P, opts, context, syntax)        values the engine lacks, var(--…)
 *   htmlRank(P, opts, option, context, info)   attribute names by context
 *   cssRank(P, opts, option, context, info)    properties and values
 *   js(P, opts, context, syntax)         the Bootstrap JavaScript API
 *   tagContext(state, node)              { tag, classes, attrs, styles, parents }
 *   docTokens(text)                      { '--name': value }
 *
 * opts.design(state): the design's part of the predictor's context
 * ({ classes, tokens, ids }); the document's own custom properties are
 * added to its tokens. Nothing here touches the page: the syntax tree and
 * the state are the engine's (Lezer HTML with CSS mounted in <style> and
 * style=""). window.PgStyleComplete in a browser; module.exports under Node.
 * Plain ES5.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function (root) {
    'use strict';

    // How many of the predictor's suggestions move up in the engine's list,
    // and the boost of the first one (the next ones get one less each).
    var RANKED = 20;

    function slice(state, from, to) { return state.sliceDoc(from, to); }

    function child(node, name) { return node ? node.getChild(name) : null; }

    // An attribute's value without its quotes.
    function attrValue(state, attr) {
        var v = child(attr, 'AttributeValue') || child(attr, 'UnquotedAttributeValue');
        if (!v) return '';
        return slice(state, v.from, v.to).replace(/^(["'])([\s\S]*)\1$/, '$2').replace(/^["']/, '');
    }

    // Declarations of a style text: { property: value }.
    function styleMap(text) {
        var out = {};
        String(text || '').split(';').forEach(function (d) {
            var at = d.indexOf(':');
            if (at < 1) return;
            var name = d.slice(0, at).trim().toLowerCase();
            if (name) out[name] = d.slice(at + 1).trim();
        });
        return out;
    }

    // The open (or self-closing) tag a node sits in, or null.
    function tagOf(node) {
        for (var n = node; n; n = n.parent) {
            if (n.name === 'OpenTag' || n.name === 'SelfClosingTag') return n;
            if (n.name === 'Element' || n.name === 'Document') return null;
        }
        return null;
    }

    // The predictor's context of the element whose tag is `tag`: its name,
    // classes, attributes (not class or style), declarations and up to six
    // ancestors nearest first, read from the syntax tree.
    function tagContext(state, tag) {
        var ctx = { tag: '', classes: [], attrs: {}, styles: {}, parents: [] };
        if (!tag) return ctx;
        var name = child(tag, 'TagName');
        ctx.tag = name ? slice(state, name.from, name.to).toLowerCase() : '';
        tag.getChildren('Attribute').forEach(function (a) {
            var an = child(a, 'AttributeName');
            if (!an) return;
            var key = slice(state, an.from, an.to).toLowerCase();
            var val = attrValue(state, a);
            if (key === 'class') ctx.classes = val.split(/\s+/).filter(Boolean);
            else if (key === 'style') ctx.styles = styleMap(val);
            else ctx.attrs[key] = val;
        });
        for (var el = tag.parent ? tag.parent.parent : null; el && ctx.parents.length < 6; el = el.parent) {
            if (el.name !== 'Element') continue;
            var open = child(el, 'OpenTag');
            if (!open) continue;
            var pn = child(open, 'TagName');
            var ptag = pn ? slice(state, pn.from, pn.to).toLowerCase() : '';
            if (ptag === 'body' || ptag === 'html') break;
            var cls = [];
            open.getChildren('Attribute').forEach(function (a) {
                var an = child(a, 'AttributeName');
                if (an && slice(state, an.from, an.to).toLowerCase() === 'class') cls = attrValue(state, a).split(/\s+/).filter(Boolean);
            });
            ctx.parents.push({ tag: ptag, classes: cls });
        }
        return ctx;
    }

    // Custom properties the text declares: { '--name': value }, the last
    // declaration of a name kept.
    function docTokens(text) {
        var out = {}, re = /(--[A-Za-z0-9_-]+)\s*:\s*([^;{}]+)/g, m;
        while ((m = re.exec(String(text || ''))) !== null) out[m[1]] = m[2].trim();
        return out;
    }

    var tokenCache = { doc: null, tokens: {} };
    function withDesign(state, opts, ctx) {
        var design = (opts && typeof opts.design === 'function') ? (opts.design(state) || {}) : {};
        if (tokenCache.doc !== state.doc) tokenCache = { doc: state.doc, tokens: docTokens(state.doc.toString()) };
        var tokens = {}, k;
        for (k in (design.tokens || {})) if (Object.prototype.hasOwnProperty.call(design.tokens, k)) tokens[k] = design.tokens[k];
        for (k in tokenCache.tokens) if (Object.prototype.hasOwnProperty.call(tokenCache.tokens, k)) tokens[k] = tokenCache.tokens[k];
        ctx.design = { classes: design.classes || {}, ids: design.ids || [], tokens: tokens };
        return ctx;
    }

    // ── HTML ─────────────────────────────────────────────────────────────
    // Where the cursor is in a tag: { kind: 'name', from, q, tag } while an
    // attribute name is typed (or after a space in the tag),
    // { kind: 'value', from, q, tag, attr } inside an attribute's value;
    // null elsewhere.
    function htmlSpot(context, node) {
        var state = context.state, pos = context.pos;
        if (node.name === 'AttributeName') {
            return { kind: 'name', from: node.from, q: slice(state, node.from, pos), tag: tagOf(node) };
        }
        if ((node.name === 'OpenTag' || node.name === 'SelfClosingTag') && /\s/.test(slice(state, pos - 1, pos))) {
            return { kind: 'name', from: pos, q: '', tag: node };
        }
        if (node.name === 'AttributeValue' || node.name === 'UnquotedAttributeValue') {
            var an = child(node.parent, 'AttributeName');
            var attr = an ? slice(state, an.from, an.to).toLowerCase() : '';
            var start = node.from + (/^["']/.test(slice(state, node.from, node.from + 1)) ? 1 : 0);
            if (pos < start) return null;
            return { kind: 'value', from: start, q: slice(state, start, pos), tag: tagOf(node), attr: attr };
        }
        return null;
    }

    // Attribute names the predictor ranks for the element, as the engine's
    // own options ({ label, type: 'property' }), so a name both offer is
    // listed once once the ranker has given them the same boost; and the
    // values of an attribute (as the engine's: label, apply, 'constant').
    // class and style have their own sources, an on… handler is script,
    // {{ is a binding.
    function html(P, opts, context, syntax) {
        if (!P) return null;
        var spot = htmlSpot(context, syntax.node);
        if (!spot || !spot.tag) return null;
        if (spot.kind === 'name' && spot.q === '' && !context.explicit && spot.from !== context.pos) return null;
        var ctx = withDesign(context.state, opts, tagContext(context.state, spot.tag));
        ctx.limit = 60;
        var out = [];
        if (spot.kind === 'name') {
            P.attributes(spot.q, ctx).forEach(function (it) {
                if (Object.prototype.hasOwnProperty.call(ctx.attrs, it.label) && it.label !== spot.q) return;
                out.push({ label: it.label, type: 'property' });
            });
            return out.length ? { from: spot.from, options: out, validFor: /^[A-Za-z0-9_:-]*$/ } : null;
        }
        if (spot.attr === 'class' || spot.attr === 'style' || /^on/.test(spot.attr) || /\{\{/.test(spot.q)) return null;
        if (/\s/.test(spot.q)) return null;
        P.attributeValues(spot.attr, spot.q, ctx).forEach(function (it) {
            if (it.label === spot.q) return;
            out.push({ label: it.label, apply: it.label, type: 'constant' });
        });
        return out.length ? { from: spot.from, options: out, validFor: /^[^"'\s]*$/ } : null;
    }

    // The predictor's order as boosts: { label: boost } for its first
    // RANKED suggestions, worked out once per document and position.
    function boosts(list) {
        var map = {};
        list.slice(0, RANKED).forEach(function (it, i) { if (!(it.label in map)) map[it.label] = RANKED - i; });
        return map;
    }
    var rankCache = { key: null, doc: null, map: null };
    function cached(context, kind, make) {
        var key = kind + ':' + context.pos;
        if (rankCache.doc === context.state.doc && rankCache.key === key) return rankCache.map;
        var map = make();
        rankCache = { key: key, doc: context.state.doc, map: map };
        return map;
    }

    function htmlRank(P, opts, option, context, info) {
        if (!P || option.type !== 'property') return 0;
        var map = cached(context, 'html', function () {
            var spot = htmlSpot(context, info.node);
            if (!spot || spot.kind !== 'name' || !spot.tag) return {};
            var ctx = withDesign(context.state, opts, tagContext(context.state, spot.tag));
            ctx.limit = RANKED;
            return boosts(P.attributes(spot.q, ctx));
        });
        return map[option.label] || 0;
    }

    // ── CSS ──────────────────────────────────────────────────────────────
    // Where the cursor is in CSS: inside a declaration block (a <style>
    // rule, a stylesheet's, or a style="" value) the text since the last
    // ';' or '{' says it: { kind: 'property', q } before a colon,
    // { kind: 'value', property, q, from } after it, { kind: 'var', q, from }
    // in var(--…). The element is the tag of a style="" attribute.
    function cssSpot(context, node) {
        var state = context.state, pos = context.pos;
        var box = null;
        for (var n = node; n; n = n.parent) {
            if (n.name === 'Block' || n.name === 'Styles') { box = n; break; }
        }
        if (!box) return null;
        var start = box.from + (box.name === 'Block' ? 1 : 0);
        var text = slice(state, start, pos);
        var cut = Math.max(text.lastIndexOf(';'), text.lastIndexOf('{'), text.lastIndexOf('}'));
        var seg = text.slice(cut + 1);
        var spot = { tag: box.name === 'Styles' ? tagOf(box) : null, styles: styleMap(slice(state, start, box.to)) };
        var v = /var\(\s*(--[A-Za-z0-9_-]*)$/.exec(seg);
        if (v) { spot.kind = 'var'; spot.q = v[1]; spot.from = pos - v[1].length; return spot; }
        var m = /^\s*([-A-Za-z0-9_]+)\s*:([\s\S]*)$/.exec(seg);
        if (!m) {
            var p = /([-A-Za-z0-9_]*)$/.exec(seg);
            spot.kind = 'property';
            spot.q = p ? p[1] : '';
            return spot;
        }
        // Inside an open bracket other than var(): calc(), url()… are left
        // to the engine.
        if ((m[2].match(/\(/g) || []).length > (m[2].match(/\)/g) || []).length) return null;
        var w = /([-#.%A-Za-z0-9_]*)$/.exec(m[2]);
        spot.kind = 'value';
        spot.property = m[1].toLowerCase();
        spot.q = w ? w[1] : '';
        spot.from = pos - spot.q.length;
        return spot;
    }

    function cssContext(context, opts, spot) {
        var ctx = withDesign(context.state, opts, tagContext(context.state, spot.tag));
        var k;
        for (k in spot.styles) if (Object.prototype.hasOwnProperty.call(spot.styles, k)) ctx.styles[k] = spot.styles[k];
        return ctx;
    }

    // A label the engine already offers at a value: a plain keyword or a
    // named colour (it lists every keyword it knows wherever a value is
    // typed). Those are ranked, not added, so they are not listed twice.
    function engineHas(label) { return /^[a-z][a-z-]*$/i.test(label); }

    // What the engine does not offer: a value with a unit, a colour, a
    // var(--token), and in var(--…) the tokens themselves, with the
    // predictor's swatch.
    function css(P, opts, context, syntax) {
        if (!P) return null;
        var spot = cssSpot(context, syntax.node);
        if (!spot || spot.kind === 'property') return null;
        if (spot.kind === 'value' && spot.q === '' && !context.explicit) return null;
        var ctx = cssContext(context, opts, spot);
        ctx.limit = 60;
        var out = [];
        if (spot.kind === 'var') {
            P.tokens(spot.q, ctx).forEach(function (it, i) {
                if (it.label === spot.q) return;
                var o = { label: it.label, type: 'variable', boost: Math.max(0, RANKED - i) };
                if (it.swatch) o.swatch = it.swatch;
                out.push(o);
            });
            return out.length ? { from: spot.from, options: out, validFor: /^--[A-Za-z0-9_-]*$/ } : null;
        }
        P.values(spot.property, spot.q, ctx).forEach(function (it, i) {
            if (it.label === spot.q || engineHas(it.label)) return;
            var o = { label: it.label, type: 'constant', boost: Math.max(0, RANKED - i) };
            if (it.swatch) o.swatch = it.swatch;
            out.push(o);
        });
        return out.length ? { from: spot.from, options: out } : null;
    }

    // The engine's properties ranked by the element's context (what it
    // already sets, its tag and classes), and its keywords at a value by
    // the predictor's values for that property.
    function cssRank(P, opts, option, context, info) {
        if (!P) return 0;
        var map = cached(context, 'css', function () {
            var spot = cssSpot(context, info.node);
            if (!spot || spot.kind === 'var') return { kind: '', map: {} };
            var ctx = cssContext(context, opts, spot);
            ctx.limit = RANKED;
            if (spot.kind === 'property') return { kind: 'property', map: boosts(P.properties(spot.q, ctx)) };
            return { kind: 'value', map: boosts(P.values(spot.property, spot.q, ctx).filter(function (it) { return engineHas(it.label); })) };
        });
        if (map.kind === 'property' && option.type !== 'property') return 0;
        if (map.kind === 'value' && option.type !== 'keyword' && option.type !== 'constant') return 0;
        return (map.map && map.map[option.label]) || 0;
    }

    // ── JavaScript ───────────────────────────────────────────────────────
    // The Bootstrap API where a script or an on…="" handler uses it, read
    // from the text before the cursor (at most JS_WINDOW characters):
    //   bootstrap.Mo|                         the component classes
    //   bootstrap.Modal.get|                  their static members
    //   new bootstrap.Modal(el).|, x.|        an instance's members, x
    //                                         being set from one of those
    //   addEventListener('sh| / .on('sh|      the components' events
    // In a handler the element ranks them (a button in a modal: Modal
    // first); in a <script> nothing does.
    var JS_WINDOW = 20000;

    function jsSpot(text) {
        var m = /\bbootstrap\.([A-Za-z]+)\.([A-Za-z]*)$/.exec(text);
        if (m) return { kind: 'static', cls: m[1], q: m[2] };
        m = /\bbootstrap\.([A-Za-z]*)$/.exec(text);
        if (m) return { kind: 'class', q: m[1], back: 'bootstrap.'.length };
        m = /(?:\bnew\s+bootstrap\.([A-Za-z]+)|\bbootstrap\.([A-Za-z]+)\.get(?:OrCreate)?Instance)\((?:[^()]|\([^()]*\))*\)\s*\.([A-Za-z]*)$/.exec(text);
        if (m) return { kind: 'member', cls: m[1] || m[2], q: m[3] };
        m = /(?:addEventListener|\.on|\.one|\.off)\(\s*(['"`])([A-Za-z.]*)$/.exec(text);
        if (m) return { kind: 'event', q: m[2] };
        m = /\b([A-Za-z_$][\w$]*)\.([A-Za-z]*)$/.exec(text);
        if (m && m[1] !== 'bootstrap') {
            // The last assignment of that name from a Bootstrap instance.
            var re = new RegExp('\\b' + m[1].replace(/\$/g, '\\$') + '\\s*=\\s*(?:new\\s+bootstrap\\.([A-Za-z]+)|bootstrap\\.([A-Za-z]+)\\.get(?:OrCreate)?Instance)\\(', 'g');
            var hit = null, a;
            while ((a = re.exec(text)) !== null) hit = a;
            if (hit) return { kind: 'member', cls: hit[1] || hit[2], q: m[2] };
        }
        return null;
    }

    function js(P, opts, context, syntax) {
        if (!P) return null;
        var pos = context.pos;
        var spot = jsSpot(slice(context.state, Math.max(0, pos - JS_WINDOW), pos));
        if (!spot) return null;
        var tag = tagOf(syntax.node);
        var ctx = tag ? withDesign(context.state, opts, tagContext(context.state, tag)) : {};
        ctx.limit = 60;
        var out = [], from = pos - spot.q.length;
        var add = function (label, type, i) {
            if (label !== spot.q) out.push({ label: label, type: type, boost: Math.max(0, RANKED - i) });
        };
        if (spot.kind === 'class') {
            // The class is offered whole ('bootstrap.Modal'), from the
            // start of 'bootstrap.'.
            from -= spot.back;
            P.jsClasses(spot.q, ctx).forEach(function (it, i) { if (it.label !== 'bootstrap.' + spot.q) out.push({ label: it.label, type: 'class', boost: Math.max(0, RANKED - i) }); });
            return out.length ? { from: from, options: out, validFor: /^bootstrap\.[A-Za-z]*$/ } : null;
        }
        if (spot.kind === 'static' || spot.kind === 'member') {
            P.jsMembers(spot.cls, spot.q, spot.kind === 'static').forEach(function (it, i) { add(it.label, 'method', i); });
        } else {
            P.jsEvents(spot.q, ctx).forEach(function (it, i) { add(it.label, 'constant', i); });
        }
        return out.length ? { from: from, options: out, validFor: /^[A-Za-z.]*$/ } : null;
    }

    var api = {
        js: js,
        jsSpot: jsSpot,
        html: html,
        css: css,
        htmlRank: htmlRank,
        cssRank: cssRank,
        tagContext: tagContext,
        docTokens: docTokens,
        htmlSpot: htmlSpot,
        cssSpot: cssSpot
    };
    root.PgStyleComplete = api;

    if (typeof module === 'object' && module && module.exports) {
        module.exports = api;
    }
})(typeof window !== 'undefined' ? window : this);
