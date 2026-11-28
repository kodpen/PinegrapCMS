/**
 * Pinegrap - Enterprise Website Platform
 *
 * Per-place values of a shared component, the browser twin of
 * includes/fn/component_overrides.php (window.PGComponentOverrides). A node
 * of a component's tree may open some of its fields to the places that use
 * the component: `props._open` is a list of {id, kind, label}. A placing
 * shared_ref node keeps its own values in `props._place`, an object keyed by
 * the open field's stable id. Pure: it reads what it is handed; apply()
 * answers a copy and never changes the tree it is given.
 *
 * - KINDS: text (props.text), picture (props.src, props.alt), link
 *   (props.href), icon (props.iconName), shown (false leaves the node out).
 * - openFields(tree): [{id, kind, label, nodeId, value}] in document order,
 *   value being the component's own; a placed component inside is not
 *   entered; an id met twice keeps its first node.
 * - apply(tree, values): the tree as drawn at a place; with nothing to
 *   apply, the same tree object comes back.
 * - leftovers(tree, values): ids of values no open field takes any more.
 * - safeValues(tree, values): the values as they may be drawn (a link or
 *   picture that would run a script left out, a text's markers inert).
 * - newFieldId(taken), validFieldId(id), cleanValue(kind, value),
 *   placeValues(refNode), addressRunsScript(address, picture).
 * - variants(tree), variant(tree, name), variantChanges(v),
 *   placeVariant(refNode), applyVariant(tree, name) (the same object when
 *   nothing applies), variantNodes(default, edited), cleanVariantName(),
 *   variantPropOk(key): named sets of classes and options (root
 *   props._variants), a place keeping only the name (props._variant); a
 *   renamed variant keeps its former names (`was`) and answers to them.
 *
 * Nothing runs at load but the definition. tests/component_overrides_test.php
 * runs it in Node beside the PHP functions.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */
(function () {
    'use strict';

    var KINDS = {
        text: ['text'],
        picture: ['src', 'alt'],
        link: ['href'],
        icon: ['iconName'],
        shown: []
    };

    function has(obj, key) {
        return Object.prototype.hasOwnProperty.call(obj, key);
    }

    function isObject(v) {
        return v !== null && typeof v === 'object' && !Array.isArray(v);
    }

    function validFieldId(id) {
        return typeof id === 'string' && /^[a-z][a-z0-9_]{0,39}$/.test(id);
    }

    // Random so that a field opened after another was closed never inherits
    // the old field's values.
    function newFieldId(taken) {
        var chars = 'abcdefghijklmnopqrstuvwxyz0123456789', seen = {}, id, i;
        (taken || []).forEach(function (t) { seen[String(t)] = true; });
        do {
            id = 'of_';
            for (i = 0; i < 8; i++) {
                id += chars.charAt(Math.floor(Math.random() * 36));
            }
        } while (has(seen, id));
        return id;
    }

    function nodeOpen(node) {
        var list = isObject(node) && isObject(node.props) ? node.props._open : null, out = [];
        if (!Array.isArray(list)) return out;
        list.forEach(function (entry) {
            if (!isObject(entry) || !validFieldId(entry.id)) return;
            if (typeof entry.kind !== 'string' || !has(KINDS, entry.kind)) return;
            out.push({
                id: entry.id,
                kind: entry.kind,
                label: typeof entry.label === 'string' ? entry.label.trim() : ''
            });
        });
        return out;
    }

    function defaultValue(node, kind) {
        var props = isObject(node.props) ? node.props : {};
        function str(key) {
            var v = props[key];
            return (typeof v === 'string' || typeof v === 'number') ? String(v) : '';
        }
        switch (kind) {
            case 'picture': return {src: str('src'), alt: str('alt')};
            case 'shown': return true;
            case 'text': return str('text');
            case 'link': return str('href');
            case 'icon': return str('iconName');
        }
        return null;
    }

    function openFields(tree) {
        var fields = [], seen = {};
        (function walk(node) {
            if (!isObject(node)) return;
            nodeOpen(node).forEach(function (entry) {
                if (has(seen, entry.id)) return;
                seen[entry.id] = true;
                fields.push({
                    id: entry.id,
                    kind: entry.kind,
                    label: entry.label,
                    nodeId: nodeId(node),
                    value: defaultValue(node, entry.kind)
                });
            });
            if (node.type === 'shared_ref') return;
            if (Array.isArray(node.children)) node.children.forEach(walk);
        })(tree);
        return orderFields(fields, isObject(tree) && isObject(tree.props) ? tree.props._openOrder : null);
    }

    // A node's id: the designer's `_id`, else `id`, else ''.
    function nodeId(node) {
        var keys = ['_id', 'id'];
        for (var i = 0; i < keys.length; i++) {
            var v = node[keys[i]];
            if (typeof v === 'string' || (typeof v === 'number' && Math.floor(v) === v)) return String(v);
        }
        return '';
    }

    // The fields in the stored order: ids in `order` first, then the rest
    // as given; entries naming no field are skipped.
    function orderFields(fields, order) {
        if (!Array.isArray(order) || !order.length || !fields.length) return fields;
        var byId = {}, used = {}, out = [];
        fields.forEach(function (f, i) { byId[f.id] = i; });
        order.forEach(function (id) {
            if (typeof id !== 'string' || !has(byId, id) || has(used, id)) return;
            used[id] = true;
            out.push(fields[byId[id]]);
        });
        fields.forEach(function (f) { if (!has(used, f.id)) out.push(f); });
        return out;
    }

    function cleanValue(kind, value) {
        switch (kind) {
            case 'text':
            case 'link':
            case 'icon':
                return typeof value === 'string' ? value : null;
            case 'shown':
                return typeof value === 'boolean' ? value : null;
            case 'picture':
                if (typeof value === 'string') return {src: value, alt: null};
                if (!isObject(value) || typeof value.src !== 'string') return null;
                return {src: value.src, alt: typeof value.alt === 'string' ? value.alt : null};
        }
        return null;
    }

    function placeValues(ref) {
        return isObject(ref) && isObject(ref.props) && isObject(ref.props._place) ? ref.props._place : {};
    }

    function apply(tree, values) {
        if (!isObject(tree) || !isObject(values) || !Object.keys(values).length) return tree;
        var fields = openFields(tree), usable = {}, any = false, seen = {};
        fields.forEach(function (field) {
            if (!has(values, field.id)) return;
            var clean = cleanValue(field.kind, values[field.id]);
            if (clean !== null) { usable[field.id] = clean; any = true; }
        });
        if (!any) return tree;

        // A node left out takes its own ids and those of its subtree with
        // it, as openFields() gives them to it: a later node with the same id
        // (a duplicated element) never takes their values.
        function claim(node) {
            if (!isObject(node)) return;
            nodeOpen(node).forEach(function (entry) { seen[entry.id] = true; });
            if (node.type === 'shared_ref') return;
            if (Array.isArray(node.children)) node.children.forEach(claim);
        }

        // Answers the node with its values, or null when it is not shown.
        // The root itself is never left out.
        function walk(node, root) {
            if (!isObject(node)) return node;
            var entries = nodeOpen(node), i, entry, value;
            for (i = 0; i < entries.length; i++) {
                entry = entries[i];
                if (has(seen, entry.id)) continue;
                seen[entry.id] = true;
                if (!has(usable, entry.id)) continue;
                value = usable[entry.id];
                switch (entry.kind) {
                    case 'shown':
                        if (value === false && !root) {
                            claim(node);
                            return null;
                        }
                        break;
                    case 'text':
                        node.props.text = value;
                        break;
                    case 'link':
                        node.props.href = value;
                        break;
                    case 'icon':
                        node.props.iconName = value;
                        break;
                    case 'picture':
                        node.props.src = value.src;
                        if (value.alt !== null) node.props.alt = value.alt;
                        break;
                }
            }
            if (node.type === 'shared_ref') return node;
            if (Array.isArray(node.children)) {
                node.children = node.children.map(function (child) {
                    return walk(child, false);
                }).filter(function (child) {
                    return child !== null;
                });
            }
            return node;
        }

        return walk(JSON.parse(JSON.stringify(tree)), true);
    }

    function leftovers(tree, values) {
        if (!isObject(values)) return [];
        var kinds = {};
        openFields(tree).forEach(function (field) { kinds[field.id] = field.kind; });
        return Object.keys(values).filter(function (id) {
            return !has(kinds, id) || cleanValue(kinds[id], values[id]) === null;
        });
    }

    // Whether an address would run a script where a link or a picture
    // prints it (javascript:, vbscript:, livescript:, and data: but for a
    // picture's data:image/).
    function addressRunsScript(address, picture) {
        var el = typeof document !== 'undefined' ? document.createElement('textarea') : null, raw = String(address);
        if (el) { el.innerHTML = raw.replace(/</g, '&lt;'); raw = el.value; }
        raw = raw.replace(/[\x00-\x20]+/g, '').toLowerCase();
        if (/^(?:javascript|vbscript|livescript):/.test(raw)) return true;
        if (raw.indexOf('data:') === 0) return !(picture && raw.indexOf('data:image/') === 0);
        return false;
    }

    // A place's values as they may be drawn: the ones an open field takes
    // and that fit, a link or picture that would run a script left out,
    // a text's render markers and PHP open tags made inert.
    function safeValues(tree, values) {
        var out = {};
        if (!isObject(values)) return out;
        openFields(tree).forEach(function (field) {
            if (!has(values, field.id) || has(out, field.id)) return;
            var value = cleanValue(field.kind, values[field.id]);
            if (value === null) return;
            if (field.kind === 'link' && addressRunsScript(value, false)) return;
            if (field.kind === 'picture' && addressRunsScript(value.src, true)) return;
            if (field.kind === 'text') value = value.replace(/<!--pg-/gi, '<!-- pg-').split('<?').join('&lt;?');
            out[field.id] = value;
        });
        return out;
    }

    // ── Variants ──────────────────────────────────────────────────────────
    // The root's props._variants: [{name, nodes}] in the order added, nodes
    // mapping a node's id to the props the variant gives it; a place keeps
    // only the name (props._variant). Content stays the component's.
    var CONTENT_PROPS = {
        text: 1, html: 1, content: 1, code: 1, src: 1, srcset: 1, alt: 1, href: 1,
        iconName: 1, sharedId: 1, sharedName: 1, placement: 1
    };

    function cleanVariantName(name) {
        if (typeof name !== 'string') return '';
        name = name.replace(/\s+/g, ' ').trim();
        return Array.from(name).slice(0, 60).join('');
    }

    function variantPropOk(key) {
        return typeof key === 'string' && key !== '' && key.charAt(0) !== '_' && !has(CONTENT_PROPS, key);
    }

    function cleanVariantProps(props) {
        var out = {};
        if (!isObject(props)) return out;
        Object.keys(props).forEach(function (key) {
            var v = props[key];
            if (variantPropOk(key) && (typeof v === 'string' || typeof v === 'boolean' || (typeof v === 'number' && isFinite(v)))) out[key] = v;
        });
        return out;
    }

    // Cleaned as pg_co_variants(): a renamed variant keeps `was`, its
    // former names (at most 20), never a name a variant has now or one an
    // earlier variant keeps; none leaves no `was`.
    function variants(tree) {
        var list = tree && tree.props && tree.props._variants, out = [], names = {}, formers = [];
        if (!Array.isArray(list)) return out;
        for (var i = 0; i < list.length && out.length < 50; i++) {
            var entry = list[i];
            if (!isObject(entry)) continue;
            var name = cleanVariantName(entry.name), key = name.toLowerCase();
            if (name === '' || has(names, key)) continue;
            names[key] = true;
            var nodes = {};
            if (isObject(entry.nodes)) {
                Object.keys(entry.nodes).forEach(function (id) {
                    var p = cleanVariantProps(entry.nodes[id]);
                    if (Object.keys(p).length) nodes[id] = p;
                });
            }
            out.push({name: name, nodes: nodes});
            formers.push(Array.isArray(entry.was) ? entry.was : []);
        }
        var claimed = {};
        Object.keys(names).forEach(function (k) { claimed[k] = true; });
        out.forEach(function (v, n) {
            var was = [];
            for (var j = 0; j < formers[n].length && was.length < 20; j++) {
                var former = cleanVariantName(formers[n][j]), fk = former.toLowerCase();
                if (former === '' || has(claimed, fk)) continue;
                claimed[fk] = true;
                was.push(former);
            }
            if (was.length) v.was = was;
        });
        return out;
    }

    // A variant's name now first, then a former name of a renamed one, then
    // a name now in any case (a rename that changed only the case).
    function variant(tree, name) {
        name = cleanVariantName(name);
        if (name === '') return null;
        var list = variants(tree), i, key = name.toLowerCase();
        for (i = 0; i < list.length; i++) { if (list[i].name === name) return list[i]; }
        for (i = 0; i < list.length; i++) { if (list[i].was && list[i].was.indexOf(name) !== -1) return list[i]; }
        for (i = 0; i < list.length; i++) { if (list[i].name.toLowerCase() === key) return list[i]; }
        return null;
    }

    function variantChanges(v) {
        var n = 0;
        if (v && isObject(v.nodes)) Object.keys(v.nodes).forEach(function (id) { n += Object.keys(v.nodes[id] || {}).length; });
        return n;
    }

    function placeVariant(ref) {
        return ref && ref.props ? cleanVariantName(ref.props._variant) : '';
    }

    // The tree in variant `name`, a copy; an unknown name or an empty
    // variant answers the same tree object.
    function applyVariant(tree, name) {
        var v = tree ? variant(tree, name) : null;
        if (!v || !Object.keys(v.nodes).length) return tree;
        function walk(node, top) {
            if (!node || typeof node !== 'object') return node;
            var copy = {};
            Object.keys(node).forEach(function (k) { copy[k] = node[k]; });
            var id = nodeId(node);
            if (id !== '' && has(v.nodes, id)) {
                copy.props = {};
                if (isObject(node.props)) Object.keys(node.props).forEach(function (k) { copy.props[k] = node.props[k]; });
                Object.keys(v.nodes[id]).forEach(function (k) { copy.props[k] = v.nodes[id][k]; });
            }
            if (!top && node.type === 'shared_ref') return copy;
            if (Array.isArray(node.children)) copy.children = node.children.map(function (c) { return walk(c, false); });
            return copy;
        }
        return walk(tree, true);
    }

    // What a variant keeps after an edit: per node of `edited` that
    // `deflt` has too, the allowed props that differ; a string or switch
    // removed is kept as '' or false.
    function variantNodes(deflt, edited) {
        var before = {}, out = {};
        (function collect(node, top) {
            if (!node || typeof node !== 'object') return;
            var id = nodeId(node);
            if (id !== '') before[id] = isObject(node.props) ? node.props : {};
            if (!top && node.type === 'shared_ref') return;
            (Array.isArray(node.children) ? node.children : []).forEach(function (c) { collect(c, false); });
        })(deflt, true);
        (function diff(node, top) {
            if (!node || typeof node !== 'object') return;
            var id = nodeId(node);
            if (id !== '' && has(before, id)) {
                var oldP = cleanVariantProps(before[id]), newP = cleanVariantProps(node.props), changed = {}, any = false;
                Object.keys(newP).forEach(function (k) {
                    if (!has(oldP, k) || oldP[k] !== newP[k]) { changed[k] = newP[k]; any = true; }
                });
                Object.keys(oldP).forEach(function (k) {
                    if (has(newP, k)) return;
                    if (typeof oldP[k] === 'string' && oldP[k] !== '') { changed[k] = ''; any = true; }
                    else if (oldP[k] === true) { changed[k] = false; any = true; }
                });
                if (any) out[id] = changed;
            }
            if (!top && node.type === 'shared_ref') return;
            (Array.isArray(node.children) ? node.children : []).forEach(function (c) { diff(c, false); });
        })(edited, true);
        return out;
    }

    window.PGComponentOverrides = {
        KINDS: KINDS,
        validFieldId: validFieldId,
        newFieldId: newFieldId,
        openFields: openFields,
        cleanValue: cleanValue,
        placeValues: placeValues,
        apply: apply,
        leftovers: leftovers,
        addressRunsScript: addressRunsScript,
        safeValues: safeValues,
        cleanVariantName: cleanVariantName,
        variantPropOk: variantPropOk,
        variants: variants,
        variant: variant,
        variantChanges: variantChanges,
        placeVariant: placeVariant,
        applyVariant: applyVariant,
        variantNodes: variantNodes
    };
})();
